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
├── local/
│ └── modules/
│ └── university.fileasync/
│ ├── lib/
│ │ ├── Entity/FileTaskTable.php # ORM-сущность задачи
│ │ └── Service/RabbitMQPublisher.php # Публикация в очередь
│ ├── tools/
│ │ └── worker.php # CLI-воркер обработки
│ └── install/
│ └── db/mysql/install.sql # SQL-схема таблицы
├── ajax/
│ ├── upload.php # Приём файла и публикация задачи
│ ├── get_tasks.php # AJAX: список задач (HTML)
│ └── get_status.php # AJAX: статусы задач (JSON)
├── download.php # Безопасная отдача файлов
├── composer.json # Зависимости (php-amqplib/php-amqplib)
├── README.md # Этот файл
└── .gitignore # Исключения для репозитория
└── rmq.php # Страница сервиса

## Установка и запуск

### Предварительные требования
- Установленная и настроенная 1С-Битрикс
- Запущенный RabbitMQ с вхостом `/university` и пользователем `bitrix_dev`
- PHP 8.1+ с расширениями: `mbstring`, `json`, `zip`, `curl`
###

1. Установка зависимостей
```bash
cd /path/to/site/root
composer install --no-dev

2. Создание таблицы
Выполните SQL из local/modules/university.fileasync/install/db/mysql/install.sql в вашей БД.

3. Настройка подключения к RabbitMQ
Отредактируйте массив $cfg в файлах:
local/modules/university.fileasync/lib/Service/RabbitMQPublisher.php
local/modules/university.fileasync/tools/worker.php

4. Запуск воркера
```bash
php local/modules/university.fileasync/tools/worker.php

Воркер работает в режиме демона: слушает очередь и обрабатывает задачи по мере поступления.

5. Проверка работы
Откройте rmq.php
Загрузите файл (изображение, PDF, DOCX, XLSX)
Файл появится в списке со статусом pending
Через несколько секунд статус автоматически обновится на done, а рядом отобразятся метаданные:

6. Масштабируемость
Архитектура поддерживает горизонтальное масштабирование. Запуск нескольких воркеров (в разных терминалах или через systemd/Supervisor)
```bash
php worker.php  # Воркер #1
php worker.php  # Воркер #2
php worker.php  # Воркер #3