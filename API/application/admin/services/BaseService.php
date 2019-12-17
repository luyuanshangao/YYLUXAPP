<?php
namespace app\admin\services;

use app\common\helpers\CommonLib;
use app\admin\model\ProductModel;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\exception\HttpException;
use think\Log;
use think\Monlog;


/**
 * 基础接口
 */
class BaseService
{
    public $redis;
    protected $productModel;
    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->redis = new RedisClusterBase();
    }

    /**
     * 目前支持 -- 产品标题和描述的多语言
     * @param int $product_id 需要查找多语言的产品ID
     * @param string $lang  语种
     * @return array
     */
    public function getProductMultiLang($product_id,$lang){
        //获取产品标题和产品内容的多语言
        $productMultiLang =  $this->redis->get('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang);
        if(empty($productMultiLang)){
            //获取这个产品的多语言缓存
            $productMultiLang = $this->productModel->getProductMultiLang($product_id,$lang);
            if (!empty($productMultiLang)) {
                $this->redis->set('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang, $productMultiLang, 3600);
            }
        }
        return $productMultiLang;
    }

    /**
     * 币种费率
     * @param $key
     * @return mixed
     */
    public function getCurrencyRate($key){
        $rate = '';
        $currency = $this->getExchangeRate();
        if(!empty($currency)){
            foreach($currency as $value){
                if($value['To'] == $key){
                    $rate = $value['Rate'];break;
                }
            }
        }
        return $rate;
    }

    /**
     * 费率列表
     * @return array|mixed
     */
    private function getExchangeRate(){
        $rateRedis = $this->redis->get('EXCHANGE_RATE_LISTS');
        if(empty($rateRedis)){
            try{
                $currency = doCurl(config("currency_url"));
                if(!empty($currency)){
                    $this->redis->set('EXCHANGE_RATE_LISTS',$currency,3600);
                    $this->redis->set('EXCHANGE_RATE_FOREVER_LISTS',$currency);
                    $rateRedis = $currency;
                }else{
                    $rateRedis = $this->redis->get('EXCHANGE_RATE_FOREVER_LISTS');
                }
            }catch (Exception $e){
                //获取永久redis缓存费率
                $rateRedis = $this->redis->get('EXCHANGE_RATE_FOREVER_LISTS');
            }
        }
        if(empty($rateRedis) || !is_array($rateRedis)){
            Log::record('getExchangeRate'.__METHOD__.__FUNCTION__.'rate null','error');
        }
        return $rateRedis;
    }

    private function doCurl($url,$data = null,$options = null,$isPost = false,$header = null){
        if (is_array($data)) {
            $data = json_encode($data);
        }

        $ch = curl_init();
        if (!empty($options)) {
            $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (is_null($header)) {
            $header = array(
                "Content-type:application/json"
            );
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //设置头信息的地方
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $data = curl_exec($ch);
        //$this->errorCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //HTTPSTAT
        curl_close($ch);

        return json_decode($data,true);
    }


    /**
     * 产品列表 ---公用方法
     * @param $products 产品表数据
     * @param $params 当前语种和币种
     *
     * 展示内容为图片
     * 名称
     * 价格（展示区间价格，该SPU的最低价至最高价）
     * 颜色（销售属性中有颜色的，展示颜色数，如4 colors）
     * 星级评分
     * 评论数
     * Free Shipping（物流方式为此项的展示）
     * tagName 图标
     *
     * @return array
     */
    public function commonProdcutDataToDownLoad($products,$params){
        $result = [];

        foreach($products as $k => $product){
            //产品id
            $result[$k]['id'] = isset($product['_id']) ? $product['_id'] : '';
            //首图
            $result[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
            if(empty($result[$k]['FirstProductImage'])){
                $result[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
            }
            $result[$k]['FirstProductImage_210'] = '';
            if(!empty($result[$k]['FirstProductImage'])){
                $img = explode('.',$result[$k]['FirstProductImage']);
                $result[$k]['FirstProductImage_210'] = 'http://img.dxcdn.com'.$img[0].'_210x210.'.$img[1];
            }
            $result[$k]['FirstProductImage'] = 'http://img.dxcdn.com'.$result[$k]['FirstProductImage'];

            //链接地址组合
            $result[$k]['LinkUrl'] ='https://dx.com/p/'.$product['RewrittenUrl'].'-'.$product['_id'];//链接地址
            //标题
            $result[$k]['Title'] = isset($product['Title']) ? htmlspecialchars($product['Title']) : '';
            $result[$k]['Descriptions'] = isset($product['Descriptions']) ? htmlspecialchars_decode($product['Descriptions']) : '';
            //语言切换 --公共方法
            if('en' != $params['lang']){
                $productMultiLang = $this->getProductMultiLang($product['_id'],$params['lang']);
                $result[$k]['Title'] = !empty($productMultiLang['Title'][$params['lang']]) ?
                    $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
                $result[$k]['Descriptions'] = !empty($productMultiLang['Descriptions'][$params['lang']]) ?
                        $productMultiLang['Descriptions'][$params['lang']] : $product['Descriptions'];//默认英语
            }

            //国家区域价格
            if(!empty($country)){
                $regionPrice = $this->getProductRegionPrice($product['_id'],$country);
                //这个产品有国家区域价格
                if(!empty($regionPrice)){
                    //这个产品有国家区域价格
                    $this->handleProductRegionPrice($product,$regionPrice);
                }
            }

            //原价的价格区间
            $originalLowPrice = isset($product['LowPrice']) ? sprintf('%01.2f',$product['LowPrice']) : '';//最低价格
            $originalHightPrice = isset($product['HightPrice']) ? sprintf('%01.2f',$product['HightPrice']) : '';//最高价

            //折扣后的价格区间
            $discountLowPrice = isset($product['DiscountLowPrice']) ? sprintf('%01.2f',$product['DiscountLowPrice']) : '';//最低价格
            $discountHightPrice = isset($product['DiscountHightPrice']) ? sprintf('%01.2f',$product['DiscountHightPrice']) : '';//最高价

            //价格逻辑处理
            $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
            //商品展示的销售价格
            $result[$k]['LowPrice'] = $priceArray['LowPrice'];
            //币种切换
            if('USD' != $params['currency']){
                $rate = $this->getCurrencyRate($params['currency']);
                if(!empty($rate)){
                    $result[$k]['LowPrice'] = sprintf("%01.2f",$result[$k]['LowPrice'] * $rate);
                }
            }
            //价格币种符号
            $result[$k]['LowPrice_Code'] = $params['currency_code'].$result[$k]['LowPrice'];
            //币种符号
            $result[$k]['currency_code'] = $params['currency_code'];
//            $result[$k]['HightPrice'] = $priceArray['HightPrice'];
            //原价
//            $result[$k]['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
//            $result[$k]['OriginalHightPrice'] = $priceArray['OriginalHightPrice'];

            $result[$k]['firstClassName'] = $this->getClassName($product['FirstCategory'],$params['lang']);
            $result[$k]['secondClassName'] = $this->getClassName($product['SecondCategory'],$params['lang']);
            if(!empty($params['platform']) && $params['platform'] == 'Shareasale'){
                //增加默认数据
                $result[$k]['Category'] = 6;
                $result[$k]['SubCategory'] = 47;
                $result[$k]['Status'] = 'instock';
                $result[$k]['Your_MerchantID'] = 32431;
                $result[$k]['Custom_1'] = 'DealeXtreme';
            }
        }
        return $result;
    }

    /**
     * 获取类别名称
     * @param $id
     * @param $lang
     * @return mixed|null
     */
    public function getClassName($id,$lang){
        $data = $this->productModel->getClassDetail(['id'=>(int)$id],$lang);
        if(!empty($data)){
            if('en' != $lang){
                $data['title_en'] = !empty($data['Common'][$lang]) ? $data['Common'][$lang] : $data['title_en'];//默认英语
            }
            return $data['title_en'];
        }
        return null;
    }

    /**
     * 商品价格前端展示逻辑处理
     * @param $originalLowPrice
     * @param $originalHightPrice
     * @param $discountLowPrice
     * @param $discountHightPrice
     * @return mixed
     */
    public function commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice){
        //商品展示的销售价格
        $result['LowPrice'] = '';
        $result['HightPrice'] = '';
        //原价
        $result['OriginalLowPrice'] = '';
        $result['OriginalHightPrice'] = '';

        //前端展示价格逻辑判断  start
        // 商品无折扣
        if(empty($discountLowPrice) || empty($discountHightPrice) || $discountLowPrice == '0.00' || $discountHightPrice == '0.00'){
            if($originalLowPrice == '0.00'){
                $originalLowPrice = $originalHightPrice;
            }
            $result['LowPrice'] = $originalLowPrice;
            //原价是否相等
            if($originalLowPrice != $originalHightPrice){
                $result['HightPrice'] = $originalHightPrice;
            }
        }else{
            //折扣价格是否相等
            if($discountLowPrice == $discountHightPrice){
                $result['LowPrice'] = $discountLowPrice;
                //原价是否相等
                if($originalLowPrice == $originalHightPrice){
                    $result['OriginalLowPrice'] = $originalLowPrice;
                    $result['OriginalHightPrice'] = '';
                }else{
                    $result['OriginalLowPrice'] = $originalLowPrice;
                    $result['OriginalHightPrice'] = $originalHightPrice;
                }
            }else{
                //如果折扣价格比原价还高
                if($discountLowPrice > $originalLowPrice){
                    //原价是否相等
                    if($originalLowPrice == $originalHightPrice){
                        $result['LowPrice'] = $originalLowPrice;
                        $result['HightPrice'] = '';
                    }else{
                        $result['LowPrice'] = $originalLowPrice;
                        $result['HightPrice'] = $originalHightPrice;
                    }
                    $result['OriginalLowPrice'] = $discountLowPrice;
                    $result['OriginalHightPrice'] = $discountHightPrice;

                }else{
                    //原价是否相等
                    if($originalLowPrice == $originalHightPrice){
                        $result['OriginalLowPrice'] = $originalLowPrice;
                        $result['OriginalHightPrice'] = '';
                    }else{
                        $result['OriginalLowPrice'] = $originalLowPrice;
                        $result['OriginalHightPrice'] = $originalHightPrice;
                    }
                    $result['LowPrice'] = $discountLowPrice;
                    $result['HightPrice'] = $discountHightPrice;
                }
            }
        }
        //判断折扣价和原价是否相等
        if($result['LowPrice'] == $result['OriginalLowPrice']){
            $result['OriginalLowPrice'] = '';
        }

        if($result['LowPrice'] == '0.00'){
            $result['LowPrice'] = '';
        }
        if($result['HightPrice'] == '0.00'){
            $result['HightPrice'] = '';
        }
        if($result['OriginalLowPrice'] == '0.00'){
            $result['OriginalLowPrice'] = '';
        }
        if($result['OriginalHightPrice'] == '0.00'){
            $result['OriginalHightPrice'] = '';
        }
        //前端展示价格逻辑判断  end
        return $result;
    }


    /**
     * 币种切换
     * @param array $products 产品数据
     * @param string $currency 切换的币种
     * @return array
     */
    public function changeCurrentRate($products,$currency){
        $currentRate = $this->getCurrencyRate($currency);
        foreach($products as $key => $val){

            if(isset($val['HightPrice']) && !empty($val['HightPrice']) && $val['HightPrice'] != '0.00'){
                $products[$key]['HightPrice'] = sprintf("%01.2f",(double)$val['HightPrice'] * $currentRate);
            }
            if(isset($val['LowPrice']) && !empty($val['LowPrice']) && $val['LowPrice'] != '0.00'){
                $products[$key]['LowPrice'] = sprintf("%01.2f",(double)$val['LowPrice'] * $currentRate);
            }
            if(isset($val['OriginalHightPrice']) && !empty($val['OriginalHightPrice']) && $val['OriginalHightPrice'] != '0.00'){
                $products[$key]['OriginalHightPrice'] = sprintf("%01.2f",(double)$val['OriginalHightPrice'] * $currentRate);
            }
            if(isset($val['OriginalLowPrice']) && !empty($val['OriginalLowPrice']) && $val['OriginalLowPrice'] != '0.00'){
                $products[$key]['OriginalLowPrice'] = sprintf("%01.2f",(double)$val['OriginalLowPrice'] * $currentRate);
            }
        }
        return $products;
    }

    /**
     * 国家区域产品价格
     * @param int $product_id 需要查找多语言的产品ID
     * @param string $country  国家
     * @return array
     */
    public function getProductRegionPrice($product_id,$country){
        //获取当前产品的区域价格
        $productPrice =  $this->redis->get('PRODUCT_COUNTRY_'.$product_id.'_'.$country);
        if(empty($productPrice)){
            $productPrice = $this->productModel->getProductRegionPrice($product_id,$country);
            if (!empty($productPrice)) {
                $this->redis->set('PRODUCT_COUNTRY_'.$product_id.'_'.$country, $productPrice, 3600);
            }
        }
        return $productPrice;
    }

    /**
     * 处理国家产品价格
     * @param array $product 产品数据
     * @param array $regionPrice 产品国家价格数据
     */
    public function handleProductRegionPrice(&$product,$regionPrice){
        //最低价格区间
        $product['LowPrice'] = isset($regionPrice['LowPrice']) ? $regionPrice['LowPrice'] : $product['LowPrice'];
        $product['HightPrice'] = isset($regionPrice['HightPrice']) ? $regionPrice['HightPrice'] : $product['HightPrice'];

        $discountArray = array();
        //sku价格
        if(isset($product['Skus']) && !empty($product['Skus']) && isset($regionPrice['Skus']) && !empty($regionPrice['Skus'])){
            foreach($product['Skus'] as $skey => $skus){
                //通过skuid
                $skuRegionPrice = CommonLib::filterArrayByKey($regionPrice['Skus'],'_id',$skus['_id']);
                if(!empty($skuRegionPrice)){
//                    //售价
//                    if(isset($skus['SalesPrice']) && !empty($skus['SalesPrice'])) {
//                        $product['Skus'][$skey]['SalesPrice'] = isset($skuRegionPrice['SalesPrice']) && !empty($skuRegionPrice['SalesPrice'])
//                            ? $skuRegionPrice['SalesPrice'] : $skus['SalesPrice'];
//                    }
//                    //批发价
//                    if(isset($skus['BulkRateSet']['SalesPrice']) && !empty($skus['BulkRateSet']['SalesPrice'])) {
//                        $product['Skus'][$skey]['BulkRateSet']['SalesPrice'] = isset($skuRegionPrice['BulkRateSet']['SalesPrice']) && !empty($skuRegionPrice['BulkRateSet']['SalesPrice'])
//                            ? $skuRegionPrice['BulkRateSet']['SalesPrice'] : $skus['BulkRateSet']['SalesPrice'];
//                    }
                    //活动价格,活动结束后，数据不会清除，要加条件判断
                    if(isset($product['IsActivity']) && $product['IsActivity'] > 0) {
                        if (isset($skus['ActivityInfo']) && !empty($skus['ActivityInfo'])) {
                            $product['Skus'][$skey]['ActivityInfo']['DiscountPrice'] = sprintf('%01.2f', $skuRegionPrice['SalesPrice'] * $skus['ActivityInfo']['Discount']);
                            $discountArray[] = $product['Skus'][$skey]['ActivityInfo']['DiscountPrice'];
                        }
                    }
                }
            }
        }
        //折扣最低价格，最高价格
        if(!empty($discountArray)){
            $discountLowPrice = min($discountArray);
            $discountHightPrice = max($discountArray);
            $product['DiscountLowPrice'] = $discountLowPrice;
            $product['DiscountHightPrice'] = $discountHightPrice;
        }

    }
}
