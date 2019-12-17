var Product = function() {
     /**
     * 初始化函数
     */
    function Init(){

    };
    function batch_submit(){
        $('.js-notbatch-submit').click(function(){
            var that = $(this),spu = that.data('spu'),
                reason = $('.reason').val();
                type = $('#type').val();
            if(type == '' || type==null){
                layer.msg('理由不能为空', {icon: 2});
                return;
            }
            if(reason == '' || reason==null){
                layer.msg('类型不能为空', {icon: 2});
                return;
            }
            $.ajax({
                type:"POST",
                url:'/ProductManagement/batchNotExamine',
                data:{id:spu,reason:reason,type:type},
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
                error:function(error){layer.msg('提交出错', {icon: 2});}
            });
        });

    }
    /**
    * product ExamineList
    */
    function productExamineList(){
        Common.AllSelect($("#all"),$('.single-checkbox'));
        batch_submit(); //批量不通过确定
        //批量通过
        $("#getValue").click(function(){
            var valArr = new Array(),vals;
            $(".single-checkbox:checked").each(function (i) {
                valArr[i] = $(this).val();
            });
            vals = valArr.join(',');
            if(vals != ''){
                layer.msg('确定要批量通过么？', {
                    time: 0, //不自动关闭
                    btn: ['确定', '取消'],
                    yes: function(index){
                    layer.close(index);
                        $.ajax({
                            type:"POST",
                            url:'/ProductManagement/batchExamine',
                            data:{id:vals},
                            dataType:"json",
                            cache:false,
                            success:function(msg){
                            if(msg.code == 200){
                                layer.msg(msg.result, {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                },1500);
                            }else{
                                layer.msg(msg.result, {icon: 2});
                            }
                            },
                            error:function(error){}
                        });
                    }
                });
            }else{
                layer.msg('获取不到数据');
            }
        });

        //批量不通过
        $("#notgetValue").click(function(){
            var valArr = new Array(),vals,
                _batchNotDialog = $('#batchNotDialog'),
               selectHtml = _batchNotDialog.data('html');
            $(".single-checkbox:checked").each(function (i,ele) {
                valArr[i] = $(this).val();
            });
            vals = valArr.join(',');
            if(vals != ''){
                layer.open({
                    title: '批量不通过理由',
                    type: 1,
                    skin: 'layui-layer-rim', //加上边框<form id="examine_submit"  method="post">
                    area: ['420px', '340px'], //宽高
                    content:_batchNotDialog
                });
                $('.js-notbatch-submit').data('spu',vals);
            }else{
                layer.msg('获取不到数据');
            }
        });

        //鼠标移入缩略图放大图片
        $(".show-pic").mouseover(function(event) {
            var _this = $(this),
                imgSrc = _this.attr("src"),
                enlarge_images = $("#enlarge_images"),
                top = $(document).scrollTop() + event.clientY + 10 + "px";
                left = $(document).scrollTop() + event.clientX + 10 + "px";
                enlarge_images.show();
                enlarge_images.html('<img width="400" height="400" src="' + imgSrc + '" />');
                enlarge_images.css({"top":top, "left":left});
        });
        //鼠标移出缩略图隐藏放大的图片
        $(".show-pic").mouseout(function(event) {
            var enlarge_images = $("#enlarge_images");
                enlarge_images.hide();
                enlarge_images.html('');
        });
        $(".show-pic").click(function(event) {
            window.open($(this).attr("src"));
        })
    };
    /**
    *属性颜色添加页面
    */
    function attrColorManage(id){
        $('.add-attr-color').click(function(){
             var that = $(this),
                _id = that.data('id') ? that.data('id'):0;
            $.get('/AttributeColor/add/id/'+_id, function (data) {
                layer.open({
                    title: "配置颜色",
                    content: data,
                    type: 1,
                    area: ['400px', '300px'],
                    offset: '10px',
                    btn: ["保存", "取消"],
                    yes: function (index) {
                        var formData = new FormData($( "#addForm" )[0]);
                        $.ajax({
                            type:"POST",
                            url:"/AttributeColor/addPost",
                            dataType: 'json',
                            data:formData,
                            async: false,
                            cache: false,
                            contentType: false,
                            processData: false,
                            success:function(msg){
                                if(msg.code == 200){
                                    layer.msg(msg.result, {icon: 1});
                                    setTimeout(function(){
                                        window.location.reload();
                                    },1500);
                                }else{
                                    layer.msg(msg.result, {icon: 2});
                                }
                            }
                        });
                    },
                    cancel: function () {

                    }
                });
            });
        });
    };
    /**
    * 添加属性值页面
    */
    function addAttribute(){
        //添加和编辑属性提交
        $('.add-editor-attribute-btn').click(function(){
            $.ajax({
                //几个参数需要注意一下
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url: "submit_attribute" ,//url
                data: $('#form_send_address').serialize(),
                success: function (result) {
                    if (result.code == 200) {
                        layer.msg(result.result, {icon: 1});
                        setTimeout(function(){
                            history.go(-1);
                        },1000);
                    }else{
                        layer.msg(result.result, {icon: 2});
                    }
                },error : function() {
                    layer.msg('异常！', {icon: 2});
                }
            });
        });

        if(!$("#color_view").is(":hidden")){
            var name = $("#input-color").val();
            if($.trim(name) == '颜色'){
                $.ajax({
                    type: "GET",
                    dataType: "json",
                    url: "/AttributeColor/asyncGetAllColor" ,
                    success: function (result) {
                        if (result.code == 200) {
                            var data = result.html;
                            var html = "";
                            var formControl = $(".behind").find(".form-control"); // 颜色英文名
                            $("#color_view .color-wrap").html("");
                            $.each(data,function(n,val){
                            var res = data[n];
                            html += '<span><input  class="color_name_'+ res.id +' color_name_first_'+ res.id +' color-selection-box" data-id="'+ res.id +'"  type="checkbox" name="color" data-color = "'+ res.title_cn +'" data-color-en = "'+ res.title_en +'" value="'+ res.color_value +'" ';
                                for(var i=0;i<formControl.length;i++){
                                    if(formControl.eq(i).val() == res.title_en){
                                        html += 'checked="checked" ';
                                    }
                                }
                            html += '"><span style="background: '+ res.color_value +';" class="span_coler" ></span></span>';
                            })
                            $("#color_view .color-wrap").html(html);
                        }else{
                            layer.msg(result.result, {icon: 2});
                        }
                    },error : function() {
                        layer.msg('异常！', {icon: 2});
                    }
                });
            }
        };
        //屬性添加新項
        $('.behind').on('click','.add-attr-btn',function(){
            var that = $(this),e,
                val_attribute = that.data('total');
            if(val_attribute != null){
                e = val_attribute + 1;//console.log(e);
                val_attribute = e;
                e = val_attribute;
                }else{
                e = e + 1;
                val_attribute = e;
            }
            $(".delect").remove();
            $(".behind").append('<dl class="c-h-dl-validator form-group clearfix add-attribute'+e+' delect_dl"><dd class="v-title"><label><em>*</em>中文描述：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="where['+e+'][title_cn]" class="form-control input-medium" type="text"></div>  英文名： <div style="" class="input-icon right inline-block"><input style="" name="where['+e+'][title_en]" class="form-control " type="text"></div>  选项值： <div style="" class="input-icon right inline-block"><input style="" name="where['+e+'][value]" class="form-control " type="text"></div> 排序：<div style="" class="input-icon right inline-block inline_block"><input name="where['+e+'][sort]" class="form-control input-val-1 w100" type="text"></div><a class="eliminate-btn2 add-attr-btn delect added'+e+'"  data-total="'+e+'" href="javascript:;">添加新项</a><a class="eliminate-btn2 eliminate'+e+'" onclick="add_delect('+e+')" href="javascript:;">删除</a></dd><dt></dt></dl>');

        });

        //颜色属性勾选添加
       $('#color_view').on('click','.color-selection-box',function(){
            var that = $(this),
                _id = that.data('id');
            Common.colorName.call(that,_id);
        });
       //分类排序修改
     $('body').on('click','.sort',function(){
       $('.sort').blur(function () {
            var that = $(this),
                val = that.val(),
                class_id = that.data('id');
            $.ajax({
                type:"POST",
                url:"/ProductManagement/class_sort.html",
                dataType: 'json',
                data:{class_id:class_id,val:val},
                cache:false,
                success:function(msg){
                   // console.log(12);return;
                    if(msg.code == 200){
                        layer.msg(msg.result, {icon: 1});
                    }else{
                        layer.msg(msg.result, {icon: 2});
                    }
                }
           });
            $(".sort").unbind();
       })

   })
       //分类邮编修改
       $('.HSCode').blur(function () {
            var that    = $(this),
               HSCode   = that.val(),
               class_id = that.data('id');
            $.ajax({
                type:"POST",
                url:"/ProductManagement/class_HSCode.html",
                dataType: 'json',
                data:{class_id:class_id,HSCode:HSCode},
                cache:false,
                success:function(msg){
                   // console.log(12);return;
                    if(msg.code == 200){
                        layer.msg(msg.result, {icon: 1});
                    }else{
                        layer.msg(msg.result, {icon: 2});
                    }
                }
           });
       })
       //获取分类多余语言
        $('#input_class_lang').blur(function () {
             var that    = $(this),html = '',
                class_id   = that.val();
                // console.log(class_id);
             if(!class_id){
                return;
             }
             $.ajax({
                    type:"POST",
                    url:"/ProductManagement/class_language_data.html",
                    dataType: 'json',
                    data:{class_id:class_id},
                    cache:false,
                    success:function(msg){//console.log(msg);console.log(msg.code);
                        if(msg.code == 200){
                              //console.log(JSON.stringify(msg.result.Common.length));
                               $.each(msg.result.Common, function (index, item) {
                                   html += '<dl class="c-h-dl-validator form-group clearfix deletelang">' +
                                            '<dd class="v-title">'+
                                            '<label><em>*</em>'+index+'：</label>'+
                                            '</dd>'+
                                            '<dd><div class="input-icon right"><i class="fa"></i>'+
                                            '<input style="width: 466px !important;" value="'+item+'" name="Common['+index+'][title_en]"id="input-color-en" class="form-control input-medium fl" type="text">'+
                                            '</div></dd><dt></dt>'+
                                            '</dl>';
                               });
                               $(".deletelang").remove();
                               $(".lang").after(html);
                               $(".delete_class").remove();
                               $(".class_name").append('<label class="delete_class">'+msg.result.class_html+'</label>');
      // console.log(msg.result.class_html);
                        }else{
                                $(".deletelang").remove();
                                $(".delete_class").remove();
                                layer.msg(msg.result, {icon: 2});
                        }
                    }
                });
        });
        $('.editor-class-btn').click(function(event) {
              $.ajax({
                type: "POST",//方法类型
                dataType: "json",//预期服务器返回的数据类型
                url:'/ProductManagement/eidt_class_lang.html',//url"/PaymentSetting/eidt_config"
                data: $('#addUserForm').serialize(),
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

        // $('#input-class').on('click','.input-class',function(){
        //     console.log(1212);
        // });

    };
     //添加属性值
    function addAndEditorBrand(url){

        // console.log(2);
    };
    /**
    * 品牌管理产品
    */
    function brandManage() {
        //添加品牌
        $('.add-brand-btn').click(function(){
            $.get('/ProductManagement/add_brand.html', function (data) {
                layer.open({
                    title: "添加品牌",
                    content: data,
                    type: 1,
                    area: ['680px', '600px'],
                    offset: '10px',
                    btn: ["保存", "取消"],
                    yes: function (index) {
                        var brand_name = $("#brand_name").val();
                        if(brand_name ==""){
                            layer.msg("请录入品牌名称", {icon: 2});
                            return;
                        }
                        //判断是否有选择上传文件
                        var imgPath = $("#chooseImage").val();
                        if (imgPath == "") {
                            layer.msg("请选择图片文件", {icon: 2});
                            return;
                        }
                        //判断上传文件的后缀名
                        var strExtension = imgPath.substr(imgPath.lastIndexOf('.') + 1);
                        if (strExtension != 'jpg' && strExtension != 'gif'&& strExtension != 'png' && strExtension != 'bmp') {
                            layer.msg("请选择图片文件", {icon: 2});
                            return;
                        }
                        var formData = new FormData($( "#addUserForm" )[0]);
                        $.ajax({
                            type:"POST",
                            url:"/ProductManagement/add_brand.html",
                            dataType: 'json',
                            data:formData,
                            async: false,
                            cache: false,
                            contentType: false,
                            processData: false,
                            // data:JsonData,
                            success:function(msg){//console.log(msg);console.log(msg.code);
                                if(msg.code == 200){
                                        layer.msg(msg.result, {icon: 1});
                                        setTimeout(function(){
                                            window.location.reload();
                                        },1500);
                                }else{
                                        layer.msg(msg.result, {icon: 2});
                                }
                            }
                        });
                    },
                    cancel: function () {

                    }
                });
            });
        });

        //编辑品牌
        $('.editor-brand-td').on('click','.editor-brand-btn',function(){
           var that = $(this),
            _brandId = that.data('brandid');
            $.get('/ProductManagement/edit_brand/id/'+_brandId, function (data) {
                layer.open({
                    title: "修改品牌",
                    content: data,
                    type: 1,
                    area: ['680px', '600px'],
                    offset: '10px',
                    btn: ["保存", "取消"],
                    yes: function (index) {
                        //alert('ssss');
                        var brand_name = $("#brand_name").val();
                        if(brand_name ==""){
                            layer.msg("请录入品牌名称", {icon: 2});
                            return;
                        }
                        var edit_img_path = $("#cropedBigImg")[0].src;
                        //alert(edit_img_path);
                        if(edit_img_path ==""){
                            //判断是否有选择上传文件
                            var imgPath = $("#chooseImage").val();
                            if (imgPath == "") {
                                layer.msg("请选择图片文件", {icon: 2});
                                return;
                            }
                            //判断上传文件的后缀名
                            var strExtension = imgPath.substr(imgPath.lastIndexOf('.') + 1);
                            if (strExtension != 'jpg' && strExtension != 'gif'&& strExtension != 'png' && strExtension != 'bmp') {
                                layer.msg("请选择图片文件", {icon: 2});
                                return;
                            }
                        }
                        var formData = new FormData($( "#addUserForm" )[0]);
                        $.ajax({
                            type:"POST",
                            url:"/ProductManagement/edit_brand.html",
                            dataType: 'json',
                            data:formData,
                            async: false,
                            cache: false,
                            contentType: false,
                            processData: false,
                            // data:JsonData,
                            success:function(msg){
                                if(msg.code == 200){
                                        layer.msg(msg.result, {icon: 1});
                                        setTimeout(function(){
                                            window.location.reload();
                                        },1500);
                                }else{
                                        layer.msg(msg.result, {icon: 2});
                                }
                            }
                        });
                    },
                    cancel: function () {

                    }
                });
            });
        });

        //删除品牌
         $('.editor-brand-td').on('click','.delete-brand-btn',function(){
            var that = $(this),
            _brandId = that.data('brandid'),
            param = {
               id:_brandId
            };
            Common.Delete('/ProductManagement/del_brand',param,"确定要删除吗？");
        });

        //鼠标移入缩略图放大图片
        $(".show-pic").mouseover(function(event) {
            var _this = $(this),
                imgSrc = _this.attr("src"),
                enlarge_images = $("#enlarge_images"),
                top = $(document).scrollTop() + event.clientY + 10 + "px";
                left = $(document).scrollTop() + event.clientX + 10 + "px";
                enlarge_images.show();
                enlarge_images.html('<img width="160" height="50" src="' + imgSrc + '" />');
                enlarge_images.css({"top":top, "left":left});
        });
        //鼠标移出缩略图隐藏放大的图片
        $(".show-pic").mouseout(function(event) {
            var enlarge_images = $("#enlarge_images");
                enlarge_images.hide();
                enlarge_images.html('');
        });
        $(".show-pic").click(function(event) {
            window.open($(this).attr("src"));
        })
    };
    /**
    *绑定品牌页面
    */
    function brandAttributeList(){
        //添加绑定品牌
        $('.add-bind-brand-btn').click(function(){
            Common.addAndEditor('/ProductManagement/eidtBrandAttribute.html','/ProductManagement/bindingBrandAttribute.html');
        });
        //编辑绑定属性
        $('.bind-brand-td').on('click','.editor-bind-brand-btn',function(){
            var that = $(this),
                _id = that.data('id');
            Common.addAndEditor('/ProductManagement/eidtBrandAttribute/id/'+_id,'/ProductManagement/bindingBrandAttribute.html');
        });
        //删除绑定属性
        $('.bind-brand-td').on('click','.delete-bind-brand-btn',function(){
            var that = $(this),
                _id = that.data('id'),
                param = {
                    id:_id
                };
            Common.Delete('/ProductManagement/delete_binding',param,"确定要删除吗？");
        });
        //批量修改
        $('.edit-ShopInquiries').click(function(event) {
                layer.open({
                  title: '批量修改商铺',
                  type: 1,
                  skin: 'layui-layer-rim', //加上边框
                  area: ['450px', '420px'], //宽高
                  content: '<div class="pl30">' +
                  '<div class="mt20">格式 SPU:店铺ID（2600001:999）多个请用逗号隔开或者换行</div>' +
                  '<div class="mt30">' +
                  '<form id="submit-ShopInquiries" class="navbar-left"  method="post" role="search">' +
                  '<label class="w120">SPU：</label>' +
                  '<textarea class="" name="spu" rows="13" cols="60" style=""></textarea>' +
                  '</div>' +
                  '<div class="tcenter">' +
                  '<a href="javascript:void(0);"  class = "submit btn-qing f18 submit-ShopInquiries">确认修改</a>' +
                  '</div>' +
                  '</form>' +
                  '</div>'
                 });
        });
        // $('.submit-ShopInquiries').click(function(event) {

        $('body').on('click','.submit-ShopInquiries',function(){
                var formData = new FormData($( "#submit-ShopInquiries" )[0]);
                $.ajax({
                    type:"POST",
                    url:"/ProductManagement/submit_ShopInquiries.html",
                    dataType: 'json',
                    data:formData,
                    async: false,
                    cache: false,
                    contentType: false,
                    processData: false,
                    // data:JsonData,
                    success:function(msg){
                        if(msg.code == 200){
                                layer.msg(msg.result, {icon: 1});
                                setTimeout(function(){
                                    window.location.reload();
                                },1500);
                        }else{
                                layer.msg(msg.result, {icon: 2});
                        }
                    }
                });
             // console.log(1212);
        });
        $('.StoreID').click(function(event) {
             if(!$(".StoreID_input").hasClass('StoreID_input')){
                var that = $(this),
                    data_id = that.data('id'),
                    data_storeid = that.data('storeid');
                $(this).html('<input type="text" data-id="'+data_id+'" data-storeid="'+data_storeid+'" name="StoreID" class="StoreID_input" value="'+data_storeid+'" >');
             }
        });
//         $(".StoreID_input").live('blur',function(){
// console.log(121);
//         });
        $('body').on('blur','.StoreID_input',function(){

                  var that = $(this);
                  var spu = that.data('id'),
                        StoreID = that.val();
                  if(that.val() == that.data('storeid')){

                     $('.StoreID_input').after(StoreID);
                     $('.StoreID_input').remove();
                     return;
                  }else{

                    $.ajax({
                        type:"POST",
                        url:"/ProductManagement/edit_StoreID.html",
                        dataType: 'json',
                        data:{StoreID:StoreID,spu:spu},
                        cache:false,
                        success:function(msg){
                            if(msg.code == 200){
                                  layer.msg(msg.result, {icon: 1});
                                 setTimeout(function(){
                                    window.location.reload();
                                },1500);
                            }else{

                                    layer.msg(msg.result, {icon: 2});
                            }
                        }
                   });
                    $(".StoreID_input").unbind();
                  }
        })

        $('body').on('click','#product_brand_btn', function(event) {
            event.stopPropagation();
            $(".select-brand-wrapper-inner").toggleClass("hide");
            $("#brand_keyword").val("");
            $(".select-brand-wrapper-inner ul").html("").addClass('hide');
        })
        $('body').on('keyup','#brand_keyword', function(event) {
            event.stopPropagation();
            var that = $(this),
                BrandName=that.val();
            $.ajax({
                type:"POST",
                url:"/ProductManagement/AcquireBrand",
                dataType: 'json',
                data:{BrandName:BrandName},
                cache:false,
                success:function(data){
                    if(data.code == 200){

                        var html = '',
                            data = data.result;

                        for (index in data) {
                            html += '<li dataname="'+ data[index].BrandName +'" datavalue="'+ data[index].BrandId +'">'+ data[index].BrandName +'</li>';
                        }
                        $(".select-brand-wrapper-inner ul").html(html);
                        if(data.length>0){
                            $(".select-brand-wrapper-inner ul").removeClass('hide');
                        }else{
                            $(".select-brand-wrapper-inner ul").addClass('hide');
                        }

                    }else{
                        layer.msg(data.result, {icon: 2});
                    }
                }
            });
        })

        $(document).on("click","#brand_select li",function(){
            var _this = $(this);
            var max = 0;
            var name = _this.attr("datavalue");
            var value  = _this.attr("dataname");
            if($(".delete"+name).is('.delete'+name)){
              return;
            }
            $(".brand_sort input").each(function() {
                var that = $(this);
                var id = parseInt(that.val());
                if (id > max) {
                    max = id;
                }
            });
            max = max + 1;
            var html = 'add_activity_class('+value+')';
            var html_input = '<dl class="c-h-dl-validator form-group clearfix delete'+name+'"> <dd class="v-title"><label><em></em></label></dd><dd><div class="input-icon right"><input type="hidden"  name="brand['+name+'][id]"  value="'+name+'"><input value="'+value+'" readonly="readonly" name="brand['+name+'][name]" id="input-color-en" class="form-control input-medium fl w100" type="text"> 排序：<div style="" class="input-icon right inline-block inline_block brand_sort"><input value="'+max+'" name="brand['+name+'][sort]" class="form-control input-val-1 w100" type="text"></div></div></dd><a class="btn-top5-del eliminate'+name+'" onclick="delect_brand(\''+name+'\')" href="javascript:;">删除</a></dd><dt></dt></dl>'
            $("#brand_select").append(html_input);
            $(".select-brand-wrapper-inner").addClass("hide");
        })

        $(document).click(function(event){
            var _con = $('.select-brand-wrapper');
            var _conAttr = $('.select-attr-wrapper');
            if(!_con.is(event.target) && _con.has(event.target).length === 0){
                $('.select-brand-wrapper-inner').addClass('hide');
            }
            if(!_conAttr.is(event.target) && _conAttr.has(event.target).length === 0){
               $('.select-attr-wrapper-inner').addClass('hide');
            }
        });


        $('body').on('click','#product_attr_btn', function(event) {
            event.stopPropagation();
            $(".select-attr-wrapper-inner").toggleClass("hide");
            $("#attr_keyword").val("");
            $(".select-attr-wrapper-inner ul").html("").addClass('hide');
        })
        $('body').on('keyup','#attr_keyword', function(event) {
            event.stopPropagation();
            var that = $(this),
                title_en = that.val();
            $.ajax({
                type:"POST",
                url:"/ProductManagement/AcquireAttribute",
                dataType: 'json',
                data:{title_en:title_en},
                cache:false,
                success:function(data){
                    if(data.code == 200){
                        var html = '',
                            data = data.result;

                        for (index in data) {
                            html += '<li enname="'+ data[index].title_en +'" dataname="'+ data[index].title_cn +'" dataid="'+ data[index]._id +'">'+ data[index].title_cn +' ('+ data[index].title_en +')</li>';
                        }
                        $(".select-attr-wrapper-inner ul").html(html);
                        if(data.length>0){
                            $(".select-attr-wrapper-inner ul").removeClass('hide');
                        }else{
                            $(".select-attr-wrapper-inner ul").addClass('hide');
                        }
                    }else{
                        layer.msg(data.result, {icon: 2});
                    }
                }
            });
        })


        $(document).on("click","#attribute_select li",function(){
            var _this = $(this),
                dataValue = _this.attr("dataid");

               var max = 0;
               var value = _this.attr("dataid");
               var name  = _this.attr("dataname");
               var enName = _this.attr("enname");

               $("#attribute_select .sort input").each(function() {
                  var id = parseInt($(this).val());
                  if (id > max) {
                      max = id;
                  }
               });
               max = max + 1;
               if($(".delete_attribute"+value).is('.delete_attribute'+value)){
                  return;
               }
               var html = 'add_activity_class('+value+')';
               var html_input = '<dl class="c-h-dl-validator form-group clearfix delete_attribute'+value+'"> <dd class="v-title"><label><em></em></label></dd><dd><div class="input-icon right"><input type="hidden"  name="attribute['+value+'][id]"  value="'+value+'"><input value="'+name+' ('+enName+')" readonly="readonly" name="attribute['+value+'][name]" id="input-color-en" class="form-control input-medium fl w200" type="text"> 排序：<div style="" class="input-icon right inline-block inline_block sort"><input value="'+max+'" name="attribute['+value+'][sort]" class="form-control input-val-1 w100" type="text"></div></div></dd><a class="btn-top5-del eliminate'+value+'" onclick="delect_attribute(\''+value+'\')" href="javascript:;">删除</a></dd><dt></dt></dl>'
               $("#attribute_select").append(html_input);
               $(".select-attr-wrapper-inner").addClass("hide");
        })


    };
    /**
    *产品列表页面
     */
    function productList() {
        //违规下架
        $('.operation-td').on('click','.Irregularities-btn',function(){
            var that = $(this),
                _id = that.data('id'),
                param = {
                    id:_id
                };
            Common.Delete('/ProductManagement/ProductStatus/status/4',param,"你确定要做此操作么？");
        });
        //删除
         $('.operation-td').on('click','.delet-btn',function(){
            var that = $(this),
                _id = that.data('id'),
                param = {
                    id:_id
                };
            Common.Delete('/ProductManagement/ProductStatus/status/10',param,"确定要删除吗？？");
        });

        //鼠标移入缩略图放大图片
        $(".show-pic").mouseover(function(event) {
            var _this = $(this),
                imgSrc = _this.attr("src"),
                enlarge_images = $("#enlarge_images"),
                top = $(document).scrollTop() + event.clientY + 10 + "px";
                left = $(document).scrollTop() + event.clientX + 10 + "px";
                enlarge_images.show();
                enlarge_images.html('<img width="400" height="400" src="' + imgSrc + '" />');
                enlarge_images.css({"top":top, "left":left});
        });
        //鼠标移出缩略图隐藏放大的图片
        $(".show-pic").mouseout(function(event) {
            var enlarge_images = $("#enlarge_images");
                enlarge_images.hide();
                enlarge_images.html('');
        });
        $(".show-pic").click(function(event) {
            window.open($(this).attr("src"));
        })
    };
    $(function(){
        Init();
    });
    return {
        productExamineList:productExamineList,
        attrColorManage:attrColorManage,
        addAttribute:addAttribute,
        brandManage:brandManage,
        brandAttributeList:brandAttributeList,
        productList:productList
    }
}();