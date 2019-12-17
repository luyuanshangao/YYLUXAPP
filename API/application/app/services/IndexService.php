<?php
namespace app\app\services;

use app\admin\dxcommon\BaseApi;
use think\Monlog;

/**
 * 创建：tinghu.liu
 * 功能：index Services
 * 时间：2018-10-12
 */
class IndexService extends BaseService {

    const EIGHT = 8;
    const FOUR= 4;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取单个国家
     * @return array|mixed
     */
    public function getCountryInfo($params){
        $result = [];
//        if(config('cache_switch_on')){
        $result = $this->redis->get(COUNTRY_BY_.$params['Code']);
//        }
        if(empty($result)){
            $base_api = new BaseApi();
            $request = $base_api->regionFind(['Code'=>$params['Code'],'ParentID'=>0]);
            /*$request = doCurl(MALL_API . '/share/region/find', ['Code'=>$params['Code'],'ParentID'=>0], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if ($request['code'] != 200) {
                //Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/share/region/find',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return $result;
    }

    /**
     * 币种列表
     */
    public function getCurrencyList(){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(CURRENCY_MENU);
        }
        if(empty($result)){
            $base_api = new BaseApi();
            $request = $base_api->getCurrencyList();
            /*$request = doCurl(MALL_API . 'share/currency/getCurrencyList', [], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if ($request['code'] != 200) {
                Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/currency/getCurrencyList',$request);
                return $result;
            }
            $result = $request['data'];
            //缓存
            if(!empty($result)){
                $this->redis->set(CURRENCY_MENU,$request['data'],CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 语种列表
     */
    public function getLangs(){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get(LANG_MENU);
        }
        if(empty($result)){
            $base_api = new BaseApi();
            $request = $base_api->getLangList();
            /*$request = doCurl(MALL_API . '/share/header/langs', [], [
                'access_token' => $this->getAccessToken()
            ]);*/
            if ($request['code'] != 200) {
                //错误信息
                Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/share/header/langs',$request);
                return $result;
            }
            //数据返回
            $result = isset($request['data']) && is_array($request['data']) ? $request['data'] : [];
            //缓存
            if(!empty($result)){
                $this->redis->set(LANG_MENU,$result,CACHE_DAY);
            }
        }
        return $result;
    }

}