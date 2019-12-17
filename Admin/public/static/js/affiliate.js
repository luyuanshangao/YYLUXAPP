function save_public(e){
    $.get(e, function (data) {
        layer.open({
            title: "编辑",
            content: data,
            type: 1,
            area: ['680px', '600px'],
            offset: '10px',
            btn: ["保存", "取消"],
            yes: function (index) {
                var formData = new FormData($( "#saveForm" )[0]);//:nth-child(3)
                $.ajax({
                    type:"POST",
                    url:e,
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



function classid(e){
    var id = e;
    var val = $(".class"+id).find("li").length;
    if(val){
        var b =	$(".hitarea"+id).hasClass('expandable-hitarea');
        if(b){
            $(".hitarea"+id).removeClass('expandable-hitarea');
            $(".hitarea"+id).addClass('collapsable-hitarea');

            $(".class"+id).toggle();
            // console.log(11111);

        }else{
            $(".hitarea"+id).addClass('expandable-hitarea');
            $(".hitarea"+id).removeClass('collapsable-hitarea');

            if(".class"+id == ".class1"){
                $(".class1").addClass('shuoge');
            }else{
                $(".class"+id).hide();
            }
        }
        return;
    }else{
        $(".hitarea"+id).removeClass('expandable-hitarea');
    }
    // $('body').on('click','#academyAddEdit',function(){});
    $.ajax({
        type:"POST",
        url:"/Article/class_name",
        data:{cate_id:id},
        dataType:"json",
        cache:false,
        success:function(msg){
            // $('.class'+e).attr('title', alt).after('<h4>' + alt + '</h4>');
            $('.class'+e).prepend(msg.html);


        },
        error:function(error){}
    });

}

//删除单个销售属性
function delArticleCate(e){
    $.ajax({
        type:"POST",
        url:"/Article/delCate",
        data:{cate_id:e},
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
var affiliate = function() {
    function affiliate_order_statistics(){
            //Affiliate订单统计导出
            $('.affiliate_order_statistics').click(function(event){
                var that = $(this);
                var affiliate_id = $('#affiliate_id').val()?$('#affiliate_id').val():'';
                var order_number = $('#order_number').val()?$('#order_number').val():'';
                var sku_id = $('#sku_id').val()?$('#sku_id').val():'';
                var settlement_status = $('#settlement_status').val()?$('#settlement_status').val():'';
                var startTime = $('#startTime').val()?$('#startTime').val():'';
                var endTime = $('#endTime').val()?$('#endTime').val():'';
                var url = that.data('url');
                affiliate_id = affiliate_id.replace(/\n|\r\n|\r|\s|\;|\；|\，/g, "," );
                affiliate_id = affiliate_id.replace(/(,)+/g, "," );
                order_number = order_number.replace(/\n|\r\n|\r|\s|\;|\；|\，/g, "," );
                order_number = order_number.replace(/(,)+/g, "," );
                sku_id = sku_id.replace(/\n|\r\n|\r|\s|\;|\；|\，/g, "," );
                sku_id = sku_id.replace(/(,)+/g, "," );
                // console.log(affiliate_id);
                //var url = url+'?affiliate_id='+affiliate_id+'&order_number='+order_number+'&sku_id='+sku_id+'&settlement_status='+settlement_status+'&startTime='+startTime+'&endTime='+endTime;
                /*$.post(url,{"affiliate_id":affiliate_id,"order_number":order_number,"sku_id":sku_id,"settlement_status":settlement_status,"startTime":startTime,"endTime":endTime},function (res) {
                    console.log(123)
                })*/
                $("#navbar").attr("action",url);
                $("#navbar").submit();
                //window.location.href=url;
            })
        //Affiliate订单查询
        $('.affiliate_order_query').click(function(event){
            var that = $(this);
            var url = that.data('url');
            $("#navbar").attr("action",url);
            $("#navbar").submit();
        })

            //Affiliate用户统计
            $('.AffiliateUserStatistics').click(function(event){
                var that = $(this);
                var affiliate_id = $('#Affiliate_id').val()?$('#Affiliate_id').val():'';
                var CustomerID = $('#CustomerID').val()?$('#CustomerID').val():'';
                var PayPalEU = $('#PayPalEU').val()?$('#PayPalEU').val():'';
                var startTime = $('#startTime').val()?$('#startTime').val():'';
                var endTime = $('#endTime').val()?$('#endTime').val():'';
                var url = that.data('url');
                var url = url+'?Affiliate_id='+affiliate_id+'&CustomerID='+CustomerID+'&PayPalEU='+PayPalEU+'&startTime='+startTime+'&endTime='+endTime;
                window.location.href=url;
            })
            //Affiliate订单交易情况查询检测
            $('.order-transaction').click(function(event) {
                var affiliate_id = $('#affiliate_id').val()?$('#affiliate_id').val():'';
                var startTime = document.getElementById('startTime').value;
                var endTime = document.getElementById('endTime').value;
                // affiliate_id = affiliate_id.replace(/\n|\r\n|\r|\s|\;|\；|\，/g, "," );
                // affiliate_id = affiliate_id.replace(/(,)+/g, "," );
                if(startTime && endTime){
                        // var time = new Date().valueOf();
                       //  var curDate = Date.parse(new Date());
                       //  var date = curDate - 24*60*60*1000; //前一天
                       //  var curDate1 = new Date();
                       //  var preDate = new Date(curDate1.getTime() - 24*60*60*1000); //前一天
                       // console.log(preDate);
                       //  console.log(date); console.log(endTime); return;
                        if(startTime > endTime){
                            layer.msg('开始时间不能大于结束时间', {icon: 2});
                            return ;
                        }
                        //判断时间跨度是否大于1个月
                        var arr1 = startTime.split('-');
                        var arr2 = endTime.split('-');
                        // console.log(arr1);console.log(arr2);
                        arr1[1] = parseInt(arr1[1]);
                        arr1[2] = parseInt(arr1[2]);
                        arr2[1] = parseInt(arr2[1]);
                        arr2[2] = parseInt(arr2[2]);
 // console.log(arr1);console.log(arr2);
                        if(arr1[0] == arr2[0]){//同年
                            if(arr2[1]-arr1[1] > 1){ //月间隔超过1个月
                                layer.msg('时间间隔大于一个月', {icon: 2});
                                return ;
                            }else if(arr2[1]-arr1[1] == 1){ //月相隔1个月，比较日
                                if(arr2[2] > arr1[2]){ //结束日期的日大于开始日期的日
                                     layer.msg('结束日期的日大于开始日期的日', {icon: 2});
                                     return ;
                                }
                            }
                        }else{ //不同年
                            if(arr2[0] - arr1[0] > 1){  //跨度超过一年
                                layer.msg('时间间隔大于一个月', {icon: 2});
                                return ;
                            }else if(arr2[0] - arr1[0] == 1){
                                if(arr1[1] < 10){ //开始年的月份小于10时，不需要跨年
                                    // flag = false;
                                }else if(arr1[1]+1-arr2[1] < 12){ //月相隔大于1个月
                                    layer.msg('时间间隔大于一个月', {icon: 2});
                                    return ;
                                }else if(arr1[1]+1-arr2[1] == 12){ //月相隔1个月，比较日
                                    if(arr2[2] > arr1[2]){ //结束日期的日大于开始日期的日
                                        layer.msg('结束日期的日大于开始日期的日', {icon: 2});
                                        return ;
                                    }
                                }
                            }
                        }
                }else if(startTime && !endTime){
                    layer.msg('时间要么都不选要么都选', {icon: 2});
                    return;
                }else if(!startTime && endTime ){
                    layer.msg('时间要么都不选要么都选', {icon: 2});
                    return;
                }
                $("#navbar").submit();
            });
            $('.OrderTransaction').click(function(event) {
                  var that = $(this);
                  var affiliate_id = $('#affiliate_id').val()?$('#affiliate_id').val():'';
                  var startTime = $('#startTime').val()?$('#startTime').val():'';
                  var endTime = $('#endTime').val()?$('#endTime').val():'';
                  var url = that.data('url');
                  var url = url+'?affiliate_id='+affiliate_id+'&startTime='+startTime+'&endTime='+endTime;
                  window.location.href=url;

            });
            $('.Export_SalesStatistics').click(function(event) {
                    var that = $(this);
                    var affiliate_id = $('#affiliate_id').val()?$('#affiliate_id').val():'';
                    var startTime = $('#startTime').val()?$('#startTime').val():'';
                    var endTime = $('#endTime').val()?$('#endTime').val():'';
                    var url = that.data('url');
                    var url = url+'?affiliate_id='+affiliate_id+'&startTime='+startTime+'&endTime='+endTime;
                    window.location.href=url;
                    // console.log(url);
            });
            $('.Export_ClassifiedSales').click(function(event) {
                    var that = $(this);
                    var affiliate_id = $('#affiliate_id').val()?$('#affiliate_id').val():'';
                    var sku_id = $('#sku_id').val()?$('#sku_id').val():'';
                    var startTime    = $('#startTime').val()?$('#startTime').val():'';
                    var endTime      = $('#endTime').val()?$('#endTime').val():'';
                    var first_level  = $('#first_level').val()?$('#first_level').val():'';
                    var second_level = $('#second_level').val()?$('#second_level').val():'';
                    var third_level  = $('#third_level').val()?$('#third_level').val():'';
                    var fourth_level = $('#fourth_level').val()?$('#fourth_level').val():'';
                        affiliate_id = affiliate_id.replace(/\n|\r\n|\r|\s|\;|\；|\，/g, "," );
                        affiliate_id = affiliate_id.replace(/(,)+/g, "," );
                        sku_id = sku_id.replace(/\n|\r\n|\r|\s|\;|\；|\，/g, "," );
                        sku_id = sku_id.replace(/(,)+/g, "," );
                    var url = that.data('url');
                    var url = url+'?affiliate_id='+affiliate_id+'&startTime='+startTime+'&endTime='+endTime+'&sku_id='+sku_id+'&first_level='+first_level+'&second_level='+second_level+'&third_level='+third_level+'&fourth_level='+fourth_level;
                     // console.log(url);
                    window.location.href=url;
            })

            $('.delete_black').click(function(event) {
                  var that = $(this),affiliateid = that.data('affiliateid'),
                  status = that.data('status');
                  layer.msg(name, {
                    time: 0, //不自动关闭
                    btn: ['确定', '取消'],
                    yes: function(index){
                    layer.close(index);
                        // console.log(affiliateid);console.log(status);
                        $.ajax({
                            type:"POST",
                            url:'/AffiliateReport/delete_black',
                            data:{affiliateid:affiliateid,status:status},
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
                            error:function(error){layer.msg('提交出错', {icon: 2});}
                        });


                    }
                });
            });

    }
      return {
        affiliate_order_statistics:affiliate_order_statistics,
      }
}();