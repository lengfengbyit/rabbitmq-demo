<?php

/**
 * RabbitMQ 死信队列学习
 * 消息生产者
 *
 * 1. 定义两个消息队列
 *    - 缓存队列， 没有消费者，缓存消息固定的时间，达到消息延时的目的
 *    - 消费队列， 缓存队列中的消息达到缓存时间后，会转移到消费队列， 然后被消费者消费
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

$host  = '127.0.0.1';
$port  = 5672;
$user  = 'myuser';
$pwd   = 'mypass';
$vhost = '/';

// 缓存队列
$cacheTime         = 5000; // 缓存队列的缓存时间 单位毫秒
$cacheExchangeName = 'exchange@cache:' . $cacheTime;
$cacheQueueName    = 'queue@cache';
$cacheRouteName    = '/cache';

//消费队列
$customerExchangeName = 'exchange@customer';
$customerQueueName    = 'queue@customer';
$customerRouteName    = '/customer'; // 表示只要有消息到达了Exchange，那么都会路由到这个queue上

$conn    = new AMQPStreamConnection($host, $port, $user, $pwd, $vhost);
$channel = $conn->channel();

// 设置当缓存队列中的消息到达缓存时间后，自动将消息转发到消费者队列中
$table = new AMQPTable();
$table->set('x-dead-letter-exchange', $customerExchangeName); // 设置死信队列，这里是我们的消费者队列
$table->set('x-dead-letter-routing-key', $customerRouteName); // 设置死信队列的路由
$table->set('x-message-ttl', $cacheTime); // 设置消息的生存时间(缓存时间) 单位毫秒

// 定义缓存队列
$channel->exchange_declare($cacheExchangeName, AMQPExchangeType::DIRECT, false, true, false);
$channel->queue_declare($cacheQueueName, false, true, false, false, false, $table);
$channel->queue_bind($cacheQueueName, $cacheExchangeName, $cacheRouteName);

// 定义消费队列
$channel->exchange_declare($customerExchangeName, AMQPExchangeType::DIRECT, false, true, false);
$channel->queue_declare($customerQueueName, false, true, false, false);
$channel->queue_bind($customerQueueName, $customerExchangeName, $customerRouteName);


$data = [
    'msg_id'                => session_create_id('msg'),
    'version'               => 1,
    'create_time'           => time(),
    'msg_body'              => ['order_id' => rand(1, 1000), 'shop_id' => rand(1, 100)],
    'notify_url'            => 'http://127.0.0.1:18306/shop/notify',
    'notify_rules'           => [5, 10, 15], // 通知规则， 每次间隔时间, 和通知次数
    'notify_retries_number' => 0, // 重试的次数， 默认为0
    'status'                => 1, // 消息的状态
];

$msg = new AMQPMessage(json_encode($data), [
    'content_type'  => 'text/plain',
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 消息持久化
]);

// 将消息发送到缓存队列中
for ($i = 0; $i < 100; $i++) {
    $channel->basic_publish($msg, $cacheExchangeName, $cacheRouteName);
    //$channel->basic_publish($msg, $customerExchangeName, $customerRouteName);
}

// 关闭连接
$channel->close();
$conn->close();
