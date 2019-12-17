<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 开发：钟宁
 * 功能：获取积分产品
 * 时间：2018-06-05
 */
class ProductPointsModel extends Model{

    const table = 'product_points';//积分产品
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }


    /**
     * 积分产品列表
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function getPointsLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : 8;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name(self::table);

        //查询字段
        $query->field(['_id'=>false,'SPU'=>true,'SKU'=>true,'DXPoints'=>true,'ReferralPoints'=>true,'EnableDXPoints'=>true,'EnableReferralPoints'=>true]);

        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

}