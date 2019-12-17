<?php
namespace app\app\services;

use app\app\model\ConfigDataModel;
use app\common\helpers\CommonLib;
use app\app\model\ProductClassModel;
use app\app\model\ProductExtensionModel;
use app\app\model\ProductModel;
use think\Cache;
use think\Exception;


/**
 * 产品详情数据推荐业务层
 */
class ProductExtensionService extends BaseService
{
    const CACHE_KEY = 'DX_MALL_PRODUCTEXTENSION_';
    const CACHE_TIME = 86400;//一天
    /**
     * 推荐类型
     */
    const RelatedProducts = 1;
    const AlsoViewed = 2;
    const AlsoBought = 3;
    const CartAlsoBought = 4;

    const SPUSCOUNT = 30;
    const AlsoViewedCount = 25;

    /**
     * 产品详情页 --related products位置推荐
     * @param $params
     * @return array
     */
    public function getRelatedProducts($params){
        $currency = !empty($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $country = isset($params['country']) ? $params['country'] : null;
        //推荐表取30个SPU
        //推荐表已有SPU所属三级品类下，同品牌的其他SPU，且价格上下波动范围不超过50%
        //取推荐表已有SPU所属三级品类下，价格上下波动不超过50%的其他SPU
        //取推荐表已有SPU所属三级品类下的其他SPU
        //取推荐表已有SPU所属二级品类下的其他SPU

        //产品搜索，类别，价格，品牌，SR排序

        $products = $spus = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get('APP_'.RELATED_PRODUCT_ . $params['product_id'].'_'.$params['lang'].'_'.$currency.'_'.$country);
        }
        if(empty($products)){
            //推荐表取30个SPU
            $productRecommend = (new ProductExtensionModel())->getRecommendedSpu($params);
            if(!empty($productRecommend)){
                $spus = isset($productRecommend['ProductRecommend'][self::RelatedProducts]['RecommendedSpu'])
                    ? $productRecommend['ProductRecommend'][self::RelatedProducts]['RecommendedSpu'] : [];
            }
            //推荐表已有SPU所属三级品类下，同品牌的其他SPU，且价格上下波动范围不超过50%
            if(count($spus) < self::SPUSCOUNT){
                $product = (new ProductModel())->findProduct($params);
                //类别映射
                $class_info = (new ProductClassModel())->getClassDetail(['id'=>(int)$product['ThirdCategory']]);
                if(isset($class_info['pdc_ids']) && !empty($class_info['pdc_ids'])){
                    array_push($class_info['pdc_ids'],$product['ThirdCategory']);
                    $product['ThirdCategory'] = CommonLib::supportArray($class_info['pdc_ids']);
                }

                $startPrice = $product['LowPrice'] / 2 ;
                $endPrice = $startPrice + $product['LowPrice'];

                $firstRule = (new ProductModel())->selectRecommendProduct([
                    'brandId'=>$product['BrandId'],
                    'thirdCategory'=>$product['ThirdCategory'],
                    'salesRank'=>true,
                    'limit'=> self::SPUSCOUNT,
                    'startPrice'=>$startPrice,
                    'endPrice'=>$endPrice,
                ],$params['product_id']);

                if(count($firstRule) != 0){
                    $firstRuleSpus = CommonLib::getColumn('_id',$firstRule);
                    $spus = array_unique(array_merge($spus,$firstRuleSpus));
                }
            }

            //取推荐表已有SPU所属三级品类下，价格上下波动不超过50%的其他SPU
            if(count($spus) < self::SPUSCOUNT){
                $ruleData = (new ProductModel())->selectRecommendProduct([
                    'thirdCategory'=>$product['ThirdCategory'],
                    'salesRank'=>true,
                    'limit'=> self::SPUSCOUNT,
                    'startPrice'=>$startPrice,
                    'endPrice'=>$endPrice,
                ],$params['product_id']);
                if(count($ruleData) != 0){
                    $ruleSpus = CommonLib::getColumn('_id',$ruleData);
                    $spus = array_unique(array_merge($spus,$ruleSpus));
                }
            }

            //取推荐表已有SPU所属三级品类下的其他SPU
            if(count($spus) < self::SPUSCOUNT){
                $ruleData = (new ProductModel())->selectRecommendProduct([
                    'thirdCategory'=>$product['ThirdCategory'],
                    'salesRank'=>true,
                    'limit'=> self::SPUSCOUNT
                ],$params['product_id']);
                if(count($ruleData) != 0){
                    $ruleSpus = CommonLib::getColumn('_id',$ruleData);
                    $spus = array_unique(array_merge($spus,$ruleSpus));
                }
            }

            //取推荐表已有SPU所属二级品类下的其他SPU
            if(count($spus) < self::SPUSCOUNT){
                //类别映射
                $class_info = (new ProductClassModel())->getClassDetail(['id'=>(int)$product['SecondCategory']]);
                if(isset($class_info['pdc_ids']) && !empty($class_info['pdc_ids'])){
                    array_push($class_info['pdc_ids'],$product['SecondCategory']);
                    $product['SecondCategory'] = CommonLib::supportArray($class_info['pdc_ids']);
                }

                $ruleData = (new ProductModel())->selectRecommendProduct([
                    'secondCategory'=>$product['SecondCategory'],
                    'salesRank'=>true,
                    'limit'=> self::SPUSCOUNT
                ],$params['product_id']);
                if(count($ruleData) != 0){
                    $ruleSpus = CommonLib::getColumn('_id',$ruleData);
                    $spus = array_unique(array_merge($spus,$ruleSpus));
                }
            }

            if(!empty($spus)){
                $search_key = array_search($params['product_id'],$spus);
                if($search_key !== false){
                    unset($spus[$search_key]);
                }
            }
            //超过30个默认排序
            if(count($spus) > self::SPUSCOUNT){
                $spus = array_slice($spus,0,self::SPUSCOUNT);
            }
            $products = (new ProductModel())->selectRecommendProduct([
                'product_id'=>CommonLib::supportArray($spus)
            ]);

            $products = $this->commonProdcutListData($products,$params);
            if(!empty($products)){
                $this->redis->set('APP_'.RELATED_PRODUCT_ . $params['product_id'].'_'.$params['lang'].'_'.$currency.'_'.$country,$products,CACHE_DAY);
            }
        }
        return $products;
    }


