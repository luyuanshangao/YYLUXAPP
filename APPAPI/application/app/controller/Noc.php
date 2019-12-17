<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\services\CommonService;
use app\app\services\NocService;
use think\Log;

/**
 * 开发：tinghu.liu
 * 功能：NOCNOC功能类
 * 时间：2018-11-01
 */
class Noc extends AppBase
{
    private $NocService = null;
    private $CommonService = null;
    public function __construct(){
        parent::__construct();
        $this->NocService = new NocService();
        $this->CommonService = new CommonService();
    }

    /**
     * 填写TaxId的时候提交的动作(前端输入taxID后触发的操作)
     * 根据taxId获取NOCNOC返回的数据，
     * 需要缓存这个TaxId
     * 返回给前端的是NOCNOC返回的正确与错误信息
     * 如果返回正确，则直接调用submotOrder
     * 如果返回错误，把错误信息保存下来，前端用户可以重新输入TaxId，或者返回cart页面修改商品信息
     * @return json
     */
    public function checkNocNocByTaxId(){
        $_tax_id = input('tax_id');
        //用户ID
        $CustomerId = input('customer_id');
        $result = $this -> addCustomerTaxId($_tax_id,$CustomerId);

        if($result && is_array($result)){
            if(isset($result['code']) && $result['code'] != 200){
                if($result['code'] == 101 || $result['code'] == 102){
                    $ReturnData['code'] = 0;
                    $ReturnData['msg'] = $result['msg'];
                    return json($ReturnData);
                }else{
                    return $result;
                }
            }
        }

        $NocParams['customer_id'] = $CustomerId;
        $NocParams['tax_id'] = $_tax_id;
        $NocParams['address_id'] = input('address_id');;
        //需要在询价成功才写入cookie？？？？
        //Cookie::set("nocnoc_tax_id",$_tax_id);
        //选择地址的邮编
        $NocParams['zipcode'] = input('zipcode');
        $_is_buynow =input('IsBuyNow');
        if($_is_buynow){
            $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCartBuyNow_".$CustomerId);
        }else{
            $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_CheckOut".$CustomerId);
        }
        foreach ($_cart_info[$CustomerId]['StoreData'] as $k3 => $v3) {
            foreach ($v3['ProductInfo'] as $k4 => $v4) {
                foreach ($v4 as $k5 => $v5) {
                    $NocParams['country'] = $_cart_info[$CustomerId]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShipTo'];
                    break 3;
                }
            }
        }
        $res = $this->NocService->claNocNocData($NocParams, $_cart_info, 4);
        Log::record('checkNocNocByTaxId$CustomerId'.json_encode($_cart_info));
        if($_is_buynow){
            $this->CommonService->loadRedis()->set("ShoppingCartBuyNow_".$CustomerId, $_cart_info);
        }else{
            $this->CommonService->loadRedis()->set("ShoppingCart_CheckOut".$CustomerId, $_cart_info);
        }
        $ShoppingCartBuyNow_=$this->CommonService->loadRedis()->get("ShoppingCartBuyNow_".$CustomerId);

        if(!empty($res['code'])&&($res['code']== 1)){
            $ReturnData['code'] = 200;
            $ReturnData['data'] = url('/checkout');
            $ReturnData['msg'] = '';
        }else{
            $ReturnData['code'] = 10000;
            $ReturnData['data'] =[];
            $ReturnData['msg'] = !empty($res['msg'])?$res['msg']:'';
        }
        return json($ReturnData);
    }

    /**
     * 新增用户的TaxId
     * @return array
     */
    private function addCustomerTaxId($_tax_id,$CustomerId){
        $strlen =strlen($_tax_id);
        if(empty($_tax_id) || $strlen<1 || $strlen > 45){
            $data['msg'] ='CUIT/CUIL error';
            $data['code'] =101;
            return $data;
        }

        $pattern='/^([a-z_A-Z-.+0-9]+)$/';
        $rs=preg_match($pattern, $_tax_id);
        if(!$rs){
            $msg = 'CUIT/CUIL error';
            $data['code'] =102;
            $data['msg'] =$msg;
            return $data;
        }

        $result = $this->NocService->editCustomerTaxIdService($CustomerId,$_tax_id);
        return $result;
    }


