# Сервис загрузки и асинхронной обработки файлов (1С-Битрикс + RabbitMQ)

Веб-сервис для приёма файлов от пользователя, их сохранения в системе и последующей асинхронной обработки с использованием очередей сообщений.

## Соответствие требованиям:
- Прием файлов от пользователя  
- Сохранение через ORM 1С-Битрикс (D7)  
- Постановка задачи в очередь RabbitMQ  
- Асинхронная обработка без блокировки UI  
- Обновление статуса обработки в БД  
- Масштабируемость за счёт увеличения воркеров  

## Технологический стек

| Компонент | Технология |
|-----------|-----------|
| CMS | 1С-Битрикс: Управление сайтом (ядро 23+, модуль D7) |
| Очереди | RabbitMQ 3.12+ |
| PHP | 8.1+ |
| База данных | MySQL 8.0+ / MariaDB |
| Клиентская часть | Vanilla JS + Fetch API |

## 📁 Структура проекта

### Модуль обработки файлов
- **local/modules/university.fileasync/lib/Entity/FileTaskTable.php** — ORM-сущность задачи
- **local/modules/university.fileasync/lib/Service/RabbitMQPublisher.php** — публикация в очередь RabbitMQ
- **local/modules/university.fileasync/tools/worker.php** — CLI-воркер обработки
- **local/modules/university.fileasync/install/db/mysql/install.sql** — SQL-схема таблицы

### AJAX-обработчики
- **local/ajax/upload.php** — приём файла и публикация задачи
- **local/ajax/get_tasks.php** — получение списка задач (HTML)
- **local/ajax/get_status.php** — получение статусов задач (JSON)

### Публичная часть
- **rmq.php** — главная страница сервиса (загрузка + список файлов)
- **download.php** — безопасная отдача файлов

### Конфигурация
- **composer.json** — зависимости (php-amqplib/php-amqplib)
- **README.md** — документация
- **.gitignore** — исключения для Git

## Установка и запуск

### Предварительные требования
- Установленная и настроенная 1С-Битрикс
- Запущенный RabbitMQ с хостом `/university` и пользователем `bitrix_dev`
- PHP 8.1+ с расширениями: `mbstring`, `json`, `zip`, `curl`
###

1. Установка зависимостей

```
cd /path/to/site/root
```
```
composer install --no-dev
```

3. Создание таблицы. Выполните SQL из

```
local/modules/university.fileasync/install/db/mysql/install.sql
```

5. Настройка подключения к RabbitMQ. Отредактируйте массив $cfg в файлах:
```
local/modules/university.fileasync/lib/Service/RabbitMQPublisher.php
```
```
local/modules/university.fileasync/tools/worker.php
```

6. Запуск воркера
```
php local/modules/university.fileasync/tools/worker.php
```
- Воркер работает в режиме демона: слушает очередь и обрабатывает задачи по мере поступления. Для остановки нажмите Ctrl + C.

5. Проверка работы
- Откройте страницу сервиса rmq.php
- Загрузите файл (изображение, PDF, DOCX, XLSX)
- Файл появится в списке со статусом pending
- Через несколько секунд статус автоматически обновится на done, а рядом отобразятся метаданные

6. Масштабируемость
- Архитектура поддерживает горизонтальное масштабирование. Запуск нескольких воркеров (в разных терминалах или через systemd/Supervisor)
