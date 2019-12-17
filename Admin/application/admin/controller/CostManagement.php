<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use think\Paginator;
use app\admin\dxcommon\BaseApi;
/*
 * 商城促销
 * AddTime:2018-05-13
 * author: Wang
 *
 */
class CostManagement extends Action
{
  	public function __construct(){
         Action::__construct();
         define('ACTIVITY', 'activity');//mysql数据表
    }
    public function costList(){
         return View('costList');
    }


}