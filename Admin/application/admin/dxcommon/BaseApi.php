<?php
namespace app\admin\dxcommon;
use think\Log;
use app\admin\dxcommon\API;
/**
 * 基础API静态类
 * Class BaseApi
 * @author zhangheng
 * @date 2018-03-25
 * @package app\admin\common
 */
class BaseApi extends API
{

    // const access_token_str = '';
    // const access_token = Api_token();//api秘钥
    // public $access_token = '';
    // public function __construct()
    // {

    //     // parent::__construct();
    //    $this->access_token_str = Api_token();
    //     // self::$access_token_str = Api_token();
    // }

    /**
     * 获取区域数据--全部国家数据（不包含中国）
     * @return string
     */
    public static function getRegionData_AllCountryData($areaID='',$codeOrName=''){

    	if(!empty($codeOrName)){
    		//$codeOrName = $search['CodeOrName'];
    	}//dump($AreaID);exit;
        // dump(config('api_base_url').'/share/region/getRegion?access_token='.Api_token());
        return json_decode(curl_request(config('api_base_url').'/share/region/getRegion?access_token='.Api_token()
        		,['AreaID'=>$areaID]

        		), true);
    }

    /**
     * 获取供应商数据
     * @return string
     */
    public static function getSellerData($search=''){
    	//var_dump($search);
    	if(!empty($search)){
            // http://api.dxinterns.com/seller/seller/lists?access_token=1
            // $data =curl_request(config('api_base_url').'/seller/seller/lists'.self::$access_token_str,$search);
    		//$codeOrName = $search['CodeOrName'];
    	}else{

        }
        $data =curl_request(config('api_base_url').'/seller/seller/lists?access_token='.config('access_token'),$search);//dump($data);
        // $data =curl_request(config('api_base_url').'/seller/seller/lists'.self::$access_token_str,$search);//dump($data);
    	//var_dump($data);
    	return json_decode($data,true);
    }

     /**
     * 获取产品列表
     * @return string
     * auther wang   2018-04-01
     */
    public static function productList($data){
            $url = config('api_base_url')."mallextend/product/lists";
            $data['access_token'] = config('access_token');
            return json_decode(curl_request($url,$data), true);
            // $data['access_token'] = config('access_token');
            // return json_decode(curl_request(config('product_list'),$data), true);
    }

