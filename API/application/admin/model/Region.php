<?php
namespace app\admin\model;
use think\Model;
use think\Db;
/**
 * 地区模型
 * @author
 * @version tinghu.liu 2018/3/19
 */
class Region extends Model{
    private  $db = '';
    protected $table = 'dx_region';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /**
     * 根据parent ID获取数据
     * @param int $parent_id 父级ID：1-省
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataWithParent_id($parent_id=1){
        return $this->db->table($this->table)->where('PARENT_ID','=', $parent_id)->select();
    }

    /**
     * 根据REGION_ID获取数据
     * @param $region_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoWithRegion_id($region_id){
        $query = $this->db->table($this->table);
        if (is_array($region_id)){
            $data = $query->where('REGION_ID', 'in', $region_id)->select();
        }else{
            $data = $query->where('REGION_ID', '=', $region_id)->find();
        }
        return $data;
    }


}