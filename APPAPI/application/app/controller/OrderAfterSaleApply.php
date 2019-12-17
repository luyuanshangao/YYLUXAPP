<?php
/**
 * 用户售后申请控制器
 * Created by PhpStorm.
 * User: Kevin
 * Date: 2018/3/8
 * Time: 16:55
 */
namespace app\app\controller;

use app\app\dxcommon\BaseApi;
use app\common\controller\FTPUpload;
use app\common\params\OrderAfterSaleApplyParams;
use think\Log;
use \think\Session;
use \think\Cookie;
use \think\Request;
use vendor\aes\aes;
use TCPDF;
use app\common\controller\AppBase;
class OrderAfterSaleApply  extends AppBase
{
    /**
     * 售后订单列表
     * @return mixed
     */
    public function index()
    {
        //获取售后订单相关配置
        $baseApi = new BaseApi();
        $after_config_api = $baseApi->getAfterSaleConfig();

        $after_config = (isset($after_config_api['data']) && !empty($after_config_api['data']))?$after_config_api['data']:[];
        /** 参数校验 20181127 start **/
        $_type = input("type");
//        $_status = input("status");
        $_order_number = input("order_number");
        $_create_on_start = input("create_on_start");
        $_create_on_end = input("create_on_end");
        $cstomer['ID']= input("customer_id");
        //type参数校验，只能在指定范围内
        if (!empty($_type)){
            $config_flag = false;
            foreach ($after_config as $v1){
                if ($_type == $v1['code']){
                    $config_flag = true;
                    break;
                }
            }
            if (!$config_flag){
                $_type = '';
            }
        }
        //时间校验：1、开始时间不能低于1970年01月01日08时00分00秒；2、不能低于现在；3、相隔时间不能大于30天
        $_time_verify_res = time_verify_real($_create_on_start, $_create_on_end);
        $_create_on_start = $_time_verify_res['create_on_start'];
        $_create_on_end = $_time_verify_res['create_on_end'];
        /** 参数校验 20181127 end **/
        $param['customer_id'] = $cstomer['ID'];
        $param['type'] = $_type;
        //$param['status'] = $_status;
        $param['order_number'] = $_order_number;
        $param['create_on_start'] = $_create_on_start;
        $param['create_on_end'] = $_create_on_end;

        $param['page_size'] = (int)input('page_size',20);
        $param['page'] = (int)input('page',1);
        $param['path'] = url("OrderAfterSaleApply/index");
        $param = array_filter($param);

        $data = $baseApi->getOrderAfterSaleApplyList($param);

        $OrderRefundsType = $baseApi->getSysCofig('OrderRefundsType');
        $data['data']['after_config']=$after_config;
        $data['data']['OrderRefundsType']=$OrderRefundsType['data'];

        return json($data);
    }

