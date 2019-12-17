/**
 * Created by guo.r on 2014/12/4.
 */
var YG_Order = {
    orderList: function (dModule) {
        moment.locale('zh-cn');
        $('#reservationtime,#canceltime,.tip_time').daterangepicker({
            timePicker: true,
            timePickerIncrement: 5,
            format: 'YYYY-MM-DD HH:mm'
        });
        //重置按钮
        $('.btn_reset').on("click",function(){
            var form_arr=$('.frm_search .form-control');
            $.each(form_arr,function(index,val){
                $(this).val("");
            })
        })
    }
};

$(function () {
    App.init();
    G.ExcuteModule('#Order_List', YG_Order.orderList);
    G.ExcuteModule('#Order_Details', YG_Order.orderDtails);
})