var YG_Order = {
    orderMonitor: function (dModule) {
        $('#reservationtime,#canceltime').daterangepicker({
            timePicker: true,
            timePickerIncrement: 5,
            format: 'YYYY-MM-DD HH:mm'
        });
        $('#tabs').on("click",function(event) {
            var has_class=$('#tab_5_3').hasClass('has_ajax');
            if(has_class){//限制请求次数，初始化has_ajax存在就请求，不存在就不请求
                $.ajax({
                    url: 'monitor.shtml',
                    type:"Get",
                    dataType: 'html',
                    success:function(msg){
                        $("#tab_5_3").html(msg);
                        $('#tab_5_3').removeClass('has_ajax');
                        depots();//仓库监控图表函数
                        G.ExcuteModule('#Order_Monitor', YG_Order.orderMonitor);//日期函数
                    },
                })
            }
        }); 
    }
};
function ordere_totl(){
	var visitors = [
            ['0', 0],
            ['1', 3],
            ['2', 5],
            ['3', 7],
            ['4', 8],
            ['5', 9],
            ['6', 9],
            ['7', 10],
            ['8', 9],
            ['9', 9],
            ['10',8],
            ['11',8],
            ['12',8],
            ['13',7],
            ['14',5],
            ['15',3],
            ['16',3],
            ['17',3],
            ['18',4],
            ['19',5],
            ['20',6],
            ['21',7],
            ['22',8],
            ['23',9],
        ];
    if ($('#site_statistics').size() != 0) {
        $('#site_statistics_loading').hide();
        $('#site_statistics_content').show();
        var plot_statistics = $.plot($("#site_statistics"),
            [
                {
                    //label: "订单总数:"+visitors[0][1],  //曲线名称
                    data: visitors,
                    barts: {
                        fill: 0.6,
                        lineWidth: 0,
                    },
                    color: ['#f89f9f']
                },
                {
                    data: visitors,
                    points: {
                        show: true,
                        fill: true,
                        radius: 5,
                        fillColor: "#f89f9f",
                        lineWidth: 3
                    },
                    color: '#fff',
                    shadowSize: 0
                },
            ],

            {

                xaxis: {
                    tickLength: 0,
                    tickDecimals: 0,
                    mode: "categories",
                    min: 0,
                    font: {
                        lineHeight: 14,
                        style: "normal",
                        variant: "small-caps",
                        color: "#6F7B8A"
                    }
                },
                yaxis: {
                    ticks: [0,7,10],//设置y轴显示的刻度
                    tickDecimals: 0,
                    tickColor: "#eee",
                    font: {
                        lineHeight: 14,
                        style: "normal",
                        variant: "small-caps",
                        color: "#6F7B8A"
                    }
                },
                grid: {
                    hoverable: true,
                    clickable: true,
                    tickColor: "#eee",
                    borderColor: "#eee",
                    borderWidth: 1
                }
            });
        var previousPoint = null;
        $("#site_statistics").bind("plothover", function (event, pos, item) {
            $("#x").text(pos.x.toFixed(2));
            $("#y").text(pos.y.toFixed(2));
            if (item) {
                if (previousPoint != item.dataIndex) {
                    previousPoint = item.dataIndex;
                    $(".tooltips").remove();
                    var x = item.datapoint[0].toFixed(2),
                        y = item.datapoint[1].toFixed(2);
                    showChartTooltip(item.pageX , item.pageY, "订单总数:" + item.datapoint[0] + "时合计:", item.datapoint[1] + '订单');
                }
            } else {
                $(".tooltips").remove();
                previousPoint = null;
            }
        });
    }
};
function amounts(){
    var amounts = [
            ['0', 0],
            ['1', 0],
            ['2', 0],
            ['3', 0],
            ['4', 4],
            ['5', 3],
            ['6', 2],
            ['7', 7],
            ['8', 8],
            ['9',9],
            ['10',10],
            ['11',7],
            ['12',6],
            ['13',5],
            ['14',4],
            ['15',3],
            ['16',2],
            ['17',2],
            ['18',2],
            ['19',2],
            ['20',3],
            ['21',4],
            ['22',5],
            ['23',6],
        ];
    if ($('#site_amounts').size() != 0) {
            $('#site_amounts_loading').hide();
            $('#site_amounts_content').show();
            var plot_amounts = $.plot($("#site_amounts"),
                [
                    {
                        //label: "订单总数:"+visitors[0][1],  //曲线名称
                        data: amounts,
                        barts: {
                            fill: 0.6,
                            lineWidth: 0,
                        },
                        color: ['#3da8b9']
                    },
                    {
                        data: amounts,
                        points: {
                            show: true,
                            fill: true,
                            radius: 5,
                            fillColor: "#3da8b9",
                            lineWidth: 3
                        },
                        color: '#fff',
                        shadowSize: 0
                    },
                ],

                {

                    xaxis: {
                        tickLength: 0,
                        tickDecimals: 0,
                        mode: "categories",
                        min: 0,
                        font: {
                            lineHeight: 14,
                            style: "normal",
                            variant: "small-caps",
                            color: "#6F7B8A"
                        }
                    },
                    yaxis: {
                        ticks: [0, 5,7],//设置y轴显示的刻度
                        tickDecimals: 0,
                        tickColor: "#eee",
                        font: {
                            lineHeight: 14,
                            style: "normal",
                            variant: "small-caps",
                            color: "#6F7B8A"
                        }
                    },
                    grid: {
                        hoverable: true,
                        clickable: true,
                        tickColor: "#eee",
                        borderColor: "#eee",
                        borderWidth: 1
                    }
                });
            var previousPoint = null;
            $("#site_amounts").bind("plothover", function (event, pos, item) {
                $("#x").text(pos.x.toFixed(2));
                $("#y").text(pos.y.toFixed(2));
                if (item) {
                    if (previousPoint != item.dataIndex) {
                        previousPoint = item.dataIndex;
                        $(".tooltips").remove();
                        var x = item.datapoint[0].toFixed(2),
                            y = item.datapoint[1].toFixed(2);
                        showChartTooltip(item.pageX , item.pageY, "订单金额:" + item.datapoint[0] + "时合计:", item.datapoint[1] + '元');
                    }
                } else {
                    $(".tooltips").remove();
                    previousPoint = null;
                }
            });
    }
};
function depots(){
    var depots= [[0.7,0], [0.7,3],null,[1.7,0], [1.7,2],null,[2.7,0], [2.7,2]];//已审核未流入库房数据
    var depots_2= [[0.6,0],[0.6,1],null,[1.6,0],[1.6,1],null,[4,0], [4,7]];//已流入库房未发货数据
    var depots_3 = [[3,0], [3,3]];//已发货数据
    if ($('#site_depots').size() != 0) {
        $('#site_depots_loading').hide();
        $('#site_depots_content').show();
        var plot_depots = $.plot($("#site_depots"),
            [
                {
                    label: "已审核未流入库房",  //曲线名称
                    data: depots,
                    barts: {
                        fill: 0.6,
                        lineWidth: 0,
                    },
                    color: ['#ecc654']
                },
                {
                    label: "已流入库房未发货",  //曲线名称
                    data: depots_2,
                    barts: {
                        fill: 0.6,
                        lineWidth: 0,
                    },
                    color: ['#f89f9f']
                },
                {
                    label: "已发货",  //曲线名称
                    data: depots_3,
                    barts: {
                        fill: 0.6,
                        lineWidth: 0,
                    },
                    color: ['#3DA8B9']
                },
                // {
                //     //data: depots,
                //     points: {
                //         show: true,
                //         fill: true,
                //         radius: 5,
                //         fillColor: "#f89f9f",
                //         lineWidth: 3
                //     },
                //     color: '#fff',
                //     shadowSize: 0
                // },
            ],

            {

                xaxis: {
                    autoscaleMargin:1,
                    ticks: [[0, "地区_广州仓库"], [1, "地区_广州仓库"], [2, "北京仓库"],[3, "阿道夫"],[4, "阿道夫"],[5, "阿道夫"],[6, "阿道夫"],[7, "阿道夫"]],//设置y轴显示的刻度
                    tickLength: 0,
                    tickDecimals: 0,
                    // mode: "categories",
                    min:0,
                    font: {
                        lineHeight: 14,
                        style: "normal",
                        variant: "small-caps",
                        color: "#6F7B8A"
                    }
                },
                yaxis: {
                    ticks: [0, 1, 2,3,4,5,6,7],//设置y轴显示的刻度
                    tickDecimals: 0,
                    tickColor: "#eee",
                    font: {
                        lineHeight: 14,
                        style: "normal",
                        variant: "small-caps",
                        color: "#6F7B8A"
                    }
                },
                grid: {
                    hoverable: true,
                    clickable: true,
                    tickColor: "#eee",
                    borderColor: "#eee",
                    borderWidth: 1
                }
            });
        var previousPoint = null;
        $("#site_depots").bind("plothover", function (event, pos, item) {
            $("#x").text(pos.x.toFixed(2));
            $("#y").text(pos.y.toFixed(2));
            if (item) {
                if (previousPoint != item.dataIndex) {
                    previousPoint = item.dataIndex;
                    $(".tooltips").remove();
                    var x = item.datapoint[0].toFixed(2),
                        y = item.datapoint[1].toFixed(2);
                    showChartTooltip(item.pageX , item.pageY,item.series.label+ item.datapoint[1] + '订单',' ');
                }
            } else {
                $(".tooltips").remove();
                previousPoint = null;
            }
        });
    }
};
function showChartTooltip(x, y, xValue, yValue) {
    $('<div  class="chart-tooltip tooltips">'+ xValue + yValue + '<\/div>').css({
        position: 'absolute',
        fontSize:'12px',
        display: 'none',
        top: y - 40,
        left: x - 40,
        border: '0px solid #ccc',
        padding: '2px 6px',
        'background-color': '#fff',
    }).appendTo("body").fadeIn(200);
}
$(function () {
    App.init();
    ordere_totl();
    amounts();
    G.ExcuteModule('#Order_Monitor', YG_Order.orderMonitor);
})