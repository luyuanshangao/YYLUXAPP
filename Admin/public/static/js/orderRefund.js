$(function() {
    $('.audit-status').on('click', function () {
        /*var that = $(this),
            _id = that.data('id'),
            _Status = that.data('status'),
            _From_Status = that.data('from-status'),
            _url = "/CustomerService/refundAudit";*/
        var that = $(this),
            _id = new Array(),
            _Status = that.data('status');
        _id.push(that.data('id'));
        audit_refund(_id, _Status)
    });

    //审核通过
    $(".audit-pass").on('click',function () {
        var ids = new Array();
        var status = 2;
        $.each($('input:checkbox:checked'),function(){
            if($(this).val() != 'on'){
                ids.push($(this).val());
            }
        });
        audit_refund(ids,status);
    })

    //审核拒绝
    $(".audit-reject").on('click',function () {
        var ids = new Array();
        var status = 4;
        $.each($('input:checkbox:checked'),function(){
            if($(this).val() != 'on'){
                ids.push($(this).val());
            }
        });
        audit_refund(ids,status);
    })

    //Refund
    $('.query-refund-order').click(function(event){
        $("#is_export").val(0);
        $("#navbar").submit();
    })
    /*$('.export-refund-order').click(function(event){
        $("#is_export").val(1);
        $("#navbar").submit();
    })*/
});

function audit_refund(refund_id,status) {
    loadingIndex = layer.load(1, {
        shade: [0.5,'#000'], //0.1透明度的白色背景
        offset:'22%'
    });
    var _id = refund_id,
        _Status = status,
        _url = "/CustomerService/refundAudit";
    if(_Status == 2){
        layer.confirm('确定执行此操作吗？',{
                btn: ['确定','取消'],
                offset:'20%'
            }
            ,function(){
                $.post(_url,{"status":_Status,"refund_id":_id},function (res) {
                    layer.close(loadingIndex);
                    if(res.code == 200){
                        layer.msg(res.msg,{"icon":6,"time": 2000});
                        window.location.reload()
                    }else {
                        layer.msg(res.msg,{"icon":5});
                    }
                })
            },
            function (index) {
                layer.close(loadingIndex);
            }
        );
    }else {
        layer.open({
            title: '审核拒绝理由',
            type: 1,
            skin: 'layui-layer-rim', //加上边框<form id="examine_submit"  method="post">
            area: ['420px', '340px'], //宽高
            offset:'20%',
            content: '<div class="ml30"><form id="examine_submit"  method="post"><input type="hidden" value="'+_id+'" name="refund_id"><input type="hidden" value="'+_Status+'"  name="status"><div class="mt10"><label class="reason relative">理由：</label><textarea class="audit_remarks" name="audit_remarks" cols="37" rows="9"></textarea></div></form><a href="javascript:;" onclick = "Status_submit(\''+_url+'\')" class = "btn btn-success btn-sm submit" style="margin: 10px 133px;">提交</a></div>',
            cancel: function(){
                layer.close(loadingIndex);
            }
        });
    }
}

function Status_submit(url){
    var formData = new FormData($( "#examine_submit" )[0]);
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
                layer.msg(msg.result, {icon: 1});
                setTimeout(function(){
                    window.location.reload();
                },2000);
            }else{
                layer.msg(msg.result, {icon: 2});
            }
        }
    });
}