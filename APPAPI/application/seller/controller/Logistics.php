<?php
namespace app\seller\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\params\seller\seller\ResetPasswordParams;
use app\common\params\seller\seller\UpdateSellerExtensionParams;
use app\common\params\seller\seller\UpdateSellerParams;
use app\seller\model\Logistics AS lms_logistics;
use think\Db;


/**
 * 物流更新  来自  定时系统的触发
 */
class Logistics extends Base
{
    public $userInfoModel;
    public function __construct()
    {
        parent::__construct();
        $this->userInfoModel = new \app\seller\model\UserInfo();
    }
   /**更新商城shipping_cost 数据表
   * [shipping_cost description]
   * @return [type] [description]
   */
  public function shipping_cost(){

    $result = lms_logistics::shipping_cost();//pr(json_decode($result,true));
// dump(json_decode($result,true));
    return $result;
  }
  //入队
  // public function redis_enqueue(){
  //   $result = lms_logistics::redis_enqueue();return $result;
  // }
  // //出队
  // public function redis_dequeue(){
  //   $result = lms_logistics::redis_dequeue();return $result;
  // }




}
