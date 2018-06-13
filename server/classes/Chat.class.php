<?php

class Chat {

    /**
     * 登录
     */
    public static function login($loginData) {

        //连接
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('app123456');
        if (!$loginData['username']) {

            throw new Exception('Fill in all the required fields.');
        }
        $customerServiceSocketIdRedisKey   = 'customerServiceSocketId:' . $loginData['fd'];
        $customerServiceSocketIdRedisValue = $loginData['username'];
        $res                               = $redis->set($customerServiceSocketIdRedisKey, $customerServiceSocketIdRedisValue);
        if (!$res) {
            throw new Exception('login error.');
        }
    }

    /**
     * 获取用户在线列表
     */
    public static function getOnlineUsers($socketId) {

        // 连接
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('app123456');
        // 获取当前登录客服正在服务的用户
        $onlineUsers = [];
        // 获取当前登录客服username
        $redisKey = 'customerServiceSocketId:' . $socketId;
        if ($redis->exists($redisKey)) {
            $userName = $redis->get($redisKey);
            $users    = $redis->keys('talkLog:' . $userName . ':*');
            if (!empty($users)) {
                foreach ($users AS $key => $value) {
                    $talkLogList = $redis->lRange($value, 0, -1);
                    if (!empty($talkLogList)) {
                        $length = count($talkLogList);
                        foreach ($talkLogList AS $k => $v) {
                            if ($k == ($length - 1)) {
                                $detail                              = json_decode($v, true);
                                $onlineUsers[$key]['source']         = $detail['source'];
                                $onlineUsers[$key]['originalId']     = $detail['originalId'];
                                $onlineUsers[$key]['openid']         = $detail['openid'];
                                $onlineUsers[$key]['username']       = $detail['username'];
                                $onlineUsers[$key]['avatar']         = $detail['avatar'];
                                $onlineUsers[$key]['time']           = $detail['time'];
                                $onlineUsers[$key]['service_status'] = '<span class="history service_status">历史记录</span>';
                            }
                            $onlineUsers[$key]['detail'][] = $v;
                        }

                    }

                }
            }

            return $onlineUsers;
        }
    }

    /**
     * 客服下线操作
     * @param $socketId
     * @return bool|string
     */
    public static function logout($socketId) {

        //连接
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('app123456');
        $redisKey = 'customerServiceSocketId:' . $socketId;
        $userName = '';
        if ($redis->exists($redisKey)) {
            $userName = $redis->get($redisKey);
            // 清除在线
            $redis->del($redisKey);
            // 清除该客服服务用户集合
            $redisKey = 'serviceUsers:' . $userName;
            $redis->del($redisKey);
        }

        return $userName;
    }

    public static function change($data) {

        $pushMsg['code'] = 6;
        $pushMsg['msg']  = '换房成功';
        $user            = new ChatUser();
        $is_copyed       = $user->changeUser($data['oldroomid'], $data['fd'], $data['roomid']);
        if ($is_copyed) {
        }
        $pushMsg['data']['oldroomid'] = $data['oldroomid'];
        $pushMsg['data']['roomid']    = $data['roomid'];
        $pushMsg['data']['mine']      = 0;
        $pushMsg['data']['fd']        = $data['fd'];
        $pushMsg['data']['name']      = $data['params']['name'];
        $pushMsg['data']['avatar']    = $data['params']['avatar'];
        $pushMsg['data']['time']      = date("H:i", time());
        unset($data);

        return $pushMsg;
    }

    public static function noLogin($data) {

        $pushMsg['code'] = 5;
        $pushMsg['msg']  = "系统不会存储您的Email，只是为了证明你是一个地球人";
        if (!$data['params']['name']) {
            $pushMsg['msg'] = "输入一个昵称或许可以让更多人的人了解你";
        }
        $pushMsg['data']['mine'] = 1;
        unset($data);

        return $pushMsg;
    }

    public static function open($data) {

        $pushMsg['code']         = 4;
        $pushMsg['msg']          = 'success';
        $pushMsg['data']['mine'] = 0;
        //$pushMsg['data']['rooms'] = self::getRooms();
        $pushMsg['data']['users'] = self::getOnlineUsers($data['fd']);
        unset($data);

        return $pushMsg;
    }

