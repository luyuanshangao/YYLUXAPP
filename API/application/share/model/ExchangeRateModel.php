<?php
namespace app\share\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use think\Session;
use think\Log;


class ExchangeRateModel extends Model{

    public static $currency_name = array(
        "USD" => '美元',
        "EUR" => '欧元',
        "CNY" => '人民币',
        "HKD" => '港币',
        "GBP" => '英镑',
        "RUB" => '卢布',
        "CZK" => '捷克克朗',
        "JPY" => '日元',
        "ARS" => '阿根廷披索',
        "TRY" => '土耳其里拉',
        "ZAR" => '兰特(南非)',
        "NOK" => '挪威克朗',
        "INR" => '印度卢比',
        "MXN" => '墨西哥比',
        "PLN" => '波兰兹罗提',
        "CAD" => '加元',
        "BRL" => '巴西里尔',
        "AUD" => '澳元',
        "CLP" => '智利比索',
        "ILS" => '以色列谢克尔',
        "UAH" => '乌克兰赫里夫纳',
        "CHF" => '瑞士法郎',
        "DKK" => '丹麦克朗',
        "SEK" => '瑞典克朗',
        "SGD" => '新加坡元',
        "KRW" => '韩元',
        "IDR" => '卢比'
    );

    private $table_rate = 'dx_exchange_rate';
    private $table_rate_log = 'dx_exchange_rate_log';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getDataPaginate($where = array(), $page_size=10,$params = array()){
        return $this->db->table($this->table_rate)->where($where)->paginate($page_size,false,['query'=>$params]);
    }

    /**
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getExchangeRate($id){
        return $this->db->table($this->table_rate)->where(['id' => $id])->find();
    }

    /**
     * @param $id
     * @param $update
     * @return int|string
     * @throws Exception
     */
    public function updateExchangeRate($id,$update){
        return $this->db->table($this->table_rate)->where(['id' => $id])->update($update);
    }

    /**
     * 获取信息【分页】
     * @param array $where
     * @return $this
     */
    public function selectExchangeRate($where = array()){
        return $this->db->table($this->table_rate)->where($where)->field(['From','To','Rate','BaseRate','Ratio','Alarm'])->select();
    }

    /**
     * @param array $before 更新前
     * @param array $after 更新后
     * @param int $rate_id 汇率id
     * @param null $operation 操作人
     * @return int $ret
     */
    public function addExchangeRateLog($before,$after,$rate_id,$operation = null){

        //DX汇率
        if(!empty($after['Rate']) && $before['Rate'] != $after['Rate']){
            $ret = $this->_addExchangeRateLog($before['Rate'],$after['Rate'],$rate_id,'DX汇率',$operation);
        }

        //DX基础汇率
        if(!empty($after['BaseRate']) && $before['BaseRate'] != $after['BaseRate']){
            $ret = $this->_addExchangeRateLog($before['BaseRate'],$after['BaseRate'],$rate_id,'DX基础汇率',$operation);
        }

        //上浮率
        if(!empty($after['Ratio']) && $before['Ratio'] != $after['Ratio']){
            $ret = $this->_addExchangeRateLog($before['Ratio'],$after['Ratio'],$rate_id,'上浮率',$operation);
        }

        //报警阀值
        if(!empty($after['Alarm']) && $before['Alarm'] != $after['Alarm']){
            $ret = $this->_addExchangeRateLog($before['Ratio'],$after['Ratio'],$rate_id,'报警阀值',$operation);
        }
        return $ret;
    }

    /**
     * 添加汇率日志
     * @param $before_v 原值
     * @param $after_v 新值
     * @param $rate_id 汇率ID
     * @param string $field_name 操作类型
     * @param null $operation 操作人
     * @return int|string
     */
    private function _addExchangeRateLog($before_v,$after_v,$rate_id,$field_name,$operation = null){
        $insert['exchange_rate_id'] = $rate_id;
        $insert['operation_before'] = $before_v;
        $insert['operation_after'] = $after_v;
        $insert['operation_type'] = $field_name;
        $insert['operation'] = $operation;
        $insert['add_time'] = date('Y-m-d H:i:s',time());
        return $this->db->table($this->table_rate_log)->insert($insert);
    }
}