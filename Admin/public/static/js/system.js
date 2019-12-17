var System  = function() {
    function Init(){

    };
    /**
    *商城配置页面
     */
    function mallSet(){
        $("#Code").change(function(){
            var Code = $(this).val();
            $.ajax({
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url: '/SystemManage/exhibition',
                data: { "Code": Code},
                success: function (result) {
                    if (result.code == 200) {
                    $('#HotWords').children('dl').remove();
                    $("#HotWords").append(result.result);
                    }else{
                        layer.msg(result.result, {icon: 2});
                    };
                },
                error : function() {
                    layer.msg('异常！');
                }
            });
        });
        $('.btn_send').click(function(event) {
            $.ajax({
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url:'/SystemManage/advertisement',//url"/PaymentSetting/eidt_config"
                data: $('#form_send_address').serialize(),
                success: function (result) {
                    if (result.code == 200) {
                        layer.msg(result.result, {icon: 1});
                    }else{
                        layer.msg(result.result, {icon: 2});
                    };
                },
                error : function() {
                    layer.msg("异常！");
                }
            });
        });
        //添加热搜
       $('#HotWords').on('click','.add-hotwords-btn',function(){
            var that = $(this),html = '',
                _id = that.data('id');
                _id++;
            html += '<dl class="c-h-dl-validator form-group clearfix dl-HotWords">';
            html += '<dd class="v-title w100"><label></label></dd>';
            html += '<dd><div class="input-icon right"><i class="fa"></i>';
            html += '<input value="" name="HotWords[]" class="form-control input-medium inline w200 fl mr5" type="text"></div></dd>';
            html += '<dd>';
            html += '<a class="fa fa-plus add-hotwords-btn hotwords-btn add-HotWords'+_id+'" data-id="'+_id+'" href="javascript:void(0);"></a>';
            html += '<a class="fa fa-minus hotwords-btn delete-hotwords-btn f20 delect-t'+_id+'" data-id="'+_id+'" href="javascript:;"></a>';
            html += '</dd><dt></dt></dl>';
            that.parents('.dl-HotWords').after(html);
            that.remove();
        });
        $('#HotWords').on('click','.delete-hotwords-btn',function(){
            var that = $(this),
                _id = that.data('id'),
                _dlHotWords = that.parents('.dl-HotWords'),
                _deleteBtn = _dlHotWords.prev().find('.delete-hotwords-btn');
           if(_dlHotWords.next('.dl-HotWords').length === 0){
               if(_deleteBtn.length !== 0){
                    _deleteBtn.before('<a class="fa fa-plus hotwords-btn add-hotwords-btn" data-id="'+_id--+'" href="javascript:void(0);"></a>');
               }else{
                    _dlHotWords.prev().append('<a class="fa fa-plus hotwords-btn add-hotwords-btn" data-id="'+_id--+'" href="javascript:void(0);"></a>');
               }
           };
           _dlHotWords.remove();
        });
        $('.delete-lang').click(function(event) {
               layer.msg('你确定要删除么？', {
                  time: 0 //不自动关闭
                  ,btn: ['确定', '取消']
                  ,yes: function(index){
                         layer.close(index);
                         var lang_id = $('#Code').val();
                         $.ajax({
                            type:"POST",
                            url:'/SystemManage/lang_delecte',
                            data:{lang_id:lang_id},
                            dataType:"json",
                            cache:false,
                            success:function(msg){
                              if(msg.code == 200){
                                 layer.msg(msg.result, {icon: 1});
                                 setTimeout(function(){
                                     window.location.reload()
                                 },1500);
                              }else{
                                 layer.msg(msg.result, {icon: 2});
                              }
                            },
                            error:function(error){}
                         });
               }
            });



            //    $.ajax({
            //     type: "POST",//方法类型
            //     dataType: "json",//预期服务器返回的数据类型
            //     url:'/SystemManage/lang_delecte',//url"/PaymentSetting/eidt_config"
            //     data: {'lang_id':lang_id},
            //     success: function (result) {
            //         if (result.code == 200) {
            //             layer.msg(result.result, {icon: 1});
            //         }else{
            //             layer.msg(result.result, {icon: 2});
            //         };
            //     },
            //     error : function() {
            //         layer.msg("异常！");
            //     }
            // });
               // console.log(lang_id);
        });

    };
    /**
    *支付设置页面
     */
    function paymentSet(){
        //添加新项
        $('.behind').on('click','.add-payment-btn',function(){
            var that = $(this),
                _html = '';
                _index = that.data('index');
            _index++;
            _html +='<div class="mb10 input-icon right">';
            _html +='<select name="channel['+_index+'][channel]" id="first_level" class="form-control input-small inline  ">';
            _html +='<option value="">请选择</option>';
            _html += $('#paymentHtml').html();
            _html += '</select>';
            _html += '<input value="" id="input-color" name="channel['+_index+'][restriction]" class="ml5 input-medium" type="text">';
            _html += '<a class="btn btn-qing add-payment-btn ml10" data-index="'+_index+'" href="javascript:;">添加新项</a>';
            _html += '<a class="btn btn-qing delete-payment-btn ml10" data-index="'+_index+'" href="javascript:;">删除</a>';
            _html += '</div>';
            that.parents(".behind").append(_html);
            that.remove();
        });
         $('.behind').on('click','.delete-payment-btn',function(){
              var that = $(this),
                _index = that.data('index'),
                _inputIcon = that.parents('.input-icon'),
                _deleteBtn = _inputIcon.prev().find('.delete-payment-btn');
                _index--;
            if(_inputIcon.next('.input-icon').length === 0){
                if(_deleteBtn.length !== 0){
                   _deleteBtn.before('<a class="btn btn-qing add-payment-btn ml10" data-index="'+_index+'" href="javascript:;">添加新项</a>');
                }else{
                    _inputIcon.prev().find('span.tips').before('<a class="btn btn-qing add-payment-btn ml10" data-index="'+_index+'" href="javascript:;">添加新项</a>');
                }
            };
            _inputIcon.remove();
         });
         $('.payment-submit').click(function(){
             $.ajax({
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url: '/PaymentSetting/add_config',//url"/PaymentSetting/eidt_config"
                data: $('#form_send_address').serialize(),
                success: function (result) {
                    //console.log(result);//打印服务端返回的数据(调试用)
                    if (result.code == 200) {
                        layer.msg(result.result, {icon: 1});
                        setTimeout(function(){
                            window.location="/PaymentSetting/index";
                        },2000);
                    }else{
                        layer.msg(result.result, {icon: 2});
                    }
                    ;
                },
                error : function() {
                   layer.msg('异常！');
                }
            });
         });
    };
    /**
    * 国家区域管理页面
     */
    function ragionManage(){
        //导入按钮
        $('.import-btn').click(function(){
            Common.addAndEditor('/SystemManage/importData','/SystemManage/importDataPost','','350px','数据导入');
        });
        //查询按钮
        $('.quick-btn').click(function(){
            $("#navbar").submit();
        });
    };
    /**
    *分类设置页面
     */
    function classSett(params) {
        //添加新项
        $('.behind').on('click','.add-new-item-btn',function(){
            var that = $(this),_html = '',
                _index = that.data('index');
                _index++;
                _html += '<dl class="c-h-dl-validator form-group clearfix">';
                _html += '<dd  class="v-title w100"><label><em>*</em>分类：</label></dd>';
                _html += '<dd><div class="input-icon right">';
                _html += '<select name="where['+_index+'][classId]" id="first" class="form-control input-small inline input-class-name"><option value="">请选择</option>';
                _html += $('#classHtml').html();
                _html += '</select>';
                _html += '</div></dd>';
                _html += '<dd class="v-title"><label><em>*</em>分类名称：</label></dd>';
                _html += '<dd><div class="input-icon right"><i class="fa"></i>';
                _html += '<input value="" id="input-color"  name="where['+_index+'][className]" class="form-control input-medium w130 fl" type="text">';
                _html += '<input class="class-name" type="hidden" name="where['+_index+'][class_name]" value=""></div></dd>';
                _html += '<dd><a class="btn btn-qing add-new-item-btn ml10"  href="javascript:;">添加新项</a>';
                _html += '<a class="btn btn-qing ml10 delete-class-btn" data-index="'+_index+'" href="javascript:;">删除</a></dd><dt></dt></dl>';
            that.parents(".behind").append(_html);
            that.remove();
        });
        //删除新项
        $('.behind').on('click','.delete-class-btn',function(){
              var that = $(this),
                _index = that.data('index'),
                _dl = that.parents('dl'),
                _deleteBtn = _dl.prev().find('.delete-class-btn');
                _index--;
            if(_dl.next('dl').length === 0){
                if(_deleteBtn.length !== 0){
                   _deleteBtn.before('<a class="btn btn-qing add-new-item-btn ml10" data-index="'+_index+'" href="javascript:;">添加新项</a>');
                }else{
                    _dl.prev().find('dd').eq(3).after('<dd><a class="btn btn-qing add-new-item-btn ml10" data-index="'+_index+'" href="javascript:;">添加新项</a></dd>');
                }
            };
            _dl.remove();
         });
         //选择分类
           $('.behind').on('change','.input-class-name',function(){
               var that = $(this),
                _name  = that.find("option:selected").text();
            that.parents('dl').find('.class-name').val(_name);
        });
        //表单提交
        $('.submit-btn').click(function(){
            var that = $(this),_url,
                _id = that.data('id');
            if(_id){
                _url = '/SystemManage/edit_Configure?_id='+_id;
            }else{
                _url = '/SystemManage/add_Configure'
            }
            $.ajax({
                type: "POST",
                dataType: "json",
                url: _url,//url
                data: $('#form_send_address').serialize(),
                success: function (result) {
                    if (result.code == 200) {
                        layer.msg(result.result, {icon: 1});

                    }else{
                        layer.msg(result.result, {icon: 2});
                    }
                },
                error : function(result) {
                    alert("操作一次异常，请稍后重试！"+ result.result);
                }
            });
        });
    };
    /**
    *系统配置信息页面
     */
    function configList(){
        $('.add-configlist-btn').click(function(){
            Common.addAndEditor('/SystemManage/add_config','/SystemManage/add_config','','400pxspu','添加配置');
        });
    };
    /**
     * [nationalCompetition description]
     * @return {[type]} [description]
     * @author wang   addtime 2018-09-13
     */
    function nationalCompetition(){
       $('#nationalCompetition').click(function(){
            Common.addAndEditor('/CustomFilter/nationalCompetition','/CustomFilter/nationalCompetition','700px','550px','','#editorPermissions');
       });
       $('#CustomFilter').click(function(){
            Common.submit_data('/CustomFilter/add');
       });
       //点击购买次数
       $("#order_buy_condition").change(function(){
            var that = $(this);
                val = that.val();
                if(val !=0){
                     $("#order_buy_count").show();
                }else{
                     $("#order_buy_count").hide();
                }
            // console.log(val);
       });
    }
    /**
    *广告数据导入
    *@author wang   addtime 2019-03-11
    */
    function import_ads(){
        $('.ad-index-import').click(function(){
            Common.addAndEditor('/Advertisement/import_ads','/Advertisement/import_ads','600px','200px','广告导入','#addUserForm',2);
        });
    };

    /**
    *风控
    *@author wang   addtime 2019-03-23
    */
    function SpecialList(){
        //风控新增
        $('.add-special-list').click(function(){
            var that = $(this),
            _id = that.data('id');
            _id = _id?_id:'';
            Common.addAndEditor('/WindControl/SpecialList_add?id='+_id,'/WindControl/SpecialList_add','600px','300px','风控新增','#addUserForm',2);
        });
         //风控新增
        $('.add-special-address').click(function(){
            var that = $(this),
            _id = that.data('id');
            _id = _id?_id:'';
            Common.addAndEditor('/WindControl/SpecialAddress_add?id='+_id,'/WindControl/SpecialAddress_add','600px','300px','新增高风险地址','#addUserForm',2);
        });
        //风控新增国家
        $('.add-special-country-list').click(function(){
            var that = $(this),
            _id = that.data('id');
            _id = _id?_id:'';
            Common.addAndEditor('/WindControl/AddRiskCountry?id='+_id,'/WindControl/AddRiskCountry','600px','300px','新增高风险国家','#addUserForm',2);
        });
        //风控新增城市
        $('.add-special-city-list').click(function(){
            var that = $(this),
            _id = that.data('id');
            _id = _id?_id:'';
            Common.addAndEditor('/WindControl/AddRiskCity?id='+_id,'/WindControl/AddRiskCity','600px','300px','新增高风险城市','#addUserForm',2);
        });

    };
    function edm(){
        //edm国家删除
        $('.national-language-delete').click(function(){
            var that = $(this),
            _id = that.data('id');
            _id = _id?_id:'';
            var dataParam = {id:_id};
            Common.Delete('/CustomFilter/nationalLanguageDelete',dataParam,'国家配置中删除');
        });

    }

    $(function(){
        Init();
    });
    return {
        mallSet:mallSet,
        paymentSet:paymentSet,
        ragionManage:ragionManage,
        classSett:classSett,
        configList:configList,
        nationalCompetition:nationalCompetition,
        import_ads:import_ads,
        SpecialList:SpecialList,
        edm:edm

    }
}();