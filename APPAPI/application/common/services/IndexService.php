<?php
namespace app\common\services;

use app\app\services\ProductActivityService;
use app\app\services\ProductService;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Cookie;
use think\Exception;
use think\Monlog;
use app\app\services\rateService;
/**
 * 创建：钟宁
 * 功能：商城首页数据
 * 时间：2018-05-17
 */
class IndexService extends Api{

    const EIGHT = 8;
    const FOUR  = 4;
    const SIX  = 6;
    const THREE  = 3;

    public $redis;
    public function __construct()
    {
        $this->redis = new RedisClusterBase();
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
            $request = doCurl(MALL_API . '/share/header/langs', [], [
                'access_token' => $this->getAccessToken()
            ]);
            if ($request['code'] != 200) {
                //错误信息
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/header/langs',$request);
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
     * 币种列表
     */
    public function getCurrencyList(){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(CURRENCY_MENU);
        }
        if(empty($result)){
            $request = doCurl(MALL_API . 'share/currency/getCurrencyList', [], [
                'access_token' => $this->getAccessToken()
            ]);
            if ($request['code'] != 200) {
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/currency/getCurrencyList',$request);
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
     * 商城首页头部国家列表
     * @param $isFocus = 1默认所有
     * @return array|mixed
     */
    public function getCountryList($isFocus = 1){
        $params = array();
        $result = [];
        if(config('cache_switch_on')){
            //区分重点国家，1是所有国家列表，2是过滤了重点国家
            $result = $this->redis->get(COUNTRY_MENU.'_'.$isFocus);

        }
        //区分重点国家，1是所有国家列表，2是过滤了重点国家
        if($isFocus == 2){
            $params['isFocus'] = $isFocus;
        }
        if(empty($result)){
            //新增条件，去除重点国家
            $request = doCurl(MALL_API . '/share/region/getHeaderCountry', $params, [
                'access_token' => $this->getAccessToken()
            ]);
            if ($request['code'] != 200) {
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/region/getHeaderCountry',$request);
                return $result;
            }
            $result = $request['data'];
            if(!empty($result) && is_array($result)){
                $this->redis->set(COUNTRY_MENU.'_'.$isFocus,$result,CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 获取单个国家
     * @return array|mixed
     */
    public function getCountryInfo($params){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(COUNTRY_BY_.$params['Code']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API . '/share/region/find', ['Code'=>$params['Code'],'ParentID'=>0], [
                'access_token' => $this->getAccessToken()
            ]);
            if ($request['code'] != 200) {
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/share/region/find',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return $result;
    }

    /**
     * 商城重点国家列表
     * @return array|mixed
     */
    public function getFocusCountryList(){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(COUNTRY_MENU.'_FOCUS');
        }
        if(empty($result)){
            $request = doCurl(MALL_API . '/share/region/getFocusRegion', [], [
                'access_token' => $this->getAccessToken()
            ]);
            if ($request['code'] != 200) {
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/region/getFocusCountryList',$request);
                return $result;
            }
            $result = $request['data'];
            if(is_array($result) && !empty($result)){
                //打标签，区分最后一个重点国家，方便前端JS区分
                $arr = array_slice($result,-1,1,true);
                $lastKey = key($arr);
                $result[$lastKey]['isLastFocus'] = 1;
                //新增缓存
                $this->redis->set(COUNTRY_MENU.'_FOCUS',$result,CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 商城LOGO图片地址
     */
    public function getLogo(){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get(MALL_HOME_LOGO);
        }
        if(empty($data)){
            $result = doCurl(MALL_API.'/mall/baseConfig/getLogo',[],[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getLogo',$result);
                return $data;
            }
            $data = $result['data'];
        }
        return $data;
    }

    /**
     * 商城首页顶部通栏广告图片地址
     */
    public function getTopBrands(){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get(MALL_HOME_TOP_BANNER);
        }
        if(empty($data)){
            $result = doCurl(MALL_API.'/mall/baseConfig/getTopBrands',[],[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getTopBrands',$result);
                return $data;
            }
            $data = $result['data'];
        }
        return $data;
    }

    /**
     * 搜索栏下方位置，搜索热度词
     */
    public function getSearchHotkey($params){
        $result = [];
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        if(config('cache_switch_on')){
            $result = $this->redis->get(SEARCH_HOT_KEY_.$lang);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/baseConfig/getSearchHotWord',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/baseConfig/getSearchHotWord',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return isset($result['language'][$lang]['HotWords']) ? $result['language'][$lang]['HotWords'] : $result['language'][DEFAULT_LANG]['HotWords'];
    }

    /**
     * falsh 首页（5）产品
     */
    public function getFlashData($params){
        $activity_products = array();
        $time = 0;
        if(config('cache_switch_on')) {
            $activity_products = $this->redis->get(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$params['country']);
        }
        if(empty($activity_products)){
            try{
                $result = doCurl(MALL_API.'/mall/productActivity/getHomeFlashList',$params,[
                    'access_token' => $this->getAccessToken()
                ]);
                if($result['code'] != 200){
                    logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productActivity/getHomeFlashList',$result);
                    $data['data'] = array();
                    return $data;
                }
                if(!empty($result['data']) && is_array($result['data'])){
                    $activity_products = $result['data'];
                }
            }catch (Exception $e){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productActivity/getHomeFlashList',$e->getMessage());
                return array();
            }
        }

        $products = isset($activity_products['product']) ? $activity_products['product'] : [];
        $time = $this->redis->ttl(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$params['country']);
        //定时没有及时清除活动
        if($time < 0){
            $time = 0;
            $products = [];
            $this->redis->rm(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$params['country']);
        }
        //取数限制
        if(isset($params['count'])){
            $products = CommonLib::getRandArray($products,$params['count']);
        }
        //币种切换
        if(!empty($products) && is_array($products)){
            if($params['currency'] != DEFAULT_CURRENCY){

                $currentRate = (new rateService())->getCurrentRate($params['currency']);
                foreach($products as $key => $val){
                    if(isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00'){
                        $products[$key]['OriginalPrice'] = sprintf("%01.2f",(double)$val['OriginalPrice'] * $currentRate);
                    }
                    if(isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00'){
                        $products[$key]['SalesPrice'] = sprintf("%01.2f",(double)$val['SalesPrice'] * $currentRate);
                    }
                }
            }
        }
        $data['time'] = $time;
        $data['data'] = $products;
        return $data;
    }

    /**
     * New Arrivals 首页（8）产品
     */
    public function getNewArrivalsData($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? $params['country'] : '';
        $products = array();

        //判断是否有缓存+语种
        if(config('cache_switch_on')) {
            $products = $this->redis->get(NEW_ARRIVALS_DATA_ . $lang.'_'.$country);
        }
        if(empty($products)){
            //获取新品数据
            $result = doCurl(MALL_API.'/mall/product/getNewArrivalsProducts',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/product/getNewArrivalsProducts',$result);
                return $products;
            }
            $products = $result['data'];
        }
        if(!empty($products) && is_array($products)){
            //取数限制
            if(isset($params['count'])){
                //随机打乱
                shuffle($products);
                $products = CommonLib::getRandArray($products,$params['count']);
            }
            //币种切换
            if($params['currency'] != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$params['currency']);
            }
        }
        return $products;
    }

    /**
     * 分类页面新品推荐
     */
    public function getClassNewArrivalsData($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? $params['country'] : '';
        $products = array();
        if(isset($params['secondCategory']) && !empty($params['secondCategory'])){
            $params['category'] = $params['secondCategory'];
        }else{
            $params['category'] = $params['firstCategory'];
        }
        //判断是否有缓存+语种
        if(config('cache_switch_on')) {
            $products = $this->redis->get(NEW_ARRIVALS_DATA_ .$params['category'].'_'. $lang.'_'.$country);
        }
        if(empty($products)){
            //获取新品数据
            $result = doCurl(MALL_API.'/mall/product/getClassNewArrivalsData',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/product/getClassNewArrivalsData',$result);
                return $products;
            }
            $products = $result['data'];
        }
        if(!empty($products) && is_array($products)){
            //取数限制
            if(isset($params['count'])){
                $products = CommonLib::getRandArray($products,$params['count']);
            }
            //币种切换
            if($params['currency'] != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$params['currency']);
            }
        }
        return $products;
    }



    /**
     * key获取config配置的spu,根据spu获取对应Key的产品列表
     * @param $params
     * @return array|mixed
     */
    public function getConfigDataProductByKey($params){
        $products = array();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? $params['country'] : '';
        $key = $params['key'];
        //判断是否有缓存
        if(config('cache_switch_on')) {
            $products = $this->redis->get(PRODUCT_CONFIG_DATA_BY_.$key. '_' . $lang.'_'.$country);
        }
        if(empty($products)){
            $result = doCurl(MALL_API.'/mall/baseConfig/getProductDataByKey',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/baseConfig/getProductDataByKey',$result);
                return array();
            }
            $products = $result['data'];
        }

        if(!empty($products) && is_array($products)){
            //随机打乱
            shuffle($products);
            //取数限制
            if(isset($params['count'])){
                $products = CommonLib::getRandArray($products,$params['count']);
            }
            //币种切换
            if($params['currency'] != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$params['currency']);
            }
        }
        return $products;
    }

    /**
     * 广告数据接口
     * @param array $params 请求参数
     * @param int $type 请求类型
     * @return array
     */
    public function getAdvertising($params,$type =1){
        $advertisingData = array();
        //判断缓存广告缓存
        if(config('cache_switch_on')){
            $advertisingData = $this->redis->get(ADVERTISING_INFO_BY_.$params['key']);
        }
        if(empty($advertisingData)){
            $result = doCurl(MALL_API.'/mall/advertising/get',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            //请求后日志
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/advertising/get',$result);
                return $advertisingData;
            }
            $advertisingData = isset($result['data']) && is_array($result['data']) ? $result['data'] : $advertisingData;
        }
        if(!empty($advertisingData)){
            //公共方法整合banner数据
            switch($type){
                case 1://Banners
                    $advertisingData = CommonService::getBannerInfos($advertisingData,$params['lang']);
                    break;
                case 2://SKUs
                    break;
                case 3://Keyworks
                    $advertisingData = CommonService::getKeywordsInfos($advertisingData,$params['lang']);
                    break;

            }
        }
        return $advertisingData;
    }

    /**
     * 数据整理接口，方便前端展示数据 --广告接口+产品接口数据整合
     * @param array $keys 通过key获取后台配置spu
     * @param $lang 语种
     * @param $currency 币种
     * @param string $country 国家
     * @return mixed
     */
    public function getNewAndTopAndUnder($keys,$lang,$currency,$country){
        foreach($keys as $k => $key){
            //获取广告数据
            $advertisingData = $this->getAdvertising(['key'=>$key,'lang'=>$lang]);
            //newArraveral、topseller、under模板位置，广告数据都只有一张，抛出第一个数组
            $data[$k]['advertising'] = !empty($advertisingData) ? array_shift($advertisingData) : array();

            $params = [
                'lang' => $lang ,//当前语种
                'currency' => $currency,//当前币种
                'count'=> self::EIGHT,//取数
                'country' => $country //取当前国家价格
            ];
            //产品数据
            switch($k){
                //8个新品
                case 'newArrival':
                    $params['isNewProduct'] = true;//是否是新品
                    $data[$k]['product'] = $this->getNewArrivalsData($params);
                    break;
                //8个topseller
                case 'top':
                    $params['key'] = 'TopSellers';
                    $data[$k]['product'] = $this->getConfigDataProductByKey($params);
                    break;
                //8个under
                case 'under':
                    $params['key'] = 'UnderPrice-0.99';
                    $data[$k]['product'] = $this->getConfigDataProductByKey($params);
                    break;
            }
        }
        return $data;
    }

    /**
     * 广告列表接口
     * @param $keys
     * @return array|mixed
     */
    public function getAdvertisingList($keys){
        $result= array();
        $string = implode('_',$keys);
        $cache_key = CommonLib::getCacheKey(['key'=>$string]);
        if(config('cache_switch_on')){
            $result = $this->redis->get(ADVERTISING_INFO_BY_.$cache_key);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/advertising/lists',['key'=>$keys],[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,['key'=>$keys],MALL_API.'/mall/advertising/lists',$request);
            }
            if(isset($request['data']) && is_array($request['data'])){
                $this->redis->set(ADVERTISING_INFO_BY_.$cache_key,$request['data'],CACHE_DAY);
                return $request['data'];
            }
            return array();
        }
        return $result;
    }


    /**
     * 产品+广告 -- 首页SmartPhones板块数据
     * @param array $keys 通过key获取spu列表
     * @param $lang 语种
     * @param $currency 币种
     * @param string $country 国家
     * @return mixed
     */
    public function getSmartPhonesTemplate($keys,$lang,$currency,$country){
        $data['advertising'] = $data['top'] = $data['product'] = array();
        $result = $this->getAdvertisingList($keys);
        if(!empty($result)){
            //配合前端，数据整合
            foreach($result as $value){
                if($value['Key'] == 'dx_home_smartphone_1'){
                    //公共方法整合banner数据
                    $data['advertising'] = (CommonService::getBannerInfos($value,$lang));
                }else{
                    $topAd = (CommonService::getBannerInfos($value,$lang));
                    if(!empty($topAd)){
                        $data['top'][] = array_shift($topAd);
                    }
                }
            }
        }
        //产品数据
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> self::EIGHT,//取数
            'key'=>'Smartphones',//SmartPhones 首页（8）产品
            'country' => $country //获取当前国家的产品价格
        ];
        $result = $this->getConfigDataProductByKey($params);
        if(!empty($result)){
            //将数组分割4个为一个数组
            $data['product'] = array_chunk($result,self::FOUR);
        }
        return $data;
    }

    /**
     * Electronics 板块数据 产品数据+广告数据
     * @param string $key 获取当前key的spu
     * @param $lang 语种
     * @param $currency 币种
     * @param string $country 国家
     * @return mixed
     */
    public function getElectronicsTemplate($key,$lang,$currency,$country){
        //广告数据
        $data['advertising'] = $this->getAdvertising(['key'=>$key,'lang'=>$lang]);

        //产品数据
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> self::EIGHT,//取数
            'key'=>'Electronics',//Electronic 首页（8）产品
            'country' => $country //获取当前国家的产品价格
        ];
        $data['product'] = $this->getConfigDataProductByKey($params);

        return $data;
    }


    /**
     * Diy&Fun 板块 广告+产品
     * @param array $keys 广告系统编码唯一值
     * @param $lang 当前语种
     * @param $currency 当前币种
     * @param string $country 国家
     * @return array
     */
    public function getDiyAndFunBannerTemplate($keys,$lang,$currency,$country){
        $data['advertising'] = $data['top'] = $data['product'] = [];

        //广告数据
        $result = $this->getAdvertisingList($keys);
        if(!empty($result)){
            //配合前端，数据整合
            foreach($result as $value){
                if($value['Key'] == $keys[0]){
                    //公共方法整合banner数据
                    $data['advertising'] = (CommonService::getBannerInfos($value,$lang));
                }else{
                    $topAd = (CommonService::getBannerInfos($value,$lang));
                    if(!empty($topAd)){
                        $data['top'][] = array_shift($topAd);
                    }
                }
            }
        }
        //产品数据
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> self::EIGHT,//取数
            'key'=>'DiyAndFun',//DiyAndFun 首页（8）产品
            'country' => $country //当前国家的产品价格
        ];
        $result = $this->getConfigDataProductByKey($params);
        if(!empty($result)){
            //随机打乱
            shuffle($result);
            //将数组分割4个为一个数组
            $data['product'] = array_chunk($result,self::FOUR);
        }
        return $data;
    }


    /**
     * Indoor 广告轮播图片
     * @param string $key 广告系统编码唯一值
     * @param $lang 语种
     * @param $currency 币种
     * @param string $country 国家
     * @return array
     */
    public function getIndoorAndOutDoorTemplate($key,$lang,$currency,$country){
        //广告数据
        $data['advertising'] = $this->getAdvertising(['key'=>$key,'lang'=>$lang]);

        //产品数据
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> self::EIGHT,//取数
            'key'=>'IndoorAndOutdoor',//Indoor&Outdoor 首页(8)产品
            'country' => $country //根据国家获取产品的价格
        ];
        $data['product'] = $this->getConfigDataProductByKey($params);

        return $data;
    }

    /**
     * 品牌区域
     * @param string $key 广告系统编码唯一值
     * @param $lang 当前语种
     * @param $currency 当前币种
     * @param string $country 国家
     * @return array
     */
    public function getBrandsTemplate($key,$lang,$currency,$country){
        $data = array();
        //广告数据
        $data['advertising'] = $this->getAdvertising(['key'=>$key,'lang'=>$lang]);

        $result = $this->getBrandsImg();
        $data['brands'] = [];
        if(!empty($result)){
            $data['brands'] = CommonLib::getRandArray($result,12);
        }
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> self::EIGHT,//取数
            'key'=>'Brands',//品牌区域 首页（8）产品
            'country' => $country //通过国家获取产品价格
        ];
        $data['product'] = $this->getConfigDataProductByKey($params);
        return $data;
    }

    /**
     * Branding DX -- Google Code for Remarketing tag
     */
    public function getGoogleConversionLabel($params){
        $google = config('GoogleConversionLabel');
        if(isset($google[$params['key']])){
            return $google[$params['key']];
        }
        return null;
//        $products = array();
//        if(config('cache_switch_on')){
//            $products = $this->redis->get(GOOGLE_CONVERSION_LABEL);
//        }
//        if(empty($products)){
//            $result = doCurl(MALL_API.'/mall/baseConfig/getGoogleConversionLabel',[],[
//                'access_token' => $this->getAccessToken()
//            ]);
//            if($result['code'] != 200){
//                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getGoogleConversionLabel',$result);
//                return $result;
//            }
//            $products = $result['data'];
//        }
//        $arr = null;
//        if(!empty($products)){
//            $arr = CommonLib::filterArrayByKey($products['GoogleConversionLabel'],'Key',$params['key']);
//            if(!empty($arr)){
//                return $arr['Value'];
//            }
//        }
//        return $arr;
    }

    /**
     * 基础配置 -- 品牌logo图片列表
     * @return array
     */
    public function getBrandsImg(){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(HOME_BRAND_LOGO_IMG);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/baseConfig/getBrandsLogo',[],[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getBrandsLogo',$request);
                return $result;
            }
            $result = $request['data'];
        }
        if(!empty($result)){
            foreach($result as $key => $value){
                $result[$key]['ImageUrl'] = IMG_URL.$value['Brand_Icon_Url'];
                $result[$key]['LinkUrl'] = '/s/'.$value['BrandName'];
                $result[$key]['MainText'] = $value['BrandName'];
                $result[$key]['SubText'] = $value['BrandName'];
            }
        }
        return $result;
    }

    /**
     * 首页文本广告数据处理
     */
    public function homeTextAdvertising($lang,$keys){
        $resutl = array();
        $data = $this->getAdvertisingList($keys);
        if(!empty($data)){
            foreach($data as $key => $val){
                $resutl[$val['Key']] = CommonService::getKeywordsInfos($val,$lang);
            }
        }
        return $resutl;
    }

    /**
     * 产品详情页，payment信息
     */
    public function getPaymentConfig(){
        $result = array();
        if(config('cache_switch_on')) {
            $result = $this->redis->get(PRODUCT_PAYMENT_CONFIG);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/baseConfig/getPayment',[],[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getPayment',$request);
                return $request;
            }
            $result = $request['data'];
        }
        return $result;
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
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getShippingConfig',$result);
                return $data;
            }
            $data = $result['data'];
            if(is_array($data) && !empty($data)){
                $this->redis->set('SHIPPING_CONFIG_TIME',$data,CACHE_DAY);
            }
        }
        return $data;
    }


    /**
     * Automobiles区域
     * @param string $key 获取当前key的spu
     * @param $lang 语种
     * @param $currency 币种
     * @param string $country 国家
     * @return mixed
     */
    public function getAutomobilesTemplate($key,$lang,$currency,$country){
        //广告数据
        $data['advertising'] = $this->getAdvertising(['key'=>$key,'lang'=>$lang]);

        //产品数据
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> 18,//取数
            'key'=>'Automobiles',//Electronic 首页（8）产品
            'country' => $country //获取当前国家的产品价格
        ];
        $data['product'] = $this->getConfigDataProductByKey($params);
        if(!empty($data['product'])){
            //将数组分割6个为一个数组
            $data['product'] = array_chunk($data['product'],self::SIX);
        }
        return $data;
    }

    /**
     * Home & Lighting区域
     * @param array $keys 广告系统编码唯一值
     * @param $lang 当前语种
     * @param $currency 当前币种
     * @param string $country 国家
     * @return array
     */
    public function getHomeAndLightingTemplate($keys,$lang,$currency,$country){
        $data['advertising'] = $data['top'] = $data['product'] = [];

        //广告数据
        $result = $this->getAdvertisingList($keys);
        if(!empty($result)){
            //配合前端，数据整合
            foreach($result as $value){
                if($value['Key'] == $keys[0]){
                    //公共方法整合banner数据
                    $data['advertising'] = (CommonService::getBannerInfos($value,$lang));
                }else{
                    $topAd = (CommonService::getBannerInfos($value,$lang));
                    if(!empty($topAd)){
                        $data['top'][] = array_shift($topAd);
                    }
                }
            }
        }
        //产品数据
        $params = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'count'=> 9,//取数
            'key'=>'HomeAndLighting',//DiyAndFun 首页（8）产品
            'country' => $country //当前国家的产品价格
        ];
        $result = $this->getConfigDataProductByKey($params);
        if(!empty($result)){
            //随机打乱
            shuffle($result);
            //将数组分割3个为一个数组
            $data['product'] = array_chunk($result,self::THREE);
        }
        return $data;
    }

    /**
     * 移动端开关跳转
     * @return array|mixed
     */
    public function getMobileJumpStatus(){
        $status = Cookie::get('mobileJupmSwitch');
        if(empty($status)){
            if(config('cache_switch_on')){
                $status = $this->redis->get('MOBILE_JUMP_STATUS');
            }
            if(empty($status)){
                $result = doCurl(MALL_API.'/mall/baseConfig/getMobileJumpStatus',[],[
                    'access_token' => $this->getAccessToken()
                ]);
                if($result['code'] != 200){
                    logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getMobileJumpStatus',$result);
                }
                if(!empty($result['data'])){
                    $status = $result['data'];
                    $this->redis->set('MOBILE_JUMP_STATUS',$status,CACHE_DAY);
                }
            }
        }
        //显示弹框
        if($status == 1){
            Cookie::set('mobileJupmSwitch',$status,['domain'=>MALL_DOMAIN]);
        }else{
            //2不显示弹框
            if($status != 2){
                Cookie::set('mobileJupmSwitch',0,['domain'=>MALL_DOMAIN]);
            }
        }
    }


    /**
     * 获取首页所有的banner图片
     * @param array $keys 后台配置广告key值
     * @param $lang 语种
     * @return array
     */
    public function getHomeBannerInfo($keys,$lang){
        $banners = array();
        $result = $this->getAdvertisingList($keys);
        if(!empty($result)){
            //配合前端，数据整合
            foreach($result as $value){
                $banners[$value['Key']] = CommonService::getBannerInfos($value,$lang);
            }
        }
        return $banners;
    }

    /**
     * 后台系统配置信息
     */
    public function getSystemConfigs($key){
        $config = array();
        //判断缓存广告缓存
        if(config('cache_switch_on')){
            $config = $this->redis->get('SYSTEM_CONFIGVAL_'.$key);
        }
        if(empty($config)){
            $result = doCurl(MALL_API.'/mall/baseConfig/getSystemConfigs',['configKey'=>$key],[
                'access_token' => $this->getAccessToken()
            ]);
            //请求后日志
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$key,MALL_API.'/mall/baseConfig/getSystemConfigs',$result);
            }
            if(!empty($result['data']['ConfigValue'])){
                $config = json_decode(htmlspecialchars_decode($result['data']['ConfigValue']),true);
                $this->redis->set('SYSTEM_CONFIGVAL_'.$key,$config,CACHE_DAY);
            }
        }
        return $config;
    }

    /**
     * 首页coupon展示
     * @param $lang 当前语种
     * @return array
     */
    public function getCoupons($lang){
        $coupons = array();
        //从后台配置获取conpon
        if(config('cache_switch_on')){
            $coupons = $this->redis->get('HOME_COUPONS');
        }
        if(empty($coupons)){
            //后台配置获取coupon_ids
            $coupon_ids = $this->getSystemConfigs('IndexCoupons');

            $result = (new ProductActivityService())->getCouponByIds(['coupon_ids'=>!empty($coupon_ids['indexRightCoupon']) ? $coupon_ids['indexRightCoupon'] : array(),'lang' => $lang]);
            if(!empty($result['data'])){
                $coupons = $result['data'];
                $this->redis->set('HOME_COUPONS',$coupons,CACHE_DAY);
            }
        }
        return $coupons;
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
            $coupons = $this->redis->get('HOME_COUPONS_DETAIL');
        }
        $couponKey = array();
        if(empty($coupons)){
            //后台配置获取coupon_ids
            $couponData = $this->getSystemConfigs('IndexCoupons');
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
                $this->redis->set('HOME_COUPONS_DETAIL',$keyVal,CACHE_DAY);
                return $keyVal;
            }
        }
        return $coupons;
    }

    /**
     * 首页coupon产品展示
     * @param $lang 当前语种
     * @return array
     */
    public function getCouponsProduct($lang,$currency,$country){
        $coupons = $coupon_ids = array();
        //从后台配置获取conpon
        if(config('cache_switch_on')){
            $coupons = $this->redis->get('IndexCouponsRangeProduct');
        }
        if(empty($coupons)){
            //后台配置获取coupon_ids
            $coupon_config = $this->getSystemConfigs('IndexCouponsActivityPage');

            if(!empty($coupon_config)){
                $coupon_ids = CommonLib::getColumn('coupon_id',$coupon_config);
            }
            $params = [
                'lang'=>$lang,
                'currency' => $currency,
                'country' => $country,
                'coupon_ids' =>$coupon_ids
            ];
            $result = doCurl(MALL_API.'/mall/product/getCouponsProduct',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            //请求后日志
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/coupon/getHomeCouponList',$result);
            }
            if(!empty($result['data'])){
                $this->redis->set('IndexCouponsRangeProduct',$result['data'],CACHE_HOUR);
                $coupons = $result['data'];
            }
        }
        $show = array();
        if(!empty($coupons)){
            //整理数据，每一组需要不同的coupon
            for($i = 1; $i <= 5; $i++) {
                foreach ($coupons as $key => $val) {
                    //随机获取一个产品
                    $show[$i][] = $val[array_rand($val)];
                }
                //打乱循序
                shuffle($show[$i]);
            }
        }
        return $show;
    }

    /**
     * 热销产品的背景图片，背景颜色，coupon信息；
     * 新品页面的背景图片，背景颜色，coupon信息；
     * @param $lang 当前语种
     * @return array
     */
    public function hotProductPageConfig($coupon_id,$lang,$currency='',$country=''){
        $banner = $result = $coupon = $productData = array();
        //从后台配置获取conpon
        if(config('cache_switch_on')){
            $result = $this->redis->get('APP_HotProductPageConfig_'.$coupon_id.$lang.$currency.$country);
        }
        if(empty($result)){
            //后台配置获取coupon_ids
            $coupon_config = $this->getSystemConfigs('IndexCouponsActivityPage');
            //获取广告图片
            if(!empty($coupon_config)){
                $ag_keys = CommonLib::getColumn('bg_img',$coupon_config);
                $banner = $this->getHomeBannerInfo($ag_keys,$lang);

                $coupon_ids = array();
                $product_ids = array();
                foreach($coupon_config as $val){
                    $coupon_ids = array_unique(array_merge($coupon_ids,$val['coupon_show']));
                    if(!empty($val['product_show'])){
                        $product_ids = array_unique(array_merge($product_ids,$val['product_show']));
                    }
                }
                //获取coupon信息
                $couponData = (new ProductActivityService())->getIndexCouponByIds(['coupon_ids'=>$coupon_ids,'lang' => $lang]);
                if(!empty($couponData['data'])){
                    $coupon = $couponData['data'];
                }

                //获取产品
                if(!empty($product_ids)){
                    $productData = (new ProductService())->getTopProductByOrderProduct(['product_ids'=>$product_ids,'lang' => $lang,'country'=>$country]);
                    //币种切换
                    if(!empty($productData['data']) && is_array($productData['data'])){
                        if($currency != DEFAULT_CURRENCY){
                            $productData['data'] = $this->changeCurrentRate($productData['data'],$currency);
                        }
                    }
                }

                //整理数据
                foreach($coupon_config as $val){
                    $result[$val['coupon_id']]['bg_color'] = $val['bg_color'];
                    $result[$val['coupon_id']]['banner'] = isset($banner[$val['bg_img']][0]) ? $banner[$val['bg_img']][0] : array();
                    if(!empty($val['coupon_show'])){
                        $coupon_show = array();
                        foreach($val['coupon_show'] as $cid){
                            if(!empty($coupon[$cid])){
                                //1是手动，2是自动
                                if($coupon[$cid]['coupon_type'] == 1){
                                    //手动只能2个，前端展示
                                    if(!empty($coupon_show['manual']) && count($coupon_show['manual']) > 1){
                                        continue;
                                    }
                                    $coupon_show['manual'][] = $coupon[$cid];
                                }else{
                                    //自动只能放4个，前端展示
                                    if(!empty($coupon_show['auto']) && count($coupon_show['auto']) > 3){
                                        continue;
                                    }
                                    $coupon_show['auto'][] = $coupon[$cid];
                                }
                            }
                        }
                        $result[$val['coupon_id']]['coupon'] = $coupon_show;
                    }
                    //产品数据
                    if(!empty($val['product_show'])){
                        //需要查找
                        $result[$val['coupon_id']]['product'] = !empty($productData['data']) ? $productData['data'] : array();
                    }
                }
            }
            //获取coupon
            $this->redis->set('APP_HotProductPageConfig_'.$coupon_id.$lang.$currency.$country,$result,CACHE_HOUR);
        }
        if(!empty($result[$coupon_id])){
            return $result[$coupon_id];
        }else{
            return $result['hotProduct'];
        }
    }

    /**
     * 热销产品的背景图片，背景颜色，coupon信息；
     * 新品页面的背景图片，背景颜色，coupon信息；
     * @param $lang 当前语种
     * @return array
     */
    public function ActivityPageConfig($coupon_id,$lang,$currency='',$country=''){
        $banner = $result = $coupon = $productData = array();
        //从后台配置获取conpon
        if(config('cache_switch_on')){
            $result = $this->redis->get('APP_HotProductPageConfig_'.$coupon_id.$lang.$currency.$country);
        }
        if(empty($result)){
            //后台配置获取coupon_ids
            $coupon_config = $this->getSystemConfigs('IndexCouponsActivityPage');
            //获取广告图片
            if(!empty($coupon_config)){
                $ag_keys = CommonLib::getColumn('bg_img',$coupon_config);
                $banner = $this->getHomeBannerInfo($ag_keys,$lang);

                $coupon_ids = array();
                $product_ids = array();
                foreach($coupon_config as $val){
                    $coupon_ids = array_unique(array_merge($coupon_ids,$val['coupon_show']));
                    if(!empty($val['product_show'])){
                        $product_ids = array_unique(array_merge($product_ids,$val['product_show']));
                    }
                }
                //获取coupon信息
                $couponData = (new ProductActivityService())->getIndexCouponByIds(['coupon_ids'=>$coupon_ids,'lang' => $lang]);
                if(!empty($couponData['data'])){
                    $coupon = $couponData['data'];
                }



                //整理数据
                foreach($coupon_config as $val){
//                    $result[$val['coupon_id']]['bg_color'] = $val['bg_color'];
//                    $result[$val['coupon_id']]['banner'] = isset($banner[$val['bg_img']][0]) ? $banner[$val['bg_img']][0] : array();
                    if(!empty($val['coupon_show'])){
                        $coupon_show = array();
                        foreach($val['coupon_show'] as $cid){
                            if(!empty($coupon[$cid])){
                                //1是手动，2是自动
                                if($coupon[$cid]['coupon_type'] == 1){
                                    //手动只能2个，前端展示
                                    if(!empty($coupon_show['manual']) && count($coupon_show['manual']) > 1){
                                        continue;
                                    }
                                    $coupon_show['manual'][] = $coupon[$cid];
                                }else{
                                    //自动只能放4个，前端展示
                                    if(!empty($coupon_show['auto']) && count($coupon_show['auto']) > 3){
                                        continue;
                                    }
                                    $coupon_show['auto'][] = $coupon[$cid];
                                }
                            }
                        }
                        $result[$val['coupon_id']]['coupon'] = $coupon_show;
                    }

                }
            }
            //获取coupon
            $this->redis->set('APP_HotProductPageConfig_'.$coupon_id.$lang.$currency.$country,$result,CACHE_HOUR);
        }
        if(!empty($result[$coupon_id])){
            return $result[$coupon_id];
        }else{
            return $result['hotProduct'];
        }
    }

}