    /**
     * 产品详情页 --Customers Who Viewed This Item Also Viewed位置推荐
     * @param $params
     * @return array
     */
    public function getAlsoViewed($params){
        //推荐表取25个SPU
        //销量降序排列取该SPU所属三级类别下的SPU
        $products = $spus = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(VIEW_ALSO_VIEW_ . $params['product_id'].'_'.$params['lang']);
        }
        if(empty($products)){
            //推荐表取25个SPU
            $productRecommend = (new ProductExtensionModel())->getRecommendedSpu($params);
            if(!empty($productRecommend)){
                $spus = isset($productRecommend['ProductRecommend'][self::AlsoViewed]['RecommendedSpu'])
                    ? $productRecommend['ProductRecommend'][self::AlsoViewed]['RecommendedSpu'] : [];
            }
            //销量降序排列取该SPU所属三级类别下的SPU
            if(count($spus) < self::AlsoViewedCount){
                //获取当前详情页产品ID信息
                $product = (new ProductModel())->findProduct($params);
                //类别映射
                $class_info = (new ProductClassModel())->getClassDetail(['id'=>(int)$product['ThirdCategory']]);
                if(isset($class_info['pdc_ids']) && !empty($class_info['pdc_ids'])){
                    array_push($class_info['pdc_ids'],$product['ThirdCategory']);
                    $product['ThirdCategory'] = CommonLib::supportArray($class_info['pdc_ids']);
                }
                //补全推荐数据
                $firstRule = (new ProductModel())->selectRecommendProduct([
                    'thirdCategory'=>$product['ThirdCategory'],
                    'salesCounts'=>true,
                    'limit'=> self::AlsoViewedCount
                ],$params['product_id']);
                if(count($firstRule) != 0){
                    $firstRuleSpus = CommonLib::getColumn('_id',$firstRule);
                    $spus = array_unique(array_merge($spus,$firstRuleSpus));
                }
            }

            if(!empty($spus)){
                $search_key = array_search($params['product_id'],$spus);
                if($search_key !== false){
                    unset($spus[$search_key]);
                }
            }

            //超过25个默认排序
            if(count($spus) > self::AlsoViewedCount){
                $spus = array_slice($spus,0,self::AlsoViewedCount);
            }
            $products = (new ProductModel())->selectRecommendProduct([
                'product_id'=>CommonLib::supportArray($spus)
            ],$params['product_id']);
            $products = $this->commonProdcutListData($products,$params);
            if(!empty($products)){
                $this->redis->set(VIEW_ALSO_VIEW_ . $params['product_id'].'_'.$params['lang'],$products,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$products]);
    }

