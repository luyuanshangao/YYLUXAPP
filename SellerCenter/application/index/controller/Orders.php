<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use app\index\dxcommon\Order;
use app\index\dxcommon\Phpexcel;
use app\services\controller\WcfService;
use think\Log;

/**
 * Class Orders
 * @author tinghu.liu
 * @date 2018-04-10
 * @package app\index\controller
 * seller 订单
 */
class Orders extends Common
{
    /**
     * 所有订单
     */
    public function all(){
        /** 其他搜索条件 **/
        $where['store_id'] = $this->login_user_id;
        $product_name = input('product_name');
        $customer_name = input('customer_name');
        if(!empty($product_name)){
            if(is_numeric($product_name)){
                $where['product_id'] = $product_name;
            }else{
                $where['product_name'] = $product_name;
            }
        }

        if(!empty($customer_name)){
            if(is_numeric($customer_name)){
                $where['customer_id'] = $customer_name;
            }else{
                $where['customer_name'] = $customer_name;
            }
        }
        $where['is_reply'] = input("is_reply");
        $where['order_number'] = input('order_number');
        $where['order_status'] = input('order_status');
        $where['sku_num'] = input('sku_num');
        $where['unread'] = input('unread');
        $istoday = input('istoday');
        if (!empty($istoday) && $istoday == 1){
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d 23:59:59');
        }else{
            $create_on_start = input('create_on_start');
            $create_on_end = input('create_on_end');
        }
        $where['create_on_start'] = strtotime($create_on_start);
        $where['create_on_end'] = strtotime($create_on_end);
        /** 分页条件 start **/
        $where['page_size'] = input('page_size/d', 5);
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page' && $k != 'page_size'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('Orders/all', $p, config('default_return_type'), true);
        /** 分页条件 end **/
        $base_api = new BaseApi();
        $order_data_api  = $base_api->getOrderLists($where);
        $order_data = isset($order_data_api['data']['data'])&&!empty($order_data_api['data']['data'])?$order_data_api['data']['data']:[];
        $page_html = isset($order_data_api['data']['Page'])&&!empty($order_data_api['data']['Page'])?$order_data_api['data']['Page']:'';
        $order_status_data = Base::getOrderStatus();

        foreach ($order_data as &$order_info){
            //订单状态
            //$order_status_str = Base::getOrderStatus($order_info['order_status']);
            //$order_info['order_status_str'] = isset($order_status_str['name'])?$order_status_str['name']:'-';
            //币种符号
            $order_info['grand_total'] = sprintf("%.2f",$order_info['grand_total']);
            $order_info['captured_amount_usd'] = sprintf("%.2f",$order_info['captured_amount_usd']);
            $currency_code_str = '$';//默认美元
            $currency_code = $order_info['currency_code'];
            if ($currency_code != 'USD'){
                $currency_info_api = $base_api->getCurrencyList();
                $currency_info = isset($currency_info_api['data'])&&!empty($currency_info_api['data'])?$currency_info_api['data']:[];
//                print_r($currency_info);
                foreach ($currency_info as $c_info){
                    if ($c_info['Name'] == $currency_code){
                        $currency_code_str = $c_info['Code'];
                        break;
                    }
                }
            }
            $order_info['currency_code_str'] = $currency_code_str;
            //产品过滤（去掉没有产品的数据）
            foreach ($order_info['item_data'] as $key=>$item){
                if (empty($item['product_id']) || $item['product_id'] == 0){
                    unset($order_info['item_data'][$key]);
                }
            }
            foreach ($order_info['item_data'] as &$order_item){
                $product_attr_desc = htmlspecialchars_decode($order_item['product_attr_desc']);
                $order_item['product_attr_desc'] = Base::handleOrderProductaAttrDesc($product_attr_desc);
            }
            //价格调整
            $order_info['grand_total_new'] = $order_info['grand_total']+$order_info['adjust_price'];
            $order_info['captured_amount_usd_new'] = $order_info['captured_amount_usd'] + round($order_info['adjust_price']/$order_info['exchange_rate'], 2);
            //订单状态
            $order_status = $order_info['order_status'];
            $order_status_str = '';
            foreach ($order_status_data as $key=>$val) {
                if ($val['code'] == $order_status){
                    $order_status_str = $val['name'];
                    break;
                }
            }
            $order_info['order_status_str'] = $order_status_str;
        }
        //获取订单状态对应数量
        $status_data_api = $base_api->getOrderStatusNum(['store_id'=>$this->login_user_id]);
        $status_data = isset($status_data_api['data'])&&!empty($status_data_api['data'])?$status_data_api['data']:[];
        //订单状态
        $this->assign('create_on_start', $create_on_start);
        $this->assign('create_on_end', $create_on_end);
        $this->assign('status_data', $status_data);
        $this->assign('order_status_data', $order_status_data);
        $this->assign('order_data', $order_data);
        $this->assign('page_html', $page_html);
        $this->assign('ajax_url', json_encode([
            'async_adjustmentPrice'=>url('Orders/async_adjustmentPrice'),
            'async_adjustmentDeliveryTime'=>url('Orders/async_adjustmentDeliveryTime'),
        ]));
        $this->assign('title','所有订单');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
    }