    /*
     * 由供应商ID获取详细数据
     */
    public static function getSellerByID($id){

    	$search['user_id'] = $id;
    	$data =curl_request(config('api_base_url').'/seller/seller/get?access_token='.config('access_token'),$search);
    	//var_dump($data);
    	return json_decode($data,true);
    }
    /**
     * 商户修改
     * @return string
     * auther wang   2018-04-01
     */
    public static function merchantEdit($data=''){
            if($data){
                $merchant             = config('merchant');
                $data['access_token'] = config('access_token');
                $url =config('api_base_url').$merchant['url'];
                //var_dump($url);
                //die();
                return json_decode(curl_request($url,$data), true);
            }else{
                return json_decode(curl_request(config('product_list'),['access_token'=>config('access_token'),'page_size'=>15,'page'=>1]), true);
            }
    }
    /**
     * 商户删除
     * @return string
     * auther wang   2018-04-03
     */
    public function MerchantDelete($data){
         $MerchantDelete       = config('MerchantDelete');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($MerchantDelete['url'],$data), true);
    }
    /**
     * 产品状态
     * @return string
     * auther wang   2018-04-03
     */
    public function ProductStatus($data){
         $MerchantDelete       = config('ProductStatus');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($MerchantDelete['url'],$data), true);
    }

    /**
     * 更改产品状态
     * @param $data
     * @return mixed
     */
    public function productChangeStatusPost($data){
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/changeStatus', $data), true);
    }


    /**
     * 商户修改密码
     * @return string
     * auther wang   2018-04-04
     */
    public function reset_password($data){
         $reset_password       = config('reset_password');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($reset_password['url'],$data), true);
    }
     /**
     * 物流添加
     * @return string
     * auther wang   2018-04-08
     */
    public function AddLogistics($data){
         $AddLogistics         = config('AddLogistics');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($AddLogistics['url'],$data), true);
    }
     /**
     * 物流修改
     * @return string
     * auther wang   2018-04-09
     */
    public function EditLogistics($data){
         $EditLogistics        = config('EditLogistics');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($EditLogistics['url'],$data), true);
    }
    /**
     * 物流列表
     * @return string
     * auther wang   2018-04-08
     */
    public function LogisticsList($data){
         $LogisticsList        = config('LogisticsList');
         $data['access_token'] = config('access_token');
         $r =curl_request($LogisticsList['url'],$data);
         //var_dump($r);
         return json_decode($r, true);
    }
    /**
     * 删除物流信息
     * @return string
     * auther wang   2018-04-08
     */
    public function deleteLogistics($data){
         $deleteLogistics        = config('deleteLogistics');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($deleteLogistics['url'],$data), true);
    }
     /**
     * 删除物流信息
     * @return string
     * auther wang   2018-04-08
     */
    public function productExamine($data){
         $productExamine        = config('productExamine');
         $data['access_token'] = config('access_token');
         if($data['ProductStatus'] === 0){
            return json_decode(curl_request($productExamine['product_list'],$data), true);//未审核列表
         }else{
            return json_decode(curl_request($productExamine['url'],$data), true);//审核接口
         }
    }
     /**
     * 会员管理
     * @return string
     * auther wang   2018-04-17
     */
    public static function getCustomerList($data){
         $url = config('cic_api_url')."cic/Customer/getCustomerList";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
    /**
     * 修改会员状态
     * @return string
     * auther wang   2018-04-17
     */
    public static function updateStatus($data){
         $getCustomerList      = config('getCustomerList');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($getCustomerList['urlStatus'],$data), true);
    }
     /**
     * 修改会员状态
     * @return string
     * auther wang   2018-04-21
     */
    public static function langs(){
         $langs      = config('langs');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($langs['url'],$data), true);
    }
    /**
   * [LMS description]
   * 同步LMS系统渠道数据数据接口
   * author: Wang
   * AddTime:2018-04-26
   */
    public static function LMS($data){
         $LMS      = config('LMS');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($LMS['url'],$data), true);
    }

    /**
     * 获取后台配置数据
     * @param array $param 参数条件
     * @return mixed
     */
    public  static function getSysCofig(array $param){
        $url = config('api_base_url').'/mallextend/SysConfig/getSysCofig?access_token='.config('access_token');
        return json_decode(curl_request($url, $param), true);
    }

    /**
     * 根据产品ID获取产品信息
     * @param $product_id 产品ID
     * @return mixed
     */
    public static function getProductInfoByID($product_id){
        $url = config('api_base_url').'/mallextend/Product/getProduct?access_token='.config('access_token');
        $param = ['product_id'=>$product_id];
        return json_decode(curl_request($url, $param), true);
    }


    /**
     * 根据产品ID获取产品信息
     * @param $data 产品ID
     * @return mixed
     */
    public static function getProductInfo($data){
        $url = config('api_base_url').'/mallextend/Product/getProduct?access_token='.config('access_token');
        return json_decode(curl_request($url, $data), true);
    }

    /**
     * 根据分类ID数据获取对应分类信息
     * @param array $param
     * @return mixed
     */
    public static function getCategoryDataByCategoryIDData(array $param){
        $url = config('api_base_url').'/mallextend/ProductCategory/getCategoryDataByCategoryIDData?access_token='.config('access_token');
        return json_decode(curl_request($url, $param), true);
    }

    /**
     * 获取后台订单数据
     * @param array $param
     * @return mixed
     */
    public static function getOrderListForPage(array $param){
    	$url = config('api_base_order_frontend_url').'orderfrontend/OrderQuery/getOrderList';
    	$param['access_token'] =config('access_token');
    	$result = curl_request($url, $param);
    	return json_decode(curl_request($url, $param), true);
    }

    /**
     *获取币种接口
     * author: Wang
     * AddTime:2018-06-12
     */
    public static function getCurrencyList(){
         $getCurrencyList      = config('getCurrencyList');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($getCurrencyList['url'],$data), true);
    }

    /**
     * 获取后台订单数据
     * @param array $param
     * @return mixed
     */
    public static function getOrderDetail(array $param){
    	$url = config('api_base_order_frontend_url').'orderfrontend/OrderQuery/getOrderDetail';
    	$param['access_token'] = config('access_token');

    	return json_decode(curl_request($url, $param), true);
    }

    /**
     * 获取后台订单数据
     * @param array $param
     * @return mixed
     */
    public static function getOrderBasics(array $param){
        $url = config('api_base_order_frontend_url').'orderfrontend/MyOrder/getOrderBasics';
        $param['access_token'] = config('access_token');
        return json_decode(curl_request($url, $param), true);
    }



    /**
     * 获取售后订单数据
     * @param array $param
     * @return mixed
     */
    public static function getOrderAfterSaleApplyInfo(array $param){
        $url = config('api_base_order_frontend_url').'orderfrontend/OrderAfterSaleApply/getOrderAfterSaleApplyInfo';
        $param['access_token'] = config('access_token');

        return json_decode(curl_request($url, $param), true);
    }

    /**
     * 获取退款订单数据
     * @param array $param
     * @return mixed
     */
    public static function getOrderRefundInfo(array $param){
        $url = config('api_base_order_frontend_url').'orderbackend/OrderRefund/getOrderRefundInfo';
        $param['access_token'] = config('access_token');

        return json_decode(curl_request($url, $param), true);
    }

    /**
     * 获取退款订单列表
     * @param array $param
     * @return mixed
     */
    public static function getOrderRefundList(array $param){
        $url = config('api_base_order_frontend_url').'orderbackend/OrderRefund/getOrderRefundList';
        $param['access_token'] = config('access_token');

        return json_decode(curl_request($url, $param), true);
    }

    public static function orderStatus($data=array()){
         $orderStatus      = config('orderStatus');
         $data['access_token'] =config('access_token');
         return json_decode(curl_request(config('api_base_order_frontend_url').$orderStatus['url'],$data), true);
    }
    /**
     * 投诉管理
     * [orderAccuse description]
     * @param  array  $data [description]
     * @return [type]       [description]
     * @author wang   2018-06-23
     */
    public static function orderAccuse($data = array()){
         $orderAccuse      = config('orderAccuse');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request(config('api_base_order_frontend_url').$orderAccuse['url'],$data), true);
    }

    /**
     * 退换货款管理
     * [orderAccuse description]
     * @param  array  $data [description]
     * @return [type]       [description]
     * @author wang   2018-06-25
     */
    public static function orderRefund($data = array()){
         $orderRefund      = config('orderRefund');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request(config('api_base_order_frontend_url').$orderRefund['url'],$data), true);
    }
    /**
     * 退换货款管理之订单导出
     * [orderAccuse description]
     * @param  array  $data [description]
     * @return array     [description]
     * @author yxh   2019-02-14
     */
    public static function orderRefundExcel($data = array()){
        $url='orderbackend/OrderRefund/getOrderLists';
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_order_frontend_url').$url,$data), true);
    }
    /**
     * ApiConfig  部分配置信息
     */
    public static function  apiConfig($data = array()){
         $apiConfig      = config('apiConfig');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request(config('api_base_order_frontend_url').$apiConfig['url'],$data), true);
    }
     /**
     * ApiConfig  部分配置信息
     */
    public static function afterSaleDetails($data = array()){
         $afterSaleDetails     = config('afterSaleDetails');
         $data['access_token'] = config('access_token');
         return json_decode(curl_request(config('api_base_order_frontend_url').$afterSaleDetails['url'],$data), true);
    }

    /**
     * 后台获取退款详情
     * add 20190509 kevin
     */
    public static function getAdminOrderRefundInfo($data = array()){
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_url').'orderbackend/OrderRefund/getAdminOrderRefundInfo',$data), true);
    }

     /**
     * 获取风控配置
     * @param array $param
     * @return mixed
     */
    public static function RiskConfig(){
        $RiskConfig     = config('RiskConfig');
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_order_frontend_url').$RiskConfig['url'],$data), true);
        // return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }
    /**
     * 仲裁管理
     * [arbitration description]
     * @return [type] [description]
     * @author wang   2018-08-18
     */
    public static function arbitration($data=array()){
        $arbitration     = config('arbitration');
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_order_frontend_url').$arbitration['url'],$data), true);
    }
    /**
     * 仲裁回复
     * [arbitration description]
     * @return [type] [description]
     * @author wang   2018-08-21
     */
    public static function applyLog($data){
        $applyLog     = config('applyLog');
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_order_frontend_url').$applyLog['url'],$data), true);
    }

    /**
     * 生成文章静态页面
     * [arbitration description]
     * @return
     * @author kevin   2018-08-21
     */
    public static function makeArticle($data){
        $help_url     = config('help_url');
        return curl_request($help_url."MakeHtml/MakeAllArticle",$data);
    }

    /**
     * 生成关于我们页面
     * [arbitration description]
     * @return
     * @author kevin   2018-12-19
     */
    public static function MakeAllAboutUs($data){
        $help_url     = config('help_url');
        return json_decode(curl_request($help_url."MakeHtml/MakeAllAboutUs",$data), true);
    }
    /**
     * 关税赔宝
     * [CustomsInsurance description]
     * @param [type] $data [description]
     * @author wang   2018-09-08
     */
    public static function CustomsInsurance($data){
        $url = config('api_base_url').'orderfrontend/OrderQuery/CustomsInsurance';
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);

        // $CustomsInsurance     = config('CustomsInsurance');
        // $data['access_token'] = config('access_token');
        // return json_decode(curl_request(config('api_base_url').$CustomsInsurance['url'],$data), true);
    }


    /*
     * 获取反馈详情
     * */
    public static function getReportInfo($data){
        $url = config('api_base_url').'admin/Reports/getReportInfo';
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 增加报告信息
     * @param $data
     * @return mixed
     */
    public static function addReports($data){
        $url = config('api_base_url')."admin/Reports/addReports";
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 增加报告信息
     * @param $data
     * @return mixed
     */
    public static function addReportsforAdmin($data){
        $url = config('api_base_url')."admin/Reports/addReportsforAdmin";
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 更新报告信息
     * @param $data
     * @return mixed
     */
    public static function updateReportsforAdmin($data){
        $url = config('api_base_url')."admin/Reports/updateReportsforAdmin";
        return json_decode(curl_request($url,$data), true);
    }


    /**
     * 后台退款
     * [refundOrder description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function refundOrder($data){
        $refundOrder['url']     = "orderbackend/OrderRefund/refundOrder";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_url').$refundOrder['url'],$data), true);
    }
     /**
     * 后台批量退款
     * [refundOrder description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function AdminrefundOrder($data){
        $refundOrder['url']     = "orderbackend/Order/AdminrefundOrder";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_url').$refundOrder['url'],$data), true);
    }
    /**
     * EDM营销  查询
     * [order_query description]
     * @return [type] [description]
     * @author wang   2018-09-27
     */
    public static function order_query($data){
        $order_query['url']     = 'orderfrontend/OrderQuery/order_query';
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_url').$order_query['url'],$data), true);
    }
    /**
     * 到seller获取商铺名
     * [shop_name description]
     * @return [type] [description]
     */
    public static function shop_name($data){
        $shop_name     = config('shop_name');
        $data['access_token'] = config('access_token');
        return json_decode(curl_request(config('api_base_url').$shop_name['url'],$data), true);
    }

    /**
     * 获取订单状态
     * @param int $code 订单状态码
     * @return mixed
     */
    public static function getOrderStatus($code=-1){
        //$rtn = config('order_status_data');
        $rtn = [];
        $base_api = new BaseApi();
        $status_data = $base_api->getSysCofig(['ConfigName' => 'OrderStatusView']);
        if (!empty($status_data['data'])){
            /** 订单状态处理 start **/
            $OrderStatusViewStr = explode(";", $status_data);
            if(!empty($OrderStatusViewStr)){
                foreach ($OrderStatusViewStr as $key=>$value){
                    $OrderStatusViewArr[$key] = explode(":",$OrderStatusViewStr[$key]);
                    if($OrderStatusViewArr){
                        $rtn[$key]['code'] = $OrderStatusViewArr[$key][0];
                        $NameValue = explode('-',$OrderStatusViewArr[$key][1]);
                        $rtn[$key]['en_name'] = $NameValue[0];
                        $rtn[$key]['name'] = $NameValue[1];
                    }
                }
            }
            /** 订单状态处理 end **/
        }else{
            $rtn = config('order_status_data');
        }
        if ($code !== -1 && is_numeric($code)){
            $tem = [];
            foreach ($rtn as $key=>$val) {
                if ($val['code'] == $code){
                    $tem = $rtn[$key];
                    continue;
                }
            }
            $rtn = $tem;
        }
        return $rtn;
    }

    /**
     * 获取售后订单配置数据
     * @return string
     */
    public static function getAfterSaleConfig(){
        $url = config('api_base_url')."/orderfrontend/MyOrder/getAfterSaleConfig";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 添加订单售后接口
     * @param array $params 参数
     * @return string
     */
    public static function saveOrderAfterSaleApply(array $params){
        $url = config('api_base_url')."/orderfrontend/OrderAfterSaleApply/saveOrderAfterSaleApply";
        //$params['access_token'] = config('access_token');
        return json_decode(curl_request($url,$params), true);
    }

    /**
     * 修改订单售后接口
     * @param array $params 参数
     * @return string
     */
    public static function updateApplyData(array $params){
        $url = config('api_base_url')."/orderfrontend/OrderAfterSaleApply/updateApplyData";
        return json_decode(curl_request($url,$params), true);
    }

    /**
     * 获取订单退款总数接口
     * @param array $params 参数
     * @return string
     */
    public static function getRefundedAmount(array $params){
        $url = config('api_base_url')."/orderfrontend/MyOrder/getRefundedAmount";
        //$params['access_token'] = config('access_token');
        return json_decode(curl_request($url,$params), true);
    }

    /**
     * 获取后台用户详情
     * @return string
     * auther kevin   2018-10-16
     */
    public static function getAdminCustomerInfo($params){
        $url = config('cic_api_url')."/cic/Customer/getAdminCustomerInfo";
        $params['access_token'] = config('access_token');
        return json_decode(curl_request($url,$params), true);
    }

    /**
     * 获取后台用户详情
     * @return string
     * auther kevin   2018-10-16
     */
    public static function getAdminCustomerData($params){
        $url = config('cic_api_url')."/cic/Customer/getAdminCustomerData";
        $params['access_token'] = config('access_token');
        return json_decode(curl_request($url,$params), true);
    }


    /**
     * 获取后台用户地址
     * @return string
     * auther kevin   2018-10-16
     */
    public static function getAddress($params){
        $url = config('cic_api_url')."/cic/Address/getAddress";
        $params['access_token'] = config('access_token');
        return json_decode(curl_request($url,$params), true);
    }


    /**
     * 获取用户积分信息
     * @param $data
     * @return string
     */
    public static function getPointsBasicInfo($data){
        $url = config('cic_api_url')."cic/Points/getPointsBasicInfo";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取积分详情列表
     * @param $data
     * @return string
     */
    public static function getPointsDetailsList($data){
        $url = config('cic_api_url')."cic/Points/getPointsDetailsList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取SC详情列表
     * @param $data
     * @return string
     */
    public static function getStoreCreditDetailsList($data){
        $url = config('cic_api_url')."cic/StoreCredit/getStoreCreditDetailsList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取订阅
     * @param $data
     * @return string
     */
    public static function getSubscriber($data){
        $url = config('cic_api_url')."cic/Subscriber/getSubscriber";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取订阅
     * @param $data
     * @return string
     */
    public static function saveMemberProfile($data){
        $url = config('cic_api_url')."cic/Customer/saveProfile";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 添加修改会员密码信息
     * @param $data
     * @return string
     */
    public static function changepasswordHistory($data){
        $url = config('cic_api_url')."cic/Customer/changepasswordHistory";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 设置支付密码
     * @param $data
     * @return string
     */
    public static function savePaymentPassword($data){
        $url = config('cic_api_url')."cic/Customer/savePaymentPassword";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * StoreCredit管理
     * @param $data
     * @return string
     */
    public static function StoreCredit($data){
        $url = config('cic_api_url')."cic/StoreCredit/StoreCredit";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * StoreCredit管理详情
     * @param $data
     * @return string
     */
    public static function StoreCreditDetails($data){
        $url = config('cic_api_url')."cic/StoreCredit/StoreCreditDetails";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     *积分提款审核不通过返回
     * @param $data
     * @return string
     */
    public static function auditWithdrawalReferralPoints($data=array()){
        $url = config('cic_api_url')."cic/Points/auditWithdrawalReferralPoints";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * DxPoints管理
     * @param $data
     * @return string
     */
    public static function DxPoints($data=array()){
        $url = config('cic_api_url')."cic/StoreCredit/DxPoints";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
     /**
     * DxPoints管理
     * @param $data
     * @return string
     */
    public static function DxPointsDetails($data){
        $url = config('cic_api_url')."cic/StoreCredit/DxPointsDetails";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * Affililate管理
     * @param $data
     * @return string
     */
    public static function Affililate($data){
        $url = config('cic_api_url')."cic/StoreCredit/Affililate";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取Affililate佣金数据集合
     * @param $data
     * @return string
     */
    public static function getAffililateCommissionData($data){
        $url = config('api_base_url')."admin/Affiliate/getAffililateCommissionData";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * Affiliate用户统计
     * [AffiliateUserStatistics description]
     */
    public static function AffiliateUserStatistics($data){
        $url = config('cic_api_url')."cic/StoreCredit/AffiliateUserStatistics";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
     /**
     * Affililate管理详情
     * @param $data
     * @return string
     */
    public static function AffililateDetails($data){
        $url = config('cic_api_url')."cic/StoreCredit/AffililateDetails";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * 获取Affililate新用户数量
     * @param $data
     * @return string
     */
    public static  function affiliateIdSum($data){
        $url = config('cic_api_url')."cic/StoreCredit/AffiliateIdSum";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * 付款通过
     * @param $data
     * @return string
     */
    public static function WithdrawStatus($data){
        $url = config('cic_api_url')."cic/StoreCredit/WithdrawStatus";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * 获取seller用户
     * @param $data
     * @return string
     */
    public static function getSendMessageSeller($data){
        $url = config('api_base_url')."seller/Seller/getSendMessageSeller";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    public static function AddNotes($data){
        $url = config('api_base_url')."orderfrontend/OrderMessage/AddNotes";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取跟踪号对应节点
     * [AdminLisLogisticsDetail description]
     * @param [type] $data [description]
     */
    public static  function AdminLisLogisticsDetail($data){
         $url = config('api_base_url')."lis/logisticsDetail/AdminLisLogisticsDetail";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取跟踪号对应节点 列表
     * [AdminLisLogisticsDetail description]
     * @param [type] $data [description]
     */
    public static  function AdminLisLogisticsDetails($data){
        $url = config('api_base_url')."lis/logisticsDetail/AdminLisLogisticsDetails";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * Affiliate用户最后登录时间
     * [lastLoginTime description]
     * @return [type] [description]
     */
    public static function lastLoginTime(){
         $url = config('cic_api_url')."cic/StoreCredit/lastLoginTime";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
    /**
     * 获取affiliate_id对应的cic  id
     * [add_black description]
     * @param [type] $data [description]
     */
    public static function add_black($data){
        $url = config('cic_api_url')."cic/StoreCredit/add_black";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * 更改cic  affiliate_id状态
     * [joinBlacklist description]
     * @return [type] [description]
     */
    public static function joinBlacklist($data){
        $url = config('cic_api_url')."cic/Affiliate/joinBlacklist";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
     /**
     * 移出黑名单
     * [removeBlacklist description]
     * @return [type] [description]
     */
    public static function removeBlacklist($data){
         $url = config('cic_api_url')."cic/Affiliate/removeBlacklist";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
     /**
     * 根据条件获取cic用户信息
     * [removeBlacklist description]
     * @return [type] [description]
     */
    public static function FinancialAudit($data){
         $url = config('cic_api_url')."cic/Affiliate/FinancialAudit";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }

    /**
     * 根据条件获取seller供应商列表
     * @return [type] [description]
     */
    public static function getSellerLists($data){
        $url = config('api_base_url')."seller/Seller/lists";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 根据条件获取店铺列表
     * @return [type] [description]
     */
    public static function getStoreLists($data){
        $url = config('api_base_url')."seller/Seller/getStoreLists";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 人工录入物流信息
     * [orderAccuse description]
     * @param  array  $data [description]
     * @return array     [description]
     * @author yxh   2019-02-14
     */
    public static function addTracking($url,$data = array()){
        $data['access_token'] = config('access_token');
        return json_decode(http_post_json(config('api_base_order_frontend_url').$url,json_encode($data)), true);
    }

    /*
     * 获取后台产品提问列表
     * @param  array  $data [description]
     * @return array     [description]
     * @author kevin   2019-02-21
     * */
    static function getAdminQuestionlist($data){
        $url = config('api_base_url')."seller/Question/getAdminQuestionlist";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
    * 获取后台产品批发询价列表
    * @param  array  $data [description]
    * @return array     [description]
    * @author kevin   2019-02-21
    * */
    static function getAdminWholesaleInquirylist($data){
        $url = config('api_base_url')."seller/WholesaleInquiry/getAdminWholesaleInquirylist";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 修改产品提问列表
     * @param  array  $data [description]
     * @return array     [description]
     * @author kevin   2019-02-22
     * */
    static function updateQuestion($data){
        $url = config('api_base_url')."seller/Question/updateQuestion";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取一条产品提问
     * @param  array  $data [description]
     * @return array     [description]
     * @author kevin   2019-02-22
     * */
    static function getOneQuestion($data){
        $url = config('api_base_url')."seller/Question/getOneQuestion";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 修改产品询价列表
     * @param  array  $data [description]
     * @return array     [description]
     * @author kevin   2019-02-22
     * */
    static function updateWholesaleInquiry($data){
        $url = config('api_base_url')."seller/WholesaleInquiry/updateWholesaleInquiry";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取单条用户提问
     * @param  array  $data [description]
     * @return array     [description]
     * @author kevin   2019-02-22
     * */
    static function getQuestionWhere($data){
        $url = config('api_base_url')."seller/Question/getQuestionWhere";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
 * 获取单条用户提问
 * @param  array  $data [description]
 * @return array     [description]
 * @author kevin   2019-02-22
 * */
    static function getWholesaleInquiryWhere($data){
        $url = config('api_base_url')."seller/WholesaleInquiry/getWholesaleInquiryWhere";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
        * 添加回答
        * @param  array  $data [description]
        * @return array     [description]
        * @author kevin   2019-02-22
        * */
    static function addAnswer($data){
        $url = config('api_base_url')."seller/Question/addAnswer";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取一条产品提问
     * @param  array  $data [description]
     * @return array     [description]
     * @author kevin   2019-02-22
     * */
    static function getOneWholesaleInquiry($data){
        $url = config('api_base_url')."seller/WholesaleInquiry/getOneWholesaleInquiry";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
        * 获取回答问题
        * @param  array  $data [description]
        * @return array     [description]
        * @author kevin   2019-02-22
        * */
    static function addWholesaleInquiryAnswer($data){
        $url = config('api_base_url')."seller/WholesaleInquiry/addWholesaleInquiryAnswer";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

     /**
     * 移出黑名单
     * [removeBlacklist description]
     * @return [type] [description]
     * auther wang  2019-02-20
     */
    public static function ConfigurationInformation($data = array()){
         $url = config('api_base_url')."admin/OrderComplaint/ConfigurationInformation";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
    /**
     * 客服统计报表获取订单信息
     * [OrderInformation description]
     *  auther wang  2019-02-20
     */
    public static function OrderInformation($data = array()){
         $url = config('api_base_url')."orderfrontend/Order/OrderInformation";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }

    /**
     * 客服统计报表获取订单信息
     * [OrderInformation description]
     *  auther wang  2019-03-07
     */
    public static function getAdminReportList($data = array()){
        $url = config('api_base_url')."admin/Reports/getAdminReportList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 客服统计报表获取订单信息
     * [OrderInformation description]
     *  auther wang  2019-03-07
     */
    public static function getAdminReportListForFinancial($data = array()){
        $url = config('api_base_url')."admin/Reports/getAdminReportListForFinancial";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
     /**
     * 判断申请表是否有该记录
     * [OrderInformation description]
     *  auther wang  2019-03-05
     */
    public static function order_after_sale_apply($data = array()){
         $url = config('api_base_url')."orderfrontend/OrderAfterSaleApply/order_after_sale_apply";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
    /**
     * 判断申请表是否有该记录
     * [OrderInformation description]
     *  auther wang  2019-03-05
     */
    public static function order_after_sale_apply_item($data = array()){
         $url = config('api_base_url')."orderfrontend/OrderAfterSaleApply/order_after_sale_apply_item";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }

    /**
     * 判断申请表是否有该记录
     * [OrderInformation description]
     *  auther wang  2019-03-05
     */
    public static function save_order_refund_item($data = array()){
        $url = config('api_base_url')."orderbackend/OrderRefund/save_order_refund_item";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
     /**
     * 判断订单是否已付款
     * [OrderInformation description]
     *  @auther wang  2019-03-08
     */
    public static function OrderDetection($data = array()){
         $url = config('api_base_url')."orderfrontend/OrderAfterSaleApply/OrderDetection";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
    /**
     * 关闭订单
     * [OrderInformation description]
     *  @auther wang  2019-03-18
     */
    public static function OrderShutDown($data = array()){
         $url = config('api_base_url')."orderfrontend/Order/realChangeOrderStatus";
         $data['access_token'] = config('access_token');
         return json_decode(curl_request($url,$data), true);
    }
     /**
     * 获取退款订单订单
     * [OrderInformation description]
     *  @auther wang  2019-03-19
     */
    public static function ExportRefundOrder($data = array()){
        $url = config('api_base_url')."orderfrontend/Order/ExportRefundOrder";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 根据条件获取邮件模板信息
     * @param array $param
     * @return mixed
     */
    public static function getEmailTemplateData(array $param){
        $url = config('api_base_url').'/mallextend/EmailTemplate/getData';
        $param['access_token'] = config('access_token');
        return json_decode(curl_request($url,$param), true);
    }

    /*
  * 获取用户信息
  * */
    public static function getCustomerByID($ID){
        $url = config('cic_api_url')."/cic/Customer/getCustomerByID";
        $data['ID'] = $ID;
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

     /**
     * 获取风控特殊名单
     * [OrderInformation SpecialList]
     *  @auther wang  2019-03-29
     */
    public static function SpecialList($data){
        $url = config('api_base_url')."windcontrol/WindControl/SpecialList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 修改会员状态
     * @return string
     * auther wang   2018-04-17
     */
    public static function updateTokenTimeout($data){
        $url = config('api_base_url')."sso/Token/updateTokenTimeout";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取售后订单数量
     * add 20190415 kevin
     * @return mixed
     */
    public static function getUserAfterSaleCount($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/getUserAfterSaleCount";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 统计订单数量，用于后台用户管理
     * add 20190415 kevin
     * @return mixed
     */
    public static function getAdminCustomerOrder($data){
        $url = config('api_base_url')."orderbackend/Order/getAdminCustomerOrder";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 保存订单退款信息
     * add 20190416 kevin
     * @return mixed
     */
    public static function saveOrderRefund($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/saveOrderRefund";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取订单退款信息
     * add 20190416 kevin
     * @return mixed
     */
    public static function getOrderRefund($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/getOrderRefund";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 保存订单退款信息
     * add 20190416 kevin
     * @return mixed
     */
    public static function getAdminReview($data){
        $url = config('api_base_url')."reviews/Reviews/getAdminReview";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*修改评论审核状态
     *add 20190417 kevin
    */
    public static function updateReviewStatus($data){
        $url = config('api_base_url')."reviews/Reviews/updateReviewStatus";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * 获取留言历史记录
     * [HistoryRecordList description]
     * @auther wang  2019-04-19
     */
    public static function HistoryRecordList($data){
        $url = config('api_base_url')."orderfrontend/OrderQuery/HistoryRecordList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取订单退款记录
     * add 20190418 kevin
     * */
    public static function getOrderRefundOperation($data){
        $url = config('api_base_url')."orderbackend/Order/getOrderRefundOperation";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取订单设置
     * @param $order_id
     * @return string
     */
    public static function getOrderConfig($config_key){
        $url = config('api_base_url')."share/OrderConfig/getOrderConfig";
        $data['access_token'] = config('access_token');
        $data['config_key'] = $config_key;
        return json_decode(curl_request($url,$data), true);
    }
    /**
     * 获取无效订单
     * [InvalidOrder description]
     */
    public static function InvalidOrder($data){
       $url = config('api_base_url')."admin/Affiliate/InvalidOrder";
       $data['access_token'] = config('access_token');
       return json_decode(curl_request($url,$data), true);
    }

    /*
     * 根据邮箱获取用户信息
     * add by 20190505 kevin
     * */
    public static function getCustomerInfoByAccount($data){
        $url = config('cic_api_url')."cic/Customer/GetCustomerInfoByAccount";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
    /**
     *
     * [ReportStatistics description]
     */
    public static function ReportStatistics($data){
        $url = config('api_base_url')."admin/Affiliate/ReportStatistics";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取后台普通退款列表
     * add by 20190508 kevin
     * */
    public static function getAdminOrderRefundList($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/getAdminOrderRefundList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取地区信息
     * add by 20190508 kevin
     * */
    public static function getRegionList($Code='',$CountryCode=''){
        $data['Code'] = $Code;
        $data['CountryCode'] = $CountryCode;
        $url = config('api_base_url')."share/region/getRegionList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

     /*
     * 获取订单产品
     * @auther wang  2019-05-21
     * */
    public static function OrderProductExport($data){
        $url = config('api_base_url')."admin/CustomerService/OrderProductExport";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 创建RMA订单
     * add by 20190508 kevin
     * */
    public static function createAdminRmaOrder($data){
        $url = config('api_base_url')."orderbackend/Order/createAdminRmaOrder";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取地区信息
     * add by 20190508 kevin
     * */
    public static function getOrderStatusOmsRecord($data){
        $url = config('api_base_url')."orderbackend/Order/getOrderStatusOmsRecord";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取订单折扣异常记录
     * add by 20190619 kevin
     * */
    public static function getOrderDiscountExceptionList($data){
        $url = config('api_base_url')."orderbackend/Order/getOrderDiscountExceptionList";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    public static function getThirdpartyData($data){
        $url = config('payment_base_url')."unification/query/queryInfo";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 调用payment退款接口
     */
    public static function paymentRefund($data){
        $url = config('payment_base_url')."unification/refund/index";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    public static function updatePaymentStatus($data){
        $url = config('payment_base_url')."unification/query/updateStatus";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控列表数据获取
     * @auther wang  2019-05-23
     */
    public static function RiskOrderList($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/RiskOrderList?".'&access_token='.$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控列表数据获取
     */
    public static function RiskOrderListForHistory($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/RiskOrderListForHistory?access_token=".$data['access_token'];
        Log::record('RiskOrderListForHistory'.$url);
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控详情数据获取
     * @auther wang  2019-05-23
     */
    public static function WindControlOrderList($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/WindControlOrderList?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控详情数据获取
     * @auther wang  2019-05-23
     */
    public static function WindControlOrderJoin($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/WindControlOrderJoin?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 以色列风控结果数据获取
     * @auther wang  2019-05-23
     */
    public static function ThirdPartyResults($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/ThirdPartyResults?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控详情数据获取
     * @auther wang  2019-05-23
     */
    public static function WindControlOrderDetails($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_base_url')."windcontrol/WindControl/WindControlOrderDetails?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控历史数据获取
     * @auther wang  2019-05-23
     */
    public static function WindControlOrderLog($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/WindControlOrderLog?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控数据修改
     * @auther wang  2019-05-23
     */
    public static function RiskManageSave($data = [],$id){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/RiskManageSave?id=".$id.'&access_token='.$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控数据修改
     * @auther wang  2019-05-23
     */
    public static function RiskManageSaveOrder($data = [],$OrderNumber){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/RiskManageSaveOrder?OrderNumber=".$OrderNumber.'&access_token='.$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控日志添加
     * @auther wang  2019-05-23
     */
    public static function logSaveResult($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/logSaveResult?&access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 风控判定结果通知
     * @auther wang  2019-06-12RiskManage($where])
     */
    public static function RiskManage($data = []){
        $data['access_token'] = config('access_token');
        $url = config('payment_base_url')."/unification/RiskManage/index?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取风控记录
     * @auther wang  2019-06-12RiskManage($where])
     */
    public static function HistoricalWindControlOrders($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/HistoricalWindControlOrders?&access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取海外仓发货单
     * add by 20190711 kevin
     * */
    public static function getDeliveryOrder($data){
        $url = config('api_base_url')."orderbackend/Order/getDeliveryOrder";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取海外仓发货单
     * add by 20190711 kevin
     * */
    public static function getProductPurchasePrice($data){
        $url = config('api_base_url')."mallextend/Product/getProductPurchasePrice";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 添加产品评论
     * add by 201900813 kevin
     * */
    public static function addReviews($data){
        $url = config('api_base_url')."reviews/Reviews/addReviews";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 新版后台退款
     * [refundOrder description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function refundOrderNew($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/refundOrderNew";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 获取后台订单支付数据
     * @param array $param
     * @return mixed
     */
    public static function getTransaction(array $param){
        $url = config('api_base_order_frontend_url').'orderfrontend/Order/getTransaction';
        $param['access_token'] = config('access_token');
        return json_decode(curl_request($url, $param), true);
    }

    /**
     * 获取风控历史信息数据
     * @param array $param
     * @return mixed
     */
    public static function getWindcontrolHistoryInfo(array $param){
        $url = config('api_base_order_frontend_url').'windcontrol/WindControl/getHistoryInfo';
        $param['access_token'] = config('access_token');
        return json_decode(curl_request($url, $param), true);
    }

    /**
     * 风控详情数据获取
     * @auther wang  2019-05-23
     */
    public static function getBrank($data = []){
        $data['access_token'] = config('access_token');
        $url = config('api_share_url')."windcontrol/WindControl/getBrank?access_token=".$data['access_token'];
        return json_decode(curl_request($url,$data), true);
    }

  /*
     * 根据条件筛选EIP数据
     * add by 201900814 kevin
     * */
    public static function getSkuSelection($data){
        $url = config('api_base_url')."admin/EipReport/getSkuSelection";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

	/*
     * 获取退款信息
     * */
    public function getOrderRefundSummary($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/getOrderRefundSummary";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /*
     * 获取订单产品
     * @auther kevin  2019-10-28
     * */
    public static function getOrderInformation($data){
        $url = config('api_base_url')."admin/CustomerService/getOrderInformation";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

    /**
     * 修改退款信息数据
     * add 20191031 kevin
     * @return mixed
     */
    public static function updateOrderRefund($data){
        $url = config('api_base_url')."orderbackend/OrderRefund/updateOrderRefund";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }
	
	
    /**
     * 获取后台风控订单数据
     * @param array $param
     * @return mixed
     */
    public static function getWindControlOrderList(array $param){
        $url = config('api_base_url').'orderbackend/Order/getWindControlOrderList';
        $param['access_token'] =config('access_token');
        return json_decode(curl_request($url, $param), true);
    }

	 /**
     * 根据SkuCode获取产品Sku数据
     * add 20191105 kevin
     * @return mixed
     */
    public static function getSkuProductBySkuCode($data){
        $url = config('api_base_url')."mallextend/Product/getSkuProductBySkuCode";
        $data['access_token'] = config('access_token');
        return json_decode(curl_request($url,$data), true);
    }

}
