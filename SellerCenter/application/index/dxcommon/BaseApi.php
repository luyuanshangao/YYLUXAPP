<?php
namespace app\index\dxcommon;
use think\Controller;
use think\Log;

/**
 * 基础API类
 * Class BaseApi
 * @author tinghu.liu
 * @date 2018-03-09
 * @package app\index\dxcommon
 */
class BaseApi extends API
{
    /**
     * 根据区域ID获取区域信息
     * @param $region_id 区域ID
     * @return string
     */
    public function getRegionDataWithRegionID($region_id ){
        $url = config('api_base_url').'/admin/region/getRegionInfo';
        return $this->requestApi($url, ['parent_id'=>$region_id]);
    }

    /**
     * 根据地区ID获取地区数据
     * @param $region_id 区域ID
     * @return string
     */
    public function getRegionInfoByRegionID($region_id ){
        $url = config('api_base_url').'/admin/region/getRegionInfoByRegionID';
        return $this->requestApi($url, ['region_id'=>$region_id]);
    }

    /**
     * 根据类别标题名称获取分类目录信息
     * @param $title
     * @param int $type 类型：1-按照英文名搜索【默认】，2-按照中文名搜索
     * @return string
     */
    public function getCategoryDataWithTitle($title, $type=1){
//        return json_decode(curl_request(config('api_base_url').'/mallextend/Product_Category/searchByTitle'.self::$access_token_str, ['search_content'=>$title]), true);
        $url = config('api_base_url').'/mallextend/Product_Category/searchByTitle';
        return $this->requestApi($url, ['search_content'=>$title, 'type'=>$type]);
    }

    /**
     * 根据类别ID获取分类目录信息【倒推】
     * @param $id
     * @param int $flag
     * @return string
     */
    public function getCategoryDataWithID($id, $flag=0){
        $url = config('api_base_url').'/mallextend/Product_Category/getCategoryByID';
        return $this->requestApi($url, ['id'=>$id, 'flag'=>$flag]);
    }

    /**
     * 根据分类ID获取下一个子级
     * @param $id 分类ID
     * @return mixed
     */
    public function getNextCategoryByID($id,$class_type=1){
        $url = config('api_base_url').'/mallextend/Product_Category/getNextCategoryByID';
        return $this->requestApi($url, ['id'=>$id,'class_type'=>$class_type]);
    }

    /**
     * 根据分类ID获取产品品牌
     * @param $class_id
     * @return mixed
     */
    public function getProBrandByCategoryID($class_id){
        $url = config('api_base_url').'/mallextend/Product/getProductBrand';
        return $this->requestApi($url, ['classId'=>$class_id]);
    }

    /**
     * 获取计量单位
     * @return mixed
     */
    public function getMeasurementUnit(){
        // 具体API地址 TODO
        $url = config('api_base_url').'';
        return $this->requestApi($url);
    }

    /**
     * 根据分类ID获取对应属性
     * @param $category_id
     * @return mixed
     */
    public function getProAttrByCategoryID($category_id){
        $url = config('api_base_url').'/mallextend/Product/getProductAttribute';
        return $this->requestApi($url, ['classId'=>$category_id]);
    }

    /**
     * 获取产品运费模板 TODO
     * @return mixed
     */
    public function getProFreightFormwork(){
        $url = config('api_base_url').'';
        return $this->requestApi($url);
    }

    /**
     * 获取产品服务模板 TODO
     * @return mixed
     */
    public function getProServiceFormwork(){
        $url = config('api_base_url').'';
        return $this->requestApi($url);
    }

    /**
     * 产品提交
     * @param json $product_data
     * @return mixed
     */
    public function productPost($product_data){
        return json_decode(http_post_json(config('api_base_url').'/mallextend/Product/addProduct'.self::$access_token_str, $product_data), true);
    }

    /**
     * 产品分组是否存在
     * @param json $group_id
     * @return mixed
     */
    public function hasGroupPost($user_id,$group_name){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product_group/hasGroup'.self::$access_token_str, ['user_id'=>$user_id,'group_name'=>$group_name]), true);
    }

