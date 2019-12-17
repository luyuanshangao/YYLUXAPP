(function () {

    var formList = [], BBYFE = window.BBYFE = window.BBYFE || {
        LoadFormUrl: '/assets/plugins/formeditor/testdata.json',
        DataUrl: function () {
            return (this.LoadFormUrl + G.GetSearch()).trim();
        },
        plugins: [],
        pluginsTypes: {
            text: 'text',
            ctext: 'text',
            textarea: 'textarea',
            select: 'select',
            checkbox: 'keyValueList',
            radio: 'keyValueList',
            uploadfile: 'text'
        },
        propertys: {
            label: 'orglabel',
            name: 'name',
            value: 'value',
            style: 'style',
            size: 'size',
            placeholder: 'placeholder'
        },
        propertyEditorConfigs: {
            text: ['label', 'name', 'value', 'size', 'placeholder', 'style', 'hr', 'btnOk', 'space', 'btnCancel'],
            textarea: ['label', 'name', 'pValue', 'size', 'style', 'hr', 'btnOk', 'space', 'btnCancel'],
            ctext: ['label', 'name', 'value', 'size', 'placeholder', 'style', 'hr', 'btnOk', 'space', 'btnCancel'],
            checkbox: ['label', 'name', 'multiValue', 'style', 'hr', 'btnOk', 'space', 'btnCancel'],
            radio: ['label', 'name', 'multiValue', 'style', 'hr', 'btnOk', 'space', 'btnCancel'],
            select: ['label', 'name', 'multiValue', 'size', 'style', 'hr', 'btnOk', 'space', 'btnCancel'],
            fileuploader: ['label', 'name', 'value', 'style', 'hr', 'btnOk', 'space', 'btnCancel']
        },
        editorTemplates: {
            label: {
                type: 'text',
                property: 'label',
                template: '<label>标题</label> <input id="orglabel" class="form-control inline" type="text" placeholder="必填项">'
            },
            name: {
                type: 'text',
                property: 'name',
                template: '<label>字段名</label> <input id="name" class="form-control" type="text" placeholder="必填项">'
            },
            multiValue: {
                type: 'keyValueList',
                property: 'value',
                template: '<label>列表值(text:value)</label><textarea id="value" class="form-control" style="min-height: 200px"></textarea><p class="help-block">文字和内容用：(半角)分隔 <br>例：音乐:1</p>'
            },
            value: {
                type: 'text',
                property: 'value',
                template: '<label>值</label> <input id="value" class="form-control" type="text" placeholder="默认值">'
            },
            pValue: {
                type: 'ptext',
                property: 'value',
                template: '<label>值</label><textarea id="value" class="form-control" style="min-height: 100px" placeholder="内容"></textarea>'
            },
            size: {
                type: 'select',
                property: 'class',
                template: '<label>大小</label><select id="size" title="大小" class="form-control"><option value="">默认</option><option value="input-mini">迷你</option><option value="input-xsmall">超小</option><option value="input-small">小</option><option value="input-medium">中</option><option value="input-large">大</option><option value="input-xlarge">超大</option></select>'
            },
            placeholder: {
                type: 'text',
                property: 'placeholder',
                template: '<label>placeHolder</label><input id="placeholder" class="form-control" placeholder="placeholder">'
            },
            style: {
                type: 'text',
                property: 'style',
                template: '<label>样式</label> <input id="style"class="form-control" type="text"  placeholder="样式">'
            },
            hr: {
                type: 'hr',
                property: 'hr',
                template: '<hr>'
            },
            btnOk: {
                type: 'button',
                property: 'btn-info',
                template: '<button class="btn btn-qing btnProperytyOk" type="button">确定</button>'
            },
            space: {
                type: 'space',
                property: 'space',
                template: '&nbsp;&nbsp;\t\t'
            },
            btnCancel: {
                type: 'button',
                property: 'btn-danger',
                template: '<button class="btn btn-white btnProperytyCancel" type="button">取消</button>'
            }
        },
        getFormList: function () {
            return formList;
        },
        getSourceCode: function () {
            return $('#source').val().trim();
        },
        genSource: function () {
            var $temptxt = $("<div>").html($("#build").html());
            $($temptxt).find(".component").attr({
                "title": null,
                "data-original-title": null,
                "data-type": null,
                "data-content": null,
                "rel": null,
                "trigger": null,
                "style": null
            });
            $($temptxt).find(".valtype").attr("data-valtype", null).removeClass("valtype");
            $($temptxt).find(".component").removeClass("component");
            $($temptxt).find("form").attr({
                "id": null,
                "style": null
            });
            $temptxt.find('[type=checkbox],[type=radio]').each(function (i, item) {
                $(item).unwrap().unwrap()
            });
            $("#source").val($temptxt.html().replace(/\n\ \ \ \ \ \ \ \ \ \ \ \ /g, "\n"));
        },
        genformList: function () {
            formList = [];
            $('.component', '#build').each(function (i, item) {
                var cpn = $(item), cm = $(cpn).find('.bbyfeplugins'),
                    clabel = cpn.find('.v-title').text().trim();
                formList.push({field: cm.attr('name'), type: cm.attr('bbyfeplugins').toUpperCase(), text: clabel});
            });
        },
        initForm: function (region) {
            if (!jQuery().uniform) {
                return;
            }
            var test = $("input[type=checkbox]:not(.toggle, .make-switch), input[type=radio]:not(.toggle, .star, .make-switch)", (region ? region : ''));
            if (test.size() > 0) {
                test.each(function () {
                    if ($(this).parents(".checker").size() == 0 && $(this).parents(".radio").size() == 0) {
                        $(this).show();
                        $(this).uniform();
                    }
                });
            }
        }
    };

    $.fn.RefreshFormEditor = function () {
        $('#target .c-h-dl-validator').addClass('component');
        return $(this);
    };
    /* 表单名称控件 form_name
     acc  是 class="component" 的DIV
     e_plugin 是 class="bbyfeplugins" 的控件
     */
    BBYFE.plugins['form_name'] = function (acc, e_plugin) {
        var plugins = 'form_name',
            popover = $(".popover");
        //右弹form  初始化值
        $(popover).find("#orgvalue").val($(e_plugin).val());
        //右弹form  取消控件
        $(popover).delegate(".btn-danger", "click", function (e) {
            e.preventDefault();
            acc.popover("hide");
        });
        //右弹form  确定控件
        $(popover).delegate(".btn-info", "click", function (e) {
            e.preventDefault(); //阻止元素发生默认的行为(例如,当点击提交按钮时阻止对表单的提交
            var inputs = $(popover).find("input");
            $.each(inputs, function (i, e) {
                var attr_name = $(e).attr("id"); //属性名称
                var attr_val = $("#" + attr_name).val();
                if (attr_name == 'orgvalue') {
                    $(e_plugin).attr("value", attr_val);
                    acc.find(".bbyfeplugins-orgvalue").text(attr_val);
                }
                acc.popover("hide");
                BBYFE.genSource();
            });
        });

    }
})();
$(function () {
    $("#navtab").delegate("#sourcetab", "click", function (e) {
        BBYFE.genSource();
    });
    $("form").delegate(".component", "mousedown", function (md) {
        md.preventDefault();
        $(".propertyBox").remove();
        var tops = [];
        var mouseX = md.pageX;
        var mouseY = md.pageY;
        var $temp;
        var timeout;
        var $this = $(this);
        var delays = {
            main: 0,
            form: 120
        }
        var type;

        if ($this.parent().parent().parent().parent().attr("id") === "components") {
            type = "main";
        } else {
            type = "form";
        }

        var delayed = setTimeout(function () {
            if (type === "main") {
                $temp = $("<form class='form-horizontal f12_i' id='temp'></form>").append($this.clone());
            } else {
                if ($this.attr("id") !== "legend") {
                    $temp = $("<form class='form-horizontal f12_i' id='temp'></form>").append($this);
                }
            }

            $("body").append($temp);

            $temp.css({
                "position": "absolute",
                "top": mouseY - ($temp.height() / 2) + "px",
                "left": mouseX - ($temp.width() / 2) + "px",
                "opacity": "0.9"
            }).show();

            var cpm = $temp.find('.bbyfeplugins');
            cpm.attr('name', 'feild_' + cpm.attr('bbyfeplugins') + '_' + +new Date());

            var half_box_height = ($temp.height() / 2);
            var half_box_width = ($temp.width() / 2);
            var $target = $("#target");
            var tar_pos = $target.offset();
            var $target_component = $("#target .component");

            $(document).delegate("body", "mousemove", function (mm) {

                var mm_mouseX = mm.pageX;
                var mm_mouseY = mm.pageY;

                $temp.css({
                    "top": mm_mouseY - half_box_height + "px",
                    "left": mm_mouseX - half_box_width + "px"
                });

                console.log('>>>my type:' + type);
//                console.log('mm_mouseX:' + mm_mouseX + ' mm_mouseY:' + mm_mouseY + ' l:' + tar_pos.left + ' t:' + tar_pos.top + ' w:' + $target.width() + ' h:' + $target.height() + ' tw:' + $temp.width() + ' th:' + $temp.height() + 'x>left:' + (mm_mouseX > tar_pos.left) + ' x<left+width+twidth/2:' + (mm_mouseX < tar_pos.left + $target.width() + $temp.width() / 2) + ' y>top:' + (mm_mouseY > tar_pos.top) + ' y<top+height+theight/2:' + (mm_mouseY < tar_pos.top + $target.height() + $temp.height() / 2));
                if (mm_mouseX > tar_pos.left &&
                    mm_mouseX < tar_pos.left + $target.width() + $temp.width() / 2 &&
                    mm_mouseY > tar_pos.top &&
                    mm_mouseY < tar_pos.top + $target.height() + $temp.height() / 2
                    ) {
                    $("#target").css("background-color", "#fafdff");
                    $target_component.css({
                        "border-top": "1px solid white",
                        "border-bottom": "none"
                    });
                    tops = $.grep($target_component, function (e) {
                        return ($(e).offset().top - mm_mouseY + half_box_height > 0 && $(e).attr("id") !== "legend");
                    });
                    if (tops.length > 0) {
                        $(tops[0]).css("border-top", "1px solid #22aaff");
                    } else {
                        if ($target_component.length > 0) {
                            $($target_component[$target_component.length - 1]).css("border-bottom", "1px solid #22aaff");
                        }
                    }
                } else {
                    $("#target").css("background-color", "#fff");
                    $target_component.css({
                        "border-top": "1px solid white",
                        "border-bottom": "none"
                    });
                    $target.css("background-color", "#fff");
                }
            });

            $("body").delegate("#temp", "mouseup", function (mu) {
                mu.preventDefault();
                var mu_mouseX = mu.pageX;
                var mu_mouseY = mu.pageY;
                var tar_pos = $target.offset();

                $("#target .component").css({
                    "border-top": "1px solid white",
                    "border-bottom": "none"
                });

                // acting only if mouse is in right place
                if (mu_mouseX + half_box_width > tar_pos.left &&
                    mu_mouseX - half_box_width < tar_pos.left + $target.width() &&
                    mu_mouseY + half_box_height > tar_pos.top &&
                    mu_mouseY - half_box_height < tar_pos.top + $target.height()
                    ) {
                    $temp.attr("style", null);
                    // where to add
                    if (tops.length > 0) {
                        $($temp.html()).insertBefore(tops[0]);
                    } else {
                        $("#target fieldset").append($temp.append("\n").html());
                    }
                } else {
                    // no add
                    $("#target .component").css({
                        "border-top": "1px solid white",
                        "border-bottom": "none"
                    });
                    tops = [];
                }

                //clean up & add popover
                $target.css("background-color", "#fff");
                $(document).undelegate("body", "mousemove");
                $("body").undelegate("#temp", "mouseup");
                $temp.remove();
                BBYFE.genSource();
                BBYFE.genformList();
            });
        }, delays[type]);

        $(document).mouseup(function () {
            clearInterval(delayed);
            return false;
        });
        $(this).mouseout(function () {
            clearInterval(delayed);
            return false;
        });
    });

    function getSettingBox(pluginType) {
        var template = ['<form class="form"><div class="controls">'];
        $(BBYFE.propertyEditorConfigs[pluginType]).each(function (i, item) {
            var tpl = BBYFE.editorTemplates[item];
            if (tpl) {
                template.push(tpl.template);
            }
        });
        template.push('</div></form>');
        return template.join('');
    }

    //popover on click event
    $("#target").delegate(".component", "click", function (e) {
        e.preventDefault();
        $(".rightPanel").append('<div class="propertyBox"></div>');
        var acc = $(this),
            e_plugin = acc.find(".bbyfeplugins"),
            plugins = $(e_plugin).attr("bbyfeplugins"); //bbyfeplugins="text"
        $('.propertyBox').html(getSettingBox(plugins));
        //exec plugins
        if (typeof(BBYFE.plugins[plugins]) == 'function') {
            try {
                BBYFE.plugins[plugins](acc, e_plugin);
            } catch (e) {
                throw new Error(e)
                alert('控件异常，请到联系bby 反馈或寻求帮助！');
            }
        } else {
            alert("控件有误或不存在，请与我们联系！");
        }

    });

    $('.input-checkbox, .input-radio').disableSelection();
});