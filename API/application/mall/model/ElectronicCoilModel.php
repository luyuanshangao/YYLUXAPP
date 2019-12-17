<?php
namespace app\mall\model;

use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\mongo\Query;

/**
 * 开发：钟宁
 * 功能：电子券
 * 时间：2018-10-23
 */
class ElectronicCoilModel extends Model{

    /**
     * 电子券状态
     */
    const COIL_STATUS_NO_USE = 1;  //未使用
    const COIL_STATUS_PAYMENT = 2;  //支付中
    const COIL_STATUS_HAS_USE = 3;  //已使用

    protected $db;
    protected $coupon_code = 'electronic_coil';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 根据用户ID，电子券号码获取可用状态
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function getCoil($params){
        $where = array();
        $query = $this->db->name($this->coupon_code);
        if(isset($params['coil_id']) && !empty($params['coil_id'])){
            $where['ElectronicCoil_ID'] = (int)$params['coil_id'];
        }
        if(isset($params['status']) && $params['status']){
            $where['status'] = (int)$params['status'];
        }

        if(isset($params['user_id']) && !empty($params['user_id'])){
            $where['user_id'] = $params['user_id'];
        }
        if(empty($where)){
            return array();
        }
        $data = $query->where($where)
            ->field(['_id'=>false,'status','user_id','ElectronicCoil_ID'])->find();
        return $data;

    }

    /**
     * 根据用户ID，绑定电子券
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function bindCoil($params){
        $findData = $this->db->name($this->coupon_code)->where(['ElectronicCoil_ID'=>$params['coil_id']])->field(['_id','status','user_id','add_time'])->find();
        if(empty($findData)){
            return false;
        }
        //查询是否已经绑定
        if(!empty($findData['user_id']) || $findData['status'] == 2 || $findData['status'] == 3){
            return false;
        }
        //判断是否超过三个月
        $time = strtotime('+3 month',$findData['add_time']);
        if($time < time()){
            return false;
        }

        $ret = $this->db->name($this->coupon_code)->where(['ElectronicCoil_ID'=>$params['coil_id']])->update(['status'=>1,'user_id'=>(int)$params['user_id']]);
        if(!$ret){
            return false;
        }
        return true;
    }
}