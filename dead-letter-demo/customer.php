<?php

/**
 * 消息消费者，监听消费者队列
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$host  = '127.0.0.1';
$port  = 5672;
$user  = 'myuser';
$pwd   = 'mypass';
$vhost = '/';

//消费队列
$customerExchangeName = 'exchange@customer';
$customerQueueName    = 'queue@customer';
$customerRouteName    = '/customer';

$conn    = new AMQPStreamConnection($host, $port, $user, $pwd, $vhost);
$channel = $conn->channel();
$channel->exchange_declare($customerExchangeName, AMQPExchangeType::DIRECT, false, true, false);
$channel->queue_declare($customerQueueName, false, true, false, false);
$channel->queue_bind($customerQueueName, $customerExchangeName, $customerRouteName);

$channel->basic_consume($customerQueueName, '', false, false, false, false, function (AMQPMessage $message) {
    $data = json_decode($message->body, true);

    // 递增投递次数
    $data['notify_retries_number']++;

    // 检查是否已达到最大投递次数
    if ($data['notify_retries_number'] > count($data['notify_rules'])) {
        // 记录消息到redis或数数据库

        // 消息确认,消息会从队列中移除
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        return;
    }

    // 执行正常的业务逻辑
    var_dump($data);

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
});

while ($channel->is_consuming()) {
    $channel->wait();
}