    public static function doLogout($data) {

        //删除
        //File::logout($data['fd']);
        $pushMsg['code']                      = 3;
        $pushMsg['msg']                       = $data['params']['name'] . "客服下线了";
        $pushMsg['data']['fd']                = $data['fd'];
        $pushMsg['data']['service_socket_id'] = $data['service_socket_id'];
        $pushMsg['data']['username']          = $data['params']['username'];
        unset($data);

        return $pushMsg;
    }

    //发送新消息
    public static function sendNewMsg($data) {

        $pushMsg['code']                      = 2;
        $pushMsg['msg']                       = "";
        $pushMsg['data']['source']            = $data['source'];
        $pushMsg['data']['originalId']        = $data['originalId'];
        $pushMsg['data']['username']          = $data['params']['username'];
        $pushMsg['data']['avatar']            = $data['params']['avatar'];
        $pushMsg['data']['openid']            = $data['openid'];
        $pushMsg['data']['fd']                = $data['fd'];
        $pushMsg['data']['service_socket_id'] = $data['service_socket_id'];
        $pushMsg['data']['newmessage']        = escape(htmlspecialchars($data['message']));
        if ($data['c'] == 'image') {
            $pushMsg['data']['newmessage'] = '<img class="chat-img" onclick="preview(this)" style="display: block; max-width: 120px; max-height: 120px; visibility: visible;" src=' . $pushMsg['data']['newmessage'] . '>';
        } else {
            global $emotion;
            foreach ($emotion as $_k => $_v) {
                $pushMsg['data']['newmessage'] = str_replace($_k, $_v, $pushMsg['data']['newmessage']);
            }
        }
        $pushMsg['data']['time'] = date("m-d H:i", time());
        if($data['activate'] == 1){
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->auth('app123456');
            $serviceNum = $redis->sCard('serviceUsers:' . $data['customerUserName']);
            if ($serviceNum < hsw::SERVICE_NUM) {
                $serviceUsersRedisKey         = 'serviceUsers:' . $data['customerUserName'];
                $serviceUsersRedisValue       = [
                    'source'     => $data['source'],
                    'originalId' => $data['originalId'],
                    'openid'     => $data['openid'],
                    'username'   => '小富婆用户',
                    'avatar'     => '/client/static/images/gameicon/xiaofupo.jpg',
                ];
                $redis->sAdd($serviceUsersRedisKey, json_encode($serviceUsersRedisValue));
            }else{
                return false;
            }
        }
        unset($data);

        return $pushMsg;
    }

    //登录
    public static function doLogin($data) {

        $pushMsg['code']                      = 1;
        $pushMsg['msg']                       = $data['params']['username'] . " 客服上线啦";
        $pushMsg['data']['fd']                = $data['fd'];
        $pushMsg['data']['service_socket_id'] = $data['service_socket_id'];
        $pushMsg['data']['username']          = $data['params']['username'];
        $pushMsg['data']['password']          = $data['params']['password'];
        $pushMsg['data']['avatar']            = DOMAIN . '/static/images/header_log.png';
        $pushMsg['data']['time']              = date("m-d H:i", time());
        $loginParam                           = [
            'fd'       => $data['fd'],
            'username' => $data['params']['username'],
            'avatar'   => $pushMsg['data']['avatar'],
        ];
        self::login($loginParam);
        $pushMsg['data']['users'] = self::getOnlineUsers($data['fd']);
        unset($data);

        return $pushMsg;
    }

    public static function getRooms() {

        global $rooms;
        $roomss = [];
        foreach ($rooms as $_k => $_v) {
            $roomss[] = [
                'roomid'   => $_k,
                'roomname' => $_v,
            ];
        }

        return $roomss;
    }

