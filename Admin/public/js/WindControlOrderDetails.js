$(function() {
    var $list = $(".listDescTemplate"),
        $text = $(".descText");

    $text.focus(function() {
        $list.show();
    }).click(function() { return false; });

    $("li", $list).click(function() {
        $text.val($(this).text());
    });

    $("html").click(function() {
        $list.hide();
    });

    $("#ucContactProcess_chkAuthRecord").change(function() {
        if ($(this).is(":checked")) {
            $("#dvAuthInfo").slideDown();
            $("#ucContactProcess_cblAuthTypes").find(":checkbox").attr("checked", false);
        } else
            $("#dvAuthInfo").slideUp();
    });

    $('.ptogtitle').click(function() {
        $(this).parent().siblings('.bDiv').toggle();
        $(this).toggleClass('vsble');
    });
    $(".show-pic").click(function(event) {
        window.open($(this).attr("src"));
    });

    $(".ui-tab-li").click(function() {
        var _this = $(this),
            ind = _this.index();
        _this.addClass('ui-tabs-selected ui-state-active').siblings().removeClass('ui-tabs-selected ui-state-active');
        $("#ucRiskTxnInfoMulti_pnContent .ui-tabs-panel").addClass('ui-tabs-hide');
        $("#ucRiskTxnInfoMulti_pnContent .ui-tabs-panel").eq(ind).removeClass('ui-tabs-hide');
        return false;
    });

    $(".tab-order li").click(function() {
        var _this = $(this),
            ind = _this.index(),
            orderMain = _this.parent().siblings('.order-main');
        _this.addClass('active').siblings().removeClass('active');
        orderMain.addClass('hide');
        orderMain.eq(ind).removeClass('hide');
        return false;
    });

    // 不通过弹窗
    $('.no_pass').click(function(){
        var _this = $(this),
            transactionid = _this.attr("data-transactionid"),
            siteid = _this.attr("data-siteid"),
            id = _this.attr("data-id");
        $(".wind_control_pop_btn").attr({"data-id":id,"data-transactionid":transactionid,"data-siteid":siteid});
        $(".wind_control_pop_mask").show();
        $(".wind_control_pop").show();
    })

    //关闭不通过弹窗
    $(".close_wind_control_pop, .cancel_wind_control_pop").click(function() {
        $(".wind_control_pop_mask").hide();
        $(".wind_control_pop").hide();
    });

    // 搜索
    $(".order-search-btn").click(function(event){
        event.preventDefault();
        var val = $.trim($("#order-number").val()),
            id = $("#CustomerID").val(),
            _url = '',
            reg = /^\d+$/,
            isTrue = reg.test(val);
        if(!val || !isTrue){
            layer.msg("请输入订单号", {icon: 2});
            return false;
        }
        _url = "/WindControl/WindControlOrderDetails/OrderNumber/"+val+"/CustomerID/"+id;
        window.open(_url); 
    });

    $(".egp-bin-code").click(function(event) {
        event.preventDefault();
        var code = $(this).attr("data-code");
        $.ajax({
            type: 'get',
            url: '/WindControl/getBrank?Number='+code,
            success: function (response) {
                if(response.code == 200){
                    var html = '',
                        _data = response.data,
                        _Number=_Brand=_Bank=_Type=_Level=_Iso=_Info=_CountryIso=_Country2Iso=_Country3Iso=_Www=_Phone=_FormerBank=_Address='';
                    if(!$.isEmptyObject(response.data)){
                        _Number = _data.Number;
                        _Brand = _data.Brand;
                        _Bank = _data.Bank;
                        _Type = _data.Type;
                        _Level = _data.Level;
                        _Iso = _data.Iso;
                        _Info = _data.Info;
                        _CountryIso = _data.CountryIso;
                        _Country2Iso = _data.Country2Iso;
                        _Country3Iso = _data.Country3Iso;
                        _Www = _data.Www;
                        _Phone = _data.Phone;
                        _FormerBank = _data.FormerBank;
                        _Address = _data.Address;
                    }
                    html+= '<p class="CmInfo"><label>Number</label><span >'+_Number+'</span></p>';
                    html+='<p class="CmInfo"><label>Brand</label><span >'+_Brand+'</span></p>';
                    html+='<p class="CmInfo"><label>Bank</label><span >'+_Bank+'</span></p>';
                    html+='<p class="CmInfo"><label>Type</label><span >'+_Type+'</span></p>';
                    html+='<p class="CmInfo"><label>Level</label><span >'+_Level+'</span></p>';
                    html+='<p class="CmInfo"><label>ISO</label><span >'+_Iso+'</span></p>';
                    html+='<p class="CmInfo"><label>Info</label><span >'+_Info+'</span></p>';
                    html+='<p class="CmInfo"><label>Country_iso</label><span >'+_CountryIso+'</span></p>';
                    html+='<p class="CmInfo"><label>Country2_iso</label><span >'+_Country2Iso+'</span></p>';
                    html+='<p class="CmInfo"><label>Country3_iso</label><span >'+_Country3Iso+'</span></p>';
                    html+='<p class="CmInfo"><label>Www</label><span >'+_Www+'</span></p>';
                    html+='<p class="CmInfo"><label>Phone</label><span >'+_Phone+'</span></p>';
                    html+='<p class="CmInfo"><label>Former_bank</label><span >'+_FormerBank+'</span></p>';
                    html+='<p class="CmInfo"><label>Address</label><span >'+_Address+'</span></p>';

                    $(".bin-code-main").html(html);
                    $(".wind_control_pop_mask").show();
                    $(".bin-code-detail-pop").show();
                }else{
                    layer.msg(response.msg, {icon: 2});
                }
            }
         })
    });
    $(".bin-code-close").click(function() {
        $(".wind_control_pop_mask").hide();
        $(".bin-code-detail-pop").hide();
    });

});

function hideContactProcess() {
    $("#divContactProcess").hide();
}

function showContactProcess() {
    $("#divContactProcess").show();
}

$(".accordion").collapse({
    show: function() {
        this.animate({
            opacity: 'toggle',
            height: 'toggle'
        }, 300);
    },
    hide: function() {
        this.animate({
            opacity: 'toggle',
            height: 'toggle'
        }, 300);
    }
});

//回复弹出功能
$('.riskReply1').click(function(){
    var _id = $(this).data('id');
    $('#riskReplyId').val(_id);
    layer.open({
        title: '回复',
        type: 1,
        skin: 'layui-layer-rim', //加上边框
        area: ['500px', '400px'], //宽高
        content:$('#riskReplyDialog')
    });
});
//值改变的时候
$('#riskReplySelect').change(function(){
    var _that = $(this),
        _val = _that.val();
    $('#riskReplyTextarea').val(_val);
})
//表单数据提交的时候
$('body').on('click','#riskReplySubmit',function(){
    var dataParam = $('#riskReplyDataPost').serialize();
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