    /**
     * 订单详情
     */
    public function detail(){
        $order_number = input('order_number');
        if (empty($order_number) || !is_numeric($order_number) || $order_number<=0){
            abort(404);
        }
        $base_api = new BaseApi();
        $order_info_api = $base_api->getOrderInfo($order_number, $this->login_user_id);
        if ($order_info_api['code'] == 90001){
            abort(404);
        }
        $order_info = [];
        if (isset($order_info_api['data']) && !empty($order_info_api['data'])){
            $order_info = $order_info_api['data'];
            //订单状态
            $order_status_str = Base::getOrderStatus($order_info['order_status']);
            $order_info['order_status_str'] = isset($order_status_str['name'])?$order_status_str['name']:'';
            //币种
            $order_info['currency_code_str'] = Base::getCurrencyCodeStr($order_info['currency_code']);
        }
//        print_r($order_info);
        //将订单留言修改为已读
        $base_api->updateOrderMessageStatus(['order_id'=>$order_info['order_id'], "message_type"=>2,'statused'=>1]);
        //查找产品相关追踪号
        foreach ($order_info['item_data'] as &$i_val){
            $i_val['sku_qty'] = 0;
            $temp = [];
            $is_nocnoc = 0;
            if (isset($order_info['package_data'])){
                foreach ($order_info['package_data'] as $p_val){
                    foreach ($p_val['item_data'] as $pi_val){
                        if ($i_val['sku_id'] == $pi_val['sku_id']){
                            $i_val['sku_qty']+= $pi_val['sku_qty'];
                            $temp[] = $p_val['tracking_number'];
                        }
                    }
                }
            }
            if (strtoupper($i_val['shipping_model']) == 'NOCNOC'){
                $is_nocnoc = 1;
            }
            $i_val['is_nocnoc'] = $is_nocnoc;
            $i_val['tracking_number_data'] = $temp;
            $i_val['product_attr_desc'] = Base::handleOrderProductaAttrDesc($i_val['product_attr_desc']);
        }


        //价格调整
        $order_info['grand_total_new'] = $order_info['grand_total']+$order_info['adjust_price'];
        $order_info['captured_amount_usd_new'] = $order_info['captured_amount_usd'] + round($order_info['adjust_price']/$order_info['exchange_rate'], 2);

        //grand_total 实收总金额转换
        //$adjust_price = !empty($order_info['adjust_price'])?$order_info['adjust_price']:0;
        //$order_info['grand_total'] += $adjust_price;
        $this->assign('order_info',$order_info);
        $this->assign('ajax_url', json_encode([
            'async_adjustmentPrice'=>url('Orders/async_adjustmentPrice'),
            'async_updateOrderRemark'=>url('Orders/async_updateOrderRemark'),
            'async_addOrderMessage'=>url('Orders/async_addOrderMessage'),
        ]));
        $this->assign('title','订单详情');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
    }

    /**
     * 订单评价管理
     */
    public function evaluate(){

        /** 拼装查询条件 **/
        $where['store_id'] = $this->login_user_id;
        if (!empty(input('order_number'))) {
            $where['order_number'] = input('order_number');
        }
        //每页大小
        $where['page_size'] = input('page_size/d', 5);
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('Orders/evaluate', $p);

        $base_api = new BaseApi();
        $order_data_api = $base_api->getReviewsList($where);

        $order_data = isset($order_data_api['data']['data'])&&!empty($order_data_api['data']['data'])?$order_data_api['data']['data']:[];
        $page_html = isset($order_data_api['data']['Page'])&&!empty($order_data_api['data']['Page'])?$order_data_api['data']['Page']:'';

        foreach ($order_data as &$info){
            //产品信息
            $product_data = $base_api->getProductInfoByID($info['product_id']);
            $info['product_info'] = isset($product_data['data']) && !empty($product_data['data'])?$product_data['data']:[];
            //计算星级百分比
            $info['overall_rating_b'] = ($info['overall_rating']/5)*100;
            //剩余天数转换为“天” reply_surplus_time
            $info['reply_surplus_days'] = ceil($info['reply_surplus_time']/(24*60*60));
            //判断是否已回复
            if (!empty($info['reply'])){
                $info['is_replyed'] = 1;
            }else{
                $info['is_replyed'] = 0;
            }
        }
//
//        print_r($where);
//
//        print_r($order_data);
//        print_r($order_data_api);

        $this->assign('ajax_url',json_encode([
            'async_replayReview'=>url('Orders/async_replayReview'),
            'async_batchReplayReview'=>url('Orders/async_batchReplayReview'),
        ]));
        $this->assign('order_data',$order_data);
        $this->assign('page',$page_html);
        $this->assign('title','订单评价管理');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','evaluate');
        return $this->fetch();
    }

