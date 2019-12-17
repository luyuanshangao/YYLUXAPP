<?php
namespace app\app\controller;

use app\app\dxcommon\BaseApi;
use app\app\services\CheckoutService;
use app\app\services\CommonService;
use app\app\services\NocService;
use app\app\services\OrderService;
use app\app\services\PaymentService;
use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\app\OrderParams;
use think\Cookie;
use think\Log;
use think\Monlog;
use TCPDF;
use app\admin\model\Img;
/**
 * 开发：yanxh
 * 功能：MyOrder
 * 时间：2019-09-02
 */
class MyOrder extends AppBase{
    private $CommonService;
    public function __construct(){
        parent::__construct();
        $this->CommonService = new CommonService();
        $this->baseApi = new BaseApi();
    }
    /*下载发票*/
    public function downloadInvoice(){
        $params = request()->param();
        $customer_id = $params['customer_id'];
        $order_number =$params['o_number'];
        $lang =isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        //$order = input("order");
        $baseApi = new BaseApi();
        /*if(!empty($order)){
            if(strlen($order)>15){
                $order_number = $order;
                $order_id = '';
            }else{
                $order_number = '';
                $order_id = $order;
            }
        }*/
        $data = $baseApi->getOrderInfo(['customer_id'=>$customer_id,'order_number'=>$order_number]);
        if(!isset($data['data']['order_id'])){
            return ["code"=>2001, "msg"=>"Orders do not exist"];
        }
        if(isset($data['data']['order_package'])){
            $tracking_number = isset($data['data']['order_package'][0]['tracking_number'])?$data['data']['order_package'][0]['tracking_number']:'';
        }
        if(!empty($data['data']['shipping_address']['street2'])){
            $street_address =$data['data']['shipping_address']['street2'].",".$data['data']['shipping_address']['street1'];
        }else{
            $street_address =$data['data']['shipping_address']['street1'];
        }
        $create_on = date("d/m/Y H:i:s",$data['data']['create_on']);
        $order_status = lang("order_status_".$data['data']['order_status']);
        $order_items = "";
        foreach ($data['data']['item'] as $key=>$value){
            /*去除属性中图片*/
            $product_attr_desc_array = explode(",",$value['product_attr_desc']);
            $product_attr_desc_data = "";
            if(!empty($product_attr_desc_array)){
                foreach ($product_attr_desc_array as $k1=>$v1){
                    $product_attr_desc_key_array = explode("|",$v1);
                    if($k1 == 0){
                        $product_attr_desc_data .= isset($product_attr_desc_key_array[0])?$product_attr_desc_key_array[0]:'';
                    }else{
                        $product_attr_desc_data .= isset($product_attr_desc_key_array[0])?",".$product_attr_desc_key_array[0]:'';
                    }
                }
            }
            $order_items .= "<tr align='left'>
            <td><p style='padding: 5px;'>{$value['sku_num']}</p></td>
            <td><p style='padding: 5px;'>".htmlentities($value['product_name'])."</p></td>
            <td><p style='padding: 5px;'>{$product_attr_desc_data}</p></td>
            <td><p style='padding: 5px;'>{$value['product_nums']}</p></td>
            <td><p style='padding: 5px;'>{$value['captured_price']}</p></td>
        </tr>";
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
            $pdf->setLanguageArray($lang);
        }
        //$pdf->setFontSubsetting(true);
        $pdf->SetFont('dejavusans', '', 1, '', true);
        $pdf->AddPage();
        $html = <<<EOD
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>订单打印</title>
    <style>
        *{
            margin: 0;
            padding: 0;
        }
        html{
            margin: 0 auto;
            width: 800px;
        }
        .title{
            margin-right: 10px;
            font-weight: bolder;
        }
        h2{
            margin-bottom: 20px;
            font-size: 13px;
        }
        .items-sold,
        table{
            font-size: 12px;
        }
        table td span{
            display: inline-block;
            padding: 5px;
        }
        .invoice-table{
            margin-top: 30px;
        }
        .invoice-table,
        .items-sold-table{
            width: 100%;
            line-height: 14px;
        }
        .invoice-table td,
        .items-sold-table td,
        .items-sold-table th,
        .total-table th{
            padding: 5px 10px;
            line-height: 18px;
            vertical-align: top;
        }
        .items-sold{
            margin-top: 40px;
        }
        .note{
            margin-top: 40px;
            display: flex;
        }
        .note strong{
            margin-right: 20px;
        }
        .total-table{
            margin-top: 50px;
        }
        .total-table tr td:nth-child(1){
            width: 70%;
        }
        .total-table tr td span{
            display: inline-block;
            width: 180px;
            text-align: right;
            line-height: 16px;
            padding: 5px;
        }
    </style>
</head>
<body>
    <h1>
        <a href="https://www.dx.com">
            <img src="https://c.dx.com/albums/Public/logo_DX_en.png" alt="">
        </a>
    </h1>
    <h2>DealExtreme Invoice</h2>
    <table cellspacing="0" cellpadding="0" bgcolor="#ffffff" class="invoice-table">
        <tr>
            <td><span class="title">Invoice / Order :</span> {$data['data']['order_number']}</td>
            <td rowspan="4">
                <span class="title">To:</span>
                {$data['data']['shipping_address']['first_name']} {$data['data']['shipping_address']['last_name']}<br>
                {$street_address}<br>
                {$data['data']['shipping_address']['city']}, {$data['data']['shipping_address']['state']}, {$data['data']['shipping_address']['country']}<br>
                {$data['data']['shipping_address']['postal_code']}<br>
            </td>
        </tr>
        <tr>
            <td><span class="title">Date:</span>{$create_on}</td>
        </tr>
        <tr>
            <td><span class="title">Payment (TXD):</span>{$data['data']['currency_code']}</td>
        </tr>
        <tr>
            <td><span class="title">Status:</span>{$order_status}</td>
        </tr>
    </table>



    <h3 class="items-sold">Items Sold:</h3>
    <table border="1" cellspacing="0" cellpadding="5" bgcolor="#ffffff" class="items-sold-table">
        <tr align="left">
           <th><p style="padding: 5px;">SKU</p></th>
           <th><p style="padding: 5px;">Product or Service Name</p></th>
           <th><p style="padding: 5px;">Product Properties</p></th>
           <th><p style="padding: 5px;">Quantity</p></th>
           <th><p style="padding: 5px;">Price</p></th>
        </tr>
        {$order_items}
    </table>
    <h3 class="items-sold"></h3>
    <table border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" class="total-table">
        <tr align="left">
           <td valign="top">Tracking Number:{$tracking_number}</td>
            <td>
                <span class="total-name">Order Subtotal:</span> {$data['data']['total_amount']}<br>
                <span class="total-name">+ Shipping Cost:</span> {$data['data']['receivable_shipping_fee']}<br>
                <span class="total-name">+ Handling Fee:</span> {$data['data']['handling_fee']}<br>
                <span class="total-name">- Discount Total:</span> {$data['data']['discount_total']}<br>
                <span class="total-name">GrandTotal:</span> {$data['data']['grand_total']}
            </td>
        </tr>
    </table>
</body>
</html>
EOD;
        $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
        $pdf->Output('Invoice_'.$data['data']['order_number'].'.pdf', 'D');
    }

