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
    var_dump($data);

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
});

while ($channel->is_consuming()) {
    $channel->wait();
}

