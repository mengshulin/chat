<?php

class hsw {

    // 客服服务用户上限
    const SERVICE_NUM = 1;
    // 用户来源
    const SOURCE_MINI_GAME = 'mini_game';// 小游戏用户
    const ACCESS_TOKEN     = '10_D9KOsH2OqN_cmfI4ZBr7OhffBo0FZLRWPRb_UiGMDqZW4tcR5Y8DeYKP_HKu9VEEYlpsg1_o_hPNZiwInXUFmLw2MJuK5Zci8qBVzgleBLMYkJYI-kcIWsQkNjb2jjqYJIRnEZrGFBiCEymGTKXhAFAJAN';// accessToken

    private $serv  = null;
    private $redis = null;

    public function __construct() {

        File::init();
        //连接
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->auth('app123456');
        $this->redis->select(0);
        $this->serv = new swoole_websocket_server("0.0.0.0", 9501);
        $this->serv->set([
            'task_worker_num' => 8,
        ]);
        $this->serv->on("open", [$this, "onOpen"]);
        $this->serv->on("message", [$this, "onMessage"]);
        $this->serv->on("Task", [$this, "onTask"]);
        $this->serv->on("Finish", [$this, "onFinish"]);
        $this->serv->on("close", [$this, "onClose"]);
        $this->serv->on('request', function ($request, $response) {

            // 获取所有正在服务的对象
            $serviceUsersRedisKeyArr = $this->redis->keys('serviceUsers:*');
            $flag                    = 0;
            $username                = '';
            $socketId                = '';
            if (!empty($serviceUsersRedisKeyArr)) {
                foreach ($serviceUsersRedisKeyArr AS $v) {
                    $infoArr = $this->redis->sMembers($v);
                    foreach ($infoArr AS $key => $value) {
                        $info = json_decode($value, true);
                        if (($info['source'] == $request->get['source']) && ($info['originalId'] == $request->get['ToUserName']) && ($info['openid'] == $request->get['FromUserName'])) {
                            $flag     = 1;
                            $username = explode(':', $v)[1];
                            break 2;
                        }
                    }
                }
                if ($flag == 1) {
                    $customerServiceSocketIdRedisKeyArr = $this->redis->keys('customerServiceSocketId:*');
                    if (!empty($customerServiceSocketIdRedisKeyArr)) {
                        foreach ($customerServiceSocketIdRedisKeyArr AS $_v) {
                            $username_v = $this->redis->get($_v);
                            if ($username_v == $username) {
                                $socketId = explode(':', $_v)[1];
                            }
                        }
                    }
                }
            }
            if (!$flag) {
                // 所有在线客服
                $onLineCustomerServiceList = [];
                // 所有在线可服务客服
                $customerServiceAbleList = [];
                $redisKeyArr             = $this->redis->keys('customerServiceSocketId:*');
                if (!empty($redisKeyArr)) {
                    foreach ($redisKeyArr AS $value) {
                        $arr                 = explode(':', $value);
                        $customerServiceInfo = [
                            'socket_id' => $arr[1],
                            'username'  => $this->redis->get($value),
                        ];
                        // 所有在线客服
                        $onLineCustomerServiceList[] = $customerServiceInfo;
                        // 所有在线可服务客服
                        $serviceUsersRedisKey = 'serviceUsers:' . $customerServiceInfo['username'];
                        $serviceNum           = $this->redis->sCard($serviceUsersRedisKey);
                        if ($serviceNum < self::SERVICE_NUM) {
                            $customerServiceAbleList[] = $customerServiceInfo;
                        }
                    }
                }
                // 无在线客服
                if (empty($onLineCustomerServiceList)) {
                    $sendMessageToWxParam = [
                        'touser'  => $request->get['FromUserName'],
                        'content' => '客服服务时间为周一至周五9:30~18:30',
                    ];
                    $this->sendMessageToWx($sendMessageToWxParam);
                } else {
                    $waitingGameUserRedisKey = 'waitingGameUser';
                    $waitingGameUserList     = $this->redis->lRange($waitingGameUserRedisKey, 0, -1);
                    $waitingFlag             = 0;
                    if (!empty($waitingGameUserList)) {
                        foreach ($waitingGameUserList AS $k => $v) {
                            $info = json_decode($v, true);
                            if (($info['source'] == $request->get['source']) && ($info['ToUserName'] == $request->get['ToUserName']) && ($info['FromUserName'] == $request->get['FromUserName'])) {
                                $waitingFlag = 1;
                                break;
                            }
                        }
                    }
                    if (empty($customerServiceAbleList) || $waitingFlag == 1) {
                        // 所有客服人员正忙，需等待
                        if (!$waitingFlag) {
                            $this->redis->lPush($waitingGameUserRedisKey, json_encode($request->get));
                        }
                        $len                  = $this->redis->lLen($waitingGameUserRedisKey);
                        $sendMessageToWxParam = [
                            'touser'  => $request->get['FromUserName'],
                            'content' => '当前客服正忙，您前面还有' . $len . '位在等待，请耐心等候，谢谢',
                        ];
                        $this->sendMessageToWx($sendMessageToWxParam);
                        // 发送更新
                        $data = [
                            'task'              => 'updateWaiting',
                            'fd'                => $request->fd,
                            'service_socket_id' => '-1',// 所有客服
                            'count'             => $len,// 数量
                        ];
                        $this->serv->task(json_encode($data));
                    } else {
                        $selectCustomerServiceListKey = array_rand($customerServiceAbleList);
                        $selectCustomerServiceInfo    = $customerServiceAbleList[$selectCustomerServiceListKey];
                        $serviceUsersRedisKey         = 'serviceUsers:' . $selectCustomerServiceInfo['username'];
                        $socketId                     = $selectCustomerServiceInfo['socket_id'];
                        $serviceUsersRedisValue       = [
                            'source'     => $request->get['source'],
                            'originalId' => $request->get['ToUserName'],
                            'openid'     => $request->get['FromUserName'],
                            //'username'   => '小富婆用户' . $request->get['FromUserName'] . '[小游戏]',
                            'username'   => '小富婆用户',
                            'avatar'     => '/client/static/images/gameicon/xiaofupo.jpg',
                        ];
                        $this->redis->sAdd($serviceUsersRedisKey, json_encode($serviceUsersRedisValue));
                        //var_dump($this->getMediaFromWx($request->get['MediaId']));
                        $data = [
                            'task'              => 'new',
                            'params'            => [
                                'username' => $serviceUsersRedisValue['username'],
                                'avatar'   => $serviceUsersRedisValue['avatar'],
                            ],
                            'c'                 => $request->get['MsgType'],
                            'message'           => $request->get['MsgType'] == 'text' ? $request->get['Content'] : $request->get['PicUrl'],
                            'fd'                => $request->fd,
                            'service_socket_id' => $socketId,
                            'source'            => $request->get['source'],
                            'originalId'        => $serviceUsersRedisValue['originalId'],
                            'openid'            => $serviceUsersRedisValue['openid'],
                            'activate'          => 0,
                            'customerUserName'  => $selectCustomerServiceInfo['username'],
                        ];
                        $this->serv->task(json_encode($data));
                    }
                }
            } else {
                $data = [
                    'task'              => 'new',
                    'params'            => [
                        'username' => '小富婆用户',
                        'avatar'   => '/client/static/images/gameicon/xiaofupo.jpg',
                    ],
                    'c'                 => $request->get['MsgType'],
                    'message'           => $request->get['MsgType'] == 'text' ? $request->get['Content'] : $request->get['PicUrl'],
                    'fd'                => $request->fd,
                    'service_socket_id' => $socketId,
                    'source'            => $request->get['source'],
                    'originalId'        => $request->get['ToUserName'],
                    'openid'            => $request->get['FromUserName'],
                    'activate'          => 0,
                    'customerUserName'  => $username,
                ];
                $this->serv->task(json_encode($data));
            }
        });
        $this->serv->start();
    }

