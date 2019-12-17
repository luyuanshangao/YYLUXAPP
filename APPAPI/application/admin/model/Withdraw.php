<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class Withdraw extends Model
{
    protected $table = 'dx_withdraw';
    protected $affiliate_order = 'dx_affiliate_order';
    protected $affiliate_apply = 'dx_affiliate_apply';
    protected $affiliate_order_item = 'dx_affiliate_order_item';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /*
     * 修改消息
     * */
    public function saveWithdraw($data,$where=''){
        if(empty($where)){//没有条件新增
            $res = $this->db->table($this->table)->insertGetId($data);
            return $res;
        }else{
            $res = $this->db->table($this->table)->where($where)->update($data);
        }
        return $res;
    }


    /*
     * 获取一个月内提现金额
     * */
    public function getMonthWithdrawAmount($customer_id){
        $where['customer_id'] = $customer_id;
        $where['add_time'] = ['GT',strtotime("-1 month")];
        $where['status'] = ['in',"1,2,3"];
        $amount = $this->db->table($this->table)->where($where)->sum("amount");
        return $amount;
    }
    /**
     * 获取订单最早时间和最晚时间
     * [getOrdermodel description]
     * @return [type] [description]
     * @author Wang
     * @date 2019-01-09
     */
    public function getOrdermodel($data = ''){
        $where = [];
        $data_array = [];
        $where['affiliate_order_id'] = ['in',$data];
        $where['settlement_status'] = 2;
        $list = $this->db->table($this->affiliate_order)->where($where)->order('add_time ASC')->select();
        if(!empty($list)){
            $end_time = end($list);
            $data_array['start_time'] = $list[0]['add_time'];
            $data_array['end_time']   = $end_time['add_time'];
            return ['code'=>200,'data'=> $data_array];
        }else{
            return ['code'=>1002,'msg'=>'The system has failed. Please refresh and try again.'];
        }
    }
    /**
     * [affiliateApply description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     * @author Wang
     * @date 2019-01-09
     */
    public function affiliateApply($data = []){
        if(!empty($data)){
            $list = $this->db->table($this->affiliate_apply)->insertGetId($data);
            if(!empty($list)){
               return ['code'=>200,'data'=>'Successful application submission.'];
            }else{
               return ['code'=>1002,'msg'=>'The system is busy, please try again later.'];
            }
        }else{
            return ['code'=>1002,'msg'=>'The system is busy, please try again later.'];
        }
    }
    /**
     * 更改订单状态
     * [affiliateOrder description]
     * @param  string $data [description]
     * @return [type]       [description]
     * @author Wang
     * @date 2019-01-09
     */
    public function affiliateOrder($data = ''){
        $where['affiliate_order_id'] = ['in',$data];
        $where['settlement_status'] = 2;
        $list = $this->db->table($this->affiliate_order)->where($where)->update(['settlement_status'=>3]);
        if(!empty($list )){
              return ['code'=>200,'data'=>'Successful application submission.'];
        }else{
              return ['code'=>1002,'msg'=>'The system is busy, please try again later.'];
        }
    }
    /**
     * 获取积分订单列表
     * [affiliateOrder description]
     * @param  [type]  $data      [description]
     * @param  integer $page      [description]
     * @param  integer $page_size [description]
     * @return [type]             [description]
     * @author Wang
     * @date 2019-01-10
     */
    public function OrderAffiliateList($data = [],$page = 1,$page_size = 20,$count = ''){
        if($count == ''){
           $count = $this->db->table($this->affiliate_order)->where($data)->count();
        }
        $list = $this->db->table($this->affiliate_order)->where($data)->page($page,$page_size)->order('add_time desc')->select();
        // $list = $this->db->table($this->affiliate_order)->where($data)->page($page,$page_size)->order('add_time desc')->select();
        if(!empty($list)){
             $affiliate_order_id = '';
             $data_item = [];
             foreach ($list as $k => $v) {
                  $affiliate_order_id .= $v['affiliate_order_id'].',';
             }
             $list_item =  $this->db->table($this->affiliate_order_item)->where(['affiliate_order_id'=>['in',$affiliate_order_id]])->group('affiliate_order_id')->field("affiliate_order_id,sum(commission_price) AS commission_price")->select();
             if(!empty($list_item)){
                  foreach ($list_item as $ke => $va) {
                        $data_item[$va['affiliate_order_id']] = $va;
                  }
             }
        }
        $smountStatistics = $this->AmountStatistics($data);
        return ['code'=>200,'data'=>$list,'list_item'=>$data_item,'amount'=>$smountStatistics,'count'=>$count];
    }
    /**
     * 统计改用户所有生效及未生效金额
     * [AmountStatistics description]
     * @author Wang
     * @date 2019-01-10
     */
    public function AmountStatistics($data = []){
          if(empty($data['affiliate_id']) || empty($data['cic_id'])){
              return;
          }
          $list = $this->db->table($this->affiliate_order)->where(['affiliate_id'=>$data['affiliate_id'],'cic_id'=>$data['cic_id'],'settlement_status'=>[['eq',1],['eq',2],'or']])->field('affiliate_order_id,settlement_status')->select();
          if(!empty($list)){
              $affiliate_order_id = '';
              $effective_amount = 0;//有效总金额
              $effective_plus_invalid_amount = 0;//有效加无效总金额
              $effective_affiliate_order_id = '';
              foreach ($list as $k => $v) {
                 $affiliate_order_id .= $v['affiliate_order_id'].',';
                 if($v['settlement_status'] == 2){
                       $effective_affiliate_order_id .= $v['affiliate_order_id'].',';
                 }

              }
              $list_plus_invalid =  $this->db->table($this->affiliate_order_item)->where(['affiliate_order_id'=>['in',$affiliate_order_id]])->field("sum(commission_price) AS commission_price")->find();
              $list_effective = $this->db->table($this->affiliate_order_item)->where(['affiliate_order_id'=>['in',$effective_affiliate_order_id]])->field("sum(commission_price) AS commission_price")->find();
              return ['list_plus_invalid'=>$list_plus_invalid,'list_effective'=>$list_effective];
          }

    }
}
