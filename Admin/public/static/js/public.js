var public  = function() {
    function Init(){

    };
    /*
     *公用审核
     */
    function public_status() {
       $('.public_status').click(function(event){
           var that = $(this),
               id     = that.data('id'),
               status = that.data('status'),
               url    = that.data('url'),
               mark   = that.data('mark')?that.data('mark'):'';

           if(id !='' && status!='' && url !=''){
               $.ajax({
                   type:"POST",
                   url:url,
                   dataType: 'json',
                   data:{id:id,status:status,mark:mark},
                   cache:false,
                   success:function(msg){
                       // console.log(msg);
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
       })
        /*
         *公用不通过
         */
        $('.fail_status').click(function(event){
                var that = $(this),
                    id     = that.data('id'),
                    status = that.data('status'),
                    title  = that.data('title')?that.data('title'):'编辑',
                    url    = that.data('url'),
                    mark   = that.data('mark')?that.data('mark'):'';

                layer.open({
                    title: title,
                    type: 1,
                    skin: 'layui-layer-rim', //加上边框
                    area: ['450px', '320px'], //宽高
                    content: '<form id="submit_form"  method="post" role="search">' +
                                '<div class="pl30">' +
                                '<input type="hidden" name="id"  value="'+id+'"> ' +
                                '<input type="hidden" name="status"  value="'+status+'"> ' +
                                '<input type="hidden" name="mark"  value="'+mark+'"> ' +
                                '<textarea name="remark" rows="10" cols="60"></textarea>'+
                                '<div class="mt30 tcenter">' +
                                '<a href="#" data-url="'+url+'" class = "submit_form btn-qing f18">提交</a>' +
                                '</div>' +
                                '</div>' +
                            '</form>'
                });
        })
        $('body').on('click','.submit_form', function(event) {
                var that = $(this),
                    url = that.data('url');

                var formData = new FormData($( "#submit_form" )[0]);
                $.ajax({
                    type:"POST",
                    url:url,
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
        })
        /*
         *导出
         */
        $('.publicExport').click(function(event){
            var that = $(this);
            var customer_type = $('.customer_type').val()?$('.customer_type').val():'';
            var status = $('.status').val()?$('.status').val():'';
            var Affiliate_ID = $('.affiliate_id').val()?$('.affiliate_id').val():'';
            var startTime = $('.start_add_time').val()?$('.start_add_time').val():'';
            var endTime = $('.end_add_time').val()?$('.end_add_time').val():'';
            var cic_ID = $('.cic_ID').val()?$('.cic_ID').val():'';
            var url = that.data('url');
            var url = url+'&customer_type='+customer_type+'&status='+status+'&Affiliate_ID='+Affiliate_ID+'&startTime='+startTime+'&endTime='+endTime+'&cic_ID='+cic_ID;
            window.location.href=url;
        })

    }


    return {

        public_status:public_status,
    }
}();