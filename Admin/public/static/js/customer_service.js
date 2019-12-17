var customer_service  = function() {
    function Init(){

    };

    /**
     * 邮箱验证
     * @param email
     * @returns {boolean}
     */
    function isEmail(email){
        var reg = /^([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/;
        var r = email.match(reg);
        if(r==null){
            return false;
        }
        return true;
    }
    /**
    *分类设置页面
     */
    function CustomerService(params) {
         $('.submit-btn').click(function(event) {
             var formData = new FormData($( "#addUserForm" )[0]);
             var captured_amount = $("#captured_amount").val();
             var _Price   = $("#input-amount").val();
             if(parseFloat(_Price)>parseFloat(captured_amount)){
                 if($("#input-paypal").val() == '' && !isEmail($("#input-paypal").val())){
                     $("#input-paypal").focus();
                     $(".paypal-error").html("PayPal格式不正确并且不能为空！");
                     return false;
                 }else {
                     $(".paypal-error").html("");
                 }
             }
             var report_status = $("input[name='report_status']:checked").val();
             if(report_status == 4){
                 layer.confirm('您确定拒绝赔保吗？', {
                     btn: ['确定','取消'] //按钮
                 }, function(){
                     $.ajax({
                         type:"POST",
                         url:"/CustomerService/submit_edit.html",
                         dataType: 'json',
                         data:formData,
                         async: false,
                         cache: false,
                         contentType: false,
                         processData: false,
                         // data:JsonData,
                         success:function(msg){
                             if(msg.code == 200){
                                 layer.msg(msg.result, {icon: 1});

                                 setTimeout(function(){
                                     window.location.href='/CustomerService/CustomsInsurance';
                                     // window.location.reload();
                                 },1500);
                             }else{
                                 layer.msg(msg.result, {icon: 2});
                             }
                         }
                     });
                 });
             }else {
                 $.ajax({
                     type:"POST",
                     url:"/CustomerService/submit_edit.html",
                     dataType: 'json',
                     data:formData,
                     async: false,
                     cache: false,
                     contentType: false,
                     processData: false,
                     // data:JsonData,
                     success:function(msg){
                         if(msg.code == 200){
                             layer.msg(msg.result, {icon: 1});

                             setTimeout(function(){
                                 window.location.href='/CustomerService/CustomsInsurance';
                                 // window.location.reload();
                             },1500);
                         }else{
                             layer.msg(msg.result, {icon: 2});
                         }
                     }
                 });
             }


         })
        $("#order_number").blur(function(){
        // $("#order_number").bind("input propertychange", function() {
            var order_number = $(this).val();
            $.ajax({
                type: "POST",
                url: '/CustomerService/order_number_data.html',
                data: { order_number: order_number },
                dataType: "json",
                cache: false,
                success: function (msg) {
                    if (msg.code == 200) {
                        // $("#pay_time").val(timestampToTime(msg.result.pay_time));
                        $("#pay_time").html(timestampToTime(msg.result.pay_time));
                        $("#exchange_rate").val(msg.result.exchange_rate);
                        $("#TxnID").html(msg.result.TxnID);
                        $(".TxnID").val(msg.result.TxnID);
                        // alert(msg.result.pay_type.trim());
                        $("#pay_type").find('option[name="' + msg.result.pay_type.trim() + '"]').attr("selected","selected");
                        $("#CurrencyCode").find('option[name="' + msg.result.currency_code.trim() + '"]').attr("selected","selected");
                        $("input[name='currency_code']").val(msg.result.currency_code.trim());
                        //来源：1-用户MY提交，2-后台客服提交
                        $("#input-from").val(2);
                        $("#input-amount").val(msg.result.captured_amount.trim());
                        $("#ConvertedPrice").html('$'+msg.result.captured_amount_usd.trim()+'，最大支持 <b>$40（'+msg.result.currency_code+' '+(40*msg.result.exchange_rate).toFixed(2)+'）</b>');
                    } else {
                        $("#pay_time").val();
                        $("#pay_type").attr("selected",'0');
                        layer.msg(msg.result, { icon: 2 });
                    }
                },
                error: function (error) { }
            });
        });
        $("#order_number_for_tariff").blur(function(){
        // $("#order_number").bind("input propertychange", function() {
            var order_number = $(this).val();
            $.ajax({
                type: "POST",
                url: '/CustomerService/order_number_data_for_tariff.html',
                data: { order_number: order_number },
                dataType: "json",
                cache: false,
                success: function (msg) {
                    if (msg.code == 200) {
                        // $("#pay_time").val(timestampToTime(msg.result.pay_time));
                        $("#pay_time").html(timestampToTime(msg.result.pay_time));
                        $("#exchange_rate").val(msg.result.exchange_rate);
                        $("#TxnID").html(msg.result.TxnID);
                        $(".TxnID").val(msg.result.TxnID);
                        // alert(msg.result.pay_type.trim());
                        $("#pay_type").find('option[name="' + msg.result.pay_type.trim() + '"]').attr("selected","selected");
                        $("#CurrencyCode").find('option[name="' + msg.result.currency_code.trim() + '"]').attr("selected","selected");
                        $("input[name='currency_code']").val(msg.result.currency_code.trim());
                        //来源：1-用户MY提交，2-后台客服提交
                        $("#input-from").val(2);
                        $("#input-amount").val(msg.result.captured_amount.trim());
                        $("#ConvertedPrice").html('$'+msg.result.captured_amount_usd.trim()+'，最大支持 <b>$40（'+msg.result.currency_code+' '+(40*msg.result.exchange_rate).toFixed(2)+'）</b>');
                    } else {
                        $("#pay_time").val();
                        $("#pay_type").attr("selected",'0');
                        layer.msg(msg.result, { icon: 2 });
                    }
                },
                error: function (error) { }
            });
        });

        $("#input-amount").blur(
            function () {
                var amount = $(this).val();
                var captured_amount = $("#captured_amount").val();
                // if(amount>captured_amount){
                //     if(!$(".paypal").hasClass("hide")){
                //         $(".paypal").addClass("hide");
                //     }
                // }else {
                //     $(".paypal").removeClass("hide")
                // }
            }
        )
        //费率转换
        $("#input-amount").bind("input propertychange", function() {
             var _Price   = $("#input-amount").val();
             var _exchange_rate  = $("#exchange_rate").val();
             var _currency_code  = $("input[name='currency_code']").val();
             var _amount = Math.round(_Price/_exchange_rate,3);
             if(!_exchange_rate){
                return;
             }

            /**
             * ${$CustomsInsurance['ConvertedPrice']|default=0}，最大支持
             <b>$40
             （{$list['currency_code']|default='USD'} {php}echo sprintf("%.2f",$CustomsInsurance['exchange_rate'] * 40){/php}）
             </b>
             */
            $("#ConvertedPrice").html('$'+_amount+'，最大支持<b> $40（'+ _currency_code +' '+(40*_exchange_rate).toFixed(2)+'）</b>');
             /*if(_amount>40){
                $("#ConvertedPrice").html('<label  style="color: red" >'+_amount+'(已超出40美元)</label>');
             }else{
                $("#ConvertedPrice").html(_amount);
             }*/

        });
         //时间戳转成时间
         function timestampToTime(timestamp) {
            var date = new Date(timestamp * 1000);//时间戳为10位需*1000，时间戳为13位的话不需乘1000
            var Y = date.getFullYear() + '-';
            var M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
            var D = date.getDate()<10 ? '0'+ date.getDate() : date.getDate() + ' ';
            // var D = date.getDate() + ' ';
            var h = date.getHours()<10? ' 0'+ date.getHours()+ ':':date.getHours() + ':';
            // var h = date.getHours() + ':';
            var m = date.getMinutes()<10 ? '0'+ date.getMinutes()+ ':':date.getMinutes() + ':';
            // var m = date.getMinutes() + ':';
            var s = date.getSeconds()<10 ? '0'+ date.getSeconds():date.getSeconds();
            // var s = date.getSeconds();
            return Y+M+D+h+m+s;
        }

        //切换物流信息
        $('.js-logistic-tabs').click(function(){
            var that = $(this),
                _index = $('.js-logistic-tabs').index(that);
            that.addClass('curr').siblings().removeClass('curr');
           $('.layui-timeline').eq(_index).removeClass('hide').siblings('.layui-timeline').addClass('hide');
        });
    };

    $(function(){
        Init();
    });
    return {
        CustomerService:CustomerService,
    }
}();