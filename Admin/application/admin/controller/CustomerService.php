<?php
namespace app\admin\controller;

use app\admin\model\OrderModel;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use think\Log;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\Common;
use app\admin\dxcommon\ExcelTool;
use app\admin\dxcommon\Email;
use vendor\aes\aes;
// use app\admin\model\Interface;


/**
 * 平台管理--客服相关
 * @author wang   2018-09-06
 */
class CustomerService extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('REPORTS', 'reports');//Mysql数据表
        define('REPORTS_CUSTOMS_INSURANCE', 'reports_customs_insurance');//Mysql数据表
        define('REPORTS_LOG', 'reports_log');//Mysql数据表
        define('MY_REVIEW_FILTERING', 'review_filtering');//Mysql数据表
        define('USER', 'user');//Mysql数据表
        define('APPLY_LOG', 'order_after_sale_apply_log');//mysql数据表 仲裁回复表
        define('FEEDBACK', 'feedback');
        define('FEEDBACKREPLY', 'feedback_reply');
        define('MY_WITHDRAW', 'withdraw');//mysql数据表
        // define('S_CONFIG', 'dx_sys_config');//Nosql数据表


    }

    /**
     * 风控凭证
     * [CrediTcardCertificate description]
     * @author wang   2018-09-07
     */
    public function CrediTcardCertificate()
    {
        $riskConfig = BaseApi::RiskConfig();
        $admin_user = getCustomerService();
        if ($data = request()->post()) {

            if (!empty($data['customer_name'])) {
                $where['customer_name'] = array('like', '%' . $data['customer_name'] . '%');//array('like',$data['title_cn']);
            }
            if (!empty($data['seller_name'])) {
                $where['seller_name'] = array('like', '%' . $data['seller_name'] . '%');
            }
            if (!empty($data['customer_id'])) {
                $where['customer_id'] = $data['customer_id'];
            }
            if (!empty($data['report_status'])) {
                $where['report_status'] = $data['report_status'];
            }
            if (!empty($data['seller_id'])) {
                $where['seller_id'] = $data['seller_id'];
            }
            if (!empty($data['startTime']) && !empty($data['endTime'])) {
                $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
            }
            if (!empty($data['order_number'])) {
                $where['order_number'] = ['in',QueryFiltering($data['order_number'])];
            }
            if (!empty($data['sku_num'])) {
                $order_where['sku_num'] = ['in',QueryFiltering($data['sku_num'])];
            }

            if(isset($data['admin_user']) && !empty($data['admin_user'])){
                $where['distribution_admin_id'] = $data['admin_user'];
            }else{
                $group_id = session("group_id");
                if(!empty($group_id) && $group_id == 9 && $data['distribution_status']<1){
                    $where['distribution_admin_id'] = session("userid");
                }
            }
            Cookie::set('RiskManagement', $where, 3600);
        }
        $status = input('status');
        if (!$where && $status) {
            $where = Cookie::get('RiskManagement');
            $data = $where;
        }

        if ($where) {
            $where['report_type'] = 5;
            $list = Db::name(REPORTS)->where($where)->order('add_time DESC')->paginate(20);// echo Db::name(REPORTS)->getLastSql();
            $page = str_replace("page", "status=1&page", $list->render());
        } else {
            $where['report_type'] = 5;
            //信用卡证明
            $list = Db::name(REPORTS)->where($where)->order('report_status asc,is_crash ASC,id desc')->paginate(20);
            $page = $list->render();
        }
        $list_items = $list->items();

        $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
        // $list_items = $list->items();
        $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
        foreach ((array)$list_items as $key => $value) {
            $list_items[$key]['enclosure'] = json_decode(htmlspecialchars_decode($list_items[$key]['enclosure']),true);
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if ($value["report_status"] == $v["code"]) {
                    $list_items[$key]["report_name"] = $v["name"];
                }
            }
        }
       
        $this->assign(['list' => $list_items, 'page' => $page, 'statusSelectHtml' => $statusSelectHtml, 'data' => $data, 'riskConfig' => $riskConfig["data"]['report_type'],'admin_user'=>$admin_user]);
        return View();
    }
    /*分配风控凭证 20190408 kevin*/
    public function distribution_credi_tcard_certificate(){
        $query_data = request()->post();
        $ids = isset($query_data['ids'])?$query_data['ids']:'';
        $distribution_admin_id = input("distribution_admin_id",'');
        $distribution_admin = input("distribution_admin",'');
        if(!empty($ids) && !empty($distribution_admin_id) && !empty($distribution_admin)){
            $where['id'] = ['in',$ids];
            $update_data['distribution_admin_id'] = $distribution_admin_id;
            $update_data['distribution_admin'] = trim($distribution_admin);
            $update_data['distribution_time'] = time();
            $update_res = Db::name(REPORTS)->where($where)->update($update_data);
            if(!$update_res){
                return ['code'=>1002,'msg'=>'分配失败！'];
            }else{
                return ['code'=>200,'msg'=>'分配成功！'];
            }
        }else{
            return ['code'=>1001,'msg'=>'参数错误'];
        }
    }

    /*设置紧急订单消息  20190527 kevin*/
    public function crash_credi_tcard_certificate(){
        $query_data = request()->post();
        $ids = isset($query_data['ids'])?$query_data['ids']:'';
        if(!empty($ids)){
            $update_data['is_crash'] = 1;
            $where['id'] = ['in',$ids];
            $update_res = Db::name(REPORTS)->where($where)->update($update_data);
            if(!$update_res){
                return ['code'=>1002,'msg'=>'设置失败！'];
            }else{
                return ['code'=>200,'msg'=>'设置成功！'];
            }
        }else{
            return ['code'=>1001,'msg'=>'参数错误'];
        }
    }

    /**
     * 关税赔宝
     * [CustomsInsurance description]
     */
    public function CustomsInsurance()
    {
        $riskConfig = BaseApi::RiskConfig();
        $is_export = 0;
        if ($data = request()->post()) {
            if (!empty($data['customer_name'])) {
                $where['customer_name'] = array('like', '%' . $data['customer_name'] . '%');
            }
            if (!empty($data['seller_name'])) {
                $where['seller_name'] = array('like', '%' . $data['seller_name'] . '%');
            }
            if (!empty($data['customer_id'])) {
                $where['customer_id'] = $data['customer_id'];
            }
            if (!empty($data['report_status'])) {
                $where['report_status'] = $data['report_status'];
            }
            if (!empty($data['seller_id'])) {
                $where['seller_id'] = $data['seller_id'];
            }
            if (!empty($data['startTime']) && !empty($data['endTime'])) {
                $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
            }
            if (!empty($data['order_number'])) {
                $where['order_number'] = $data['order_number'];
            }
            if (!empty($data['is_export'])) {
                $is_export = $data['is_export'];

            }
            Cookie::set('RiskManagement', $where, 3600);
        }
        $status = input('status');
        if (!$where && $status) {
            $where = Cookie::get('RiskManagement');
        }
        $page_size = 20;
        if ($is_export == 1){
            $page_size = 10000;
        }
//        echo json_encode($page_size);die;
        if ($where) {
            $where['report_type'] = 4;
            $list = Db::name(REPORTS)->where($where)->order('add_time desc')->paginate($page_size);
            $page = str_replace("page", "status=1&page", $list->render());
        } else {
            $where['report_type'] = 4;
            //信用卡证明
            $list = Db::name(REPORTS)->where($where)->order('add_time desc')->paginate($page_size);
            $page = $list->render();
        }
        $list_items = $list->items();

        $order_model = new OrderModel();

        vendor('aes.aes');
        $aes = new aes();
        $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
        $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
        foreach ((array)$list_items as $key => $value) {
            //获取PayPal账号
            $paypal_main = $aes->decrypt($value['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮
            $list_items[$key]["all_paypal_account"] = $paypal_main.$value["PayPalED"];
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if ($value["report_status"] == $v["code"]) {
                    $list_items[$key]["report_name"] = $v["name"];
                }
            }
            //获取审核状态
            $verify_status_str = '-';
            $insurance_info = Db::name(REPORTS_CUSTOMS_INSURANCE)->where(['reports_id'=>$value['id']])->find();
            if (!empty($insurance_info) && isset($insurance_info['status'])){
                //审核状态：0-待审，1-通过（后财务可开始打款），2-不通过
                switch ($insurance_info['status']){
                    case 0:
                        $verify_status_str = '待审核';
                        break;
                    case 1:
                        $verify_status_str = '通过';
                        break;
                    case 2:
                        $verify_status_str = '不通过';
                        break;
                }

            }
            $list_items[$key]["verify_status_str"] = $verify_status_str;
            $order_info = $order_model->getOrderInfoByOrderNumber($value['order_number'],'captured_amount_usd', true);
            if (isset($order_info['captured_amount_usd'])){
                $list_items[$key]["captured_amount_usd"] = $order_info['captured_amount_usd'];
            }
        }
//        htmlspecialchars_decode();
//        print_r($list_items);die;
        if ($is_export == 1){
            if(!empty($list_items)){
                foreach ($list_items as $k600 => $v600) {
                    $report_type = '-';
                    $imgs_str = '';//enclosure
                    $captured_price_usd = isset($v600['captured_amount_usd'])?$v600['captured_amount_usd']:'-';//原订单金额（美金）
                    $from = '-';//来源
                    $refund_time = !empty($v600['refund_time'])?date('Y-m-d H:i:s', $v600['refund_time']):'-';
                    $reports_time = !empty($v600['add_time'])?date('Y-m-d H:i:s', $v600['add_time']):'-';
                    if (isset($riskConfig["data"]['report_type'])){
                        foreach ($riskConfig["data"]['report_type'] as $k601=>$v601){
                            if ($v601['code'] == $v600['report_type']){
                                $report_type = $v601['name'];
                                break;
                            }
                        }
                    }
                    if (!empty($v600['enclosure'])){
                        $img_arr = json_decode(htmlspecialchars_decode($v600['enclosure']), true);
                        if (!empty($img_arr)){
                            foreach ($img_arr as $k602=>$v602){
                                $img_arr[$k602] = DX_FTP_ACCESS_URL.$v602;
                            }
                        }
                        $imgs_str = implode(',', $img_arr);
                    }
                    if (!empty($v600['from'])){
                        switch ($v600['from']){
                            case 1:
                                $from = 'My';
                                break;
                            case 2:
                                $from = 'Admin';
                                break;
                        }
                    }
                    $Export[] = [
                        'reports_id'=>$v600['id'],
                        'user_id'=>$v600['customer_id'],
                        'user_name'=>$v600['customer_name'],
                        'seller_id'=>$v600['seller_id'],
                        'seller_name'=>$v600['seller_name'],
                        'reports_type'=>$report_type,
                        'paypal_account'=>$v600['all_paypal_account'],
                        'imgs'=>$imgs_str,
                        'reason'=>$v600['reason'],
                        'amount'=>$v600['amount'],
                        'currency_code'=>$v600['currency_code'],
                        'captured_price_usd'=>$captured_price_usd,
                        'order_number'=>$v600['order_number'],
                        'reports_status'=>$v600['report_name'],
                        'verify_status'=>$v600['verify_status_str'],
                        'operation_name'=>$v600['operator'],
                        'refund_time'=>$refund_time,
                        'reports_time'=>$reports_time,
                        'from'=>$from
                    ];
                }
                // dump($Export);exit;
                $header_data =[
                    'reports_id'=>'ID',
                    'user_id'=>'用户ID',
                    'user_name'=>'用户名称',
                    'seller_id'=>'卖家ID',
                    'seller_name'=>'卖家名称',
                    'reports_type'=>'举报类型',
                    'paypal_account'=>'PayPal账号',
                    'imgs'=>'投诉图片',
                    'reason'=>'退款原因',
                    'amount'=>'金额',
                    'currency_code'=>'币种',
                    'captured_price_usd'=>'原订单实收金额（$）',
                    'order_number'=>'订单编码',
                    'reports_status'=>'状态',
                    'verify_status'=>'审核状态',
                    'operation_name'=>'操作人',
                    'refund_time'=>'退款时间',
                    'reports_time'=>'投诉时间',
                    'from'=>'来源',
                ];
                $tool = new ExcelTool();
                if(!empty($Export)){
                    $tool ->export('关税赔保数据'.date('YmdHis', strtotime('+8 hours')),$header_data,$Export,'sheet1');
                }else{
                    echo '没查到数据';
                    exit;
                }
            }
        }
        $this->assign(['list' => $list_items, 'page' => $page, 'statusSelectHtml' => $statusSelectHtml, 'data' => $data, 'riskConfig' => $riskConfig["data"]['report_type']]);
        return View();
    }

    /**
     * 产品举报
     * [ProductReport description]
     */
    public function ProductReport()
    {
        $riskConfig = BaseApi::RiskConfig();
        if ($data = request()->post()) {
            if ($data['customer_name']) {
                $where['customer_name'] = array('like', '%' . $data['customer_name'] . '%');
            }
            if ($data['seller_name']) {
                $where['seller_name'] = array('like', '%' . $data['seller_name'] . '%');
            }
            if ($data['customer_id']) {
                $where['customer_id'] = $data['customer_id'];
            }
            if ($data['report_status']) {
                $where['report_status'] = $data['report_status'];
            }
            if ($data['report_type']) {
                $where['report_type'] = $data['report_type'];
            }
            if ($data['seller_id']) {
                $where['seller_id'] = $data['seller_id'];
            }
            if ($data['startTime'] && $data['endTime']) {
                $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
            }
            Cache::set('RiskManagement', $where, 3600);
        }
        $status = input('status');
        if (!$where && $status) {
            $where = Cache::get('RiskManagement');
        }

        if ($where) {
            if (empty($where['report_type'])) {
                $where['report_type'] = array(array('eq', 1), array('eq', 2), array('eq', 3), 'or');
            }
            $list = Db::name(REPORTS)->where($where)->order('add_time DESC')->paginate(20);
            $page = str_replace("page", "status=1&page", $list->render());
        } else {
            $where['report_type'] = array(array('eq', 1), array('eq', 2), array('eq', 3), 'or');
            //信用卡证明
            $list = Db::name(REPORTS)->where($where)->order('add_time DESC')->paginate(20);
            $page = $list->render();
        }
        $list_items = $list->items();

        $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
        $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
        vendor('aes.aes');
        $aes=new aes();
        foreach ((array)$list_items as $key => $value) {
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if ($value["report_status"] == $v["code"]) {
                    $list_items[$key]["report_name"] = $v["name"];
                }
            }
            //对数据进行解密
            if(!empty($value['email'])){
                $list_items[$key]['email']  = $aes->decrypt($value['email'],'Reports','Email');//加密邮件前缀
            }
            if(!empty($value['phone'])){
                $list_items[$key]['phone']  = $aes->decrypt($value['phone'],'Reports','Phone');//加密邮件前缀
            }
        }
        unset($riskConfig["data"]["report_type"][4], $riskConfig["data"]["report_type"][5], $riskConfig["data"]["report_type"][100]);
        $this->assign(['list' => $list_items, 'page' => $page, 'statusSelectHtml' => $statusSelectHtml, 'data' => $data, 'riskConfig' => $riskConfig["data"]['report_type']]);
        return View();
    }

    //关税赔宝
    public function edit()
    {
        $PaymentMethod = array();
        $CustomsInsuranceType = array();
        $id = input('id');
        $order_number = input('order_number');
        $riskConfig = BaseApi::RiskConfig();
        $PaymentMethod = $this->dictionariesQuery('PaymentMethod');
        $CustomsInsuranceType = $this->dictionariesQuery('CustomsInsuranceType');
        if ($id) {
            $data['order_number'] = $order_number;
            if (!empty($data['order_number'])) {
                $CustomsInsurance = BaseApi::CustomsInsurance($data);
            } else {
                echo '订单号为空';
                exit;
            }
            if ($CustomsInsurance["code"] == 100) {
                echo $CustomsInsurance["data"];
                exit;
            }
            $ReportInfo = BaseApi::getReportInfo(['id' => $id]);
            if(empty($ReportInfo['data'])){
                echo '数据不存在';
                exit;
            }
            $list = $ReportInfo['data'];
            if (!empty($list["enclosure"])) {
                $enclosure_array = json_decode(htmlspecialchars_decode($list["enclosure"]), true);
                $enclosure_url = '';
                foreach ((array)$enclosure_array as $key => $value) {
                    if ($value) {
                        $enclosure_url .= $value . ';';
                    }
                }
                $list["enclosure_url"] = $enclosure_url;
                $list['enclosure'] = $enclosure_array;
            }
        }
        if (!empty($list['amount'])) {
            if($list['amount'] >0 && !empty($CustomsInsurance['data']['exchange_rate'])){
                $CustomsInsurance['data']['ConvertedPrice'] = sprintf("%.2f",$list['amount'] / $CustomsInsurance['data']['exchange_rate']);
            }else{
                $CustomsInsurance['data']['ConvertedPrice'] = $list['amount'];
            }
        }

        $list['username'] = Session::get('username');
        $this->assign(['list' => $list, 'CustomsInsurance' => $CustomsInsurance['data'], 'PaymentMethod' => $PaymentMethod, 'riskConfig' => $riskConfig["data"]["Currency"], 'CustomsInsuranceType' => $CustomsInsuranceType]);
        return View();
    }

    /**
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOExceptionArray
        (
            [exchange_rate] => 1.0000
            [id] => 2378
            [order_id] => 344676
            [order_number] => 191106100101101128
            [captured_amount] => 43.84
            [currency_code] => USD
            [refund_type] => Backtrack
            [amount] => 50.00
            [paypal] => 1007360726@qq.com
            [reason] => Test
            [report_status] => 3
        )

     */
    public function submit_edit()
    {
        if ($data = request()->post()) {
            if (!$data['currency_code']) {
                return json(['code'=>100, 'result'=>'币种不能为空']);
            }
            if (!$data['refund_type']) {
                return json(['code'=>100, 'result'=>'退款方式必选']);
            }
            if (!$data['amount']) {
                return json(['code'=>100, 'result'=>'金额不能为空']);
            }
            if (!$data['exchange_rate']) {
                return json(['code'=>100, 'result'=>'订单数据错误（汇率异常）']);
            }
            $_amount = $data['amount'];
            $_exchange_rate = $data['exchange_rate'];
            $_exchange_rate_amount = sprintf("%.2f", $_amount/$_exchange_rate);
            if ($_exchange_rate_amount > 40){
                return json(['code'=>100, 'result'=>'最大金额（'.$_exchange_rate_amount.'）不能超过$40']);
            }
            $report_status = Db::name(REPORTS)->where(['id' => $data['id']])->value("report_status");
            $where['currency_code'] = $data['currency_code'];
            $where['refund_type'] = $data['refund_type'];
            $where['amount'] = $_amount;
            //1-待处理（waiting process）、2-处理中（processing）、3-（已处理）接受处理关闭（case closed(has been established)）、4-驳回处理关闭（case closed(has not been established)）、5-撤销（case withdraw）
            $where['report_status'] = $data['report_status'];
            $where['operator'] = Session::get('username');
            $where['operator_id'] = session("userid");
            $where['operator_name'] = session("username");
            
            $where['reason'] = $data['reason'];
            $where['TxnID'] = isset($data['TxnID'])?$data['TxnID']:'';
            $where['paypal'] = isset($data['paypal']) && !empty($data['paypal'])?$data['paypal']:'-';
            /** 更新 ***/
            if ($data["id"]) {
                $where['edit_time'] = time();
                $id = $data["id"];

                $where['id'] = $id;
                $where['operator_id'] = session("userid");
                $where['operator_name'] = session("username");
                //更新
                $update_res = BaseApi::updateReportsforAdmin($where);
                if(isset($update_res['code']) && $update_res['code'] == 200){
                    /****** 赔保完成的需要进行自动退款操作 *****/
                    if ($where['report_status'] == 3) { //赔保完成
                        $finish_flag = false;
                        $tips_msg = '';
                        $return_code = 100;
                        if (!$data['order_id']) {
                            $tips_msg = '获取order_id失败';
//                            return json(['code'=>100, 'result'=>'获取order_id失败']);
                        }else{

                            $order_basics_where['orderNumber'] = $data['order_number'];
                            $order_data = BaseApi::getOrderDetail($order_basics_where);
//                        Log::record('order_data:'.json_encode($order_data));
                            if(isset($order_data['code']) && $order_data['code'] == 200 && isset($order_data['data']) && !empty($order_data['data'])){
                                $order_info = $order_data['data'];
                                /*当退款金额小于等于订单实付金额时走原路退款，否则需要财务审批*/
                                if($data['amount']<=$order_data['data']['captured_amount']){
                                    //先生成退款申请
                                    $_refund_data['order_id'] = $order_info['order_id'];
                                    $_refund_data['order_number'] = $order_info['order_number'];
                                    $_refund_data['store_id'] = $order_info['store_id'];
                                    $_refund_data['customer_id'] = $order_info['customer_id'];
                                    $_refund_data['customer_name'] = $order_info['customer_name'];
                                    $_refund_data['payment_txn_id'] = $order_info['transaction_id'];
                                    $_refund_data['store_name'] = $order_info['store_name'];
                                    $_refund_data['refunded_type'] = '';//增加关穗陪保退款类型（可不传，因为没处理）
                                    $_refund_data['remarks'] = $data['reason'];//退款原因
                                    $_refund_data['captured_refunded_fee'] = $data['amount'];//
                                    //发起申请方：1 买家 ；2 卖家 ；3 admin
                                    $_refund_data['initiator'] = 3;
                                    $_refund_data['refunded_fee'] = $data['amount'];//
                                    $_refund_data['applicant_admin_id'] = session("userid");
                                    $_refund_data['applicant_admin'] = session("username");
                                    $_refund_data['from'] = 2; //需要同步修改接口 退款来源：1-正常订单退款，2-关税陪保退款
                                    $_refund_data['reports_id'] = $id;
                                    //所有的退款
                                    $res = BaseApi::saveOrderRefund($_refund_data);
                                    Log::record('关税赔保增加退款申请。url：'.config('api_base_url').'orderbackend/OrderRefund/saveOrderRefund，params：'.json_encode($_refund_data).'，res：'.json_encode($res));
                                    if (!isset($res['code']) || $res['code'] != 200) {
                                        $tips_msg = '退款申请失败，请重试';
//                                    return json(['code'=>100, 'result'=>'退款申请失败，请重试']);
                                    }else{
                                        $finish_flag = true;
                                        $tips_msg = '更新成功';
                                        $return_code = 200;
//                                    return json(['code'=>200, 'result'=>'更新成功']);
                                    }
                                }else{
                                    $tips_msg = '陪保金额大于订单实收金额 '.$order_data['data']['captured_amount'].'，不能走原路退回';
//                                return json(['code'=>100, 'result'=>'陪保金额大于订单实收金额 '.$order_data['data']['captured_amount'].'，不能走原路退回']);
                                }
                            }else{
                                $tips_msg = '获取订单错误';
//                            return json(['code'=>100, 'result'=>'获取订单错误']);
                            }
                        }
                        if ($finish_flag){
                            //记录日志
                            $reports_log['reports_id'] = $data["id"];
                            $reports_log['operation'] = "后台操作更改状态";
                            $reports_log['operator'] = Session::get('userid');
                            $reports_log['operator_name'] = Session::get('username');
                            $reports_log['operator_type'] = 3;
                            $reports_log['add_time'] = time();
                            $reports_log['order_status_from'] = $report_status;
                            $reports_log['order_status'] = $where['report_status'];
                            $add_log = Db::name(REPORTS_LOG)->insert($reports_log);
                            if(!$add_log){
                                Log::write("add_log error,data:".json_encode($add_log));
                            }
                        }else{
                            //更新失败，需要回滚更新的数据
                            $where['report_status'] = 1;
                            $roll_res = BaseApi::updateReportsforAdmin($where);
                            Log::record('关税赔保增加退款申请-失败，回滚。url：'.config('api_base_url').'admin/Reports/updateReportsforAdmin，params：'.json_encode($where).'，res：'.json_encode($roll_res));
                        }
                        return json(['code'=>$return_code, 'result'=>$tips_msg]);
                    }else{

                        //记录日志
                        $reports_log['reports_id'] = $data["id"];
                        $reports_log['operation'] = "后台操作更改状态";
                        $reports_log['operator'] = Session::get('userid');
                        $reports_log['operator_name'] = Session::get('username');
                        $reports_log['operator_type'] = 3;
                        $reports_log['add_time'] = time();
                        $reports_log['order_status_from'] = $report_status;
                        $reports_log['order_status'] = $where['report_status'];
                        $add_log = Db::name(REPORTS_LOG)->insert($reports_log);
                        if(!$add_log){
                            Log::write("add_log error,data:".json_encode($add_log));
                        }
                        return json(['code'=>200, 'result'=>'更新成功']);
                    }
                }else{
                    Log::record('submit_edit_updateReportsforAdmin, params:'.json_encode($where).', res:'.json_encode($update_res), Log::ERROR);
                    $msg = isset($update_res['msg'])?'更新失败，请重试。'.$update_res['msg']:'更新失败，请重试';
                    return json(['code'=>100, 'result'=>$msg]);
                }
            } else { /**** 新增 ******/
                //来源：1-用户MY提交，2-后台客服提交
                $where['from'] = isset($data['from'])?$data['from']:2;
                $where['order_number'] = $str['order_number'] = $data['order_number'];
                if (!$where['order_number']) {
                    return json(['code'=>100, 'result'=>'订单号不能为空']);
                }
                $exist_reports = Db::name(REPORTS)
                    ->where(['order_number' => $str['order_number']])
                    ->where('report_status', 'in', [1,2,3])
                    ->find();
                if (!empty($exist_reports)){
                    return json(['code' => 100, 'result' => '已经提交过一次，只有驳回后才能再次提交']);
                }
                /**
                 * 订单增加验证 start
                 * 1.订单状态需要已发货、已完成
                 * 2.订单必须有购买关税赔保
                 */
                $order_model = new OrderModel();
                $order_info = $order_model->getOrderInfoByOrderNumber($where['order_number']);
                if (empty($order_info) || empty($order_info['order_status']) || empty($order_info['is_tariff_insurance'])){
                    return json(['code'=>100, 'result'=>'订单号验证失败']);
                }
                if ($order_info['order_status'] < 600 || $order_info['order_status'] > 900){
                    return json(['code'=>100, 'result'=>'该订单状态不符合提交赔保条件']);
                }
                if ($order_info['is_tariff_insurance'] != 1){
                    return json(['code'=>100, 'result'=>'该订单没有购买关税赔保']);
                }
                /*********** end ***********/
                $CustomsInsurance = BaseApi::CustomsInsurance($str);
                if ($CustomsInsurance['code'] = 200) {
                    $where['report_type'] = 4;
                    $where['add_time'] = time();
                    $where['seller_id'] = $CustomsInsurance["data"]['store_id'];
                    $where['order_master_number'] = $CustomsInsurance["data"]['order_master_number'];
                    $where['seller_name'] = $CustomsInsurance["data"]['store_name'];
                    $where['customer_id'] = $CustomsInsurance["data"]['customer_id'];
                    $where['customer_name'] = $CustomsInsurance["data"]['customer_name'];
                    $where['email'] = 'admin@dx.com';
                    $insert_res = BaseApi::addReportsforAdmin($where);
                    if (isset($insert_res['code']) && $insert_res['code'] == 200){
                        return json(['code'=>200, 'result'=>'提交成功']);
                    }else{
                        Log::record('addReportsErr:params:'.json_encode($where).', res:'.json_encode($insert_res));
                        return json(['code'=>100, 'result'=>'提交失败，请重试（'.$insert_res['msg'].'）']);
                    }
                } else {
                    Log::record('submit_edit_updateReportsforAdmin, params:'.json_encode($str).', res:'.json_encode($CustomsInsurance), Log::ERROR);
                    $msg = isset($CustomsInsurance['msg'])?'抱歉，提交失败，请重试。'.$CustomsInsurance['msg']:'抱歉，提交失败，请重试';
                    return json(['code'=>100, 'result'=>$msg]);
                }
            }
        }
    }


    /**
     * 动态获取订单数据
     * [order_number_data description]
     * @return [type] [description]
     */
    public function order_number_data()
    {
        if ($data = request()->post()) {
            if (is_numeric($data['order_number'])) {
                $where['order_number'] = $data['order_number'];
                $CustomsInsurance = BaseApi::CustomsInsurance($where);
                if ($CustomsInsurance["code"] == 200) {
                    // $CustomsInsurance["data"]['pay_time'] = time();
                    if (!empty($CustomsInsurance["data"]) && isset($CustomsInsurance["data"]["order_id"]) && !empty($CustomsInsurance["data"]["order_id"])){
                        echo json_encode(array('code' => 200, 'result' => $CustomsInsurance["data"]), true);
                    }else{
                        echo json_encode(array('code' => 100, 'result' => '找不到对应订单信息，请检查后重试'), true);
                    }
                    exit;
                } else {
                    echo json_encode(array('code' => 100, 'result' => $CustomsInsurance["data"]), true);
                    exit;
                }

            } else {
                echo json_encode(array('code' => 100, 'result' => '订单号有误'), true);
                exit;
            }
        }
    }

    /**
     * 动态获取订单数据【添加关税赔保用】
     * @return \think\response\Json
     */
    public function order_number_data_for_tariff()
    {
        if ($data = request()->post()) {
            if (is_numeric($data['order_number'])) {
                $where['order_number'] = $data['order_number'];
                $CustomsInsurance = BaseApi::CustomsInsurance($where);
                if ($CustomsInsurance["code"] == 200) {
                    if (
                        !empty($CustomsInsurance["data"])
                        && isset($CustomsInsurance["data"]["order_id"])
                        && !empty($CustomsInsurance["data"]["order_id"])
                        && !empty($CustomsInsurance["data"]["order_status"])
                        && !empty($CustomsInsurance["data"]["is_tariff_insurance"])
                    ){
                        if ($CustomsInsurance['data']['order_status'] < 600 || $CustomsInsurance['data']['order_status'] > 900){
                            return json(['code'=>100, 'result'=>'该订单状态不符合提交赔保条件']);
                        }
                        if ($CustomsInsurance['data']['is_tariff_insurance'] != 1){
                            return json(['code'=>100, 'result'=>'该订单没有购买关税赔保']);
                        }
                        return json(['code'=>200,'result'=>$CustomsInsurance["data"]]);
                    }else{
                        return json(['code'=>100,'result'=>'找不到对应订单信息']);
                    }
                } else {
                    return json(['code'=>100,'result'=>'订单验证试验，请重试']);
                }
            } else {
                return json(['code'=>100,'result'=>'订单号有误']);
            }
        }else{
            return json(['code'=>100,'result'=>'异常访问']);
        }
    }


    /**
     * 遍历风控状态
     * [statusSelect description]
     * @return [type] [description]
     * @author wang   2018-08-04
     */
    public function statusSelect($data = array(), $selectId = '', $status)
    {
        $html = '';
        $select = '';
        $html .= '<select name="' . $selectId . '" id="' . $selectId . '" class="form-control input-small inline">';
        $html .= '<option value="">请选择</option>';
        foreach ((array)$data as $key => $value) {
            if ($status == $value["code"]) {
                $select = 'selected = "selected"';
            }
            $html .= '<option ' . $select . ' value="' . $value["code"] . '">' . $value["name"] . '</option>';
            $select = '';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * 风控修改
     * [RiskStatus description]
     * @author wang   2018-08-03
     */
    public function RiskStatus()
    {
        if ($data = request()->post()) {
            $id = $data['id'];
            if ($data['report_status'] == 1) {
                $report_status = 3;
            }
            $result = Db::name(REPORTS)->where(['id' => $id])->update(['report_status' => $report_status, 'operator' => Session::get('username')]);
            if ($result) {
                echo json_encode(array('code' => 200, 'result' => '关闭成功'), true);
                exit;
            } else {
                echo json_encode(array('code' => 100, 'result' => '关闭失败'), true);
                exit;
            }
        }
    }

    /**
     * 风控管理
     * @author wang   2018-08-06
     */
    public function RiskManagement()
    {
        $riskConfig = BaseApi::RiskConfig();//api配置文件订单数据
        if ($data = request()->post()) {
            if ($data['customer_name']) {
                $where['customer_name'] = $data['customer_name'];
            }
            if ($data['seller_name']) {
                $where['seller_name'] = $data['seller_name'];
            }
            if ($data['customer_id']) {
                $where['customer_id'] = $data['customer_id'];
            }
            if ($data['report_status']) {
                $where['report_status'] = $data['report_status'];
            }
            if ($data['seller_id']) {
                $where['seller_id'] = $data['seller_id'];
            }
            if ($data['startTime'] && $data['endTime']) {
                $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
            }
            // Cache::set('RiskManagement', $where,3600);

        }
        $status = input('status');
        if (!$where && $status) {
            $where = Cache::get('RiskManagement');
        }
        if ($where) {
            $list = Db::name(REPORTS)->where($where)->order('add_time asc')->paginate(20);
            $page = str_replace("page", "status=1&page", $list->render());
        } else {
            $list = Db::name(REPORTS)->order('add_time asc')->paginate(20);
            $page = $list->render();
        }
        $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
        $list_items = $list->items();
        $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
        foreach ((array)$list_items as $key => $value) {
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if ($value["report_status"] == $v["code"]) {
                    $list_items[$key]["report_name"] = $v["name"];
                }
            }
        }

        $this->assign(['list' => $list_items, 'page' => $page, 'statusSelectHtml' => $statusSelectHtml]);
        return View();
    }

    /**
     * 评论过考虑
     * [ReviewFiltering description]
     * @author wang   2018-11-12
     */
    public function ReviewFiltering()
    {
        if ($data = request()->post()) {
            if (!empty($data["KeyWord"])) {
                $where['KeyWord'] = $data["KeyWord"];
                $where['edit_time'] = time();
                $where['edit_author'] = Session::get('username');
                $result = Db::name(MY_REVIEW_FILTERING)->where(['id' => $data["id"]])->update($where);
                if ($result) {
                    // $list = Db::name(MY_REVIEW_FILTERING)->where(['id'=>1])->find();
                    // $redis = redis();
                    // if(!empty($list['KeyWord'])){
                    //    $redis->set(REDIS_REVIEW_FILTERING,$list['KeyWord']);
                    // }
                    echo json_encode(array('code' => 200, 'result' => '数据提交成功'), true);
                    exit;
                } else {
                    echo json_encode(array('code' => 100, 'result' => '数据提交失败'), true);
                    exit;
                }
            } else {
                echo json_encode(array('code' => 100, 'result' => '不能提交空数据'), true);
                exit;
            }
        } else {
            // $redis = redis();
            // dump($redis->get(REDIS_REVIEW_FILTERING));
            $list = Db::name(MY_REVIEW_FILTERING)->where(['id' => 1])->find();
            $this->assign(['list' => $list]);
            return View();
        }
    }
    /**
     * 编辑评论
     * [ReviewFiltering description]
     * @author wang   2018-11-12
     */
    // public function edit_comment(){
    //     if($data = request()->post()){
    //         $where = array();
    //         $where['KeyWord'] = $data['KeyWord'];
    //         if(empty($data["id"]) && !empty($data["KeyWord"]) ){
    //             //新增
    //             $inspect = Db::name(MY_REVIEW_FILTERING)->where(['KeyWord'=>$data["KeyWord"]])->find();
    //             if($inspect){
    //                 echo json_encode(array('code'=>100,'result'=>'该关键词加过'),true);exit;
    //             }else{
    //                 $where['add_time'] = time();
    //                 $where['add_author'] = Session::get('username');
    //                 $result = Db::name(MY_REVIEW_FILTERING)->insert($where);
    //             }

    //         }else if(!empty($data["id"]) && !empty($data["KeyWord"]) ){
    //                //修改
    //                $where['edit_time'] = time();
    //                $where['edit_author'] = Session::get('username');
    //                $result = Db::name(MY_REVIEW_FILTERING)->where(['id'=>$data["id"]])->update($where);
    //         }else{
    //             echo json_encode(array('code'=>100,'result'=>'不可以提交空数据'),true);exit;
    //         }
    //         if($result){
    //             echo json_encode(array('code'=>200,'result'=>'数据编辑成功'),true);exit;
    //         }else{
    //             echo json_encode(array('code'=>100,'result'=>'数据编辑失败'),true);exit;
    //         }
    //     }else{
    //         return View();
    //     }

    // }
    /**
     * 用户反馈
     * @author kevin   2018-12-08
     */
    public function Feedback()
    {
        $data = input();
            if ($data['question_type']) {
                $where['question_type'] = $data['question_type'];
            }
            if ($data['customer_name']) {
                $where['customer_name'] = $data['customer_name'];
            }
            if ($data['customer_id']) {
                $where['customer_id'] = $data['customer_id'];
            }
            if ($data['order_number']) {
                $where['order_number'] = $data['order_number'];
            }
            if ($data['subject']) {
                $where['subject'] = ['like',"%".$data['subject']."%"];
            }
            if ($data['is_reply']) {
                $where['is_reply'] = $data['is_reply'];
            }
            if (!empty($data['startTime']) && !empty($data['endTime'])) {
                $where['addtime'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
            }else{
                if (!empty($data['startTime'])) {
                    $where['addtime'] = array('egt', strtotime($data['startTime']));
                }
                if(!empty($data['endTime'])){
                    $where['addtime'] = array('elt', strtotime($data['endTime']));
                }
            }
        $page_size= input("page_size",20);
        $page = input("page",1);
            // Cache::set('RiskManagement', $where,3600);
        $list = Db::name(FEEDBACK)->where($where)->order('is_reply asc,addtime asc')->paginate($page_size,false,[ 'page' => $page,'query'=>$data]);
            $HelpQuestionType = BaseApi::getSysCofig(['ConfigName'=>"HelpQuestionType"]);
            $this->assign(['list' => $list, 'HelpQuestionType'=>$HelpQuestionType,'page' => $list->render()]);
            return View();
        }

    /**
     * 用户反馈回复
     * @author kevin   2018-12-08
     */
    public function FeedbackReply()
    {
        if ($data = request()->post()) {
            if(isset($data['feedback_id']) && !empty($data['feedback_id'])){
                $is_reply = Db::name(FEEDBACK)->where(['feedback_id'=>$data['feedback_id']])->value("is_reply");
                if($is_reply == 2){
                    return array('code' => 100, 'result' => '此反馈已回复');
                }
                $userid = Session::get("userid");
                $username = Session::get('username');
                $reply['feedback_id'] = $data['feedback_id'];
                $reply['operator_id'] = !empty($userid)?$userid:7;
                $reply['operator_name'] = !empty($username)?$username:"admin";
                $reply['reply_content'] = $data['reply_content'];
                $reply['addtime'] = time();
                $insert_res = Db::name(FEEDBACKREPLY)->insert($reply);
                if($insert_res){
                    $update_where['feedback_id'] = $data['feedback_id'];
                    $update_data['is_reply'] = 2;
                    $update_data['edittime'] = time();
                    $updata_res = Db::name(FEEDBACK)->where($update_where)->update($update_data);
                    if(!$updata_res){
                        Log::write('FeedbackReply-InsertSuccess-UpdateError,data:'.json_encode($update_data).'<br> updata_res'.json_encode($updata_res));
                        return array('code' => 100, 'result' => '回复失败');
                    }else{
                        return array('code' => 200, 'result' => '回复成功');
                    }
                }else{
                    Log::write('FeedbackReply-InsertError,data:'.json_encode($reply).'<br> insert_res'.json_encode($insert_res));
                    return array('code' => 100, 'result' => '回复失败');
                }
            }else{
                return array('code' => 100, 'result' => '存在为空参数');
            }
        }else{
            // Cache::set('RiskManagement', $where,3600);
            $where['f.feedback_id'] = input("feedback_id/D");
            if(empty($where['f.feedback_id'])){
                $this->error("反馈信息不存在");
            }
            $data = Db::name(FEEDBACK)->alias("f")->join("dx_feedback_reply fr","f.feedback_id = fr.feedback_id","LEFT")->where($where)->field("f.*,fr.reply_content")->find();
            $HelpQuestionType = BaseApi::getSysCofig(['ConfigName'=>"HelpQuestionType"]);
            $this->assign(['data' => $data, 'HelpQuestionType'=>$HelpQuestionType]);
            return View();
        }
    }
    /**
     * 数据回复
     * [report description]
     * @return [type] [description]
     * @author wang   2018-12-13
     */
    public function report(){
         if ($data = request()->post()) {
             if(!empty($data['id']) && !empty($data['reply'])){
                  $where = array();
                  $where['reply'] = $data['reply'];
                  $where['report_status'] = 3;
                  $where['operator'] = Session::get('username');
                 /*获取回复时效 20190527 kevin*/
                 $report_data = Db::name(REPORTS)->where(['id'=>$data['id']])->find();
                 if($report_data['aging'] == 0){
                     $aging = time()-$report_data['add_time'];
                     $where['aging'] = sprintf("%01.2f", $aging/3600);
                 }
                 if($report_data['distribution_admin_id'] == 0){
                     $resly_update['distribution_admin_id'] = session("userid");
                     $resly_update['distribution_admin'] = session("username");
                     $resly_update['distribution_time'] = time();
                 }
                 $resly_update['operator_admin_id'] = session("userid");
                 $resly_update['operator_admin'] = session("username");
                 $resly_update['reply_time'] = time();
                  $list = Db::name(REPORTS)->where(['id'=>$data['id']])->update($where);
                  if($list){
                     echo json_encode(array('code' => 200, 'result' => '回复成功'), true);
                     exit;
                  }else{
                     echo json_encode(array('code' => 100, 'result' => '回复失败'), true);
                     exit;
                  }
             }else{
                  echo json_encode(array('code' => 100, 'result' => '存在为空参数'), true);
                  exit;
             }
         }
    }

    /*旧系统提现*/
    public function oldApplyWithdrawal(){
        $page_size = config('paginate.list_rows');
        $Config =  publicConfig(S_CONFIG,'MemberType');
        $ConfigStatus =  publicConfig(S_CONFIG,'OldWithdrawalStatus');
        $where = array();
        $list_items = array();
        $list_render = array();
        $list = array();
        if($data = request()->post()){
            $data = ParameterCheck($data);
            $where = $this->ParameterCheckAudit($data);
            if($where){
                $list = Db::name(MY_WITHDRAW)->where($where)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
                // $list_items  = $list->items();
                // $list_render = $list->render();
            }else{
                $list = Db::name(MY_WITHDRAW)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                ]);
            }

        }else{
            $data = input();
            $data = ParameterCheck($data);
            $where = $this->ParameterCheckAudit($data);
            if($where){
                $list = Db::name(MY_WITHDRAW)->where($where)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
            }else{
                $list = Db::name(MY_WITHDRAW)->order('add_time desc')->paginate($page_size,false,[
//                'page' => $page,
                    'type' => 'Bootstrap',
                ]);
            }

        }
        if(!empty($list)){
            $list_items  = $list->items();
            $list_render = $list->render();
        }

        if(!empty($list_items)){
            $aes =  aes();
            foreach($list_items as $k=>$v){
                if($v["PayPalEU"]){
                    $EmailUserName = $aes->decrypt($v['PayPalEU'],'AffiliateLevel','PayPalEU');//解密邮件前缀
                    $list_items[$k]['email'] = $EmailUserName.'@'.$v['PayPalED'];
                }
            }
        }
        $ConfigStatus = json_decode(htmlspecialchars_decode($ConfigStatus["result"]["ConfigValue"]),true);
        $this->assign(['list'=>$list_items,'page'=>$list_render,'data'=>$data,'Config'=>json_decode($Config["result"]["ConfigValue"],true),'ConfigStatus'=>$ConfigStatus]);
        return View();
    }

    /*
* 参数检查去除空的查询条件
* @author: wang
* @AddTime:2018-10-27
*/
    function ParameterCheckAudit($data=array()){
        if(!empty($data)){
            if($data['customer_type']){
                $where['customer_type'] = $data['customer_type'];
            }
            if($data['status']){
                $where['status'] = $data['status'];
            }
            if($data['order_number']){
                $where['order_number'] = $data['order_number'];
            }
            if($data['customer_id']){
                $where['customer_id'] = $data['customer_id'];
            }
            if($data['customer_name']){
                $where['customer_name'] = $data['customer_name'];
            }
            if($data['bank_withdrawals']){
                $where['bank_withdrawals'] = $data['bank_withdrawals'];
            }
            if($data['startTime'] && $data['endTime']){
                $startTime = strtotime($data['startTime']);
                $endTime = strtotime($data['endTime']);
                $where['add_time'] = array('between',''.$startTime.','.$endTime.'');
            }
            return $where;
        }
        return false;
    }


    /*
    * 业务审核提款 改方法只审核
    * mark等于1为物业审核，等于2为财务审核
    * @author: Wang
    * @AddTime:2018-10-25
    */

    public function WithdrawalStatus(){
        if($data = request()->post()){
            $where = array();
            $api_data =array();
            $resultEdit = array();
            if(!empty($data['id']) && !empty($data['status'])){
                $where['status'] = $data['status'];
                if($data['mark'] == 1){
                    $where['business_edit_time'] = time();
                }else if($data['mark'] == 2){
                    $where['finance_edit_time'] = time();
                }

                $api_data['Operator'] = $where['operator']  = Session::get('username');
                $where['operator_id']  = Session::get('userid');
                $where['operator_ip']  = $_SERVER["REMOTE_ADDR"];
                $order_number_list = Db::name(MY_WITHDRAW)->where(['id'=>$data['id']])->find();
                if($where['status'] == 4 || $where['status'] == 5){
                    if(!empty($data['remark'])){
                        $api_data['Reason'] = $where['remark'] = $data['remark'];
                    }else{
                        echo json_encode(array('code'=>100,'result'=>'理由不能为空'));
                        exit;
                    }
                }else if($where['status'] == 3){
                    //如果status为3则为付款，对cic数据修改状态
                    if($order_number_list){
                        $dataApi['order_number'] = $order_number_list['order_number'];
                        $dataApi['status'] = $where['status'];
                        $resultEdit = BaseApi::WithdrawStatus($dataApi);//dump($resultEdit);
                        if($resultEdit['code'] !=200){
                            echo json_encode(array('code'=>100,'result'=>$resultEdit['data']));
                            exit;
                        }
                    }
                }

                $where['status'] = $data['status'];
                $result = Db::name(MY_WITHDRAW)->where(['id'=>$data['id']])->update($where);
                if($where['status'] == 4 || $where['status'] == 5){
                    $customer_data = BaseApi::getCustomerByID($order_number_list['customer_id']);
                    if(!empty($customer_data['data']['email'])){
                        $send_email_resp = Email::sendEmailForUser(13,$customer_data['data']['email'],$customer_data['data']['UserName'],['username'=>$customer_data['data']['UserName'],'remark'=>$data['remark']]);
                    }
                }
                if($result){
                    echo json_encode(array('code'=>200,'result'=>'操作成功'));
                    exit;
                }else{
                    echo json_encode(array('code'=>100,'result'=>'操作失败'));
                    exit;
                }
            }else{
                echo json_encode(array('code'=>100,'result'=>'传递参数出错'));
                exit;
            }
        }
    }
    /**
     * 客户统计报表
     *report_type  5风控凭证
     *
     * [CustomerServiceReport description]
     * @author: Wang
     * @AddTime:2019-02-19
     */
    public function CustomerServiceReport(){
        $data = [];
        $Report = [];
        $dictionariesQuery = publicConfig(S_CONFIG,'CustomerServiceReport');
        $CustomerServiceReport = json_decode(htmlspecialchars_decode($dictionariesQuery["result"]["ConfigValue"]),true);

        $Config = [];
        $Config['config'] = array('report_type','report_status');
        //$ConfigurationInformation = BaseApi::ConfigurationInformation($Config);
        /*获取店铺信息*/
        $SellerLists = BaseApi::getStoreLists(['status'=>1]);
        $seller_data = isset($SellerLists['data'])?$SellerLists['data']:'';

        $where = input();
        //默认时间一个月
        if(empty($where['startTime']) || empty($where['endTime'])){
            $startTime = strtotime("-1 month 00:00:00");
            $endTime = strtotime("23:59:59");
            $where['startTime'] = date('Y-m-d H:i:s',$startTime);
            $where['endTime'] = date('Y-m-d H:i:s',$endTime);
            $where['status'] = 3;
        }else{
            $startTime = strtotime($where['startTime']);
            $endTime   = strtotime($where['endTime']);
        }
        $data['add_time'] = array('between',$startTime.','.$endTime);
        // $data['add_time'] = array(array('egt',$startTime),array('elt',$endTime));
        $TimeJudgment = $this->TimeJudgment($where);

        //风控凭证
        $data['distribution_admin_id'] = ['neq',0];
        /*店铺ID*/
        /*if(!empty($where['seller_id'])){
            $data['seller_id'] = $where['seller_id'];
        }*/
        $list['RiskControlCertificate'] = $this->RiskControlCertificate($data);

        //反馈及举报
        $list['Report'] = $this->FeedbackAndReporting($data);
        //关税赔保
        $list['CustomsInsurance'] = $this->Insurance($data);
        //订单信息 和  产品Q&A
        $OrderInformation = BaseApi::OrderInformation($data);//dump($OrderInformation);exit;
        //dump($OrderInformation);exit;
        if(!empty($OrderInformation['code']) && $OrderInformation['code'] == 200){
           $list['OrderInformation'] = json_decode($OrderInformation["data"],true);
           $list['product'] = json_decode($OrderInformation["question_info"],true);
        }
        if(!empty($list['RiskControlCertificate'])){
             $Report = $this->DataCombination($list['RiskControlCertificate'],$Report);
             foreach ($list['RiskControlCertificate'] as $k => $v) {
                $Report[$v['distribution_admin_id']]['distribution_admin'] = $v['distribution_admin'];
                $Report[$v['distribution_admin_id']]['RiskControlCertificate_count'] = $v['distribution_admin_count'];
                if(empty($Report[$v['distribution_admin_id']]['sum'])){
                    $Report[$v['distribution_admin_id']]['sum'] = $v['distribution_admin_count'];
                }else{
                    $Report[$v['distribution_admin_id']]['sum'] += $v['distribution_admin_count'];
                }
             }

        }
        if(!empty($list['Report'])){
              foreach ($list['Report'] as $ke => $ve) {
                $Report[$ve['distribution_admin_id']]['distribution_admin'] = $ve['distribution_admin'];
                $Report[$ve['distribution_admin_id']]['Report_count'] = $ve['distribution_admin_count'];
                if(empty($Report[$ve['distribution_admin_id']]['sum'])){
                    $Report[$ve['distribution_admin_id']]['sum'] = $ve['distribution_admin_count'];
                }else{
                    $Report[$ve['distribution_admin_id']]['sum'] += $ve['distribution_admin_count'];
                }
              }
              $Report = $this->DataCombination($list['Report'],$Report);
        }
        if(!empty($list['CustomsInsurance'])){
             foreach ($list['CustomsInsurance'] as $ki => $vi) {
                $Report[$vi['distribution_admin_id']]['distribution_admin'] = $vi['distribution_admin'];
                $Report[$vi['distribution_admin_id']]['CustomsInsurance_count'] = $vi['distribution_admin_count'];
                if(empty($Report[$vi['distribution_admin_id']]['sum'])){
                    $Report[$vi['distribution_admin_id']]['sum'] = $vi['distribution_admin_count'];
                }else{
                    $Report[$vi['distribution_admin_id']]['sum'] += $vi['distribution_admin_count'];
                }
             }
             $Report = $this->DataCombination($list['CustomsInsurance'],$Report);
        }
        if(!empty($list['OrderInformation'])){
             foreach ($list['OrderInformation'] as $kj => $vj) {
                $Report[$vj['distribution_admin_id']]['distribution_admin'] = $vj['distribution_admin'];
                $Report[$vj['distribution_admin_id']]['OrderInformation_count'] = $vj['distribution_admin_count'];
                if(empty($Report[$vj['distribution_admin_id']]['sum'])){
                    $Report[$vj['distribution_admin_id']]['sum'] = $vj['distribution_admin_count'];
                }else{
                    $Report[$vj['distribution_admin_id']]['sum'] += $vj['distribution_admin_count'];
                }
             }
             $Report = $this->DataCombination($list['OrderInformation'],$Report);
        }
        if(!empty($list['product'])){
             foreach ($list['product'] as $kl => $vl) {
                $Report[$vl['distribution_admin_id']]['distribution_admin'] = $vl['distribution_admin'];
                $Report[$vl['distribution_admin_id']]['product_count'] = $vl['distribution_admin_count'];
                if(empty($Report[$vl['distribution_admin_id']]['sum'])){
                    $Report[$vl['distribution_admin_id']]['sum'] = $vl['distribution_admin_count'];
                }else{
                    $Report[$vl['distribution_admin_id']]['sum'] += $vl['distribution_admin_count'];
                }
             }
             $Report = $this->DataCombination($list['product'],$Report);
        }
         // dump( Db::name(REPORTS)->getLastSql());
        $this->assign(['CustomerServiceReport'=>$CustomerServiceReport,'Report'=>$Report,'where'=>$where,'seller_data'=>$seller_data]);
        return View();
    }
    /**
     * 风控凭证
     * [RiskControlCertificate description]
     */
    public function RiskControlCertificate($data=array()){
        $data['report_type'] = 5;
        $RiskControlCertificate = Db::name(REPORTS)->where($data)->field('count(distribution_admin_id) as distribution_admin_count,distribution_admin_id,distribution_admin,report_type,AVG(aging) AS aging')->group('distribution_admin_id')->select();
        if(!empty($RiskControlCertificate)){
            foreach($RiskControlCertificate as $k => $v) {
                $data['report_type'] = 5;
                //解决任务数量
                $data['is_reply']    = 3;
                $data['distribution_admin_id'] = $v['distribution_admin_id'];
                $RiskControlCertificate[$k]['NumberOfSolutions'] = Db::name(REPORTS)->where($data)->count();
                //回复数量
                $data['is_reply']    = array(array('eq',3),array('eq',2), 'or');
                $RiskControlCertificate[$k]['NumberOfResponses'] = Db::name(REPORTS)->where($data)->count();
                //每个人的所有任务数
                unset($data['add_time'],$data['is_reply']);
                $RiskControlCertificate[$k]['AllTasks'] = Db::name(REPORTS)->where($data)->count();
            }
        }
        return $RiskControlCertificate;
    }
     /**
     * 反馈及举报
     * [RiskControlCertificate description]
     */
    public function FeedbackAndReporting($data=array()){
        $data['report_type'] = 1;
        $Report = Db::name(REPORTS)->where($data)->field('count(distribution_admin_id) as distribution_admin_count,distribution_admin_id,distribution_admin,report_type,AVG(aging) AS aging')->group('distribution_admin_id')->select();
        if(!empty($Report)){
            foreach($Report as $k => $v) {
                //解决任务数量
                $data['is_reply']    = 3;
                $data['distribution_admin_id'] = $v['distribution_admin_id'];
                $Report[$k]['NumberOfSolutions'] = Db::name(REPORTS)->where($data)->count();
                //回复数量
                $data['is_reply']    = array(array('eq',3),array('eq',2), 'or');
                $Report[$k]['NumberOfResponses'] = Db::name(REPORTS)->where($data)->count();
                //每个人的所有任务数
                unset($data['add_time'],$data['is_reply']);
                $Report[$k]['AllTasks'] = Db::name(REPORTS)->where($data)->count();
            }
        }
        return $Report;
    }
     /**
     * 关税赔保
     * [RiskControlCertificate description]
     */
    public function Insurance($data=array()){
        $data['report_type'] = 4;
        $CustomsInsurance = Db::name(REPORTS)->where($data)->field('count(distribution_admin_id) as distribution_admin_count,distribution_admin_id,distribution_admin,AVG(aging) AS aging')->group('distribution_admin_id,report_type')->select();
        if(!empty($CustomsInsurance)){
            foreach($CustomsInsurance as $k => $v) {
                //解决任务数量
                $data['is_reply']    = 3;
                $data['distribution_admin_id'] = $v['distribution_admin_id'];
                $CustomsInsurance[$k]['NumberOfSolutions'] = Db::name(REPORTS)->where($data)->count();
                //回复数量
                $data['is_reply']    = array(array('eq',3),array('eq',2), 'or');
                $CustomsInsurance[$k]['NumberOfResponses'] = Db::name(REPORTS)->where($data)->count();
                //每个人的所有任务数
                unset($data['add_time'],$data['is_reply']);
                $CustomsInsurance[$k]['AllTasks'] = Db::name(REPORTS)->where($data)->count();
            }
        }
        return $CustomsInsurance;
    }
    public function DataCombination($data=array(),$Report=array()){
           foreach ($data as $k => $v) {
                if(empty($v['distribution_admin_id'])){continue;}
                if(empty($Report[$v['distribution_admin_id']]['NumberOfSolutions'])){
                    $Report[$v['distribution_admin_id']]['NumberOfSolutions'] = $v['NumberOfSolutions'];
                }else{
                    $Report[$v['distribution_admin_id']]['NumberOfSolutions'] += $v['NumberOfSolutions'];
                }
                if(empty($Report[$v['distribution_admin_id']]['NumberOfResponses'])){
                    $Report[$v['distribution_admin_id']]['NumberOfResponses'] = $v['NumberOfResponses'];
                }else{
                    $Report[$v['distribution_admin_id']]['NumberOfResponses'] += $v['NumberOfResponses'];
                }
                if(empty($Report[$v['distribution_admin_id']]['AllTasks'])){
                    $Report[$v['distribution_admin_id']]['AllTasks'] = $v['AllTasks'];
                }else{
                    $Report[$v['distribution_admin_id']]['AllTasks'] += $v['AllTasks'];
                }
                if(empty($Report[$v['distribution_admin_id']]['aging'])){
                    $Report[$v['distribution_admin_id']]['aging'] = $v['aging'];
                }else{
                    $Report[$v['distribution_admin_id']]['aging'] += $v['aging'];
                }
             }
             return $Report;
    }
    /**
     *
     * 一天时间判断
     */
    public function TimeJudgment($data = array()){
            $startTime =  getdate(strtotime($data['startTime']));
            $endTime   =  getdate(strtotime($data['endTime']));
            if($data['status'] == 1){
                    if(($startTime['year']===$endTime['year']) && ($startTime['mday']===$endTime['mday']) && ($startTime['mon']===$endTime['mon'])){
                        return true;
                    }else{
                        return false;
                    }
            }else if($data['status'] == 2){
                    //周一
                    $monday = strtotime('last Monday',strtotime($data['startTime']));
                    //周日
                    $sunday = $monday+24*3600*7;
                    //判断
                    if(strtotime($data['endTime'])>$sunday){
                        return false;
                    }
                    if(strtotime($data['endTime'])<=$monday){
                        return false;
                    }
                    return true;
            }else if($data['status'] == 3){
                    if(($startTime['year']===$endTime['year']) && ($startTime['mday']===$endTime['mday'])){
                         return true;
                    }else if($startTime['mday']===$endTime['mday']){
                         $year = $endTime['year'] - $startTime['year'];
                         if($year == 1){
                            return true;
                         }else{
                            return false;
                         }
                    }else{
                         return false;
                    }
            }

    }
    /**
     * 退款数据导出
     */
    public function Refund(){
        /*获取币种*/
        $baseApi = new BaseApi();
        $currency_info_api = $baseApi::getCurrencyList();
        $currency_info_data = $currency_info_api['data'];
        $param_data = [];
        $data = input();
        $is_export = input("is_export",0);
        if($is_export == 1){
            $param_data['page_size'] = 10000000;
            $param_data['page'] = 1;
        }else{
            $param_data['page_size'] = input('page_size',20);
            $param_data['page'] = input("page",1);
        }
        if(!empty($data['payment_txn_id'])){
            $param_data['payment_txn_id'] = QueryFiltering($data['payment_txn_id']);
        }

        if(!empty($data['order_number'])){
            $param_data['order_number'] = QueryFiltering($data['order_number']);
        }
        //第三方交易标识
        if(!empty($data['third_party_txn_id'])){
            $param_data['third_party_txn_id'] = QueryFiltering($data['third_party_txn_id']);
        }

        if(!empty($data['currency_code'])){
            $param_data['currency_code'] = $data['currency_code'];
        }
        if(!empty($data['startTime'])){
            $param_data['startTime'] = $data['startTime'];
        }
        if(!empty($data['endTime'])){
            $param_data['endTime'] = $data['endTime'];
        }
        if(!empty($data['currency_code'])){
            $param_data['currency_code'] = $data['currency_code'];
        }
        $ExportRefundOrder = BaseApi::ExportRefundOrder($param_data);
        if(!empty($ExportRefundOrder['code']) && $ExportRefundOrder['code'] == 200){
             if(!empty($ExportRefundOrder['data'])){
                   foreach ((array)$ExportRefundOrder['data']['data'] as $k_order => $v_order) {
                       if(!empty($v_order['amount'])){
                           $ExportRefundOrder['data']['data'][$k_order]['amount'] = sprintf("%.2f", abs($v_order['amount']));
                       }
                       if(!empty($ExportRefundOrder['data']['data'][$k_order]['amount']) && !empty($v_order['exchange_rate'])){
                           $ExportRefundOrder['data']['data'][$k_order]['refunded_amount'] = sprintf("%.2f", $ExportRefundOrder['data']['data'][$k_order]['amount']/$v_order['exchange_rate']);
                       }else{
                           $ExportRefundOrder['data']['data'][$k_order]['refunded_amount'] = 0;
                       }
                        if(!empty($v_order['order_id'])){
                              $order[$v_order['order_id']] = $v_order;
                        }
                   }
             }
        }
        if($is_export == 1){
                if(!empty($ExportRefundOrder['data']['data'])){
                    foreach ((array)$ExportRefundOrder['data']['data'] as $k => $v) {
                        $order_master_number = !empty($v['order_master_number'])?$v['order_master_number']:'';
                        if($order_master_number == ''){
                            continue;
                        }
                        $order_number = !empty($v["order_number"])?$v["order_number"]:'';

                        if(!empty($v['refunded_amount'])  && !empty($v['exchange_rate'])){
                            $refunded_amount = abs(sprintf("%.2f", $v['amount']/$v['exchange_rate']));
                        }else{
                            $refunded_amount = '';
                        }
                        $pay_channel = !empty($v['pay_channel'])?$v['pay_channel']:'';
                        $country_code = !empty($v['country_code'])?$v['country_code']:'';
                        $pay_time = !empty($v['pay_time'])?date('Y-m-d h:i:s',$v['pay_time']):'';
                        $refund_time = $v['create_on']-$v['pay_time'];

                        $refund_day = 0;
                        if($refund_time>0){
                            $refund_day = $refund_time/(3600*24);
                            $refund_day= sprintf("%.1f", $refund_day);
                        }
                        $Export[] = ['order_number'=>$order_number,'reason'=>$v['remarks'],'grand_total'=>$v['grand_total'],'amount'=>$v['amount'],'currency_code'=>$v['currency_code'], 'payment_txn_id'=>$v['payment_txn_id'], 'pay_channel'=>$pay_channel,
                        'country_code'=>$country_code,'Operator'=>$v['operator_name'],'pay_time'=>$pay_time,'create_on'=>!empty($v["create_on"])?date("Y-m-d H:i:s",$v["create_on"]):'','refund_day'=>$refund_day
                        ];
                    }
                    // dump($Export);exit;
                    $header_data =['order_number'=>'子订单号', 'reason'=>'退款原因','grand_total'=>'订单总金额','amount'=>'退款金额','currency_code'=>'退款币种','payment_txn_id'=>'Invoice ID','pay_channel'=>'支付渠道',
                        'country_code'=>'国家','Operator'=>'操作人','pay_time'=>'支付时间','create_on'=>'退款时间','refund_day'=>"从下单到订单退款（天）"
                    ];
                   /* $header_data =['payment_txn_id'=>'Invoice ID','order_master_number'=>'订单号','order_number'=>'子订单号',
                        'reason'=>'退款原因','refunded_amount'=>'退款金额（换算为USD）','pay_channel'=>'支付渠道','txn_type'=>'交易类型',
                        'country_code'=>'国家','Operator'=>'操作人','pay_time'=>'收款时间','txn_time'=>'退款时间',
                    ];*/
                    $tool = new ExcelTool();
                    if(!empty($Export)){
                        $tool ->export('Refund',$header_data,$Export,'sheet1');
                    }else{
                        echo '没查到数据';
                        exit;
                    }

                }

        }
        $this->assign(['data'=>$data,'RefundOrder'=>!empty($ExportRefundOrder['data'])?$ExportRefundOrder['data']:'','currency_info_data'=>$currency_info_data]);
        return View();

    }
    /**
    * 订单产品导出查询
    * [OrderProductExport description]
    */
    public function OrderProductExport(){
        $item_sku = [];
        $list = [];
        $address = [];
        $after_sale_apply = [];
        $sales_txn_notes = [];
        $data = request()->post();
        if(empty($data)){
            $this->assign(['data'=>$data,'list'=>$list]);
            return View();
        }

        $fileName = '朴多维系统手动订单信息导出样本'.date('Ymd');
        header("Content-type:application/vnd.ms-excel;charset = UTF-8");
        header('Content-Disposition: attachment;filename  = "'.$fileName.'.csv"');
        header('Cache-Control: max-age = 0');
        // 直接输出到浏览器
        $fp = fopen('php://output','A');
        //在写入的第一个字符串开头加 bom。
        $header_data = [
               mb_convert_encoding('订单号请填写系统内生成的订单号（客服生成）','gb2312','utf-8'),
               mb_convert_encoding('产品信息(如果没有SKU号请留空)格式：B001*1|B002*2|','gb2312','utf-8'),
               mb_convert_encoding('收件人','gb2312','utf-8'),
               mb_convert_encoding('地址1','gb2312','utf-8'),
               mb_convert_encoding('地址2','gb2312','utf-8'),
               mb_convert_encoding('城市','gb2312','utf-8'),
               mb_convert_encoding('省份','gb2312','utf-8'),
               mb_convert_encoding('邮编','gb2312','utf-8'),
               mb_convert_encoding('收件人电话','gb2312','utf-8'),
               mb_convert_encoding('收件人手机','gb2312','utf-8'),
               mb_convert_encoding('国家代码','gb2312','utf-8'),
               mb_convert_encoding('系统备注（客服填写）','gb2312','utf-8'),
               mb_convert_encoding('其他标识可为空','gb2312','utf-8'),
               mb_convert_encoding('总价格(美元)注意：写正常金额','gb2312','utf-8'),
               mb_convert_encoding('物流ID(不选择可填空)','gb2312','utf-8'),
               mb_convert_encoding('最大发货时间(天)默认值','gb2312','utf-8'),
               mb_convert_encoding('产品单价（美元）','gb2312','utf-8')
        ];
        fputcsv($fp, $header_data);
        if($data){
            if(!empty($data['order_number'])){
                $after_sale_refund = [];
                $sales_txn_notes = [];
                $after_sale_apply = [];
                $where['order_number'] = QueryFiltering($data['order_number']);
                if(!empty($where['order_number'])){
                     $list = BaseApi::OrderProductExport($where);
                     if(!empty($list['item_sku'])){
                         $item_sku = $list['item_sku'];
                     }
                     if(!empty($list['address'])){
                         $address = $list['address'];
                     }

                     if(!empty($list['after_sale_apply'])){
                         $after_sale_apply = $list['after_sale_apply'];
                     }
                     if(!empty($list['sales_txn_notes'])){
                         $sales_txn_notes = $list['sales_txn_notes'];
                     }
                     if(!empty($list['after_sale_refund'])){
                         $after_sale_refund = $list['after_sale_refund'];
                     }

                     if(!empty($list["data"])){
                         foreach ($list["data"] as $k => $v) {
                              $customer_name = '';
                              $first_name = '';
                              $last_name = '';
                              $remarks = '';
                              $remarks .= !empty($v['remark'])?$v['remark']:'';
                              if(!empty($after_sale_apply[$v['order_id']])){
                                  $remarks .= $after_sale_apply[$v['order_id']];
                              }
                              if(!empty($sales_txn_notes[$v['order_id']])){
                                  $remarks .= $sales_txn_notes[$v['order_id']];
                              }
                              if(!empty($after_sale_refund[$v['order_id']])){
                                  $remarks .= $after_sale_refund[$v['order_id']];
                              }
                              $first_name = !empty($address[$v['order_id']]['first_name'])?$address[$v['order_id']]['first_name']:'';
                              $last_name  = !empty($address[$v['order_id']]['last_name'])?$address[$v['order_id']]['last_name']:'';

                              $Export = [
                                   '\''.$v['order_number'],
                                   !empty($item_sku[$v['order_id']]['sku'])?$item_sku[$v['order_id']]['sku']:'',
                                   // !empty($v['customer_name'])?$v['customer_name']:'',
                                   $first_name.' '.$last_name,
                                   !empty($address[$v['order_id']]['street1'])?$address[$v['order_id']]['street1']:'',
                                   !empty($address[$v['order_id']]['street2'])?$address[$v['order_id']]['street2']:'',
                                   !empty($address[$v['order_id']]['city'])?$address[$v['order_id']]['city']:'',
                                   !empty($address[$v['order_id']]['state'])?$address[$v['order_id']]['state']:'',
                                   !empty($address[$v['order_id']]['postal_code'])?$address[$v['order_id']]['postal_code']:'',
                                   !empty($address[$v['order_id']]['mobile'])?$address[$v['order_id']]['mobile']:'',
                                   !empty($address[$v['order_id']]['phone_number'])?$address[$v['order_id']]['phone_number']:'',
                                   !empty($address[$v['order_id']]['country_code'])?$address[$v['order_id']]['country_code']:'',
                                   mb_convert_encoding($remarks,'gb2312','utf-8'),
                                   '',
                                   !empty($v['captured_amount_usd'])?$v['captured_amount_usd']:'',
                                   '',
                                   '',
                                   !empty($item_sku[$v['order_id']]['captured_price_usd'])?$item_sku[$v['order_id']]['captured_price_usd']:'',
                             ];
                             fputcsv($fp, $Export);
                         }
                     }
                }
                fclose($fp);
            }
        }
    }
    /**
     * 导出csv
     * [OrderProductExportCsv description]
     */
    public function OrderProductExportCsv(){

    }

    /*
     * 获取筛选时间
     * */
    public function getQueryTime(){
        /*时间周期类型 1：天 ，2：周，3：月*/
        $time_type = input("time_type",1);
        $start_time = input("start_time");
        $end_time = input("end_time");
        $now_time = time();
        switch ($time_type){
            case 1:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",$now_time);
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = $start_time;
                    $end_time_str = date("Y-m-d H:i:s",strtotime("+1 day",strtotime($start_time)));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d H:i:s",strtotime("-1 day",strtotime($end_time)));
                    $end_time_str = $end_time;
                }
                break;
            case 2:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime("-1 week"));
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = $start_time;
                    $end_time_str = date("Y-m-d 00:00:00",strtotime("+1 week",strtotime($start_time)));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d 23:59:59",strtotime("-1 week",strtotime($end_time)));
                    $end_time_str = $end_time;
                }
                break;
            case 3:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime("-1 month"));
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = $start_time;
                    $end_time_str = date("Y-m-d 00:00:00",strtotime("+1 month",strtotime($start_time)));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d 23:59:59",strtotime("-1 month",strtotime($end_time)));
                    $end_time_str = $end_time;
                }
                break;
            default:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",$now_time);
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = $start_time;
                    $end_time_str = date("Y-m-d H:i:s",strtotime("+1 day",strtotime($start_time)));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d H:i:s",strtotime("-1 day",strtotime($end_time)));
                    $end_time_str = $end_time;
                }
                break;
        }
        $data['start_time_str'] = $start_time_str;
        $data['end_time_str'] = $end_time_str;
        return $data;
    }

    /*
     * 订单信息列表
     * */
    public function orderList(){
        /*获取币种*/
        $baseApi = new BaseApi();
        $currency_info_api = $baseApi::getCurrencyList();
        $currency_info_data = $currency_info_api['data'];
        $param_data = [];
        $data = input();
        $is_export = input("is_export",0);
        if($is_export == 1){
            $param_data['page_size'] = 10000000;
            $param_data['page'] = 1;
        }else{
            $param_data['page_size'] = input('page_size',20);
            $param_data['page'] = input("page",1);
        }
        if(!empty($data['payment_txn_id'])){
            $param_data['payment_txn_id'] = QueryFiltering($data['payment_txn_id']);
        }

        if(!empty($data['order_number'])){
            $param_data['order_number'] = QueryFiltering($data['order_number']);
        }
        //第三方交易标识
        if(!empty($data['third_party_txn_id'])){
            $param_data['third_party_txn_id'] = QueryFiltering($data['third_party_txn_id']);
        }

        if(!empty($data['currency_code'])){
            $param_data['currency_code'] = $data['currency_code'];
        }
        if(!empty($data['startTime'])){
            $param_data['startTime'] = $data['startTime'];
        }
        if(!empty($data['endTime'])){
            $param_data['endTime'] = $data['endTime'];
        }
        if(!empty($data['currency_code'])){
            $param_data['currency_code'] = $data['currency_code'];
        }
        //支付渠道
        if(!empty($data['payment_method'])){
            $param_data['payment_method'] = $data['payment_method'];
        }
        $OrderInformation = BaseApi::getOrderInformation($param_data);
        if(!empty($OrderInformation['code']) && $OrderInformation['code'] == 200){
            $sku_code_data = array();
            if(!empty($OrderInformation['data'])){
                if(!empty($OrderInformation['data']['data'])){
                    $sku_nums = array_column($OrderInformation['data']['data'],"sku_num");
                    $sku_data = BaseApi::getSkuProductBySkuCode(['sku_codes'=>$sku_nums]);
                    if(!empty($sku_data['data'])){
                        foreach ($sku_data['data'] as $key=>$value){
                            if(!empty($value['Skus'])){
                                foreach ($value['Skus'] as $k=>$v){
                                    $sku_code_data[$v['Code']] = $v;
                                }
                            }
                        }
                    }
                }
                foreach ((array)$OrderInformation['data']['data'] as $k_order => $v_order) {
                    if(!empty($sku_code_data[$v_order['sku_num']])){
                        $OrderInformation['data']['data'] [$k_order]['inventory'] = $sku_code_data[$v_order['sku_num']]['Inventory'];
                    }else{
                        $OrderInformation['data']['data'] [$k_order]['inventory'] = 9999;
                    }
                    if(!empty($v_order['captured_price_usd']) && $v_order['captured_price_usd']!=0){
                        $exchange_rate = $v_order['captured_price']/$v_order['captured_price_usd'];
                        $OrderInformation['data']['data'][$k_order]['exchange_rate'] = sprintf("%.2f", $exchange_rate);
                    }else{
                        $OrderInformation['data']['data'][$k_order]['exchange_rate'] = 0;
                    }
                }
            }
        }
        if($is_export == 1){
            if(!empty($OrderInformation['data']['data'])){
                foreach ((array)$OrderInformation['data']['data'] as $k => $v) {

                    $Export[] = ['order_number'=>$v['order_number'],'user_name'=>$v['first_name'].' '.$v['last_name'],
                        'address'=>$v['street1'].' '.$v['street2'],'state'=>$v['state'],'city'=>$v['city'],'mobile'=>$v['mobile'],'country'=>$v['country'],
                        'postal_code'=>$v['postal_code'],'sku_num'=>$v['sku_num'],'product_nums'=>$v['product_nums'],'inventory'=>$v['inventory'],'captured_price'=>$v['captured_price'],'currency_code'=>$v['currency_code'],
                        'product_name'=>$v['product_name'],'exchange_rate'=>$v['exchange_rate'],'captured_price_usd'=>$v['captured_price_usd']
                    ];
                }
                // dump($Export);exit;
                $header_data =['order_number'=>'订单号','user_name'=>'用户姓名','address'=>'地址',
                    'state'=>'省份','city'=>'城市','mobile'=>'电话','country'=>'国家',
                    'postal_code'=>'邮编','sku_num'=>'SKU','product_nums'=>'数量','inventory'=>'库存','captured_price'=>'单价',
                    'currency_code'=>'币种', 'product_name'=>'产品名称','exchange_rate'=>'汇率','captured_price_usd'=>'单价（美金）'
                ];
                $tool = new ExcelTool();
                if(!empty($Export)){
                    $tool ->export('OrderList'.time(),$header_data,$Export,'sheet1');
                }else{
                    echo '没查到数据';
                    exit;
                }

            }

        }
        $this->assign(['data'=>$data,'OrderInformation'=>!empty($OrderInformation['data'])?$OrderInformation['data']:'','currency_info_data'=>$currency_info_data]);
        return View();
    }


    /*
     * 退款审核列表
     * */
    public function refundAuditList(){
        /*获取币种*/
        $baseApi = new BaseApi();
        $currency_info_api = $baseApi::getCurrencyList();
        $currency_info_data = $currency_info_api['data'];
        $param_data = [];
        $data = input();
        $is_export = input("is_export",0);
        if($is_export == 1){
            $param_data['page_size'] = 10000000;
            $param_data['page'] = 1;
        }else{
            $param_data['page_size'] = input('page_size',20);
            $param_data['page'] = input("page",1);
        }
        if(!empty($data['order_number'])){
            $param_data['order_number'] = QueryFiltering($data['order_number']);
        }
        //用户名称
        if(!empty($data['customer_name'])){
            $param_data['customer_name'] = QueryFiltering($data['customer_name']);
        }

        if(!empty($data['startTime'])){
            $param_data['startTime'] = $data['startTime'];
        }
        if(!empty($data['endTime'])){
            $param_data['endTime'] = $data['endTime'];
        }
        //状态
        if(!empty($data['status'])){
            $param_data['status'] = $data['status'];
        }
        $param_data['is_page'] = 1;
        $OrderRefundList = BaseApi::getOrderRefundList($param_data);
        //dump($OrderRefundList);exit;
        if($is_export == 1){
            /*if(!empty($OrderInformation['data']['data'])){
                foreach ((array)$OrderInformation['data']['data'] as $k => $v) {

                    $Export[] = ['order_number'=>$v['order_number'],'user_name'=>$v['first_name'].' '.$v['last_name'],
                        'address'=>$v['street1'].' '.$v['street2'],'state'=>$v['state'],'city'=>$v['city'],'mobile'=>$v['mobile'],'country'=>$v['country'],
                        'postal_code'=>$v['postal_code'],'sku_num'=>$v['sku_num'],'product_nums'=>$v['product_nums'],'stock'=>'','captured_price'=>$v['captured_price'],'currency_code'=>$v['currency_code'],
                        'product_name'=>$v['product_name'],'exchange_rate'=>$v['exchange_rate'],'captured_price_usd'=>$v['captured_price_usd']
                    ];
                }
                // dump($Export);exit;
                $header_data =['order_number'=>'订单号','user_name'=>'用户姓名','address'=>'地址',
                    'state'=>'省份','city'=>'城市','mobile'=>'电话','country'=>'国家',
                    'postal_code'=>'邮编','sku_num'=>'SKU','product_nums'=>'数量','stock'=>'有无库存','captured_price'=>'单价',
                    'currency_code'=>'币种', 'product_name'=>'产品名称','exchange_rate'=>'汇率','captured_price_usd'=>'单价（美金）'
                ];
                $tool = new ExcelTool();
                if(!empty($Export)){
                    $tool ->export('OrderList'.time(),$header_data,$Export,'sheet1');
                }else{
                    echo '没查到数据';
                    exit;
                }

            }*/

        }
        $status_data = [1=>"退款申请中",2=>"退款成功",3=>"退款失败",4=>"退款拒绝"];
        $type_data =[1=>"仅退款",2=>"退货并退款",3=>"不退货退款"];
        $this->assign(['data'=>$data,'OrderRefundList'=>!empty($OrderRefundList['data'])?$OrderRefundList['data']:'','currency_info_data'=>$currency_info_data,'status_data'=>$status_data,'type_data'=>$type_data]);
        return View();
    }


    /*
     * 退款审核操作【允许退款和拒绝退款】
     * */
    public function refundAudit(){
        $rtn = ['msg'=>'', 'code'=>100];
        $param = input();
        $audit_remarks = input("audit_remarks");
        $status = input("status");
        $refund_id = !empty($param["refund_id"])?$param["refund_id"]:[];
        if($status!=2 && $status!=4){
            $rtn['msg'] = "审核状态值有误！";
            return $rtn;
        }
        if(empty($refund_id)){
            $rtn['msg'] = "审核退款ID为空！";
            return $rtn;
        }
        if(strlen($audit_remarks)>200){
            $rtn['msg'] = "描述长度不能超过200个字符！";
            return $rtn;
        }
        $base_api = new BaseApi();
        if($status == 4){//审核拒绝
            $update_param['refund_id'] = $refund_id;
            $update_param['status'] = $status;
            $update_param['audit_remarks'] = $audit_remarks;
            $update_param['audit_admin_id'] = session("userid");
            $update_param['audit_admin'] = session("username");
            $update_res = $base_api->updateOrderRefund($update_param);
            return $update_res;
        }else{//审核通过进行退款
            //再次校验$refund_id
            if (
                isset($param['refund_id']) && !empty($param['refund_id'])
                &&isset($param['status']) && !empty($param['status'])
            ){
                if(!is_array($refund_id)){
                    $refund_id = explode(",",$refund_id);
                }
                foreach ($refund_id as $key=>$vaule){
                    $param_refund = array();
                    $param_refund['refund_id'] = $vaule;
                    $refund_data = BaseApi::getOrderRefund($param_refund);
                    if(empty($refund_data)||empty($refund_data['data']['refund_id'])){
                        $rtn['code'] =100;
                        $rtn['msg'] = '订单编号:'.$refund_data['data']['order_number'].' 退款id不能为空,请联系开发人员';
                        return json($rtn);
                    }
                    if($refund_data['data']['status'] == 2){
                        $rtn['code'] =100;
                        $rtn['msg'] = '订单编号:'.$refund_data['data']['order_number'].' 订单已退款成功，请勿重复退款';
                        return json($rtn);
                    }
                    $order_info_api = $base_api::getOrderDetail(['orderNumber'=>$refund_data['data']['order_number']]);
                    $param_data['refund_id'] = $vaule;
                    $param_data['status'] = 2;
                    $param_data['customer_id'] = $order_info_api['data']['customer_id'];
                    //进入平台自动退款，若成功则变为‘退款成功’，若失败则列表行该列不发生变化 TODO
                    $up_param['refund_id'] = $vaule;
                    $up_param['order_id'] = $order_info_api['data']['order_id'];
                    //新系统并且是payply
                    if(($order_info_api['data']['payment_system']==2)&&(strtolower($order_info_api['data']['pay_channel'])=='paypal')){
                        $transaction_id = $base_api::getTransaction(['order_id'=>$order_info_api['data']['order_id'],'txn_type'=>'Capture']);
                    }else{
                        $transaction_id = $order_info_api['data']['transaction_id'];
                        if(empty($transaction_id)){
                            $transaction_id = $base_api::getTransaction(['order_id'=>$order_info_api['data']['order_id']]);
                        }
                    }
                    if(empty($transaction_id) || !is_numeric($transaction_id)){
                        $rtn['code'] =100;
                        $rtn['msg'] = '订单编号:'.$order_info_api['data']['order_number'].',transaction_id不能为空,请联系开发人员';
                        return json($rtn);
                    }
                    $payment_system = $order_info_api['data']['payment_system'];
                    //退款来源:1-seller售后退款；2-my退款；3-admin退款
                    $up_param['refund_from'] = 3;
                    //退款类型：1-陪保退款；2-售后退款；3-订单取消退款；4-普通退款
                    $up_param['refund_type'] = 4;
                    //操作人类型：1-admin，2-seller，3-my
                    $up_param['operator_type'] = 1;
                    $up_param['operator_id'] = session("userid");
                    $up_param['operator_name'] = session("username");
                    $up_param['reason'] = !empty($remarks)?$remarks:'退款确认申请退款';
                    $chage_desc = "Admin refund operation cancels order";
                    $change_reason = !empty($audit_remarks)?$audit_remarks:$chage_desc;
                    /*取消订单*/
                    $up_param['change_reason'] = $change_reason;
                    //$up_param['change_reason_id'] = "";
                    $up_param['create_by'] = "Admin,operator id:".session("userid").",operator name:".session("username");
                    $up_param['create_ip'] = GetIp();
                    $up_param['chage_desc'] = $chage_desc;
                    //$res = BaseApi::refundOrder($up_param);
                    Log::record($payment_system.'退款'.print_r($up_param, true));
                    $res = BaseApi::refundOrder($up_param);
                    Log::record($up_param['order_id'].'$res退款'.print_r($res, true));
                    if ($res['code'] == 200){
                        $rtn['code'] = 200;
                        $rtn['msg'] = 'success';
                    }else{
                        $rtn['msg'] = '订单编号：'.$order_info_api['data']['order_number'].',退款失败 '.$res['msg'];
                        Log::record('async_refundAllConfirmApply->确认申请 退款失败'.print_r($res, true),",param:".json_encode($up_param));
                        return $rtn;
                    }

                }
            }
        }
        return $rtn;
    }
}