var YG_Order = {
    orderList: function (dModule) {
        $('#reservationtime,#canceltime').daterangepicker({
            timePicker: true,
            timePickerIncrement: 5,
            format: 'YYYY-MM-DD HH:mm'
        });
        $('.group-del').on('click', function (e) {
            e.preventDefault();
            //提示信息开始
            bootbox.dialog({
                message: "<div class='tcenter'>此商品售完，不支持十天补差价！</div>",
                title: "提示",
                animate: false,
                buttons: {
                    ok: {
                        label: "确定",
                        className: "btn-qing",
                        callback: function () {
                            //弹窗，自定义窗口开始
                            $.get("cou_gift_card.html", function(data){
                            var sa = bootbox.dialog({
                                message: data,
                                title: "发送补差礼品卡",
                                width:1000,
                                animate: false,
                                buttons: {
                                    ok: {
                                        label: "确定",
                                        className: "btn-qing",
                                        callback: function () {
                                        }
                                    },
                                    cancel: {
                                        label: "取消",
                                        className: "btn-gray",
                                        callback: function () {
                                        }
                                    }
                                },
                            });
                            //回调函数，调用弹窗位置居中的函数
                            dialogCenter();
                            });
                            //弹窗，自定义窗口结束
                        }
                    },
                    cancel: {
                        label: "取消",
                        className: "btn-gray",
                        callback: function () {
                        }
                    }
                }
            });
            //弹窗，提示框结束
            dialogCenter();//弹窗垂直和左右居中
        });
        //start优惠券查询，优惠券方案弹窗
     $('.coupon_scheme').on("click",function(){
       //start bootbox.dialog
       $.get("coupon_scheme.html", function(data){
            bootbox.dialog({
                message: data,
                title: "优惠券方案详情",
                width:720,
                animate: false,
                buttons: {
                    cancel: {
                        label: "关闭",
                        className: "btn-gray",
                        callback: function () {
                        }
                    }
                }
            });
            //回调函数，调用弹窗位置居中的函数
            dialogCenter();

        });
       //end bootbox.dialog
     })
     //end优惠券查询，优惠券方案弹窗

     //start优惠券作废查看
     $('.coupon_check_void').on("click",function(){
        //start bootbox.dialog
       $.get("coupon_check_void.html", function(data){
            bootbox.dialog({
                message: data,
                title: "优惠券使用情况",
                width:720,
                animate: false,
                buttons: {
                    cancel: {
                        label: "关闭",
                        className: "btn-gray",
                        callback: function () {
                        }
                    }
                }
            });
            //回调函数，调用弹窗位置居中的函数
            dialogCenter();
        });
       //end bootbox.dialog
     })
     //end优惠券作废查看
        $(window).resize(function(event) {
            dialogCenter();
        });
    }
};

// bootbox.dialog({})位置居中的函数
function dialogCenter(){
    var _w_height=$(window).height();
    var modal_height=$('.modal-content').height();
    var _top=parseInt((_w_height-modal_height)/2);
    $('.modal-dialog').css("marginTop",_top);
}
$(function () {
    App.init();
    G.ExcuteModule('#Order_List', YG_Order.orderList);
})