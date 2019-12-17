$(function() {
    $(".export-data").click(function () {
        $("#is_export").val(1);
        $("#navbar").submit();
    })

    $(".query-data").click(function () {
        $("#is_export").val(0);
        $("#navbar").submit();
    })

});
