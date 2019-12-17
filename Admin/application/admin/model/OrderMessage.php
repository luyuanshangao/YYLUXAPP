<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2019/2/15
 * Time: 10:55
 */
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Model;
use think\Db;
class OrderMessage  extends Model{
    public function __construct(){
        $this->db = "db_order";
        $this->table = "dx_sales_order_message";
        $this->order_table = "dx_sales_order";
        $this->order_item_table = "dx_sales_order_item";
    }

    public function getOrderMessage($order_where,$message_where='',$page_size,$page,$query1,$is_restrict = 0,$order=''){
        $order_table1 = $this->order_table;
        /*利用子查询查询出符合条件的数据*/
        $query = Db::connect($this->db)->table("dx_sales_order_message")->alias("om");
        if($order_where){
            $query->where('o.order_id','in',function($query_1) use($order_table1,$order_where) {
                /*第一步：当查询条件带有订单数据时，先将符合订单的数据筛选处理*/
                $query_1->table($order_table1)
                    ->alias("o")
                    ->join("dx_sales_order_item oi","o.order_id=oi.order_id","LEFT")
                    ->where($order_where)->field("o.order_id");
            });
        }
        if(!empty($message_where['distribution_admin_id']) && !is_array($message_where['distribution_admin_id']) && $is_restrict){
            $query->where("distribution_admin_id= '".$message_where['distribution_admin_id']."' OR distribution_admin_id = 0");
            unset($message_where['distribution_admin_id']);
        }
        $query->where($message_where);
        if(!empty($order)){
            $query->order($order);
        }
        $data = $query->order('is_reply asc,is_crash ASC,id desc')->join("dx_sales_order o","om.order_id = o.order_id","LEFT")
            ->field("dx_sales_order_message.*,o.order_id,o.order_number,o.store_id,o.store_name,o.customer_id,o.customer_name,o.add_time,o.order_status,o.pay_type,o.country,o.captured_amount_usd,o.fsc_shipment")
            ->paginate($page_size,false,[ 'page' => $page,'query'=>$query1]);
        $res = $data->toArray();
        foreach ($res['data'] as $key=>$value){
            $res['data'][$key]['message'] = htmlspecialchars_decode(htmlspecialchars_decode($value['message']));
            $no_reply_where['order_id'] = $value['order_id'];
            $no_reply_where['message_type'] = 2;
            $no_reply_where['is_reply'] = 1;
            $res['data'][$key]['no_reply_count'] = Db::connect($this->db)->table("dx_sales_order_message")->where($no_reply_where)->count();
        }
        $res['Page'] = $data->render();
        return $res;
    }



    /*修改订单消息*/
    public function updateOrderMessage($where,$data){
        return Db::connect($this->db)->table($this->table)->where($where)->update($data);
    }


    /*获取用户订单消息*/
    public function getUserOrderMessage($where){
        return Db::connect($this->db)->table($this->table)->where($where)->select();
    }

    /*获取用户最新一条订单消息*/
    public function getUserNewOrderMessageId($where){
        return Db::connect($this->db)->table($this->table)->where($where)->order("id","desc")->value("id");
    }

    /*获取用户最新一条订单消息*/
    public function getUserNewOrderMessageData($where){
        return Db::connect($this->db)->table($this->table)->where($where)->order("id","desc")->find();
    }

    /*获取用户第一条订单消息*/
    public function getUserOneOrderMessageId($where){
        return Db::connect($this->db)->table($this->table)->where($where)->order("id","desc")->field("id,aging")->find();
    }

