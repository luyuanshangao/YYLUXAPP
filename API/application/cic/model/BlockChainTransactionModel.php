<?php
namespace app\cic\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 区块链收益模型
 * @author
 * @version zhongning 2019/10/24
 */
class BlockChainTransactionModel extends Model{

    protected $table = 'cic_block_chain_transaction';
    protected $table_item = 'cic_block_chain_transaction_item';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }

    /**
    * 用户区块链收益列表
    */
    public function getTransactionList($where,$page_size=20,$page=1,$path='',$order='',$page_query = array()){
        $res = $this->db->table($this->table)
            ->where($where)
            ->order($order)
            ->field("id,customer_id,order_number,goods_count,grand_total,pay_type,product_id,product_name,contract_term,virtual_currency,total_amount,used_amount,order_create_on,order_complete_on,effective_time,virtual_currency_rate")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
    * 获取收益数据
    */
    public function getTransaction($where){
        return $this->db->table($this->table)->where($where)->find();
    }

    /**
     * 获取收益数据
     */
    public function selectTransaction($where){
         return $this->db->table($this->table)->where($where)->select();
    }

    /**
     * 更新
     * @param $where
     * @param $update
     * @return int|string
     */
    public function updateTransaction($where,$update){
        return $this->db->table($this->table)->where($where)->update($update);
    }

    /**
     * 更新
     * @param $insert
     * @return int|string
     */
    public function addTransaction($insert){
        return $this->db->table($this->table)->insertGetId($insert);
    }

    /**
    * 用户区块链收益列表
    */
    public function getTransactionItemList($where,$page_size=20,$page=1,$path='',$order='',$page_query = array()){
        $res = $this->db->table($this->table_item)
            ->where($where)
            ->order($order)
            ->field("id,transaction_type,amount,add_time")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * @param $data
     * @return int|string
     */
    public function insertTransactionItem($data){
        return $this->db->table($this->table_item)->insertGetId($data);
    }

    /**
     * 事物添加回滚
     * @param $where 收益表更新条件
     * @param $update 收益表更新
     * @param $insert item表插入时间
     * @return bool|int|string
     */
    public function updateTransactionAndItem($where,$update,$insert){
        $dbInstanceObj = $this->db;
        $dbInstanceObj->startTrans();
        //更新订单状态
        $rtn = $dbInstanceObj->table($this->table)->where($where)->update($update);
        if($rtn){
            $dbInstanceObj->table($this->table_item)->insert($insert);
        }else{
            $rtn = false;
            $dbInstanceObj->rollback();
        }
        $dbInstanceObj->commit();
        unset($dbInstanceObj);
        return $rtn;
    }

    /**
     * 用户区块链收益列表
     */
    public function getTransactionMergeOrderList($data){
        if(!empty($data['data'])){
            foreach($data['data'] as $key => $val){
                $insert = array();
                $data['data'][$key]['total_amount'] = '0.00000000';
                $data['data'][$key]['used_amount'] = '0.00000000';
                if($val['order_status'] == 200){
                    //查找数据
                    $find = $this->getTransaction(['order_number' => $val['order_number']]);
                    if(empty($find)){
                        //插入数据
                        $insert['customer_id'] = $val['customer_id'];
                        $insert['order_number'] = $val['order_number'];
                        $insert['goods_count'] = $val['goods_count'];
                        $insert['grand_total'] = $val['grand_total'];
                        $insert['pay_type'] = $val['pay_type'];
                        $insert['product_id'] = $val['product_id'];
                        $insert['product_name'] = $val['product_name'];
                        $insert['contract_term'] = $val['contract_term'];
                        $insert['virtual_currency'] = $val['virtual_currency'];
                        $insert['virtual_currency_rate'] = $val['virtual_currency_rate'];
                        $insert['order_create_on'] = $val['order_create_on'];
                        $insert['order_complete_on'] = $val['order_create_on'];
                        $insert['effective_time'] = $val['effective_time'];
                        $insert['total_amount'] = 0;
                        $insert['used_amount'] = 0;
                        $insert['add_time'] = time();
                        $id = $this->addTransaction($insert);
                        if($id > 0 ){
                            $data['data'][$key]['id'] = $find['total_amount'];
                        }
                    }else{
                        $data['data'][$key]['id'] = $find['id'];
                        $data['data'][$key]['total_amount'] = $find['total_amount'];
                        $data['data'][$key]['used_amount'] = $find['used_amount'];
                    }
                }
            }
        }
        return $data;
    }
}