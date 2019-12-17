<?php
namespace app\mallextend\services;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\mallextend\model\ProductClassModel;
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
    public function __construct(){

        $this->model = new ProductModel();
        $this->classModel = new ProductClassModel();
        $this->base_api = new BaseApi();
        $this->redis = new RedisClusterBase();
    }

    public function getProduct($params){
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
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
        return apiReturn(['code'=>200, 'data'=>['id'=>$data]]);
    }



    /**
     * 产品更新
     * @param $params
     * @return mixed
     */
    public function updateProductsPrice($params){
        $time = time();
        foreach($params as $product){
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
                }
            }
            if(empty($updateProduct)){
                return false;
            }else{
                $newPriceArray = CommonLib::getColumn('SalesPrice',$findProudct['Skus']);
                $updateProduct['LowPrice'] = (double)min($newPriceArray);
                $updateProduct['HightPrice'] = (double)max($newPriceArray);
            }

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
            $regex = "/\~|\!|\@|\#|\\$|\%|\^|\&|\{|\}|\:|\<|\>|\?|\[|\]|\;|\`|\=|\\\|\|/";
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
        $day = isset($data['Days']) ? (int)$data['Days']: 0;
        $data['Days'] = $day;
        $data['ExpiryDate'] = strtotime("+$day day");

        $data['PackingList']['Weight'] = (double)$data['PackingList']['Weight'];
        if($data['PackingList']['UseCustomWeight'] == true){
            $data['PackingList']['CustomeWeightInfo']['Qty'] = (int)$data['PackingList']['CustomeWeightInfo']['Qty'];
            $data['PackingList']['CustomeWeightInfo']['IncreaseQty'] = (int)$data['PackingList']['CustomeWeightInfo']['IncreaseQty'];
            $data['PackingList']['CustomeWeightInfo']['IncreaseWeight'] = (int)$data['PackingList']['CustomeWeightInfo']['IncreaseWeight'];
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
            $regex = "/\~|\!|\@|\#|\\$|\%|\^|\&|\{|\}|\:|\<|\>|\?|\[|\]|\;|\`|\=|\\\|\|/";
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
            $data['ProductStatus'] = (int)$data['ProductStatus'];
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
            $day = (int)$data['Days'];
            $data['Days'] = $day;
            $data['ExpiryDate'] = strtotime("+$day day");
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
        $DefaultSkus = $products['Skus'][0];
        $products['DefaultSkuId'] = $DefaultSkus['_id'];
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


}
