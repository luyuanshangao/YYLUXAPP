<?php
namespace app\mallextend\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

class ProductBrandModel extends Model{

    protected $db;
    protected $name = 'brands';
    protected $attribute = 'brand_attribute';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 品牌
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getBrandList($params){
        $where = array();
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->name);

        if(isset($params['brand_name']) && !empty($params['brand_name'])){
            $where['BrandName'] = ['like',$params['brand_name']];
        }

        if(!empty($where)){
            $query->where($where);
        }
        $query->field(['BrandId','BrandName','Brand_Icon_Url','CreatedTime','_id'=>false]);

        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    public function getBrandAttribute($params){
        $query = $this->db->name($this->attribute)->where(['_id'=>$params['_id']])->field(['_id','attribute'.$params['attr_id']])->find();
    }


}