<?php
namespace app\lis\controller;
use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\common\controller\Base;
use app\lms\model\DxRegion;
use app\lms\dxcommon\BaseApi;
use think\Db;
use app\common\helpers\RedisClusterBase;

/**
 * LIS系统 接口
 * author: Wang
 * AddTime:2018-12-08
 */
class logisticsDetail extends Base
{

  // public function __construct(){
  //       define('SQL_ORSER_PACKAGE', 'order_package');
  //       define('SQL_ORSER_PACKAGE_TRACK', 'order_package_track');
  //       $this->db = Db::connect('db_order');
  // }

  /**
   * 卖家和买家获取物流时时状况
   * [LisLogisticsDetail description]
   */
  public function LisLogisticsDetail(){
      $data = input();
      if(empty($data['status'])){
           $data['status'] = 0;//默认为0，指卖家
      }
      $result = model("logisticsDetail")->LisLogisticsDetail($data);
      return $result;
  }
  /**
   * 后台Admin管理员查看物流节点
   * [AdminLisLogisticsDetail description]
   */
  public function AdminLisLogisticsDetail(){
      if($data = request()->post()){
           $result = model("logisticsDetail")->AdminLisLogisticsDetail($data);
           return $result;
      }
  }




}