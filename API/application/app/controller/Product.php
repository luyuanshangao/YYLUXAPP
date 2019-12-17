<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\params\mall\ProductParams;
use app\app\services\ProductService;
use think\Db;
use think\Exception;
use think\Monlog;


/**
 * 产品接口
 */
class Product extends AppBase
{
    public $productService;
    public $productParams;
    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->productParams = new ProductParams();
    }

    /**
     * 校验产品是否有效
     * @return mixed
     */
    public function checkProduct(){
        try{
            $paramData = request()->post();

            $data = $this->productService->checkProduct($paramData);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据产品ID，获取产品详情
     * @return mixed
     */
    public function getProduct(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->getProductRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->getProduct($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
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

    /*
     * 新品数据，首页数据接口
     * 数量限制
     */
    public function getNewArrivalsProducts(){
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData,$this->productParams->newArrivalsRule());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        //取数限制
        $paramData['limit'] = 50;
        try{
            $result = $this->productService->getNewProduct($paramData);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>100000001, 'msg'=>$e->getMessage()]);
        }
    }


    /*
     * 分类页面新品数据接口
     * 数量限制
     */
    public function getClassNewArrivalsData(){
        $paramData = request()->post();

        $paramData['isNewProduct'] = 1;
        $paramData['addTimeSort'] = 1;
        //参数校验
        $validate = $this->validate($paramData,$this->productParams->newArrivalsRule());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }

        if(isset($paramData['firstCategory']) && !empty($paramData['firstCategory'])){
            $paramData['category'] = $paramData['firstCategory'];
            unset($paramData['firstCategory']);
        }
        if(isset($paramData['secondCategory']) && !empty($paramData['secondCategory'])){
            $params['category'] = $paramData['secondCategory'];
            unset($paramData['secondCategory']);
        }
        //取数限制
        $paramData['limit'] = 50;
        try{
            $result = $this->productService->getClassNewArrivals($paramData);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>100000001, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 一级分类页面，二级分类产品接口
     */
    public function getSecCategroyProduct(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,$this->productParams->secProductRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            if(isset($paramData['firstCategory']) && !empty($paramData['firstCategory'])){
                $paramData['category'] = $paramData['firstCategory'];
                unset($paramData['firstCategory']);
            }
            if(isset($paramData['secondCategory']) && !empty($paramData['secondCategory'])){
                $paramData['category'] = $paramData['secondCategory'];
                unset($paramData['secondCategory']);
            }
            $paramData['limit'] = 50;

            $result = $this->productService->getSecCategroy($paramData);
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>100000005, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 二级分类页面
     *
     * 产品列表页面数据接口
     * @return mixed
     */
    public function getCategroyPageList(){
        try{
            $paramData = request()->post();

            $data = $this->productService->getCategoryPageLists($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品基本详情信息
     */
    public function getBaseSpuInfo(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getProductInfoRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $products = array();
            $data = $this->productService->getBaseSpuInfo($params);
            //过滤字段，APP特殊处理
            if(!empty($data)){
                //币种符号
                $products['currencyCode'] = $data['currencyCode'];
                $products['currencyCodeSymbol'] = $data['currencyCodeSymbol'];
                $products['isActivity'] = isset($data['IsActivity']) ? (int)$data['IsActivity'] : 0;
                $products['isMvp'] = isset($data['IsMVP']) ? (int)$data['IsMVP'] : 0;;
                $products['Title'] = $data['Title'];
                $products['RewrittenUrl'] = $data['RewrittenUrl'];
                $products['LowPrice'] = $data['LowPrice'];
                $products['HightPrice'] = $data['HightPrice'];
                $products['OriginalLowPrice'] = $data['OriginalLowPrice'];
                $products['OriginalHightPrice'] = $data['OriginalHightPrice'];
                $products['ProductImg'] = $data['ProductImg'];
                $products['Discount'] = $data['Discount'];
                $products['Skus'] = $data['Skus'];
                $products['AttrList'] = $data['AttrList'];
            }
            return apiReturn(['code'=>200, 'data'=>$products]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 产品运费模板详情
     */
    public function getSpuShippingInfo(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getShippingRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $shipping = $this->productService->getSpuShipping($params);
            return apiReturn(['code'=>200, 'data'=>$shipping]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品内容详情
     */
    public function getSupOverview(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getRatingRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->getSpuDescriptions($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());
            return apiReturn(['code'=>1000000068, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品星级详情
     * @return mixed
     */
    public function getSupReviews(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getRatingRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->getSpuReviewsDetail($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000067, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * 新品页面数据接口
     * @return mixed
     */
    public function getNewArrivalsLists(){
        try{
            $paramData = request()->post();

            $paramData['lang'] = isset($paramData['lang']) ? $paramData['lang'] : DEFAULT_LANG;
            $paramData['currency'] = isset($paramData['currency']) ? $paramData['currency'] : DEFAULT_CURRENCY;
            $paramData['newArrivals'] = true;
            $data = $this->productService->newArrivalList($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch(Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * topseller 页面数据
     * @return mixed
     */
    public function getTopSellerLists(){
        try{
            $paramData = request()->post();

            $data = $this->productService->topSellerLists($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch(Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * under $0.99页面数据
     * @return mixed
     */
    public function getUnderPriceLists(){
        try{
            $paramData = request()->post();

            $params['key'] = isset($params['key']) && !empty($params['key']) ? $params['key'] : '0.99';
            $params['page'] = isset($params['page']) ? $params['page'] : 1;

            $data = $this->productService->underPriceLists($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * MVP页面数据
     * @return mixed
     */
    public function getMvpLists(){
        try{
            $paramData = request()->post();

            $paramData +=[
                'isMvp' => true
            ];
            $data = $this->productService->mvpProducts($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch(Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);

        }

    }


    /**
     * StaffPicks 页面数据
     * @return mixed
     */
    public function getStaffPicksLists(){
        try{
            $paramData = request()->post();

            $data = $this->productService->staffPicksProducts($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * Presale 页面数据
     * @return mixed
     */
    public function getPresaleLists(){
        try{
            $paramData = request()->post();

            $data = $this->productService->presaleProducts($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }
    
    /**
     * 根据SKUID，获取产品ID
     * @return mixed
     */
    public function getProductIdBySkuId(){
        try{
            $paramData = request()->post();

            $data = $this->productService->getProductIdBySkuId($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch(Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }
    /**
     * 根据SKUIDS，获取产品IDS
     * @return mixed
     */
    public function getProductIdBySkuIdS(){
    	$paramData = request()->post();

    	$data = $this->productService->getProductIdBySkuIds($paramData);
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 品牌页面列表产品数据
     */
    public function getBrandProduct(){
        try{
            $paramData = request()->post();

            $data = $this->productService->selectBrandProduct($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 查找下个待审核产品
     */
    public function getNextAuditProduct(){
        try{
            $paramData = request()->post();

            $data = $this->productService->getNextAuditProduct($paramData);
            if(!is_array($data)){
                return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }

    }
    
    /**
     * 根据SkuIdArr扣减库存，按具体的SkuId返回成功与否
     */
    public function editInventoryBySkuIdArr(){
    	$paramData = request()->post();

    	$data = $this->productService->editInventoryBySkuIdArr($paramData);
    	if(!is_array($data)){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }
    
    /**
     * 获取affiliate信息
     */
    public function getAffiliateInfo(){
    	$paramData = request()->post();

        try{
            $data = $this->productService->getAffiliateInfo($paramData);
            if(!is_array($data)){
                return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * cart产品列表，获取信息，计算运费
     */
    public function getCartProductList(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getRatingRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->getCartProductList($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * cart产品列表，获取信息，计算运费
     */
    public function getProductToShipping(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getRatingRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->productService->getProductToShipping($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    public function getNewArrivalsTemptale(){
        try{
            $params = request()->post();
            $data = $this->productService->getNewArrivalsTemptale($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    public function getSmartphonesTemplate(){
        try{
            $params = request()->post();
            $data = $this->productService->getSmartphonesTemplate($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    public function getElectronicsTemplate(){
        try{
            $params = request()->post();
            $data = $this->productService->getElectronicsTemplate($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    public function getDiyAndFunTemplate(){
        try{
            $params = request()->post();
            $data = $this->productService->getDiyAndFunTemplate($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    public function getIndoorAndOutDoorTemplate(){
        try{
            $params = request()->post();
            $data = $this->productService->getIndoorAndOutDoorTemplate($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    public function getBrandsTemplate(){
        try{
            $params = request()->post();
            $data = $this->productService->getBrandsTemplate($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }
}
