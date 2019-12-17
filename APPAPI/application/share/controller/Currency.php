<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;

/**
 * 币种
 */
class Currency extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

	//缓存时间：86400=1天
	const CACHE_TIME =86400;

    /**
     * 获取汇率
     * @return mixed
     */
    public function getExchangeRate(){
        //todo 公用缓存
        $currency = doCurl(config("currency_url"));
//        if(!empty($currency)){
//            Cache::set('DX_EXCHANGE_RATE',$currency,self::CACHE_TIME);
//        }
        return apiReturn(['code'=>200,'data'=>$currency]);
    }

    /**
     * 获取币种列表
     */
    public function getCurrencyList(){
    	/* 固定东西，直接输出，不放缓存
    	*/
    	$currencyList = config("Currency");
    	return apiReturn(['code'=>200,'data'=>$currencyList]);
     }

     public function phpinfo(){
     	phpinfo();
     }

}
