<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 积分信息模型
 * @author
 * @version Kevin 2018/3/25
 */
class PointsBasicInfo extends Model{
    protected $table = 'cic_points_basic_info';
    protected $referral_table = 'cic_referral_points_basic_info';
    protected $points_details = 'cic_points_details';
    protected $referral_points_details = 'cic_referral_points_details';
    protected $affiliate_order = 'dx_affiliate_order';
    protected $affiliate_order_item = 'dx_affiliate_order_item';
    protected $affiliate_apply = 'dx_affiliate_apply';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
        $this->admin = Db::connect('db_admin');

    }
    /*
* 新增用户积分
* */
    public function addPoints($data,$type=1){
        if($type == 1){
            $res = $this->db->table($this->table)->insertGetId($data);
            if($res){
                if($data['NewTotalCount']>0){
                    $PointsDetails['CustomerID'] = $data['CustomerID'];
                    $PointsDetails['ClientID'] = isset($data['ClientID'])?$data['ClientID']:1;
                    $PointsDetails['OrderNumber'] = '';
                    $PointsDetails['TransactionTime'] = time();
                    $PointsDetails['OperateType'] = 1;
                    $PointsDetails['PointsCount'] = $data['NewTotalCount'];
                    $PointsDetails['ActiveFlag'] = 1;
                    $PointsDetails['CreateTime'] = time();
                    $PointsDetails['Memo'] = "Register and grant";
                    $PointsDetails['Operator'] = "system";
                    $PointsDetails['OperateReason'] = 1;
                    $PointsDetails['ReasonDetail'] = "Register for bonus points";
                    $PointsDetails['Status'] = 1;
                    $PointsDetails['IsNewDx'] = 1;
                    $type = input("type",1);//积分类型 1-普通积分 2-介绍积分
                    model("PointsDetails")->addPointsDetails($PointsDetails,$type);
                }
            }
        }else{
            $res = $this->db->table($this->referral_table)->insertGetId($data);
        }
        return $res;
    }

    /*
     * 增加减少用户积分
     * */
    public function editPoints($CustomerID,$edit_type,$number,$type=1){
        $where['CustomerID'] = $CustomerID;
        if($type == 1){
            if($number>0){
                $res = $this->db->table($this->table)->where($where)->setInc($edit_type,$number);
            }else{
                $res = $this->db->table($this->table)->where($where)->setDec($edit_type,abs($number));
            }
        }else{
            if($number>0){
                $res = $this->db->table($this->referral_table)->where($where)->setInc($edit_type,$number);
            }else{
                $res = $this->db->table($this->referral_table)->where($where)->setDec($edit_type,abs($number));
            }
        }

        return $res;
    }

    /*
    * 获取用户积分$this->admin
    * */
    public function getPointsBasicInfo($CustomerID,$type = 1){
        $where['CustomerID'] = $CustomerID;
        if($type == 1){
            $res = $this->db->table($this->table)->where($where)->field("ID,CustomerID,ClientID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount")->find();
            if(!$res){
                $where['ClientID'] = 1;
                $this->db->table($this->table)->insert($where);
                $res = $this->db->table($this->table)->where($where)->field("ID,CustomerID,ClientID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount")->find();
            }
        }else{
            $res = $this->db->table($this->referral_table)->where($where)->field("ID,CustomerID,ClientID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount")->find();
            if(!$res){
                $where['ClientID'] = 1;
                $this->db->table($this->referral_table)->insert($where);
                $res = $this->db->table($this->referral_table)->where($where)->field("ID,CustomerID,ClientID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount")->find();
            }
        }
        return $res;
    }
    /*
     * 扣减用户积分
     * */
    public function DecPoints($CustomerID,$dx_points,$referral_points,$data){
        $res = $this->db->transaction(function() use ($CustomerID,$dx_points,$referral_points,$data){
            $where['CustomerID'] = $CustomerID;
            if($dx_points>0){
                $res = $this->db->table($this->table)->where($where)->setDec("UsableCount",$dx_points);
                $this->db->table($this->table)->where($where)->setInc("UsedCount",$dx_points);
                $data['PointsCount'] = -$dx_points;
                $this->db->table($this->points_details)->insertGetId($data);
            }
            if($referral_points>0){
                $res = $this->db->table($this->referral_table)->where($where)->setDec("NewTotalCount",$referral_points);
                //$this->db->table($this->referral_table)->where($where)->setInc("UsedCount",$referral_points);
                $data['PointsCount'] = -$referral_points;
                $this->db->table($this->referral_points_details)->insertGetId($data);
            }
            return $res;
        });
        return $res;
    }


    /*
 * 增加用户积分
 * */
    public function IncPoints($CustomerID,$dx_points=0,$referral_points=0,$data){
        $res = [];
        $res = $this->db->transaction(function() use ($CustomerID,$dx_points,$referral_points,$data){
            /*判断推荐积分用户是否存在，不存在则添加*/
            if($referral_points>0){
                $points_where['CustomerID'] = $CustomerID;
                $points_count = $this->db->table($this->referral_table)->where($points_where)->field("CustomerID,UsableCount")->count();
                if($points_count == 0){
                    $add_data['CustomerID'] = $CustomerID;
                    $add_data['ClientID'] = 1;
                    $add_data['TotalCount'] = 0;
                    $add_data['UsedCount'] = 0;
                    $add_data['UsableCount'] = 0;
                    $add_data['InactiveCount'] = 0;
                    $add_data['NewTotalCount'] = 0;
                    $this->db->table($this->referral_table)->where($points_where)->insert($add_data);
                }
            }
            $where['CustomerID'] = $CustomerID;
            if($dx_points>0){
                if($data['IsNewDx'] == 1){
                    $res = $this->db->table($this->table)->where($where)->setInc("NewInactiveCount",$dx_points);
                }else{
                    $res = $this->db->table($this->table)->where($where)->setInc("UsableCount",$dx_points);
                    $this->db->table($this->table)->where($where)->setInc("TotalCount",$dx_points);
                }
                $data['PointsCount'] = $dx_points;
                $this->db->table($this->points_details)->insertGetId($data);
            }
            if($referral_points>0){
                if($data['IsNewDx'] == 1){
                   $res = $this->db->table($this->referral_table)->where($where)->setInc("NewInactiveCount",$referral_points);
                }else{
                    $res = $this->db->table($this->referral_table)->where($where)->setInc("UsableCount",$referral_points);
                    $this->db->table($this->referral_table)->where($where)->setInc("TotalCount",$referral_points);
                }
                $data['PointsCount'] = $referral_points;
                $this->db->table($this->referral_points_details)->insertGetId($data);
                return $res;
            }
            return $res;
        });
        return $res;
    }

    /*
    * 获取用户登录，可用积分
    * */
    public function getCustomerPoints($CustomerID){
        $where['CustomerID'] = $CustomerID;
        $data['CustomerID'] = $CustomerID;
        //DX积分
        $ret = $this->db->table($this->table)->where($where)->field("CustomerID,UsableCount")->find();
        $data['DXPoints'] = isset($ret['UsableCount']) ? $ret['UsableCount'] : 0;
        //推荐积分
        $ret = $this->db->table($this->referral_table)->where($where)->field("CustomerID,UsableCount")->find();
        $data['ReferralPoints'] = isset($ret['UsableCount']) ? $ret['UsableCount'] : 0;
        return $data;
    }

    /*
     * 更改用户积分详情
     * type 1普通积分 2推荐积分
     * */
    public function CancelOrderDecPoints($CustomerID,$OrderNumber,$OperateReason=6,$type=1){
        $res = $this->db->transaction(function() use ($CustomerID,$OrderNumber,$OperateReason,$type) {
            $points_details_where['CustomerID'] = $CustomerID;
            $points_details_where['OrderNumber'] = $OrderNumber;
            $points_details_where['OperateReason'] = 5;
            $details_data['OperateType'] = 0;
            $details_data['CustomerID'] = $CustomerID;
            $details_data['OrderNumber'] = $OrderNumber;
            $details_data['TransactionTime'] = time();
            $details_data['CreateTime'] = time();
            $details_data['Memo'] = "The order has been cancelled, deducting the corresponding points！";
            $details_data['Operator'] = "系统后台";
            $details_data['OperateReason'] = $OperateReason;
            $details_data['ReasonDetail'] = "The order has been cancelled, deducting the corresponding points！";
            $details_data['Status'] = 2;
            $details_data['IsNewDx'] = 1;
                $points_details = $this->db->table($this->points_details)->where($points_details_where)->field("ActiveFlag,PointsCount")->find();
                $details_data['PointsCount'] = -$points_details['PointsCount'];
                if($points_details['ActiveFlag'] == 0){//是否已经将冻结积分增加到总积分
                    $this->db->table($this->points_details)->where($points_details_where)->update(['Status'=>2]);
                    $this->db->table($this->table)->where(['CustomerID'=>$CustomerID])->setDec("NewInactiveCount",$points_details['PointsCount']);
                    $res = $this->db->table($this->points_details)->insertGetId($details_data);
                }else{
                    $this->db->table($this->points_details)->where($points_details_where)->update(['Status'=>2]);
                    $this->db->table($this->table)->where(['CustomerID'=>$CustomerID])->setDec("NewTotalCount",$points_details['PointsCount']);
                    $res = $this->db->table($this->points_details)->insertGetId($details_data);
                }
            return $res;
        });
        return $res;
    }


    /*
     * affiliate订单取消扣减推荐积分
     * */
    public function CancelOrderDecReferralPoints($CustomerID,$OrderNumber,$OperateReason=8,$type=1){
        $res = $this->db->transaction(function() use ($CustomerID,$OrderNumber,$OperateReason,$type) {
            $points_details_where['CustomerID'] = $CustomerID;
            $points_details_where['OrderNumber'] = $OrderNumber;
            $points_details_where['OperateReason'] = 7;
            $details_data['CustomerID'] = $CustomerID;
            $details_data['OrderNumber'] = $OrderNumber;
            $details_data['TransactionTime'] = time();
            $details_data['CreateTime'] = time();
            $details_data['OperateType'] = 0;
            $details_data['Memo'] = "The order has been cancelled, deducting the corresponding referra points！";
            $details_data['Operator'] = "系统后台";
            $details_data['OperateReason'] = $OperateReason;
            $details_data['ReasonDetail'] = "The order has been cancelled, deducting the corresponding referra points！";
            $details_data['Status'] = 2;
            $details_data['IsNewDx'] = 1;
            $points_details = $this->db->table($this->referral_points_details)->where($points_details_where)->field("ActiveFlag,PointsCount")->find();
            $details_data['PointsCount'] = -$points_details['PointsCount'];
            if($points_details['ActiveFlag'] == 0){//是否已经将冻结积分增加到总积分
                $this->db->table($this->referral_points_details)->where($points_details_where)->update(['Status'=>2]);
                $this->db->table($this->referral_table)->where(['CustomerID'=>$CustomerID])->setDec("NewInactiveCount",$points_details['PointsCount']);
                $res = $this->db->table($this->referral_points_details)->insertGetId($details_data);
            }else{
                $this->db->table($this->referral_points_details)->where($points_details_where)->update(['Status'=>2]);
                $this->db->table($this->referral_table)->where(['CustomerID'=>$CustomerID])->setDec("NewTotalCount",$points_details['PointsCount']);
                $res = $this->db->table($this->referral_points_details)->insertGetId($details_data);
            }
            return $res;
        });
        return $res;
    }

    /*
     * affiliate佣金提现审核不通过
     * */
    public function auditWithdrawalReferralPoints($CustomerID,$PointsCount,$OrderNumber,$Reason,$Operator,$OperateReason=20,$type=1){
        $res = $this->db->transaction(function() use ($CustomerID,$PointsCount,$OrderNumber,$Reason,$Operator,$OperateReason,$type) {
            $details_data['CustomerID'] = $CustomerID;
            $details_data['TransactionTime'] = time();
            $details_data['OrderNumber'] = $OrderNumber;
            $details_data['CreateTime'] = time();
            $details_data['Memo'] = $Reason;
            $details_data['Operator'] = !empty($Operator)?$Operator:"系统后台";
            $details_data['OperateReason'] = $OperateReason;
            $details_data['ReasonDetail'] = $Reason;
            $details_data['Status'] = 2;
            $details_data['IsNewDx'] = 1;
            $details_data['PointsCount'] = $PointsCount;
            if($type == 0){//加入到冻结积分
                $this->db->table($this->referral_table)->where(['CustomerID'=>$CustomerID])->setInc("NewInactiveCount",$PointsCount);
                $res = $this->db->table($this->referral_points_details)->insertGetId($details_data);
            }else{//加入到可用积分
                $this->db->table($this->referral_table)->where(['CustomerID'=>$CustomerID])->setInc("NewTotalCount",$PointsCount);
                $res = $this->db->table($this->referral_points_details)->insertGetId($details_data);
            }
            return $res;
        });
        return $res;
    }
    /*
    * 获取用户选择订单积分
    * */
    public function OrderAffiliateCredit($data){
        $cash_withdrawal = 0;
        $withdrawal_amount = 0;//最多提取金额
        $cumulative_amount = 0;//累积金额
        $res_array = [];
        $time = strtotime(date("Y-m-01 00:00:00"));
// return $time;
        $list_affiliate_apply = $this->admin->table($this->affiliate_apply)->where(['affiliate_id'=>$data,'status'=>0,'add_time'=>['egt',$time]])->select();
        if(!empty($list_affiliate_apply)){
            foreach ($list_affiliate_apply as $k_apply => $v_apply) {
                 if(!empty($v_apply['amount'])){
                     $cash_withdrawal += $v_apply['amount'];
                 }
            }
        }
        $withdrawal_amount = 1000 - $cash_withdrawal;//一个月最多只能体现1000美元，每次大于等于30美元
        if($withdrawal_amount < 30){
           return apiReturn(['code'=>101,'msg'=>'Your monthly quota has reached the limit and you cannot withdraw it again!']);
        }

        $list = $this->admin->table($this->affiliate_order)->where(['affiliate_id'=>$data,'settlement_status'=>2])->field("affiliate_order_id,order_number")->select();
        $where = '';
        $where_array = [];
        if(empty($list)){
           return apiReturn(['code'=>101,'msg'=>'The withdrawal amount has not been reached.']);
        }
        foreach ($list as $k => $v) {
           $where .= $v['affiliate_order_id'].',';
        }
        $where = rtrim($where,',');
        $res = $this->admin->table($this->affiliate_order_item)->where(['affiliate_order_id'=>['in',$where]])->group('affiliate_order_id')->field("affiliate_order_id,sum(commission_price) AS commission_price")->select();

        if(!empty($res)){
            $res_array['affiliate_order_id'] = '';
            foreach ($res as $ke => $va) {
                $cumulative_amount += $va['commission_price'];
                if($cumulative_amount <= $withdrawal_amount){
                    $res_array['cumulative_amount'] = $cumulative_amount;
                    $res_array['affiliate_order_id'] .= $va['affiliate_order_id'].',';
                }
            }
            // return apiReturn(['code'=>101,'msg'=>$res,'as'=>$res_array,'sql'=>$this->admin->table($this->affiliate_order_item)->getlastsql()]);
            if($res_array['cumulative_amount'] >= 30){
               return apiReturn(['code'=>200,'data'=>$res_array,]);
            }else{
               return apiReturn(['code'=>101,'msg'=>'Withdrawal amount must be greater than $30.']);
            }
        }else{
            return apiReturn(['code'=>101,'msg'=>'The withdrawal amount has not been reached.']);
        }


        return $list;
    }
}