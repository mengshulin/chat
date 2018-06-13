<?php

//实例化redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('app123456');
$redis->select(0);

$redisKey = 'waitingGameUser';

if($redis->exists($redisKey)){
    $count = $redis->lLen($redisKey);
    echo json_encode(['code' => 1, 'message' => '查询成功', 'response' => ['count' => $count]],JSON_UNESCAPED_UNICODE);
}else{
    echo json_encode(['code' => 1, 'message' => '查询成功', 'response' => ['count' => 0]],JSON_UNESCAPED_UNICODE);
}


