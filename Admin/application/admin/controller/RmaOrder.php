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
use app\admin\services\BaseService;
use app\admin\dxcommon\ExcelTool;
// use app\admin\model\Interface;

/**
 * RMA订单
 * @author kevin   2019-02-15
 */
class RmaOrder extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
    }

    /**
     * RMA订单列表
     * @author kevin   2019-05-10
     */
    public function index()
    {
        $baseApi = new BaseApi();
        return $this->fetch();
    }

    /**
     * 创建RMA订单
     * @author kevin   2019-05-10
     */
    public function createRmaOrder()
    {
        if(request()->isPost()){
            $data = request()->post();
            if(empty($data['customer_id'])){
                return ['code'=>1001,'msg'=>"用户ID不能为空"];
            }
            $customer_data = BaseApi::getCustomerByID($data['customer_id']);
            if(!empty($customer_data['data']['UserName'])){
                $params['customer_id'] = $data['customer_id'];
                $params['customer_name'] = $customer_data['data']['UserName'];
            }else{
                return ['code'=>1001,'msg'=>"用户不存在"];
            }
			//巴西国家必须填写CPF tinghu.liu 20191128
            $_country_code = !empty($data['country_code'])?$data['country_code']:'';
            $_cpf = !empty($data['cpf'])?$data['cpf']:'';
            if ($_country_code == 'BR' && empty($_cpf)){
                return ['code'=>1001,'msg'=>"收货地址国家为“巴西”，CPF不能为空"];
            }
            $params['order_number'] = input('order_number');
            $params['store_id'] = isset($data['store_id'])?$data['store_id']:0;
            $params['store_name'] = isset($data['store_name'])?$data['store_name']:'';
            $params['currency_code'] = isset($data['currency_code'])?$data['currency_code']:'';
            $params['exchange_rate'] = !empty($data['currency_rate'])?$data['currency_rate']:1;
            $params['remark'] = isset($data['remark'])?$data['remark']:'';
            $params['goods_total'] = !empty($data['goods_total'])?sprintf("%.2f",$data['goods_total']):0;
            $params['shipping_fee'] = !empty($data['shipping_fee'])?sprintf("%.2f",$data['shipping_fee']):0;
            $params['handling_fee'] = !empty($data['handling_fee'])?$data['handling_fee']:0;

            $params['captured_amount'] = sprintf("%.2f",floatval($params['goods_total'])+floatval($params['shipping_fee'])+floatval($params['handling_fee']));
            $params['captured_amount_usd'] = sprintf("%.2f",$params['captured_amount']/$params['exchange_rate']);
            /*订单商品*/

            /*订单地址信息*/
            $params['first_name'] = $data['first_name'];
            $params['last_name'] = $data['last_name'];
            $params['mobile'] = $data['mobile'];
            $params['phone_number'] = $data['phone_number'];
            $params['postal_code'] = $data['postal_code'];
            $params['street1'] = $data['street1'];
            $params['street2'] = $data['street2'];
            $params['country'] = $data['country'];
            $params['country_code'] = !empty($_country_code)?$_country_code:$params['country'];
            $params['email'] = !empty($data['email'])?$data['email']:'';
            $params['state'] = $data['state'];
            $params['state_code'] = !empty($data['state_code'])?$data['state_code']:$params['state'];
            $params['city'] = $data['city'];
            $params['city_code'] = !empty($data['city_code'])?$data['city_code']:$params['city'];
            $params['cpf'] = $_cpf;
            /*foreach (){

            }*/
            $params['data'] = array();
            if(!empty($data['goods']) && !empty($data['checked_sku'])){
                foreach ($data['goods'] as $key=>$value){
                    if(in_array($key,$data['checked_sku'])){
                        $params['data'][$value['sku_id']]['product_id'] = $value['product_id'];
                        $params['data'][$value['sku_id']]['sku_id'] = $value['sku_id'];
                        $params['data'][$value['sku_id']]['sku_num'] = $value['sku_num'];
                        $params['data'][$value['sku_id']]['product_nums'] = $value['product_nums'];

                        //产品原售价
                        $params['data'][$value['sku_id']]['pruduct_price'] = $value['SalesPrice'];
                        //$value['captured_price']是当前产品总价，转换为单价 tinghu.liu 20191128
                        $product_captured_price = sprintf('%.2f', $value['captured_price']/$value['product_nums']);
                        $params['data'][$value['sku_id']]['captured_price'] = $product_captured_price;
                        $params['data'][$value['sku_id']]['captured_price_usd'] = sprintf('%.2f',$product_captured_price/$params['exchange_rate']);

                        $params['data'][$value['sku_id']]['shipping_model'] = $data['ShippingMethod'];
                        $params['data'][$value['sku_id']]['remark'] = $value['sku_remark'];
                    }
                }
            }
            if(empty($params['data'])){
                return ['code'=>1001,'msg'=>"选择商品不能为空"];
            }
//            echo json_encode($params);die;/**/
            $params['add_user_name'] = Session::get("username");
            $params['add_user_id'] = Session::get("userid");
            $res = BaseApi::createAdminRmaOrder($params);
            Log::record('创建RMA订单参数：'.json_encode($params).'，返回：'.json_encode($res));
            //print_r($res);die;
            //返回处理，tinghu.liu 20191128
            $result_return = ['code'=>101,'msg'=>'创建失败，请重试。'];
            if (isset($res['code']) && $res['code'] == 200) {
                $result_return['code'] = 200;
                $result_return['msg'] = '创建成功';
                //支付地址
                $pay_url = '';
                if (isset($res['data']['pay_token'])) {
                    $pay_url = config('dx_url') . 'payConfirm?PayToken=' . $res['data']['pay_token'];
                }
                $result_return['pay_url'] = $pay_url;
                //订单号
                $result_return['order_number'] = $res['data']['order_number'];
            }else{
                if (isset($res['msg'])){
                    $result_return['msg'] = $result_return['msg'].$res['msg'];
                }
            }
            $is_captured_zero = 0;
            if ($params['captured_amount'] == 0){
                $is_captured_zero = 1;
            }
            $result_return['is_zero'] = $is_captured_zero;
            return $result_return;

        }else{
            $order_number = input("order_number");
            $currency = BaseApi::getCurrencyList();
            if(!empty($order_number)){
                $data['orderNumber']= $order_number;
                $result = BaseApi::getOrderDetail($data);
                //dump($result);exit;
                if(!empty($result['data'])){
                    $this->assign('OrderDetail',$result['data']);
                }
            }
            $country = BaseApi::getRegionList();
            $ShippingServiceMethod = $this->dictionariesQuery('ShippingServiceMethod');
            if(!empty($ShippingServiceMethod)){
                foreach ($ShippingServiceMethod as $key=>$value){
                    $ShippingServiceMethodData = explode('-',$value[1]);
                    $ShippingServiceMethod[$key]['cn'] = $ShippingServiceMethodData[0];
                    $ShippingServiceMethod[$key]['en'] = $ShippingServiceMethodData[1];
                }
            }
            $this->assign('country',$country);
            $this->assign('currency',!empty($currency['data'])?$currency['data']:'');
            $this->assign('ShippingServiceMethod',$ShippingServiceMethod);
            return $this->fetch();
        }
    }

    /*
     * 根据SKU获取产品
     * */
    public function getProductInfo(){
        $param_data = input();
        $where = array();
        $sku_id = isset($param_data['sku_id'])?$param_data['sku_id']:0;
        $sku_code = isset($param_data['sku_code'])?$param_data['sku_code']:'';
        $currency_code = isset($param_data['currency_code'])?$param_data['currency_code']:'USD';
        $store_id = isset($param_data['store_id'])?$param_data['store_id']:0;
        if(!empty($sku_id)){
            $where['sku_id'] = $sku_id;
        }
        if(!empty($sku_code)){
            $where['sku_code'] = $sku_code;
        }
        if(!empty($store_id)){
            $where['store_id'] = $store_id;
        }
        if(!empty($where)){
            $result = BaseApi::getProductInfo($where);
//            $currency_code = input('currency_code','USD');
            $BaseService = new BaseService();
            $currency_rate = $BaseService->getCurrencyRate($currency_code);
            if(!empty($result['data'])){
                if(!empty($result['data']['Skus'])){
                    if(!empty($sku_id)){
                        $result['data']['sku'] = $this->processSkuInfo($result['data']['Skus'],$sku_id);
                    }
                    if(!empty($sku_code)){
                        $result['data']['sku'] = $this->processSkuInfo($result['data']['Skus'],'',$sku_code);
                    }
                }
                /*转换汇率的价格*/
                $result['data']['sku']['TransSalesPrice'] = sprintf("%01.2f",(double)$result['data']['sku']['SalesPrice'] * $currency_rate);
                return $result;
                //dump($result);exit;
            }else{
                return ['code'=>1001,'msg'=>"SKU不存在！"];
            }
        }else{
            return ['code'=>1001,'msg'=>"请输入SKU！"];
        }

    }

    /*获取汇率*/
    public function getCurrencyRate(){
        $currency_code = input('currency_code','USD');
        $BaseService = new BaseService();
        $currency_rate = $BaseService->getCurrencyRate($currency_code);
        return $currency_rate;
    }

    /**
     * 处理SKU信息
     * @param unknown $_sku_info
     * @param unknown $sku_id
     * @return
     */
    public function processSkuInfo($_sku_info,$sku_id='',$sku_code=''){
        $_return_data = array();
        $_attr_desc = array();
        if(is_array($_sku_info)){
            foreach ($_sku_info as $k=>$v){
                if((!empty($v['_id']) && $v['_id'] == $sku_id) || (!empty($v['Code']) && $v['Code'] == $sku_code)){
                    $_return_data = $v;
                    if(isset($v['SalesAttrs'])){
                        foreach ($v['SalesAttrs'] as $k1=>$v1){
                            if(isset($v1['Image']) && $v1['Image']){
                                $_attr_desc[] = $v1['Name'].':<img width="70" src='.config('dx_mall_img_url').$v1['Image'].'>';
                            }else{
                                if(isset($v1['CustomValue']) && $v1['CustomValue']){
                                    $_attr_desc[] = $v1['Name'].':'.$v1['CustomValue'];
                                }else{
                                    if(isset($v1['DefaultValue']) && $v1['DefaultValue']){
                                        $_attr_desc[] = $v1['Name'].':'.$v1['DefaultValue'];
                                    }else{
                                        $_attr_desc[] = $v1['Name'].':'.$v1['Value'];
                                    }

                                }
                            }

                        }
                    }
                    break;
                }

            }
        }
        //$_attr_desc = mb_substr($_attr_desc,0,-1,'utf8');
        $_return_data['attr_desc'] = $_attr_desc;

        return $_return_data;
    }

    /*获取地区列表*/
    public function getRegionList(){
        $code = input('code');
        $country_code = input('country_code');
        $country = BaseApi::getRegionList($code,$country_code);
        return $country;
    }


}