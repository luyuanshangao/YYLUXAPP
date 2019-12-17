<?php
namespace app\mall\services;

use app\mall\model\ProductModel;
use app\mall\model\WishModel;
use think\Cache;


/**
 * 收藏接口
 */
class WishService
{

    /**
     * 添加收藏
     * @param $params
     * @return bool
     */
    public function create($params){
        $productModel = new ProductModel();
        $time = time();
        if(!empty($params)){
            foreach($params as $wish){
                $product = $productModel->findProduct(['product_id' => $wish['product_id']]);
                if(empty($product)){
                    return false;
                }
                $wishData['UserId'] = (int)$wish['user_id'];
                $wishData['ProductId'] = (int)$wish['product_id'];
                $wishData['SkuId'] = (int)$wish['sku_id'];
                $wishData['ProductName'] = $product['Title'];
                $wishData['ProductImg'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
                $wishData['ProductPrice'] = isset($product['ProductPrice']) ? $product['ProductPrice'] : 0;
                $wishData['Lang'] = $wish['lang'];
                $wishData['addTime'] = $time;
                $ret = (new WishModel())->add($wishData);
                if(!$ret){
                    return false;
                }
            }
            return true;
        }
        return false;

    }

}
