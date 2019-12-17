/**
 * Created by lijunfang on 2014/12/12.
 */
(function (window) {
    var G = {
        ExcuteModule: function (id, excutor) {
            if ($(id).length > 0) {
                try {
                    excutor();
                } catch (e) {
                    //console.error('Hey,回调方法不能为空！');
                }
            }
        },
        /**
         * 判断是否存在匹配条件，是则执行call，否则执行fnElse
         * @_match {String} 匹配条件
         * @call {Function} 成功回调方法
         * @fnElse {Function} 失败回调方法
         * @constructor
         */
        ExistObjeRun: function (_match, call, fnElse) {
            if ($('[src$="{0}"]'.format(_match))[0]) {
                call();
            } else {
                fnElse();
            }
        },
        GetLayerContent: function (data, id, opts) {
            var _data = $.parseHTML(data, true),
                _cid = $('<input type="hidden" data-layer-rel value="{0}">'.format(id)).data('value', opts),
                lid = 'layui_layer_c' + id,
                qid = '#' + lid;
            $("[id^='layui_layer_c']").remove();
            $('<div id="{0}" style="display:none;"/>'.format(lid)).append(_data).append(_cid).appendTo('body');
            return $(qid);
        },
        /*绑定加减按钮, 样式请自行控制
         <span class="ASButton">
         <a data-rel="99893531009" data-type="sub" href="javascript:void(0);" class="shopping_btn">-</a>
         <input id="99893531009" type="text" value="0" maxlength="5" data-max="51" class="form-control shoppingNumbers">
         <a data-rel="99893531009" data-type="add" href="javascript:void(0);" class="shopping_btn">+</a>
         </span>
         */
        //args:{region:'.mycart_nav',callback:btnBuy}
        //region:范围
        //callback:回调方法
        bindButton: function (args) {
            //console.log($('a[data-type]','.mycart_nav'));
            var _region = (args ? args.region ? args.region + ' ' : '' : ''),
                strSelector = _region + 'a[data-type]',
                befn = function () {
                    clipboardData.setData('text', clipboardData.getData('text').replace(/[^\d]/g, ''));
                },
                rpfn = function () {
                    this.value = this.value.replace(/[^\d]/g, '');
                    this.ktype = 'change';
                },
            // 判断回调
                cbk = function (type, id, value, obj) {
                    if (args && args.callback) {
                        args.callback(type, id, value, obj);
                    }
                    obj.ovalue = obj.value;
                },
                _flag = {};
            $(strSelector).unbind();
            $(strSelector).click(function (e) {
                var $this = $(this),
                    _type = $this.attr('data-type'),
                    _rel = $this.attr('data-rel'),
                //解决同页面相同id冲突 update by lijunfang 20150107
                    $text = $('#' + _rel, _region),
                    _max = parseInt($text.attr('data-max')),
                    _value = parseInt($text.val());
                if (!_value) {
                    _value = 0;
                }

                if (_type === 'sub') {
                    if (_value - 1 > -1) {
                        $text.val(--_value);
                        $text[0].ktype = 'sub';
                        $text.blur();
                    }
                } else {
                    if (_value + 1 <= _max) {
                        $text.val(++_value);
                        $text[0].ktype = 'add';
                        $text.blur();
                    }
                }

            });
            var _inputSelector = _region + '.shoppingNumbers';
            $(_inputSelector).each(function () {
                var $text = $(this)[0];
                /*$text.onbeforepaste = befn;
                 $text.onpaste = function() {
                 this.ktype = 'change';
                 };
                 $text.onkeyup = rpfn;*/
                $(document).delegate('#' + $text.id, 'beforepaste', befn).delegate('#' + $text.id, 'paste', function () {
                    this.ktype = 'change';
                }).delegate('#' + $text.id, 'keyup', rpfn);

                $($text).blur(function () {
                    //console.log('change');
                    this.value = this.value.replace(/[^\d]/g, '');
                    if (!$.trim(this.value)) {
                        this.value = 0;
                    }
                    var tmax = parseInt($($text).attr('data-max')),
                        tvalue = parseInt(this.value);
                    if (tvalue > tmax) {
                        this.value = tmax;
                    }
                    cbk(this.ktype, this.id, this.value, $text);
                });
                $text.ovalue = $text.value;
            });
        },
        GetParam: function (key) {
            var params = {};
            $(location.search.substr(1).split('&')).each(function (i, item) {
                var pr = item.split('=');
                params[pr[0]] = pr[1];
            });
            return params[key.trim()];
        },
        GetSearch: function () {
            return location.search;
        },
        POPOVER: {},
        Download: function (form) {
            $('.only-dowload-link').remove();
            $('<a class="only-dowload-link" href="{0}?{1}" target="_blank"><span></span></a>'.format($(form).attr('action'), $(form).serialize())).appendTo('body').find('span').click();
        },
        Debug: function () {
            if (location.search.indexOf('debugmode') > -1) {
                $('<script src="assets/plugins/mock/mock-min.js"></script>').appendTo('body');
                $('<script src="static/js/debug-data.js"></script>').appendTo('body');
            }
        },
        /**
         * 表格的tbody收缩
         * add by lijunfang 20151224
         * update by guoran 20160104
         */
        TableExContract: function (args) {
            $('.table-ex').on("click", ".btn-tbody-contract", function () {
                var that = $(this);
                tbody = that.parents('thead').siblings('tbody');
                if (tbody.hasClass('hide')) {
                    tbody.removeClass('hide');
                    that.html('-');
                } else {
                    tbody.addClass('hide');
                    that.html('+');
                }
            });

            $('.table-ex-body-toggle').on("click", "thead", function () {
                $(this).siblings('tbody').toggle();
            });
        },

        /**
        * 查询条件收缩,部分收缩和全部展示
        * add by lijunfang 20160104
        **/
        searchPartUpDown:function(){
           //更多搜索按钮
            $('.btn-search-senior').click(function(){
                var that = $(this);
                if(!that.hasClass('.btn-search-common')){
                    that.parent().siblings('.search-senior-list').show();
                    that.addClass('.btn-search-common');
                    that.html("普通查询");  
                }else{
                    that.parent().siblings('.search-senior-list').hide();
                    that.removeClass('.btn-search-common');
                    that.html("高级查询");  
                }
            });
        }

    };

    window.G = G;
})(window);

