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
 * 优惠额度异常订单
 * @author kevin   2019-06-19
 */
class OrderDiscountException extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
    }

    /**
     * 优惠额度异常订单
     * @author kevin   2019-04-16
     */
    public function index()
    {
        $baseApi = new BaseApi();
        $data = input();
        $param_data['path'] = url("OrderDiscountException/index");
        $param_data['page_size']= input("page_size",20);
        $param_data['page'] = input("page",1);
        $param_data['page_query'] = $data;
        if(isset($data['page_size'])){
            unset($data['page_size']);
        }
        if(isset($data['page'])){
            unset($data['page']);
        }
        if(isset($data['order_number']) && !empty($data['order_number'])){
            $param_data['order_number'] = $data['order_number'];
        }

        if(isset($data['startCreateOn']) && !empty($data['startCreateOn'])){
            $param_data['startCreateOn'] = strtotime($data['startCreateOn']);
        }
        if(isset($data['endCreateOn']) && !empty($data['endCreateOn'])){
            $param_data['endCreateOn'] = strtotime($data['endCreateOn']);
        }
        $data = $baseApi::getOrderDiscountExceptionList($param_data);
        $this->assign("list",!empty($data['data'])?$data['data']:'');
        return $this->fetch('');
    }
}