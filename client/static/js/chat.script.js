$(document).ready(function () {
    // Run the init method on document ready:
    face.init();
    chat.init();
});
var successReturn = {
    error_code: 0,
    message   : '成功',
};
var failReturn    = {
    error_code: 1,
    message   : '失败',
};
var chat          = {
    data        : {
        wSock           : null,
        login           : false,
        storage         : null,
        type            : 1,
        fd              : 0,
        username        : "",
        avatar          : "",
        rds             : [],//所有房间ID
        crd             : 'a', //当前房间ID
        remains         : [],
        serviceUsers    : [],
        showUsers       : [],
        colorFlag       : 0,
        setIntervalArr  : [],
        selectOpenid    : '',
        selectSource    : '',
        selectOriginalId: '',
        selectUserName  : '',
        selectAvatar    : '',
    },
    init        : function () {
        this.copyright();
        this.off();
        chat.data.storage = window.localStorage;
        this.ws();
    },
    doLogin     : function (username, password) {
        if (username == '' || password == '') {
            username = $("#username").val();
            password = $('#password').val();
        }
        username = $.trim(username);
        password = $.trim(password);
        if (username == "" || password == "") {
            chat.displayError('chatErrorMessage_logout', "请输入账号和密码", 1);
            return false;
        }
        //登录操作
        chat.data.type  = 1; //登录标志
        chat.data.login = true;
        var json        = {"type": chat.data.type, "username": username, "password": password};
        chat.wsSend(JSON.stringify(json));
        return false;
    },
    logout      : function () {
        if (!this.data.login) return false;
        chat.data.type = 0;
        chat.data.storage.removeItem('dologin');
        chat.data.storage.removeItem('name');
        chat.data.storage.removeItem('password');
        chat.data.fd     = '';
        chat.data.name   = '';
        chat.data.avatar = '';
        location.reload();
    },
    keySend     : function (event) {
        if (event.ctrlKey && event.keyCode == 13) {
            $('#chattext').val($('#chattext').val() + "\r\n");
        } else if (event.keyCode == 13) {
            event.preventDefault();//避免回车换行
            this.sendMessage();
        }
    },
    sendMessage : function () {
        if (!this.data.login) return false;
        //发送消息操作
        var text = $('#chattext').val();
        if (text.length == 0) return false;
        console.log(chat.data);
        if (chat.data.selectOriginalId == '' || chat.data.selectOpenid == '' || chat.data.selectSource == '') return false;
        chat.data.type = 2; //发送消息标志
        var json       = {
            "type"      : chat.data.type,
            "username"  : chat.data.selectUserName,
            "avatar"    : chat.data.selectAvatar,
            "message"   : text,
            "c"         : 'text',
            'originalId': chat.data.selectOriginalId,
            'openid'    : chat.data.selectOpenid,
            'source'    : chat.data.selectSource,
            'activate'  : 0,
            'customerUserName'  : chat.data.username
        };
        var identification = md5(chat.data.selectSource+'|'+chat.data.selectOriginalId+'|'+chat.data.selectOpenid);
        if ($('#user-' + identification + ' .history').length > 0) {
            json.activate = 1;
        }
        chat.wsSend(JSON.stringify(json));
    
        // 发送消息到微信服务器
        chat.sendMessageToWx(json);
        return true;
    },
    ws          : function () {
        this.data.wSock = new WebSocket(config.wsserver);
        this.wsOpen();
        this.wsMessage();
        this.wsOnclose();
        this.wsOnerror();
    },
    wsSend      : function (data) {
        this.data.wSock.send(data);
    },
    wsOpen      : function () {
        this.data.wSock.onopen = function (event) {
            //初始化房间
            chat.print('wsopen', event);
            //判断是否已经登录过，如果登录过。自动登录。不需要再次输入昵称和邮箱
            
            var isLogin = chat.data.storage.getItem("dologin");
            if (isLogin) {
                var name     = chat.data.storage.getItem("username");
                var password = chat.data.storage.getItem("password");
                chat.doLogin(name, password);
            }
            
        }
    },
    wsMessage   : function () {
        this.data.wSock.onmessage = function (event) {
            var d = jQuery.parseJSON(event.data);
            switch (d.code) {
                case 1:
                    if (d.data.mine) {
                        chat.data.fd       = d.data.fd;
                        chat.data.username = d.data.username;
                        chat.data.avatar   = d.data.avatar;
                        chat.data.storage.setItem("dologin", 1);
                        chat.data.storage.setItem("username", d.data.username);
                        chat.data.storage.setItem("password", d.data.password);
                        document.title = d.data.username + '-' + document.title;
                        $('#customerServiceUserName').html(d.data.username);
                        chat.loginDiv(d.data);
                        chat.initUsers(d.data.users);
                    }
                    chat.displayError('chatErrorMessage_login', d.msg, 1);
                    break;
                case 2:
                    var s                 = d.data.source + '|' + d.data.originalId + '|' + d.data.openid;
                    d.data.identification = md5(s);
                    var pushParams        = {
                        openid        : d.data.openid,
                        username      : d.data.username,
                        avatar        : d.data.avatar,
                        identification: d.data.identification,
                        originalId    : d.data.originalId,
                        source        : d.data.source,
                        time          : d.data.time,
                        service_status: '<span class="service service_status">服务中...</span>',
                    };
                    if (d.data.mine) {
                        d.data.avatar = '/client/static/images/header_logo.png';
                        chat.addChatLine('mymessage', d.data);
                        $("#chattext").val('');
                        if ($('#user-' + d.data.identification + ' .history').length > 0) {
                            $('#user-' + d.data.identification + ' .service_status').removeClass('history').addClass('service').html('服务中...');
                            $('#end-client').show();
                        }
                    } else {
                        chat.chatAudio();
                        chat.addChatLine('chatLine', d.data);
                        if (!chat.inArray(chat.data.showUsers, s)) {
                            chat.data.showUsers.push(s);
                            //chat.data.serviceUsers.push(cdiv.render('user', pushParams));
                            //$('#conv-lists').html(chat.data.serviceUsers.join(''));
                            $('#conv-lists').removeClass('no-messages');
                            if ($('#conv-lists .select-user-area').length === 0) {
                                $('#conv-lists').html(cdiv.render('user', pushParams));
                                $('#conv-lists .user-area:first').addClass('select-user-area');
                                $('#chat-lists .chatLineHolderBox:first').show();
                                chat.data.selectSource     = d.data.source;
                                chat.data.selectOriginalId = d.data.originalId;
                                chat.data.selectOpenid     = d.data.openid;
                                chat.data.selectUserName   = d.data.username;
                                chat.data.selectAvatar     = d.data.avatar;
                                $('#detailed-information-button').show();
                                $('#source-value').html(d.data.source);
                                $('#originalId-value').html(d.data.originalId);
                                $('#openid-value').html(d.data.openid);
                                $('#end-client').show();
                            } else {
                                $('#conv-lists').append(cdiv.render('user', pushParams));
                            }
                        } else {
                            $('#user-' + pushParams.identification + ' .service_status').removeClass('history').addClass('service').html('服务中...');
                            if (d.data.openid == chat.data.selectOpenid) {
                                $('#end-client').show();
                            }
                        }
                        // 增加消息
                        //chat.showMsgCount(d.data.roomid, 'show');
                        // 新消息颜色闪烁
                        if ((chat.data.setIntervalArr['user-' + pushParams.identification] === undefined) && (chat.data.selectOpenid != d.data.openid)) {
                            chat.data.setIntervalArr['user-' + pushParams.identification] = setInterval(function () {
                                chat.changeColor($('#user-' + pushParams.identification));
                            }, 500);
                        }
                        if (Notification.permission == "granted") {
                            var icon = '';
                            switch (d.data.source) {
                                case 'mini_game':
                                    switch (d.data.originalId) {
                                        case 'gh_a8243226a880':
                                            icon = '/client/static/images/gameicon/xiaofupo.jpg';
                                            break;
                                    }
                                    break;
                            }
                            var notification = new Notification("您有新的消息，请及时查看", {
                                title: '新消息',
                                body : d.data.newmessage,
                                icon : icon
                            });
                            
                            //chat.changeUser();
                        }
                    }
                    break;
                case 3:
                    chat.removeUser('logout', d.data);
                    if (d.data.mine && d.data.action == 'logout') {
                        return;
                    }
                    chat.displayError('chatErrorMessage_logout', d.msg, 1);
                    break;
                case 4: //页面初始化
                    chat.initPage(d.data);
                    break;
                case 5:
                    if (d.data.mine) {
                        chat.displayError('chatErrorMessage_logout', d.msg, 1);
                    }
                    break;
                case 6:
                    if (d.data.mine) {
                        //如果是自己
                        
                    } else {
                        //如果是其他人
                        
                    }
                    //删除旧房间该用户
                    chat.changeUser(d.data);
                    chat.addUserLine('user', d.data);
                    break;
                case 7:// 结束服务
                    // 提示结束成功
                    chat.displayError('chatErrorMessage_login', d.msg, 1);
                    
                    var s              = d.data.source + '|' + d.data.originalId + '|' + d.data.openid;
                    var identification = md5(s);
                    $('#user-' + identification + ' .service_status').removeClass('service').addClass('history').html('历史记录');
                    $('#end-client').hide();
                    break;
                case 8:// 更新等待人数
                    if (d.data.count == 0) {
                        $('#watting-box').hide();
                    } else {
                        $('#watting-count').html(d.data.count);
                        $('#watting-box').show();
                    }
                    break;
                case 9:// 接入成功
                    var s                 = d.data.source + '|' + d.data.originalId + '|' + d.data.openid;
                    d.data.identification = md5(s);
                    var pushParams        = {
                        openid        : d.data.openid,
                        username      : d.data.username,
                        avatar        : d.data.avatar,
                        identification: d.data.identification,
                        originalId    : d.data.originalId,
                        source        : d.data.source,
                        time          : d.data.time,
                        service_status: '<span class="service service_status">服务中...</span>',
                    };
                    chat.chatAudio();
                    chat.addChatLine('chatLine', d.data);
                    if (!chat.inArray(chat.data.showUsers, s)) {
                        chat.data.showUsers.push(s);
                        //chat.data.serviceUsers.push(cdiv.render('user', pushParams));
                        //$('#conv-lists').html(chat.data.serviceUsers.join(''));
                        $('#conv-lists').removeClass('no-messages');
                        if ($('#conv-lists .select-user-area').length === 0) {
                            $('#conv-lists').html(cdiv.render('user', pushParams));
                            $('#conv-lists .user-area:first').addClass('select-user-area');
                            $('#chat-lists .chatLineHolderBox:first').show();
                            chat.data.selectSource     = d.data.source;
                            chat.data.selectOriginalId = d.data.originalId;
                            chat.data.selectOpenid     = d.data.openid;
                            chat.data.selectUserName   = d.data.username;
                            chat.data.selectAvatar     = d.data.avatar;
                            $('#detailed-information-button').show();
                            $('#source-value').html(d.data.source);
                            $('#originalId-value').html(d.data.originalId);
                            $('#openid-value').html(d.data.openid);
                            $('#end-client').show();
                        } else {
                            $('#conv-lists').append(cdiv.render('user', pushParams));
                        }
                    } else {
                        $('#user-' + pushParams.identification + ' .service_status').removeClass('history').addClass('service').html('服务中...');
                        if (d.data.openid == chat.data.selectOpenid) {
                            $('#end-client').show();
                        }
                    }
                    // 新消息颜色闪烁
                    if ((chat.data.setIntervalArr['user-' + pushParams.identification] === undefined) && (chat.data.selectOpenid != d.data.openid)) {
                        chat.data.setIntervalArr['user-' + pushParams.identification] = setInterval(function () {
                            chat.changeColor($('#user-' + pushParams.identification));
                        }, 500);
                    }
                    break;
                default :
                    chat.displayError('chatErrorMessage_logout', d.msg, 1);
            }
        }
    },
    wsOnclose   : function () {
        this.data.wSock.onclose = function (event) {
        }
    },
    wsOnerror   : function () {
        this.data.wSock.onerror = function (event) {
            //alert('服务器关闭，请联系QQ:1335244575 开放测试2');
        }
    },
    showMsgCount: function (roomid, type) {
        if (!this.data.login) {
            return;
        }
        if (type == 'hide') {
            $("#message-" + roomid).text(parseInt(0));
            $("#message-" + roomid).css('display', 'none');
        } else {
            if (chat.data.crd != roomid) {
                $("#message-" + roomid).css('display', 'block');
                var msgtotal = $("#message-" + roomid).text();
                $("#message-" + roomid).text(parseInt(msgtotal) + 1);
            }
        }
    },
    /**
     * 当一个用户进来或者刷新页面触发本方法
     *
     */
    initPage    : function (data) {
        //this.initRooms(data.rooms);
        this.initUsers(data.users);
    },
    /**
     * 填充用户列表
     */
    initUsers   : function (data) {
        console.log(data);
        if (getJsonLength(data)) {
            for (var i = 0; i < data.length; i++) {
                console.log(data[i]);
                var s          = data[i]['source'] + '|' + data[i]['originalId'] + '|' + data[i]['openid'];
                var detailJson = {
                    identification: md5(s),
                    source        : data[i]['source'],
                    originalId    : data[i]['originalId'],
                    openid        : data[i]['openid'],
                    username      : data[i]['username'],
                    avatar        : data[i]['avatar'],
                    time          : data[i]['time'],
                    service_status: data[i]['service_status'],
                };
                console.log(detailJson);
                chat.data.showUsers.push(s);
                chat.data.serviceUsers.push(cdiv.render('user', detailJson));
                $('#conv-lists').removeClass('no-messages');
                $('#conv-lists').html(chat.data.serviceUsers.join(''));
                $('#detailed-information-button').show();
                $('#source-value').html(detailJson.source);
                $('#originalId-value').html(detailJson.originalId);
                $('#openid-value').html(detailJson.openid);
                for (var j = 0; j < data[i]['detail'].length; j++) {
                    var logJosn            = $.parseJSON(data[i]['detail'][j]);
                    logJosn.identification = detailJson.identification;
                    if (logJosn.to.indexOf('|') == '-1') {
                        chat.addChatLine('chatLine', logJosn);
                    } else {
                        logJosn.avatar = '/client/static/images/header_logo.png';
                        chat.addChatLine('mymessage', logJosn);
                    }
                }
            }
            var obj = $('#conv-lists .user-area:first');
            obj.addClass('select-user-area');
            chat.data.selectSource     = obj.attr('source');
            chat.data.selectOriginalId = obj.attr('originalId');
            chat.data.selectOpenid     = obj.attr('openid');
            chat.data.selectUserName   = obj.attr('username');
            chat.data.selectAvatar     = obj.attr('avatar');
            $('#chat-lists .chatLineHolderBox:first').show();
            if (obj.find('.service').html() == '服务中...') {
                $('#end-client').show();
            } else {
                $('#end-client').hide();
            }
        } else {
            $('#conv-lists').html('暂无消息');
            $('#conv-lists').addClass('no-messages');
        }
    },
    /**
     * 1.初始化房间
     * 2.初始化每个房间的用户列表
     * 3.初始化每个房间的聊天列表
     */
    initRooms   : function (data) {
        var rooms     = [];//房间列表
        var userlists = [];//用户列表
        var chatlists = [];//聊天列表
        if (data.length) {
            var display = 'none';
            for (var i = 0; i < data.length; i++) {
                if (data[i]) {
                    //存储所有房间ID
                    this.data.rds.push(data[i].roomid);
                    data[i].selected = '';
                    if (i == 0) {
                        data[i].selected = 'selected';
                        this.data.crd    = data[i].roomid; //存储第一间房间ID，自动设为默认房间ID
                        display          = 'block';//第一间房的用户列表和聊天记录公开
                    }
                    //初始化每个房间的用户列表
                    userlists.push(cdiv.userlists(data[i].roomid, display));
                    //初始化每个房间的聊天列表
                    chatlists.push(cdiv.chatlists(data[i].roomid, display));
                    //创建所有的房间
                    rooms.push(cdiv.render('rooms', data[i]));
                    display = 'none';
                }
            }
            $('.main-menus').html(rooms.join(''));
            $("#user-lists").html(userlists.join(''));
            $("#chat-lists").html(chatlists.join(''));
        }
    },
    loginDiv    : function (data) {
        /*设置当前房间*/
        //this.data.crd = data.roomid;
        /*显示头像*/
        $('.profile').html(cdiv.render('my', data));
        $('#loginbox').fadeOut(function () {
            $('.input-area').fadeIn();
            $('.action-area').fadeIn();
            $('.input-area').focus();
        });
    },
    changeRoom  : function (obj) {
        //未登录
        if (!this.data.login) {
            this.shake();
            chat.displayError('chatErrorMessage_logout', "未登录用户不能查看房间哦～", 1);
            return false;
        }
        var roomid  = $(obj).attr("roomid");
        var userObj = $("#conv-lists-" + roomid).find('#user-' + this.data.fd);
        if (userObj.length > 0) {
            return;
        }
        
        $("#main-menus").children().removeClass("selected");
        $("#user-lists").children().css("display", "none");
        
        $("#chat-lists").children().css("display", "none");
        $("#conv-lists-" + roomid).css('display', "block");
        $(obj).addClass('selected');
        $("#chatLineHolder-" + roomid).css('display', "block");
        var oldroomid  = this.data.crd;
        //设置当前房间
        this.data.crd  = roomid;
        //用户切换房间
        this.data.type = 3;//改变房间
        var json       = {
            "type"     : chat.data.type,
            "name"     : chat.data.name,
            "avatar"   : chat.data.avatar,
            "oldroomid": oldroomid,
            "roomid"   : this.data.crd
        };
        chat.wsSend(JSON.stringify(json));
        
    },
    
    // The addChatLine method ads a chat entry to the page
    
    addChatLine: function (t, params) {
        
        if ($('#chatLineHolder-' + params.identification).length > 0) {
            if (t != 'mymessage') {
                var markup = cdiv.render('newmessage', params);
            } else {
                var markup = cdiv.render(t, params);
            }
            $('#chatLineHolder-' + params.identification).append(markup);
        } else {
            var markup = cdiv.render(t, params);
            $("#chat-lists").append(markup);
        }
        
        this.scrollDiv('chat-lists');
    },
    addUserLine: function (t, params) {
        var markup = cdiv.render(t, params);
        // $('#conv-lists-' + params.roomid).append(markup);
        $('#conv-lists').append(markup);
    },
    removeUser : function (t, params) { //type 1=换房切换，0=退出
        $("#user-" + params.fd).fadeOut(function () {
            $(this).remove();
            $("#chatLineHolder").append(cdiv.render(t, params));
        });
    },
    changeUser : function (obj) {
        //未登录
        if (!this.data.login) {
            this.shake();
            chat.displayError('chatErrorMessage_logout', "登录已过期，请重新登录～", 1);
            return false;
        }
        var objId          = $(obj).attr("id");
        var identification = objId.slice(5);
        var userObj        = $('#conv-lists .user-area');
        if (userObj.length < 1) {
            return;
        }
        
        userObj.removeClass('select-user-area');
        $(obj).addClass('select-user-area');
        chat.data.selectSource     = $(obj).attr('source');
        chat.data.selectOriginalId = $(obj).attr('originalId');
        chat.data.selectOpenid     = $(obj).attr('openid');
        chat.data.selectUserName   = $(obj).attr('username');
        chat.data.selectAvatar     = $(obj).attr('avatar');
        console.log(chat.data);
        clearInterval(chat.data.setIntervalArr[$(obj).attr('id')]);
        $('#chat-lists .chatLineHolderBox').hide();
        $('#chat-lists #chatLineHolder-' + identification).show();
        $('#detailed-information-button').show();
        $('#source-value').html(chat.data.selectSource);
        $('#originalId-value').html(chat.data.selectOriginalId);
        $('#openid-value').html(chat.data.selectOpenid);
        if ($(obj).find('.service').html() == '服务中...') {
            $('#end-client').show();
        } else {
            $('#end-client').hide();
        }
        // console.log(data);
        // $("#conv-lists-" + data.oldroomid).find('#user-' + data.fd).fadeOut(function () {
        //     chat.showMsgCount(data.roomid, 'hide');
        //     $(this).remove();
        //     //chat.addChatLine('logout',data,data.oldroomid);
        // });
    },
    scrollDiv  : function (t) {
        var mai       = document.getElementById(t);
        mai.scrollTop = mai.scrollHeight + 100;//通过设置滚动高度
    },
    remind     : function (obj) {
        console.log('显示用户信息');
    },
    
    // This method displays an error message on the top of the page:
    displayError              : function (divID, msg, f) {
        var elem = $('<div>', {
            id  : divID,
            html: msg
        });
        
        elem.click(function () {
            $(this).fadeOut(function () {
                $(this).remove();
            });
        });
        if (f) {
            setTimeout(function () {
                elem.click();
            }, 5000);
        }
        elem.hide().appendTo('body').slideDown();
    },
    chatAudio                 : function () {
        if ($("#chatAudio").length <= 0) {
            $('<audio id="chatAudio"><source src="./static/voices/notify.ogg" type="audio/ogg"><source src="./static/voices/notify.mp3" type="audio/mpeg"><source src="./static/voices/notify.wav" type="audio/wav"></audio>').appendTo('body');
        }
        $('#chatAudio')[0].play();
    },
    shake                     : function () {
        $("#layout-main").attr("class", "shake_p");
        var shake = setInterval(function () {
            $("#layout-main").attr("class", "");
            clearInterval(shake);
        }, 200);
    },
    off                       : function () {
        document.onkeydown = function (event) {
            if (event.keyCode == 116) {
                event.keyCode      = 0;
                event.cancelBubble = true;
                return false;
            }
        }
    },
    copyright                 : function () {
        console.log("您好！不介意的话可以加QQ讨论学习（1335244575）");
    },
    print                     : function (flag, obj) {
        console.log('----' + flag + ' start-------');
        console.log(obj);
        console.log('----' + flag + ' end-------');
    },
    sendMessageToWx           : function (data) {
        console.log(data);
        $.ajax({
            type   : "POST",
            url    : "/sendMessage.php",
            data   : {originalId: data.originalId, openid: data.openid, content: data.message},
            success: function (res) {
                console.log(res);
                res = $.parseJSON(res);
                if (res.code == 1) {
                    console.log(res);
                } else {
                    $('#chatLineHolder-'+md5(data.source+'|'+data.originalId+'|'+data.openid)+' .msg-box:last .me .msg-bubble-area > div').append('<image style="width: 20px" src="/client/static/images/warn.png" />');
                    console.log(res);
                }
            },
            error  : function (res) {
                console.log(res);
                console.log('系统错误');
            }
        });
    },
    inArray                   : function (arr, obj) {
        var i = arr.length;
        while (i--) {
            if (arr[i] === obj) {
                return true;
            }
        }
        return false;
    },
    changeColor               : function (obj) {
        if (obj.css('background-color') == 'rgba(96, 189, 255, 0.42)') {
            obj.css("background-color", "");
        } else {
            obj.css("background-color", "rgba(96, 189, 255, 0.42)");
        }
    },
    displayDetailedInformation: function () {
        var type = $('#detailed-information').css('display');
        switch (type) {
            case 'block':
                $('#detailed-information').fadeOut('slow', function () {
                    $('#detailed-information-button').html('点击查看用户详细信息（收起）')
                });
                break;
            case 'none':
                $('#detailed-information').fadeIn('slow', function () {
                    $('#detailed-information-button').html('点击查看用户详细信息（展开）')
                });
                ;
                break;
        }
        
    },
    accessClient              : function () {
        if (!this.data.login) return false;
        //发送消息操作
        var text       = '----------------野火客服接入----------------\r\n\r\n很高兴为你服务';
        chat.data.type = 5; //发送消息标志
        var json       = {
            "type"   : chat.data.type,
            "message": text,
        };
        chat.wsSend(JSON.stringify(json));
        return true;
    },
    endClient                 : function () {
        if (!this.data.login) return false;
        //结束服务操作
        chat.data.type = 4; //结束服务标志
        if (chat.data.selectOriginalId == '' || chat.data.selectOpenid == '' || chat.data.selectSource == '') return false;
        var json = {
            "type"      : chat.data.type,
            'originalId': chat.data.selectOriginalId,
            'openid'    : chat.data.selectOpenid,
            'source'    : chat.data.selectSource
        };
        console.log(json);
        chat.wsSend(JSON.stringify(json));
        return true;
    },
}
