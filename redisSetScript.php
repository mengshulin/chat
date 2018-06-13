<?php
$redis = new Redis();
//连接
$redis->connect('127.0.0.1', 6379);
$redis->auth('app123456');
$redis->select(0);
// 存储所有客服人员
$customerServiceRedisKey   = 'customerService:mengshulin';
$customerServiceRedisValue = [
    'id'          => 1,
    'username'    => 'mengshulin',
    'password'    => md5('123456'),
    'phone'       => '17324230351',
    'online'      => 0,
    'service_num' => 0,
    'socket_id'   => 0,
    'create_time' => '1527660424',
    'delete_time' => '',
];
$redis->hMset($customerServiceRedisKey, $customerServiceRedisValue);
$customerServiceRedisKey   = 'customerService:wuchang';
$customerServiceRedisValue = [
    'id'          => 2,
    'username'    => 'wuchang',
    'password'    => md5('123456'),
    'phone'       => '13366666666',
    'online'      => 0,
    'service_num' => 0,
    'socket_id'   => 0,
    'create_time' => '1527660424',
    'delete_time' => '',
];
$redis->hMset($customerServiceRedisKey, $customerServiceRedisValue);
$customerServiceRedisValue = [
    'id'          => 3,
    'username'    => 'yulujun',
    'password'    => md5('123456'),
    'phone'       => '13366669999',
    'online'      => 0,
    'service_num' => 0,
    'socket_id'   => 0,
    'create_time' => '1527660424',
    'delete_time' => '',
];
$customerServiceRedisKey   = 'customerService:yulujun';
$redis->hMset($customerServiceRedisKey, $customerServiceRedisValue);
//// 存储所有在线客服人员
//$onLineCustomerServiceRedisKey = 'onLineCustomerService';
//
//$redis->sAdd($onLineCustomerServiceRedisKey, 'customerService:1', 'customerService:2');
// 客服服务人员
//$CustomerServiceUserCountRedisKey = 'onLineCustomerService';
//$redis->sAdd($onLineCustomerServiceRedisKey, 'customerService:1', 'customerService:2');


