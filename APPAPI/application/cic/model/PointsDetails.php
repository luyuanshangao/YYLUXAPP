<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 积分信息详情模型
 * @author
 * @version Kevin 2018/3/25
 */
class PointsDetails extends Model{
    protected $table = 'cic_points_details';
    protected $referral_table = 'cic_referral_points_details';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 新增用户积分详情
* */
    public function addPointsDetails($data,$type=1){
        if($type == 1){
            $res = $this->db->table($this->table)->insertGetId($data);
        }else{
            $res = $this->db->table($this->referral_table)->insertGetId($data);
        }
        return $res;
    }

    /*
    * 获取用户积分详情列表
    * */
    public function getPointsDetailsList($where,$page_size,$page,$path,$type=1){
        if($type == 1){
            $res = $this->db->table($this->table)->where($where)->order("ID desc")->field("ID,CustomerID,ClientID,OrderNumber,TransactionTime,OperateType,PointsCount,ActiveFlag,CreateTime,Memo,Reserve1,DataSource,RequestClientID,Operator,OperateReason,ReasonDetail,ManualOperateReason,Status,CurrencyType,IsNewDx")->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        }else{
            $res = $this->db->table($this->referral_table)->where($where)->order("ID desc")->field("ID,CustomerID,ClientID,OrderNumber,TransactionTime,OperateType,PointsCount,ActiveFlag,CreateTime,Memo,Reserve1,DataSource,RequestClientID,Operator,OperateReason,ReasonDetail,ManualOperateReason,Status,CurrencyType,IsNewDx")->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        }

        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取用户积分详情
     * */
    public function getPointsDetails($where,$type=1){
        if($type == 1) {
            $res = $this->db->table($this->table)->where($where)->field("ID,CustomerID,ClientID,OrderNumber,TransactionTime,OperateType,PointsCount,ActiveFlag,CreateTime,Memo,Reserve1,DataSource,RequestClientID,Operator,OperateReason,ReasonDetail,ManualOperateReason,Status,CurrencyType,IsNewDx")->find();
        }else{
            $res = $this->db->table($this->referral_table)->where($where)->field("ID,CustomerID,ClientID,OrderNumber,TransactionTime,OperateType,PointsCount,ActiveFlag,CreateTime,Memo,Reserve1,DataSource,RequestClientID,Operator,OperateReason,ReasonDetail,ManualOperateReason,Status,CurrencyType,IsNewDx")->find();
        }
        return $res;
    }
}