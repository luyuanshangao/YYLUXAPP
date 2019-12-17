<?php
/**
 * 订单退款控制器
 * Created by PhpStorm.
 * User: Kevin
 * Date: 2019/04/19
 * Time: 16:55
 */
namespace app\index\controller;

use app\common\params\OrderAfterSaleApplyParams;
use app\common\params\OrderRefundParams;
use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use app\index\model\ProductQaModel;
use app\index\model\WholesaleInquiryModel;
use think\Config;
use think\Log;
class OrderRefund  extends Common
{
    /**
     * 申请订单退款数据
     * @return mixed
     */
    public function orderRefund(){
        $order_id = input('order_id');
        if (empty($order_id) || !is_numeric($order_id) || $order_id<=0){
            abort(404);
        }
        $base_api = new BaseApi();
        $order_info_api = $base_api->getOrderInfo($order_id, $this->login_user_id);
        if ($order_info_api['code'] == 90001){
            abort(404);
        }
        $order_info = [];
        if (isset($order_info_api['data']) && !empty($order_info_api['data'])){
            $order_info = $order_info_api['data'];
            //订单状态
            $order_status_str = Base::getOrderStatus($order_info['order_status']);
            $order_info['order_status_str'] = isset($order_status_str['name'])?$order_status_str['name']:'-';
            //币种
            $order_info['currency_code_str'] = Base::getCurrencyCodeStr($order_info['currency_code']);
        }
        //dump($order_info);exit;
        //获取售后订单相关配置
        $after_config = (isset($after_config_api['data']) && !empty($after_config_api['data']))?$after_config_api['data']:[];
        foreach ($order_info['item_data'] as &$value){
            $product_attr_desc = htmlspecialchars_decode($value['product_attr_desc']);
            $value['product_attr_desc'] = Base::handleOrderProductaAttrDesc($product_attr_desc);
        }
     // dump($order_info);exit;
        $this->assign([
            'order_info'=>$order_info,
            'ajax_url'=>json_encode([
                'async_submitRefund'=>url('OrderRefund/async_submitRefund'),
            ]),
        ]);
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
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
    public function async_submitRefund(){
        $rtn = ['msg'=>'', 'code'=>100];
        $param = input();
        //数据校验
        $validate = $this->validate($param,(new OrderRefundParams())->async_submitRefundRules());
        if(true !== $validate){
            $rtn['msg'] = $validate;
            return json($rtn);
        }
        $base_api = new BaseApi();
        //item数据校验
        if(isset($param['item']) && !empty($param['item'])){
            foreach ($param['item'] as $info){
                $validate_itemt = $this->validate($info,(new OrderRefundParams())->async_submitRefundItemsRules());
                if(true !== $validate_itemt){
                    $rtn['msg'] = $validate_itemt;
                    return json($rtn);
                }
            }
            $item_count = count($param['item']);
        }
        //$param['captured_refunded_fee'] = $param['captured_refunded_fee'];
        $param['initiator'] = 2;
        /*$param['customer_id'] = $param['customer_id'];
        $param['customer_name'] = $param['customer_name'];
        $param['store_id'] = $param['store_id'];
        $param['store_name'] = $param['store_name'];*/
        $order_info_api = $base_api->getOrderInfo($param['order_id'], $this->login_user_id);
        //dump($order_info_api);exit;
        /*$order_item = $base_api->getOrderItem(['order_id'=>$param['order_id']]);
        $OrderBasics = $base_api->getOrderBasics(['order_id'=>$param['order_id']]);*/
        /*
         * 20190118 kevin 修改订单退款失败后可再次进行退款
         * */
        $refund_id = input("refund_id");
        if(empty($refund_id)){
            $order_item_count = 0;
            if(isset($order_info_api['data']['item_data']) && !empty($order_info_api['data']['item_data'])){
                $order_item_count = count($order_info_api['data']['item_data']);
            }
            if($param['captured_refunded_fee']>0){
                $param['refunded_fee'] = $param['captured_refunded_fee'];
            }else{
                if($order_item_count>$item_count){
                    //价格累加
                    $price_count = 0;
                    foreach ($param['item'] as $val){
                        $price_count += $val['product_price'] * $val['product_nums'];
                    }
                    $param['refunded_fee'] = $param['captured_refunded_fee'] = $price_count;
                }else{
                    $param['refunded_fee'] = $param['captured_refunded_fee'] = $order_info_api['data']['captured_amount'];
                }
            }
            $res = $base_api->saveOrderRefund($param);
            if($res['code'] == API_RETURN_SUCCESS){
                $refund_id =  $res['data'];
            }
        }
        if (isset($refund_id) && !empty($refund_id)){
            $param['refund_id'] = $refund_id;
            $param['status'] = 2;
            $param['customer_id'] = $order_info_api['data']['customer_id'];
            $rtn = config('ajax_return_data');
            if (
                isset($param['refund_id']) && !empty($param['refund_id'])
                &&isset($param['status']) && !empty($param['status'])
            ){
                $time = time();
                //售后类型（1换货，2退货 3退款）
                $type = 3;
                //处理确认申请信息
                //进入平台自动退款，若成功则变为‘退款成功’，若失败则列表行该列不发生变化 TODO
                $up_param['refund_id'] = $param['refund_id'];
                $up_param['order_id'] = $param['order_id'];
                //退款来源:1-seller售后退款；2-my退款；3-admin退款
                $up_param['refund_from'] = 1;
                //退款类型：1-陪保退款；2-售后退款；3-订单取消退款；4-普通退款
                $up_param['refund_type'] = 4;
                //操作人类型：1-admin，2-seller，3-my
                $up_param['operator_type'] = 2;
                /*将用户登录真正信息传入*/
                $up_param['operator_id'] = $this->real_login_user_id;
                $up_param['operator_name'] = $this->real_login_user_name;
                $up_param['reason'] = isset($param['remarks'])&&!empty($param['remarks'])?$param['remarks']:'退款确认申请退款';
                $chage_desc = "Seller order refund operation cancels order";
                $change_reason = isset($param['remarks'])&&!empty($param['remarks'])?$param['remarks']:$chage_desc;
                /*取消订单*/
                $up_param['change_reason'] = $change_reason;
                //$up_param['change_reason_id'] = "";
                $up_param['create_by'] = "Seller,operator id:".$this->real_login_user_id.",operator name:".$this->real_login_user_name;
                $up_param['create_ip'] = GetIp();
                $up_param['chage_desc'] = $chage_desc;
                    $res = $base_api->refundOrderNoAfter($up_param);
                    if ($res['code'] == API_RETURN_SUCCESS){
                        $rtn['code'] = 200;
                        $rtn['msg'] = 'success';
                    }else{
                        $rtn['msg'] = '退款失败 '.$res['msg'];
                        Log::record('async_refundAllConfirmApply->确认申请 退款失败'.print_r($res, true));
                    }
                }
            }else{
                $rtn['msg'] = 'Submission failure, '.$res['msg'];
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
                $res['url'] = $remotePath.'/'.$localres['FileName'];
            }else{
                unlink($localres['url']);
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            echo json_encode($res);
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
            }else{
                // 上传失败获取错误信息
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