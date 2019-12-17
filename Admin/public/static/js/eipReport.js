var EipReport = function () {
    /**
    * 初始化函数
    */
    function Init() {

    };

    $(function () {
        Init();
    });
    function skuSelection () {
        var _country = $('#country'),
            _countryHidden = $("#countryHidden"),
            _defaultVal = _countryHidden.val().split(',');
        Common.LoadSelect(_country);
        _country.selectpicker('val', _defaultVal); //设置多选下拉框的初始值
        $("#oneCategory").change(function () {
            var oneCategory = $(this).val();
            var class_level = 1;
            var url = "/EipReport/catalog_next";
            $.post(url,{"id":oneCategory,"class_level":class_level},function (res) {
                var html = "<option value=''>二级品类</option>";
                $.each(res,function (k,v) {
                    html += "<option value='"+v.id+"'>"+v.title_en+"</option>";
                })
                $("#twoCategory").html(html);
                $("#threeCategory").html("<option value=''>三级品类</option>");
            })
        })

        $("#twoCategory").change(function () {
            var twoCategory = $(this).val();
            var class_level = 2;
            var url = "/EipReport/catalog_next";
            $.post(url,{"id":twoCategory,"class_level":class_level},function (res) {
                var html = "<option value=''>三级品类</option>";
                $.each(res,function (k,v) {
                    html += "<option value='"+v.id+"'>"+v.title_en+"</option>";
                })
                $("#threeCategory").html(html);
            })
        })

        $("#btn-search").click(function () {
            var _country = $('#country');
            $("#is_export").val(0);
            var countryHiddenVal = _country.val() ? _country.val().join(',') : '';
            $('#countryHidden').val(countryHiddenVal);
            $(".search-form").submit();
        })

        $("#export-btn").click(function () {
            $("#is_export").val(1);
            $(".search-form").submit();
        })
    }
    return {
        skuSelection: skuSelection
    }
}();