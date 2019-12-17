<?php
namespace app\app\controller;

use app\common\services\ReviewsService;
use app\app\services\WishService;
use app\common\params\WishParams;
use think\Cookie;
use \think\Session;
use \think\Cache;
use \think\Db;
use \app\common\controller\Base;
use \think\Cache\Driver\Redis;
use \think\Loader;
use app\common\controller\AppBase;
use app\app\dxcommon\BaseApi;
/**
 * 开发：钟宁
 * 功能：添加收藏
 * 时间：2018-05-30
 */
class Wish extends AppBase
{

    public $wishService;
    public function __construct(){
        parent::__construct();
        $this->wishService = new WishService();
    }

    /**
     *添加收藏
	 */
    public function addWish()
    {
        $params = input();
        //获取登录信息
        $params['CustomerID'] = isset($params['CustomerID']) ? $params['CustomerID'] : 0;
        $lang=isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $currency = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $country = isset($params['country']) ? $params['country'] : 'US';
        if(empty($params['CustomerID'])){
            $returnData['code'] = 10001;
            $returnData['msg'] = lang('tips_10001');
            return json($returnData);
        }
        $params['Username'] = isset($Cstomer['UserName']) ? $Cstomer['UserName'] : '';
        $params['SPU'] = isset($params['SPU']) ? $params['SPU'] : '';
        if(empty($params['SPU'])){
            return json(['code'=>'10000006','msg'=>'spu required']);
        }
        //$params['PriceWhenAdded'] = isset($params['PriceWhenAdded']) ? $params['PriceWhenAdded'] : 'PriceWhenAdded';
        //$params['ShippingWhenAdded'] = isset($params['ShippingWhenAdded']) ? $params['ShippingWhenAdded'] : 'ShippingWhenAdded';
        //$params['Comments'] = isset($params['Comments']) ? $params['Comments'] : 'Comments';
        $params['CategoryID'] = isset($params['categoryID']) ? $params['categoryID'] : '0';
        //参数校验
        $validate = $this->validate($params,(new WishParams())->addWishRules());
        if(true !== $validate){
            return json(['code'=>1002, 'msg'=>$validate]);
        }
        $resData = $this->wishService->addWish($params,$params['SPU'].'_'.$lang.'_'.$currency.'_'.$country);
        return json($resData);
    }

    /**
     * 用户是否收藏
     */
    public function isWish(){
        $params = input();
        $paramsRequest['SPU'] = isset($params['spu']) ? $params['spu'] : '';
        //参数校验
        $validate = $this->validate($paramsRequest,(new WishParams())->isWishRules());
        if(true !== $validate){
            return json(['code'=>1002, 'msg'=>$validate]);
        }

        $login = $this->getLoginInfo();
        $paramsRequest['CustomerID'] = isset($login['ID']) ? $login['ID'] : 0;
        if(empty($paramsRequest['CustomerID'])){
            return json(['code'=>'200','data'=>false]);
        }
        $resData = $this->wishService->isWish($paramsRequest);
        return json(['code'=>'200','data'=>$resData]);
    }

    /*
     * 获取分类
     */
    public function getCategory(){
        $params = input();
        $cstomer['ID']= isset($params['CustomerID']) ? $params['CustomerID'] : 0;
        $baseApi = new BaseApi();
        $WishCategoryID = $baseApi->getWishCategoryID(['CustomerID'=>$cstomer['ID']]);
        $WishCategoryID['data'] = array_filter($WishCategoryID['data']);
        $WishCategoryData = '';
       $lang=isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        if(isset($WishCategoryID['data']) && !empty($WishCategoryID['data'])){
            $WishCategoryData = $baseApi->getCategoryDataByCategoryIDData($WishCategoryID['data']);
            $WishCategoryData = $WishCategoryData['data'];
        }
        if(isset($WishCategoryID['data']) && !empty($WishCategoryID['data'])){
            $WishCategoryData = $baseApi->getCategoryDataByCategoryIDData($WishCategoryID['data']);
            $WishCategoryData = $WishCategoryData['data'];
        }
        return json(['code'=>'200','data'=>$WishCategoryData]);

    }



}