    public function onOpen($serv, $request) {

        $data = [
            'task' => 'open',
            'fd'   => $request->fd,
        ];
        $this->serv->task(json_encode($data));
        echo "open\n";
    }

    public function onMessage($serv, $frame) {

        $data = json_decode($frame->data, true);
        switch ($data['type']) {
            case 1://登录
                $taskData = [
                    'task'              => 'login',
                    'params'            => [
                        'username' => $data['username'],
                        'password' => $data['password'],
                    ],
                    'fd'                => $frame->fd,
                    'service_socket_id' => $frame->fd,
                ];
                if (!$taskData['params']['username']) {
                    $this->serv->push($frame->fd, json_encode(['code' => 0, 'msg' => 'not logged in']));
                    break;
                }
                if ($this->checkServiceUser($taskData['params'])) {
                    $this->serv->task(json_encode($taskData));
                    // 发送更新
                    $len        = $this->redis->lLen('waitingGameUser');
                    $updateData = [
                        'task'              => 'updateWaiting',
                        'fd'                => $data['fd'],
                        'service_socket_id' => '-1',// 所有客服
                        'count'             => $len,// 数量
                    ];
                    $this->serv->task(json_encode($updateData));
                } else {
                    $this->serv->push($frame->fd, json_encode(['code' => 0, 'msg' => '密码错误，请重新输入']));
                }
                break;
            case 2: //新消息
                $taskData = [
                    'task'              => 'new',
                    'params'            => [
                        'username' => $data['username'],
                        'avatar'   => $data['avatar'],
                    ],
                    'c'                 => $data['c'],
                    'message'           => $data['message'],
                    'fd'                => $frame->fd,
                    'service_socket_id' => $frame->fd,
                    'source'            => $data['source'],
                    'originalId'        => $data['originalId'],
                    'openid'            => $data['openid'],
                    'activate'          => $data['activate'],
                    'customerUserName'  => $data['customerUserName'],
                ];
                $this->serv->task(json_encode($taskData));
                break;
            case 3: // 改变房间
                $taskData = [
                    'task'      => 'change',
                    'params'    => [
                        'name'   => $data['name'],
                        'avatar' => $data['avatar'],
                    ],
                    'fd'        => $frame->fd,
                    'oldroomid' => $data['oldroomid'],
                    'roomid'    => $data['roomid'],
                    'source'    => 'mini_game',
                ];
                $this->serv->task(json_encode($taskData));
                break;
            case 4: // 结束服务
                $taskData = [
                    'task'              => 'endClient',
                    'fd'                => $frame->fd,
                    'service_socket_id' => $frame->fd,
                    'source'            => $data['source'],
                    'originalId'        => $data['originalId'],
                    'openid'            => $data['openid'],
                ];
                $this->serv->task(json_encode($taskData));
                break;
            case 5: // 客服接入
                $taskData = [
                    'task'              => 'accessClient',
                    'fd'                => $frame->fd,
                    'service_socket_id' => $frame->fd,
                    'message'           => $data['message'],
                ];
                $this->serv->task(json_encode($taskData));
                break;
            default :
                $this->serv->push($frame->fd, json_encode(['code' => 0, 'msg' => 'type error']));
        }
    }

