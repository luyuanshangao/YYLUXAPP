<?php
namespace app\mallaffiliate\dxcommon;

use app\admin\statics\BaseFunc;
use think\Controller;
use think\Log;

/**
 * 基础API类
 * Class BaseApi
 * @author tinghu.liu
 * @date 2018-04-23
 * @package app\mallaffiliate\dxcommon
 */
class BaseApi extends API{

    /**
 * 会员管理
 * @return string
 * auther wang   2018-04-17
 */
    public function getCustomerList($data){
        $url = CIC_API."cic/Customer/getCustomerList".self::$access_token_str;
        $param = json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    public function getAdminCustomerInfo($data){
        $url = CIC_API."cic/Customer/getAdminCustomerInfo".self::$access_token_str;
        $param = json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    public function getDay($data){
        $url = CIC_API."cic/Statistics/getDay".self::$access_token_str;
        $param = json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    /**
     * 获取后台订单数据
     * @param array $param
     * @return mixed
     */
    public  function getOrderDetail(array $param){
        $url = MALL_API.'orderfrontend/OrderQuery/getOrderDetail'.self::$access_token_str;
        $param = json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    /**
     * 获取退款订单列表
     * @param array $param
     * @return mixed
     */
    public  function getOrderRefundList(array $param){
        $url = MALL_API.'orderbackend/OrderRefund/getOrderRefundList'.self::$access_token_str;
        $param = json_encode($param);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    /*
    * 获取订单退款记录
    * add 20190418 kevin
    * */
    public  function getOrderRefundOperation($data){
        $url = MALL_API."orderbackend/Order/getOrderRefundOperation".self::$access_token_str;
        $data['access_token'] = config('access_token');
        $param = json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }

    /**
     *获取币种接口
     * author: Wang
     * AddTime:2018-06-12
     */
    public  function getCurrencyList(){
        $getCurrencyList      = config('getCurrencyList');
        $data['access_token'] = config('access_token');
        $param = json_encode($data);
        return json_decode(BaseFunc::http_post_json($getCurrencyList['url'], $param), true);
    }

    /**
     * 会员管理
     * @return string
     * auther wang   2018-04-17
     */
    public function getWishNum($data){
        $url = CIC_API."cic/MyWish/getWishNum".self::$access_token_str;
        $param = json_encode($data);
        return json_decode(BaseFunc::http_post_json($url, $param), true);
    }
}
