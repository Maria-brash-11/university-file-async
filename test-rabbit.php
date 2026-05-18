<?php
// Минимальный тест: только RabbitMQ, без Bitrix
require __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

echo "🔌 Connecting to RabbitMQ...\n";

try {
    $connection = new AMQPStreamConnection(
        '127.0.0.1', 5672, 'bitrix_dev', 'devpass123', '/university'
    );
    echo "✅ Connected!\n";
    
    $channel = $connection->channel();
    $channel->queue_declare('file_processing', false, true, false, false);
    echo "✅ Queue declared!\n";
    
    // Проверяем, есть ли сообщения в очереди
    $method = $channel->basic_get('file_processing', false);
    if ($method) {
        echo "📦 Message found!\n";
        echo "Body: " . $method->body . "\n";
        $channel->basic_ack($method->delivery_info['delivery_tag']);
        echo "✅ Message acknowledged\n";
    } else {
        echo "⏳ Queue is empty. Waiting for new messages (Ctrl+C to stop)...\n";
        
        $callback = function($msg) {
            echo "📥 Received: " . $msg->body . "\n";
            $msg->ack();
            echo "✅ Done. Exiting.\n";
            exit(0); // Выходим после первого сообщения для теста
        };
        
        $channel->basic_consume('file_processing', '', false, false, false, false, $callback);
        
        while ($channel->is_consuming()) {
            echo ". ";
            sleep(1);
            $channel->wait(null, false, 10); // Ждём максимум 10 секунд
        }
    }
    
    $channel->close();
    $connection->close();
    
} catch (\Throwable $e) {
    echo "🔴 Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}