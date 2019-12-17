function queryLogistics() {
    var nums = $("#Logistics-tracking-number").val().trim();
    if (nums) {
        var arr = nums.split(",")
        var params = { tracking_number: arr }
        $.ajax({
            type: "POST",
            url: '/DataQuery/LogisticsNode',
            data: params,
            success: function(data) {
                if (data.code == 200) {
                    var _data = data.data;
                    var html = '';
                    $.each(_data, function(n, v) {
                        var raw = $.parseJSON(v.raw_data);
                        html += '<div class="tab-content clearfix mb50">';

                        html += '<div class=" active detail-box">';
                        html += '<div class="">订单号：' + v.order_number + '</div>';
                        html += '<div class="">物流跟踪号：' + v.tracking_number + '</div>';
                        html += '<div class="db-left">';
                        html += '<div class="db-left-bottom js-logistic-tabs curr">' + v.country + '<span>Destination country</span></div>';
                        html += '<div class="db-left-bottom js-logistic-tabs">China<span>Sending country</span></div></div>';
                        html += '<div class="info-pb10 c-h-dl-label100 mt10 db-right">';
                        html += '<ul class="layui-timeline">';
                        if(raw.track.z2.length > 2){
                          html += '<a href="javascript:;">点击展开查看更多信息 &#8595;</a>';
                        }
                        if (raw.track.z2.length > 0) {
                            $.each(raw.track.z2, function(z2N, z2V) {
                                html += '<li class="layui-timeline-item"><i class="layui-icon layui-timeline-axis">&#xe63f;</i>';
                                html += '<div class="layui-timeline-content layui-text">';
                                html += '<h3 class="layui-timeline-title">' + z2V.a + '</h3>';
                                html += '<p>' + z2V.z + '</p></div></li>';
                            })
                        } else {
                            html += '尚未有信息';
                        }
                        html += '</ul>';
                        html += '<ul class="layui-timeline hide">';
                        if(raw.track.z1.length > 2){
                          html += '<a href="javascript:;" class="view-more">点击展开查看更多信息 &#8595;</a>';
                        }
                        if (raw.track.z1.length > 0) {
                            $.each(raw.track.z1, function(z1N, z1V) {
                                html += '<li class="layui-timeline-item"><i class="layui-icon layui-timeline-axis">&#xe63f;</i>';
                                html += '<div class="layui-timeline-content layui-text">';
                                html += '<h3 class="layui-timeline-title">' + z1V.a + '</h3>';
                                html += '<p>' + z1V.z + '</p></div></li>';
                            });
                        } else {
                            html += '尚未有信息';
                        }
                        html += '</ul>';
                        html += '</div></div></div>';
                    });
                    $("#logistics-List").html(html);
                }
            },
            error: function(error) {
                console.log(error)
            }
        })
    }
}

$("#query-logistics").click(function() {
    queryLogistics();
});

$("body").on("click", "#logistics-List .js-logistic-tabs", function() {
    var that = $(this),
        _index = that.index();
    that.addClass('curr').siblings().removeClass('curr');
    that.parent().siblings('.db-right').find('.layui-timeline').eq(_index).removeClass('hide').siblings('.layui-timeline').addClass('hide');
});

$("body").on("click", ".view-more", function() {
    var _this = $(this),
        h = _this.parent().height();

    if (h > 70) {
        $(".layui-timeline").css({ "height": "70px" });
        _this.html('点击展开查看更多信息 &#8595;');
    } else {
        _this.parent().css({ "height": "auto" });
        _this.html('收起详情 &#8593;');
    }
});