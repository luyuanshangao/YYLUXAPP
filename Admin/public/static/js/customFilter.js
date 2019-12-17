//µ¯³ö²ã
function OpenShowSelectCountryPage(groupID, selectCounties, _title, SubmitCallBack) {
    //$.blockUI();
    $.ajax(
        {
            url: "SelectCountry.aspx?groupID=" + groupID,
            dataType: 'html',
            cache: false,
            success: function (data) {
                //$.unblockUI();

                $('#webengine_dialog_Model').remove();

                //创建model窗口
                $("body").append('<div id="webengine_dialog_Model" title="' + _title + '"></div>');

                $("#webengine_dialog_Model").dialog({
                    autoOpen: false,
                    bgiframe: true,
                    modal: true,
                    draggable:false,
                    buttons:
	                {
	                    Cancel: function () {
	                        $(this).dialog('close');
	                    },
	                    Submit: function () {
	                        $(this).dialog('close');
	                        var _groupid = groupID;
	                        var countriesStr = GetSelectCountryCode();
	                        SubmitCallBack(_groupid, countriesStr);
	                    }
	                }
                });

                $("#webengine_dialog_Model").html(data);
                $("#webengine_dialog_Model").dialog("open");
                var _height_model = $(".space_div").height() + 140;
                var _width_model = $(".space_div").width() + 30;

                $('#webengine_dialog_Model').dialog('option', 'width', _width_model);
                $('#webengine_dialog_Model').dialog('option', 'height', _height_model);
                $('#webengine_dialog_Model').dialog('option', 'position', 'center');

                InitSelectCountry(selectCounties);
            }
        });
}
function CheckInput() {
    var name = $("#shortCutShowName").val();
    if (name.length == 0) { alert("请输入显示名称。"); return false; }
    //检验是否有选择国家
    if ($(".c_list input:checkbox:checked").length == 0) {
        alert("请选择国家。");
        return false;
    }
    // var id_array=new Array();
    // $(".c_list input:checkbox:checked").each(function(){
    //       console.log($(this).val());
    //       id_array.push($(this).val());//向数组中添加元素
    // });
    // var idstr=id_array.join(',');//将数组元素连接起来以构建一个字符串
    var formData = new FormData($( "#saveForm" )[0]);//:nth-child(3)
    // console.log(formData);
    $.ajax({
        type:"POST",
        url:'/CustomFilter/CountryShortCutAct',
        dataType: 'json',
        data:formData,
        async: false,
        cache: false,
        contentType: false,
        processData: false,
        // data:JsonData,
        success:function(msg){
            if(msg.code == 200){console.log(msg.code);
                layer.msg(msg.result, {icon: 1});
                setTimeout(function(){
                    window.location.reload();
                },1500);
            }else{
                layer.msg(msg.result, {icon: 2});
            }
        }
    });
}
var customFilter = function() {
      function affiliate_order_statistics(){



      }
      return {
        affiliate_order_statistics:affiliate_order_statistics,
      }
}();