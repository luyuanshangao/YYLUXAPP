//页面初始化
$(function () {

    //显示用户角色
    $("#btnShowUserRole").click(function () {
        //清空
        $("#msg_invaild").text("");
        $("#spCurUserNname").text("");
        $("#userID").val("");
        $(".oCB input:checkbox").each(function () {
            $(this)[0].checked = false;
        });
        //不为空
        var userName = $("#txtUserName").val();
        if (userName.length == 0) {
            $("#msg_invaild").text("请输入用户名");
            return;
        }
        //
        $.ajax({
            type: "POST",
            url: "AjaxHandle/RoleServer.ashx",
            async: false,
            dataType: "json",
            data: {
                FunctionName: "GetUserRoleByUserName",
                UserName: userName
            },
            success: function (data) {
                if (data.success) {
                    if (data.data) {
                        var userRoles = data.data.UserRoles;
                        var userID = data.data.UserID;
                        //绑定用户角色
                        $(".oCB input:checkbox").each(function () {
                            var $this = $(this);
                            var isExist = false;
                            for (var i = 0; i < userRoles.length; i++) {
                                if ($this.val() == userRoles[i].RoleID) {
                                    isExist = true;
                                    break;
                                }
                            }
                            //
                            $this[0].checked = isExist;
                        });
                        $("#userID").val(userID);
                        $("#spCurUserNname").text("当前用户：" + userName);
                    }
                    else {
                        $("#msg_invaild").text("输入用户无效");
                    }
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

    //保存
    $("#btnSave").click(function () {
        //验证
        if ($("#userID").val() == "") {
            alert("请确定一个当前用户！");
            return;
        }
        //
        var userID = $("#userID").val();
        var selectedRoles = GetSelectedRoles();
        if (selectedRoles.length == 0) {
            alert("请选择一个角色！");
            return;
        }
        $.ajax({
            type: "POST",
            url: "AjaxHandle/RoleServer.ashx",
            dataType: "json",
            data: {
                FunctionName: "EditRoleUser",
                UserID: userID,
                SelectedRoles: selectedRoles
            },
            success: function (data) {
                if (data.success) {
                    alert("保存成功！");
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

//获取选中的角色
function GetSelectedRoles() {
    var selecedRoles = "";
    //
    $(".oCB input:checkbox").each(function () {
        var isChecked = $(this).is(":checked");
        if (isChecked) {
            selecedRoles += $(this).val() + ",";
        }
    });
    //处理最后的逗号
    selecedRoles = selecedRoles.substr(0, selecedRoles.length - 1);
    return selecedRoles;
}