//扩展String方法
//create by guoran 20150107
;
(function (s) {
    s.prototype.popOverFormat = function (_id) {
        ///<summary>
        ///格式化popOver
        ///</summary>
        ///<param name="_id" type="string">pop对象ID</param>
        return this + '<input type="hidden" data-popover-rel="' + _id + '" />';
    };
})(String);

/*扩展string，添加format方法
 add by guoran 20140811
 两种调用方法：
 eg1:
 var template1="我是{0}，今年{1}了";
 var result1=template1.format("loogn",22);
 eg2:
 var template2="我是{name}，今年{age}了";
 var result2=template2.format({name:"loogn",age:22});
 两个结果都是"我是loogn，今年22了"
 */
(function () {
    String.prototype.format = function (args) {
        var result = this;
        if (arguments.length > 0) {
            if (arguments.length == 1 && typeof(args) == "object") {
                for (var key in args) {
                    if (args[key] != undefined) {
                        var reg = new RegExp("({" + key + "})", "g");
                        result = result.replace(reg, args[key]);
                    }
                }
            } else {
                for (var i = 0; i < arguments.length; i++) {
                    if (arguments[i] != undefined) {
                        var reg = new RegExp("({)" + i + "(})", "g");
                        result = result.replace(reg, arguments[i]);
                    }
                }
            }
        }
        return result;
    };

    String.prototype.len = function () {
        return this.replace('[^\x00-\xff]/g', 'aa').length;
    };
})();

/*简单Nav切换效果*/
;
(function ($) {
    $.fn.NavBarBasic = function (callback) {
        var $this = $(this);
        $this.each(function () {
            var $that = $(this);
            $('.navbar-title a', $that).click(function (e) {
                e.preventDefault();
                var _a = $(this);
                _a.addClass('active').siblings().removeClass('active');
                $(_a.attr('href')).addClass('active').siblings().removeClass('active');
                if (callback) {
                    if (typeof callback == 'function') {
                        callback(_a);
                    } else {
                        console.error('亲，你可以不传，但如果你传的话，则必须是个方法呀！');
                    }
                }

            });
        });
        return $this;
    };

    /*默认Nav页切换效果*/
    $.fn.NavPage = function (callback) {
        $(this).on('click', 'a[data-toggle=navitem]', function (e) {
            e.preventDefault();
            var _a = $(this),
                _li = _a.parents('li');
            _li.addClass('active').siblings().removeClass('active');
            $(_a.attr('href')).addClass('active').siblings().removeClass('active');
            if (callback) {
                if (typeof callback == 'function') {
                    callback(_a);
                } else {
                    console.error('亲，你可以不传，但如果你传的话，则必须是个方法呀！');
                }
            }
        });
        return $(this);
    };
})(jQuery);
/*
 ;
 (function ($) {
 $.fn.NavPage = function (callback) {
 var $this = $(this);
 $this.each(function () {
 var $that = $(this);
 $('.navs li', $that).click(function (e) {
 e.preventDefault();
 var _li = $(this),
 _a = $('a',_li);
 _li.addClass('active').siblings().removeClass('active');
 $(_a.attr('href')).addClass('active').siblings().removeClass('active');
 if (callback) {
 if (typeof callback == 'function') {
 callback(_a);
 }
 else {
 console.error('亲，你可以不传，但如果你传的话，则必须是个方法呀！');
 }
 }

 });
 });
 };
 })(jQuery);*/
