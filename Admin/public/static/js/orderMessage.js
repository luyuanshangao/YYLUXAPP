/*分配订单消息*/
function distribution(obj) {
    var ids = new Array();
    $.each($('input:checkbox:checked'),function(){
        if($(this).val() != 'on'){
            ids.push($(this).val());
        }
    });
    var distribution_admin_id = $(".distribution_admin_user").val();
    var distribution_admin = $(".distribution_admin_user").find("option:selected").text();
    var url = $(obj).attr("post-url");
    $.post(url,{'ids':ids,'distribution_admin_id':distribution_admin_id,'distribution_admin':distribution_admin},function (res) {
        if(res.code == 200){
            if(res.code == 200){
                layer.msg(res.msg,{"icon":6,"time": 2000},function () {
                    window.location.reload();
                });
            }else {
                layer.msg(res.msg,{"icon":5});
            }
        }
    })
}

/*设置紧急*/
function crash(obj) {
    var ids = new Array();
    $.each($('input:checkbox:checked'),function(){
        if($(this).val() != 'on'){
            ids.push($(this).val());
        }
    });
    var url = $(obj).attr("post-url");
    $.post(url,{'ids':ids},function (res) {
        if(res.code == 200){
            if(res.code == 200){
                layer.msg(res.msg,{"icon":6,"time": 2000},function () {
                    window.location.reload();
                });
            }else {
                layer.msg(res.msg,{"icon":5});
            }
        }
    })
}

//添加物流
function reply_order_message(e,lengthS,wideS,order_id){
    var _length = lengthS ? lengthS : '680px',
        _width = wideS ? wideS :'600px';
    $.get(e, function (data) {
        layui.use('layer', function(){
            var layer = layui.layer;
            layer.open({
                title: "回复",
                content: data,
                type: 1,
                area: [_length,_width],
                offset: '10px',
                zIndex:10,
                btn: ["回复","已解决", "取消"],
                success: function(layero){
                    layero.find('.layui-layer-btn').css('text-align', 'center'); //改变位置
                },
                yes: function (index) {
                    var formData =new FormData($( "#ReplyOrderMessage" )[0]);//:nth-child(3)
                    $.ajax({
                        type:"POST",
                        url:e,
                        dataType: 'json',
                        data:formData,
                        async: false,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success:function(msg){
                            if(msg.code == 200){
                                layer.msg(msg.msg, {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                },1500);
                            }else{
                                layer.msg(msg.msg, {icon: 2});
                            }
                        }
                    });
                },
                btn2: function(index){
                    var formData =new FormData($( "#ReplyOrderMessage" )[0]);//:nth-child(3)
                    var url = "/OrderMessage/solved_order_message";
                    $.ajax({
                        type:"POST",
                        url:url,
                        dataType: 'json',
                        data:formData,
                        async: false,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success:function(msg){
                            if(msg.code == 200){
                                layer.msg(msg.msg, {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                },1500);
                            }else{
                                layer.msg(msg.msg, {icon: 2});
                            }
                        }
                    });
                    return false;
                },
                cancel: function () {
                }
            });
        });
    });
    // console.log(2);
}

function order_order_number() {
    var order_order_number = $("#order_order_number").val();
    if(order_order_number.length<1 || order_order_number == 2){
        $("#order_order_number").val(1);
    }else {
        $("#order_order_number").val(2);
    }
    $("#navbar").submit();
}

function order_captured_amount_usd() {
    var order_captured_amount_usd = $("#order_captured_amount_usd").val();
    if(order_captured_amount_usd.length<1 || order_captured_amount_usd == 2){
        $("#order_captured_amount_usd").val(1);
    }else {
        $("#order_captured_amount_usd").val(2);
    }
    $("#navbar").submit();
}