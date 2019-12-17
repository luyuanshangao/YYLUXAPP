layui.use('upload', function() {
    var upload = layui.upload;

    //执行实例
    var uploadInst = upload.render({
        elem: '#addImg', //绑定元素
        url: '/Image/upload', //上传接口
        multiple: true,
        field: "imgFile",
        before: function(obj) {
            obj.preview(function(index, file, result) {
                $('#preview').append('<p class="layui-upload-img">' + file.name + '</p>');
            });
            layer.msg('正在上传中，请耐心等候',{
                time: 800
            });
        },
        done: function(res) {
            if (res.error == 0 && res.url != "") {
                $(".done-img-list").append('<img class="show-pic" src="' + res.url + '" />');
                $(".done-img-link-list").append('<p class="img-link-item clearfix"><a href="' + res.url + '" class="link-a" title="' + res.url + '" target="_blank">' + res.url + '</a><a href="javascript:;" class="get-url" data-href="' + res.url + '">复制</a><a href="javascript:;" class="del-url" data-href="' + res.url + '" data-id="'+res.id+'">删除</a></p>');
                $(".get-all-url").removeClass("hide");
            }
        },
        error: function(index) {}
    });
});
// 复制单个url
var clipboard = new Clipboard('.get-url', {
    text: function(trigger) {
        return trigger.getAttribute('data-href');
    }
});
clipboard.on('success', function(e) {
    layer.msg('复制成功',{
        time: 800
    });
});
clipboard.on('error', function(e) {
    layer.msg('复制失败',{
        time: 800
    });
});

function getAll() {
    var getUrl = $(".get-url"),
        getUrlLen = getUrl.length,
        allUrl = [];

    for (var i = 0; i < getUrlLen; i++) {
        allUrl.push(getUrl.eq(i).attr("data-href"));
    }
    var arr = allUrl.join('\r\n');
    $(".get-all-url").attr("data-val", arr);
}

// 复制所有url
var allClipboard = new Clipboard('.get-all-url', {
    text: function(trigger) {
        getAll();
        return trigger.getAttribute('data-val');
    }
});
allClipboard.on('success', function(e) {
    layer.msg('复制成功',{
        time: 800
    });
});
allClipboard.on('error', function(e) {
    layer.msg('复制失败',{
        time: 800
    });
});

//鼠标移入缩略图放大图片
$(".done-img-list").on("mouseover", ".show-pic", function(event) {
    var _this = $(this),
        imgSrc = _this.attr("src"),
        enlarge_images = $("#enlarge_images"),
        top = $(document).scrollTop() + event.clientY + 10 + "px";
    left = $(document).scrollTop() + event.clientX + 10 + "px";
    enlarge_images.show();
    enlarge_images.html('<img width="400"  src="' + imgSrc + '" />');
    enlarge_images.css({ "top": top, "left": left });
});
//鼠标移出缩略图隐藏放大的图片
$(".done-img-list").on("mouseout", ".show-pic", function(event) {
    var enlarge_images = $("#enlarge_images");
    enlarge_images.hide();
    enlarge_images.html('');
});
// 选中缩略图
$(".done-img-list").on("click", ".show-pic", function(event) {
    var _this = $(this),
        _index = _this.index();

    _this.addClass("active").siblings().removeClass("active");
    $(".link-a").removeClass("active").eq(_index).addClass("active");
});
//  删除图片
$(".done-img-link-list").on("click", ".del-url", function(event) {
    var _this = $(this),
        _id = _this.attr("data-id"),
        _index = _this.parent().index();

    $.ajax({
        url: '/Image/del',
        type: 'post',
        dataType: 'json',
        data: { "id": _id },
        success: function(data) {
            if (data.code == 200) {
                _this.parent().remove();
                $(".show-pic").eq(_index).remove();
                $(".layui-upload-img").eq(_index).remove();
                layer.msg('删除成功',{
                    time: 800
                });
            } else {
                layer.msg('删除失败',{
                    time: 800
                });
            }
        }
    })
});