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
 * 海外订单管理
 * @author kevin   2019-07-11
 */
class DeliveryOrder extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
    }

    /**
     * 海外发货的导出
     * @author kevin   2019-07-11
     */
    public function deliveryOrderExport()
    {
        $baseApi = new BaseApi();
        $pdata = input();
        $param_data['path'] = url("DeliveryOrder/deliveryOrderExport");
        $param_data['page_size']= input("page_size",20);
        $param_data['page'] = input("page",1);
        $param_data['page_query'] = $pdata;
        /*是否是导出操作 1是，0否*/
        $param_data['is_export'] = input("is_export",0);
        if(isset($pdata['page_size'])){
            unset($pdata['page_size']);
        }
        if(isset($pdata['page'])){
            unset($pdata['page']);
        }
        /*if(isset($data['store_id']) && !empty($data['store_id'])){
            $param_data['store_id'] = $data['store_id'];
        }*/
        $param_data['store_id'] = input("store_id",888);

        if(isset($pdata['startTime']) && !empty($pdata['startTime'])){
            $param_data['startTime'] = $pdata['startTime'];
        }
        if(isset($pdata['endTime']) && !empty($pdata['endTime'])){
            $param_data['endTime'] = $pdata['endTime'];
        }
        $data = $baseApi::getDeliveryOrder($param_data);
        $sku_unit_cost = array();
        if($param_data['is_export'] == 0){
            /*获取出货单信息*/
            if($data['code'] == 200 && $data['data']['total']>0){
                $delivery_order_data = $data['data']['data'];
                $sku_data = array();
                foreach ($delivery_order_data as $key=>$value){
                    $delivery_order_data[$key]['total_cost'] = sprintf("%.2f",($value["captured_price"]*$value['sku_qty'])+$value['shipping_fee']);
                    $delivery_order_data[$key]['total_cost_usd'] = sprintf("%.2f",($value["captured_price_usd"]*$value['sku_qty'])+$value['shipping_fee']);
                }
            }
            /* $product_purchase_price = $baseApi::getProductPurchasePrice(['skus'=>$sku_data]);
             if($product_purchase_price['code'] == 200 && !empty($product_purchase_price['data'])){
                 foreach ($product_purchase_price['data'] as $key=>$value){
                     $sku_unit_cost[$value['SKU']] = $value['UnitCost'];
                 }
             }
             $this->assign("sku_unit_cost",$sku_unit_cost);*/
            $data['data']['data'] = $delivery_order_data;
            $this->assign("list",!empty($data['data'])?$data['data']:'');
            return $this->fetch('');
        }else{
            if($data['code'] == 200 && !empty($data['data'])){
                /*获取出货单信息*/
                $delivery_order_data = $data['data'];
                /*$sku_data = array();
                foreach ($delivery_order_data as $key=>$value){
                    if(!in_array($value['sku_id'],$sku_data)){
                        $sku_data[] = $value['sku_id'];
                    }
                }*/
            }
            if($param_data['is_export'] == 1) {
                if (!empty($delivery_order_data)) {
                    foreach ($delivery_order_data as $k => $v) {
                        $delivery_order_data[$k]['total_cost'] = sprintf("%.2f", ($v["captured_price"] * $v['sku_qty']) + $v['shipping_fee']);
                        $delivery_order_data[$k]['total_cost_usd'] = sprintf("%.2f", ($v["captured_price_usd"] * $v['sku_qty']) + $v['shipping_fee']);
                        $delivery_order_data[$k]['create_on'] = !empty($v['create_on']) ? date("Y-m-d H:i:s", $v['create_on']) : '';
                        $delivery_order_data[$k]['shipments_time'] = !empty($v['shipments_time']) ? date("Y-m-d H:i:s", $v['shipments_time']) : '';
                        $delivery_order_data[$k]['shipment_address'] = $v['street1'] . "  " . $v['street2'];
                        $delivery_order_data[$k]['buyer_name'] = $v['first_name'] . " " . $v['last_name'];
                    }
                    $header_data = ['order_number' => 'OrderNumber', 'sku_id' => 'SKU', 'unit_cost' => 'UnitCost',
                        'currency_code' => 'Currency', 'captured_price' => 'Price', 'captured_price_usd' => 'Price(USD)', 'sku_qty' => 'Quantity',
                        'total_cost' => 'TotalCost', 'total_cost_usd' => 'TotalCost(USD)', 'shipping_fee' => 'ShippingFee', 'discount_total' => 'Discount',
                        'first_category_id' => 'PrimaryCategoryID', 'create_on' => 'OrderDate', 'shipments_time' => 'ScanDate', 'shipment_address' => 'ShipmentAddress', 'country_code' => 'CountryCode',
                        'buyer_name' => 'BuyerName', 'shipping_channel_name' => 'ChannelName', 'tracking_number' => 'TrackingNumber'
                    ];

                } else {
                    $this->error("没查到数据");
                }
            }else{
                foreach ($delivery_order_data as $k => $v) {
                    $delivery_order_data[$k]['create_on'] = !empty($v['create_on'])?date("Y-m-d H:i:s",$v['create_on']):'';
                    $delivery_order_data[$k]['shipments_time'] = !empty($v['shipments_time'])?date("Y-m-d H:i:s",$v['shipments_time']):'';
                }
            $header_data =['order_number'=>'OrderNumber','currency_code'=>'Currency', 'tariff_insurance'=>'TariffInsurance','create_on'=>'OrderDate','shipments_time'=>'ScanDate',  'country_code' => 'CountryCode'
        ];
    }
            $tool = new ExcelTool();
            if(!empty($param_data['startCreateOn']) && !empty($param_data['endCreateOn'])){
                $excel_file_name = " ".date("md",strtotime($param_data['startCreateOn']))."-".date("md",strtotime($param_data['endCreateOn']));
            }else{
                $excel_file_name = "";
            }
            if(!empty($delivery_order_data)){
                $tool ->export('partnerData'.$excel_file_name,$header_data,$delivery_order_data,'sheet1');
            }else{
                $this->error("没查到数据");

            }
        }
    }
}