<?php
namespace University\FileAsync\Service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    public static function push(int $taskId): bool
    {
        $cfg = self::getConfig();
        
        $connection = new AMQPStreamConnection(
            $cfg['host'],
            $cfg['port'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['vhost']
        );
        
        $channel = $connection->channel();
        

        $channel->queue_declare($cfg['queue'], false, true, false, false);
        $payload = json_encode(['task_id' => $taskId], JSON_THROW_ON_ERROR);
        $msg = new AMQPMessage($payload, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT 
        ]);
        
        $channel->basic_publish($msg, '', $cfg['queue']);
        
        $channel->close();
        $connection->close();
        
        return true;
    }
    
    private static function getConfig(): array
    {
        return [
            'host'  => '127.0.0.1',
            'port'  => 5672,
            'user'  => 'bitrix_dev',
            'pass'  => 'devpass123',
            'vhost' => '/university',
            'queue' => 'file_processing',
        ];
    }
}