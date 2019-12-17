var PromotionManagement = function () {
    /**
    * 初始化函数
    */
    function Init() {

    };
    /**
    促销管理
     */
    function promotionList() {
        Common.leadingIn('#path', '#upfile');//图片上传函数
        //上传文件表单提交
        $('.js-btn-upload-submit').click(function(){
            var that = $(this);
            if(that.hasClass('disabled')){
                return false;
            }
            $('#uploadForm').submit();
            that.addClass('disabled');
        });

        //确认按钮
        $('.js-btn-upload-confirm').click(function(){
            var that = $(this);
            if (that.hasClass('disabled')) {
                return false;
            }
            $('#uploadFormConfirm').submit();
            that.addClass('disabled');
        })

        //一键发布
        $('.js-btn-release').click(function () {
            var that = $(this),
                _id = that.data('id');
            if (that.hasClass('gray')) {
                return false;
            }
            that.addClass('gray').removeClass('Qing');
            $.ajax({
                type: "POST",
                dataType: 'json',
                data: { id: _id },
                url: '/PromotionManagement/subProduct',
                success: function (data) {
                    that.addClass('Qing').removeClass('gray');
                    layer.msg(data.msg);
                }
            })
        });

        //一键审核
        $('.js-btn-examine').click(function(){
            var that = $(this),
                _id = that.data('id');
            if (that.hasClass('gray')){
                return false;
            }
            that.addClass('gray').removeClass('Qing');
            $.ajax({
                type: "POST",
                dataType: 'json',
                data:{ id: _id},
                url:'/PromotionManagement/checkAct',
                success:function(data){
                    that.addClass('Qing').removeClass('gray');
                    layer.msg(data.msg);
                }
            })
        });
        
    }
    $(function () {
        Init();
    });
    return {
        promotionList: promotionList
    }
}();