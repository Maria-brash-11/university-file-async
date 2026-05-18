<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$fileId = (int)($_GET['file_id'] ?? 0);
if ($fileId <= 0) {
    die("Ошибка: неверный ID файла");
}

$arFile = \CFile::GetByID($fileId)->Fetch();
if (!$arFile) {
    die("Файл не найден");
}

$filePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $arFile['SUBDIR'] . '/' . $arFile['FILE_NAME'];
if (!file_exists($filePath)) {
    die("Физический файл отсутствует");
}

header('Content-Type: ' . $arFile['CONTENT_TYPE']);
header('Content-Disposition: attachment; filename="' . $arFile['ORIGINAL_NAME'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private');
readfile($filePath);
exit;