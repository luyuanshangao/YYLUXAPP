var radio_checbox = function () {

    // Handles custom checkboxes & radios using jQuery Uniform plugin
    var handleUniform = function () {
        if (!jQuery().uniform) {
            return;
        }
        var test = $("input[type=checkbox]:not(.toggle, .make-switch), input[type=radio]:not(.toggle, .star, .make-switch)");
        if (test.size() > 0) {
            test.each(function () {
                if ($(this).parents(".checker").size() == 0) {
                    $(this).show();
                    $(this).uniform();
                }
            });
        }
    }
    //* END:CORE HANDLERS *//
    return {
        //main function to initiate the theme
        init: function () {
            //core handlers
            handleUniform(); // hanfle custom radio & checkboxes

        },

    };

}();