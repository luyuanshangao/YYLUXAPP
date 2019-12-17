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
class BrandModel extends Model
{
    protected $db;
    const tableName = 'brands';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 产品数
     * @param $params
     * @return data
     */
    public function select($params)
    {
        $query = $this->db->name(self::tableName);

        $where = [
            'BrandId'=> isset($params['brand_id']) ? $params['brand_id'] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);

        $query->where($where);

        //查询字段
        $query->field(['_id'=>false,'BrandId'=>true,'BrandName'=>true,'Brand_Icon_Url'=>true]);

        return $query->select();
    }

}