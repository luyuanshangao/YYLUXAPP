<?php
namespace app\admin\controller;

use app\admin\dxcommon\ExcelTool;
use app\admin\model\BlockChainOrderModel;
use app\admin\model\BlockChainWithdrawModel;
use app\admin\model\LogisticsLog;
use app\admin\model\OrderMessageTemplateModel;
use app\admin\model\OrderModel;
use app\admin\dxcommon\Token;
use app\common\helpers\CommonLib;
use think\Exception;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cache;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\Base;
use think\Log;

/**
 * 商城管理--订单管理--区块链订单管理
 * Add by:zhongning 20191022
 */
class BlockChainOrder extends Action
{
    public function __construct(){
        Action::__construct();
    }

    /**
     * 订单列表
     */
    public function index()
    {
        $params = input();
        $model = new BlockChainOrderModel();
        //列表信息
        $where = [];
        if (!empty($params['order_number'])){
            $where['order_number'] = $params['order_number'];
        }
        if (!empty($params['customer_id'])){
            $where['customer_id'] = $params['customer_id'];
        }
        if (!empty($params['order_status'])){
            $where['order_status'] = $params['order_status'];
        }
        if (!empty($params['startTime']) && !empty($params['endTime'])){
            $where['create_on'] = array('between', strtotime($params["startTime"]) . ',' . strtotime($params["endTime"]));
        }
        $page_size = config('paginate.list_rows');
        $orderList = $model->getBlockChainOrderPaginate($where,$page_size);
        //获取后台配置的订单状态
        $this->assign('orderStautsDict', $model::$orderStatus);
        $this->assign('orderList', $orderList);
        $this->assign('params', $params);
        return View();
    }

    /**
     * 提现审核
     */
    public function reviewWithdraw(){
        $params = input();
        $query_param = array();
        //列表信息
        $where = [];
        if (!empty($params['order_number'])){
            $query_param['order_number'] = $where['withdraw_number'] = $params['order_number'];
        }
        if (!empty($params['user_id'])){
            $query_param['user_id'] = $where['customer_id'] = $params['user_id'];
        }
        if (!empty($params['withdraw_status'])){
            $query_param['withdraw_status'] = $where['status'] = $params['withdraw_status'];
        }
        if (!empty($params['startTime']) && !empty($params['endTime'])){
            $query_param['startTime'] = $params['startTime'];
            $query_param['endTime'] = $params['endTime'];
            $where['add_time'] = array('between', strtotime($params["startTime"]) . ',' . strtotime($params["endTime"]));
        }
        $withdrawList = (new BlockChainWithdrawModel())->getWithdrawPaginate($where,config('paginate.list_rows'),$query_param);
        $this->assign('withdrawList', $withdrawList);
        $this->assign('params', $params);
        return View();
    }

    /**
     * 财务审核
     */
    public function reviewFinancial()
    {
        $params = input();
        $query_param = array();
        //列表信息
        $where = [];
        if (!empty($params['order_number'])){
            $query_param['order_number'] = $where['withdraw_number'] = $params['order_number'];
        }
        if (!empty($params['user_id'])){
            $query_param['user_id'] = $where['customer_id'] = $params['user_id'];
        }
        if (!empty($params['withdraw_status'])){
            $query_param['withdraw_status'] = $where['status'] = $params['withdraw_status'];
        }else{
            $where['status'] = ['in',[2,3,4]];
        }
        if (!empty($params['startTime']) && !empty($params['endTime'])){
            $query_param['startTime'] = $params['startTime'];
            $query_param['endTime'] = $params['endTime'];
            $where['add_time'] = array('between', strtotime($params["startTime"]) . ',' . strtotime($params["endTime"]));
        }
        $withdrawList = (new BlockChainWithdrawModel())->getWithdrawPaginate($where,config('paginate.list_rows'),$query_param);
        $this->assign('withdrawList', $withdrawList);
        $this->assign('params', $params);
        return View();
    }

