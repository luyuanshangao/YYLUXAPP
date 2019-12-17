<?php
namespace app\common\params\app;


/**
 * 开发：tinghu.liu
 * 功能：订单相关校验类
 * 时间：2018-11-06
 */
class OrderParams
{

    /**
     * 获取支付密钥key数据校验
     * @return array
     */
    public function getSecretKeyRules(){
        return[
            ['CustomerId','require|integer', 'The CustomerId is require.|The CustomerId must be an integer.'],
        ];
    }
    /**
     * 提交订单必传参数判断
     * @return array
     */
    public function submitOrderParams(){
        return [
            ['pay_token','require'],
            ['pay_type','require'],
            ['pay_chennel','require'],
        ];
    }
    /**
     * 创建订单且支付数据校验
     * @return array
     */
    public function submitPayRules()
    {
        return[
            ['Sign','require', 'The Sign is require.'], //
            ['CustomerId','require|integer', 'The CustomerId is require.|The CustomerId must be an integer.'],//
            //邮箱不需要传，根据ID调接口获取
            //['CustomerEmail','require|email', 'The CustomerEmail is require.|The CustomerEmail must be mailbox.'],//
            ['Currency','require', 'The Currency is require.'],//
            ['Lang','require', 'The Lang is require.'], //
            ['ShipTo','require', 'The ShipTo is require.'],

            ['PayType','require', 'The PayType is require.'],//
            ['PayChennel','require', 'The PayChennel is require.'],//
            ['CustomerAddressId','require', 'The CustomerAddressId is require.'],//
            ['OrderFrom','require|integer', 'The OrderFrom is require.|The OrderFrom must be an integer.'],//
            ['IsBuyNow','require|integer', 'The IsBuyNow is require.|The IsBuyNow must be an integer.'],//

            /** 非必传参数 start **/
//            BuyNow: 1 //
//            Message[0][StoreId]: 18 //
//            Message[0][ProductId]: 2044005//
//            Message[0][SkuId]: 887056//
//            Message[0][Messages]://
//
//            IsPaypalQuick: 1 //
              //快捷支付回到checkout时url的参数
//            Querystring: PaymentToken=ee3043c3-0140-40ea-9f11-8b80f5ab9f2c&token=EC-4J3089714R1113010&PayerID=6PMM7EZX8SFSU&isReload=1
//
//            CheckoutToken:
//            PaypalCreateOrder: 0 //paypal创建订单
//            ScPassword://
//            ScPrice://
//            CPF: 213213
//            CardBank: MC
//
//            //新增信用卡时
//            City: 23 //
//             -- Country: Brazil //
//            CountryCode: BR
//            Email: 1232@dd.com
//            FirstName: 123
//            LastName: 123
//            Mobile: 232323
//            Phone: 232323
//            PostalCode: 232323
//            State: BR
//            Street1: 123
//            Street2:
//            CVVCode: 2222
//            CardNumber: 1121312231231231231
//            ExpireMonth: 12
//            ExpireYear: 22
//            IssuingBank:
//            SaveCard: false
//            ProvinceCode: AL
//            CityCode: 23 //
//
//            //选择存在的信用卡时
//            CreditCardTokenId:8DFB5CDE-E036-586D-778B-DF215A7F391B

            //Asiabill 卡类型，用于判断支付渠道为“Asiabill”时判断
//            CardType //

            //快捷支付带回来的地址
//            PaypalQuickAddress:[
//                city
//                cityCode
//                country
//                countryCode
//                email
//                firstName
//                lastName
//                phonenumber
//                postalcode
//                province
//                provinceCode
//                street1
//                street2
//            ]
//
//            //关税保险
//            IsTariffInsurance

            //NOCNOC交易ID
//            NocNocTaxId

            /** 非必传参数 end **/
        ];
    }

    /**
     * 改变订单状态数据规则校验
     * @return array
     */
    public function realChangeOrderStatusRules(){
        return [
            ['customer_id','require|integer'],
            ['order_number','require|integer'],
            ['order_status','require|integer'],
            ['change_reason','require'],
            /*['create_on','require|integer'],*/
            /*['create_by','require'],*/
            ['create_ip','require'],
            /*['chage_desc','require']*/
        ];
    }

    /**
     * IDeal回调
     * @return array
     */
    public function iDealCallBackParams(){
        return [
            ['cko-payment-token','require|max:150'],
            ['responseCode','require|number'],
            ['trackId','require|number']
        ];
    }

}