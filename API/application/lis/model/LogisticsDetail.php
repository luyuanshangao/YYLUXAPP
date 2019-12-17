<?php
namespace app\lis\model;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 供应商模型
 * @author wang  2018/12/08
 * @version
 */
class LogisticsDetail extends Model{
    const order_package = 'order_package';
    const order_package_track = 'order_package_track';
    const order_shipping_address = 'order_shipping_address';
    const sales_order = 'sales_order';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }
  /**
   * 获取节点
   * [LisLogisticsDetail description]
   */
  public function LisLogisticsDetail($data=array()){
      if(!empty($data['package_id']) && !empty($data['store_id']) && !empty($data['order_id'])){
           if(!empty($data['status'])){
               $sales_order = $this->db->name(self::sales_order)->where(['customer_id'=>$data['store_id'],'order_id'=>$data['order_id']])->field('order_id')->find();
           }else{
               $sales_order = $this->db->name(self::sales_order)->where(['store_id'=>$data['store_id'],'order_id'=>$data['order_id']])->field('order_id')->find();
           }
           if(empty($sales_order)){
              return apiReturn(['code'=>100,'data'=>'参数传递出错']);
           }
           $where['package_id'] = $data['package_id'];

           $list = $this->db->name(self::order_package_track)->where($where)->order('add_time desc')->field('raw_data')->find();
            // echo  $this->db->getlastsql();
           if(!empty($list)){
             return apiReturn(['code'=>200,'data'=>$list]);
           }else{
             return apiReturn(['code'=>100,'data'=>'尚未查到数据']);
           }
      }else{
         return apiReturn(['code'=>100,'data'=>'参数传递出错']);
      }
  }
   /**
   * 后台Admin管理员查看物流节点
   * [AdminLisLogisticsDetail description]
   */
  public function AdminLisLogisticsDetail($data=array()){
      if(!empty($data['package_id'])){
         $where['package_id'] = $data['package_id'];
         $list['LogisticsDetail'] = $this->db->name(self::order_package_track)->where($where)->order('add_time desc')->field('raw_data')->find();
          if(!empty($data['order_id'])){
              $list['order_address'] = $this->db->name(self::order_shipping_address)->where(['order_id'=>$data['order_id']])->find();
          }
         if(!empty($list)){
             return apiReturn(['code'=>200,'data'=>$list]);
         }
         return apiReturn(['code'=>101,'data'=>'查无数据']);
      }else{
         return apiReturn(['code'=>100,'data'=>'参数传递出错']);
      }
  }

    /**
     * 后台Admin管理员查看物流节点-列表
     * [AdminLisLogisticsDetail description]
     */
    public function AdminLisLogisticsDetails($data=array()){
        if(!empty($data['tracking_number'])){
            $where['a.tracking_number'] = ['in',$data['tracking_number']];
            $list = $this->db->name(self::order_package_track)
                ->alias('a')
                ->join(self::order_package.' w','a.package_id = w.package_id')
                ->join(self::sales_order.' b','w.order_number = b.order_number')
                ->where($where)->order('a.add_time desc')
                ->field('a.raw_data,a.tracking_number,w.order_number,b.country')
                ->select();
            if(!empty($list)){
                return apiReturn(['code'=>200,'data'=>$list]);
            }
            return apiReturn(['code'=>101,'data'=>'查无数据']);
        }else{
            return apiReturn(['code'=>100,'data'=>'参数传递出错']);
        }
    }

    /**
     * 根据条件获取订单邮寄信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderShippingAddressDataByWhere(array $where){
        return $this->db->name(self::order_shipping_address)->where($where)->find();
    }

}