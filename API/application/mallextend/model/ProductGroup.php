<?php
namespace app\mallextend\model;
use think\Model;
use think\Db;
/**
 * 产品分组模型
 * @author
 * @version kevin 2018/4/1
 */
class ProductGroup extends Model{
    protected $db;
    protected $table = 'dx_product_group';
    protected $class_status=1; //产品分类状态：1为开启，2为关闭

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /*
     * 获取分组列表
     * */
    public function getGroup($where){
        $where['parent_id'] = (int)0;
        $where['deletetime'] = (int)0;
        $res = $this->db->table($this->table)->where($where)->order("_id","desc")->select();
        if(!empty($res)){
            foreach ($res as $k=>$v){
                $res[$k]['group_id'] = $v['_id'];
                $where1['parent_id'] = (int)$v['_id'];
                $where1['deletetime'] = (int)0;
                $child = $this->db->table($this->table)->where($where1)->select();
                if(!empty($child)){
                    foreach ($child as $key=>$value){
                        $child[$key]['group_id'] = $value['_id'];
                    }
                    $res[$k]['child'] = $child;
                }
                if(!isset($res[$k]['child'])){
                    $res[$k]['child'] = '';
                }
            }
        }
        return $res;
    }

    /*
     * 判断产品分组是否存在
     * */
    public function hasGroup($where){
        $count = $this->db->table($this->table)->where($where)->count();
        if($count > 0){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 修改保存分组
     * */
    public function saveGroup($data,$where=''){
        if(empty($where)){//没有条件新增
            $lastid = $this->db->table($this->table)->order("_id" ,"desc")->value("_id");
            if($lastid){
                $data['_id'] = (int)$lastid+1;
            }else{
                $data['_id'] = (int)1;
            }
            $data['store_open'] = (int)$data['store_open'];
            $data['parent_id'] = (int)$data['parent_id'];
            $data['user_id'] = (int)$data['user_id'];
            $data['addtime'] = (int)time();
            $data['deletetime'] = (int)0;
            $res = $this->db->table($this->table)->insert($data);
        }else{

            $res = $this->db->table($this->table)->where($where)->update($data);
        }
        return $res;
    }

    /*
     * 获取组名称
     * */
    public function getGroupName($where){
        $name = $this->db->table($this->table)->where($where)->value("group_name");
        return $name;
    }

    /**
     * 分组列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getGroupList($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name('product_group');

        if(isset($params['seller_id']) && !empty($params['seller_id'])){
            $where['user_id'] = (int)$params['seller_id'];
        }

        $query->where($where);
        $query->field(['user_id','group_name','parent_id','addtime']);

        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }
}