<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/11/6
 * Time: 11:24
 */
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\dxcommon\BaseApi;
use think\Log;

class MyCoupon extends AppBase
{
    public $baseApi;
    public function __construct()
    {
        parent::__construct();
        $this->baseApi = new BaseApi();
    }

    public function getCouponList()
    {
        $data = input();
        $res = $this->baseApi->getCouponList($data);
        return $res;
    }

    public function usedCouponByCode()
    {
        $data = input();
        $res = $this->baseApi->usedCouponByCode($data);
        return $res;
    }

    public function getCouponByCouponId()
    {
        $data = input();
        $res = $this->baseApi->getCouponByCouponId($data);
        return $res;
    }

    public function addCoupon()
    {
        $data = input();
        $res = $this->baseApi->addCoupon($data);
        return $res;
    }

    public function usedCoupon()
    {
        $data = input();
        $res = $this->baseApi->usedCoupon($data);
        if(empty($res) ||(!empty($res)&&$res!=200)){
            Log::record('usedCoupon'.json_encode($res));
        }
        return $res;
    }

    public function getCouponCount()
    {
        $data = input();
        $res = $this->baseApi->getCouponCount($data);
        return $res;
    }
}