/*创建div dom*/
var cdiv = {
    userlists: function (roomid, is_none) {
        var arr = [];
        arr     = ['<div class="conv-lists" id="conv-lists-', roomid, '" style="display:', is_none, '"></div>'];
        return arr.join('');
    },
    chatlists: function (roomid, is_none) {
        var arr = [];
        arr     = ['<div class="msg-items" id="chatLineHolder-' + roomid + '" style="display:', is_none, '"></div>'];
        return arr.join('');
    },
    render   : function (template, params) {
        var arr = [];
        switch (template) {
            case 'mymessage':
                arr = [
                    '<div style="display: block;" class="msg-box"><div class="chat-item me"><div class="clearfix"><div class="avatar"><div class="normal user-avatar" style="background-image: url(', params.avatar, ');"></div></div><div class="msg-bubble-box"><div class="msg-bubble-area"><div><div class="msg-bubble"><pre class="text">', params.newmessage, '</pre></div></div></div></div></div></div></div>'
                ];
                break;
            
            case 'chatLine':
                arr = [
                    '<div class="msg-items chatLineHolderBox" id="chatLineHolder-' + params.identification + '" style="display: none;"><div style="display: block;" class="msg-box"><div class="chat-item not-me"><div class="chat-profile-info clearfix"><span class="profile-wrp"><span class="name clearfix"><span class="name-text">', params.username, '</span></span></span><span class="chat-time">', params.time, '</span></div><div class="clearfix"><div class="avatar"><div class="normal user-avatar" onclick="chat.remind(this)" fd="', params.fd, '" uname="', params.username, '" style="background-image: url(\'', params.avatar, '\');"></div></div><div class="msg-bubble-box"><div class="msg-bubble-area"><div class="msg-bubble"><pre class="text">', params.newmessage, '</pre></div></div></div></div></div></div></div>'
                ];
                break;
            
            case 'newmessage':
                arr = [
                    '<div style="display: block;" class="msg-box"><div class="chat-item not-me"><div class="chat-profile-info clearfix"><span class="profile-wrp"><span class="name clearfix"><span class="name-text">', params.username, '</span></span></span><span class="chat-time">', params.time, '</span></div><div class="clearfix"><div class="avatar"><div class="normal user-avatar" onclick="chat.remind(this)" fd="', params.fd, '" uname="', params.username, '" style="background-image: url(\'', params.avatar, '\');"></div></div><div class="msg-bubble-box"><div class="msg-bubble-area"><div class="msg-bubble"><pre class="text">', params.newmessage, '</pre></div></div></div></div></div></div>'
                ];
                break;
            
            case 'user':
                arr = [
                    '<div id=\'user-', params.identification, '\' onclick="chat.changeUser(this)" openid="', params.openid, '" source="', params.source, '" originalId="', params.originalId, '" username="', params.username, '" avatar="', params.avatar, '"class="list-item conv-item context-menu conv-item-company user-area"><i class="iconfont icon-delete-conv tipper-attached"></i><div class="avatar-wrap"><div class="group-avatar"><div class="normal group-logo-avatar" style="background-image: url(', params.avatar, ');"></div></div></div><div class="conv-item-content"><div class="title-wrap info"><div class="name-wrap"><p class="name">', params.username, '</p></div><span class="time">', params.time, '</span></div>',params.service_status,'</div></div>'
                ];
                break;
            case 'newlogin':
                arr = [
                    '<div class="chat-status chat-system-notice">系统消息：欢迎&nbsp;', params.username, '&nbsp;加入了对话</div>'
                ];
                break;
            case 'logout':
                arr = [
                    '<div class="chat-status chat-system-notice">系统消息：&nbsp;', params.username, '&nbsp;退出了对话</div>'
                ];
                break;
            case 'my':
                arr = [
                    '<div class="big-52 with-border user-avatar" uid="', params.fd, '" title="', params.username, '" style="background-image: url(', params.avatar, ');"></div>'
                ];
                break;
            case 'rooms':
                arr = [
                    '<li class="menu-item ', params.selected, '" roomid="', params.roomid, '" onclick="chat.changeRoom(this)">', params.roomname, '<span id="message-', params.roomid, '">0</span></li>'
                ];
                break;
        }
        return arr.join('');
    }
    
}