    /**
     * 产品详情页 --Customers Who Bought This Item Also Bought位置推荐
     * 推荐表取25个SPU,补数规则：Staffpick中按照Popularity顺序取数不足
     * @param $params
     * @return array
     */
    public function getAlsoBought($params){
        try{
            $spus = array();
            $products = array();
            $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
            //判断是否有缓存+语种
            if(config('cache_switch_on')) {
                $products = $this->redis->get(BOUGHT_ALSO_BOUGHT_ . $params['product_id'].'_' . $lang);
            }
            if(empty($products)){
                //推荐表取25个SPU
                $productRecommend = (new ProductExtensionModel())->getRecommendedSpu($params);
                if(!empty($productRecommend)){
                    $spus = isset($productRecommend['ProductRecommend'][self::AlsoBought]['RecommendedSpu'])
                        ? $productRecommend['ProductRecommend'][self::AlsoBought]['RecommendedSpu'] : [];
                }

                //推荐表数据不全
                if(count($spus) < self::AlsoViewedCount){
                    //从StaffPick中随机抽取25个SPU
                    $firstRule = (new ProductModel())->selectRecommendProduct([
                        'isStaffPick' => true,
                        'limit' => self::AlsoViewedCount
                    ],$params['product_id']);
                    if(count($firstRule) != 0){
                        $firstRuleSpus = CommonLib::getColumn('_id',$firstRule);
                        $spus = array_unique(array_merge($spus,$firstRuleSpus));
                    }
                }

                if(!empty($spus)){
                    $search_key = array_search($params['product_id'],$spus);
                    if($search_key !== false){
                        unset($spus[$search_key]);
                    }
                }
                //超过25个默认排序
                if(count($spus) > self::AlsoViewedCount){
                    $spus = array_slice($spus,0,self::AlsoViewedCount);
                }
                if(empty($spus)){
                    return apiReturn(['code'=>200, 'data'=>array()]);
                }
                $products = (new ProductModel())->selectRecommendProduct([
                    'product_id'=>CommonLib::supportArray($spus)
                ]);
                if(!empty($products)){
                    //格式化
                    $products = $this->commonProdcutListData($products,$params);
                    //缓存
                    $this->redis->set(BOUGHT_ALSO_BOUGHT_ . $params['product_id'].'_'.$lang, $products, CACHE_DAY);
                }
                return apiReturn(['code'=>200, 'data'=>is_array($products) ? $products : array()]);
            }
            return apiReturn(['code'=>200, 'data'=>is_array($products) ? $products : array()]);
        }catch (Exception $e){
            return apiReturn(['code'=>100000011, 'msg'=>$e->getMessage()]);
        }

    }


    /**
     * 产品详情页 --Recommendations Based On Your Recent History位置推荐
     * @param $params
     * @return array
     */
    public function getRecentHistory($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $count = isset($params['count']) ? $params['count'] : 6;

        $products = (new ProductModel())->selectRecommendProduct([
            'isStaffPick' => 1,
            'salesCounts' => true,
            'limit' => self::AlsoViewedCount
        ]);

        //超过6个默认排序
        if(count($products) > $count){
            $products = array_slice($products,0,$count);
        }
        if(!empty($products)){
            $products = $this->commonProdcutListData($products,$params);
        }
        return $products;
    }

