var EdmSystem = function () {
    /**
    * 初始化函数
    */
    function Init() {
        // 全选
        Common.AllSelect($(".js-select-All"), $('.js-single-checkbox'));
        // 选中（取消）某个
        $('.js-single-checkbox').click(function () {
            if ($(this).is(':checked')) {
                var len = $('.js-single-checkbox').length,
                    checkedLen = $('.js-single-checkbox:checked').length;
                if (len == checkedLen) {
                    $(".js-select-All").prop('checked', true);
                }
            } else {
                $(".js-select-All").prop('checked', false);
            }
        });
    };

    /**
     * ajax 提交
     * @param data 数据
     * @param url 地址
     */
    function activityAjax(data,url){
        $.ajax({
            type: 'post',
            url: url,
            data: { data: data },
            success: function (data) {
                if (data['code'] == 200) {
                    layer.msg(data['msg'], { icon: 1 }, function () {
                        window.location.href = data['url'];
                    });
                } else {
                    layer.msg(data['msg'], {
                        icon: 2
                    });
                }
            }
        });
    }

    /**
     * 上传图片
     */
    function uploadImg(ele){
        layui.use('upload', function () {
            var upload = layui.upload;
            //执行实例
            var uploadInst = upload.render({
                elem:$(ele), //绑定元素
                
                url: '/activity/imgUpload', //上传接口
                
                done: function (res,index,upload) {
                    //上传完毕回调
                    if (res.code == 200) {
                        this.item.siblings('.upload-img-input').val(res.url);
                        layer.msg(res.msg, { icon: 1 });
                    } else {
                        layer.msg(res['msg'], { icon: 2 });
                    }
                },
                error: function () {
                    //请求异常回调
                    layer.msg('异常！', { icon: 2 });
                }
            });
        });
    }

    // 选中的checkbox
    function checkedId(obj, tips) {
        var checkedLen = $(obj + ':checked').length,
            arr = [];

        if (checkedLen > 0) {
            for (var i = 0; i < checkedLen; i++) {
                var id = $(obj + ':checked').eq(i).val();
                arr.push(id)
            }
            return arr;
        }
        layer.msg(tips);
        return false
    };

    // 预览图片
    function previewImg() {
        $('body').on('click','.js-preview-img',function(){
            var url = $(this).siblings('.upload-img-input').val().trim(),
                reg = /^([hH][tT]{2}[pP]:\/\/|[hH][tT]{2}[pP][sS]:\/\/)(([A-Za-z0-9-~]+).)+([A-Za-z0-9-~\/])+$/;
            if (!url) {
                return false;
            }else {
                if (!reg.test(url)) {
                    url = $('#js-image-domain').val() + url;
                }
                window.open(url);
            }
            
        });
        $("#previewBannerUrl,#previewBanner2Url").click(function(){
            var val = $(this).siblings('input').val().trim();
            if (val){
                window.open(val);
            }
        })
    }
    /**
     * ctreat by lijunfang 20190611
     * 活动列表页面
     */
    function activityIndex () {
        // 启用活动
        $('.js-enable-activity').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要启用的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:1
            };
            activityAjax(post_data,'/activity/operating');
        });
        // 禁用活动
        $('.js-disable-activity').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要禁用的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:2
            };
            activityAjax(post_data,'/activity/operating');
        });
        // 删除活动
        $('.js-del-activity').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要删除的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:3
            };
            layer.msg('确定删除吗？', {
                time: 0, //不自动关闭
                btn: ['确定', '取消'],
                yes: function(index){
                    layer.close(index);
                    activityAjax(post_data,'/activity/operating');
                }
            });
        });

        //查询
        $('.js-search-activity').click(function () {
            var ActivityTitle = $('input[name="ActivityTitle"]').val();
            window.location.href = '?title='+ActivityTitle;
        });

    };

    /**
     * ctreat by lijunfang 20190611
     * 新增活动模板页面
     */
    function saveTemplate() {
        //切换语种
        $('#ddlLang').change(function () {
            var v = $('#ddlLang option:selected').val();
            if (!v) {
                $('#btnShowTemplateBox').attr('disabled', true);
                $('#boxTemplateTips').show();
            } else {
                $('#btnShowTemplateBox').attr('disabled', false);
                $('#boxTemplateTips').hide();
            }
        });
        uploadImg('.js-upload-img-btn');//图片上传

        $('.btn-save-template').click(function () {
            var post_data = {
                id: $('input[name="activityTemplateID"]').val(),
                Title: $('input[name="Title"]').val(),
                Thumb: $('input[name="Banner"]').val(),
                OrderID: $('input[name="OrderID"]').val(),
                LangCode: $("#ddlLang").val(),
                TemplateType: $("#ddlTpType").val(),
                Content: $('#Text').val(),
                Status: $('input:radio[name="status-isabled"]:checked').val()
            };
            activityAjax(post_data,'/activity/saveTemplate');
        });
        previewImg();
    };

    /**
     * ctreat by lijunfang 20190611
     * 新增活动页面
     */
    function saveActivity (){
        //切换语种
        $('#ddlLang').change(function () {
           var that = $(this),
               _currVal = that.val(),
               btnShowTemplateBox = $('#btnShowTemplateBox'),
               formData = {
                   LangCode: _currVal
               }
            // 删除已选择的模板   
            $('#selected_tp_cntr').html('');
            $('#TemplateID').val('');

            if (!_currVal){
                btnShowTemplateBox.attr('disabled','disabled');
            }else{
                btnShowTemplateBox.removeAttr('disabled', 'disabled');
                $.ajax({
                    type: "POST",
                    url: "/activity/getTemplateList",
                    dataType: 'json',
                    data: formData,
                    success:function(data){
                        var _html = '';
                        $(data).each(function(index,ele){
                            _html += Common.StrFormat('<dl data-tp-lang="{lang}" data-oran-para=\'{json}\'><dt><img src="{src}"></dt><dd>{title}</dd></dl>',{
                                lang: _currVal,
                                json: ele.json,
                                src:ele.thumb,
                                title: ele.title
                            })
                           
                        })
                        $('#TB_ajaxContent').find('.js-img-cont').html(_html);
                    },
                    error:function(){
                        
                    }
                })
            }
        });
        // 添加主推
        var idIndex = 0;
        $('.banner_section .eliminating').click(function () {
            var _this = $(this),
                html = '',
                len = $(".default-item").length;
            if (len){
                idIndex = Number(_this.prev().attr('data-id')) + 1;
            } else {
                idIndex = 1;
            }
            html += '<li class="default-item" data-id="'+idIndex+'"><a class="del_section"></a>';
            html += '<p class="mb5"><textarea class="z-banner-text" style="width:351px;" placeholder="主文案"></textarea></p>';
            html += '<p class="mb5"><textarea class="f-banner-text" style="width:351px;" placeholder="辅文案"></textarea></p>';
            html += '<p class="mb5"><span class="oui_image_uploader"><input value="" type="text" maxlength="128" class="banner-url ltxt w250 mr5 upload-img-input" placeholder="上传Banner图片或者输入Banner图片地址">';
            html += '<button type="button" id="testUpload'+idIndex+'" class="layui-btn file-btn js-upload-img-btn mr5"><i class="layui-icon layui-icon-upload-drag"></i>上传图片</button><i class="glyphicon glyphicon-search f14 gray js-preview-img"></i></span>';
            html += '<div style="display:none;" class="oui_image_uploader_box "><p class="oui_image_uploader_close"><span></span></p>';
            html += '<p class="oui_image_uploader_box_file">上传文件：<input type="file"></p>';
            html += '<p class="oui_image_uploader_box_tips">允许上传jpg|png|gif，大小在1000KB以内。</p>';
            html += '<p class="oui_image_uploader_box_button"><input class="btn_hl" type="button" value="上传"></p>';
            html += '<p class="oui_image_uploader_box_message">正在上传，请稍候...</p></div></p>';
            html += '<p class="mb5"><input class="banner-spu" type="text" style="width:351px;" placeholder="SPU+(赠品)SPU" value=""></p></li>';

            _this.before(html);
            uploadImg('#testUpload'+idIndex);
        });

        // 删除主推
        $('.banner_section').on('click', '.del_section', function () {
            var _this = $(this);
            _this.parent().remove();
        });
        // 选择模板弹窗
        $('#btnShowTemplateBox').click(function () {
            $('#TB_overlay, #TB_window').show();
        });
        // 删除已选择的模板
        $('#btn_remove_main_tp').click(function () {
            $('#selected_tp_cntr').html('');
            $('#TemplateID').val('');
        });

        // 关闭选择模板弹窗
        $('#TB_overlay, #TB_closeAjaxWindow').click(function (event) {
            $('#TB_overlay, #TB_window').hide();
        });
        // 选择模板
        $('#TB_ajaxContent').on('click', 'dl', function () {
            var _this = $(this),
                oranPara = _this.attr('data-oran-para') ? JSON.parse(_this.attr('data-oran-para')):'',
                html = '<dl class="selected_task_tp_box"><dt><img src="' + oranPara.thumb + '"></dt><dd>' + oranPara.title + '</dd></dl>';

            $('#selected_tp_cntr').html(html);
            $('#TemplateID').val(oranPara.id);
            $('#TB_overlay, #TB_window').hide();
        });


        $('.btn-save').click(function () {
            var taskTitleEle = $('input[name="TaskTitle"]'),
                OtherSKUTextEle = $('#OtherSKUText'),
                PageTitleEle = $('#PageTitle'),
                UrlSnippetEle = $('#UrlSnippet'),
                EmailTitleEle = $('input[name="EmailTitle"]'),

                _taskTitle = taskTitleEle.val(), 
                _OtherSKUText = OtherSKUTextEle.val(),
                _PageTitle = PageTitleEle.val(),
                _UrlSnippet = UrlSnippetEle.val(),
                _EmailTitle = EmailTitleEle.val(),

                post_data = {
                    id: $('input[name="activityID"]').val(),
                    TaskTitle: _taskTitle,
                    EmailTitle: _EmailTitle,
                    Banner: $('input[name="Banner"]').val(),
                    BannerUrl: $('input[name="BannerUrl"]').val(),
                    Banner2nd: $('input[name="Banner2nd"]').val(),
                    Banner2Url: $('input[name="Banner2Url"]').val(),
                    SKUText: $('#SKUText').val(),
                    OtherSKUText: _OtherSKUText,
                    LangCode: $("#ddlLang").val(),
                    CurrencyCode: $("#CurrencyCode").val(),
                    PageTitle: _PageTitle,
                    UrlSnippet: _UrlSnippet,
                    GACode: $('input[name="GACode"]').val(),
                    Remark: $('#Remark').val(),
                    Status: $('input:radio[name="status-isabled"]:checked').val(),
                    TemplateID: $('input[name="TemplateID"]').val()
                };
            if (!_taskTitle.trim()){
                taskTitleEle.siblings('.error-tips').html('任务名称不能为空！');
                return false;
            }
            if (!/^\d{6,}_\w+_(en|es|pt|ru|fr|de|nl|cs|fi|it|sv|no|id|ja|ar)$/.test(_taskTitle.trim())){
                taskTitleEle.siblings('.error-tips').html('输入的格式不正确，请按照以下格式：20190617_ActivityName_en');
                return false;
            }
            taskTitleEle.siblings('.error-tips').html('');

            if (!_EmailTitle.trim()){
                EmailTitleEle.siblings('.error-tips').html('邮编标题不能为空！');
                return false;
            }
            EmailTitleEle.siblings('.error-tips').html('');

            if (!_OtherSKUText.trim()) {
                OtherSKUTextEle.siblings('.error-tips').html('spu不能为空！');
                return false;
            }
            OtherSKUTextEle.siblings('.error-tips').html('');

            if (!_PageTitle.trim()) {
                PageTitleEle.siblings('.error-tips').html('网页标题不能为空!');
                return false;
            }
            PageTitleEle.siblings('.error-tips').html('');

            if (!_UrlSnippet.trim()) {
                UrlSnippetEle.siblings('.error-tips').html('url地址不能为空！');
                return false;
            }
            UrlSnippetEle.siblings('.error-tips').html('');

            post_data.Banners = [];
            $('#banners_ul li').each(function(index,ele){
                var _ele = $(ele);
                if(!_ele.hasClass('eliminating')){
                    var _bannerObj = {
                        MainTitle: _ele.find('.z-banner-text').val(),
                        SubTitle: _ele.find('.f-banner-text').val(),
                        ImageUrl: _ele.find('.banner-url').val(),
                        SPUList: _ele.find('.banner-spu').val()
                    }
                    post_data.Banners.push(_bannerObj);
                }
            })
           
           activityAjax(post_data,'/activity/saveActivity');
        });

        uploadImg('.js-upload-img-btn');//图片上传

        // 预览图片
        previewImg()
    };
    /**
     * ctreat by lijunfang 20190611
     * 模板列表页面
     */
    function templateIndex () {
        // 启用模板
        $('.js-enable-template').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要启用的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:1
            };
            activityAjax(post_data,'/activity/templateOperating');
        });

        // 禁用模板
        $('.js-disable-template').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要禁用的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:2
            };
            activityAjax(post_data,'/activity/templateOperating');
        });
        // 删除模板
        $('.js-del-template').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要删除的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:3
            };
            layer.msg('确定删除吗？', {
                time: 0, //不自动关闭
                btn: ['确定', '取消'],
                yes: function(index){
                    layer.close(index);
                    activityAjax(post_data,'/activity/templateOperating');
                }
            });
        });
        // 模板列表页显示放大缩略图
        $('.view-img').click(function (event) {
            var url = $(this).attr('src');
            window.open(url, '_blank');
        });

        //查询
        $('.js-search-template').click(function () {
            var ActivityTitle = $('input[name="name"]').val();
            window.location.href = '?title='+ActivityTitle;
        });
    };
    /**
     * ctreat by lijunfang 20190613
     * 邮件任务列表新增页面
     */
    function saveTask () {
        //是否拆分js交互
        $('input[name="isSplit"]').click(function(){
            var that = $(this),
                jsLangDl = $('.js-lang-dl'),
                checkedVal = parseInt(that.val());
            //1表示启用，0表示不启用
            if (checkedVal === 1){
                jsLangDl.removeClass('hide');
            }else{
                jsLangDl.addClass('hide');
            }
        });

        $('.btn-email-save').click(function () {
            var taskTitleEle = $('input[name="TaskTitle"]'),
                MailBody = $('#MailBody'),
                Followers = $('#Followers'),
                _taskTitle = taskTitleEle.val(),
                _MailBody = MailBody.val(),
                _Followers = Followers.val(),
                _RecipientID=0;

                if(parseInt($('input[name="emailTaskID"]').val())>0)
                {
                    _RecipientID=$("#RecipientValveID").val()
                }
                else
                {
                    _RecipientID=$("#RecipientID").val();
                }

                post_data = {
                    id: $('input[name="emailTaskID"]').val(),
                    TaskTitle: _taskTitle,
                    StartTime: $('input[name="startTime"]').val(),
                    ActivityName: $('input[name="ActivityName"]').val(),
                    RecipientID: _RecipientID,
                    ImmediatelySend: $("#ImmediatelySend").is(':checked'),
                    IsActivity: $('input:radio[name="IsActivity"]:checked').val(),
                    LangCode: $("#ddlLang").val(),
                    SenderID: $("#SenderID").val(),
                    IsEnable: $('input:radio[name="isEnable"]:checked').val(),
                    MailSubject: $('input[name="MailSubject"]').val(),
                    MailBody: _MailBody,
                    Followers: _Followers
                };
            activityAjax(post_data,'/emailtask/saveTask');
        });
    };

    ///是否实时发送邮件
    function CheckSendChange()
    {
        if($("#ImmediatelySend").prop("checked")){
            $("#orderstarttime").val("");
            $("#orderstarttime").attr("disabled","disabled");
        }else{
            $("#orderstarttime").removeAttr("disabled")
        }
    }

    /**
     * ctreat by lijunfang 20190611
     * 邮件任务列表
     */
    function emailTaskIndex () {
        // 删除活动
        $('.js-del-task').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要删除的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr,
                type:3
            };
            layer.msg('确定删除吗？', {
                time: 0, //不自动关闭
                btn: ['确定', '取消'],
                yes: function(index){
                    layer.close(index);
                    activityAjax(post_data,'/Emailtask/operating');
                }
            });
        });

        //查询
        $('.js-search-emailtask').click(function () {
            var ActivityTitle = $('input[name="name"]').val();
            window.location.href = '?title='+ActivityTitle;
        });
    };

    /** 发送测试邮件 */
    function SentTestEmail($obj)
    {
        var date={taskID:$obj};
        activityAjax(date,"/Emailtask/BroadcastTrigger");
        $("#test-email-"+data)[0].innerText("已发送测试邮件");
        $("#test-email-"+data)[0].attr("style","color:#3300cc;");
    }

    /** 开启发送邮件 */
    function SentEmail($obj)
    {
        var date={taskID:$obj};
        activityAjax(date,"/Emailtask/SentEmail");
    }

    /**  重试创建收件人信息 */
    function RetryUploadSenderInfo($obj)
    {
        var date={recipientLineId:$obj};
        activityAjax(date,"/Emailtask/RetryRecipientLine");
    }

    /**
     * ctreat by lijunfang 20190611
     * 邮件任务列表
     */
    function recipientListIndex () {
        // 删除活动
        $('.js-del-task').click(function (event) {
            event.preventDefault();
            var arr = checkedId('.js-single-checkbox', '请选择要删除的记录');
            if (!arr) {
                return false
            }
            var post_data = {
                id: arr
            };
            layer.msg('确定删除吗？', {
                time: 0, //不自动关闭
                btn: ['确定', '取消'],
                yes: function(index){
                    layer.close(index);
                    activityAjax(post_data,'/Emailtask/recipientDel');
                }
            });
        });

        //查询
        $('.js-search-recipientTitle').click(function () {
            var ActivityTitle = $('input[name="name"]').val();
            window.location.href = '?title='+ActivityTitle;
        });
    };

    $(function () {
        Init();
    });
    return {
        saveTemplate:saveTemplate,
        saveActivity:saveActivity,
        templateIndex:templateIndex,
        activityIndex:activityIndex,
        emailTaskIndex:emailTaskIndex,
        recipientListIndex:recipientListIndex,
        saveTask:saveTask,//保存邮件任务信息
        SentTestEmail:SentTestEmail,//发送测试邮件
        SentEmail:SentEmail,//发送正式邮件
        RetryUploadSenderInfo:RetryUploadSenderInfo,//重试创建收件人列表
        CheckSendChange:CheckSendChange,//是否实时发送邮件
    }
}();