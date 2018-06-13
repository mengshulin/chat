<?php
$accessToken = '10_D9KOsH2OqN_cmfI4ZBr7OhffBo0FZLRWPRb_UiGMDqZW4tcR5Y8DeYKP_HKu9VEEYlpsg1_o_hPNZiwInXUFmLw2MJuK5Zci8qBVzgleBLMYkJYI-kcIWsQkNjb2jjqYJIRnEZrGFBiCEymGTKXhAF1AJAN';
$data = [
    'url'      => 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$accessToken,
    'postData' => [
        'touser'  => $_POST['openid'],
        'msgtype' => 'text',
        'text'    => [
            'content' => $_POST['content'],
        ],
    ],
];
function postReq($postReqData) {

    //初始化
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $postReqData['url']);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, false);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //设置post方式提交
    curl_setopt($curl, CURLOPT_POST, 1);
    $header = [
        "Accept: application/json",
        "Content-Type: application/json;charset=utf-8",
    ];
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    //设置post数据
    $post_data = json_encode($postReqData['postData'],JSON_UNESCAPED_UNICODE);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);

    //显示获得的数据
    return $data;
}

$response = postReq($data);
$responseArr = json_decode($response, true);
if ($responseArr['errcode'] == 0) {
    echo json_encode(['code' => 1, 'message' => '发送成功！！！', 'response' => $responseArr],JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['code' => 0, 'message' => '发送失败！！！', 'response' => $responseArr],JSON_UNESCAPED_UNICODE);
}