    /**
     * 浏览历史数据
     * @param $params
     * @return array|bool
     */
    public function getProductViewHistory($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $products = $data = array();
        //获取前25个浏览历史
        $spus = array_slice(explode(',',$params['spu']),0,25);
        if(!empty($spus)){
            //格式化
            $params['product_id'] = CommonLib::supportArray($spus);
            $products = (new ProductModel())->selectProduct($params);
            if(!empty($products)){
                $products = $this->commonProdcutListData($products,$params);
            }
            //按浏览历史排序
            foreach($spus as $key => $spu){
                $searchData = CommonLib::filterArrayByKey($products,'id',$spu);
                if(!empty($searchData)){
                    $data[$key] = $searchData;
                }
            }
            if(!empty($data)){
                $data = array_values($data);
            }
        }
        return $data;
    }

    /**
     * 搜索页面推荐数据 --取staffPick数据随机展示
     * @param $params
     * @return array|bool
     */
    public function getRecommendations($params){
        try{
            //搜索条件是staffpick
            $products = (new ProductModel())->newArrivalsProductLists($params);
            if(!empty($products['data'])){
                $products['data'] = $this->commonProdcutListData($products['data'],$params);
            }
            return $products;
        }catch (Exception $e){
            return $e->getMessage();
        }
    }


    /**
     * 搜索页面推荐数据 --取staffPick数据随机展示
     * @param $params
     * @return array|bool
     */
    public function getRecommend($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $cartSpu = array();
        //获取购物车信息
        $Uid = $params['CustomerId'];
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        //获取SKUID
        if(isset($CartInfo[$Uid]['StoreData']) && is_array($CartInfo[$Uid]['StoreData'])) {
            foreach ($CartInfo[$Uid]['StoreData'] as $k => $v) {
                if(isset($v['ProductInfo']) && is_array($v['ProductInfo'])) {
                    foreach ($v['ProductInfo'] as $k1 => $v1) {
                        //获取购物车SPU
                        $cartSpu[$k1] = $k1;
                    }
                }
            }
        }

        //购物车没有数据
        if(empty($cartSpu)){
            $spus = $product_ids = array();
            //获取Staffpick配置
            if(config('cache_switch_on')) {
                $spus = $this->redis->get(STAFFPICKS_CONFIG);
            }
            if(empty($spus)){
                $spus = (new ConfigDataModel())->getDataConfig(['key'=>'StaffPicks']);
                if(isset($spus['spus']) && !empty($spus['spus'])){
                    $this->redis->set(STAFFPICKS_CONFIG,$spus,CACHE_DAY);
                }else{
                    return array();
                }
            }
            //格式化产品id
            $params['product_id'] = CommonLib::supportArray($spus,'spus');
        }else{
            $data = array();
            //推荐表取25个SPU
            $productRecommend = (new ProductExtensionModel())->selectRecommendedSpu($cartSpu,self::CartAlsoBought);
            if(!empty($productRecommend)){
                foreach($productRecommend as $bouht){
                    $spus = isset($bouht['ProductRecommend'][self::CartAlsoBought]['RecommendedSpu'])
                        ? $bouht['ProductRecommend'][self::CartAlsoBought]['RecommendedSpu'] : [];
                    $data = array_merge($data,$spus);
                }
                $data = array_unique($data);
            }
            //推荐表数据不全
            if(count($data) < 20){
                //从StaffPick中随机抽取25个SPU
                $firstRule = (new ProductModel())->newArrivalsProductLists([
                    'isStaffPick' => 1,
                    'page_size' => 100
                ]);
                if(count($firstRule['data']) != 0){
                    $firstRuleSpus = CommonLib::getColumn('_id',$firstRule['data']);
                    $data = array_unique(array_merge($data,$firstRuleSpus));
                }
            }
            //超过25个默认排序
            /*
            if(count($data) > 20){
                $data = array_slice($data,0,20);
            }*/
            $params['product_id'] = CommonLib::supportArray($data);
        }
        //查询产品
        $products = (new ProductModel())->topSellerProductLists($params);
        if(!empty($products)&&!empty($products['data'])){
            //格式化产品数据
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

}
