$(function(){
	//After-sale type 类型选择
	$("#type-select").change(function(){
		var $this = $(this),
			selected = $this.find("option:selected"),
			val = selected.val(),
			ind = selected.index(),
			reason = after_config_json[ind].reason,
			html = '';
			
		$.each(reason,function(n,v){
			html += '<option value="'+ v.code +'">'+ v.en_name +'</option>';
		})
		$("#reason-select").html(html);
		$(".reason-dl").removeClass("hide");

		if(val == 3){
			var refundedTypeSelect = $("#refunded-type-select"),
				optionLen = refundedTypeSelect.find("option").length;
			if(optionLen < 1){
				var refundedType = after_config_json[ind].refunded_type,
				htmls = '';
				$.each(refundedType,function(k,name){
					htmls += '<option value="'+ name.code +'">'+ name.en_name +'</option>';
				})
				$("#refunded-type-select").html(htmls);
			}
			$(".refunded-type-dl").removeClass("hide");
		}else{
			$(".refunded-type-dl").addClass("hide");
		}
	});
	//自定义填写退款价格
	$(".edit-price").click(function () {
		if($(this).hasClass("addprice")){
            $("#edit-price").removeClass("hide");
            $(this).removeClass("addprice");
            $(this).html("使用订单总价");
            $("input[name='captured_refunded_fee']").val("");
		}else {
            $("#edit-price").addClass("hide");
            $(this).addClass("addprice");
            $(this).html("填写退款价格");
		}

    })
	/*$("input[name='orderitem.checkbox']").change(function () {
        $("input[name='orderitem.checkbox']:checked");
    })*/
    //submit
	$(".saleafter-submit-btn").click(function () {
        if($(this).hasClass("arrest")){
            $(this).removeClass("btn-qing");
            $(this).addClass("btn-gray");
            return false;
        }
        var item = [], 
       	 	params = {},
       	 	imgs = [],
       	 	li = $("#uploadCustorVoucherList .uploader-list"),
        	order = $("#order"),
        	describe = $(".describe"),
        	describeErr = describe.siblings(".err"),
        	orderitem = $("input:checkbox[name='orderitem.checkbox']:checked");
        	captured_refunded_fee_max = $("input[name='captured_refunded_fee']").attr("max");
        	captured_refunded_fee = $("input[name='captured_refunded_fee']").val();
            if(captured_refunded_fee =='' || isNaN(captured_refunded_fee) || captured_refunded_fee == 0){
                layer.alert("退款金额请输入数字,并且不能等于0");return false;
            }
        for(var k=0;k<orderitem.length;k++){
            var arr = {};

            arr.product_id = orderitem.eq(k).attr("data-product-id");
            arr.sku_id = orderitem.eq(k).attr("data-sku-id");
            arr.sku_num = orderitem.eq(k).attr("data-sku-num");
            arr.product_name = orderitem.eq(k).attr("data-product-name");
            arr.product_img = orderitem.eq(k).attr("data-product-img");
            arr.product_attr_ids = orderitem.eq(k).attr("data-product-attr-ids");
            arr.product_attr_desc = orderitem.eq(k).attr("data-product-attr-desc");
            arr.product_nums = orderitem.eq(k).attr("data-product-nums");
            arr.product_price = orderitem.eq(k).attr("data-product-price");
            item.push(arr);
        };
        if(parseFloat(captured_refunded_fee)>parseFloat(captured_refunded_fee_max) || parseFloat(captured_refunded_fee)<=0){
            $("input[name='captured_refunded_fee']").focus();
            $(".captured-refunded-fee-err").removeClass('hide');
            return false;
        }else {
            $(".captured-refunded-fee-err").addClass('hide');
        }
        //描述
        if(!describe.val()){
        	describe.focus();
        	describeErr.removeClass('hide');
        	return false;
        }else{
			describeErr.addClass('hide');
        }


        if(li.length > 0){
        	for(var i=0;i<li.length;i++){
        		src = li.eq(i).find(".upload-state-done").attr("data-upload-images");
        		if(src){
        			imgs.push(src);
        		}
        	}
        }
        
        /**
		 * //TODO
         * params data.
         * [
         *  'order_id'=>,
         *  'order_number'=>,
         *  'customer_id'=>, //后端拼
         *  'customer_name'=>, //后端拼
         *  'store_id'=>,
         *  'store_name'=>,
         *  'payment_txn_id'=>,
         *  'type'=>,
         *  'refunded_type'=>,
         *  'after_sale_reason'=>,
         *  'imgs'=>, //申请图片，json数组保存
         *  'remarks'=>, //描述
         *  'refunded_fee'=>, //退款金额（退款时有）-后端拼，根据选择的产品
         *  'captured_refunded_fee'=>, //实际退款金额（退款时有）-后端拼，根据选择的产品
         *  'item'=>[
         *              [
         *                  'product_id'=>,
         *                  'sku_id'=>,
         *                  'sku_num'=>, //产品表sku编码
         *                  'product_name'=>,
         *                  'product_img'=>,
         *                  'product_attr_ids'=>,
         *                  'product_attr_desc'=>,
         *                  'product_nums'=>, //商品（sku）数量
         *                  'product_price'=>, //商品售价（sku单价）
         *              ],
         *      ],
         * ]
         */
        
        params.order_id = order.attr("data-id");
        params.order_number = order.html();
        params.store_id = order.attr("data-store-id");
        params.customer_id = order.attr("data-customer-id");
        params.customer_name = order.attr("data-customer-name");
        params.payment_txn_id = order.attr("data-transaction-id");
        params.store_name = order.attr("data-store-name");
        params.order_number = order.attr("data-order-number");
        params.type = $("input[name='type']").val();
        params.refunded_type = $("select[name='refunded_type'] option:selected").val()?$("select[name='refunded_type'] option:selected").val():0;
        params.after_sale_reason = $("select[name='after_sale_reason'] option:selected").val();
        params.imgs = imgs;
        params.remarks = $(".describe").val();
        params.captured_refunded_fee = $("input[name='captured_refunded_fee']").val();
        params.item = item;
        var index = layer.load();
        var obj = this;
        $(obj).removeClass("btn-qing");
        $(obj).addClass("btn-gray");
        $(obj).addClass("arrest");
        $.post( ajax_url.async_submitRefund, params, function (data) {
            layer.close(index);
            if(data.code == 200){
            	//Global.showAndHide($('.afterSaleApply-dialog'),true);
            	layer.msg(data.msg,{'icon':1});
	            setTimeout(function(){
                    window.location.reload();
                },1000);
            }else{
            	layer.msg(data.msg,{"icon":5});
                $(obj).removeClass("btn-gray");
                $(obj).addClass("btn-qing");
                $(obj).removeClass("arrest");
            	/*$(".dialog-cont").css({"width":"300px"});
            	$('.afterSaleApply-dialog').find('.cont').html('<i class="iconfontmy icon-cuowu1 red mr5 f18 tmiddle"></i>' + '<span class="red fb">' + data.msg + '</span>');
            	Global.showAndHide($('.afterSaleApply-dialog'),true);
                setTimeout(function(){
                    Global.showAndHide($('.afterSaleApply-dialog'));
                },2000);*/
            }
        });
    });
});
function num(obj){
    obj.value = obj.value.replace(/[^\d.]/g,""); //清除"数字"和"."以外的字符
    obj.value = obj.value.replace(/^\./g,""); //验证第一个字符是数字
    obj.value = obj.value.replace(/\.{2,}/g,"."); //只保留第一个, 清除多余的
    obj.value = obj.value.replace(".","$#$").replace(/\./g,"").replace("$#$",".");
    obj.value = obj.value.replace(/^(\-)*(\d+)\.(\d\d).*$/,'$1$2.$3'); //只能输入两个小数
}