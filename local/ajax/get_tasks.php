<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/university.fileasync/lib/Entity/FileTaskTable.php';

use University\FileAsync\Entity\FileTaskTable;

// Отдаём чистый HTML для вставки в #filesList
header('Content-Type: text/html; charset=utf-8');

$rsTasks = FileTaskTable::getList([
    'select' => ['ID', 'FILE_ID', 'STATUS', 'ERROR_MESSAGE', 'CREATED_AT'],
    'order'  => ['ID' => 'DESC']
]);

$files = [];
while ($task = $rsTasks->fetch()) {
    $file = \CFile::GetByID($task['FILE_ID'])->Fetch();
    $task['FILE'] = $file;
    $files[] = $task;
}

if (count($files) > 0):
    foreach ($files as $task): 
        // Получаем расширение и размер файла
        $originalName = $task['FILE']['ORIGINAL_NAME'] ?? '';
        $fileExt = $originalName ? strtoupper(pathinfo($originalName, PATHINFO_EXTENSION)) : '—';
        $fileSize = isset($task['FILE']['FILE_SIZE']) ? round($task['FILE']['FILE_SIZE'] / 1024, 2) . ' KB' : '—';
    ?>
        <div class="file-item" id="task-<?= $task['ID'] ?>">
            <div class="file-info">
                <div class="file-name">
                    <?= htmlspecialchars($originalName ?: 'Неизвестный файл') ?>
                </div>
                <div class="file-meta">
                    <strong>ID:</strong> <?= $task['ID'] ?> | 
                    <strong>Тип:</strong> <?= htmlspecialchars($fileExt) ?> | 
                    <strong>Размер:</strong> <?= $fileSize ?> | 
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
    <?php endforeach;
else: ?>
    <div class="empty-state">
        <p>Файлы ещё не загружены</p>
        <p style="font-size: 13px; margin-top: 10px;">Загрузите первый файл выше</p>
    </div>
<?php endif; ?>