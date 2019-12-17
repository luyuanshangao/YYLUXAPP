<?php
/*
 * EIP报表模型
 * add by 20190815 kevin
 * */
namespace app\admin\model;
use think\Model;
use think\Db;
class EipReports extends Model
{
    protected $table = 'dx_sku_statistics';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /**
     * 获取EIP品类数据
     * @author Wang
     * @date 2019-01-25
     */
    public function getSkuSelection($country_code,$where,$order,$limit){
        foreach ($country_code as $key=>$value){
            $where['country_code'] = $value;
            $list[$value] = $this->db->table($this->table)->where($where)->order($order)->limit($limit)->select();
        }
        return $list;
    }

}
