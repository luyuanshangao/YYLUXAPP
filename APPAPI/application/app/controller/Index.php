<?php
namespace app\app\controller;

use app\app\model\ProductTopsellerDay;
use app\common\helpers\CommonLib;
use app\common\services\CategoryService;
use app\app\services\IndexService;
use app\common\services\logService;
use app\app\services\ProductActivityService;
use app\app\services\ProductService;
use app\common\services\rateService;
use app\mallextend\controller\Product;
use think\Cookie;
use think\Exception;
use think\Log;
use think\Monlog;
use think\Request;
use \think\Session;
use \think\Cache;
use \think\Db;
use \app\common\controller\AppBase;
use \think\Cache\Driver\Redis;
use \think\Loader;
use app\app\model\ProductClassModel;

/**
 * 开发：钟宁
 * 功能：商城首页数据
 * 时间：2018-05-25
 */
class Index extends AppBase
{
    const FIVE = 5;
    public $indexService;
    protected $noNeedLogin = ['*'];
    protected $productService;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
//        $this->indexService = new IndexService();
    }

    /*
     * 首页头部数据
     */
    public function getHeadrIndex()
    {
        //轮播图
        $banner = $this->getBanner();
        //分类图标和数据
        $class_img = $this->getClassImg();
        //品牌榜单
        $brand = $this->getBrand();
        $data=[];
        $data['banner']=$banner;
        $data['class_img']=$class_img;
        $data['brand']=$brand;
        return $this->result($data);
    }

    public function getBanner()
    {
        $banner = config('top_banner');
        $banner = [
            [
                'link' => "https://www.dx.com/lp/20191211_umimobile_en?utm_source=dx&utm_medium=mobile&utm_campaign=20191211_umimobile_en",
                'src' => IMG_USER."index/banner/banner_1.png",
                'title' => "Xiaomi",
            ],
            [
                'link' => "https://www.dx.com/lp/20191211_umimobile_en?utm_source=dx&utm_medium=mobile&utm_campaign=20191211_umimobile_en",
                'src' => IMG_USER."index/banner/banner_2.png",
                'title' => "Xiaomi",
            ],
            [
                'link' => "https://www.dx.com/lp/20191211_umimobile_en?utm_source=dx&utm_medium=mobile&utm_campaign=20191211_umimobile_en",
                'src' => IMG_USER."index/banner/banner_3.png",
                'title' => "Xiaomi",
            ],
        ];
        return $banner;
    }

    public function getClassImg()
    {
        $class_img = config('class_img');
        $class_img = [
            [
                'class_id' => "0",
                'src' => IMG_USER."index/class/class_0.png",
                'title' => "所有分类",
            ],
            [
                'class_id' => "1",
                'src' => IMG_USER."index/class/class_1.png",
                'title' => "服饰",
            ],
            [
                'class_id' => "2",
                'src' => IMG_USER."index/class/class_2.png",
                'title' => "箱包",
            ],
            [
                'class_id' => "3",
                'src' => IMG_USER."index/class/class_3.png",
                'title' => "配饰",
            ],
            [
                'class_id' => "4",
                'src' => IMG_USER."index/class/class_4.png",
                'title' => "美妆",
            ],
            [
                'class_id' => "5",
                'src' => IMG_USER."index/class/class_5.png",
                'title' => "鞋帽",
            ],
            [
                'class_id' => "6",
                'src' => IMG_USER."index/class/class_6.png",
                'title' => "品牌",
            ],
            [
                'class_id' => "7",
                'src' => IMG_USER."index/class/class_7.png",
                'title' => "精品钜惠",
            ],
        ];
        return $class_img;
    }

    public function getBrand()
    {
        $brand = config('brand');
        $products1=$this->getProducts([],2);
        $products2=$this->getProducts([],2);
        $products3=$this->getProducts([],2);
        $brand = [
            [
                'brand'=>1,
                'src' =>IMG_USER."index/brand/brand_1.png",
                'img' =>IMG_USER."index/brand/brand_4.png",
                'product' =>$products1,
            ],
            [
                'brand'=>2,
                'src' =>IMG_USER."index/brand/brand_1.png",
                'img' =>IMG_USER."index/brand/brand_5.png",
                'product' => $products2,
            ],
            [
                'brand'=>3,
                'src' =>IMG_USER."index/brand/brand_1.png",
                'img' =>IMG_USER."index/brand/brand_6.png",
                'product' => $products3,

            ],
        ];
        return $brand;
    }

    /*
     * 首页中间数据
     */
    public function getCenterIndex()
    {
        //热销单品
        $paramTop['salesCounts']=1;
        $top_sellers = $this->getProducts($paramTop);
        //明星热款
        $paramStar['addTimeSort']=1;
        $star_sellers = $this->getProducts($paramStar);
        //中间广告位
        $center_img = [
            'img'=>IMG_USER."index/boutique/boutique.png",
            'url'=>"https://c.dx.com/collection/banner/201912/20191211/Power3_730x450_en.jpg",
        ];
        $data=[];
        $data['top_sellers']=$top_sellers;
        $data['star_sellers']=$star_sellers;
        $data['center_img']=$center_img;
        return $this->result($data);
    }

    /*
    * 首页底部分页产品数据
    */
    public function getFooterIndex()
    {
        //分页产品数据

    }

    /*
     *首页数据接口
     */
    public function getProducts($paramData,$count=3)
    {
        //取数限制
        $paramData['limit'] = 50;
        $result = $this->productService->getNewProduct($paramData);
        if($result['code'] != 200){
            return $this->result([], 900, '产品不存在');
        }
        $products = $result['data'];
        if(!empty($products) && is_array($products)){
            //取数限制
            if(isset($count)){
                //随机打乱
                shuffle($products);
                $products = CommonLib::getRandArray($products,$count);
            }
        }
        return $products;
    }
    /**
     * 昨天销量50的产品
     * @return \think\response\Json
     */
    public function getTopProduct()
    {
        try {
            $params = input();
            $products = array();
            $params = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,
                'currency' => isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY,
                'count' => isset($params['count']) ? $params['count'] : 9,
                'country' => isset($params['country']) ? $params['country'] : 'US',
            ];
            $resData = $this->productService->getTopProductByOrder($params);
            if (!empty($resData)) {
                $products = $resData;
                //随机打乱
                shuffle($products);
                //取数限制
                if (isset($params['count'])) {
                    $products = CommonLib::getRandArray($products, $params['count']);
                }
            }
            return apiJosn(['code' => 200, 'data' => $products]);
        } catch (Exception $e) {
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            return apiJosn(['code' => 1001, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * flashDeals 5个限时商品
     */
    public function getFlashDeals()
    {
        try {
            $params = input();
            $params = [
                'page' => isset($params['page']) ? $params['page'] : 1,
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,
                'currency' => isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY,
                'country' => isset($params['country']) ? $params['country'] : 'US',
            ];
            $resData = (new ProductActivityService())->getHomeFlashDeals($params);

            $resData['code'] = 200;
            return apiJosn($resData);
        } catch (Exception $e) {
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            $resData['code'] = 1200;
            $resData['msg'] = $e->getMessage();
            return apiJosn($resData);
        }
    }

    /**
     * 首页展示可领取的coupn，登录前后切换图
     * @return \think\response\Json
     */
    public function indexCoupnoInfo()
    {
        try {
            $result = array();
            $params = input();
            $params = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,
            ];
            //首页展示coupon
            $coupons = $this->indexService->getIndexCouponsShow($params['lang']);
            $result['coupons'] = !empty($coupons) ? array_values($coupons) : array();
            $coupons_detail_img = $this->indexService->getIndexCouponsDetail($params['lang']);
            $result['login'] = !empty($coupons_detail_img['loginCoupon']) ? $coupons_detail_img['loginCoupon'] : array();
            $result['notLogin'] = !empty($coupons_detail_img['notLoginCoupon']) ? $coupons_detail_img['notLoginCoupon'] : array();
            $data['code'] = 200;
            $data['data'] = $result;
            return apiJosn($data);
        } catch (Exception $e) {
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            $resData['code'] = 1200;
            $resData['msg'] = $e->getMessage();
            return apiJosn($resData);
        }
    }


    /**
     * 1.展示3个配置的分类信息，配置3个分配的背景图；
     * 2.    根据配置的分类ID,展示分类名称；
     * 3.    根据配置的分类ID，取半年内有动销的产品，随机展示3个产品图；
     * 4.    点击产品图片链接，进入该分类列表页，展示该分类ID下的所有产品信息
     * @return \think\response\Json
     */
    public function getCategoryTopData()
    {
        try {
            $params = input();
            $params = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,
            ];
            //首页展示coupon
            $result = $this->indexService->getTopDataByConfigCategory($params);
            $data['code'] = 200;
            $data['data'] = $result;
            return apiJosn($data);
        } catch (Exception $e) {
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            $data['code'] = 1200;
            $data['msg'] = $e->getMessage();
            return apiJosn($data);
        }
    }

}
