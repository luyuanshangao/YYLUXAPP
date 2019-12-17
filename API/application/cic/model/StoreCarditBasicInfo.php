<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * SC信息模型
 * @author
 * @version Kevin 2018/3/25
 */
class StoreCarditBasicInfo extends Model{
    protected $table = 'cic_store_cardit_basic_info';
    protected $transaction_table = 'cic_store_credit_transaction';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
     * 增加减少用户SC
     * */
    public function editStoreCarditBasicInfo($CustomerID,$CurrencyType,$edit_type,$number){
        $where['CustomerID'] = $CustomerID;
        $where['CurrencyType'] = $CurrencyType;
        if($number>0){
            $res = $this->db->table($this->table)->where($where)->setInc($edit_type,$number);
        }else{
            $res = $this->db->table($this->table)->where($where)->setDec($edit_type,abs($number));
        }
        return $res;
    }

    /*
     * 获取用户币种
     * */
    public function getCurrencyTypeByCustomerID($CustomerID){
        $where['CustomerID'] = $CustomerID;
        $res = $this->db->table($this->table)->where($where)->column("CurrencyType");
        return $res;
    }

    /*
    * 获取用户积分
    * */
    public function getStoreCarditBasicInfo($where){
        //$where['CustomerID'] = $CustomerID;
        $res = $this->db->table($this->table)->where($where)->field("ID,CustomerID,CurrencyType,TotalAmount,UsedAmount,UsableAmount,FreezeAmount,Memo")->find();
        return $res;
    }


    /*
     * StoreCredit支付退款
     * type 1支付 2
     * */
    public function PaymentRefundByStoreCredit($paramData,$type=1){
        if($type==1){
            $paramData['TransactionType'] = "PM";
            $paramData['TransactionAmount'] = $paramData['PaymentAmount'];
        }else{
            $paramData['TransactionType'] = "RF";
            $paramData['TransactionAmount'] = $paramData['RefundAmount'];
        }
       $res = $this->db->transaction(function()use ($paramData,$type){
            $where['CustomerID'] = $paramData['CustomerID'];
            $where['CurrencyType'] = $paramData['CurrencyType'];
            if($type==1){
                /*减少积分*/
                $res = $this->db->table($this->table)->where($where)->setDec("UsableAmount",$paramData['TransactionAmount']);
                $transaction['Memo'] = 'Payment Deducting';
                $paramData['TransactionAmount'] = -$paramData['TransactionAmount'];
            }else{
                /*增加积分*/
                $res = $this->db->table($this->table)->where($where)->setInc("UsableAmount",$paramData['TransactionAmount']);
                $transaction['Memo'] = 'Refunded Add';
                $transaction['RefundedAmount'] = $paramData['RefundAmount'];
            }
            /*记录详情*/
            $transaction['RequestClientID'] = isset($paramData['ClientID'])?$paramData['ClientID']:1;
            $transaction['CustomerID'] = $paramData['CustomerID'];
            $transaction['OrderNumber'] = $paramData['OrderNumber'];
            $transaction['DXBankOrderNumber'] = isset($paramData['DXBankOrderNumber'])?$paramData['DXBankOrderNumber']:$this->createDXBankOrderNumber($paramData['TransactionType']);
            $transaction['CurrencyType'] = $paramData['CurrencyType'];
            $transaction['TransactionType'] = $paramData['TransactionType'];
            $transaction['TransactionTime'] = time();
            //$transaction['OperateType'] = $paramData['OperateType'];
            $transaction['TransactionAmount'] = $paramData['TransactionAmount'];
            $transaction['TransactionStatus'] = '00';
            $transaction['CustomField'] = isset($paramData['CustomField'])?$paramData['CustomField']:'';
            $transaction['CreateTime'] = time();
            $transaction['RequestClientID'] = 1;
            $transaction['ManualOperateReason'] = isset($paramData['DataSource'])?$paramData['DataSource']:'';
            $transaction['DataSource'] = $paramData['DataSource'];
            $transaction['Status'] = 0;
            $this->db->table($this->transaction_table)->insert($transaction);
            return ['status'=>$res,'transaction'=>$transaction];
        });
       if($res['status']){
           $res_data['ClientID'] = $res['transaction']['RequestClientID'];
           $res_data['CurrencyType'] = $res['transaction']['CurrencyType'];
           $res_data['CustomerID'] = $res['transaction']['CustomerID'];
           $res_data['OrderNumber'] = $res['transaction']['OrderNumber'];
           $res_data['DXBankOrderNumber'] = $res['transaction']['DXBankOrderNumber'];

           $res_data['TransactionTime'] = date("Y-m-d H:i:s");

           $res_data['CustomField'] = $res['transaction']['CustomField'];;
           $res_data['ErrorCode'] = '';
           $res_data['ErrorMessage'] = '';
           if($type==1){
               $res_data['PaymentAmount'] = $res['transaction']['TransactionAmount'];
               $res_data['PaymentStatus'] = '00';
           }else{
               $res_data['RefundAmount'] = $res['transaction']['TransactionAmount'];
               $res_data['RefundStatus'] = '00';
           }
       }else{
           $res_data = false;
       }
        return $res_data;
    }


