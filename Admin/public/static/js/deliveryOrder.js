var Delivery = function() {
    /**
     * 初始化函数
     */
    function Init(){

    };

    /*
    *海外订单导出
     */
    function deliveryOrder() {
        $('.query-btn').on('click',function(){
            $("#is_export").val(0);
            $("#navbar").submit();
        });
        $('.export-btn').on('click',function(){
            $("#is_export").val(1);
            $("#navbar").submit();
        });
        $('.export-btn-gs').on('click',function(){
            $("#is_export").val(2);
            $("#navbar").submit();
        });
    };
    $(function(){
        Init();
    });
    return {
        deliveryOrder:deliveryOrder,
    }
}();