<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\ConfigDataModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductExtensionModel;
use app\mall\model\ProductModel;
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

    const SPUSCOUNT = 30;
    const AlsoViewedCount = 25;

    /**
     * 产品详情页 --related products位置推荐
     * @param $params
     * @return array
     */
    public function getRelatedProducts($params){
        //推荐表取30个SPU
        //推荐表已有SPU所属三级品类下，同品牌的其他SPU，且价格上下波动范围不超过50%
        //取推荐表已有SPU所属三级品类下，价格上下波动不超过50%的其他SPU
        //取推荐表已有SPU所属三级品类下的其他SPU
        //取推荐表已有SPU所属二级品类下的其他SPU

        //产品搜索，类别，价格，品牌，SR排序

        $products = $spus = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(RELATED_PRODUCT_ . $params['product_id'].'_'.$params['lang'].'_'.$params['country']);
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
            if(!empty($spus)){
                $products = (new ProductModel())->selectRecommendProduct([
                    'product_id'=>CommonLib::supportArray($spus)
                ]);

                $products = $this->commonProdcutListData($products,$params);
                if(!empty($products)){
                    $this->redis->set(RELATED_PRODUCT_ . $params['product_id'].'_'.$params['lang'].'_'.$params['country'],$products,CACHE_HOUR);
                }
            }else{
                return array();
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
            $products = $this->redis->get(VIEW_ALSO_VIEW_ . $params['product_id'].'_'.$params['lang'].'_'.$params['country']);
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
            if(!empty($spus)){
                $products = (new ProductModel())->selectRecommendProduct([
                    'product_id'=>CommonLib::supportArray($spus)
                ],$params['product_id']);
                $products = $this->commonProdcutListData($products,$params);
                if(!empty($products)){
                    $this->redis->set(VIEW_ALSO_VIEW_ . $params['product_id'].'_'.$params['lang'].'_'.$params['country'],$products,CACHE_HOUR);
                }
            }else{
                return array();
            }

        }
        return $products;
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
            $country = isset($params['country']) ? $params['country'] : '';
            //判断是否有缓存+语种
            if(config('cache_switch_on')) {
                $products = $this->redis->get(BOUGHT_ALSO_BOUGHT_ . $params['product_id'].'_' . $lang.'_'.$country);
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
                    $this->redis->set(BOUGHT_ALSO_BOUGHT_ . $params['product_id'].'_'.$lang.'_'.$country, $products, CACHE_HOUR);
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
        $products = array();
        //从浏览历史数据里随机选择一个SPU，根据该SPU所属三级品类的销量降序取出前25个SPU
        $product = (new ProductModel())->findProduct($params);
        if(!empty($product)){
            //类别映射
            $class_info = (new ProductClassModel())->getClassDetail(['id'=>(int)$product['ThirdCategory']]);
            if(isset($class_info['pdc_ids']) && !empty($class_info['pdc_ids'])){
                array_push($class_info['pdc_ids'],$product['ThirdCategory']);
                $product['ThirdCategory'] = CommonLib::supportArray($class_info['pdc_ids']);
            }

            $products = (new ProductModel())->selectRecommendProduct([
                'thirdCategory' => $product['ThirdCategory'],
                'salesCounts' => true,
                'limit' => self::AlsoViewedCount
            ],$params['product_id']);
            if(!empty($products)){
                $products = $this->commonProdcutListData($products,$params);
            }
        }
        return $products;
    }

    /**
     * 浏览历史数据
     * @param $params
     * @return array|bool
     */
    public function getProductViewHistory($params){
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
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 搜索页面推荐数据 --取staffPick数据随机展示
     * @param $params
     * @return array|bool
     */
    public function getRecommendations($params){

        $spus = $product_ids = array();
        //获取配置的spu列表
        if(config('cache_switch_on')) {
            $spus = $this->redis->get(STAFFPICKS_CONFIG);
        }
        if(empty($spus)){
            $spus = (new ConfigDataModel())->getDataConfig(['key'=>'StaffPicks']);
            if(isset($spus['spus']) && !empty($spus['spus'])){
                $this->redis->set(STAFFPICKS_CONFIG,$spus,CACHE_HOUR);
            }else{
                return array();
            }
        }
        //格式化产品id
        $params['product_id'] = CommonLib::supportArray($spus,'spus');

        //查询产品
        $products = (new ProductModel())->topSellerProductLists($params);
        if(!empty($products)){
            //格式化产品数据
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

}