    /**
     * 订单物流详情
     */
    public function logisticsDetail(){
        $package_id = input('package_id');
        $order_id = input('order_id');
        if (
            empty($package_id) ||
            empty($order_id) || !is_numeric($order_id) || $order_id < 0
        ){
            $this->error('错误访问', url('Orders/all'));
        }
        //疑问：如果一个订单有多个物流单号则应该怎么处$detail['GetPackageTraceResult']->PackageList->Package
        //根据订单ID获取物流单号【或者通过get的形式传递物流单号，视情况而定】 TODO
        //$tracking_number = 'RE404099797SE';
        //根据物流编号，获取物流详情
        // $service = new WcfService();
        // $params = array(
        //     'GetPackageTrace' => array(
        //         'request' => array(
        //             'HasAll' => true,
        //             'TrackingNos' => array(
        //                 //RI256778026CN
        //                 'string'=>array(
        //                     $tracking_number,
        //                 )
        //             )
        //         )
        //     )
        // );

        $base_api = new BaseApi();
        $tracking_data['package_id'] = $package_id;
        $tracking_data['store_id'] = $this->login_user_id;
        $tracking_data['order_id'] = $order_id;
        $order_data_api = $base_api->LisLogisticsDetail($tracking_data);
        if($order_data_api['code'] == 200){
              $package_trace_list = json_decode($order_data_api['data']["raw_data"],true);
        }else{
              $package_trace_list = '';
        }
        // dump($package_trace_list);//exit;

        // $detail = $service->lisServiceSoap('GetPackageTrace', $params);dump($detail);
        // $package_trace_list = isset($detail['GetPackageTraceResult']->PackageList->Package) && !empty($detail['GetPackageTraceResult']->PackageList->Package)?$detail['GetPackageTraceResult']->PackageList->Package->PackageTraceList->PackageTrace:'';

        //获取订单信息
        $order_info = Order::getOrderInfoByOrderId($order_id,$tracking_data['store_id']);
        if(empty($order_info)){
          $this->error('错误访问', url('Orders/all'));
        }
        $this->assign('package_trace_list',$package_trace_list);
        $this->assign('order_info',$order_info);
        $this->assign('title','订单物流详情');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
    }

    /**
     * 退款&纠纷&&投诉
     */
    public function refundAll(){
        $base_api = new BaseApi();
        //tab类型：1-退换货管理，2-纠纷管理
        $tab_type = input('tab_type', 1);
        /** 其他搜索条件 **/
        $where['store_id'] = $this->login_user_id;
        /** 分页条件 start **/
        $where['page_size'] = input('page_size/d', 10);
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page' && $k != 'page_size'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('Orders/refundAll', $p, config('default_return_type'), true);
        /** 分页条件 end **/
        $where['create_on_start'] = !empty(input('create_on_start'))?strtotime(input('create_on_start')):null;
        $where['create_on_end'] = !empty(input('create_on_end'))?strtotime(input('create_on_end')):null;
        //退换货管理
        if ($tab_type == 1){
            $where['order_number'] = input('order_number');
            $where['after_sale_number'] = input('after_sale_number');

            $where['type'] = input('type');
            $where['status'] = input('status');
            $where['count_down_type'] = input('count_down_type');
            $where['is_platform_intervention'] = input('is_platform_intervention');
            $list = $base_api->getOrderRefundGetLists($where);
            $list_data = isset($list['data']['data'])&&!empty($list['data']['data'])?$list['data']['data']:[];
            $page_html = isset($list['data']['Page'])&&!empty($list['data']['Page'])?$list['data']['Page']:'';
            //获取订单售后类型
            $type = Base::getOrderAfterSaleType();
            //获取售后状态
            $status_api = $base_api->getAfterSaleStatus();
            $status = isset($status_api['data'])&&!empty($status_api['data'])?$status_api['data']:[];
            foreach ($list_data as &$info){
                //处理附件
                $info['imgs'] = json_decode(html_entity_decode($info['imgs']), true);
                //币种符号
                $_currency_code_str = '$';
                if(!empty($info['currency_code'])){
                    $_currency_code_str = Base::getCurrencyCodeStr($info['currency_code']);
                }
                $info['currency_code_str'] = $_currency_code_str;
            }
            //获取待处理倒计时
            $count_down_data = Base::getOrderAfterSaleCountDown();
            $this->assign('type', $type);
            $this->assign('status', $status);
            $this->assign('count_down_data', $count_down_data);
            $this->assign('page_html', $page_html);
            $this->assign('track17_url', config('track17_url'));
            $this->assign('list_data', $list_data);
            $this->assign('title','退换货管理');
        }elseif ($tab_type == 2){//纠纷管理
            $where['after_sale_type'] = input('after_sale_type');
            $where['complaint_status'] = input('complaint_status');
            $list = $base_api->getComplaintDataForPage($where);
            $list_data = isset($list['data']['data'])&&!empty($list['data']['data'])?$list['data']['data']:[];
            $page_html = isset($list['data']['Page'])&&!empty($list['data']['Page'])?$list['data']['Page']:'';
            //获取订单售后类型
            $type = Base::getOrderAfterSaleType();
            $complaint_status = Base::getOrderComplaintStatus();
            $this->assign('type', $type);
            $this->assign('complaint_status', $complaint_status);
            $this->assign('page_html', $page_html);
            $this->assign('list_data', $list_data);
        }
        $this->assign('tab_type',$tab_type);
        $this->assign('parent_menu','order');
        $this->assign('child_menu','orders-refund-all');
        $this->assign('url',json_encode([
            'Orders'=>url('Orders/export'),
        ]));
        return $this->fetch();
    }

