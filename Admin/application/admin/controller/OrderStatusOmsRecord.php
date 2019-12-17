<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cache;
use think\Log;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\ExcelTool;
// use app\admin\model\Interface;

/**
 * OMS推送订单状态记录管理
 * @author kevin   2019-05-25
 */
class OrderStatusOmsRecord extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
    }

    /**
     * OMS推送订单状态
     * @author kevin   2019-04-16
     */
    public function index()
    {
        $baseApi = new BaseApi();
        $data = input();
        $param_data['path'] = url("OrderStatusOmsRecord/index");
        $param_data['page_size']= input("page_size",20);
        $param_data['page'] = input("page",1);
        if(!empty($data['order_number'])){
            $param_data['order_number'] =$data['order_number'];
        }
        if(isset($data['page_size'])){
            unset($data['page_size']);
        }
        if(isset($data['page'])){
            unset($data['page']);
        }
        $param_data['page_query'] = $data;
        //获取后台配置的订单状态
        $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
        $order_status_data = array();
        if(!empty($orderStautsDict)){
            foreach ($orderStautsDict as $key=>$value){
                if(!empty($value[0]) && !empty($value)){
                    $order_status_data[$value[0]] = $value[1];
                }
            }
        }
        $data =$baseApi::getOrderStatusOmsRecord($param_data);
        $this->assign("order_status_data",$order_status_data);
        $this->assign("list",$data['data']);
        return $this->fetch('');
    }
}