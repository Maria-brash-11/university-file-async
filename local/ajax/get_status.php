<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/university.fileasync/lib/Entity/FileTaskTable.php';

use University\FileAsync\Entity\FileTaskTable;

header('Content-Type: application/json; charset=utf-8');

$rsTasks = FileTaskTable::getList([
    'select' => ['ID', 'STATUS'],
    'filter' => ['!=STATUS' => 'done'],
    'order'  => ['ID' => 'DESC']
]);

$tasks = [];
while ($task = $rsTasks->fetch()) {
    $tasks[] = ['ID' => $task['ID'], 'STATUS' => $task['STATUS']];
}

echo json_encode(['tasks' => $tasks, 'timestamp' => time()]);