    /**
     * 支付页面计算NOCNOC运费
     */
    public function CheckoutProductInfoNocProcess(){
        $CustomerId = input('customer_id');
        if(!$CustomerId){
            $CustomerId = $this->guestUniquenessIdentify;
        }
        $_tax_id = input("tax_id")?input("tax_id"):'';
        /*if(empty($_tax_id) || $_tax_id==''){
            $ReturnData['code'] = 0;
            $ReturnData['msg'] =  Lang::get('tips_3060005');
            return json($ReturnData);
        }*/
        //$Country = input("country")?input("country"):$this->checkoutCountry;
        $Country = input("country")?input("country"):'';
        $BuyNow = input("IsBuyNow")?input("IsBuyNow"):'';
        if($BuyNow){
            $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCartBuyNow_".$CustomerId);
        }else{
            $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_CheckOut".$CustomerId);
        }
        //$_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_CheckOut".$CustomerId);

        $params['customer_id'] = $CustomerId;
        $params['country'] = $Country;
        $params['tax_id'] = $_tax_id;
        //选择地址的邮编
        $params['zipcode'] = input("zipcode")?input("zipcode"):'';
        $params['email'] = input("email")?input("email"):'';
        $params['full_name'] = input("user_name")?input("user_name"):'';
        $params['address_id'] = input("address_id")?input("address_id"):'';;
        /**需要判断当前币种是否是我们的实收币种，如果不是，则强制转成美元来收取*/
        $IsPaypalQuick = 0;
        $PayType = input('pay_type')?input('pay_type'):'';
        $Currency = input("currency")?input("currency"):'';
        if(strtolower($PayType) != 'paypal' || $IsPaypalQuick ){
            /**如果支付方式是非paypal的(包括没有传支付方式过来的，比如刚加载的时候)，要获取我们dx支付的币种进行比对，
             * 不在其中的，全部切换成USD
             */
            if($IsPaypalQuick){
                /**如果是paypal快捷支付的，验证是否在paypal不支持的数据里，也就是ARS与BRL*/
                if(
                    in_array($Currency,config('paypal_not_support_currency'))
                    || !in_array($Currency,config('paypal_support_currency'))
                ){
                    $Currency = 'USD';
                }
            }else{
                if(!in_array($Currency,config('dx_support_currency'))){
                    $Currency = 'USD';
                }
            }
        }else{
            /**如果是paypal的支付方式的，要获取我们和paypal签订的币种进行比对，
             * 不在其中的全部切成USD
             */
            if(!in_array($Currency,config('paypal_support_currency'))){
                $Currency = 'USD';
            }
        }
        $params['currency'] = $Currency;

        $res = $this->NocService->claNocNocData($params,$_cart_info, 4);
        if($BuyNow){
            $this->CommonService->loadRedis()->set("ShoppingCartBuyNow_".$CustomerId, $_cart_info);
        }else{
            $this->CommonService->loadRedis()->set("ShoppingCart_CheckOut".$CustomerId, $_cart_info);
        }
        if(!empty($res['code'])&&($res['code']== 1)){
            $data=[];
            if(!empty($res['data'])){
                foreach($res['data'] as $key=>$v){
                    $da=$v;
                    $da['store_id']=(int)$key;
                    $data[]=$da;
                }
            }
            $ReturnData['code'] = 200;
            $ReturnData['data'] = $data;
            $ReturnData['msg'] = '';
            }else{
                $ReturnData['code'] = 0;
                $ReturnData['data'] =[];
                //$ReturnData['msg'] = 'Sorry, the purchase of NOCNOC service failed, reason:'.$res['msg'].', To purchase, please click <a href="'.url('home/Cart/cart').'">Go to Cart</a>';
                $ReturnData['msg'] = 'Sorry, the purchase of NOCNOC service failed, reason:'.(is_array($res['msg'])?'System error.':$res['msg']);
                Log::record('CheckoutProductInfoNocProcess$params'.json_encode($params).'$res'.json_encode($res));
            }
        return json($ReturnData);
    }
}
