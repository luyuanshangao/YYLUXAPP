var YG_Customer={
	customerDistribution:function(){
		$('.distributor_selection').click(function(){
			//start bootbox.dialog
	       $.get("distributor_information.html", function(data){
	            bootbox.dialog({
	                message: data,
	                title: "选择分销商账号",
	                width:1000,
	                animate:false,
	                buttons: {
	                    cancel: {
	                        label: "关闭",
	                        className: "btn-gray",
	                        callback: function () {
	                        }
	                    }
	                }
	            });
	             // bootbox.dialog({})位置居中的函数
	        	dialogCenter();
	        });
	       //end bootbox.dialog
		})

       //start 款色编码bootbox.dialog
       	$('#color_cod').click(function(){
       		var span_p_val=$('#span_p').html();
       		if(!span_p_val){
	       		bootbox.dialog({
	                message: "<div class='tcenter'>为了计算会员优惠，请先查询出会员信息！</div>",
	                title: "提示",
	                animate:false,
	                buttons: {
	                    cancel: {
	                        label: "关闭",
	                        className: "btn-gray",
	                        callback: function () {
	                        }
	                    }
	                }
	            });
            }else{
            	var color_val=$("#color_value").val();
            	if(!color_val){
        			bootbox.dialog({
		                message: "<div class='tcenter'>供应商款色编码不能为空!</div>",
		                title: "提示",
		                animate:false,
		                buttons: {
		                    cancel: {
		                        label: "关闭",
		                        className: "btn-gray",
		                        callback: function () {
		                        }
		                    }
		                }
		            });
            	}else{
		            bootbox.dialog({
		                message:"<div class='tcenter'>选择货品</div>",
		                title: "选择货品",
		                animate:false,
		                buttons: {
		                    cancel: {
		                        label: "关闭",
		                        className: "btn-gray",
		                        callback: function () {
		                        }
		                    }
		                }
		            });
            	}
            }
            // bootbox.dialog({})位置居中的函数
        	dialogCenter();
       	})
       //end 款色编码bootbox.dialog

       //start 款色编码bootbox.dialog
       	$('#goods_cod').click(function(){
       		var span_p_val=$('#span_p').html();
       		if(!span_p_val){
	       		bootbox.dialog({
	                message: "<div class='tcenter'>为了计算会员优惠，请先查询出会员信息！</div>",
	                title: "提示",
	                animate:false,
	                buttons: {
	                    cancel: {
	                        label: "关闭",
	                        className: "btn-gray",
	                        callback: function () {
	                        }
	                    }
	                }
	            });
            }else{
            	var color_val=$("#color_value").val();
           		$.get("goods_details.html", function(data){
		            bootbox.dialog({
		                message: data,
		                title: "商品详情",
		                width:1000,
		                animate:false,
		                buttons: {
		                    cancel: {
		                        label: "关闭",
		                        className: "btn-gray",
		                        callback: function () {
		                        }
		                    }
		                }
		            });
		            dialogCenter();
		        });
            }
            // bootbox.dialog({})位置居中的函数
        	dialogCenter();
       	})
       //end 款色编码bootbox.dialog

       //start 导入功能bootbox.dialog
       $('#path').change(function(event) {
       	 var path_val=$(this).val();
       	 var point = path_val.lastIndexOf("."); 
       	 var type = path_val.substr(point);
       	 $('#upfile').html(path_val);
       	 if(type=='.csv'){
       	 	//---------start bootbox.dialog----------
       	 	bootbox.dialog({
                message: "<div class='tcenter'>确定要导入该文件吗？</div>",
                title: "提示",
                animate:false,
                buttons: {
                	ok: {
                        label: "确定",
                        className: "btn-qing",
                        callback: function () {
                        }
                    },
                    cancel: {
                        label: "关闭",
                        className: "btn-gray",
                        callback: function () {
                        }
                    }
                }
            });
       	 	//---------end bootbox.dialog------
       	 }else{
       	 	bootbox.dialog({
                message: "<div class='tcenter'>请选择csv文件</div>",
                title: "提示",
                animate:false,
                buttons: {
                    cancel: {
                        label: "关闭",
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
       //end 导入功能bootbox.dialog
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
	G.ExcuteModule('#Customer_Distribution', YG_Customer.customerDistribution);
})