    public function onTask($serv, $task_id, $from_id, $data) {

        $pushMsg = ['code' => 0, 'msg' => '', 'data' => []];
        $data    = json_decode($data, true);
        switch ($data['task']) {
            case 'open':
                $pushMsg = Chat::open($data);
                $this->serv->push($data['fd'], json_encode($pushMsg));

                return 'Finished';
            case 'login':
                $pushMsg = Chat::doLogin($data);
                break;
            case 'new':
                $pushMsg = Chat::sendNewMsg($data);
                if(!$pushMsg){
                    throw new Exception('服务用户上限，请先结束一个用户服务');
                    //$this->serv->push($data['fd'], json_encode(['code' => 0, 'msg' => '服务用户上限，请先结束一个用户服务']));
                }
                break;
            case 'logout':
                $pushMsg = Chat::doLogout($data);
                break;
            case 'nologin':
                $pushMsg = Chat::noLogin($data);
                $this->serv->push($data['fd'], json_encode($pushMsg));

                return "Finished";
            case 'change':
                $pushMsg = Chat::change($data);
                break;
            case 'endClient':
                $pushMsg              = Chat::endClient($data);
                $sendMessageToWxParam = [
                    'touser'  => $data['openid'],
                    'content' => '此次服务已结束，谢谢',
                ];
                $this->sendMessageToWx($sendMessageToWxParam);
                break;
            case 'accessClient':
                $pushMsg = Chat::accessClient($data);
                if ($pushMsg) {
                    $sendMessageToWxParam = [
                        'touser'  => $pushMsg['data']['openid'],
                        'content' => '',
                    ];
                    $this->sendMessageToWx($sendMessageToWxParam);
                    // 发送更新
                    $updateData     = [
                        'task'              => 'updateWaiting',
                        'fd'                => $data['fd'],
                        'service_socket_id' => '-1',// 所有客服
                        'count'             => $pushMsg['data']['count'],// 数量
                    ];
                    $pushMsgWaiting = Chat::updateWaiting($updateData);
                    $this->sendMsg($pushMsgWaiting, $data['fd']);
                } else {
                    throw new Exception('服务用户上限，请先结束一个用户服务');
                    //$this->serv->push($data['fd'], json_encode(['code' => 0, 'msg' => '服务用户上限，请先结束一个用户服务']));
                }
                break;
            case 'updateWaiting':
                $pushMsg = Chat::updateWaiting($data);
                break;
        }
        $this->sendMsg($pushMsg, $data['fd']);

        return "Finished";
    }

    public function onClose($serv, $fd) {

        $pushMsg = ['code' => 0, 'msg' => '', 'data' => []];
        //获取用户信息
        $userName = Chat::logout($fd);
        if (!empty($userName)) {
            $data = [
                'task'   => 'logout',
                'params' => [
                    'username' => $userName,
                ],
                'fd'     => $fd,
            ];
            $this->serv->task(json_encode($data));
        }
        echo "client {$fd} closed\n";
    }

