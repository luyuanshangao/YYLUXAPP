<?php
namespace app\mallextend\model;

use think\Db;
use think\Model;
/**
 * 国家数据表
 */
class RegionModel extends Model{

    protected $db;
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 新增国家
     */
    public function createRegion($data){
        $newData = $this->db->name('region')->order('_id',"desc")->find();
        if(isset($newData['_id'])){
            $data['_id'] = $newData['_id'] + 1;
        }else{
            $data['_id'] = $this->createNo();
        }
        $data['ParentID'] = (int)$data['ParentID'];

    	 return $this->db->name('region')->insert($data);
    }

    /**
     * 修改国家区域
     */
    public function updateRegionArea($data){
        $params = explode(',',$data['ids']);
        if(!count($params)){
            return false;
        }

        foreach($params as $key => $id){
            $ids[] = (int)$id;
        }
        return $this->db->name('region')->where('_id','in',$ids)
            ->update(['AreaID'=>(int)$data['AreaID'],'AreaName'=>$data['AreaName']]);

    }

    /**
     * 获取国家详情-列表
     * @param $params
     * type 是否带ParentID
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRegion($params = [],$type=0){
        $query = $this->db->name('region');
        if(!$type){
            $query->where(['ParentID' => isset($params['ParentID']) ? (int)$params['ParentID'] : 0,]);
        }
        if(isset($params['Name']) && $params['Name']){
            $query->where(['Name' => ['like',$params['Name']]]);
        }
        if(isset($params['Code']) && $params['Code']){
            $query->where(['Code' => $params['Code']]);
        }
        if(isset($params['id']) && $params['id']){
            $query->where(['_id' => (int)$params['id']]);
        }
        if(isset($params['AreaID']) && $params['AreaID']){
            $query->where(['AreaID' => (int)$params['AreaID']]);
        }
        $query->order("Name","asc");
        $query->field(array('Name'=>true,'Code'=>true,'AreaID'=>true,'AreaName'=>true,'HasChildren'=>true));

        return $query->select();
    }

    /**
     * 首页头部国家列表
     */
    public function getHeaderCountry(){
        $query = $this->db->name('region')->where(['ParentID' => 0])->field(array('Name'=>true,'Code'=>true))->select();
        return $query;
    }

    /**
     * 查找单个国家
     */
    public function getCountry($params){
        $query = $this->db->name('region');

        if(isset($params['Code']) && $params['Code']){
            $query->where(['Code' => $params['Code']]);
        }
        if(isset($params['ParentID'])){
            $query->where(['ParentID' => (int)$params['ParentID']]);
        }

        $query->field(array('Name'=>true,'Code'=>true,'AreaID'=>true,'AreaName'=>true,'HasChildren'=>true));
        return $query->find();
    }


    /**
     * 随机数
     * @return string
     */
    private static function createNo()
    {
        $no = date("YmdHis", time()) . mt_rand(1000, 9999);
        return $no;
    }


    /*修改地址简码*/
    public function  updateCode($ParentID,$Name,$Code){
        $where['ParentID'] = (int)$ParentID;
        $where['Name'] = $Name;
        $query = $this->db->name('region')->where($where)->update(['Code'=>$Code]);
        return $query;
    }
}