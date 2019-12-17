var Review = function() {
    /**
     * 初始化函数
     */
    function Init(){

    };

    /*
    *会员管理页面
     */
    function reviewManage() {
        $('.update-status').on('click',function(){
            var that = $(this),
                _id = that.data('id'),
                _Status = that.data('status');
            _url = "/Review/updateStatus";
            layer.confirm('确定执行此操作吗？',{
                    btn: ['确定','取消']}
                ,function(){
                    $.post(_url,{"Status":_Status,"ID":_id},function (res) {
                        if(res.code == 200){
                            layer.msg(res.msg,{"icon":6,"time": 2000});
                            window.location.reload()
                        }else {
                            layer.msg(res.msg,{"icon":5});
                        }
                    })
                }
            );
        });

        $('.update-status-all').on('click',function(){
            var ids = new Array();
            $.each($('input:checkbox:checked'),function(){
                if($(this).val() != 'on'){
                    ids.push($(this).val());
                }
            });
            var that = $(this),
                _Status = that.data('status');
            _url = "/Review/updateStatus";
            layer.confirm('确定执行此操作吗？',{
                    btn: ['确定','取消']}
                ,function(){
                    $.post(_url,{"Status":_Status,"ID":ids},function (res) {
                        if(res.code == 200){
                            layer.msg(res.msg,{"icon":6,"time": 2000});
                            window.location.reload()
                        }else {
                            layer.msg(res.msg,{"icon":5});
                        }
                    })
                }
            );
        });
        $('.add_review').on('click',function(){
            var _length = '800px',
                _width = '600px';
                _url = "/Review/addReview"
            $.get(_url, function (data) {
                layui.use('layer', function() {
                    var layer = layui.layer;
                    layer.open({
                        title: "添加评论",
                        content: data,
                        type: 1,
                        area: [_length, _width],
                        offset: ['100px','150px'],
                        zIndex: 10,
                        btn: ["添加", "取消"],
                        success: function (layero) {
                            layero.find('.layui-layer-btn').css('text-align', 'center'); //改变位置
                        },
                        yes: function (index) {
                            var paramData = {};
                            var formData = $("#AddReviewFrom").serializeArray();
                            $.each(formData, function() {
                                paramData[this.name] = this.value;
                            });
                            var reviwsVideo = $("#video");
                            if(reviwsVideo.val() && (!checkYoutube(reviwsVideo.val()))){
                                layer.msg("视频地址格式错误", {icon: 2});
                                return false;
                            }else if(reviwsVideo.val() && (checkYoutube(reviwsVideo.val()))){
                                paramData['video'] = getYoutubecode(reviwsVideo.val());
                            }
                            var sku_num = $("#sku_num").val();
                            var customer_name = $("#customer_name").val();
                            var country_code = $("#country_code").val();
                            var content = $("#content").val();
                            if(sku_num == ''){
                                layer.msg("SKU不能为空", {icon: 2});return false;
                            }
                            if(customer_name == ''){
                                layer.msg("用户名不能为空", {icon: 2});return false;
                            }
                            if(country_code == ''){
                                layer.msg("国家不能为空", {icon: 2});return false;
                            }
                            if(content == ''){
                                layer.msg("评论内容不能为空", {icon: 2});return false;
                            }
                            $.post(_url,paramData,function (msg) {
                                if (msg.code == 200) {
                                    layer.msg(msg.msg, {icon: 1});
                                    setTimeout(function () {
                                        window.location.reload();
                                    }, 1500);
                                } else {
                                    layer.msg(msg.msg, {icon: 2});
                                }
                            })
                            /*$.ajax({
                                type: "POST",
                                url: _url,
                                dataType: 'json',
                                data: formData,
                                async: false,
                                cache: false,
                                contentType: false,
                                processData: false,
                                // data:JsonData,
                                success: function (msg) {
                                    if (msg.code == 200) {
                                        layer.msg(msg.msg, {icon: 1});
                                        setTimeout(function () {
                                            window.location.reload();
                                        }, 1500);
                                    } else {
                                        layer.msg(msg.msg, {icon: 2});
                                    }
                                }
                            });*/
                        },
                        cancel: function () {
                        }
                    });
                });
            })
        })
    };

    //检查是否是youtube链接
    function checkYoutube(url) {
        var re = /^((http|https):\/\/(?:www.)?(youtu.be|youtube.com)).*/;
        if(!re.test(url)) {
            return false;
        } else {
            return true;
        }
    }
    function getYoutubecode(url) {
        // var url = "http://youtu.be/dQw4w9WgXcQ?feature=youtube_gdata_player";
        var rx = /^.*(?:(?:youtu\.be\/|v\/|vi\/|u\/\w\/|embed\/)|(?:(?:watch)?\?v(?:i)?=|\&v(?:i)?=))([^#\&\?]*).*/;
        r = url.match(rx);
        return r[1];
    }
    $(function(){
        Init();
    });
    return {
        reviewManage:reviewManage,
    }
}();