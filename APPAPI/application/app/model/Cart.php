<?php
/**
 * Created by PhpStorm.
 * User: yxh
 * Date: 2019/9/11
 * Time: 18:40
 */
namespace app\app\model;

use app\app\model\BaseModel;
use think\exception\HttpException;
use think\Log;

class Cart extends BaseModel
{
    protected $connection='db_order';
    protected $table = 'dx_cart';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'add_time';
    protected $updateTime = false;

    public function getCarList($where = [])
    {
        $list = $this->all($where);
        $product_sum = 0;
        if ($list) {
            foreach ($list as $key => &$value) {
                $spuId = $value['product_id'];
                $skuId = $value['sku_id'];
                $product = $this->getProduct($spuId, $skuId);
                if ($product) {
                    $value['product_name'] = $product['product_name'];
                    $value['product_img'] = $product['product_img'];
                    $value['product_price'] = $product['product_price'];;
                    $value['product_attributes'] = $product['product_attributes'];
                    $value['product_total'] = $value['product_price'] * $value['qty'];
                } else {
                    unset($list[$key]);
                }
            }
        }

        return $list;
    }

    public function getCarCount($where = [])
    {
        $count = $this->where($where)->count();
        return $count;
    }

    /*
     * 获取产品信息 线上
     */
    public function getProduct($spuId, $skuId)
    {
        $SkuSpu = new SkuSpu();
        $data = $SkuSpu->getSpuInfo($spuId, $skuId);
        $product = [];
        if (!empty($data)) {
            if (!empty($data['product_img'])) {
                if (strpos($data['product_img'], 'http') === false) {
                    $product['product_img'] = IMG_SHOP . $data['product_img'];
                } else {
                    $product['product_img'] = $data['product_img'];
                }
            } else {
                $product['product_img'] = '';
            }

            $product['product_name'] = !empty($product['product_name'])?$product['product_name']:'';
            $product['product_img'] = !empty($product['product_img'])?$product['product_img']:'';
            $product['product_attributes'] = !empty($product['product_attributes'])?$product['product_attributes']:'';

            if (!empty($data['profit']) && !empty($data['product_price']) && isset($data['freight'])) {
                $product['product_price'] = $data['product_price'];
                $product['profit'] = $data['profit'];//利润
                $product['freight'] = $data['freight'];//运费
            } else {
                Log::record($spuId . '产品价格异常' . $skuId . 'data' . json_encode($data), 'error');
            }
        } else {
            Log::record($spuId . '产品不存在' . $skuId, 'error');
        }

        return $product;
    }


}