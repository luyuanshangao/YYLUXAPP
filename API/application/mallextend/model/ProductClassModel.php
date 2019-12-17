<?php
namespace app\mallextend\model;
use app\common\helpers\CommonLib;
use think\Log;
use think\Model;
use think\Db;
/**
 * 产品分类模型
 * @author
 * @version tinghu.liu 2018/3/20
 */
class ProductClassModel extends Model{
    private  $db = '';
    protected $table = 'product_class';
    protected $table_attribute = 'attribute';//产品属性
    protected $table_brand_attribute = 'brand_attribute';//品牌属性表
    protected $class_status=1; //产品分类状态：1为开启，2为关闭

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
        $this->adminDB = Db::connect('db_admin');
    }

    /**
     * 根据类别名称获取数据
     * @param $search_content 搜索内容
     * @param $type 类型：1-按英文名称，2-按中文名称
     * @param int $class_type 类别类型：1-erp数据，2-pdc数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataWithTitle($search_content, $type = 1, $class_type=1){
        if ($type == 1){
            $title = 'title_en';
        }elseif ($type == 2){
            $title = 'title_cn';
        }
        $where['status'] = (int)$this->class_status;
        $where['type'] = (int)$class_type;//默认erp数据

        return $this->db->name($this->table)->where($title, 'like', $search_content)->where($where)->select();
    }

    /**
     * 根据ID获取单条数据
     * @param $id ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoWithId($id){
        $where['status'] = (int)$this->class_status;
        //$where['type'] =1;//只允许查询type=1的数据(ERP)
        return $this->db->name($this->table)->where('id','=', (int)$id)->where($where)->find();
    }

    /**
     * 根据父级ID获取数据
     * @param $pid
     * @param int $class_type 类别类型：1-erp数据，2-pdc数据
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoWithIdForPID($pid, $class_type=1){
        $where['status'] = (int)$this->class_status;
        if($class_type != 0){
            $where['type'] = (int)$class_type;//默认erp数据
        }
        return $this->db->name($this->table)->where('pid','=', (int)$pid)->where($where)->select();
    }

    /**
     * 根据分类数组ID获取数据
     * @param array $id_arr
     * @param int $class_type 类别类型：1-erp数据，2-pdc数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataWithIdArray(array $id_arr, $class_type = 1){
        $id_str = implode(',', $id_arr);
        foreach ($id_arr as $val){
            $id_arr_new[] = (int)$val;
        }
        //return $this->db->name($this->table)->where('id', 'in', $id_arr_new)->where(['type'=>$class_type])->select();
        $data = $this->db->name($this->table)->where('id', 'in', $id_arr_new)->select();
        return $data;
    }


    /**
     * 根据父级ID获取数据
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function selectClass($params){
        $query = $this->db->name($this->table);
        $where = [
            'status'=> (int)$this->class_status,
            'pid'=> isset($params['pid']) ? (int)$params['pid'] : 0,
        ];

        if(isset($params['class_id'])){
            if(is_array($params['class_id'])){
                $where['id'] = CommonLib::supportArray($params['class_id']);
            }else{
                $where['id'] = (int)$params['class_id'];
            }
        }
        $query->where($where);
        $query->field(['_id'=>false,'id'=>true,'pid'=>true,'title_en'=>true,'addtime'=>true]);
        return $query->select();
    }

    /**
     * 根据父级ID获取数据
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function selectClassForSr($params){
        $query = $this->db->name($this->table);
        $where = [
            'status'=> (int)$this->class_status,
            'type'=> 1,//对外接口只能提供erp类别数据（张恒）
            'pid'=> isset($params['pid']) ? (int)$params['pid'] : 0,
        ];

        if(isset($params['class_id'])){
            if(is_array($params['class_id'])){
                $where['id'] = CommonLib::supportArray($params['class_id']);
            }else{
                $where['id'] = (int)$params['class_id'];
            }
        }
        $query->where($where);
        $query->field(['_id'=>false,'id'=>true,'pid'=>true,'title_en'=>true,'addtime'=>true]);
        return $query->select();
    }


    /**
     * 获取HSCode
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getHscode($params){
        $query = $this->db->name($this->table);
        $where = [
            'status'=> (int)$this->class_status,
        ];

        if(isset($params['class_id'])){
            if(is_array($params['class_id'])){
                $where['id'] = CommonLib::supportArray($params['class_id']);
            }else{
                $where['id'] = (int)$params['class_id'];
            }
        }
        $query->where($where);
        $query->field(['_id'=>false,'HSCode'=>true,'declare_en'=>true,'id'=>true,'title_en'=>true]);
        return $query->select();
    }

    /**
     * 根据id获取分类信息
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getCategoryByIDs($params){
        $query = $this->db->name($this->table);
//        $where = [
//            'status'=> (int)$this->class_status,
//        ];

        if(isset($params['class_id'])){
            if(is_array($params['class_id'])){
                $where['id'] = CommonLib::supportArray($params['class_id']);
            }else{
                $where['id'] = (int)$params['class_id'];
            }
        }
        $query->where($where);
        $query->field(['_id'=>false,'id','pid','title_en','title_cn','rewritten_url','level','isleaf','id_path','type','pdc_ids']);
        return $query->select();
    }

    /**
     * 获取单个类别详情
     * @param $where
     * @param string $lang
     * @return array|false|\PDOStatement|string|Model
     */
    public function getClassDetail($where,$lang=DEFAULT_LANG){
        $where['status'] = 1;
        $query = $this->db->name($this->table)->where($where)
            ->field(['_id'=>false,'id','pid','title_en','common.'.$lang,'common.en','rewritten_url','type',
                'id_path','pdc_ids','level','HSCode','declare_en','isleaf'])->find();

        return $query;
    }

    public function queryClass($params){
        $where['status'] = (int)$params['status'];
        $where['type'] = (int)$params['type'];
        $query = $this->db->name($this->table)->where($where)
            ->field(['_id'=>false,'id','pid','title_en','common.en','rewritten_url','type',
                'id_path','pdc_ids','level','HSCode','declare_en'])->select();

        return $query;
    }

    public function getClassLists($params){

        $where = [
            'status'=> (int)$this->class_status,
        ];

        if(isset($params['type']) && !empty($params['type'])){
            $where['type'] = (int)$params['type'];
        }
        if(isset($params['class_id']) && !empty($params['class_id'])){
            $where['id'] = (int)$params['class_id'];
        }
        if(isset($params['pid'])){
            $where['pid'] = (int)$params['pid'];
        }
        $query = $this->db->name($this->table)->where($where)
            ->field(['_id'=>false,'id','pid','title_en','rewritten_url','type','level','HSCode','declare_en','id_path'])->select();

        return $query;
    }

    /**
     * 产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getClassListsCommon($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 100;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name('product_class_926');
        $where = [
            'status'=> (int)$this->class_status,
        ];
        $query->where($where);
        //搜索字段
        $query->field(['id','Common']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    public function updateClassCommon($where,$update){
        $ret = $this->db->name($this->table)->where($where)->update($update);
        return $ret;
    }


    /**
     * 分页查询
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function paginateClass($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 10;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->table_brand_attribute);

        if(isset($params['class_id']) && !empty($params['class_id'])){
            $where['id'] = (int)$params['class_id'];
        }

//        $where['type'] = (int)1;
//        $where['status'] = (int)$this->class_status;

//        $query->where($where);
        //搜索字段
        $query->field(['_id','attribute']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 查找类别
     * @param $id ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function findAttribute($id){
        $query = $this->db->name($this->table_attribute);
        $query->where(['_id'=>(int)$id]);
        $query->field(['_id']);
        return $query->find();
    }

    /**
     * 类别
     * @param $attr
     * @return int|string
     */
    public function insertAttribute($attr){
        return $this->db->name($this->table_attribute)->insert($attr);
    }

    /**
     * 类别
     * @param $params
     * @return int|string
     */
    public function find_erp_purchase_cost($params){
        return $this->adminDB->table('fs_erp_purchase_cost')->where(['dx_spu' => $params['SPU'],'sku' => $params['SKU']])->field(['unitcost'])->find();
    }

}