<?php
namespace app\mallextend\services;

use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\mallextend\model\ConfigDataModel;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductModel;
use think\Cache;
use think\Exception;


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
    public function __construct()
    {
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
            $productMultiLang = (new ProductModel())->getProductMultiLang($product_id,$lang);
            if (!empty($productMultiLang)) {
                $this->redis->set('PRODUCT_LANGUAGE_'.$product_id.'_'.$lang, $productMultiLang, CACHE_DAY);
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
                    $productArrt = (new ProductModel())->getProductCustomAttrMultiLangs($attr_id,$option_id.'_'.$sku_id,$product_id);
                }else{
                    $productArrt = (new ProductModel())->getProductAttrDefsLang($attr_id,$option_id.'_'.$sku_id);
                }
                if (!empty($productArrt)) {
                    $this->redis->set('PRODUCT_ATTR_LANGUAGE_'.$sku_id.'_'.$attr_id.'_'.$option_id, $productArrt, CACHE_DAY);
                }
            }
            return $productArrt;
        }catch (Exception $e){
            return $e->getMessage();
        }

    }


    /**
     * 币种费率
     * @param $key
     * @return mixed
     */
    public function getCurrencyRate($key){
        $rate = '';
        try{
            $currency = doCurl(config("currency_url"));
            foreach($currency as $value){
                if($value['To'] == $key){
                    $rate = $value['Rate'];break;
                }
            }
            return $rate;
        }catch (Exception $e){
            //记录日志
            return false;
        }
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
                $this->redis->set(PRODUCT_TAGS_CONFIG, $tags, CACHE_DAY);
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
                if((int)$product[$tag['tag']] == true){
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
                        if((int)$product['Tags'][$tag['tag']] == true){
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
    public function commonProdcutListData($products,$params){
        $lang = isset($params['lang']) ? $params['lang'] : self::DEFAULT_LANG;
//        $currency = isset($params['currency']) ? $params['currency'] : self::DEFAULT_CURRENCY;
        $result = [];

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
            if(self::DEFAULT_LANG != $lang){
                $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);
                $result[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) ? $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
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
            $result[$k]['HightPrice'] = $priceArray['HightPrice'];
            //原价
            $result[$k]['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
            $result[$k]['OriginalHightPrice'] = $priceArray['OriginalHightPrice'];


            //颜色
            $result[$k]['ColorCount'] = isset($product['ColorCount']) ? $product['ColorCount'] : 0;
            //星级评分
            $result[$k]['AvgRating'] = isset($product['AvgRating'])&& !empty($product['AvgRating']) ? $product['AvgRating'] : 5;
            //评论数
            $result[$k]['ReviewCount'] = isset($product['ReviewCount']) ? $product['ReviewCount'] : 0;
            //运费状态  0免邮  1MVP 24小时到货提示 2不免邮
            $result[$k]['ShippingFee'] = isset($product['ShippingFee']) ? $product['ShippingFee'] : 0;//是否免邮
            //是否是MVP产品
            $ismvp = isset($product['IsMVP']) && $product['IsMVP'] == true ? true : false;//是否免邮
            if($ismvp){
                $result[$k]['ShippingFee'] = 1;
            }else{
                if($result[$k]['ShippingFee'] != 0){
                    $result[$k]['ShippingFee'] =  2 ;
                }
            }

            //是否是MVP，预售，折扣，达人推荐商品
            //tagName 前端展示图标使用
            $result[$k]['tagName'] = $this->getProuctTags($product);

            $result[$k]['firstClassId'] = $product['FirstCategory'];

            $result[$k]['VideoCode'] = isset($product['VideoCode']) && !empty($product['VideoCode']) ? $product['VideoCode'] : null;
        }
        return $result;
    }


    public function getFlashData($products,$params){
        $lang = isset($params['lang']) ? $params['lang'] : self::DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : self::DEFAULT_CURRENCY;
        $avtivityModel = new ProductActivityModel();
        $time = time();

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
            if(self::DEFAULT_LANG != $lang){
                $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);
                $result[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) ? $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
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
            $result[$k]['SalesPrice'] = $priceArray['LowPrice'];
            //原价
            $result[$k]['OriginalPrice'] = $priceArray['OriginalLowPrice'];

            //折扣
            $result[$k]['Discount'] = isset($product['HightDiscount']) ? sprintf('%01.2f',$product['HightDiscount']) : '';

            $result[$k]['firstClassId'] = $product['FirstCategory'];
            //flashDeals产品肯定是折扣产品，展示折扣图标
            $result[$k]['tagName'] = 'tag-discount';

            if(isset($params['soon'])){
                //下一场活动未开始，进度条为0
                $result[$k]['TimeGone'] = 0;
            }else{
                //已进行的时间进度条

                //获取活动详情
//                $avtivity = $avtivityModel->getActivityProduct(array_merge($params,['productId'=>$product['_id']]));

//                C：商城中的Flash Deals展示的活动售价为折扣后的售价，
//若该SPU下的默认主SKU的活动数量卖完，该SPU下的其他产品活动数量未卖完，则继续展示该产品的活动售价 （给其他产品引流）
// TODO 活动数量进度条：按该SPU下的所有SKU的活动数量总和计算，该SPU的下所有SKU的活动数量全部卖完时，则该SPU从Flash Deals首页移出，但仍在Flash Deals的活动列表页展示；

                $result[$k]['TimeGone'] = 10;
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
            array_push($class['pdc_ids'],$class_id);
            $class_id = CommonLib::supportArray($class['pdc_ids']);
        }
        return $class_id;
    }

    /**
     * 类别映射查询
     * @param $params
     * @return mixed
     */
    public function newCommonClassMap(&$params){
        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $class = (new ProductClassModel())->getClassDetail(['id'=>(int)$params['lastCategory']]);
            if(!empty($class)){
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
}