    /*
     * 审核是否统一退款
     * */
    public function approved_refund(){
        $param['after_sale_id'] = input("after_sale_id");
        $param['status'] = input('status');
        $cstomer['ID']= input("customer_id");
        $cstomer['UserName']= input("UserName");
        $param['customer_id'] = $cstomer['ID'];
        $param['order_id'] = input("order_id");
        $baseApi = new BaseApi();
        $rtn = config('ajax_return_data');
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            &&isset($param['status']) && !empty($param['status'])
        ){
            if($param['status'] == 7){
                $up_param['status'] = $param['status'];
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['edit_time'] = time();
                $res = $baseApi->updateApplyData($up_param);
                return $res;
            }else{
                $time = time();
                //售后类型（1换货，2退货 3退款）
                $type = 3;
                $base_api = new BaseApi();
                //处理确认申请信息
                //进入平台自动退款，若成功则变为‘退款成功’，若失败则列表行该列不发生变化 TODO
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['order_id'] = $param['order_id'];
                //退款来源:1-seller售后退款；2-my退款；3-admin退款
                $up_param['refund_from'] = 2;
                //退款类型：1-陪保退款；2-售后退款；3-订单取消退款
                $up_param['refund_type'] = 2;
                //操作人类型：1-admin，2-seller，3-my
                $up_param['operator_type'] = 3;
                $up_param['operator_id'] = $cstomer['ID'];
                $up_param['operator_name'] = $cstomer['UserName'];
                $up_param['reason'] = '退款确认申请退款';
                $res = $base_api->backrefundOrder($up_param);
                if ($res['code'] == API_RETURN_SUCCESS){
                    $rtn['code'] = 200;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '退款失败 '.$res['msg'];
                    Log::record('async_refundAllConfirmApply->确认申请 退款失败'.print_r($res, true));
                }
            }
        }
        return json($rtn);
    }

    /**
     * 售后数据详情
     * @return mixed
     */
    public function afterSaleApplyDetails(){
        //$after_sale_id = input("after_sale_id");
        $order_id= input("order_id");
        $cstomer['ID']= input("customer_id");
        if(empty($order_id)) {
            echo "error";exit;
        }
        $baseApi = new BaseApi();
        /*
             $param['after_sale_id'] = $after_sale_id;
             $data = $baseApi->getOrderAfterSaleApplyInfo($param);
             $data = (isset($data['data']) && !empty($data['data']))?$data['data']:[];
            var_dump($data);
             //计算总额

                   $total_price = 0;
                   if (isset($data['item']) && !empty($data['item'])){
                       foreach ($data['item'] as $item) {
                           $total_price += $item['product_price']*$item['product_nums'];
                       }
                   }
                   $data['total_price'] = round($total_price, 2);*/
        //var_dump(1221);die();
        $customer_id = $cstomer['ID'];
        //获取订单详情
        $order_info_api = $baseApi->getOrderInfo(['order_id'=>$order_id,'customer_id'=>$customer_id]);
        $order_info = (isset($order_info_api['data']) && !empty($order_info_api['data']))?$order_info_api['data']:[];
        if(!empty($order_info['order_id'])){
            $data['order_id']=$order_info['order_id'];
            $data['order_number']=$order_info['order_number'];
            $data['shipping_address']=$order_info['shipping_address'];
            foreach($order_info['item'] as &$value){
                $value['tracking_number']='';
            }
            $data['item']=$order_info['item'];
        }


        return [
            'code'=>200,
            'data'=>$data,
           // 'data'=>$data,
           // 'cstomer_arbitration_info'=>$cstomer_arbitration_info,
            ];

    }

    /**
     * 售后日志
     * @return mixed
     */
    public function afterSaleApplyLog(){
        $after_sale_id = input("after_sale_id");
        $cstomer['ID']= input("customer_id");
        if(empty($after_sale_id)) {
            echo "error";exit;
        }
        $baseApi = new BaseApi();
        $param['after_sale_id'] = $after_sale_id;
        $data = $baseApi->getOrderAfterSaleApplyInfo($param);
        $data = (isset($data['data']) && !empty($data['data']))?$data['data']:[];
        if(!empty($data['add_time'])){
            $log['title']=$data['remarks'];
            if(!empty($data['imgs'])){
                $imgs1=ltrim($data['imgs'],'["');
                $imgs2=rtrim($imgs1, '"]');
            }else{
                $imgs2='';
            }
            $log['img']=$imgs2;
            $log['time']=$data['add_time'];
        }else{
            $log=[];
        }

        $da[]=$log;

        return [
            'code'=>200,
            'data'=>$da,
            'msg'=>''
        ];

    }
    /**
     * 申请售后订单数据
     * @return mixed
     */
    public function afterSaleApply(){
        $order_number = input("o_number");
        $base_api = new BaseApi();
        $customer_id= input("customer_id");
        //获取订单详情
        $order_info_api = $base_api->getOrderInfo(['order_number'=>$order_number,'customer_id'=>$customer_id]);
        $order_info = (isset($order_info_api['data']) && !empty($order_info_api['data']))?$order_info_api['data']:[];
        //获取售后订单相关配置
        $after_config_api = $base_api->getAfterSaleConfig();
        $after_config = (isset($after_config_api['data']) && !empty($after_config_api['data']))?$after_config_api['data']:[];
//        print_r($after_config);
//        print_r($order_info);
        //判断是否是申请退款
        $after_config_new = [];
        $is_only_refund = 0;
        if ($order_info['order_status'] <= 400){
            foreach ($after_config as $key=>$info){
                //退款的类型
                if ($info['code'] == 3){
                    foreach ($info['refunded_type'] as $k=>&$val){
                        //去掉非仅退款类型
                        if ($val['code'] !=1){
                            unset($info['refunded_type'][$k]);
                        }
                    }
                    $after_config_new[] = $info;
                    break;
                }
            }
            $is_only_refund = 1;
        }
        if (!empty($after_config_new)){
            $after_config = $after_config_new;
        }

        return [
            'code'=>200,
            'data'=>$after_config,
            'msg'=>'',
        ];

    }

    /**
     * 仲裁管理
     * @return mixed
     */
    public function arbitration(){
        $after_sale_id = input("after_sale_id");
        if(empty($after_sale_id)) {
            echo "error";exit;
        }
        $baseApi = new BaseApi();
        $param['after_sale_id'] = $after_sale_id;
        $data = $baseApi->getOrderAfterSaleApplyInfo($param);
        $data = (isset($data['data']) && !empty($data['data']))?$data['data']:[];
        //计算总额
        $total_price = 0;
        if (isset($data['item']) && !empty($data['item'])){
            foreach ($data['item'] as $item) {
                $total_price += $item['product_price']*$item['product_nums'];
            }
        }
        $data['total_price'] = round($total_price, 2);
        $cstomer = session("cstomer");
        $customer_id = $cstomer['ID'];
        //获取订单详情
        $order_info_api = $baseApi->getOrderInfo(['order_id'=>$data['order_id'],'customer_id'=>$customer_id]);
        $order_info = (isset($order_info_api['data']) && !empty($order_info_api['data']))?$order_info_api['data']:[];

        //判断是否已经申请仲裁
        $have_arbitration = 0;
        $cstomer_arbitration_info = [];//买家申请的仲裁数据
        foreach ($data['log'] as &$log){
            $log['imgs'] = isset($log['imgs']) && !empty($log['imgs'])?json_decode(htmlspecialchars_decode($log['imgs']), true):[];
            //log_type：记录类型：0-不是仲裁，1-是仲裁；user_type:1买家 2卖家 3后台
            if ($log['log_type'] == 1 && $log['user_type'] == 1 ){
                $have_arbitration = 1;
                $cstomer_arbitration_info = $log;
            }
        }
//        print_r($cstomer_arbitration_info);
//        print_r($data);
        $this->assign([
            'order_info'=>$order_info,
            'data'=>$data,
            'have_arbitration'=>$have_arbitration,
            'cstomer_arbitration_info'=>$cstomer_arbitration_info,
            'ajax_url'=>json_encode([
                'uploadImgs'=>url('OrderAfterSaleApply/uploadImgs'),
                'async_cancelArbitration'=>url('OrderAfterSaleApply/async_cancelArbitration'),
                'async_refundAllArbitration'=>url('OrderAfterSaleApply/async_refundAllArbitration')
            ]),
        ]);
        return $this->fetch();
    }

    /**
     * 取消仲裁
     * Cancellation of arbitration and closing of after sale
     * @return \think\response\Json
     */
    public function async_cancelArbitration(){
        $rtn = ['msg'=>'', 'code'=>100];
        $params = input();
        //数据校验
        $validate = $this->validate($params,(new OrderAfterSaleApplyParams())->async_cancelArbitrationRules());
        if(true !== $validate){
            $rtn['msg'] = $validate;
            return json($rtn);
        }
        $cstomer = session("cstomer");
        $params['log_type'] = 1;//记录类型：0-不是仲裁，1-是仲裁
        $params['title'] = 'Cancellation of arbitration';
        $params['user_type'] = 1;//1买家 2卖家 3后台
        $params['user_id'] = $cstomer['ID'];
        $params['user_name'] = $cstomer['UserName'];
        $params['content'] = 'Cancellation of arbitration and closing of after sale.';
        $base_api = new BaseApi();
        $res = $base_api->cancelArbitration($params);
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 200;
            $rtn['msg'] = 'Success';
        }else{
            $rtn['msg'] = 'Failure '.$res['msg'];
        }
        return json($rtn);
    }

    /**
     * 提交售后申请数据
     * @return \think\response\Json
     * [
     *  'order_id'=>,
     *  'order_number'=>,
     *  'customer_id'=>, //后端拼
     *  'customer_name'=>, //后端拼
     *  'store_id'=>,
     *  'store_name'=>,
     *  'payment_txn_id'=>,
     *  'type'=>,
     *  'refunded_type'=>,
     *  'after_sale_reason'=>,
     *  'imgs'=>, //申请图片，json数组保存
     *  'remarks'=>, //描述
     *  'refunded_fee'=>, //退款金额（退款时有）-后端拼，根据选择的产品
     *  'captured_refunded_fee'=>, //实际退款金额（退款时有）-后端拼，根据选择的产品
     *  'item'=>[
     *              [
     *                  'product_id'=>,
     *                  'sku_id'=>,
     *                  'sku_num'=>, //产品表sku编码
     *                  'product_name'=>,
     *                  'product_img'=>,
     *                  'product_attr_ids'=>,
     *                  'product_attr_desc'=>,
     *                  'product_nums'=>, //商品（sku）数量
     *                  'product_price'=>, //商品售价（sku单价）
     *              ],
     *      ],
     * ]
     */
    public function async_submitApply(){
        $rtn = ['msg'=>'', 'code'=>100];
        //$param = input();
        $param =$_POST;
        //return $param['item'];
        Log::record('async_submitApply'.json_encode($param));
        //数据校验
        $validate = $this->validate($param,(new OrderAfterSaleApplyParams())->async_submitApplyRules());
        if(true !== $validate){
            $rtn['msg'] = $validate;
            $rtn['data'] = '';
            return apiJosn($rtn);
        }
        //20181208 解决提交售后申请没有产品数据问题
        if (empty($param['item']) ){
            $rtn['msg'] = 'Product data error.';
            return apiJosn($rtn);
        }
        //var_dump($param);die;
        $param['item']=json_decode(htmlspecialchars_decode($param['item']),true);
        //return $param['item'];
        Log::record('async_submitApply'.json_encode($param));
        $base_api = new BaseApi();
        //item数据校验
        if(is_array($param['item'])){
            foreach ($param['item'] as $key=>$info){
                $new_info=[];
                //Log::record('$info'.json_encode($info));
                $validate_itemt = $this->validate($info,(new OrderAfterSaleApplyParams())->async_submitApplyItemsRules());
                if(true !== $validate_itemt){
                    $rtn['msg'] = $validate_itemt;
                    return apiJosn($rtn);
                }
                $new_info['product_id']=$info['product_id'];
                $new_info['sku_id']=$info['sku_id'];
                $new_info['sku_num']=$info['sku_num'];
                $new_info['product_name']=$info['product_name'];
                $new_info['product_img']=$info['product_img'];
                $new_info['product_attr_ids']=$info['product_attr_ids'];
                $new_info['product_attr_desc']=$info['product_attr_desc'];
                $new_info['product_nums']=$info['product_nums'];
                $new_info['product_price']=$info['product_price'];
                $param['item'][$key]=$new_info;
            }
        }else{
            $rtn['code'] = 1001;
            $rtn['msg'] = 'item error';
            $rtn['data'] = [];
            return apiJosn($rtn);
        }
        
        $item_count = count($param['item']);

        $OrderBasics = $base_api->getOrderBasics(['order_number'=>$param['order_number']],$param['customer_id']);

        /** 因为将order_id修改为order_number导致出错问题修复 BY tinghu.liu IN 20190123  **/
        $order_id = isset($OrderBasics['data']['order_id'])?$OrderBasics['data']['order_id']:0;
        $order_item = $base_api->getOrderItem(['order_id'=>$order_id,'customer_id'=>$param['customer_id']]);

        if (
            !isset($OrderBasics['code'])
            || $OrderBasics['code'] != 200
            || !isset($order_item['code'])
            || $order_item['code'] != 200
            || $order_id == 0
        ){
            $rtn['msg'] = 'Submission failure, please try again.';
        }else{
            $param['order_id'] = $order_id;
            $order_item_count = 0;
            if(isset($order_item['data']) && !empty($order_item['data'])){
                $order_item_count = count($order_item['data']);
            }
            if($order_item_count>$item_count){
                //价格累加
                $price_count = 0;
                foreach ($param['item'] as $val){
                    $price_count += $val['product_price'] * $val['product_nums'];
                }
                $param['refunded_fee'] = $param['captured_refunded_fee'] = $price_count;
            }else{
                $param['refunded_fee'] = $param['captured_refunded_fee'] = $OrderBasics['data']['captured_amount'];
            }
            $param['create_ip'] = GetIp();

            $res = $base_api->saveOrderAfterSaleApply($param);
            if ($res['code'] == 200){
                $rtn['code'] = 200;
                $rtn['data'] = url('OrderAfterSaleApply/index');
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = 'Submission failure, '.$res['msg'];
                $rtn['data'] = '';
            }
        }
        return apiJosn($rtn);
    }

    /**
     * 申请仲裁
     * @return \think\response\Json
     * [
     * 'after_sale_id'=>,
     * 'content'=>,
     * 'imgs'=>,
     * ]
     */
    public function async_refundAllArbitration(){
        $rtn = ['msg'=>'', 'code'=>100];
        $rtn['msg'] = 'failure';
        $paramData = input();
        $param = $paramData['data'];
        if (
            isset($param['after_sale_id']) && !empty($param['after_sale_id'])
            && isset($param['content']) && !empty($param['content'])
        ){
            $param['title'] = '申请仲裁';
            //1买家 2卖家 3后台
            $param['user_type'] = 1;
            //记录类型：0-不是仲裁，1-是仲裁
            $param['log_type'] = 1;
            $cstomer = session("cstomer");
            $param['user_id'] = $cstomer['ID'];
            $param['user_name'] = $cstomer['UserName'];
            //处理附件
            if (isset($param['imgs']) && !empty($param['imgs'])){
                $param['imgs'] = json_encode($param['imgs']);
            }
            $time = time();
            $base_api = new BaseApi();
            //新增“订单售后申请操作记录”数据
            $res = $base_api->addApplyLogData($param);
            if ($res['code'] == 200){
                //将售后状态修改为“仲裁处理中”，平台介入修改为“已介入”
                $up_param['status'] = 6;
                $up_param['after_sale_id'] = $param['after_sale_id'];
                $up_param['is_platform_intervention'] = 1;
                $up_param['edit_time'] = $time;
                $s_res = $base_api->updateApplyData($up_param);
                if ($s_res['code'] == 200){
                    $rtn['code'] = 200;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = 'Operation Failed '.$s_res['msg'];
                    Log::record('async_refundAllArbitration->申请仲裁失败1：'.print_r($res, true));
                }
            }else{
                $rtn['msg'] = 'Add data failure '.$res['msg'];
                Log::record('async_refundAllArbitration->【售后订单】申请仲裁失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = 'The request parameter can not null.';
        }
        return json($rtn);
    }

    /**
     * 增加退货的物流信息数据
     * @return \think\response\Json
     * [
     *   'after_sale_id'=>,
     *   'expressage_company'=>,
     *   'expressage_num'=>,
     *   'expressage_fee'=>,
     *   'phone'=>,
     *   'explain'=>,
     *   'imgs'=>,
     * ]
     */
    public function async_addReturnProductExpressage(){
        $rtn = ['msg'=>'', 'code'=>100];
        $rtn['msg'] = 'failure';
        $param = input();
        Log::record('async_addReturnProductExpressage->params:'.print_r($param, true));
        $base_api = new BaseApi();
        $res = $base_api->addReturnProductExpressage($param);
        if ($res['code'] == 200){
            $rtn['code'] = 200;
            $rtn['msg'] = 'success';
        }else{

            $rtn['msg'] = 'Add data failure '.$res['msg'];
            Log::record('async_addReturnProductExpressage->增加退货的物流信息数据失败'.print_r($res, true));
        }
        return json($rtn);
    }

    /**
     * 上传图片
     * 图片路径：orders/afterSale/
     */
    public function uploadImgs(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = $this->localUpload();
        $ftp_config = config('ftp_config');
        if($localres['code']==200){
            $remotePath = $ftp_config['UPLOAD_DIR']['ORDER_AFTER_SALE_IMAGES'].date("Ymd");
            $config = [
                'dirPath'=>$remotePath, // ftp保存目录
                'romote_file'=>$localres['FileName'], // 保存文件的名称
                'local_file'=>$localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            if($upload){
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['data'] = $remotePath.'/'.$localres['FileName'];
            }else{
                unlink($localres['url']);
                $res['code'] = 100;
                $res['data'] ='';
                $res['msg'] = "Remote Upload Fail";
            }
            return json($res);
        }
    }

    /**
     * 本地上传图片
     * @return mixed
     */
    public function localUpload(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $path = "public".DS . 'uploads';
            $upload_path = ROOT_PATH . $path;
            $info = $file->move($upload_path);
            if($info){
                $file_path= 'uploads'. DS .$info->getSaveName();
                $res['code'] = 200;
                $res['msg'] = "上传成功";
                $res['url'] = $file_path;
                $res['FileName'] = $info->getFilename();
                return $res;
            } else {
                //上传失败获取错误信息
                $res['code'] = 100;
                $res['msg'] = $file->getError();
                return $res;
            }
        }else{
            $res['code'] = 100;
            $res['msg'] = "上传图片超过尺寸";
            return $res;
        }
    }


    /*
     * 下载退换货地址
     * */
    public function downloadReturnAddress(){
        $base_api = new BaseApi();
        $after_sale_id = input("after_sale_id");
        $OrderAfterSaleApplyInfo = $base_api->getOrderAfterSaleApplyInfo(['after_sale_id'=>$after_sale_id]);
        $ReturnProductExpressage = $base_api->getReturnProductExpressage(['after_sale_id'=>$after_sale_id]);
        //$imgurl = url("OrderAfterSaleApply/barcode_create",['barcode'=>$OrderAfterSaleApplyInfo['data']['after_sale_number']],'',true);
        //dump($imgurl);exit;
        if(isset($OrderAfterSaleApplyInfo['data']['shipping_address']['country_code'])){
            $CountryToRMAAddress = $this->getCountryToRMAAddress($OrderAfterSaleApplyInfo['data']['shipping_address']['country_code']);
            $item_html = "";
            $i = 1;
            foreach ($OrderAfterSaleApplyInfo['data']['item'] as $key=>$value){
                $item_html.='<tr align="center">
                    <td>'.$i.'</td>
                    <td valign="top">
                    '.$value['product_name'].'
                    </td>
                    <td>'.$value['sku_num'].'</td>
                    <td>'.$value['product_nums'].'</td>
                </tr>';
                $i++;
            }
        }else{
            $this->error("error");
        }
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('luo dao');
        $pdf->SetTitle('Return Address');
        /*$pdf->SetSubject('TCPDF Tutorial');
        $pdf->SetKeywords('TCPDF, PDF, example, test, guide');*/
        //$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 001', PDF_HEADER_STRING, array(0,64,255), array(0,64,128));
        /*$pdf->setFooterData(array(0,64,0), array(0,64,128));
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));*/
        //$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        //$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        /*$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);*/
        if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
            require_once(dirname(__FILE__).'/lang/eng.php');
            $pdf->setLanguageArray($l);
        }
        //$pdf->setFontSubsetting(true);
        $pdf->SetFont('dejavusans', '', 1, '', true);
        $pdf->AddPage();
        //$pdf->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));
        $html = <<<EOD
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
        <style type="text/css">
            body {
                background: #eeeeee;
            }
            div{
                margin: 0;
                padding: 0;
            }
            p{
                margin: 0;
                padding: 0;
            }
            img{
                border: none;
            }
        </style>
    </head>
<body style="background: #eeeeee; padding: 0px;margin:0;">
    <div style="margin: 0 auto; padding: 10px 26px 14px 30px;background-color: #ffffff;width: 1460px;color: #000000;">
        <p style="margin: 0px auto;padding: 0;width: 100%;font-size: 12px;line-height:12px;color: #333333;">The label need to be sticked outside the package......</p>
        <table cellpadding="0" cellspacing="0" border="1" style="font-family:Arial, Helvetica, sans-serif; color:#000000; margin: 0 auto;font-size: 18px;border-color:#000000;" bgcolor="#ffffff">
            <tbody>
                <tr>
                    <td align="left"> 
                        TO:<br><span style="display: inline-block;vertical-align: top;">
                            {$CountryToRMAAddress['Address1']}<br>
                            {$CountryToRMAAddress['Address2']}<br>
                            {$CountryToRMAAddress['Address3']}<br>
                        </span>
                        <br>
                        <span>{$OrderAfterSaleApplyInfo['data']['shipping_address']['first_name']} {$OrderAfterSaleApplyInfo['data']['shipping_address']['last_name']}</span>
                        <br>
                        Order No: {$OrderAfterSaleApplyInfo['data']['order_number']}
                        <p style="text-align:right;">
                            RMACode:{$OrderAfterSaleApplyInfo['data']['after_sale_number']}<br>
                            <img src="http://c.dx.com/banner/201808/20180807/BarCode.jpg" width="500">
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p style="font-size: 12px;line-height:12px;color: #333333;">
            <img src="http://c.dx.com/banner/201808/20180807/line.jpg" style="width: 1100px;">
            <br>
            The label need to be sticked outside the package......</p>
        <table cellpadding="0" cellspacing="0" border="1" width="100%" align="center" valign="top" style="font-family:Arial, Helvetica, sans-serif; color:#000000;font-size: 14px;border-color:#000000;" bgcolor="#ffffff">
            <tbody>
                <tr style="font-weight: normal;font-size: 14px;">
                    <th>INDEX</th>
                    <th>NAME</th>
                    <th>SKU</th>
                    <th>QUANTITY</th>
                </tr>
                {$item_html}
            </tbody>
        </table>
    </div>
</body>
</html>
EOD;
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $pdf->Output('ReturnAddress'.$OrderAfterSaleApplyInfo['data']['after_sale_number'].'.pdf', 'D');
    }

    public function getCountryToRMAAddress($country_code){
        $CountryToRMAAddress = json_decode(config("CountryToRMAAddress"),true);
            foreach ($CountryToRMAAddress['Stores']['Store'] as $key=>$value){
                foreach ($value['IncludeCountry'] as $k=>$v){
                    if($v['CountryCode'] == $country_code){
                        unset($value['IncludeCountry']);
                        return $value;
                    }
                }
            }
        return false;
    }

    public function barcode_create($barcode){
        $content= input("barcode",$barcode);
        // 引用barcode文件夹对应的类
        import('BCode.BCGFontFile',EXTEND_PATH);
        //Loader::import('BCode.BCGColor',EXTEND_PATH);
        import('BCode.BCGDrawing',EXTEND_PATH);
        // 条形码的编码格式
        import('BCode.BCGcode39',EXTEND_PATH,'.barcode.php');
        // $code = '';
        // 加载字体大小
        //$font = new BCGFontFile('./class/font/Arial.ttf', 18);
        //颜色条形码
        $color_black = new \BCGColor(0, 0, 0);
        $color_white = new \BCGColor(255, 255, 255);
        $drawException = null;
        try
        {
            $code = new \BCGcode39();
            $code->setScale(2);
            $code->setThickness(30); // 条形码的厚度
            $code->setForegroundColor($color_black); // 条形码颜色
            $code->setBackgroundColor($color_white); // 空白间隙颜色
            // $code->setFont($font); //
            $code->parse($content); // 条形码需要的数据内容
        }
        catch(\Exception $exception)
        {
            $drawException = $exception;
        }
        //根据以上条件绘制条形码
        $drawing = new \BCGDrawing('', $color_white);
        if($drawException) {
            $drawing->drawException($drawException);
        }else{
            $drawing->setBarcode($code);
            $drawing->draw();
        }
        // 生成PNG格式的图片
        header('Content-Type: image/png');
        //header('Content-Disposition:attachment; filename="barcode.png"'); //自动下载
        $drawing->finish(\BCGDrawing::IMG_FORMAT_PNG);
    }
}