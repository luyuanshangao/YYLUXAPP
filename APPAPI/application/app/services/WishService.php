<?php
namespace app\app\services;

use think\Cache;


/**
 * 创建：钟宁
 * 功能：收藏列表服务层
 * 时间：2018-05-17
 */
class WishService extends BaseService{

    public $redis;


    /**
     * 添加收藏
     */
    public function addWish($params,$product_cache=''){
        $request = doCurl(CIC_API . 'cic/MyWish/addWish',$params, [
            'access_token' => $this->getAccessToken()
        ]);
        if ($request['code'] != 200) {
            //错误日志：已经加入心愿列表的错误提示“Has been added to the wish list”不属于200，为了不记录这大量的日志，add zhongning 20190328
            if( $request['code'] != 100000002){
                //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/cic/MyWish/addWish',$request);
            }
        }else{
            if(!empty($request['data']) && $request['data'] > 0){
                //iswish缓存要更改，不然下一次还是没有收藏
                if($this->redis->get(IS_WISH_BY_ . $params['CustomerID'].'_'.$params['SPU'])){
                    $this->redis->set(IS_WISH_BY_ . $params['CustomerID'].'_'.$params['SPU'],['code'=>200,'data'=>"true",'msg'=>'Success','url'=>MYDXINTERNS."/Wishlist/index.html"],CACHE_DAY);
                }

                //增加产品缓存wish数量
                if($this->redis->get(PRODUCT_INFO_ .$product_cache)){
                    $product = $this->redis->get(PRODUCT_INFO_ .$product_cache);
                    $product['WishCount'] = $product['WishCount'] + 1;
                    $this->redis->set(PRODUCT_INFO_ .$product_cache,$product,CACHE_HOUR);
                }
            }
        }
        $request['url'] = MYDXINTERNS."/Wishlist/index.html";
        return $request;
    }

    /**
     * 是否添加收藏
     */
    public function isWish($params){
        $cache = array();
        if(config('cache_switch_on')) {
            $cache = $this->redis->get(IS_WISH_BY_ . $params['CustomerID'].'_'.$params['SPU']);
        }
        if(empty($cache)){
            $request = doCurl(CIC_API . 'cic/MyWish/isWish',$params, [
                'access_token' => $this->getAccessToken()
            ]);
            if ($request['code'] != 200) {
                //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/cic/MyWish/isWish',$request);
                return $cache;
            }
            $request['url'] = MYDXINTERNS."/Wishlist/index.html";
            $cache = $request;
            $this->redis->set(IS_WISH_BY_ . $params['CustomerID'].'_'.$params['SPU'],$request,CACHE_DAY);
        }
        return $cache;
    }

}