var rma = function() {
     /**
     * 初始化函数
     */
    function Init(){

    };
    /**
    order index page
    */
    function CreateRma(){
        /*添加SUK*/
         $("#add_sku").click(function (obj) {
            var sku_code = $("#sku_code").val();
			var store_id = $("#store_id").val();
            var url = $(this).attr('url-data');
            var currency_code = $('#currency_code').val();
            $.post(url,{'sku_code':sku_code,'currency_code':currency_code,'store_id':store_id},function (res) {
                console.log(res);
                if(res.code == 200){
                    if($(".js-sku-id[data-sku='"+res.data.sku._id+"']").length>0){
                        layer.msg("请不要重复添加SKU", {icon: 2});return false;
                    }
                    var store_id = $("#store_id").val();
                    if(store_id>0){
                        if(res.data.StoreID != store_id){
                            layer.msg("产品店铺不一样，请根据店铺分开创建订单", {icon: 2});return false;
                        }
                    }else{
                        $("#store_id").val(res.data.StoreID);
                        $("#store_name").val(res.data.StoreName);
                    }
                    var html = '<tr><td><input type="checkbox" class="single-checkbox js-sku-id" data-sku="'+res.data.sku._id+'" name="goods['+res.data.sku._id+'][sku_id]" value="'+res.data.sku._id+'"></td>';
                    html += '<td>'+res.data.Title+'</td>';
                    html += '<td><span class="js-sales-price-span" data-sku="'+res.data.sku._id+'">'+res.data.sku.TransSalesPrice+'</span></td>';
                    html += '<td>'+res.data.sku.attr_desc+'</td>';
                    html += '<td><input type="number" class="form-control input-medium w50 center-block js-number" data-sku="'+res.data.sku._id+'"  name="goods['+res.data.sku._id+'][product_nums]" value="1"></td>';
                    html += '<td>'+res.data.sku.Code+'</td>';
                    html += '<td><input type="number" class="form-control input-medium w80 center-block js-captured-price" data-sku="'+res.data.sku._id+'"  name="goods['+res.data.sku._id+'][captured_price]" value="'+res.data.sku.TransSalesPrice+'"></td>';
                    /*html += '<td><input type="text" class="form-control input-medium w150 center-block js-remark" data-sku="'+res.data.sku._id+'" name="goods['+res.data.sku._id+'][sku_remark]" value=""></td>';*/
                    html += '<input type="hidden" class="js-sales-price" name="goods['+res.data.sku._id+'][SalesPrice]" data-sku="'+res.data.sku._id+'" value="'+res.data.sku.TransSalesPrice+'">';
                    html += '<input type="hidden" class="js-usd-sales-price" name="goods['+res.data.sku._id+'][UsdSalesPrice]" data-sku="'+res.data.sku._id+'" value="'+res.data.sku.SalesPrice+'">';
                    html += '<input type="hidden" class="js-product-id" name="goods['+res.data.sku._id+'][product_id]" data-sku="'+res.data.sku._id+'" value="'+res.data._id+'">';
                    html += '</tr>';
                    $('#product-data-list').append(html);
                    Common.AllSelect($('.selectAll'),$('.single-checkbox'));
                }else {
                    layer.msg(res.msg, {icon: 2});
                }
            })
         })

        /*币种切换*/
        $("#currency_code").change(function () {
            var currency_code = $(this).val();
            var url = $(this).attr('data-url');
            $('.shipping_currency_code').text(currency_code);

            $.post(url,{'currency_code':currency_code},function (res) {
                $('#currency_rate').val(res);
                CurrencyConversion();
            })
        })

        /*币种转换计算产品价格显示*/
        function CurrencyConversion() {
            var currency_rate = $("#currency_rate").val();
            $("#product_table").find(".js-sku-id").each(function (k,v) {
                var sku_id = $(v).val();
                var SalesPrice = parseFloat($(".js-usd-sales-price[data-sku='"+sku_id+"']").val()*parseFloat(currency_rate)).toFixed(2);
                var number = parseInt($(".js-number[data-sku='"+sku_id+"']").val());
                var captured_price = (SalesPrice*number).toFixed(2);
                $(".js-sales-price-span[data-sku='"+sku_id+"']").text(SalesPrice);
                $(".js-captured-price[data-sku='"+sku_id+"']").val(captured_price);
                CalculatedTotalPrice();
            });
        }

        /*选择国家*/
        $("#country_code").change(function () {
            var country = $("#country_code").find("option:selected").text()
            $("#country").val(country);
            GetState();
        })
        
        function GetState(state_code,state) {
            var code = $("#country_code").val();
            $("#state_code").val('');
            var url = $("#country_code").attr('data-url');
            $.post(url,{'code':code},function (res) {
                if(res != false){
                    var state_html = '<select class="form-control input-small inline" name="state_code" id="state_code">';
                    state_html+= '<option value="">请选择</option>';
                    $.each(res,function (k,v) {
                        if(state_code != '' && state_code == v.Value) {
                            state_html += '<option value="' + v.Value + '" selected="selected">' + v.Text + '</option>';
                        }else {
                            state_html += '<option value="' + v.Value + '">' + v.Text + '</option>';
                        }
                    })
                    state_html +='</select>';
                    if(state!='' && state!= undefined){
                        state_html +='<input type="hidden" name="state" id="state" value="'+state+'">';
                    }else {
                        state_html +='<input type="hidden" name="state" id="state" value="">';
                    }
                }else {
                    if(state_code == '' || state_code == undefined){
                        var state_html = '<input class="form-control input-medium fl w100" name="state" id="state" value="">';
                    }else {
                        var state_html = '<input class="form-control input-medium fl w100" name="state" id="state" value="'+state_code+'">';
                    }
                }
                $('.state-dd').html(state_html);
            })
        }

        /*选择省份*/
        $(".shipping-address-div").on('change','#state_code',function () {
            var state = $("#state_code").find("option:selected").text();
            if(state!='' && state!= undefined){
                $("#state").val(state);
            }
            GetCity();
        })

        /*选择城市*/
        $(".shipping-address-div").on('change','#city_code',function () {
            var city = $("#city_code").find("option:selected").text();
            if(city!='' && city!= undefined){
                $("#city").val(city);
            }
        })
        function GetCity(city_code,city,state_code) {
            var country_code = $('#country_code').val();
            if(state_code!='' && state_code!= undefined){
                var code = state_code;
            }else {
                var code = $('#state_code').val();
            }
            var url = $('#country_code').attr('data-url');
            $.post(url,{'code':code,'country_code':country_code},function (res) {
                if(res != false){
                    var city_html = '<select class="form-control input-small inline" name="city_code" id="city_code">';
                    city_html+= '<option value="">请选择</option>';
                    $.each(res,function (k,v) {
                        if(city_code != '' && city_code == v.Value){
                            city_html+= '<option value="'+v.Value+'" selected="selected">'+v.Text+'</option>';
                        }else {
                            city_html+= '<option value="'+v.Value+'">'+v.Text+'</option>';
                        }
                    })
                    city_html +='</select>';
                    if(city!='' && city!=undefined){
                        city_html +='<input type="hidden" name="city" id="city" value="'+city+'">';
                    }else {
                        city_html +='<input type="hidden" name="city" id="city" value="">';
                    }
                }else {
                    if(city_code == '' || city_code == undefined){
                        var city_html = '<input class="form-control input-medium fl w100" name="city" id="city" value="">';
                    }else {
                        var city_html = '<input class="form-control input-medium fl w100" name="city" id="city" value="'+city_code+'">';
                    }
                }
                $('.city-dd').html(city_html);
            })
        }

        /*产品数量改变*/
        $("#product_table").on("change",".js-number",function () {
            var sku_id = $(this).closest('tr').find(".single-checkbox").val();
            var number = $(this).val();
            var SalesPrice = $(".js-sales-price[data-sku='"+sku_id+"']").val();
            if(parseInt(number)<1){
                layer.msg("数量不能小于1", {icon: 2});
                return false;
            }
            var captured_price = parseInt(number)*parseFloat(SalesPrice);
            $(".js-captured-price[data-sku='"+sku_id+"']").val(captured_price);
            CalculatedTotalPrice();
        })

        $("#product_table").on("change",".single-checkbox,.js-captured-price,.selectAll",function () {
            CalculatedTotalPrice();
        })

        /*计算总价*/
        function CalculatedTotalPrice() {
            var TotalPrice = 0;
            $("#product_table").find(".js-sku-id:checked").each(function (k,v) {
                var sku_id = $(v).val();
                TotalPrice+=parseFloat($(".js-captured-price[data-sku='"+sku_id+"']").val());
            })
            TotalPrice = TotalPrice.toFixed(2);
            $("#goods_total").val(TotalPrice);
            $(".TotalPrice").text($("#currency_code").val()+' '+TotalPrice);
        }
        var state_code = $("#state_code").val();
        var state = $("#state").val();
        if(state_code.length>0){
            GetState(state_code,state);
        }
        var city_code = $("#city_code").val();
        var city = $("#city").val();
        if(city_code.length>0){
            GetCity(city_code,city,state_code);
        }
        
        $(".create-submit").click(function () {
            var t = $('#create-rma-form').serializeArray();
            var data = {};
            var checked_sku = {};
            $("#product_table").find(".js-sku-id:checked").each(function (k,v) {
                checked_sku[k] = $(v).val();
            });
            $.each(t, function() {
                data [this.name] = this.value;
            });
            data['checked_sku'] = checked_sku;
            var url = $(this).attr('href');
            if(data['checked_sku'] == undefined || data['checked_sku']==''){
                layer.msg("选择商品信息不能为空",{"icon":5});return false;
            }
            if(data.country == undefined || data.country==''){
                layer.msg("国家不能为空",{"icon":5});return false;
            }
            if(data.state == undefined || data.state==''){
                layer.msg("省份不能为空",{"icon":5});return false;
            }
            if(data.city == undefined || data.city==''){
                layer.msg("城市不能为空",{"icon":5});return false;
            }
            if(data.customer_id == undefined || data.customer_id==''){
                layer.msg("用户ID不能为空",{"icon":5});return false;
            }
            if(data.first_name == undefined || data.first_name==''){
                layer.msg("收货地址用户姓名名不能为空",{"icon":5});return false;
            }
            if(data.last_name == undefined || data.last_name==''){
                layer.msg("收货地址用户姓名名不能为空",{"icon":5});return false;
            }
            if(data.mobile == undefined || data.mobile==''){
                layer.msg("手机号码不能为空",{"icon":5});return false;
            }
            if(data.postal_code == undefined || data.postal_code==''){
                layer.msg("邮编不能为空",{"icon":5});return false;
            }
            if(data.store_id == undefined || data.store_id==''){
                layer.msg("店铺不存在",{"icon":5});return false;
            }
            if(data.store_id == undefined || data.store_id==''){
                layer.msg("店铺不存在",{"icon":5});return false;
            }
            if(data.remark == undefined || data.remark==''){
                layer.msg("补发说明不能为空",{"icon":5});return false;
            }
            $.post(url,data,function (res) {
                console.log('生成RMA单返回：')
                console.log(res)
                if(res.code == 200){
                    //询问框
                    var content_html = '<div style="padding: 20px;"><!--<div style="font-size: 16px;font-weight: bold;margin: 10px 0;">信息如下</div>--><div style="font-size: 14px;margin: 10px 0;">订单号：'+res.order_number+'</div><div style="font-size: 14px;margin: 10px 0;">支付链接：</div><div><a href="'+res.pay_url+'" target="_blank" style="color: #0d638f;">'+res.pay_url+'</a></div></div>';
                    if (res.is_zero == '1'){
                        content_html = '<div style="padding: 20px;"><!--<div style="font-size: 16px;font-weight: bold;margin: 10px 0;">信息如下</div>--><div style="font-size: 14px;margin: 10px 0;">订单号：'+res.order_number+'</div></div>';
                    }
                    layer.open({
                        type: 1,
                        title: '生成RMA成功',
                        skin: 'layui-layer-demo', //样式类名
                        closeBtn: 0, //不显示关闭按钮
                        anim: 2,
                        shadeClose: false, //开启遮罩关闭
                        btn: ['确认'],
                        content: content_html
                    });
                }else {
                    layer.msg(res.msg,{"icon":5});
                }
            },'json')
            /*var param = $('#create-rma-form').serialize();
            console.log(param);*/
        })
    };

    $(function(){
        Init();
    });
    return {
        CreateRma:CreateRma,
    }
}();