    /*
     * StoreCredit支付退款
     * type 1支付 2
     * */
    public function OperateStoreCredit($paramData){
        $res = $this->db->transaction(function()use ($paramData){
            $where['CustomerID'] = $paramData['CustomerID'];
            $where['CurrencyType'] = $paramData['CurrencyType'];
            if($paramData['TransationType'] == 0){
                /*减少积分*/
                $res = $this->db->table($this->table)->where($where)->setDec("UsableAmount",$paramData['StoreCreditAmount']);
                $paramData['TransactionType'] = $prefix = "RD";
                $transaction['TransactionAmount'] = -$paramData['StoreCreditAmount'];
            }else{
                /*增加积分*/
                $res = $this->db->table($this->table)->where($where)->setInc("UsableAmount",$paramData['StoreCreditAmount']);
                $paramData['TransactionType'] = $prefix = "AD";
                $prefix = "AD";
                $transaction['TransactionAmount'] = $paramData['StoreCreditAmount'];
                $transaction['RefundedAmount'] = $paramData['StoreCreditAmount'];
            }
            /*记录详情*/
            $transaction['RequestClientID'] = isset($paramData['ClientID'])?$paramData['ClientID']:1;
            $transaction['CustomerID'] = $paramData['CustomerID'];
            $transaction['OrderNumber'] = $paramData['OrderNumber'];
            $transaction['DXBankOrderNumber'] = $this->createDXBankOrderNumber($prefix);
            $transaction['CurrencyType'] = $paramData['CurrencyType'];
            $transaction['TransactionType'] = isset($paramData['TransactionType'])?$paramData['TransactionType']:0;
            $transaction['TransactionTime'] = time();
            //$transaction['OperateType'] = $paramData['OperateType'];
            $transaction['TransactionStatus'] = '00';
            $transaction['CustomField'] = isset($paramData['CustomField'])?$paramData['CustomField']:'';
            $transaction['CreateTime'] = time();
            $transaction['Memo'] = isset($paramData['Memo'])?$paramData['Memo']:'';
            $transaction['RequestClientID'] = 1;
            $transaction['ManualOperateReason'] = isset($paramData['DataSource'])?$paramData['DataSource']:'';
            $transaction['DataSource'] = $paramData['DataSource'];
            $transaction['Status'] = 0;
            $TransactionID = $this->db->table($this->transaction_table)->insertGetId($transaction);
            return ['status'=>$res,'transaction'=>$transaction,'TransactionID'=>$TransactionID];
        });
        if($res['status']){
            $res_data['ClientID'] = "Y";
            $res_data['TransactionID'] = $res['TransactionID'];
            $res_data['StoreCreditUsableAmout'] = $this->getStoreCreditUsableAmout(['CustomerID'=>$paramData['CustomerID']]);
            $res_data['ErrorCode'] = '';
            $res_data['ErrorMessage'] = '';
        }else{
            $res_data = false;
        }
        return $res_data;
    }





    /*
     * 5.查询StoreCredit交易记录
     * */
    public function QueryStoreCreditTransaction($where,$page_size,$page,$path){
        $res = $this->db->table($this->transaction_table)
            ->where($where)
            ->field("RequestClientID ClientID,CurrencyType,CustomerID,OrderNumber,DXBankOrderNumber,TransactionType,TransactionTime,CustomField,TransactionStatus,Memo,DataSource,TransactionAmount,RefundedAmount,Status,ManualOperateReason,AccountSource,AccountTo")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $res = $res->toArray();
        if(isset($res['data'])){
            foreach ($res['data'] as $key => $value){
                $res['data'][$key]['TransactionTime'] = date("Y-m-d H:i:s",$value['TransactionTime']);
            }
        }
        return$res;
    }

    /*
     * 生成DX Bank订单编号
     * */
    public function createDXBankOrderNumber($prefix=''){
        $_time = substr(date("Ymd"),2,6);
        $_rand = rand(10000,99999);
        $res = $prefix.$_time.$_rand;
        $where['DXBankOrderNumber'] = $res;
        $DXBankOrderNumber = $this->db->table($this->transaction_table)->where($where)->count();
        if($DXBankOrderNumber>0){
            $this->createDXBankOrderNumber($prefix);
        }else{
            return $res;
        }
    }

    /*
     * 查询用户SC余额
     * */
    public function getStoreCreditUsableAmout($where){
        $StoreCreditUsableAmout = $this->db->table($this->table)->where($where)->value("UsableAmount");
        return $StoreCreditUsableAmout;
    }

    /*
     * 查询用户SC余额
     * */
    public function getStoreCreditTransaction($where){
        $StoreCreditTransaction = $this->db->table($this->transaction_table)->where($where)->find();
        return $StoreCreditTransaction;
    }
}