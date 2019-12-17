$(function() {
    function getId(id) {
        return document.getElementById(id);
    }

    var dragulaInput = $(".dragula-img-link");

    for(var i=0; i<dragulaInput.length; i++){
      var id = $(dragulaInput[i]).attr("id");
      dragula([getId(id)], {
          moves: function(el, container, handle) {
              return handle.className === 'handle';
          }
      });
    }
})