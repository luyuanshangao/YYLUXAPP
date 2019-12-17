<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\mallextend\product\ErpCreateProductParams;
use app\common\params\mallextend\product\ErpCreateProductSkuParams;
use app\common\params\mallextend\product\ProductParams;
use app\common\params\seller\product\CreateProductParams;
use app\common\params\seller\product\CreateProductSkuParams;
use app\common\params\mallextend\product\FindProductParams;
use app\common\params\seller\product\UpdateProductStatusParams;
use app\demo\controller\Auth;
use app\mallextend\model\ConfigDataModel;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductCountryModel;
use app\mallextend\model\ProductExtendModel;
use app\mallextend\model\ProductHistoryModel;
use app\mallextend\model\ProductModel;
use app\mallextend\model\RegionModel;
use app\mallextend\model\ShippingCostModel;
use app\mallextend\model\SysConfigModel;
use app\mallextend\services\BaseService;
use app\mallextend\services\ProductService;
use app\mallextend\services\RepairService;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * 功能：修复工具类
 * 开发：钟宁
 * 时间：2018-10-12
 */
class RepairTool extends Base
{
    public $productService;
    public $productModel;
    public $redis;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->productModel = new ProductModel();
        $this->redis = new RedisClusterBase();
    }

    //插入指定变更历史
    public $spusArray = [
        2000275,
    ];
    public function fixProducts(){
        ini_set('max_execution_time', '0');
        $param = input();
        $service = new RepairService();
        $spusArray = $service->getRepairData();
        $spusArray = CommonLib::supportArray($spusArray);
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'product_id'=>$spusArray]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductHistory($product_ids['data']);
        }
        $url = url('repairTool/fixProducts', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    /**
     * 同步产品变更历史表
     */
    public function updateProductHistory($product_ids){
        ini_set('max_execution_time', '0');
        $historyModel = new ProductHistoryModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
//                $data = $historyModel->findProductHistory($product_id['_id']);
//                if(!empty($data)){
//                   continue;
//                }
//                if(isset($product_id['IsHistory']) && $product_id['IsHistory'] == 1){
//                    $IsHistory = 1;
//                }else{
//                    $IsHistory = 0;
//                }
                $ret = $historyModel->addProductHistory($product_id['_id']);
            }
        }
        pr('success');
    }

    public function fixProductsRewrittenUrl(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
//        $historyModel = new ProductHistoryModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page']]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductsRewrittenUrl($product_ids['data']);
        }
        $url = url('repairTool/fixProductsRewrittenUrl', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function updateProductsRewrittenUrl($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $regex = "/[^a-zA-Z0-9\-]/";
                $RewrittenUrl = preg_replace($regex,"",$product_id['RewrittenUrl']);
                pr($RewrittenUrl);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],['RewrittenUrl'=>$RewrittenUrl]);
                pr($ret);
            }
        }
        pr('success');
    }

    //根据Code下架产品
    public function updateProductByCode(){
        $params = request()->post();
        $productModel = new ProductModel;
        $codeArray = isset($params['code']) ? $params['code'] : array();
        if(empty($codeArray)){
            pr("empty codeArray");die;
        }
        foreach($codeArray as $key => $sku_id){
            //记录跑了的产品ID
            \think\Log::pathlog('SKUCODE = ',$sku_id,'updateProductByCode2.log');
            $updateKey = -1;
            $updateProduct = array();
            $findProudct = $productModel->getProduct(['sku_code'=>$sku_id,'status'=>[1,5],'field'=>['ProductStatus','_id','Skus._id','Skus.Code','Skus.Inventory']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //更新sku价格,查找key值
            foreach($findProudct['Skus'] as $pkey => $productSkus){
                if($sku_id == $productSkus['_id']){
                    $updateKey = $pkey;
                    $productSkus['Inventory'] = 0;
                }
                if($sku_id == $productSkus['Code']){
                    $updateKey = $pkey;
                    $productSkus['Inventory'] = 0;
                }
            }
            if($updateKey == -1){
                //记录找不到的SKU
                \think\Log::pathlog('productid = ',$sku_id,'updateProductByCode.error');
                continue;
            }
            $updateProduct['Skus.'.$updateKey.'.Inventory'] = 0;
            $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$updateProduct);
        }
        pr('success');die;
    }

    //根据Code下架产品
    public function updateProductStatus(){
        $params = request()->post();
        $productModel = new ProductModel;
        $codeArray = isset($params['code']) ? $params['code'] : array();
        if(empty($codeArray)){
            pr("empty codeArray");die;
        }
        foreach($codeArray as $key => $sku_id){
            $total = 0;
            $updateProduct = array();
            $findProudct = $productModel->getProduct(['sku_code'=>$sku_id,'status'=>[1,5],'field'=>['ProductStatus','_id','Skus._id','Skus.Code','Skus.Inventory']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //更新sku价格,查找key值
            foreach($findProudct['Skus'] as $pkey => $productSkus){
                $total = $total + $productSkus['Inventory'];
            }
            if($total == 0){
                \think\Log::pathlog('productid = ',$sku_id,'updateProductStatus.log');
                $updateProduct['ProductStatus'] = 3 ;
            }
            if(empty($updateProduct)){
                continue;
            }
            $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$updateProduct);
        }
        pr('success');die;
    }


    //修复产品首图
    public function fixProductsFirstImg(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
//        $historyModel = new ProductHistoryModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProductImg(['page'=>$param['page'],'AddTime'=>true]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductsImg($product_ids['data']);
        }
        $url = url('repairTool/fixProductsFirstImg', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function updateProductsImg($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $firstImg = '';
                if(isset($product_id['ImageSet']['ProductImg'][0]) && !empty($product_id['ImageSet']['ProductImg'][0])){
                    $firstImg = $product_id['ImageSet']['ProductImg'][0];
                }
                if(!empty($firstImg)){
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],['FirstProductImage'=>$firstImg]);
                }
            }
        }
        pr('success');
    }


    public function syncProductBulkRateFor916(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
//        $product_ids = $productModel->queryProduct916(['seller_id'=>888,'page'=>$param['page']]);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page']]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductLangs($product_ids['data']);
        }
        $url = url('RepairTool/syncProductBulkRateFor916', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    /**
     * 产品多语言没有，插入变更历史
     */
    public function updateProductLangs($product_ids){
        $time = time();
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel();
//        $productHistoryModel = new ProductHistoryModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $isTranLang = false;
                pr($product_id['_id']);
                $productLang = $productModel->getPrdouctmMultiLangs(['id'=>(int)$product_id['_id']]);
                if(empty($productLang)){
                    $isTranLang = true;
//                    $ret = $productHistoryModel->addProductHistory($product_id['_id']);
                }else{
                    //标题
                    if(isset($productLang['Title']) && !empty($productLang['Title'])){
                        if(is_array($productLang['Title'])){
                            $enLangVal = isset($productLang['Title']['en']) ? $productLang['Title']['en'] : '';
                            foreach($productLang['Title'] as $lang => $langVal){
                                if($lang != 'en'){
                                    if($langVal == $enLangVal){
                                        $isTranLang = true;
                                        break;
//                                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
                                    }
                                }
                            }
                            if(count($productLang['Title']) <= 10){
                                $isTranLang = true;
//                                $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                                continue;
                            }
                        }
                    }else{
                        $isTranLang = true;
//                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                        continue;
                    }

                    //详情
                    if(isset($productLang['Descriptions']) && !empty($productLang['Descriptions'])){
                        if(is_array($productLang['Descriptions'])){
                            $enLangVal = isset($productLang['Descriptions']['en']) ? $productLang['Descriptions']['en'] : '';
                            foreach($productLang['Descriptions'] as $lang => $langVal){
                                if($lang != 'en'){
                                    if($langVal == $enLangVal){
                                        $isTranLang = true;
                                        break;
//                                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
                                    }
                                }
                            }
                            if(count($productLang['Descriptions']) <= 10){
                                $isTranLang = true;
//                                $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                                continue;
                            }
                        }
                    }else{
                        $isTranLang = true;
//                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                        continue;
                    }
                }
                //记录需要翻译的产品ID
                if($isTranLang){
                    \think\Log::pathlog('spu:',$product_id['_id'],'spuLang.log');
                }
            }
        }
        pr('success');

    }

    /**
     * 更新
     * 店铺id 888
     * skuid 916开头
     */
    public function updateProductTime($product_ids){
        $time = time();
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $update = array();
                $update['AddTime'] = $time;
                pr($product_id['_id']);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                pr($ret);
            }
        }
        pr('success');


    }
    /**
     * 更新
     * 店铺id 888
     * skuid 916开头
     */
    public function product916($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $update = array();
                pr($product_id['_id']);
                if(!empty($product_id['Skus'])){
                    foreach($product_id['Skus'] as $key => $skus){
                        //916打头的产品数据
//                        if(substr($skus['_id'],0,3) == '916' ){
                        $bulkRatePrice = round((double)$skus['SalesPrice'] - (double)$skus['SalesPrice'] * 0.025,2);
//                            if($bulkRatePrice == $skus['BulkRateSet']['SalesPrice']){
//                                continue;
//                            }
                        $update['Skus.'.$key.'.BulkRateSet.SalesPrice'] = (double)$bulkRatePrice;
                        $update['Skus.'.$key.'.BulkRateSet.Discount'] = round(($skus['SalesPrice'] - $bulkRatePrice) / $skus['SalesPrice'],3);
//                        }
                    }
                    if(empty($update)){
                        continue;
                    }
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                    pr($ret);
//                    die;
                }

            }
        }
        pr('success');


    }


    /**
     * 同步产品批发折扣，价格
     */
    public function fixProductsBulkRatePrice(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'seller_id'=>333]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_ProductsBulkRatePrice($product_ids['data']);
        }
        $url = url('repairTool/fixProductsBulkRatePrice', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _ProductsBulkRatePrice($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $update = array();
                pr($product_id['_id']);
                //获取价格数组
                $skus = $product_id['Skus'];
                if(!empty($skus)){
                    foreach($skus as $skey => $sku){
                        if(isset($sku['SalesPrice']) && isset($sku['BulkRateSet']['Discount']) && $sku['BulkRateSet']['Discount'] == '0.05'){
                            $price = $sku['SalesPrice'] - $sku['SalesPrice'] * 0.05;
                            $update['Skus.'.$skey.'.BulkRateSet.SalesPrice'] = (double)round($price,2);
                        }else{
                            continue;
                        }
                    }
                }
                if(!empty($update)){
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                }
            }
        }
        pr('success');
    }

    public function findErpProductByClass(){
        ini_set('max_execution_time', '0');
        $baseService = new BaseService();
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        $service = new RepairService();
        $spusArray = $service->getRepairData();
        $a = array();
        $calssCount = array();
        foreach($spusArray as $val){
            $class = explode(',',$val);
            $id = $class[0];
            $cdata = $classModel->getClassDetail(["id" => (int)$class[0]]);
            if($cdata['type'] !=1){
                if(!empty($cdata['pdc_ids'])){
                    foreach($cdata['pdc_ids'] as $erp_id){
                        $erp_data = $classModel->getClassDetail(["id" => (int)$erp_id]);
                        if(!empty($erp_data)){
                            if($erp_data['level'] ==2){
                                if(!empty($calssCount[$erp_id])){
                                    //增加数量
                                    $calssCount[$erp_id]['count'] = $calssCount[$erp_id]['count'] +  $class[1];
                                    //只能增加一次
                                    break;
                                }else{
                                    $calssCount[$erp_id]['name'] = $erp_data['title_en'];
                                    $calssCount[$erp_id]['id'] = $erp_id;
                                    $calssCount[$erp_id]['count'] = $class[1];
                                    break;
                                }
                            }else{
                                if($erp_data['level'] == 1){
                                    $pid_data = $classModel->queryClass(["pid" => (int)$erp_id]);
                                    if(!empty($pid_data)){
                                        foreach($pid_data as $pidVal){
                                            $pathArray = explode('-',$pidVal['id_path']);
                                            $secLevel = $pathArray[1];
                                            if(!empty($calssCount[$secLevel])){
                                                //增加数量
                                                $calssCount[$secLevel]['count'] = $calssCount[$secLevel]['count'] +  $class[1];
                                                break;
                                            }else{
                                                $calssCount[$secLevel]['name'] = $erp_data['title_en'];
                                                $calssCount[$secLevel]['id'] = $secLevel;
                                                $calssCount[$secLevel]['count'] = $class[1];
                                                break;
                                            }
                                        }
                                    }
                                }else{
                                    $pathArray = explode('-',$erp_data['id_path']);
                                    $secLevel = isset($pathArray[1]) ? $pathArray[1] : 0;
                                    if(!empty($calssCount[$secLevel])){
                                        //增加数量
                                        $calssCount[$secLevel]['count'] = $calssCount[$secLevel]['count'] +  $class[1];
                                        break;
                                    }else{
                                        $calssCount[$secLevel]['name'] = $erp_data['title_en'];
                                        $calssCount[$secLevel]['id'] = $secLevel;
                                        $calssCount[$secLevel]['count'] = $class[1];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }else{
                $calssCount[$id]['name'] = $cdata['title_en'];
                $calssCount[$id]['id'] = $id;
                $calssCount[$id]['count'] = $class[1];
            }


//            if($data['level'] == 2){
//                $update['name1'] = $pid['title_en'];
//            }elseif($data['level'] == 3){
//                $update['name1'] = $ppid['title_en'];
//                $update['name2'] = $pid['title_en'];
//            }elseif($data['level'] == 4){
//                $update['name1'] = $pppid['title_en'];
//                $update['name2'] = $ppid['title_en'];
//                $update['name3'] = $pid['title_en'];
//            }

//            $classModel->updateClassCount(["_id" => (int)$val['_id']],$update);
//            $classArray = explode(',',$val);
//            $update = array();
//            $data = $classModel->getClassDetail(["id" => (int)$classArray[0]]);
//            if( $data['level'] != 1){
//                $path = explode('-',$data['id_path']);
//                foreach($path as $class_id){
//                    $data = $classModel->getClassCount(["_id" => (int)$class_id]);
//                    if(!empty($data)){
//                        $count = $data['count'] + $classArray[1];
//                        $classModel->updateClassCount(["_id" => (int)$class_id],['count' => (int)$count]);
//                    }else{
//                        $insert['_id'] = (int)$class_id;
//                        $insert['count'] = (int)$classArray[1];
//                        $classModel->addClassCount($insert);
//                    }
//                }
//            }
        }
        $data = array();
        $i = 0;

        //获取采购价
        foreach($calssCount as $key => $val){
            $data[$key]['id'] = $val['id'];
            $data[$key]['count'] = $val['count'];
            $data[$key]['name'] = $val['name'];
        }
        $objPHPExcel = new \PHPExcel();

        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中

        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(30);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '类别ID')
            ->setCellValue('B1', '产品数量')
            ->setCellValue('C1', '类别名称');
        $objPHPExcel->getActiveSheet()->setTitle('产品数量');
        //设置数据
        $i = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
//        $data = array_values($data);
        foreach ($data as $vo){
            $objActSheet->setCellValue('A'.$i, $vo["id"]);
            $objActSheet->setCellValue('B'.$i, $vo["count"]);
            $objActSheet->setCellValue('C'.$i, $vo["name"]);
            $i++;
        }
        // excel头参数
        $fileName = "二级类别产品数量".date('_YmdHis');
        $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        //ob_end_clean();
        header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".xls'");
        header("Content-Disposition: attachment;filename=$xlsTitle.xls");
        header('Cache-Control: max-age=0');
        //excel5为xls格式，excel2007为xlsx格式
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;

    }

    public $packingWeight = [
        37,61.5,

    ];


    public function productPackingList(){
        ini_set('max_execution_time', '0');
        $historyModel = new ProductHistoryModel();
        $productModel = new ProductModel;
        $packingList = $this->packingWeight;
        foreach($packingList as $list){
//            $update = array();
            $params = explode(',',$list);
            $product_id = isset($params[0]) ? $params[0] : 0;
//            $weight = isset($params[1]) ? $params[1] : 0;
//            $dimensions = isset($params[2]) ? $params[2] : 0;
//            $title = isset($params[3]) ? $params[3] : 0;
            if(empty($product_id)){
                continue;
            }
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['sku_search'=>$product_id,'status'=>[1,5],'field'=>['ProductStatus','_id','IsHistory']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //产品重量变更需要插入变更历史
            if(isset($findProudct['IsHistory']) && $findProudct['IsHistory'] == 1){
                $IsHistory = 1;
            }else{
                $IsHistory = 0;
            }
            $historyModel->addProductHistory($findProudct['_id'],$IsHistory);
//            if(!isset($findProudct['PackingList']) || !isset($findProudct['PackingList']['Weight'])){
//                $weight = sprintf("%01.3f",$weight/1000);
//                $update['PackingList']['Weight'] = $weight > 0.01 ? $weight : 0.01;//最小值
//                $update['PackingList']['UseCustomWeight'] = 0;
//                $update['PackingList']['CustomeWeightInfo'] = null;
//                $update['PackingList']['Dimensions'] = null;
//                $update['PackingList']['Title'] = null;
//                if(!empty($update)){
//                    pr($update);
//                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$update);
//                }
//            }
        }
        pr("done");
    }

    public function productPackingTitleList(){
        $productModel = new ProductModel;
        $packingList = $this->packingTitle;;
        foreach($packingList as $list){
            $update = array();
            $params = explode(',',$list);
            $product_id = isset($params[0]) ? $params[0] : 0;
            $title = isset($params[1]) ? $params[1] : null;
            if(empty($product_id)){
                continue;
            }
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['sku_search'=>$product_id,'status'=>[1,5],'field'=>['ProductStatus','_id','PackingList']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            if(isset($findProudct['PackingList']) && isset($findProudct['PackingList']['Title'])){
                $update['PackingList.Title'] = (string)$title;
                if(!empty($update)){
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$update);
                }
            }
        }
        pr("done");
    }

    public $productClass=[
        "2002761,371",

    ];

    /**
     * 更新产品类别
     * @var array
     */

    public function updateProductClass(){
        $ids = '2030932,2030937,2039058,2039076,2044566,2044568,2048165,2048167,2048173,2048670,2048672,2048675,2067849,2067850,2067852,2067856,2067858,2067860,2067899,2067901,2067907,2068483,2084402,2601158,2094980,2067862,2001268';
        $packingList = explode(',',$ids);
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
//        $packingList = $this->productClass;
        foreach($packingList as $list){
//            $update = array();
//            $params = explode(',',$list);
//            $product_id = isset($params[0]) ? $params[0] : 0;
//            $class_id = isset($params[1]) ? $params[1] : null;
//            if(empty($product_id)){
//                continue;
//            }
            $product_id = $list;
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['product_id'=>$product_id,'status'=>[1,5],'field'=>['ProductStatus','_id','CategoryPath']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //查询类别
//            $classData = $classModel->getClassDetail(['id' => (int)$class_id]);
//            pr($classData);
//            if(empty($classData)){
//                continue;
//            }
//            $path = isset($classData['id_path']) ? $classData['id_path'] : null;
//            if(!empty($path)){
//                $classArray = explode('-',$path);
//                $update['CategoryPath'] = $path;
//                $update['FirstCategory'] = isset($classArray[0]) ? (int)$classArray[0] : 0;
//                $update['SecondCategory'] = isset($classArray[1]) ? (int)$classArray[1] : 0;
//                $update['ThirdCategory'] = isset($classArray[2]) ? (int)$classArray[2] : 0;
//                $update['FourthCategory'] = isset($classArray[3]) ? (int)$classArray[3] : 0;
//                $update['FifthCategory'] = isset($classArray[4]) ? (int)$classArray[4] : 0;
//                pr($update);

            $update['CategoryPath'] = '10-1800044-1800057';
            $update['FirstCategory'] = 10;
            $update['SecondCategory'] = 1800044;
            $update['ThirdCategory'] = 1800057;
            $update['FourthCategory'] = 0;
            $update['FifthCategory'] = 0;
            $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$update);
//            }
        }
        pr("done");
    }



    public function fixProductsClass(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page']]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixProductsClass($product_ids['data']);
        }
        $url = url('repairTool/fixProductsClass', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixProductsClass($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $update = array();
                if(isset($product_id['CategoryPath']) && !empty($product_id['CategoryPath'])){
                    $classArray = explode('-',$product_id['CategoryPath']);
                    $update['FirstCategory'] = isset($classArray[0]) ? (int)$classArray[0] : 0;
                    $update['SecondCategory'] = isset($classArray[1]) ? (int)$classArray[1] : 0;
                    $update['ThirdCategory'] = isset($classArray[2]) ? (int)$classArray[2] : 0;
                    $update['FourthCategory'] = isset($classArray[3]) ? (int)$classArray[3] : 0;
                    $update['FifthCategory'] = isset($classArray[4]) ? (int)$classArray[4] : 0;
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                    pr($ret);
                }
            }
        }
        pr('success');
    }

    //导出没有产品的分类分类名，一级分类及分类，三级分类，四级分类
    public function findClassNotProduct(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductClassModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->paginateClass(['page'=>$param['page']]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_findClassNotProduct($product_ids['data']);
        }
        $url = url('repairTool/findClassNotProduct', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    private function _findClassNotProduct($classArray){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        if(!empty($classArray)){
            foreach($classArray as $classDetail){
//                pr($classDetail['id']);
//                if(isset($classDetail['pdc_ids']) && !empty($classDetail['pdc_ids'])){
//                    $class_ids = $classDetail['pdc_ids'];
//                    array_push($class_ids,$classDetail['id']);
//                    $class_ids = CommonLib::supportArray($class_ids);
//                }else{
//                    $class_ids = (int)$classDetail['id'];
//                }
//                pr($class_ids);
                //查找产品是否存在
//                $findProudct = $productModel->getProduct(['lastCategory'=>$class_ids,'status'=>[1,5],'field'=>['ProductStatus','_id']]);
//                pr($findProudct);
//                if(!empty($findProudct)){
//                    continue;
//                }else{
                    $classPath = array();
                    $firstClass = array();
                    $firstname = $secname = $thirdname = $fouthname = '';
                    $classPath = explode('-',$classDetail['id_path']);
                    $first = isset($classPath[0]) ? $classPath[0] : 0 ;
                    if(!empty($first)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$first]);
                        $firstname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

                    $sec = isset($classPath[1]) ? $classPath[1] : 0 ;
                    if(!empty($sec)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$sec]);
                        $secname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

                    $third = isset($classPath[2]) ? $classPath[2] : 0 ;
                    if(!empty($third)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$third]);
                        $thirdname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

//                    $fouth = isset($classPath[3]) ? $classPath[3] : 0 ;
//                    if(!empty($fouth)){
//                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$fouth]);
//                        $fouthname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
//                    }

                    //过滤特殊字符
                    $regex = "/\/|\,|\\\|\|/";
                    $classDetail['title_en'] = preg_replace($regex,"&",$classDetail['title_en']);
                    \think\Log::pathlog('class_data:',$classDetail['id'].';'.$classDetail['pid'].';'.$firstname.';'.$secname.';'.$thirdname.';','classNotProduct3.log');
//                }
            }
        }
    }


    //修复拆分产品
    public function fixSplitProducts(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'is_split'=>1]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixSplitProducts($product_ids['data']);
        }
        $url = url('repairTool/fixSplitProducts', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixSplitProducts($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $update = array();
                if(isset($product_id['AddTime']) && !empty($product_id['AddTime'])){
                    $update['AddTime'] = (int)$product_id['AddTime'];
                }
                if(isset($product_id['EditTime']) && !empty($product_id['EditTime'])){
                    $update['EditTime'] = (int)$product_id['EditTime'];
                }
                if(!empty($update)){
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                }
            }
        }
        pr('success');
    }

    //修复拆分产品
    public function productSkuList(){
        ini_set('max_execution_time', '0');
        $param = input();
        $packingList = (new RepairService())->getRepairData();
        $start = isset($param['start']) ? $param['start'] : 0;
        $output = array_slice($packingList,$start,500);
        if(empty($output)){
            pr("end");die;
        }else{
            $this->_productSkuList($output);
        }
        $url = url('repairTool/productSkuList', ['start'=>$start+500,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _productSkuList($packingList){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        foreach($packingList as $sku_id){
            if(empty($sku_id) || $sku_id < 0 ){
                continue;
            }
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['sku_search'=>$sku_id,'status'=>[1,5],'field'=>['_id','CategoryPath']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            if(isset($findProudct['CategoryPath']) && !empty($findProudct['CategoryPath'])){
                $classArray = explode('-',$findProudct['CategoryPath']);
                //末级分类
                $lastClassid = array_pop($classArray);
                //查看映射
                $lastClassArray = $classModel->getClassDetail(['id'=>(int)$lastClassid]);
                $erpClassid = 0;
                //类别映射
                if($lastClassArray['type'] == 2){
                    if(!empty($lastClassArray['pdc_ids'])){
                        $erpClassid = array_shift($lastClassArray['pdc_ids']);
                        $erpClassArray = $classModel->getClassDetail(['id'=>(int)$erpClassid]);
                        $classArray = explode('-',$erpClassArray['id_path']);
                        $fir = isset($classArray[0]) ? $classArray[0] : 0;
                        $lastClassArray = $classModel->getClassDetail(['id'=>(int)$fir]);
                        $name1 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                        $sec = isset($classArray[1]) ? $classArray[1] : 0;
                        $lastClassArray = $classModel->getClassDetail(['id'=>(int)$sec]);
                        $name2 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                        $thi = isset($classArray[2]) ? $classArray[2] : 0;
                        $lastClassArray = $classModel->getClassDetail(['id'=>(int)$thi]);
                        $name3 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                        $fou = isset($classArray[3]) ? $classArray[3] : 0;
                        $lastClassArray = $classModel->getClassDetail(['id'=>(int)$fou]);
                        $name4 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                    }
                }
                //没有映射
                if(empty($erpClassid)){
                    $classArray = explode('-',$findProudct['CategoryPath']);
                    $fir = isset($classArray[0]) ? $classArray[0] : 0;
                    $lastClassArray = $classModel->getClassDetail(['id'=>(int)$fir]);
                    $name1 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                    $sec = isset($classArray[1]) ? $classArray[1] : 0;
                    $lastClassArray = $classModel->getClassDetail(['id'=>(int)$sec]);
                    $name2 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                    $thi = isset($classArray[2]) ? $classArray[2] : 0;
                    $lastClassArray = $classModel->getClassDetail(['id'=>(int)$thi]);
                    $name3 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                    $fou = isset($classArray[3]) ? $classArray[3] : 0;
                    $lastClassArray = $classModel->getClassDetail(['id'=>(int)$fou]);
                    $name4 = isset($lastClassArray['title_en']) ? $lastClassArray['title_en'] : null;
                }
                \think\Log::pathlog('sku_id:',$sku_id.';'.$name1.';'.$name2.';'.$name3.';'.$name4,'skuerpclass.log');
//                pr('sku_id='.$sku_id.';n1='.$name1.';n2='.$name2.';n3='.$name3.';n4='.$name4.';');
            }
        }
        pr("done");
    }


    //同步缺少类别数据
    public function attribute(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductClassModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->paginateClass(['page'=>$param['page'],'page_size'=>20]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_attribute($product_ids['data']);
        }
        $url = url('repairTool/attribute', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _attribute($data){
        ini_set('max_execution_time', '0');
        $classModel = new ProductClassModel();
        $historyModel = new ProductHistoryModel();
        foreach($data as $attribute){
            pr('id = '.$attribute['_id']);
//            $historyModel->addProductAttribute($attribute['_id']);
            if(isset($attribute['attribute']) && !empty($attribute['attribute'])){
                foreach($attribute['attribute'] as $akey => $attr){
                    if(isset($attr['attribute_value']) && !empty($attr['attribute_value'])){
                        $isexist = $classModel->findAttribute($attr['id']);
                        if(empty($isexist)){
                            pr('attr = '.$attr['id']);
                            $attr['_id'] = (int)$attr['id'];
                            $attr['status'] = 100;
                            $attr['sort'] = (int)$attr['sort'];
                            $attr['attribute'] = $attr['attribute_value'];
                            unset($attr['id'],$attr['attribute_value']);
                            $ret = $classModel->insertAttribute($attr);
                            if($ret){
                                $historyModel->addProductAttribute($attr['_id']);
                            }
                        }
                    }
                }
            }
        }
        pr("done");
    }

    //翻译customValue多语言
    public function langCustomValue(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>100]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_langCustomValue($product_ids['data']);
        }
        $url = url('repairTool/langCustomValue', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _langCustomValue($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $isLang = false;
                pr($product_id['_id']);
                //判断是否有customValue
                if(!isset($product_id['Skus'])){
                    continue;
                }
                //商品SKU循环校验
                foreach($product_id['Skus'] as $skey => $sku){
                    if($isLang){
                        break;
                    }
                    //校验销售属性
                    if(isset($sku['SalesAttrs']) && !empty($sku['SalesAttrs'])){
                        foreach($sku['SalesAttrs'] as $attrKey => $attrVal){
                            $customValue = isset($attrVal['CustomValue']) ? $attrVal['CustomValue'] : null;
                            if(!empty($customValue)){
                                //需要翻译
                                $isLang = true;
                                break;
                            }
                        }
                    }
                }
                if($isLang){
                    $productModel->addTempCustomeValueLang(['_id'=>(int)$product_id['_id'],'IsSync'=>false]);
                }
            }
        }
        pr('success');
    }

    //修复描述图片地址错误
    public function descriptionsImg(){
        ini_set('max_execution_time', '0');
        $param = input();
        $service = new RepairService();
        $imgJson = json_decode($service->errorImgJson(),true);
//        $newarray = array();
//        foreach($imgJson['data']['list'] as $akey => $arr){
//            $newarray[$arr['spu']]['_id'] = $arr['spu'];
//            $newarray[$arr['spu']]['imgs'][$akey]['img'] = $arr['img'];
//            $newarray[$arr['spu']]['imgs'][$akey]['new_img'] = $arr['new_img'];
//            $newarray[$arr['spu']]['imgs'] = array_values($newarray[$arr['spu']]['imgs']);
//
//        }
        $start = isset($param['start']) ? $param['start'] : 0;
        $output = array_slice($imgJson,$start,10);
        if(empty($output)){
            pr("end");die;
        }else{
            $this->_descriptionsImg($output);
        }
        $url = url('repairTool/descriptionsImg', ['start'=>$start+10,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _descriptionsImg($data){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel();
        foreach($data as $imgs){
            pr($imgs['_id']);
            $findProduct = $productModel->getPrdouctmMultiLangsByFiled(['id'=>$imgs['_id']]);
            if(!isset($findProduct['Descriptions']) && empty($findProduct['Descriptions'])){
                continue;
            }
            $update = array();
            //多语言循环
            foreach($findProduct['Descriptions'] as $dkey => $desc){
                $descriptions = htmlspecialchars_decode($desc);
                //图片地址循环
                foreach($imgs['imgs'] as $desImg){
                    $find = strstr($descriptions,$desImg['img']);
                    if($find === false){
                        \think\Log::pathlog('spu:',$imgs['_id'].';'.$desImg['img'].';'.$desImg['new_img'],'descriptions1.log');
                    }else{
                        $descriptions = str_replace($desImg['img'],$desImg['new_img'],$descriptions);
                    }
                }
                $update[$dkey] = $descriptions;
            }
            if(empty($update)){
                continue;
            }
            $ret = $productModel->updatePrdouctmMultiLangsByWhere(['_id'=>(int)$imgs['_id']],['Descriptions'=>$update]);
            pr($ret);
        }
        pr("done");
    }

    public function descriptionsTest(){
        $productModel = new ProductModel();
        $findProduct = $productModel->getProduct(['product_id'=>2065572,'status'=>[1,5],'field'=>['_id','Descriptions']]);
        if(!isset($findProduct['Descriptions']) && empty($findProduct['Descriptions'])){
            return 0;
        }
        $descriptions = htmlspecialchars_decode($findProduct['Descriptions']);
        $append =  '<table class="t_info" style="width:960px;">
	<tbody>
		<tr>
			<th class="title" colspan="2">
				<span style="font-size:12px;">Packing List</span>
			</th>
		</tr>
		<tr>
			<td align="left;">
				<span class="packingTableTitle" style="font-size:12px;">Product</span>
			</td>
		</tr>
		<tr>
		</tr>
	</tbody>
</table><br />';
        $descriptions = $descriptions.$append;

        $ret = $productModel->updateProductSkuPrice(['_id'=>(int)2065572],['Descriptions'=>$descriptions]);
        pr($ret);
        pr("done");die;
    }

    //PackingList.Title多语言翻译
    public function packingListTitleLang(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>100]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_packingListTitleLang($product_ids['data']);
        }
        $url = url('repairTool/packingListTitleLang', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _packingListTitleLang($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $productData){
                if(isset($productData['PackingList']['Title']) && !empty($productData['PackingList']['Title'])){
                    pr($productData['_id']);
                    $ret = $productModel->addTempPackingTitleLang(['_id'=>(int)$productData['_id'],'IsSync'=>false,'Title'=>['en'=>$productData['PackingList']['Title']]]);
                    pr($ret);
                }
            }
        }
        pr('success');
    }

    //修复OptionId为字符串
    public function findOptionId(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>100]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_findOptionId($product_ids['data']);
        }
        $url = url('repairTool/findOptionId', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _findOptionId($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $productData){
                $updateProduct = array();
                pr($productData['_id']);
                if(isset($productData['Skus']) && !empty($productData['Skus'])) {
                    foreach ($productData['Skus'] as $pkey => $productSkus) {
                        if (isset($productSkus['SalesAttrs']) && !empty($productSkus['SalesAttrs'])) {
                            foreach ($productSkus['SalesAttrs'] as $akey => $productAttr) {
                                $updateProduct['Skus.' . $pkey . '.SalesAttrs.' . $akey . '._id'] = (string)$productAttr['_id'];
                                $updateProduct['Skus.' . $pkey . '.SalesAttrs.' . $akey . '.OptionId'] = (string)$productAttr['OptionId'];
                            }
                        }

                    }
                }
                if(empty($updateProduct)){
                    continue;
                }
                pr($updateProduct);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$productData['_id']],$updateProduct);
            }
        }
        pr('success');
    }


    //追加产品描述
    public function productpackingtitlelang(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->product_packing_title_list(['page'=>$param['page']]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_productpackingtitlelang($product_ids['data']);
        }
        $url = url('repairTool/productpackingtitlelang', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _productpackingtitlelang($product_ids){
        ini_set('max_execution_time', '0');
        $service = new RepairService();
        $packingLang = $service->getPackingListLang();
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $productData){
                $isupdate = 0;
                pr($productData['_id']);
                if(!isset($productData['Title']) || empty($productData['Title'])){
                    continue;
                }
                //产品表
                $findProduct = $productModel->getProduct(['product_id'=>(int)$productData['_id'],'status'=>[1,5],'field'=>['_id','Descriptions']]);
                if(!isset($findProduct['Descriptions']) || empty($findProduct['Descriptions'])){
                    continue;
                }
                $descriptions = htmlspecialchars_decode($findProduct['Descriptions']);

                //产品多语言表
                $prdouctmMultiLangs = $productModel->getPrdouctmMultiLangsByFiled(['id'=>(int)$productData['_id']]);
                if(!isset($prdouctmMultiLangs['Descriptions']) || empty($prdouctmMultiLangs['Descriptions'])){
                    continue;
                }

                foreach ($packingLang as $pkey => $title) {
                    //dx_product_packing_title_temp的翻译数据
                    $temp_packing_lang = isset($productData['Title'][$pkey]) ? $productData['Title'][$pkey] : null;
                    if(empty($temp_packing_lang)){
                        continue;
                    }
                    $append =  '<table class="t_info" style="width:960px;"><tbody><tr><th class="title" colspan="2"><span style="font-size:12px;">'.$title.'</span></th></tr><tr><td align="left;"><span class="packingTableTitle" style="font-size:12px;">'.$temp_packing_lang.'</span></td></tr><tr></tr></tbody></table><br />';

                    //英文需要更新产品的内容
                    if($pkey == 'en'){
                        //防止重复更新，对比一次
                        if(strpos($descriptions,$append) === false){
                            $descriptions = $descriptions.$append;
                        }
                    }
                    //需要追加dx_product_multiLangs的翻译数据
                    $langData = isset($prdouctmMultiLangs['Descriptions'][$pkey]) ? $prdouctmMultiLangs['Descriptions'][$pkey] : null;
                    if(empty($langData)){
                        continue;
                    }
                    if(strpos($langData,$append) === false){
                        $langData = $langData.$append;
                    }
                    $prdouctmMultiLangs['Descriptions'][$pkey] = $langData;

                }
                //已经更新过的
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$productData['_id']],['Descriptions'=>$descriptions]);
                pr($ret);
                $ret = $productModel->updatePrdouctmMultiLangsByWhere(['_id'=>(int)$productData['_id']],['Descriptions'=>$prdouctmMultiLangs['Descriptions']]);
                pr($ret);
            }
        }
        pr('success');
    }

    //维护dx_product_skus表
    public function fixProductSkus(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>100]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixProductSkus($product_ids['data']);
        }
        $url = url('repairTool/fixProductSkus', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixProductSkus($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $productData){
                $addProduct = array();
                if(isset($productData['Skus']) && !empty($productData['Skus'])) {
                    $skuArray = CommonLib::getColumn('_id',$productData['Skus']);
                    $addProduct['Skus'] = $skuArray;
                }
                if(empty($addProduct)){
                    continue;
                }
                $addProduct['_id'] = (int)$productData['_id'];
                $addProduct['AddTime'] = date('Y-m-d H:i:s',time());
                $findData = $productModel->find_product_skus($productData['_id']);
                if(!empty($findData)){
                    continue;
                }
                $ret = $productModel->add_product_skus($addProduct);
            }
        }
        pr('success');
    }

    //修复OptionId为字符串
    public function findSkusId(){
        ini_set('max_execution_time', '0');
        $param = input();
        $service = new RepairService();
        $spusArray = $service->getRepairData();
        $spusArray = CommonLib::supportArray($spusArray);
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'product_id'=>$spusArray]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_findSkusId($product_ids['data']);
        }
        $url = url('repairTool/findSkusId', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _findSkusId($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $productData){
                $updateProduct = array();
                pr($productData['_id']);
                if(isset($productData['Skus']) && !empty($productData['Skus'])) {
                    foreach ($productData['Skus'] as $pkey => $productSkus) {
                        $updateProduct['Skus.' . $pkey .'._id'] = (int)$productSkus['_id'];
                    }
                }
                if(empty($updateProduct)){
                    continue;
                }
                pr($updateProduct);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$productData['_id']],$updateProduct);
            }
        }
        pr('success');
    }

    public function fixProductsTitle(){
        ini_set('max_execution_time', '0');
        $param = input();
        $spus = '2000081,2000083,2000117,2000131,2000173,2000208,2000449,2000452,2000583,2000665,2000689,2000975,2001369,2001373,2001379,2001400,2001413,2001455,2001509,2001540,2001541,2001555,2001563,2001832,2001841,2001878,2001898,2001929,2001938,2001943,2001945,2001960,2001966,2002002,2002025,2002318,2002324,2002348,2002985,2003000,2003057,2003097,2003176,2003432,2003462,2003499,2003554,2034155,2034166,2035505,2036418,2036513,2037901,2037903,2038073,2038119,2038971,2039243,2039505,2039509,2039542,2039561,2039563,2039609,2039615,2039618,2039623,2040117,2040129,2040548,2040598,2040872,2041230,2041590,2041602,2041753,2041811,2042762,2042848,2043109,2043114,2043342,2043358,2043363,2043397,2043458,2043463,2043467,2043469,2043484,2043488,2043506,2043517,2043530,2043540,2043560,2043572,2043581,2043598,2043628,2043631,2043654,2043657,2043864,2043868,2044000,2044007,2044008,2044114,2044417,2044719,2044721,2044786,2044807,2044964,2045454,2045463,2045466,2045498,2045529,2045542,2045564,2045569,2045673,2045676,2045684,2045695,2045917,2045922,2045926,2045937,2045939,2045948,2045951,2045955,2045958,2045961,2045968,2045977,2045989,2045997,2046094,2046096,2046099,2046105,2046165,2046212,2046271,2046311,2046597,2046601,2046603,2046613,2046618,2046620,2046623,2046635,2046681,2046683,2046689,2046695,2046698,2046841,2046986,2047424,2047427,2047442,2047473,2047480,2047486,2047576,2047619,2047631,2047647,2047929,2047941,2047960,2047968,2047970,2047976,2047994,2048075,2048079,2048147,2048154,2048255,2048265,2048287,2048296,2048298,2048313,2048316,2048321,2048798,2048806,2048814,2048881,2048884,2048888,2048984,2048999,2049017,2049176,2049186,2049189,2049192,2049194,2049202,2049204,2049210,2049213,2049218,2049236,2049242,2049246,2049248,2049269,2049271,2049302,2049341,2049373,2049398,2049403,2049447,2049458,2049461,2049473,2049483,2049489,2049492,2049565,2049582,2049702,2049733,2049736,2049816,2049865,2049868,2049881,2049892,2049907,2049924,2050096,2050176,2050263,2050275,2050288,2050290,2050462,2050470,2050589,2050608,2050683,2050703,2050788,2050818,2084361,2084367,2084377';
        $spus = explode(',',$spus);
        $products = CommonLib::supportArray($spus);
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>100,'product_id'=>$products]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixProductsTitle($product_ids['data']);
        }
        $url = url('repairTool/fixProductsTitle', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixProductsTitle($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                //防止重复更新，对比一次
                if(strpos($product_id['Title'],"\\'") !== false){
                    pr($product_id['_id']);
                    $product_id['Title'] = str_replace("\\'","'",$product_id['Title']);
                    //已经更新过的
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],['Title'=>$product_id['Title']]);
                    pr($ret);
                }
            }
        }
        pr('success');
    }

    //修复国家价格的最低最高价格
    public function fixProductCountryPrice(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductCountryModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->paginateCountryProduct(['page'=>$param['page'],'page_size'=>20]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixProductCountryPrice($product_ids['data']);
        }
        $url = url('repairTool/fixProductCountryPrice', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixProductCountryPrice($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        $countryModel = new ProductCountryModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                //查询当前的产品
                $findProduct = $productModel->getProduct(['product_id'=>(int)$product_id['Spu'],'field'=>['_id','Skus.SalesPrice','Skus._id','Skus.Code']]);
                if(empty($findProduct)){
                    continue;
                }
                if(count($findProduct['Skus']) == count($product_id['Skus'])){
                    pr($product_id['Spu']);
                    pr($product_id['Country']);
                    //原产品中最低价，最高价
                    $newPriceArray = CommonLib::getColumn('SalesPrice',$product_id['Skus']);
                    $updateProduct['LowPrice'] = (double)min($newPriceArray);
                    $updateProduct['HightPrice'] = (double)max($newPriceArray);
                    $ret = $countryModel->updateCountryProductSkuPrice(['Spu'=>(int)$product_id['Spu'],'Country'=>$product_id['Country']],$updateProduct);
                    pr($ret);
                }
            }
        }
        pr('success');
    }



    //修复产品描述里面有字符\\'s的
    public function fixDescriptions(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>50]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixDescriptions($product_ids['data']);
        }
        $url = url('repairTool/fixDescriptions', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixDescriptions($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $productData){
                pr($productData['_id']);
                if(!isset($productData['Descriptions']) || empty($productData['Descriptions'])){
                    continue;
                }
                $descriptions = htmlspecialchars_decode($productData['Descriptions']);
                $find1 = "\\'";//1个\
                $find2 = "\\\'";//2个\
                $find3 = "\\\\\'";//3个\
                $findArray = [$find3,$find2,$find1];
                //因为有些产品有1个\，有些产品3个\，所以只能数组循环
                foreach($findArray as $findVal){
                    if(strpos($descriptions,$findVal) === false){
                        continue;
                    }else{
                        pr($productData['_id']);
                        $descriptions = str_replace($findVal,"'",$descriptions);
                    }
                }

                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$productData['_id']],['Descriptions'=>$descriptions]);
                pr($ret);

                //产品多语言表
                $prdouctmMultiLangs = $productModel->getPrdouctmMultiLangsByFiled(['id'=>(int)$productData['_id']]);
                if(!isset($prdouctmMultiLangs['Descriptions']) || empty($prdouctmMultiLangs['Descriptions'])){
                    continue;
                }
                foreach ($prdouctmMultiLangs['Descriptions'] as $pkey => $descriptions) {
                    if(empty($descriptions)){
                        continue;
                    }
                    foreach($findArray as $findVal){
                        if(strpos($descriptions,$findVal) === false){
                            continue;
                        }else{
                            $descriptions = str_replace($findVal,"'",$descriptions);
                        }
                    }
                    $prdouctmMultiLangs['Descriptions'][$pkey] = $descriptions;
                }
                $ret = $productModel->updatePrdouctmMultiLangsByWhere(['_id'=>(int)$productData['_id']],['Descriptions'=>$prdouctmMultiLangs['Descriptions']]);
                pr($ret);
            }
        }
        pr('success');
    }


    //初始化市场价字段
    public function addListPrice(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>500]);

        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_addListPrice($product_ids['data']);
        }
        $url = url('repairTool/addListPrice', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _addListPrice($products){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
//        $listPriceDiscount = (new SysConfigModel())->getSysCofig('ListPriceDiscount');
//        $randData = json_decode($listPriceDiscount['ConfigValue'],true);
        if(!empty($products)){
            foreach($products as $productData){
                pr($productData['_id']);
//                pr($productData['PackingList']['Title']);
                //需要解析 add by zhongning 20190807
                if(!empty($productData['PackingList']['Title'])) {
                    $Title = htmlspecialchars_decode($productData['PackingList']['Title']);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$productData['_id']],['PackingList.Title' => $Title]);
                    pr($ret);
                }

                /*
                $update = array();
                $update['LowListPrice'] = 0;
                $update['HighListPrice'] = 0;
                $randkey = array_rand($randData);
                pr($randData[$randkey]);
                if($randData[$randkey] != 0) {
                    if (!empty($productData['LowPrice'])) {
                        $update['LowListPrice'] = (double)round($productData['LowPrice'] / (1 - $randData[$randkey]), 2);
                    }
                    if (!empty($productData['HightPrice'])) {
                        $update['HighListPrice'] = (double)round($productData['HightPrice'] / (1 - $randData[$randkey]), 2);
                    }
                    //SKU
                    if(isset($productData['Skus']) && !empty($productData['Skus'])) {
                        foreach ($productData['Skus'] as $pkey => $productSkus) {
                            $update['Skus.' . $pkey .'._id'] = (int)$productSkus['_id'];
                            $update['Skus.' . $pkey .'.ListPrice'] = (double)round($productSkus['SalesPrice'] / (1 - $randData[$randkey]), 2);
                        }
                    }
                }
                $update['ListPriceDiscount'] = (double)$randData[$randkey];

                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$productData['_id']],$update);
                pr($ret);
                */
            }
        }
        pr('success');
    }

    /**
     * 0.99数据初始化
     */
    public function initUnderFivePage(){
        //产品表查询0-0.99价格以下的
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'page_size'=>100]);
//        pr($product_ids);die;

        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_initUnderFivePage($product_ids['data']);
        }
        $url = url('repairTool/initUnderFivePage', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _initUnderFivePage($products){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        $focusCountry = (new RegionModel())->getFocusCountry();
        $extendModel = new ProductExtendModel();
        if(empty($focusCountry)){
            pr('重点国家为空');die;
        }
        $time = time();
        if(!empty($products)){
            foreach($products as $productData){
                //重点国家循环
                foreach($focusCountry as $country){
                    //查询国家产品表的价格
                    $countryData = $productModel->getProductRegionPrice($productData['_id'],$country['Code']);
                    if(!empty($countryData)){
                        $type = $this->getPriceType($countryData);
                    } else{
                        //没有国家价格，如果这个产品默认价格低于5美金，加入当前国家
                        $type = $this->getPriceType($productData);
                    }
                    if($type != 0){
                        $insert = array();
                        $insert['Country'] = $country['Code'];
                        $insert['Type'] = $type;
                        $data  = $extendModel->find(['type' => $type,'country' => $country['Code']]);
                        if(empty($data)){
                            //查找其他金额范围是否有这个产品（比如这个产品之前是1.2元，现在变成了2.2元，那么1.2元的位置要删除这个产品）
                            $data  = $extendModel->find(['country' => $country['Code'],'product_id' => $productData['_id']]);
                            if(!empty($data['Spus'])){
                                $update = array();
                                //删除当前位置的产品（比如删除1.2元的位置）
                                $update['Spus'] = array_merge(array_diff($data['Spus'], array($productData['_id'])));
                                $ret = $extendModel->upd(['type' => (int)$data['Type'],'country' => $country['Code']],$update);
                            }
                            //比如新增2.2元所在的位置
                            $insert['AddTime'] = $time;
                            $insert['Spus'] = array();
                            array_push($insert['Spus'],(int)$productData['_id']);
                            $ret = $extendModel->add($insert);
                        }else{
                            //查找其他金额范围是否有这个产品（比如这个产品之前是1.2元，现在变成了2.2元，那么1.2元的位置要删除这个产品）
                            $otherTypeData  = $extendModel->find(['country' => $country['Code'],'product_id' => $productData['_id']]);
                            if(isset($otherTypeData['Type']) && $otherTypeData['Type'] != $type){
                                $update = array();
                                //删除当前位置的产品
                                $update['Spus'] = array_merge(array_diff($otherTypeData['Spus'], array($productData['_id'])));
                                $ret = $extendModel->upd(['type' => (int)$otherTypeData['Type'],'country' => $country['Code']],$update);
                            }

                            //如果这个产品在当前位置了，那么继续循环
                            if(in_array($productData['_id'],$data['Spus'])){ continue; }
                            $update = array();
                            $update['UpdateTime'] = $time;
                            $update['Spus'] = $data['Spus'];
                            array_push($update['Spus'],(int)$productData['_id']);
                            $ret = $extendModel->upd(['type' => $type,'country' => $country['Code']],$update);
                        }
                    }else{
                        //如果是1元，升到了6元，那么要删除之前1元的位置
                        $otherTypeData  = $extendModel->find(['country' => $country['Code'],'product_id' => $productData['_id']]);
                        if(!empty($otherTypeData['Spus'])){
                            $update = array();
                            //删除当前位置的产品
                            $update['Spus'] = array_merge(array_diff($otherTypeData['Spus'], array($productData['_id'])));
                            $ret = $extendModel->upd(['type' => (int)$otherTypeData['Type'],'country' => $country['Code']],$update);
                        }
                    }
                }
                //判断默认价格，加入其它国家价格表
                $type = $this->getPriceType($productData);
                if($type != 0){
                    $insert = array();
                    $insert['Country'] = 'Other';
                    $insert['Type'] = $type;
                    $data  = $extendModel->find(['type' => $type,'country' => 'Other']);
                    if(empty($data)){
                        //查找是否有这个产品,金额范围是否一致
                        $data  = $extendModel->find(['country' => 'Other','product_id' => $productData['_id']]);
                        if(!empty($data['Spus'])){
                            $update = array();
                            //删除当前位置的产品
                            $update['Spus'] = array_merge(array_diff($data['Spus'], array($productData['_id'])));
                            $ret = $extendModel->upd(['type' => (int)$data['Type'],'country' => 'Other'],$update);
                        }
                        $insert['AddTime'] = $time;
                        $insert['Spus'] = array();
                        array_push($insert['Spus'],(int)$productData['_id']);
                        $ret = $extendModel->add($insert);
                    }else{

                        //查找是否有这个产品,金额范围是否一致
                        $otherTypeData  = $extendModel->find(['country' => 'Other','product_id' => $productData['_id']]);
                        if(isset($otherTypeData['Type']) && $otherTypeData['Type'] != $type){
                            $update = array();
                            //删除当前位置的产品
                            $update['Spus'] = array_merge(array_diff($otherTypeData['Spus'], array($productData['_id'])));
                            $ret = $extendModel->upd(['type' => (int)$otherTypeData['Type'],'country' => 'Other'],$update);
                        }

                        if(in_array($productData['_id'],$data['Spus'])) { continue;}

                        $update = array();
                        $update['UpdateTime'] = $time;
                        $update['Spus'] = $data['Spus'];
                        array_push($update['Spus'],(int)$productData['_id']);
                        $ret = $extendModel->upd(['type' => $type,'country' => 'Other'],$update);
                    }
                }else{
                    //如果是1元，升到了6元，那么要删除之前1元的位置
                    $otherTypeData  = $extendModel->find(['country' => 'Other','product_id' => $productData['_id']]);
                    if(!empty($otherTypeData['Spus'])){
                        $update = array();
                        //删除当前位置的产品
                        $update['Spus'] = array_merge(array_diff($otherTypeData['Spus'], array($productData['_id'])));
                        $ret = $extendModel->upd(['type' => (int)$otherTypeData['Type'],'country' => 'Other'],$update);
                    }
                }
            }
        }
        pr('success');
    }
    private function getPriceType($countryData){
        $type = 0;
        if($countryData['LowPrice'] > 0 && $countryData['LowPrice'] <= 0.99){
            $type = 1;
        }elseif($countryData['LowPrice'] > 0.99 && $countryData['LowPrice'] <= 1.99){
            $type = 2;
        }elseif($countryData['LowPrice'] > 1.99 && $countryData['LowPrice'] <= 2.99){
            $type = 3;
        }elseif($countryData['LowPrice'] > 2.99 && $countryData['LowPrice'] <= 3.99){
            $type = 4;
        }elseif($countryData['LowPrice'] > 3.99 && $countryData['LowPrice'] <= 4.99){
            $type = 5;
        }
        return $type;
    }

    /**
     * 删除无用运费数据
     */
    public function deleteShippingData(){
        ini_set('max_execution_time', '0');
        $param = input();
        $shippingCostModel = new ShippingCostModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $shippingCostModel->paginateShipping(['page'=>$param['page'],'page_size'=>200]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_deleteShippingData($product_ids['data']);
        }
        $url = url('repairTool/deleteShippingData', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _deleteShippingData($products){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        $shippingCostModel = new ShippingCostModel();
        if(!empty($products)){
            foreach($products as $productData){
                //查询当前的产品
                $findProduct = $productModel->getProduct(['product_id'=>(int)$productData['ProductId'],'status'=>[1,5],'field'=>['_id']]);
                if(empty($findProduct)){
                    //删除运费
                    $ret = $shippingCostModel->delshippingid($productData['_id']);
//                    pr($ret);
                }
            }
        }
        pr('success');
    }

    public function exportGtins(){
        ini_set('max_execution_time', '0');
        $spusArray = (new RepairService())->getRepairData();
        $baseService = new BaseService();
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        $data = array();
        $calssCount = array();
        foreach($spusArray as $product_id){
            $region = array();
            $findProudct = $productModel->getProduct(['product_id'=>(int)$product_id,'status'=>1,'field'=>['_id','RewrittenUrl','Title','Descriptions','Gtins','FirstProductImage','LowPrice','FirstCategory',
                'SecondCategory']]);
            $data[$product_id]['SPU'] = $findProudct['_id'];
            $data[$product_id]['ProductName'] = $findProudct['Title'];
            $data[$product_id]['ProductURL'] = 'https://dx.com/p/'.$findProudct['RewrittenUrl'].'-'.$findProudct['_id'];
            if(!empty($findProudct)){
                //查询国家价格
                $region = $baseService->getProductRegionPrice($product_id,'NL');
            }
            $data[$product_id]['ProductPrice'] = !empty($region['LowPrice']) ? $region['LowPrice'] : $findProudct['LowPrice'];

            $data[$product_id]['ImageURL_large'] = !empty($findProudct['FirstProductImage']) ? 'http://img.dxcdn.com'.$findProudct['FirstProductImage'] : '';
            $data[$product_id]['ImageURL_small'] = '';
            if(!empty($findProudct['FirstProductImage'])){
                $img = explode('.',$findProudct['FirstProductImage']);
                $data[$product_id]['ImageURL_small'] = 'http://img.dxcdn.com'.$img[0].'_210x210.'.$img[1];
            }
            $data[$product_id]['Description'] = !empty($findProudct['Descriptions']) ? $findProudct['Descriptions'] : '';
            $classDate = $classModel->getClassDetail(['id' => (int)$findProudct['FirstCategory']]);
            $data[$product_id]['Category'] = !empty($classDate['title_en']) ? $classDate['title_en'] : '';
            $classDate = $classModel->getClassDetail(['id' => (int)$findProudct['SecondCategory']]);
            $data[$product_id]['Subcategory'] = !empty($classDate['title_en']) ? $classDate['title_en'] : '';
            $data[$product_id]['GTIN'] = '';
            if(!empty($findProudct['Gtins'])){
                foreach($findProudct['Gtins'] as $val){
                    if(!empty($val['Value'])){
                        $data[$product_id]['GTIN'] = $val['Value'];
                    }
                }
            }
        }
        $objPHPExcel = new \PHPExcel();

        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中

        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(30);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'SPU')
            ->setCellValue('B1', 'ProductName')
            ->setCellValue('C1', 'ProductURL')
            ->setCellValue('D1', 'ProductPrice')
            ->setCellValue('E1', 'ImageURL_large')
            ->setCellValue('F1', 'ImageURL_small')
            ->setCellValue('G1', 'Description')
            ->setCellValue('H1', 'Category')
            ->setCellValue('I1', 'Subcategory')
            ->setCellValue('J1', 'GTIN');
        $objPHPExcel->getActiveSheet()->setTitle('产品');
        //设置数据
        $i = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        $data = array_values($data);
        foreach ($data as $vo){
            $objActSheet->setCellValue('A'.$i, $vo["SPU"]);
            $objActSheet->setCellValue('B'.$i, $vo["ProductName"]);
            $objActSheet->setCellValue('C'.$i, $vo["ProductURL"]);
            $objActSheet->setCellValue('D'.$i, $vo["ProductPrice"]);
            $objActSheet->setCellValue('E'.$i, $vo["ImageURL_large"]);
            $objActSheet->setCellValue('F'.$i, $vo["ImageURL_small"]);
            $objActSheet->setCellValue('G'.$i, $vo["Description"]);
            $objActSheet->setCellValue('H'.$i, $vo["Category"]);
            $objActSheet->setCellValue('I'.$i, $vo["Subcategory"]);
            $objActSheet->setCellValue('J'.$i, $vo["GTIN"]);
            $i++;
        }
        // excel头参数
        $fileName = "产品数据".date('_YmdHis');
        $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        //ob_end_clean();
        header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".xls'");
        header("Content-Disposition: attachment;filename=$xlsTitle.xls");
        header('Cache-Control: max-age=0');
        //excel5为xls格式，excel2007为xlsx格式
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;

    }


    public function exportOldMVP(){
        ini_set('max_execution_time', '0');
        $baseService = new BaseService();
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        $data = array();
        $spusArray = $productModel->getProductByIDs(['status'=>1,'is_mvp'=>1]);
        foreach($spusArray as $findProudct){
            $product_id = $findProudct['_id'];
            $data[$product_id]['SPU'] = $findProudct['_id'];
            $data[$product_id]['SalesCounts'] = !empty($findProudct['SalesCounts']) ? $findProudct['SalesCounts'] : 0;
        }
        $objPHPExcel = new \PHPExcel();

        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中

        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(30);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'SPU')
            ->setCellValue('B1', 'SalesCounts');

        $objPHPExcel->getActiveSheet()->setTitle('旧MVP数据');
        //设置数据
        $i = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        $data = array_values($data);
        foreach ($data as $vo){
            $objActSheet->setCellValue('A'.$i, $vo["SPU"]);
            $objActSheet->setCellValue('B'.$i, $vo["SalesCounts"]);
            $i++;
        }
        // excel头参数
        $fileName = "旧MVP数据".date('_YmdHis');
        $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        //ob_end_clean();
        header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".xls'");
        header("Content-Disposition: attachment;filename=$xlsTitle.xls");
        header('Cache-Control: max-age=0');
        //excel5为xls格式，excel2007为xlsx格式
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