;
(function ($) {
    function calendarWidget(el, params) {
        var now = new Date();
        var thismonth = now.getMonth();
        var thisyear = now.getYear() + 1900;
        var opts = {
            month: thismonth,
            year: thisyear
        };
        $.extend(opts, params);
        var monthNames = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'];
        var dayNames = ['日', '一', '二', '三', '四', '五', '六'];
        month = i = parseInt(opts.month);
        year = parseInt(opts.year);
        var m = 0;
        var table = '';
        // next month
        if (month == 11) {
            var next_month = '<a href="?month=' + 1 + '&amp;year=' + (year + 1) + '" title="' + monthNames[0] + ' ' + (year + 1) + '">' + monthNames[0] + ' ' + (year + 1) + '</a>';
        } else {
            var next_month = '<a href="?month=' + (month + 2) + '&amp;year=' + (year) + '" title="' + monthNames[month + 1] + ' ' + (year) + '">' + monthNames[month + 1] + ' ' + (year) + '</a>';
        }
        // previous month
        if (month == 0) {
            var prev_month = '<a href="?month=' + 12 + '&amp;year=' + (year - 1) + '" title="' + monthNames[11] + ' ' + (year - 1) + '">' + monthNames[11] + ' ' + (year - 1) + '</a>';
        } else {
            var prev_month = '<a href="?month=' + (month) + '&amp;year=' + (year) + '" title="' + monthNames[month - 1] + ' ' + (year) + '">' + monthNames[month - 1] + ' ' + (year) + '</a>';
        }
        //        table += ('<h3 id="current-month">' + monthNames[month] + ' ' + year + '</h3>');
        // uncomment the following lines if you'd like to display calendar month based on 'month' and 'view' paramaters from the URL
        //        table += ('<div class="nav-prev">'+ prev_month +'</div>');
        //        table += ('<div class="nav-next">'+ next_month +'</div>');
        table += ('<table class="calendar-month " ' + 'id="calendar-month' + i + ' " cellspacing="0">');
        table += '<tr>';
        for (d = 0; d < 7; d++) {
            table += '<th class="weekday">' + dayNames[d] + '</th>';
        }
        table += '</tr>';
        var days = getDaysInMonth(month, year);
        var firstDayDate = new Date(year, month, 1);
        var firstDay = firstDayDate.getDay();
        var prev_days = getDaysInMonth(month, year);
        var firstDayDate = new Date(year, month, 1);
        var firstDay = firstDayDate.getDay();
        var prev_m = month == 0 ? 11 : month - 1;
        var prev_y = prev_m == 11 ? year - 1 : year;
        var prev_days = getDaysInMonth(prev_m, prev_y);
        firstDay = (firstDay == 0 && firstDayDate) ? 7 : firstDay;
        var i = 0;
        for (j = 0; j < 42; j++) {
            if ((j < firstDay)) {
                table += ('<td class="other-month"><span class="day">' + (prev_days - firstDay + j + 1) + '</span></td>');
            } else if ((j >= firstDay + getDaysInMonth(month, year))) {
                i = i + 1;
                table += ('<td class="other-month"><span class="day">' + i + '</span></td>');
            } else {
                table += ('<td class="current-month day' + (j - firstDay + 1) + '"><span class="day">' + (j - firstDay + 1) + '</span></td>');
            }
            if (j % 7 == 6) table += ('</tr>');
        }
        table += ('</table>');
        el.html(table);
    }

    function getDaysInMonth(month, year) {
        var daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ((month == 1) && (year % 4 == 0) && ((year % 100 != 0) || (year % 400 == 0))) {
            return 29;
        } else {
            return daysInMonth[month];
        }
    }

    // jQuery plugin initialisation
    $.fn.calendarWidget = function (params) {
        calendarWidget(this, params);
        return this;
    };
})(jQuery);

