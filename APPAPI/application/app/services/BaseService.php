<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\app\model\ConfigDataModel;
use app\app\model\ProductActivityModel;
use app\app\model\ProductClassModel;
use app\app\model\ProductModel;
use app\app\model\WishModel;
use think\Cache;
use think\cache\driver\Redis;
use think\Exception;
use think\Monlog;


/**
 * 基础接口
 */
class BaseService
{

    const BASE_CACHE = 'DX_MALL_BASESERVICE_';
    const CACHE_TIME = 3600;//一小时
    const DEFAULT_CURRENCY = 'USD';
    const DEFAULT_LANG = 'en';

    public $redis;
    protected $productModel;
    protected $classModel;
    private $parameters;
    private $key = 'phoenix';
    private $password = 'fU9wboOsRx9JQDA2';
    private $version = '8.8.8';
    public function __construct()
    {
        $this->classModel = new ProductClassModel();
        $this->productModel = new ProductModel();
        $this->redis = new Redis();
    }

    /**
     * 目前支持 -- 产品标题和描述的多语言
     * @param int $product_id 需要查找多语言的产品ID
     * @param string $lang  语种
     * @return array
     */
    public function getProductMultiLang($product_id,$lang){
        $productMultiLang = array();
        //获取产品标题和产品内容的多语言
        if(config('cache_switch_on')) {
            $productMultiLang =  $this->redis->get('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang);
        }
        if(empty($productMultiLang)){
            //获取这个产品的多语言缓存
            $productMultiLang = $this->productModel->getProductMultiLang($product_id,$lang);
            if (!empty($productMultiLang)) {
                $this->redis->set('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang, $productMultiLang, CACHE_HOUR);
            }
        }
        return $productMultiLang;
    }

    /**
     * 获取产品属性的多语言
     *
     * 颜色 --对应的ID --title对应翻译
     * 红色 --对应ID -- Option对应翻译
     * @param $attr_id
     * @param $option_id
     * @param $sku_id
     * @return array
     */
    public function getProductAttrMultiLang($attr_id,$option_id,$sku_id,$product_id){
        $productArrt = array();
        if(config('cache_switch_on')) {
            $productArrt =  $this->redis->get('PRODUCT_ATTR_LANGUAGE_' .$sku_id.'_'. $attr_id.'_'.$option_id);
        }
        try{
            if(empty($productArrt)){
                //大于10000的option_id在其他表
                if($option_id >= 10000){
                    $productArrt = $this->productModel->getProductCustomAttrMultiLangs($attr_id,$option_id.'_'.$sku_id,$product_id);
                }else{
                    $productArrt = $this->productModel->getProductAttrDefsLang($attr_id,$option_id.'_'.$sku_id);
                }
                if (!empty($productArrt)) {
                    $this->redis->set('PRODUCT_ATTR_LANGUAGE_'.$sku_id.'_'.$attr_id.'_'.$option_id, $productArrt, CACHE_HOUR);
                }
            }
            return $productArrt;
        }catch (Exception $e){
            return $e->getMessage();
        }

    }

    /**
     * 获取产品属性的多语言
     *
     * 颜色 --对应的ID --title对应翻译
     * 红色 --对应ID -- Option对应翻译
     * @param $attr_id
     * @param $option_id
     * @param $class_id
     * @return array
     */
    public function getProductAttrMultiLangNew($attr_id,$option_id,$class_id = 0){
        $old_option_id = $option_id;
        $productAttr = array();
        if(config('cache_switch_on')) {
            $productAttr =  $this->redis->get('PRODUCT_ATTR_LANGUAGE_NEW_' .$attr_id.'_'.$old_option_id);
        }
        try{
            if(empty($productAttr)){
                //判断是否有_字符
                if(strstr($option_id,'_') !== false){
                    //查询品牌类别表
                    $classData = $this->classModel->findAttributeByWhere(['_id'=>(int)$class_id],['_id','attribute']);
                    if(isset($classData['attribute'][$attr_id]['attribute_value']) && !empty($classData['attribute'][$attr_id]['attribute_value'])){
                        foreach($classData['attribute'][$attr_id]['attribute_value'] as $akey => $attr_value){
                            if($attr_value['id'] == $option_id){
                                //重新赋值，才能在 product_attr_multiLangs_new 找到多语言
                                $option_id = $akey;
                                break;
                            }
                        }
                    }
                }
                $productAttr = $this->productModel->getProductCustomAttrMultiLangsNew($attr_id,$option_id);
                if (!empty($productAttr)) {
                    //要替换成原来的option_id,因为产品表保存的是有_字符的
                    if(isset($productAttr['Options'][$option_id]) && !empty($productAttr['Options'][$option_id])){
                        $productAttr['Options'][$old_option_id] = $productAttr['Options'][$option_id];
                    }
                    $this->redis->set('PRODUCT_ATTR_LANGUAGE_NEW_'.$attr_id.'_'.$old_option_id, $productAttr, CACHE_HOUR);
                }
            }
            return $productAttr;
        }catch (Exception $e){
            return $e->getMessage();
        }

    }

    /**
     * 产品计量单位多语言
     * @param int $product_id 需要查找多语言的产品ID
     * @param string $lang  语种
     * @param string $title
     * @return array
     */
    public function getProductUnitTypeLang($product_id,$lang,$title){
        $productMultiLang = array();
        //获取产品标题和产品内容的多语言
        if(config('cache_switch_on')) {
            $productMultiLang =  $this->redis->get('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang.'_'.$title);
        }
        if(empty($productMultiLang)){
            //获取这个产品的多语言缓存
            $productMultiLang = $this->productModel->getProductUnitTypeLang(['lang'=>$lang,'title'=>$title]);
            if (!empty($productMultiLang)) {
                $this->redis->set('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang.'_'.$title, $productMultiLang, CACHE_HOUR);
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
        $rateRedis = array();
        if(config('cache_switch_on')){
            $rateRedis = $this->redis->get(EXCHANGE_RATE_.'LISTS');
        }
        if(empty($rateRedis)){
            try{
                $currency = doCurl(config("currency_url"));
                if(!empty($currency)){
                    $this->redis->set(EXCHANGE_RATE_.'LISTS',$currency,CACHE_HOUR);
                    $this->redis->set(EXCHANGE_RATE_.'FOREVER_LISTS',$currency);
                    $rateRedis = $currency;
                }else{
                    $rateRedis = $this->redis->get(EXCHANGE_RATE_.'FOREVER_LISTS');
                }
            }catch (Exception $e){
                //获取永久redis缓存费率
                $rateRedis = $this->redis->get(EXCHANGE_RATE_.'FOREVER_LISTS');
            }
        }
        if(empty($rateRedis) || !is_array($rateRedis)){
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,'rate null');
        }
        return $rateRedis;
    }

    /**
     * staffpick mvp discount presale规则
     */
    public function getProuctTags($product){
        $flag = '';
        $tags = array();
        if(config('cache_switch_on')) {
            $tags =  $this->redis->get(PRODUCT_TAGS_CONFIG);
        }
        if(empty($tags)){
            $tags = (new ConfigDataModel())->getDataConfig(['key' => 'ProductTag']);
            if (!empty($tags)) {
                $this->redis->set(PRODUCT_TAGS_CONFIG, $tags, CACHE_HOUR);
            }
        }
        //数据示例：基本配置内容
//        "key" => 'ProductTag',
//        'tags'=>[
//            [
//                'tag'=>'IsMVP'
//            ]
//        ]
        //默认config_data已经排序好的规则，如果不是，则需要重新按照priority升序
        foreach($tags['tags'] as $tag){
            //数据示例：产品表字段
            //  "IsStaffPick": true, //是否达人推荐产品
            //  "IsMVP": true, //MVP产品  (原salestag数组改为字段)
            if(isset($product[$tag['tag']]) && !empty($product[$tag['tag']])){
                if((int)$product[$tag['tag']] == true || (int)$product[$tag['tag']] > 0){
                    $flag = $tag['tag'];
                    break;
                }
            }else{
                //数据示例："Tags":{
                //"IsPresale":false,//是否预售产品
                //"IsDiscount":false//是否折扣产品
                //}
                if(isset($product['Tags'])){
                    if(isset($product['Tags'][$tag['tag']]) && !empty($product['Tags'][$tag['tag']])){
                        if((int)$product['Tags'][$tag['tag']] == true ||(int)$product['Tags'][$tag['tag']] > 0 ){
                            $flag = $tag['tag'];
                            break;
                        }
                    }
                }
            }
        }
        switch($flag){
            case 'IsMVP':
                return 'tag-mvp';//前端样式名称
                break;
            case 'IsStaffPick':
                return 'tag-sp';//前端样式名称
                break;
            case 'IsPresale':
                return 'tag-presale';//前端样式名称
                break;
            case 'IsDiscount':
                return 'tag-discount';//前端样式名称
                break;
            default:
                return '';
        }
    }

    /**
     * 产品列表 ---公用方法
     * @param $products 产品表数据
     * @param $params 当前语种和币种
     * @param $tag_name=null 图标
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
    public function commonProdcutListData($products,$params,$tag_name = null){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? $params['country'] : null;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $result = [];
        $currentRate = '';


        foreach($products as $k => $product){
            //产品id
            $result[$k]['id'] = isset($product['_id']) ? $product['_id'] : '';
            //首图
            $result[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
            if(empty($result[$k]['FirstProductImage'])){
                $result[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
            }
            //链接地址组合
            $result[$k]['LinkUrl'] ='/p/'.$product['RewrittenUrl'].'-'.$product['_id'];//链接地址
            //标题
            $result[$k]['Title'] = isset($product['Title']) ? $product['Title'] : '';
            //语言切换 --公共方法
            if(DEFAULT_LANG != $lang){
                $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);
                $result[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) && !empty($productMultiLang['Title'][$params['lang']]) ?
                    $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
            }



            //原价的价格区间
            $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
            $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

            //折扣后的价格区间,有些产品数据库保存的是字符串类型，NULL add by zhongning 2019-05-16
            $discountLowPrice = !empty($product['DiscountLowPrice']) && $product['DiscountLowPrice'] != 'NULL' ? (string)$product['DiscountLowPrice'] : '';//最低价格
            $discountHightPrice = !empty($product['DiscountHightPrice']) && $product['DiscountHightPrice'] != 'NULL' ? (string)$product['DiscountHightPrice'] : '';//最高价

            //价格逻辑处理
            $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
            //商品展示的销售价格
            $result[$k]['LowPrice'] = $priceArray['LowPrice'];
            $result[$k]['HightPrice'] = $priceArray['HightPrice'];
            $result[$k]['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
            $result[$k]['OriginalHightPrice'] =  $priceArray['OriginalHightPrice'];
            $result[$k]['Discount'] = 0;

            if(empty($product['IsActivity'])){
                //如果没有原价为空，那么原价就是市场价格
                //市场折扣逆推市场价，全球统一折扣功能 add by zhongning 20191107
                if(!empty($product['ListPriceDiscount'])){
                    $result[$k]['Discount'] = (string)$product['ListPriceDiscount'];
                    $result[$k]['OriginalLowPrice'] = (string)round($priceArray['LowPrice'] / (1 - $product['ListPriceDiscount']), 2);
                    if(!empty($priceArray['HightPrice'])){
                        $result[$k]['OriginalHightPrice'] = (string)round($priceArray['HightPrice'] / (1 - $product['ListPriceDiscount']), 2);
                    }
                }
            }

            //原价区间价格一样，高的为空
            if($result[$k]['OriginalLowPrice'] == $result[$k]['OriginalHightPrice']){
                $result[$k]['OriginalHightPrice'] = '';
            }

            //颜色
            $result[$k]['ColorCount'] = isset($product['ColorCount']) ? (int)$product['ColorCount'] : 0;

            //星级评分
            if(isset($product['AvgRating'])){
                $result[$k]['AvgRating'] = empty($product['AvgRating']) || (int)$product['AvgRating'] == 0 ? 5 : (int)$product['AvgRating'];
            }else{
                $result[$k]['AvgRating'] = 5;
            }

            //评论数
            $result[$k]['ReviewCount'] = isset($product['ReviewCount']) ? (int)$product['ReviewCount'] : 0;
            //运费状态  0免邮  1MVP 24小时到货提示 2不免邮
            $result[$k]['ShippingFee'] = isset($product['ShippingFee']) ? (int)$product['ShippingFee'] : 0;//是否免邮
            //是否是MVP产品
            $ismvp = isset($product['IsMVP']) && $product['IsMVP'] == true ? true : false;//是否免邮
            if($ismvp){
                $result[$k]['ShippingFee'] = 1;
            }else{
                if($result[$k]['ShippingFee'] != 0){
                    $result[$k]['ShippingFee'] =  2 ;
                }
            }

            $result[$k]['firstClassId'] = $product['FirstCategory'];
            $result[$k]['Discount'] = (string)0;
            //折扣展示
            if(!empty($product['HightDiscount'])){
                $result[$k]['Discount'] = (string)(1 - $product['HightDiscount']);
            }
        }
        return $result;
    }


    public function getFlashData($products,$params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $country = isset($params['country']) ? $params['country'] : null;//国家区域售价
        $avtivityModel = new ProductActivityModel();
        $time = time();

        foreach($products as $k => $product){
            if(empty($product['_id'])){
                continue;
            }
            //产品id
            $result[$k]['id'] = $product['_id'];
            //首图
            $result[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
            if(empty($result[$k]['FirstProductImage'])){
                $result[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
            }
            //链接地址组合
            $result[$k]['LinkUrl'] ='/p/'.$product['RewrittenUrl'].'-'.$product['_id'];//链接地址
            //标题
            $result[$k]['Title'] = isset($product['Title']) ? $product['Title'] : '';
            //语言切换 --公共方法
            if(self::DEFAULT_LANG != $lang){
                $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);
                $result[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) && !empty($productMultiLang['Title'][$params['lang']])
                    ? $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
            }

            //国家区域价格
            if(!empty($country)){
                $regionPrice = $this->getProductRegionPrice($product['_id'],$country);
                //这个产品有国家区域价格
                if(!empty($regionPrice)){
                    $this->handleProductRegionPrice($product,$regionPrice);
                }
            }
            //原价的价格区间
            $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
            $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

            //折扣后的价格区间
            $discountLowPrice = !empty($product['DiscountLowPrice']) ? (string)$product['DiscountLowPrice'] : '';//最低价格
            $discountHightPrice = !empty($product['DiscountHightPrice']) ? (string)$product['DiscountHightPrice'] : '';//最高价

            //价格逻辑处理
            $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
            //商品展示的销售价格
            $result[$k]['SalesPrice'] = $priceArray['LowPrice'];
            //原价
//            $result[$k]['OriginalPrice'] = $priceArray['OriginalLowPrice'];
            //如果有市场价，原价展示市场价，add by zhongning 20190507
            $result[$k]['OriginalPrice'] = !empty($product['LowListPrice']) ? (string)$product['LowListPrice'] : (string)$priceArray['OriginalLowPrice'];

            //折扣
            $result[$k]['Discount'] = !empty($product['HightDiscount']) ? (string)$product['HightDiscount'] : '';
	        //折扣按市场价格算
            if(!empty($product['LowListPrice'])){
                $result[$k]['Discount'] = (string)round($priceArray['LowPrice']/$product['LowListPrice'],2);
            }

            $result[$k]['firstClassId'] = (int)$product['FirstCategory'];
            $result[$k]['isActivity'] = isset($product['IsActivity']) ? (int)$product['IsActivity'] : 0;
            $result[$k]['isMvp'] = (int)$product['IsMVP'];

            //币种符号
            $result[$k]['currencyCode'] = DEFAULT_CURRENCY;
            $result[$k]['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
            if(self::DEFAULT_CURRENCY != $currency) {
                $result[$k]['currencyCode'] = $currency;
                $result[$k]['currencyCodeSymbol'] = $this->getCurrencyCode($currency);
            }

            //flashDeals产品肯定是折扣产品，展示折扣图标
            $result[$k]['tagName'] = 'tag-discount';

            if(isset($params['soon'])){
                //下一场活动未开始，进度条为0
                $result[$k]['TimeGone'] = 0;
            }else{
                //若该SPU下的默认主SKU的活动数量卖完，该SPU下的其他产品活动数量未卖完，则继续展示该产品的活动售价 （给其他产品引流）
                //活动数量进度条：按该SPU下的所有SKU的活动数量总和计算，
                //该SPU的下所有SKU的活动数量全部卖完时，则该SPU从Flash Deals首页移出，但仍在Flash Deals的活动列表页展示；
                if(isset($product['InventoryActivitySalse']) && !empty($product['InventoryActivitySalse'])){
                    $TimeGone = $product['InventoryActivitySalse'] / $product['InventoryActivity'];
                    //修复问题，TimeGone = 55.00000000000001，-1的问题；
                    if($TimeGone > 0){
                        $result[$k]['TimeGone'] = sprintf('%.2f',$TimeGone * 100);
                    }else{
                        $result[$k]['TimeGone'] = 0;
                    }
                }else{
                    $result[$k]['TimeGone'] = 0;
                }
            }

        }
        return $result;
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
        if(empty($discountLowPrice) && empty($discountHightPrice)){
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

        /*折扣产品更改规则，只针对某一个sku打折，导致出现价格如下问题，那么前端就会出现原价是4.79，售价是74.29 需要修复这个问题
        [LowPrice] =74.29
        [HightPrice] =;
        [OriginalLowPrice] =4.79
        [OriginalHightPrice] =130.37
        */
        if(!empty($result['LowPrice']) && empty($result['HightPrice']) && !empty($result['OriginalLowPrice']) && !empty($result['OriginalHightPrice'])){
            if($result['LowPrice'] > $result['OriginalLowPrice'] && $result['LowPrice'] < $result['OriginalHightPrice']){
                $result['OriginalLowPrice'] = $result['OriginalHightPrice'] ;
                $result['OriginalHightPrice'] = '';
            }
        }
        //前端展示价格逻辑判断  end
        return $result;
    }



    /**
     * 类别映射查询
     * @param $params
     * @return mixed
     */
    public function newCommonClassMap(&$params){
        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $class = (new ProductClassModel())->getClassDetail(['id'=>(int)$params['lastCategory']]);
            //{"code":100000005,"msg":"array_push() expects parameter 1 to be array, string given","data":[]} add by zhongning 20190610
            if(!empty($class['pdc_ids'])){
                $level = $class['level'];
                switch($level){
                    case 1:
                        array_push($class['pdc_ids'],$params['lastCategory']);
                        $params['lastCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                    case 2:
                        array_push($class['pdc_ids'],$params['lastCategory']);
                        $params['lastCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                    case 3:
                        array_push($class['pdc_ids'],$params['lastCategory']);
                        $params['lastCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                    case 4:
                        array_push($class['pdc_ids'],$params['lastCategory']);
                        $params['lastCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                }
            }
        }
    }

    /**
     * 类别映射查询
     * @param $params
     * @return mixed
     */
    public function commonClassMap(&$params){
        if(isset($params['category']) && !empty($params['category'])){
            $class = (new ProductClassModel())->getClassDetail(['id'=>(int)$params['category']]);
            if(!empty($class)){
                $level = $class['level'];
                switch($level){
                    case 1:
                        array_push($class['pdc_ids'],$params['category']);
                        $params['firstCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                    case 2:
                        array_push($class['pdc_ids'],$params['category']);
                        $params['secondCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                    case 3:
                        array_push($class['pdc_ids'],$params['category']);
                        $params['thirdCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                    case 4:
                        array_push($class['pdc_ids'],$params['category']);
                        $params['fourthCategory'] = CommonLib::supportArray($class['pdc_ids']);
                        break;
                }
            }
        }
    }

    /**
     * 单个class类别映射
     * @param $class_id
     * @return int
     */
    public function getMapClassByID($class_id){
        $class = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id]);
        if(!empty($class)&&is_array($class['pdc_ids'])){
            //admin后台新增的类别，没有映射，导致报错 add by zhongning 20190809 //array_push() expects parameter 1 to be array, string given
            if(!empty($class['pdc_ids']) && is_array($class['pdc_ids'])){
                array_push($class['pdc_ids'],$class_id);
            }else{
                return $class_id;
            }
            $class_id = CommonLib::supportArray($class['pdc_ids']);
        }
        return $class_id;
    }



    //banner图片数据整理
    public function getBannerInfos($data,$lang){
        $result = $currentData = $defaultData = array();
        if(isset($data['Banners']['BannerImages']['BannerFonts'])){
            $infos = $data['Banners']['BannerImages']['BannerFonts'];
            //当前语种数据
            $currentData = CommonLib::filterArrayByKey($infos,'Language',$lang);
            if($lang != DEFAULT_LANG){
                //取出默认是英文的数据
                $defaultData = CommonLib::filterArrayByKey($infos,'Language',DEFAULT_LANG);
            }
            if(empty($currentData)){
                $currentData = $defaultData;
            }
            if(isset($currentData['ImageUrl'])){
                foreach($currentData['ImageUrl'] as $key => $imgs){
                    //当切换语种的时候，其他数据为空的情况下，默认赋值英文的数据
                    $result[$key]['ImageUrl'] = $imgs;
                    if(empty($imgs) && $lang != DEFAULT_LANG){
                        $result[$key]['ImageUrl'] = isset($defaultData['ImageUrl'][$key]) ? $defaultData['ImageUrl'][$key] : '';
                    }

                    $result[$key]['LinkUrl'] = !empty($currentData['LinkUrl'][$key]) ? $currentData['LinkUrl'][$key] : '';
                    if(empty($currentData['LinkUrl'][$key]) && $lang != DEFAULT_LANG){
                        $result[$key]['LinkUrl'] = isset($defaultData['LinkUrl'][$key]) ? $defaultData['LinkUrl'][$key] : '';
                    }
                    $result[$key]['MainText'] = !empty($currentData['MainText'][$key]) ? $currentData['MainText'][$key] : '';
                    if(empty($currentData['MainText'][$key]) && $lang != DEFAULT_LANG){
                        $result[$key]['MainText'] = isset($defaultData['MainText'][$key]) ? $defaultData['MainText'][$key] : '';
                    }
                    $result[$key]['SubText'] = !empty($currentData['SubText'][$key]) ? $currentData['SubText'][$key] : '';
                    if(empty($currentData['SubText'][$key]) && $lang != DEFAULT_LANG){
                        $result[$key]['SubText'] = isset($defaultData['SubText'][$key]) ? $defaultData['SubText'][$key] : '';
                    }
                }
            }
        }
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
     * 币种符号
     * @param $currency 币种
     * @return string
     */
    public function getCurrencyCode($currency){
        if($currency != DEFAULT_CURRENCY){
            $currencyList = config("Currency");
            $arr = CommonLib::filterArrayByKey($currencyList,'Name',$currency);
            return isset($arr['Code']) ? $arr['Code'] : DEFAULT_CURRENCY_CODE;
        }
        return DEFAULT_CURRENCY_CODE;
    }


    /**
     * 国家区域产品价格
     * @param int $product_id 需要查找多语言的产品ID
     * @param string $country  国家
     * @return array
     */
    public function getProductRegionPrice($product_id,$country){
        $productPrice = array();
        //获取当前产品的区域价格
        if(config('cache_switch_on')) {
            $productPrice =  $this->redis->get('PRODUCT_COUNTRY_'.$product_id.'_'.$country);
        }
        if(empty($productPrice)){
            $productPrice = $this->productModel->getProductRegionPrice($product_id,$country);
            if (!empty($productPrice)) {
                $this->redis->set('PRODUCT_COUNTRY_'.$product_id.'_'.$country, $productPrice, CACHE_HOUR);
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
                    //售价
                    if(isset($skus['SalesPrice']) && !empty($skus['SalesPrice'])) {
                        $product['Skus'][$skey]['SalesPrice'] = isset($skuRegionPrice['SalesPrice']) && !empty($skuRegionPrice['SalesPrice'])
                            ? $skuRegionPrice['SalesPrice'] : $skus['SalesPrice'];
                    }
                    //批发价
                    if(isset($skus['BulkRateSet']['SalesPrice']) && !empty($skus['BulkRateSet']['SalesPrice'])) {
                        $product['Skus'][$skey]['BulkRateSet']['SalesPrice'] = isset($skuRegionPrice['BulkRateSet']['SalesPrice']) && !empty($skuRegionPrice['BulkRateSet']['SalesPrice'])
                            ? $skuRegionPrice['BulkRateSet']['SalesPrice'] : $skus['BulkRateSet']['SalesPrice'];
                    }
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

    /**
     * 获取接口access_token
     * @return mixed
     */
    public function getAccessToken(){
        return $this->makeSign();
    }
    /**
     * 获取参数值
     * @param $key
     * @return string
     */
    public function getParameter($key)
    {
        return isset($this->parameters[$key]) ? $this->parameters[$key] : '';
    }
    /**
     * 设置参数
     * @param string $key
     * @param string $value
     */
    public function setParameter($key = '', $value = '')
    {
        if (!is_null($value) && !is_bool($value)) {
            $this->parameters[$key] = $value;
        }
    }
    /**
     * @return string
     * @internal param array $params
     */
    public function toUrlParams()
    {
        $buff = "";
        foreach ($this->parameters as $k => $v) {
//            if ($k != "sign" && !is_null($v) && $k != '_url' && $k != '_file') {
            $buff .= $k . "=" . (is_array($v) ? json_encode($v) : $v) . "&";
//            }
        }
        $buff = trim($buff, "&");

        return $buff;
    }
    /**
     * 生成签名
     *
     * @return string
     */
    public function makeSign()
    {
        // 设置校验时间戳
        !$this->getParameter('_timestamp') && $this->setParameter('_timestamp', date('YmdH'));
        // 设置密码
        !$this->getParameter('_password') && $this->setParameter('_password', $this->password);
        // 设置版本号
//        !$this->getParameter('_version') && $this->setParameter('_version', $this->version);
        //签名步骤一：按字典序排序参数
        ksort($this->parameters);
        $string = $this->toUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . '&' . $this->key;
        //签名步骤三：MD5加密
        $result = md5($string);
        //所有字符转为小写
        $sign = strtolower($result);

        return $sign;
    }
}
