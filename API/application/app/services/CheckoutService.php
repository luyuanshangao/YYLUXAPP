<?php
namespace app\app\services;

use think\Cookie;
use think\Session;
use think\Log;

/**
 * 开发：tinghu.liu
 * 功能：CheckoutService
 * 时间：2018-11-01
 */
class CheckoutService extends BaseService
{
    public $CommonService;
//    public $CouponService;
//    public $ProductService;
//    public $NocService;
    public function __construct()
    {
        parent::__construct();
        $this->CommonService = new CommonService();
//        $this->ProductService = new ProductService();
//        $this->CouponService = new CouponService();
//        $this->NocService = new NocService();
    }

    /**
     * 获取BuyNow产品详情
     * @param unknown $Uid
     * @param unknown $Currency
     * @param unknown $Country
     * @param unknown $Lang
     * @return unknown|boolean
     */
    public function getCartInfoBuyNow($Uid,$Currency,$Country,$Lang,$GuestId,$UserName){
        if(!$Uid){
            return false;
        }
        $CartInfo = null;
        if($Uid){
            $CartInfo = $this->redis->get(SHOPPINGCART_BUYNOW_.$Uid);
            $GuesCartInfo = $this->redis->get(SHOPPINGCART_BUYNOW_.$GuestId);
            if(isset($GuesCartInfo[$GuestId])){
                $CartInfo = array();
                // todo ???????????????
                $this->redis->rm(SHOPPINGCART_BUYNOW_.$GuestId);
                //如果没登录的用户里没有购物车信息，则取游客的购物车信息
                $CartInfo[$Uid] = $GuesCartInfo[$GuestId];
                unset($CartInfo[$GuestId]);
            }
        }
        $GlobalShipTo = '';
        $IsHasNocNoc = 0;
        /*2、遍历购物车里的信息，*/
        if($CartInfo){
            $this->CommonService->processCartProduct($CartInfo,$Currency,$Country,$Lang,$Uid,'checkout',$GlobalShipTo,$IsHasNocNoc,$UserName);
        }

        $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);
        $returnData['IsHasNocNoc'] = $IsHasNocNoc;
        if(isset($CartInfo[$Uid]['StoreData'])){
            foreach ($CartInfo[$Uid]['StoreData'] as $k=>$v){
                $returnData[$Uid][$k]['Coupon'] = [];
                $returnData[$Uid][$k]['StoreInfo'] = $v['StoreInfo'];
                foreach ($v['ProductInfo'] as $K2=>$v2){
                    foreach ($v2 as $k3=>$v3){
                        if(isset($v3['ShippModelStatusType'])){
                            $returnData[$Uid][$k]['ProductInfo'][] = $v3;
                        }
                    }
                }
            }
            if(isset($returnData[$Uid])){
                return $returnData;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 获取购物车信息
     * @param int $Uid 用户ID
     * @param string $Currency 当前币种
     * @return array|boolean
     */
    public function getCartInfo($Uid,$Currency,$Country,$Lang,$GuestId,$UserName){
        /*1、读取redis里的购物车信息*/
        $CartInfo = null;
        if($Uid){
            $CartInfo = $this->redis->get(SHOPPINGCART_CHECKOUT_.$Uid);
            $GuesCartInfo = null;
            //如果没登录的用户里没有购物车信息，则取游客的购物车信息
            $GuesCartInfo = $this->redis->get(SHOPPINGCART_CHECKOUT_.$GuestId);
            if($GuesCartInfo){
                //方法1：如果存在游客的购物车信息，则把游客的购物车合并到登录用户的购物车，登录用户的购物车的isBuy字段置为0
                //方法2：如果存在游客的购物车信息，不合并购物车，登录用户的购物车的isBuy字段置为0
                if($CartInfo[$Uid]){
                    foreach ($CartInfo[$Uid]['StoreData'] as $k => $v){
                        foreach ($v['ProductInfo'] as $k1=>$v1){
                            foreach ($v1 as $k2=>$v2){
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1][$k2]['IsBuy'] = 0;
                            }
                        }
                    }
                }
                $CartInfo = $this->CommonService->combineCart($CartInfo,$GuesCartInfo,$Uid,$GuestId,'checkout');
                $this->redis->rm(SHOPPINGCART_CHECKOUT_.$GuestId);
            }
        }else{
            return false;
        }
        $GlobalShipTo = '';
        $IsHasNocNoc = 0;
        /*2、遍历购物车里的信息，*/
        if($CartInfo){
            $this->CommonService->processCartProduct($CartInfo,$Currency,$Country,$Lang,$Uid,'checkout',$GlobalShipTo,$IsHasNocNoc,$UserName);
        }
        Cookie::set('prevCountry',$Country);//用来判断是否切换了国家
        //$this->CommonService->loadRedis()->set(SHOPPINGCART_.$Uid,$CartInfo);
        $this->redis->set(SHOPPINGCART_CHECKOUT_.$Uid,$CartInfo);
        //重新组合数据返回给前端
        $returnData = array();
        $returnData['GlobalShipTo'] = $GlobalShipTo;
        $returnData['IsHasNocNoc'] = $IsHasNocNoc;
        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) !=DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        if(isset($CartInfo[$Uid]['StoreData'])){
            foreach ($CartInfo[$Uid]['StoreData'] as $k=>$v){
                $returnData[$Uid][$k]['Coupon'] = [];
                $returnData[$Uid][$k]['StoreInfo'] = $v['StoreInfo'];
                if(isset($v['isUsedCouponDX'])){
                    if(isset($v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'])){
                        $TmpPrice = $v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'];
                        $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
                        $TmpPrice = sprintf("%.2f", $TmpPrice);
                        $v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                    }
                    $returnData[$Uid][$k]['isUsedCouponDX'] = $v['isUsedCouponDX'];
                    $returnData[$Uid][$k]['isUsedCouponDX'] = $v['isUsedCouponDX'];
                }
                if(isset($v['isUsedCoupon'])){
                    if(isset($v['isUsedCoupon']['DiscountInfo']['DiscountPrice'])){
                        $TmpPrice = $v['isUsedCoupon']['DiscountInfo']['DiscountPrice'];
                        $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
                        $TmpPrice = sprintf("%.2f", $TmpPrice);
                        $v['isUsedCoupon']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                    }
                    $returnData[$Uid][$k]['isUsedCoupon'] = $v['isUsedCoupon'];
                }
                foreach ($v['ProductInfo'] as $K2=>$v2){
                    foreach ($v2 as $k3=>$v3){
                        //&& $v3['ShippModelStatusType'] != 3
                        if(isset($v3['ShippModelStatusType']) && isset($v3['IsBuy']) && $v3['IsBuy'] == 1){
                            if(isset($v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'])){
                                $TmpPrice = $v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'];//coupon的价格汇率计算
                                $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
                                $TmpPrice = sprintf("%.2f", $TmpPrice);
                                $v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                            }
                            $returnData[$Uid][$k]['ProductInfo'][] = $v3;
                        }
                    }
                }
            }
            if(isset($returnData[$Uid])){
                return $returnData;
            }else{
                return false;
            }
        }else{
            //dump('----------');
            return false;
        }
    }

    /**
     * 获取支付方式
     * @param str $Currency 当前币种
     * @param str $Currency 当前所在国家
     * @return multitype:unknown |boolean
     */
    public function getPayType($Currency,$Country){
        $NocNocPayInfo = null;
        if(Session::get('nocnoc_payinfo')){
            $NocNocPayInfo = json_decode(Session::get('nocnoc_payinfo'),true);
        }
        $Params['Currency'] = $Currency;
        $Params['Country'] = $Country;
        $RedisPayType = $this->redis->get('PayType'.$Currency);
        //$RedisPayType = null;
        if($RedisPayType){
            $Data = $RedisPayType;
        }else{
            $Url = MALL_API."/cart/cart/getPayType";
            $Data = doCurl($Url,$Params,null,true);
        }
        //dump($Data);
        //根据国家和币种缓存支付方式？
        $ReturnData = array();
        if(isset($Data['data']['PayType'])){
            $this->redis->set("PayType".$Currency,$Data,config('pay_type_expire_time'));
            foreach ($Data['data']['PayType'] as $k=>$v){
                if(isset($v['channel']) && is_array($v['channel'])){
                    foreach ($v['channel'] as $k1=>$v1){
                        if(isset($v1['restriction'])){
                            $_restriction = explode(",",$v1['restriction']);
                            if(!in_array($Country,$_restriction)){
                                //如果所在国家不在数组里
                                unset($Data['data']['PayType'][$k]['channel'][$k1]);
                            }
                        }
                    }
                }else{
                    unset($Data['data']['PayType'][$k]);
                    continue;
                }
                //支付方式状态：1-启用，2-未启用；去掉未启用的支付方式
                if(isset($v['status']) && $v['status'] != 1){
                    unset($Data['data']['PayType'][$k]);
                    continue;
                }

                unset($Data['data']['PayType'][$k]['status']);
                unset($Data['data']['PayType'][$k]['edittime']);
                unset($Data['data']['PayType'][$k]['edit_person']);
            }
        }else{
            return false;
        }

        //对$Data再做一次循环，过滤出唯一的支付渠道
        if(isset($Data['data']['PayType'])){
            foreach ($Data['data']['PayType'] as $k=>$v){
                if($NocNocPayInfo){
                    if($v['payname'] == $NocNocPayInfo['pay_type']){
                        $Data['data']['PayType'][$k]['is_select'] = 1;
                    }
                    //Astropay
                    if($NocNocPayInfo['pay_type'] == 'CreditCard' && $v['payname'] == $NocNocPayInfo['pay_type'] && $NocNocPayInfo['pay_chennel'] != 'Astropay'){
                        if($NocNocPayInfo['credit_card_token_id']){
                            //如果是选择已有信用卡的
                        }else{
                            //是填写信用卡信息的
                            $Data['data']['PayType'][$k]['cartInfo']['BillingAddress'] = $NocNocPayInfo['BillingAddress'];
                            $Data['data']['PayType'][$k]['cartInfo']['cardInfo'] = $NocNocPayInfo['CardInfo'];
                        }
                    }
                }

                sort($Data['data']['PayType'][$k]['channel']);
                if(isset($v['channel']) && count($v['channel']) > 1){
                    foreach ($v['channel'] as $k1=>$v1){
                        if(!isset($v1['restriction'])){
                            unset($Data['data']['PayType'][$k]['channel'][$k1]);
                        }else{
                            $Data['data']['PayType'][$k]['channel'] = $v1;
                        }
                    }
                    //sort($Data['data']['PayType'][$k]['channel']);
                    $Data['data']['PayType'][$k]['channel'] = $Data['data']['PayType'][$k]['channel'];
                }else{
                    $Data['data']['PayType'][$k]['channel'] = $Data['data']['PayType'][$k]['channel'][0];
                }
            }
        }
        /**暂时关闭COD
        $CODPayInfo = array();
        //判断所在国家支不支持货到付款
        if(config('support_cod_country') && is_array(config('support_cod_country'))){
        if(in_array($Country,config('support_cod_country'))){
        $CODPayInfo[] = array(
        'payname' => 'COD',
        'defaultImg' => '',
        'selectedImg' => '',
        'introduction' => '',
        'channel' => array(
        'channelName' => 'COD',
        'channelId' => 1
        ),
        'IconImg' => '',
        'status' => 1,
        );
        }
        }
         */
        $TmpPayType = array();
        for ($i = 0; $i < count($Data['data']['PayType']); $i++){
            foreach ($Data['data']['PayType'] as $k => $v){
                if($v['payname'] == 'CreditCard'){
                    $TmpValue = $v;
                    $TmpValue['isSelect'] = 1;
                    $TmpPayType[0] = $TmpValue;
                }
                if($v['payname'] == 'Boleto-Astropay'){
                    $TmpPayType[1] = $v;
                }
                if($v['payname'] == 'Transfer-Astropay'){
                    $TmpPayType[2] = $v;
                }
                if(strtolower($v['payname']) == 'paypal'){
                    //对于paypal的支付做特殊的处理
                    if(in_array($Currency,config('paypal_not_support_currency'))){
                        //$v['change_currency_msg'] = lang('tips_3020006');
                        $v['change_currency_msg'] = 'You will be required to pay with US dollars if you choose paypal!';
                    }
                    $TmpPayType[3] = $v;
                }
            }
        }
        ksort($TmpPayType);
        $CODPayInfo = array();//COD重新开发测试或是做好了再开启 TODO
        $TmpPayInfo = array_merge($TmpPayType,$CODPayInfo);
        if(isset($TmpPayInfo)){
            return $TmpPayInfo;
        }else{
            return false;
        }
    }

    /**
     * 获取astroPay渠道需要的卡的信息
     * @param string $Currency
     * @param string $Country
     * @param string $PayType
     */
    public function getAstroPayCardInfo($Currency,$Country,$PayType){
        /*$NocNocPayInfo = null;
        if(Session::get('nocnoc_payinfo')){
            $NocNocPayInfo = json_decode(Session::get('nocnoc_payinfo'),true);
            $ReturnData['CpfNumber'] = $NocNocPayInfo['cpf'];
            $ReturnData['BankSelect'] = $NocNocPayInfo['card_bank'];
        }*/

        $BankList = array();
        $PayTypeInputName = '';
        switch ($Currency){
            case "MXN":
                $PayTypeInputName = 'CURP/RFC/IFE';
                //墨西哥币种支付的信用卡支付
                $BankList = ['VI','MC'];
                break;
            case "ARS":
                $PayTypeInputName = 'DNI';
                //阿根廷币种支持的信用卡支付
                $BankList = ['VI','MC','AE','CL','NJ','TS','NT','CS','AG'];
                break;
            case "BRL":
                $PayTypeInputName = 'CPF';
                //巴西里尔支持的信用卡支付
                $BankList = ['VI','MC','EL','DC','HI','ML','AE'];
                if($PayType == 'Transfer-Astropay'){
                    //银行卡转帐支持
                    $BankList = ['SB','CA','I','BB','B','H'];
                }else if($PayType == 'Boleto-Astropay'){
                    //线下付款支付的银行
                    $BankList = ['BL'];
                }
                break;
        }
        $ReturnData['BankList'] = $BankList;
        $ReturnData['PayTypeInputName'] = $PayTypeInputName;

        return $ReturnData;
    }

    /**
     * Astropay支付方式转换【为了传给payment，因为数据库保存的类型和payment不一致】
     * @param $payment_method
     * @return string
     */
    public function astropayPaymentMethodTrans($payment_method){
        $result = $payment_method;
        $payment_method = strtolower($payment_method);
        switch ($payment_method){
            case 'creditcard': //CreditCard
                $result = 'AstropayCreditCard';
                break;
            case 'boleto-astropay': //Boleto-Astropay
                $result = 'AstropayBoleto';
                break;
            case 'transfer-astropay': //Transfer-Astropay
                $result = 'AstropayTransfer';
                break;
            default:break;
        }
        return $result;
    }

    /**
     * 编辑用户地址
     * @param int $Uid 用户ID
     * @param string $Params 参数组字符串
     * @return mixed|boolean
     */
    public function editUserAddress($Uid,$Params){
        $Url = MALL_API."/cic/address/saveAddress";
        $Data = doCurl($Url,$Params,null,true);
        return $Data;
    }

    /**
     * BuyNow
     * @param $Params
     * @return mixed
     */
    public function addToCartBuyNow($Params){
        $Uid = $Params['Uid'];
        $UserName = $Params['UserName'];
        $ProductID = $Params['ProductID'];
        $SkuID = $Params['SkuID'];
        $Qty = $Params['Qty'];
        $ShippingMoel = $Params['ShippingMoel'];
        $OldShippingMoel = $Params['OldShippingMoel'];
        $ShippingFee = $Params['ShippingFee'];
        $ShippingDays = $Params['ShippingDays'];
        $ShippModelStatusType = $Params['ShippModelStatusType'];
        $ShipTo = $Params['ShipTo'];
        $Currency = $Params['Currency'];
        $ShippingFeeType = $Params['ShippingFeeType'];
        $Lang = $Params['lang'];
        /*判断缓存里有没有产品信息，如果没有，则去接口请求调出来*/
        $ProductInfo = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency);
        if(!isset($ProductInfo['code']) || $ProductInfo['code'] != 200 || !isset($ProductInfo['data']) || count($ProductInfo['data']) < 1){
            //没有找到数据
            $Return['code'] = 0;
            $Return['msg'] = 'Product data is error!';
            return $Return;
        }
        $ProductInfo = $ProductInfo['data'];
        sort($ProductInfo['Skus']);
        $_product_price = $ProductInfo['Skus'][0]['SalesPrice'];
        $StoreID = $ProductInfo['StoreID'];//
        $ProductUnit = isset($ProductInfo['SalesUnitType'])?$ProductInfo['SalesUnitType']:'';
        if(isset($ProductInfo['Skus'])){
            $_sku_info = $this->CommonService->processSkuInfo($ProductInfo['Skus'],$SkuID);
        }else{
            $Return['code'] = 2010003;
            $Return['msg'] = 'sku data is error';
            return $Return;
        }
        $AttrsDesc = isset($_sku_info['attr_desc'])?$_sku_info['attr_desc']:'';

        $NewGoodsData['StoreID'] = isset($ProductInfo['StoreID'])?$ProductInfo['StoreID']:0;
        $NewGoodsData['StoreName'] = '';
        $NewGoodsData['ProductID'] = $ProductID;
        $NewGoodsData['SkuID'] = $SkuID;
        $NewGoodsData['ProductTitle'] = $ProductInfo['Title'];
        $NewGoodsData['ProductImg'] = isset($ProductInfo['ImageSet']['ProductImg'][0])?$ProductInfo['ImageSet']['ProductImg'][0]:'';
        $NewGoodsData['Qty'] = $Qty;
        $NewGoodsData['Currency'] = $Currency;
        $NewGoodsData['ShippingMoel'] = $ShippingMoel;
        $NewGoodsData['OldShippingMoel'] = $OldShippingMoel;
        $NewGoodsData['ShippingFee'] = $ShippingFee;
        $NewGoodsData['ShippingFeeType'] = $ShippingFeeType;
        $NewGoodsData['ShippingDays'] = $ShippingDays;
        $NewGoodsData['ShippModelStatusType'] = $ShippModelStatusType;
        $NewGoodsData['ShipTo'] = $ShipTo;
        $NewGoodsData['ProductUnit'] = $ProductUnit;
        $NewGoodsData['IsChecked'] = 1;
        $NewGoodsData['IsBuy'] = 1;
        $NewGoodsData['CreateOn'] = time();
        $NewGoodsData['AttrsDesc'] = $AttrsDesc;
        $NewGoodsData['IsPutInStorage'] = 0;//是否已入库,0代表未入库,1代表已入库
        $NewGoodsData['Weight'] = isset($ProductInfo['PackingList']['Weight'])?$ProductInfo['PackingList']['Weight']:'';//单个重量
        $NewGoodsData['FirstCategory'] = isset($ProductInfo['FirstCategory'])?$ProductInfo['FirstCategory']:0;
        $NewGoodsData['SecondCategory'] = isset($ProductInfo['SecondCategory'])?$ProductInfo['SecondCategory']:0;
        $NewGoodsData['ThirdCategory'] = isset($ProductInfo['ThirdCategory'])?$ProductInfo['ThirdCategory']:0;
        $NewGoodsData['BrandId'] = isset($ProductInfo['BrandId'])?$ProductInfo['BrandId']:0;
        $NewGoodsData['ProductType'] = isset($ProductInfo['ProductType'])?$ProductInfo['ProductType']:0;
        $NewGoodsData['IsMvp'] = isset($ProductInfo['IsMVP'])?$ProductInfo['IsMVP']:0;
        $NewGoodsData['LinkUrl'] = MALLDOMAIN.'/p/'.$ProductID;
        //获取阶梯价的单价
        if($ProductInfo){
            $_product_price_info = $this->CommonService->getProductPrice($ProductInfo,$SkuID,$Qty);
            if(isset($_product_price_info['code']) && $_product_price_info['code']){
                $_product_price = $_product_price_info['product_price'];
            }
        }

        $NewGoodsData['ProductPrice'] = $_product_price;
        $NewGoodsData['enable_select_active'] = isset($_product_price_info['enable_select_active'])?$_product_price_info['enable_select_active']:array();;

        $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID] = $NewGoodsData;
        $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['StoreName'] = isset($ProductInfo['StoreName'])?$ProductInfo['StoreName']:'';
        $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['CustomerName'] = $UserName;
        $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['StoreUrl'] = MYDXINTERNS.'/message/sendMessageSeller/seller_id/'.$ProductInfo['StoreID'];

        $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);

        $Return['code'] = 1;
        $Return['data'] = 'success';
        return $Return;
    }


}