;
(function ($) {
    function LoadCalendar(_obj, args) {
        var $md = $(_obj),
            _boxid = 'calendarBox' + +new Date(),
            _boxidQ = '#' + _boxid;
        $md.attr('readonly', 'readonly');
        $md.wrap('<div class="calendarBox" id="' + _boxid + '"></div>');
        $md = $(_obj, _boxidQ);
        $(_boxidQ).append('<div class="calendar"></div>');
        $(".calendar", _boxidQ).calendarWidget(args);
        $(_boxidQ).on('mouseleave', function () {
            $('.calendar', _boxidQ).hide();
        });
        $(".calendar-month td[class!='other-month']", _boxidQ).on('click', function (e) {
            var $this = $(this),
                $md2 = $md,
                _d = $md2.data('value') ? $md2.data('value').split(',') : [],
                _text = $this.text();
            if ($this.hasClass('hover')) {
                $this.removeClass('hover');
                _d = $.grep(_d, function (item, i) {
                    return item != _text;
                });
            } else {
                $this.addClass('hover');
                _d.push(_text);
            }
            var _data = _d.join(',');
            $md2.data('value', _data).val(_data);
        });
        var _vl = $md.data('value'),
            daylist = _vl ? _vl.split(',') : [];
        $('.calendar-month .current-month', _boxidQ).each(function (i, item) {
            var $item = $(item);
            if ($.inArray($item.text(), daylist) > -1) {
                $item.addClass('hover');
            }
        });
        $md.val(_vl);
        $md.on('click', function (e) {
            if ($(_boxidQ)[0]) {
                $('.calendar', _boxidQ).show();
            }
        });
    }

    /*多选日历 create by guoran 20150211
     * month： 月份，从0出发
     * year: 年份*/
    $.fn.multiSelectCarlendar = function (args) {
        /*args:{
         month: 1,
         year: 2015
         }*/
        $.each(this, function (i, item) {
            LoadCalendar(item, args);
        });
    }
})(jQuery);

