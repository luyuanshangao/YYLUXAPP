var Employee = function() {
     /**
     * 初始化函数
     */
    function Init(){

    };
    /**
    编辑角色全选管理
     */
    function editorPermissions(){

        $('.editor-rolse-box').on('click','.leve-input',function(){
            var that = $(this),
                _leve = that.parents('.leve1');
            if(that.prop('checked')){
                that.siblings().find('.leve-input').prop('checked','checked');
                that.parents().siblings('.leve-input').prop('checked','checked');
            }else{
               if(that.siblings('.leve-input').length === 0 && that.siblings().length !== 0){
                   that.siblings().find('.leve-input').prop('checked','');
               }else{
                   if(that.siblings('.leve-input:checked').length === 0){
                       that.parent().siblings('.leve-input').prop('checked','');
                   }
               };
                var _leveCheckedLength = that.parents().find('.leve-input:checked').length;
                    _leveLength = parseInt(that.parent().data('leve'));
               if( _leveCheckedLength === _leveLength-1  || _leveCheckedLength === _leveLength-2){
                   that.parents().find('.leve-input').prop('checked','');
               }

            }
        });
        Common.AllSelect($('#selectAll'),$('.leve-input')); //全选功能
        Common.ReverseAllSelect($('#reverseSelect'),$('.leve-input')); //反选功能

    }
    //控制会员状态
    // function user_status(){
    //       $('.user_status').on('click', function(e) {
    //                 layer.msg('你确定要修改状态么？', {
    //                   time: 0 //不自动关闭
    //                   ,btn: ['确定', '取消']
    //                   ,yes: function(index){
    //                       layer.close(index);
    //                       var that = $(this),
    //                           id  = e;
    //                       // var classId =  $(".brand"+e).attr("class-id");
    //                       $.ajax({
    //                           type:"POST",
    //                           url:"/EmployeeManagement/user_status",
    //                           data:{id:id},
    //                           dataType:"json",
    //                           cache:false,
    //                           success:function(msg){
    //                               if(msg.code == 200){
    //                                  layer.msg(msg.result, {icon: 1});
    //                                   setTimeout(function(){
    //                                     window.location.reload()
    //                                 },1500);
    //                               }else{
    //                                  layer.msg(msg.result, {icon: 2});
    //                               }
    //                           },
    //                               error:function(error){}
    //                       });


    //                  }
    //             });

    //           // console.log(2);
    //       })
    // }
    function roleManagement(){
         $('.delete_role').click(function(event) {
            var that = $(this),
                _id  = that.data('id'),
                _url = that.data('url'),
                _title = that.data('title'),
                dataParam = {id:_id};

              Common.Delete(_url,dataParam, _title);
         });
    }
    $(function(){
        Init();
    });
    return {
        editorPermissions:editorPermissions,
        roleManagement:roleManagement,
    }
}();