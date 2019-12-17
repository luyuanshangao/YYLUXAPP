<?php
namespace app\app\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 基础数据配置
 */
class NoticesModel extends Model
{
    protected $db;
    const tableName = 'notice_customers';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 获取客户是否存在未读消息
     * @param $params
     * @return data
     */
    public function getIsNotRead($params)
    {
        $result = $this->db->name(self::tableName)
            ->where(['CustomerID' => (int)$params['CustomerID'],'IsRead'=>true ])
            ->count();
        return $result;
    }



}