!function(a){function b(){var a=document.createElement("p"),b=!1;if(a.addEventListener)a.addEventListener("DOMAttrModified",function(){b=!0},!1);else{if(!a.attachEvent)return!1;a.attachEvent("onDOMAttrModified",function(){b=!0})}return a.setAttribute("id","target"),b}function c(b,c){if(b){var d=this.data("attr-old-value");if(c.attributeName.indexOf("style")>=0){d.style||(d.style={});var e=c.attributeName.split(".");c.attributeName=e[0],c.oldValue=d.style[e[1]],c.newValue=e[1]+":"+this.prop("style")[a.camelCase(e[1])],d.style[e[1]]=c.newValue}else c.oldValue=d[c.attributeName],c.newValue=this.attr(c.attributeName),d[c.attributeName]=c.newValue;this.data("attr-old-value",d)}}var d=window.MutationObserver||window.WebKitMutationObserver;a.fn.attrchange=function(e,f){if("object"==typeof e){var g={trackValues:!1,callback:a.noop};if("function"==typeof e?g.callback=e:a.extend(g,e),g.trackValues&&this.each(function(b,c){for(var e,d={},b=0,f=c.attributes,g=f.length;g>b;b++)e=f.item(b),d[e.nodeName]=e.value;a(this).data("attr-old-value",d)}),d){var h={subtree:!1,attributes:!0,attributeOldValue:g.trackValues},i=new d(function(b){b.forEach(function(b){var c=b.target;g.trackValues&&(b.newValue=a(c).attr(b.attributeName)),"connected"===a(c).data("attrchange-status")&&g.callback.call(c,b)})});return this.data("attrchange-method","Mutation Observer").data("attrchange-status","connected").data("attrchange-obs",i).each(function(){i.observe(this,h)})}return b()?this.data("attrchange-method","DOMAttrModified").data("attrchange-status","connected").on("DOMAttrModified",function(b){b.originalEvent&&(b=b.originalEvent),b.attributeName=b.attrName,b.oldValue=b.prevValue,"connected"===a(this).data("attrchange-status")&&g.callback.call(this,b)}):"onpropertychange"in document.body?this.data("attrchange-method","propertychange").data("attrchange-status","connected").on("propertychange",function(b){b.attributeName=window.event.propertyName,c.call(a(this),g.trackValues,b),"connected"===a(this).data("attrchange-status")&&g.callback.call(this,b)}):this}return"string"==typeof e&&a.fn.attrchange.hasOwnProperty("extensions")&&a.fn.attrchange.extensions.hasOwnProperty(e)?a.fn.attrchange.extensions[e].call(this,f):void 0}}(jQuery),$.fn.attrchange.extensions={disconnect:function(a){return"undefined"!=typeof a&&a.isPhysicalDisconnect?this.each(function(){var a=$(this).data("attrchange-method");"propertychange"==a||"DOMAttrModified"==a?$(this).off(a):"Mutation Observer"==a?$(this).data("attrchange-obs").disconnect():"polling"==a&&clearInterval($(this).data("attrchange-polling-timer"))}).removeData(["attrchange-method","attrchange-status"]):this.data("attrchange-status","disconnected")},remove:function(a){return $.fn.attrchange.extensions.disconnect.call(this,{isPhysicalDisconnect:!0})},getProperties:function(a){var b=$(this).data("attrchange-method"),c=$(this).data("attrchange-pollInterval");return{method:b,isPolling:"polling"==b,pollingInterval:"undefined"==typeof c?0:parseInt(c,10),status:"undefined"==typeof b?"removed":$(this).data("attrchange-status")}},reconnect:function(a){return this.data("attrchange-status","connected")},polling:function(a){return a.hasOwnProperty("isComputedStyle")&&"true"==a.isComputedStyle?this.each(function(b,c){if(!a.hasOwnProperty("properties")||"[object Array]"!==Object.prototype.toString.call(a.properties)||0==a.properties.length)return!1;for(var d={},b=0;b<a.properties.length;b++)d[a.properties[b]]=$(this).css(a.properties[b]);var c=this;$(this).data("attrchange-polling-timer",setInterval(function(){for(var f,b={},e=!1,g=0;g<a.properties.length;g++)f=$(c).css(a.properties[g]),d[a.properties[g]]!==f&&(e=!0,b[a.properties[g]]={oldValue:d[a.properties[g]],newValue:f},d[a.properties[g]]=f);e&&"connected"===$(c).data("attrchange-status")&&a.callback.call(c,b)},a.pollInterval?a.pollInterval:1e3)).data("attrchange-method","polling").data("attrchange-pollInterval",a.pollInterval).data("attrchange-status","connected")}):this.each(function(b,c){for(var e,d={},b=0,f=c.attributes,g=f.length;g>b;b++)e=f.item(b),d[e.nodeName]=e.nodeValue;$(c).data("attrchange-polling-timer",setInterval(function(){for(var f,b={},e=!1,g=0,h=c.attributes,i=h.length;i>g;g++)f=h.item(g),d.hasOwnProperty(f.nodeName)&&d[f.nodeName]!=f.nodeValue?(b[f.nodeName]={oldValue:d[f.nodeName],newValue:f.nodeValue},e=!0):d.hasOwnProperty(f.nodeName)||(b[f.nodeName]={oldValue:"",newValue:f.nodeValue},e=!0),d[f.nodeName]=f.nodeValue;e&&"connected"===$(c).data("attrchange-status")&&a.callback.call(c,b)},a.pollInterval?a.pollInterval:1e3)).data("attrchange-method","polling").data("attrchange-pollInterval",a.pollInterval).data("attrchange-status","connected")})}};! function(a) {
    function b() {
        var a = document.createElement("p"),
            b = !1;
        if (a.addEventListener) a.addEventListener("DOMAttrModified", function() {
            b = !0
        }, !1);
        else {
            if (!a.attachEvent) return !1;
            a.attachEvent("onDOMAttrModified", function() {
                b = !0
            })
        }
        return a.setAttribute("id", "target"), b
    }

    function c(b, c) {
        if (b) {
            var d = this.data("attr-old-value");
            if (c.attributeName.indexOf("style") >= 0) {
                d.style || (d.style = {});
                var e = c.attributeName.split(".");
                c.attributeName = e[0], c.oldValue = d.style[e[1]], c.newValue = e[1] + ":" + this.prop("style")[a.camelCase(e[1])], d.style[e[1]] = c.newValue
            } else c.oldValue = d[c.attributeName], c.newValue = this.attr(c.attributeName), d[c.attributeName] = c.newValue;
            this.data("attr-old-value", d)
        }
    }
    var d = window.MutationObserver || window.WebKitMutationObserver;
    a.fn.attrchange = function(e, f) {
        if ("object" == typeof e) {
            var g = {
                trackValues: !1,
                callback: a.noop
            };
            if ("function" == typeof e ? g.callback = e : a.extend(g, e), g.trackValues && this.each(function(b, c) {
                for (var e, d = {}, b = 0, f = c.attributes, g = f.length; g > b; b++) e = f.item(b), d[e.nodeName] = e.value;
                a(this).data("attr-old-value", d)
            }), d) {
                var h = {
                        subtree: !1,
                        attributes: !0,
                        attributeOldValue: g.trackValues
                    },
                    i = new d(function(b) {
                        b.forEach(function(b) {
                            var c = b.target;
                            g.trackValues && (b.newValue = a(c).attr(b.attributeName)), "connected" === a(c).data("attrchange-status") && g.callback.call(c, b)
                        })
                    });
                return this.data("attrchange-method", "Mutation Observer").data("attrchange-status", "connected").data("attrchange-obs", i).each(function() {
                    i.observe(this, h)
                })
            }
            return b() ? this.data("attrchange-method", "DOMAttrModified").data("attrchange-status", "connected").on("DOMAttrModified", function(b) {
                b.originalEvent && (b = b.originalEvent), b.attributeName = b.attrName, b.oldValue = b.prevValue, "connected" === a(this).data("attrchange-status") && g.callback.call(this, b)
            }) : "onpropertychange" in document.body ? this.data("attrchange-method", "propertychange").data("attrchange-status", "connected").on("propertychange", function(b) {
                b.attributeName = window.event.propertyName, c.call(a(this), g.trackValues, b), "connected" === a(this).data("attrchange-status") && g.callback.call(this, b)
            }) : this
        }
        return "string" == typeof e && a.fn.attrchange.hasOwnProperty("extensions") && a.fn.attrchange.extensions.hasOwnProperty(e) ? a.fn.attrchange.extensions[e].call(this, f) : void 0
    }
}(jQuery), $.fn.attrchange.extensions = {
    disconnect: function(a) {
        return "undefined" != typeof a && a.isPhysicalDisconnect ? this.each(function() {
            var a = $(this).data("attrchange-method");
            "propertychange" == a || "DOMAttrModified" == a ? $(this).off(a) : "Mutation Observer" == a ? $(this).data("attrchange-obs").disconnect() : "polling" == a && clearInterval($(this).data("attrchange-polling-timer"))
        }).removeData(["attrchange-method", "attrchange-status"]) : this.data("attrchange-status", "disconnected")
    },
    remove: function(a) {
        return $.fn.attrchange.extensions.disconnect.call(this, {
            isPhysicalDisconnect: !0
        })
    },
    getProperties: function(a) {
        var b = $(this).data("attrchange-method"),
            c = $(this).data("attrchange-pollInterval");
        return {
            method: b,
            isPolling: "polling" == b,
            pollingInterval: "undefined" == typeof c ? 0 : parseInt(c, 10),
            status: "undefined" == typeof b ? "removed" : $(this).data("attrchange-status")
        }
    },
    reconnect: function(a) {
        return this.data("attrchange-status", "connected")
    },
    polling: function(a) {
        return a.hasOwnProperty("isComputedStyle") && "true" == a.isComputedStyle ? this.each(function(b, c) {
            if (!a.hasOwnProperty("properties") || "[object Array]" !== Object.prototype.toString.call(a.properties) || 0 == a.properties.length) return !1;
            for (var d = {}, b = 0; b < a.properties.length; b++) d[a.properties[b]] = $(this).css(a.properties[b]);
            var c = this;
            $(this).data("attrchange-polling-timer", setInterval(function() {
                for (var f, b = {}, e = !1, g = 0; g < a.properties.length; g++) f = $(c).css(a.properties[g]), d[a.properties[g]] !== f && (e = !0, b[a.properties[g]] = {
                    oldValue: d[a.properties[g]],
                    newValue: f
                }, d[a.properties[g]] = f);
                e && "connected" === $(c).data("attrchange-status") && a.callback.call(c, b)
            }, a.pollInterval ? a.pollInterval : 1e3)).data("attrchange-method", "polling").data("attrchange-pollInterval", a.pollInterval).data("attrchange-status", "connected")
        }) : this.each(function(b, c) {
            for (var e, d = {}, b = 0, f = c.attributes, g = f.length; g > b; b++) e = f.item(b), d[e.nodeName] = e.nodeValue;
            $(c).data("attrchange-polling-timer", setInterval(function() {
                for (var f, b = {}, e = !1, g = 0, h = c.attributes, i = h.length; i > g; g++) f = h.item(g), d.hasOwnProperty(f.nodeName) && d[f.nodeName] != f.nodeValue ? (b[f.nodeName] = {
                    oldValue: d[f.nodeName],
                    newValue: f.nodeValue
                }, e = !0) : d.hasOwnProperty(f.nodeName) || (b[f.nodeName] = {
                    oldValue: "",
                    newValue: f.nodeValue
                }, e = !0), d[f.nodeName] = f.nodeValue;
                e && "connected" === $(c).data("attrchange-status") && a.callback.call(c, b)
            }, a.pollInterval ? a.pollInterval : 1e3)).data("attrchange-method", "polling").data("attrchange-pollInterval", a.pollInterval).data("attrchange-status", "connected")
        })
    }
};


