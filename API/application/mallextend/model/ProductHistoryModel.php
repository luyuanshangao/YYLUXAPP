<?php
namespace app\mallextend\model;
use think\Model;
use think\Db;
/**
 * 变更历史模型
 */
class ProductHistoryModel extends Model{
    protected $db;
    protected $table = 'product_class_histories';
    protected $product = 'product_histories';
    protected $activity = 'activity_histories';
    protected $table_attribute = 'attribute_history';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /*
     * 获取类别历史列表
     * */
    public function classHistoryLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->table);

        if(isset($params['startTime']) && !empty($params['startTime'])){
            $query->where(['CreatedDateTime'=>[ '>=' , strtotime($params['startTime'])]]);
        }

        if(isset($params['endTime']) && !empty($params['endTime'])){
            $query->where(['CreatedDateTime'=>[ '<=' , strtotime($params['endTime'])]]);
        }
        if(isset($params['endTime']) && isset($params['startTime']) && !empty($params['startTime']) && !empty($params['endTime'])){
            $query->where(['CreatedDateTime'=>[ 'between' , [strtotime($params['startTime']),strtotime($params['endTime'])]]]);
        }
        //查询字段
        $query->field(["EntityId"=>true,'_id'=>false,'CreatedDateTime']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();

    }

    /*
     * 获取活动历史列表
     * */
    public function activityHistoryLists($params){
        $query = $this->db->name($this->activity);

        if(isset($params['startTime']) && !empty($params['startTime'])){
            $query->where(['CreatedDateTime'=>[ '>=' , strtotime($params['startTime'])]]);
        }

        if(isset($params['endTime']) && !empty($params['endTime'])){
            $query->where(['CreatedDateTime'=>[ '<=' , strtotime($params['endTime'])]]);
        }
        //查询字段
        $query->field(["EntityId"=>true,'_id'=>false]);
        return $query->select();

    }

    /*
     * 获取产品历史列表
     * */
    public function productHistoryLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);

        if(isset($params['startTime']) && !empty($params['startTime'])){
            $query->where(['CreatedDateTime'=>[ '>=' , strtotime($params['startTime'])]]);
        }

        if(isset($params['endTime']) && !empty($params['endTime'])){
            $query->where(['CreatedDateTime'=>[ '<=' , strtotime($params['endTime'])]]);
        }
        if(isset($params['isHistory']) && !empty($params['isHistory'])){
            $query->where(['IsHistory'=>1]);
        }
        //查询字段
        $query->field(["EntityId"=>true,'_id'=>false,'CreatedDateTime']);

        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }


    /*
     * 添加类别历史
     * */
    public function addClassHistory($data){
        return $this->db->name($this->table)->insert($data);
    }

    /**
     * 添加产品历史
     * @param $product_id
     * @return int|string
     */
    public function addProductHistory($product_id,$IsHistory = 0){

//        $params['IsHistory'] = $IsHistory;
        $params['EntityId'] = (int)$product_id;
        $params['IsSync'] = false;
        $params['CreatedDateTime'] = time();
        $params['Note'] = '指定产品翻译20190215';
        $params['AddTime'] = date('Y-m-d H:i:s',time());
        return $this->db->name($this->product)->insert($params);
    }

    /**
     * 添加产品历史
     * @param $product_id
     * @return int|string
     */
    public function updateProductHistory($product_id,$update){
        $ret = $this->db->name($this->product)->where(['EntityId' => (int)$product_id])->update($update);
    }

    /**
     * 添加产品历史
     * @param $product_id
     * @return int|string
     */
    public function findProductHistory($product_id){
        return $this->db->name($this->product)->where(['EntityId' => (int)$product_id])->field(['EntityId'])->find();
    }

    /**
     * 添加类别历史
     * @param $attr_id
     * @return int|string
     */
    public function addProductAttribute($attr_id){

        $params['EntityId'] = (int)$attr_id;
        $params['IsSync'] = false;
        $params['CreatedDateTime'] = time();
        $params['Note'] = '重新翻译attribute';
        $params['AddTime'] = date('Y-m-d H:i:s',time());
        return $this->db->name($this->table_attribute)->insert($params);
    }

}