    /*回复订单信息*/
    public function addOrderMessage($data,$is_solved=0){
        $one_message = Db::connect($this->db)->table($this->table)->where(['order_id'=>$data['order_id'],'message_type'=>2])->field("id,create_on,aging")->find();//第一天提问的问题

        if($one_message['aging'] == 0){
            $aging = time()-$one_message['create_on'];
            $resly_update['aging'] = sprintf("%01.2f", $aging/3600);
        }
        $res = Db::connect($this->db)->table($this->table)->transaction(function() use ($data,$is_solved) {
            $res = Db::connect($this->db)->table($this->table)->insert($data);
            /*如果是后台回复，则更改买家留言回复状态*/
            if(isset($data['order_id']) && !empty($data['order_id'])){
                $update_where['order_id'] = $data['order_id'];
                $update_where['message_type'] = 2;
                if($is_solved){
                    $is_reply = 3;
                }else{
                    $is_reply = 2;
                }
                $update_where['is_reply'] = ['neq',3];
                Db::connect($this->db)->table($this->table)->where($update_where)->update(['is_reply'=>$is_reply]);
                if(isset($data['parent_id']) && !empty($data['parent_id'])){
                    //判断是否分配，未分配这回复直接分配给此用户
                    $parent_distribution_where['order_id'] = $data['order_id'];
                    $parent_distribution_where['message_type'] = 2;
                    $distribution_data = Db::connect($this->db)->table($this->table)->where($parent_distribution_where)->field("id,distribution_admin_id,operator_admin_id")->select();
                    foreach ($distribution_data as $key=>$value){
                        $resly_update = array();
                        if($value['distribution_admin_id'] == 0){
                            $resly_update['distribution_admin_id'] = session("userid");
                            $resly_update['distribution_admin'] = session("username");
                            $resly_update['distribution_time'] = time();
                        }
                        if($value['operator_admin_id'] == 0){
                            $resly_update['operator_admin_id'] = session("userid");
                            $resly_update['operator_admin'] = session("username");
                            $resly_update['reply_time'] = time();
                        }
                        if(!empty($resly_update)){
                            /*记录操作人*/
                            $resly_update['id'] = $value['id'];
                            Db::connect($this->db)->table($this->table)->update($resly_update);
                        }
                    }

                    /*获取回复时效*/
                    $one_message = Db::connect($this->db)->table($this->table)->where(['order_id'=>$data['order_id'],'message_type'=>2])->field("id,create_on,aging")->find();//第一天提问的问题
                    if($one_message['aging'] == 0){
                        if($one_message['distribution_time']>0){
                            $aging = time()-$one_message['distribution_time'];
                            $aging_update['aging'] = sprintf("%01.2f", $aging/3600);
                        }else{
                            $aging_update['aging'] = 0.01;
                        }
                        $aging_update['id'] = $one_message['id'];
                        Db::connect($this->db)->table($this->table)->update($aging_update);
                    }
                }
            }
            return $res;
        });
        return $res;
    }

    /*获取到订单留言ID*/
    public function getOrderMessageIds($where,$field="id"){
        return Db::connect($this->db)->table($this->table)->where($where)->column($field);
    }