/*
 * Translated default messages for bootstrap-select.
 * Locale: ZH (Chinese)
 * Region: CN (China)
 */
;
(function ($) {
    if (!$.fn.selectpicker) {
        return;
    }
    $.fn.selectpicker.defaults = {
        noneSelectedText: '请选择',
        noneResultsText: '没有匹配项',
        countSelectedText: '选中{1}中的{0}项',
        maxOptionsText: ['超出限制 (最多选择{n}项)', '组选择超出限制(最多选择{n}组)'],
        multipleSeparator: ', ',
        selectAllText: '全选',
        deselectAllText: '清空'
    };
})(jQuery);

(function ($) {
    $.fn.serializeObject = function (opts) {
        var fields = $(this).serializeArray(), results = {};
        $(fields).each(function (i, item) {
            results[item.name] = item.value;
        });
        return results;
    }
})(jQuery);

(function($){
    /*
     * ztree下拉框树形
     **/
    function ztreeDropDowm(target, options) {
        var $target = $(target),
            _id = + new Date(),
            _name = $target.attr('name') ? $target.attr('name') : 'DD_Text_'+ _id,
            $value = !$target.removeAttr('name') || ($('<input type="hidden" name="{0}">'.format(_name))).insertAfter($target) ,
            resroucesId = 'RS_Tree_'+ _id,
            treeListId = 'RS_Tree_List_'+ _id,
            strTemp = '<div id="{0}" class="role-tree-wrap" tabindex="9999"><ul id="{1}" class="ztree"></ul></div>'.format(resroucesId, treeListId),
            $resourceTree = $('#'+resroucesId).length > 0? $('#'+resroucesId): $(strTemp).appendTo('body');
        $target.attr('readonly', true);
        //点击文本框显示树下拉框
        $target.click(function (event) {
            var that = $(this),
                cityObj = that,
                cityOffset = that.offset();
            $resourceTree.css({
                left: cityOffset.left,
                top: cityOffset.top + cityObj.outerHeight()
            }).slideDown("fast");
            $resourceTree.data('click', false);
            event.stopPropagation(); //阻止事件冒泡行为
        }).on('blur', function () {
//            console.log('dk'+ +new Date())
            if($("#resourcesTreeWrap").data('click')){
                setTimeout(function () {
//                    console.log('fk'+ +new Date());
                    $resourceTree.hide();
                }, 200);
            }
        });

        //点击其他的地方隐藏树形拉下框
        $("body").bind("mousedown", function(event) {
            if (!(event.target.id == resroucesId || event.target.id == treeListId || $(event.target).parents('#'+resroucesId).length > 0)) {
                $resourceTree.slideUp("fast");
            }
        });

        ztreeFun($resourceTree, $target, $value, options);//树形函数执行
    }


    /*
     * ztree函数
     **/
    function ztreeFun($resourceTree, $text, $value, options) {
        var setting = {
            async: {
                enable: true,
                url: "static/js/test-tree-data.json",
                autoParam: ["id", "name=n", "level=lv"],
                otherParam: {
                    "bbyparam": "bbytree"
                },
                dataFilter: filter
            },
            check: {
                enable: false
            },
            data: {
                simpleData: {
                    enable: true
                }
            },
            callback: {
                onClick: onClick
            }
        };

        $.extend(setting.async, options || {});

        //ztree配置
        function filter(treeId, parentNode, childNodes) {
            if (!childNodes) return null;
            for (var i = 0, l = childNodes.length; i < l; i++) {
                childNodes[i].name = childNodes[i].name.replace(/\.n/g, '.');
            }
            return childNodes;
        }

        //点击节点显示元素
        function onClick(e, treeId, treeNode) {
            $text.val(treeNode.name);
            $value.val(treeNode.id);
            $resourceTree.data('click', true).hide();
        }

        $.fn.zTree.init($('ul', $resourceTree), setting);
    }

    /**
     * 树形下拉菜单
     * @param opts 异步对参数  {url: "static/js/test-tree-data.json", autoParam: ["id", "name=n", "level=lv"], otherParam: 'bbyParam'}
     * @constructor
     */
    $.fn.TreeDownLoad = function(opts){
        $(this).each(function(i, item){
            ztreeDropDowm(item, opts);
        });
    }

})(jQuery);

