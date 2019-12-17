<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\ConfigDataModel;
use app\mall\model\CouponModel;
use app\mall\model\ElectronicCoilModel;
use app\mall\model\ProductModel;
use think\Cache;
use think\Exception;


/**
 * Coupon接口
 */
class ElectronicCoilService extends BaseService
{
    /**
     * 获取电子券
     */
    public function getCoil($params){
        $result = (new ElectronicCoilModel())->getCoil($params);
        return $result;
    }

    /**
     * 获取电子券
     * @param $params
     * @return array
     */
    public function bindCoil($params){
        $result = (new ElectronicCoilModel())->bindCoil($params);
        return $result;
    }

}
