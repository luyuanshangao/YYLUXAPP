<?php
namespace app\cic\controller;
use app\common\controller\Base;
use app\common\helpers\CommonLib;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class BlockChainTransaction extends Base
{

    /**
     * 用户区块链收益列表
     * @return mixed
     */
    public function getTransactionList(){
        $list = array();
        $paramData = request()->post();
        $where['customer_id'] = isset($paramData['customer_id']) ? $paramData['customer_id']:0;
        if(empty($where['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        $result = doCurl(API_URL.'orderfrontend/myOrder/getBlockChainOrderList',$paramData,null,true);
        if(!empty($result['code']) && $result['code'] == 200){
            $list = model("BlockChainTransactionModel")->getTransactionMergeOrderList($result['data']);
        }
        return apiReturn(['code'=>200,'data'=>$list]);
    }

    /**
     * 用户区块链收益明细列表
     * @return mixed
     */
    public function getTransactionItemList(){
        $paramData = request()->post();
        $where['customer_id'] = isset($paramData['customer_id']) ? $paramData['customer_id'] : 0;
        $where['block_chain_transaction_id'] = isset($paramData['transaction_id']) ? $paramData['transaction_id'] : 0;
        if(empty($where['customer_id']) || empty($where['block_chain_transaction_id'])){
            return apiReturn(['code'=>1001]);
        }
        $page_size = input("post.page_size",20);
        $page = input("post.page",1);
        $path = input("post.path");
        $order = isset($paramData['order'])?$paramData['order']:"id desc";
        $page_query = isset($paramData['page_query']) ? $paramData['page_query'] : array();
        $list = model("BlockChainTransactionModel")->getTransactionItemList($where,$page_size,$page,$path,$order,$page_query);
        return apiReturn(['code'=>200,'data'=>$list]);
    }


    /**
     * 交易操作 提现操作：余额扣减，驳回操作：余额增加
     * @return mixed
     */
    public function operatorTransaction(){
        $paramData = request()->post();
        if(empty($paramData['customer_id']) || empty($paramData['transaction_id']) || empty($paramData['operator'])){
            return apiReturn(['code'=>1001]);
        }

        $amount = !empty($paramData['amount']) ? $paramData['amount'] : 0;
        //查询交易ID是否存在
        $find = model("BlockChainTransactionModel")->getTransaction(['id' => $paramData['transaction_id']]);
        if(empty($find)){
            return apiReturn(['code'=>100002,'could not find it transactionid']);
        }

        $update = array();
        if($paramData['operator'] == 1){//用户提现，扣减
            $update['total_amount'] = bcsub($find['total_amount'],$amount,8);
            $update['used_amount'] = bcadd($find['used_amount'],$amount,8);

        }elseif($paramData['operator'] == 2){//审核驳回，增加
            $update['total_amount'] = bcadd($find['total_amount'],$amount,8);
            $update['used_amount'] = bcsub($find['used_amount'],$amount,8);
        }else{
            return apiReturn(['code'=>100001,'Unsupported operation']);
        }
        $update['update_time'] = time();

        $item['customer_id'] = $paramData['customer_id'];
        $item['block_chain_transaction_id'] = $paramData['transaction_id'];
        $item['transaction_type'] = $paramData['operator'] == 2 ? 1 : 2;
        $item['amount'] = $amount;
        $item['add_time'] = time();
        $ret = model("BlockChainTransactionModel")->updateTransactionAndItem(['id' => $paramData['transaction_id']],$update,$item);
        if($ret > 0){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>100001,'update failed']);
        }
    }

    /**
     * 交易查询
     * @return mixed
     */
    public function getTransaction(){
        $paramData = request()->post();
        if(empty($paramData['transaction_id'])){
            return apiReturn(['code'=>1001]);
        }
        //查询交易ID是否存在
        $find = model("BlockChainTransactionModel")->getTransaction(['id' => $paramData['transaction_id']]);
        if(empty($find)) {
            return apiReturn(['code' => 100002, 'could not find it transactionid']);
        }
        return apiReturn(['code'=>200,'data' => $find]);
    }

    /**
     * 新增记录
     */
    public function addTransaction(){
        $paramData = request()->post();
        //查询交易ID是否存在
        $find = model("BlockChainTransactionModel")->getTransaction(['order_number' => $paramData['order_number']]);
        if(!empty($find)) {
            return apiReturn(['code' => 100002, 'order number already exists']);
        }
        $insert['customer_id'] = $paramData['customer_id'];
        $insert['order_number'] = $paramData['order_number'];
        $insert['goods_count'] = $paramData['goods_count'];
        $insert['grand_total'] = $paramData['grand_total'];
        $insert['pay_type'] = $paramData['pay_type'];
        $insert['product_id'] = $paramData['product_id'];
        $insert['product_name'] = $paramData['product_name'];
        $insert['contract_term'] = $paramData['contract_term'];
        $insert['virtual_currency'] = $paramData['virtual_currency'];
        $insert['virtual_currency_rate'] = $paramData['virtual_currency_rate'];
        $insert['order_create_on'] = $paramData['create_on'];
        $insert['order_complete_on'] = $paramData['complete_on'];
        $insert['effective_time'] = strtotime(date('Y-m-d H:i:s',$paramData['create_on'])."+1day");
        $insert['total_amount'] = 0;
        $insert['used_amount'] = 0;
        $insert['add_time'] = time();
        $ret = model("BlockChainTransactionModel")->addTransaction($insert);
        if($ret){
            return apiReturn(['code'=>200,'data' => $ret]);
        }else{
            return apiReturn(['code'=>1000001,'msg' => 'add Transaction failed']);
        }
    }


    /**
     * 用户每日收益添加接口
     * @return mixed
     */
    public function dailyExpectedReturn(){
        ini_set('max_execution_time', '0');
        $paramData = request()->post();
        if(empty($paramData['date_effective']) || empty($paramData['product_name'])){
            return apiReturn(['code'=>1001]);
        }
        $rate = !empty($paramData['rate']) ? $paramData['rate'] : 0;
        $data = model("BlockChainTransactionModel")->selectTransaction([
            'product_name' => $paramData['product_name'],
            'effective_time' => ["between",[strtotime($paramData['date_effective']),strtotime($paramData['date_effective'] . ' 23:59:59')]]
        ]);
        if(empty($data)){
            return apiReturn(['code'=>100002,'empty data']);
        }
        $time = time();
        $date = date('Ymd');
        foreach($data as $key => $val){
            $update = $item = array();
            //今天收益是否已执行
            if($val['date_query'] == $date){
                continue;
            }
            //合约时间
            $contract_time  = strtotime(date('Y-m-d H:i:s',$val['effective_time']) .'+3year');
            //时间生效范围内
//            if($time >= $val['effective_time'] && $time <= $contract_time){
                //获取THS数量，按比例更新收益
                $income = bcmul($val['goods_count'],$rate,8);
                $update['total_amount'] = bcadd($val['total_amount'],$income,8);
                $update['update_time'] = $time;
                $update['date_query'] = $date;
                //新增交易明细记录
                $item['customer_id'] = $val['customer_id'];
                $item['block_chain_transaction_id'] = $val['id'];
                $item['transaction_type'] = 1;//收益
                $item['amount'] = $income;
                $item['add_time'] = $time;
                model("BlockChainTransactionModel")->updateTransactionAndItem(['id' =>$val['id']],$update,$item);
//            }else{
//                continue;
//            }
        }
        return apiReturn(['code'=>200]);
    }
}
