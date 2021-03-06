<?php
namespace app\admin\dxcommon;

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
    /**
     * 根据产品ID获取产品信息
     * @param $product_id 产品ID
     * @return mixed
     */
    public function getProductInfoByID($product_id){
        $url = config('api_base_url').'/mallextend/Product/getProduct'.self::$access_token_str;
        $param = json_encode(['product_id'=>$product_id]);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    /**
     * 获取后台配置数据
     * @param array $param 参数条件
     * @return mixed
     */
    public function getSysCofig(array $param){
        $url = config('api_base_url').'/mallextend/SysConfig/getSysCofig'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取分类ID数据
     * @param array $param 参数条件
     * @return mixed
     */
    public function getCategoryByID($category_id){
        $url = config('api_base_url').'/mallextend/ProductCategory/getCategoryByID'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode(['id'=>$category_id])), true);
    }

    /**
     * 根据分类ID获取单条分类信息
     * @param array $param 参数条件
     * @return mixed
     */
    public function getCategoryInfoByCategoryID($category_id){
        $url = config('api_base_url').'/mallextend/ProductCategory/getCategoryInfoByCategoryID'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode(['id'=>$category_id])), true);
    }

    /**
     * 根据分类ID数据获取对应分类信息
     * @param array $param
     * @return mixed
     */
    public function getCategoryDataByCategoryIDData(array $param){
        $url = config('api_base_url').'/mallextend/ProductCategory/getCategoryDataByCategoryIDData'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 根据条件判断是否存在产品数据
     * @param array $param
     * @return mixed
     */
    public function judgeIsHaveProductByParams(array $param){
        $url = config('api_base_url').'/mallextend/Product/judgeIsHaveProductByParams'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 更新产品活动数据
     * @param array $param
     * [
     *  'product_id_arr'=>[10,20,30]
     * ]
     * @return mixed
     */
    public function updateActivityFortask(array $param){
        $url = config('api_base_url').'/mallextend/Product/updateActivityFortask'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 更新affiliate订单状态
     * @param array $param
     * @return mixed
     */
    public function updateAffiliateOrderStatus(array $param){
        $url = config('api_base_url').'/admin/Affiliate/updateAffiliateOrderStatus'.self::$access_token_str;
        //return accessTokenToCurl($url,null,$param,true);
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 订单取消扣减积分
     * @param array $param
     * @return mixed
     */
    public function cancelOrderDecPoints(array $param){
        $url = config('api_base_url').'/cic/Points/CancelOrderDecPoints'.self::$access_token_str;
        //return accessTokenToCurl($url,null,$param,true);
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 产品提交
     * @param array $param
     * @return mixed
     */
    public function productPost(array $param){
        $url = config('api_base_url').'/mallextend/Product/addProduct'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * getProductToShipping
     * @param array $param
     * @return mixed
     */
    public function getProductToShipping(array $param){
        $url = config('api_base_url').'/mall/product/getProductToShipping'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取单个国家
     * @param array $param
     * @return mixed
     */
    public function regionFind(array $param){
        $url = config('api_base_url').'/share/region/find'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取币种列表
     * @param array $param
     * @return mixed
     */
    public function getCurrencyList(array $param = []){
        $url = config('api_base_url').'/share/currency/getCurrencyList'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * 获取语种列表
     * @param array $param
     * @return mixed
     */
    public function getLangList(array $param = []){
        $url = config('api_base_url').'/share/header/langs'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * getExchangeRate
     * @param array $param
     * @return mixed
     */
    public function getExchangeRate(array $param = []){
        $url = config('api_base_url').'/share/currency/getExchangeRate'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

    /**
     * affiliate订单取消扣减推荐积分
     * @param array $param
     * @return mixed
     */
    public function CancelOrderDecReferralPoints(array $param = []){
        $url = config('api_base_url').'/cic/Points/CancelOrderDecReferralPoints'.self::$access_token_str;
        return json_decode(BaseFunc::http_post_json($url, json_encode($param)), true);
    }

}
