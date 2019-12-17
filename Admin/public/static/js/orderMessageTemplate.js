var System  = function() {
    function Init(){

    };
    /**
    *订单回复模板页面
     */
    function configList(){
        $('.add-template-btn').click(function(){
            Common.addAndEditor('/OrderMessageTemplate/add_template','/OrderMessageTemplate/add_template','','400pxspu','添加配置');
        });
    };

    $(function(){
        Init();
    });
    return {
        configList:configList,
    }
}();