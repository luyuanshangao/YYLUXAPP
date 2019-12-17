//页面初始化
$(function () {
    //自动完成框
    $("#txt_country").autocomplete({
        source: countrys,
        select: function (event, ui) { CheckCountryBox(ui.item.value); }
    });

    //注册语言分组的全选
    $(".allCB input:checkbox").change(function () {
        var that = $(this),
               oCBs = that.closest("tr").next().find("input:checkbox");

        var checked = that.is(":checked");

        oCBs.each(function () {
            if (checked) {
                $(this).prop("checked", true);
                $(this).next().addClass("em");
            } else {
                $(this).prop("checked", false).next().removeClass("em");
            }
        });
    });

    //注册单选
    $(".oCB input:checkbox").change(function () {
        var that = $(this),
                tr = that.closest("tr"),
                allCB = tr.prev().find(".allCB input:checkbox");

        if (tr.find("input:checkbox:not(:checked)").length == 0) {
            allCB.prop("checked", true);
        } else {
            allCB.prop("checked", false);
        }

        var checked = that.is(":checked");

        if (checked) {
            that.next("label").addClass("em");
        } else {
            that.next("label").removeClass("em");
        }
    });

    //注册全选
    $("#selAllCB").change(function () {
        var that = $(this),
                oCBs = $(".c_list input:checkbox, .allCB input:checkbox");

        var checked = that.is(":checked");

        oCBs.each(function () {
            var isSubCB = !$(this).parent().hasClass("allCB");
            if (checked) {
                $(this).prop("checked", true);
                if (isSubCB) {
                    $(this).next().addClass("em");
                }
            }
            else {
                $(this).prop("checked", false);
                if (isSubCB) {
                    $(this).next().removeClass("em");
                }
            }
        });
    });

    //注册取消全选
    $(".oCB input:checkbox, .allCB input:checkbox").change(function () {
        var that = $(this),

                allCB = $("#selAllCB");

        if ($(".oCB input:checkbox, .allCB input:checkbox").filter(":not(:checked)").length == 0) {
            allCB.prop("checked", true);
        } else {
            allCB.prop("checked", false);
        }
    });

    //初始化已选择项的样式
    var checkedCountry = $(".c_list input:checkbox:checked");
    for (var i = 0; i < checkedCountry.length; i++) {
        checkedCountry.next().addClass("em");
    }

    //处理展开更多选项
    var moreHandle = function () {
        var that = $(this),
                list = that.closest("tr").next().find(".c_list"),
                height = parseInt(list.attr("data-height"), 10);

        if (that.text() == "展开") {
            that.text("收缩");
            list.height("auto");
        } else {
            that.text("展开");
            list.height(height);
        }
    };
    $(".more").each(function () {
        var that = $(this),
                list = that.closest("tr").next().find(".c_list"),
                height = parseInt(list.attr("data-height"), 10);

        if (height && list.height() > height) {
            that.click(moreHandle).parent().show();
            list.height(height);
        }
    });

    //注册保存
    $("#btnSave").click(function () {
        //校验
        var countries = GetSelectedCountries();
        if (countries == "") {
            alert("请您选择国家！");
            return;
        }
        //
        $.ajax({
            type: "POST",
            url: "AjaxHandle/RoleServer.ashx",
            dataType: "json",
            data: {
                FunctionName: "EditRoleCountry",
                RoleID: $("#roleID").val(),
                SelectedCountries: countries
            },
            success: function (data) {
                if (data.success) {
                    alert("保存成功");
                }
                else {
                    alert("系统错误，请联系管理员！\r\n错误信息：" + data.message);
                }
            },
            error: function (data) {
                //session超时的登录界面
                window.location.reload();
            }
        });
    });
});

//根据国家编号勾选
function CheckCountryBox(countryCode) {
    var that = $("span[title='" + countryCode + "'] input:checkbox");
    var more = that.closest("tr").prev().find(".more");
    if (more.text() == "展开") {
        more.text("收缩");
        var stretch = that.closest(".c_list");
        stretch.height("auto");
    }

    that.prop("checked", true);
    that.next("label").addClass("em");
    that.focus();
    that.next("label").fadeTo("slow", 0.1);
    that.next("label").fadeTo("slow", 1);
    that.next("label").fadeTo("slow", 0.1);
    that.next("label").fadeTo("slow", 1);
}

function GetSelectedCountries()
{
    var countries = "";
    $(".oCB input:checkbox").each(function () {
        var $this = $(this);
        var checked = $this.is(":checked");
        if (checked) {
            var code = $this.parent("span").attr("title");
            countries += code + ",";
        }
    });
    //处理最后的逗号
    if (countries != "") {
        countries = countries.substr(0, countries.length - 1);
    }
    return countries;
}