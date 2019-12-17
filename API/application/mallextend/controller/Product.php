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
use app\mallextend\model\ProductBrandModel;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductCountryModel;
use app\mallextend\model\ProductHistoryModel;
use app\mallextend\model\ProductModel;
use app\mallextend\services\BaseService;
use app\mallextend\services\ProductService;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * 产品接口
 * Class ProductCategory
 * @author zhi gong 2018/3/22
 * @package app\seller\controller
 */
class Product extends Base
{
    public $productService;
    public $productModel;
    public $redis;
    private $classModel;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->productModel = new ProductModel();
        $this->redis = new RedisClusterBase();
        $this->classModel = new ProductClassModel();
    }

	/**
	 * 新增商品接口 -- seller平台使用
	 * @param input json;
	 * @return json
	 */
	public function addProduct(){
        try{
            $paramData = request()->post();
            //参数校验
            $validate = $this->validate($paramData,(new CreateProductParams())->rules());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }
            //是否是来至拆分产品：1-是，0-不是
            $is_split = isset($paramData['is_split'])?$paramData['is_split']:0;
            $flag = false;
            $shop = $this->getSelfSupport();
            if(in_array($paramData['StoreID'],$shop) && $is_split != 1){
                $paramData['IsSelfSupport'] = 1;
                $flag = true;
            }
            //商品SKU循环校验
            foreach($paramData['Skus'] as $sku){
                $validate = $this->validate($sku,(new CreateProductSkuParams())->rules());
                if(true !== $validate){
                    return apiReturn(['code'=>1002, 'msg'=>$validate]);
                }
                //判断是否是自营，code要唯一
                if($flag){
                    $validate = $this->validate(['Code'=>$sku['Code']],(new CreateProductSkuParams())->Coderules());
                    if(true !== $validate){
                        return apiReturn(['code'=>1002, 'msg'=>$validate]);
                    }
                }
            }
            //判断自营店铺，code的唯一性
            if($flag){
                $seller_id = CommonLib::supportArray($shop);
                $code = CommonLib::getColumn('Code',$paramData['Skus']);
                $exist = $this->productModel->getCode(['seller_id'=>$seller_id,'Code'=>$code]);
                if(!empty($exist)){
                    return apiReturn(['code'=>1002, 'msg'=>'商品编码重复']);
                }
            }
		    //数据开始插入.
            $data = $this->productService->addProduct($paramData);

            return apiReturn(['code'=>200, 'data'=>['id'=>$data]]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
	}

    /**
     * 产品修改
     * @return mixed
     */
    public function update(){
        try{
            $paramData = request()->post();
            //参数校验
            $validate = $this->validate($paramData,(new CreateProductParams())->updateProductRule());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $flag = false;
            if(isset($paramData['Skus'])){
                //获取自营店铺
                $shop = $this->getSelfSupport();
                if(in_array($paramData['StoreID'],$shop)){
                    $paramData['IsSelfSupport'] = 1;
                    $flag = true;
                }
                //如果是seller平台-产品管理列表页修改库存，则不需要校验code唯一性，因为没有涉及到code的修改，只涉及到库存的修改
                if (isset($paramData['InventoryEditorFromSeller']) && $paramData['InventoryEditorFromSeller'] == 1){
                    $flag = false;
                }

                //商品SKU循环校验
                foreach($paramData['Skus'] as $key => $sku){
                    //批发价格处理
//                    if(isset($sku['BulkRateSet']['Discount']) && !empty($sku['BulkRateSet']['Discount'])){
//                        $paramData['Skus'][$key]['BulkRateSet']['Discount'] = $sku['BulkRateSet']['Discount'] / 100;
//                    }

                    $validate = $this->validate($sku,(new CreateProductSkuParams())->rules());
                    if(true !== $validate){
                        return apiReturn(['code'=>1002, 'msg'=>$validate]);
                    }
                    //判断是否是自营，code要唯一
                    if($flag){
                        $validate = $this->validate(['Code'=>$sku['Code']],(new CreateProductSkuParams())->Coderules());
                        if(true !== $validate){
                            return apiReturn(['code'=>1002, 'msg'=>$validate]);
                        }
                    }
                }
                //判断自营店铺，code的唯一性
                if($flag){
                    $seller_id = CommonLib::supportArray($shop);
                    $code = CommonLib::getColumn('Code',$paramData['Skus']);
                    $exist = $this->productModel->getCode(['seller_id'=>$seller_id,'Code'=>$code,'product_id'=>$paramData['id']]);
                    if(!empty($exist)){
                        return apiReturn(['code'=>1002, 'msg'=>'商品编码重复']);
                    }
                }
            }

            //清除缓存
            CommonLib::rmProductCache($paramData['id']);
            //数据开始插入.
            return $this->productService->updateProduct($paramData);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据StoreID更新产品联盟佣金数据
     * @return mixed
     */
    public function updateCommission(){
        $paramData = request()->post();
        //参数校验
        foreach ($paramData as $info){
            $validate = $this->validate($info,(new CreateProductParams())->updateCommissionRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        //数据更新
        try{
            foreach ($paramData as $info){
                $up_data = [
                    'CommissionType'=>$info['CommissionType'],
                    'Commission'=>$info['Commission'],
                ];
                $res = $this->productModel->updateProductCommission($info['StoreID'], $up_data);
                if (!$res){
                    return apiReturn(['code'=>1002, 'msg'=>'更新失败']);
                }
            }
            return apiReturn(['code'=>200, 'data'=>'success']);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据一级分类更新产品联盟佣金数据
     * @return mixed
     */
    public function updateCommissionByFirstCategory(){
        $paramData = request()->post();
        //参数校验
        foreach ($paramData as $info){
            $validate = $this->validate($info,(new CreateProductParams())->updateCommissionByFirstCategoryRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        //数据更新
        try{
            foreach ($paramData as $info){
                $up_data = [
                    'CommissionType'=>$info['CommissionType'],
                    'Commission'=>$info['Commission'],
                ];
                $SecondClassIds = isset($info['SecondClassIds'])?$info['SecondClassIds']:'';
                $res = $this->productModel->updateProductCommissionByFirstCategory($info['StoreID'], $info['FirstCategory'], $up_data, $SecondClassIds);
                //不用判断，因为会存在，佣金不发生改变的情况 tinghu.liu 20190801
//                if (!$res){
//                    return apiReturn(['code'=>1002, 'msg'=>'更新失败']);
//                }
            }
            return apiReturn(['code'=>200, 'data'=>'success']);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据二级分类更新产品联盟佣金数据
     * @return mixed
     */
    public function updateCommissionBySecondCategory(){
        $paramData = request()->post();
        //参数校验
        foreach ($paramData as $info){
            $validate = $this->validate($info,(new CreateProductParams())->updateCommissionBySecondCategoryRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        //数据更新
        try{
            foreach ($paramData as $info){
                $up_data = [
                    'CommissionType'=>$info['CommissionType'],
                    'Commission'=>$info['Commission'],
                ];
                $res = $this->productModel->updateProductCommissionBySecondCategory($info['StoreID'], $info['FirstCategory'],  $info['SecondCategory'], $up_data);
                //不用判断，因为会存在，佣金不发生改变的情况 tinghu.liu 20190801
//                if (!$res){
//                    return apiReturn(['code'=>1002, 'msg'=>'更新失败']);
//                }
            }
            return apiReturn(['code'=>200, 'data'=>'success']);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取产品列表
     */
    public function lists(){
        try{
            $paramData = request()->post();

            $products = $this->productModel->productLists($paramData);
            return apiReturn(['code'=>200, 'data'=>$products]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 根据产品ID，获取产品详情
     * @return mixed
     */
    public function getProduct(){
        try{
            $paramData = request()->post();

//            if (!isset($paramData['product_id'])) {
//                return apiReturn(['code'=>1002, 'msg'=>'error']);
//            }
            $data = $this->productService->getProduct($paramData);
            if(empty($data)){
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
            //获取店铺名称
            if(isset($data['StoreID'])){
                $seller = doCurl(API_URL."seller/seller/get",['user_id'=>$data['StoreID']],null,true);
                $data['StoreName'] = isset($seller['data']['true_name']) ? $seller['data']['true_name'] : '';
            }
            //产品属性组合数据
            if(isset($paramData['attrList'])){
                //销售属性展示
                foreach($data['Skus'] as $key => $sku){
                    //批发价格处理
                    if(isset($sku['BulkRateSet']['Discount']) && !empty($sku['BulkRateSet']['Discount'])){
                        $data['Skus'][$key]['BulkRateSet']['Discount'] = $sku['BulkRateSet']['Discount'] * 100;
                    }

                    $optionKey= [];
                    foreach($sku['SalesAttrs'] as $k => $attr){
                        $optionKey[] = $attr['OptionId'];
                    }
                    $AttrPriceKey =  implode('-',$optionKey);
                    $data['AttrList'][$AttrPriceKey] = $data['Skus'][$key];
                }
            }

            /** 拼装类别数据 start **/
            if(isset($data['CategoryPath'])){
                $category_path_arr = explode('-',$data['CategoryPath']);
                $class_type = 1;
                //判断是否是历史数据，历史数据需要获取PDC的类别
                if (isset($data['IsHistory']) && $data['IsHistory'] == 1){
                    $class_type = 2;
                }
                $product_class_model = new ProductClassModel();
                $category_path_data = $product_class_model->getDataWithIdArray($category_path_arr, $class_type);
                $category_arr = [];
                foreach ($category_path_data as $category_name){
                    foreach ($category_path_arr as $k => $v) {
                        if($category_name['id'] ==$v){
                            $category_arr[$k] = $category_name['title_cn'];
                        }
                    }
                }
                $data['CategoryPathStr'] = '';
                if (!empty($category_arr)){
                    ksort($category_arr);
                    $data['CategoryPathStr'] = implode(' >> ', $category_arr);
                }
            }
            /** 拼装类别数据 end **/
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>2000000001, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据产品ID，获取产品详情
     * @return mixed
     */
    public function getProductDiy(){
        try{
            $paramData = request()->post();

            if (!isset($paramData['product_id'])) {
                return apiReturn(['code'=>1002, 'msg'=>'error']);
            }
            $data = $this->productModel->getProduct($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>2000000001, 'msg'=>$e->getMessage()]);
        }
    }
	
	/**
	 * 根据类别ID，获取品牌信息
	 */
	public function getProductBrand(){
        $paramData = request()->post();

        if (!isset($paramData['classId'])) {
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
        try{
            $data = $this->productModel->getProductBrand($paramData['classId']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>'error '.$e->getMessage()]);
        }
	}
	
	/**
	 * 根据类别ID，获取属性信息
	 */
	public function getProductAttribute(){
        $paramData = request()->post();

        if (!isset($paramData['classId'])) {
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
        try{
            $product_class_model = new ProductClassModel();
            $info = $product_class_model->getInfoWithId($paramData['classId']);
            if(empty($info )){
                return apiReturn(['code'=>200, 'data'=>$info]);
            }
            $data = $this->productModel->getProductAttribute($paramData['classId']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
	}

    /**
     * 根据条件判断是否存在产品数据
     * @return array
     */
	public function judgeIsHaveProductByParams(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new CreateProductParams())->getProductByStoreIDRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            //没有数据
            $is_have = 2;
            $data = $this->productModel->getProductByParams($paramData, 1);
            if (!empty($data)){
                //有数据
                $is_have = 1;
            }
            return apiReturn(['code'=>200, 'msg'=>'success', 'is_have'=>$is_have]);
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>'error '.$e->getMessage()]);
        }
    }


    //修改产品状态，产品上下架，删除等
    public function changeStatus(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new UpdateProductStatusParams())->rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            //记录上下架日志
//            Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,__FUNCTION__,$paramData,null,null);
            //修改接口
            $ret = $this->productModel->updateProductField($paramData);
            if(false == $ret){
                return apiReturn(['code'=>1002, 'msg'=>'error']);
            }
            return apiReturn(['code'=>200, 'data'=>[]]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * 根据产品ID，修改分组
     * @return mixed
     */
    public function updateGroup(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new CreateProductParams())->updateGroup());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $this->productModel->updateProductField($paramData);
            return apiReturn(['code'=>200, 'data'=>[]]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 根据卖家ID，统计商品数量
     */
    public function countBySeller(){
        try{
            $paramData = request()->post();

            $validate = $this->validate($paramData,(new CreateProductParams())->countSeller());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->productModel->countProdcut($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 延长有效期
     * @return mixed
     */
    public function prolongExpiry(){
        try{
            $paramData = request()->post();

            $validate = $this->validate($paramData,(new CreateProductParams())->prolongExpiry());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->productModel->updateProlongExpiry($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品审核
     */
    public function audit(){
        try{
            $paramData = request()->post();

            $validate = $this->validate($paramData,(new CreateProductParams())->audit());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //审核不通过，理由必填
            if($paramData['status'] == ProductModel::PRODUCT_STATUS_REJECT){
                if (!isset($paramData['reason']) || !isset($paramData['type'])) {
                    return apiReturn(['code'=>1002]);
                }
            }
            $this->productModel->auditProduct($paramData);
            return apiReturn(['code'=>200, 'data'=>[]]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取收藏商品
     * */
    public function getWishProductLists($paramData = '',$lang = 'en'){
        $paramData = !empty($paramData)?$paramData:request()->post();

        if (!isset($paramData['ids'])) {
            return apiReturn(['code'=>3000012, 'msg'=>'id required']);
        }

        $country = !empty($paramData['country']) ? $paramData['country'] : null;
//        $ids = array();
//        foreach ($paramData['ids'] as $key=>$value){
//            if(!empty($value)){
//                $ids[] = (int)$value;
//            }
//        }
        $where['_id'] = CommonLib::supportArray($paramData['ids']);
        try{
            $res = $this->productModel->WishProductLists($where);
            if($res){
                $server = new BaseService();
                foreach ($res as $key=>$val){
                    //多语言
                    if($lang != DEFAULT_LANG){
                        $productMultiLang = $server->getProductMultiLang($val['_id'],$lang);
                        if(isset($productMultiLang['Title'][$lang]) && !empty($productMultiLang['Title'][$lang])){
                            $res[$key]['Title'] = isset($productMultiLang['Title'][$lang]) ? $productMultiLang['Title'][$lang] : '';
                            //代码优化 add by zhongning 20190724
                            $res[$key]['Descriptions'] = isset($productMultiLang['Descriptions'][$lang]) ? $productMultiLang['Descriptions'][$lang] : '';
                        }else{
                            //unset($res['data'][$key]);
                            continue;
                        }
                    }

                    //国家区域价格
                    if (!empty($country)) {
                        $regionPrice = $server->getProductRegionPrice($val['_id'], $country);
                        //这个产品有国家区域价格
                        if (!empty($regionPrice)) {
                            $server->handleProductRegionPrice($res[$key], $regionPrice);
                        }
                    }

                }
            }
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
    * 获取Sku选品产品数据
    * */
    public function getSkuSelectionProduct(){
        $paramData = request()->post();
        $lang = !empty($paramData['lang'])?$paramData['lang']:DEFAULT_LANG;
        $sku_ids = array();
        if(!empty($paramData['sku_ids'])){
            foreach ($paramData['sku_ids'] as $key=>$value){
                if(!empty($value)){
                    $sku_ids[] = (int)$value;
                }
            }
            $where['Skus._id'] = ['in',$sku_ids];
        }

        if(!empty($paramData['ids']) && is_array($paramData['ids'])){
            $where['_id'] = CommonLib::supportArray(array_keys($paramData['ids']));
        }

        try{
            $res = $this->productModel->SkuSelectionProductLists($where);
            if($res){
                $server = new BaseService();
                foreach ($res as $key => $val){
                    if($lang != DEFAULT_LANG){
                        //多语言文案
                        $productMultiLang = $server->getProductMultiLang($val['_id'],$lang);
                        if(isset($productMultiLang['Title'][$lang]) && !empty($productMultiLang['Title'][$lang])){
                            $res[$key]['Title'] = $productMultiLang['Title'][$lang];
                            $res[$key]['Descriptions'] = $productMultiLang['Descriptions'][$lang];
                        }else{
                            //unset($res['data'][$key]);
                            continue;
                        }
                    }

                    //获取国家价格
                    if(!empty($paramData['ids']) && !empty($paramData['ids'][$val['_id']])){
                        $regionPrice = $server->getProductRegionPrice($val['_id'],trim($paramData['ids'][$val['_id']]));
                        if(!empty($regionPrice)){
                            $server->handleProductRegionPrice($res[$key],$regionPrice);
                        }
                    }
                }
            }
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
    * 根据SkuCode获取产品Sku数据
    * */
    public function getSkuProductBySkuCode(){
        $paramData = request()->post();
        $lang = !empty($paramData['lang'])?$paramData['lang']:"en";
        $sku_ids = array();
        foreach ($paramData['sku_codes'] as $key=>$value){
            if(!empty($value)){
                $sku_ids[] = (string)$value;
            }
        }
        $where['Skus.Code'] = ['in',$sku_ids];
        try{
            $res = $this->productModel->getSkuProductBySkuCode($where);
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 同步处理SKU库存和SPU销量数据【定时任务】
     * @return mixed
     */
    public function synInventoryAndSalesCounts(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->synInventoryAndSalesCountsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $res = $this->productModel->synInventoryAndSalesCounts($paramData);
            if (true === $res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>$res]);
            }
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 同步处理SKU库存和销量
     * added by wangyj 20190123
     * @return mixed
     */
    public function synInventoryAndSalesCountsV2(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->synInventoryAndSalesCountsV2Rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $res = $this->productModel->synInventoryAndSalesCountsV2($paramData);
            if (true === $res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>$res]);
            }
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 更新产品活动数据【定时任务】
     * [
     *  'product_id_arr'=>[10,20,30]
     * ]
     *
     * @return mixed
     */
    public function updateActivityFortask(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->updateActivityFortaskRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            if ($this->productModel->updateActivityStatus($paramData)){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'修改失败']);
            }
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 根据多个产品ID获取产品数据
     * [
     *  'product_id_arr'=>[10,20,30]
     * ]
     *
     * @return mixed
     */
    public function getPruductDataByIds(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->getPruductDataByIdsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productModel->getMorePruductData($paramData);
            if (!empty($data)){
                return apiReturn(['code'=>200, 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'没有数据']);
            }
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1002, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 初始化sku，spu自增ID
     */
    public function initSpuIncrement(){
        $this->productModel->initSpuIncrement();
        return apiReturn(['code'=>200]);
    }

    /**
     * 初始化sku，spu自增ID
     */
    public function initBrandIncrement(){
        $this->productModel->initBrandIncrement();
        return apiReturn(['code'=>200]);
    }

    /**
     * 获取产品浏览历史数据【my使用】
     * [
     *
     *    'page_size'=>10,
     *    'page'=>1,
     *    'path'=>'',
     *    'product_id_arr'=>[12,25,36],
     *    'category_id'=>14,
     *    'product_status'=>1,
     * ]
     * @return mixed
     */
    public function getProductViewHistoryDataForMy(){
        try{
            $paramData = request()->post();

            //参数校验
            $validate = $this->validate($paramData,(new ProductParams())->getProductViewHistoryDataForMyRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->productModel->getProductViewHistoryDataForMy($paramData);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (\Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 类别，获取产品列表数据
     */
    public function listByCategory(){
        try{
            $paramData = input();

//            \think\Log::pathlog('params = ',$paramData,'listByCategory.log');
            //参数校验
            $validate = $this->validate($paramData,(new ProductParams())->getProductListsByCategory());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //分类映射
            if(isset($paramData['first_category'])&& !empty($paramData['first_category'])){
                $paramData['first_category'] = (new BaseService())->getMapClassByID($paramData['first_category']);
            }
            $classModel = new ProductClassModel();
            $data = $this->productModel->getProductListsByCategory($paramData);
            if(isset($data['data']) && !empty($data['data'])){
                foreach($data['data'] as $key => $product){
                    $classPath = isset($product['CategoryPath']) ? $product['CategoryPath'] : null;
                    if(empty($classPath)){
                        continue;
                    }
                    //分类信息
                    $classArray = explode('-',$classPath);
                    $classInfo = $classModel->getClassDetail(['id'=>(int)end($classArray)]);
                    if(isset($classInfo['type']) && $classInfo['type'] != 1){
                        //映射ERP类别数据
                        if( isset($classInfo['pdc_ids']) && !empty($classInfo['pdc_ids'])) {
                            $erpClass = (new ProductClassModel())->getClassDetail(['id' => (int)$classInfo['pdc_ids'][0]]);
                            $data['data'][$key]['CategoryPath'] = isset($erpClass['id_path']) ? $erpClass['id_path'] : $product['CategoryPath'];
                        }
                    }
                    if(isset($product['Skus']) && !empty($product['Skus'])){
                        $codeArray = array();
                        foreach($product['Skus'] as $skey => $skus){
                            if(isset($skus['Code']) && !empty($skus['Code'])){
                                $codeArray[$skey]['Code'] = $skus['Code'];
                            }
                        }
                        $data['data'][$key]['Skus'] = array_values($codeArray);
                    }
                }
            }
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取自营的门店
     */
    public function getSelfSupport(){
        $data = array();
        if(config('cache_switch_on')) {
            $data = $this->redis->get('SELF_SUPPORT');
        }
        if(empty($data)) {
            $request = doCurl(API_URL . '/seller/Seller/getSelfSupport');
            if ($request['code'] == 200) {
                if (!empty($request['data'])) {
                    $shop = CommonLib::getColumn('id', $request['data']);
                    $this->redis->set('SELF_SUPPORT', $shop, CACHE_DAY);
                    return $shop;
                }
            } else {
                //错误日志
                Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,null,null,$request);
            }
        }
           return $data;
    }

    /**
     * 更新sales_rank
     * @return mixed
     */
    public function updateSalesRank(){
        try{
            $paramData = input();
            //参数校验
//            $validate = $this->validate($paramData,(new ProductParams())->updateSalesRank());
//            if(true !== $validate){
//                return apiReturn(['code'=>1002, 'msg'=>$validate]);
//            }

            $salesRankData = isset($paramData['salesRankData']) ? $paramData['salesRankData'] : array();
            if(empty($salesRankData)){
                return apiReturn(['code'=>1002]);
            }
            foreach($salesRankData as $val){
                if(!isset($val['product_id']) || !isset($val['sales_rank'])){
                    continue;
                }
                //先查询产品
                $find = $this->productModel->getProductInField(['_id'=>(int)$val['product_id']],['IsUpdateSaleRank']);
                //如果指定更新过的产品字段SalesRank，不需要更新
                if(isset($find['IsUpdateSaleRank']) && $find['IsUpdateSaleRank'] == 1){
                    continue;
                }
                $ret = $this->productModel->updateProductSalesRank(['_id'=>(int)$val['product_id']],['SalesRank'=>(double)$val['sales_rank']]);
            }
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 姚遥、刘锐
     * 获取分类信息，ids
     */
    public function  getProductByIDs(){
        $params = input();
        if (!isset($params['id'])){
            return apiReturn(['code'=>1003]);
        }
        try{
            $server = new BaseService();
            $countryModel = new ProductCountryModel();
            $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
            if(!isset($params['status'])){
                $params['status'] = [1,5];
            }
            $data = $this->productModel->getProductByIDs(['id'=>explode(',',$params['id']),'status'=>$params['status']]);
            if(!empty($data)){
                foreach($data as $key =>$val){

                    $data[$key]['CountryPrice'] = array();
                    //是否有国家价格,刘锐需要每个国家的最低价格和最高价格
                    $countryData = $countryModel->selectCountryProduct(['Spu'=>(int)$val['_id']]);
                    if(!empty($countryData)){
                        foreach($countryData as $ck => $countryProduct){
                            $data[$key]['CountryPrice'][$ck]['Country'] = $countryProduct['Country'];
                            $data[$key]['CountryPrice'][$ck]['LowPrice'] = $countryProduct['LowPrice'];
                            $data[$key]['CountryPrice'][$ck]['HightPrice'] = $countryProduct['HightPrice'];
                        }
                    }

                    //判断语种
                    if(DEFAULT_LANG != $lang) {
                        //标题多语言
                        $productMultiLang = $server->getProductMultiLang($val['_id'],$lang);
                        if(isset($productMultiLang['Title'][$lang]) && !empty($productMultiLang['Title'][$lang])){
                            $data[$key]['Title'] = $productMultiLang['Title'][$lang];
                        }else{
                            unset($data[$key]);
                            continue;
                        }
                        //属性多语言
                        if(!empty($val['Skus'])){
                            foreach($val['Skus'] as $skey => $sku){
                                if(!empty($sku['SalesAttrs'])){
                                    foreach($sku['SalesAttrs'] as $k => $attr){
                                        $option = isset($attr['OptionId']) ? $attr['OptionId'] : 0;
                                        $lang_key = $option.'_'.$sku['_id'];
                                        $langData = $server->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$val['_id']);
                                        //例：color颜色的多语言
                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Name'] = isset($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
//                                        //例：color下蓝色blue的多语言
//                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Value'] = $attr['Value'];
//                                        //dx_product_customAttr_multiLangs
//                                        if(isset($langData['Options'][$lang_key][$lang])){
//                                            $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Value'] = $langData['Options'][$lang_key][$lang];
//                                        }
//                                        //dx_product_attr_multiLangs
//                                        if(isset($langData['Options'][$option][$lang])){
//                                            $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Value'] = $langData['Options'][$option][$lang];
//                                        }
                                    }
                                }
                            }
                        }
                    }
                    //复制默认值
                    if(!empty($val['Skus'])){
                        foreach($val['Skus'] as $skey => $sku){
                            if(!empty($sku['SalesAttrs'])){
                                foreach($sku['SalesAttrs'] as $k => $attr){
                                    $DefaultValue = isset($attr['DefaultValue']) ? $attr['DefaultValue'] : '';
                                    $CustomValue = isset($attr['CustomValue']) ? $attr['CustomValue'] : '';
                                    if(empty($DefaultValue) && empty($CustomValue)){
                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['DefaultValue'] = $attr['Value'];
                                    }
                                }
                            }
                        }
                    }
                    //产品重量
                    $data[$key]['PackingList']['Weight'] = isset($val['PackingList']['Weight']) ? $val['PackingList']['Weight'] : 0;
                    $data[$key]['Supplier'] = isset($val['Supplier']) ? $val['Supplier'] : '';

                    $data[$key]['EditTime'] = isset($val['EditTime']) ? date('Y-m-d H:i:s',$val['EditTime']) : '';
                    $data[$key]['AddTime'] = date('Y-m-d H:i:s',$val['AddTime']);
                    if(isset($val['FirstCategory']) && !empty($val['FirstCategory'])){
                        $data[$key]['LastCategory'] = $val['FirstCategory'];
                    }
                    if(isset($val['SecondCategory']) && !empty($val['SecondCategory'])){
                        $data[$key]['LastCategory'] = $val['SecondCategory'];
                    }
                    if(isset($val['ThirdCategory']) && !empty($val['ThirdCategory'])){
                        $data[$key]['LastCategory'] = $val['ThirdCategory'];
                    }
                    if(isset($val['FourthCategory']) && !empty($val['FourthCategory'])){
                        $data[$key]['LastCategory'] = $val['FourthCategory'];
                    }

                    if(!isset($val['DeclarationName'])){
                        $data[$key]['DeclarationName'] = '';
                    }
                    if(!isset($val['IsHistory'])){
                        $data[$key]['IsHistory'] = 0;
                    }
                    if(!isset($val['SalesUnitType'])){
                        $data[$key]['SalesUnitType'] = 'piece';
                    }
                    $data[$key]['Discount'] = isset($val['HightDiscount']) ? (double)$val['HightDiscount'] : 0;
                    $data[$key]['DiscountLowPrice'] = isset($val['DiscountLowPrice']) ? (double)$val['DiscountLowPrice'] : 0;
                    $data[$key]['DiscountHightPrice'] = isset($val['DiscountHightPrice']) ? (double)$val['DiscountHightPrice'] : 0;
                    $data[$key]['IsActivity'] = isset($val['IsActivity']) ? $val['IsActivity'] : 0;

                    $data[$key]['Gtins'] = isset($val['GTINs']) ? $val['GTINs'] : [];
                    //品牌名称
                    if(isset($val['BrandName']) && $val['BrandName'] == 'N/A'){
                        $val['BrandName'] = '';
                    }

                    $data[$key]['ProductImg'] = isset($val['ImageSet']['ProductImg']) ? $val['ImageSet']['ProductImg'] : '';
                    unset($data[$key]['ImageSet']);
                }
            }
            return apiReturn(['code'=>200, 'data'=>array_values($data)]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取历史产品数据【同步运费模板&&历史产品图片专用】
     * @return mixed
     */
    public function getHistoryDataForAsyncShippingTemplateAndImgs(){
        try{
            $params = input();
            $data = $this->productModel->getHistoryDataForAsyncShippingTemplateAndImgs(
                $params['page_size'],
                $params['start_spu_id'],
                $params['end_spu_id'],
                $params['us_spus'],
                $params['check_flag']
            );
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * erp - 新增商品接口
     * @param input json;
     * @return json
     */
    public function productCreate(){
        try{
            $paramData = request()->post();
            $validateSkuParams = new ErpCreateProductSkuParams();//sku参数校验
            $attribute = new ProductBrandModel();

            //参数校验
            $validate = $this->validate($paramData,(new ErpCreateProductParams())->productCreateRule());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }

            //获取类别详情
            $classData = $this->classModel->getClassDetail(['id'=>(int)$paramData['LastCategory']]);
            if(empty($classData)){
                return apiReturn(['code'=>1002, 'msg'=>$paramData['LastCategory'].' 类别不存在,请在后台添加类别！']);
            }

            $flag = false;
            $shop = $this->getSelfSupport();
            if(in_array($paramData['StoreID'],$shop)){
                $paramData['IsSelfSupport'] = 1;
                $flag = true;
            }
            //商品SKU循环校验
            foreach($paramData['Skus'] as $skey => $sku){
                //sku校验
                $validate = $this->validate($sku,$validateSkuParams->rules());
                if(true !== $validate){
                    return apiReturn(['code'=>1002, 'msg'=>$validate]);
                }
                //校验批发价格
                if(isset($paramData['AllowBulkRate']) && (bool)$paramData['AllowBulkRate'] == true){
                    $validate = $this->validate($sku,$validateSkuParams->BulkRateRules());
                    if(true !== $validate){
                        return apiReturn(['code'=>1002, 'msg'=>$validate]);
                    }
                }
                //判断是否是自营，code要唯一
                if($flag){
                    $validate = $this->validate(['Code'=>$sku['Code']],$validateSkuParams->Coderules());
                    if(true !== $validate){
                        return apiReturn(['code'=>1002, 'msg'=>$validate]);
                    }
                }
                //校验销售属性
                if(isset($sku['SalesAttrs']) && !empty($sku['SalesAttrs'])){
                    foreach($sku['SalesAttrs'] as $attrKey => $attrVal){
                        $validate = $this->validate($attrVal,$validateSkuParams->SalesAttrsRules());
                        if(true !== $validate){
                            return apiReturn(['code'=>1002, 'msg'=>$validate]);
                        }

                        if(isset($attrVal['IsColor']) && $attrVal['IsColor'] == 1){
                            $Image = isset($attrVal['Image']) ? $attrVal['Image'] :  null;
                            if(empty($Image)){
                                return apiReturn(['code'=>1002, 'msg'=>'颜色小图不能为空']);
                            }
                        }
                        $defaultValue = isset($attrVal['DefaultValue']) ? $attrVal['DefaultValue'] : null;
                        $customValue = isset($attrVal['CustomValue']) ? $attrVal['CustomValue'] : null;
                        //初始化值
                        $paramData['Skus'][$skey]['SalesAttrs'][$attrKey]['DefaultValue'] = $defaultValue;
                        $paramData['Skus'][$skey]['SalesAttrs'][$attrKey]['CustomValue'] = $customValue;
                        if(empty($defaultValue) && empty($customValue)){
                            return apiReturn(['code'=>1002, 'msg'=>'DefaultValue 不能为空']);
                        }

                        //判断颜色COLOR是否存在表里，否则多语言翻译功能有误，不能找到对应类别属性的翻译
//                        $colorExist = $attribute->getBrandAttribute(['_id' => (int)$classData['id'],'attr_id' => $attrVal['_id']]);
//                        if(empty($colorExist['attribute'][$attrVal['_id']])){
//                            return apiReturn(['code'=>1002, 'msg'=>$attrVal['Name'].'所属类别属性，在DX类别属性中未能找到对应的数据']);
//                        }
                        //判断颜色-红色是否存在表里
                    }
                }
            }
            //判断自营店铺，code的唯一性
            if($flag){
                $seller_id = CommonLib::supportArray($shop);
                $code = CommonLib::getColumn('Code',$paramData['Skus']);
                $exist = $this->productModel->getCode(['seller_id'=>$seller_id,'Code'=>$code]);
                if(!empty($exist)){
                    return apiReturn(['code'=>1002, 'msg'=>'商品编码重复']);
                }
            }

            //校验产品重量
            if(isset($paramData['PackingList']['UseCustomWeight']) && (bool)$paramData['PackingList']['UseCustomWeight'] == true){
                $validate = $this->validate($paramData['PackingList'],(new ErpCreateProductParams())->productWeightRule());
                if(true !== $validate){
                    return apiReturn(['code'=>1002, 'msg'=>$validate]);
                }
            }
            //数据开始插入.
            return $this->productService->createProduct($paramData);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    public function queryProductId(){
        try{
            $paramData = input();

            $data = $this->productService->queryProductId($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * （刘锐请求接口）
     * 根据产品id,产品状态获取产品信息
     */
    public function  getProductInfo(){
        $params = input();
        //参数校验
        $validate = $this->validate($params,(new UpdateProductStatusParams())->statusRule());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $classModel = new ProductClassModel();
            $countryModel = new ProductCountryModel();
            $server = new BaseService();
            $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
            $data = $this->productModel->getProductByIDs(['id'=>$params['id'],'status'=>$params['status']]);
            if(!empty($data)){
                foreach($data as $key =>$val){
                    $data[$key]['CountryPrice'] = array();
                    //是否有国家价格,刘锐只需要最低价格和最高价格
                    $countryData = $countryModel->selectCountryProduct(['Spu'=>(int)$val['_id']]);
                    if(!empty($countryData)){
                        foreach($countryData as $ck => $countryProduct){
                            $data[$key]['CountryPrice'][$ck]['Country'] = $countryProduct['Country'];
                            $data[$key]['CountryPrice'][$ck]['LowPrice'] = $countryProduct['LowPrice'];
                            $data[$key]['CountryPrice'][$ck]['HightPrice'] = $countryProduct['HightPrice'];
                        }
                    }
                    //判断语种
                    if(DEFAULT_LANG != $lang) {
                        //标题多语言
                        $productMultiLang = $server->getProductMultiLang($val['_id'],$lang);
                        $data[$key]['Title'] = isset($productMultiLang['Title'][$lang]) ? $productMultiLang['Title'][$lang] : $val['Title'];//默认英语
                        //属性多语言
                        if(!empty($val['Skus'])){
                            foreach($val['Skus'] as $skey => $sku){
                                if(!empty($sku['SalesAttrs'])){
                                    foreach($sku['SalesAttrs'] as $k => $attr){
                                        $option = isset($attr['OptionId']) ? $attr['OptionId'] : 0;
                                        $lang_key = $option.'_'.$sku['_id'];
                                        $langData = $server->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$val['_id']);
                                        //例：color颜色的多语言
                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Name'] = isset($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                                        //例：color下蓝色blue的多语言
                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Value'] = $attr['Value'];
                                        //dx_product_customAttr_multiLangs
                                        if(isset($langData['Options'][$lang_key][$lang])){
                                            $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Value'] = $langData['Options'][$lang_key][$lang];
                                        }
                                        //dx_product_attr_multiLangs
                                        if(isset($langData['Options'][$option][$lang])){
                                            $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Value'] = $langData['Options'][$option][$lang];
                                        }
                                    }
                                }
                            }
                        }

                    }

                    $data[$key]['EditTime'] = isset($val['EditTime']) ? date('Y-m-d H:i:s',$val['EditTime']) : '';
                    $data[$key]['AddTime'] = date('Y-m-d H:i:s',$val['AddTime']);

                    if(isset($val['SecondCategory']) && !empty($val['SecondCategory'])){
                        $data[$key]['LastCategory'] = $val['SecondCategory'];
                    }
                    if(isset($val['ThirdCategory']) && !empty($val['ThirdCategory'])){
                        $data[$key]['LastCategory'] = $val['ThirdCategory'];
                    }
                    if(isset($val['FourthCategory']) && !empty($val['FourthCategory'])){
                        $data[$key]['LastCategory'] = $val['FourthCategory'];
                    }
                    if(isset($data[$key]['LastCategory']) && !empty($data[$key]['LastCategory'])){
                        $classData = $classModel->getClassDetail(['id'=>(int)$data[$key]['LastCategory']]);
                        if(isset($classData['type']) && $classData['type'] != 1){
                            //产品映射
                            if(isset($classData['pdc_ids']) && !empty($classData['pdc_ids'])){
                                $data[$key]['LastCategory'] = $classData['pdc_ids'][0];
                            }else{
                                //上传谷歌缺失，PDC就是0
                                $data[$key]['LastCategory'] = 0;
                            }
                        }
                    }
                    //最低价格
                    if(isset($val['DiscountLowPrice']) && !empty($val['DiscountLowPrice'])){
                        if(strtoupper($val['DiscountLowPrice']) != 'NULL'){ //异常数据
                            $data[$key]['LowPrice'] = $val['DiscountLowPrice'];
                        }
                    }
                    //接口需要ThirdCategory字段
                    $data[$key]['ThirdCategory'] = $data[$key]['LastCategory'];

                    $data[$key]['PackingList']['Weight'] = sprintf("%01.2f",$val['PackingList']['Weight']);

                    if(!isset($val['DeclarationName'])){
                        $data[$key]['DeclarationName'] = '';
                    }
                    if(!isset($val['IsHistory'])){
                        $data[$key]['IsHistory'] = 0;
                    }
                    $data[$key]['ProductImg'] = array();
                    if(isset($val['ImageSet']['ProductImg']) && !empty($val['ImageSet']['ProductImg'])){
                        $img = array();
                        //去除wt
                        foreach($val['ImageSet']['ProductImg'] as $imgkey => $val){
                            $img[$imgkey] = str_replace('wt','',$val);
                        }
                        $data[$key]['ProductImg'] = $img;
                    }
                    unset($data[$key]['ImageSet']);
                }
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 批量修改产品价格
     * @return mixed
     */
    public function updateProductsPrice(){
        $params = input();
        if(empty($params)){
            return apiReturn(['code'=>1002]);
        }
        $productService = new ProductService();
        $ret = $productService->updateProductsPrice($params);
        return apiReturn(['code'=>200]);
    }

    /**
     * 产品拆分接口
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
     */
    public function splitProduct(){
        $params = request()->post();
        try{
            //参数校验
            $validate = $this->validate($params,(new ProductParams())->splitProductRule());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }
            foreach ($params['data'] as $info){
                $validate = $this->validate($info,(new ProductParams())->splitProductDataRule());
                if(true !== $validate){
                    return (['code'=>1002, 'msg'=>$validate]);
                }
            }
            return apiReturn($this->productService->splitProduct($params));
        }catch (\Exception $e){
            $product_id = isset($params['product_id'])?$params['product_id']:'';
            Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,'splitProduct'.$product_id, $params,'product info', '系统异常 '.$e->getMessage());
            return (['code'=>1010, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }
     /**
     * 根据产品ID，获取产品多语言的翻译
     * @return mixed
     */
    public function getProductMultiLangs(){
        try{
            $paramData = request()->post();
            if (!isset($paramData['product_id'])) {
                return apiReturn(['code'=>1002, 'msg'=>'error']); 
            }
            $data = $this->productModel->getProductAllMultiLangs($paramData['product_id']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>2000000001, 'msg'=>$e->getMessage()]);
        }
    }
    /**
     * 修改产品数据【同步历史产品运费模板数据专用】
     * @return array
     */
    public function updateForSyncHistoryProductSTAndImgs(){
        $params = request()->post();
        try{
            //参数校验
            /*$validate = $this->validate($params,(new ProductParams())->splitProductRule());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }*/
            //return apiReturn($this->productService->updateForSyncHistoryProductSTAndImgs($params));

            $res = $this->productModel->updateForSyncHistoryProductSTAndImgs($params);
            if ($res){
                return (['code'=>200]);
            }else{
                return (['code'=>1009, 'msg'=>'修改失败']);
            }
        }catch (\Exception $e){
            return (['code'=>1010, 'msg'=>'系统异常 '.$e->getMessage()]);
        }

    }

    /**
     * 同步多语言表
     * @param input json;
     * @return json
     */
    public function updatePrdouctmMultiLangs(){
        try{
            $paramData = request()->post();
            //参数校验
            $validate = $this->validate($paramData,(new CreateProductParams())->updatePrdouctmMultiLangs());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $result = $this->productService->updatePrdouctmMultiLangs($paramData);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }
    /**
    *获取当前SKU最大编码
    *
    */
    public function getMaxSku(){
        $res = $this->productModel->getAutoIncrement();
        if(!empty($res)){
            return apiReturn(['code'=>200, 'data'=>$res['SubSKU']]);
        }
    }

    /**
     * 更新sales_rank
     * @return mixed
     */
    public function batchUpdateSalesRank(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new CreateProductParams())->batchUpdateSalesRank());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            if(isset($params['spus']) && !empty($params['spus'])){
                $params['spus'] = explode(',',$params['spus']);
                $_id = CommonLib::supportArray($params['spus']);
            }
            //IsUpdateSaleRank 张恒:只要是通过这个接口更新的，sr以后不能更改，除非出接口改这个字段值为0
            $this->productModel->updateProductSkuPrice(['_id'=>$_id],['SalesRank'=>(double)$params['sales_rank'],'IsUpdateSaleRank'=>1]);
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>'system error']);
        }
    }


    /**
     * 取消何元自己更新SR接口 IsUpdateSaleRank = 0
     * @return mixed
     */
    public function cancelUpdateSalesRank(){
        try{
            $params = request()->post();
            //参数校验
//            $validate = $this->validate($params,(new CreateProductParams())->batchUpdateSalesRank());
//            if(true !== $validate){
//                return apiReturn(['code'=>1002, 'msg'=>$validate]);
//            }
            if(isset($params['spus']) && !empty($params['spus'])){
                $params['spus'] = explode(',',$params['spus']);
                $_id = CommonLib::supportArray($params['spus']);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'spus empty']);
            }
            //IsUpdateSaleRank sr更改，改这个字段值为0
            $this->productModel->updateProductSkuPrice(['_id'=>$_id],['IsUpdateSaleRank'=>0]);
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>'system error']);
        }
    }

    /**
     * 更新MVP
     * @return mixed
     */
    public function batchUpdateMvp(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new CreateProductParams())->batchUpdateMvp());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            if(isset($params['spus']) && !empty($params['spus'])){
                $params['spus'] = explode(',',$params['spus']);
                $_id = CommonLib::supportArray($params['spus']);
            }
            $this->productModel->updateProductSkuPrice(['_id'=>$_id],['IsMVP'=>(int)$params['is_mvp']]);
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>'system error']);
        }
    }

    /**
     * 更新产品状态
     * @return mixed
     */
    public function batchUpdateProductStatus(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new CreateProductParams())->batchUpdateProductStatus());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            if(isset($params['spus']) && !empty($params['spus'])){
                $params['spus'] = explode(',',$params['spus']);
                $_id = CommonLib::supportArray($params['spus']);
            }
            $ret = $this->productModel->updateProductSkuPrice(['_id'=>$_id],['ProductStatus'=>(int)$params['status']]);
            //记录产品状态变更日志
            Monlog::write(LOGS_MALLEXTEND_API,'info',__METHOD__,__FUNCTION__,$params,null,null);
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1003,'msg'=>'system error']);
        }
    }

    //根据Code修改库存
    public function updateProductQtyByCode(){
        $params = request()->post();
        if(empty($params)){
            return apiReturn(['code'=>1002, 'msg'=>'Invalid parameters']);
        }
        $productModel = new ProductModel();
        foreach($params as $skuArray){
            $skuCode = isset($skuArray['sku']) ? $skuArray['sku'] : '';
            if(empty($skuCode) || !isset($skuArray['qty'])){
                continue;
            }
            $updateKey = -1;
            $updateProduct = array();
            $findProudct = $productModel->getProduct(['sku_code'=>$skuCode,'field'=>['ProductStatus','_id','Skus._id','Skus.Code','Skus.Inventory']]);
            if(empty($findProudct)){
                continue;
            }
            $totalQty = 0;
            //更新sku库存,查找key值
            foreach($findProudct['Skus'] as $pkey => $productSkus){
                $inventory = $productSkus['Inventory'];
                if($skuCode == $productSkus['Code']){
                    $updateKey = $pkey;
                    $inventory = $skuArray['qty'];
                }
                $totalQty = $totalQty + $inventory;
            }
            if($updateKey == -1){
                continue;
            }
            if($totalQty == 0){
                $updateProduct['ProductStatus'] = 3;//库存为0，停售
            }else{
                if($findProudct['ProductStatus'] == 3){
                    $updateProduct['ProductStatus'] = 1;//有库存，正常销售
                }
            }
            $updateProduct['Skus.'.$updateKey.'.Inventory'] = (int)$skuArray['qty'];
            $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$updateProduct);
            if(isset($updateProduct['ProductStatus']) && $updateProduct['ProductStatus'] == 3){
                $insert['EntityId'] = (int)$findProudct['_id'];
                $insert['CreatedDateTime'] = time();
                $insert['IsSync'] = true;
                $insert['Note'] = '库存为0停售';
                $insert['AddTime'] = date('Y-m-d H:i:s',time());
                $productModel->addProductHistory($insert);
            }
        }
        return apiReturn(['code'=>200]);
    }

    /**
     * 仲洋业务单独使用接口
     * 获取分类信息，ids
     */
    public function  getProductListByIDs(){
        $params = input();
        if (!isset($params['id'])){
            return apiReturn(['code'=>1003]);
        }
        try{
            $server = new BaseService();
            $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
            $country = !empty($params['country']) ? trim($params['country']) : null;
            if(!isset($params['status'])){
                $params['status'] = [1,5];
            }
            $data = $this->productModel->getProductByIDs(['id'=>explode(',',$params['id']),'status'=>$params['status']]);
            if(!empty($data)){
                foreach($data as $key =>$val){
                    //判断语种
                    if(DEFAULT_LANG != $lang) {
                        //标题多语言
                        $productMultiLang = $server->getProductMultiLang($val['_id'],$lang);
                        if(isset($productMultiLang['Title'][$lang]) && !empty($productMultiLang['Title'][$lang])){
                            $data[$key]['Title'] = $productMultiLang['Title'][$lang];
                        }
                        //属性多语言
                        if(!empty($val['Skus'])){
                            foreach($val['Skus'] as $skey => $sku){
                                if(!empty($sku['SalesAttrs'])){
                                    foreach($sku['SalesAttrs'] as $k => $attr){
                                        $option = isset($attr['OptionId']) ? $attr['OptionId'] : 0;
                                        $lang_key = $option.'_'.$sku['_id'];
                                        $langData = $server->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$val['_id']);
                                        //例：color颜色的多语言
                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['Name'] = isset($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attr['Name'];
                                    }
                                }
                            }
                        }
                    }
                    //复制默认值
                    if(!empty($val['Skus'])){
                        foreach($val['Skus'] as $skey => $sku){
                            if(!empty($sku['SalesAttrs'])){
                                foreach($sku['SalesAttrs'] as $k => $attr){
                                    $DefaultValue = isset($attr['DefaultValue']) ? $attr['DefaultValue'] : '';
                                    $CustomValue = isset($attr['CustomValue']) ? $attr['CustomValue'] : '';
                                    if(empty($DefaultValue) && empty($CustomValue)){
                                        $data[$key]['Skus'][$skey]['SalesAttrs'][$k]['DefaultValue'] = $attr['Value'];
                                    }
                                }
                            }
                        }
                    }
                    //产品重量
                    $data[$key]['PackingList']['Weight'] = isset($val['PackingList']['Weight']) ? $val['PackingList']['Weight'] : 0;
                    $data[$key]['Supplier'] = isset($val['Supplier']) ? $val['Supplier'] : '';

                    $data[$key]['EditTime'] = isset($val['EditTime']) ? date('Y-m-d H:i:s',$val['EditTime']) : '';
                    $data[$key]['AddTime'] = date('Y-m-d H:i:s',$val['AddTime']);
                    if(isset($val['FirstCategory']) && !empty($val['FirstCategory'])){
                        $data[$key]['LastCategory'] = $val['FirstCategory'];
                    }
                    if(isset($val['SecondCategory']) && !empty($val['SecondCategory'])){
                        $data[$key]['LastCategory'] = $val['SecondCategory'];
                    }
                    if(isset($val['ThirdCategory']) && !empty($val['ThirdCategory'])){
                        $data[$key]['LastCategory'] = $val['ThirdCategory'];
                    }
                    if(isset($val['FourthCategory']) && !empty($val['FourthCategory'])){
                        $data[$key]['LastCategory'] = $val['FourthCategory'];
                    }

                    if(!isset($val['DeclarationName'])){
                        $data[$key]['DeclarationName'] = '';
                    }
                    if(!isset($val['IsHistory'])){
                        $data[$key]['IsHistory'] = 0;
                    }
                    if(!isset($val['SalesUnitType'])){
                        $data[$key]['SalesUnitType'] = 'piece';
                    }
                    //新增国家定价功能
                    if(!empty($country)){
                        $regionPrice = $server->getProductRegionPrice($val['_id'],$country);
                        //这个产品有国家区域价格
                        if(!empty($regionPrice)){
                            //这个产品有国家区域价格
                            $server->handleProductRegionPrice($data[$key],$regionPrice);
                        }
                    }
                    $data[$key]['Discount'] = isset($val['HightDiscount']) ? (double)$val['HightDiscount'] : 0;

                    //市场价区间价
                    $LowListPrice = isset($val['LowListPrice']) ? $val['LowListPrice'] : 0;
                    $HighListPrice = isset($val['HighListPrice']) ? $val['HighListPrice'] : 0;

                    //折扣后的价格区间,有些产品数据库保存的是字符串类型，NULL add by zhongning 2019-05-16
                    $data[$key]['DiscountLowPrice'] = !empty($data[$key]['DiscountLowPrice']) && $data[$key]['DiscountLowPrice'] != 'NULL'
                        ? $data[$key]['DiscountLowPrice'] : $LowListPrice;//最低价格
                    $data[$key]['DiscountHightPrice'] = !empty($data[$key]['DiscountHightPrice']) && $data[$key]['DiscountHightPrice'] != 'NULL'
                        ? $data[$key]['DiscountHightPrice'] : $HighListPrice;//最高价

                    //市场折扣
                    if(empty($data[$key]['Discount']) && !empty($val['LowListPrice']) && !empty($val['LowPrice'])){
                        if($val['LowListPrice'] > $val['LowPrice']){
                            $data[$key]['Discount'] =  1 - round(($val['LowListPrice'] - $val['LowPrice']) / $val['LowListPrice'],2);
                        }
                    }

                    $data[$key]['IsActivity'] = isset($val['IsActivity']) ? $val['IsActivity'] : 0;
                    //是否是MVP
                    $data[$key]['IsMVP'] = isset($val['IsMVP']) ? $val['IsMVP'] : 0;
                    //是否新品
                    $time = isset($val['AddTime']) ? $val['AddTime'] : 0;
                    $data[$key]['isNewarrivals'] = 0;
                    if($time > strtotime('-15 day')){
                        $data[$key]['isNewarrivals'] = 1;
                    }

                    $data[$key]['Gtins'] = isset($val['GTINs']) ? $val['GTINs'] : [];
                    //品牌名称
                    if(isset($val['BrandName']) && $val['BrandName'] == 'N/A'){
                        $val['BrandName'] = '';
                    }

                    $data[$key]['ProductImg'] = isset($val['ImageSet']['ProductImg']) ? $val['ImageSet']['ProductImg'] : '';
                    unset($data[$key]['ImageSet']);
                }
            }
            return apiReturn(['code'=>200, 'data'=>array_values($data)]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 修改国家产品价格  -- 何元
     * @return mixed
     */
    public function updateCountryProductPrice(){
        $paramData = input();
        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->updateCountryProduct());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->updateCountryProductsPrice($paramData);
            if($data){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1000000021,'msg'=>'failed']);
            }
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 批量修改国家产品价格 -- 何元
     * @return mixed
     */
    public function batchUpdateCountryProductPrice(){
        $paramData = input();
        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->batchUpdateCountryProduct());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $params['spu'] = $paramData['spu'];
            $params['sku_id'] = $paramData['sku_id'];
            foreach($paramData['country'] as $countryData){
                $params['country'] = $countryData['country'];
                $params['price'] = $countryData['price'];
                $this->productService->updateCountryProductsPrice2($params);
            }
            //插入变更日志
            if($params['spu']){
                $this->productService->addHistory($params['spu']);
            }
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 删除国家价格  -- 何元
     * @return mixed
     */
    public function deleteCountryProduct(){
        $paramData = input();
        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->deleteCountryProduct());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->deleteCountryProduct($paramData);
            if($data){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1000000021,'msg'=>'failed']);
            }
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }


    //获取产品运费模板
    public function getProductShipping(){
        $paramData = request()->post();

        if(!isset($paramData['product_id'])){
            return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
        }
        try{
            $data = $this->productService->getShipping($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000022, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 国家产品价格黑名单 -- 何元
     * @return mixed
     */
    public function countryProductBlacklist(){
        $paramData = input();
        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->countryProductBlacklist());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $this->productService->CountryProductsPriceBlacklist($paramData);
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 修改市场价格，按sku -- 何元
     * @return mixed
     */
    public function updateProductListPrice(){
        $params = request()->post();
        if(empty($params)){
            return apiReturn(['code'=>1002]);
        }
        $productService = new ProductService();
        $ret = $productService->updateProductListPrice($params);
        return apiReturn(['code'=>200]);
    }

    /**
     * add by zhongning 20191107
     * $param = '[{"product_id":2006844,"discount":0.5},{"product_id":2006844,"discount":0.1}]';
     * 修改市场价格折扣，按SPU -- 成晓龙
     * @return mixed
     */
    public function updateProductListPriceDiscount(){
        $params = request()->post();
        if(empty($params)){
            return apiReturn(['code'=>1002]);
        }
        $productService = new ProductService();
        return $productService->updateProductListPriceDiscount($params);
    }

    /**
     * 获取这个产品的，所有的价格（包括国家） -- 何元
     */
    public function getProductPrice(){
        $params = request()->post();
        if(empty($params['spus'])){
            return apiReturn(['code'=>1002]);
        }
        $productService = new ProductService();
        $ret = $productService->getProductPrice($params);
        return apiReturn(['code'=>200,'data'=>$ret]);
    }

    /**
     * 根据SKU获取成本价
     * add by 20190711 kevin
     */
    public function getProductPurchasePrice($params = array()){
        $params = !empty($params)?$params:request()->post();
        if(empty($params['skus'])){
            return apiReturn(['code'=>1002]);
        }
        $productService = new ProductService();
        $ret = $productService->getProductPurchasePrice($params);
        return apiReturn(['code'=>200,'data'=>$ret]);
    }

    /**
     * 产品修改图片 -- 想华
     * @return mixed
     */
    public function updateProductImg(){
        try{
            $paramData = request()->post();
            //参数校验
            $validate = $this->validate($paramData,(new CreateProductParams())->updateProductRule());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $ret = $this->productService->updateProductImg($paramData);
            if($ret){
                return apiReturn(['code'=>200]);
            }
            return apiReturn(['code'=>1002,'msg'=>'更新失败']);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 更新产品属性
     * @return mixed
     */
    public function updateProductAttributes(){
        try{
            $paramData = request()->post();
            if(empty($paramData) || !is_array($paramData)){
                return apiReturn(['code'=>1002,'msg'=>'参数有误']);
            }
            //参数校验
            $ret = $this->productService->updateProductAttributes($paramData);
            if($ret){
                return apiReturn(['code'=>200]);
            }
            return apiReturn(['code'=>1002,'msg'=>'更新失败']);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALLEXTEND_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }
}