<?php
namespace app\mallextend\controller;
use app\common\controller\Base;
use app\mallextend\model\ProductBrandModel;


/**
 * 创建：钟宁
 * 功能：产品品牌
 * 时间：2018-05-28
 */
class ProductBrand extends Base
{
    public function __construct(){
        parent::__construct();
    }

    /**
     * 生成静态头部
     */
    public function getBrandList(){
        try{
            $params = request()->post();
            $result = (new ProductBrandModel())->getBrandList($params);
            return $result;
        }catch (Exception $e){
            return apiReturn(['code'=>100000001, 'msg'=>$e->getMessage()]);
        }
    }

}
