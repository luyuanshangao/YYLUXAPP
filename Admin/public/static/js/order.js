var Order = function() {
     /**
     * 初始化函数
     */
    function Init(){

    };
    /**
    order index page
    */
    function index(){
         $("#PaymentMethod").change(function(){
            var PaymentMethod =$("#PaymentMethod option:selected").text();
            $('#paymentMethod_name').val(PaymentMethod);

        });

        $('#orderstarttime').click(function() {
            var end_id = 'orderendtime';
            WdatePicker({
                startDate: '%y-%M-%d 00:00:00',
                dateFmt: 'yyyy-MM-dd HH:mm:ss',
                alwaysUseStartDate: false,
                minDate:'2016-01-01',
                maxDate: '#F{$dp.$D('+end_id+')}',
                onpicked: function () {
                    var starttime = $(this).val();
                    starttime = new Date(starttime.replace(/-/g,"/")).getTime();
                    starttime = starttime -1000 + (3*30*24*3600*1000);
                    var timestamp = Date.parse(new Date());
                    if(starttime>=timestamp){
                        starttime = timestamp;
                    }
                    var oDate  = new Date(starttime),
                        oYear = oDate.getFullYear(),
                        oMonth = oDate.getMonth()+1,
                        oDay = oDate.getDate(),
                        oHour = oDate.getHours(),
                        oMin = oDate.getMinutes(),
                        oSen = oDate.getSeconds(),
                        oTime = oYear +'-'+ getzf(oMonth) +'-'+ getzf(oDay) +' '+ getzf(oHour) +':'+ getzf(oMin) +':'+getzf(oSen);//最后拼接时间
                    $('#orderendtime').val(oTime)
                }
            });
        });
        function getzf(num){
            if(parseInt(num) < 10){
                num = '0'+num;
            }
            return num;
        }
        $('#orderendtime').click(function() {

            var start_id = 'orderstarttime';

            WdatePicker({
                startDate: '%y-%M-%d 23:59:59',
                dateFmt: 'yyyy-MM-dd HH:mm:ss',
                minDate: '#F{$dp.$D('+start_id+')}',
                maxDate: '%y-%M-%d',
                alwaysUseStartDate: false,
                onpicked: function () {
                    var endtime = $(this).val();
                    endtime = new Date(endtime.replace(/-/g,"/")).getTime();
                    maxstarttime = endtime + 1000 - (3*30*24*3600*1000);
                    starttime = new Date($('#orderstarttime').val()).getTime();
                    if(starttime<maxstarttime){
                        var oDate  = new Date(maxstarttime),
                            oYear = oDate.getFullYear(),
                            oMonth = oDate.getMonth(),
                            oDay = oDate.getDate(),
                            oHour = oDate.getHours(),
                            oMin = oDate.getMinutes(),
                            oSen = oDate.getSeconds(),
                            oTime = oYear +'-'+ getzf(oMonth) +'-'+ getzf(oDay) +' '+ getzf(oHour) +':'+ getzf(oMin) +':'+getzf(oSen);//最后拼接时间
                        $('#orderstarttime').val(oTime)
                    }
                }
            });
        });
    };

    $(".distribution").click(function () {
        var distribution_admin_id = $(".distribution_admin_user").val();
        var distribution_admin = $(".distribution_admin_user").find("option:selected").text();
        var url = $(this).attr("post-url");
        var ids = $(this).attr("data-id");
        $.post(url,{'ids':ids,'distribution_admin_id':distribution_admin_id,'distribution_admin':distribution_admin},function (res) {
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
    });


    $(".crash").click(function () {
        var url = $(this).attr("post-url");
        var ids = $(this).attr("data-id");
        var is_crash = $(this).attr("is-crash");
        $.post(url,{'ids':ids,'is_crash':is_crash},function (res) {
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
    })

    /**
    * riskManagement page
     */
    function riskManageMent(){
        //是否处理订单
        $('.risk-status-td').on('click','.js-risk-status',function(){
            var that = $(this),
                name = that.data('msgname'),
                dataParam = {
                    id:that.data('id'),
                    report_status:that.data('status')
                };

            if(name == null){
                var name = '确定要操作么?';
            }
            layer.msg(name, {
                time: 0, //不自动关闭
                btn: ['确定', '取消'],
                yes: function(index){
                layer.close(index);
                    $.ajax({
                        type:"POST",
                        url:'/CustomerService/RiskStatus',
                        data:dataParam,
                        dataType:"json",
                        cache:false,
                        success:function(msg){
                            if(msg.code == 200){
                                layer.msg(msg.result, {icon: 1});
                                setTimeout(function(){
                                    window.location.reload()
                                },1500);
                            }else{
                                layer.msg(msg.result, {icon: 2});
                            }
                        },
                        error:function(error){}
                    });
                }

            });
        });
        $('.reply_to_report').click(function(event) {
                var that = $(this),
                    _id = that.data('id');
                $('#replyTcardId').val(_id);
                layer.open({
                      title: '回复',
                      type: 1,
                      skin: 'layui-layer-rim', //加上边框
                      area: ['450px', '350px'], //宽高
                      content:$('#replyTcard')
                });
        })
        $('#replyTcardSelect').change(function(){
            var that = $(this),
                _val = that.val();
                $('#replyTcardTextarea').val(_val);
        });
        $('body').on('click','#NotesSubmit',function(){
                var dataParam = $('#importDataPost').serialize();
                $.ajax({
                    type:"POST",
                    dataType: 'json',
                    data:dataParam,
                    url:"/CustomerService/report",
                    success:function(data){
                        if(data.code == 200){
                            layer.msg(data.result, {icon: 1});
                            setTimeout(function(){
                              window.location.reload();
                            },1000);
                        }else{
                            layer.msg(data.result, {icon: 2});
                        }
                    }
                })
              // console.log(121);
        })
        //Refund
        $('.query-refund-order').click(function(event){
            $("#is_export").val(0);
            $("#navbar").submit();
        })
        $('.export-refund-order').click(function(event){
            $("#is_export").val(1);
            $("#navbar").submit();
        })
    };
    /**
    * 订单详情页面
    */
    function orderDetail(){
        var total = 0;
        $("#productList").find("tr").each(function(){
            var v = $(this).children(':last').text();
            total += parseFloat(v);
        });
        total = total.toFixed(2);
        $('#spTotal').text(total);
        $('#holdBtn').click(function(event) {
            var dataParam = {
                status: $("#holdBtn").attr("lock-status"),
                order_id:$("#order_id").val(),
                order_number:$("#order_number").val()
            };
            $.ajax({
                type:"POST",
                dataType: 'json',
                data:dataParam,
                url:"/Order/orderStatus",
                success:function(data){
                    if(data.code == 200){
                        layer.msg(data.data, {icon: 1});
                        setTimeout(function(){
                          window.location.reload();
                        },1000);
                    }else{
                        layer.msg(data.data, {icon: 2});
                    }
                }
            })
        });
        $('#order-shut-down').click(function(event) {
                var that = $(this),
                order_id = that.data('order-id'),
                order_status = that.data('order-status');
                var dataParam = {
                      order_id:order_id,
                      order_status:order_status
                    };
                    //console.log(dataParam);
                layer.msg('确定要关闭么？', {
                    time: 0, //不自动关闭
                    btn: ['确定', '取消'],
                    yes: function(index){
                        layer.close(index);
                        $.ajax({
                            type:"POST",
                            url:'/Order/OrderShutDown',
                            data:dataParam,
                            dataType:"json",
                            success:function(msg){
                            if(msg.code == 200){
                                layer.msg(msg.result, {icon: 1});
                                setTimeout(function(){
                                    window.location.reload()
                                },1500);
                            }else{
                                layer.msg(msg.result, {icon: 2});
                            }
                            },
                            error:function(error){}
                        });
                    }
                });
        })

        $('#AddNotes').click(function(event) {
                var order_id = $('#order_id').val();
                var order_message = $('#order_message').val();//console.log(order_message);
                var status = order_message?1:0;
                order_message = order_message?order_message:'';
                layer.open({
                      title: '添加备注',
                      type: 1,
                      skin: 'layui-layer-rim', //加上边框
                      area: ['450px', '320px'], //宽高
                      content: '<form id="importDataPost" enctype="multipart/form-data" >'+
                      '<div class="pl30">' +
                      '<input type="hidden"  name="order_id" value="'+order_id+'"> ' +
                      '<input type="hidden"  name="status" value="'+status+'"> ' +
                      '<div class="mt20">' +
                      '<textarea name="message" rows="10" cols="60">'+order_message+'</textarea>'+
                      '</div>' +
                      '<div class="mt30 tcenter">' +
                      '<a href="javascript:;" id = "NotesSubmit" class = "submit btn-qing f18">提交</a>' +
                      '</div>' +
                      '</div>' +
                      '</form>'
                });
        })
        $('body').on('click','#NotesSubmit',function(){
               var dataParam = $('#importDataPost').serialize();

                $.ajax({
                    type:"POST",
                    dataType: 'json',
                    data:dataParam,
                    url:"/Order/AddNotes",
                    success:function(data){
                        if(data.code == 200){
                            layer.msg(data.data, {icon: 1});
                            setTimeout(function(){
                              window.location.reload();
                            },1000);
                        }else{
                            layer.msg(data.data, {icon: 2});
                        }
                    }
                })
              // console.log(121);
        })

        $(".add-info").click(function(event) {
            layer.open({
                title: '人工上传',
                type: 1,
                skin: 'layui-layer-rim', //加上边框
                area: ['700px', '570px'], //宽高
                content: '<div class="layui-tab layui-tab-brief">' +
                         '<ul class="layui-tab-title"><li class="tab-item layui-this">新增</li><li class="tab-item">换单</li></ul>' +
                         '<div class="layui-tab-content"><div class="main-item main-item1 layui-tab-item layui-show"><div class="info-pb10 c-h-dl-label100 mt10 logisticsPop" id="logisticsPop">' +
                         '<form id="" action="" enctype="multipart/form-data">' +
                         '<dl class="c-h-dl-validator form-group clearfix"></dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix"><label><input type="radio" value="0" checked name="new-add-logistics-radio">新增不删除</label><label><input type="radio" value="1" name="new-add-logistics-radio">新增删除</label></dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label><em>*</em>追踪号：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="logisticsPop-Tracking-Number" type="text"></dd>' +
                         '</dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label>包裹号码：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="logisticsPop-Package-Number" type="text"></dd>' +
                         '</dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label style="width: 115px;"><em>*</em>运输渠道名称：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="shipping_channel_name" type="text"></dd>' +
                         '</dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label>重量(kg)：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="logisticsPop-weight" type="text"></dd>' +
                         '</dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label>运费金额：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="logisticsPop-Shipping-amount" type="text"></dd>' +
                         '</dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label>关税金额：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="logisticsPop-Tariff-amount" type="text"></dd>' +
                         '</dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label>总金额：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="logisticsPop-total-amount" type="text"></dd>' +
                         '</dl>' +
                         '<div class="behind">' +
                         '<label for="male">产品订单信息</label>' +
                         '<dl class="c-h-dl-validator form-group clearfix add-attribute1 delect_dl"><dd class="v-title"><label><em>*</em>SKU：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="" class="form-control sku-val" type="text"></div>  数量： <div class="input-icon right inline-block"><input name="" class="form-control num-val" type="text"></div> <a class="btn btn-qing add-attr-btn eliminate-btn1 delect added1" href="javascript:;" data-total="1">添加新项</a></dd><dt></dt></dl>' +
                         '</div>' +
                         '<div class="mt10 btn-wrap"><a class="btn btn-qing mr20 btn-logisticsPop-save" href="javascript:;">保存</a></div>' +
                         '</form></div></div>' +
                         '<div class="main-item main-item2 layui-tab-item"><div class="info-pb10 c-h-dl-label100 mt10 logisticsPop"><dl class="c-h-dl-validator form-group clearfix"><form id="" action="" enctype="multipart/form-data">' +
                         '<dd class="v-title"><label><em>*</em>订单号：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="order-number2" type="text"></dd></dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label><em>*</em>旧的包裹号：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="old-logisticsPop-Shipping-num2" type="text"></dd></dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label><em>*</em>新的包裹号：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="new-logisticsPop-Shipping-num2" type="text"></dd></dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label><em>*</em>渠道名称：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="Channel-name2" type="text"></dd></dl>' +
                         '<dl class="c-h-dl-validator form-group clearfix">' +
                         '<dd class="v-title"><label>渠道中文名称：</label></dd>' +
                         '<dd><input name="" class="form-control inline" id="Channel-cn-name2" type="text"></dd></dl>' +
                         '<div class="mt10 btn-wrap"><a class="btn btn-qing mr20 btn-change-order-save fl ml100" href="javascript:;">保存</a></div>' +
                         '</form></div>' +
                         '</div></div></div>'
            });
        })
        
        layui.use('element', function(){
          var element = layui.element;
        });

        $('body').on('click','#logisticsPop .add-attr-btn',function(){
            var that = $(this),e,
                val_attribute = that.data('total');
            if(val_attribute != null){
                e = val_attribute + 1;
                val_attribute = e;
                e = val_attribute;
                }else{
                e = e + 1;
                val_attribute = e;
            }
            if(e ==2){
              $('.add-attribute1').append('<a class="eliminate-btn2 eliminate1 ml0" onclick="add_delect(1)" href="javascript:;">删除</a>');
            }
            $(".delect").remove();
            $(".behind").append('<dl class="c-h-dl-validator form-group clearfix add-attribute'+e+' delect_dl"><dd class="v-title"><label><em>*</em>SKU：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="" class="form-control sku-val" type="text"></div>   数量： <div style="" class="input-icon right inline-block"><input style="" name="" class="form-control num-val" type="text"></div> <a class="eliminate-btn2 add-attr-btn delect added'+e+'"  data-total="'+e+'" href="javascript:;">添加新项</a><a class="eliminate-btn2 eliminate'+e+'" onclick="add_delect('+e+')" href="javascript:;">删除</a></dd><dt></dt></dl>');
        });

        $('body').on('click','.btn-logisticsPop-save',function(){
          var newAddLogisticsRadio = $('input[name="new-add-logistics-radio"]:checked').val(),
              order_number = $("#logisticsPop-order-number").val(),
              tracking_number = $("#logisticsPop-Tracking-Number").val(),
              package_number = $("#logisticsPop-Package-Number").val(),
              shipping_channel_name= $("#shipping_channel_name").val(),
              weight = $("#logisticsPop-weight").val(),
              shipping_fee = $("#logisticsPop-Shipping-amount").val(),
              triff_fee = $("#logisticsPop-Tariff-amount").val(),
              total_amount = $("#logisticsPop-total-amount").val(),
              validator = $("#logisticsPop .behind .c-h-dl-validator"),
              skuIdList = $('.sku-id'),
              skuIdListArray = [],
              item_info = [],
              params = {};

          if (!order_number) {
            layer.msg('订单编号不能为空');
            return false;
          }
          if (!tracking_number) {
            layer.msg('追踪号不能为空');
            return false;
          }

          for(var i=0,len=validator.length;i<len;i++){
            var sku = $(validator).eq(i).find(".sku-val").val(),
                num = $(validator).eq(i).find(".num-val").val(),
                data = {};

            if(!sku || !num) {
              layer.msg('请填写完整的产品订单信息');
              return false;
            }
            data.sku_id = sku;
            data.sku_qty = num;
            item_info.push(data)
          }

          for(var j=0,len=skuIdList.length;j<len;j++){
            skuIdListArray.push($(skuIdList).eq(j).text().trim());
          }

          params.is_delete = newAddLogisticsRadio;
          params.order_number = order_number;
          params.tracking_number = tracking_number;
          params.package_number = package_number;
          params.shipping_channel_name = shipping_channel_name;
          params.weight = weight;
          params.shipping_fee = shipping_fee;
          params.triff_fee = triff_fee;
          params.total_amount = total_amount;
          params.item_info = item_info;
          params.store_id = $("#store_id").val();
          params.sku_code = skuIdListArray;
          $.ajax({
                type:"POST",
                dataType: 'json',
                data:params,
                url:'/Order/addTrackingNumber',
                success:function(data){
                  if(data.code ==200){
                    layer.msg(data.msg)
                    setTimeout(function(){
                      layer.closeAll()
                    },1000)
                  }else{
                    layer.msg(data.msg)
                  }
                },
                error:function(err){
                 console.log(err)
                }
            })
        })
        $('body').on('click','.btn-change-order-save',function(){
          var orderNumber2 = $('#order-number2').val(),
              oldLogisticsPopShippingNum2 = $("#old-logisticsPop-Shipping-num2").val(),
              newLogisticsPopShippingNum2 = $("#new-logisticsPop-Shipping-num2").val(),
              ChannelName2 = $("#Channel-name2").val(),
              ChannelCnName2 = $("#Channel-cn-name2").val(),
              params = {};

          if (!orderNumber2) {
            layer.msg('订单号不能为空');
            return false;
          }
          if (!oldLogisticsPopShippingNum2) {
            layer.msg('旧的包裹号');
            return false;
          }
          if (!newLogisticsPopShippingNum2) {
            layer.msg('新的包裹号');
            return false;
          }
          if (!ChannelName2) {
            layer.msg('渠道名称');
            return false;
          }

          
          params.type = 2;
          params.order_number = orderNumber2;
          params.tracking_number = newLogisticsPopShippingNum2;
          params.old_tracking_number = oldLogisticsPopShippingNum2;
          params.shipping_channel_name = ChannelName2;
          params.shipping_channel_name_cn = ChannelCnName2;
          params.store_id = $("#store_id").val();
          $.ajax({
                type:"POST",
                dataType: 'json',
                data:params,
                url:'/Order/addTrackingNumber',
                success:function(data){
                  if(data.code ==200){
                    layer.msg(data.msg)
                    setTimeout(function(){
                      layer.closeAll()
                    },1000)
                  }else{
                    layer.msg(data.msg)
                  }
                },
                error:function(err){
                 console.log(err)
                }
            })
        })

        $(".reply").click(function () {
            var formData =new FormData($( "#ReplyOrderMessage" )[0]);//:nth-child(3)
            $.ajax({
                type:"POST",
                url:"/OrderMessage/reply_order_message",
                dataType: 'json',
                data:formData,
                async: false,
                cache: false,
                contentType: false,
                processData: false,
                success:function(msg){
                    if(msg.code == 200){
                        layer.msg(msg.msg, {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        },1500);
                    }else{
                        layer.msg(msg.msg, {icon: 2});
                    }
                }
            });
        })

        $(".reply-solved").click(function () {
            var formData = new FormData($( "#ReplyOrderMessage" )[0]);//:nth-child(3)
            formData.append('is_solved',1);
            //console.log(formData);return false;
            $.ajax({
                type:"POST",
                url:"/OrderMessage/reply_order_message",
                dataType: 'json',
                data:formData,
                async: false,
                cache: false,
                contentType: false,
                processData: false,
                success:function(msg){
                    if(msg.code == 200){
                        layer.msg(msg.msg, {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        },1500);
                    }else{
                        layer.msg(msg.msg, {icon: 2});
                    }
                }
            });
        })


        $(".solved").click(function () {
            var formData =new FormData($( "#ReplyOrderMessage" )[0]);//:nth-child(3)
            var url = "/OrderMessage/solved_order_message";
            $.ajax({
                type:"POST",
                url:url,
                dataType: 'json',
                data:formData,
                async: false,
                cache: false,
                contentType: false,
                processData: false,
                success:function(msg){
                    if(msg.code == 200){
                        layer.msg(msg.msg, {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        },1500);
                    }else{
                        layer.msg(msg.msg, {icon: 2});
                    }
                }
            });
            return false;
        })

        /**
         * creat by lijunfang 20190613
         *右侧悬浮菜单交互 
         */
        $('.js-go-back-menu').on('click','li',function(){
            var that = $(this),
                documentHeight = $(document).height();
            that.addClass('curr').siblings().removeClass('curr');
            if(that.hasClass('shut-down')){
                that.parent().addClass('hide');
            }
            if (that.hasClass('top')){
                //回到顶部
                $("html,body").animate({ scrollTop: 0 }, 800);
            }
            if (that.hasClass('bottom')) {
                //回到顶部
                $("html,body").animate({ scrollTop: documentHeight+'px' }, 800);
            }
        })

        /**
         * creat by tinghu.liu 20190703
         * 增加修改订单状态功能
         */
        $('#updateOrderStatus').click(function(event) {
            var order_id = $('#order_id').val();
            var order_status = $('#order_status').val();
            var current_order_status_str = $('#currentOrderStatusStr').val();
            layer.open({
                title: '修改订单状态',
                type: 1,
                skin: 'layui-layer-rim', //加上边框
                area: ['450px', '320px'], //宽高
                content: '<form id="updateOrderStatusDataPost" enctype="multipart/form-data" >'+
                '<div class="pl30">' +
                '<input type="hidden"  name="order_id" value="'+order_id+'"> ' +
                '<input type="hidden"  name="from_status" value="'+order_status+'"> ' +

                '<div class="mt20">从 <b class="red">'+current_order_status_str+'</b> 变为 <select name="to_status" id=""><option value="">请选择</option>'+$('#updateOrderStatusToData').html()+'</select> </div>' +
                '<div class="mt20"><div class="mb10">原因：</div>' +
                '<textarea name="reason" rows="8" cols="57"></textarea>'+
                '</div>' +
                '<div class="mt30 tcenter">' +
                '<a href="javascript:;" id = "updateOrderStatusSubmit" class = "submit btn-qing f18">提交</a>' +
                '</div>' +
                '</div>' +
                '</form>'
            });
        })
        var ajax_falg = false;
        $('body').on('click','#updateOrderStatusSubmit',function(){
            console.log('ajax_falg')
            console.log(ajax_falg)
            if (ajax_falg) return false;
            ajax_falg = true;
            var dataParam = $('#updateOrderStatusDataPost').serialize();
            var index;
            $.ajax({
                type:"POST",
                dataType: 'json',
                data:dataParam,
                url:"/Order/updateOrderStatusSubmit",
                success:function(data){
                    ajax_falg = false;
                    console.log('====== data ======');
                    console.log(data);
                    if(data['code'] == 200){
                        layer.msg(data['msg'], {icon: 1}, function () {
                            layer.close(index)
                            window.location.reload();
                        });
                        // setTimeout(function(){
                        //     window.location.reload();
                        // },1000);
                    }else{
                        layer.msg(data['msg'], {icon: 2}, function () {
                            layer.close(index)
                        });
                    }
                },
                beforeSend:function () {
                    index = layer.load(1)
                },
                error:function () {
                    ajax_falg = false;
                },
                complete:function () {

                }
            })
            // console.log(121);
        })
    };
    /**
    * 售后申请详情页面
    */
    function afterSaleDetails(params) {
        $('#holdBtn').click(function(event) {
            var dataParam = {
                status:$("#holdBtn").attr("lock-status"),
                order_id:$("#order_id").val()
            };
            $.ajax({
                type:"POST",
                dataType: 'json',
                data:dataParam,
                url:"{:url('Order/orderStatus')}",
                success:function(data){
                    if(data.code == 200){
                        layer.msg(data.data, {icon: 1});
                        setTimeout(function(){
                          window.location.reload();
                        },1000);
                    }else{
                        layer.msg(data.data, {icon: 2});
                    }
                }
            })
        });
    };
    /**
     * 仲裁管理页面
     * @return {[type]} [description]
     */
    function arbitrationManage(){
        $('.after_sale_id').click(function(event) {
            var that = $(this),
                _id = that.data('id');
                _type = that.data('type');
                _user_id = that.data('user-id');
                _name = that.data('name');
                _AfterSaleType = that.data('AfterSaleType');
                // console.log(_id);
            Common.addAndEditor('/Order/arbitrationManage/id/'+_id+'/user_type/'+_type+'/user_id/'+_user_id+'/user_name/'+_name+'/type/'+_AfterSaleType,'/Order/arbitrationManage','500px','350px');

        });
    };

    //订单退款
    function retrunOrder(e){
        $.get('/Order/retrunOrder?id='+e, function (data) {
            layer.open({
                title: "订单退款",
                content: data,
                type: 1,
                area: ['550px','500px'],
                yes: function (index) {
                    var formData = new FormData($( "#saveForm" )[0]);
                    $.ajax({
                        type:"POST",
                        url:"/Order/retrunOrder",
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
                                    window.location.reload();
                                },1500);
                            }else{
                                layer.msg(msg.result, {icon: 2});
                            }
                        }
                    });
                },
                cancel: function () {

                }
            });
        });
        // console.log(2);
    }

    //订单退款
    function retrunCloseOrder(e){
        $.get('/Order/retrunCloseOrder?id='+e, function (data) {
            layer.open({
                title: "订单关闭",
                content: data,
                type: 1,
                area: ['550px','400px'],
                yes: function (index) {
                    var formData = new FormData($( "#saveForm" )[0]);
                    $.ajax({
                        type:"POST",
                        url:"/Order/retrunCloseOrder",
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
                                    window.location.reload();
                                },1500);
                            }else{
                                layer.msg(msg.result, {icon: 2});
                            }
                        }
                    });
                },
                cancel: function () {

                }
            });
        });
        // console.log(2);
    }

     //订单批量退款
    function batch_refund(){
        $('.batch_refund').click(function(event) {
                $.get('/Order/Refund', function (data) {
                    layer.open({
                        title: "订单退款",
                        content: data,
                        type: 1,
                        area: ['600px','500px'],
                        yes: function (index) {
                            var formData = new FormData($( "#saveForm" )[0]);
                            $.ajax({
                                type:"POST",
                                url:"/Order/Refund",
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
                                            window.location.reload();
                                        },1500);
                                    }else{
                                        layer.msg(msg.result, {icon: 2});
                                    }
                                }
                            });
                        },
                        cancel: function () {

                        }
                    });
                });
        });
        /**提交申请**/
        $('.submit_refund').click(function(event) {
          var that = $(this);
          var formData = new FormData($( "#saveForm" )[0]);
          if(that.hasClass('btn-disabled')){
            return false;
          }
          that.addClass('btn-disabled').removeClass('btn-qing');
          $.ajax({
              type:"POST",
              url:"/Order/Refund",
              dataType: 'json',
              data:formData,
              async: false,
              cache: false,
              contentType: false,
              processData: false,
              success:function(msg){
                  if(msg.code == 200){
                      $(".refund-msg").append(msg.msg);
                      layer.alert(msg.data, {icon: 1});
                      // setTimeout(function(){
                      //     window.location.reload();
                      // },1500);
                  }else{
                      //console.log(2);
                      layer.alert(msg.data, {icon: 2});
                  }
                  that.addClass('btn-qing').removeClass('btn-disabled');
              },
              error:function(data){

              }
          });
        });

        // console.log(2);
    }
    //订单退款
    function re_retrunOrder(order_number,refund_id){
        var formData = {"order_number":order_number,"refund_id":refund_id};
        $.post("/Order/async_submitRefund",formData,function (msg) {
            if(msg.code == 200){
                layer.msg(msg.msg, {icon: 1});
                setTimeout(function(){
                    window.location.reload();
                },2000);
            }else{
                layer.msg(msg.msg, {icon: 2});
            }
        })
    }
    /*获取订单信息*/
    function historyRecordList(UserID,order_id,Page,i) {
        var url = "/Order/HistoryRecordList";
        if(typeof (Page) == undefined || typeof (Page) == 'undefined'){
            i = 0;
            var page_jump = 1;
        }else {
            var page_jump = Page;
        }
        $.post(url,{"UserID":UserID,"order_id":order_id,"page":page_jump},function (res) {
            if(res.code == 200 && res.data.data!=''){
                var html ='';
                $.each(res.data.data,function (k,v) {
                    var distribution = '';
                    var create_on = '';
                    var is_reply = '';
                    if(v.distribution_admin == "" || v.distribution_admin == null || v.distribution_admin == undefined || v.distribution_admin == 'undefined'){
                        distribution = "未分配";
                    }else{
                        distribution = "已分配";

                    }
                    if(v.create_on != "" ){
                       create_on = UnixToDate(v.create_on,true);
                    }
                    if(v.is_reply ==1){
                       is_reply = '未回复';
                    }else if(v.is_reply ==2){
                       is_reply = '已回复';
                    }else if(v.is_reply ==3){
                       is_reply = '已解决';
                    }
                    // alert(UnixToDate(v.create_on,true));
                    html+= "<tr>" +
                            "<td><a href='/order/edit/id/"+v.order_number+"' target='_blank'>"+v.order_number+"</a></td>"+
                            "<td>"+v.captured_amount_usd+"</td>"+
                            "<td>"+v.store_name+"</td>"+
                            "<td>"+v.message+"</td>"+
                            "<td>"+distribution+'('+v.distribution_admin+')'+"</td>"+
                            "<td>"+create_on+"</td>"+
                            "<td>"+is_reply+"</td>"+
                            "<td>"+v.operator_admin+"</td>"+
                            "<td><a href='/order/edit/id/"+v.order_number+"'  target='_blank'>回复</a></td>"+
                            // "<td>"+v.pay_channel+"</td>"+
                        "</tr>"
                })
            }else {
                html = "<thead><tr><td colspan='13'>查询不到信息</td></tr></thead>";
            }
            $(".record_list").html(html);
            if(typeof (Page) == "undefined"){
                layui.use('laypage', function(){
                    var laypage = layui.laypage;
                    laypage.render({
                        elem: 'orderpage'
                        ,count: res.data.total //数据总数
                        ,limit:res.data.per_page
                        ,jump: function(obj){
                            if(i==1){
                                  historyRecordList(UserID,order_id,obj.curr,1);
                            }
                            i = 1;
                        }
                    });
                });
            }
        })
    }
    /**
     * 时间戳转换日期
     * @param <int> unixTime    待时间戳(秒)
     * @param <bool> isFull    返回完整时间(Y-m-d 或者 Y-m-d H:i:s)
     * @param <int>  timeZone   时区
     */
       function UnixToDate(unixTime,isFull,timeZone) {
        if (typeof (timeZone) == 'number')
        {
            unixTime = parseInt(unixTime) + parseInt(timeZone) * 60 * 60;
        }
        var time = new Date(unixTime * 1000);
        var ymdhis = "";
        ymdhis += time.getUTCFullYear() + "-";
        ymdhis += (time.getUTCMonth()+1) + "-";
        ymdhis += time.getUTCDate();
        if (isFull === true)
        {
            ymdhis += " " + time.getUTCHours() + ":";
            ymdhis += time.getUTCMinutes() + ":";
            ymdhis += time.getUTCSeconds();


        }
        return ymdhis;
    }


    $(".translate-cn").click(function () {
        var text = $(this).parent().siblings(".message-content").html();
        //text=text.replace(' ','%20');
        window.open("https://translate.google.cn/#view=home&op=translate&sl=auto&tl=zh-CN&text="+encodeURIComponent(text));
    })

    $(".translate-en").click(function () {
        var text = $(this).parent().siblings(".message-content").html();
        //text=text.replace(' ','%20');
        window.open("https://translate.google.cn/#view=home&op=translate&sl=auto&tl=en&text="+encodeURIComponent(text));
    })
    
    $("#order_message_template").change(function () {
        editor.html($(this).val());
        $("textarea[name='message']").text($(this).val());
    })
    $(function(){
        Init();
    });
    return {
        Index:index,
        riskManageMent:riskManageMent,
        orderDetail:orderDetail,
        afterSaleDetails:afterSaleDetails,
        arbitrationManage:arbitrationManage,
        retrunOrder:retrunOrder,
        re_retrunOrder:re_retrunOrder,
        retrunCloseOrder:retrunCloseOrder,
        batch_refund:batch_refund,
        historyRecordList:historyRecordList,
        /*distribution:distribution,
        crash:crash*/
    }
}();