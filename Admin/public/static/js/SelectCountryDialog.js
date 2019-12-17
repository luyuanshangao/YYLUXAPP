$(function() {
    var availableTags = [{ value: "DK", label: "丹麦(DK)" }, { value: "IS", label: "冰岛(IS)" }, { value: "LI", label: "列支敦士登(LI)" }, { value: "HU", label: "匈牙利(HU)" }, { value: "LU", label: "卢森堡(LU)" }, { value: "IN", label: "印度(IN)" }, { value: "AT", label: "奥地利(AT)" }, { value: "GR", label: "希腊(GR)" }, { value: "DE", label: "德国(DE)" }, { value: "IT", label: "意大利(IT)" }, { value: "LV", label: "拉脱维亚(LV)" }, { value: "NO", label: "挪威(NO)" }, { value: "CZ", label: "捷克(CZ)" }, { value: "MC", label: "摩纳哥(MC)" }, { value: "SK", label: "斯洛伐克(SK)" }, { value: "SI", label: "斯洛文尼亚(SI)" }, { value: "SG", label: "新加坡(SG)" }, { value: "JP", label: "日本(JP)" }, { value: "BE", label: "比利时(BE)" }, { value: "FR", label: "法国(FR)" }, { value: "PL", label: "波兰(PL)" }, { value: "AU", label: "澳大利亚(AU)" }, { value: "IE", label: "爱尔兰(IE)" }, { value: "EE", label: "爱沙尼亚(EE)" }, { value: "SE", label: "瑞典(SE)" }, { value: "CH", label: "瑞士(CH)" }, { value: "LT", label: "立陶宛(LT)" }, { value: "RO", label: "罗马尼亚(RO)" }, { value: "US", label: "美国(US)" }, { value: "FI", label: "芬兰(FI)" }, { value: "GB", label: "英国(GB)" }, { value: "NL", label: "荷兰(NL)" }, { value: "PT", label: "葡萄牙(PT)" }, { value: "ES", label: "西班牙(ES)" }, { value: "KR", label: "韩国(KR)" }, { value: "HK", label: "香港(HK)" }];
    $("#txt_country").autocomplete({
        source: availableTags,
        //focus: function (event, ui) { alert($("#txt_country").val()); },
        select: function(event, ui) { CheckCountryBox(ui.item.value); }
    });
});

function CheckCountryBox(countryCode) {
    var that = $("#ck_country_" + countryCode);
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

function InitSelectCountry(countriesStr) {
    countriesStr = $.trim(countriesStr);
    var countryArray = countriesStr.split(',');

    for (var i = 0; i < countryArray.length; i++) {
        var ck = $("#ck_country_" + countryArray[i])
        ck.attr("checked", "checked");
        ck.next("label").addClass("em");
    }
}

function GetSelectCountryCode() {
    var countryArray = new Array();
    $(".c_list :checkbox:checked").each(function() {
        var that = $(this);
        countryArray.push(that.val());
    });
    return countryArray.join(",");
}

(function() {
    $(".allCB input:checkbox").change(function() {
        var that = $(this),
            oCBs = that.closest("tr").next().find("input:checkbox");

        var checked = that.is(":checked");

        oCBs.each(function() {
            if (checked) {
                $(this).prop("checked", true).next().addClass("em");
            } else {
                $(this).prop("checked", false).next().removeClass("em");
            }
        });
    });

    $(".oCB input:checkbox").change(function() {
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

    $("#selAllCB").change(function() {
        var that = $(this),
            oCBs = $(".c_list input:checkbox, .allCB input:checkbox");

        var checked = that.is(":checked");

        oCBs.each(function() {
            var isSubCB = !$(this).parent().hasClass("allCB");
            if (checked) {
                $(this).prop("checked", true);
                if (isSubCB) {
                    $(this).next().addClass("em");
                }
            } else {
                $(this).prop("checked", false);
                if (isSubCB) {
                    $(this).next().removeClass("em");
                }
            }
        });
    });

    $(".oCB input:checkbox, .allCB input:checkbox").change(function() {
        var that = $(this),

            allCB = $("#selAllCB");

        if ($(".oCB input:checkbox, .allCB input:checkbox").filter(":not(:checked)").length == 0) {
            allCB.prop("checked", true);
        } else {
            allCB.prop("checked", false);
        }
    });
    // 初始化已选择项的样式
    //            var checkedCountry = $(".c_list input:checkbox:checked");
    //            for (var i = 0; i < checkedCountry.length; i++) {
    //                checkedCountry.next().addClass("em");
    //            }
    var moreHandle = function() {
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

    $(".more").each(function() {
        var that = $(this),
            list = that.closest("tr").next().find(".c_list");
        height = parseInt(list.attr("data-height"), 10);
        that.click(moreHandle).parent().show();

    });
}());