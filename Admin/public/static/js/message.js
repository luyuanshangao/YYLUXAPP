var sendMessage = function() {
    /**
     * 初始化函数
     */
    function Init(){
        $("input[name='userType']").change(function () {
            if($(this).val() == 1){
                $(".user-zhmc").find("label").html("账户名称：");
                $(".user-zhmc").find("textarea").attr("placeholder","请输入用户名称，多个请用,分隔");
            }else if($(this).val() == 2) {
                $(".user-zhmc").find("label").html("账户ID：");
                $(".user-zhmc").find("textarea").attr("placeholder","请输入用户ID，多个请用,分隔");
            }else {
                $(".user-zhmc").find("label").html("账户Email：");
                $(".user-zhmc").find("textarea").attr("placeholder","请输入用户Email，多个请用,分隔");
            }
        })

        $("input[name='MessageType']").change(function () {
            if($(this).val() == 1){
                $('.send-znx').removeClass('hide');
                $('.send-email').addClass('hide');
            }else {
                $('.send-znx').addClass('hide');
                $('.send-email').removeClass('hide');
            }
        });

        $(".send-message").click(function () {
            var sendType = $("input[name='sendType']:checked").val();
            var userType = $("input[name='userType']:checked").val();
            var MessageType = $("input[name='MessageType']:checked").val();
            var userData = $("#userData").val();
            var content = $("#content").val();
            var Remark = $("#Remark").val();
            var title = $("#title").val();
            if(userData.length<1){
                if(userType == 1){
                    layer.msg("账号名不能为空");
                }else if(userType == 2){
                    layer.msg("账号id不能为空");
                }else {
                    layer.msg("账号Email不能为空");
                }
            }
            if(MessageType == 1){
                if(Remark.length<1){
                    layer.msg("发送内容不能为空");
                }
            }else {
                if(content.length<1){
                    layer.msg("发送内容不能为空");
                }
            }
            if(title.length<1){
                layer.msg("发送标题不能为空");
            }
            var url = '';
            $.post(url,{"sendType":sendType,"userType":userType,"MessageType":MessageType,"userData":userData,"content":content,"Remark":Remark,"title":title},function (res) {
                if(res.code==200){
                    layer.msg(res.msg,{'icon':6,'time':2000},function () {
                        window.location.reload();
                    });
                }else {
                    layer.msg(res.msg,{'icon':5});
                }
            })
        })
    };

    /*
    *发送消息页面
     */
    function sendMessage() {

    };
    /*获取订单信息*/

    $(function(){
        Init();
    });
    return {
        sendMessage:sendMessage
    }
}();