<?php
namespace app\orderfrontend\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 订单售后模型
 * @author
 * @version Kevin 2018/3/25
 */
class OrderAfterSaleApply extends Model{
    private $db;
    private $order = "dx_sales_order";
    private $order_item = "dx_sales_order_item";
    private $order_message = "dx_sales_order_message";
    private $shipping_address = "dx_order_shipping_address";
    private $order_after_sale_apply = "dx_order_after_sale_apply";
    private $order_after_sale_apply_item = "dx_order_after_sale_apply_item";
    private $order_after_sale_apply_log = "dx_order_after_sale_apply_log";
    private $return_product_expressage = "dx_return_product_expressage";
    private $order_complaint = "dx_order_complaint";
    private $order_accuse = "dx_order_accuse";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }
    /*
    * 保存订单售后申请
    * */
    public function saveOrderAfterSaleApply($data,$where=''){
        if(!isset($data['after_sale_id']) && empty($where)){
            $tran = $this->db->transaction(function () use ($data,$where){
                //如果已经提交了售后申请，则不能再次提交审核
                $apply_data = $this->db->table($this->order_after_sale_apply)->where(['order_number'=>$data['order_number']])->find();
                if (!empty($apply_data)){
                    Log::record('订单'.$data['order_number'].'重复提交售后申请');
                    return -99;exit;
                }
                $data['add_time'] = time();
                $data['after_sale_number'] = createNumner();
                $item = isset($data['item'])?$data['item']:'';
                unset($data['item']);
                $res = $this->db->table($this->order_after_sale_apply)->insertGetId($data);
                if($item){
                    foreach ($item as $key=>$value){
                        $value['after_sale_id'] = $res;
                        $this->db->table($this->order_after_sale_apply_item)->insertGetId($value);
                    }
                }
                    if(!isset($data['initiator']) || $data['initiator'] != 2){
                        //修改原订单状态为关闭-1900
                        $this->db->table($this->order)
                            ->where(['order_number'=>$data['order_number']])
                            ->update(['order_status'=>1900]);
                        return $res;
                    }else{
                        return $res;
                    }
                });
            return $tran;
        }else{
            $tran = $this->db->transaction(function () use ($data,$where){
                $data['edit_time'] = time();
                $item = $data['item'];
                unset($data['item']);
                $res = $this->db->table($this->order_after_sale_apply)->where($where)->update($data);
                if($item){
                    $this->db->table($this->order_after_sale_apply_item)->where('after_sale_id',$data['after_sale_id'])->delete();
                    foreach ($item as $key=>$value){
                        $value['after_sale_id'] = $data['after_sale_id'];
                        $this->db->table($this->order_after_sale_apply_item)->insertGetId($value);
                    }
                }
                if($data['status'] == 6){
                    $log['log_type'] = 1;
                    $log['after_sale_id'] = $data['after_sale_id'];
                    $log['log_type'] = "User application for arbitration";
                    $log['user_type'] = 1;
                    $log['user_id'] = isset($data["customer_id"])?$data["customer_id"]:0;
                    $log['user_name'] = isset($data["user_name"])?$data["user_name"]:"";
                    $log['content'] = "Apply for the successful submission of arbitration!";
                    $log['add_time'] = time();
                    $this->addOrderAfterSaleApplyLog($log);
                }
                return $res;
            });
            return $tran;
        }
        return false;
    }

    /*
    * 获取用户售后请单
    * */
    public function getOrderAfterSaleApplyList($where,$page_size=10,$page=1,$path='',$order=''){
        $res = $this->db->table($this->order_after_sale_apply)
            ->alias("oa")
            ->join($this->order." so","oa.order_id = so.order_id")
            ->field("oa.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time,adjust_price")
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        if(!empty($data)){
            foreach ($data['data'] as $key=>$value){
                //售后状态
                $after_sale_status = config('after_sale_status');
                $status_str = '-';
                foreach ($after_sale_status as $status){
                    if ($value['status'] == $status['code']){
                        $status_str = $status['en_name'];
                        break;
                    }
                }
                $data['data'][$key]['status_str'] = $status_str;
                //售后类型
                $after_sale_type_config = config('after_sale_type');
                $type = $value['type'];
                $refunded_type = $value['refunded_type'];
                $after_sale_reason = $value['after_sale_reason'];
                foreach ($after_sale_type_config as $val) {
                    if ($val['code'] == $type){
                        //售后类型
                        $data['data'][$key]['type_str'] = $val['en_name'];
                        //售后原因
                        foreach ($val['reason'] as $valr){
                            if ($valr['code'] == $after_sale_reason){
                                $data['data'][$key]['after_sale_reason_str'] = $valr['en_name'];
                                break;
                            }
                        }
                        if ($type == 3){
                            //退款类型
                            foreach ($val['refunded_type'] as $valf){
                                if ($valf['code'] == $refunded_type){
                                    $data['data'][$key]['refunded_type_str'] = $valf['en_name'];
                                    break;
                                }
                            }
                        }
                        break;
                    }
                }
                //币种
                $data['data'][$key]['currency_value'] = getCurrency('',$value['currency_code']);
                $oi_tem_where['order_id'] = $value['order_id'];
                $oa_item_where['after_sale_id'] = $value['after_sale_id'];
                $data['data'][$key]['item'] = $this->db->table($this->order_after_sale_apply_item)->where($oa_item_where)->field("after_sale_item_id,after_sale_id,sku_id,sku_num,product_nums,product_price,product_id,product_name,product_img,product_attr_ids,product_attr_desc")->select();
                //$data['data'][$key]['order_item'] = $this->db->table($this->order_item)->where($oi_tem_where)->field("sku_id,sku_num,product_price,product_name,product_img,product_attr_ids,product_attr_desc")->select();
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取用户退款申请详情
     * */
    public function getOrderAfterSaleApplyInfo($where){
        $data = $this->db->table($this->order_after_sale_apply)
            ->alias("oa")
            ->join($this->order." so","oa.order_id = so.order_id")
            ->field("oa.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time")
            ->where($where)->find();
        if(!empty($data)){
            //获取收货订单相关状态
            $after_sale_status_config = config('after_sale_status');
            $status = $data['status'];
            foreach ($after_sale_status_config as $info){
                if ($info['code'] == $status){
                    $data['status_str'] = $info['en_name'];
                    break;
                }
            }
            //获取收货订单相关类型
            $after_sale_type_config = config('after_sale_type');
            $type = $data['type'];
            $refunded_type = $data['refunded_type'];
            $after_sale_reason = $data['after_sale_reason'];
            foreach ($after_sale_type_config as $val) {
                if ($val['code'] == $type){
                    //售后类型
                    $data['type_str'] = $val['en_name'];
                    //售后原因
                    foreach ($val['reason'] as $valr){
                        if ($valr['code'] == $after_sale_reason){
                            $data['after_sale_reason_str'] = $valr['en_name'];
                            break;
                        }
                    }
                    if ($type == 3){
                        //退款类型
                        foreach ($val['refunded_type'] as $valf){
                            if ($valf['code'] == $refunded_type){
                                $data['refunded_type_str'] = $valf['en_name'];
                                break;
                            }
                        }
                    }
                    break;
                }
            }
            //获取售后产品详情数据
            $oi_tem_where['order_id'] = $data['order_id'];
            $oa_item_where['after_sale_id'] = $data['after_sale_id'];
            $address_where['order_id'] = $data['order_id'];
            $data['shipping_address'] = $this->db->table($this->shipping_address)->where($address_where)->find();
            $data['item'] = $this->db->table($this->order_after_sale_apply_item)->where($oa_item_where)->field("after_sale_item_id,after_sale_id,sku_id,sku_num,product_name,product_price,product_nums,product_img,product_attr_ids,product_attr_desc")->select();
            $log_where['after_sale_id'] = $data['after_sale_id'];
            //获取操作数据
            $data['log'] = $this->getOrderAfterSaleApplyLog($log_where);
        }
        return $data;
    }

    /*
   * 保存订单售后记录
   * */
    public function addOrderAfterSaleApplyLog($data=''){
        $data['add_time'] = time();
        $res = $this->db->table($this->order_after_sale_apply_log)->insertGetId($data);
        return $res;
    }

    /**
     * 根据条件更新 售后处理主表 信息
     * @param array $where 条件
     * @param array $up_data  要更新的数据
     * @return int|string
     * @throws \think\Exception
     */
    public function updateApplyDataByWhere(array $where, array $up_data){
        return $this->db->table($this->order_after_sale_apply)->where($where)->update($up_data);
    }

    /*
  * 获取订单退款记录
  * */
    public function getOrderAfterSaleApplyLog($where){
        $res = $this->db->table($this->order_after_sale_apply_log)->where($where)->order(['add_time'=>'asc','log_id'=>'desc'])->select();
        return $res;
    }
    /*
     * 添加退货快递单
     * */
    public function addReturnProductExpressage($data){
        $data['add_time'] = time();
        $res = $this->db->table($this->return_product_expressage)->insertGetId($data);
        return $res;
    }

    /*
     * 添加退货快递单【my专用】
     * */
    public function addReturnProductExpressageForMy($data){
        $rtn = true;
        $this->db->startTrans();
        try{
            //添加退货快递单
            $data['add_time'] = time();
            $this->db->table($this->return_product_expressage)->insertGetId($data);
            //修改售后单状态为“待卖家收货”
            $this->db->table($this->order_after_sale_apply)
                ->where(['after_sale_id'=>$data['after_sale_id']])
                ->update(['status'=>3]);
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            $this->db->rollback();
        }
        return $rtn;
    }

    /*
     * 保存纠纷订单
     * */
    public function saveOrderComplaint($data){
        $data['add_time'] = time();
        $res = $this->db->table($this->order_complaint)->insertGetId($data);
        return $res;
    }
    /*
     * 获取纠纷订单列表
     * */
    public function getOrderComplaintList($where,$page_size=10,$page=1,$path='',$order){
        $res = $this->db->table($this->order_complaint)->field("complaint_id,store_id,store_name,customer_id,order_number,after_sale_id,after_sale_type,after_sale_status,is_platform_intervention,complaint_status,complaint_imgs,remarks,add_time,edit_time")
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        if(!empty($data)){
            foreach ($data['data'] as $key=>$value){
                $oa_item_where['after_sale_id'] = $value['after_sale_id'];
                $data['data'][$key]['item'] = $this->db->table($this->order_after_sale_apply_item)->where($oa_item_where)->field("after_sale_item_id,after_sale_id,sku_id,sku_num,product_name,product_img,product_attr_ids,product_attr_desc")->select();
            }
        }
        $data['Page'] = $Page;
        return $data;
    }
    /*
     * 获取纠纷订单详情
     * */
    public function getOrderComplaintInfo($where){
        $data = $this->db->table($this->order_complaint)
            ->alias("oc")
            ->join($this->order." so","oc.order_number = so.order_number")
            ->field("oc.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time")
            ->where($where)->find();
        if(!empty($data)){
            $oa_item_where['after_sale_id'] = $data['after_sale_id'];
            $data['item'] = $this->db->table($this->order_after_sale_apply_item)->where($oa_item_where)->field("after_sale_item_id,after_sale_id,sku_id,sku_num,product_name,product_img,product_attr_ids,product_attr_desc")->select();
        }
        return $data;
    }

    /*
     * 保存投诉订单
     * */
    public function saveOrderAccuse($data){
        if(!isset($data['accuse_id'])){
            $data['accuse_number'] = createNumner();
            $data['add_time'] = time();
            $res = $this->db->table($this->order_accuse)->insertGetId($data);
        }
        return $res;
    }

    /*
     * 获取投诉订单列表
     * */
    public function getOrderAccuseList($where,$page_size=10,$page=1,$path='',$order){
        $res = $this->db->table($this->order_accuse)->field("accuse_id,accuse_number,order_id,order_number,order_number,customer_id,customer_name,store_id,store_name,accuse_reason,accuse_status,imgs,remarks,add_time,edit_time")
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 取消仲裁
     * @param $data 数据
     * @return mixed
     * @throws \Exception
     * @throws \Throwable
     * @throws \think\exception\PDOException
     */
    public function cancelArbitration($data){
        $after_sale_id = $data['after_sale_id'];
        $where['after_sale_id'] = $after_sale_id;
        return $this->db->transaction(function () use ($data,$where,$after_sale_id){
            $rtn = false;
            $time = time();
            $up_data['status'] = 10;
            $up_data['edit_time'] = $time;
            //1、更新售后状态为关闭
            $res1 = $this->db->table($this->order_after_sale_apply)->where($where)->update($up_data);
            //2、记录log
            $log['log_type'] = isset($data["log_type"])?$data["log_type"]:0;
            $log['after_sale_id'] = $after_sale_id;
            $log['title'] = isset($data["title"])?$data["title"]:"";
            $log['user_type'] = isset($data["user_type"])?$data["user_type"]:0;
            $log['user_id'] = isset($data["customer_id"])?$data["customer_id"]:0;
            $log['user_name'] = isset($data["user_name"])?$data["user_name"]:"";
            $log['content'] = isset($data["content"])?$data["content"]:"";
            $log['imgs'] = isset($data["imgs"])?$data["imgs"]:"";
            $log['add_time'] = $time;
            $res2 = $this->addOrderAfterSaleApplyLog($log);
            if ($res1 && $res2){
                $rtn = true;
            }
            return $rtn;
        });
    }

    /*
     * 获取退货单信息
     * */
    public function getReturnProductExpressage($where){
       return $this->db->table($this->return_product_expressage)->where($where)->field("expressage_id,after_sale_id,expressage_company,expressage_num,expressage_fee,phone,explain,imgs,add_time")->find();
    }

    /*
     * 买家审核是否同意退款
     * */
    public function approved_refund(){

    }
}