<?
error_reporting(E_ALL);
ini_set('display_errors', 1);

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("NO_SESSION", true);
define("BX_CRONTAB", true);

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use University\FileAsync\Entity\FileTaskTable;

$root = realpath(__DIR__ . '/../../../../');
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['SITE_DIR'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

function log_msg($msg) {
    $file = $_SERVER['DOCUMENT_ROOT'] . '/worker.log';
    $time = date('H:i:s');
    file_put_contents($file, "[$time] $msg\n", FILE_APPEND);
}

log_msg(" Воркер PID:" . getmypid() . " запущен");

try {
    require $root . '/bitrix/modules/main/include/prolog_before.php';
    //log_msg("Ядро Битрикс загружено.");

    require $root . '/local/modules/university.fileasync/lib/Entity/FileTaskTable.php';
    require $root . '/vendor/autoload.php';
    //log_msg("Библиотеки загружены.");

    $cfg = [
        'host'  => '127.0.0.1', 'port' => 5672,
        'user'  => 'bitrix_dev', 'pass' => 'devpass123',
        'vhost' => '/university', 'queue' => 'file_processing',
    ];

    $connection = new AMQPStreamConnection($cfg['host'], $cfg['port'], $cfg['user'], $cfg['pass'], $cfg['vhost']);
    $channel = $connection->channel();
    $channel->basic_qos(null, 1, null);
    log_msg("RabbitMQ подключен");

    //ОБРАБОТЧИК СООБЩЕНИЙ
    $callback = function($msg) use ($channel) {
        $data = json_decode($msg->body, true);
        $taskId = $data['task_id'] ?? null;

        if (!$taskId) {
            log_msg("Пустой task_id");
            $msg->nack(false, false);
            return;
        }

        log_msg("[PID:" . getmypid() . "] Задача #$taskId");

        try {
            FileTaskTable::update($taskId, [
                'STATUS' => 'processing',
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime()
            ]);

            $task = FileTaskTable::getById($taskId)->fetch();
            if (!$task || !isset($task['FILE_ID'])) {
                throw new \Exception("Задача не найдена или отсутствует FILE_ID");
            }

            $fileId = (int)$task['FILE_ID'];
            $fileData = \CFile::GetByID($fileId)->Fetch();
            
            if (!is_array($fileData)) {
                throw new \Exception("Файл #$fileId не найден в БД");
            }

            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $fileData['SUBDIR'] . '/' . $fileData['FILE_NAME'];
            
            if (!is_file($filePath)) {
                throw new \Exception("Файл отсутствует на диске. Искал по пути: " . $filePath);
            }

            $meta = [];
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = $fileData['CONTENT_TYPE'] ?? 'unknown';
            $sizeKB = round(filesize($filePath) / 1024, 2);
            
            $meta[] = "Format: " . strtoupper($ext);
            $meta[] = "Size: {$sizeKB} KB";

            if (preg_match('/^image\//', $mime)) {
                $img = @getimagesize($filePath);
                if ($img) {
                    $meta[] = "Dimensions: {$img[0]}x{$img[1]} px";
                    $thumb = dirname($filePath) . '/thumb_' . basename($filePath);
                    $resizeMode = defined('BX_RESIZE_IMAGE_PROPORTIONAL') ? BX_RESIZE_IMAGE_PROPORTIONAL : 2;
                    if (@\CFile::ResizeImage($fileId, ['width'=>150, 'height'=>150], $resizeMode, false, $thumb)) {
                        $meta[] = "Thumbnail created";
                    }
                }
            } 
            elseif ($ext === 'pdf') {
                $header = @file_get_contents($filePath, false, null, 0, 5);
                if ($header && strpos($header, '%PDF-') === 0) {
                    $content = @file_get_contents($filePath);
                    preg_match_all('/\/Type\s*\/Page[^s]/', $content, $m);
                    $meta[] = "Pages: " . (count($m[0]) ?: 1);
                } else {
                    throw new \Exception("Invalid PDF header");
                }
            } 
            elseif (in_array($ext, ['docx', 'xlsx'])) {
                if (class_exists('ZipArchive')) {
                    $zip = new \ZipArchive();
                    if ($zip->open($filePath) === true) {
                        if ($ext === 'docx') {
                            $meta[] = "Type: Word Document";
                            $xml = $zip->getFromName('word/document.xml');
                            if ($xml) {
                                preg_match_all('/<w:p\b/', $xml, $p);
                                $meta[] = "Paragraphs: ~" . count($p[0]);
                            }
                        } elseif ($ext === 'xlsx') {
                            $sheets = 0;
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                if (strpos($zip->getNameIndex($i), 'xl/worksheets/sheet') === 0) $sheets++;
                            }
                            $meta[] = "Sheets: $sheets";
                        }
                        $zip->close();
                    } else {
                        throw new \Exception("Corrupt Office archive");
                    }
                } else {
                    throw new \Exception("ZipArchive extension missing");
                }
            } 
            else {
                $meta[] = "Type: Generic file";
            }

            $meta[] = "Processed: " . date('Y-m-d H:i:s');

            FileTaskTable::update($taskId, [
                'STATUS' => 'done',
                'ERROR_MESSAGE' => implode(' | ', $meta),
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime()
            ]);

            log_msg("#$taskId выполнена");
            $msg->ack();

        } catch (\Throwable $e) {
            log_msg("#$taskId ошибка: " . $e->getMessage());
            FileTaskTable::update($taskId, [
                'STATUS' => 'error',
                'ERROR_MESSAGE' => $e->getMessage(),
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime()
            ]);
            $msg->nack(false, false);
        }
    };

    $channel->basic_consume($cfg['queue'], '', false, false, false, false, $callback);

    while ($channel->is_consuming()) {
        $channel->wait();
    }

} catch (\Throwable $e) {
    log_msg("КРИТИЧЕСКАЯ ОШИБКА СКРИПТА: " . $e->getMessage());
    log_msg("В файле: " . $e->getFile() . " (строка " . $e->getLine() . ")");
}