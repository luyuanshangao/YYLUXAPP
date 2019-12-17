<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 地址模型
 * @author
 * @version Kevin 2018/3/15
 */
class Region extends Model{

    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    public function getRegion($ParentID=0){
        return $this->db->name('dx_region')->where(array("ParentID"=>(int)$ParentID))
            ->field(array('_id'=>true,'Name'=>true,'Code'=>true))->select();
    }

    public function updateAreaID(){
        $data = $this->db->name('region')->where(array("ParentID"=>(int)0))
            ->field(array('_id'=>true,'Name'=>true,'Code'=>true,'AreaName'=>true))->select();
        foreach ($data as $key=>$value){
            switch ($value['AreaName']){
                case "Asia":
                    $this->db->name('region')->where(array("_id"=>(int)$value['_id']))->update(['AreaID'=>1]);
                    break;
                case "Europe":
                    $this->db->name('region')->where(array("_id"=>(int)$value['_id']))
                        ->update(['AreaID'=>2]);
                    break;
                case "North America":
                    $this->db->name('region')->where(array("_id"=>(int)$value['_id']))
                        ->update(['AreaID'=>3]);
                    break;
                case "South America":
                    $this->db->name('region')->where(array("_id"=>(int)$value['_id']))
                        ->update(['AreaID'=>4]);
                    break;
                case "Africa":
                    $this->db->name('region')->where(array("_id"=>(int)$value['_id']))
                        ->update(['AreaID'=>5]);
                    break;
                case "Oceania":
                    $this->db->name('region')->where(array("_id"=>(int)$value['_id']))
                        ->update(['AreaID'=>6]);
                    break;
            }

        }
        return 1;
    }
}