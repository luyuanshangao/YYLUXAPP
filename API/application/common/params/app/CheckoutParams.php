<?php
namespace app\common\params\app;

/**
 * 开发：tinghu.liu
 * 功能：Checkout
 * 时间：2018-11-01
 */
class CheckoutParams
{
    /**
     * 添加购物车数据校验
     * @return array
     */
    public function getRules()
    {
        return[
            ['CustomerId','require|integer','The CustomerId is require.|The CustomerId must be an integer.'],
            ['CustomerName','require', 'The CustomerName is require.'],
            ['GuestId','require', 'The GuestId is require.'],
            //币种
            ['Currency','require', 'The Currency is require.'],
            //收货国家【收货地址选择的国家简码】
            ['Country','require', 'The Country is require.'],
            //语种
            ['Lang','require', 'The Lang is require.'],
            /** 非必传参数  **/
            //['IsBuyNow','require'], //是否是BuyNow：0-不是，1-是
            //['PayType','require'], //支付方式
            //['IsPaypalQuick','require'], //是否是paypal快捷支付
        ];
    }

    /**
     * 初始化checkou产品运费数据数据校验
     * @return array
     */
    public function getInitShippingDataRules()
    {
        return[
            ['CustomerId','require|integer','The CustomerId is require.|The CustomerId must be an integer.'],
            ['GuestId','require', 'The GuestId is require.'],

            //币种
            ['Currency','require', 'The Currency is require.'],
            //收货国家【收货地址选择的国家简码】
            ['Country','require', 'The Country is require.'],
            //语种
            ['Lang','require', 'The Lang is require.'],


            /** 非必传参数  **/
            //['IsBuyNow','require'], //是否是BuyNow：0-不是，1-是
            //['PayType','require'], //支付方式
            //['IsPaypalQuick','require'], //是否是paypal快捷支付
            //['FromFlag','require'], //来源标识：1-来至checkout改变支付方式，如果币种或地址发生变化，只需要更新价格，不需要更新运输方式，运输方式需要保持用户之前选中的

        ];
    }

    /**
     * 获取支付方式数据校验
     * @return array
     */
    public function getPayTypeRules()
    {
        return[
            //币种
            ['Currency','require', 'The Currency is require.'],
            //收货国家【收货地址选择的国家简码】
            ['Country','require', 'The Country is require.'],
        ];
    }

    /**
     * 获取AstroPay支付渠道支付的银行信息数据校验
     * @return array
     */
    public function getAstroPayCardInfoRules(){
        return [
            ['PayType','require','The PayType is require.'],
            //币种
            ['Currency','require', 'The Currency is require.'],
            //收货国家【收货地址选择的国家简码】
            ['Country','require', 'The Country is require.'],
        ];
    }

    /**
     * 产品详情页BuyNow数据校验
     * @return array
     */
    public function addToCartBuyNowRules(){
        return [

            ['GuestId','require', 'The GuestId is require.'],

            //客户ID，非必传
            ['CustomerId','integer','The CustomerId must be an integer.'],
            //['CustomerName','require'], 登录有用户名，非必填

            ['ProductID','require|integer','The ProductID is require.|The ProductID must be an integer.'],
            ['SkuID','require', 'The SkuID is require.'],
            ['Qty','require', 'The Qty is require.'],

            //收货国家【收货地址选择的国家简码】
            ['Country','require', 'The Country is require.'],
            //币种
            ['Currency','require', 'The Currency is require.'],
            ['Lang','require','The Lang is require.'],

            ['ShippingService','require','The ShippingService is require.'],
        ];
    }


}