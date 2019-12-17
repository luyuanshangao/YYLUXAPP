<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use app\app\model\BrandModel;
use app\app\model\ConfigDataModel;
use app\app\model\ProductModel;
use app\app\model\SysConfigModel;
use think\Cache;


/**
 * 基础配置数据
 */
class ConfigDataService extends  BaseService
{
    const CACHE_KEY = 'DX_DATACONFIG_';
    const CACHE_TIME = 3600;
    const CACHE_TIME_DAY = 86400;
    const RESULT_COUNT = 50;//配置产品数量超过100，取50返回

    /**
     * 搜索栏下拉出现，分类搜索热度词
     */
    public function getCategoryHotWord($params){
        //判断是否有缓存
//        if(Cache::has(self::CACHE_KEY.'getCategoryHotWord')){
//            return Cache::get(self::CACHE_KEY.'getCategoryHotWord');
//        }
        $keyWord = (new ConfigDataModel())->getDataConfig(['key'=>'IndexFirstCategorySearch']);
//        Cache::set(Self::CACHE_KEY .'getCategoryHotWord', $keyWord, Self::CACHE_TIME * 100);
        return $keyWord;
    }

    /**
     * 搜索栏下方位置，搜索热度词
     */
    public function getSearchHotWord($params){
        $result = array();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;

        if(config('cache_switch_on')){
            $result = $this->redis->get(SEARCH_HOT_KEY_.$lang);
        }
        if(empty($result)){
            $result = (new ConfigDataModel())->getMallSetData('language.'.$lang);
            if(!empty($result)){
                $this->redis->set(SEARCH_HOT_KEY_.$lang,$result,CACHE_DAY);
            }
        }
        if(!empty($result)){
            $data = isset($result['language'][$lang]['HotWords']) ? $result['language'][$lang]['HotWords'] : [];
            return $data;
        }
        return array();
    }

    /**
     * 商城首页顶部通栏广告图片地址配置
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getTopBanner(){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get(MALL_HOME_TOP_BANNER);
        }
        if(empty($data)){
            $data = (new SysConfigModel())->getTopBanner();
            if(!empty($data)){
                $this->redis->set(MALL_HOME_TOP_BANNER,$data ,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 商城LOGO图片地址
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getLogo(){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get(MALL_HOME_LOGO);
        }
        if(empty($data)){
            $data = (new SysConfigModel())->getLogo();
            if(!empty($data)){
                $this->redis->set(MALL_HOME_LOGO,$data ,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }


    /**
     * Google Code for Remarketing tag
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getGoogleConversionLabel(){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(GOOGLE_CONVERSION_LABEL);
        }
        if(empty($result)){
            $result = (new ConfigDataModel())->getMallSetData('GoogleConversionLabel');
            if(!empty($result)){
                $this->redis->set(GOOGLE_CONVERSION_LABEL,$result ,CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 首页推荐品牌logo图片
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getBrandsLogo(){
        $brand = [];
        if(config('cache_switch_on')){
            $brand = $this->redis->get(HOME_BRAND_LOGO_IMG);
        }
        if(empty($brand)){
            $brandConfig = (new ConfigDataModel())->getDataConfig(['key'=>'HotBrandsLogo'],['spus']);
            if(!empty($brandConfig)){
                //搜索格式化
                $brand_id = CommonLib::supportArray($brandConfig['spus']);
                $brand = (new BrandModel())->select(['brand_id'=>$brand_id]);
                if(!empty($brand)){
                    $this->redis->set(HOME_BRAND_LOGO_IMG,$brand,CACHE_DAY);
                }
            }
        }
        return $brand;
    }

    /**
     * key获取配置spu,根据spu获取对应Key的产品列表
     * @param $params
     * @return mixed
     */
    public function getProductDataByKey($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $key = $params['key'];
        $products = array();
        if(config('cache_switch_on')){
            $products = $this->redis->get(PRODUCT_CONFIG_DATA_BY_.$key. '_' . $lang);
        }
        if(empty($products)){
            //获取topSeller配置的spu列表
            $spus = (new ConfigDataModel())->getDataConfig(['key'=>$key],['spus']);
            if(isset($spus['spus']) && !empty($spus['spus'])){
                //搜索格式化
                $product_id = CommonLib::supportArray($spus['spus']);
                //查询
                $productArr = (new ProductModel())->configProductLists(['page_size'=>self::RESULT_COUNT,'product_id'=>$product_id]);
                //格式化产品数据
                $products = $this->commonProdcutListData($productArr['data'],$params);
                if(!empty($products)){
                    $this->redis->set(PRODUCT_CONFIG_DATA_BY_.$key. '_' . $lang,$products,CACHE_HOUR);
                }
            }
        }
        return $products;
    }

    /**
     * 首页推荐品牌logo图片
     * @return array|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getPriceList(){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(CONFIG_PRICE_LIST);
        }
        if(empty($result)){
            $result = (new ConfigDataModel())->getMallSetData('PriceConfig');
            if(!empty($result)){
                $this->redis->set(CONFIG_PRICE_LIST,$result,CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 产品详情页，payment
     */
    public function getPayment($params){
        $result = array();

        if(config('cache_switch_on')){
            $result = $this->redis->get(PRODUCT_PAYMENT_CONFIG);
        }
        if(empty($result)){
            $result = (new ConfigDataModel())->getMallSetData('Payment');
            if(!empty($result)){
                $this->redis->set(PRODUCT_PAYMENT_CONFIG,$result,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

}
