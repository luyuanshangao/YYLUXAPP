
var YG_Order = {
    orderList: function (dModule) {
        moment.locale('zh-cn');
        $('#reservationtime,#canceltime').daterangepicker({
                timePicker: true,
                timePickerIncrement: 5,
                format: 'YYYY-MM-DD HH:mm'
            },
            function (start, end, label) {
                console.log(start.toISOString(), end.toISOString(), label);
            });
//        moment.locale('zh-cn');
        $('#more-search').click(function () {
            $('#search-standard').hide();
            $('#search-advanced').slideDown();
        });

        $('#btn-search-standard').click(function () {
            $('#search-standard').show();
            $('#search-advanced').slideUp();
        });
        LoadSelect('.bs-select');
    },
    orderDtails: function (dMoudle) {
        $('#change_goods').on("click",function(){
            $.get("pop_product_edit.html", function(data){
                bootbox.dialog({
                    message: data,
                    width:900,
                    animate:false,
                    buttons: {
                        ok:{
                            label:"确定",
                            className:"btn-qing",
                            callback:function(){

                            }
                        },
                        cancel: {
                            label: "取消",
                            className: "btn-gray",
                            callback: function () {
                            }
                        }
                    }
                });
                 // bootbox.dialog({})位置居中的函数
                 dialogCenter();
                 tableDialogClose();//关闭按钮的位置调整
            });
        });
        //改变窗口大小的时候调用位置居中函数
        $(window).resize(function(event) {
            dialogCenter();
        });
        $('#yougouPrice,.trip10', dMoudle).popover({trigger: 'hover', container: 'body', html: true, placement: 'bottom', content: function () {
            return '<div><p><label class="w180 tright">10.27cat&迈乐限时抢-接券 ：</label>10 </p><p><label class="w180 tright">&迈乐限时抢-接券：</label>20 </p><p class="border-top-gray pt5"><label class="w180 tright"></label>30</p></div>';
        }});
        $('#coupons', dMoudle).popover({container: 'body', html: true, placement: 'bottom', content: function () {
            var s_content = [];
            s_content.push('<table class="table-pop">');
            s_content.push('<tr><td colspan="4" class="tcenter bg-gray">优惠券（ <span class="Red">已使用</span>）</td></tr>');
            s_content.push('<tr>');
            s_content.push('<td>优惠券编码：</td>');
            s_content.push('<td>D2********AB6</td>');
            s_content.push('<td>面值：</td>');
            s_content.push('<td>&yen;40</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>获取渠道：</td>');
            s_content.push('<td>线下</td>');
            s_content.push('<td>最低消费：</td>');
            s_content.push('<td>&yen;0.0</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>有效期开始时间：</td>');
            s_content.push('<td>2014-07-17 00:00:00</td>');
            s_content.push('<td>有效期结束时间：</td>');
            s_content.push('<td>2014-07-27 23:59:00</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>优惠券方案：</td>');
            s_content.push('<td colspan="3">20140717市场部申请7月中下旬联盟40元优惠券</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>使用范围：</td>');
            s_content.push('<td colspan="3">全场商品（特殊商品除外，有关优惠券的使用具体规则，请见本页下面的使用详细说明）</td>');
            s_content.push('</tr>');
            s_content.push('<tr><td colspan="4" class="tcenter bg-gray">赠送优惠券</td></tr>');
            s_content.push('<tr><td colspan="4" class="tcenter"><span class="Red">未赠送优惠券</span></td></tr>');
            s_content.push('</table>');
            return s_content.join('');
        }});
        G.POPOVER["coupons"]="coupons";
        $('#giftCards', dMoudle).popover({container: 'body', html: true, placement: 'bottom', content: function () {
            var s_content = [];
            s_content.push('<table class="table-pop">');
            s_content.push('<tr><td colspan="4" class="tcenter bg-gray">礼品卡（ <span class="Red">已使用</span>）</td></tr>');
            s_content.push('<tr>');
            s_content.push('<td>优惠券编码：</td>');
            s_content.push('<td>D3********46D</td>');
            s_content.push('<td>面值：</td>');
            s_content.push('<td>&yen;50</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>获取渠道：</td>');
            s_content.push('<td>线下</td>');
            s_content.push('<td>最低消费：</td>');
            s_content.push('<td>&yen;0.0</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>有效期开始时间：</td>');
            s_content.push('<td>2014-07-17 00:00:00</td>');
            s_content.push('<td>有效期结束时间：</td>');
            s_content.push('<td>2014-07-27 23:59:00</td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>优惠券方案：</td>');
            s_content.push('<td>20140717运营部申请店庆3周年员工回馈50元礼品卡</td>');
            s_content.push('<td>礼品卡号：</td>');
            s_content.push('<td></td>');
            s_content.push('</tr>');
            s_content.push('<tr>');
            s_content.push('<td>使用范围：</td>');
            s_content.push('<td colspan="3">全场商品</td>');
            s_content.push('</tr>');
            s_content.push('</table>');
            return s_content.join('');
        }});
        G.POPOVER["giftCards"]="giftCards";

        $('#c_order_more', dMoudle).popover({container: 'body', html: true, placement: 'bottom', content: function () {
            var s = '';
            $.ajax({
                type: 'GET',
                url: 'pop_order_details_more.html',
                dataType: 'html',
                async: false,
                error: function (e) {
                    s = e.toString();
                },
                success: function (data) {
                    s = data;
                }
            });
            return s;
        }}).on('shown.bs.popover', function () {
            var _nav = $('.yg-pop-nav .nav'),
                _content = $('.yg-pop-nav .tab-content');
            //_content.css('margin-left', _nav.outerWidth());
        });
        G.POPOVER["c_order_more"]="c_order_more";

        $('.navbar-basic').NavBarBasic(function (obj) {
            console.log(obj.html());
        });

//        $('.navbar-basic').NavBarBasic();

        $('#fConfirm,#expressCancel').click(function (e) {
            e.preventDefault();
            /*            bootbox.dialog({
             message: "您是否要申请退款？",
             title: "确认退款",
             buttons: {
             ok: {
             label: "确定",
             className: "btn-qing",
             callback: function () {
             alert("哟，确定了！");
             }
             },
             cancel: {
             label: "取消",
             className: "btn-gray",
             callback: function () {
             alert("哎，取消了!");
             }
             }
             }
             });*/
            $('#expressInfo').toggle();
            $('#form-express').toggleClass('hide');

        });

        $('#fReg').click(function (e) {
            e.preventDefault();
            bootbox.alert("<span class='Red'>警告：您不能申请退款</span>");
        });

        var handleValidation2 = function () {
            // for more info visit the official plugin documentation:
            // http://docs.jquery.com/Plugins/Validation

            var form2 = $('#form_send_address');
            var error2 = $('.alert-danger', form2);
            var success2 = $('.alert-success', form2);

            form2.validate({
                errorElement: 'span', //default input error message container
                errorClass: 'help-block', // default input error message class
                focusInvalid: false, // do not focus the last invalid input
                ignore: "",
                rules: {
                    name: {
                        minlength: 2,
                        required: true
                    },
                    region: {
                        required: true
                    },
                    addressInfo: {
                        minlength: 5,
                        required: true
                    },
                    mobile: {
                        required: true
                    },
                    zip: {
                        required: true
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    senddate: {
                        required: true
                    }
//                number: {
//                    required: true,
//                    number: true
//                },
//                digits: {
//                    required: true,
//                    digits: true
//                },
//                creditcard: {
//                    required: true,
//                    creditcard: true
//                },
                },
                messages: {
                    name: {
                        minlength: '名称不能小于2位',
                        required: '必须输入名称'
                    },
                    region: {
                        required: '必须选择地区'
                    },
                    addressInfo: {
                        minlength: '地址信息输入错误',
                        required: '必须输入详细地址'
                    },
                    mobile: {
                        required: '手机和电话至少输入一个'
                    },
                    zip: {
                        required: '邮编必须输入'
                    },
                    email: {
                        required: '必须输入电子邮箱',
                        email: '电子邮箱地址错误'
                    },
                    senddate: {
                        required: '必须选择送货日期'
                    }
                },

                invalidHandler: function (event, validator) { //display error alert on form submit
                    success2.hide();
                    error2.show();
                    App.scrollTo(error2, -200);
                },

                errorPlacement: function (error, element) { // render error placement for each input type
                    var icon = $(element).parent('.input-icon').children('i');
                    icon.removeClass('fa-check').addClass("fa-warning");
                    //icon.attr("data-original-title", error.text()).tooltip({'container': 'body'});
                    $(element).closest('.form-group').removeClass('has-success');
                    $(element).closest('.c-h-dl-validator').find('dt').html(error.text());
                },

                highlight: function (element) { // hightlight error inputs
                    $(element).closest('.form-group').addClass('has-error'); // set error class to the control group
                },

                unhighlight: function (element) { // revert the change done by hightlight

                },

                success: function (label, element) {
                    var icon = $(element).parent('.input-icon').children('i');
                    $(element).closest('.form-group').removeClass('has-error').addClass('has-success'); // set success class to the control group
                    icon.removeClass("fa-warning").addClass("fa-check");
                },

                submitHandler: function (form) {
                    success2.show();
                    error2.hide();
                }
            });
        };

        handleValidation2();

        $('#sAddress,#addressCancel').click(function (e) {
            e.preventDefault();
            $('#addressText').toggle();
            $('#form_send_address').toggleClass('hide');
        });

/*        G.bindButton({region: '.selectChangeNumber', callback: function (type, id, value, obj) {
            console.log(value);
        }});*/

/*        $('#changeProduct', dMoudle).click(function (e) {
            e.preventDefault();
            var $this = $(this);
            var t = (+new Date()) % 2 == 0 ? true : false;
            if (t) {
                $('#changeProduct').popover({container: 'body', html: true, placement: 'bottom', content: function () {
                    var s = '';
                    $.ajax({
                        type: 'GET',
                        url: this.href,
                        dataType: 'html',
                        async: false,
                        error: function (e) {
                            s = e.toString();
                        },
                        success: function (data) {
                            s = data;
                        }
                    });
                    return s;
                }}).popover('show');
            } else {
                $('.edModal').modal({remote:true});
            }
        });*/


/*        $('#changeProduct').editable({
            validate: function (value) {
                if ($.trim(value) == '') return 'This field is required';
            }
        });*/

        $('#changeProduct').popover({container: 'body', html: true, placement: 'bottom', content: function () {
            var s = '';
            $.ajax({
                type: 'GET',
                url: 'pop_product_edit_number.html',
                dataType: 'html',
                async: false,
                error: function (e) {
                    s = e.toString();
                },
                success: function (data) {
                    s = data;
                }
            });
            return s.popOverFormat('#changeProduct');
        }});

        // 订单详情基本信息数据拉取
        $('.tab_ajax').click(function(){
            var $this = $(this),
                a_href = $this.attr('href'),
                url = $this.data('url');
            if($this.hasClass('first')){
                $.ajax({
                    url:url,
                    type:'Get',
                    dataType:'html',
                    success:function(msg){
                        $(a_href).html(msg);
                        $this.removeClass('first');
                    }
                })
            }
        });

        //历史订单按钮弹窗
        $('.order_history').click(function(){
            //start layer.open
            $.get("order_details_History.shtml", function(data){
                layer.open({
                    title:"历史订单",
                    type: 1,
                    area: ['1200px', '800px'],
                    fix: false, //不固定
                    content: data,
                    btn: ['关闭'],
                    yes: function(index, layero){
                        layer.close(index);
                    }
                });
            });
            //end layer.close
        });

        //锁定工单按钮
        $('#lock_btn').click(function(){
            $('.lock_area').removeClass('hide');
            $(this).addClass('hide');
        })

        //解锁工单按钮
        $('#deblock_btn').click(function(){
            $('.lock_area').addClass('hide');
            $('#lock_btn').removeClass('hide');
        });  
    },
    detailsLog: function (dModule) {
        $('.log-item').on('click', function (e) {
            var _target = $(e.target),
                isContain = _target.hasClass('log-details') ? true : _target.parents('.log-details').length > 0 ? true : false;
            if (!isContain) {
                $(this).find('.log-details').toggle();
            }
        }).first().find('.log-details').show();
    }
};
//zong
function dialogTotal(){
    dialogCenter();
    tableDialogClose();
}
// bootbox.dialog({})位置居中的函数
function dialogCenter(){
    var _w_height=$(window).height();
    var modal_height=$('.modal-content').height();
    var _top=parseInt((_w_height-modal_height)/2);
    $('.modal-dialog').css("marginTop",_top);
}
//当弹窗为选项卡的时候重新调整关闭按钮的位置函数
function tableDialogClose(){
    $('.close').css('top','40px');
    $('.close').css('z-index','10');
}

//下拉选项
function LoadSelect(obj){
    $(obj).selectpicker({
        iconBase: 'fa',
        tickIcon: 'fa-check',
        size: 5
    });
}
$(function () {
    App.init();
    G.ExcuteModule('#Order_List', YG_Order.orderList);
    G.ExcuteModule('#Order_Details', YG_Order.orderDtails);
    YG_Order.detailsLog();
})