    /*
    * 获取订单留言类型
    * add by 20190422 kevin
    * */
    public function getMessageType(){
        $baseApi = new BaseApi();
        $order_message_type = $baseApi->getOrderConfig('order_message_type');
        return $order_message_type;
    }

    public function addOrderMessage(){
        $customer_id = input('customer_id');
        $UserName = input('UserName');
        $order_where['order_number'] = input("o_number");
        $baseApi = new BaseApi();
        $OrderBasics = $baseApi->getOrderBasics($order_where,$customer_id);
        if(isset($OrderBasics['code']) && $OrderBasics['code'] ==200){
            $data['order_id'] = $OrderBasics['data']['order_id'];
            $data['parent_id'] = input("parent_id",0);
            $data['message_type'] = 2;
            $data['message'] = input("message");
            $data['first_category'] = input("first_category/d");
            $data['second_category'] = input("second_category/d");
            $data['user_id'] = $customer_id;
            $data['user_name'] = $UserName;
            $data['file_url'] = input("file_url");
            $baseApi = new BaseApi();
            $res = $baseApi->addOrderMessage($data);
            if($res['code']==200){
                Log::record('addOrderMessage0'.json_encode(input()).'$data'.json_encode($data).'$res'.json_encode($res));
                return $this->result([]);
            }else{
                Log::record('addOrderMessage1'.json_encode(input()).'$data'.json_encode($data).'$res'.json_encode($res));
                return $this->result([],10001,"operation failed.");
            }
        }else{
            Log::record('addOrderMessage2'.json_encode(input()).'$OrderBasics'.json_encode($OrderBasics));
            return $this->result([],10002,"operation failed.");
        }
    }

    public function getOrderMessage(){
        $baseApi = new BaseApi();
        $customer_id = input('customer_id');
        $order_id = input('order_id');
        $message_where['order_id'] = $order_id;
        $order_message = $baseApi->getOrderMessage($message_where);
        if(!empty($order_message['data'])){
            return $order_message;
        }else{
            return $this->result([]);
        }
    }

}
