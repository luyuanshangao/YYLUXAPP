<?php

namespace app\admin\model;

use think\Log;
use think\Model;
use think\Db;

/**
 * 区块链订单模型
 * 开发: zhongning 20191022
 */
class BlockChainOrderModel  extends Model{


    protected $db;
    protected $table;
    protected $table_item;


    public static $orderStatus = array(
        "100" => '待付款',
        "120" => '付款确认中',
        "200" => '付款成功',
    );

    public function __construct(){
        $this->db = Db::connect('db_order');
        $this->table = "dx_block_chain_order";
        $this->table_item = "dx_block_chain_order_item";
    }

    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @param $params
     * @return $this
     */
    public function getBlockChainOrderPaginate($where = array(), $page_size = 10,$params = array()){
        return $this->db->table($this->table)->where($where)->order('create_on','desc')->paginate($page_size,false,['query'=>$params]);
    }

    /**
     * 获取关联订单商品列表
     * @param array $where
     * @return array
     */
    public function getBlockChainOrderList($where = array()){
        $res = $this->db->table($this->table)->alias('o')
            ->join($this->table_item." oi","o.order_id=oi.order_id")
            ->field('o.order_id,order_number,o.add_time,customer_id,customer_name,goods_count,grand_total,product_id,product_name')
            ->where($where)
            ->select();
        return $res;
    }
}