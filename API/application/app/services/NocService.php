<?php
/**
 * NOCNOC处理服务类
 * Created by PhpStorm.
 */
namespace app\app\services;

use \think\Cache;
use \think\Cookie;
use think\Log;
use think\Monlog;


class NocService extends BaseService
{
    private $CommonService = '';
    public function __construct(){
        parent::__construct();
        $this->CommonService = new CommonService();//公共服务类
    }

    /**
     * 收集购物车的信息，
     * 整理成按seller为单位的SKU信息
     * 注意是该SKU是否选中
     * 把这些信息发往NOCNOC
     * 如果成功，则把NOCNOC返回的信息写入到cartInfo
     * $_cart_info[$_customer_id]['StoreData'][$k]['NocData']
     * 把cartInfo重新写入到redis中
     * 把计算得到的NOCNOC费用返回给调用端
     * @param $params
     * @param $_cart_info
     * @param int $from 来源：0-默认，1-来至cart改变运输方式&&cart改变选中产品&&cart页面初始化&&cart改变产品数量，2-来至checkout改变运输方式, 3- 来至填写taxid页面的检验，4-来至checkou页面初始化（返回错误提示&计算的运费）
     * @return bool|null
     */
    public function claNocNocData($params,&$_cart_info,$from=0){
        if ($from == 1){
            //cart页面去掉NOC运费计算，2018-08-17确定cart页面不计算noc运费
            return true;
        }
        $_customer_id = $params['customer_id'];
        $_tax_id = isset($params['tax_id'])?$params['tax_id']:0;
        $zipcode = isset($params['zipcode'])?$params['zipcode']:'';
        //币种
        $_currency = Cookie::get('DXGlobalization_currency');
        $rate_source = [];
        if($_currency != DEFAULT_CURRENCY) {
            $rate_source = $this->CommonService->getRateDataSource();
        }
        //$_store_id = $params['store_id'];//商家ID
        $_country = $params['country'];
        $_process_sku_ischecked = isset($params['process_sku_ischecked'])?$params['process_sku_ischecked']:1;
        $_process_sku_ischecked_arr = null;
        $flag = false;
        $log_key = 'claNocNocData'.$_customer_id.$_tax_id.$from;
        if(isset($_cart_info[$_customer_id]['StoreData'])){
            $_nocnoc_request_params['products'] = array();
            if (empty($zipcode)){
                $adress = ['country'=>$_country];
            }else{
                $adress = ['country'=>$_country, 'zipcode'=>$zipcode];
            }
            $_nocnoc_request_params['address'] = $adress;
            //$_nocnoc_request_params['pickup'] = array('country'=>'CN');
            $_nocnoc_request_params['customer'] = array(
                'full_name' => 'DX',
                'country' => $_country,
            );
            /**购物车询价无需传入**/
            if($_tax_id && $from != 1){
                $_nocnoc_request_params['customer']['tax_id'] = $_tax_id;
            }
            /**
             * 获取DX与NOC类别映射数据
             *  20180927修改：类别不用取dx_nocnoc_class表数据，如果是历史产品数据，则需要根据末级类别找到对应ERP的二级类别传过去；若非历史数据，则直接取产品二级类别即可。
             */
            /*$dx_noc_class_map = $this-> getNocClassMap();
            if(empty($dx_noc_class_map)){
                Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,'dx map noc class empty!');
                return false;
            }*/
            foreach ($_cart_info[$_customer_id]['StoreData'] as $k => $v){
                foreach ($v['ProductInfo'] as $k1=>$v1){
                    foreach ($v1 as $k2=>$v2){
                        if(
                            strtolower($v2['ShippingMoel']) == 'nocnoc'
                            &&
                            ( ($from == 3 && $v2['IsBuy'] == 1) || ($from == 2 && $v2['IsBuy'] == 1) || ($from == 4 && $v2['IsBuy'] == 1) || ($from == 1 && $v2['IsChecked'] == 1) )
                        ){
                            //if(strtolower($v2['ShippingMoel']) == 'nocnoc'){
                                $_tmp_arr[0] = $v2['StoreID'];
                                $_tmp_arr[1] = $v2['ProductID'];
                                $_tmp_arr[2] = $v2['SkuID'];
                                $_process_sku_ischecked_arr[] = $_tmp_arr;
                                $flag = true;
                                $_nocnoc_tmp_params['name'] = mb_substr($v2['ProductTitle'],0,99);
                                $_nocnoc_tmp_params['sku'] = $v2['SkuID'];
                                $_weight = isset($v2['Weight'])?$v2['Weight']:0.001;
                                $_nocnoc_tmp_params['weight'] = (int)($_weight*1000); //NOC 是以克为单位，故此处转化为克
                                $_nocnoc_tmp_params['seller_id'] = $v2['StoreID'];
                                $_nocnoc_tmp_params['brand'] = isset($v2['BrandName'])?$v2['BrandName']:'N/A';
                                $_nocnoc_tmp_params['dimension'] = array(
                                    'length' => isset($v2['Length'])?$v2['Length']:10,
                                    'width' => isset($v2['Width'])?$v2['Width']:10,
                                    'height' => isset($v2['Height'])?$v2['Height']:10
                                );
                                //产品价格，统一为美元
                                $_amount_usd = $v2['ProductPrice'];//单价*数量
                                if($_currency != DEFAULT_CURRENCY){
                                    //如果当前币种不是美元的话要转成美元币种存一份
                                    $_amount_usd = $this->CommonService->calculateRate($_currency,DEFAULT_CURRENCY,$_amount_usd,$rate_source);
                                    $_amount_usd = sprintf('%.2f' ,$_amount_usd);
                                }
                                $_nocnoc_tmp_params['amount_usd'] = $_amount_usd;//单价*数量
                                //$_nocnoc_tmp_params['subcategory_id'] = isset($v2['ThirdCategory'])?$v2['ThirdCategory']:0;
                                /**
                                 * 20180927修改：类别不用取dx_nocnoc_class表数据，如果是历史产品数据，则需要根据末级类别找到对应ERP的二级类别传过去；若非历史数据，则直接取产品二级类别即可。
                                 */
                                /*$noc_class_id = 740;
                                if(isset($v2['SecondCategory']) && !empty($v2['SecondCategory'])){
                                    $noc_class_id = $v2['SecondCategory'];
                                }*/
                                $noc_class_id = $this->getNocClass($v2['ProductID'],$v2['SkuID']);
                                if($noc_class_id === false){
                                    //Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,'noc class empty!');
                                    if ($from == 3){
                                        return 'noc class empty!';
                                    }elseif($from == 4){
                                        return ['code'=>0, 'msg'=>'noc class empty!'];
                                    }else {
                                        return false;
                                    }
                                }
                                //*开发环境暂时注释掉,要不NOC会报错 TODO
                                //先匹配二级分类，如果未匹配到，则用一级匹配
                                /*foreach ($dx_noc_class_map as $Key => $Value){
                                    if(isset($Value['class_id'])){
                                        if($Value['class_id'] ==$v2['SecondCategory']){
                                            $noc_class_id = $v2['SecondCategory'];
                                        }elseif($Value['pid'] ==$v2['FirstCategory']){
                                            $noc_class_id = $v2['FirstCategory'];
                                        }
                                    }
                                }*/
                                $_nocnoc_tmp_params['subcategory_id'] = $noc_class_id;
                                $_nocnoc_tmp_params['hs_code'] = isset($v2['HsCode'])?$v2['HsCode']:'9603301090';
                                //$_nocnoc_tmp_params['hs_code'] = '9603301090';
                                $_nocnoc_tmp_params['quantity'] = $v2['Qty'];
                                $_nocnoc_tmp_params['pickup'] = array('country'=>'CN'); //需要判断是否香港仓店铺，如果是，则设置香港仓出货
                                $_nocnoc_request_params['products'][] = $_nocnoc_tmp_params;
                            //}
                            continue;
                        }
                    }
                }
            }
            if($flag){
                //调用NOCNOC服务接口
                //$_url = "https://sandbox.nocnocgroup.com/api/order/dx/quote";
                $_post_header[] = "Content-Type: application/json";
                $_post_header[] = "Accept-Language: en-US";
                $_post_header[] = 'X-api-key:'.API_NOC_KEY;
                $_user_agent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.146 Safari/537.36";

                Log::record(API_NOC_URL.'/'.API_NOC_KEY.',NOCNOC-params:'.json_encode($_nocnoc_request_params));

                $_nocnoc_data = doCurl(API_NOC_URL,$_nocnoc_request_params,null,true,$_post_header,$_user_agent);

                Monlog::write(LOGS_MALL,'error',__METHOD__,$log_key,$_nocnoc_request_params,API_NOC_URL.'/'.API_NOC_KEY,$_nocnoc_data);

                Log::record(API_NOC_URL.'/'.API_NOC_KEY.',NOCNOC-params:'.json_encode($_nocnoc_request_params).',NOCNOC-res:'.json_encode($_nocnoc_data));

                $_arr_tmp = null;
                $_res = null;
                $_res_tmp = null;
                if(is_array($_nocnoc_data)){
                    if(isset($_nocnoc_data['total_weight'])){
                        //单个订单，一维数组
                        $_store_id_tmp = $_nocnoc_data['products'][0]['seller_id'];
                        $_arr_tmp[$_store_id_tmp]['seller_id'] = $_store_id_tmp;
                        $_arr_tmp[$_store_id_tmp]['shipping_usd'][] = $_nocnoc_data['shipping_usd'];
                        $_arr_tmp[$_store_id_tmp]['tax_handling_usd'][] = $_nocnoc_data['tax_handling_usd'];
                    }else if(isset($_nocnoc_data[0])){
                        //多维数组
                        foreach ($_nocnoc_data as $k => $v){
                            $_store_id_tmp = $v['products'][0]['seller_id'];
                            $_arr_tmp[$_store_id_tmp]['seller_id'] = $_store_id_tmp;
                            $_arr_tmp[$_store_id_tmp]['shipping_usd'][] = $v['shipping_usd'];
                            $_arr_tmp[$_store_id_tmp]['tax_handling_usd'][] = $v['tax_handling_usd'];
                        }
                    }else{
                        Monlog::write(LOGS_MALL,'error',__METHOD__,$log_key,null,null,'nocnoc询价出错：params->'.json_encode($_nocnoc_request_params).',red->'.json_encode($_nocnoc_data));
                        //出错处理,遍历SKU该seller，把该seller的所有商品的IsChecked置为0
                        if($_process_sku_ischecked){
                            if(is_array($_process_sku_ischecked_arr)){
                                foreach ($_process_sku_ischecked_arr as $k => $v){
                                    if(isset($_cart_info[$_customer_id]['StoreData'][$v[0]]['ProductInfo'][$v[1]][$v[2]])){
                                        $_cart_info[$_customer_id]['StoreData'][$v[0]]['ProductInfo'][$v[1]][$v[2]]['IsChecked'] = 0;
                                    }
                                }
                            }
                        }
                        if ($from == 3){
                            return $_nocnoc_data['message'];
                        }elseif($from == 4){
                            return ['code'=>0, 'msg'=>$_nocnoc_data['message']];
                        }else {
                            return false;
                        }
                    }
                }else{
                    Monlog::write(LOGS_MALL,'error',__METHOD__,$log_key,null,null,'nocnoc询价出错：params->'.json_encode($_nocnoc_request_params).',red->'.json_encode($_nocnoc_data));
                    //错误描述，遍历SKU该seller，把该seller的所有商品的IsChecked置为0
                    if($_process_sku_ischecked){
                        if(is_array($_process_sku_ischecked_arr)){
                            foreach ($_process_sku_ischecked_arr as $k => $v){
                                if(isset($_cart_info[$_customer_id]['StoreData'][$v[0]]['ProductInfo'][$v[1]][$v[2]])){
                                    $_cart_info[$_customer_id]['StoreData'][$v[0]]['ProductInfo'][$v[1]][$v[2]]['IsChecked'] = 0;
                                }
                            }
                        }
                    }
                    if ($from == 3){
                        return 'System error.';
                    }elseif($from == 4){
                        return ['code'=>0, 'msg'=>'System error.'];
                    }else{
                        return false;
                    }
                }
                if ($from == 3){
                    return true;
                }else{
                    if($_arr_tmp){
                        foreach ($_arr_tmp as $k => $v){
                            $_res[$v['seller_id']]['shipping_usd'] = sprintf("%.2f",array_sum($v['shipping_usd']));
                            $_res[$v['seller_id']]['tax_handling_usd'] = sprintf("%.2f",array_sum($v['tax_handling_usd']));
                            $_res[$v['seller_id']]['o_shipping_usd'] = sprintf("%.2f",array_sum($v['shipping_usd']));
                            $_res[$v['seller_id']]['o_tax_handling_usd'] = sprintf("%.2f",array_sum($v['tax_handling_usd']));
                        }
                    }
                    //币种处理，因为noc询价返回的是美元，而前端需要显示为用户选择的币种价格
                    if($_currency != DEFAULT_CURRENCY){
                        foreach ($_res as $k10=>$v10){
                            $_res[$k10]['shipping_usd'] = sprintf('%.2f' ,$this->CommonService->calculateRate(DEFAULT_CURRENCY,$_currency,$_res[$k10]['shipping_usd'],$rate_source));
                            $_res[$k10]['tax_handling_usd'] = sprintf('%.2f' ,$this->CommonService->calculateRate(DEFAULT_CURRENCY,$_currency,$_res[$k10]['tax_handling_usd'],$rate_source));
                        }
                    }
                    $_cart_info[$_customer_id]['nocdata'] = $_res;
                    if($from == 4) {
                        return ['code' => 1, 'msg'=>'success', 'data' => $_res];
                    }else{
                        return $_res;
                    }
                }
            }else{
                //没有NOCNOC，不需要处理
                if($from == 4) {
                    return ['code' => 1, 'msg'=>'success', 'data' =>''];
                }else{
                    return true;
                }
            }
        }
    }

