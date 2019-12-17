<?php
namespace app\app\services;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\app\model\ConfigDataModel;
use app\app\model\ProductActivityModel;
use app\app\model\ProductClassModel;
use app\app\model\ProductMappingsModel;
use app\app\model\ProductModel;
use think\Cache;
use think\Exception;
use think\Log;


/**
 * 产品接口
 */
class ProductService extends BaseService
{

    //产品体积单边长不能超过60,平邮和挂号不走
    const PRODUCT_MAX_LENGTH = 60;
    //产品体积和不能超过90,平邮和挂号不走
    const PRODUCT_MAX_TOTAL = 90;
    //产品重量不能超过2kg,平邮和挂号不走
    const PRODUCT_MAX_WEIGHT = 2;
    //运费手续费
    const SHIPPING_RATE = 0.03;
    //店铺ID
    const ERPSELLER = 333;
    const SZSELLER = 666;
    const USSELLER = 777;
    const SKSELLER = 888;
    const HKSELLER = 999;
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
        $country = isset($params['country']) ? $params['country'] : null;

        $data = (new ProductModel())->findProduct($params);
        if(!empty($data)){
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
                        }
                    }
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
        $country = isset($params['country']) ? $params['country'] : '';
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
                    $this->redis->set(NEW_ARRIVALS_DATA_ .$params['category'].'_'.$lang.'_'.$country, $result, CACHE_HOUR);
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
    public function    getNewProduct($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $country = isset($params['country']) ? $params['country'] : '';
        $self_id = isset($params['spu']) ? $params['spu'] : null;
        $result = array();

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

    }

    /**
     * 使用接口：一级分类页面
     * 使用接口：二级分类数据接口
     * @param $params
     * @return array
     */
    public function getSecCategroy($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;

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
        $products = (new ProductModel())->selectProduct($params);
        if(!empty($products)){
            $data = $this->commonProdcutListData($products,$params);
            $data['code']=200;
        }
        return $data;
    }


    /**
     * 二级、三级分类页面，产品列表数据
     *
     * @param $params
     * @return array
     */
    public function getCategoryPageLists($params){
        $products = (new ProductModel())->categoryPageLists($params);
        $products['code']=200;
        if(!empty($products)){
            $products['data'] = $this->commonProdcutListData($products['data'],$params);
        }else{
            $products=[];
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
            $products = $this->redis->get('APP_'.PRODUCT_INFO_ . $params['spu'].'_'.$lang.'_'.$currency.'_'.$country);
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

            //第二步：查询当前产品详情信息
            $products = (new ProductModel())->getBaseSpuDetail($params['spu']);

            //第三步：增加查询SKU_id add zhongning 20190711
            if(empty($products)){
                $products = (new ProductModel())->getBaseSpuDetailBySkusID($params['spu']);
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

                //默认SKUid
                $products['DefaultSkuId'] = $DefaultSkus['_id'];
                $products['DefaultSkuCode'] = $DefaultSkus['Code'];

                //首图
                $products['FirstProductImage'] = isset($products['FirstProductImage']) ? $products['FirstProductImage'] : '';
                if(empty($products['FirstProductImage'])){
                    $products['FirstProductImage'] = isset($products['ImageSet']['ProductImg'][0]) ? $products['ImageSet']['ProductImg'][0] : '';
                }

                //产品图片分组-- 前端展示需要
                $products['ProductImg'] = $products['ImageSet']['ProductImg'];
                $products['RewrittenUrl'] = isset($products['RewrittenUrl']) ? $products['RewrittenUrl'] : null;

                //关键字
                $products['Keywords'] = isset($products['Keywords']) ? implode(',',$products['Keywords']) : '';

                //折扣后的价格区间,有些产品数据库保存的是字符串类型，NULL add by zhongning 2019-05-16
                $discountLowPrice = !empty($products['DiscountLowPrice']) && $products['DiscountLowPrice'] != 'NULL' ? (string)$products['DiscountLowPrice'] : '';//最低价格
                $discountHightPrice = !empty($products['DiscountHightPrice']) && $products['DiscountHightPrice'] != 'NULL' ? (string)$products['DiscountHightPrice'] : '';//最高价
                //价格
                $this->productPrice($products,$discountLowPrice,$discountHightPrice,$regionPrice,$activityStatu);

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
//                    $products['Skus'][$key]['SalesLimit'] = '';
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
                    }else{
                        //没有销售属性的产品
                        $products['AttrPriceList'] = [];
                        $products['AttrPriceList'][''] = $products['Skus'][$key];
                    }
                }
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
                        $newAttrList[$i]['attr'] = $newAttr;
                        $newAttrList[$i]['_id'] = $attrs['id'];
                        $newAttrList[$i]['name'] = $attrs['name'];
                        $i++;
                    }
                    $products['AttrList'] =  $newAttrList;
                }

                //只有一个销售属性
                if(count($products['AttrPriceList']) == 1){
                    $products['AttrPriceList'][''] = array_pop($products['AttrPriceList']);
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
                //币种符号
                $products['currencyCode'] = DEFAULT_CURRENCY;
                $products['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
                if(self::DEFAULT_CURRENCY != $currency) {
                    $products['currencyCode'] = $currency;
                    $products['currencyCodeSymbol'] = $this->getCurrencyCode($currency);
                }
                //删除数据
                unset($products['FilterOptions'],$products['ImageSet']);
                $this->redis->set('APP_'.PRODUCT_INFO_ . $params['spu'].'_'.$lang.'_'.$currency.'_'.$country,$products,CACHE_HOUR);
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
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $products = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(PRODUCT_DESCRIPTION_ . $params['spu'].'_'.$params['lang']);
        }
        if(empty($products)){
            $products = (new ProductModel())->getSpuDescriptions($params['spu']);

            if(!empty($products)){
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
                $products['PackingList']['Title'] = isset($products['PackingList']['Title']) ? $products['PackingList']['Title'] : '';

                if(DEFAULT_LANG != $params['lang']){
                    $productMultiLang = $this->getProductMultiLang($params['spu'],$params['lang']);
                    $products['Descriptions'] =
                        isset($productMultiLang['Descriptions'][$params['lang']]) && !empty($productMultiLang['Descriptions'][$params['lang']]) ?
                            $productMultiLang['Descriptions'][$params['lang']] : $products['Descriptions'];//默认英语
                }
                $products['Descriptions'] = htmlspecialchars_decode($products['Descriptions']);
                $products['PackingList']['Title'] = htmlspecialchars_decode($products['PackingList']['Title']);

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
                $this->redis->set(PRODUCT_SHIPPING_INFO_ . $params['spu'].'_'.$params['country'],$shipping,CACHE_DAY);
            }
        }
        return $shipping;
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
        if(!empty($products)){
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
        $country = isset($params['country']) ? $params['country'] : '';

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
        if(!empty($products)){
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
        if(!empty($products)){
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
    private function productPrice(&$products,$discountLowPrice,$discountHightPrice,$regionPrice=array(),$activityStatu=0){
        //原价的价格区间
        $originalLowPrice = !empty($products['LowPrice']) ? (string)$products['LowPrice'] : '';//最低价格
        $originalHightPrice = !empty($products['HightPrice']) ? (string)$products['HightPrice'] : '';//最高价

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

        if($activityStatu){
            //原价，优先市场价，如果市场价为空，那么就原价
            $products['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
            $products['OriginalHightPrice'] = $priceArray['OriginalHightPrice'];
        }else{
            //原价，优先市场价，如果市场价为空，那么就原价
            $products['OriginalLowPrice'] = !empty($products['LowListPrice']) && $products['LowListPrice'] > $priceArray['OriginalLowPrice'] ? (string)$products['LowListPrice'] : $priceArray['OriginalLowPrice'];
            $products['OriginalHightPrice'] = !empty($products['HighListPrice']) && $products['HighListPrice'] > $priceArray['OriginalHightPrice'] ? (string)$products['HighListPrice'] : $priceArray['OriginalHightPrice'];
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
     * 新品区域数据
     * @param $params
     * @return mixed
     */
    public function getNewArrivalsTemptale($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $adService = new AdvertisingService();
        $configService = new ConfigDataService();

        $adData = $adService->getLists(['key'=>['dx_home_new_1','dx_home_top_1','dx_home_under_1']]);
        if(!empty($adData)){
            foreach($adData as $key => $val){
                $advertisingData = $this->getBannerInfos($val,$lang);

                //新品
                if($val['Key'] == 'dx_home_new_1'){
                    $products = array();
                    //新品位置广告数据
                    $data['new']['advertising'] = !empty($advertisingData) ? array_shift($advertisingData) : [];
                    //新品数据
                    $productParams['lang'] = $lang;
                    $productParams['limit'] = 50;
                    $productParams['isNewProduct'] = true;
                    $newProduct = $this->getNewProduct($productParams);
                    if(isset($newProduct['data']) && !empty($newProduct['data'])){
                        $products = CommonLib::getRandArray($newProduct['data'],8);
                    }
                    //币种切换
                    if($currency != DEFAULT_CURRENCY){
                        if(!empty($products)){
                            $products = $this->changeCurrentRate($products,$currency);
                        }
                    }
                    $data['new']['product'] = $products;
                }

                //topSeller
                if($val['Key'] == 'dx_home_top_1'){
                    $products = array();
                    $data['top']['advertising'] = !empty($advertisingData) ? array_shift($advertisingData) : [];
                    $productParams = array();
                    $productParams['key'] = 'TopSellers';
                    $productParams['lang'] = $lang;
                    $products = $configService->getProductDataByKey($productParams);
                    if(!empty($products)){
                        $products = CommonLib::getRandArray($products,8);
                        //币种切换
                        if($currency != DEFAULT_CURRENCY){
                            $products = $this->changeCurrentRate($products,$currency);
                        }
                    }
                    $data['top']['product'] = $products;
                }

                //upder数据
                if($val['Key'] == 'dx_home_under_1'){
                    $products = array();
                    $data['under']['advertising'] = !empty($advertisingData) ? array_shift($advertisingData) : [];

                    $productParams = array();
                    $productParams['key'] = 'UnderPrice-0.99';
                    $productParams['lang'] = $lang;
                    $products = $configService->getProductDataByKey($productParams);
                    if(!empty($products)){
                        $products = CommonLib::getRandArray($products,8);
                        //币种切换
                        if($currency != DEFAULT_CURRENCY){
                            $products = $this->changeCurrentRate($products,$currency);
                        }
                    }
                    $data['under']['product'] = $products;
                }
            }
        }
        return $data;
    }


    /**
     * 新品区域数据
     * @param $params
     * @return mixed
     */
    public function getSmartphonesTemplate($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $adService = new AdvertisingService();
        $configService = new ConfigDataService();
        $data = array();

        //广告数据
        $adData = $adService->getLists(['key'=>['dx_home_smartphone_1','dx_home_smartphone_2','dx_home_smartphone_3']]);
        if(!empty($adData)){
            foreach($adData as $key => $val){
                if($val['Key'] == 'dx_home_smartphone_1'){
                    //公共方法整合banner数据
                    $data['advertising'] = $this->getBannerInfos($val,$lang);
                }else{
                    $topAd = $this->getBannerInfos($val,$lang);
                    if(!empty($topAd)){
                        $data['top'][] = array_shift($topAd);
                    }
                }
            }
        }

        //产品数据
        $productParams = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'key'=>'Smartphones'//SmartPhones 首页（8）产品
        ];
        $products = $configService->getProductDataByKey($productParams);
        if(!empty($products)){
            $products = CommonLib::getRandArray($products,8);
            //币种切换
            if($currency != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$currency);
            }
            $data['product'] = $products;
        }
        return $data;
    }

    /**
     * 新品区域数据
     * @param $params
     * @return mixed
     */
    public function getElectronicsTemplate($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $adService = new AdvertisingService();
        $configService = new ConfigDataService();
        $data = array();

        //广告数据
        $adData = $adService->getAdvertisingInfo(['key'=>'dx_home_ele_1']);
        if(isset($adData['data']) && !empty($adData['data'])){
            $data['advertising'] = $this->getBannerInfos($adData['data'],$lang);
        }
        //产品数据
        $productParams = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'key'=>'Electronics'//SmartPhones 首页（8）产品
        ];
        $products = $configService->getProductDataByKey($productParams);
        if(!empty($products)){
            $products = CommonLib::getRandArray($products,8);
            //币种切换
            if($currency != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$currency);
            }
            $data['product'] = $products;
        }
        return $data;
    }


    /**
     * 新品区域数据
     * @param $params
     * @return mixed
     */
    public function getDiyAndFunTemplate($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $adService = new AdvertisingService();
        $configService = new ConfigDataService();
        $data = array();

        //广告数据
        $adData = $adService->getLists(['key'=>['dx_home_diy_1','dx_home_diy_2','dx_home_diy_3']]);
        if(!empty($adData)){
            foreach($adData as $key => $val){
                if($val['Key'] == 'dx_home_diy_1'){
                    //公共方法整合banner数据
                    $data['advertising'] = $this->getBannerInfos($val,$lang);
                }else{
                    $topAd = $this->getBannerInfos($val,$lang);
                    if(!empty($topAd)){
                        $data['top'][] = array_shift($topAd);
                    }
                }
            }
        }

        //产品数据
        $productParams = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'key'=>'DiyAndFun'//SmartPhones 首页（8）产品
        ];
        $products = $configService->getProductDataByKey($productParams);
        if(!empty($products)){
            $products = CommonLib::getRandArray($products,8);
            //币种切换
            if($currency != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$currency);
            }
            $data['product'] = $products;
        }
        return $data;
    }


    /**
     * 新品区域数据
     * @param $params
     * @return mixed
     */
    public function getIndoorAndOutDoorTemplate($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $adService = new AdvertisingService();
        $configService = new ConfigDataService();
        $data = array();

        //广告数据
        $adData = $adService->getAdvertisingInfo(['key'=>'dx_home_indoor_1']);
        if(isset($adData['data']) && !empty($adData['data'])){
            $data['advertising'] = $this->getBannerInfos($adData['data'],$lang);
        }
        //产品数据
        $productParams = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'key'=>'IndoorAndOutdoor'//SmartPhones 首页（8）产品
        ];
        $products = $configService->getProductDataByKey($productParams);
        if(!empty($products)){
            $products = CommonLib::getRandArray($products,8);
            //币种切换
            if($currency != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$currency);
            }
            $data['product'] = $products;
        }
        return $data;
    }

    /**
     * 新品区域数据
     * @param $params
     * @return mixed
     */
    public function getBrandsTemplate($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $adService = new AdvertisingService();
        $configService = new ConfigDataService();
        $data = $result = array();


        //广告数据
        $adData = $adService->getAdvertisingInfo(['key'=>'dx_home_brands_2']);
        if(isset($adData['data']) && !empty($adData['data'])){
            $data['advertising'] = $this->getBannerInfos($adData['data'],$lang);
        }
        //品牌LOGO
        $data['brands'] = [];
        $result = $configService->getBrandsLogo();
        if(!empty($result)){
            foreach($result as $key => $value){
                $result[$key]['ImageUrl'] = IMG_URL.$value['Brand_Icon_Url'];
                $result[$key]['LinkUrl'] = '/s/'.$value['BrandName'];
                $result[$key]['MainText'] = $value['BrandName'];
                $result[$key]['SubText'] = $value['BrandName'];
            }
            $data['brands'] = CommonLib::getRandArray($result,12);
        }

        //产品数据
        $productParams = [
            'lang' => $lang ,//当前语种
            'currency' => $currency,//当前币种
            'key'=>'Brands'//SmartPhones 首页（8）产品
        ];
        $products = $configService->getProductDataByKey($productParams);
        if(!empty($products)){
            $products = CommonLib::getRandArray($products,8);
            //币种切换
            if($currency != DEFAULT_CURRENCY){
                $products = $this->changeCurrentRate($products,$currency);
            }
            $data['product'] = $products;
        }
        return $data;
    }

    /**
     * 这个产品到某个国家各种运费方式的计算
     * @param $params
     * @param $currentRate
     * @return array2611620
     */
    /**
     * 这个产品到某个国家各种运费方式的计算
     * @param $params
     * @param $currentRate
     * @return array
     */
    public function countProductShipping($params,$currentRate = ''){
        try{
            $shippingCache = array();
            $shippingProvince = isset($params['province'])?trim($params['province']):'';
            $shippingCountry = isset($params['country'])?trim($params['country']):'';
            //新增初始化运费缓存
            if(isset($params['count']) && $params['count'] == 1){
                $cacheKey = CommonLib::getCacheKey($params);
                if(config('cache_switch_on')) {
                    $shippingCache = $this->redis->get("PRODUCT_SHIPPING_COST_" .$params['spu'].'_' .$cacheKey);
                }
                if(is_array($shippingCache) && count($shippingCache) > 0){
                    return $shippingCache;
                }
            }

            $nocnoc = array();
            //判断缓存，是否有这个产品的信息
            $product = $this->getProductToCountShipping($params['spu']);

//            $product = $this->getSpuBaseInfo(['spu'=>$params['spu'],'lang'=>$params['lang'],'currency'=>$params['currency']]);
            if(empty($product)){
                return array();
            }
            $packingList = isset($product['PackingList']) ? $product['PackingList'] : null;
            if(empty($packingList)){
                return array();
            }
            $count = $params['count'];
            $isMvp = isset($product['IsMVP']) ? $product['IsMVP'] : 1;
            $seller_id = isset($product['StoreID']) ? $product['StoreID'] : null;
            $second_category = isset($product['SecondCategory']) ? $product['SecondCategory'] : 0;
            $params['country'] = trim($params['country']);

            //产品重量
            $productWeight = $this->getPorductWeight($count,$packingList);
            //产品长宽高,长宽高之和
            $dimensions = isset($packingList['Dimensions']) && !empty($packingList['Dimensions']) ? explode('-',$packingList['Dimensions']) : array();
            $length = isset($dimensions[0]) ? $dimensions[0] : 0;
            $width = isset($dimensions[1]) ? $dimensions[1] : 0;
            $hight = isset($dimensions[2]) ? $dimensions[2] : 0;
            $productTotal = $length + $width + $hight;

            //国家名称
            $service = new IndexService();
            $arr = $service->getCountryInfo(['Code'=>$params['country']]);
            $CountryName = isset($arr['Name']) ? $arr['Name'] : null;
            if(empty($CountryName)){
                return array();
            }

            $shippingDays = array();
            //后台配置的运费天数
            $shippingDaysConfig = $service->getShippingTime();
            if(isset($shippingDaysConfig['ConfigValue']) && !empty($shippingDaysConfig['ConfigValue'])){
                $shippingDays = json_decode(htmlspecialchars_decode($shippingDaysConfig['ConfigValue']),true);
            }
            if(empty($currentRate)){
                //币种切换
                if($params['currency'] != DEFAULT_CURRENCY){
                    $rateService = new rateService();
                    $currentRate = $rateService->getCurrentRate($params['currency']);
                }
            }
            //人民币切换美元
            $rmbRate = (new rateService())->getCurrentRate(DEFAULT_CURRENCY,'CNY');

            //NOCNOC国家配置
            if(in_array($params['country'],config('nocnoc_country'))){
                //916数据，不走nocnoc -- 张恒
                if($seller_id != self::SKSELLER){

                    ///巴西的只有圣保罗才支持NOCNOC tinghu.liu 20191011
                    if (strtolower($shippingCountry) == 'br'){
                        if (!empty($shippingProvince)){ //判断是否为空，因为cart页面没有收货地址的省份，但是需要展示NOCNOC tinghu.liu 20191011
                            if (strtolower($shippingProvince) == 'sao paulo'){
                                $nocnoc[0]['Cost'] = 4;
                                if($params['currency'] != DEFAULT_CURRENCY){
                                    $nocnoc[0]['Cost'] = sprintf("%01.2f",4 * $currentRate);
                                }
                                $nocnoc[0]['ShippingFee'] = 2;
                                $nocnoc[0]['ShippingService'] = 'NOCNOC';
                                $nocnoc[0]['OldShippingService'] = 'NOCNOC';
                                $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 '.lang('shipping_days',[],$params['lang']);
                                $nocnoc[0]['CountryCode'] = $params['country'];
                                $nocnoc[0]['CountryName'] = $CountryName;
                                $nocnoc[0]['TrackingInformation'] = lang('Available',[],$params['lang']);
                            }
                        }else{
                            $nocnoc[0]['Cost'] = 4;
                            if($params['currency'] != DEFAULT_CURRENCY){
                                $nocnoc[0]['Cost'] = sprintf("%01.2f",4 * $currentRate);
                            }
                            $nocnoc[0]['ShippingFee'] = 2;
                            $nocnoc[0]['ShippingService'] = 'NOCNOC';
                            $nocnoc[0]['OldShippingService'] = 'NOCNOC';
                            $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 '.lang('shipping_days',[],$params['lang']);
                            $nocnoc[0]['CountryCode'] = $params['country'];
                            $nocnoc[0]['CountryName'] = $CountryName;
                            $nocnoc[0]['TrackingInformation'] = lang('Available',[],$params['lang']);


                        }

                    }else{
                        $nocnoc[0]['Cost'] = 4;
                        if($params['currency'] != DEFAULT_CURRENCY){
                            $nocnoc[0]['Cost'] = sprintf("%01.2f",4 * $currentRate);
                        }
                        $nocnoc[0]['ShippingFee'] = 2;
                        $nocnoc[0]['ShippingService'] = 'NOCNOC';
                        $nocnoc[0]['OldShippingService'] = 'NOCNOC';
                        $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 '.lang('shipping_days',[],$params['lang']);
                        $nocnoc[0]['CountryCode'] = $params['country'];
                        $nocnoc[0]['CountryName'] = $CountryName;
                        $nocnoc[0]['TrackingInformation'] = lang('Available',[],$params['lang']);
                    }
                }
            }

            //判断是否是手机,成人用品，无人飞机，过滤印度,add by zhongning 20191031
            if($params['country'] == 'IN'){
                if($second_category == 52 || $second_category == 1800203 || $second_category == 1800163
                    || $second_category == 801 || $second_category == 511){
                    return array();
                }
            }

            //获取运费模板
            $shipping = $this->getProductShipping(['spu'=>$params['spu'],'lang'=>$params['lang'],'country'=>$params['country']]);
            if(empty($shipping)){
                if(empty($nocnoc)){
                    return array();
                }
                //美国仓的去除nocnoc
                if($seller_id == self::USSELLER){
                    return array();
                }
                return $nocnoc;
            }

            //运费规则
            $rules = array_values($this->getShippingRule($shipping));
            $default = array();
            //计算运费价格
            foreach($rules as $key => $rule){
                $discount = $sWeight = $price = 0;
                if($rule['ShippingType'] != 2){
                    //是否有折扣
                    if(isset($rule['Discount']) && !empty($rule['Discount'])){
                        $discount = (double)$rule['Discount'];
                    }
                    //重量还是数量
                    if(isset($rule['custom_type'])){
                        //数量规则
                        $ret = $this->countShippingByQty($key,$count,$rule,$default);
                        if(!$ret) continue;
                    }else{
                        //自定义规则
                        if(isset($rule['delivery_type']) && $rule['delivery_type'] == 3){
                            if($productWeight > $rule['FirstData']){
                                //判断产品重量是否在最大阶梯范围，如果产品重量超过最大的阶梯范围，那么这个运费方式就不支持
                                $isSupportShipping = end($rule['IncreaseRule']);
                                if($productWeight > $isSupportShipping['end_data']){
                                    unset($default[$key]);
                                    continue;
                                }
                                //自定义重量规则
                                $firstGearPrice = $this->countShippingByCustomWeight($productWeight,$rule);
                                $default[$key]['Cost'] = sprintf("%01.2f",$firstGearPrice + $rule['FirstPrice']);
                                //折扣
                                if(!empty($discount)){
                                    //运费 = 阶梯价 + 首重价格
                                    $default[$key]['Cost'] = round(($firstGearPrice + $rule['FirstPrice'])* $discount / 100,2);
                                }
                            }else{
                                $default[$key]['Cost'] = sprintf("%01.2f",$rule['FirstPrice']);
                                //折扣
                                if(!empty($discount)){
                                    $default[$key]['Cost'] = round($rule['FirstPrice'] * $discount / 100,2);
                                }
                            }

                        }else{
                            //LMS重量规则
                            $GOODSWEIGHT  = $productWeight;
                            if(is_array($rule["IncreaseRule"])){
                                continue;
                            }
                            $calculation_formula = '<?php '.htmlspecialchars_decode($rule["IncreaseRule"]).' ?>';
                            eval( '?>' .$calculation_formula );
                            if($price <= 0){
                                continue;
                            }
                            //人民币切换美元
                            $default[$key]['Cost'] = sprintf("%01.2f",$price * $rmbRate);
                            //折扣
                            if(!empty($discount) && $discount > 0){
                                $default[$key]['Cost'] = round($price * $rmbRate * $discount / 100,2);
                            }
                        }

                        //币种切换
                        if($params['currency'] != DEFAULT_CURRENCY){
                            $default[$key]['Cost'] = sprintf("%01.2f",(double)$default[$key]['Cost'] * $currentRate);
                        }
                    }
                }else{
                    $default[$key]['Cost'] = 0;
                }
                //增加3%的收款费率成本
                if($default[$key]['Cost'] != 0){
                    $newCost = round($default[$key]['Cost'] * self::SHIPPING_RATE,2) + $default[$key]['Cost'];
                    $default[$key]['Cost'] = sprintf("%01.2f",$newCost);
                }

                $default[$key]['TrackingInformation'] = 'Available';
                //运费模板跟踪信息，跟平邮，产品价格挂钩
                //刘凯 2019-2-13 15:04:39
                //规则：
                //1. 前端：产品单价格小于$20，Cart展示平邮可选择。。(详情页)
                if($rule['ShippingService'] == 'SuperSaver'){
                    //平邮追踪不可用
                    $default[$key]['TrackingInformation'] = 'Unavailable';
                    //初始化价格，避免找不到SKU的情况下报错
                    $skuPrice = 0;
                    $firstSkuid = isset($product['Skus'][0]['_id']) ? $product['Skus'][0]['_id'] : null;
                    $firstSkuCode = isset($product['Skus'][0]['Code']) ? $product['Skus'][0]['Code'] : null;
                    $sku_id = isset($product['DefaultSkuId']) ? $product['DefaultSkuId'] : $firstSkuid;
                    $sku_code = isset($product['DefaultSkuCode']) ? $product['DefaultSkuCode'] : $firstSkuCode;
                    //当前产品sku价格
                    if(isset($params['skuid']) && !empty($params['skuid'])){
                        $sku = CommonLib::filterArrayByKey($product['Skus'],'_id',$params['skuid']);
                        if(empty($sku)){
                            $sku = CommonLib::filterArrayByKey($product['Skus'],'Code',$params['skuid']);
                        }
                        if(!empty($sku)){
                            $skuPrice = $sku['SalesPrice'];
                            if(isset($sku['ActivityInfo']) && !empty($sku['ActivityInfo'])){
//                                if(time() >= $sku['ActivityInfo']['ActivityStartTime'] && time() <= $sku['ActivityInfo']['ActivityEndTime']){
                                $skuPrice = $sku['ActivityInfo']['DiscountPrice'];
//                                }
                            }
                        }
                    }else{
                        $sku = CommonLib::filterArrayByKey($product['Skus'],'_id',$sku_id);
                        if(empty($sku)){
                            $sku = CommonLib::filterArrayByKey($product['Skus'],'Code',$sku_code);
                        }
                        if(!empty($sku)){
                            $skuPrice = $sku['SalesPrice'];
                            if(isset($sku['ActivityInfo']) && !empty($sku['ActivityInfo'])){
//                                if(time() >= $sku['ActivityInfo']['ActivityStartTime'] && time() <= $sku['ActivityInfo']['ActivityEndTime']){
                                $skuPrice = $sku['ActivityInfo']['DiscountPrice'];
//                                }
                            }
                        }
                    }
                    $tracking = Tracking_Information_price;
                    if($params['currency'] != DEFAULT_CURRENCY){
                        $skuPrice = sprintf("%01.2f",$skuPrice * $currentRate);
                        $tracking = sprintf("%01.2f",Tracking_Information_price * $currentRate);
                    }
                    //如果产品价格大于，平邮不显示，如果$skuPrice为0，说明没有找到ID和CODE
                    //这里修改为单价（2019-2-13）
                    if($skuPrice > $tracking || $skuPrice == 0){
                        unset($default[$key]);
                        continue;
                    }
                }
                //免邮状态 0免邮 1MVP 24小说到货提示 2不免邮
                $default[$key]['ShippingService'] = $rule['ShippingService'];

                $estimatedDeliveryTime = empty($rule['EstimatedDeliveryTime']) ? '7-15' : $rule['EstimatedDeliveryTime'];
                //运费天数配置开关，如果后期人工LMS配置好，这配置改false
                if(config('shipping_time_on')){
                    $estimatedDeliveryTime = isset($shippingDays[$rule['ShippingServiceID']]) ? $shippingDays[$rule['ShippingServiceID']] : '7-15';
                }
                $default[$key]['EstimatedDeliveryTime'] = $estimatedDeliveryTime.' '.lang('shipping_days',[],$params['lang']);
                $default[$key]['CountryCode'] = $rule['Country'];
                $default[$key]['CountryName'] = $CountryName;
                $default[$key]['OldShippingService'] = $rule['ShippingService'];
                //专线名称
                if(isset($rule['ShippingServiceID']) && $rule['ShippingServiceID'] == 40){
                    $default[$key]['OldShippingService'] = $rule['ShippingService'];
                    $default[$key]['ShippingService'] = 'Exclusive';
                }
            }
            if(!empty($nocnoc)){
                $default = array_merge($default,$nocnoc);
            }
            if(!empty($default)){
                $default = CommonLib::multiArraySort($default,'Cost','SORT_ASC');
                $SuperSaverKey = 100;//当前赋值无任何意义
                $SuperSaverPrice = $StandardPrice = 0;
                foreach($default as $key => $v){
//                    张恒 2019-1-21 11:22:34
//                    1.单边最长不超过60CM
//                    2.长宽高之和大于90 CM 不能走平邮挂号;需求再次确认：长+宽+高，不是长*宽*高
//                    3.重量大于2KG不能走平邮挂号;
//                    张恒：2019-1-24 补充 店铺id 777,888 不做这些条件判断
                    if($seller_id != self::USSELLER && $seller_id != self::SKSELLER){
                        if($length >= self::PRODUCT_MAX_LENGTH || $width >= self::PRODUCT_MAX_LENGTH || $hight >= self::PRODUCT_MAX_LENGTH
                            || $productTotal >= self::PRODUCT_MAX_TOTAL || $productWeight >= self::PRODUCT_MAX_WEIGHT){
                            if($v['ShippingService'] == 'SuperSaver' || $v['ShippingService'] == 'Standard'){
                                unset($default[$key]);
                                continue;
                            }
                        }
                    }

                    if(isset($v['ShippingService']) && $v['ShippingService'] == 'SuperSaver'){
                        $SuperSaverPrice = $v['Cost'];
                        $SuperSaverKey = $key;
                    }
                    if(isset($v['ShippingService']) && $v['ShippingService'] == 'Standard'){
                        $StandardPrice = $v['Cost'];
                    }
                    $default[$key]['ShippingFee'] = 2;
                    //免邮状态 0免邮 1MVP 24小说到货提示 2不免邮
                    if($v['Cost'] == 0){
                        if($isMvp == true || $isMvp == 1){
                            $default[$key]['Cost'] = FREE_SHIPPING_IN_ONEDAY;
                            $default[$key]['ShippingFee'] = 1;
                        }else{
                            $default[$key]['Cost'] = FREE_SHIPPING;
                            $default[$key]['ShippingFee'] = 0;
                        }
                    }
                    $default[$key]['ShippingService'] = lang($v['ShippingService'],[],$params['lang']);
                    $default[$key]['TrackingInformation'] = lang($v['TrackingInformation'],[],$params['lang']);
                }
//                          当价商品的价格*数量大于等于N时，不展示平邮(原规则);
//                          当价商品的价格*数量小于N时，如果平邮价格高于挂号，则不展示平邮(本次新增);
                if($SuperSaverKey != 100){
                    if($SuperSaverPrice > $StandardPrice && $StandardPrice != 0){
                        unset($default[$SuperSaverKey]);
                    }
                }
            }
            $shippingCache = array_values($default);
            //新增初始化运费缓存
            if(isset($params['count']) && $params['count'] == 1){
                if(config('cache_switch_on')) {
                    $this->redis->set("PRODUCT_SHIPPING_COST_" .$params['spu'].'_' .$cacheKey,$shippingCache,CACHE_HOUR);
                }
            }
            return $shippingCache;
        }catch (Exception $e){
            Log::record('countProductShipping->Exception:'.$e->getMessage().', params:'.json_encode($params).', currentRate:'.$currentRate);
            return [];
        }
    }

    /**
     * 获取产品重量
     * @param $count 产品数量
     * @param array $packingList 产品规格
     * @return mixed
     */
    private function getPorductWeight($count,$packingList){
        $productWeight = 0;
        if($count == 1){
            return $packingList['Weight'];
        }
        //获取产品重量
        if(empty($packingList['CustomeWeightInfo'])){
            //如果没有重量规则，那么就重量*数量
            $productWeight = $packingList['Weight'] * $count;
        }else{
            if(isset($packingList['CustomeWeightInfo']['Qty']) && !empty($packingList['CustomeWeightInfo']['Qty'])){
                //买家购买规定数量以内，按照产品重量计算运费
                if($count <= $packingList['CustomeWeightInfo']['Qty']){
                    $productWeight = $packingList['Weight'] * $count;
                }else{
                    //在此基础上，买家每多买?个，重量增加？KG
                    $overWeight = ceil(($count - $packingList['CustomeWeightInfo']['Qty']) / $packingList['CustomeWeightInfo']['IncreaseQty']) * $packingList['CustomeWeightInfo']['IncreaseWeight'];
                    $productWeight = ($packingList['Weight'] * $packingList['CustomeWeightInfo']['Qty']) + $overWeight;
                }
            }else{
                $productWeight = $packingList['Weight'] * $count;
            }
        }
        return $productWeight;
    }

    /**
     * 获取产品信息 -- 用于计算运费
     * @param array $spus
     * @return array
     */
    public function getProductToCountShipping($spus){
        $products = array();
        if(config('cache_switch_on')) {
            $products = $this->redis->get(COUNT_SHIPPING_PRODUCT_ . $spus);
        }
        if(empty($products)){
            $base_api = new BaseApi();
            $result = $base_api->getProductToShipping(['spu'=>$spus]);
            /*$result = doCurl(MALL_API.'/mall/product/getProductToShipping',['spu'=>$spus],[
                'access_token' => $this->getAccessToken()
            ]);*/
            if($result['code'] != 200){
                //Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$spus,MALL_API.'/mall/product/getShippingProduct',$result);
                return array();
            }
            $products = $result['data'];
            if(!empty($products)){
                $this->redis->get(COUNT_SHIPPING_PRODUCT_ . $spus,$products,CACHE_HOUR);
            }
        }
        return $products;
    }

    /**
     * 产品运费模板
     * @param $params
     * @return array
     */
    public function getProductShipping($params){
        $shipping = array();
        if(config('cache_switch_on')) {
            $shipping = $this->redis->get(PRODUCT_SHIPPING_INFO_ . $params['spu'].'_'.$params['country']);
        }
        if(empty($shipping)){
            $result = doCurl(MALL_API.'/mall/product/getSpuShippingInfo',$params, null, true);
            if($result['code'] != 200){
                //Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/product/getSpuShippingInfo',$result);
                return $shipping;
            }
            $shipping = $result['data'];
        }
        return $shipping;
    }

    /**
     * 运费规则
     * @param $result
     * @return array
     */
    public function getShippingRule($result){
        $rule = array();
        if(!empty($result)){
            $shippings = $result['ShippingCost'];
            foreach($shippings as $skey => $shipping){
                //运费类型：1-标准运费[有折扣，单位%，如：10，对应折扣10%，打九折]，2-卖家承担运费，3-自定义运费
                switch($shipping['ShippingType']){
                    case 1:
                        $rule[$skey]['Country'] = $result['ToCountry'];
                        $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                        $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                        $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                        $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                        $rule[$skey]['Discount'] = $shipping['discount'];
                        //标准规则
                        $rule[$skey]['IncreaseRule'] = $shipping['LmsRuleInfo'];
                        $rule[$skey]['ShippingType'] = $shipping['ShippingType'];
                        break;
                    case 2:
                        $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                        $rule[$skey]['Country'] = $result['ToCountry'];
                        $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                        $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                        $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                        $rule[$skey]['ShippingType'] = $shipping['ShippingType'];
                        break;
                    //卖家自定义物流运费计算规则
                    case 3:
                        //发货类型：1-标准运费，2-卖家承担运费, 3-自定义
                        $customerType = $shipping['ShippingTamplateRuleInfo']['delivery_type'];
                        switch($customerType){
                            case 1:
                                $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                                $rule[$skey]['Country'] = $result['ToCountry'];
                                $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                                $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                                $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                                $rule[$skey]['Discount'] = $shipping['ShippingTamplateRuleInfo']['discount'];
                                $rule[$skey]['IncreaseRule'] = $shipping['LmsRuleInfo'];
                                $rule[$skey]['ShippingType'] = 1;
                                //标准规则
                                break;
                            case 2:
                                $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                                $rule[$skey]['Country'] = $result['ToCountry'];
                                $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                                $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                                $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                                $rule[$skey]['ShippingType'] = 2;
                                break;
                            case 3:
                                $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                                $rule[$skey]['ShippingType'] = 3;
                                $rule[$skey]['Country'] = $result['ToCountry'];
                                $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                                $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                                $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                                //自定义运费
                                $customShipping = $shipping['ShippingTamplateRuleInfo']['CustomShipping'];
                                $rule[$skey]['FirstData'] = $customShipping[0]['first_data'];
                                $rule[$skey]['FirstPrice'] = $customShipping[0]['first_freight'];
                                $rule[$skey]['Cost'] = $rule[$skey]['FirstPrice'];
                                //自定义运费规则
                                $rule[$skey]['delivery_type'] = 3;
                                $rule[$skey]['IncreaseRule'] = $customShipping;
                                //按1重量还是2数量
                                if($customShipping[0]['custom_freight_type'] == 2){
                                    $rule[$skey]['custom_type'] = 2;
                                }
                                break;
                        }
                        break;
                }
            }
        }
        return $rule;
    }

    /**
     * 数量运费规则
     * @param int $key 键值
     * @param $count  产品数量
     * @param $rule 运费规则
     * @param $default 结果数组
     * @return true
     */
    private function countShippingByQty($key,$count,$rule,&$default){
        if($count > $rule['FirstData']){
            //产品数量没有阶梯计算，只有一条数据
            $IncreaseRule = end($rule['IncreaseRule']);

            //判断购买的产品数量，是否大于规定内的数量范围
            if($count > $IncreaseRule['start_data']){
                unset($default[$key]);
                return false;
            }
            //数量阶梯计算
            $firstGearPrice = ceil(($count - $rule['FirstData']) / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];

            $default[$key]['Cost'] = sprintf("%01.2f",$firstGearPrice + $rule['FirstPrice']);
        }elseif($count == $rule['FirstData']){
            $default[$key]['Cost'] = sprintf("%01.2f",$rule['FirstPrice']);
        }else{
            //判断数量是否在最低采购量
            unset($default[$key]);
            return false;
        }
        return true;
    }

    /**
     * 自定义重量运费规则
     * @param $productWeight  产品重量
     * @param $rule 运费规则
     * @return price
     */
    private function countShippingByCustomWeight($productWeight,$rule){
        $firstGearPrice = 0;
        //减去首重后，阶梯计算
        foreach($rule['IncreaseRule'] as $inKey => $IncreaseRule){
            //1百分比、2金额
//            $freight_type = $IncreaseRule['first_freight_type'];
            //自增的价格
//            if($freight_type == 1){
//                $IncreaseRule['add_freight'] = $rule['LMSFirstPrice'] * $IncreaseRule['add_freight'] / 100;
//                $IncreaseRule['first_freight'] = $rule['LMSFirstPrice'] * $IncreaseRule['first_freight'] / 100;
//            }

            //产品重量大于当前阶梯的结束值
            if($productWeight >= $IncreaseRule['end_data']){
                //价格算法，（阶梯结束 - 阶梯开始 ）/ 每增加多少kg  * 增加多少钱
                $price = ceil(($IncreaseRule['end_data'] - $IncreaseRule['start_data']) / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];
                //剩余重量
                $sWeight = $productWeight - $IncreaseRule['end_data'];
            }else{
                //没有剩余重量，说明产品重量在第一个阶梯范围
                if(empty($sWeight)){
                    $price = ceil(($productWeight - $rule['FirstData']) / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];
                }else{
                    $price = ceil($sWeight / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];
                }
            }
            //当前阶梯重量的价格
            $firstGearPrice = $firstGearPrice + $price;
            //不必进入下一个阶梯
            if($productWeight <= $IncreaseRule['end_data']){
                break;
            }
        }
        return $firstGearPrice;
    }

    /**
     *  购物车专用，批量获取运费信息
     * @param $params
     * @param $currentRate
     * @return array
     */
    public function getBatchShippingCost($params,$currentRate=''){
        try{
            $cartProduct = array();
            $service = new IndexService();
            $nocnoc_country = config('nocnoc_country');
            //错误日志：spu不存在问题修复 add zhongning 20190328
            if(!empty($params['spus'])){
                $spuArray = CommonLib::getColumn('spu',$params['spus']);
            }else{
                return $cartProduct;
            }
            //获取全部cart的产品信息
            $products = $this->getBatchProductByCountShipping($spuArray);
            if(!empty($products)){
                if(empty($currentRate)){
                    //币种切换
                    if($params['currency'] != DEFAULT_CURRENCY){
                        $rateService = new rateService();
                        $currentRate = $rateService->getCurrentRate($params['currency']);
                    }
                }
                //人民币切换美元
                $cnyRate = (new rateService())->getCurrentRate(DEFAULT_CURRENCY,'CNY');

                $shippingDays = array();
                //后台配置的运费天数
                $shippingDaysConfig = $service->getShippingTime();
                if(isset($shippingDaysConfig['ConfigValue']) && !empty($shippingDaysConfig['ConfigValue'])){
                    $shippingDays = json_decode(htmlspecialchars_decode($shippingDaysConfig['ConfigValue']),true);
                }

                foreach($params['spus'] as $spu){
//                    pr(microtime());
                    $nocnoc = array();
                    $sku_id = $spu['skuid'];
                    $count = $spu['count'];
                    $spu['country'] = trim($spu['country']);

                    //查找产品数据
                    $product = CommonLib::filterArrayByKey($products,'_id',$spu['spu']);
                    $packingList = isset($product['PackingList']) ? $product['PackingList'] : '';
                    if(empty($packingList)){
                        $cartProduct[$sku_id] = array();
                        continue;
                    }
                    $isMvp = isset($product['IsMVP']) ? $product['IsMVP'] : 1;
                    $seller_id = isset($product['StoreID']) ? $product['StoreID'] : null;
                    $second_category = isset($product['SecondCategory']) ? $product['SecondCategory'] : 0;
                    //产品重量
                    $productWeight = $this->getPorductWeight($count,$packingList);
                    //产品长宽高,长宽高之和
                    $dimensions = isset($packingList['Dimensions']) && !empty($packingList['Dimensions']) ? explode('-',$packingList['Dimensions']) : array();
                    $length = isset($dimensions[0]) ? $dimensions[0] : 0;
                    $width = isset($dimensions[1]) ? $dimensions[1] : 0;
                    $hight = isset($dimensions[2]) ? $dimensions[2] : 0;
                    $productTotal = $length + $width + $hight;

                    //国家名称
                    $arr = $service->getCountryInfo(['Code'=>$spu['country']]);
                    $CountryName = $arr['Name'];
                    //NOCNOC国家配置
                    if(in_array($spu['country'],$nocnoc_country)){
                        if($seller_id != 888) {
                            ///巴西的只有圣保罗才支持NOCNOC tinghu.liu 20191011
                            if (strtolower($spu['country']) == 'br'){
                                //判断是否为空，因为cart页面没有收货地址的省份，但是需要展示NOCNOC tinghu.liu 20191011
                                if (isset($spu['province']) && !empty($spu['province'])){
                                    if (strtolower($spu['province']) == 'sao paulo'){
                                        $nocnoc[0]['Cost'] = 4;
                                        if ($params['currency'] != DEFAULT_CURRENCY) {
                                            $nocnoc[0]['Cost'] = sprintf("%01.2f", 4 * $currentRate);
                                        }
                                        $nocnoc[0]['ShippingFee'] = 2;
                                        $nocnoc[0]['ShippingService'] = 'NOCNOC';
                                        $nocnoc[0]['OldShippingService'] = 'NOCNOC';
                                        $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 ' . lang('shipping_days', [], $params['lang']);
                                        $nocnoc[0]['CountryCode'] = $spu['country'];
                                        $nocnoc[0]['CountryName'] = $CountryName;
                                        $nocnoc[0]['TrackingInformation'] = lang('Available', [], $params['lang']);
                                    }
                                }else{
                                    $nocnoc[0]['Cost'] = 4;
                                    if ($params['currency'] != DEFAULT_CURRENCY) {
                                        $nocnoc[0]['Cost'] = sprintf("%01.2f", 4 * $currentRate);
                                    }
                                    $nocnoc[0]['ShippingFee'] = 2;
                                    $nocnoc[0]['ShippingService'] = 'NOCNOC';
                                    $nocnoc[0]['OldShippingService'] = 'NOCNOC';
                                    $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 ' . lang('shipping_days', [], $params['lang']);
                                    $nocnoc[0]['CountryCode'] = $spu['country'];
                                    $nocnoc[0]['CountryName'] = $CountryName;
                                    $nocnoc[0]['TrackingInformation'] = lang('Available', [], $params['lang']);
                                }
                            }else{
                                $nocnoc[0]['Cost'] = 4;
                                if ($params['currency'] != DEFAULT_CURRENCY) {
                                    $nocnoc[0]['Cost'] = sprintf("%01.2f", 4 * $currentRate);
                                }
                                $nocnoc[0]['ShippingFee'] = 2;
                                $nocnoc[0]['ShippingService'] = 'NOCNOC';
                                $nocnoc[0]['OldShippingService'] = 'NOCNOC';
                                $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 ' . lang('shipping_days', [], $params['lang']);
                                $nocnoc[0]['CountryCode'] = $spu['country'];
                                $nocnoc[0]['CountryName'] = $CountryName;
                                $nocnoc[0]['TrackingInformation'] = lang('Available', [], $params['lang']);
                            }


                        }
                    }

                    //判断是否是手机,成人用品，无人飞机，过滤印度,add by zhongning 20191031
                    if(!empty($spu['country']) && $spu['country'] == 'IN'){
                        if($second_category == 52 || $second_category == 1800203 || $second_category == 1800163
                            || $second_category == 801 || $second_category == 511){
                            $cartProduct[$sku_id] = array();
                        }
                    }

                    //获取运费模板
                    $shipping = $this->getProductShipping(['spu'=>$spu['spu'],'lang'=>$params['lang'],'country'=>$spu['country']]);
                    if(empty($shipping)){
                        if(empty($nocnoc)){
                            $cartProduct[$sku_id] = array();
                            continue;
                        }
                        //美国仓的去除nocnoc
                        if($seller_id == 777){
                            $cartProduct[$sku_id] = array();
                            continue;
                        }
                        $cartProduct[$sku_id] = $nocnoc;
                        continue;
                    }
                    //运费规则
                    $rules = array_values($this->getShippingRule($shipping));
                    $default = array();
                    //计算运费价格
                    foreach($rules as $key => $rule){
                        $discount = $sWeight = $price = 0;
                        if($rule['ShippingType'] != 2){
                            //是否有折扣
                            if(isset($rule['Discount']) && !empty($rule['Discount'])){
                                $discount = (double)$rule['Discount'];
                            }
                            //重量还是数量
                            if(isset($rule['custom_type'])){
                                //数量规则
                                $ret = $this->countShippingByQty($key,$count,$rule,$default);
                                if(!$ret) continue;
                            }else{
                                //自定义规则
                                if(isset($rule['delivery_type']) && $rule['delivery_type'] == 3){
                                    if($productWeight > $rule['FirstData']){
                                        //判断产品重量是否在最大阶梯范围，如果产品重量超过最大的阶梯范围，那么这个运费方式就不支持
                                        $isSupportShipping = end($rule['IncreaseRule']);
                                        if($productWeight > $isSupportShipping['end_data']){
                                            unset($default[$key]);
                                            continue;
                                        }
                                        //自定义重量规则
                                        $firstGearPrice = $this->countShippingByCustomWeight($productWeight,$rule);
                                        $default[$key]['Cost'] = sprintf("%01.2f",$firstGearPrice + $rule['FirstPrice']);
                                        //折扣
                                        if(!empty($discount)){
                                            //运费 = 阶梯价 + 首重价格
                                            $default[$key]['Cost'] = round(($firstGearPrice + $rule['FirstPrice'])* $discount / 100,2);
                                        }
                                    }else{
                                        $default[$key]['Cost'] = sprintf("%01.2f",$rule['FirstPrice']);
                                        //折扣
                                        if(!empty($discount)){
                                            $default[$key]['Cost'] = round($rule['FirstPrice'] * $discount / 100,2);
                                        }
                                    }

                                }else{
                                    //LMS重量规则
                                    $GOODSWEIGHT  = $productWeight;
                                    if(is_array($rule["IncreaseRule"])){
                                        continue;
                                    }
                                    $calculation_formula = '<?php '.htmlspecialchars_decode($rule["IncreaseRule"]).' ?>';
                                    eval( '?>' .$calculation_formula );
                                    if($price <= 0){
                                        continue;
                                    }
                                    $default[$key]['Cost'] = sprintf("%01.2f",$price * $cnyRate);
                                    //折扣
                                    if(!empty($discount)){
                                        $default[$key]['Cost'] = round($price * $cnyRate * $discount / 100,2);
                                    }
                                }

                                //币种切换
                                if($params['currency'] != DEFAULT_CURRENCY){
                                    $default[$key]['Cost'] = sprintf("%01.2f",(double)$default[$key]['Cost'] * $currentRate);
                                }
                            }
                        }else{
                            $default[$key]['Cost'] = 0;
                        }

                        //增加3%的收款费率成本
                        if($default[$key]['Cost'] != 0){
                            $newCost = round($default[$key]['Cost'] * self::SHIPPING_RATE,2) + $default[$key]['Cost'];
                            $default[$key]['Cost'] = sprintf("%01.2f",$newCost);
                        }

                        $default[$key]['TrackingInformation'] = 'Available';
                        //运费模板跟踪信息，跟平邮，产品价格挂钩
                        //刘凯 2019-2-13 15:04:39
                        //规则：
                        //1. 前端：产品单价格小于$20，Cart展示平邮可选择。。(详情页)
                        if($rule['ShippingService'] == 'SuperSaver'){
                            //初始化价格，避免找不到SKU的情况下报错
                            $skuPrice = 0;
                            //当前产品sku价格
                            $sku = CommonLib::filterArrayByKey($product['Skus'],'_id',$sku_id);
                            if(!empty($sku)){
                                $skuPrice = $sku['SalesPrice'];
                                if(isset($sku['ActivityInfo']) && !empty($sku['ActivityInfo'])){
                                    $skuPrice = $sku['ActivityInfo']['DiscountPrice'];
                                }
                            }
                            $tracking = Tracking_Information_price;
                            if($params['currency'] != DEFAULT_CURRENCY){
                                $skuPrice = sprintf("%01.2f",$skuPrice * $currentRate);
                                $tracking = sprintf("%01.2f",Tracking_Information_price * $currentRate);
                            }
                            //价格为0，说明没有找到
                            //2019-02-13 修改为产品单价小于20
                            if($skuPrice > $tracking || $skuPrice == 0){
                                unset($default[$key]);
                                continue;
//                                $default[$key]['TrackingInformation'] = 'Unavailable';
                            }
                        }
                        //免邮状态 0免邮 1MVP 24小说到货提示 2不免邮
                        $default[$key]['ShippingService'] = $rule['ShippingService'];
                        $estimatedDeliveryTime = empty($rule['EstimatedDeliveryTime']) ? '7-15' : $rule['EstimatedDeliveryTime'];
                        //运费天数配置开关，如果后期人工LMS配置好，这配置改false
                        if(config('shipping_time_on')){
                            $estimatedDeliveryTime = isset($shippingDays[$rule['ShippingServiceID']]) ? $shippingDays[$rule['ShippingServiceID']] : '7-15';
                        }
                        $default[$key]['EstimatedDeliveryTime'] = $estimatedDeliveryTime.' '.lang('shipping_days',[],$params['lang']);
                        $default[$key]['CountryCode'] = $rule['Country'];
                        $default[$key]['CountryName'] = $CountryName;
                        $default[$key]['OldShippingService'] = $rule['ShippingService'];
                        //专线名称
                        if(isset($rule['ShippingServiceID']) && $rule['ShippingServiceID'] == 40){
                            $default[$key]['OldShippingService'] = $rule['ShippingService'];
                            $default[$key]['ShippingService'] = 'Exclusive';
                        }
                    }

                    if(!empty($nocnoc)){
                        $default = array_merge($default,$nocnoc);
                    }

                    if(!empty($default)){
                        $default = CommonLib::multiArraySort($default,'Cost','SORT_ASC');
                        $SuperSaverPrice = 0;
                        $StandardPrice = 0;
                        $SuperSaverKey = 100;
                        foreach($default as $key => $v){
//                    张恒 2019-1-21 11:22:34
//                    1.单边最长不超过60CM
//                    2.长宽高之和大于90 CM 不能走平邮挂号;需求再次确认：长+宽+高，不是长*宽*高
//                    3.重量大于2KG不能走平邮挂号;
//                    张恒：2019-1-24 补充 店铺id 777,888 不做这些条件判断
                            if($seller_id != self::USSELLER && $seller_id != self::SKSELLER) {
                                if ($length >= self::PRODUCT_MAX_LENGTH || $width >= self::PRODUCT_MAX_LENGTH || $hight >= self::PRODUCT_MAX_LENGTH
                                    || $productTotal >= self::PRODUCT_MAX_TOTAL || $productWeight >= self::PRODUCT_MAX_WEIGHT
                                ) {
                                    if ($v['ShippingService'] == 'SuperSaver' || $v['ShippingService'] == 'Standard') {
                                        unset($default[$key]);
                                        continue;
                                    }
                                }
                            }
                            if(isset($v['ShippingService']) && $v['ShippingService'] == 'SuperSaver'){
                                $SuperSaverPrice = $v['Cost'];
                                $SuperSaverKey = $key;
                            }
                            if(isset($v['ShippingService']) && $v['ShippingService'] == 'Standard'){
                                $StandardPrice = $v['Cost'];
                            }
                            $default[$key]['ShippingFee'] = 2;
                            //免邮状态 0免邮 1MVP 24小说到货提示 2不免邮
                            if($v['Cost'] == 0){
                                if($isMvp == true || $isMvp == 1){
                                    $default[$key]['Cost'] = lang('free_shipping_24',[],$params['lang']);
                                    $default[$key]['ShippingFee'] = 1;
                                }else{
                                    $default[$key]['Cost'] = lang('header_free_shipping',[],$params['lang']);
                                    $default[$key]['ShippingFee'] = 0;
                                }
                            }
                            $default[$key]['ShippingService'] = lang($v['ShippingService'],[],$params['lang']);
                            $default[$key]['TrackingInformation'] = lang($v['TrackingInformation'],[],$params['lang']);
                        }
//                          当价商品的价格*数量大于等于N时，不展示平邮(原规则);
//                          当价商品的价格*数量小于N时，如果平邮价格高于挂号，则不展示平邮(本次新增);
                        if($SuperSaverKey != 100){
                            if($SuperSaverPrice > $StandardPrice && $StandardPrice != 0){
                                unset($default[$SuperSaverKey]);
                            }
                        }
                    }
                    $cartProduct[$sku_id] = array_values($default);
                }
            }
            return $cartProduct;
        }catch (Exception $e){
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());
            return [];
        }
    }

    /**
     * 批量获取产品信息 -- 用于计算运费
     * @param array $spus
     * @return array
     */
    public function getBatchProductByCountShipping($spus){
        $products = array();
        $string = implode('_',$spus);
        $cacheKey = CommonLib::getCacheKey(['key'=>$string]);
        if(config('cache_switch_on')) {
            $products = $this->redis->get(CART_PRODUCT_ . $cacheKey);
        }
        if(empty($products)){
            /*$result = doCurl(MALL_API.'/mall/product/getCartProductList',['spu'=>$spus],[
                'access_token' => $this->getAccessToken()
            ]);*/
            $result = doCurl(MALL_API.'/mall/product/getCartProductList',['spu'=>$spus],null, true);
            if($result['code'] != 200){
                //Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$spus,MALL_API.'/mall/product/getShippingProduct',$result);
                return $products;
            }
            if(isset($result['data']) && is_array($result['data'])){
                $this->redis->get(CART_PRODUCT_ . $cacheKey,$result['data'],CACHE_HOUR);
            }
            $products = $result['data'];
        }
        return $products;
    }

    /**
     * 订单销量最好的产品
     * @param $params
     * @return array|mixed
     */
    public function getTopProductByOrder($params){
        $products = array();
        $currency = !empty($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $cacheKey = CommonLib::getCacheKey($params);
        if(config('cache_switch_on')) {
            $products = $this->redis->get('APP_TOP_ORDER_PRODUCT_LIST_' . $cacheKey);
        }
        if(isset($products['data']) && empty($products['data'])){
            $products = array();
        }

        if(empty($products)){
            //先获取产品订单销量好的产品
            if(config('cache_switch_on')) {
                $result = $this->redis->get('APP_TOP_ORDER_PRODUCT');
            }else{
                $result = doCurl(API_SHARE_URL.'/orderfrontend/orderQuery/getTopSellerOrderData',['startTime' => strtotime("-1 day"),'limit' => 100],[
                    'access_token' => $this->getAccessToken()
                ],true);

                if($result['code'] != 200){
                    return array();
                    //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,['endTime' => strtotime("-1 day"),'limit' => 100],MALL_API.'/orderfrontend/orderQuery/getTopSellerOrderData',$result);
                }else{
                    if(!empty($result['data'])){
                        $this->redis->set('APP_TOP_ORDER_PRODUCT',$result['data'],CACHE_DAY);
                    }
                }
            }

            //获取产品信息
            $params['product_ids'] = !empty($result['data']) ? $result['data'] : array();

            $result = doCurl(API_SHARE_URL.'/mall/product/getTopProductByOrder',$params,[
                'access_token' => $this->getAccessToken()
            ]);

            if($result['code'] != 200){
                return array();
            }

            if(!empty($result['data']) && is_array($result['data'])){
                $res=$this->redis->set('APP_TOP_ORDER_PRODUCT_LIST_'.$cacheKey,$result['data'],CACHE_FIVE_MIN);
            }

            $products = $result['data'];

        }
            //币种切换
            if(!empty($products) && is_array($products)){
                if($params['currency'] != DEFAULT_CURRENCY){
                    $products = $this->changeCurrentRate($products,$params['currency']);
                }
                //图片拼接尺寸，过滤数据
                foreach($products as $p => $product){
                    if(!empty($product['FirstProductImage'])){
                        $img = explode('.',$product['FirstProductImage']);
                        if(!empty($img[0]) && !empty($img[1])){
                            $products[$p]['FirstProductImage'] = $img[0].'_210x210.'.$img[1];
                        }
                    }
                    //币种符号
                    $products[$p]['currencyCode'] = DEFAULT_CURRENCY;
                    $products[$p]['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
                    if(self::DEFAULT_CURRENCY != $currency) {
                        $products[$p]['currencyCode'] = $currency;
                        $products[$p]['currencyCodeSymbol'] = $this->getCurrencyCode($currency);
                    }
                    unset($products[$p]['AvgRating'],
                        $products[$p]['ShippingFee'],
                        $products[$p]['Discount'],
                        $products[$p]['HightPrice'],
                        $products[$p]['OriginalLowPrice'],
                        $products[$p]['OriginalHightPrice'],
                        $products[$p]['tagName']);
                }

//            $products['data'] = handleProductImgBySize($products['data']);
        }

        return $products;
    }

    /**
     * 根据产品ID，查询产品
     */
    public function getTopProductByOrderProduct($params){
        $result = doCurl(MALL_API.'/mall/product/getTopProductByOrder',$params,[
            'access_token' => $this->getAccessToken()
        ]);
        if($result['code'] != 200){
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/product/getTopProductByOrder',$result);
            return array();
        }
        return $result;
    }
}
