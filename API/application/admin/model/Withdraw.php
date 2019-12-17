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
    protected $affiliate_level = 'cic_affiliate_level';
    protected $block_chain_withdraw = 'dx_block_chain_withdraw';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
        $this->dbcic = Db::connect('db_cic');
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
    public function OrderAffiliateList($data = array(),$page = 1,$page_size = 20,$count = ''){
        $data_item = array();
        $list = array();
        $smountStatistics = array();

        if(empty($data)){
            if($count == ''){
               $count = $this->db->table($this->affiliate_order)->count();
            }
            $list = $this->db->table($this->affiliate_order)->page($page,$page_size)->order('add_time desc')->select();

        }else{
            if($count == ''){
               $count = $this->db->table($this->affiliate_order)->where($data)->count();
            }
            $list = $this->db->table($this->affiliate_order)->where($data)->page($page,$page_size)->order('add_time desc')->select();
        }
        // $list = $this->db->table($this->affiliate_order)->where($data)->page($page,$page_size)->order('add_time desc')->select();
        if(!empty($list)){
             $affiliate_order_id = '';
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
        if(empty($data['affiliate_id'])){
          return;
        }
        $effective_amount = $this->db->table($this->affiliate_order)->where(['affiliate_id'=>$data['affiliate_id'],'settlement_status'=>2])->field('sum(total_valid_commission_price) commission_price')->find();
        $effective_plus_invalid_amount = $this->db->table($this->affiliate_order)
            ->alias("ao")
            ->join($this->affiliate_order_item." aoi","aoi.affiliate_order_id=ao.affiliate_order_id")
            ->where(['ao.affiliate_id'=>$data['affiliate_id'],'ao.settlement_status'=>["in","1,2"]])
            ->field('sum(commission_price) commission_price')
            ->find();
        return ['list_plus_invalid'=>$effective_plus_invalid_amount,'list_effective'=>$effective_amount];
    }

     /**
     * 获取affiliate_id是否为黑名单状态
     * [BlacklistVerification description]
     * @author Wang
     * @date 2019-01-25
     */
    public function BlacklistVerification($data){

         $list = $this->dbcic->table($this->affiliate_level)->where(['RCode'=>$data["affiliate_id"]])->field("IsBlacklist")->find();
         if(empty($list['IsBlacklist'])){   return ['code'=>1002,'msg'=>'An error occurred while sending data. Please try again later.'];  }

         if($list['IsBlacklist'] == 1){
            return ['code'=>1002,'msg'=>'You have been blacklisted, please contact the relevant personnel.'];
         }
         if($list['IsBlacklist'] == 2){
            return ['code'=>200,'data'=>$list];
         }else{
            return ['code'=>1002,'msg'=>'An error occurred while sending data. Please try again later.'];
         }
    }

    /*提交旧系统affiliate提现*/
    public function getOldWithdrawCount($where){
        $res = $this->db->table($this->table)->where($where)->count();
        return $res;
    }


    /**
     * 区块链提现列表
     * add by zhongning 20191024
     * @param $where
     * @param int $page_size
     * @param int $page
     * @param string $path
     * @param string $order
     * @param string $page_query
     * @return array
     */
    public function getBlockChainWithdrawList($where,$page_size=20,$page=1,$path='',$order='',$page_query=''){
        $res = $this->db->table($this->block_chain_withdraw)
            ->where($where)
            ->order($order)
            ->field("id,customer_id,block_chain_transaction_id,withdraw_number,product_title,paypal_number,virtual_currency,virtual_rate,withdraw_virtual_currency,virtual_currency,withdraw_amount,status,remarks,add_time,review_time,handling_fee")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 区块链提现新增
     * add by zhongning 20191024
     * @param array $insert
     * @return int
     */
    public function addBlockChainWithdraw($insert){
        return $this->db->table($this->block_chain_withdraw)->insertGetId($insert);
    }

    /**
     * 区块链提现更新
     * add by zhongning 20191024
     * @param array $where
     * @return int
     */
    public function updateBlockChainWithdraw($where,$update){
        return $this->db->table($this->block_chain_withdraw)->where($where)->update($update);
    }

    /**
     * 区块链提现更新
     * add by zhongning 20191024
     * @param array $where
     * @return int
     */
    public function findBlockChainWithdraw($where){
        return $this->db->table($this->block_chain_withdraw)->where($where)->find();
    }
}
