<?php
namespace app\seller\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use app\common\helpers\CommonLib;

/**
 *获取相关商铺信息
 *auther Wang   2018-10-13
 */
class ShopData extends Model{

    public $page_size = 20;
    public $page = 1;
    protected $table = 'sl_shipping_template';
    protected $seller = 'sl_seller';

	public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }
     /**
     * 根据店铺ID获取店铺名
     * [shop_name description]
     * @return [type] [description]
     */
    public function shop_name($data){
         if($data["seller_id"]){
                $ShopInquiries = array();
                $where = array();
                $spu_ShopInquiries_array = explode(",", $data["seller_id"]);
                //数据检查
                $spu = array();
                foreach ($spu_ShopInquiries_array as $k => $v) {
                     $spu_array = array();
                     if($v){
                        $spu_array = explode(":", $v);
                        $where['id'][] =  array('eq',$spu_array[1]);
                     }
                }
               $where['id'][] = 'OR';//return $where;
               $list =  $this->db->name($this->seller)->where($where)->field('id,true_name')->select();
               return $list ;
         }
    }


}