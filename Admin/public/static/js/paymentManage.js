var PaymentManage = function () {
    /**
    * 初始化函数
    */
    function Init() {

    };
    /**
     * 多笔交易查询页面
     */
    function multiTransaction(){
        $('.js-refund-btn').click(function(){
            var isAjax = true,
                _amount = $('#refundDialog').find('input[name="amount"]'),
                _reason = $('#refundDialogReason'),
                _transactionId = $('#transactionId');
            _amount.siblings('.error-tip').html('');
            _reason.siblings('.error-tip').html('');
            layer.open({
                title: "订单退款",
                content: $('#refundDialog'),
                type: 1,
                area: ['400px', '380px'],
                btn: ['确定', '取消'],
                yes: function () {
                    var _amountVal = _amount.val(),
                        _reasonVal = _reason.val(),
                        _transactionIdVal = _transactionId.val();

                    if (!_amountVal){
                        _amount.siblings('.error-tip').html('请输入金额');
                        return false;
                    }

                    if (!_reasonVal) {
                        _reason.siblings('.error-tip').html('请输入原因');
                        return false;
                    }
                    _amount.siblings('.error-tip').html('');
                    _reason.siblings('.error-tip').html('');
                   var formData = {
                       RefundAmount: _amountVal,
                       Note: _reasonVal,
                       TransactionId: _transactionIdVal,
                    }
                    if (!isAjax){
                        return false;
                    }
                    isAjax = false;
                    $.ajax({
                        type: "POST",
                        url: "/payment/refund",
                        dataType: 'json',
                        data: formData,
                        async: false,
                        cache: false,
                        //contentType: false,
                        //processData: false,
                        success: function (data) {
                            if (data.code == 200) {
                                layer.msg(data.msg, { icon: 1 });
                                setTimeout(function () {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                layer.msg(data.msg, { icon: 2 });
                                isAjax = true;
                            }
                            
                        },
                        error:function(data){
                            console.log(data);
                            isAjax = true;
                        }
                    });

                },
                cancel: function () {

                }
            });
        })
    }
    /**
     * 单笔交易查询页面
     */
    function singleTrabsaction () {
        $('.js-refund-btn').click(function () {
            var isAjax = true,
                _amount = $('#refundDialog').find('input[name="amount"]'),
                _reason = $('#refundDialogReason'),
                _transactionId = $('#transactionId');
            _amount.siblings('.error-tip').html('');
            _reason.siblings('.error-tip').html('');
            layer.open({
                title: "订单退款",
                content: $('#refundDialog'),
                type: 1,
                area: ['400px', '380px'],
                btn: ['确定', '取消'],
                yes: function () {
                    var _amountVal = _amount.val(),
                        _reasonVal = _reason.val(),
                        _transactionIdVal = _transactionId.val();

                    if (!_amountVal) {
                        _amount.siblings('.error-tip').html('请输入金额');
                        return false;
                    }

                    if (!_reasonVal) {
                        _reason.siblings('.error-tip').html('请输入原因');
                        return false;
                    }
                    _amount.siblings('.error-tip').html('');
                    _reason.siblings('.error-tip').html('');
                    var formData = {
                        RefundAmount: _amountVal,
                        Note: _reasonVal,
                        TransactionId: _transactionIdVal,
                    }
                    if (!isAjax) {
                        return false;
                    }
                    isAjax = false;
                    $.ajax({
                        type: "POST",
                        url: "/payment/refund",
                        dataType: 'json',
                        data: formData,
                        async: false,
                        cache: false,
                        success: function (data) {
                            if (data.code == 200) {
                                layer.msg(data.msg, { icon: 1 });
                                setTimeout(function () {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                layer.msg(data.msg, { icon: 2 });
                                isAjax = true;
                                
                            }
                        },
                        error:function(data){
                            console.log(data);
                            isAjax = true;
                        }
                    });

                },
                cancel: function () {

                }
            });
        })
    }
    /**
     * 单笔交易对账查询页面
     */
    function singleContrast () {
        var isAjax = true;
        $('.js-remote-transaction').click(function(){
            var _transactionId = $('#transactionId').val(),
                _tradeStatus = $('#tradeStatus').val(),
                _tradeStatusSummary = $('#tradeStatusSummary').val(),
                _currencyCode = $('#currencyCode').val(),
                _amount = $('#amount').val(),
                _invoiceId = $('#invoiceId').val(),

                formData = {
                    TransactionId:_transactionId,
                    TradeStatus:_tradeStatus,
                    CurrencyCode:_currencyCode,
                    Amount:_amount,
                    TradeStatusSummary:_tradeStatusSummary,
                    InvoiceId:_invoiceId
                };
            
            if(!isAjax){
                return false;
            }
            isAjax = false;
            $.ajax({
                type: "POST",
                url: "/payment/updateTransaction",
                dataType: 'json',
                data: formData,
                async: false,
                cache: false,
                success: function (data) {
                    if (data.code == 200) {
                        layer.msg(data.msg, { icon: 1 });
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                        
                    } else {
                        layer.msg(data.msg, { icon: 2 });
                        isAjax = true;
                    }
                },
                error:function(data){
                    console.log(data);
                    isAjax = true;
                }
            });
        })
    }

    $(function () {
        Init();
    });
    return {
        multiTransaction: multiTransaction,
        singleTrabsaction: singleTrabsaction,
        singleContrast: singleContrast
    }
}();