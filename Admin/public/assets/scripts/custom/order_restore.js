var YG_Order = {
	orderRestore:function(){
		$('#reservationtime,#canceltime,.tip_time').daterangepicker({
            timePicker: true,
            timePickerIncrement: 5,
            format: 'YYYY-MM-DD HH:mm'
        });
        //全选按钮
        $('.check_all').click(function(event) {
        	var check=$('input[name="check_lock"]');
        	var checked_length=$('input[name="check_lock"]:checked').length;
        	if(!checked_length){
        		$('.checker').find('span').addClass('checked');
        		check.attr("checked","checked");
        	}else{
        		$('.checker').find('span').removeClass('checked');
        		check.removeAttr('checked');
        	}
        });
        //start 批量解锁按钮弹窗
        $('.batch_unlock').click(function(event) {
        	var checked_length=$('input[name="check_lock"]:checked').length;
        	if(checked_length){
        		bootbox.dialog({
	                message: "<div class='tcenter'>解锁成功</div>",
	                title: "提示",
                    animate:false,
	                buttons: {
	                    cancel: {
	                        label: "确定",
	                        className: "btn-gray",
	                        callback: function () {
	                        }
	                    }
	                }
	            });
        	}else{
	        	bootbox.dialog({
	                message: "<div class='tcenter'>请选择要解锁的订单</div>",
	                title: "提示",
                    animate:false,
	                buttons: {
	                    cancel: {
	                        label: "确定",
	                        className: "btn-gray",
	                        callback: function () {
	                        }
	                    }
	                }
	            });
        	}
        // bootbox.dialog({})位置居中的函数
        dialogCenter();
        });
        //end 批量解锁按钮弹窗
        //start 强制解锁弹窗
        $('.deblock').click(function(){
        	 bootbox.dialog({
                message: "<div class='tcenter'>解锁成功</div>",
                title: "提示",
                animate:false,
                buttons: {
                    cancel: {
                        label: "确定",
                        className: "btn-gray",
                        callback: function () {
                        }
                    }
                }
            });
             // bootbox.dialog({})位置居中的函数
             dialogCenter();
	    })
		//end 强制解锁弹窗
		//start 修复弹窗
		$('.btn_restore').click(function(){
			bootbox.dialog({
                message: "<div class='tcenter'>计算可退数量 = 1 (与目前一致无需修复)</div>",
                title: "提示",
                animate:false,
                buttons: {
                    cancel: {
                        label: "确定",
                        className: "btn-gray",
                        callback: function () {
                        }
                    }
                }
            });
            // bootbox.dialog({})位置居中的函数
            dialogCenter();
		})
		//end 修复弹窗
        //窗口改变的时候，设置dialog的垂直位置
        $(window).resize(function(event) {
            dialogCenter();
        });
	}
}
// bootbox.dialog({})位置居中的函数
function dialogCenter(){
    var _w_height=$(window).height();
    var modal_height=$('.modal-content').height();
    var _top=parseInt((_w_height-modal_height)/2);
    $('.modal-dialog').css("marginTop",_top);
}
$(function(){
	App.init();
	G.ExcuteModule('#Order_Restore', YG_Order.orderRestore);
})