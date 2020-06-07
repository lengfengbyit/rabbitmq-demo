# rabbitmq-demo
1. 利用死信机制(dead letter)实现消息延时投递
```php
// 死信设置
$table = new AMQPTable();
$table->set('x-dead-letter-exchange', $customerExchangeName); // 设置死信队列，这里是我们的消费者队列
$table->set('x-dead-letter-routing-key', $customerRouteName); // 设置死信队列的路由
$table->set('x-message-ttl', $cacheTime); // 设置消息的生存时间(缓存时间) 单位毫秒
```
