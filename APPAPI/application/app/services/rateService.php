<?php
namespace app\app\services;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Monlog;

class rateService extends BaseService {

    public $redis;
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取货币转换的汇率
     * @param string $From
     * @param string $To
     * @return rate
     */
    public function getCurrentRate($To,$From='USD'){
        $rateRedis = '';
        //判断缓存
        if(config('cache_switch_on')){
            $rateRedis = $this->redis->get(EXCHANGE_RATE_.$To.'_'.$From);
        }
        if(empty($rateRedis)){
            $base_api = new BaseApi();
            $result = $base_api->getExchangeRate();
            /*$result = doCurl(MALL_API . '/share/currency/getExchangeRate', [], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if (empty($result)) {
                return ['code' => 20000001, 'msg' => 'NULL'];
            }
            $rate = $result['data'];
            if (!empty($rate) && is_array($rate)) {
                foreach ($rate as $k => $v) {
                    if ($v['From'] == $From && $v['To'] == $To) {
                        $this->redis->set(EXCHANGE_RATE_.$To.'_'.$From,$v['Rate'],CACHE_HOUR);
                        return $v['Rate'];
                    }
                }
            }
        }
        return $rateRedis;
    }

}