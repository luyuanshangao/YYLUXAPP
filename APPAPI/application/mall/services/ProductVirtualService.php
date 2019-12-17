<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\ProductActivityModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductModel;
use app\mall\model\ProductVirtualModel;
use think\Cache;
use think\Exception;
use think\Request;


/**
 * 虚拟产品服务层
 * 开发：zhongning 20191018
 */
class ProductVirtualService extends BaseService
{

    /**
     * 获取虚拟产品列表
     *
     * @param $params
     * @return array
     */
    public function selectProductVirtual($params){
        return (new ProductVirtualModel())->selectProductVirtual($params);
    }

    /**
     * 获取单个产品
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getProductVirtual($params){
        return (new ProductVirtualModel())->findProductVirtual($params);
    }

    /**
     * 处理库存和销量处理
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function synInventoryAndSalesCount($params){
        return (new ProductVirtualModel())->synInventoryAndSalesCount($params);
    }

}
