var DdElectronic  = function() {
    function Init(){

    };
    function electronic_coil(){
        $('.add-electronic-coil').click(function(event) {
            layer.open({
                title: '生成点券',
                type: 1,
                skin: 'layui-layer-rim', //加上边框
                area: ['450px', '320px'], //宽高
                content: '<div class="pl30">' +
                    '<form id="form_electronic_coil" class="navbar-left"  method="post">'+
                    '<div class="mt20">' +
                    '<label class="w120 tright">电子卷数量：</label>' +
                    '<input type="text" value="" name="roll_sum" size="25" class="declare_en" datatype="require" placeholder="英文海关品名" >' +
                    '</div>' +
                    '<div class="mt30 tcenter">' +
                    '<a href="#"  class = "submit-electronic-coil btn-qing f18">提交</a>' +
                    '</div>' +
                    '</div>'+
                    '</form>'
            });
        })
        $('body').on('click','.submit-electronic-coil',function(){
            var that    = $(this);
                // val = that.val();
            $.ajax({
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url:'/DdElectronicCoil/addElectronicCoil',//url"/PaymentSetting/eidt_config"
                data: $('#form_electronic_coil').serialize(),
                success: function (result) {
                    if (result.code == 200) {
                        layer.msg(result.result, {icon: 1});
                        setTimeout(function(){
                            window.location.reload();
                        },1500);
                    }else{
                        layer.msg(result.result, {icon: 2});
                    };
                },
                error : function() {
                    layer.msg("异常！");
                }
            });
            // console.log(val);
        })
        //点券状态修改
        $('.electronic_coil_status').change(function(event) {
             var that    = $(this),status=that.val(),electronicCoil_id = that.data('electroniccoilid');
             $.ajax({
                type:"POST",
                url:"/DdElectronicCoil/ElectronicCoilStatus",
                dataType: 'json',
                data:{status:status,electronicCoil_id:electronicCoil_id},
                cache:false,
                success:function(msg){
                    // console.log(12);return;
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

        })
    }


    $(function(){
        Init();
    });
    return {
        electronic_coil:electronic_coil

    }
}();