$(function(){
    $('.addUserForm').click(function(event) {
        var that = $(this),
            url = that.data('url'),
            params = {
                'type': 2,
                'to': 'alluser',
                'title': $("#notification-title").val(),
                'body': $("#notification-content").val(),
                'data': {}
            },
            id = $(".notification-type option:selected").val(),
            notificationTypeItem = $('.notification-type-item');

        params.data.type = id;
        if (id == 1) {
            params.data.id = $("#notification-product-SPU").val();
        } else if (id == 2) {
            params.data.id = $("#notification-category-id").val();
            params.data.class_name = $("#notification-category-name").val();
            params.data.activity_img = $("#notification-category-img").val();
        } else if (id == 3) {
            params.data.activity_url = $("#notification-active-link").val();
            params.data.activity_img = $("#notification-active-img").val();
        }

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: params,
            success: function(data){
                if(data.code == 200){
                    layer.msg(data.msg, {icon: 1});
                }else{
                    layer.msg(data.msg, {icon: 2});
                }
            }
        });         
    });
    
    $(".notification-type").change(function(){
        var index = $(this).prop('selectedIndex');
        $(".notification-type-item").addClass("hide");
        $(".notification-type-item input").val("");
        if(index !=0){
            $(".notification-type-item").eq(index-1).removeClass("hide");
        }
    });
});