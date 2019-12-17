<?php
namespace app\admin\model;
use think\Model;
use think\Db;
/**
 * 产品分类模型
 * @author
 * @version tinghu.liu 2018/3/20
 */
class ProductClass extends Model{
    private  $db = '';
    protected $table = 'dx_product_class';
    protected $class_status=1; //产品分类状态：1为开启，2为关闭

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /**
     * 根据类别名称获取数据
     * @param $search_content 搜索内容
     * @param $type 类型：1-按英文名称，2-按中文名称
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataWithTitle($search_content, $type=1){
        if ($type == 1){
            $title = 'title_en';
        }elseif ($type ==2){
            $title = 'title_cn';
        }
        return $this->db->table($this->table)->where($title, 'like', '%'.$search_content.'%')->where('status',$this->class_status)->select();
    }

    /**
     * 根据ID获取单条数据
     * @param $id ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoWithId($id){
        return $this->db->table($this->table)->where('id', $id)->where('status',$this->class_status)->find();
    }

    /**
     * 根据父级ID获取数据
     * @param $pid
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoWithIdForPID($pid){
        return $this->db->table($this->table)->where('pid', $pid)->where('status',$this->class_status)->select();
    }


}