(function($) {
    /**
     * [EditText 行内编辑控件]
     *
     * @Author   guoran
     *
     * @DateTime 2015-11-10T17:19:50+0800
     *
     * @param    {Function}   callback 回调函数
     */
    function EditText(callback) {
        var $this = $(this),
            text = $this.siblings('.inline-edit-text'),
            _dtype = text.data('dtype'),
            _twidth = text.outerWidth(),
            _theight = text.outerHeight(),
            _motest = _theight % 20,
            _rheight = _theight + 20 - _motest,
            _lastHeight = _dtype === 'text' ? 20 : _rheight,
            _id = 'text_eidt_' + +new Date(),
            _size = text.attr('maxlength'),
            editor = $('<span class="yg-text-edit-span" id="{0}"><textarea class="edit-text form-control inline" MaxLength="{1}"></textarea> <a class="edit-save Qing" href="javascript:;" title="保存"><i class="fa fa-save Qing"></i></a></span>'.format(_id, _size)).appendTo('body'),
            editText = $('.edit-text', editor),
            btnsave = $('.edit-save', editor);
        editText.width(_twidth).height(_lastHeight).val(text.text()).end().css({
            left: text.offset().left - 3,
            top: function(e) {
                return text.offset().top - (_dtype === 'text' ? 1 : (editor.height() - text.height()) / 2);
            }
        }).find('.input-small').focus();
        $this.css('visibility', 'hidden');

        function removeControl() {
            $this.css('visibility', 'visible');
            editor.remove();
        }

        function textValidate(iptext) {
            var r = true;
            switch (_dtype) {
                case 'float':
                    // if (!(/^\d+\.\d{2}$/.test(iptext))) {
                    if (isNaN(iptext)) {
                        layer.msg('请输入正确的数据 例:( 100.00 )', {
                            icon: 2
                        });
                        r = false;
                    } else {
                        this.text = parseFloat(iptext).toFixed(2);
                    }
                    break;
                case 'int':
                    if (!(/^\d+$/.test(iptext))) {
                        layer.msg('请输入正确的数据 例:( 100 )', {
                            icon: 2
                        });
                        r = false;
                    } else {
                        this.text = parseInt(iptext);
                    }
                    break;
                default:
                    this.text = $.trim(iptext);
                    break;
            }
            return r;
        }

        btnsave.click(function(e) {
            var ipvalue = editText.val(),
                testobj = {
                    text: ''
                };
            if (!textValidate.call(testobj, ipvalue)) {
                return;
            }
            if (callback) {
                (function() {
                    var df = $.Deferred();
                    callback.call(this, e, {
                        success: df.resolve,
                        error: df.reject,
                        text: text,
                        btn: $this
                    });
                    return df;
                })().done(function() {
                    text.html(testobj.text);
                    layer.msg('修改成功', {
                        icon: 1
                    });
                    removeControl();
                }).fail(function() {
                    layer.msg('修改失败', {
                        icon: 2
                    });
                });
            } else {
                editText.focus();
            }
        });

        $(document).off('mousedown.text.edit').on('mousedown.text.edit', function(e) {
            if ($(e.target).is('.yg-text-edit-span') || $(e.target).parents('.yg-text-edit-span')[0]) {
                return;
            }
            // removeControl();
        }).off('keydown.text.edit', '.edit-text').on('keydown.text.edit', '.edit-text', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                return;
            }
        }).off('blur.text.edit').on('blur.text.edit', '.edit-text', function(e) {
            setTimeout(function() {
                removeControl();
            }, 100);
        });
    }
    /**
     * 行内文本编辑
     *
     * @example $('.inline-edit').inlineTextEdit(function(e, ops) {
                    $.get('order-log.shtml').done(function() {
                        ops.success();
                    }).fail(function() {
                        ops.error();
                    });
                });
     *
     * @Author   guoran
     *
     * @DateTime 2015-11-10T17:21:03+0800
     *
     * @param    {Function}               callback 回调函数
     *
     * @return   {object}                          jquery对象
     */
    $.fn.inlineTextEdit = function(callback) {
        $(document).off('click.inline.text.editbtn').on('click.inline.text.editbtn', this.selector, function(e) {
            e.preventDefault();
            EditText.call(this, callback);
        });
        return $(this);
    }

    /**
     * 编辑按钮的菜单
     *
     * @Author   guoran
     *
     * @DateTime 2015-11-17T16:41:55+0800
     *
     * @param    {object}                 args 要操作的对象，可以不输入
     *
     * @return   {object}                      object
     */
    $.fn.loadUpdateMenu = function(args) {
        var opts = $.extend({
            listClass: '.yg-update-menu-list'
        }, args);
        $(this).hover(function(e) {
            var $this = $(this),
                $list = $(opts.listClass, $this),
                _btnwidth = $this.width(),
                _lwidth = $list.width();
            $list.css('left', $this.offset().left + _btnwidth - _lwidth).css('top', $this.offset().top);
            $list.show();
        }, function(e) {
            $(opts.listClass, this).hide();
        });
        return $(this);
    }
})(jQuery);

//注入
$(function () {
    G.Debug();
    $(document).on('click', 'a[data-popover-btn="cancel"]', function () {
        var $this = $(this),
            _rel = $this.parents('.popover-content').find('input[data-popover-rel]').attr('data-popover-rel');
        $(_rel).popover('hide');

    });
    //$('.page-sidebar-menu .sub-menu a[href="' + location.pathname.substr(1) + '"]').parent().addClass('active').siblings().removeClass('active').parent().parent().addClass('active').click().siblings().removeClass('active');
    var page_obj1;

    function SetSiteBar(isChild) {
        page_obj1 = $('.page-sidebar-menu .sub-menu a[href="' + (isChild ? location.pathname : location.pathname.substr(1)) + '"]').parent().addClass('active');
    }

    //update by guoran 20150817
    G.ExistObjeRun('version=child', function () {
//        SetSiteBar(true);
    }, function () {
//        SetSiteBar(false);
//        page_obj1.siblings().removeClass('active');
//        page_obj1.parent().parent().addClass('active').click().siblings().removeClass('active');
    })
});
