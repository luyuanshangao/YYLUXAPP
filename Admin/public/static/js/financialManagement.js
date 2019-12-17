$(function () {
    $(".financialManagement").click(function (obj) {
        var id = $(this).attr("id-data");
        var order_number = $(this).attr("ordernumber-data");
        var url = $(this).attr("url-data");
        layer.msg('确定已付款完成？', {
            time: 0 //不自动关闭
            ,btn: ['确定', '取消']
            ,yes: function(index){
                $.post(url,{"id":id,"order_number":order_number},function (res) {
                    if(res.code == 200){
                        layer.msg(res.result, {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        },1500);
                    }else{
                        layer.msg(res.result, {icon: 2});
                    }
                },"json")
            }
        });
    });

    $(".js-verify-op").click(function () {
        var that = $(this),
            title = '',
            btn1 = '',
            id = that.attr('data-id'),
            insurance_id = that.attr('data-insurance-id'),
            url = that.attr('data-url'),
            from = that.attr('data-from'),
            type = that.attr('data-type');
        if (from == '1'){ //关税赔保审核
            if (type == '1'){
                title = '确认审核通过吗？';
                btn1 = '通过';
            }else if (type == '2'){
                title = '确认审核不通过吗？';
                btn1 = '不通过';
            }else{
                layer.msg('错误操作', {icon: 2});
                return false;
            }
        }else if (from == '2'){//关税赔保打款
            if (type == '1'){
                title = '确认操作吗？';
                btn1 = '已打款';
            }else{
                layer.msg('错误操作', {icon: 2});
                return false;
            }
        }
        
        layer.msg(title, {
            time: 0 //不自动关闭
            ,btn: [btn1, '取消']
            ,yes: function(index){
                $.post(url,{"id":id,"type":type,"insurance_id":insurance_id,"from":from},function (res) {
                    if(res.code == 200){
                        layer.msg(res.msg, {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        },1500);
                    }else{
                        layer.msg(res.msg, {icon: 2});
                    }
                },"json")
            }
        });


    });
})