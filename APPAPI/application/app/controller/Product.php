<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\params\mall\ProductParams;
use app\app\services\ProductService;
use app\common\services\IndexService;
use think\Db;
use think\Exception;
use think\Log;
use think\Monlog;
use app\app\services\CommonService;
use app\common\services\CategoryService;

/**
 * 产品接口
 */
class Product extends AppBase
{
    public $productService;
    public $productParams;
    protected $noNeedLogin = ['*'];

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->productParams = new ProductParams();
    }

    /**
     * 二三级分类页面列表
     * 产品列表数据
     * params
     * lang 语种
     * currency 币种
     */
    public function getCategorySpuList()
    {
        $serviceparams = [
            'firstCategory' => isset($params['firstCategory']) ? (int)$params['firstCategory'] : null,//一级类别
            'thirdCategory' => isset($params['thirdCategory']) ? (int)$params['thirdCategory'] : null,//三级类别
            'page' => isset($params['page']) ? (int)$params['page'] : 1,
            'brandId' => isset($params['brandId']) ? $params['brandId'] : array(),
            'salesCounts' => isset($params['salesCounts']) ? $params['salesCounts'] : null,//销量
            'addTimeSort' => isset($params['addTimeSort']) ? $params['addTimeSort'] : null,//时间
            'reviewCount' => isset($params['reviewCount']) ? $params['reviewCount'] : null,//评论
            'priceSort' => isset($params['priceSort']) ? $params['priceSort'] : null,//价格
            'lowPrice' => isset($params['lowPrice']) && !empty($params['lowPrice']) ? (double)$params['lowPrice'] : null,
            'hightPrice' => isset($params['hightPrice']) && !empty($params['hightPrice']) ? (double)$params['hightPrice'] : null,
            'freeShipping' => isset($params['freeShipping']) ? $params['freeShipping'] : null,
        ];
        $resData = $this->productService->getCategoryPageLists($serviceparams);
        return $resData;
    }

    /**
     * 根据产品ID，获取产品详情
     * @return mixed
     */
    public function getProduct()
    {
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData, (new ProductParams())->getProductRules());
        if (true !== $validate) {
            return $this->result([], 1004, $validate);
        }

        $data = $this->productService->getProduct($paramData);
        return $this->result($data);

    }

    /**
     * 校验产品是否有效
     * @return mixed
     */
    public function checkProduct()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->checkProduct($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }


    //获取产品运费模板
    public function getProductShipping()
    {
        $paramData = request()->post();

        if (!isset($paramData['product_id'])) {
            return apiReturn(['code' => 1000000021, 'msg' => '请求失败']);
        }
        try {
            $data = $this->productService->getShipping($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000022, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 新品数据，首页数据接口
     * 数量限制
     */
    public function getNewArrivalsProducts()
    {
        $paramData = request()->post();

        //参数校验
        $validate = $this->validate($paramData, $this->productParams->newArrivalsRule());
        if (true !== $validate) {
            return (['code' => 1002, 'msg' => $validate]);
        }
        //取数限制
        $paramData['limit'] = 50;
        try {
            $result = $this->productService->getNewProduct($paramData);
            return $result;
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 100000001, 'msg' => $e->getMessage()]);
        }
    }


    /*
     * 分类页面新品数据接口
     * 数量限制
     */
    public function getClassNewArrivalsData()
    {
        $paramData = request()->post();

        $paramData['isNewProduct'] = 1;
        $paramData['addTimeSort'] = 1;
        //参数校验
        $validate = $this->validate($paramData, $this->productParams->newArrivalsRule());
        if (true !== $validate) {
            return (['code' => 1002, 'msg' => $validate]);
        }

        if (isset($paramData['firstCategory']) && !empty($paramData['firstCategory'])) {
            $paramData['category'] = $paramData['firstCategory'];
            unset($paramData['firstCategory']);
        }
        if (isset($paramData['secondCategory']) && !empty($paramData['secondCategory'])) {
            $params['category'] = $paramData['secondCategory'];
            unset($paramData['secondCategory']);
        }
        //取数限制
        $paramData['limit'] = 50;
        try {
            $result = $this->productService->getClassNewArrivals($paramData);
            return $result;
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 100000001, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 一级分类页面，二级分类产品接口
     */
    public function getSecCategroyProduct()
    {
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData, $this->productParams->secProductRules());
        if (true !== $validate) {
            return (['code' => 1002, 'msg' => $validate]);
        }
        try {
            if (isset($paramData['firstCategory']) && !empty($paramData['firstCategory'])) {
                $paramData['category'] = $paramData['firstCategory'];
                unset($paramData['firstCategory']);
            }
            if (isset($paramData['secondCategory']) && !empty($paramData['secondCategory'])) {
                $paramData['category'] = $paramData['secondCategory'];
                unset($paramData['secondCategory']);
            }
            $paramData['limit'] = 50;

            $result = $this->productService->getSecCategroy($paramData);
            return apiReturn(['code' => 200, 'data' => $result]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 100000005, 'msg' => $e->getMessage()]);
        }
    }


    /**
     * 产品基本详情信息
     */
    public function getBaseSpuInfo()
    {
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getProductInfoRules());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }

        $products = array();
        $data = $this->productService->getBaseSpuInfo($params);

        //过滤字段，APP特殊处理
        if (!empty($data)) {
            //币种符号
            $products['ProductStatus'] = !empty($data['ProductStatus']) ? (int)$data['ProductStatus'] : 0;
            $products['ProductAttributes'] = !empty($data['ProductAttributes']) ? $data['ProductAttributes'] : '';
            $products['WishCount'] = !empty($data['WishCount']) ? (int)$data['WishCount'] : 0;
            $products['CategoryPath'] = !empty($data['CategoryPath']) ? (string)$data['CategoryPath'] : '';
            $products['StoreID'] = !empty($data['StoreID']) ? (int)$data['StoreID'] : 0;
            $products['BrandId'] = !empty($data['BrandId']) ? (int)$data['BrandId'] : 0;
            $products['AvgRating'] = !empty($data['AvgRating']) ? (int)$data['AvgRating'] : 5;
            $products['Title'] = !empty($data['Title']) ? (string)$data['Title'] : '';
            $products['RewrittenUrl'] = !empty($data['RewrittenUrl']) ? (string)$data['RewrittenUrl'] : '';
            $products['LowPrice'] = !empty($data['LowPrice']) ? $data['LowPrice'] : 0;
            $products['HightPrice'] = !empty($data['HightPrice']) ? $data['HightPrice'] : 0;
            $products['OriginalLowPrice'] = !empty($data['OriginalLowPrice']) ? $data['OriginalLowPrice'] : 0;
            $products['OriginalHightPrice'] = !empty($data['OriginalHightPrice']) ? $data['OriginalHightPrice'] : 0;
            $products['ProductImg'] = !empty($data['ProductImg']) ? $data['ProductImg'] : [];
            $products['Discount'] = !empty($data['Discount']) ? $data['Discount'] : 0;
            if (!empty($data['Skus'])) {
                foreach ($data['Skus'] as &$Skus)
                    if (!empty($Skus['SalesAttrs'])) {
                        foreach ($Skus['SalesAttrs'] as $key => $va) {
                            $Skus['SalesAttrs'][$key]['_id'] = !empty($va['_id']) ? (int)$va['_id'] : 0;
                            $Skus['SalesAttrs'][$key]['Name'] = !empty($va['Name']) ? (string)$va['Name'] : '';
                            $Skus['SalesAttrs'][$key]['OptionId'] = !empty($va['OptionId']) ? (string)$va['OptionId'] : '';
                            $Skus['SalesAttrs'][$key]['Value'] = !empty($va['Value']) ? (string)$va['Value'] : 0;
                        }
                    } else {
                        $Skus['SalesAttrs'] = [];
                    }
                if (!empty($Skus['BulkRateSet'])) {
                    $Skus['BulkRateSet']['Discount'] = !empty($Skus['BulkRateSet']['Discount']) ? (float)$Skus['BulkRateSet']['Discount'] : 0;
                    $Skus['BulkRateSet']['SalesPrice'] = !empty($Skus['BulkRateSet']['SalesPrice']) ? (string)$Skus['BulkRateSet']['SalesPrice'] : 0;
                    $Skus['BulkRateSet']['Batches'] = !empty($Skus['BulkRateSet']['Batches']) ? (int)$Skus['BulkRateSet']['Batches'] : 0;
                } else {
                    $Skus['BulkRateSet'] = (object)[];
                }
                $Skus['SalesPrice'] = !empty($Skus['SalesPrice']) ? (string)$Skus['SalesPrice'] : 0;
                $Skus['Inventory'] = !empty($Skus['Inventory']) ? (int)$Skus['Inventory'] : 0;
                $products['Skus'] = $data['Skus'];
            } else {
                $products['Skus'] = [];
                Log::record('SKus异常' . json_encode($params), 'error');
            }
            $products['AttrList'] = !empty($data['AttrList']) ? (array)$data['AttrList'] : [];
            $products['ActivityEndTime'] = !empty($data['ActivityEndTime']) ? (string)$data['ActivityEndTime'] : '';
            $products['ActivityImg'] = !empty($data['ActivityImg']) ? (string)$data['ActivityImg'] : '';
            $products['ActivityTitle'] = !empty($data['ActivityTitle']) ? (string)$data['ActivityTitle'] : '';
        }
        return apiReturn(['code' => 200, 'data' => $products]);

    }

    /*
     * 产品运费模板详情
     */
    public function getSpuShippingInfo()
    {
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getShippingRules());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        //IN国家简码去除空格
        $params['country'] = trim($params['country']);
        try {
            $shipping = $this->productService->getSpuShipping($params);
            return apiReturn(['code' => 200, 'data' => $shipping]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 产品内容详情
     */
    public function getSupOverview()
    {
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getRatingRules());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        try {
            $data = $this->productService->getSpuDescriptions($params);
            return $data;
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());
            return apiReturn(['code' => 1000000068, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 产品星级详情
     * @return mixed
     */
    public function getSupReviews()
    {
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getRatingRules());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        try {
            $data = $this->productService->getSpuReviewsDetail($params);
            return $data;
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000067, 'msg' => $e->getMessage()]);
        }
    }


    /**
     * 新品页面数据接口
     * @return mixed
     */
    public function getNewArrivalsLists()
    {
        try {
            $params = request()->post();
            $paramData = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,//当前语种
                'currency' => isset($paramData['currency']) ? $paramData['currency'] : DEFAULT_CURRENCY,
                'newArrivals' => true,//默认新品
                'category' => isset($params['category']) ? (int)$params['category'] : null,//一级类别查询
                'page' => isset($params['page']) ? (int)$params['page'] : 1,
                'salesRank' => isset($params['salesRank']) ? $params['salesRank'] : null,
                'salesCounts' => isset($params['salesCounts']) ? $params['salesCounts'] : null,
                'addTimeSort' => isset($params['addTimeSort']) ? $params['addTimeSort'] : null,
                'reviewCount' => isset($params['reviewCount']) ? $params['reviewCount'] : null,
                'priceSort' => isset($params['priceSort']) ? $params['priceSort'] : null,
                'lowPrice' => isset($params['lowPrice']) ? $params['lowPrice'] : null,
                'hightPrice' => isset($params['hightPrice']) ? $params['hightPrice'] : null,
                'country' => isset($params['country']) ? $params['country'] : 'US',
            ];
            $result = $this->productService->newArrivalList($paramData);
            return apiReturn(['code' => 200, 'data' => empty($result) ? getDefaultData($result) : $result]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * topseller 页面数据
     * @return mixed
     */
    public function getTopSellerLists()
    {
        try {
            $paramData = request()->post();

            $result = $this->productService->topSellerLists($paramData);
            return apiReturn(['code' => 200, 'data' => empty($result) ? getDefaultData($result) : $result]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * under $0.99页面数据
     * @return mixed
     */
    public function getUnderPriceLists()
    {
        try {
            $paramData = request()->post();

            $params['key'] = isset($params['key']) && !empty($params['key']) ? $params['key'] : '0.99';
            $params['page'] = isset($params['page']) ? $params['page'] : 1;

            $data = $this->productService->underPriceLists($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * MVP页面数据
     * @return mixed
     */
    public function getMvpLists()
    {
        try {
            $paramData = request()->post();

            $paramData['isMvp'] = true;
            $result = $this->productService->mvpProducts($paramData);
            return apiReturn(['code' => 200, 'data' => empty($result) ? getDefaultData($result) : $result]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);

        }

    }


    /**
     * StaffPicks 页面数据
     * @return mixed
     */
    public function getStaffPicksLists()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->staffPicksProducts($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Presale 页面数据
     * @return mixed
     */
    public function getPresaleLists()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->presaleProducts($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 根据SKUID，获取产品ID
     * @return mixed
     */
    public function getProductIdBySkuId()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->getProductIdBySkuId($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 根据SKUIDS，获取产品IDS
     * @return mixed
     */
    public function getProductIdBySkuIdS()
    {
        $paramData = request()->post();

        $data = $this->productService->getProductIdBySkuIds($paramData);
        if (false == $data) {
            return apiReturn(['code' => 1000000021, 'msg' => '请求失败']);
        }
        return apiReturn(['code' => 200, 'data' => $data]);
    }

    /**
     * 品牌页面列表产品数据
     */
    public function getBrandProduct()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->selectBrandProduct($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 查找下个待审核产品
     */
    public function getNextAuditProduct()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->getNextAuditProduct($paramData);
            if (!is_array($data)) {
                return apiReturn(['code' => 1000000021, 'msg' => '请求失败']);
            }
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }

    }

    /**
     * 根据SkuIdArr扣减库存，按具体的SkuId返回成功与否
     */
    public function editInventoryBySkuIdArr()
    {
        $paramData = request()->post();

        $data = $this->productService->editInventoryBySkuIdArr($paramData);
        if (!is_array($data)) {
            return apiReturn(['code' => 1000000021, 'msg' => '请求失败']);
        }
        return apiReturn(['code' => 200, 'data' => $data]);
    }

    /**
     * 获取affiliate信息
     */
    public function getAffiliateInfo()
    {
        $paramData = request()->post();

        try {
            $data = $this->productService->getAffiliateInfo($paramData);
            if (!is_array($data)) {
                return apiReturn(['code' => 1000000021, 'msg' => '请求失败']);
            }
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * cart产品列表，获取信息，计算运费
     */
    public function getCartProductList()
    {
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getRatingRules());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        try {
            $data = $this->productService->getCartProductList($params);
            return $data;
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }


    /**
     * cart产品列表，获取信息，计算运费
     */
    public function getProductToShipping()
    {
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getRatingRules());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        try {
            $data = $this->productService->getProductToShipping($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    public function getNewArrivalsTemptale()
    {
        try {
            $params = request()->post();
            $data = $this->productService->getNewArrivalsTemptale($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    public function getSmartphonesTemplate()
    {
        try {
            $params = request()->post();
            $data = $this->productService->getSmartphonesTemplate($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    public function getElectronicsTemplate()
    {
        try {
            $params = request()->post();
            $data = $this->productService->getElectronicsTemplate($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    public function getDiyAndFunTemplate()
    {
        try {
            $params = request()->post();
            $data = $this->productService->getDiyAndFunTemplate($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    public function getIndoorAndOutDoorTemplate()
    {
        try {
            $params = request()->post();
            $data = $this->productService->getIndoorAndOutDoorTemplate($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    public function getBrandsTemplate()
    {
        try {
            $params = request()->post();
            $data = $this->productService->getBrandsTemplate($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * App接口 - 返回首页数据
     */
    public function getNewBanner()
    {
        $params = request()->post();
        $params['key'] = 'app_new_banner';
        try {
            $commonService = new CommonService();
            $data = $commonService->getAppBanner($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            return apiReturn(['code' => 1000000070, 'msg' => $e->getMessage()]);
        }
    }

    /*
    * 新品的优惠劵接口
    */
    public function getNewCoupon()
    {
        $params = request()->post();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;//当前语种
        $currency = isset($paramData['currency']) ? $paramData['currency'] : DEFAULT_CURRENCY;
        $country = isset($params['country']) ? $params['country'] : 'US';
        $configKey = empty($coupon_id) ? 'hotProduct' : $coupon_id;
        $indexService = new IndexService();
        $pageConfig = $indexService->hotProductPageConfig($configKey, $lang, $currency, $country);
        return apiJosn(['code' => 200, 'data' => $pageConfig]);
    }

    public function getNewcategories()
    {
        $params = request()->post();
        $coupon_id = !empty($params['coupon_id']) ? $params['coupon_id'] : 0;
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;//当前语种
        $categoryService = new CategoryService();
        if (!empty($coupon_id)) {
            //指定coupon页面
            $categories = $categoryService->getCatetoryProductCount(['key' => 'Coupon', 'lang' => $lang, 'coupon_id' => $coupon_id]);
        } else {
            //hotproduct
            $categories = $categoryService->getCatetoryProductCount(['key' => 'HotProduct', 'lang' => $lang]);
        }
        return apiJosn(['code' => 200, 'data' => $categories]);
    }
}
