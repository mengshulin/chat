<?php
////实例化redis
$redis = new Redis();
////连接
$redis->connect('127.0.0.1', 6379);
$redis->auth('app123456');
$redis->select(0);
// 此处接口可接收到微信服务器传来的消息内容
$response = file_get_contents("php://input");
// 消息存储
$redisKey = 'gameUserMessageLog';
$redis->lPush($redisKey, $response);
$messageArr = json_decode($response, true);
// 推送消息
if(empty($messageArr)){
    echo '未收到任何消息';
    exit;
}
$str    = http_build_query($messageArr);
$client = new swoole_client(SWOOLE_SOCK_TCP);
if (!$client->connect('0.0.0.0', 9501, -1)) {
    exit("connect failed. Error: {$client->errCode}\n");
}
$client->send("GET /?$str HTTP/1.1\r\n\r\n");
//$client->recv();
$client->close();
echo 'success';




