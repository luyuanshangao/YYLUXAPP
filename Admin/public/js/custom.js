// (function($) {})
	//三级联动第一级
	$("#first").change(function () {
	   var catalogId = $("#first").val();//console.log(catalogId);
	   $.get("catalog/select/1/id/"+catalogId, function(result){
			$("#second").remove();
			$("#third").remove();
			$("#fourth").remove();
			$("#fifth").remove();
			$("#first").after(result);
		});
	});
	//三级联动第二级$("#second").live('change',function(){  $('#mainmenu').on('click', ‘a’, function)
	$(document).on('change', '#second', function(event) {
            var catalogId = $("#second").val();
       $.get("catalog/select/2/id/"+catalogId, function(result){
             if(result != 100){
                $("#third").remove();
                $("#fourth").remove();
                $("#fifth").remove();
                $("#second").after(result);
             }else if(result == 100){
                $("#third").remove();
                $("#fourth").remove();
                $("#fifth").remove();
             }
        });
  });
//console.log(catalogId);
    //三级联动第三级
   $(document).on('change', '#third', function(event) {
       var catalogId = $("#third").val();//console.log(catalogId);
       $.get("catalog/select/3/id/"+catalogId, function(result){
             // var data = eval("(" + result+ ")");
             if(result != 100){

                $("#fourth").remove();
                $("#fifth").remove();
                $("#third").after(result);
             }else if(result == 100){

                $("#fourth").remove();
                $("#fifth").remove();
             }
        });
    });
    //三级联动第四级
    $(document).on('change', '#fourth', function(event) {
       var catalogId = $("#fourth").val();//console.log(catalogId);
       $.get("catalog/select/4/id/"+catalogId, function(result){
             // var data = eval("(" + result+ ")");
             if(result != 100){
                $("#fifth").remove();
                $("#fourth").after(result);
             }else if(result == 100){
                $("#fifth").remove();
             }
        });
    });

     //异步追加使用这种形式
	// $("#input-color").live('change',function(){
  $(document).on('change', '#input-color', function(event) {
		var name = $("#input-color").val();//console.log(catalogId);
        if(name == '' || name == null){
	        $(".attribute_name").remove();
	    	if(!$("#attribute_name").hasClass('attribute_name')){
	    		$("#input-color").after('<span class ="attribute_name" style="color: red;">中文名不能为空</span>');
	    	}
        }else{
        	$(".attribute_name").remove();
        }
        // console.log(name);
    });

 //    $("#input-color").mouseleave(function(){ console.log(11);
	//   // $("p").css("background-color","#E9E9E4");
	// });
    //触发颜色选框的显示与隐藏
    $('#input-color').bind('input propertychange', function() {
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
                      $("#color_view .color-wrap").html("");
                      $.each(data,function(n,val){
                        var res = data[n];
                        html += '<span><input {$list_find[\'input\']['+ res.id +']?\'checked="checked"\':\'\'} class="color_name_'+ res.id +' color_name_first_'+ res.id +' color-selection-box" data-id="'+ res.id +'"  type="checkbox" name="color" data-color = "'+ res.title_cn +'" data-color-en = "'+ res.title_en +'" value="'+ res.color_value +'"><span style="background: '+ res.color_value +';" class="span_coler" ></span></span>';
                      })
                      $("#color_view .color-wrap").html(html);
                      $("#color_view").css("display","block");
                      $(".delect_dl").remove();
                    }else{
                        layer.msg(result.result, {icon: 2});
                    }
                },error : function() {
                    layer.msg('异常！', {icon: 2});
                }
            });

 // console.log(name);
         }else{
         	$("#color_view").css("display","none");
	         	 if(!$(".delect").is('.delect')){
	         	     $(".delect_dl").remove();
		         	 if(!$(".add-attribute1").is('.add-attribute1')){
		         	 	$(".hidden_name").after('<dl class="c-h-dl-validator form-group clearfix add-attribute1 delect_dl"><dd class="v-title"><label><em>*</em>中文描述：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="where[1][title_cn]" class="form-control input-medium input-1" type="text"></div> 英文名： <div style="" class="input-icon right inline-block "><!-- <i class="fa"></i> --><input style="" name="where[1][title_en]" class="form-control " type="text"></div> 选项值： <div style="" class="input-icon right inline-block "><!-- <i class="fa"></i> --><input style="" name="where[1][value]" class="form-control input-val-1" type="text"></div> 排序：<div style="" class="input-icon right inline-block inline_block"><input name="where[1][sort]" class="form-control input-val-1 w100" type="text"></div><a class="delect" style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;" onclick="add_attribute(1)" href="javascript:;">添加新项</a></dd><dt></dt>');
		         	 }
	         	}else{
	         		if(!$(".add-attribute1").is('.add-attribute1')){
	         	 	  $(".hidden_name").after('<dl class="c-h-dl-validator form-group clearfix add-attribute1 delect_dl"><dd class="v-title"><label><em>*</em>中文描述：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="where[1][title_cn]" class="form-control input-medium input-1" type="text"></div> 英文名： <div style="" class="input-icon right inline-block"><!-- <i class="fa"></i> --><input style="" name="where[1][title_en]" class="form-control" type="text"></div> 选项值： <div style="" class="input-icon right inline-block"><!-- <i class="fa"></i> --><input style="" name="where[1][value]" class="form-control input-val-1" type="text"></div> 排序：<div style="" class="input-icon right inline-block inline_block"><input name="where[1][sort]" class="form-control input-val-1 w100" type="text"></div><a class="delect" style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;" onclick="add_attribute(1)" href="javascript:;">添加新项</a></dd><dt></dt>');
	         	 }
	         	}


         }
    });



    $("#input-color-en").change(function () {
	   var name_en = $("#input-color-en").val();
        if(name_en == '' || name_en == null){
	        $(".attribute_name_en").remove();
	    	if(!$("#attribute_name_en").hasClass('attribute_name_en')){
	    		$("#input-color-en").after('<span class ="attribute_name_en" style="color: red;">英文名不能为空</span>');
	    	}
        }else{
        	$(".attribute_name_en").remove();
        }
         // console.log(name_en);
	});

   //获取图片并显示
   $('#chooseImage').on('change',function(){
            var files = event.target.files, file;
            if (files && files.length > 0) {
                // 获取目前上传的文件
                file = files[0];// 文件大小校验的动作

                var filePath = $(this).val();        //获取到input的value，里面是文件的路径
                fileFormat = filePath.substring(filePath.lastIndexOf(".")).toLowerCase();
                if(file.size > 1024 * 1024 * 2) {
                    error_prompt_alert('图片大小不能超过 2MB!');
                    return false;
                }else if(!fileFormat.match(/.png|.jpg|.jpeg/)){
                    error_prompt_alert('上传错误,文件格式必须为：png/jpg/jpeg');
                    return;
                }
                // 获取 window 的 URL 工具
                var URL = window.URL || window.webkitURL;
                // 通过 file 生成目标 url
                var imgURL = URL.createObjectURL(file);
                //用attr将img的src属性改成获得的url
                $('#cropedBigImg').attr('src',imgURL);
                $('#cropedBigImg').show();
                // 使用下面这句可以在内存中释放对此 url 的伺服，跑了之后那个 URL 就无效了
                // URL.revokeObjectURL(imgURL);
           }
     });

    /*
    三级递归
    */
    $("#first_level").change(function () {
       var catalogId = $("#first_level").val();//console.log(catalogId);
        if(catalogId !='' && catalogId !=0){
            $.get("/ProductManagement/catalog_next/class_level/0/id/"+catalogId, function(result){
                $("#first_level").after(result);
            });
        }
        $("#second_level").remove();
        $("#third_level").remove();
        $("#fourth_level").remove();
        $("#fifth_level").remove();
    });

    // $("#second_level").live('change',function(){
    $(document).on('change', '#second_level', function(event) {
       var catalogId = $("#second_level").val();//console.log(catalogId);
        if(catalogId !='' && catalogId !=0){
            $.get("/ProductManagement/catalog_next/class_level/1/id/"+catalogId, function(result){
                $("#third_level").remove();
                $("#fourth_level").remove();
                $("#fifth_level").remove();
                $("#second_level").after(result);
            });
        }else{
            $("#third_level").remove();
            $("#fourth_level").remove();
            $("#fifth_level").remove();
        }
    });
     // $("#third_level").live('change',function(){
    $(document).on('change', '#third_level', function(event) {
        var catalogId = $("#third_level").val();//console.log(catalogId);
        if(catalogId !='' && catalogId !=0){
            $.get("/ProductManagement/catalog_next/class_level/2/id/"+catalogId, function(result){
                $("#fourth_level").remove();
                $("#fifth_level").remove();
                $("#third_level").after(result);
            });
        }else{
            $("#fourth_level").remove();
            $("#fifth_level").remove();
        }
    });

     /*
    三级递归_mongo表
    */

    $("#first_level_mongo").change(function () {
    // $(document).on('change', '#first_level_mongo', function(event) {
       var catalogId = $("#first_level_mongo").val();
       var class_url = $("#class_url").val();//console.log(class_url);return;
       if(class_url){
          $.get(class_url+"/class_level/0/id/"+catalogId, function(result){
              $("#second_level_mongo").remove();
              $("#third_level_mongo").remove();
              $("#fourth_level_mongo").remove();
              $("#fifth_level_mongo").remove();
              $("#first_level_mongo").after(result);
          });
       }else{
          $.get("catalog_next/class_level/0/id/"+catalogId, function(result){
              $("#second_level_mongo").remove();
              $("#third_level_mongo").remove();
              $("#fourth_level_mongo").remove();
              $("#fifth_level_mongo").remove();
              $("#first_level_mongo").after(result);
          });
       }

    });

    // $("#second_level").live('change',function(){
    $(document).on('change', '#second_level_mongo', function(event) {
       var catalogId = $("#second_level_mongo").val();
       var class_url = $("#class_url").val();//控制器及方法
       // console.log(class_url);return;
       if(class_url){
           $.get(class_url+"/class_level/1/id/"+catalogId, function(result){
              $("#third_level_mongo").remove();
              $("#fourth_level_mongo").remove();
              $("#fifth_level_mongo").remove();
              $("#second_level_mongo").after(result);
           });
       }else{
           $.get("catalog_next/class_level/1/id/"+catalogId, function(result){
              $("#third_level_mongo").remove();
              $("#fourth_level_mongo").remove();
              $("#fifth_level_mongo").remove();
              $("#second_level_mongo").after(result);
           });
       }

    });
     // $("#third_level").live('change',function(){
    $(document).on('change', '#third_level_mongo', function(event) {
       var catalogId = $("#third_level_mongo").val();//console.log(catalogId);
       var class_url = $("#class_url").val();//控制器及方法
       if(class_url){
          $.get(class_url+"/class_level/2/id/"+catalogId, function(result){
            $("#fourth_level_mongo").remove();
            $("#fifth_level_mongo").remove();
            $("#third_level_mongo").after(result);
          });
       }else{
          $.get("catalog_next/class_level/2/id/"+catalogId, function(result){
            $("#fourth_level_mongo").remove();
            $("#fifth_level_mongo").remove();
            $("#third_level_mongo").after(result);
          });
       }

    });

 //导航栏
 function navigation(e){
 	$.ajax({
        type:"POST",
          url:"/index/header",
          data: { id: e},
        dataType:"json",
        cache:false,
        success:function(msg){
          if(msg.code == 200){
          	 $(".open").removeClass('open');
             $(".open"+e).addClass('open');
                $(".page-sidebar-menu").html(msg.result);
          }else{
          }
        },
        error:function(error){}
    });
}
  //添加属性值
 function attribute(){
 	   $.get('/ProductManagement/add_eidt.html', function (data) {
                layer.open({
                    title: "添加属性",
                    content: data,
                    type: 1,
                    area: ['680px', '500px'],
                    offset: '10px',
                    btn: ["保存", "取消"],
                    yes: function (index) {
                        $('#addUserForm').submit();
                    },
                    cancel: function () {

                    }
                });
            });
      // console.log(2);
  }
  //上传属性值特效
  function add_delect(e){

  	 if(!$(".added"+e).hasClass('added'+e)){
  	 	     $(".add-attribute"+e).remove();
    	 }else{
    	 	 var a = e-1;
    	 	 if($(".eliminate"+a).hasClass('eliminate'+a)){
                  $(".eliminate"+a).before('<a class="eliminate-btn2 add-attr-btn delect added'+a+'" data-total="'+a+'" style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;"  href="javascript:;">添加新项</a>');
    	 	      $(".add-attribute"+e).remove();
    	 	 }else{
                var b = existence(a-1);
                if(b == 1){
                	//剩下一个的情况下
                	$(".inline_block").after('<a class="eliminate-btn2 add-attr-btn delect " style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;"  href="javascript:;">添加新项</a>');
                	$(".add-attribute"+e).remove();
                }else{
                	$(".eliminate"+b).before('<a class="eliminate-btn2 add-attr-btn delect added'+b+'"  data-total="'+b+'" style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;"  href="javascript:;">添加新项</a>');
                	$(".add-attribute"+e).remove();

                }

    	 	 }

    	 }

  }

      //公用按钮添加删除
      function public_add_delect(e,d){

         if(!$(".added"+e).hasClass('added'+e)){
                 $(".add-attribute"+e).remove();
             }else{
                 var a = e-1;
                 if($(".eliminate"+a).hasClass('eliminate'+a)){
                      $(".eliminate"+a).before('<a class="delect added'+a+'" style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;" onclick="'+d+'('+a+')" href="javascript:;">添加新项</a>');
                      $(".add-attribute"+e).remove();
                 }else{
                    var b = existence(a-1);
                    if(b == 1){
                        //剩下一个的情况下
                        $(".inline_block").after('<a class="delect " style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;" onclick="'+d+'('+b+')" href="javascript:;">添加新项</a>');
                        $(".add-attribute"+e).remove();
                    }else{
                        $(".eliminate"+b).before('<a class="delect added'+b+'" style="background: #35b9f7;margin-left: 10px;padding-left: 10px;padding-right: 10px;padding-top: 6px;padding-bottom: 6px;" onclick="'+d+'('+b+')" href="javascript:;">添加新项</a>');
                        $(".add-attribute"+e).remove();
                    }
                 }
             }
      }

  //遍历判断标签是否存在
  function existence(e){
       if($(".eliminate"+e).hasClass('eliminate'+e)){
          return e;
       }else{
	       	if(e == 0 || e == 1){
	          return 1;
	       	}else{
             return existence(e-1);
	       	}
       }
  }

   function color_name(e){
              var name = $(".color_name_"+e).attr("data-color");
              var name_en = $(".color_name_"+e).attr("data-color-en");
              var val = $(".color_name_"+e).val();
              var formControl = $(".behind").find(".form-control");

              if($(".color_name_"+e).prop("checked") == true){
                    if(name && val){
                         var isHas = false;
                         for(var j=0;j<formControl.length;j++){
                          if(formControl.eq(j).val() == name_en){
                              isHas = true;
                              return;
                          }
                        }
                        if(!isHas){
                          $(".hidden_name").after('<dl class="c-h-dl-validator form-group clearfix add-attribute'+e+' delect_dl"><dd class="v-title"><label><em>*</em>中文名：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="where['+e+'][title_cn]" class="form-control input-medium" type="text"  value="'+name+'"></div>  英文名： <div style="" class="input-icon right inline-block"><input style="" name="where['+e+'][title_en]" class="form-control " type="text" value="'+name_en+'"></div>   选项值： <div style="" class="input-icon right inline-block"><input style="" name="where['+e+'][value]" class="form-control " type="text" value="'+val+'"></div> 排序：<div style="" class="input-icon right inline-block inline_block"><input name="where['+e+'][sort]" class="form-control input-val-1 w100" type="text" value=""></div></dd><dt></dt></dl>');
                        }
                     }
              }else{
                for(var i=0;i<formControl.length;i++){
                    if(formControl.eq(i).val() == name_en){
                        formControl.eq(i).parents(".form-group").remove();
                    }
                }
              }
   }
   //删除单个销售属性
   function del_attribute(e){
        // console.log(e);class-id
        //var id  = e;
        //var classId =  $(".attribute"+e).attr("class-id");
        $.ajax({
        type:"POST",
        url:"/ProductManagement/del_attribute",
        data:{id:e},
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
     //公用的ID删除
     function public_delete(e,url){
            layer.msg('你确定要删除么？', {
                offset: '150px',
              time: 0 //不自动关闭
              ,btn: ['确定', '取消']
              ,yes: function(index){
                layer.close(index);

             $.ajax({
                type:"POST",
                url:url,
                data:{id:e},
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
   }


function data_download(id,url){
    layer.msg('确定下载？', {
        time: 0 //不自动关闭
        ,btn: ['确定', '取消']
        ,yes: function(index){
            layer.close(index);
            $.ajax({
                type:"POST",
                url:url,
                data:{id:id},
                dataType:"json",
                cache:false,
                success:function(msg){
                    if(msg.code != 200){
                        layer.msg(msg.result, {icon: 2});
                    }
                },
                error:function(error){}
            });

        }
    });
}

function delete_datafeed(e,url){
    layer.msg('你确定要删除么？', {
        offset: '150px',
        time: 0 //不自动关闭
        ,btn: ['确定', '取消']
        ,yes: function(index){
            layer.close(index);

            $.ajax({
                type:"POST",
                url:url,
                data:{id:e},
                dataType:"json",
                cache:false,
                success:function(msg){
                    if(msg.code == 200){
                        layer.msg(msg.result, {icon: 1});
                        setTimeout(function(){
                            window.location.href = '/DataQuery/DataFeed'
                        },1500);
                    }else{
                        layer.msg(msg.result, {icon: 2});
                    }
                },
                error:function(error){}
            });

        }
    });
}

     /*
      * 添加配置
      */
     function add_config(){
           $.get('/SystemManage/add_config', function (data) {
                layer.open({
                    title: "添加配置",
                    content: data,
                    type: 1,
                    area: ['680px', '600px'],
                    offset: '10px',
                    btn: ["保存", "取消"],
                    yes: function (index) {
                            var formData = new FormData($( "#addUserForm" )[0]);
                             // console.log(formData);
                            $.ajax({
                                type:"POST",
                                url:"/SystemManage/add_config",
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
          // console.log(2);
      }

      //添加物流
   function edit_public(e,lengthS,wideS){
       var _length = lengthS ? lengthS : '680px',
            _width = wideS ? wideS :'600px';
            $.get(e, function (data) {
              layer.open({
                  title: "编辑",
                  content: data,
                  type: 1,
                  area: [_length,_width],
                  offset: '10px',
                  btn: ["保存", "取消"],
                  yes: function (index) {

                          var formData = new FormData($( "#addUserForm" )[0]);//:nth-child(3)
                          $.ajax({
                              type:"POST",
                              url:e,
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
        // console.log(2);
    }

     //状态修改
      function  EditStatus(id,url){
        layer.msg('你确定要做此操作么？', {
              time: 0 //不自动关闭
              ,btn: ['确定', '取消']
              ,yes: function(index){
                layer.close(index);
                $.ajax({
                  type:"POST",
                  url:url,
                  data:{id:id},
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
              }
            });
      }
       //会员管理状态修改
    function UserStatus(e,Status,url){
         if(Status == 0 || Status == 20){
                if(Status == 0){
                   var val = '你确定要激活么？';
                }else if(Status == 20){
                   var val = '你确定要启用么？';
                }
                layer.msg('"'+val+'"', {
                  time: 0 //不自动关闭
                  ,btn: ['确定', '取消']
                  ,yes: function(index){
                     layer.close(index);
                     $.ajax({
                        type:"POST",
                        url:url,
                        data:{ID:e,Status:Status},
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
                        error:function(error){layer.msg('提交出错', {icon: 1});}
                    });
                  }
                });
         }else{
                 var url ="'"+url+"'";
                layer.open({
                title: '禁用理由',
                type: 1,
                skin: 'layui-layer-rim', //加上边框<form id="examine_submit"  method="post">
                area: ['420px', '340px'], //宽高
                content: '<div style="margin-left: 30px;"><form id="examine_submit"  method="post"><input type="hidden" value="'+e+'" name="ID"><input type="hidden" value="'+Status+'"  name="Status"><div style="margin-top: 10px;"><label style="top: -76px;position: relative;">理由：</label><textarea class="Remarks" name="Remarks" cols="37" rows="9"></textarea></div></form><a href="javascript:;" onclick = "Status_submit('+url+')" class = "submit" style="padding: 8px 18px; background: #b2b2b2;font-size: 14px;float: right;margin-right: 71px;">提交</a></div>'
                });
         }

    }
 function Status_submit(url){
      var formData = new FormData($( "#examine_submit" )[0]);
          $.ajax({
              type:"POST",
              url:url,
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
     }


 /*
  * 添加VAT
  */
 function add_vat(){
       $.get('/SystemManage/add_vat', function (data) {
            layer.open({
                title: "添加VAT配置",
                content: data,
                type: 1,
                area: ['480px', '400px'],
                offset: '10px',
                btn: ["保存", "取消"],
                yes: function (index) {
                        var formData = new FormData($( "#addVatForm" )[0]);
                         // console.log(formData);
                        $.ajax({
                            type:"POST",
                            url:"/SystemManage/add_vat",
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
      // console.log(2);
  }


//开始时间
function startingTime(obj) {
  WdatePicker({
    readOnly:true,
    maxDate:$(obj).siblings('.endTime').val() || '%y-%M-%d %H:%m:%s',
    dateFmt:'yyyy-MM-dd HH:mm:ss'
  })
}
//结束时间
function endingTime(obj) {
  WdatePicker({
    readOnly:true,
    minDate:$(obj).siblings('.startTime').val(),
    maxDate:'%y-%M-%d %H:%m:%s',
    dateFmt:'yyyy-MM-dd HH:mm:ss'
  })
}

$(".startTime").click(function(){
  var _this = $(this);
  startingTime(_this);
})

$(".endTime").click(function(){
  var _this = $(this);
  endingTime(_this);
})




var Timeout;
$(".exceed").on({
    mouseenter: function(event) {
        clearTimeout(Timeout);
        var _this = $(this),
            txt = _this.text(),
            x = _this.offset().left,
            y = _this.offset().top - $(document).scrollTop() + 30;
            e = event || window.event;
            // __xx = e.pageX || e.clientX + $(document).scrollLeft();
            // __yy = e.pageY || e.clientY + $(document).scrollTop();
            // console.log($(document).scrollTop())
        $(".show-copy-pop").html(txt).css({"left":x, "top":y, "margin-left":"100px"}).removeClass("hide");
    },
    mouseout: function(event) {
        Timeout = setTimeout(function(){
            $(".show-copy-pop").addClass('hide')
        },300);
    }
});

$(".show-copy-pop").on({
    mouseenter: function(event) {
        clearTimeout(Timeout);
        $(".show-copy-pop").removeClass('hide')
    },
    mouseout: function(event) {
        Timeout = setTimeout(function(){
          $(".show-copy-pop").addClass('hide')
        },10)
    }
});