    /*获取订单留言数量统计*/
    public function getOrderMessageTotal($where){
        $data = array();
        /*留言订单数量*/
        $where['message_type'] = 2;
        $order_count_where = $where;
        unset($order_count_where['distribution_admin_id']);
        $data['order_count'] = Db::connect($this->db)->table($this->table)->alias("om")->where($order_count_where)->count("DISTINCT(order_id)");
        /*新进留言订单数量*/
        $new_order_count_where = $where;
        $new_order_count_where['is_earliest'] = 1;
        unset($new_order_count_where['distribution_admin_id']);
        $data['new_order_count'] = Db::connect($this->db)->table($this->table)->alias("om")->where($new_order_count_where)->count("DISTINCT(order_id)");
        /*支付：每日未分配且未回复的pending payment、payment processing和paymentconfirmed状态的订单数量*/
        $new_order_pending_where = $where;
        $new_order_pending_where['order_status'] = ["IN","100,120,200"];
        $data['new_order_pending_count'] = Db::connect($this->db)
            ->table($this->table)
            ->alias("om")
            ->join($this->order_table." o","o.order_id=om.order_id")
            ->where($new_order_pending_where)
            ->count("DISTINCT(o.order_id)");
        /*发货状态：每日未分配且未回复的shipment processing、picking completed和partial shipped状态的订单数量*/
        $new_order_shipment_where = $where;
        $new_order_shipment_where['order_status'] = ["IN","400,407,500,600"];
        $data['new_order_shipment_count'] = Db::connect($this->db)
            ->table($this->table)
            ->alias("om")
            ->join($this->order_table." o","o.order_id=om.order_id")
            ->where($new_order_shipment_where)
            ->count("DISTINCT(o.order_id)");
        /*未收到货：每日未分配且未回复的 公司已发货和awaiting delivery状态的订单数量*/
        $new_order_awaiting_where = $where;
        $new_order_awaiting_where['order_status'] = ["IN","700"];
        $data['new_order_awaiting_count'] = Db::connect($this->db)
            ->table($this->table)
            ->alias("om")
            ->join($this->order_table." o","o.order_id=om.order_id")
            ->where($new_order_awaiting_where)
            ->count("DISTINCT(o.order_id)");
        /*售后：每日未分配且未回复的completed、aftersales processing和closed状态的订单数量*/
        $new_order_aftersales_where = $where;
        $new_order_aftersales_where['order_status'] = ["IN","900,920,1900"];
        $data['new_order_aftersales_count'] = Db::connect($this->db)
            ->table($this->table)
            ->alias("om")
            ->join($this->order_table." o","o.order_id=om.order_id")
            ->where($new_order_aftersales_where)
            ->count("DISTINCT(o.order_id)");
        /*其他：1400:Cancelled-取消订单;1500:Hold-等待;1600:Claim-索赔;1700:Disputes-纠纷中;1800:Conflict-争议订单;*/
        $new_order_other_where = $where;
        $new_order_other_where['order_status'] = ["IN","1400,1500,1600,1700,1800"];
        $data['new_order_other_count'] = Db::connect($this->db)
            ->table($this->table)
            ->alias("om")
            ->join($this->order_table." o","o.order_id=om.order_id")
            ->where($new_order_other_where)
            ->count("DISTINCT(o.order_id)");

        /*分配订单数量*/
        $distribution_order_count_where = $where;
        if(isset($distribution_order_count_where['om.create_on'])){
            $distribution_order_count_where['distribution_time'] = $distribution_order_count_where['om.create_on'];
            unset($distribution_order_count_where['om.create_on']);
        }
        $data['distribution_order_count'] = Db::connect($this->db)->table($this->table)->alias("om")->where($distribution_order_count_where)->count("DISTINCT(order_id)");
        /*回复订单数量*/
        $reply_order_count_where = $where;
        if(isset($reply_order_count_where['om.create_on'])){
            $reply_order_count_where['reply_time'] = $reply_order_count_where['om.create_on'];
            unset($reply_order_count_where['om.create_on']);
        }
        $reply_order_count_where['is_reply'] = ['GT',1];
        $data['reply_order_count'] = Db::connect($this->db)->table($this->table)->alias("om")->where($reply_order_count_where)->count("DISTINCT(order_id)");
        /*解决订单数量*/
        $solve_order_count_where = $where;
        if(isset($solve_order_count_where['om.create_on'])){
            $solve_order_count_where['reply_time'] = $solve_order_count_where['om.create_on'];
            unset($solve_order_count_where['om.create_on']);
        }
        $solve_order_count_where['is_reply'] = 3;
        $data['solve_order_count'] = Db::connect($this->db)->table($this->table)->alias("om")->where($solve_order_count_where)->count("DISTINCT(order_id)");
        /*回复时效*/
        $order_aging_where = $where;
        if(isset($order_aging_where['om.create_on'])){
            $order_aging_where['reply_time'] = $order_aging_where['om.create_on'];
            unset($order_aging_where['om.create_on']);
        }
        //$order_aging_where['is_earliest'] = 1;
        $order_aging_where['aging'] = ['GT',0];
        $order_aging_sum = Db::connect($this->db)->table($this->table)->alias("om")->where($order_aging_where)->sum("aging");
        $order_aging_count = Db::connect($this->db)->table($this->table)->alias("om")->where($order_aging_where)->count("DISTINCT(order_id)");
        $data['order_aging_avg'] = 0;
        if($order_aging_count>0){
            $data['order_aging_avg'] = sprintf("%01.2f", $order_aging_sum/$order_aging_count);
        }
        return $data;
    }

}