    /**
     * 虚拟币提现审核操作
     */
    public function changeStatus(){
        $withdrawModel = new BlockChainWithdrawModel();
        $params = input();
        if(empty($params['id']) || empty($params['status'])){
            return ['result' => '参数有误','code' => 100];
        }
        //查询ID是否存在
        $find = $withdrawModel->findWithdraw(['id' => $params['id']]);
        if(!empty($find)){
            $update['remarks'] = !empty($params['remark']) ? $params['remark'] : '';
            $update['status'] = !empty($params['status']) ? $params['status'] : 1;
            $update['update_time'] = time();
            $update['review_time'] = time();
            $update['operator'] = Session::get('username');
            //驳回操作，增加用户收益
            if($update['status'] == 3){
//                pr(['customer_id' => $find['customer_id'],'transaction_id' => $find['block_chain_transaction_id'],'operator' => 2,'amount' => $find['withdraw_total_virtual_currency']]);
//                pr(CIC_API.'cic/blockChainTransaction/operatorTransaction');
                $apiRes = accessTokenToCurl(config('cic_api_url').'cic/blockChainTransaction/operatorTransaction',null,
                    ['customer_id' => $find['customer_id'],'transaction_id' => $find['block_chain_transaction_id'],'operator' => 2,'amount' => $find['withdraw_total_virtual_currency']],true);
//                $apiRes = json_decode($apiRes,true);
//                pr($apiRes);die;
                if(empty($apiRes['code']) || $apiRes['code'] != 200){
                    return ['result' => '驳回失败','code' => 100];
                }
            }
            $withdrawModel->updateWithdraw(['id' => $params['id']],$update);
            return ['result' => '操作成功','code' => 200];
        }else{
            return ['result' => '数据不存在','code' => 100];
        }

    }

