<?php
namespace app\mallextend\services;

use app\common\helpers\CommonLib;
use app\mall\model\ProductClassModel;
use app\mallextend\model\ProductExtensionModel;
use app\mallextend\model\ProductModel;
use think\Cache;
use think\Exception;


/**
 * 产品详情数据推荐业务层
 */
class ProductExtensionService extends BaseService
{
    /**
     * 推荐类型
     */
    const AlsoBought = 4;

    const SPUSCOUNT = 30;
    const AlsoViewedCount = 25;


    /**
     * 推荐数据
     * @param $params
     * @return array|bool
     */
    public function getRecommendations($params){
        if(!is_array($params['product_id'])){
            $product_id[] = $params['product_id'];
        }else{
            $product_id = $params['product_id'];
        }
        $data = array();
        if(!empty($product_id)){
            //推荐表取25个SPU
            $productRecommend = (new ProductExtensionModel())->selectRecommendedSpu($product_id,self::AlsoBought);
            if(!empty($productRecommend)){
                foreach($productRecommend as $bouht){
                    $spus = isset($bouht['ProductRecommend'][self::AlsoBought]['RecommendedSpu'])
                        ? $bouht['ProductRecommend'][self::AlsoBought]['RecommendedSpu'] : [];
                    $data = array_merge($data,$spus);
                }
                $data = array_unique($data);
            }
        }
        //推荐表数据不全
        if(count($data) < self::AlsoViewedCount){
            //从StaffPick中随机抽取25个SPU
            $firstRule = (new ProductModel())->getProductLists([
                'isStaffPick' => 1,
                'page_size' => 50
            ]);
            if(count($firstRule['data']) != 0){
                $firstRuleSpus = CommonLib::getColumn('_id',$firstRule['data']);
                $data = array_unique(array_merge($data,$firstRuleSpus));
            }
        }

        //超过25个默认排序
        if(count($data) > self::AlsoViewedCount){
            $data = array_slice($data,0,self::AlsoViewedCount);
        }
        if(!empty($data)){
            $productsData = array();
            $products = (new ProductModel())->getProductLists(['product_id'=>CommonLib::supportArray($data)]);
            if(!empty($products['data'])){
                //格式化
                $productsData = $this->commonProdcutListData($products['data'],$params);
            }
            return $productsData;
        }
        return $data;
    }

    /**
     * 新品推荐
     * @return array|bool
     */
    public function getNewProductData($params){
        $data = array();
        //查询产品
        $products = (new ProductModel())->newArrivalsProductLists($params);
        if(!empty($products)){
            $data = $this->commonProdcutListData($products['data'],$params);
        }
        //超过25个默认排序
        if(count($data) > 9){
            $data = array_slice($products['data'],0,9);
        }
        return $data;
    }

}