    public function sendMsg($pushMsg, $myfd) {

        foreach ($this->serv->connections as $fd) {
            if ($fd === $myfd) {
                $pushMsg['data']['mine'] = 1;
            } else {
                $pushMsg['data']['mine'] = 0;
            }
            if ($pushMsg['data']['service_socket_id'] == '-1') {
                $this->serv->push($fd, json_encode($pushMsg));
            } elseif (!empty($pushMsg['data']['service_socket_id']) && $fd == $pushMsg['data']['service_socket_id']) {
                $this->serv->push($fd, json_encode($pushMsg));
                // 发送数据
                if ($pushMsg['code'] == 2) {
                    $username = $this->redis->get('customerServiceSocketId:' . $pushMsg['data']['service_socket_id']);
                    if ($pushMsg['data']['mine'] == 1) {
                        // 记录对话
                        $talkData = [
                            'to'         => $pushMsg['data']['source'] . '|' . $pushMsg['data']['originalId'] . '|' . $pushMsg['data']['openid'],
                            'from'       => $username,
                            'source'     => $pushMsg['data']['source'],
                            'originalId' => $pushMsg['data']['originalId'],
                            'openid'     => $pushMsg['data']['openid'],
                            'username'   => $pushMsg['data']['username'],
                            'avatar'     => $pushMsg['data']['avatar'],
                            'newmessage' => $pushMsg['data']['newmessage'],
                            'time'       => date('m-d H:i', time()),
                        ];
                    } else {
                        // 记录对话
                        $talkData = [
                            'to'         => $username,
                            'from'       => $pushMsg['data']['source'] . '|' . $pushMsg['data']['originalId'] . '|' . $pushMsg['data']['openid'],
                            'source'     => $pushMsg['data']['source'],
                            'originalId' => $pushMsg['data']['originalId'],
                            'openid'     => $pushMsg['data']['openid'],
                            'username'   => $pushMsg['data']['username'],
                            'avatar'     => $pushMsg['data']['avatar'],
                            'newmessage' => $pushMsg['data']['newmessage'],
                            'time'       => date('m-d H:i', time()),
                        ];
                    }
                    $this->redis->rPush('talkLog:' . $username . ':' . $pushMsg['data']['source'] . '|' . $pushMsg['data']['originalId'] . '|' . $pushMsg['data']['openid'], json_encode($talkData));
                }
            }
        }
    }

    public function onFinish($serv, $task_id, $data) {

        echo "Task {$task_id} finish\n";
        echo "Result: {$data}\n";
    }

    function req($reqData, $type = 'get') {

        //初始化
        $curl = curl_init();
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, false);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        if ($type == 'get') {
            //设置抓取的url
            $url = $reqData['url'] . '?' . http_build_query($reqData['data']);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 0);
            $header = [
                "Content-Type: image/jpeg",
            ];
        } else {
            //设置抓取的url
            curl_setopt($curl, CURLOPT_URL, $reqData['url']);
            curl_setopt($curl, CURLOPT_POST, 1);
            $curl_data = json_encode($reqData['data'], JSON_UNESCAPED_UNICODE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_data);
            $header = [
                "Accept: application/json",
                "Content-Type: application/json;charset=utf-8",
            ];
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置post数据
        //执行命令
        $data = curl_exec($curl);
        if ($type == 'get') {
            $img_info = getimagesize($data);
            var_dump($img_info);
            $img_src = "data:{$img_info['mime']};base64," . base64_encode(file_get_contents($data));
            $data    = $img_src;
        }
        //关闭URL请求
        curl_close($curl);

        //显示获得的数据
        return $data;
    }

    public function sendMessageToWx($data) {

        $reqData = [
            'url'  => 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . self::ACCESS_TOKEN,
            'data' => [
                'touser'  => $data['touser'],
                'msgtype' => 'text',
                'text'    => [
                    'content' => $data['content'],
                ],
            ],
        ];
        $res     = $this->req($reqData, 'post');

        return true;
    }

    public function getMediaFromWx($mediaId) {

        $getReqData = [
            'url'  => 'https://api.weixin.qq.com/cgi-bin/media/get',
            'data' => [
                'access_token' => self::ACCESS_TOKEN,
                'media_id'     => $mediaId,
            ],
        ];
        $res        = $this->req($getReqData, 'get');
        var_dump($res);

        return true;
    }

    public function checkServiceUser($data) {

        $redisKey      = 'customerService:' . $data['username'];
        $passwordRedis = $this->redis->hGet($redisKey, 'password');
        if ($passwordRedis == md5($data['password'])) {
            return true;
        } else {
            return false;
        }
    }

}