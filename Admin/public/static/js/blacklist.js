$(function(){
    $('.add-blacklist').click(function(){
        var html = '<div class="modal-body f12_i"><div class="info-pb10 c-h-dl-label100">';
            html += '<form  id="add_navbar" action="/AffiliateReport/blacklist/"><dl class="c-h-dl-validator form-group clearfix">';
            html += '<dd class="v-title"><label><em>*</em>AffilaiteID:</label></dd>';
            html += '<dd><textarea rows="8" name="affiliate_id" cols="40" placeholder="AffilaiteID一行一个"></textarea></dd>';
            html += '<dd class="v-title"><label><em>*</em>备注:</label></dd>';
            html += '<dd><textarea name="remarks" rows="8" cols="40" placeholder="备注不能超多300字"></textarea></dd>';
            html += '</form></div></div>';
        layer.open({
            title: "添加限制用户",
            content: html,
            type: 1,
            area: ['450px', '440px'],
            offset: '10px',
            btn: ["保存", "取消"],
            yes: function (index) {
                var formData = new FormData($( "#add_navbar" )[0]);
                $.ajax({
                    type:"POST",
                    url:"/AffiliateReport/add_black",
                    dataType: 'json',
                    data:formData,
                    async: false,
                    cache: false,
                    contentType: false,
                    processData: false,
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
})