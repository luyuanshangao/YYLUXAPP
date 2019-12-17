<?php
namespace app\mallextend\services;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductCountryModel;
use app\mallextend\model\ProductModel;
use app\common\controller\Mongo;
use think\Cache;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * 开发：钟宁
 * 功能：产品业务层
 * 时间：2018-07-09
 */
class ProductService extends BaseService
{

    private $model;
    private $classModel;
    private $base_api;
    public $redis;
    private $countryModel;
    public function __construct(){

        $this->model = new ProductModel();
        $this->classModel = new ProductClassModel();
        $this->base_api = new BaseApi();
        $this->redis = new RedisClusterBase();
        $this->countryModel = new ProductCountryModel();
    }

    public function getProduct($params){
        $lang = !empty($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $data = $this->model->getProduct($params);
        if($lang != DEFAULT_LANG){
            //标题多语言
            $productMultiLang = $this->getProductMultiLang($data['_id'],$lang);
            if(isset($data['Title'])){
                $data['Title'] = isset($productMultiLang['Title'][$lang]) ? $productMultiLang['Title'][$lang] : $data['Title'];//默认英语
            }
            if(isset($data['Descriptions'])){
                $data['Descriptions'] = isset($productMultiLang['Descriptions'][$lang]) && !empty($productMultiLang['Descriptions'][$lang]) ?
                        $productMultiLang['Descriptions'][$lang] : $data['Descriptions'];//默认英语
            }
            if(isset($data['Keywords'])){
                $data['Keywords'] = isset($productMultiLang['Keywords'][$lang]) && !empty($productMultiLang['Keywords'][$lang]) ?
                        $productMultiLang['Keywords'][$lang] : $data['Keywords'];//默认英语
            }
        }
        return $data;

    }

    /**
     * 新增产品
     * @param $productData
     * @return true
     */
    public function addProduct($productData){

        $code = array();
        //数据类型整理 不然会出现数据类型不一致，传过来的是整型，保存的是字符串
        $this->analysisAddProductData($productData);

        //获取分类HScode
        $hscode = $this->classModel->getHscode([
            "class_id"=>[$productData['FirstCategory'],$productData['SecondCategory'],$productData['ThirdCategory'],$productData['FourthCategory']]]
        );
        if(!empty($hscode)){
            foreach($hscode as $key => $val){
                if(!empty($val['HSCode'])){
                    $code[$val['id']]['HSCode'] = $val['HSCode'];
                    $code[$val['id']]['declare_en'] = $val['declare_en'];
                    $code[$val['id']]['title_en'] = $val['title_en'];
                }
            }
            if(!empty($code)){
                if(isset($code[$productData['FirstCategory']])){
                    $productData['HSCode'] = $code[$productData['FirstCategory']]['HSCode'];
                    $productData['DeclarationName'] = !empty($code[$productData['FirstCategory']]['declare_en']) ? $code[$productData['FirstCategory']]['declare_en'] :
                        $code[$productData['FirstCategory']]['title_en'];
                }elseif(isset($code[$productData['SecondCategory']])){
                    $productData['HSCode'] = $code[$productData['SecondCategory']]['HSCode'];
                    $productData['DeclarationName'] = !empty($code[$productData['SecondCategory']]['declare_en']) ? $code[$productData['SecondCategory']]['declare_en'] :
                        $code[$productData['SecondCategory']]['title_en'];
                }elseif(isset($code[$productData['ThirdCategory']])){
                    $productData['HSCode'] = $code[$productData['ThirdCategory']]['HSCode'];
                    $productData['DeclarationName'] = !empty($code[$productData['ThirdCategory']]['declare_en']) ? $code[$productData['ThirdCategory']]['declare_en'] :
                        $code[$productData['ThirdCategory']]['title_en'];
                }elseif(isset($code[$productData['FourthCategory']])){
                    $productData['HSCode'] = $code[$productData['FourthCategory']]['HSCode'];
                    $productData['DeclarationName'] = !empty($code[$productData['FourthCategory']]['declare_en']) ? $code[$productData['FourthCategory']]['declare_en'] :
                        $code[$productData['FourthCategory']]['title_en'];
                }
            }
        }
        $store = ProductModel::$selfStore;
        if(isset($store[$productData['StoreID']])){
            $productData['Supplier'] = $store[$productData['StoreID']];
        }
        return $this->model->addProduct($productData);
    }

    /**
     * 产品更新
     * @param $params
     * @return mixed
     */
    public function updateProduct($params){
        //查询更新产品是否存在
        $proudct = $this->model->getProduct(['product_id'=>(int)$params['id'],'field'=>['_id','RejectType','HSCode','DeclarationName','CategoryPath']]);
        if(empty($proudct)){
            return apiReturn(['code'=>1000000022, 'msg'=>'找不到此商品']);
        }

        //判断海关编码和海关名称是否为空
        if(!isset($proudct['HSCode']) || empty($proudct['HSCode'])){
            //分类信息
            $classArray = explode('-',$proudct['CategoryPath']);
            $classInfo = (new ProductClassModel())->getClassDetail(['id'=>(int)end($classArray)]);
            if($classInfo['type'] == 1) {
                //ERP类别数据
                $params['HSCode'] = isset($classInfo['HSCode']) ? $classInfo['HSCode'] : 0;
                $params['DeclarationName'] = isset($classInfo['DeclarationName']) && !empty($classInfo['DeclarationName']) ? $classInfo['DeclarationName'] : $classInfo['title_en'];
            }else{
                //映射
                if(isset($classInfo['pdc_ids']) && !empty($classInfo['pdc_ids'])) {
                    $classInfo = (new ProductClassModel())->getClassDetail(['id' => (int)$classInfo['pdc_ids'][0]]);
                    $params['HSCode'] = isset($classInfo['HSCode']) ? $classInfo['HSCode'] : 0;
                    $params['DeclarationName'] = isset($classInfo['DeclarationName']) && !empty($classInfo['DeclarationName']) ? $classInfo['DeclarationName'] : $classInfo['title_en'];
                }
            }
        }

        //当审核产品不通过，类型为“侵权/禁限售”时不能修改
        if(
            isset($proudct['RejectType'])
            && !empty($proudct['RejectType'])
            && $proudct['RejectType'] == 1
        ){
            return apiReturn(['code'=>1000000023, 'msg'=>'该产品为‘侵权/禁限售’，不能修改']);
        }

        CommonLib::filterEmptyData($params);
        //数据类型整理 不然会出现数据类型不一致，传过来的是整型，保存字符串
        $this->analysisUpdateProductData($params);
        //过滤空值
        $params['IsHistory'] = 0;  //所有的数据编辑后必须为0，不要删除或注释

        return $this->model->updateProduct($params);
    }


    /**
     * ERP 新增产品
     * @param $productData
     * @return true
     */
    public function createProduct($productData){
        //数据类型整理 不然会出现数据类型不一致，传过来的是整型，保存的是字符串
        $this->analysisAddProductData($productData);

        //获取类别详情
        $classData = $this->classModel->getClassDetail(['id'=>(int)$productData['LastCategory']]);

        if(!empty($classData)){
            //判断是否是末级分类
            if($classData['isleaf'] != 1){
                return apiReturn(['code'=>1002, 'msg'=>$productData['LastCategory'].'不是末级分类id']);
            }
            $id_path = explode('-',$classData['id_path']);
            $productData['CategoryPath'] = $classData['id_path'];
            //获取海关编码
            $productData['HSCode'] = $classData['HSCode'];
            $productData['DeclarationName'] = $classData['declare_en'];
            foreach($id_path as $level => $class_id){
                if($level == 0){
                    $productData['FirstCategory'] = (int)$class_id;
                }elseif($level == 1){
                    $productData['SecondCategory'] = (int)$class_id;
                }elseif($level == 2){
                    $productData['ThirdCategory'] = (int)$class_id;
                }elseif($level == 3){
                    $productData['FourthCategory'] = (int)$class_id;
                }
            }
            unset($productData['LastCategory']);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>$productData['LastCategory'].'类别不存在']);
        }

