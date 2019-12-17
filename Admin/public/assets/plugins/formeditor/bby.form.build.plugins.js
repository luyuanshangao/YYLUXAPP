(function (window, undefined) {
    BBYFE.LoadData = function (e_plugin, popover, pluginType) {
        //右弹form  初始化值
        for (var item in this.propertys) {
            var cx = $('#' + this.propertys[item], popover);
            switch (item) {
                case 'label':
                    if ($.inArray(pluginType, ['checkbox', 'radio']) > -1) {
                        cx.val($(e_plugin).parents('label.input-' + pluginType).attr("title"));
                    } else {
                        cx.val($(e_plugin).attr("title"));
                    }
                    break;
                case 'value':
                    this.TypeProperty(e_plugin, popover, pluginType, item, cx);
                    break;
                case 'size':
                    var pclass = $(e_plugin).attr('class').split(' ').map(function (item, i) {
                        return $.inArray(item, sizeClass) > -1 ? item : '';
                    }).join('');

                    cx.val(pclass);
                    break;
                case 'style':
                    if ($.inArray(pluginType, ['checkbox', 'radio']) > -1) {
                        cx.val($(e_plugin).parents('label.input-' + pluginType).attr(item));
                    } else {
                        cx.val($(e_plugin).attr(item));
                    }
                    break;
                default:
                    cx.val($(e_plugin).attr(item));
                    break;
            }
        }
    };
    BBYFE.TypeProperty = function (e_plugin, popover, pluginType, item, cx) {
        var val = '';
        switch (this.pluginsTypes[pluginType]) {
            case 'keyValueList':
                val = $.map($(e_plugin), function (e, i) {
                    return $(e).parents('label.input-' + pluginType).text().trim() + ':' + $(e).val();
                }).join("\r");
                $(popover).find("#value").text(val);
                break;
            case 'select':
                val = $('option', e_plugin).map(function (i, item) {
                    return $(item).text() + ':' + $(item).val();
                }).get().join('\r');
                $(popover).find("#value").text(val);
                break;
            default:
                //cx.prop('value', $(e_plugin).prop(item));
                cx.val($(e_plugin).val());
                break;

        }
    };

    var sizeClass = ['input-mini', 'input-xsmall', 'input-small', 'input-medium', 'input-large', 'input-xlarge'],
        cTypeSetting = function (acc, e_plugin, inputs, pluginType, attr_name, attr_val) {
            switch (pluginType) {
                case 'textarea':
                    $(e_plugin).prop(attr_name, attr_val);
                    break;
                case 'select':
                    var attr_values = attr_val.split("\n");
                    $(e_plugin).html($.map(attr_values, function (item, i) {
                        var kv = item.split(':');
                        return '<option value="' + (kv[1] ? kv[1].trim() : '') + '">' + kv[0].trim() + '</option>';
                    }).join('\r'));
                    break;
                default:
                    $(e_plugin).attr(attr_name, attr_val);
                    break;
            }
        },
        SettingComponet = function (acc, e_plugin, inputs, pluginType) {
            $.each(inputs, function (i, e) {
                var attr_name = $(e).attr("id"); //属性名称
                var attr_val = $(e).val();
                switch (attr_name) {
                    case 'orgvalue':
                        $(e_plugin).attr("value", attr_val);
                        break;
                    case 'orglabel':
                        $(e_plugin).attr("title", attr_val);
                        acc.find(".bbyfeplugins-orgname").text(attr_val);
                        break;
                    case 'name':
                        if ($.inArray(pluginType, ['checkbox', 'radio']) > -1) {
                            $('input', e_plugin).attr(attr_name, attr_val);
                        } else {
                            $(e_plugin).attr(attr_name, attr_val);
                        }
                        break;
                    case 'value':
                        cTypeSetting(acc, e_plugin, inputs, pluginType, attr_name, attr_val);
                        break;
                    case 'size':
                        $(sizeClass).each(function (i, item) {
                            $(e_plugin).removeClass(item);
                        });
                        $(e_plugin).addClass(attr_val);
                        break;
                    default:
                        $(e_plugin).attr(attr_name, attr_val);
                        break;
                }
            });
        };

    /* 文本框控件 text
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['text'] = function (acc, e_plugin) {
        var plugins = 'text',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin, popover, plugins);

        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {

            var inputs = $(popover).find("input,textarea,select");
            SettingComponet(acc, e_plugin, inputs, plugins);
            popover.remove();
            BBYFE.genSource();
        });
    }

    /* 自定义文本框控件 ctext
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['ctext'] = function (acc, e_plugin) {
        var plugins = 'ctext',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin);

        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {
            var inputs = $(popover).find("input,textarea,select");
            SettingComponet(acc, e_plugin, inputs, plugins);
            popover.remove();
            BBYFE.genSource();
        });
    }

    /* 多行文本框控件 textarea
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['textarea'] = function (acc, e_plugin) {
        var plugins = 'textarea',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin, popover, plugins);
        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {
            var inputs = $(popover).find("input,textarea,select");
            SettingComponet(acc, e_plugin, inputs, plugins);
            popover.remove();
            BBYFE.genSource();
        });
    }
    /* 下拉框控件 select
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['select'] = function (acc, e_plugin) {
        var plugins = 'select',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin, popover, plugins);
        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {
            var inputs = $(popover).find("input,textarea,select");
            SettingComponet(acc, e_plugin, inputs, plugins);
            popover.remove();
            BBYFE.genSource();
        });
    }

    /* 复选控件 checkbox
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['checkbox'] = function (acc, e_plugin) {
        var plugins = 'checkbox',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin, popover, plugins);
        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {

            var inputs = $(popover).find("input"),
                tvalue = $('#value', popover).val(),
                values = tvalue.trim() ? tvalue.split("\n") : '',
                html = [],
                compomentValueBox = $(acc).find(".bbyfeplugins-orgvalue");
            $(values).each(function (i, text) {
                var kv = text.split(':');
                html.push('<label class="input-checkbox inline">\n<input type="checkbox" class="bbyfeplugins" value="' + (kv[1] ? kv[1].trim() : '') + '" bbyfeplugins="checkbox" >' + kv[0] + '\n</label>');
            });
            compomentValueBox.html(html);
            var compoments = $('.input-checkbox', compomentValueBox);
            SettingComponet(acc, compoments, inputs, plugins);
            popover.remove();
            BBYFE.genSource();
            BBYFE.initForm();
        });
    }

    /* 复选控件 radio
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['radio'] = function (acc, e_plugin) {
        var plugins = 'radio',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin, popover, plugins);
        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {
            var inputs = $(popover).find("input"),
                tvalue = $('#value', popover).val(),
                values = tvalue.trim() ? tvalue.split("\n") : '',
                html = [],
                compomentValueBox = $(acc).find(".bbyfeplugins-orgvalue");
            $(values).each(function (i, text) {
                var kv = text.split(':');
                html.push('<label class="input-radio inline">\n<input type="radio" class="bbyfeplugins" value="' + (kv[1] ? kv[1].trim() : '') + '" bbyfeplugins="radio" >' + kv[0] + '\n</label>');
            });
            compomentValueBox.html(html);
            var compoments = $('.input-radio', compomentValueBox);
            SettingComponet(acc, compoments, inputs, plugins);
            popover.remove();
            BBYFE.genSource();
            BBYFE.initForm();
        });
    }

    /* 上传控件 uploadfile
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['uploadfile'] = function (acc, e_plugin) {
        var plugins = 'uploadfile',
            popover = $(".propertyBox");
        BBYFE.LoadData(e_plugin);
        //右弹form  取消控件
        $(popover).delegate(".btnProperytyCancel", "click", function (e) {
            popover.remove();
        });
        //右弹form  确定控件
        $(popover).delegate(".btnProperytyOk", "click", function (e) {
            var inputs = $(popover).find("input");
            SettingComponet(acc, e_plugin, inputs, plugins);
            popover.remove();
            BBYFE.genSource();

        });
    }
}(window, undefined));