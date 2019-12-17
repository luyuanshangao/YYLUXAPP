<?php
namespace app\common\params\windcontrol;

/**
 * 风控验证
 * @author Hai.Ouyang 20190703
 */
class WindControlParams
{
    public static function baseRules(){
        return[
            ['OrderNumber','require'],
            ['CustomerID','require'],
            ['TransactionChannel','require'],
            ['TransactionType','require'],
            ['Amount','require'],
            ['ShippingAddress','require'],
            ['TransactionID','require'],
            ['CurrencyCode','require'],
            ['SkuInfos','require'],
            ['SiteID','require'],
            ['CustomerIP','require'],
        ];
    }

    public static function shippingRules(){
        return[
            ['Email','require'],
            ['Country','require'],
            ['State','require'],
            ['City','require'],
            ['Street1','require'],
        ];   
    }

    public static function skuRules(){
        return [
            ['ProductId','require'],
            ['SkuId','require'],
            ['SkuCode','require'],
            ['Name','require'],
            ['UnitPrice','require'],
            ['Count','require'],
        ];
    }

    public static function afterBaseRules(){
        return [
            ['Amount','require'],
            ['ChildOrderList','require'],
            ['BillingAddress','require'],
            ['ExchangeRate','require'],
            ['ThirdPartyRiskStatus','require'],
            ['ThidPartyRiskResult','require'],
            ['OrderNumber','require'],
            ['ChildOrderList','require'],
            ['TransactionID','require'],
            ['CustomerID','require'],
            ['CustomerIP','require'],
            ['CurrencyCode','require'],
            ['PaymentChannel','require'],
            ['PaymentMethod','require'],
            ['ThirdPartyTxnID','require'],
            ['TxnResult','require'],
        ];
    }

    public static function afterShippingRules(){
        return [
            ['FirstName','require'],
            ['LastName','require'],
            ['Country','require'],
            ['State','require'],
            ['City','require'],
        ];
    }
}