        //自营店铺编码
        $store = ProductModel::$selfStore;
        if(isset($store[$productData['StoreID']])){
            $productData['Supplier'] = $store[$productData['StoreID']];
        }

        //自定义重量
        $productData['SourceCode'] = 'ERP';
        $data = $this->model->addProduct($productData);
        if($data > 0){
            return apiReturn(['code'=>200, 'data'=>['id'=>$data]]);
        }else{
            return apiReturn(['code'=>100, 'msg'=>'create failed']);
        }
    }



    /**
     * 产品更新
     * @param $params
     * @return mixed
     */
    public function updateProductsPrice($params){
        $time = time();
        foreach($params as $product){
            //市场价格折扣
//            $listPriceDiscount = CommonLib::getListPriceDiscount();
            $updateProduct = array();
            if(!isset($product['product_id']) || !isset($product['skus'])){
                //记录返回日志
                continue;
            }
            //查询更新产品是否存在
            $findProudct = $this->model->getProduct(['product_id'=>(int)$product['product_id'],
                'field'=>['_id','Skus.SalesPrice','Skus._id','Skus.Code','Skus.BulkRateSet','LowPrice','HightPrice']]);
            if(empty($findProudct)){
                continue;
            }
            //获取价格数组
//            $olePriceArray = CommonLib::getColumn('SalesPrice',$findProudct['Skus']);
//            $newPriceArray = CommonLib::getColumn('price',$product['skus']);
//
//            //更新最低价格
//            if(count($newPriceArray) == count($olePriceArray)){
//                $updateProduct['LowPrice'] = (double)min($newPriceArray);
//            }else{
//                if(min($olePriceArray) > min($newPriceArray)){
//                    $updateProduct['LowPrice'] = (double)min($newPriceArray);
//                }
//            }
//
//            if(count($newPriceArray) == count($olePriceArray)){
//                $updateProduct['HightPrice'] = (double)max($newPriceArray);
//            }else{
//                if(max($olePriceArray) <= max($newPriceArray)){
//                    $updateProduct['HightPrice'] = (double)max($newPriceArray);
//                }
//            }

            //更新价格
            if(!empty($product['skus'])){
                foreach($product['skus'] as $skus){
                    $updateKey = -1;
                    if(!isset($skus['price'])){
                        continue;
                    }
                    //更新sku价格,查找key值
                    foreach($findProudct['Skus'] as $pkey => $productSkus){
                        if($skus['id'] == $productSkus['_id']){
                            $updateKey = $pkey;
                            break;
                        }
                        if($skus['id'] == $productSkus['Code']){
                            $updateKey = $pkey;
                            break;
                        }
                    }
                    if($updateKey == -1){
                        continue;
                    }
                    $findProudct['Skus'][$updateKey]['SalesPrice'] = (double)$skus['price'];
                    $updateProduct['Skus.'.$updateKey.'.SalesPrice'] = (double)$skus['price'];
                    //批发价格
                    if(isset($skus['bulkRate']) && !empty($skus['bulkRate'])){
                        if(isset($skus['bulkRate']['discount'])){
                            $updateProduct['Skus.'.$updateKey.'.BulkRateSet.Discount'] = (double)$skus['bulkRate']['discount'];
                        }
                        if(isset($skus['bulkRate']['price'])){
                            $updateProduct['Skus.'.$updateKey.'.BulkRateSet.SalesPrice'] = (double)$skus['bulkRate']['price'];
                        }
                        if(isset($skus['bulkRate']['batches'])){
                            $updateProduct['Skus.'.$updateKey.'.BulkRateSet.Batches'] = (double)$skus['bulkRate']['batches'];
                        }
                    }else{
                        //默认设置批发价格
                        $bulkRatePrice = round((double)$skus['price'] - (double)$skus['price'] * 0.025,2);
                        $updateProduct['Skus.'.$updateKey.'.BulkRateSet.SalesPrice'] = (double)$bulkRatePrice;
                        $updateProduct['Skus.'.$updateKey.'.BulkRateSet.Discount'] = round(($skus['price'] - $bulkRatePrice) / $skus['price'],3);
                    }
                    //市场价格
//                    if($listPriceDiscount != 0){
//                        $updateProduct['Skus.'.$updateKey.'.ListPrice'] = (double)round($skus['price'] / (1 - $listPriceDiscount), 2);
//                    }
                }
            }
            if(empty($updateProduct)){
                return false;
            }else{
                $newPriceArray = CommonLib::getColumn('SalesPrice',$findProudct['Skus']);
                $updateProduct['LowPrice'] = (double)min($newPriceArray);
                $updateProduct['HightPrice'] = (double)max($newPriceArray);
            }

            //市场价格区间
//            $listPriceArray = CommonLib::countListPrice($updateProduct['LowPrice'],$updateProduct['HightPrice'],$listPriceDiscount);
//            $updateProduct['LowListPrice'] = (double)$listPriceArray['LowListPrice'];
//            $updateProduct['HighListPrice'] = (double)$listPriceArray['HighListPrice'];
//            $updateProduct['ListPriceDiscount'] = (double)$listPriceArray['ListPriceDiscount'];

            $result =  $this->model->updateProductSkuPrice(['_id'=>(int)$product['product_id']],$updateProduct);
            if($result){
                //记录日志
                $insert['EntityId'] = (int)$product['product_id'];
                $insert['CreatedDateTime'] = $time;
                $insert['IsSync'] = true;
                $insert['Note'] = '价格更新';
                $this->model->addProductHistory($insert);
            }
            return $result;
        }
    }

    /**
     * 新增产品数据处理
     * @param $data
     * @return mixed
     */
    private function analysisAddProductData(&$data){

        //keyword
        if(isset($data['Keywords'])){
            $words = array();
            if(is_array($data['Keywords'])){
                foreach($data['Keywords'] as $key => $val){
                    $words[$key] = $val;
                }
                $data['Keywords'] = array_values($words);
            }
        }
        //去除头尾空格
        $data['Title'] = rtrim($data['Title']);
        $data['Title'] = ltrim($data['Title']);
        //过滤特殊字符
        if(isset($data['Title'])){
            $regex = "/\~|\!|\@|\#|\\$|\%|\^|\&|\{|\}|\<|\>|\?|\[|\]|\;|\`|\=|\\\|\|/";
            $data['Title'] = preg_replace($regex,"",$data['Title']);
        }
        $data['ShippingFee'] = isset($data['ShippingFee']) ? (int)$data['ShippingFee'] : 0;

        $data['RewrittenUrl'] = CommonLib::filterTitle($data['Title']);
        //再次过滤，非字母数字的特殊符号全去除
        $regex = "/[^a-zA-Z0-9\-]/";
        $data['RewrittenUrl'] = preg_replace($regex,"",$data['RewrittenUrl']);
        $data['FirstProductImage'] = isset($data['ImageSet']['ProductImg'][0]) ? $data['ImageSet']['ProductImg'][0] : '';
        $data['IsActivity'] = isset($data['IsActivity'])? $data['IsActivity'] : 0;
        $data['IsActivityEnroll'] = isset($data['IsActivityEnroll'])? $data['IsActivityEnroll'] : 0;

        $data['GroupId'] = isset($data['GroupId']) ? (int)$data['GroupId'] : 0;
        $data['StoreID'] = (int)$data['StoreID'];
        $data['StoreName'] = isset($data['StoreName']) ? $data['StoreName'] : null;
        $data['BrandId'] = (int)$data['BrandId'];
        //处理品牌名称的情况
        if($data['BrandName'] == 0){
            $data['BrandName'] = '';
        }

        //新增产品初始化都是0
        $data['AvgRating'] = 100;
        $data['ReviewCount'] = 0;
        $data['SalesRank'] = 0;
        $data['SalesCounts'] = 0;

        $data['DiscountLowPrice'] = 0;
        $data['DiscountHightPrice'] = 0;

        $data['FirstCategory'] = isset($data['FirstCategory']) ? (int)$data['FirstCategory'] : 0;
        $data['SecondCategory'] = isset($data['SecondCategory']) ? (int)$data['SecondCategory'] : 0;
        $data['ThirdCategory'] = isset($data['ThirdCategory']) ? (int)$data['ThirdCategory'] : 0;
        $data['FourthCategory'] = isset($data['FourthCategory']) ? (int)$data['FourthCategory'] : 0;
        $data['FifthCategory'] = isset($data['FifthCategory']) ? (int)$data['FifthCategory'] : 0;

        $data['ProductType'] = isset($data['ProductType']) ? (int)$data['ProductType'] : 1;//默认值1
        $data['IsMVP'] = isset($data['IsMVP']) ? $data['IsMVP'] : 0;
        //统一时间戳的格式
        $time = time();

        //是否是来至拆分产品：1-是，0-不是
        $is_split = isset($data['is_split']) ? $data['is_split'] : 0;
        if($is_split != 1){
            $data['AddTime'] = $time;
            $data['EditTime'] = $time;
        }else{
            //拆分传过来的时间，是字符串，需转换处理
            if(isset($data['AddTime']) && !empty($data['AddTime'])){
                $data['AddTime'] = (int)$data['AddTime'];
            }
            if(isset($data['EditTime']) && !empty($data['EditTime'])){
                $data['EditTime'] = (int)$data['EditTime'];
            }
        }

        $data['ProductStatus'] = isset($data['ProductStatus']) ? (int)$data['ProductStatus']: 0;
        //有效期写死999 add by zhongning 何元需求
        $data['Days'] = 999;
        $data['ExpiryDate'] = strtotime("+999 day");

        $data['PackingList']['Weight'] = (double)$data['PackingList']['Weight'];
        if($data['PackingList']['UseCustomWeight'] == true){
            $data['PackingList']['CustomeWeightInfo']['Qty'] = (int)$data['PackingList']['CustomeWeightInfo']['Qty'];
            $data['PackingList']['CustomeWeightInfo']['IncreaseQty'] = (int)$data['PackingList']['CustomeWeightInfo']['IncreaseQty'];
            $data['PackingList']['CustomeWeightInfo']['IncreaseWeight'] = (int)$data['PackingList']['CustomeWeightInfo']['IncreaseWeight'];
        }
        //需要解析 add by zhongning 20190807
        if(!empty($data['PackingList']['Title'])) {
            $data['PackingList']['Title'] = htmlspecialchars_decode($data['PackingList']['Title']);
        }
        //产品属性
        if(!empty($data['ProductAttributes'])) {
            $data['ProductAttributes'] = htmlspecialchars_decode($data['ProductAttributes']);
        }
        $data['IsOnSale'] = isset($data['IsOnSale']) ? $data['IsOnSale'] : 0;
        $data['IsCoupon'] = isset($data['IsCoupon']) ? $data['IsCoupon'] : 0;
        $data['LogisticsTemplateId'] = (int)$data['LogisticsTemplateId'];
        $data['SalesMode'] = isset($data['SalesMode']) ? (int)$data['SalesMode'] : '';
        $data['HSCode'] = "";
        $data['DeclarationName'] = "";
        //Reviews 初始化
        $data['Reviews'] = [
            'fiveStarCount'=>0,
            'fourStarCount'=>0,
            'threeStarCount'=>0,
            'twoStarCount'=>0,
            'oneStarCount'=>0,
            'fiveStarRatio'=>0,
            'fourStarRatio'=>0,
            'threeStarRatio'=>0,
            'twoStarRatio'=>0,
            'oneStarRatio'=>0
        ];
        if(!isset($data['IsHistory'])){
            $data['IsHistory'] = 0;
        }
        //替换\\'s = 's
        if(!empty($data['Descriptions'])){
            $descriptions = htmlspecialchars_decode($data['Descriptions']);
            $find1 = "\\'";//1个\
            $find2 = "\\\'";//2个\
            $find3 = "\\\\\'";//3个\
            $findArray = [$find3,$find2,$find1];
            //因为有些产品有1个\，有些产品3个\，所以只能数组循环
            foreach($findArray as $findVal){
                if(strpos($descriptions,$findVal) === false){
                    continue;
                }else{
                    $descriptions = str_replace($findVal,"'",$descriptions);
                }
            }
            $data['Descriptions'] = $descriptions;
        }
        //是否是自营店铺商品
        $data['IsSelfSupport'] = isset($data['IsSelfSupport']) ? (int)$data['IsSelfSupport'] : 0;
    }

    /**
     * 修改产品数据处理
     * @param $data
     * @return mixed
     */
    private function analysisUpdateProductData(&$data){

        //过滤特殊字符
        if(isset($data['Title'])){
            $regex = "/\~|\!|\@|\#|\\$|\%|\^|\&|\{|\}|\<|\>|\?|\[|\]|\;|\`|\=|\\\|\|/";
            $data['Title'] = preg_replace($regex,"",$data['Title']);
        }
        //历史数据标识
        if(isset($data['IsHistory'])){
            $data['IsHistory'] = (int)$data['IsHistory'];
        }
        //keyword
        if(isset($data['Keywords'])){
            $words = array();
            if(is_array($data['Keywords'])){
                foreach($data['Keywords'] as $key => $val){
                    $words[$key] = $val;
                }
                $data['Keywords'] = array_values($words);
            }
        }

        //产品状态
        if(isset($data['ProductStatus'])){
            //张恒：修改产品，产品状态保持不变
            unset($data['ProductStatus']);
//            $data['ProductStatus'] = (int)$data['ProductStatus'];
        }
        //更新时间
        $data['EditTime'] = time();

        /** 报名活动相关 start **/
        if(isset($data['IsActivityEnroll'])){
            $data['IsActivityEnroll'] = (int)$data['IsActivityEnroll'];
        }
        if(isset($data['IsActivityEnrollStartTime'])){
            $data['IsActivityEnrollStartTime'] = (int)$data['IsActivityEnrollStartTime'];
        }
        if(isset($data['IsActivityEnrollEndTime'])){
            $data['IsActivityEnrollEndTime'] = (int)$data['IsActivityEnrollEndTime'];
        }
        /** 报名活动相关 end **/

        if(isset($data['GroupId'])){
            $data['GroupId'] = (int)$data['GroupId'];
        }
        if(isset($data['StoreID'])){
            $data['StoreID'] = (int)$data['StoreID'];
        }
        if(isset($data['BrandId'])){
            $data['BrandId'] = (int)$data['BrandId'];
        }
        if(isset($data['FirstCategory']) && isset($data['SecondCategory']) && isset($data['ThirdCategory']) && isset($data['FourthCategory'])){
            $data['FirstCategory'] = (int)$data['FirstCategory'];
            $data['SecondCategory'] = (int)$data['SecondCategory'];
            $data['ThirdCategory'] = (int)$data['ThirdCategory'];
            $data['FourthCategory'] = (int)$data['FourthCategory'];
        }elseif(isset($data['FirstCategory']) && isset($data['SecondCategory']) && isset($data['ThirdCategory']) && !isset($data['FourthCategory'])){
            $data['FirstCategory'] = (int)$data['FirstCategory'];
            $data['SecondCategory'] = (int)$data['SecondCategory'];
            $data['ThirdCategory'] = (int)$data['ThirdCategory'];
            $data['FourthCategory'] = 0;
        }elseif(isset($data['FirstCategory']) && isset($data['SecondCategory']) && !isset($data['ThirdCategory']) && !isset($data['FourthCategory'])){
            $data['FirstCategory'] = (int)$data['FirstCategory'];
            $data['SecondCategory'] = (int)$data['SecondCategory'];
            $data['ThirdCategory'] = 0;
            $data['FourthCategory'] = 0;
        }

        if(isset($data['PackingList']['Weight'])){
            $data['PackingList']['Weight'] = (double)$data['PackingList']['Weight'];
        }
        if(isset($data['PackingList']['UseCustomWeight']) && $data['PackingList']['UseCustomWeight'] == true){
            $data['PackingList']['CustomeWeightInfo']['Qty'] = (int)$data['PackingList']['CustomeWeightInfo']['Qty'];
            $data['PackingList']['CustomeWeightInfo']['IncreaseQty'] = (int)$data['PackingList']['CustomeWeightInfo']['IncreaseQty'];
            $data['PackingList']['CustomeWeightInfo']['IncreaseWeight'] = (double)$data['PackingList']['CustomeWeightInfo']['IncreaseWeight'];
        }
        if(isset($data['ReviewCount'])){
            $data['ReviewCount'] = (int)$data['ReviewCount'];
        }
        if(isset($data['AvgRating'])){
            $data['AvgRating'] = (double)$data['AvgRating'];
        }
        if(isset($data['IsOnSale'])){
            $data['IsOnSale'] = (int)$data['IsOnSale'];
        }
        if(isset($data['IsCoupon'])){
            $data['IsCoupon'] = (int)$data['IsCoupon'];
        }
        if(isset($data['LogisticsTemplateId'])){
            $data['LogisticsTemplateId'] = (int)$data['LogisticsTemplateId'];
        }
        if(isset($data['LogisticsTemplateName'])){
            $data['LogisticsTemplateName'] = $data['LogisticsTemplateName'];
        }
        //是否同步运费模板/图片标识
        if(isset($data['IsHistoryIsSyncSTAndImgs'])){
            $data['IsHistoryIsSyncSTAndImgs'] = (int)$data['IsHistoryIsSyncSTAndImgs'];
        }
        if(isset($data['IsMVP'])){
            $data['IsMVP'] = (int)$data['IsMVP'];
        }
        if(isset($data['Days'])){
            //有效期写死999 add by zhongning 何元需求
            $data['Days'] = 999;
            $data['ExpiryDate'] = strtotime("+999 day");
        }
        if(isset($data['DiscountLowPrice'])){
            $data['DiscountLowPrice'] = (double)$data['DiscountLowPrice'];
        }
        if(isset($data['DiscountHightPrice'])){
            $data['DiscountHightPrice'] = (double)$data['DiscountHightPrice'];
        }
        /*if(isset($data['ProductStatus'])){
            $data['ProductStatus'] = (int)$data['ProductStatus'];
        }*/
        if(isset($data['SalesCounts'])){
            $data['SalesCounts'] = (int)$data['SalesCounts'];
        }
        if(isset($data['SalesRank'])){
            $data['SalesRank'] = (double)$data['SalesRank'];
        }
        if(isset($data['CommissionType'])){
            $data['CommissionType'] = (int)$data['CommissionType'];
        }
        if(isset($data['Commission'])){
            $data['Commission'] = (float)$data['Commission'];
        }
        if(isset($data['Commission'])){
            $data['Commission'] = (float)$data['Commission'];
        }
        if(isset($data['IsActivity'])){
            $data['IsActivity'] = (int)$data['IsActivity'];
        }
//        $data['EditorTime'] = time();
        //首图
        if(isset($data['ImageSet']['ProductImg'][0]) && !empty($data['ImageSet']['ProductImg'][0])){
            $data['FirstProductImage'] = $data['ImageSet']['ProductImg'][0];
        }

        //替换\\'s = 's
        if(!empty($data['Descriptions'])){
            $descriptions = htmlspecialchars_decode($data['Descriptions']);
            $find1 = "\\'";//1个\
            $find2 = "\\\'";//2个\
            $find3 = "\\\\\'";//3个\
            $findArray = [$find3,$find2,$find1];
            //因为有些产品有1个\，有些产品3个\，所以只能数组循环
            foreach($findArray as $findVal){
                if(strpos($descriptions,$findVal) === false){
                    continue;
                }else{
                    $descriptions = str_replace($findVal,"'",$descriptions);
                }
            }
            $data['Descriptions'] = $descriptions;
        }
    }

    /**
     *  查询spu，sku
     */
    public function queryProductId($params){
        $data = $this->model->queryProductId($params);
        if(!empty($data)){
            $result = array();
            foreach($data as $k => $val){
                $result[$k]['status'] = $val['ProductStatus'];
                $result[$k]['spu'] = $val['_id'];
                $result[$k]['sku'] = [];
                $result[$k]['Code'] = [];
                if(isset($val['Skus']) && !empty($val['Skus'])){
                    $result[$k]['sku'] = CommonLib::getColumn('_id',$val['Skus']);
                    $result[$k]['Code'] = CommonLib::getColumn('Code',$val['Skus']);
                }
            }
            return $result;
        }
        return $data;
    }

    /**
     * 产品数据拆分
     * @param $params
     * [
        'product_id'=>100,
        'store_id'=>666,
        'data'=>[
            [
            'title'=>'title',
            'sku_codes'=>'1245,235,1231',
            ]
        ]
    ]
     * @return array
     */
    public function splitProduct($params){
        $product_id = $params['product_id'];
        $store_id = $params['store_id'];
        $split_data = $params['data'];
        $product_info = $this->model->getProduct(['product_id'=>$product_id,'store_id'=>$store_id]);
        Log::record('1-splitProduct'.$product_id);
        Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,'splitProduct'.$product_id, $params,'product info', $product_info);
        if (empty($product_info)){
            return ['code'=>1003,'msg'=>'产品不存在'];
        }
        $_skus = $product_info['Skus'];
        $_store_name = $product_info['StoreName'];
        if (count($_skus) <=1){
            return ['code'=>1004,'msg'=>'产品'.$product_id.'不符合拆分条件'];
        }
        /** 数据校验 **/
        //所有符合条件需要拆分的sku code集合
        $_all_sku_codes = [];
        foreach ($split_data as $k=>$v){
            $_tem = [];
            $_tem_code = [];
            $_tem['title'] = $v['title'];
            $tmp_sku_codes = explode(',', $v['sku_codes']);
            foreach ($tmp_sku_codes as $k1=>$v1){
                //验证sku_codes的正确性，过滤掉不正确的code
                foreach ($_skus as $k2=>$v2){
                    if ($v2['Code'] == $v1){
                        $_all_sku_codes[] = $v1;
                        $_tem_code[] = $v1;
                    }
                }
            }
            if (!empty($_tem_code)){
                $_tem['sku_codes'] = implode(',', $_tem_code);
                //重新赋值要拆分的sku数据，确保拆分sku数据的正确性
                $split_data[$k] = $_tem;
            }else{
                unset($split_data[$k]);
            }
        }
        if (empty($_all_sku_codes)){
            return ['code'=>1005,'msg'=>'sku_codes数据错误'];
        }
        /** 获取拆分产品公共数据部分 **/
        $common_product_info = $product_info;
        unset($common_product_info['_id']);
        unset($common_product_info['Title']);
        unset($common_product_info['Skus']);
        //$common_product_info['IsHistory'] = 0;
        /** 根据拆分的sku分组，重新组装成新的spu **/
        $split_success_sku_codes = [];
        Log::record('2-splitProduct'.json_encode($split_data));
        Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,'splitProduct'.$product_id, $params,'final split_data', $split_data);
        //拆分后的产品ID
        $new_product_id_arr = [];
        foreach ($split_data as $k3=>$v3){
            $tmp_return = [];
            $tmp_split_success_sku_codes = [];
            //组装title
            $common_product_info['Title'] = $v3['title'];
            //组装Skus
            $_split_sku_data = [];
            $tmp_sku_codes_arr = explode(',', $v3['sku_codes']);
            //FilterOptions数据
            $_filter_options = [];
            foreach ($tmp_sku_codes_arr as $k4=>$v4){
                foreach ($_skus as $k5=>$v5){
                    if ($v5['Code'] == $v4){
                        $_split_sku_data[] = $v5;
                        $tmp_split_success_sku_codes[] = $v4;
                        //重组FilterOptions
                        if (!empty($v5['SalesAttrs'])){
                            foreach ($v5['SalesAttrs'] as $k9=>$v9){
                                //$option_tmp = [];
                                $option_tmp_key = $v9['_id'].'-'.$v9['OptionId'];
                                //$option_tmp[$option_tmp_key] = 1;
                                $_filter_options[$option_tmp_key] = 1;
                            }
                        }
                    }
                }
            }
            $common_product_info['Skus'] = $_split_sku_data;
            $common_product_info['is_split'] = 1; //是否是拆分产品
            //根据销售属性重新组装FilterOptions数据
            $common_product_info['FilterOptions'] = $_filter_options;
            //产品描述
            $common_product_info['Descriptions'] = htmlspecialchars_decode($common_product_info['Descriptions']);
            Log::record('3-splitProduct'.json_encode($common_product_info));
            //新增拆分好的产品
            Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,'splitProduct'.$product_id, $common_product_info,'add productPost params', $common_product_info);
            //20181018 如果计量单位为空，则默认为piece
            if (
                !isset($common_product_info['SalesUnitType'])
                || empty($common_product_info['SalesUnitType'])
            ){
                $common_product_info['SalesUnitType'] = 'piece';
            }
            $add_res = $this->base_api->productPost($common_product_info);
            Log::record('4-splitProduct'.json_encode($add_res));
            Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,'splitProduct'.$product_id, $common_product_info,'productPost res', $add_res);
            if ($add_res['code'] == 200){
                //将拆分成功的skucode记录下来
                foreach ($tmp_split_success_sku_codes as $k6=>$v6){
                    $split_success_sku_codes[] = $v6;
                }
                //处理运费模板问题
                $new_product_id = $add_res['data']['id'];
                $this->redis->lPush(
                    'addProductShippingTemplateList',
                    json_encode(
                        [
                            'product_id'=>$new_product_id,
                            'product_is_charged'=>$common_product_info['LogisticsLimit'][0],
                            'template_id'=>$common_product_info['LogisticsTemplateId'],
                            'from_flag'=>1 //来源标识：1-新增产品，2-修改产品信息
                        ]
                    )
                );
                //记录新拆分的产品ID
                $tmp_return['product_id'] = $new_product_id;
                $new_product_id_arr[] = $tmp_return;
            }else{
                return ['code'=>1007,'msg'=>$add_res['msg']];
            }
        }
        if (!empty($split_success_sku_codes)){
            //是否拆分完毕：0-不是，1-是
            $is_all_split = 0;
            /** 根据拆分成功的skucode，将原产品对应的skucode删除 **/
            $new_skus = $_skus;
            foreach ($split_success_sku_codes as $k7=>$v7) {
                foreach ($new_skus as $k8=>$v8) {
                    if ($v8['Code'] == $v7) {
                        unset($new_skus[$k8]);
                    }
                }
            }
            $new_skus = array_merge($new_skus);
            $new_update_data = ['Skus'=>$new_skus];
            $mongo = new Mongo('dx_product');
            /** 如果是完全拆分，需要将产品修改为下架[4]/删除[10] **/
            if (count($split_success_sku_codes) == count($_skus)){
                $is_all_split = 1;
                $new_update_data['ProductStatus'] = 10;
            }
            //根据销售属性重新组装FilterOptions数据
            $_new_filter_options = [];
            foreach ($new_skus as $k10=>$v10){
                //重组FilterOptions
                if (!empty($v10['SalesAttrs'])){
                    foreach ($v10['SalesAttrs'] as $k11=>$v11){
                        //$_option_tmp = [];
                        $_option_tmp_key = $v11['_id'].'-'.$v11['OptionId'];
                        //$_option_tmp[$_option_tmp_key] = 1;
                        $_new_filter_options[$_option_tmp_key] = 1;
                    }
                }
            }
            $new_update_data['FilterOptions'] = $_new_filter_options;

            Log::record('5-splitProduct'.json_encode($new_update_data));
            $up_res = $mongo->update(['_id' =>(int)$product_id], ['$set'=>$new_update_data]);
            Log::record('6-splitProduct'.json_encode($up_res));
            Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,'splitProduct'.$product_id, $new_update_data,'mongo update res', $up_res);
            return [
                'code'=>200,
                'data'=>[
                    'product_id'=>$product_id,
                    'store_name'=>$_store_name,
                    'is_all_split'=>$is_all_split,
                    'new_data'=>$new_product_id_arr
                ]
            ];
        }else{
            return ['code'=>1006,'msg'=>'没有拆分成功的数据'];
        }
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
     * 产品更新
     * @param $params
     * @return mixed
     */
    public function updatePrdouctmMultiLangs($params){
        //查询更新产品是否存在
        $proudct = $this->model->getProduct(['product_id'=>(int)$params['id'],'field'=>['_id'=>true,'HumanTranslation']]);
        if(empty($proudct)){
            return apiReturn(['code'=>1000000022, 'msg'=>'找不到此商品']);
        }
        $data = array();
        $res =$this->model->getProductAllMultiLangs($params['id']);
        if(empty($res)){
             if(isset($params['title']) && !empty($params['title'])){
                 $data['Title'][$params['lang']] = $params['title'];
            }
            if(isset($params['descriptions']) && !empty($params['descriptions'])){
                $data['Descriptions'][$params['lang']] = $params['descriptions'];
            }
            if(isset($params['keywords']) && !empty($params['keywords'])){
                $data['Keywords'][$params['lang']] = $params['keywords'];
            }
            if(!empty($data)){
               $data['_id'] =(int)$params['id'];
               $ret =$this->model->addProductMultiLang($data);
            }else{
                return apiReturn(['code'=>1000000022, 'msg'=>'无数据更新']);
            }
        }else{
            if(isset($params['title']) && !empty($params['title'])){
                $data['Title.'.$params['lang']] = $params['title'];
            }
            if(isset($params['descriptions']) && !empty($params['descriptions'])){
                $data['Descriptions.'.$params['lang']] = $params['descriptions'];
            }
            if(isset($params['keywords']) && !empty($params['keywords'])){
                $data['Keywords.'.$params['lang']] = $params['keywords'];
            }
            if(!empty($data)){
                $ret = $this->model->updatePrdouctmMultiLangs(['_id'=>(int)$params['id']],$data);
            }else{
                return apiReturn(['code'=>1000000022, 'msg'=>'无数据更新']);
            }
        }
        if($ret){
            //删除多语言的缓存
            $this->redis->rm('PRODUCT_LANGUAGE_'.$params['id'].'_'.$params['lang']);
            //修改产品
            if(isset($proudct['HumanTranslation'])){
                array_push($proudct['HumanTranslation'],$params['lang']);
                $proudct['HumanTranslation'] = array_unique($proudct['HumanTranslation']);
            }else{
                $proudct['HumanTranslation'] = [$params['lang']];
            }
            $this->model->updateProductSkuPrice(['_id'=>(int)$params['id']],['HumanTranslation'=>$proudct['HumanTranslation']]);
        }
        return apiReturn(['code'=>200]);
    }


    /**
     * 修改国家，产品价格
     * @param $params
     * @return mixed
     */
    public function updateCountryProductsPrice($params){
        try{
            $time = time();
            $updateProduct = array();
            //查询更新产品是否存在
            $findProduct = $this->model->getProduct(['product_id'=>(int)$params['spu'],
                'field'=>['_id','Skus.SalesPrice','Skus._id','Skus.Code','Skus.BulkRateSet','LowPrice','HightPrice']]);
            if(empty($findProduct)){
                return false;
            }
            $country = strtoupper(trim($params['country']));
            //查询国家是否存在
            $findCountry = $this->countryModel->findCountry($country);
            if(empty($findCountry)){
                //国家不存在
                return false;
            }
            $sku_id = $params['sku_id'];
            //暂时兼容code和_id
            $thisSku = CommonLib::filterArrayByKey($findProduct['Skus'],'_id',$params['sku_id']);
            if(empty($thisSku)){
                $thisSku = CommonLib::filterArrayByKey($findProduct['Skus'],'Code',$params['sku_id']);
                if(empty($thisSku)){
                    //要修改的CODE不存在
                    return false;
                }
                $sku_id = $thisSku['_id'];
            }
            $discount = !empty($thisSku['BulkRateSet']['Discount']) ? $thisSku['BulkRateSet']['Discount'] :0;
            $findCountryProductData = $this->countryModel->findCountryProductSkuPrice(['Spu'=>(int)$params['spu'],'Country'=>$country]);
            //判断新增还是修改
            if(empty($findCountryProductData)){
                //新增
                $insertData['Spu'] = (int)$params['spu'];
                $insertData['Country'] = $country;
                $insertData['Skus'][0]['_id'] = (int)$sku_id;
                $insertData['Skus'][0]['SalesPrice'] = (double)$params['price'];
                //批发价格重新计算
                $insertData['Skus'][0]['BulkRateSet']['Discount'] = $discount;
                $bulkPrice = sprintf('%01.2f',$params['price'] - $params['price'] * $discount);
                $insertData['Skus'][0]['BulkRateSet']['SalesPrice'] = (double)$bulkPrice;

                //找出最低价格
                foreach($findProduct['Skus'] as $pkey => $productSkus){
                    if($sku_id == $productSkus['_id']){
                        $updateKey = $pkey;
                        break;
                    }
                }
                //为了查找最低价最高价
                $findProduct['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];
                //最低价，最高价
                $newPriceArray = CommonLib::getColumn('SalesPrice',$findProduct['Skus']);
                $insertData['LowPrice'] = (double)min($newPriceArray);
                $insertData['HightPrice'] = (double)max($newPriceArray);

                $insertData['AddTime'] = date('Y-m-d H:i:s',$time);
                $result =  $this->countryModel->insertCountryProductSkuPrice($insertData);
            }else{
                //修改
                $updateKey = -1;
                //更新sku价格,查找key值
                foreach($findCountryProductData['Skus'] as $pkey => $productSkus){
                    if($sku_id == $productSkus['_id']){
                        $updateKey = $pkey;
                        break;
                    }
                }
                if($updateKey == -1){
                    //新增SKU
                    $count = count($findCountryProductData['Skus']);
                    $updateProduct['Skus.'.$count.'._id'] = (int)$sku_id;
                    $updateProduct['Skus.'.$count.'.SalesPrice'] = (double)$params['price'];
                    //批发价格重新计算
                    $updateProduct['Skus.'.$count.'.BulkRateSet.Discount'] = $discount;
                    $bulkPrice = sprintf('%01.2f',$params['price'] - $params['price'] * $discount);
                    $updateProduct['Skus.'.$count.'.BulkRateSet.SalesPrice'] = (double)$bulkPrice;

                    //找出现在国家产品价格中的最低价格，最高价格
                    $findCountryProductData['Skus'][$count]['_id'] = (int)$sku_id;
                    $findCountryProductData['Skus'][$count]['SalesPrice'] = (double)$params['price'];
                }else{
                    //更新产品价格
                    $updateProduct['Skus.'.$updateKey.'.SalesPrice'] = (double)$params['price'];
                    //根据之前的折扣，传入的金额,重新计算批发价格
                    $bulkPrice = sprintf('%01.2f',$params['price'] - $params['price'] * $discount);
                    $updateProduct['Skus.'.$updateKey.'.BulkRateSet.SalesPrice'] = (double)$bulkPrice;

                    //找出现在国家产品价格中的最低价格，最高价格
                    $findCountryProductData['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];
                }



                //找出原产品中最低价格，最高价格
                foreach($findProduct['Skus'] as $pkey => $productSkus){
                    if($sku_id == $productSkus['_id']){
                        $updateKey = $pkey;
                        break;
                    }
                }
                $findProduct['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];

                if(count($findCountryProductData['Skus']) != count($findProduct['Skus'])){
                    //原产品中最低价，最高价
                    $oldPriceArray = CommonLib::getColumn('SalesPrice',$findProduct['Skus']);
                    $updateProduct['LowPrice'] = (double)min($oldPriceArray);
                    $updateProduct['HightPrice'] = (double)max($oldPriceArray);

                    //现在国家产品价格中的最低价格，最高价格
                    $newPriceArray = CommonLib::getColumn('SalesPrice',$findCountryProductData['Skus']);
                    $lowPrice = (double)min($newPriceArray);
                    $hightPrice = (double)max($newPriceArray);
                    //取最小的
                    if($lowPrice < $updateProduct['LowPrice']){
                        $updateProduct['LowPrice'] = $lowPrice;
                    }
                    //取最大的
                    if($hightPrice > $updateProduct['HightPrice']){
                        $updateProduct['HightPrice'] = $hightPrice;
                    }
                }else{
                    //现在国家产品价格中的最低价格，最高价格
                    $newPriceArray = CommonLib::getColumn('SalesPrice',$findCountryProductData['Skus']);
                    $updateProduct['LowPrice'] = (double)min($newPriceArray);
                    $updateProduct['HightPrice'] = (double)max($newPriceArray);
                }

                $updateProduct['UpdateTime'] = date('Y-m-d H:i:s',$time);
                $result =  $this->countryModel->updateCountryProductSkuPrice(['Spu'=>(int)$params['spu'],'Country'=>$country],$updateProduct);
            }

            if($result){
                //记录日志
                $insert['EntityId'] = (int)$params['spu'];
                $insert['CreatedDateTime'] = $time;
                $insert['IsSync'] = true;
                $insert['Note'] = $country.'国家产品价格更新';
                $insert['AddTime'] = date('Y-m-d H:i:s',$time);
                $this->model->addProductHistory($insert);
            }
            return $result;
        }catch (Exception $e){
            Log::record('batchUpdateCountryProductPrice params:'.json_encode($params).';error:'.$e->getMessage(),'error');
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());
        }
    }


    /**
     * 修改国家，产品价格
     * @param $params
     * @return mixed
     */
    public function deleteCountryProduct($params){
        $params['country'] = isset($params['country']) ? $params['country'] : null;
        $time = time();
        //查询更新产品是否存在
        $findProduct = $this->model->getProduct(['product_id'=>(int)$params['spu'],
            'field'=>['_id','Skus.SalesPrice','Skus._id','Skus.Code','Skus.BulkRateSet','LowPrice','HightPrice']]);
        if(empty($findProduct)){
            return false;
        }
        $countryData = array();
        if(!empty($params['country'])){
            $country = explode(',',$params['country']);
            foreach($country as $key => $cval){
                $cval = strtoupper(trim($cval));
                //查询国家是否存在
                $findCountry = $this->countryModel->findCountry($cval);
                if(!empty($findCountry)){
                    $countryData[] = $cval;
                }
            }
        }
        if(empty($countryData)){
            //删除所有的国家产品价格
            $result = $this->countryModel->deleteCountryProduct(['Spu'=>(int)$params['spu']]);
        }else{
            $result = $this->countryModel->deleteCountryProduct(['Spu'=>(int)$params['spu'],'Country'=>['in',$countryData]]);
        }
        if($result){
            //记录日志
            $insert['EntityId'] = (int)$params['spu'];
            $insert['CreatedDateTime'] = $time;
            $insert['IsSync'] = true;
            $insert['Note'] = $params['country'].'删除国家产品价格';
            $insert['AddTime'] = date('Y-m-d H:i:s',$time);
            $this->model->addProductHistory($insert);
        }
        return $result;
    }
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
     * 国家产品价格黑名单
     * @param $params
     * @return mixed
     */
    public function CountryProductsPriceBlacklist($params){
        //查询黑名单是否存在
        $findCountry = $this->countryModel->findCountryProductBlacklist(['SPU'=>(int)$params['spu'],'SKU'=>(int)$params['sku']]);
        if(!empty($findCountry)){
            //国家存在，是否删除操作
            if($params['operation'] == 2){
                return $this->countryModel->deleteCountryProductBlacklist(['SPU'=>(int)$params['spu'],'SKU'=>(int)$params['sku']]);
            }
            return true;
        }
        //新增
        if($params['operation'] == 1){
            date_default_timezone_set('PRC');
            return $this->countryModel->addCountryProductBlacklist(['SPU'=>(int)$params['spu'],'SKU'=>(int)$params['sku'],'AddTime'=>date('Y-m-d H:i:s')]);
        }
        return true;
    }

    /**
     * 市场价格更新
     * @param $params
     * @return mixed
     */
    public function updateProductListPrice($params){

        foreach($params as $product){
            $updateProduct = array();
            if(!isset($product['product_id']) || !isset($product['skus'])){
                //记录返回日志
                continue;
            }
            //查询更新产品是否存在
            $findProudct = $this->model->getProduct(['product_id'=>(int)$product['product_id'],
                'field'=>['_id','Skus.SalesPrice','Skus._id','Skus.Code','Skus.ListPrice','Skus.BulkRateSet','LowPrice','HightPrice','ListPriceDiscount']]);
            if(empty($findProudct)){
                continue;
            }
            //更新价格
            if(!empty($product['skus'])){
                foreach($product['skus'] as $skus){
                    $updateKey = -1;
                    if(!isset($skus['list_price'])){
                        continue;
                    }
                    //更新sku价格,查找key值
                    foreach($findProudct['Skus'] as $pkey => $productSkus){
                        if($skus['id'] == $productSkus['_id']){
                            $updateKey = $pkey;
                            break;
                        }
                        if($skus['id'] == $productSkus['Code']){
                            $updateKey = $pkey;
                            break;
                        }
                    }
                    if($updateKey == -1){
                        continue;
                    }
                    $findProudct['Skus'][$updateKey]['ListPrice'] = (double)$skus['list_price'];
                    $updateProduct['Skus.'.$updateKey.'.ListPrice'] = (double)$skus['list_price'];
                }
            }
            if(empty($updateProduct)){
                return false;
            }else{
                $newPriceArray = CommonLib::getColumn('ListPrice',$findProudct['Skus']);
                $updateProduct['LowListPrice'] = (double)min($newPriceArray);
                $updateProduct['HighListPrice'] = (double)max($newPriceArray);
                if($updateProduct['LowListPrice'] - $findProudct['LowPrice'] > 0){
                    $updateProduct['ListPriceDiscount'] = round(($updateProduct['LowListPrice'] - $findProudct['LowPrice']) / $updateProduct['LowListPrice'] ,2);
//                    if(!empty($findProudct['ListPriceDiscount'])){
//                        $updateProduct['ListPriceDiscount'] = $updateProduct['ListPriceDiscount'] > $findProudct['ListPriceDiscount'] ?
//                            $updateProduct['ListPriceDiscount'] : $findProudct['ListPriceDiscount'];
//                    }
                }
            }
            $result =  $this->model->updateProductSkuPrice(['_id'=>(int)$product['product_id']],$updateProduct);
            return $result;
        }
    }


    /**
     * 市场价格更新
     * @param $params
     * @return mixed
     */
    public function getProductPrice($params){
        $result = array();
        $countryResult = array();
        $params = explode(',',$params['spus']);
        foreach($params as $product_id){
            //查询更新产品是否存在
            $findProudct = $this->model->getProduct(['product_id'=>(int)$product_id,
                'field'=>['_id','Skus.SalesPrice','Skus.Code','Skus._id']]);
            if(empty($findProudct)){
                continue;
            }

            $selectCountryProductData = $this->countryModel->selectCountryProduct(['Spu'=>(int)$product_id]);
//            pr($selectCountryProductData);die;

            //国家售价
            if(!empty($selectCountryProductData)){
                foreach($selectCountryProductData as $ckey => $countryData){
                    foreach($countryData['Skus'] as $skey => $skuData){
                        $countryResult[$skuData['_id']][] = $countryData['Country'].','.$skuData['SalesPrice'];
                    }
                }
//                pr($countryResult);die;
            }

            $result[$product_id]['spu'] = $findProudct['_id'];
            $result[$product_id]['skus'] = array();
            foreach($findProudct['Skus'] as $skey => $sku){
                $result[$product_id]['skus'][$skey]['sku'] = $sku['Code'];
                $result[$product_id]['skus'][$skey]['price'] = (string)$sku['SalesPrice'];
                $result[$product_id]['skus'][$skey]['country_price'] = '';
                if(!empty($countryResult[$sku['_id']])){
                    $result[$product_id]['skus'][$skey]['country_price'] = implode('|',$countryResult[$sku['_id']]);
                }
            }
        }
        return array_values($result);
    }


    /**
     * 根据SKU获取成本价
     * @param $params
     * @return mixed
     * add by 20190711 kevin
     */
    public function getProductPurchasePrice($params){
        $result = $this->model->getProductPurchasePrice($params);
        return $result;
    }


    /**
     *  [ error ] [28]Cannot create field 'BulkRateSet' in element {3: null}[/data/website/api.dx.com/vendor/topthink/think-mongo/src/Connection.php:402]
     * 修改国家，产品价格
     * @param $params
     * @return mixed
     */
    public function updateCountryProductsPrice2($params){
        $time = time();
        $updateProduct = array();
        //查询更新产品是否存在
        $findProduct = $this->model->getProduct(['product_id'=>(int)$params['spu'],
            'field'=>['_id','Skus.SalesPrice','Skus._id','Skus.Code','Skus.BulkRateSet','LowPrice','HightPrice']]);
        if(empty($findProduct)){
            return false;
        }
        $country = strtoupper(trim($params['country']));
        //查询国家是否存在
        $findCountry = $this->countryModel->findCountry($country);
        if(empty($findCountry)){
            //国家不存在
            return false;
        }
        $sku_id = $params['sku_id'];
        //暂时兼容code和_id
        $thisSku = CommonLib::filterArrayByKey($findProduct['Skus'],'_id',$params['sku_id']);
        if(empty($thisSku)){
            $thisSku = CommonLib::filterArrayByKey($findProduct['Skus'],'Code',$params['sku_id']);
            if(empty($thisSku)){
                //要修改的CODE不存在
                return false;
            }
            $sku_id = $thisSku['_id'];
        }
        $discount = !empty($thisSku['BulkRateSet']['Discount']) ? $thisSku['BulkRateSet']['Discount'] :0;
        $findCountryProductData = $this->countryModel->findCountryProductSkuPrice(['Spu'=>(int)$params['spu'],'Country'=>$country]);
        //判断新增还是修改
        if(empty($findCountryProductData)){
            //新增
            $insertData['Spu'] = (int)$params['spu'];
            $insertData['Country'] = $country;
            $insertData['Skus'][0]['_id'] = (int)$sku_id;
            $insertData['Skus'][0]['SalesPrice'] = (double)$params['price'];
            //批发价格重新计算
            $insertData['Skus'][0]['BulkRateSet']['Discount'] = $discount;
            $bulkPrice = sprintf('%01.2f',$params['price'] - $params['price'] * $discount);
            $insertData['Skus'][0]['BulkRateSet']['SalesPrice'] = (double)$bulkPrice;

            //找出最低价格
            foreach($findProduct['Skus'] as $pkey => $productSkus){
                if($sku_id == $productSkus['_id']){
                    $updateKey = $pkey;
                    break;
                }
            }
            //为了查找最低价最高价
            $findProduct['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];
            //最低价，最高价
            $newPriceArray = CommonLib::getColumn('SalesPrice',$findProduct['Skus']);
            $insertData['LowPrice'] = (double)min($newPriceArray);
            $insertData['HightPrice'] = (double)max($newPriceArray);

            $insertData['AddTime'] = date('Y-m-d H:i:s',$time);
            $result =  $this->countryModel->insertCountryProductSkuPrice($insertData);
        }else{
            //修改
            $updateKey = -1;
            //更新sku价格,查找key值
            foreach($findCountryProductData['Skus'] as $pkey => $productSkus){
                if($sku_id == $productSkus['_id']){
                    $updateKey = $pkey;
                    break;
                }
            }
            if($updateKey == -1){
                //新增SKU
                $count = count($findCountryProductData['Skus']);
                $updateProduct['Skus'] = $findCountryProductData['Skus'];

                $upd['_id'] = (int)$sku_id;
                $upd['SalesPrice'] = (double)$params['price'];
                $upd['BulkRateSet']['Discount'] = (double)$discount;
                $bulkPrice = sprintf('%01.2f',$params['price'] - $params['price'] * $discount);
                $upd['BulkRateSet']['SalesPrice'] = (double)$bulkPrice;
                $updateProduct['Skus'][$count] = $upd;

                //找出现在国家产品价格中的最低价格，最高价格
                $findCountryProductData['Skus'][$count]['_id'] = (int)$sku_id;
                $findCountryProductData['Skus'][$count]['SalesPrice'] = (double)$params['price'];
            }else{
                $updateProduct['Skus'] = $findCountryProductData['Skus'];
                //更新产品价格
                $updateProduct['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];

                //根据之前的折扣，传入的金额,重新计算批发价格
                $bulkPrice = sprintf('%01.2f',$params['price'] - $params['price'] * $discount);
                $updateProduct['Skus'][$updateKey]['BulkRateSet']['SalesPrice'] = (double)$bulkPrice;

                //找出现在国家产品价格中的最低价格，最高价格
                $findCountryProductData['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];
            }

            //找出原产品中最低价格，最高价格
            foreach($findProduct['Skus'] as $pkey => $productSkus){
                if($sku_id == $productSkus['_id']){
                    $updateKey = $pkey;
                    break;
                }
            }
            $findProduct['Skus'][$updateKey]['SalesPrice'] = (double)$params['price'];

            if(count($findCountryProductData['Skus']) != count($findProduct['Skus'])){
                //原产品中最低价，最高价
                $oldPriceArray = CommonLib::getColumn('SalesPrice',$findProduct['Skus']);
                $updateProduct['LowPrice'] = (double)min($oldPriceArray);
                $updateProduct['HightPrice'] = (double)max($oldPriceArray);

                //现在国家产品价格中的最低价格，最高价格
                $newPriceArray = CommonLib::getColumn('SalesPrice',$findCountryProductData['Skus']);
                $lowPrice = (double)min($newPriceArray);
                $hightPrice = (double)max($newPriceArray);
                //取最小的
                if($lowPrice < $updateProduct['LowPrice']){
                    $updateProduct['LowPrice'] = $lowPrice;
                }
                //取最大的
                if($hightPrice > $updateProduct['HightPrice']){
                    $updateProduct['HightPrice'] = $hightPrice;
                }
            }else{
                //现在国家产品价格中的最低价格，最高价格
                $newPriceArray = CommonLib::getColumn('SalesPrice',$findCountryProductData['Skus']);
                $updateProduct['LowPrice'] = (double)min($newPriceArray);
                $updateProduct['HightPrice'] = (double)max($newPriceArray);
            }

            $updateProduct['UpdateTime'] = date('Y-m-d H:i:s',$time);

            if(!empty($updateProduct['Skus'])){
                $updateProduct['Skus'] = (object)$updateProduct['Skus'];
            }
            $result =  $this->countryModel->updateCountryProductSkuPrice(['Spu'=>(int)$params['spu'],'Country'=>$country],$updateProduct);
        }

        return $result;
    }

    /**
     * 增加历史
     */
    public function addHistory($spu){
        $insert['EntityId'] = (int)$spu;
        $insert['CreatedDateTime'] = time();
        $insert['IsSync'] = true;
        $insert['Note'] = '国家产品价格更新';
        $insert['AddTime'] = date('Y-m-d H:i:s',time());
        $this->model->addProductHistory($insert);
    }
    /**
     * 产品更新图片
     * @param $params
     * @return mixed
     */
    public function updateProductImg($params){
        //查询更新产品是否存在
        $proudct = $this->model->getProduct(['product_id'=>(int)$params['id'],'field'=>['_id','Skus','ImageSet']]);
        if(empty($proudct)){
            return apiReturn(['code'=>1000000022, 'msg'=>'找不到此商品']);
        }
        //首图
        if(isset($params['ImageSet']['ProductImg'][0]) && !empty($params['ImageSet']['ProductImg'][0])){
            $update['FirstProductImage'] = $params['ImageSet']['ProductImg'][0];
            $update['ImageSet'] = $params['ImageSet'];
        }
        if(!empty($params['Skus'])){
            foreach($proudct['Skus'] as $key => $sku){
                if(isset($params['Skus'][$sku['Code']])){
                    foreach($sku['SalesAttrs'] as $attkey => $attval){
                        if(!empty($attval['Image'])){
                            //插入图片
                            $proudct['Skus'][$key]['SalesAttrs'][$attkey]['Image'] = $params['Skus'][$sku['Code']];
                        }
                    }
                }
            }
        }
        $update['Skus'] = $proudct['Skus'];
        return $this->model->primevalUpdateProduct($params['id'],$update);
    }

    /**
     * 更新产品属性
     * @param $params
     * @return mixed
     */
    public function updateProductAttributes($params){
        foreach($params as $product){
            $proudctInfo = $this->model->getProduct(['product_id'=>(int)$product['product_id'],'field'=>['_id']]);
            //查询更新产品是否存在
            if(empty($proudctInfo)){
                continue;
            }
            if(!empty($product['product_attr'])){
                $ret = $this->model->updateProductSkuPrice(['_id'=>(int)$product['product_id']],['ProductAttributes'=>htmlspecialchars_decode($product['product_attr'])]);
                if($ret){
                    //变更记录
                    $params['IsSync'] = false;
                    $params['Note'] = 'updateProductAttributes';
                    CommonLib::productHistories($product['product_id'].'-updateProductAttributes',$product['product_id']);
                }
            }else{
                continue;
            }
        }
        return true;
    }

    /**
     * 更新市场折扣
     * @param $params
     * @return mixed
     */
    public function updateProductListPriceDiscount($params){
        if(!is_array($params)){
            return apiReturn(['code'=>1000001,'msg'=>'参数有误！']);
        }

        foreach($params as $product){
            $updateProduct = array();
            if(!isset($product['product_id']) || empty($product['discount'])){
                //记录返回日志
                continue;
            }
            //折扣不能大于等于1
            if($product['discount'] >= 1){
                continue;
            }

            //查询更新产品是否存在
            $findProudct = $this->model->getProduct(['product_id'=>(int)$product['product_id'],'field'=>['_id','LowPrice','HightPrice','ListPriceDiscount']]);
            if(empty($findProudct)){
                continue;
            }
            $updateProduct['ListPriceDiscount'] = (double)$product['discount'];
            $updateProduct['LowListPrice'] = (double)round($findProudct['LowPrice'] / (1 - $product['discount']), 2);
            $updateProduct['HighListPrice'] = 0;
            if(!empty($findProudct['HightPrice'])){
                $updateProduct['HighListPrice'] = (double)round($findProudct['HightPrice'] / (1 - $product['discount']), 2);
            }
//            pr($updateProduct);die;
            $result =  $this->model->updateProductSkuPrice(['_id'=>(int)$product['product_id']],$updateProduct);
        }
        return apiReturn(['code'=>200]);
    }
}
