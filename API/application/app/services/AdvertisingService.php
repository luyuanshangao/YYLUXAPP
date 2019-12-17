<?php
namespace app\app\services;

use app\app\model\AdvertisingModel;
use think\Cache;


/**
 * 广告业务层
 */
class AdvertisingService extends BaseService
{

    /**
     * 获取单个详情
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getAdvertisingInfo($params){
        $result = array();
        $key = $params['key'];
        if(config('cache_switch_on')){
            $result = $this->redis->get(ADVERTISING_INFO_BY_.$key);
        }
        if(empty($result)){
            $result = (new AdvertisingModel())->find($params);
            if(!empty($result)){
                $this->redis->set(ADVERTISING_INFO_BY_.$key,$result,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * 获取列表
     * @param $params
     * @return data
     */
    public function getLists($params){
        return (new AdvertisingModel())->lists($params);
    }


    /**
     * 获取单个详情
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getAppHomeBanner($params){
        $lang = isset($params['lang']) ? $params['lang'] : 'en';
        $result = array();
        $key = $params['key'];
        if(config('cache_switch_on')){
            $result = $this->redis->get(ADVERTISING_INFO_BY_.$key);
        }
        if(empty($result)){
            $result = (new AdvertisingModel())->find($params);
            if(!empty($result)){
                $this->redis->set(ADVERTISING_INFO_BY_.$key,$result,CACHE_DAY);
            }
        }
        $data  = $this->getBannerInfos($result,$lang);
        return $data;
    }
}
