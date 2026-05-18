<?
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/php_error.log');

if (empty($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '/') {
    $_SERVER['DOCUMENT_ROOT'] = 'C:/OSPanel/home/rmq';
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/university.fileasync/lib/Entity/FileTaskTable.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use University\FileAsync\Entity\FileTaskTable;

$APPLICATION->SetTitle("Сервис асинхронной загрузки файлов");
$APPLICATION->ShowHead();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $APPLICATION->GetTitle() ?></title>
    <link href="/local/assets/style.css" type="text/css"  rel="stylesheet" />
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Сервис загрузки файлов</h1>
            <p>Асинхронная обработка с использованием RabbitMQ</p>
        </div>
        
        <div class="upload-section">
            <h2 style="margin-bottom: 20px; color: #333;">Загрузка файла</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Выберите файл для загрузки:</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="file" id="file" required>
                        <span id="fileNameDisplay" class="file-name-display">Файл не выбран</span>
                    </div>
                </div>
                <button type="submit" class="btn">Загрузить и отправить в обработку</button>
            </form>
            <div id="result"></div>
        </div>
        
        <div class="files-section">
            <div class="files-header" id="toggleFiles">
                <span>Загруженные файлы</span>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button type="button" class="btn-secondary" id="refreshBtn" title="Обновить список">Обновить</button>
                    <span class="toggle-icon">▼</span>
                </div>
            </div>
            
            <div class="files-list" id="filesList">
                <?
                $rsTasks = FileTaskTable::getList([
                    'select' => ['ID', 'FILE_ID', 'STATUS', 'ERROR_MESSAGE', 'CREATED_AT'],
                    'order'  => ['ID' => 'DESC']
                ]);
                
                $files = [];
                $hasActive = false;
                while ($task = $rsTasks->fetch()) {
                    $file = \CFile::GetByID($task['FILE_ID'])->Fetch();
                    $task['FILE'] = $file;
                    $files[] = $task;
                    if ($task['STATUS'] !== 'done') $hasActive = true;
                }
                
                if (count($files) > 0): ?>
                    <?foreach ($files as $task): ?>
                        <div class="file-item" id="task-<?= $task['ID'] ?>">
                            <div class="file-info">
                                <div class="file-name">
                                    <?= htmlspecialchars($task['FILE']['ORIGINAL_NAME'] ?? 'Неизвестный файл') ?>
                                </div>
                                <div class="file-meta">
                                    <strong>ID:</strong> <?= $task['ID'] ?> | 
                                    <strong>Тип:</strong> <?= htmlspecialchars(strtoupper(pathinfo($task['FILE']['ORIGINAL_NAME'] ?? '', PATHINFO_EXTENSION)) ?: '—') ?> | 
                                    <strong>Размер:</strong> <?= isset($task['FILE']['FILE_SIZE']) ? round($task['FILE']['FILE_SIZE'] / 1024, 2) . ' KB' : '—' ?> | 
                                    <strong>Загружен:</strong> <?= $task['CREATED_AT'] ? $task['CREATED_AT']->toString() : '—' ?>
                                </div>
                            </div>
                            
                            <span class="file-status status-<?= htmlspecialchars($task['STATUS']) ?>" id="status-<?= $task['ID'] ?>">
                                <?= htmlspecialchars($task['STATUS']) ?>
                            </span>
                            
                            <div class="file-actions">
                                <a href="/download.php?file_id=<?= $task['FILE_ID'] ?>" class="download-link">⬇ Скачать</a>
                            </div>
                        </div>
                    <?endforeach;?>
                <?else:?>
                    <div class="empty-state">
                        <p>Файлы ещё не загружены</p>
                        <p style="font-size: 13px; margin-top: 10px;">Загрузите первый файл выше</p>
                    </div>
                <?endif;?>
            </div>
        </div>
    </div>
    
    <div class="refresh-indicator" id="refreshIndicator">
        <div class="spinner"></div>
        <span>Обновление...</span>
    </div>

    <script>
        //Показ имени выбранного файла
        document.getElementById('file').addEventListener('change', function(e) {
            const display = document.getElementById('fileNameDisplay');
            const fileName = e.target.files[0]?.name || 'Файл не выбран';
            display.textContent = fileName;
            display.classList.toggle('has-file', e.target.files[0]);
            //console.log('Выбран файл:', fileName);
        });
        
        //Загрузка файл
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            //console.log('Отправка формы...');
            
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Загрузка...';
            
            try {
                //console.log('Fetch запрос к /local/ajax/upload.php');
                const res = await fetch('/local/ajax/upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                //console.log('Ответ:', res.status, res.statusText);
                const text = await res.text();
                //console.log('Тело ответа:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (jsonErr) {
                    throw new Error('Сервер вернул не JSON: ' + text.substring(0, 200));
                }
                
                const box = document.getElementById('result');
                box.style.display = 'block';
                box.className = data.success ? 'success' : 'error';
                box.innerHTML = `<strong>${data.success ? 'Успех' : 'Ошибка'}</strong><br>` + 
                                (data.message || data.warning || data.error);
                
                if (data.success) {
                    //console.log('Загрузка успешна, обновляем список...');
                    setTimeout(() => refreshList(), 1000);
                }
            } catch (err) {
                //console.error('Ошибка загрузки:', err);
                const box = document.getElementById('result');
                box.style.display = 'block';
                box.className = 'error';
                box.innerHTML = '<strong>Ошибка</strong><br>' + err.message;
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
        
        //Toggle списка
        document.getElementById('toggleFiles').addEventListener('click', function() {
            const list = document.getElementById('filesList');
            const header = document.getElementById('toggleFiles');
            list.classList.toggle('show');
            header.classList.toggle('active');
        });
        
        //Кнопка обновления
        document.getElementById('refreshBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            refreshList();
        });
        
        //Обновление списка через AJAX
        async function refreshList() {
            const indicator = document.getElementById('refreshIndicator');
            indicator.classList.add('show');
            
            try {
                const res = await fetch('/local/ajax/get_tasks.php');
                const html = await res.text();
                document.getElementById('filesList').innerHTML = html;
                //console.log('Список обновлён');
            } catch (err) {
                console.error('Не удалось обновить список:', err);
                location.reload();
            } finally {
                indicator.classList.remove('show');
            }
        }
        
        //Автообновление статусов
        <?if ($hasActive): ?>
            //console.log('Есть активные задачи, включаем проверку каждые 10 сек');
            setInterval(() => {
                fetch('/local/ajax/get_status.php')
                    .then(r => r.json())
                    .then(data => {
                        if (data.tasks) {
                            data.tasks.forEach(task => {
                                const statusEl = document.getElementById('status-' + task.ID);
                                if (statusEl && statusEl.textContent !== task.STATUS) {
                                    statusEl.textContent = task.STATUS;
                                    statusEl.className = 'file-status status-' + task.STATUS;
                                    //console.log('Статус задачи #' + task.ID + ' изменён на ' + task.STATUS);
                                }
                            });
                        }
                    })
                    .catch(err => console.warn('Не удалось обновить статусы:', err));
            }, 10000);
        <?endif; ?>
    </script>
</body>
</html>

<?require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>