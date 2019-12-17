var Seller = function() {
     /**
     * 初始化函数
     */
    function Init(){
      
    };

    /*
    *会员管理页面
     */
    function sellerManage() {
        $("#province").change(function () {
            var catalogId = $("#province").val();//console.log(catalogId);
            $.get("{:url('Seller/NationalSubordinate')}?province="+catalogId, function(result){
                $("#city option").remove();
                $("#country_town option").remove();
                $("#city").append(result);
                $("#country_town").append('<option value="">请选择</option>');

            });
        });
        $("#city").change(function () {
            var catalogId = $("#city").val();//console.log(catalogId);
            $.get("{:url('Seller/NationalSubordinate')}?province="+catalogId, function(result){
                // $("#city option").remove();
                $("#country_town option").remove();
                $("#country_town").append(result);
                // $("#country_town").append('<option value="">请选择</option>');

            });
        });
        //删除弹窗
        $('.operation-td').on('click','.delete-btn',function(){
            var that = $(this),
                _id = that.data('id');
            $('.delete-dialog-panel').find('.delete-submit').data('id',_id);
            layer.open({
                title: '删除理由',
                type: 1,
                skin: 'layui-layer-rim', //加上边框
                area: ['450px', '340px'], //宽高
                content: $('.delete-dialog-panel')
            });
        });
        //删除提交
        $('.delete-submit').click(function(){
            var _id = $(this).data('id'),
                _op_desc = $('.reason').val();
                $.ajax({
                    type:"POST",
                    url:"{:url('Seller/MerchantDelete')}",
                    data:{user_id:_id,op_desc:_op_desc},
                    dataType:"json",
                    cache:false,
                    success:function(msg){
                      if(msg.code == 200){
                          layer.msg(msg.msg, {icon: 1});
                          setTimeout(function(){
                             window.location.reload();
                          },1500);

                      }else{
                          layer.msg(msg.msg, {icon: 2});
                      }
                    },
                    error:function(error){}
                });
        });
        Common.AllSelect($('.selectAll'),$('.single-checkbox'));
    };
    function sellerEditor(){
        $('.editor-seller-submit').click(function(){
            var _sellerid = $(this).data('sellerid');
             $.ajax({
                //几个参数需要注意一下
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url: 'Seller/edit/id'+_sellerid,//url
                data: $('#editSeller').serialize(),
                success: function (result) {
                    //console.log(result);//打印服务端返回的数据(调试用)
                    if (result.code == 200) {
                            layer.msg(result.result, {icon: 1});
                    }else{
                        layer.msg(result.result, {icon: 2});
                    }
                    ;
                },
                error : function() {
                    alert("异常！");
                }
            });
        });
        //重置密码
        $('#reset_password').click(function(event) {
            var user_id  = $('#user_id').val();
            var email    = $('.input-email').val();
            layer.msg('确定重新生成密码么？', {
                time: 0, //不自动关闭
                btn: ['确定', '取消'],
                yes: function(index){
                    layer.close(index);
                    $.ajax({
                    type:"POST",
                    url:"{:url('seller/reset_password')}",
                    data:{user_id:user_id,email:email},
                    dataType:"json",
                    cache:false,
                    success:function(msg){
                            if(msg.code == 200){
                                layer.msg(msg.result, {icon: 1});
                            }else{
                                layer.msg(msg.result, {icon: 2});
                            }
                        },
                        error:function(error){layer.msg('获取信息出问题', {icon: 2});}
                    });
                }
            });
        });
   
        $('.reason_hide').click(function(event) {
            $(".reason_show_hide").remove();
        });
        $('.reason_show').click(function(event) {
            var _opdec = $(this).data('opdec');
            if($(".reason_show_hide").length === 0){
               $(".reason_add_delete").after('<dl class="c-h-dl-validator form-group clearfix show_hide reason_show_hide"><dd class="v-title"><label>原因：</label></dd><dd><div class="input-icon right"><i class="fa"></i><div><textarea name="op_desc" maxlength="500"  rows="8" cols="80">'+_opdec+'</textarea></div></div></dd><dt></dt></dl>');
            }
        });
                                              
    }
    $(function(){
        Init();
    });
    return {
       sellerManage:sellerManage,
       sellerEditor:sellerEditor
    }
}();