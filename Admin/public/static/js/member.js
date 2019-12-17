var Member = function() {
     /**
     * 初始化函数
     */
    function Init(){

    };

    /*
    *会员管理页面
     */
    function memberManage() {
        $('.update-status').on('click',function(){
            var that = $(this),
                _id = that.data('id'),
                _Status = that.data('status'),
                _From_Status = that.data('data-from-status'),
                _url = "/MemberManagement/updateStatus";
                console.log(_From_Status);
                if(_Status!=20 && _Status!=21){
                    layer.confirm('确定执行此操作吗？',{
                        btn: ['确定','取消']}
                            ,function(){
                                $.post(_url,{"Status":_Status,"From_Status":_From_Status,"ID":_id},function (res) {
                                    if(res.code == 200){
                                        layer.msg(res.msg,{"icon":6,"time": 2000});
                                        window.location.reload()
                                    }else {
                                        layer.msg(res.msg,{"icon":5});
                                    }
                                })
                            }
                        );
                }else {
                    layer.open({
                        title: '禁用理由',
                        type: 1,
                        skin: 'layui-layer-rim', //加上边框<form id="examine_submit"  method="post">
                        area: ['420px', '340px'], //宽高
                        content: '<div class="ml30"><form id="examine_submit"  method="post"><input type="hidden" value="'+_id+'" name="ID"><input type="hidden" value="'+_Status+'"  name="Status"><div class="mt10"><label class="reason relative">理由：</label><textarea class="Remarks" name="Remarks" cols="37" rows="9"></textarea></div></form><a href="javascript:;" onclick = "Status_submit(\''+_url+'\')" class = "submit">提交</a></div>'
                    });
                }
        });
    };
    $(".ddls").click(function () {
        var UserID = $(this).attr("data-id");
        getOrderList(UserID);
        $(".nav-justified").find("li").removeClass("active");
        $(this).addClass("active");
    })
    /*获取订单信息*/
    function getOrderList(UserID,Page) {
        var url = "/MemberManagement/getOrderList";
        if(typeof (Page) == undefined || typeof (Page) == 'undefined'){
            var page_jump = 1;
        }else {
            var page_jump = Page;
        }
        $.post(url,{"UserID":UserID,"page":page_jump},function (res) {
            if(res.code == 200 && res.data.data!=''){
                var html = "<thead><tr>" +
                    "                        <th>订单号</th>" +
                    "                        <th>下单时间</th>" +
                    "                        <th>订单状态</th>" +
                    "                        <th>所属店铺</th>" +
                    "                        <th>支付渠道</th>" +
                    "                        <th>收货国家</th>" +
                    "                        <th>商品总额</th>" +
                    "                        <th>应付金额</th>" +
                    "                        <th>实收金额</th>" +
                    "                        <th>运费金额</th>" +
                    "                        <th>退款金额</th>" +
                    "                        <th>币种</th>" +
                    "                        <th>兑美元的汇率</th>\n" +
                    "                    </tr></thead><tbody>";
                $.each(res.data.data,function (k,v) {
                    html+= "<tr>" +
                            "<td><a href='/order/edit/id/"+v.order_number+"' target='_blank'>"+v.order_number+"</a></td>"+
                            "<td>"+v.create_on+"</td>"+
                            "<td>"+v.order_status_name+"</td>"+
                            "<td>"+v.store_name+"</td>"+
                            "<td>"+v.pay_channel+"</td>"+
                            "<td>"+v.country+"</td>"+
                            "<td>"+v.goods_total+"</td>"+
                            "<td>"+v.total_amount+"</td>"+
                            "<td>"+v.captured_amount+"</td>"+
                            "<td>"+v.receivable_shipping_fee+"</td>"+
                        "<td>"+v.refunded_amount+"</td>"+
                        "<td>"+v.currency_code+"</td>"+
                        "<td>"+v.exchange_rate+"</td>"+
                        "</tr>"
                })
                //html+= "<tr><td colspan='13'>"+res.data.Page+"</td></tr>";
                html +="<tbody>";
            }else {
                html = "<thead><tr><td colspan='13'>查询不到信息</td></tr></thead>";
            }
            $(".ywmx").html(html);
            if(typeof (Page) == "undefined"){
                layui.use('laypage', function(){
                    var laypage = layui.laypage;
                    laypage.render({
                        elem: 'orderpage'
                        ,count: res.data.total //数据总数
                        ,limit:res.data.per_page
                        ,jump: function(obj){
                            getOrderList(UserID,obj.curr);
                        }
                    });
                });
            }
        })
    }

    /*获取积分*/
    $(".dxpmx").click(function () {
        var UserID = $(this).attr("data-id");
        getPoints(UserID);
        $(".nav-justified").find("li").removeClass("active");
        $(this).addClass("active");
    });

    function getPoints(UserID,Page) {
        var url = "/MemberManagement/getPoints";
        if(typeof (Page) == "undefined"){
            var page_jump = 1;
        }else {
            var page_jump = Page;
        }
        $.post(url,{"customer_id":UserID,"page":page_jump},function (res) {
            if(res.code == 200 && res.data.data!=''){
                var html = "<thead><tr>" +
                    "                        <th>时间</th>" +
                    "                        <th>订单编号</th>" +
                    "                        <th>详情</th>" +
                    "                        <th>状态</th>" +
                    "                        <th>积分</th>" +
                    "                    </tr></thead><tbody>";
                $.each(res.data.data,function (k,v) {
                    html+= "<tr>" +
                        "<td>"+v.CreateTime+"</td>"+
                        "<td>"+v.OrderNumber+"</td>"+
                        "<td>"+v.ReasonDetail+"</td>"+
                        "<td>"+v.Status+"</td>"+
                        "<td>"+v.PointsCount+"</td>"+
                        "</tr>"
                })
                html +="<tbody>";
            }else {
                html = "<thead><tr><td colspan='5'>查询不到信息</td></tr></thead>";
            }
            $(".ywmx").html(html);
            if(typeof (Page) == "undefined"){
                layui.use('laypage', function(){
                    var laypage = layui.laypage;
                    laypage.render({
                        elem: 'orderpage'
                        ,count: res.data.total //数据总数
                        ,limit:res.data.per_page
                        ,jump: function(obj){
                            getPoints(UserID,obj.curr);
                        }
                    });
                });
            }
        })
    }

    $(".scmx").click(function () {
        var UserID = $(this).attr("data-id");
        getStoreCredit(UserID);
        $(".nav-justified").find("li").removeClass("active");
        $(this).addClass("active");
    });

    function getStoreCredit(UserID,Page) {
        var url = "/MemberManagement/getStoreCredit";
        if(typeof (Page) == "undefined"){
            var page_jump = 1;
        }else {
            var page_jump = Page;
        }
        $.post(url,{"customer_id":UserID,"page":page_jump},function (res) {
            if(res.code == 200 && res.data.data!=''){
                var html = "<thead><tr>" +
                    "                        <th>时间</th>" +
                    "                        <th>订单编号</th>" +
                    "                        <th>站点</th>" +
                    "                        <th>Store Credit</th>" +
                    "                        <th>备注</th>" +
                    "                    </tr></thead><tbody>";
                $.each(res.data.data,function (k,v) {
                    html+= "<tr>" +
                        "<td>"+v.CreateTime+"</td>"+
                        "<td>"+v.OrderNumber+"</td>"+
                        "<td>"+v.RequestClientID+"</td>"+
                        "<td>"+v.CurrencyTypeVal+"</td>"+
                        "<td>"+v.Memo+"</td>"+
                        "</tr>"
                })
                html +="<tbody>";
            }else {
                html = "<thead><tr><td colspan='4'>查询不到信息</td></tr></thead>";
            }
            $(".ywmx").html(html);
            if(typeof (Page) == "undefined"){
                layui.use('laypage', function(){
                    var laypage = layui.laypage;
                    laypage.render({
                        elem: 'orderpage'
                        ,count: res.data.total //数据总数
                        ,limit:res.data.per_page
                        ,jump: function(obj){
                            getStoreCredit(UserID,obj.curr);
                        }
                    });
                });
            }
        })
    }

    /*获取订阅*/
    $(".dyxq").click(function () {
        var UserID = $(this).attr("data-id");
        var url = "/MemberManagement/getSubscribe";
        $.post(url,{"customer_id":UserID},function (res) {
            if(res.code == 200 && res.data.data!=''){
                var html = "<thead><tr>" +
                    "                        <th>添加时间</th>" +
                    "                        <th>订阅邮箱</th>" +
                    "                        <th>站点</th>" +
                    "                        <th>是否激活</th>" +
                    "                    </tr></thead><tbody>";
                    html+= "<tr>" +
                        "<td>"+res.data.CreateTime+"</td>"+
                        "<td>"+res.data.Email+"</td>"+
                        "<td>"+res.data.SiteId+"</td>"+
                        "<td>"+res.data.Active+"</td>"+
                        "</tr>";
                html +="<tbody>";
            }else {
                html = "<thead><tr><td colspan='4'>查询不到信息</td></tr></thead>";
            }
            $(".ywmx").html(html);
        })
        $(".nav-justified").find("li").removeClass("active");
        $(this).addClass("active");
        $("#orderpage").html("");
    });

    /*重置登录密码*/
    $(".czdlmm").click(function () {
        var UserID = $(this).attr("data-id");
        layer.prompt({title: '输入重置登录密码，并确认', formType: 1}, function(pass, index){
            if(pass.length<6 || pass.length>30){
                layer.msg("重置密码长度必须在6-30个字符",{'icon':5});return;
            }else {
                var url = "/MemberManagement/resetPassword";
                $.post(url,{'customer_id':UserID,'Password':pass},function (res) {
                    if(res.code == 200){
                        layer.msg("重置密码成功！",{'icon':1,'time':3000});
                    }else {
                        layer.msg(res.msg,{'icon':5});
                    }
                })
            }
            //layer.close(index);

        });

    });

    /*重置支付密码*/
    $(".czzfmm").click(function () {
        var UserID = $(this).attr("data-id");
        layer.prompt({title: '输入重置支付密码，并确认', formType: 1}, function(pass, index){
            if(pass.length<6 || pass.length>30){
                layer.msg("重置密码长度必须在6-30个字符",{'icon':5});return;
            }else {
                var url = "/MemberManagement/resetpaymentPassword";
                $.post(url,{'customer_id':UserID,'Password':pass},function (res) {
                    if(res.code == 200){
                        layer.msg("重置支付成功！",{'icon':1,'time':3000});
                    }else {
                        layer.msg(res.msg,{'icon':5});
                    }
                })
            }
            //layer.close(index);

        });

    });

    $(function(){
        Init();
    });
    return {
        memberManage:memberManage,
        getOrderList:getOrderList,
    }
}();