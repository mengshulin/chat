<?php

//实例化redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('app123456');
$redis->select(0);