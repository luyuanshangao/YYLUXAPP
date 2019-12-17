/**
 * 公共操作JS
 * @author lijunfang
 * @date 2018-03-21
 */
window.Global = {
    /**
     * 去除空格
     */
    trim:function(str){
        return str.replace(/(^\s*)|(\s*$)/g, "");
    },

    /**
     * 判断对象是否为空
     */
    isEmpty:function(str){
        if(str=="" || str==null || str=="undefined"){
            return true;
        }
        return false;
    },
    /**
     * 
     * @param  tipBoxId 需要所展示的提示框
     * @param  {[type]：true,false} okBox正确提示框展示的图标
     * @param  {[type]：string} text:错误提示框提示的内容
     */
    show_err_tip:function(tipBoxId,okBox,text){
        var tipBox = $(tipBoxId);
        if(tipBox.hasClass('hide')){
            tipBox.removeClass('hide');
        }
        if(okBox){
            if(text){
                tipBox.html('<i class="iconfontmy icon-iconcorrect ok-sign-tip green"></i><span class="green">'+text+'</span>');
                tipBox.addClass('ok');
            }else{
                tipBox.html('<i class="iconfontmy icon-iconcorrect ok-sign-tip green"></i>');
                tipBox.addClass('ok');
            }
            
        }else{
            tipBox.html('<i class="iconfontmy icon-cuowu orange mr5 tmiddle"></i><span class="orange">'+text+'</span>');
            tipBox.removeClass('ok');
        }
    },
    /**
     * 显示和隐藏元素
     * Pattaya.Mall.showAndHide
     * @param  {[type] dom} ele  [需要隐藏和显示元素]
     * @param  {[type] boolean} show [ture:表示显示元素]
     */
    showAndHide:function(ele,show){
        var _ele = $(ele);
        if(show && typeof show !== "boolean"){
            return;
        }
        if(show){
            _ele.removeClass('hide');
        }else{
            _ele.addClass('hide');
        }
    },
    /**
     * 隱藏提示
     */
    hide_err_tip:function(tipBoxId){
        $(tipBoxId).addClass('hide');
    },

    /**
     * 电话号码验证
     * @param phone
     * @returns {boolean}
     */
    isPhoneNum:function(phone){
        var reg = /^1[23456789]\d{9}$/;
        var r = phone.match(reg);
        if(r==null){
            return false;
        }
        return true;
    },

    /**
     * 邮箱验证
     * @param email
     * @returns {boolean}
     */
    isEmail:function(email){
        var reg = /^([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/;
        var r = email.match(reg);
        if(r==null){
            return false;
        }
        return true;
    },
    /**
     * 密码强弱验证
     * @param  input框
     * @return Level 1,2,3分别代表弱、中、强
     */
    getPwdLevel:function(id){
        var passW = $(id), 
            passVal = passW.val(),
            Level,
            arr = [];
        if(passVal.length < 6){
            return 0;
        }
        if(/\d/.test(passVal)){
            arr.push(1);
        }
        if(/[a-zA-Z]/.test(passVal)){
            arr.push(2);
        }
        if(/[^A-Za-z0-9]/.test(passVal)){
            arr.push(3);
        }
        Level = arr.length;
        return Level;  
    },
    /**
     * 重复密码判断
     */
    getPwdRepeat:function(pwdEle,pwdRepeatEle){
        var pass  = $(pwdEle),
            passRepeat = $(pwdRepeatEle);
        if(pwdRepeatEle.val() != pass.val()){
            return false;
        }
        return true;
    },
    /**
     * 匹配中文
     * @return {Boolean} [description]
     */
    isCNchart:function(id){
        if(/[\u4e00-\u9fa5]/.test($(id).val())){
            return false;
        }
        return true;
    },
    /**
     * 设置字符串为空
     */
    setEmpty:function(str){
        if(str=="" || str==null || str=="undefined" || str=="null"){
            return "";
        }else{
            return str;
        }
    },
    /**
     * 只能是英文、数字、-
     */
    isEnName:function(str){
        if (/^(\d|[a-zA-Z]|\-|^\s+|\s+$|\s+)+$/.test(str)) {
            return true;
        }
        return false;
    },
    /**
     * ajax请求
     */
    ajax:function(c_type,c_url,c_data,callback){
        $.ajax({
            type:c_type,
            url:c_url,
            data:c_data,
            success:function(data){
                callback(data);
                /*if(data['code']=="0"){
                    callback(data);
                }else{
                    Common.tipsBox(data['msg']);
                }*/
            }
        });
    },

    /**
     * 获取表单json对象
     */
    getFormJson:function(form_id){
        var param = $("#"+form_id).serialize();
        var param_arr = param.split('&');
        var len = param_arr.length;
        var json = {};
        $.each(param_arr,function(i,val){
            var arr = val.split('=');
            var key = arr[0];
            var value = arr[1];
            json[key] = value;
        });
        return json;
    },

    /**
     * 判断来源是否是PC端
     * @returns {Boolean}
     */
    isPC:function() {
        var sUserAgent = navigator.userAgent.toLowerCase();
        // var bIsIpad = sUserAgent.match(/ipad/i) == "ipad";
        var bIsIphoneOs = sUserAgent.match(/iphone os/i) == "iphone os";
        var bIsMidp = sUserAgent.match(/midp/i) == "midp";
        var bIsUc7 = sUserAgent.match(/rv:1.2.3.4/i) == "rv:1.2.3.4";
        var bIsUc = sUserAgent.match(/ucweb/i) == "ucweb";
        var bIsAndroid = sUserAgent.match(/android/i) == "android";
        var bIsCE = sUserAgent.match(/windows ce/i) == "windows ce";
        var bIsWM = sUserAgent.match(/windows mobile/i) == "windows mobile";
        // if (!(bIsIpad || bIsIphoneOs || bIsMidp || bIsUc7 || bIsUc || bIsAndroid || bIsCE || bIsWM) ){
        //     return true;
        // }else{
        //     return false;
        // }
        if (!(bIsIphoneOs || bIsMidp || bIsUc7 || bIsUc || bIsAndroid
                || bIsCE || bIsWM)) {
            return true;
        } else {
            return false;
        }
    },

    /**
     * 判断是安卓还是IOS
     */
    isAndroidOrIOS:function(){
        var u = navigator.userAgent, app = navigator.appVersion;
        var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Linux') > -1; //android终端或者uc浏览器
        var isiOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/); //ios终端
        if(isAndroid){
            return 1;
        }else if(isiOS){
            return 2;
        }else{
            return 0;
        }
    },

    /**
     * 判断来源是否来至微信
     * @returns {Boolean}
     */
    isWeixin:function() {
        var ua = navigator.userAgent.toLowerCase();
        if (ua.match(/MicroMessenger/i) == "micromessenger") {
            return true;
        } else {
            return false;
        }
    },

    /**
     * 获取当前时间
     */
    getNowFormatDate:function () {
        var date = new Date();
        var seperator1 = "-";
        var seperator2 = ":";
        var year = date.getFullYear();
        var month = date.getMonth() + 1;
        var strDate = date.getDate();
        if (month >= 1 && month <= 9) {
            month = "0" + month;
        }
        if (strDate >= 0 && strDate <= 9) {
            strDate = "0" + strDate;
        }
        var currentdate = year + seperator1 + month + seperator1 + strDate
            + " " + date.getHours() + seperator2 + date.getMinutes()
            + seperator2 + date.getSeconds();
        return currentdate;
    },

    /**
     * 获取当前日期【格式为2017-03-15 00:00:00】
     */
    getNowFormatDay:function () {
        var date = new Date();
        var seperator1 = "-";
        var year = date.getFullYear();
        var month = date.getMonth() + 1;
        var strDate = date.getDate();
        if (month >= 1 && month <= 9) {
            month = "0" + month;
        }
        if (strDate >= 0 && strDate <= 9) {
            strDate = "0" + strDate;
        }
        var currentdate = year + seperator1 + month + seperator1 + strDate
            + " 00:00:00";
        return currentdate;
    },

    /**
     * 身份证号验证
     */
    isCardID:function(sId){
        var aCity={11:"北京",12:"天津",13:"河北",14:"山西",15:"内蒙古",21:"辽宁",22:"吉林",23:"黑龙江",31:"上海",32:"江苏",33:"浙江",34:"安徽",35:"福建",36:"江西",37:"山东",41:"河南",42:"湖北",43:"湖南",44:"广东",45:"广西",46:"海南",50:"重庆",51:"四川",52:"贵州",53:"云南",54:"西藏",61:"陕西",62:"甘肃",63:"青海",64:"宁夏",65:"新疆",71:"台湾",81:"香港",82:"澳门",91:"国外"};
        var iSum=0 ;
        var info="" ;
        if(!/^\d{17}(\d|x)$/i.test(sId)) return "你输入的身份证长度或格式错误";
        sId=sId.replace(/x$/i,"a");
        if(aCity[parseInt(sId.substr(0,2))]==null) return "你的身份证地区非法";
        sBirthday=sId.substr(6,4)+"-"+Number(sId.substr(10,2))+"-"+Number(sId.substr(12,2));
        var d=new Date(sBirthday.replace(/-/g,"/")) ;
        if(sBirthday!=(d.getFullYear()+"-"+ (d.getMonth()+1) + "-" + d.getDate()))return "身份证上的出生日期非法";
        for(var i = 17;i>=0;i --) iSum += (Math.pow(2,i) % 11) * parseInt(sId.charAt(17 - i),11) ;
        if(iSum%11!=1) return "你输入的身份证号非法";
        return "success";
    },

    /**
     * 比较日期时间大小
     */
    checkEndTime:function(startTime,endTime){
        var start = new Date(Date.parse(startTime.replace(/-/g,"/"))).getTime();
        var end = new Date(Date.parse(endTime.replace(/-/g,"/"))).getTime();
        if(end<start){
            return false;
        }
        return true;
    },

    /**
     * 提示框
     */
    tipsBox:function(tips,callback){
        //layer.msg('用户名字段能不能为空',{time: 2000,offset: ['0%'],area: ['100%']});
        /*layer.msg(tips,{icon:5,shade:[0.5]},function(){*/
        layer.msg(tips,{offset: ['0%'],area: ['100%']},function(){
            if(callback){
                callback();
            }
        });
    },

    /**
     * 重新加载页面
     */
    reloadPage:function(){
        window.location.reload();
    },

    //是否是正整数
    isPInt:function(str) {
        var g = /^(0|[1-9]\d*)$/;
        return g.test(str);
    },

    //是否是整数
    isInt:function(str){
        var g=/^-?\d+$/;
        return g.test(str);
    },

    /**
     * 非负小数判断
     */
    isPlusFloat:function (num){
        var test_reg = /(^\d+$)|(^\d+\.\d+$)/;
        if (Common.isEmpty (num) || !test_reg.test(num)){
            return false;
        }
        return true;
    },

    /**
     * 判断是安卓还是IOS
     */
    isAndroidOrIOS:function(){
        var u = navigator.userAgent, app = navigator.appVersion;
        var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Linux') > -1; //android终端或者uc浏览器
        var isiOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/); //ios终端
        if(isAndroid){
            return 1;
        }else if(isiOS){
            return 2;
        }else{
            return 0;
        }
    },

    /**
     * 地址跳转
     * @param url：跳转URL
     * @param type：跳转类型
     */
    jumpUrl:function(url,type){
        if (!type){
            window.location.href=url;
        }else{
            window.open(url);
        }
    },

    /*
    * 字符串添加格式
    * @param str 需要追加的字符串
    * creat by lijunfang 20180315
    */
    format: function(str){
        if (typeof str !== "string") {
            return str;
        }
        var l = arguments.length, i = 0, reg;
        
        if (l === 2 && $.type(arguments[1]) === 'object') {
            var repalceObj = arguments[1], key;
            for (key in repalceObj) {
                if (repalceObj.hasOwnProperty(key)) {
                    reg = new RegExp('\\{' + key + '\\}', 'g');
                    str = str.replace(reg, function(){
                        return repalceObj[key];
                    });
                }
            }
        }
        else {
            for (; i < l - 1; i++) {
                reg = new RegExp('\\{' + i + '\\}', 'g');
                var v = arguments[i + 1];
                str = str.replace(reg, function(){
                    return v;
                });
            }
            
        }
        return str;
    },

    /**
     * 注销登录
     */
    logOut:function(){
        layer.confirm(Lang.logOut, {
            btn: [Lang.determine,Lang.cancel]
        }, function(){
            Common.ajax('post','/Login/m_logout',{},function(data){
                Common.jumpUrl(data['url']);
            });
        }, function(){
            layer.closeAll();
        });
    },
    /**
     * 弹窗
     * @param 需要弹窗的盒子
     * @param  弹窗的大小
     */
    dialogShow:function(id,param){
        var confing = {
            "width":"",
            "height":""
        };
        $.extend(true,confing,param);
        var widths,heights,
            dialogWrap = $(id),
            dialog = dialogWrap.find(".dialog-cont"),
            closeBtn = dialog.find(".close-dialog"),
            yesBtn = dialog.find(".yes-dialog");
       var setWidthHeight = function(){
            widths = param.width ? param.width : null;
            heights = param.height ? param.height : null;
            if(widths){
                dialog.css({
                    "width":widths,
                    "height":heights
                })
            }
        },
        Show = function(){
            dialogWrap.removeClass('hide');
        },
        Hide = function(){
            dialogWrap.addClass('hide');
        },
        closeDialog = function(){
            closeBtn.click(function(event) {
                Hide();
            });
            yesBtn.click(function(){
                Hide();
            });
        },
        Init = function(){
            setWidthHeight();
            Show();
           closeDialog();
        };
       (function(){
            Init();
        }());   
    },
    /**
     * 关闭弹窗
     * @param  需要关闭的弹窗的盒子
     */
    dialogHide:function(id){
        $(id).addClass('hide');
    },
    /**
     * 显示和隐藏元素
     * Pattaya.Mall.showAndHide
     * @param  {[type] dom} ele  [需要隐藏和显示元素]
     * @param  {[type] boolean} show [ture:表示显示元素]
     */
    showAndHide:function (ele, show) {
        var _ele = $(ele);
        if (show && typeof show !== "boolean") {
            return;
        }
        if (show) {
            _ele.removeClass('hide');
        } else {
            _ele.addClass('hide');
        }
    },
    /**
     * 全选功能
     */
    allSelect:function(allId,other){
        var allEle = $(allId),
            otherEle = $(other);
        allEle.click(function(){
            otherEle.eq(0).attr("disabled");
            otherEle.each(function(index,el) {
                if(allEle.prop("checked") && !$(el).attr("disabled")){
                    $(el).prop("checked",true);
                }else{
                    $(el).prop("checked",false);
                }
            });
        });
    },
    /**
     * 初始化应用
     */
    initApp:function(){
        //全局ajax请求loading效果
        $.ajaxSetup({
            beforeSend:function(){
                loading = layer.load(2, {
                    shade: [0.001,'#000'],
                    // offset: ['30%']
                });
            },
            complete:function(){
                layer.close(loading);
            }
        });
    },
    /**
     * 初始化应用
     */
    pagegGo:function(obj){
        var page = $(".page-put").val();
        if(page>0){
            var href = window.location.href.split("&page")[0];
            window.location.href=href+'&page='+page;
        }
    },
    upLoadImg:function(btn,list,server){
        var $list = $(".uploader-list");
        // 初始化Web Uploader
        var $list = $(list);
        var uploader = WebUploader.create({

            // 选完文件后，是否自动上传。
            auto: true,

            // swf文件路径
            swf:'static/js/webuploader/Uploader.swf',

            // 文件接收服务端。
            server:server,

            // 选择文件的按钮。可选。
            // 内部根据当前运行是创建，可能是input元素，也可能是flash.
            pick: $(btn),

            // 只允许选择图片文件。
            accept: {
                title: 'Images',
                extensions: 'gif,jpg,jpeg,bmp,png',
                mimeTypes: 'image/*'
            }
        });
        // 当有文件添加进来的时候
        uploader.on( 'fileQueued', function( file ) {
            var $li = $(
                    '<li><div id="' + file.id + '" class="file-item thumbnail">' +
                        '<img>' +
                        '<div class="info">' + file.name + '</div>' +
                    '</div></li>'
                    ),
                $img = $li.find('img');


            // $list为容器jQuery实例
            $list.append( $li );

            // 创建缩略图
            // 如果为非图片文件，可以不用调用此方法。
            // thumbnailWidth x thumbnailHeight 为 100 x 100
            uploader.makeThumb( file, function( error, src ) {
                if ( error ) {
                    $img.replaceWith('<span>'+ Lang.noCanPreview +'</span>');
                    return;
                }
                $img.attr( 'src', src );
            }, 80,80);
        });
        // 文件上传过程中创建进度条实时显示。
        uploader.on( 'uploadProgress', function( file, percentage ) {
            var $li = $( '#'+file.id ),
                $percent = $li.find('.progress span');

            // 避免重复创建
            if ( !$percent.length ) {
                $percent = $('<p class="progress"><span></span></p>')
                        .appendTo( $li )
                        .find('span');
            }

            $percent.css( 'width', percentage * 100 + '%' );
        });

        // 文件上传成功，给item添加成功class, 用样式标记上传成功。
        uploader.on( 'uploadSuccess', function( file , response) {
            console.log(response)
            if(response.code==200){
                $("#PhotoPath").val(response.url)
            }
            $( '#'+file.id ).addClass('upload-state-done');
        });

        // 文件上传失败，显示上传出错。
        uploader.on( 'uploadError', function( file ) {
            var $li = $( '#'+file.id ),
                $error = $li.find('div.error');

            // 避免重复创建
            if ( !$error.length ) {
                $error = $('<div class="error"></div>').appendTo( $li );
            }

            $error.text(Lang.uploadFailed);
        });

        // 完成上传完了，成功或者失败，先删除进度条。
        uploader.on( 'uploadComplete', function( file ) {
            $( '#'+file.id ).find('.progress').remove();
        });
    },
    displayCopy:function(){
        var Timeout;
        $(".display-copy").on({
            mouseenter: function(event) {
                clearTimeout(Timeout);
                var _this = $(this),
                    txt = _this.text(),
                    x = _this.offset().left,
                    y = _this.offset().top - $(document).scrollTop() + 40;

                $(".show-copy-pop").html(txt).css({"left":x, "top":y}).removeClass("hide");
            },
            mouseout: function(event) {
                Timeout = setTimeout(function(){
                    Global.showAndHide($(".show-copy-pop"));
                },300);
            }
        });

        $(".show-copy-pop").on({
            mouseenter: function(event) {
                clearTimeout(Timeout);
                Global.showAndHide($(".show-copy-pop"),true);
            },
            mouseout: function(event) {
                Timeout = setTimeout(function(){
                    Global.showAndHide($(".show-copy-pop"));
                },10)
            }
        });
    }
};

// Common.initApp();

/**
 * 检测数组元素索引
 */
Array.prototype.indexOf = function(val) {
    for (var i = 0; i < this.length; i++) {
        if (this[i] == val) return i;
    }
    return -1;
};

/**
 * 检查数组是否包含某个元素
 */
Array.prototype.in_array = function(e){
    for(i=0;i<this.length && this[i]!=e;i++);
    return !(i==this.length);
};

/**
 * 删除数组元素
 */
Array.prototype.remove = function(val) {
    var index = this.indexOf(val);
    if (index > -1) {
        this.splice(index, 1);
    }
};