    /**
     *  日下单T数表
     */
    public function exportDaysOrderCountTHS(){
        $data_array = array();
        $tool = new ExcelTool();
        $orderList = (new BlockChainOrderModel())->getBlockChainOrderList(['order_status' => 200]);
        if(!empty($orderList)){
            foreach($orderList as $list){
                if(empty($list['add_time'])){
                    continue;
                }
                $date = date('Ymd',strtotime($list['add_time']));
                if(empty($data_array[$date])){
                    $data_array[$date]['date'] = date('Y-m-d',strtotime($list['add_time']));
                    $data_array[$date]['product_name'] = $list['product_name'];
                    $data_array[$date]['nums'] = $list['goods_count'];
                    $data_array[$date]['total_amount'] = $list['grand_total'];
                }else{
                    $data_array[$date]['nums'] = $data_array[$date]['nums'] + $list['goods_count'];
                    $data_array[$date]['total_amount'] = $data_array[$date]['total_amount'] + $list['grand_total'];
                }
            }

            $header_data =[
                'date'=>'日期',
                'product_name'=>'云算力商品',
                'nums'=>'购买T数',
                'total_amount'=>'收入金额'
            ];
            $tool ->export('日下单T数表',$header_data,$data_array,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

    /**
     *  提现审核成功数据导出
     */
    public function reviewWithdrawExport(){
        $params = input();

        $where = [];
        if (!empty($params['order_number'])){
            $where['withdraw_number'] = $params['order_number'];
        }
        if (!empty($params['user_id'])){
            $where['customer_id'] = $params['user_id'];
        }
        if (!empty($params['withdraw_status'])){
            $where['status'] = $params['withdraw_status'];
        }else{
            $where['status'] = ['in',[2,4]];
        }
        if (!empty($params['startTime']) && !empty($params['endTime'])){
            $query_param['startTime'] = $params['startTime'];
            $query_param['endTime'] = $params['endTime'];
            $where['add_time'] = array('between', strtotime($params["startTime"]) . ',' . strtotime($params["endTime"]));
        }

        $data_array = array();
        $tool = new ExcelTool();
        $withdrawList = (new BlockChainWithdrawModel())->selectWithdraw($where);
        if(!empty($withdrawList)){
            foreach($withdrawList as $key => $list){
                $data_array[$key]['withdraw_number'] = $list['withdraw_number'];
                $data_array[$key]['add_time'] = date('Y-m-d H:i:s',$list['add_time']);
                $data_array[$key]['product_title'] = $list['product_title'];
                $data_array[$key]['customer_id'] = $list['customer_id'];
                $data_array[$key]['paypal_number'] = $list['paypal_number'];
                $data_array[$key]['withdraw_virtual_currency'] = $list['withdraw_virtual_currency'];
                $data_array[$key]['virtual_rate'] = $list['virtual_rate'];
                $data_array[$key]['withdraw_amount'] = $list['withdraw_amount'];
                switch($list['status']){
                    case 2:
                        $data_array[$key]['status'] = '审核成功';
                        break;
                    case 4:
                        $data_array[$key]['status'] = '驳回';
                        break;
                    default:
                        $data_array[$key]['status'] = '审核失败';
                }
            }

            $header_data =[
                'withdraw_number'=>'提现单号',
                'add_time'=>'申请时间',
                'product_title'=>'合约',
                'customer_id'=>'客户ID',
                'paypal_number'=>'Paypal账号',
                'withdraw_virtual_currency'=>'提现虚拟币',
                'virtual_rate'=>'当前汇率',
                'withdraw_amount'=>'折算金额',
                'status'=>'状态',
            ];
            $tool ->export('财务提现审核表',$header_data,$data_array,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

    /**
     * 弹出导入数据的就界面
     */
    public function importData(){
        return View();
    }

    /**
     * 导入数据
     */
    public function importDataPost()
    {
        ini_set('memory_limit','200M');
        vendor("PHPExcel.PHPExcel");
        //获取表单上传文件
        $file = request()->file('excel');
        if (!empty($file)) {
            $model = new BlockChainWithdrawModel();
            $info = $file->validate(['ext' => 'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');
            if ($info) {
                $exclePath = $info->getSaveName();  //获取文件名
                $file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址

                $name_arr = explode(".",$info->getInfo('name'));
                if( $name_arr[1] =='xlsx' )
                {
                    $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
                }
                else
                {
                    $objReader = \PHPExcel_IOFactory::createReader('Excel5');
                }
                $insertAll = array();
                $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
                $excel_array = $obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
                array_shift($excel_array);  //删除第一个数组(标题);
                $time = time();
                $date = date('Ymd');
                if(!empty($excel_array)){
                    foreach ($excel_array as $key => $val) {
                        $find = $model->findDailyIncome(['date_query' => $date]);
                        if(!empty($find)){
                            continue;
                        }
                        $insertAll[$key]['effective_date'] = date('Ymd',strtotime($val[0])); //订单生效时间
                        $insertAll[$key]['product_name'] = $val[1];
                        $insertAll[$key]['ths_num'] = $val[2];
                        $insertAll[$key]['daily_income'] = $val[3];
                        $insertAll[$key]['operator'] = Session::get('username');
                        $insertAll[$key]['add_time'] = $time;
                        $insertAll[$key]['date_query'] = $date;//执行时间
                    }
                }
                $insertAll = array_values($insertAll);
                if(!empty($insertAll)){
                    $ret = $model->insertAllDailyIncome($insertAll);
                    if($ret){
                        echo json_encode(array('code' => 200, 'result' => '导入成功'));
                    }
                }else{
                    echo json_encode(array('code' => 103, 'result' => '导入失败'));
                }
            } else {
                echo json_encode(array('code' => 102, 'result' => '数据提交失败'));
            }
        } else {
            echo json_encode(array('code' => 101, 'result' => '请检查数据后再上传！'));
        }
        exit;
    }
}