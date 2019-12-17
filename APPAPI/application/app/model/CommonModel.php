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
class CommonModel extends Model
{
    protected $db;
    const tableName = 'one_key_filters';
    const app_version = 'app_version';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 保存一键过滤
     * @param $params
     * @return data
     */
    public function saveCustomerFilter($params)
    {
        $result =0;
        if(empty($params) || !isset($params['CustomerID']) || !isset($params['BlackCategoryIds'])){
           return $result;
        }
        $params['IsFilter'] = true;
        $params['CustomerID'] = (int)$params['CustomerID'];
        $query = $this->db->name(self::tableName)->where(['CustomerID' => (int)$params['CustomerID']])->count();
        if($query>0){
            $params['edit_time'] = time();
            $result = $this->db->name(self::tableName)
                        ->where(['CustomerID' => (int)$params['CustomerID']])
                        ->update($params);
        }else{
            $params['add_time'] = time();
            $result =  $this->db->name(self::tableName)
                        ->insert($params);
        }
        return $result>0;
    }


    /**
     * 查询用户一键过滤数据
     * @param $customerID CIC ID
     * @return 数据
     */
    public function getCustomerFilter($customerID){
        $result = $this->db->name(self::tableName)
            ->where(['CustomerID' => (int)$customerID])
            ->find();
        return $result;
    }

    /**
     * app 版本信息
     * @return array|false|\PDOStatement|string|Model
     */
    public function getAppVersion(){
        $result = $this->db->name(self::app_version)->find();
        return $result;
    }

}