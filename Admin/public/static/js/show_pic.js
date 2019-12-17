$(function(){
    //鼠标移入缩略图放大图片
    $(".show-pic").mouseover(function(event) {
        var _this = $(this),
            imgSrc = _this.attr("src"),
            enlarge_images = $("#enlarge_images"),
            top = $(document).scrollTop() + event.clientY + 10 + "px";
            left = $(document).scrollLeft() + event.clientX + 10 + "px";
            enlarge_images.show();
            enlarge_images.html('<img width="400" height="400" src="' + imgSrc + '" />');
            enlarge_images.css({"top":top, "left":left});
    });
    //鼠标移出缩略图隐藏放大的图片
    $(".show-pic").mouseout(function(event) {
        var enlarge_images = $("#enlarge_images");
            enlarge_images.hide();
            enlarge_images.html('');
    });
    $(".show-pic").click(function(event) {
        window.open($(this).attr("src"));
    })
})