<?php

$redis = new Redis();
////连接
$redis->connect('127.0.0.1', 6379);
$redis->auth('app123456');
$redis->select(0);

$username = $_POST['username'];
$password = $_POST['password'];

$redisKey = 'customerService:'.$username;
$passwordRedis = $redis->hGet($redisKey,'password');

if($passwordRedis == md5($password)){
    echo json_encode(['code' => 1, 'message' => '登录成功'],JSON_UNESCAPED_UNICODE);
}else{
    echo json_encode(['code' => 0, 'message' => '登录失败'],JSON_UNESCAPED_UNICODE);
}
