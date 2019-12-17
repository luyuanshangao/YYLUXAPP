<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 基础数据配置
 */
class ConfigDataModel extends Model
{
    protected $db;
    const tableName = 'data_config';
    const tableMallSet = 'mall_set';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 配置的数据
     * @param $params
     * @param $field
     * @return data
     */
    public function getDataConfig($params,$field = null)
    {
        $query = $this->db->name(self::tableName)->where(['key' => $params['key']]);
        if($field){
            $query->field($field);
        }
        return $query->find();
    }

    /**
     * 商城配置信息
     * @param $field
     * @return array|false|\PDOStatement|string|Model
     */
    public function getMallSetData($field){
        $query = $this->db->name(self::tableMallSet)->field([$field,'language.en']);
        return $query->find();
    }


}