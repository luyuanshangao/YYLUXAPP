$(function() {

})
//开始时间
function startingTime(obj) {
    var end_id = 'canceltime';
    WdatePicker({
        readOnly:true,
        maxDate: '#F{$dp.$D('+end_id+')}',
        dateFmt:'yyyy-MM-dd HH:mm:ss',
        onpicked:function () {
            var granularity = $("#granularity").val();
            if(granularity>0){
                var start_time = $(this).val();
                var url = $("#granularity").attr("data-url");
                $.post(url,{'time_type':granularity,'start_time':start_time},function (res) {
                    $("#reservationtime").val(res.start_time_str);
                    $("#canceltime").val(res.end_time_str);
                })
            }
        }
    })
}
//结束时间
function endingTime(obj) {
    WdatePicker({
        readOnly:true,
        maxDate: '%y-%M-%d 23:59:59',
        dateFmt:'yyyy-MM-dd HH:mm:ss',
        onpicked:function () {
            var granularity = $("#granularity").val();
            if(granularity>0){
                var end_time = $(this).val();
                var url = $("#granularity").attr("data-url");
                $.post(url,{'time_type':granularity,'end_time':end_time},function (res) {
                    $("#reservationtime").val(res.start_time_str);
                    $("#canceltime").val(res.end_time_str);
                })
            }
        }
    })
}

$(".start_add_time").click(function(){
    var _this = $(this);
    startingTime(_this);
})

$(".end_add_time").click(function(){
    var _this = $(this);
    endingTime(_this);
})

$("#granularity").change(function () {
    var granularity = $("#granularity").val();
    var url = $("#granularity").attr("data-url");
    $.post(url,{'time_type':granularity},function (res) {
        $("#reservationtime").val(res.start_time_str);
        $("#canceltime").val(res.end_time_str);
    })

})

/*跳转至分配订单消息页面*/
$(".jump-distribution-order-message").click(function () {
    var url = $(this).attr("data-url");
    var admin_user = $(this).attr("data-admin-user");
    var start_time = $("#reservationtime").val();
    var end_time = $("#canceltime").val();
    url = encodeURI(url+"?admin_user="+admin_user+"&distribution_time_start="+start_time+"&distribution_time_end="+end_time);
    window.location.href= url;
})

/*跳转至回复订单消息页面*/
$(".jump-reply-order-message").click(function () {
    var url = $(this).attr("data-url");
    var admin_user = $(this).attr("data-admin-user");
    var reply_type = $(this).attr("data-reply-type");
    var start_time = $("#reservationtime").val();
    var end_time = $("#canceltime").val();
    url = encodeURI(url+"?admin_user="+admin_user+"&reply_type="+reply_type+"&reply_time_start="+start_time+"&reply_time_end="+end_time);
    window.location.href= url;
})

