<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\ProductParams;
use app\mall\services\BaseService;
use app\mall\services\ConfigDataService;
use app\mall\services\ProductService;
use think\Db;
use think\Exception;
use think\Monlog;


/**
 * 产品接口
 */
class Product extends Base
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
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        try {
            $data = $this->productService->getProduct($paramData);
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
//        \think\Log::pathlog('time1 = ',microtime(),'getSecCategroyProduct.log');
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
//            \think\Log::pathlog('time2 = ',microtime(),'getSecCategroyProduct.log');
            $result = $this->productService->getSecCategroy($paramData);
            return $result;
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 100000005, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 二级分类页面
     *
     * 产品列表页面数据接口
     * @return mixed
     */
    public function getCategroyPageList()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->getCategoryPageLists($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
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
        try {
            $data = $this->productService->getBaseSpuInfo($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, 'line=' . $e->getLine() . ' error=' . $e->getMessage());
            return apiReturn(['code' => 1000000066, 'msg' => 'line=' . $e->getLine() . ' error=' . $e->getMessage()]);
        }
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
            $data = $this->productService->getSpuShipping($params);
            return $data;
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
        $validate = $this->validate($params, (new ProductParams())->getViewHistoryRules());
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
        $validate = $this->validate($params, (new ProductParams())->getSupReviewsRule());
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
            $paramData = request()->post();

            $data = $this->productService->newArrivalList($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
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

            $data = $this->productService->topSellerLists($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
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

            $paramData += [
                'isMvp' => true
            ];
            $data = $this->productService->mvpProducts($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
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

    /**
     * google推荐产品code
     */
    public function getProductLocalCode()
    {
        $params = request()->post();
        try {
            $data = $this->productService->getProductLocalCode($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());
            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 根据SPUID，返回产品列表信息
     */
    public function getProductListBySkus()
    {
        $paramData = input();
        try {
            $paramData['lang'] = isset($paramData['lang']) ? $paramData['lang'] : DEFAULT_LANG;
            $paramData['currency'] = isset($paramData['currency']) ? $paramData['currency'] : DEFAULT_CURRENCY;
            //参数校验
            $validate = $this->validate($paramData, (new ProductParams())->getList());
            if (true !== $validate) {
                return jsonp(['code' => 1002, 'msg' => $validate]);
            }
            $data = $this->productService->getProductListBySku($paramData);
            if (!is_array($data)) {
                return jsonp(['code' => 1000000021, 'msg' => '请求失败']);
            }
            return jsonp(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 根据SPUID，返回产品列表信息
     */
    public function getProductListBySpus()
    {
        $paramData = input();
        try {
            $paramData['lang'] = isset($paramData['lang']) ? $paramData['lang'] : DEFAULT_LANG;
            $paramData['currency'] = isset($paramData['currency']) ? $paramData['currency'] : DEFAULT_CURRENCY;
            //LP页面支持国家定价
            $paramData['country'] = isset($paramData['country']) ? $paramData['country'] : 'US';
            //参数校验
            $validate = $this->validate($paramData, (new ProductParams())->getSpusList());
            if (true !== $validate) {
                return jsonp(['code' => 1002, 'msg' => $validate]);
            }
            $config_data = array();
            //获取活动配置
            $icon_config = (new ConfigDataService())->getSystemConfigs('MallIconConfiguration');
            if (isset($icon_config['ConfigValue']) && !empty($icon_config['ConfigValue'])) {
                $config_data = json_decode(htmlspecialchars_decode($icon_config['ConfigValue']), true);
            }
            $data = $this->productService->getProductListBySpu($paramData);
            if (is_array($data) && !empty($data)) {
                foreach ($data as $key => $product) {
                    $data[$key]['isMvp'] = 0;
                    $data[$key]['isNewarrivals'] = 0;
                    //新增是否是mvp
                    if (isset($product['tagName']) && $product['tagName'] == 'tag-mvp') {
                        $data[$key]['isMvp'] = 1;
                    }
                    //新增是否是新品
                    $time = isset($product['AddTime']) ? $product['AddTime'] : 0;
                    if ($time > strtotime('-15 day')) {
                        $data[$key]['isNewarrivals'] = 1;
                    }
                    //活动图标，比如双11 add by zhongning 20191108
                    if (!empty($product['IsActivity']) && !empty($config_data['activity_id'])) {
                        if ($product['IsActivity'] == $config_data['activity_id']) {
                            $data[$key]['ActivityStatus'] = 1;
                            $data[$key]['ActivityImg'] = !empty($config_data['img_url']) ? $config_data['img_url'] : '';
                        }
                    }
                }
            }
            if (!is_array($data)) {
                return jsonp(['code' => 1000000021, 'msg' => 'request error']);
            }
            if (isset($paramData['from_flag']) && $paramData['from_flag'] == 100) {
                return apiReturn(['code' => 200, 'data' => $data]);
            } else {
                return jsonp(['code' => 200, 'data' => $data]);
            }
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return jsonp(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 根据SPUID，返回产品列表信息
     */
    public function getEdmActivityProductListBySpus($paramData = array())
    {
        $paramData = empty($paramData) ? input() : $paramData;
        try {
            $paramData['lang'] = isset($paramData['lang']) ? $paramData['lang'] : DEFAULT_LANG;
            $paramData['currency'] = isset($paramData['currency']) ? $paramData['currency'] : DEFAULT_CURRENCY;
            //LP页面支持国家定价
            $paramData['country'] = isset($paramData['country']) ? $paramData['country'] : 'US';
            $templateType = isset($paramData['templateType']) ? $paramData['templateType'] : 'landing_pg';
            //参数校验
            $validate = $this->validate($paramData, (new ProductParams())->getSpusList());
            if (true !== $validate) {
                return jsonp(['code' => 1002, 'msg' => $validate]);
            }
            $data = $this->productService->getProductListBySpu($paramData);
            if (is_array($data) && !empty($data)) {
                $data['currencyCode'] = DEFAULT_CURRENCY;
                $data['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
                if (DEFAULT_CURRENCY != $paramData['currency']) {
                    $data['currencyCode'] = $paramData['currency'];
                    $data['currencyCodeSymbol'] = (new BaseService())->getCurrencyCode($paramData['currency']);
                }

                foreach ($data as $key => $product) {
                    if (!isset($product['id'])) continue;
                    $data[$key]['isMvp'] = 0;
                    $data[$key]['isNewarrivals'] = 0;
                    //新增是否是mvp
                    if (isset($product['tagName']) && $product['tagName'] == 'tag-mvp') {
                        $data[$key]['isMvp'] = 1;
                    }
                    //新增是否是新品
                    $time = isset($product['AddTime']) ? $product['AddTime'] : 0;
                    if ($time > strtotime('-15 day')) {
                        $data[$key]['isNewarrivals'] = 1;
                    }
                    //拆分价格
                    $LowPrice = explode('.', $product['LowPrice']);
                    $data[$key]['IntPrice'] = empty($LowPrice[0]) ? 0 : $LowPrice[0];
                    $data[$key]['FloatPrice'] = empty($LowPrice[1]) ? 00 : $LowPrice[1];

                    if (!empty($product['OriginalLowPrice'])) {
                        $LowPrice = explode('.', $product['OriginalLowPrice']);
                        $data[$key]['OriginaIntPrice'] = empty($LowPrice[0]) ? 0 : $LowPrice[0];
                        $data[$key]['OriginaFloatPrice'] = empty($LowPrice[1]) ? 00 : $LowPrice[1];
                    }

                    //图片拼接
                    if (strstr($product['FirstProductImage'], 'newprdimgs') !== false) {
                        $img = explode('.', $product['FirstProductImage']);
                        if (!empty($img[0]) && !empty($img[1])) {
                            $data[$key]['FirstProductImage'] = $img[0] . '_300x300.' . $img[1];
                        }
                    }
                    $data[$key]['FirstProductImage'] = 'https://img.dxcdn.com' . $data[$key]['FirstProductImage'];
                    if (!empty($product['Discount'])) {
                        $data[$key]['Discount'] = $product['Discount'] * 100;
                    }

                    //产品地址拼接,要区分移动端还是PC地址
                    if ($templateType == 'm_landing_pg') {
                        $data[$key]['LinkUrl'] = 'https://m.dx.com' . $data[$key]['LinkUrl'] . '?ta=' . $paramData['country'] . '&tc=' . $paramData['currency'] . '&lp=3';
                    } else {
                        $data[$key]['LinkUrl'] = 'https://dx.com' . $data[$key]['LinkUrl'] . '?ta=' . $paramData['country'] . '&tc=' . $paramData['currency'] . '&lp=1';
                    }
                }
            }
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage() . ' line:' . $e->getLine() . ' file:' . $e->getFile()]);
        }
    }

    /**
     *  lp页面
     * @return mixed
     */
    public function getLandingPage()
    {
        $paramData = input();
        $string = controller('admin/EDMActivity')->viewSource($paramData['title'], $paramData['country']);
        return apiReturn(['code' => 200, 'data' => $string]);
    }

    /**
     * 特殊产品404页面推荐数据
     * @return mixed
     */
    public function getRecommendNotFoundProduct()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->getRecommendNotFoundProduct($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * TOP50产品
     * @return mixed
     */
    public function getTopProductByOrder()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->getTopProductByOrder($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 根据配置的分类ID，取数据库已经跑好的数据
     */
    public function getTopDataByConfigCategory()
    {
        try {
            $paramData = request()->post();

            $data = $this->productService->getTopDataByConfigCategory($paramData);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $paramData, null, $e->getMessage());

            return apiReturn(['code' => 1000000021, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 首页coupon产品展示
     */
    public function getCouponsProduct()
    {
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params, (new ProductParams())->getCouponsProduct());
        if (true !== $validate) {
            return apiReturn(['code' => 1002, 'msg' => $validate]);
        }
        try {
            $data = $this->productService->getCouponsProduct($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 指定coupon产品列表
     */
    public function getCouponProductList()
    {
        $params = request()->post();
        //参数校验
        try {
            $data = $this->productService->getCouponProductList($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 热门产品，moretolove
     */
    public function getHotProductList()
    {
        $params = request()->post();

        try {
            $data = $this->productService->getHotProductList($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 新品的热卖产品
     */
    public function getHotNewArrivalsList()
    {
        $params = request()->post();
        try {
            $data = $this->productService->getHotNewArrivalsList($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        } catch (Exception $e) {
            //错误日志
            Monlog::write(LOGS_MALL_API, 'error', __METHOD__, __FUNCTION__, $params, null, $e->getMessage());

            return apiReturn(['code' => 1000000066, 'msg' => $e->getMessage()]);
        }
    }
}
