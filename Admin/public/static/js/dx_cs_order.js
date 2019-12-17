var DX_order = function(){
     /**
     * 初始化函数
     */
    function Init(){
        DX_order_dialog();
        $('.d-element-bind').pingy(function(session) {
            session.extend({
                ygNumber: "12345"
            });
        });
    };

    /**
     * 订单查看页面js
     */
    function DX_order_dialog(){
        $(".btn-modity").click(function(event) {
            $.get('/admin.php/admin/ProductManagement/eidt.html', function (data) {
                layer.open({
                    title: "修改订单",
                    content: data,
                    type: 1,
                    area: ['680px', '500px'],
                    offset: '10px',
                    btn: ["保存", "取消"],
                    yes: function (index) {alert(1);
                        $('#addUserForm').submit();
                    },
                    cancel: function () {

                    }
                });
            });
        });
    };

    function DX_order_extend(){

    }
    $(function(){
        Init();
    });

    return {
       DXOrderExtend:DX_order_extend
    };
}();

