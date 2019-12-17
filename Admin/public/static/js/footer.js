/**
 * Created by lijunfang 20180808
 */
var Footer = function () {
    function Init() {
        LoadFrame();//框架加载
        //给框架设置高度
        setInterval(function () {
            $('.curholder iframe').height($('.curholder iframe').contents().find('body').height()).attr('scrolling', 'no');
            // console.log('hol'+ +new Date());
        }, 500);
        $('.logout').click(function () {
            var val = 1;
            $.ajax({
                type: "POST",
                url: "/Login/logout",
                // data:{val:val},
                dataType: "json",
                cache: false,
                success: function (msg) {
                    if (msg.code == 200) {
                        $(".success_data").after('<div class = "success_out" style="position: absolute;top:40%;left: 49%;background: #999999;padding: 10px 15px 10px 15px;font-size: 17px;color: #ffff;">' + msg.result + '</div>');
                        setTimeout(function () {
                            window.location = "/Login/index";
                        }, 2000);
                    } else {
                        $(".success_data").after('<div class = "success_out" style="position: absolute;top:40%;left: 49%;background: #999999;padding: 10px 15px 10px 15px;font-size: 17px;color: #ffff;">' + msg.result + '</div>');
                        setTimeout(function () {
                            $(".success_out").remove();
                        }, 2000);
                    }

                },
                error: function (error) { }
            });


        })
    };
    //添加一个页签
    function addTab($this, refresh) {
        $(".jericho_tab").show();
        $("#mainFrame").hide();
        $.fn.jerichoTab.tabIndex = $this.data('id');
        $.fn.jerichoTab.addTab({
            tabFirer: $this,
            title: $this.text().trim(),
            closeable: true,
            data: {
                dataType: 'iframe',
                dataLink: $this.attr('href')
            }
        }).loadData(refresh);
        return false;
    };
    //
    function LoadFrame() {
        var tabTitleHeight = 33; // 页签的高度
        //  初始化页签
        $.fn.initJerichoTab({
            renderTo: '.page-content',
            uniqueId: 'wmstab',
            contentCss: {
                'height': $('.page-content').height() - tabTitleHeight
            },
            tabs: [],
            loadOnce: true,
            tabWidth: 110,
            titleHeight: tabTitleHeight,
            isResize: false
        });
        setTimeout(function () {
            // 绑定菜单单击事件
            $('.page-sidebar').on('click', 'a', function (e) {
                e.preventDefault();
                if (!/^http:|^https:/g.test(this.protocol) || this.href.lastIndexOf('#') > -1) {
                    return;
                }
                if ($('.jericho_tabs').length > 11) {
                    layer.msg('最多只能同时打开12个窗口', { icon: 0, time: 1000 });
                    return false;
                }
                addTab($(this), true);
                $('#jerichotab_contentholder').height('auto');
            });
        }, 0);

        // 初始化点击第一个一级菜单
        $("#menu a.menu:first span").click();
        //  下拉菜单以选项卡方式打开
        $("#userInfo .dropdown-menu a").mouseup(function () {
            return addTab($(this), true);
        }); //
    };
    $(function () {
        Init();
    });

    return {

    };
}();