    /**
     * 添加产品分组
     * @param json $group_id
     * @return mixed
     */
    public function addGroupPost($user_id,$group_name,$parent_id=0){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product_group/addGroup'.self::$access_token_str, ['user_id'=>$user_id,'group_name'=>$group_name,'parent_id'=>$parent_id]), true);
    }
    /**
     * 获取产品分组
     * @param json $group_id
     * @return mixed
     */
    public function getGroupPost($user_id){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product_group/getGroup'.self::$access_token_str, ['user_id'=>$user_id]), true);
    }

    /**
     * 删除产品分组
     * @param json $group_id
     * @return mixed
     */
    public function delGroupPost($group_id){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product_group/delGroup'.self::$access_token_str, ['group_id'=>$group_id]), true);
    }
    /**
     * 修改产品分组
     * @param json $group_id
     * @return mixed
     */
    public function editGroupPost($data){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product_group/editGroup'.self::$access_token_str, $data), true);
    }
    /**
     * 获取分组产品
     * @param $data
     * @return mixed
     */
    public function getGroupProductPost($data){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/lists'.self::$access_token_str, $data), true);
    }

    /**
     * 更改产品分组
     * @param $data
     * @return mixed
     */
    public function updateProductGrouptPost($data){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/updateGroup'.self::$access_token_str, $data), true);
    }

    /**
     * 根据产品ID获取产品信息
     * @param $product_id 产品ID
     * @return mixed
     */
    public function getProductInfoByID($product_id,$store_id='',$sku_id='',$lang=''){
        $url = config('api_base_url').'/mallextend/Product/getProduct'.self::$access_token_str;
        $param = json_encode(['product_id'=>$product_id,'store_id'=>$store_id,'lang'=>$lang,'sku_id'=>$sku_id,'attrList'=>true]);
        return json_decode(http_post_json($url, $param), true);
    }

    /**
     * 获取产品详情
     * @param json $product_id
     * @param int $store_id
     * @return mixed
     */
    public function getProductInfoPost($product_id, $store_id=0){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/getProduct'.self::$access_token_str, ['product_id'=>$product_id,'store_id'=>$store_id]), true);
    }

    /**
     * 自定义获取产品详情
     * @param array $param
     * @return mixed
     */
    public function getProductInfo($param){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/getProduct'.self::$access_token_str, $param), true);
    }

    /**
     * 获取产品数量
     * @param $user_id sellerID
     * @param $status 状态数组
     * @param array $reject_type 产品审核不通过类型
     * @return mixed
     */
    public function CountBySellerPost($user_id,$status,$reject_type=[]){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/countBySeller'.self::$access_token_str, ['seller_id'=>$user_id,'status'=>$status,'reject_type'=>$reject_type]), true);
    }

    /**
     * 更改产品
     * @param $data
     * @return mixed
     */
    public function updateProductInfoPost($data){
        return json_decode(http_post_json(config('api_base_url').'/mallextend/product/update'.self::$access_token_str, $data), true);
    }

    /**
     * 更改产品【同步历史产品运费模板专用】
     * @param $data
     * @return mixed
     */
    public function updateProductInfoPostForSyncHistoryProductSTAndImgs($data){
        return json_decode(http_post_json(config('api_base_url').'/mallextend/product/updateForSyncHistoryProductSTAndImgs'.self::$access_token_str, $data), true);
    }

    /**
     * 更改产品状态
     * @param $data
     * @return mixed
     */
    public function productChangeStatusPost($data){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/changeStatus'.self::$access_token_str, $data), true);
    }

    /**
     * 更改产品有效期
     * @param $data
     * @return mixed
     */
    public function prolongExpiryPost($data){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product/prolongExpiry'.self::$access_token_str, $data), true);
    }

