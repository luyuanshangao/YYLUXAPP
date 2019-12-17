<?php
namespace app\orderfrontend\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 订单消息
 * @author
 * @version Kevin 2018/3/25
 */
class OrderMessage extends Model{
    private $db;
    private $order = "dx_sales_order";
    private $order_message = "dx_sales_order_message";

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }

    /*
     * 获取订单消息数量
     * */
    public function getOrderMessageCountByUserId($where){
        return $this->db->table($this->order_message)->where($where)->count();
    }

    /*
     * 获取订单信息列表
     * */
    public function getOrderMessageList($where,$page_size=10,$page=1,$path='',$query=''){
        $res = $this->db->table($this->order_message)
            ->alias("om")
            ->join($this->order." o","om.order_id=o.order_id")
            ->field("om.id,om.order_id,o.order_number,o.store_name,om.parent_id,om.user_id,om.user_name,om.message_type,om.message,om.file_url,om.statused,om.create_on")
            ->order("statused asc,create_on desc")
            ->where($where)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($query)?$query:$where]);
        $Page = $res->render();
        $data = $res->toArray();
        foreach ($data['data'] as $key =>$value){
            $read_count = $this->db->table($this->order_message)->where(['order_id'=>$value['order_id'],'message_type'=>1,'statused'=>-1])->count();
            if($read_count>0){
                $data['data'][$key]['is_read_reply'] = -1;
            }else{
                $data['data'][$key]['is_read_reply'] = 1;
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
     *一键阅读回复
     * */
    public function orderMessageFullRead($where){
        $order_id = $this->db->table($this->order_message)->where($where)->value("order_id");
        $res = $this->db->table($this->order_message)
            ->where(['order_id'=>$order_id])
            ->update(['statused'=>1]);
        return $res;
    }
    /**
     * 后台admin留言
     * [AddNotes description]
     */
    public function AddNotes($data,$status){
       if(isset($status) && $status == 1){
          $result = $this->db->table($this->order_message)->where(['order_id'=>$data['order_id'],'message_type'=>3])->update($data);
       }else{
          $result = $this->db->table($this->order_message)->insert($data);
       }

       if(!empty($result)){
          return apiReturn(['code'=>200,'data'=>'数据提交成功']);
       }else{
          return apiReturn(['code'=>100,'data'=>'数据提交失败']);
       }
    }

    /*
     * 解决订单消息
     * */
    public function solvedOrderMessage($where){
        $id = $this->db->table($this->order_message)->where($where)->order("id","DESC")->value("id");
        if($id){
            $update_data['is_reply'] = 3;
            $update_data['solve_time'] = time();
            $update_where['id'] = $id;
            $res = $this->db->table($this->order_message)->where($update_where)->update($update_data);
            return $res;
        }else{
            return false;
        }
    }
}