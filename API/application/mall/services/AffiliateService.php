<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\AdvertisingModel;
use app\mall\model\AffiliateModel;
use think\Cache;


/**
 * Affiliate业务层
 */
class AffiliateService extends BaseService
{


    public function findAffiliate($params){
        $model = new AffiliateModel();
        $filter = $model->find($params['id']);
        $data['id'] = isset($filter['_id']) ? $filter['_id'] : '';
        $data['html'] = isset($filter['Html']) ? $filter['Html'] : '';
        return $data;

/*
        $affiliateArray = array();
        $model = new AffiliateModel();
        if(config('cache_switch_on')) {
            $affiliateArray = $this->redis->get(AFFILIATE_LIST.'_'.$params['id']);
//            $affiliateArray = $this->redis->hGetAll(AFFILIATE_LIST);
        }
        if(empty($affiliateArray)){
            $lists = $model->find($params['id']);
            if(!empty($lists)){
                foreach($lists as $list){
                    $data['id'] = isset($list['_id']) ? $list['_id'] : '';
                    if(empty($data['id'])){
                        continue;
                    }
                    $data['html'] = $list['Html'];
                    $this->redis->hSet(AFFILIATE_LIST,$data['id'],json_encode($data));
                }
            }
        }
        $affiliate = $this->redis->hGet(AFFILIATE_LIST,$params['id']);
        if(empty($affiliate)){
            $lists = $model->lists();
            $filter = CommonLib::filterArrayByKey($lists,'_id',$params['id']);
            $data['id'] = isset($filter['_id']) ? $filter['_id'] : '';
            if(empty($data['id'])){
                return array();
            }
            $data['html'] = $filter['Html'];
            if($filter){
                $affiliate = json_encode($data);
                $this->redis->hSet(AFFILIATE_LIST,$data['id'],$affiliate);
            }
        }
        return json_decode($affiliate,true);
*/

    }
}