    public static function remind($roomid, $msg) {

        $data = [];
        if ($msg != "") {
            $data['msg'] = $msg;
            //正则匹配出所有@的人来
            $s = preg_match_all('~@(.+?)　~', $msg, $matches);
            if ($s) {
                $m1    = array_unique($matches[0]);
                $m2    = array_unique($matches[1]);
                $user  = new ChatUser();
                $users = $user->getUsersByRoom($roomid);
                $m3    = [];
                foreach ($users as $_k => $_v) {
                    $m3[$_v['name']] = $_v['fd'];
                }
                $i = 0;
                foreach ($m2 as $_k => $_v) {
                    if (array_key_exists($_v, $m3)) {
                        $data['msg']                 = str_replace($m1[$_k], '<font color="blue">' . trim($m1[$_k]) . '</font>', $data['msg']);
                        $data['remains'][$i]['fd']   = $m3[$_v];
                        $data['remains'][$i]['name'] = $_v;
                        $i++;
                    }
                }
                unset($users);
                unset($m1, $m2, $m3);
            }
        }

        return $data;
    }

    // 结束服务
    public static function endClient($data) {

        $pushMsg['code']                      = 7;
        $pushMsg['msg']                       = '结束服务成功';
        $pushMsg['data']['source']            = $data['source'];
        $pushMsg['data']['originalId']        = $data['originalId'];
        $pushMsg['data']['openid']            = $data['openid'];
        $pushMsg['data']['fd']                = $data['fd'];
        $pushMsg['data']['service_socket_id'] = $data['service_socket_id'];
        //连接
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('app123456');
        $redisKey = 'customerServiceSocketId:' . $data['service_socket_id'];
        if ($redis->exists($redisKey)) {
            $userName = $redis->get($redisKey);
            $redisKey = 'serviceUsers:' . $userName;
            if ($redis->exists($redisKey)) {
                // 获取所有服务用户
                $allServerUser = $redis->sMembers($redisKey);
                if (!empty($allServerUser)) {
                    foreach ($allServerUser AS $value) {
                        $info = json_decode($value, true);
                        if (($info['source'] == $data['source']) && ($info['originalId'] == $data['originalId']) && ($info['openid'] == $data['openid'])) {
                            // 清除该客服服务用户
                            $redis->sRem($redisKey, $value);
                        }
                    }
                }
            }
        }

        return $pushMsg;
    }

    // 更新等待人数
    public static function updateWaiting($data) {

        $pushMsg['code']                      = 8;
        $pushMsg['msg']                       = '刷新等待人数';
        $pushMsg['data']['fd']                = $data['fd'];
        $pushMsg['data']['service_socket_id'] = $data['service_socket_id'];
        $pushMsg['data']['count']             = $data['count'];

        return $pushMsg;
    }

    // 客服接入
    public static function accessClient($data) {

        //连接
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('app123456');
        $username   = $redis->get('customerServiceSocketId:' . $data['service_socket_id']);
        $serviceNum = $redis->sCard('serviceUsers:' . $username);
        if ($serviceNum < hsw::SERVICE_NUM) {
            $redisKey               = 'waitingGameUser';
            $redisValue             = $redis->rPop($redisKey);
            $len                    = $redis->lLen($redisKey);
            $info                   = json_decode($redisValue, true);
            $serviceUsersRedisKey   = 'serviceUsers:' . $username;
            $serviceUsersRedisValue = [
                'source'     => $info['source'],
                'originalId' => $info['ToUserName'],
                'openid'     => $info['FromUserName'],
                'username'   => '小富婆用户',
                'avatar'     => '/client/static/images/gameicon/xiaofupo.jpg',
            ];
            $redis->sAdd($serviceUsersRedisKey, json_encode($serviceUsersRedisValue));
            $pushMsg['code']                      = 9;
            $pushMsg['msg']                       = '';
            $pushMsg['data']['source']            = $info['source'];
            $pushMsg['data']['originalId']        = $info['ToUserName'];
            $pushMsg['data']['openid']            = $info['FromUserName'];
            $pushMsg['data']['fd']                = $data['fd'];
            $pushMsg['data']['service_socket_id'] = $data['service_socket_id'];
            $pushMsg['data']['newmessage']        = $data['message'];
            $pushMsg['data']['username']          = $serviceUsersRedisValue['username'];
            $pushMsg['data']['avatar']            = $serviceUsersRedisValue['avatar'];
            $pushMsg['data']['time']              = date('m-d H:i', time());
            $pushMsg['data']['count']             = $len;
            $pushMsg['data']['mine']              = 1;

            return $pushMsg;
        } else {
            return false;
        }

    }

}