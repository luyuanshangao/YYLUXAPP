<?php
namespace app\app\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 活动产品
 */
class ProductActivityModel extends Model{

    const TableProductActivity = 'product_activity';//活动产品
    const TableActivity = 'activity';//活动规则

    protected $db;
    protected $_status = 1; //产品活动状态：1为开启，2为关闭

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 活动进行中
     * @param $params
     * @return array
     */
    public function activityProdcutList($params){
        $query = $this->db->name(self::TableProductActivity);

        //活动进行中
        if(isset($params['on_sale_time']) && $params['on_sale_time']){
            $query->where('activityStartTime','<=',$params['on_sale_time']);
            $query->where('activityEndTime','>',$params['on_sale_time']);
        }

        //启用状态
        $query->where(['activityStatus' => (int)1]);
        $query->field("productArray,activityStartTime,activityEndTime");
        return $query->find();
    }

    /**
     * 获取大于当前时间的所有活动
     */
    public function activitySoonList($params){
        $query = $this->db->name(self::TableProductActivity);

        //活动进行中
        if(isset($params['soon_time']) && $params['soon_time']){
            $query->where('activityStartTime','>',$params['soon_time']);
        }

        //启用状态
        $query->where(['activityStatus' => (int)1]);
        $query->order('activityStartTime','asc');
        $query->field("productArray,activityStartTime,activityEndTime,activity_title");
        return $query->find();
    }

    /**
     * 获取当期产品活动详情
     */
    public function getActivityProduct($params){
        $query = $this->db->name(self::TableProductActivity);

        $query->where('ActivityID','=',(int)$params['activity_id']);

        $query->field(['SPU','DiscountLowPrice','DiscountHightPrice','HightDiscount','_id'=>false]);
        return $query->select();
    }

    /**
     * 获取活动详情
     */
    public function getActivity($params){
        $query = $this->db->name(self::TableActivity);

        $where = [
            'type'=> isset($params['type']) ? $params['type'] : null,
            '_id'=> isset($params['activity_id']) ? (int)$params['activity_id'] : null,
            'status'=> isset($params['status']) ? (int)$params['status'] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);

        //当前时间段的活动数据
        if(isset($params['current_time']) && !empty($params['current_time'])){
            $where['activity_start_time'] = ['<',time()];
            $where['activity_end_time'] = ['>=',time()];
        }

        //大于当前时间的活动
        if(isset($params['soon_time']) && $params['soon_time']){
            $where['activity_start_time'] = ['>',time()];
        }

        $query->where($where);
        $query->field("activity_title,activity_start_time,activity_end_time,activity_img,description,common");
        //活动开始时间排序
        $query->order('activity_start_time','asc');
        return $query->find();
    }

}