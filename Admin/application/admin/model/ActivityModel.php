<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use think\Session;
use think\Log;


class ActivityModel extends Model{

    private $act_upload_file 	= 'activity_upload_file';
    private $act_upload 		= 'dx_activity_upload';
    private $act_table 			= 'dx_activity';
    private $product 			= 'dx_product';
    private $act_sku_table		= 'dx_activity_sku';
    private $act_spu_table 		= 'dx_activity_spu';
    private $product_active		= 'dx_product_activity';
    public function __construct()
    {
        parent::__construct();
    }

    public function checkAct($activity_id, $type=0){
        if ($type != 0){
            $res = Db::table($this->act_table)->where(['id'=>$activity_id,'status'=>1,'type'=>$type])->find();
        }else{
            $res = Db::table($this->act_table)->where(['id'=>$activity_id,'status'=>1])->find();
        }
        if($res){
    		return true;
    	}
    	return false;
    }

    public function addActFileLog($filename,$activity_id){
    	$time = date('Y-m-d H:i:s');

    	return Db::name($this->act_upload_file)->insertGetId(['path'=>$filename,'activity_id'=>$activity_id,'upload_time'=>$time]);
    }

    public function saveActData($data,$fileId,$activity_id){
    	set_time_limit(0);
    	$time = date('Y-m-d H:i:s');

    	Db::startTrans();
    	try{
    		foreach ($data as $value) {
    			if( empty($value['spu']) || empty($value['discount']) || empty($value['number']) ){
                    Log::record('有数据为空：'.json_encode($value),'error');
    				Db::rollback();
    				return ['code'=>400,'msg'=>'有数据为空：'.json_encode($value)];
    			}

    			$insertData = array(
    				'file_id' => $fileId,
    				'activity_id' => $activity_id,
    				'spu' => $value['spu'],
    				'sku' => $value['sku'],
    				'discount'  => $value['discount'],
    				'number'	=> $value['number'],
    				'add_time'	=> $time,
    			);

    			Db::table($this->act_upload)->insert($insertData);
    		}

    		Db::commit();
    		return ['code'=>200,'msg'=>'操作成功！'];
    	}catch (Exception $e) {
    		Log::record($e->getMessage(),'error');
    		Db::rollback();
    		return ['code'=>400,'msg'=>$e->getMessage()];
    	}
    	return ['code'=>400,'msg'=>'操作失败！'];
    }

    public function getActData($where,$limit=1000){
    	return Db::table($this->act_upload)->where($where)->limit($limit)->select();
    }

    public function getProData($spu){
    	return Db::connect("db_mongo")->name($this->product)->where(['_id'=>$spu])->field('Skus,LowPrice,DiscountLowPrice')->find();
    }

    public function updateActData($id,$data){
    	return Db::table($this->act_upload)->where(['id'=>$id])->update($data);
    }

    public function addProActData($data){
    	return Db::connect("db_mongo")->name($this->product_active)->insert($data);
    }

    public function addActProData($id,$act_spu,$act_sku){
    	//开始处理事务
		Db::startTrans();
		try{
			Db::table($this->act_spu_table)->insert($act_spu);

			Db::table($this->act_sku_table)->insertAll($act_sku);

			Db::table($this->act_upload)->where(['id'=>$id])->update(['status'=>1]);

			Db::commit();
			return true;
		}catch (Exception $e) {
    		Log::record($e->getMessage(),'error');
    		Db::rollback();
    		return false;
		}
    }

    public function uptActProData($where){
        //开始处理事务
        Db::startTrans();
        try{
            Db::table($this->act_spu_table)->where($where)->update(['status'=>2]);

            Db::table($this->act_sku_table)->where($where)->update(['status'=>2]);

            Db::commit();
            return true;
        }catch (Exception $e) {
            Log::record($e->getMessage(),'error');
            Db::rollback();
            return false;
        }
    }
}