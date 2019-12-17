<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\mall\model\ConfigDataModel;
use app\mall\model\CouponModel;
use app\mall\model\ProductActivityModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductExtendModel;
use app\mall\model\ProductMappingsModel;
use app\mall\model\ProductModel;
use app\mall\model\ProductTopSellerModel;
use app\mall\model\SysConfigModel;
use think\Cache;
use think\Exception;


/**
 * 产品接口
 */
class ProductService extends BaseService
{

    /**
     * 查询产品是否在售
     * @param $paramData
     * @return bool
     */
    public function checkProduct($paramData){
        //查询产品映射关系
        $maps = (new ProductMappingsModel())->find($paramData['product_id']);
        if(!empty($maps)){
            $paramData['product_id'] = $maps['newId'];
        }
        return (new ProductModel())->checkProduct($paramData['product_id']);
    }

    /**
     * 获取单个产品详情  -- 廷虎cart使用
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getProduct($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        //国家区域价格
        $country = isset($params['country']) ? trim($params['country']) : null;

        $data = (new ProductModel())->findProduct($params);
        if(!empty($data)){
            //获取国家区域价格
            if(!empty($country)){
                $countryPrice  = $this->getProductRegionPrice($data['_id'],$country);
                //这个产品有国家区域价格
                if(!empty($countryPrice)){
                    $this->handleProductRegionPrice($data,$countryPrice);
                }
            }
            //判断语种
            if(DEFAULT_LANG != $lang) {
                //标题多语言
                $productMultiLang = $this->getProductMultiLang($data['_id'],$lang);
                //多语言可能存在未空的情况
                $data['Title'] = isset($productMultiLang['Title'][$lang]) && !empty($productMultiLang['Title'][$lang]) ? $productMultiLang['Title'][$lang] : $data['Title'];//默认英语
                //自定义销售属性多语言
                $customValueLang = !empty($productMultiLang['SalesAttrs']) ? $productMultiLang['SalesAttrs'] : array();
                //属性多语言
                foreach($data['Skus'] as $key => $sku){
                    if(!empty($sku['SalesAttrs'])){
                        foreach($sku['SalesAttrs'] as $k => $attr){
                            $option = isset($attr['OptionId']) ? $attr['OptionId'] : 0;
                            $lang_key = $option.'_'.$sku['_id'];
                            //区分历史数据，获取多语言翻译不一样
                            if(isset($products['IsHistory']) && $products['IsHistory'] == 1){
                                //属性多语言
                                $langData = $this->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$data['_id']);
                                //例：color颜色的多语言
                                $data['Skus'][$key]['SalesAttrs'][$k]['Name'] = !empty($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                            }else{
                                //末级类别id
                                $lastClass = isset(explode('-',$data['CategoryPath'])[0]) ? explode('-',$data['CategoryPath'])[0] : 0;
                                //属性多语言
                                $langData = $this->getProductAttrMultiLangNew($attr['_id'],$option,$lastClass);
                                //例：color颜色的多语言
                                $data['Skus'][$key]['SalesAttrs'][$k]['Name'] = !empty($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                            }
                            //自定义属性名称翻译
                            if(!empty($attr['CustomValue'])){
                                //多语言翻译
                                if(!empty($customValueLang)){
                                    $custom_key = $attr['_id'].'_'.$attr['OptionId'];
                                    if(isset($customValueLang[$custom_key]['CustomValue'][$lang]) && !empty($customValueLang[$custom_key]['CustomValue'][$lang])){
                                        $data['Skus'][$key]['SalesAttrs'][$k]['CustomValue'] = $customValueLang[$custom_key]['CustomValue'][$lang];
                                    }
                                }
                            }else{
                                //默认值多语言翻译
                                if(isset($products['IsHistory']) && $products['IsHistory'] == 1) {
                                    //dx_product_customAttr_multiLangs
                                    if (isset($langData['Options'][$lang_key][$lang]) && !empty($langData['Options'][$lang_key][$lang])) {
                                        $data['Skus'][$key]['SalesAttrs'][$k]['DefaultValue'] = $langData['Options'][$lang_key][$lang];
                                    }
                                    //dx_product_attr_multiLangs
                                    if (isset($langData['Options'][$option][$lang]) && empty($langData['Options'][$option][$lang])) {
                                        $data['Skus'][$key]['SalesAttrs'][$k]['DefaultValue'] = $langData['Options'][$option][$lang];
                                    }
                                }else{
                                    if(isset($langData['Options'][$option]['Title'][$lang]) && !empty($langData['Options'][$option]['Title'][$lang])){
                                        $data['Skus'][$key]['SalesAttrs'][$k]['DefaultValue'] = $langData['Options'][$option]['Title'][$lang];
                                    }
                                }
                            }

                            /*
                            $langData = $this->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$params['product_id']);
                            //例：color颜色的多语言
                            $data['Skus'][$key]['SalesAttrs'][$k]['Name'] = isset($langData['Title'][$lang]) && !empty($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                            //例：color下蓝色blue的多语言
                            $data['Skus'][$key]['SalesAttrs'][$k]['Value'] = $attr['Value'];
                            //dx_product_customAttr_multiLangs
                            if(!empty($langData['Options'][$lang_key][$lang])){
                                $data['Skus'][$key]['SalesAttrs'][$k]['Value'] = $langData['Options'][$lang_key][$lang];
                            }
                            //dx_product_attr_multiLangs
                            if(!empty($langData['Options'][$option][$lang])){
                                $data['Skus'][$key]['SalesAttrs'][$k]['Value'] = $langData['Options'][$option][$lang];
                            }
                            */
                        }
                    }
                }
            }
            if(isset($data['IsActivity']) && !empty($data['IsActivity'])){
                $activityInfo = (new ProductActivityModel())->getActivity(['activity_id' => $data['IsActivity']]);
                if(!empty($activityInfo)){
                    $data['ActivityStartTime'] = $activityInfo['activity_start_time'];
                    $data['ActivityEndTime'] = $activityInfo['activity_end_time'];
                }
            }
            //过滤,返回当前传入参数SKU信息
            if(isset($params['sku_id'])){
                $data['Skus'] = $this->filterProductSkuInfoArray($data,'_id',$params['sku_id']);
            }
            //过滤,返回当前传入参数SKU信息
            if(isset($params['sku_code'])){
                $data['Skus'] = $this->filterProductSkuInfoArray($data,'Code',$params['sku_code']);
            }

            //类别映射 add by zhongning 20190729
            if(!empty($data['CategoryPath'])){
                //分类信息
                $classArray = explode('-',$data['CategoryPath']);
                $classInfo = (new ProductClassModel())->getClassDetail(['id'=>(int)end($classArray)]);
                if($classInfo['type'] != 1){
                    //ERP类别数据
                    if( isset($classInfo['pdc_ids']) && !empty($classInfo['pdc_ids'])) {
                        $erpData = (new ProductClassModel())->getClassDetail(['id' => (int)$classInfo['pdc_ids'][0]]);
                        $data['CategoryPath'] = !empty($erpData['id_path']) ? $erpData['id_path'] : $data['CategoryPath'];
                        $categoryPath = explode('-',$data['CategoryPath']);
                        $data['FirstCategory'] = !empty($categoryPath[0]) ? $categoryPath[0] : 0;
                        $data['SecondCategory'] = !empty($categoryPath[1]) ? $categoryPath[1] : 0;
                        $data['ThirdCategory'] = !empty($categoryPath[2]) ? $categoryPath[2] : 0;
                        $data['FourthCategory'] = !empty($categoryPath[3]) ? $categoryPath[3] : 0;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * add by zhongning 20191108
     * 查找对应的SKU信息
     */
    private function filterProductSkuInfoArray($product,$key,$val){
        $retArray = array_filter($product['Skus'], function($t) use ($key,$val){
            return $t[$key] == $val;
        });
        //市场折扣逆推市场价 add by zhongning 20191108
        if(!empty($retArray)){
            foreach($retArray as $k => $v){
                if(empty($product['IsActivity']) && !empty($product['ListPriceDiscount'])){
                    $retArray[$k]['ListPrice'] = (string)round($v['SalesPrice'] / (1 - $product['ListPriceDiscount']), 2);
                }
            }
        }
        return $retArray;
    }

    /**
     * 获取产品运费信息
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getShipping($params){
        $data = (new ProductModel())->getShipping($params);
        if(false != $data && !empty($data)){
            if(isset($params['country'])){
                $country = $params['country'];
                $retArray = array_filter($data, function($t) use ($country){
                    return $t['Country'] == $country;
                });
                return $retArray;
            }
        }
        return $data;
    }

    /**
     * 使用接口：首页数据接口
     * 使用接口：一级分类页面数据接口
     * @param $params
     * @return array
     */
    public function getClassNewArrivals($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? trim($params['country']) : '';
        $result = array();
        try{
            //判断是否有缓存+语种
            if(config('cache_switch_on')) {
                $result = $this->redis->get(NEW_ARRIVALS_DATA_ .$params['category'].'_'. $lang.'_'.$country);
            }
            if(empty($result)) {
                //类别映射
                if(isset($params['category']) && !empty($params['category'])){
                    $params['lastCategory'] = $params['category'];
                    $this->newCommonClassMap($params);
                }
                $products = (new ProductModel())->selectProduct($params);
                if (!empty($products)) {
                    $result = $this->commonProdcutListData($products, $params);
                    $this->redis->set(NEW_ARRIVALS_DATA_ .$params['category'].'_'.$lang.'_'.$country, $result, CACHE_FIVE_MIN);
                    return apiReturn(['code' => 200, 'data' => $result]);
                }
            }
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            return apiReturn(['code'=>100000002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 使用接口：分类页面新品数据
     * @param $params
     * @return array
     */
    public function getNewProduct($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? trim($params['country']) : '';
        $self_id = isset($params['spu']) ? $params['spu'] : null;
        $result = array();
        try{
            //判断是否有缓存+语种
            if(config('cache_switch_on')) {
                $result = $this->redis->get(NEW_ARRIVALS_DATA_ . $lang.'_'.$country);
            }
            if(empty($result)) {
                $products = (new ProductModel())->selectProduct($params,$self_id);
                if (!empty($products)) {
                    $result = $this->commonProdcutListData($products, $params);
                    $this->redis->set(NEW_ARRIVALS_DATA_ .$lang.'_'.$country, $result, CACHE_HOUR);
                    return apiReturn(['code' => 200, 'data' => $result]);
                }
            }
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            return apiReturn(['code'=>100000002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 使用接口：一级分类页面
     * 使用接口：二级分类数据接口
     * @param $params
     * @return array
     */
    public function getSecCategroy($params){
//        \think\Log::pathlog('time3 = ',microtime(),'getSecCategroyProduct.log');
        $data = array();
        //类别映射
        if(isset($params['category']) && !empty($params['category'])){
            $params['lastCategory'] = $params['category'];
            if(is_array($params['lastCategory'])){
                $newCategory = array();
                foreach($params['lastCategory'] as $lastCategoryId){
                    $newParams['lastCategory'] = $lastCategoryId;
                    $this->newCommonClassMap($newParams);
                    array_merge($newCategory,$newParams['lastCategory']);
                }
                $params['lastCategory'] = array_unique($newCategory);
            }else{
                $this->newCommonClassMap($params);
            }
        }
//        \think\Log::pathlog('time4 = ',microtime(),'getSecCategroyProduct.log');
        $products = (new ProductModel())->selectProduct($params);
//        \think\Log::pathlog('time5 = ',microtime(),'getSecCategroyProduct.log');
        if(!empty($products)){
            $data = $this->commonProdcutListData($products,$params,null,$log=false);
        }
//        \think\Log::pathlog('time6 = ',microtime(),'getSecCategroyProduct.log');
        return apiReturn(['code'=>200, 'data'=>$data]);
    }


    /**
     * 二级、三级分类页面，产品列表数据
     *
     * @param $params
     * @return array
     */
    public function getCategoryPageLists($params){
        //类别映射
        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $this->newCommonClassMap($params);
        }
        $products = (new ProductModel())->categoryPageLists($params);
        if(!empty($products['data'])){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * 根据spu获取产品基本信息
     * @param $params
     * @return array
     */
    public function getBaseSpuInfo($params){
        $products = array();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $country = isset($params['country']) ? trim($params['country']) : '';
        $activityStatu = false;
        $regionPrice = array();

        $time = time();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(PRODUCT_INFO_ . $params['spu'].'_'.$lang.'_'.$currency.'_'.$country);
        }
        //缓存为空
        if(empty($products)){
            //币种切换费率
            if(DEFAULT_CURRENCY != $currency){
                $rate = $this->getCurrencyRate($currency);
                //防止出现费率为空，价格为0的情况
                if(empty($rate)){
                    return array();
                }
            }
//            //查询产品映射关系
//            $maps = (new ProductMappingsModel())->find($params['spu']);
//            if(!empty($maps)){
//                $params['spu'] = $maps['newId'];
//            }

            //第一步：erp的code = 2000814和spu = 2000814冲突，所以/p/2000000以上的，直接搜索spu add zhongning 20190328
            //旧数据产品，开头851,852,916的code产品要支持搜索，add zhongning 20190430
            $substr = substr($params['spu'] , 0 , 3);
            if($params['spu'] < 2000000 || $substr == '851' || $substr == '852' || $substr == '916'){
                //先查询code -- 历史流量数据
                $productsCode = (new ProductModel())->getSpuByCode($params['spu']);
                if(isset($productsCode['_id']) && !empty($productsCode['_id'])){
                    $params['spu'] = $productsCode['_id'];
                }
            }

            //第二步：查询当前产品详情信息
            $products = (new ProductModel())->getBaseSpuDetail($params['spu']);

            //第三步：增加查询SKU_id add zhongning 20190711
            if(empty($products)){
                $products = (new ProductModel())->getBaseSpuDetailBySkusID($params['spu']);
            }

            //第四步：查询产品映射关系 add zhongning 20190808
            if(empty($products)){
                $maps = (new ProductMappingsModel())->find($params['spu']);
                if(!empty($maps)){
                    $params['spu'] = $maps['newId'];
                    $products = (new ProductModel())->getBaseSpuDetail($params['spu']);
                }
            }

            //产品信息格式化
            if(!empty($products)){
                $products['ActivityImg'] = $products['ActivityTitle'] = $products['ActivityEndTime'] = $products['Discount'] = '';
//                $discountLowPrice = '';//折扣最低价
//                $discountHightPrice = '';//折扣最高价

                //自定义属性名称多语言
                $customValueLang = array();
                //判断语种 -- 多语言切换
                if(DEFAULT_LANG != $lang){
                    $productMultiLang = $this->getProductMultiLang($params['spu'],$lang);
                    $products['Title'] = isset($productMultiLang['Title'][$lang]) && !empty($productMultiLang['Title'][$lang]) ?
                        $productMultiLang['Title'][$params['lang']] : $products['Title'];//默认英语
                    $customValueLang = isset($productMultiLang['SalesAttrs']) ? $productMultiLang['SalesAttrs'] : array();
                }

                //初始化计量单位
                $products['SalesUnitType'] = !empty($products['SalesUnitType']) ? $products['SalesUnitType'] : 'piece';
                if(DEFAULT_LANG != $lang){
                    $productMultiLang = $this->getProductUnitTypeLang($params['spu'],$lang,$products['SalesUnitType']);
                    $products['SalesUnitType'] = isset($productMultiLang['Common'][$lang]) && !empty($productMultiLang['Common'][$lang])
                        ? $productMultiLang['Common'][$lang] : $products['SalesUnitType'];
                }
                //品牌名称
                $products['BrandName'] = !empty($products['BrandName']) ? $products['BrandName'] : '';
                if($products['BrandName'] == 'N/A' || $products['BrandName'] == 'None'){
                    $products['BrandName'] = '';
                }
                //收藏数
                $products['WishCount'] = isset($products['WishCount']) ? $products['WishCount'] : 0;
                //第一个sku数据为默认SKU
                if(!isset($products['Skus'][0])){
                    return array();
                }
                $DefaultSkus = $products['Skus'][0];
                $isActivity = !empty($products['IsActivity']) ? (int)$products['IsActivity'] : 0;
                //活动数据,判断是否有活动
                if($isActivity > 0){

                    //活动标题，活动结束时间，活动图片
                    $activityInfo = (new ProductActivityModel())->getActivity(['activity_id' => $products['IsActivity'],'lang'=>$lang,'status'=>3]);
                    if(!empty($activityInfo)) {
                        //活动期内
                        if($time >= $activityInfo['activity_start_time'] && $time <= $activityInfo['activity_end_time']){
                            $products['Discount'] = !empty($products['HightDiscount']) ? (string)$products['HightDiscount'] : '';
                            //新功能，只有FLASHDEAL才有多语言标题 add by zhongning 20191107
                            if(!empty($activityInfo['common'])){
                                if(isset($activityInfo['common'][$lang]['type'])){
                                    $products['ActivityTitle'] = $activityInfo['common'][$lang]['type'];
                                }else{
                                    //存在不设置英文的情况
                                    if(isset($activityInfo['common'][DEFAULT_LANG]['type'])){
                                        $products['ActivityTitle'] = $activityInfo['common'][DEFAULT_LANG]['type'];
                                    }else{
                                        $activity_type = $activityInfo['common'][0];
                                        $products['ActivityTitle'] = $activity_type['type'];
                                    }
                                }
                            }
                            $products['ActivityImg'] = $activityInfo['activity_img'];
                            $products['ActivityEndTime'] = $activityInfo['activity_end_time'] - time();
//                            $discountLowPrice = !empty($products['DiscountLowPrice']) ? (string)$products['DiscountLowPrice'] : '';//最低价格
//                            $discountHightPrice = !empty($products['DiscountHightPrice']) ? (string)$products['DiscountHightPrice'] : '';//最高价
                            $activityStatu = true;
                        }
                    }
                }

                //默认SKUid
                $products['DefaultSkuId'] = $DefaultSkus['_id'];
                $products['DefaultSkuCode'] = $DefaultSkus['Code'];

                //首图
                $products['FirstProductImage'] = isset($products['FirstProductImage']) ? $products['FirstProductImage'] : '';
                if(empty($products['FirstProductImage'])){
                    $products['FirstProductImage'] = isset($products['ImageSet']['ProductImg'][0]) ? $products['ImageSet']['ProductImg'][0] : '';
                }

                //产品图片分组-- 前端展示需要
                if(isset($products['VideoCode']) && !empty($products['VideoCode'])){
                    $products['ProductImg'] = array_chunk($products['ImageSet']['ProductImg'],4);
                }else{
                    $products['ProductImg'] = array_chunk($products['ImageSet']['ProductImg'],5);
                }
                $products['RewrittenUrl'] = isset($products['RewrittenUrl']) ? $products['RewrittenUrl'] : null;

                //关键字
                $products['Keywords'] = isset($products['Keywords']) ? implode(',',$products['Keywords']) : '';

                //国家区域价格
                if(!empty($country)){
                    $regionPrice = $this->getProductRegionPrice($products['_id'],$country);
                    //如果有国家区域价格
                    if(!empty($regionPrice)){
                        $this->handleProductRegionPrice($products,$regionPrice);
                    }
                }
                //折扣后的价格区间,有些产品数据库保存的是字符串类型，NULL add by zhongning 2019-05-16
                $discountLowPrice = !empty($products['DiscountLowPrice']) && $products['DiscountLowPrice'] != 'NULL' ? (string)$products['DiscountLowPrice'] : '';//最低价格
                $discountHightPrice = !empty($products['DiscountHightPrice']) && $products['DiscountHightPrice'] != 'NULL' ? (string)$products['DiscountHightPrice'] : '';//最高价
                //价格
                $this->productPrice($products,$discountLowPrice,$discountHightPrice,$regionPrice,$activityStatu);

//                pr($products['OriginalLowPrice']);
//                pr($products['OriginalHightPrice']);
//                pr($products['LowPrice']);
//                pr($products['HightPrice']);
//die;
                //如果没有活动折扣，就展示市场折扣，原价必须大于销售价
                //折扣逆推市场价功能 add by zhongning 20191106
//                if(empty($products['Discount']) && !empty($products['OriginalLowPrice']) && !empty($products['LowPrice'])){
//                    if($products['OriginalLowPrice'] > $products['LowPrice']){
//                        $products['Discount'] =  (string)(1 - round(($products['OriginalLowPrice'] - $products['LowPrice']) / $products['OriginalLowPrice'],2));
//                        unset($products['ListPriceDiscount']);
//                    }
//                }

                //分类信息,获取末级类别，查询品牌列表表
                $classArray = explode('-',$products['CategoryPath']);
                $lastClass = end($classArray);
                //销售属性展示
                $skus = $products['Skus'];
                //sku数量
                $skuCount = count($products['Skus']);
//                $listPriceCount = 0;
                $total_sku = 0;//总库存

                //sku循环开始
                foreach($skus as $key => $sku){
                    //库存限制初始化
                    $products['Skus'][$key]['SalesLimit'] = '';
                    //销售属性ID初始化
                    $optionKey = [];
                    //折扣初始化
                    $products['Skus'][$key]['Discount'] = '';
                    //市场价初始化
                    $products['Skus'][$key]['ListPrice'] =  '';

                    //flash活动规则更改，可以指定折扣单个SKU，之前是全部SKU都有活动折扣
                    if(!empty($sku['ActivityInfo'])){
                        //判断是否活动时间内
                        if($activityStatu){
                            //活动库存限制初始化
                            if (isset($sku['ActivityInfo']['SalesLimit'])) {
                                if($sku['ActivityInfo']['SalesLimit'] != 0){
                                    //正常库存改为活动库存数量
                                    $products['Skus'][$key]['Inventory'] = $sku['Inventory'] = $sku['ActivityInfo']['SalesLimit'];

                                    //sku折扣比例改为活动折扣比例
                                    if (isset($sku['ActivityInfo']['Discount'])) {
                                        $products['Skus'][$key]['ActivityInfo']['Discount'] = (string)$sku['ActivityInfo']['Discount'];
                                        $products['Skus'][$key]['Discount'] = (string)$sku['ActivityInfo']['Discount'];

                                        //SetType 1表示没有指定价格，用折扣计算，2表示有指定价格,重新计算价格
                                        $products['Skus'][$key]['ActivityInfo']['DiscountPrice'] = sprintf("%01.2f",$sku['SalesPrice'] * $sku['ActivityInfo']['Discount']);
                                    }
                                }else{
                                    //sku数量只有一个，活动库存为0的时候,活动信息初始化
                                    if($skuCount == 1) {
                                        $products['LowPrice'] = $products['OriginalLowPrice'];
                                        $products['HightPrice'] = $products['OriginalHightPrice'];
                                        $products['OriginalLowPrice'] = $products['OriginalHightPrice'] = $products['Discount'] = '';
                                        $products['ActivityTitle'] = $products['ActivityImg'] = $products['ActivityEndTime'] = '';
                                    }
                                    unset($products['Skus'][$key]['ActivityInfo']);
                                }
                            }
                        }else{
                            //删除活动信息
                            unset($products['Skus'][$key]['ActivityInfo']);
                        }
                    }
                    //不是活动的SKU，但是有市场价折扣
                    if(empty($products['Skus'][$key]['ActivityInfo']) && !empty($products['ListPriceDiscount'])){
                        //折扣逆推市场价功能 add by zhongning 20191106
                        $products['Skus'][$key]['Discount'] = (string)(1 - $products['ListPriceDiscount']);
                        $products['Skus'][$key]['ListPrice'] = (string)(round($sku['SalesPrice'] / (1 - $products['ListPriceDiscount']), 2));
                    }
                    //总库存计算
                    $total_sku = $total_sku + $sku['Inventory'];

                    //价格初始化字符串
                    $products['Skus'][$key]['SalesPrice'] = (string)$sku['SalesPrice'];
                    if(isset($sku['BulkRateSet']['SalesPrice'])){
                        $products['Skus'][$key]['BulkRateSet']['SalesPrice'] = (string)$sku['BulkRateSet']['SalesPrice'];
                        $products['Skus'][$key]['BulkRateSet']['Discount'] = (string)$sku['BulkRateSet']['Discount'];
                    }

                    //判断币种
                    if(DEFAULT_CURRENCY != $currency){
                        $products['Skus'][$key]['SalesPrice'] = sprintf("%01.2f",$sku['SalesPrice'] * $rate);
                        if(isset($sku['BulkRateSet']['SalesPrice'])){
                            $products['Skus'][$key]['BulkRateSet']['SalesPrice'] = sprintf("%01.2f",$sku['BulkRateSet']['SalesPrice'] * $rate);
                        }
                        if(!empty($products['Skus'][$key]['ActivityInfo']) && $activityStatu){
                            $products['Skus'][$key]['ActivityInfo']['DiscountPrice'] = sprintf("%01.2f",$products['Skus'][$key]['ActivityInfo']['DiscountPrice'] * $rate);
                        }
                        if(!empty($products['Skus'][$key]['ListPrice'])){
                            $products['Skus'][$key]['ListPrice'] = sprintf("%01.2f",$products['Skus'][$key]['ListPrice'] * $rate);
                        }
                    }

                    $attrFilter = $sku['SalesAttrs'];
                    if(!empty($attrFilter)) {
                        foreach ($attrFilter as $k => $attr) {
                            $t = array();
                            //判断语种 -- 多语言切换
                            if (DEFAULT_LANG != $lang) {
                                $option = $attr['OptionId'];
                                //判断是否是历史数据
                                if(isset($products['IsHistory']) && $products['IsHistory'] == 1){
                                    //属性多语言
                                    $langData = $this->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$params['spu']);
                                    //例：color颜色的多语言
                                    $products['Skus'][$key]['SalesAttrs'][$k]['Name'] = isset($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                                    //例：color下蓝色blue的多语言
                                    $lang_key = $option.'_'.$sku['_id'];
                                }else{
                                    //属性多语言
                                    $langData = $this->getProductAttrMultiLangNew($attr['_id'],$option,$lastClass);
                                    //例：color颜色的多语言
                                    $products['Skus'][$key]['SalesAttrs'][$k]['Name'] = isset($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                                }
                            }
                            //属性封装 此处主要是为了方便前端展示
                            //{
                            //"颜色ID":{"颜色id","颜色name",["红色id","红色","是否有图"]}
                            //"尺寸ID":{"尺寸id","尺寸name",["尺码id","尺码"]}
                            //}
                            $arrtArr[$attr['_id']]['id'] = $attr['_id'];
                            $arrtArr[$attr['_id']]['name'] = $products['Skus'][$key]['SalesAttrs'][$k]['Name'];
                            $t['option_id'] = $attr['OptionId'];
                            if(isset($attr['CustomValue']) && !empty($attr['CustomValue'])){
                                $t['option_name'] = $attr['CustomValue'];
                            }elseif(isset($attr['DefaultValue']) && !empty($attr['DefaultValue'])){
                                $t['option_name'] = $attr['DefaultValue'];
                            }else{
                                $t['option_name'] = $attr['Value'];
                            }
                            //多语言
                            if (DEFAULT_LANG != $lang) {
                                //如果自定义名称不为空
                                if(isset($attr['CustomValue']) && !empty($attr['CustomValue'])){
                                    //多语言翻译
                                    if(!empty($customValueLang)){
                                        $custom_key = $attr['_id'].'_'.$attr['OptionId'];
                                        if(isset($customValueLang[$custom_key]['CustomValue'][$lang]) && !empty($customValueLang[$custom_key]['CustomValue'][$lang])){
                                            $t['option_name'] = $customValueLang[$custom_key]['CustomValue'][$lang];
                                        }
                                    }
                                }else{
                                    //默认值多语言翻译
                                    if(isset($products['IsHistory']) && $products['IsHistory'] == 1) {
                                        //dx_product_customAttr_multiLangs
                                        if (isset($langData['Options'][$lang_key][$lang]) && !empty($langData['Options'][$lang_key][$lang])) {
                                            $t['option_name'] = $langData['Options'][$lang_key][$lang];
                                        }
                                        //dx_product_attr_multiLangs
                                        if (isset($langData['Options'][$option][$lang]) && empty($langData['Options'][$option][$lang])) {
                                            $t['option_name'] = $langData['Options'][$option][$lang];
                                        }
                                    }else{
                                        if(isset($langData['Options'][$option]['Title'][$lang]) && !empty($langData['Options'][$option]['Title'][$lang])){
                                            $t['option_name'] = $langData['Options'][$option]['Title'][$lang];
                                        }
                                    }
                                }
                            }
                            if (isset($attr['Image']) && !empty($attr['Image'])) {
                                $t['img'] = $attr['Image'];
                            }
                            $arrtArr[$attr['_id']]['attr'][$key] = $t;
                            $optionKey[] = $attr['OptionId'];
                        }
                        //方便前端选择价格
                        $AttrPriceKey = implode('-', $optionKey);
                        $products['AttrPriceList'][$AttrPriceKey] = $products['Skus'][$key];
                        if(empty($sku['Inventory'])){
                            $products['skuSoldOutAttr'][$key] = $AttrPriceKey;
                        }
                    }else{
                        //没有销售属性的产品
                        $products['AttrPriceList'] = [];
                        $products['AttrPriceList'][''] = $products['Skus'][$key];
                    }
                }//sku循环结束


                //去除外层有市场价，sku里面没有市场价的产品
                //市场折扣逆推折扣价功能，只要有折扣，那么市场价一定存在 add by zhongning 20191107
//                if($listPriceCount == 0 && !$activityStatu){
//                        $products['Discount'] = $products['OriginalLowPrice'] = $products['OriginalHightPrice'] = '';
//                    }
                //去重,排序
                $products['AttrList'] = [];
                if(!empty($arrtArr)){
                    $i = 0;
                    foreach($arrtArr as $key => $attrs){
                        $newAttr = array_values(CommonLib::array_unset_repeat($attrs['attr'],'option_id'));
//                        $arrtArr[$key]['attr'] = $newAttr;
                        $newAttrList[$i]['attr'] = $newAttr;
                        $newAttrList[$i]['_id'] = $attrs['id'];
                        $newAttrList[$i]['name'] = $attrs['name'];
//                        $arrtArr[$key]['attr'] = CommonLib::multiArraySort($newAttr,'option_id','SORT_ASC');
                        $i++;
                    }
                    $products['AttrList'] = $newAttrList;
                }

                //只有一个销售属性
                if(count($products['AttrPriceList']) == 1){
                    $products['AttrList'] = [];
                    $products['AttrPriceList'][''] = array_pop($products['AttrPriceList']);
                }

                //原价区间，销售价区间多币种转换
                if(DEFAULT_CURRENCY != $currency) {
                    //原价
                    if(!empty($products['OriginalLowPrice'])){
                        $products['OriginalLowPrice'] = sprintf("%01.2f",$products['OriginalLowPrice'] * $rate);
                    }
                    if(!empty($products['OriginalHightPrice'])){
                        $products['OriginalHightPrice'] = sprintf("%01.2f",$products['OriginalHightPrice'] * $rate);
                    }
                    //售价
                    if(!empty($products['LowPrice'])){
                        $products['LowPrice'] = sprintf("%01.2f",$products['LowPrice'] * $rate);
                    }
                    if(!empty($products['HightPrice'])){
                        $products['HightPrice'] = sprintf("%01.2f",$products['HightPrice'] * $rate);
                    }
                }

                $products['totalInventory'] = $total_sku;
                //产品分类数据
                $classData = $this->productClassData($products,$lang);
                $products['FirstCategoryData'] = isset($classData[0]) ? $classData[0] : '';
                $products['SecondCategoryData'] = isset($classData[1]) ? $classData[1] : '';
                $products['ThirdCategoryData'] = isset($classData[2]) ? $classData[2] : '';
                $products['FourthCategoryData'] = isset($classData[3]) ? $classData[3] : '';

                //标签
                $products['tagName'] = $this->getProuctTags($products);

                //评分
                if(isset($products['AvgRating'])){
                    if(empty($products['AvgRating']) || (int)$products['AvgRating'] == 0){
                        $products['AvgRating'] = 5;
                    }else{
                        $products['AvgRating'] = $products['AvgRating'] > 5 ? 5 : $products['AvgRating'];
                    }
                }else{
                    $products['AvgRating'] = 5;
                }
                //活动标识
                $products['IsActivity'] = isset($products['IsActivity']) ? (int)$products['IsActivity'] : 0;
                //删除数据
                unset($products['FilterOptions'],$products['ImageSet'],$products['ListPriceDiscount']);
                $this->redis->set(PRODUCT_INFO_ . $params['spu'].'_'.$lang.'_'.$currency.'_'.$country,$products,CACHE_HOUR);
            }
        }
        return $products;
    }

    /**
     * 根据spu获取产品内容描述
     * @param $params
     * @return array
     */
    public function getSpuDescriptions($params){
        $products = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(PRODUCT_DESCRIPTION_ . $params['spu'].'_'.$params['lang']);
        }
        if(empty($products)){
            $products = (new ProductModel())->getSpuDescriptions($params['spu']);

            if(!empty($products)){
                $productAttributes = !empty($products['ProductAttributes']) ? $products['ProductAttributes'] : '';
                //kg单位换算
                $weight = $products['PackingList']['Weight'];
                $lb = sprintf("%.3f",$weight * 2.2046226);//英镑换算kg
                $products['PackingList']['Weight'] = $products['PackingList']['Weight'].'kg ('.$lb.'lb)';
                //cm单位换算
                if(isset($products['PackingList']['Dimensions'])){
                    $dimensions = explode('-',$products['PackingList']['Dimensions']);
                    $length = isset($dimensions[0]) ? $dimensions[0] : 0;
                    $length_in =sprintf("%.2f",$length * 0.3937008);
                    $width = isset($dimensions[1]) ? $dimensions[1] : 0;
                    $width_in =sprintf("%.2f",$width * 0.3937008);
                    $height = isset($dimensions[2]) ? $dimensions[2] : 0;
                    $height_in =sprintf("%.2f",$height * 0.3937008);
                    //包装尺寸
                    $products['PackingList']['Dimensions'] = $length.'cm x '.$width.'cm x '.$height.'cm ('.$length_in.'in x '.$width_in.'in x '.$height_in.'in)';
                }else{
                    $products['PackingList']['Dimensions'] = null;
                }

                //包装清单
                $products['PackingList']['Title'] = isset($products['PackingList']['Title']) ? htmlspecialchars_decode($products['PackingList']['Title']) : '';

                if(DEFAULT_LANG != $params['lang']){
                    $productMultiLang = $this->getProductMultiLang($params['spu'],$params['lang']);
                    $products['Descriptions'] =
                        !empty($productMultiLang['Descriptions'][$params['lang']]) ?
                            $productMultiLang['Descriptions'][$params['lang']] : $products['Descriptions'];//默认英语
                    $products['PackingList']['Title'] =
                        !empty($productMultiLang['PackingTitle'][$params['lang']]) ?
                            htmlspecialchars_decode($productMultiLang['PackingTitle'][$params['lang']]) : $products['PackingList']['Title'];//默认英语
                    $productAttributes =
                        !empty($productMultiLang['ProductAttributes'][$params['lang']]) ?
                            $productMultiLang['ProductAttributes'][$params['lang']] : $productAttributes;//默认英语
                }
                $products['Descriptions'] = htmlspecialchars_decode($products['Descriptions']);
                //产品属性拼接
                if(!empty($productAttributes)){
                    $products['Descriptions'] = htmlspecialchars_decode($productAttributes) ."<br/>".$products['Descriptions'];
                }
                //产品属性
                $products['ProductAttributes'] = htmlspecialchars_decode($productAttributes);

                //初始化计量单位
                $products['SalesUnitType'] = !empty($products['SalesUnitType']) ? $products['SalesUnitType'] : 'piece';
                $lang = $params['lang'];
                if(DEFAULT_LANG != $lang){
                    $productMultiLang = $this->getProductUnitTypeLang($params['spu'],$lang,$products['SalesUnitType']);
                    $products['SalesUnitType'] = isset($productMultiLang['Common'][$lang]) && !empty($productMultiLang['Common'][$lang])
                        ? $productMultiLang['Common'][$lang] : $products['SalesUnitType'];
                }

                //20181229 缓存时间修改为10分钟（之前是CACHE_DAY）
                $this->redis->set(PRODUCT_DESCRIPTION_ . $params['spu'].'_'.$params['lang'],$products,60*10);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$products]);
    }

    /**
     * 根据spu获取运费模板
     * @param $params
     * @return array
     */
    public function getSpuShipping($params){
        $shipping = array();
        if(config('cache_switch_on')) {
            $shipping = $this->redis->get(PRODUCT_SHIPPING_INFO_ . $params['spu'].'_'.$params['country']);
        }
        if(empty($shipping)){
            $shipping = (new ProductModel())->getSpuShipping($params);
            if(!empty($shipping)){
                $this->redis->set(PRODUCT_SHIPPING_INFO_ . $params['spu'].'_'.$params['country'],$shipping,CACHE_HOUR);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$shipping]);
    }

    /**
     * 新品列表页面
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function newArrivalList($params){
        $productModel = new ProductModel();
        //类别映射
        if(isset($params['category']) && !empty($params['category'])){
            $params['lastCategory'] = $params['category'];
            $this->newCommonClassMap($params);
        }
        //查询产品
        $products = $productModel->newArrivalsProductLists($params);
        if(!empty($products['data'])){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * topseller 产品列表
     * @param $params
     * @return array
     */
    public function topSellerLists($params){
        $spus = array();
        //获取topSeller配置的spu列表
        if(config('cache_switch_on')) {
            $spus = $this->redis->get(TOPSELLERS_CONFIG);
        }
        if(empty($spus)){
            $spus = (new ConfigDataModel())->getDataConfig(['key'=>'TopSellers']);
            if(isset($spus['spus']) && !empty($spus['spus'])){
                $this->redis->set(TOPSELLERS_CONFIG,$spus,CACHE_DAY);
            }else{
                return array();
            }
        }
        //查询转换
        $params['product_id'] = CommonLib::supportArray($spus, 'spus');

        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }

        $products = (new ProductModel())->topSellerProductLists($params);
        if(!empty($products['data'])){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * 低价产品列表
     * @param $params
     * @return array
     */
    public function underPriceLists($params){
        $price = isset($params['lowPrice']) && !empty($params['lowPrice']) ? $params['lowPrice'] : '0.99';
        $spus = $product_ids = $products = array();
        $country = isset($params['country']) ? trim($params['country']) : '';

        //类别映射
        if(!empty($params['lastCategory'])){
            $this->newCommonClassMap($params);
        }
        //获取underPrice配置的spu列表
        if(config('cache_switch_on')) {
            $spus = $this->redis->get(UNDERPRICE_CONFIG.'_'.$price.'_'.$country);
        }
        if(empty($spus)){
            //Luxury 节点人工配置 -- add by zhongning 20190514
            if($price == 'Luxury'){
                $spus = (new ConfigDataModel())->getDataConfig(['key'=>'UnderPrice'.'-'.$price]);
            }else{
                $extendModel = new ProductExtendModel();
                $priceArray = $extendModel->underPrice;
                //dx_product_under5,按国家获取spus
                $spus = $extendModel->find(['type'=>$priceArray[$price],'country'=>$country]);
                if(empty($spus['Spus'])){
                    //默认取other的价格
                    $spus = $extendModel->find(['type'=>$priceArray[$price],'country'=>'Other']);
                }
                $spus['spus'] = !empty($spus['Spus']) ? $spus['Spus'] : array();
                unset($spus['Spus']);
            }
            //缓存
            if(!empty($spus['spus'])){
                $this->redis->set(UNDERPRICE_CONFIG.'_'.$price.'_'.$country,$spus,CACHE_DAY);
            }
        }
        if(!empty($spus['spus'])){
            $params['product_id'] = CommonLib::supportArray($spus,'spus');
        }
        if(empty($params['product_id'])){
            return array();
        }
        $products = (new ProductModel())->underPriceProductLists($params);
        if(!empty($products['data'])){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * mvp商品
     * @param $params
     * @return array|bool
     */
    public function mvpProducts($params){
        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['lastCategory'] = $this->getMapClassByID($params['firstCategory']);
            unset($params['firstCategory']);
        }
        $products = (new ProductModel())->newArrivalsProductLists($params);
        if(!empty($products['data'])){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * staffPicks 商品
     * @param $params
     * @return array|bool
     */
    public function staffPicksProducts($params){

        $spus = $product_ids = array();
        //获取配置的spu列表
        if(config('cache_switch_on')) {
            $spus = $this->redis->get(STAFFPICKS_CONFIG);
        }
        if(empty($spus)){
            $spus = (new ConfigDataModel())->getDataConfig(['key'=>'StaffPicks']);
            if(isset($spus['spus']) && !empty($spus['spus'])){
                $this->redis->set(STAFFPICKS_CONFIG,$spus,CACHE_DAY);
            }else{
                return array();
            }
        }
        //格式化产品id
        $params['product_id'] = CommonLib::supportArray($spus,'spus');
        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }
        //查询产品
        $products = (new ProductModel())->topSellerProductLists($params);
        if(!empty($products['data'])){
            //格式化产品数据
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;

    }

    /**
     * presale 商品
     * @param $params
     * @return array|bool
     */
    public function presaleProducts($params){

        $spus = array();
        //获取topSeller配置的spu列表
        if(config('cache_switch_on')) {
            $spus = $this->redis->get(PRESALE_CONFIG);
        }
        if(empty($spus)){
            $spus = (new ConfigDataModel())->getDataConfig(['key'=>'Presale']);
            if(isset($spus['spus']) && !empty($spus['spus'])){
                $this->redis->set(PRESALE_CONFIG,$spus,CACHE_DAY);
            }else{
                return array();
            }
        }
        //查询转换
        $params['product_id'] = CommonLib::supportArray($spus, 'spus');

        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }
        //查询产品
        $products = (new ProductModel())->topSellerProductLists($params);
        if(!empty($products)){
            $products['data'] = $this->commonProdcutListData($products['data'],$params,'presale');
        }
        return $products;
    }

    /**
     * 产品评论星级详情
     */
    public function getSpuReviewsDetail($params){
        $products = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(REVIEWS_RATING_ . $params['product_id']);
        }
        if(empty($products)){
            $products = (new ProductModel())->getSpuReviewsDetail($params['product_id']);
            if(!empty($products)){
                if(empty($products['Reviews'])){
                    //初始化
                    $products['Reviews'] = [
                        'fiveStarCount'=>0, 'fourStarCount'=>0, 'threeStarCount'=>0, 'twoStarCount'=>0, 'oneStarCount'=>0,
                        'fiveStarRatio'=>0, 'fourStarRatio'=>0, 'threeStarRatio'=>0, 'twoStarRatio'=>0, 'oneStarRatio'=>0
                    ];
                }
                $products['Impression'] = isset($products['Impression']) ? $products['Impression'] : array();
                //评分
                if(isset($products['AvgRating'])){
                    if(empty($products['AvgRating']) || (int)$products['AvgRating'] == 0){
                        $products['AvgRating'] = 5;
                    }else{
                        $products['AvgRating'] = $products['AvgRating'] > 5 ? 5 : $products['AvgRating'];
                    }
                }else{
                    $products['AvgRating'] = 5;
                }
                $this->redis->set(REVIEWS_RATING_ . $params['product_id'],$products,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$products]);
    }

    /**
     * 品牌页面列表产品数据
     */
    public function selectBrandProduct($params){
        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }
        $products = (new ProductModel())->selectBrandProduct($params);
        if(!empty($products['data'])){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * 根据SKUID，获取产品ID
     */
    public function getProductIdBySkuId($params){
    	$params = isset($params['SkuId'])?$params['SkuId']:null;
    	$result = (new ProductModel())->getProductIdBySkuId($params);
    	return $result;
    }

    /**
     * 根据SKUIDs，获取产品IDs
     */
    public function getProductIdBySkuIds($params){
    	$returnData = array();
    	if(is_array($params['SkuIds'])){
    		foreach ($params['SkuIds'] as $k=>$v){
    			$result = (new ProductModel())->getProductIdBySkuId($v);
    			$returnData[$v] = isset($result['_id'])?$result['_id']:0;
    		}
    	}
    	
    	if(empty($returnData)){
    		return null;
    	}
    	return $returnData;
    }

    /**
     * 获取最新待审核产品
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getNextAuditProduct($params){
        return (new ProductModel())->getAuditProduct($params);
    }

    public function editInventoryBySkuIdArr($params){
    	$returnData = array();
    	if(is_array($params)){
    		foreach ($params as $k=>$v){
    			$tmpReturnData = array();
    			if(!isset($v['SKU']) || !isset($v['SKU'])){
    				$returnData['code'] = 0;
    				$returnData['msg'] = 'fatal error,the sku information is lose!';
    				return $returnData;
    				break;
    			}
    			$tmp_params['SkuId'] = $v['SKU'];
    			$tmp_params['Qty'] = $v['Quantity'];
    			$result = (new ProductModel())->editInventoryBySkuIdArr($tmp_params);
    			if($result){
    				$tmpReturnData['code'] = 1;
    			}else{
    				$tmpReturnData['code'] = 0;
    			}
    			$tmpReturnData['sku_id'] = $v['SKU'];
    			
    			$returnData[] = $tmpReturnData;
    		}
    	}
    	
    	return $returnData;
    }
    
    public function getAffiliateInfo($params){
        return (new ProductModel())->getAffiliateInfo($params);
    }


    /**
     * cart产品列表，获取信息，计算运费
     * @param $params
     * @return mixed
     */
    public function getCartProductList($params){
        $params['spu'] = CommonLib::supportArray($params['spu']);
        $shipping =  (new ProductModel())->getCartProductList($params);
        return apiReturn(['code'=>200, 'data'=>$shipping]);
    }

    /**
     * 获取产品信息，计算运费
     * @param $params
     * @return mixed
     */
    public function getProductToShipping($params){
        $products = (new ProductModel())->getProductByShipingUse($params);
        //第一个sku数据为默认SKU
        $DefaultSkus = isset($products['Skus'][0]) ? $products['Skus'][0] : array();
        $products['DefaultSkuId'] = isset($DefaultSkus['_id']) ? $DefaultSkus['_id'] : null;
        $products['DefaultSkuCode'] = isset($DefaultSkus['Code']) ? $DefaultSkus['Code'] : null;
        return $products;

    }

    /**
     * google推荐产品code前缀
     * @param $params
     * @return mixed
     */
    public function getProductLocalCode($params){
        return  (new ProductModel())->getProductLocalCode($params);
    }

    /**
     * 产品详情价格处理
     * @param $products
     * @param $discountLowPrice
     * @param $discountHightPrice
     */
    private function productPrice(&$products,$discountLowPrice,$discountHightPrice,$regionPrice = array(),$activityStatu = 0){
        //原价的价格区间
        $originalLowPrice = !empty($products['LowPrice']) ? (string)$products['LowPrice'] : '';//最低价格
        $originalHightPrice = !empty($products['HightPrice']) ? (string)$products['HightPrice'] : '';//最高价
        //非活动初始化数据
        if(!$activityStatu){
            $discountLowPrice = $discountHightPrice = 0;
        }

        unset($products['DiscountHightPrice'],$products['DiscountLowPrice']);
        //价格逻辑处理
        $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
        //特殊情况，有活动，但是只有一个SKU参加，活动价格是28.71，那么折扣价就是 DiscountLowPrice = DiscountHightPrice =28.71，但是原来销售价是区间价 24.97~63.79
        if($activityStatu){
            if(
                !empty($priceArray['OriginalHightPrice']) &&
                $priceArray['LowPrice'] > $priceArray['OriginalLowPrice'] &&
                $priceArray['LowPrice'] < $priceArray['OriginalHightPrice']
            ){
                $priceArray['OriginalLowPrice'] = $priceArray['OriginalHightPrice'];
            }
        }
        //商品展示的售价
        $products['LowPrice'] = $priceArray['LowPrice'];//销售价，如果有活动，销售价就是活动价格，没有就是原价
        $products['HightPrice'] = $priceArray['HightPrice'];
        //判断活动状态，如果是活动，只用活动价格展示，二者存一，不然折扣价，市场价会混乱 add by zhongning20190821
        $products['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
        $products['OriginalHightPrice'] = $priceArray['OriginalHightPrice'];
        if(!$activityStatu){
            //原价，优先市场价，如果市场价为空，那么就原价
            //需求更改，如有折扣比例，那么逆推市场价，全球统一折扣比例；add by zhongning 20191107
//            $products['OriginalLowPrice'] = !empty($products['LowListPrice']) && $products['LowListPrice'] > $priceArray['OriginalLowPrice'] ? (string)$products['LowListPrice'] : $priceArray['OriginalLowPrice'];
//            $products['OriginalHightPrice'] = !empty($products['HighListPrice']) && $products['HighListPrice'] > $priceArray['OriginalHightPrice'] ? (string)$products['HighListPrice'] : $priceArray['OriginalHightPrice'];
            //判断是否有市场折扣
            if(!empty($products['ListPriceDiscount'])){
                $products['Discount'] = (string)(1 - $products['ListPriceDiscount']);
                $products['OriginalLowPrice'] = (string)round($priceArray['LowPrice'] / (1 - $products['ListPriceDiscount']), 2);
                if(!empty($priceArray['HightPrice'])){
                    $products['OriginalHightPrice'] = (string)round($priceArray['HightPrice'] / (1 - $products['ListPriceDiscount']), 2);
                }
            }
        }
        //判断原价比销售价小
        if($products['OriginalLowPrice'] < $products['LowPrice']){
            $products['OriginalLowPrice'] = $products['OriginalHightPrice'] = '';
        }
        if(!empty($products['OriginalHightPrice']) && $products['OriginalHightPrice'] < $products['HightPrice'] && empty($products['OriginalLowPrice'])){
            $products['OriginalLowPrice'] = $products['OriginalHightPrice'] = '';
        }

        //区间价格一样，高的为空
        if($products['OriginalLowPrice'] == $products['OriginalHightPrice']){
            $products['OriginalHightPrice'] = '';
        }
        unset($products['HighListPrice'],$products['LowListPrice']);
    }

    /**
     * @param $products
     * @param $lang
     * @return array
     */
    private function productClassData($products,$lang){
        $classData = array();
        //分类信息
        $classArray = explode('-',$products['CategoryPath']);
        $classInfo = (new ProductClassModel())->getClassDetail(['id'=>(int)end($classArray)]);
        if($classInfo['type'] == 1){
            //ERP类别数据
            $classArray = explode('-',$classInfo['id_path']);
            foreach($classArray as $level => $class_id){
                $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                if(!empty($result[$level])){
                    $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                    $title = $result[$level]['title_en'];
                    //如果多语种没数据，默认取英文
                    if(DEFAULT_LANG != $lang) {
                        $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                            $result[$level]['Common'][$lang] : $title;
                    }
                    $classData[$level]['title'] = $title;
                    $classData[$level]['id'] = $result[$level]['id'];
                }
            }
            return $classData;
        }
        if( isset($classInfo['pdc_ids']) && !empty($classInfo['pdc_ids'])){
            $data = (new ProductClassModel())->getClassDetail(['id'=>(int)$classInfo['pdc_ids'][0]]);
            if(!empty($data)){
                //非一级类别
                if($data['level'] != 1){
                    $id_path = explode('-',$data['id_path']);
                    foreach($id_path as $level => $class_id){
                        $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                        if(!empty($result[$level])){
                            $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                            $title = $result[$level]['title_en'];
                            //如果多语种没数据，默认取英文
                            if(DEFAULT_LANG != $lang) {
                                $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                                    $result[$level]['Common'][$lang] : $title;
                            }
                            $classData[$level]['title'] = $title;
                            $classData[$level]['id'] = $result[$level]['id'];
                        }
                    }
                }else{
                    //只有一级类别
                    $result[0] =  $data;
                    if(!empty($data)){
                        $title = $data['title_en'];
                        //如果多语种没数据，默认取英文
                        if(DEFAULT_LANG != $lang) {
                            $title = isset($data['Common'][$lang]) && !empty($data['Common'][$lang]) ? $data['Common'][$lang] : $title;
                        }
                        $classData[0]['title'] = $title;
                        $classData[0]['hrefTitle'] = $data['rewritten_url'].'-'.$data['id'];
                        $classData[0]['id'] = $data['id'];
                    }
                }
            }else{
                //映射数据为空的情况下，展示PDC类别数据
                $classArray = explode('-',$products['CategoryPath']);
                foreach($classArray as $level => $class_id){
                    $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                    if(!empty($result[$level])){
                        $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                        $title = $result[$level]['title_en'];
                        //如果多语种没数据，默认取英文
                        if(DEFAULT_LANG != $lang) {
                            $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                                $result[$level]['Common'][$lang] : $title;
                        }
                        $classData[$level]['title'] = $title;
                        $classData[$level]['id'] = $result[$level]['id'];
                    }
                }
            }
        }else{
            //没有映射，只能展示PDC原来的类别
            $classArray = explode('-',$products['CategoryPath']);
            foreach($classArray as $level => $class_id){
                $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                if(!empty($result[$level])){
                    $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                    $title = $result[$level]['title_en'];
                    //如果多语种没数据，默认取英文
                    if(DEFAULT_LANG != $lang) {
                        $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                            $result[$level]['Common'][$lang] : $title;
                    }
                    $classData[$level]['title'] = $title;
                    $classData[$level]['id'] = $result[$level]['id'];
                }
            }
        }
        return $classData;
    }

    /**
     * 根据SPU获取列表
     * @param $params
     * @return array|bool
     */
    public function getProductListBySku($params){
        $products = $spus = array();
        $cacheKey = CommonLib::getCacheKey($params);
        if(config('cache_switch_on')) {
            $products = $this->redis->get("PRODUCT_LIST_SKUS_" . $cacheKey);
        }
        if(empty($products)){
            if(!empty($params['skus'])){
                $spus = explode(',',$params['skus']);
            }
            if(!empty($spus)){
                //格式化
                $sku_id = CommonLib::supportArray($spus);
                $products = (new ProductModel())->selectProduct(['sku_id' => $sku_id]);
                if(!empty($products)){
                    $products = $this->commonProdcutListData($products,$params);
                    //币种切换
                    if($params['currency'] != DEFAULT_CURRENCY){
                        $products = $this->changeCurrentRate($products,$params['currency']);
                    }
                }
                if(is_array($products) && !empty($products)){
                    $this->redis->set("PRODUCT_LIST_SKUS_" . $cacheKey,$products,CACHE_HOUR);
                }
            }
        }
        return $products;
    }

    /**
     * 根据SPU获取列表
     * @param $params
     * @return array|bool
     */
    public function getProductListBySpu($params){
        $data = $spus = array();
        $cacheKey = CommonLib::getCacheKey($params);
        if(config('cache_switch_on')) {
            $data = $this->redis->get("PRODUCT_LIST_SPUS_" . $cacheKey);
        }
        if(empty($products)) {
            if (!empty($params['spus'])) {
                $spus = explode(',', $params['spus']);
            }
            if (!empty($spus)) {
                //格式化
                $sku_id = CommonLib::supportArray($spus);
                $products = (new ProductModel())->selectProduct(['product_id' => $sku_id,'status' => ['in',[1,3,5]]]);
                if (!empty($products)) {
                    $products = $this->commonProdcutListData($products, $params);
                    //币种切换
                    if ($params['currency'] != DEFAULT_CURRENCY) {
                        $products = $this->changeCurrentRate($products, $params['currency']);
                    }
                }
                //按照传入sku顺序返回
                foreach ($spus as $key => $spu) {
                    $searchData = CommonLib::filterArrayByKey($products, 'id', $spu);
                    if (!empty($searchData)) {
                        $data[$key] = $searchData;
                    }
                }
                if (is_array($data) && !empty($data)) {
                    $data = array_values($data);
                    $this->redis->set("PRODUCT_LIST_SPUS_" . $cacheKey, $data, CACHE_HOUR);
                }
            }
        }
        return $data;
    }

    /**
     * topseller 产品列表
     * @param $params
     * @return array
     */
    public function getRecommendNotFoundProduct($params){
        $spus = array();
        //获取topSeller配置的spu列表
        if(config('cache_switch_on')) {
            $spus = $this->redis->get('TOP_STAFF_MERGE_CONFIG_DATA');
        }
        if(empty($spus)){
            $spus = array();
            $staffArray = (new ConfigDataModel())->getDataConfig(['key'=>'StaffPicks']);
            if(isset($staffArray['spus']) && !empty($staffArray['spus'])){
                $spus = $staffArray['spus'];
            }
            $topArray = (new ConfigDataModel())->getDataConfig(['key'=>'TopSellers']);
            if(isset($topArray['spus']) && !empty($topArray['spus'])){
                $spus = array_merge($spus,$topArray['spus']);
            }
            $spus = array_values(array_unique($spus));
            shuffle($spus);
            if(!empty($spus)){
                $this->redis->set('TOP_STAFF_MERGE_CONFIG_DATA',$spus,CACHE_DAY);
            }else{
                return array();
            }
        }
        //查询转换
        $params['product_id'] = CommonLib::supportArray($spus);

        $products = (new ProductModel())->topSellerProductLists($params);
        if(!empty($products)){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * topseller 产品列表
     * @param $params
     * @return array
     */
    public function getTopProductByOrder($params){
        $product_ids = array();
        if(empty($params['product_ids'])){
            $topArray = (new ConfigDataModel())->getDataConfig(['key'=>'TopSellers']);
            if(isset($topArray['spus']) && !empty($topArray['spus'])){
                $product_ids = $topArray['spus'];
            }
        }else{
            $product_ids = $params['product_ids'];
        }
        //获取100个
        $products = (new ProductModel())->selectProduct(['product_id' => CommonLib::supportArray($product_ids)]);
        if(!empty($products)){
            $products = $this->commonProdcutListData($products,$params);
        }
        return $products;
    }

    /**
     * topseller 产品列表
     * @param $params
     * @return array
     */
    public function getTopDataByConfigCategory($params){
        $productModel = new ProductModel();
        $classModel = new ProductClassModel();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? trim($params['country']) : null;
        $categorys = $product_ids = array();
        $config = (new SysConfigModel())->getSysCofig('MobileIndexCategory');
        if(!empty($config['ConfigValue'])){
            $categorys = json_decode($config['ConfigValue'],true);
        }
        //每天定时任务跑的数据
        $products = (new ProductTopSellerModel())->getNewDataTopData(['category','_id'=>false]);
        foreach($categorys as $class_id => $img){
            if(!empty($products['category'][$class_id])){
                //只取20个
                if(count($products['category'][$class_id]) > 20){
                    $product_ids[$class_id] = array_splice($products['category'][$class_id],0,20);
                }else{
                    $product_ids[$class_id] = $products['category'][$class_id];
                }
            }else{
                //特殊情况，首页展示产品不能为空，如果该类别真的没有数据，那么就随便查询这个类别下的产品
                $findData  = $productModel->selectProduct(['lastCategory' => (int)$class_id,'salesCounts' =>1,'limit'=>20]);
                $product_ids[$class_id] = CommonLib::getColumn('_id',$findData);
            }
        }

        $result = array();
        //查询产品数据
        foreach($product_ids as $class_id => $spus){
            $classinfo = $classModel->getClassDetail(['id'=>(int)$class_id],$lang);
            //按分类装好产品数据
            $products = $productModel->selectProduct(['product_id' => CommonLib::supportArray($spus)]);
            $result[$class_id]['product'] = $this->commonProdcutListData($products,['lang'=> $lang,'country'=>$country]);
            $result[$class_id]['name'] = !empty($classinfo['Common'][$lang]) ? $classinfo['Common'][$lang] : $classinfo['title_en'];
            $result[$class_id]['imgUrl'] = !empty($categorys[$class_id]) ? $categorys[$class_id] : '';
            $result[$class_id]['classId'] = $class_id;
        }
        return $result;
    }

    /**
     * 不同的coupon随机获取几个产品展示
     * @param $params
     * @return array
     */
    public function getCouponsProduct($params)
    {
        $result = array();
        $productModel = new ProductModel();
        $couponData = (new CouponModel())->selectCouponInfo([
            'coupon_id' => $params['coupon_ids'],
            'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG
        ]);
        //获取指定产品
        foreach ($couponData as $val) {
            $coupon_id = $val['CouponId'];
            $LimitData = isset($val['CouponRuleSetting']['LimitData']['Data']) ? $val['CouponRuleSetting']['LimitData']['Data'] : '';
            $CouponSku = preg_split('/[,;\r\n]+/s', $LimitData);
            //随机获取20个
            $product_skus = CommonLib::getRandArray($CouponSku,20);
            $products  = $productModel->selectProduct(['sku_code' => ['in',$product_skus]]);
            $result[$coupon_id] = $this->commonCouponProductData($products,$params,$coupon_id);
        }
        return $result;
    }

    /**
     * 不同的coupon随机获取几个产品展示
     * @param $params
     * @return array
     */
    public function getCouponProductList($params)
    {
        $couponSku = $select_product = array();

        if(config('cache_switch_on')) {
            $couponSku = $this->redis->get('CONFIG_COUPON_SKU_'.$params['coupon_id']);
        }
        if(empty($couponSku)){
            $couponData = (new CouponModel())->selectCouponInfo([
                'coupon_id' => $params['coupon_id'],
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG
            ]);
            $LimitData = isset($couponData[0]['CouponRuleSetting']['LimitData']['Data']) ? $couponData[0]['CouponRuleSetting']['LimitData']['Data'] : '';
            $couponSku = preg_split('/[,;\r\n]+/s', $LimitData);
            if(!empty($couponSku)){
                $this->redis->set('CONFIG_COUPON_SKU_'.$params['coupon_id'],$couponSku,CACHE_DAY);
            }
        }
        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }
        //用户在首页选择的产品，默认展示到第一个
        if(empty($params['firstCategory']) && $params['page'] == 1){
            if(!empty($params['product_id'])){
                $select_product = (new ProductModel())->selectProduct(['product_id' => (int)$params['product_id']]);
            }
        }
        $products = (new ProductModel())->topSellerProductLists([
            'page' => $params['page'],
            'sku_code'=>['in',$couponSku],
            'firstCategory' => isset($params['firstCategory']) ? $params['firstCategory'] : null,
            'sortSalesCounts' => 1
        ]);
        if(!empty($products['data'])){
            //合并数据，第一个展示用户选中产品，只有第一页才展示
            if(!empty($select_product[0])){
                $products['data'][0] = $select_product[0];
            }
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }
        return $products;
    }

    /**
     * hotproduct产品列表
     * @param $params
     * @return array
     */
    public function getHotProductList($params){
        $result = $select_product = array();
        //每天定时任务跑的数据
        $products = (new ProductTopSellerModel())->getNewDataTopData(['products','_id'=>false]);

        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }
        //用户在首页选择的产品，默认展示到第一个
        if(empty($params['firstCategory']) && $params['page'] == 1){
            if(!empty($params['product_id'])){
                $select_product = (new ProductModel())->selectProduct(['product_id' => (int)$params['product_id']]);
            }
        }
        //查询产品信息
        if(!empty($products['products'])){
            $result = (new ProductModel())->topSellerProductLists([
                'page' => $params['page'],
                'product_id'=>CommonLib::supportArray($products['products']),
                'firstCategory' => isset($params['firstCategory']) ? $params['firstCategory'] : null,
                'sortSalesCounts' => 1
            ]);
            if(!empty($result['data'])){
                //合并数据，第一个展示用户选中产品，只有第一页才展示
                if(!empty($select_product[0])){
                    $result['data'][0] = $select_product[0];
                }
                $result['data'] = $this->commonProdcutListData($result['data'],$params);
            }
        }
        return $result;
    }

    /**
     * 新品的热卖产品
     * @param $params
     * @return array
     */
    public function getHotNewArrivalsList($params){
        $result = array();
        //每天定时任务跑的数据
        $products = (new ProductTopSellerModel())->getNewDataTopData(['new','_id'=>false]);

        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }

        //查询产品信息
        if(!empty($products['new'])){
            $result = (new ProductModel())->topSellerProductLists([
                'page' => $params['page'],
                'product_id'=>CommonLib::supportArray($products['new']),
                'firstCategory' => isset($params['firstCategory']) ? $params['firstCategory'] : null,
                'sortSalesCounts' => 1
            ]);
            if(!empty($result)){
                $result['data'] = $this->commonProdcutListData($result['data'],$params);
            }
        }
        return $result;
    }
}
