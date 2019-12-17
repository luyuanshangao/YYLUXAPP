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