<?php
/**
 * Created by PhpStorm.
 * User: Hai.Ouyang
 * Date: 2019-06-04
 */
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Model;
use think\Db;
use app\common\helpers\CommonLib;

class Payment  extends Model{
    private $db;
    private $transaction_table;
    private $notification_table;

    public function __construct(){
        $this->db = "db_payment";
        $this->transaction_table = "transaction";
        $this->notification_table = "notification";
    }

    public function getTransactionById($where){
        $res = Db::connect($this->db)->table($this->transaction_table)->where($where)->find();

        return $res;
    }

    public function getTransactionByOrderNumber($where){
        $res = Db::connect($this->db)->table($this->transaction_table)->where($where)->select();

        return $res;
    }
 /*   public function getTransactionByOrderNumber($orderMasterNumber){
        $res = Db::connect($this->db)->table($this->transaction_table)->where(['order_master_number'=>$orderMasterNumber])->select();

        return $res;
    }*/

    public function getNotification($md5){
        $res = Db::connect($this->db)->table($this->notification_table)->where(['md5'=>$md5])->find();

        return $res;
    }

    public function addNotification($params){

        $msecTime      = CommonLib::getMsecTime();
        $time          = intval(substr($msecTime,0,10));
        $datetimePrc   = date('Y-m-d H:i:s', ($time+8*3600)).'.'.substr($msecTime,10);

        $data = array(
            'transaction_channel'   => $params['TransactionChannel'],
            'type'                  => $params['TradeStatus'],
            'type_summary'          => $params['TradeStatusSummary'],
            'invoice_id'            => $params['InvoiceId'],
            'response_data'         => base64_encode(json_encode($params)),
            'md5'                   => $params['md5'],
            'add_time'              => $time,
            'add_time_prc'          => $datetimePrc,
            'last_update_time'      => $time,
            'last_update_time_prc'  => $datetimePrc,
        );

        $res = Db::connect($this->db)->table($this->notification_table)->insert($data);        

        return $res;
    }

    public function getCaseById($where){
        return Db::connect($this->db)->table($this->notification_table)->where($where)->field("invoice_id,type,response_data")->select();
    }
}