    /**
     * 获取RAM订单post数据
     * @return \think\response\Json
     */
    public function async_getRamPostData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
        ){
            $base_api = new BaseApi();
            $res = $base_api->getRamPostData(['after_sale_id'=>$param['after_sale_id']]);
            if ($res['code'] == API_RETURN_SUCCESS){
                $data = $res['data'];
                /** 获取产品下的所有sku数据 start **/
                //获取产品ID
                $product_id_arr = [];
                foreach ($data as $info){
                    $product_id_arr[] = $info['product_id'];
                }
                //根据产品ID获取产品数据
                $p_res_api = $base_api->getPruductDataByIds(['product_id_arr'=>array_unique($product_id_arr)]);
                $p_res = $p_res_api['data'];
                //重新拼装产品数据至相应数据
                foreach ($data as &$val){
                    foreach ($p_res as $val_s){
                        if ($val['product_id'] == $val_s['_id']){
                            $val['sku_data'] = $val_s['Skus'];
                        }
                    }
                }
                /** 获取产品下的所有sku数据 end **/
                /** 重新组装返回数据格式 **/
                $r_data = [];
                foreach ($data as $v_info){
                    $tem = [];
                    $sku_data = [];
                    $tem['product_id'] = $v_info['product_id'];
                    $tem['product_nums'] = $v_info['product_nums'];
                    if (isset($v_info['sku_data'])){
                        foreach ($v_info['sku_data'] as $vv_in){
                            $tem_a = [];
                            $tem_a['_id'] = $vv_in['_id'];
                            $tem_a['Code'] = $vv_in['Code'];
                            $sku_data[] = $tem_a;
                        }
                    }
                    $tem['sku_data'] = $sku_data;
                    $r_data[] = $tem;
                }
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $r_data;
            }else{
                $rtn['msg'] = '操作失败 '.$res['msg'];
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 【售后订单】修改售后订单申请金额
     * @return \think\response\Json
     */
    public function async_editorRefundAllPrice(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            && isset($param['captured_refunded_fee']) && !empty($param['captured_refunded_fee'])
        ){
            $param['edit_time'] = time();
            $base_api = new BaseApi();
            $res = $base_api->updateApplyData($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '操作失败 '.$res['msg'];
                Log::record('async_editorRefundAllPrice->修改售后订单申请金额失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 【售后订单】确认申请
     * @return \think\response\Json
     */
    public function async_refundAllConfirmApply(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            &&isset($param['type']) && !empty($param['type'])
            &&isset($param['order_id']) && !empty($param['order_id'])
        ){
            $time = time();
            //售后类型（1换货，2退货 3退款）
            $type = $param['type'];
            $base_api = new BaseApi();
            //处理确认申请信息
            if ($type == 1 || $type == 2){
                //修改售后状态为“待买家发货”
                $up_param['status'] = 2;
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['edit_time'] = $time;
                $res = $base_api->updateApplyData($up_param);
                if ($res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '操作失败 '.$res['msg'];
                    Log::record('async_refundAllConfirmApply->确认申请失败1：'.print_r($res, true));
                }
            }elseif($type == 3){
                //进入平台自动退款，若成功则变为‘退款成功’，若失败则列表行该列不发生变化 TODO
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['order_id'] = $param['order_id'];

                //退款来源:1-seller售后退款；2-my退款；3-admin退款
                $up_param['refund_from'] = 1;
                //退款类型：1-陪保退款；2-售后退款；3-订单取消退款
                $up_param['refund_type'] = 2;
                //操作人类型：1-admin，2-seller，3-my
                $up_param['operator_type'] = 2;
                $up_param['operator_id'] = $this->login_user_id;
                $up_param['operator_name'] = $this->login_user_name;
                $up_param['reason'] = '退款确认申请退款';
                $up_param['create_by'] = "Seller,operator id:".$this->real_login_user_id.",operator name:".$this->real_login_user_name;

                $res = $base_api->refundOrder($up_param);
                if ($res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '退款失败 '.$res['msg'];
                    Log::record('async_refundAllConfirmApply->确认申请 退款失败'.print_r($res, true));
                }
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 【售后订单】拒绝申请
     * @return \think\response\Json
     */
    public function async_refundAllRefuseApply(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            && isset($param['content']) && !empty($param['content'])
        ){
            $param['title'] = '拒绝申请';
            //1买家 2卖家 3后台
            $param['user_type'] = 2;
            $param['user_id'] = $this->login_user_id;
            $param['user_name'] = $this->login_user_name;
            //处理附件
            if (isset($param['imgs']) && !empty($param['imgs'])){
                $param['imgs'] = json_encode($param['imgs']);
            }
            $time = time();
            $param['add_time'] = $time;
            $base_api = new BaseApi();
            //新增“订单售后申请操作记录”数据
            $res = $base_api->addApplyLogData($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                //将售后状态修改为“已拒绝申请” 7
                $up_param['status'] = 7;
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['edit_time'] = $time;
                $s_res = $base_api->updateApplyData($up_param);
                if ($s_res['code'] == API_RETURN_SUCCESS){
                    $rollback_data['after_sale_id'] = $param['after_sale_id'];
                    $rollback_data['create_ip'] = get_ip();
                    $rollback_data['create_by'] = "Seller User,ID:".$this->login_user_id.",Name:".$this->login_user_name;
                    $rollback = $base_api->rollbackApplyOrderStatus($rollback_data);
                    if(empty($rollback['code']) || $rollback['code'] != 200){
                        Log::record('async_refundAllConfirmApply->确认申请回退订单状态失败：rollback_data：'.json_encode($rollback_data));
                    }
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '状态操作失败 '.$res['msg'];
                    Log::record('async_refundAllConfirmApply->确认申请失败1：'.print_r($res, true));
                }
            }else{
                $rtn['msg'] = '数据添加失败 '.$res['msg'];
                Log::record('async_refundAllRefuseApply->【售后订单】拒绝申请失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 【售后订单】确认收货
     * @return \think\response\Json
     */
    public function async_refundAllConfirmCollectGoods(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            && isset($param['type']) && !empty($param['type'])
            && isset($param['order_id']) && !empty($param['order_id'])
        ){
            $time = time();
            $base_api = new BaseApi();
            //售后类型（1换货，2退货 3退款）
            $type = $param['type'];
            if ($type == 1){
                //将状态修改为“换货成功”
                $up_param['status'] = 4;
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['edit_time'] = $time;
                $res = $base_api->updateApplyData($up_param);
                if ($res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '操作失败 '.$res['msg'];
                    Log::record('async_refundAllConfirmApply->确认申请失败1：'.print_r($res, true));
                }
            }elseif ($type == 2){
                //平台将自动给买家退款，退款到账后，变为‘退款成功’5
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['order_id'] = $param['order_id'];

                //退款来源:1-seller售后退款；2-my退款；3-admin退款
                $up_param['refund_from'] = 1;
                //退款类型：1-陪保退款；2-售后退款；3-订单取消退款
                $up_param['refund_type'] = 2;
                //操作人类型：1-admin，2-seller，3-my
                $up_param['operator_type'] = 2;
                $up_param['operator_id'] = $this->login_user_id;
                $up_param['operator_name'] = $this->login_user_name;
                $up_param['reason'] = '退货确认收货退款';
                $up_param['create_by'] = "Seller,operator id:".$this->real_login_user_id.",operator name:".$this->real_login_user_name;

                $res = $base_api->refundOrder($up_param);
                if ($res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '退款失败 '.$res['msg'];
                    Log::record('async_refundAllConfirmApply->确认申请 退款失败'.print_r($res, true));
                }
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);

    }

    /**
     * 【售后订单】申请仲裁
     * @return \think\response\Json
     */
    public function async_refundAllArbitration(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            && isset($param['content']) && !empty($param['content'])
        ){
            $param['title'] = '申请仲裁';
            //1买家 2卖家 3后台
            $param['user_type'] = 2;
            //记录类型：0-不是仲裁，1-是仲裁
            $param['log_type'] = 1;
            $param['user_id'] = $this->login_user_id;
            $param['user_name'] = $this->login_user_name;
            //处理附件
            if (isset($param['imgs']) && !empty($param['imgs'])){
                $param['imgs'] = json_encode($param['imgs']);
            }
            $time = time();
            $param['add_time'] = $time;
            $base_api = new BaseApi();
            //新增“订单售后申请操作记录”数据
            $res = $base_api->addApplyLogData($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                //将售后状态修改为“仲裁处理中”，平台介入修改为“已介入”
                $up_param['status'] = 6;
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['is_platform_intervention'] = 1;
                $up_param['edit_time'] = $time;
                $s_res = $base_api->updateApplyData($up_param);
                if ($s_res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '状态操作失败 '.$res['msg'];
                    Log::record('async_refundAllArbitration->申请仲裁失败1：'.print_r($res, true));
                }
            }else{
                $rtn['msg'] = '数据添加失败 '.$res['msg'];
                Log::record('async_refundAllArbitration->【售后订单】申请仲裁失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);

    }

    /**
     * 【售后订单】提交RMA单
     * [
            'after_sale_id'=>4, //售后单号ID
            'order_id'=>10, //原订单表ID
            'price'=>20, //订单金额:订单金额为0时，订单状态直接变更为“待发货”;订单金额大于0时，订单状态变更为“待支付”
            'data'=>
            [
                [
                'product_id'=>10, //SPU id
                'sku_id'=>1, //SKU id
                'sku_code'=>1, //SKU 编码
                'sku_nums'=>4, //SKU 数量
                ],
                [
                'product_id'=>20,
                'sku_id'=>5,
                'sku_code'=>20,
                'sku_nums'=>3,
                ],
            ]
        ]
     * 提交成功后，原售后单关闭
     * @return \think\response\Json
     */
    public function async_refundAllSubmitRMA(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            && isset($param['order_id']) && !empty($param['order_id'])
            && isset($param['price'])
        ){
            $pro_data = $param['data'];
            //参数校验标识
            $flag = true;
            foreach ($pro_data as $info){
                if (
                    empty($info['product_id'])
                    || empty($info['sku_id'])
                    || empty($info['sku_code'])
                    || empty($info['sku_nums'])
                ){
                    $flag = false;
                    break;
                }
            }
            if ($flag){
                $base_api = new BaseApi();
                $res = $base_api->createRmaOrder($param);
                if ($res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '操作失败 '.$res['msg'];
                    Log::record('async_refundAllSubmitRMA->调用失败'.print_r($res, true));
                }
            }else{
                $rtn['msg'] = '参数不能为空';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 调整价格
     * @return \think\response\Json
     */
    public function async_adjustmentPrice(){
        $rtn = config('ajax_return_data');
        $param = input();
        if (
            isset($param['order_id']) && !empty($param['order_id']) && is_numeric($param['order_id'])
            &&isset($param['grand_total']) && !empty($param['grand_total'])
            &&isset($param['grand_total_changed']) && !empty($param['grand_total_changed'])
            &&isset($param['USD_captured_amount_changed']) && !empty($param['USD_captured_amount_changed'])
            &&isset($param['change_reason']) && !empty($param['change_reason'])
        ){
            $param['change_user_id'] = $this->login_user_id;
            $param['change_user_name'] = $this->login_user_name;
            $param['change_user_ip'] = ip2long(get_ip());
            $base_api = new BaseApi();
            $res = $base_api->updateOrderPrice($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '修改失败 '.$res['msg'];
                Log::record('async_adjustmentPrice->修改价格失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '参数错误';
        }
        return json($rtn);
    }

    /**
     * 调整收货时间【暂时不做】
     * @return \think\response\Json
     */
    public function async_adjustmentDeliveryTime(){
        $rtn = config('ajax_return_data');
        return json($rtn);
    }

    /**
     * 更新订单备注信息
     * @return \think\response\Json
     */
    public function async_updateOrderRemark(){
        $rtn = config('ajax_return_data');
        $param = input();
        $base_api = new BaseApi();
        $res = $base_api->updateOrderRemark($param);
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $res;
        }else{
            $rtn['msg'] = '修改失败 '.$res['msg'];
            Log::record('async_updateOrderRemark->修改订单备注失败'.print_r($res, true));
        }
        return json($rtn);
    }

    /**
     * 添加订单留言
     * @return \think\response\Json
     */
    public function async_addOrderMessage(){
        $rtn = config('ajax_return_data');
        $param = input();
        $param['message_type'] = 1;
        $param['statused'] = 1;
        $param['user_id'] = $this->login_user_id;
        $param['user_name'] = $this->login_user_name;
        $param['create_on'] = time();
        $base_api = new BaseApi();
        $res = $base_api->addOrderMessage($param);
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $res;
        }else{
            $rtn['msg'] = '添加失败 '.$res['msg'];
            Log::record('async_addOrderMessage->添加订单留言失败'.print_r($res, true));
        }
        return json($rtn);
    }

    /**
     * 回复评价
     * @return \think\response\Json
     */
    public function async_replayReview(){
        $rtn = config('ajax_return_data');
        $param = input();
        if (
            isset($param['review_id']) && !empty($param['review_id'])
            && isset($param['content']) && !empty($param['content'])
        ){
            $param['store_id'] = $this->login_user_id;
            $param['store_name'] = $this->login_user_name;
            $base_api = new BaseApi();
            $res = $base_api->addReplyReviews($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '回复失败 '.$res['msg'];
                Log::record('async_batchReplayReview->批量回复评价失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 回复评价
     * @return \think\response\Json
     */
    public function async_batchReplayReview(){
        $rtn = config('ajax_return_data');
        $param = input();
        Log::record('async_batchReplayReview'.print_r($param, true));
        if (
            isset($param['review_id']) && !empty($param['review_id'])
            && isset($param['content']) && !empty($param['content'])
        ){
            $flag = true;
            $base_api = new BaseApi();
            foreach ($param['review_id'] as $review_id){
                $data['store_id'] = $this->login_user_id;
                $data['store_name'] = $this->login_user_name;
                $data['review_id'] = $review_id;
                $data['content'] = $param['content'];
                $res = $base_api->addReplyReviews($data);
                if ($res['code'] != API_RETURN_SUCCESS){
                    $flag = false;
                    Log::record('async_batchReplayReview->批量批量回复评价失败：'.print_r($res, true));
                }
            }
            if ($flag){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '批量回复失败';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }
    /*
    * 退款信息导出
    */
    public function export()
    {
        $base_api = new BaseApi();
        /** 其他搜索条件 **/
        $where['store_id'] = $this->login_user_id;
        /** 分页条件 start **/
        $where['page_size'] = 10000;
        $input = input();
        /** 分页条件 end **/
        if(empty(input('create_on_start'))||empty(input('create_on_end'))){
            $this->error('时期不能为空,只能导出3个月的数据');
        }
        $where['create_on_start'] = !empty(input('create_on_start'))?strtotime(input('create_on_start')):null;
        $where['create_on_end'] = !empty(input('create_on_end'))?strtotime(input('create_on_end')):null;
        if($where['create_on_end']-$where['create_on_start']>7948800){
            $this->error('只能导出3个月的数据');
        }


        $where['order_number'] = input('order_number');
        $where['after_sale_number'] = input('after_sale_number');

        $where['type'] = input('type');
        $where['status'] = input('status');
        $where['count_down_type'] = input('count_down_type');
        $where['is_platform_intervention'] = input('is_platform_intervention');
        $list = $base_api->getOrderRefundLists($where);
        if(isset($list['data']['data'])&&!empty($list['data']['data'])){
            $list_data= $list['data']['data'];
        }else{
            $this->error('没有数据');
        }
        $da=[];

        foreach ($list_data as $item){
            $da[] = [
                'order_number' => ' '.$item['order_number'],
                'goods_total' => $item['goods_total'],
                'refunded_fee' => $item['refunded_fee'],
                'currency_code' => $item['currency_code'],
                'country' => $item['country'].'('.$item['country_code'].')',
                'store_id' => $item['store_id'],
                'remarks' => $item['remarks'],
                'customer_name' => $item['customer_name'],
                'add_time' => $item['add_time'] ? date('Y-m-d', $item['add_time']) : '',
            ];
        }

        $title = ['订单号', '订单总额', '退款金额', '退款币种', '国家',
            '卖家账号', '退款备注', '退款人', '退款日期'
        ];
        array_unshift($da, $title);
        //var_dump($da);die;
        $Phpexcel = new Phpexcel();
        $filename = '退款订单';
        $objwriter = $Phpexcel->create($da, $filename);
        $objwriter->save('php://output');
    }

    /*订单下载*/
    public function orderDownload(){
        /** 其他搜索条件 **/
        $where['store_id'] = $this->login_user_id;
        $product_name = input('product_name');
        $customer_name = input('customer_name');
        /*是否下载*/
        $where['is_page'] = $is_page = input("is_page",1);
        if(!empty($product_name)){
            if(is_numeric($product_name)){
                $where['product_id'] = $product_name;
            }else{
                $where['product_name'] = $product_name;
            }
        }

        if(!empty($customer_name)){
            if(is_numeric($customer_name)){
                $where['customer_id'] = $customer_name;
            }else{
                $where['customer_name'] = $customer_name;
            }
        }
        $where['order_status'] = input('order_status');
        $where['sku_num'] = input('sku_num');
        $where['create_on_start'] = input("create_on_start");
        $where['create_on_end'] = input("create_on_end");
        $where['fulfillment_status'] = input("fulfillment_status");
        /** 分页条件 end **/
        $base_api = new BaseApi();
        if($is_page == 1){
            /** 分页条件 start **/
            $where['page_size'] = input('page_size/d', 10);
            $where['page'] = input('page/d', 1);
            $where['path'] = url('Orders/orderDownload');
            $where['query'] = input();
            $order_data_api  = $base_api->sellerDownloadOrder($where);
        }else{
            $where['is_page'] = 0;
            $list  = $base_api->sellerDownloadOrder($where);
            if(isset($list['data']) && !empty($list['data'])){
                $list_data= $list['data'];
            }else{
                $this->error('没有数据');
            }
            $da=[];

            foreach ($list_data as $val){
                $da[] = [
                        'Shipping'=>'SK POST',
                        'Service'=>'Tracking',
                        'Code1'=>'',
                        'Account'=>'',
                        'Date'=>'',
                        'Name'=>$val['first_name'].' '.$val['last_name'],
                        'FromEmailAddress'=>'',
                        'TransactionID'=>$val['order_number']."01",
                        'ShippingAddress'=>'',
                        'AddressLine1'=>"",
                        'AddressLine2'=>$val['street1'].(!empty($val['street2'])?",".$val['street2']:''),
                        'City'=>$val['city'],
                        'Province'=>$val['state'],
                        'PostalCode'=>$val['postal_code'],
                        'ContactPhoneNumber'=>$val['mobile'],
                        'Country'=>$val['country'],
                        'CreateDate'=>date('Y-m-d H:i:s',$val['create_on']),
                        'ItemTitle1'=>$val['sku_num'],
                        'Description'=>$val['product_name'],
                        'ItemID'=>'',
                        'AuctionSite'=>'',
                        'BuyerID'=>'',
                        'Quantity1'=>$val['product_nums'],
                        'CurrencyCode'=>"USD",
                        'OrderLineCostPrice'=>'',
                        'OrderLineValue'=>sprintf("%.2f",$val['captured_price_usd']*$val['product_nums']),
                        'HasTrackingNumber'=>!empty($val['tracking_number'])?'True':'False',
                        'ShippingMethod'=>$val['shipping_model'],
                        'AllowSplit'=>'True',
                        'CarrierID'=>'',
                        'SiteID'=>1,
                        'OriginOrderNubmer'=>'',
                        'Notes'=>''
                ];
            }

            $title = ['Shipping', 'Service', 'Code1', 'Account', 'Date', 'Name', 'From Email Address', 'Transaction ID', 'Shipping Address',
                'Address Line 1','Address Line 2','Town/City','State/Province','Zip/Postal Code','Contact Phone Number','Country','CreateDate',
                'Item Title1','Description','Item ID','Auction Site','Buyer ID','Quantity1','Currency Code','OrderLine CostPrice (RMB)','OrderLine Value',
                'HasTrackingNumber','ShippingMethod','AllowSplit','CarrierID','SiteID','OriginOrderNubmer','Notes'];
            array_unshift($da, $title);
            $Phpexcel = new Phpexcel();
            $filename = 'Warehouse';
            Log::write("orderDownload,user_id:".$this->real_login_user_id.",where:".json_encode($where).",data:".json_encode($da));
            $objwriter = $Phpexcel->create($da, $filename);
            $path = config('order_download_excel_dir').DS.date("Ymd");
            if(!file_exists($path)){//检测文
                mkdir($path, 0777 , true );
            }
            $objwriter->save($path.DS.$filename.'-'.$this->login_user_id.'-'.time().'.xls');
            $objwriter->save('php://output');exit;
        }
        $FulfillmentStatus = Base::getConfigStatus("FulfillmentStatus");
        $FulfillmentStatusData = array();
        if(!empty($FulfillmentStatus)){
            foreach ($FulfillmentStatus as $key=>$value){
                $FulfillmentStatusData[$value['code']] = $value['name'];
            }
        }
        $list = isset($order_data_api['data']['data'])?$order_data_api['data']['data']:'';
        $page = isset($order_data_api['data']['Page'])?$order_data_api['data']['Page']:'';
        $this->assign('list',$list);
        $this->assign('page',$page);
        $this->assign('FulfillmentStatus',$FulfillmentStatus);
        $this->assign('FulfillmentStatusData',$FulfillmentStatusData);
        $this->assign('title','订单下载');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','orderDownload');
        return $this->fetch();
    }

    /**
     * 订单上传
     * add by 20190701 kevin
     */
    public function orderUpload(){
        /** 其他搜索条件 **/
        $where['store_id'] = $this->login_user_id;
        $login_user_name = $this->login_user_name;
        if(request()->isPost()){
            $ship_order_data = input("ship_order_data");
            $ship_order_data_res = array();
            /*处理传入的字符串*/
            if(!empty($ship_order_data)){
                $order_data_string = trim(str_replace(["\r","\r\n","\n"],["&","&","&"],$ship_order_data));
                $order_data1 = explode("&",$order_data_string);
                if(!empty($order_data1)){
                    foreach ($order_data1 as $key=>&$value){
                        $value = trim(str_replace([";","；"],["&"],$value));
                        if(!empty($value)){
                            $ship_order_data_res[] = explode("&",$value);
                        }
                    }
                }
            }
            if(!empty($ship_order_data_res)){
                $html = "";
                foreach ($ship_order_data_res as $key=>$value){
                    $tracking_number_data = array();
                    $type = 3;
                    $tracking_number_data['seller_id'] = $this->login_user_id;
                    $tracking_number_data['type'] = $type;
                    $tracking_number_data['order_number'] = substr($value[0],0,18);
                    $tracking_number_data['data'][0]['tracking_number'] = $value[0];
                    $tracking_number_data['data'][0]['shipping_channel_name'] = "SK POST";
                    $tracking_number_data['data'][0]['shipping_channel_name_cn'] = "蛇口仓物流";
                    $tracking_number_data['data'][0]['item_info'] = array();
                    if(!empty($value[4])){
                        $sku_str_data = explode(",",$value[4]);
                        if(!empty($sku_str_data)){
                            foreach ($sku_str_data as $k=>$v){
                                $sku_data = explode(":",$v);
                                if(!empty($sku_data[0]) && !empty($sku_data[1])){
                                    $tracking_number_data['data'][0]['item_info'][$k]['sku_id'] = $sku_data[0];
                                    $tracking_number_data['data'][0]['item_info'][$k]['sku_qty'] = $sku_data[1];
                                }
                            }
                        }
                    }
                    if(!empty($tracking_number_data['order_number']) && !empty($tracking_number_data['data'][0]) && !empty($tracking_number_data['data'][0]['tracking_number']) && !empty($tracking_number_data['data'][0]['item_info'])){
                        $base_api = new BaseApi();
                        $res = $base_api->sellerUploadOrder($tracking_number_data);
                        $res['msg'] = $tracking_number_data['order_number'].",".$res['msg'];
                        if($res['code']==200){
                            $html .="<p style='color: green'>".$res['msg']."</p>";
                        }else{
                            $html .="<p style='color: red'>".$res['msg']."</p>";
                        }
                    }else{
                        $res = ['code'=>1001,'msg'=>$tracking_number_data['order_number'].",订单数据有误，data：".json_encode($value)];
                        $html .="<p style='color: red'>".$res['msg']."</p>";
                    }
                }
                return ['code'=>200,"msg"=>$html];
            }else{
                $html ="<p style='color: red'>导入订单数据为空或者有误</p>";
                return ['code'=>1001,'msg'=>$html];
            }

        }
        $this->assign('login_user_name',$login_user_name);
        $this->assign('title','订单上传');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','orderUpload');
        return $this->fetch();
    }
}
