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
    $.post(url,{'Ids':ids,'DistributionAdminId':distribution_admin_id,'DistributionAdmin':distribution_admin},function (res) {
        if(res.code == 200){
            layer.msg(res.msg,{"icon":6,"time": 2000},function () {
                window.location.reload();
            });
        }else {
            layer.msg(res.msg,{"icon":5});
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

$(function () {
    $(".export-data").click(function () {
        $("#is_export").val(1);
        $("#navbar").submit();
    })

    $(".query-data").click(function () {
        $("#is_export").val(0);
        $("#navbar").submit();
    })
})