/**
 * Created by lijunfang on 2015/2/2.
 */
var Common = function() {
    /**
     * 是否为空
     */
    function isEmpty(str){
        if(str=="" || str==null || str=="undefined"){
            return true;
        }
        return false;
    }

    function Init() {
        searchBox();
        datapick();
       // Common.LoadSelect('.bs-select[multiple]'); //下拉框多选js
        Common.LoadSelect2('.bs-select-2'); //下拉框自动补全js
        $('#btnUpdatePwd').on('click', function(e) {
            $.get("pop_update_pwd.html", function(data) {
                layer.open({
                    title:"密码修改",
                    content: data,
                    area:["350px","300px"],
                    btn:["保存","取消"],
                    yes:function(){

                    },
                    cancel:function(){

                    }

                });
            });
        });
        $('#meansModify').on('click', function(e) {
            $.get("means_modify.html", function(data) {
                layer.open({
                    title:"资料修改",
                    content: data,
                    area:["400px","300px"],
                    btn:["保存","取消"],
                    yes:function(){

                    },
                    cancel:function(){

                    }

                });
            });
        });
    }


    //add by lijunfang 20151105 Ajax 加载时，控件渲染
    function AjaxInit() {
        searchBox();
        datapick();
        Common.LoadSelect('.bs-select'); //下拉框多选js
        Common.LoadSelect2('.bs-select-2'); //下拉框自动补全js
    }

    /**
     * 全选功能
     * @param object 点击全选按钮的id
     * @param object_2 被选中的checkbox的class
     */
    function allSelect(object,object_2){
        $(object).click(function(){
            var all_checked=$(this),
            singe_checked=$(object_2).parent();
            if(all_checked.prop("checked")){
                singe_checked.addClass("checked");
                $(object_2).prop("checked","checked");
            }else{
                singe_checked.removeClass("checked");
                $(object_2).removeAttr('checked');
            }
        });
    };
    /**
    *反选功能
    * @param object 点击反选按钮的id
    * @param object_2 被选中的checkbox的class
    */
    function reverseAllSelect(object,object_2){
        $(object).click(function(){
            var all_checked = $(this),
            singe_checked = $(object_2).parent();
            $(object_2).each(function(index,ele){
                if($(ele).prop('checked')){
                    $(ele).prop('checked','');
                }else{
                    $(ele).prop('checked','checked');
                };
            });

        });
    };

    /**
     * 查询条件收缩
     */
    function searchBox() {
        //id元素的时候
        $('#more-search').click(function() {
            $('#search-standard').hide();
            $('#search-advanced').slideDown();
        });
        $('#btn-search-standard').click(function() {
            $('#search-standard').show();
            $('#search-advanced').slideUp();
        });
        //class元素存在的时候
        $('.more-search-btn').click(function() {
            var $this = $(this),
                index = $('.more-search-btn').index(this);
            $('.search-btn-wrap').eq(index).hide();
            $('.search-content-wrap').eq(index).slideDown();
            $('.search-content-wrap form').addClass('active-form');
            $this.parents('form').removeClass('active-form');
        })
        $('.standard-search-btn').click(function() {
            var $this = $(this),
                index = $('.standard-search-btn').index(this);
            $('.search-btn-wrap').eq(index).show();
            $('.search-content-wrap').eq(index).slideUp();
            $('.search-btn-wrap form').addClass('active-form');
            $this.parents('form').removeClass('active-form');
        })
    }

    /**
     * 时间控件
     * @param dataobject
     */
    function datapick() {
        $('#reservationtime,#starttime,.data-time').click(function() {
            WdatePicker({
                startDate: '%y-%M-%d 00:00:00',
                dateFmt: 'yyyy-MM-dd HH:mm:ss',
               alwaysUseStartDate: false
            });
        });

        $('#endtime,#canceltime').click(function() {
            WdatePicker({
                startDate: '%y-%M-%d 23:59:59',
                dateFmt: 'yyyy-MM-dd HH:mm:ss',
                alwaysUseStartDate: false
            });
        });
    }


    function IsLogin() {
        var uname = localStorage.getItem("yg_tb_username");
        if (!uname && location.pathname != '/login.shtml') {
            location.href = "login.shtml";
        }
    }

    /**
     * 下拉框多选
     * @param state 下拉款选中的文字
     */
    function format(state) {
        if (!state.id) return state.text; // optgroup
        return state.text;
    }

     /**
     * 下拉框自动补全js插件执行
     * @param obj 加载下拉框自动补全的对象，class,id
     */
    function loadSelect2(ele) {
        $(ele).select2({
            placeholder: "Select a Country",
            allowClear: true,
            formatResult: format,
            formatSelection: format,
            escapeMarkup: function(m) {
                return m;
            }
        });
    }

   /**
     * 下拉框多选插件js执行
     * @param ele 加载下拉框的对象,class,id
     */
    function loadSelect(obj) {
        $(obj).selectpicker({
            iconBase: 'fa',
            tickIcon: 'fa-check',
            size: 5,
            actionsBox:true
        });
    };
    /**
    * 删除方法
    * param1 URL 删除的地址
    * param2 dataParam 删除需要传的参数
    * creat by lijunfang 20180816
    */
    function Delete(url,dataParam,title){
        layer.msg(title, {
            time: 0, //不自动关闭
            btn: ['确定', '取消'],
            yes: function(index){
                layer.close(index);
                $.ajax({
                    type:"POST",
                    url:url,
                    data:dataParam,
                    dataType:"json",
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
    };
    /**
    *添加和修改方式处理
    * param1 url ajax的地址
    * param2 dialogWidth弹窗的宽度
    * param3 dialogHeight 弹窗的高度
    * param6 ,formID 表单提交ID
    */
    function addAndEditor(urlHtml,url,dialogWidth,dialogHeight,dialogTitle,formID,format){
       var _width = dialogWidth ? dialogWidth : '680px',
            _height = dialogHeight ? dialogHeight :'600px';
            _formID = formID ? formID:"#addUserForm";
            _format =  format ? format:"1";
            $.get(urlHtml, function (data) {
              layer.open({
                  title: dialogTitle ? dialogTitle :"编辑",
                  content: data,
                  type: 1,
                  area: [_width,_height],
                  offset: '10px',
                  btn: ["保存", "取消"],
                  yes: function (index) {
                        if(_format == 1){
                                var formData = $(_formID).serialize();//:nth-child(3)
                                $.ajax({
                                    type:"POST",
                                    url:url,
                                    dataType: 'json',
                                    data:formData,
                                    success:function(msg){
                                        if(msg.code == 200){
                                            layer.msg(msg.result, {icon: 1});
                                            setTimeout(function(){
                                                if(msg.type == 1){
                                                    window.location.href = msg.url;
                                                }else{
                                                    window.location.reload();
                                                }

                                            },1500);
                                        }else if(msg.code == 201){
                                            $.each(msg.result,function(k,v){
                                                if(v.type == 'val'){
                                                    $(v.id).val(v.data);
                                                }else if(v.type == 'html'){
                                                    $(v.id).html(v.data);
                                                }
                                               // console.log(v);
                                            });

                                            setTimeout(function(){
                                               // $(".TxnID").val(msg.result.TxnID);
                                               $(".layui-layer-shade").remove(".layui-layer-shade");
                                               $(".layui-layer-page").remove(".layui-layer-page");
                                            },500);
                                        }else{
                                            layer.msg(msg.result, {icon: 2});
                                        }
                                    }
                                });
                        }else if(_format == 2){
                            // console.log(_formID);
                            var form = $(_formID)[0];
                            var formData = new FormData(form);
                            $.ajax({
                                  url: url,
                                  type: 'POST',
                                  data: formData,
                                  async: false,
                                  cache: false,
                                  dataType: 'json',
                                  contentType: false,
                                  processData: false,
                                  success: function(msg) {
                                      if(msg.code == 200){
                                            layer.msg(msg.result, {icon: 1});
                                            setTimeout(function(){
                                                if(msg.type == 1){
                                                    window.location.href = msg.url;
                                                }else{
                                                    window.location.reload();
                                                }

                                            },1500);
                                        }else if(msg.code == 201){
                                            $.each(msg.result,function(k,v){
                                                if(v.type == 'val'){
                                                    $(v.id).val(v.data);
                                                }else if(v.type == 'html'){
                                                    $(v.id).html(v.data);
                                                }
                                               // console.log(v);
                                            });

                                            setTimeout(function(){
                                               // $(".TxnID").val(msg.result.TxnID);
                                               $(".layui-layer-shade").remove(".layui-layer-shade");
                                               $(".layui-layer-page").remove(".layui-layer-page");
                                            },500);
                                        }else{
                                            layer.msg(msg.result, {icon: 2});
                                        }
                                  },
                                  error: function(data) {
                                       layer.msg('数据异常', {icon: 2});
                                  }
                            });
                        }
                  },
                  cancel: function () {
                  }
              });
          });
        // console.log(2);
    };
    /**
     * 提交表单
     * [submit description]
     * @param  {[type]} url [description]  提交路径
     * @param  {[type]} _formID [description]  表单都对应的id
     * @return {[type]}     [description]
     */
    function submit_data(url,formID){
        var _formID = formID ? formID:"#addUserForm";
            formData = $(_formID).serialize();//:nth-child(3)
            $.ajax({
                type:"POST",
                url:url,
                dataType: 'json',
                data:formData,
                success:function(msg){
                    if(msg.code == 200){
                        layer.msg(msg.result, {icon: 1});
                        setTimeout(function(){
                             if(msg.type == 1){
                                window.location.href = msg.url;
                             }else{
                                window.location.reload();
                             }
                        },1500);
                    }else{
                        layer.msg(msg.result, {icon: 2});
                    }
                }
            });
    }
    /**
    *颜色值的添加
    */
   function colorName(_id){
       var that = $(this),_html = '',
            _name = that.data('color'),
            _name_en = that.data('color-en'),
            _val = that.val(),
        formControl = $(".behind").find(".form-control");
        if(that.prop("checked")){
            if(_name && _val){
                var isHas = false;
                formControl.each(function(index,ele){
                    if($(ele).val() === _name_en){
                        isHas = true;
                        return false;
                    }
                });
                if(!isHas){
                    _html += '<dl class="c-h-dl-validator form-group clearfix add-attribute'+_id+' delect_dl">';
                    _html += '<dd class="v-title"><label><em>*</em>中文名：</label></dd>';
                    _html += '<dd><div class="input-icon right inline-block"><i class="fa"></i>';
                    _html += '<input name="where['+_id+'][title_cn]" class="form-control input-medium" type="text"  value="'+_name+'">';
                    _html += '</div>  英文名： <div style="" class="input-icon right inline-block">';
                    _html += '<input style="" name="where['+_id+'][title_en]" class="form-control " type="text" value="'+_name_en+'">';
                    _html += '</div>   选项值： <div style="" class="input-icon right inline-block">';
                    _html += '<input style="" name="where['+_id+'][value]" class="form-control " type="text" value="'+_val+'"></div>排序：';
                    _html += '<div style="" class="input-icon right inline-block inline_block">';
                    _html += '<input name="where['+_id+'][sort]" class="form-control input-val-1 w100" type="text" value="">';
                    _html += '</div></dd><dt></dt></dl>';
                    $(".hidden_name").after(_html);
                }
            }
        }else{
            formControl.each(function(index,ele){
                if($(ele).val() === _name_en){
                    $(ele).parents(".form-group").remove();
                }
            });
        }
   };
    /**
     * 字符串处理
     */
    function strFormat (str) {
        if (typeof str !== "string") {
            return str;
        }
        var l = arguments.length, i = 0, reg;

        if (l === 2 && $.type(arguments[1]) === 'object') {
            var repalceObj = arguments[1], key;
            for (key in repalceObj) {
                if (repalceObj.hasOwnProperty(key)) {
                    reg = new RegExp('\\{' + key + '\\}', 'g');
                    str = str.replace(reg, function () {
                        return repalceObj[key];
                    });
                }
            }
        }
        else {
            for (; i < l - 1; i++) {
                reg = new RegExp('\\{' + i + '\\}', 'g');
                var v = arguments[i + 1];
                str = str.replace(reg, function () {
                    return v;
                });
            }

        }
        return str;
    };
    /**
     * 图片上传功能函数
     */
    function leadingin(obj, ele) {
        $(obj).change(function (event) {
            var path_val = $(this).val();
            var point = path_val.lastIndexOf(".");
            var type = path_val.substr(point);
            $(ele).html(path_val);
        });
    };
    $(function() {
        App.ajaxCall = AjaxInit;
        Init();
    });
    return {
        AllSelect: allSelect, //全选
        SearchBox: searchBox, //查询条件收缩js执行
        LoadSelect: loadSelect,
        LoadSelect2: loadSelect2,
        isEmpty:isEmpty,
        Delete:Delete,
        addAndEditor:addAndEditor,
        ReverseAllSelect:reverseAllSelect,  //反选
        colorName:colorName,
        submit_data:submit_data,
        StrFormat:strFormat,
        leadingIn:leadingin
    };
}();
