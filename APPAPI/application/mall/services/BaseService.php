<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\mall\model\ConfigDataModel;
use app\mall\model\ProductActivityModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductModel;
use app\mall\model\WishModel;
use think\Cache;
use think\Exception;
use think\exception\HttpException;
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
    public function __construct()
    {
        $this->classModel = new ProductClassModel();
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
    public function commonProdcutListData($products,$params,$tag_name = null,$log = false){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = !empty($params['country']) && $params['country'] != 'null' ? trim($params['country']) : null;
//        $currency = isset($params['currency']) ? $params['currency'] : self::DEFAULT_CURRENCY;
        $result = [];
        //国家价格，多语言使用，一次性查询
        $product_ids = CommonLib::getColumn('_id',$products);
        if(empty($product_ids)){
            return $result;
        }
//        $in_product_ids = CommonLib::supportArray(CommonLib::getColumn('_id',$products));
        //语言切换 --公共方法
        if(DEFAULT_LANG != $lang){
            $productMultiLang = $this->selectProductMultiLang(['lang' => $lang,'product_ids'=>$product_ids]);
        }
        //国家区域价格
        if(!empty($country)){
            $regionPrice = $this->selectProductRegionPrice(['country' => $country,'product_ids'=>$product_ids]);
        }
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
            $result[$k]['Title'] = isset($product['Title']) ? htmlspecialchars($product['Title']) : '';
            //语言切换 --公共方法
            if(DEFAULT_LANG != $lang){
                $result[$k]['Title'] = !empty($productMultiLang[$product['_id']]['Title'][$lang]) ?
                    $productMultiLang[$product['_id']]['Title'][$lang] : $product['Title'];//默认英语
            }
            //国家区域价格
            if(!empty($country) && !empty($regionPrice[$product['_id']])){
                //这个产品有国家区域价格
                $this->handleProductRegionPrice($product,$regionPrice[$product['_id']]);
            }

            //原价的价格区间
            $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
            $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

            //折扣后的价格区间,有些产品数据库保存的是字符串类型，NULL add by zhongning 2019-05-16
            $discountLowPrice = !empty($product['DiscountLowPrice']) && $product['DiscountLowPrice'] != 'NULL' ? (string)$product['DiscountLowPrice'] : '';//最低价格
            $discountHightPrice = !empty($product['DiscountHightPrice']) && $product['DiscountHightPrice'] != 'NULL' ? (string)$product['DiscountHightPrice'] : '';//最高价

            $result[$k]['IsActivity'] = !empty($product['IsActivity'])? $product['IsActivity'] : 0;
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
//                $result[$k]['OriginalLowPrice'] = !empty($product['LowListPrice']) && $product['LowListPrice'] > $priceArray['LowPrice'] ?
//                    (string)$product['LowListPrice'] : $priceArray['OriginalLowPrice'];
//                $result[$k]['OriginalHightPrice'] = !empty($product['HighListPrice']) && $product['LowListPrice'] > $priceArray['LowPrice'] ?
//                    (string)$product['HighListPrice'] : $priceArray['OriginalHightPrice'];
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
            $result[$k]['ColorCount'] = isset($product['ColorCount']) ? $product['ColorCount'] : 0;
            //星级评分
            if(isset($product['AvgRating'])){
                $result[$k]['AvgRating'] = empty($product['AvgRating']) || (int)$product['AvgRating'] == 0 ? 5 : $product['AvgRating'];
            }else{
                $result[$k]['AvgRating'] = 5;
            }
            //评论数
            $result[$k]['ReviewCount'] = isset($product['ReviewCount']) ? $product['ReviewCount'] : 0;
            //运费状态  0免邮  1MVP 24小时到货提示 2不免邮
            $result[$k]['ShippingFee'] = isset($product['ShippingFee']) ? $product['ShippingFee'] : 0;//是否免邮
            //是否是MVP产品
            $ismvp = isset($product['IsMVP']) && $product['IsMVP'] == true ? true : false;//是否免邮
            if($ismvp){
                $result[$k]['ShippingFee'] = $result[$k]['ShippingFee'] == 0 ? 1 : 3;//1:免邮24小时到货提示,3:24小时到货提示
            }else{
                $result[$k]['ShippingFee'] = $result[$k]['ShippingFee'] != 0 ? 2 : $result[$k]['ShippingFee'];
            }

            //是否是MVP，预售，折扣，达人推荐商品
            //tagName 前端展示图标使用
            $result[$k]['tagName'] = $this->getProuctTags($product);
            if($tag_name == 'presale'){
                $result[$k]['tagName'] = 'tag-presale';
            }

            $result[$k]['firstClassId'] = $product['FirstCategory'];

            $result[$k]['VideoCode'] = isset($product['VideoCode']) && !empty($product['VideoCode']) ? $product['VideoCode'] : null;
            //添加时间
            $result[$k]['AddTime'] = isset($product['AddTime']) ? $product['AddTime'] : null;

            //折扣展示
            if(!empty($product['HightDiscount'])){
                $result[$k]['Discount'] = (string)(1 - $product['HightDiscount']);
            }
//            else{
                //市场折扣逆推市场价，全球统一折扣功能 add by zhongning 20191107
//                if(!empty($result[$k]['LowPrice']) && !empty($result[$k]['OriginalLowPrice'])){
//                    if($result[$k]['OriginalLowPrice'] > $result[$k]['LowPrice']){
//                        $result[$k]['Discount'] = (string)round(($result[$k]['OriginalLowPrice'] - $result[$k]['LowPrice']) / $result[$k]['OriginalLowPrice'],2);
//                    }
//                }
//            }
            //LP页面增加状态标识，add by zhongning 20191031
            $result[$k]['ProductStatus'] = isset($product['ProductStatus']) ? $product['ProductStatus'] : 1;
//            $product['ListPriceDiscount'] = !empty($product['ListPriceDiscount']) ? $product['ListPriceDiscount'] : 0;
//            $result[$k]['Discount'] = !empty($product['HightDiscount']) ? (string)(1 - $product['HightDiscount']) : (string)$product['ListPriceDiscount'];

            //如果tagName为空，那么有折扣就展示折扣标志
            if(empty($result[$k]['tagName']) && !empty($result[$k]['Discount'])){
                $result[$k]['tagName'] = 'tag-discount';
            }
        }
        return $result;
    }


    public function getFlashData($products,$params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $country = isset($params['country']) ? trim($params['country']) : null;//国家区域售价
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
            $discountLowPrice = !empty($product['DiscountLowPrice']) && $product['DiscountLowPrice'] != 'NULL' ? (string)$product['DiscountLowPrice'] : '';//最低价格
            $discountHightPrice = !empty($product['DiscountHightPrice']) && $product['DiscountHightPrice'] != 'NULL' ? (string)$product['DiscountHightPrice'] : '';//最高价

            //价格逻辑处理
            $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
            //商品展示的销售价格
            $result[$k]['SalesPrice'] = $priceArray['LowPrice'];
            //原价
//            $result[$k]['OriginalPrice'] = $priceArray['OriginalLowPrice'];
            //如果有市场价，原价展示市场价，add by zhongning 20190507
            //折扣价最大，优先使用折扣价格,不然会出现价格错乱
//            $result[$k]['OriginalPrice'] = !empty($product['LowListPrice']) && $product['LowListPrice'] > $priceArray['OriginalLowPrice'] ? (string)$product['LowListPrice'] : (string)$priceArray['OriginalLowPrice'];
            $result[$k]['OriginalPrice'] = (string)$priceArray['OriginalLowPrice'];

            //折扣
            $result[$k]['Discount'] = !empty($product['HightDiscount']) ? (string)$product['HightDiscount'] : '';
            //市场价比原价大,折扣按市场价格算
//            if(!empty($product['LowListPrice']) && $priceArray['LowPrice'] < $product['LowListPrice']){
//                $result[$k]['Discount'] = (string)round($priceArray['LowPrice']/$product['LowListPrice'],2);
//            }

            //移动端特殊需求，flashDeal数量不够，拼市场价产品
            if (empty($product['IsActivity'])) {
                //如果没有原价为空，那么原价就是市场价格
                //市场折扣逆推市场价功能 add by zhongning 20191107
                if(!empty($product['ListPriceDiscount'])){
                    $result[$k]['Discount'] = (string)(1 - $product['ListPriceDiscount']);
                    $result[$k]['OriginalPrice'] = (string)round($priceArray['LowPrice'] / (1 - $product['ListPriceDiscount']), 2);
                }else{
                    $result[$k]['Discount'] = $result[$k]['OriginalPrice'] = 0;
                }
//                $result[$k]['OriginalPrice'] = !empty($product['LowListPrice']) && $product['LowListPrice'] > $priceArray['OriginalLowPrice'] ?
//                    (string)$product['LowListPrice'] : $priceArray['OriginalLowPrice'];
//                $discount = ($result[$k]['OriginalPrice'] - $result[$k]['SalesPrice']) / $result[$k]['OriginalPrice'];
//                $result[$k]['Discount'] = (string)(1 - round($discount, 2));
            }

            //运费状态  0免邮  1MVP 24小时到货提示 2不免邮
            $result[$k]['ShippingFee'] = isset($product['ShippingFee']) ? $product['ShippingFee'] : 0;//是否免邮
            //是否是MVP产品
            $ismvp = isset($product['IsMVP']) && $product['IsMVP'] == true ? true : false;//是否免邮
            if($ismvp){
                $result[$k]['ShippingFee'] = $result[$k]['ShippingFee'] == 0 ? 1 : 3;//1:免邮24小时到货提示,3:24小时到货提示
            }else{
                $result[$k]['ShippingFee'] = $result[$k]['ShippingFee'] != 0 ? 2 : $result[$k]['ShippingFee'];
            }

            $result[$k]['firstClassId'] = $product['FirstCategory'];
            //flashDeals产品肯定是折扣产品，展示折扣图标
            $result[$k]['tagName'] = 'tag-discount';

            //销售数量
            $result[$k]['SalesCounts'] = !empty($product['SalesCounts']) ? $product['SalesCounts'] : 1;

            if(isset($params['soon'])){
                //下一场活动未开始，进度条为0
                $result[$k]['TimeGone'] = 0;
            }else{
                //若该SPU下的默认主SKU的活动数量卖完，该SPU下的其他产品活动数量未卖完，则继续展示该产品的活动售价 （给其他产品引流）
                //活动数量进度条：按该SPU下的所有SKU的活动数量总和计算，
                //该SPU的下所有SKU的活动数量全部卖完时，则该SPU从Flash Deals首页移出，但仍在Flash Deals的活动列表页展示；
                if(isset($product['InventoryActivitySalse']) && !empty($product['InventoryActivitySalse'])&& !empty($product['InventoryActivity'])){
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
            }else{
                $params['lastCategory'] = (int)$params['lastCategory'];
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
        if(!empty($class)){
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

        //最低价格区间,如果删除其中一个sku，国家定价里面没有删除，那么区间价就有误，add by zhongning 20190708
        $product['LowPrice'] = isset($regionPrice['LowPrice']) ? $regionPrice['LowPrice'] : $product['LowPrice'];
        $product['HightPrice'] = isset($regionPrice['HightPrice']) ? $regionPrice['HightPrice'] : $product['HightPrice'];
        $discountArray = $priceRange = array();
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
                        $priceRange[] = $product['Skus'][$skey]['SalesPrice'];
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

        //产品区间价格,add by zhongning 20190708
        if(!empty($priceRange)){
            $product['LowPrice'] = min($priceRange);
            $product['HightPrice'] = max($priceRange);
        }
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
     * 产品标题和描述的多语言-in查询
     * @param int $product_ids 需要查找多语言的产品IDS
     * @param string $lang  语种
     * @return array
     */
    public function selectProductMultiLang($params){
//        $cache_key = CommonLib::getCacheKey($params);
//        $productMultiLang = array();
        //获取产品标题和产品内容的多语言
        //注释缓存，reis数据内存不能过大 addby zhongning 20190910
//        if(config('cache_switch_on')) {
//            $productMultiLang =  $this->redis->get('PRODUCT_LANGUAGE_'.$params['lang'].'_'.$cache_key);
//        }
//        if(empty($productMultiLang)){
            //获取这个产品的多语言缓存
            $productMultiLang = $this->productModel->getProductMultiLang($params['product_ids'],$params['lang']);
            if (!empty($productMultiLang)) {
                $productMultiLang = array_column($productMultiLang, NULL, '_id');
//                $this->redis->set('PRODUCT_LANGUAGE_'.$params['lang'].'_'.$cache_key, $productMultiLang, CACHE_HOUR);
            }
//        }
        return $productMultiLang;
    }

    /**
     * 国家区域产品价格
     * @param int $product_id 需要查找多语言的产品ID --in查询
     * @param string $country  国家
     * @return array
     */
    public function selectProductRegionPrice($params){
        $cache_key = CommonLib::getCacheKey($params);
        $country = isset($params['country']) ? trim($params['country']) : null;
        $productPrice = array();
        //获取当前产品的区域价格
        if(config('cache_switch_on')) {
            $productPrice =  $this->redis->get('PRODUCT_COUNTRY_'.$country.'_'.$cache_key);
        }
        if(empty($productPrice)){
            $productPrice = $this->productModel->getProductRegionPrice($params['product_ids'],$country);
            if (!empty($productPrice)) {
                $productPrice = array_column($productPrice, NULL, 'Spu');
                $this->redis->set('PRODUCT_COUNTRY_'.$country.'_'.$cache_key, $productPrice, CACHE_HOUR);
            }
        }
        return $productPrice;
    }


    /**
     * 首页展示coupon使用
     * @param $products
     * @param $params
     * @return array
     */
    public function commonCouponProductData($products,$params,$coupon = 0){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? trim($params['country']) : null;
        $result = [];
        //国家价格，多语言使用，一次性查询
        $product_ids = CommonLib::getColumn('_id',$products);
        if(empty($product_ids)){
            return $result;
        }
        //语言切换 --公共方法
//        if(DEFAULT_LANG != $lang){
//            $productMultiLang = $this->selectProductMultiLang(['lang' => $lang,'product_ids'=>$product_ids]);
//        }
        //国家区域价格
        if(!empty($country)){
            $regionPrice = $this->selectProductRegionPrice(['country' => $country,'product_ids'=>$product_ids]);
        }
        foreach($products as $k => $product){
            //产品id
            $result[$k]['id'] = isset($product['_id']) ? $product['_id'] : '';
            //首图
            $result[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
            if(empty($result[$k]['FirstProductImage'])){
                $result[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
            }
            //链接地址组合
//            $result[$k]['LinkUrl'] ='/p/'.$product['RewrittenUrl'].'-'.$product['_id'];//链接地址
            //标题
//            $result[$k]['Title'] = isset($product['Title']) ? htmlspecialchars($product['Title']) : '';
            //语言切换 --公共方法
//            if(DEFAULT_LANG != $lang){
//                $result[$k]['Title'] = !empty($productMultiLang[$product['_id']]['Title'][$lang]) ?
//                    $productMultiLang[$product['_id']]['Title'][$lang] : $product['Title'];//默认英语
//            }
            //国家区域价格
            if(!empty($country) && !empty($regionPrice[$product['_id']])){
                //这个产品有国家区域价格
                $this->handleProductRegionPrice($product,$regionPrice[$product['_id']]);
            }

            //原价的价格区间
            $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
            $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

            //折扣后的价格区间
            $discountLowPrice = !empty($product['DiscountLowPrice']) ? (string)$product['DiscountLowPrice'] : '';//最低价格
            $discountHightPrice = !empty($product['DiscountHightPrice']) ? (string)$product['DiscountHightPrice'] : '';//最高价
            $discountLowPrice = $discountLowPrice == 'NULL' ? '' : $discountLowPrice;
            $discountHightPrice = $discountHightPrice == 'NULL' ? '' : $discountHightPrice;
            $isActivity = !empty($product['IsActivity'])? $product['IsActivity'] : 0;
            //价格逻辑处理
            $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
            //商品展示的销售价格
            $result[$k]['LowPrice'] = $priceArray['LowPrice'];
            $result[$k]['HightPrice'] = $priceArray['HightPrice'];
            if($isActivity > 0){
                $result[$k]['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
                $result[$k]['OriginalHightPrice'] =  $priceArray['OriginalHightPrice'];
            }else{
                //如果没有原价为空，那么原价就是市场价格
                $result[$k]['OriginalLowPrice'] = !empty($product['LowListPrice']) && $product['LowListPrice'] > $priceArray['LowPrice'] ?
                    (string)$product['LowListPrice'] : $priceArray['OriginalLowPrice'];
                $result[$k]['OriginalHightPrice'] = !empty($product['HighListPrice']) && $product['LowListPrice'] > $priceArray['LowPrice'] ?
                    (string)$product['HighListPrice'] : $priceArray['OriginalHightPrice'];
            }

            //原价区间价格一样，高的为空
            if($result[$k]['OriginalLowPrice'] == $result[$k]['OriginalHightPrice']){
                $result[$k]['OriginalHightPrice'] = '';
            }
            //获取类别名称
            $path = explode('-',$product['CategoryPath']);
            $classinfo = $this->classModel->getClassDetail(['id' =>(int)(array_pop($path))],$lang);
            $result[$k]['categoryName'] = !empty($classinfo['title_en']) ? $classinfo['title_en'] : 'Category';
            if(DEFAULT_LANG != $lang){
                $result[$k]['categoryName'] = !empty($classinfo['Common'][$lang]) ?
                    $classinfo['Common'][$lang] : $result[$k]['categoryName'];//默认英语
            }
            $result[$k]['CouponId'] = $coupon;
//            $result[$k]['Discount'] = 0;
            //折扣展示
//            if(!empty($product['HightDiscount'])){
//                $result[$k]['Discount'] = (string)(1 - $product['HightDiscount']);
//            }else{
//                if(!empty($result[$k]['LowPrice']) && !empty($result[$k]['OriginalLowPrice'])){
//                    if($result[$k]['OriginalLowPrice'] > $result[$k]['LowPrice']){
//                        $result[$k]['Discount'] = (string)round(($result[$k]['OriginalLowPrice'] - $result[$k]['LowPrice']) / $result[$k]['OriginalLowPrice'],2);
//                    }
//                }
//            }
        }
        return $result;
    }

    /**
     * 产品按一级分类分组
     * @param $products
     * @param $lang
     * @return array
     */
    public function groupByProductFirstCategory($products,$lang){
        if(empty($products)){
            return array();
        }

        //产品id按一级类别分组
        $countData = (new ProductModel())->groupByProductCategory(CommonLib::array_string_int($products),'$FirstCategory');

        if(!empty($countData)){
            //object转数组
            $countData = json_decode(json_encode($countData),true);
            //获取类别id
            $class_id = CommonLib::supportArray(CommonLib::getColumn('_id',$countData));
            //获取分类详情
            $class_list = $this->classModel->selectClass(['class_id' =>$class_id,'lang'=>$lang]);
            if(!empty($class_list)){
                $data = array();
                //循环赋值
                foreach($class_list as $key => $class){
                    //PDC数据
                    if($class['type'] == 2){
                        if(!empty($class['pdc_ids'])){
                            //类别映射
                            $mapList = $this->classModel->selectClass(['class_id'=>CommonLib::supportArray($class['pdc_ids']),'lang'=>$lang]);
                            foreach($mapList as $map){
                                $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                                if(isset($data[$map['id']])){
                                    $data[$map['id']]['count'] = $data[$map['id']]['count'] + $count['count'];
                                }else{
                                    $data[$map['id']]['count'] = $count['count'];
                                }
                                $data[$map['id']]['title'] = $map['title_en'];
                                $data[$map['id']]['sort'] = $map['sort'];
                                $data[$map['id']]['icon'] = !empty($map['icon']) ? $map['icon'] : '';
                                if($lang != DEFAULT_LANG){
                                    $data[$map['id']]['title'] = isset($map['Common'][$lang]) && !empty($map['Common'][$lang]) ?
                                        $map['Common'][$lang] : $map['title_en'];
                                }
                                $data[$map['id']]['id'] = $class['id'];
                            }
                        }else{
                            //类别映射为空
                            $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                            $data[$class['id']]['title'] = $class['title_en'];
                            $data[$class['id']]['sort'] = $class['sort'];
                            $data[$class['id']]['icon'] = !empty($class['icon']) ? $class['icon'] : '';
                            if($lang != DEFAULT_LANG){
                                $data[$class['id']]['title'] = isset($class['Common'][$lang]) && !empty($class['Common'][$lang]) ?
                                    $class['Common'][$lang] : $class['title_en'];
                            }
                            $data[$class['id']]['count'] = $count['count'];
                            $data[$class['id']]['id'] = $class['id'];
                        }
                    }else{
                        //erp数据
                        $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                        if(isset($data[$class['id']])){
                            $data[$class['id']]['count'] = $data[$class['id']]['count'] + $count['count'];
                        }else{
                            $data[$class['id']]['count'] = $count['count'];
                        }
                        $data[$class['id']]['title'] = $class['title_en'];
                        $data[$class['id']]['sort'] = $class['sort'];
                        $data[$class['id']]['icon'] = !empty($class['icon']) ? $class['icon'] : '';
                        if($lang != DEFAULT_LANG){
                            $data[$class['id']]['title'] = isset($class['Common'][$lang]) && !empty($class['Common'][$lang]) ?
                                $class['Common'][$lang] : $class['title_en'];
                        }
                        $data[$class['id']]['id'] = $class['id'];
                    }
                }
                if(!empty($data)){
                    //按照sort排序,add by zhongning20190730
                    array_multisort(array_column($data, 'sort'),SORT_ASC,$data);
                    $this->redis->set(COUNT_CATEGORY_BY_.$key. '_' . $lang,array_values($data),CACHE_DAY);
                    return array_values($data);
                }
            }
        }
        return array();
    }

    /**
     * amdin后台配置- 商城业务数据配置的spu
     * @return \app\mall\model\data|array|mixed
     */
    public function getConfigSpus($key){
        $spus = array();
        if(config('cache_switch_on')) {
            $spus = $this->redis->get('MALL_CONFIG_SPUS_'.$key);
        }
        if(empty($spus)){
            $result = (new ConfigDataModel())->getDataConfig(['key'=>$key]);
            if(isset($result['spus']) && !empty($result['spus'])){
                $spus = $result['spus'];
                $this->redis->set('MALL_CONFIG_SPUS_'.$key,$spus,CACHE_DAY);
            }else{
                return array();
            }
        }
        return $spus;
    }
}
