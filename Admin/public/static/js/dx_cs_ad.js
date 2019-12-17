/**
 * 广告管理
 * Created by tinghu.liu on 2018/4/12.
 */
var DX_AD = function() {

    /**
     * 增加页面
     */
    function addPages(){
        //添加按钮
        $('.add-pages').click(function(){
            layer.open({
                type: 1,
                title: '添加页面信息',
                //skin: 'layui-layer-rim', //加上边框
                area: ['420px', '250px'], //宽高
                content: $('#add_pages_box')
            });
        });
        //添加保存按钮
        $('.add-pages-confirm').click(function(){
            var param = $('#add_pages_form').serialize();
            $.ajax({
                type:'post',
                url:url.save_url,
                data:param,
                success:function(data){
                    if (data['code'] == 0){
                        layer.msg('添加成功', {icon: 1}, function(){
                            window.location.reload();
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });
        });
    }

    /**
     * 更新页面弹窗
     */
    function updatePagesBox() {
        //修改按钮
        $('.page-editor').click(function(){
            var _id = $(this).attr('data-id'),
                SiteID = $(this).attr('data-siteid'),
                SiteName = $(this).attr('data-sitename'),
                PageName = $(this).attr('data-pagename'),
                Domain = $(this).attr('data-domain');
            var _html = '<div id="editor_pages_box" class="default-padding page-box">'+
                '<form id="editor_pages_form">'+
                '<table class="table mt10">'+
                '<tbody><tr><td class="text-align-reverse">站点：</td>'+
                '<td><input type="hidden" value="DX" name="SiteName"><input type="hidden" value="'+_id+'" name="_id">'+
                '<select name="SiteID" class="form-control w200"><option value="1" selected="selected">DX</option></select>'+
                '</td></tr><tr><td class="text-align-reverse">页面名称：</td><td><input type="text" name="PageName" value="'+PageName+'" class="form-control w200" placeholder="请输入页面名称"></td></tr>'+
            '<tr><td class="text-align-reverse">域名：</td><td><input type="text" name="Domain" value="'+Domain+'" class="form-control w200" placeholder="请输入域名"></td></tr><tr><td class="text-align-reverse"></td><td><span class="btn btn-qing editor-pages-confirm" onclick="DX_AD.updatePages()">确认保存</span></td></tr></tbody></table></form></div>';
            layer.open({
                type: 1,
                title: '修改页面信息',
                //skin: 'layui-layer-rim', //加上边框
                area: ['420px', '250px'], //宽高
                content: _html
            });

        });
    }

    /**
     * 更新页面
     */
    function updatePages(){
        var param = $('#editor_pages_form').serialize();
        $.ajax({
                type:'post',
                url:url.update_url,
                data:param,
                success:function(data){
                    if (data['code'] == 0){
                        layer.msg('修改成功', {icon: 1}, function(){
                            window.location.reload();
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });
    }

    /**
     * 新增页面区域数据
     */
    function addRegion(){
        //添加按钮
        $('.add-region').click(function(){
            layer.open({
                type: 1,
                title: '添加区域信息',
                //skin: 'layui-layer-rim', //加上边框
                area: ['420px', '250px'], //宽高
                content: $('#add_region_box')
            });
        });
        //添加保存按钮
        $('.add-region-confirm').click(function(){
            var param = $('#add_region_form').serialize();
            $.ajax({
                type:'post',
                url:url.save_url,
                data:param,
                success:function(data){
                    if (data['code'] == 0){
                        layer.msg('添加成功', {icon: 1}, function(){
                            window.location.reload();
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });
        });
    }

    /**
     * 更新页面区域数据
     */
    function updateRegion(){
        $('.region-editor').click(function () {
            var _id = $(this).attr('data-id');
            layer.open({
                type: 1,
                title: '修改区域信息',
                //skin: 'layui-layer-rim', //加上边框
                area: ['420px', '250px'], //宽高
                content: $('#editor_region_box_'+_id)
            });
            document.getElementById('editor_region_form_'+_id).reset();
        });
        $('.editor-region-confirm').click(function () {
            var _id = $(this).attr('data-id'),
                param = $('#editor_region_form_'+_id).serialize();
            $.ajax({
                type:'post',
                url:url.update_url,
                data:param,
                success:function(data){
                    if (data['code'] == 0){
                        layer.msg('修改成功', {icon: 1}, function(){
                            window.location.reload();
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });

        });
    }

    /**
     * 新增页面区域编码
     */
    function addRegionNumber(){
        //添加按钮
        $('.add-region-number').click(function(){
            layer.open({
                type: 1,
                title: '添加区域编码信息',
                //skin: 'layui-layer-rim', //加上边框
                area: ['420px', '380px'], //宽高
                content: $('#add_region_number_box')
            });
        });
        //添加保存按钮
        $('.add-region-number-confirm').click(function(){
            var param = $('#add_region_number_form').serialize();
            console.log(param)
            $.ajax({
                type:'post',
                url:url.save_url,
                data:param,
                success:function(data){
                    if (data['code'] == 0){
                        layer.msg('添加成功', {icon: 1}, function(){
                            window.location.reload();
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });
        });
    }

    /**
     * 联动获取数据初始化
     */
    function autoLoadInit() {
        /** 联动获取数据 start **/
            //获取页面数据
        var getPagesData = function(){
                $('#add_SiteID').change(function () {
                    var param = {SiteID:$(this).val()};
                    $.ajax({
                        type:'post',
                        url:url.get_page_url,
                        data:param,
                        success:function(data){
                            if (data['code'] == '0'){
                                var _html = '<option value="">请选择</option>';
                                for (var i=0; i<data['data'].length; i++){
                                    var obj = data['data'][i];
                                    _html += '<option value="'+obj['PageID']+'">'+obj['PageName']+'</option>';
                                }
                                $('#add_PageID').html(_html);
                            }else{
                                getPagesData();
                            }
                        }
                    });
                });
            }
        getPagesData();
        //获取页面区域数据
        var getRegionData = function () {
            $('#add_PageID').change(function () {
                var param = {PageID:$(this).val()};
                // alert(param)
                $.ajax({
                    type:'post',
                    url:url.get_region_url,
                    data:param,
                    success:function(data){
                        if (data['code'] == '0'){
                            var _html = '<option value="">请选择</option>';
                            for (var i=0; i<data['data'].length; i++){
                                var obj = data['data'][i];
                                _html += '<option value="'+obj['_id']+'">'+obj['AreaName']+'</option>';
                            }
                            $('#add_AreaID').html(_html);
                        }else{
                            getRegionData();
                        }
                    }
                });

            });
        }
        getRegionData();
        //获取页面区域编号数据
        var getRegionNumberData = function () {
            $('#add_AreaID').change(function () {
                var param = {AreaID:$(this).val()};
                // alert(param)
                $.ajax({
                    type:'post',
                    url:url.get_region_number_url,
                    data:param,
                    success:function(data){
                        console.log(data)
                        if (data['code'] == '0'){
                            var _html = '<option value="">请选择</option>';
                            for (var i=0; i<data['data'].length; i++){
                                var obj = data['data'][i];
                                _html += '<option value="'+obj['_id']+'">'+obj['AreasLayoutName']+'</option>';
                            }
                            $('#add_AreasLayoutID').html(_html);
                        }else{
                            getRegionNumberData();
                        }
                    }
                });

            });
        }
        getRegionNumberData();
        /** 联动获取数据 end **/
    }

    /**
     * 初始化页面区域编码编辑页面
     */
    function editorRegionNumber(){
        $('.editor-region-number-confirm').click(function () {
            var param = $('#editor_region_number_form').serialize();
            $.ajax({
                type:'post',
                url:url.save_url,
                data:param,
                success:function(data){
                    if (data['code'] == 0){
                        layer.msg('修改成功', {icon: 1}, function(){
                            window.location.href = data['url'];
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });
        });
    }


    /**
     * 广告增加页面
     * @constructor
     */
    function index(){
        //初始化数据 绑定查询、添加按钮跳转链接等
        $('.ad-index-search').click(function () {
            var param = $('#ad-index-top-form').serialize();
            window.location.href = '?'+param;
        });
        $('.ad-index-add').click(function () {
            var param = $('#ad-index-top-form').serialize();
            /*if (
                Common.isEmpty($('#add_SiteID').val())
                || Common.isEmpty($('#add_PageID').val())
                || Common.isEmpty($('#add_AreaID').val())
                || Common.isEmpty($('#add_AreasLayoutID').val())
            ){
                layer.msg('请选择布局编号相关信息', {icon: 2});
                return false;
            }else{*/
                window.location.href = url.add_url+'?'+param;
            /*}*/

        });

    }

    /**
     * 新增页面初始化
     */
    function addInit(){
        $('#add_AreasLayoutID').change(function () {
            var flag = $(this).attr('data-flag');
            var param = $('#ad-index-top-form').serialize();
            if (
                Common.isEmpty($('#add_SiteID').val())
                || Common.isEmpty($('#add_PageID').val())
                || Common.isEmpty($('#add_AreaID').val())
                || Common.isEmpty($('#add_AreasLayoutID').val())
            ){
                layer.msg('请选择布局编号相关信息', {icon: 2});
                return false;
            }else{

                if (flag == 'add'){
                    window.location.href = '?'+param;
                }
                if (flag == 'editor'){
                    window.location.href = '?'+param;
                }
            }

        });
    }

    /**
     * 多图
     */
    function moreImg(){
        $(".add-img-items").click(function () {
            var imgInputLen = $(".imageUrlWrap").length,
                _html_url = '<div class="mt5"><span class="handle"></span><input type="text" name="LinkUrl" class="form-control common-input imgUrl'+ (imgInputLen+1) +'" placeholder="链接地址"></div>',
                mainBox = $(".main-box");

            for(var i=0;i<mainBox.length;i++){
                var _html = '',
                    lang = mainBox.eq(i).find(".language-input").val();

                // if(lang == "en"){
                    _html += '<div><div class="col-sm-10 mb5 pd0 imageUrlWrap add-wrap'+ (imgInputLen+1) +'"><span class="handle"></span><input type="text" name="ImageUrl" class="form-control mt5 common-input" placeholder="图片地址"></div><div class="col-sm-2 img-alert'+(imgInputLen+1)+'"><a href="javascript:void(0);" class="remove-img red fl block ml5 mt5 h25 lh25 remove-img'+ (imgInputLen+1) +'" data-id="'+ (imgInputLen+1) +'" data-language="'+ lang +'">删除</a></div></div>';
                    mainBox.eq(i).find('.img-items-box').prepend(_html);
                // }else{
                //     _html += '<div class="col-sm-9 mb5 pd0 imageUrlWrap add-wrap'+ (imgInputLen+1) +'"><input type="text" name="ImageUrl" class="form-control mt5 common-input inline-block" placeholder="图片地址"><div>';
                //     mainBox.eq(i).find('.img-items-box').append(_html);
                // }
            }
            $(".linkurl-items-box").prepend(_html_url);
        });
    }
    /**
     * 删除输入框
     */
    function delInput(){
        $(".main-box").on("click",".remove-img",function(){
            var _this = $(this),
                id = _this.attr("data-id"),
                language = _this.attr("data-language"),
                parent = _this.parents('#main-box-'+language),
                len = parent.find("input[name='ImageUrl']").length;
            if (len>1){
                parent.find(".add-wrap"+id).parent().remove();
                // parent.find(".img-alert"+id).remove();
                parent.find(".imgUrl"+id).parent().remove();
                parent.find(".remove-img"+id).remove();
            }else{
                layer.alert('请至少保留一个！');
            }
        })
    }
    
    /**
     * 保存操作
     */
    function btnSave() {
        $('.btn-save').click(function () {
            //拼装数据
            var post_data = new Object(),
                flag = $(this).attr('data-flag');
            if (flag == 'editor'){
                post_data.id = $('#add_ActivityID').val();
            }
            post_data.Key = $('input[name="Key"]').val();
            post_data.SiteID = $('#add_SiteID').val();
            post_data.PageID = $('#add_PageID').val();
            post_data.AreaID = $('#add_AreaID').val();
            post_data.AreasLayoutID = $('#add_AreasLayoutID').val();
            post_data.ContentTypeID = $('#add_ContentTypeID').val();

            post_data.Banners = new Object();/** 初始化banner数据 **/
            post_data.SKUs = new Object();/** 初始化sku数据 **/
            post_data.Keyworks = new Object();/** 初始化关键词数据 **/

            //拼装类型为banner的数据
            if (init_c_type == 'Banner'){
                post_data.Banners.IsMoreImage = (init_is_more_img == '是'?true:false);
                post_data.Banners.BannerImages = new Object();
                post_data.Banners.BannerImages.IsContainsFont = ($('#IsContainsFont').is(':checked')?true:false);
                // post_data.Banners.BannerImages.BannerFonts = new Array();
                var BannerFonts_arr = new Array();
                $('.box-ul').each(function () { /** 获取不同语种信息 **/
                    var that_obj = $(this),
                        language,
                        ImageUrl_arr = new Array(),
                        ImageLinkUrl_arr = new Array(),
                        link_url,
                        main_text,
                        sub_text;
                    //获取语言
                    language = that_obj.find($('input[name="Language"]')).val();
                    //获取图片
                    that_obj.find($('input[name="ImageUrl"]')).each(function () {
                        ImageUrl_arr.push($(this).val());
                    });
                    //获取链接
                    link_url = that_obj.find($('input[name="LinkUrl"]')).val();
                    that_obj.find($('input[name="LinkUrl"]')).each(function () {
                        ImageLinkUrl_arr.push($(this).val());
                    });

                    //获取主文案
                    main_text = (that_obj.find($('input[name="MainText"]')).val()).split("||");
                    //获取副文案
                    sub_text = (that_obj.find($('input[name="SubText"]')).val()).split("||");
                    //拼装
                    var push_f = {Language:language, SKU:null, ImageUrl:ImageUrl_arr, LinkUrl:ImageLinkUrl_arr, MainText:main_text, SubText:sub_text}
                    BannerFonts_arr.push(push_f);
                });
                post_data.Banners.BannerImages.BannerFonts = BannerFonts_arr;
            }else if (init_c_type == 'Text'){
                var keywd_arr = new Array();
                $('.box-ul').each(function () {
                    /** 获取不同语种信息 **/
                    var that_obj = $(this),
                        language,
                        value;
                    language = that_obj.find($('input[name="Language"]')).val();
                    value = that_obj.find($('textarea[name="keywords"]')).val();
                    var temp_obj = {Language:language, Value:value};
                    keywd_arr.push(temp_obj);
                });
                post_data.Keyworks.TextData = keywd_arr;
            }else if (init_c_type == 'SKU_AD'){
                var sku_arr = new Array();
                $('.box-ul').each(function () { /** 获取不同语种信息 **/
                var that_obj = $(this),
                    language,
                    sku,
                    link_url,
                    main_text,
                    sub_text;
                    //获取语言
                    language = that_obj.find($('input[name="Language"]')).val();
                    //获取sku
                    sku = that_obj.find($('input[name="SKUInput"]')).val();
                    //获取链接
                    link_url = that_obj.find($('input[name="LinkUrl"]')).val();
                    //获取主文案
                    main_text = that_obj.find($('input[name="MainText"]')).val();
                    //获取副文案
                    sub_text = that_obj.find($('input[name="SubText"]')).val();
                    //拼装
                    var push_f = {Language:language, SKU:sku, LinkUrl:link_url, MainText:main_text, SubText:sub_text};
                    sku_arr.push(push_f);
                });
                post_data.SKUs.SKUData = sku_arr;
            }
            post_data = JSON.stringify(post_data);
            $.ajax({
                type:'post',
                url:url.save_activity_url,
                data:{data:post_data},
                success:function(data){
                    if (data['code'] == '0'){
                        layer.msg(data['msg'], {icon: 1}, function(){
                            window.location.href = data['url'];
                        });
                    }else{
                        layer.msg(data['msg'], {
                            icon: 2
                        });
                    }
                }
            });
        });
    }

    function Init() {
        addPages();
        updatePagesBox();
        addRegion();
        updateRegion();
        addRegionNumber();
        autoLoadInit();
        index();
        addInit();
        moreImg();
        btnSave();
        delInput();
    }

    $(function() {
        Init();
    });
    return {
        updatePages:updatePages,
        editorRegionNumber:editorRegionNumber
        // LoadSelect: loadSelect,
    };
}();
