<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\ProductModel;
use app\mall\model\ProductPointsModel;


/**
 * 开发：钟宁
 * 功能：积分产品列表缓存
 * 时间：2018-06-05
 */
class ProductPointService extends BaseService
{

    /**
     * 活动进行中的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getPointsLists($params){
        $result = array();
        $image= '';
        $productModel = new ProductModel();
        $lang = isset($params['lang']) ? $params['lang'] : self::DEFAULT_LANG;
        try {
            if (empty($result)) {
                //获取活动产品ID
                $pointsData = (new ProductPointsModel())->getPointsLists($params);
                if(!empty($pointsData)){
                    //格式化产品ID 产品标题，产品价格
                    foreach($pointsData['data'] as $key => $points){
                        $productDetail = $productModel->getBaseSpuDetail($points['SPU']);
                        $pointsData['data'][$key]['Title'] = $productDetail['Title'];
                        if(self::DEFAULT_LANG != $lang){

                            $productMultiLang = $this->getProductMultiLang($points['SPU'],$lang);
                            $pointsData['data'][$key]['Title'] = isset($productMultiLang['Title'][$params['lang']]) ?
                                $productMultiLang['Title'][$params['lang']] : $productDetail['Title'];//默认英语
                        }
                        $url = isset($productDetail['RewrittenUrl']) ? $productDetail['RewrittenUrl'] : '';
                        $pointsData['data'][$key]['LinkUrl'] ='/p/'.$url.$points['SPU'];
                        //查询SKU信息
                        $skuArray = CommonLib::filterArrayByKey($productDetail['Skus'],'_id',$points['SKU']);
                        foreach($skuArray['SalesAttrs'] as $salesAttrs){
                            if(isset($salesAttrs['Image'])){
                                $image = $salesAttrs['Image'];
                            }
                        }
                        $pointsData['data'][$key]['FirstProductImage'] = empty($image) ? $productDetail['FirstProductImage'] : $image;
                        $pointsData['data'][$key]['SalesPrice'] = isset($skuArray['SalesPrice']) ? sprintf('%01.2f',$skuArray['SalesPrice']) : '0.00';//最低价格
                    }
                    return $pointsData;
                }
            }
            return $result;
        }catch (Exception $e){
            return $e->getMessage();
        }
    }

}
