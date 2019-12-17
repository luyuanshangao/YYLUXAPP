<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use think\Cache;
use app\app\model\NoticesModel;

/**
 * 基础配置数据
 */
class NoticesService extends  BaseService
{
    //const CACHE_KEY = 'DX_DATA_COMMON_';
    //const CACHE_TIME = 3600;
    //const CACHE_TIME_DAY = 86400;

    /**
     * 获取客户是否存在未读消息
     * @param $params
     * @return \app\app\model\data
     */
    public function getIsNotRead($params){
       return (new NoticesModel())->getIsNotRead($params);
    }

    public function noticeCustomerSave($params){
        $insert['CustomerType'] = 1;
        $insert['Customer'] = $params['CustomerID'];
        $insert['IsRead'] = false;
        $insert['IsDeleted'] = false;
        $insert['CreateAt'] = time();
        $insert['NoticeType'] = 1;
        return (new NoticesModel())->noticeCustomerSave($insert);
    }
}