    /**
     * 获取产品分组名称
     * @param json $group_id
     * @return mixed
     */
    public function getGroupNamePost($group_id){
        return json_decode(curl_request(config('api_base_url').'/mallextend/product_group/getGroupNmae'.self::$access_token_str, ['group_id'=>$group_id]), true);
    }

    /**
     * 获取活动数据
     * @param array $param 相关参数
     * @return mixed
     */
    public function getActivityData(array $param){
        // API地址
        $url = config('api_base_url').'/admin/Activity/getActivityData'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取活动数据单条数据
     * @param $activity_id 活动ID
     * @return mixed
     */
    public function getActivityByActivityID($activity_id){
        // API地址
        $url = config('api_base_url').'/admin/Activity/getActivityByActivityID'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode(['activity_id'=>$activity_id])), true);
    }

    /**
     * 获取活动报名数据
     * @param array $param 相关参数
     * @return mixed
     */
    public function getEnrollActivityData(array $param=[]){
        // API地址
        $url = config('api_base_url').'/admin/Activity/getEnrollActivityData'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 报名活动
     * @param array $param 相关参数
     * @return mixed
     */
    public function enrollActivity(array $param){
        // API地址
        $url = config('api_base_url').'/admin/Activity/enrollActivity'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 退出报名活动
     * @param array $param 相关参数
     * @return mixed
     */
    public function quitActivity(array $param){
        // API地址
        $url = config('api_base_url').'/admin/Activity/quitActivity'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取活动SKU数据
     * @param array $param
     * @return mixed
     */
    public function getActivitySKUData(array $param=[]){
        // API地址
        $url = config('api_base_url').'/admin/Activity/getActivitySKUData'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取活动SKU数据【列表分页】
     * @param array $where
     * @return mixed
     */
    public function getActivitySKUDataForList(array $where=[]){
        // API地址
        $url = config('api_base_url').'/admin/Activity/getActivitySKUDataForList'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($where)), true);
    }

    /**
     * 增加活动SKU数据
     * @param array $data
     * @return mixed
     */
    public function addActivitySKU(array $data=[]){
        // API地址
        $url = config('api_base_url').'/admin/Activity/addActivitySKU_new'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode(['data'=>$data])), true);
    }

    /**
     * 修改活动SKU数据
     * @param array $data
     * @return mixed
     */
    public function updateActivitySKU(array $data=[]){
        // API地址
        $url = config('api_base_url').'/admin/Activity/updateActivitySKU'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($data)), true);
    }

    /**
     * 重新提交活动SKU数据
     * @param array $data
     * @return mixed
     */
    public function resubmitActivitySKU(array $data){
        // API地址
        $url = config('api_base_url').'/admin/Activity/resubmitActivitySKU'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($data)), true);
    }

    /**
     * 根据条件获取订单数据（含分页）
     * @param array $where 条件
     * @return mixed
     */
    public function getOrderLists(array $where){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/getOrderLists'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($where)), true);
    }

    /**
     * 获取订单状态对应数量
     * @param array $where 条件
     * @return mixed
     */
    public function getOrderStatusNum(array $where){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/getOrderStatusNum'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($where)), true);
    }

    /**
     * 修改价格
     * @param array $param 条件
     * @return mixed
     */
    public function updateOrderPrice(array $param){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/updateOrderPrice'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 根据订单ID获取订单详情
     * @param $order_number 订单编号
     * @param int $seller_id sellerID
     * @return mixed
     */
    public function getOrderInfo($order_number, $seller_id=0){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/getOrderInfo'.self::$access_token_str;
        $data = array();
        if(strlen($order_number)>15){
            $data['order_number'] = $order_number;
        }else{
            $data['order_id'] = $order_number;
        }
        $data['seller_id'] = $seller_id;
        return json_decode(http_post_json($url, json_encode($data)), true);
    }

    /**
     * 根据订单ID更新订单备注信息
     * @param array $param 参数
     * @return mixed
     */
    public function updateOrderRemark(array $param){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/updateOrderRemark'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 增加订单留言信息
     * @param array $param 参数
     * @return mixed
     */
    public function addOrderMessage(array $param){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/addOrderMessage'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 更新订单留言信息
     * @param array $param 参数
     * @return mixed
     */
    public function updateOrderMessageStatus(array $param){
        // API地址
        $url = config('api_base_url').'/orderbackend/Order/updateOrderMessageStatus'.self::$access_token_str;
        return json_decode(http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取币种信息
     * @return mixed
     */
    public function getCurrencyList(){
        $url = config('api_base_url').'/share/currency/getCurrencyList';
        return $this->requestApi($url);
    }

    /**
     * 获取商品评论列表
     * @param array $param 参数
     * @return mixed
     */
    public function getReviewsList(array $param){
        $url = config('api_base_url').'/reviews/Reviews/getReviewsList';
        return $this->requestApi($url, $param);
    }

    /**
     * 回复评价
     * @param array $param 参数
     * @return mixed
     */
    public function addReplyReviews(array $param){
        $url = config('api_base_url').'/reviews/Reviews/addReplyReviews';
        return $this->requestApi($url, $param);
    }

    /**
     * 添加产品运费模板信息
     * @param array $param 要添加的数据
     * @return mixed
     */
    public function createShippingCost(array $param){
        $url = config('api_base_url').'/mallextend/ShippingCost/create';
        return $this->requestApi($url, $param);
    }

    /**
     * 修改产品时修改运费模板
     * @param array $param 要添加的数据
     * @return mixed
     */
    public function updateShippingCostForUpdateProduct(array $param){
        $url = config('api_base_url').'/mallextend/ShippingCost/updateForUpdateProduct';
        return $this->requestApi($url, $param);
    }

    /**
     * 添加Coupon信息
     * @param array $param 要添加的数据
     * @return mixed
     */
    public function addCoupon(array $param){
        $url = config('api_base_url').'/mallextend/Coupon/addCoupon';
        return $this->requestApi($url, $param);
    }

    /**
     * 添加Coupon Code信息
     * @param array $param 要添加的数据
     * @return mixed
     */
    public function addCouponCode(array $param){
        $url = config('api_base_url').'/mallextend/Coupon/addCouponCode';
        return $this->requestApi($url, $param);
    }

    /**
     * 更新Coupon信息
     * @param array $param 要更新的数据
     * @return mixed
     */
    public function updateCouponData(array $param){
        $url = config('api_base_url').'/mallextend/Coupon/updateCouponData';
        return $this->requestApi($url, $param);
    }

    /**
     * 生成Coupon Code数据
     * @param array $param 要添加的数据
     * @return mixed
     */
    public function getCouponCode(array $param){
        $url = config('api_base_url').'/mallextend/Coupon/getCouponCode';
        return $this->requestApi($url, $param);
    }

    /**
     * 根据couponID获取coupon信息
     * @param $coupon_id
     * @return mixed
     */
    public function getCouponByCouponId($coupon_id){
        $url = config('api_base_url').'/mallextend/Coupon/getCouponByCouponId';
        return $this->requestApi($url, ['CouponId'=>$coupon_id]);
    }

    /**
     * 根据couponID获取coupon code信息
     * @param $coupon_id
     * @return mixed
     */
    public function getCouponCodeByCouponId($coupon_id){
        $url = config('api_base_url').'/mallextend/Coupon/getCouponCodeByCouponId';
        return $this->requestApi($url, ['CouponId'=>$coupon_id]);
    }

    /**
     * 获取Coupon列表数据
     * @param array $param 要添加的数据
     * @return mixed
     */
    public function getCouponList(array $param){
        $url = config('api_base_url').'/mallextend/Coupon/getCouponList';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取订单退款信息列表
     * @param array $param 参数条件
     * @return mixed
     */
    public function getOrderRefundGetLists(array $param){
        $url = config('api_base_url').'/orderbackend/OrderRefund/getLists';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取订单退款和订单信息列表
     * @param array $param 参数条件
     * @return mixed
     */
    public function getOrderRefundLists(array $param){
        $url = config('api_base_url').'/orderbackend/OrderRefund/getOrderLists';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取订单售后状态
     * @return mixed
     */
    public function getAfterSaleStatus(){
        $url = config('api_base_url').'/share/OrderConfig/getAfterSaleStatus';
        return $this->requestApi($url);
    }

    /**
     * 更新订单退款退货换货数据
     * @param array $param 参数条件
     * @return mixed
     */
    public function updateApplyData(array $param){
        $url = config('api_base_url').'/orderbackend/OrderRefund/updateApplyData';
        return $this->requestApi($url, $param);
    }

    /**
     * 售后订单拒绝回退订单最后一个订单状态
     * @param array $param 参数条件
     * @return mixed
     */
    public function rollbackApplyOrderStatus(array $param){
        $url = config('api_base_url').'/orderfrontend/Order/rollbackApplyOrderStatus';
        return $this->requestApi($url, $param);
    }

    /**
     * 增加订单售后申请操作记录数据
     * @param array $param 参数条件
     * @return mixed
     */
    public function addApplyLogData(array $param){
        $url = config('api_base_url').'/orderbackend/OrderRefund/addApplyLogData';
        return $this->requestApi($url, $param);
    }

    /**
     * 售后申请新建RMA订单
     * @param array $param 参数条件
     * @return mixed
     */
    public function createRmaOrder(array $param){
        $url = config('api_base_url').'/orderbackend/Order/createRmaOrder';
        return $this->requestApi($url, $param);
    }

    /**
     * 售后申请订单退款（就算失败也只请求一次，为了避免重复支付的情况）
     * @param array $param 参数条件
     * @return mixed
     */
    public function refundOrder(array $param){
        $url = config('api_base_url').'/orderbackend/Order/afterSaleRefundOrder';
        return $this->requestApi($url, $param, 1, 1);
    }

    /**
     * 普通订单退款(非售后)
     * @param array $param 参数条件
     * @return mixed
     */
    public function refundOrderNoAfter(array $param){
        $url = config('api_base_url').'/orderbackend/OrderRefund/refundOrder';
        return $this->requestApi($url, $param, 1, 1);
    }

    /**
     * 售后纠纷数据
     * @param array $param 参数条件
     * @return mixed
     */
    public function getComplaintDataForPage(array $param){
        $url = config('api_base_url').'/orderbackend/OrderRefund/getComplaintLists';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取后台配置数据
     * @param array $param 参数条件
     * ['ConfigName' => 'ActivityPlayNumber']
     * @return mixed
     */
    public function getSysCofig(array $param){
        $url = config('api_base_url').'/mallextend/SysConfig/getSysCofigValue';
        $cache_key = 'sysconfig:'.$param['ConfigName'];
        $data = cache_redis($cache_key);
        if (empty($data)){
            $data_api = $this->requestApi($url, $param);
            if ($data_api['code'] == API_RETURN_SUCCESS && isset($data_api['data']) && !empty($data_api['data'])){
                $data = $data_api['data'];
                cache_redis($cache_key, $data, 15*24*60*60);//默认缓存配置15天
            }
        }
        return $data;
    }

    /**
     * 插入Affiliate数据到后台数据库
     * @param array $param 参数条件
     * @return mixed
     */
    public function addCommission(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/addCommission';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据seller id 获取默认佣金比例
     * @param array $param 参数条件
     * @return mixed
     */
    public function getDefaultCommissionBySellerID(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/getDefaultCommissionBySellerID';
    	return $this->requestApi($url, $param);
    }


    /**
     * 获取affiliate 分类默认佣金数据
     * @return mixed
     */
    public function getClassDefaultCommission($param = array()){
    	$url = config('api_base_url').'/admin/Affiliate/getClassDefaultCommission';
    	return $this->requestApi($url,$param);
    }

    /**
     * 根据ID删除佣金数据
     * @param $id
     * @return mixed
     */
    public function deleteCommissionById($id){
    	$url = config('api_base_url').'/admin/Affiliate/deleteCommission';
    	return $this->requestApi($url, ['id'=>$id]);
    }

    /**
     * 获取分类佣金配置列表
     * @param array $param
     * @return mixed
     */
    public function getClassCommissionList(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/getClassCommissionList';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据分类ID数据获取对应分类信息
     * @param array $param
     * @return mixed
     */
    public function getCategoryDataByCategoryIDData(array $param){
    	$url = config('api_base_url').'/mallextend/ProductCategory/getCategoryDataByCategoryIDData';
    	return $this->requestApi($url, $param);
    }

    /**
     * 联盟营销添加主推产品
     * @param array $param
     * @return mixed
     */
    public function addAffiliateProduct(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/addAffiliateProduct';
    	return $this->requestApi($url, $param);
    }

    /**
     * 获取主推产品数量情况
     * @param array $param
     * @return mixed
     */
    public function getMainProductNum(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/getMainProductNum';
    	return $this->requestApi($url, $param);
    }

    /**
     * 获取联盟营销产品列表【分页】
     * @param array $param
     * @return mixed
     */
    public function getAffiliateProductList(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/getAffiliateProductList';
    	return $this->requestApi($url, $param);
    }

    /**
     * 更新联盟营销产品数据
     * @param array $param
     * @return mixed
     */
    public function updateAffiliateProduct(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/updateAffiliateProduct';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取邮件模板信息
     * @param array $param
     * @return mixed
     */
    public function getEmailTemplateData(array $param){
    	$url = config('api_base_url').'/mallextend/EmailTemplate/getData';
    	return $this->requestApi($url, $param);
    }

    /**
     * 判断seller是否已经加入联盟营销
     * @param array $param
     * @return mixed
     */
    public function AffiliateJudgeIsJoin(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/judgeIsJoin';
    	return $this->requestApi($url, $param);
    }

    /**
     * 获取affiliate订单列表【分页】
     * @param array $param
     * @return mixed
     */
    public function getAffiliateOrderList(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/getAffiliateOrderList';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据affiliate订单ID获取affiliate订单详情
     * @param array $param
     * @return mixed
     */
    public function getAffiliateOrderInfoById(array $param){
    	$url = config('api_base_url').'/admin/Affiliate/getAffiliateOrderInfoById';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取admin库消息数据
     * @param array $param
     * @return mixed
     */
    public function getAdminMessageData(array $param = []){
    	$url = config('api_base_url').'/admin/Message/getData';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取文章列表
     * @param array $param
     * @return mixed
     */
    public function getArticleList(array $param = []){
    	$url = config('api_base_url').'/admin/Article/getList';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取用户优惠券条数
     * @param array $param
     * @return mixed
     */
    public function getCouponCount(array $param = []){
    	$url = config('api_base_url_cic').'/cic/MyCoupon/getCouponCount';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取RAM订单所需数据
     * @param array $param
     * @return mixed
     */
    public function getRamPostData(array $param = []){
    	$url = config('api_base_url').'/orderbackend/OrderRefund/getRamPostData';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据多个产品ID获取产品数据
     * @param array $param
     * @return mixed
     */
    public function getPruductDataByIds(array $param = []){
    	$url = config('api_base_url').'/mallextend/Product/getPruductDataByIds';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取coupon使用情况
     * @param array $param
     * @return mixed
     */
    public function getCouponUsedInfoByCouponId(array $param = []){
    	$url = config('api_base_url_cic').'/cic/MyCoupon/getCouponByCouponId';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取运费模板数据
     * @param array $param
     * @return mixed
     */
    public function shippingCostGetData(array $param = []){
    	$url = config('api_base_url').'/mallextend/ShippingCost/getData';
    	return $this->requestApi($url, $param);
    }

    /**
     * 根据条件获取运费模板数据 - 新
     * @param array $param
     * @return mixed
     */
    public function shippingCostGetData_New(array $param = []){
    	$url = config('api_base_url').'/mallextend/ShippingCost/getDataByWhere';
    	return $this->requestApi($url, $param);
    }

    /**
     * 修改运费模板时同步运费模板
     * @param array $param
     * @return mixed
     */
    public function updateForShippingTemplateEditor(array $param = []){
        $url = config('api_base_url').'/mallextend/ShippingCost/updateForShippingTemplateEditor';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取报告列表
     * @param array $param
     * @return mixed
     */
    public function getReportsListForSeller(array $param = []){
        $url = config('api_base_url').'/admin/Reports/getListForSeller';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取报告列表相关配置
     * @param array $param
     * @return mixed
     */
    public function getReportConfig(array $param = []){
        $url = config('api_base_url').'/admin/Reports/getReportConfig';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取站内信数据
     * @param array $param
     * @return mixed
     */
    public function getMessageListForSeller(array $param = []){
        $url = config('api_base_url').'/admin/Message/getListForSeller';
        return $this->requestApi($url, $param);
    }

    /**
     * 更新站内信数据
     * @param array $param
     * @return mixed
     */
    public function updateMessageReciveData(array $param = []){
        $url = config('api_base_url').'/admin/Message/updateMessageReciveData';
        return $this->requestApi($url, $param);
    }

    /**
     * 获取历史产品数据【同步运费模板&&历史产品图片专用】
     * @param $start_spu_id
     * @param $end_spu_id
     * @param int $page_size
     * @param array $us_spus
     * @param int $check_flag
     * @return mixed
     */
    public function getHistoryDataForAsyncShippingTemplateAndImgs($start_spu_id, $end_spu_id, $page_size=10, $us_spus=[], $check_flag=0){
        $url = config('api_base_url').'/mallextend/Product/getHistoryDataForAsyncShippingTemplateAndImgs';
        return $this->requestApi($url, ['page_size'=>$page_size, 'start_spu_id'=>$start_spu_id, 'end_spu_id'=>$end_spu_id, 'us_spus'=>$us_spus, 'check_flag'=>$check_flag]);
    }

    /**
     * 获取邮箱验证码
     * @param $data
     * @return mixed
     */
    public function createVerificationCode($data){
        $url = config('api_base_url').'/share/VerificationCode/createVerificationCode';
        return $this->requestApi($url, $data);
    }

    /**
     * 校验邮箱验证码
     * @param $data
     * @return mixed
     */
    public function checkVerificationCode($data){
        $url = config('api_base_url').'/share/VerificationCode/checkVerificationCode';
        return $this->requestApi($url, $data);
    }

     /**
     * 批量修改产品价格
     * @param $data
     * @return mixed
     */
    public function updateProductsPrice($data){
        $url = config('api_base_url').'/mallextend/product/updateProductsPrice';
        return $this->requestApi($url, $data);
    }

    /**
     * SPU 拆分
     * @param $data
     * @return mixed
     */
    public function splitProduct($data){
        $url = config('api_base_url').'/mallextend/Product/splitProduct';
        return $this->requestApi($url, $data);
    }

     /**
     * 根据分类ID数据获取对应分类及它的上级中最先有映射关系的映射数据
     * @param $id
     * @param int $flag
     * @return mixed
     */
    public function getMapDataByCategoryID($id, $flag=0){
        $url = config('api_base_url').'/mallextend/Product_Category/getMapDataByCategoryID';
        return $this->requestApi($url, ['id'=>$id, 'flag'=>$flag]);
    }

    /**
     * 下载订单
     * @param array $param
     * @return mixed
     */
    public function downloadOrder(array $param){
        $url = config('api_base_url').'/orderbackend/Order/downloadOrder';
        //增加seller下载订单签名
        $sign_flag = 'downloadOrder'.$param['seller_id'].date('Y-m-d');
        $param['download_sign'] = $this->makeSign($sign_flag);
        return $this->requestApi($url, $param);
    }

    /**
     * seller下载订单
     * @param array $param
     * @return mixed
     */
    public function sellerDownloadOrder(array $param){
        $url = config('api_base_url').'/orderbackend/Order/sellerDownloadOrder';
        return $this->requestApi($url, $param);
    }

    /**
     * seller订单导入
     * @param array $param
     * @return mixed
     */
    public function sellerUploadOrder(array $param){
        $url = config('api_base_url').'/orderfrontend/TrackingNumber/syncPost';
        $sign_flag = 'syncPostTrackingNumber'.$param['seller_id'].date('Y-m-d');
        $param['sign'] = $this->makeSign($sign_flag);
        return $this->requestApi($url, $param);
    }

    /**
     * 获取产品所有的翻译
     * @param $id
     * @return mixed
     */
    public function getProductMultiLangs($id){
        $url = config('api_base_url').'/mallextend/Product/getProductMultiLangs';
        return $this->requestApi($url, ['product_id'=>$id]);
    }
    /**
     * 更新产品多语言
     * @param $data
     * @return mixed
     */
    public function updatePrdouctmMultiLangs($data){
        $url = config('api_base_url').'/mallextend/product/updatePrdouctmMultiLangs';
        $param = ['id'=>$data['id'],'lang'=>$data['lang'],'title'=>$data['Title'],'descriptions'=>$data['Descriptions'],'keywords'=>$data['Keywords']];
        return $this->requestApi($url, $param);
    }
    /**
     * 获取售后订单配置数据
     * @return string
     */
    public function getAfterSaleConfig(){
        $url = config('api_base_url')."/orderfrontend/MyOrder/getAfterSaleConfig";
        return $this->requestApi($url);
    }
    /**
     * 获取当前最大skuCode
     * @return mixed
     */
    public function getMaxSkuCode(){
        $url = config('api_base_url')."/mallextend/product/getMaxSku";
        return $this->requestApi($url);
    }
    /**
     * 添加订单售后接口
     * @param array $params 参数
     * @return string
     */
    public function saveOrderAfterSaleApply(array $params){
        $url = config('api_base_url')."/orderfrontend/OrderAfterSaleApply/saveOrderAfterSaleApply";
        return $this->requestApi($url,$params,1,1);
    }

    /**
     * 添加订单退款接口
     * @param array $params 参数
     * @return string
     */
    public function saveOrderRefund(array $params){
        $url = config('api_base_url')."/orderbackend/OrderRefund/saveOrderRefund";
        return $this->requestApi($url,$params,1,1);
    }
     /**
     * Lis
     * @param array $params 参数
     * @return string
     */
    public function LisLogisticsDetail(array $params){
        $url = config('api_base_url')."/lis/LogisticsDetail/LisLogisticsDetail";
        return $this->requestApi($url,$params);
    }
     /**
     * 删除coupon code
     * @param array $params 参数
     * @return string
     */
    public function deleteCouponCode(array $params){
        $url = config('api_base_url')."/mallextend/Coupon/deleteCouponCode";
        return $this->requestApi($url,$params);
    }

    /**
     * 根据国家等信息获取运费模板详情
     * @param array $params 参数
     * @return string
     */
    public function getLogisticsManagement(array $params){
        $url = config('api_base_url')."/seller/Logistics/getLogisticsManagement";
        return $this->requestApi($url,$params);
    }

    /**
     * 根据产品ID获取运费模板
     * @param array $params 参数
     * @return string
     */
    public function getShipping(array $params){
        $url = config('api_base_url')."/mallextend/Product/getProductShipping";
        return $this->requestApi($url,$params);
    }

    /**
     * 根据产品ID获取毛利率
     * @param array $params 参数
     * @return string
     */
    public function getProductProfit(array $params){
        $url = config('api_base_url')."/admin/affiliate/getProductProfit";
        return $this->requestApi($url,$params);
    }

}
