<?php
error_reporting(0);
ini_set('display_errors', 0);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/university.fileasync/lib/Entity/FileTaskTable.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/university.fileasync/lib/Service/RabbitMQPublisher.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';


if (ob_get_level()) ob_end_clean();

use University\FileAsync\Entity\FileTaskTable;
use University\FileAsync\Service\RabbitMQPublisher;

header('Content-Type: application/json; charset=utf-8');


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    echo json_encode(['error' => 'Файл не передан']);
    exit;
}

$fileID = \CFile::SaveFile($_FILES['file'], 'uni_async_files');

if (!$fileID || $fileID <= 0) {
    echo json_encode(['error' => 'Ошибка сохранения файла. Код: ' . ($fileID ?: 'null')]);
    exit;
}


try {
    $taskResult = FileTaskTable::add([
        'FILE_ID' => $fileID,
        'STATUS'  => 'pending'
    ]);

    if ($taskResult->isSuccess()) {
        $taskId = $taskResult->getId();
        
        RabbitMQPublisher::push($taskId);

        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'message' => 'Файл загружен (ID: ' . $fileID . '). Задача #' . $taskId . ' отправлена.'
        ]);
    } else {
        echo json_encode(['error' => 'Ошибка создания задачи: ' . implode(', ', $taskResult->getErrorMessages())]);
    }

} catch (\Throwable $e) {
    echo json_encode([
        'success' => true,
        'file_id' => $fileID,
        'warning' => 'Файл сохранён, но очередь недоступна: ' . $e->getMessage()
    ]);
}