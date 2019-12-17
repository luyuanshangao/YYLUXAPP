<?php
namespace app\app\services;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use think\Monlog;

/**
 * 创建：tinghu.liu
 * 功能：index Services
 * 时间：2018-10-12
 */
class IndexService extends BaseService {

    const EIGHT = 8;
    const FOUR= 4;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取单个国家
     * @return array|mixed
     */
    public function getCountryInfo($params){
        $result = [];
//        if(config('cache_switch_on')){
        $result = $this->redis->get(COUNTRY_BY_.$params['Code']);
//        }
        if(empty($result)){
            $base_api = new BaseApi();
            $request = $base_api->regionFind(['Code'=>$params['Code'],'ParentID'=>0]);
            /*$request = doCurl(MALL_API . '/share/region/find', ['Code'=>$params['Code'],'ParentID'=>0], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if ($request['code'] != 200) {
                //Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/share/region/find',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return $result;
    }

    /**
     * 币种列表
     */
    public function getCurrencyList(){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(CURRENCY_MENU);
        }
        if(empty($result)){
            $base_api = new BaseApi();
            $request = $base_api->getCurrencyList();
            /*$request = doCurl(MALL_API . 'share/currency/getCurrencyList', [], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if ($request['code'] != 200) {
                Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/currency/getCurrencyList',$request);
                return $result;
            }
            $result = $request['data'];
            //缓存
            if(!empty($result)){
                $this->redis->set(CURRENCY_MENU,$request['data'],CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 语种列表
     */
    public function getLangs(){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(LANG_MENU);
        }
        if(empty($result)){
            $base_api = new BaseApi();
            $request = $base_api->getLangList();
            /*$request = doCurl(MALL_API . '/share/header/langs', [], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if ($request['code'] != 200) {
                //错误信息
                Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/header/langs',$request);
                return $result;
            }
            //数据返回
            $result = isset($request['data']) && is_array($request['data']) ? $request['data'] : [];
            //缓存
            if(!empty($result)){
                $this->redis->set(LANG_MENU,$result,CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 首页coupon展示
     * @param $lang 当前语种
     * @return array
     */
    public function getIndexCouponsShow($lang){
        $coupons = array();
        //从后台配置获取conpon
        if(config('cache_switch_on')){
            $coupons = $this->redis->get('APP_HOME_COUPONS_MOBILE');
        }
        if(empty($coupons)){
            //后台配置获取coupon_ids
            $coupon_ids = $this->getSystemConfigs('MobileIndexCoupons');

            $result = (new ProductActivityService())->getIndexCouponByIds(['coupon_ids'=>!empty($coupon_ids['indexGetCoupon']) ? $coupon_ids['indexGetCoupon'] : array(),'lang' => $lang]);
            if(!empty($result['data'])){
                $coupons = $result['data'];
                $this->redis->set('APP_HOME_COUPONS_MOBILE',$coupons,CACHE_DAY);
            }
        }
        return $coupons;
    }

    /**
     * 后台系统配置信息
     */
    public function getSystemConfigs($key){
        $config = array();
        //判断缓存广告缓存
        if(config('cache_switch_on')){
            $config = $this->redis->get('APP_SYSTEM_CONFIGVAL_'.$key);
        }
        if(empty($config)){
            $result = doCurl(API_SHARE_URL.'/mall/baseConfig/getSystemConfigs',['configKey'=>$key],[
                'access_token' => $this->getAccessToken()
            ]);
            //请求后日志
            if($result['code'] != 200){
              //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$key,MALL_API.'/mall/baseConfig/getSystemConfigs',$result);
            }
            if(!empty($result['data']['ConfigValue'])){
                $config = json_decode(htmlspecialchars_decode($result['data']['ConfigValue']),true);
                $this->redis->set('APP_SYSTEM_CONFIGVAL_'.$key,$config,CACHE_DAY);
            }
        }
        return $config;
    }

    /**
     * 首页coupon描述，登录展示，未登录展示
     * @param $lang 当前语种
     * @return array
     */
    public function getIndexCouponsDetail($lang){
        $coupons = $coupons_ids =  array();
        //从后台配置获取conpon
        if(config('cache_switch_on')){
            $coupons = $this->redis->get('APP_HOME_COUPONS_DETAIL_MOBILE');
        }
        $couponKey = array();
        if(empty($coupons)){
            //后台配置获取coupon_ids
            $couponData = $this->getSystemConfigs('MobileIndexCoupons');
            foreach($couponData as $key => $val){
                if(!empty($val['coupon_id'])){
                    $coupons_ids = array_merge($coupons_ids,$val['coupon_id']);
                    $couponKey[array_pop($val['coupon_id'])] = $key;
                }
            }
            $result = (new ProductActivityService())->getIndexCouponByIds(['coupon_ids' => $coupons_ids,'lang' => $lang]);

            if(!empty($result['data'])){
                $keyVal = array();
                foreach($result['data'] as $key => $val){
                    if(!empty($couponKey[$val['coupon_id']])){
                        $keyVal[$couponKey[$val['coupon_id']]] = $val;
                        $keyVal[$couponKey[$val['coupon_id']]]['imgUrl'] = !empty($couponData[$couponKey[$val['coupon_id']]]['ImageUrl']) ?  $couponData[$couponKey[$val['coupon_id']]]['ImageUrl'] : '';
                        $keyVal[$couponKey[$val['coupon_id']]]['LinkUrl'] = !empty($couponData[$couponKey[$val['coupon_id']]]['LinkUrl']) ?  $couponData[$couponKey[$val['coupon_id']]]['LinkUrl'] : '';
                    }
                }
                $this->redis->set('APP_HOME_COUPONS_DETAIL_MOBILE',$keyVal,CACHE_DAY);
                return $keyVal;
            }
        }
        return $coupons;
    }

    /**
    1.展示3个配置的分类信息，配置3个分配的背景图；
    2.	根据配置的分类ID,展示分类名称；
    3.	根据配置的分类ID，取半年内有动销的产品，随机展示3个产品图；
    4.	点击产品图片链接，进入该分类列表页，展示该分类ID下的所有产品信息
     */
    public function getTopDataByConfigCategory($params){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get('APP_INDEX_CATEGORY_TOP_RANGE');
        }
        if(empty($data)){
            $result = doCurl(API_SHARE_URL.'/mall/product/getTopDataByConfigCategory',$params,[
                'access_token' => $this->getAccessToken()
            ]);
//            var_dump(API_SHARE_URL.'/mall/product/getTopDataByConfigCategory',$result);
//            die;
            if($result['code'] != 200){
                //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/product/getTopDataByConfigCategory',$result);
                return $data;
            }
            $data = $result['data'];
            if(is_array($data) && !empty($data)){
                $this->redis->set('APP_INDEX_CATEGORY_TOP_RANGE',$data,CACHE_DAY);
            }
        }
        if(!empty($data)){
            foreach($data as $key => $val){
                //随机返回三个产品
                $data[$key]['product'] = CommonLib::getRandArray($val['product'],3);
                //产品图片替换
                $data[$key]['product'] = handleProductImgBySize($data[$key]['product']);
                if(!empty($data[$key]['product'])){
                    $data[$key]['product_img'] = array_column($data[$key]['product'], 'FirstProductImage');;
                }else{
                    $data[$key]['product_img']=[];
                }

                unset($data[$key]['product']);
            }
        }
        return array_values($data);
    }

    /**
     * 获取商城配置的运费天数
     */
    public function getShippingTime(){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get('SHIPPING_CONFIG_TIME');
        }
        if(empty($data)){
            $result = doCurl(MALL_API.'/mall/baseConfig/getShippingConfig',[],[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
               // logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getShippingConfig',$result);
                return $data;
            }
            $data = $result['data'];
            if(is_array($data) && !empty($data)){
                $this->redis->set('SHIPPING_CONFIG_TIME',$data,CACHE_DAY);
            }
        }
        return $data;
    }
}