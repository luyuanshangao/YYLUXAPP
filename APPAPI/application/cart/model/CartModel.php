<?php
namespace app\cart\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;

/**
 * 购物车模型s
 */
class CartModel extends Model{

    protected $table = 'cart';
    protected $table_temp_cart_key = 'temp_cart_key';
    protected $table_pay_config = 'pay_config';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb_cart');
    }

    /**
     * 查找可用状态的支付方式
     */
    public function getPayType($params)
    {
    	$where = array();
    	$query = $this->db->name('pay_config');
    	$where = ['Currency' => $params];

    	return $query->where($where)->field(['PayType.PayPal.introduction'=>false,'PayType.PayPal.status'=>false
            ,'PayType.PayPal.edittime'=>false,'PayType.PayPal.edit_person'=>false,
            'PayType.CreditCard.introduction'=>false,
            //'PayType.CreditCard.status'=>false,
            'PayType.CreditCard.edittime'=>false,
            'PayType.CreditCard.edit_person'=>false,
            'PayType.WebMoney.introduction'=>false,
            //'PayType.WebMoney.status'=>false,
            'PayType.WebMoney.edittime'=>false,
            'PayType.WebMoney.edit_person'=>false,
            'Addtime'=>false,
            'add_person'=>false,
            'edittime'=>false,
            'edit_person'=>false,
        ])->find();
        //return $query->where($where)->find();
    }

    /**
     * 【同步购物车至数据库专用】添加购物车临时KEY
     * @param array $params
     * @return bool|string
     */
    public function addTempCartKey(array $params){
        $rtn = true;
        $time = time();
        try{
            $data_key = $params['DataKey'];
            $data = $this->db->name($this->table_temp_cart_key)->where(['DataKey'=>$data_key])->find();
            if (empty($data)){
                $insert_data['DataKey'] = $data_key;
                $insert_data['AddTime'] = $time;
                $insert_data['AddDate'] = date('Y-m-d H:i:s', $time);
                $res = $this->db->name($this->table_temp_cart_key)->insert($insert_data);
                if (!$res)
                    $rtn = 'insert error.';
            }
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            Log::record('addTempCartKey_异常：'.$e->getMessage());
        }
        return $rtn;
    }

}