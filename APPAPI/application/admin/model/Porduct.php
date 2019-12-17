<?php
namespace app\admin\model;
use think\Model;
use think\Db;

/**
 * 产品模型
 * @author heng.zhang 2018-05-29
 * @version 1.0 
 * @info 产品管理
 */
class Porduct extends Model{
    protected $db;
    protected $table = 'dx_product';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 根据Seller id更新在售的产品的commission数据 （ ProductStatus=1 在售产品）
     * 此方法只更新$type=1  默认类型的佣金   类型的数据
     * @param int $seller_id
     * @param float $commission
     * @param int $type
     * @return json
     */
    public function updateCommissionBySellerID($seller_id,$commission){    	
    	$updateWhere['StoreID'] = (int)$seller_id;
    	$updateWhere['ProductStatus'] = 1;
    	#$type=1  默认类型的佣金  
    	#特别注意：  类型等于=2 或者3 的数据需要在后台审核通过后更新数据，只有等于1的才无须审核，直接更新
        //$updateWhere['CommissionType'] =1;
        //先统计数据是否存在
    	$count = $this->db->table($this->table)
    				->where($updateWhere)
    				->count();
    	if($count){
    		//type =1 或者等于null的这类数据需要更新
    		$resultDB = $this->db->table($this->table)
			    		->where($updateWhere)
			    		->where('CommissionType','<>',2)
			    		->where('CommissionType','<>',3)
			    		->update(['Commission'=>$commission,'CommissionType'=>1]);
    		if($resultDB){
    			return 200;
    		}else{
    			return '更新产品表数据失败';
    		}
    	}else{
    		return '无产品，请尽快上传商品';
    	}
    	return $result;
    }

}