    /**
     * 20180927修改：类别不用取dx_nocnoc_class表数据，如果是历史产品数据，则需要根据末级类别找到对应ERP的二级类别传过去；若非历史数据，则直接取产品二级类别即可。【默认使用740类别】
     * @return int|mixed
     */
    private function getNocClass($product_id, $sku_id){
        $class_id = 740;
        $class_id_cache_key = 'DX_NOC_CLASS_ID_KEY'.$product_id.$sku_id;
        if(config('cache_switch_on')) {
            $class_cache_id = $this->redis->get($class_id_cache_key);
        }
        if (empty($class_cache_id)){
            $params = ['product_id'=>$product_id];
            $url = MALL_API . '/mall/noc/getClass';
            $request = doCurl($url,$params, null, true);
            Monlog::write(LOGS_MALL,'info',__METHOD__,'getNocClass_res',$params,$url,$request);
            if ($request['code'] != 200) {
                return false;
            }
            $class_id = $request['class_id'];
            /*写入到redis中的购物车*/
            $this->redis->set($class_id_cache_key,$class_id,CACHE_DAY);
        }else{
            $class_id = $class_cache_id;
        }
        return $class_id;
    }

    /**
     * 判断是否有NOC的SKU
     * 这里只是判断，并不做相应的计算请求操作
     * 所有的计算操作，与写入redis操作，返回给前端的操作
     * 都在CommonService/claNocNocData方法中完成
     * @param $params
     * @param int $type 类型：1-cart，2-checkout
     * @return int
     */
    public function checkNocNoc($params, $type=1){
        $_customer_id = $params['customer_id'];
        $_is_buynow = $params['is_buynow'];
        if($_is_buynow){
            $_cart_info = $this->redis->get(SHOPPINGCART_BUYNOW_.$_customer_id);
        }else{
            if ($type == 1){
                //cart
                $_cart_info = $this->redis->get(SHOPPINGCART_.$_customer_id);
            }else{
                //checkout
                $_cart_info = $this->redis->get(SHOPPINGCART_CHECKOUT_.$_customer_id);
            }
        }
        if(isset($_cart_info[$_customer_id]['StoreData'])){
            foreach ($_cart_info[$_customer_id]['StoreData'] as $k => $v){
                if(isset($v['ProductInfo'])){
                    foreach ($v['ProductInfo'] as $k1=>$v1){
                        foreach ($v1 as $k2=>$v2){
                            if(strtolower($v2['ShippingMoel']) == 'nocnoc'){
                                return 1;
                                break;
                            }
                        }
                    }
                }

            }
        }
        return 0;
    }
}