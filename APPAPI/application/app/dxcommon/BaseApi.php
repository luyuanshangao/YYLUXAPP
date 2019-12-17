<?php
namespace app\app\dxcommon;

use app\admin\statics\BaseFunc;
use think\Controller;
use think\Log;

/**
 * 基础API类
 * Class BaseApi
 * @author tinghu.liu
 * @date 2018-04-23
 * @package app\admin\dxcommon
 */
class BaseApi extends API
{

    /*
     * 获取验证码
     * */
    public function createVerificationCode($data){
        $url = config('api_base_url')."/share/VerificationCode/createVerificationCode".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
     * 验证验证码
     * */
    public function checkVerificationCode($data){
        $url = config('api_base_url')."/share/VerificationCode/checkVerificationCode".self::$access_token_str;
        $data=json_encode($data);
        //echo $data;
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取优惠券列表
     * @param $data
     * @return string
     */
    public function getCouponList($data){
        $url = CIC_API."cic/MyCoupon/getCouponList".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取优惠券列表
     * @param $data
     * @return string
     */
    public function usedCouponByCode($data){
        $url = CIC_API."cic/MyCoupon/usedCouponByCode".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 根据多个couponID获取coupon详情
     * @param $data
     * @return string
     */
    public function getCouponByCouponIds($data){
        $url = API_SHARE_URL."/mallextend/Coupon/getCouponByCouponIds".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取卖家名称
     * @param data
     * @return string
     */
    public function getSellerName($user_ids){
        $url = API_SHARE_URL."/seller/Seller/getSellerName".self::$access_token_str;
        $data['user_ids']=$user_ids;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取订单详情
     * @param $order_id 订单ID
     * @return string
     */
    public function getOrderInfo($data){
        $url = API_URL."/orderfrontend/MyOrder/getOrderInfo".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取用户积分信息
     * @param $data
     * @return string
     */
    public function getPointsBasicInfo($data){
        $url = CIC_API."cic/Points/getPointsBasicInfo".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取积分详情列表
     * @param $data
     * @return string
     */
    public function getPointsDetailsList($data){
        $url = CIC_API."cic/Points/getPointsDetailsList".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取积分详情列表
     * @param $data
     * @return string
     */
    public function IncPoints($data){
        $url = CIC_API."cic/Points/IncPoints".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取优惠券列表
     * @param $data
     * @return string
     */
    public function getMallCouponList($data){
        $url = MALL_API."mallextend/Coupon/getCouponList".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取优惠券详情
     * @param $data
     * @return string
     */
    public function getCouponByCouponId($data){
        $url = MALL_API."mallextend/Coupon/getCouponByCouponId".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 验证支付密码
     * @param $data
     * @return string
     */
    public function confirmPaymentPassword($data){
        $url = CIC_API."cic/Customer/confirmPaymentPassword".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 根据ID获取CouponCode
     * @param $data
     * @return mixed
     */
    public function getCouponCodeByCouponId($data){
        $url = MALL_API."mallextend/Coupon/getCouponCodeByCouponId".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 添加优惠券
     * @param $data
     * @return string
     */
    public function addCoupon($data){
        $url = CIC_API."cic/MyCoupon/addCoupon".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取优惠券数量
     * @param $data
     * @return string
     */
    public function getCouponCount($data){
        $url = CIC_API."cic/MyCoupon/getCouponCount".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 使用优惠券
     * @param $data
     * @return string
     */
    public function usedCoupon($data){
        $url = CIC_API."cic/MyCoupon/usedCoupon".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 扣减用户积分
     * @param $data
     * @return string
     */
    public function decPoints($data){
        $url = CIC_API."cic/Points/DecPoints".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取售后订单配置数据
     * @return string
     */
    public function getAfterSaleConfig($data=[]){
        $url = MALL_API."/orderfrontend/MyOrder/getAfterSaleConfig".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取售后订单列表
     * @param $data
     * @return string
     */
    public function getOrderAfterSaleApplyList($data){
        $url = MALL_API."orderfrontend/OrderAfterSaleApply/getOrderAfterSaleApplyList".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取商城配置数据
     * @param $data
     * @return string
     */
    public function getSysCofig($ConfigName){
        $url = MALL_API."mallextend/SysConfig/getSysCofig".self::$access_token_str;
        $data=['ConfigName'=>$ConfigName];
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取退换货数据
     * @param $data
     * @return string
     */
    public function getOrderAfterSaleApplyInfo($data){
        $url = MALL_API."orderfrontend/OrderAfterSaleApply/getOrderAfterSaleApplyInfo".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取订单基础信息
     * @param $where
     * @return string
     */
    public function getOrderBasics($where,$customer_id){
        $url = MALL_API."/orderfrontend/MyOrder/getOrderBasics".self::$access_token_str;
        $where['customer_id'] = $customer_id;
        $data=json_encode($where);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 获取订单商品
    * */
    public function getOrderItem($data){
        $url = MALL_API."orderfrontend/MyOrder/getOrderItem".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 添加订单售后接口
     * @param array $params 参数
     * @return string
     */
    public function saveOrderAfterSaleApply(array $params){
        $url = MALL_API."/orderfrontend/OrderAfterSaleApply/saveOrderAfterSaleApply".self::$access_token_str;
        $data=json_encode($params);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取订单设置
     * @param $order_id
     * @return string
     */
    public function getOrderConfig($config_key){

        /*判断缓存是否存在*/
        $cache_key = "OrderConfig_".$config_key;

//        if(Rcache($cache_key)){
//            $data = Rcache($cache_key);
//        }else{
            $url = MALL_API."share/OrderConfig/getOrderConfig".self::$access_token_str;
            $data =  json_decode(BaseFunc::http_post_json($url, json_encode(['config_key'=>$config_key])), true);

            if(isset($data['code']) && $data['code'] == 200){
                $data['data'] = array_values($data['data']);
                Rcache($cache_key,$data,['expire'=>3600]);
            }else{
                Log::write("getOrderConfig Error,url:".$url.",param:".json_encode(['config_key'=>$config_key]));
            }
    //    }
        return $data;
    }

    /**
     * 获取订单投诉
     * @param $data
     * @return string
     */
    public function getOrderAccuseList($data){
        $url = MALL_API."orderfrontend/OrderAccuse/getOrderAccuseList".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 添加订单投诉
     * @param $data
     * @return string
     */
    public function saveOrderAccuse($data){
        $url = MALL_API."orderfrontend/OrderAccuse/saveOrderAccuse".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 添加退货快递单数据
     * @param $data
     * @return string
     */
    public function addReturnProductExpressage($data){
        $url = MALL_API."orderfrontend/OrderAfterSaleApply/addReturnProductExpressage".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取收藏分类ID
     * @param $data
     * @return string
     */
    public function getWishCategoryID($data){
        $url = CIC_API."cic/MyWish/getWishCategoryID".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 根据分类ID数据获取对应分类信息
     * @param array $param
     * @return mixed
     */
    public function getCategoryDataByCategoryIDData(array $param){
        $url = MALL_API."mallextend/ProductCategory/getCategoryDataByCategoryIDData".self::$access_token_str;
        $data=json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 根据分类ID数据获取对应分类信息
     * @param array $param
     * @return mixed
     */
    public function addGroup(array $param){
        $url = CIC_API."cic/Group/add".self::$access_token_str;
        unset($param['access_token']);
        $data=json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function indexGroup(array $param){
        $url = CIC_API."cic/Group/index".self::$access_token_str;
        unset($param['access_token']);
        $data=json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function saveGroup(array $param){
        $url = CIC_API."cic/Group/save".self::$access_token_str;
        unset($param['access_token']);
        $data=json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function delGroup(array $param){
        $url = CIC_API."cic/Group/del".self::$access_token_str;
        unset($param['access_token']);
        $data=json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getWishList(array $param){
        $url = CIC_API."cic/Group/getWishList".self::$access_token_str;
        unset($param['access_token']);
        $data=json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 增加订单消息
     * @param $data
     * @return string
     */
    public function addOrderMessage($data){
        $url = MALL_API."orderfrontend/OrderMessage/addOrderMessage".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取订单消息
     * @param $data
     * @return string
     */
    public function getOrderMessage($data){
        $url = MALL_API."orderfrontend/OrderMessage/getOrderMessage".self::$access_token_str;
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 用用户ID获取email
    * */
    public function getSubscriber($ID,$type=1){
        $url = CIC_API."/cic/Subscriber/getSubscriber".self::$access_token_str;

        $data=json_encode(['CustomerId'=>$ID,'type'=>$type]);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 判断用户是否订阅
    * */
    public function checkSubscriber($data){
        $url = CIC_API."/cic/Subscriber/checkSubscriber".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /**
     * 获取配置信息
     * @param $config_key
     * @return string
     */
    public function getBaseConfig($config_key){
        $url = MALL_API."share/BaseConfig/getBaseConfig".self::$access_token_str;
        $data=json_encode(['config_key'=>$config_key]);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 新增订阅
    * */
    public function addSubscriber($data){
        $url = CIC_API."/cic/Subscriber/addSubscriber".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
     * 取消订阅
     * */
    public function cancelSubscriber($data){
        $url = CIC_API."/cic/Subscriber/cancelSubscriber".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 登陆
    * */
    public function login($data){
        $url = CIC_API."/cic/Customer/LoginForToken".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 注册
    * */
    public function register($data){
        $url = CIC_API."/cic/Customer/registerCustomer".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    /*
    * 注册
    * */
    public function validateCustomer($data){
        $url = CIC_API."/cic/Customer/validateCustomer".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function addCustomerOther($data){
        $url = CIC_API."/cic/Customer/addCustomerOther".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function LoginForToken($data){
        $url = CIC_API."/cic/Customer/LoginForToken".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getCustomerByToken($data){
        $url = CIC_API."/cic/Customer/getCustomerByToken".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function addLoginHistory($data){
        $url = CIC_API."/cic/Customer/addLoginHistory".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function updatePhotoPath($data){
        $url = CIC_API."/cic/Customer/updatePhotoPath".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function saveProfile($data){
        $url = CIC_API."/cic/Customer/saveProfile".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function GetCustomerInfoByAccount($data){
        $url = CIC_API."/cic/Customer/GetCustomerInfoByAccount".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getCustomerByID($data){
        $url = CIC_API."/cic/Customer/getCustomerByID".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function GetEmailsByCIDs($data){
        $url = CIC_API."/cic/Customer/GetEmailsByCIDs".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getEmailsByCID($data){
        $url = CIC_API."/cic/Customer/getEmailsByCID".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function changePassword($data){
        $url = CIC_API."/cic/Customer/changePassword".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function changepasswordHistory($data){
        $url = CIC_API."/cic/Customer/changepasswordHistory".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function saveCreditCard($data){
        $url = CIC_API."/cic/CreditCard/saveCreditCard".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getCreditCard($data){
        $url = CIC_API."/cic/CreditCard/getCreditCard".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function delCreditCard($data){
        $url = CIC_API."/cic/CreditCard/delCreditCard".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function addPoints($data){
        $url = CIC_API."/cic/CreditCard/addPoints".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function checkPaymentPassword($data){
        $url = CIC_API."/cic/Customer/checkPaymentPassword".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function savePaymentPassword($data){
        $url = CIC_API."/cic/Customer/savePaymentPassword".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function addSystemLog($data){
        $url = CIC_API."/cic/Customer/addSystemLog".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function addErrorLog($data){
        $url = CIC_API."/cic/Customer/addErrorLog".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getAddress($data){
        $url = CIC_API."/cic/Address/getAddress".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function saveAddress($data){
        $url = CIC_API."/cic/Address/saveAddress".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function delAddress($data){
        $url = CIC_API."/cic/Address/delAddress".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function setDefault($data){
        $url = CIC_API."/cic/Address/setDefault".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getDefaultAddres($data){
        $url = CIC_API."/cic/Address/getDefaultAddres".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function AddCreditCard($data){
        $url = CIC_API."/cic/CreditCard/AddCreditCard".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function UpdateTokenStatus($data){
        $url = CIC_API."/cic/CreditCard/UpdateTokenStatus".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function GetCreditCardById($data){
        $url = CIC_API."/cic/CreditCard/GetCreditCardById".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function isWish($data){
        $url = CIC_API."/cic/MyWish/isWish".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function getWishProductList($data){
        $url = CIC_API."/cic/MyWish/getWishProductList".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function delWish($data){
        $url = CIC_API."/cic/MyWish/delWish".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

    public function addWish($data){
        $url = CIC_API."/cic/MyWish/addWish".self::$access_token_str;
        unset($data['access_token']);
        $data=json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $data), true);
    }

}
