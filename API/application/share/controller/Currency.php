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

    /**
     * 火币网所有币汇率
     *
        字段名称	数据类型	描述
        amount	float	以基础币种计量的交易量
        count	integer	交易笔数
        open	float	开盘价
        close	float	最新价
        low	float	最低价
        high	float	最高价
        vol	float	以报价币种计量的交易量
        symbol	string	交易对，例如btcusdt, ethbtc
     * @return mixed
     */
    public function getHuoBiTickers(){
        $currency = doCurl('https://api.huobi.pro/market/tickers');
        //$json = '{"status":"ok","ts":1572234884920,"data":[{"symbol":"btcusdt","open":9524.44,"high":9897.12,"low":9378.89,"close":9640,"amount":68161.78515533681,"vol":648612237.8211361,"count":622228},{
        //    "symbol":"omgusdt","open":0.9607,"high":1.0856,"low":0.9522,"close":0.9904,"amount":5031468.719079645,"vol":4831312.909789825,"count":32736}]}';
        //$currency =  json_decode($json,true);
        return apiReturn(['code'=>200,'data'=>$currency]);
    }
}
