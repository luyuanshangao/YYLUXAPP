<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class CustomerService extends Model
{
    protected $sales_order = 'sales_order';
    protected $sales_order_item = 'sales_order_item';
    protected $order_shipping_address = 'order_shipping_address';
    protected $sales_txn = 'sales_txn';
    protected $order_after_sale_apply = 'order_after_sale_apply';
    protected $order_refund = 'order_refund';
    // protected $affiliate_order = 'dx_affiliate_order';
    // protected $affiliate_apply = 'dx_affiliate_apply';
    // protected $affiliate_order_item = 'dx_affiliate_order_item';
    // protected $affiliate_level = 'cic_affiliate_level';

    public function __construct()
    {
         parent::__construct();
         $this->order = Db::connect('db_order');
        // $this->db = Db::connect('db_admin');
        // $this->dbcic = Db::connect('db_cic');
    }
    /**
    * 后台admin获取
    * 根据订单号查询获取产品的信息
    * [OrderProductExport description]
    * @auther wang  2019-05-21
    */
    public function OrderProductExport($where = [],$data){
              $item = [];
              $item_sku = [];
              $item_order_id = '';
              $address = [];
              $list_page = '';
              $sales_txn_notes = [];

              $list = $this->order->name($this->sales_order)
                           ->where($where)
                           ->field('order_id,order_number,customer_name,remark,captured_amount_usd')
                           ->select();
              if(!empty($list)){
                   // $list_page = $list->render();
                   // $list = $list->items();
                   foreach ($list as $k => $v) {
                       if(!empty($v['order_id'])){
                           $item_order_id .= $v['order_id'].',';
                       }
                   }
              }

              if($item_order_id != ''){
                  $item_order_id = rtrim($item_order_id,',');
                  //获取对应sku数据
                  $item = $this->order->name($this->sales_order_item)->where(['order_id'=>['in',$item_order_id]])->field('order_id,sku_num,product_nums,captured_price_usd')->select();
                  if(!empty($item)){
                          foreach ($item as $ke => $ve) {
                               if(empty($item_sku[$ve['order_id']]['sku'])){
                                    $item_sku[$ve['order_id']]['sku'] = $ve['sku_num'].'*'.$ve['product_nums'];
                                    $item_sku[$ve['order_id']]['captured_price_usd'] = $ve['captured_price_usd'].'*'.$ve['product_nums'];
                               }else{
                                    $item_sku[$ve['order_id']]['sku'] .= '|'.$ve['sku_num'].'*'.$ve['product_nums'];
                                    $item_sku[$ve['order_id']]['captured_price_usd'] .= '|'.$ve['captured_price_usd'].'*'.$ve['product_nums'];
                               }
                          }
                  }
                  //获取订单相应的地址
                  $shiping_address = $this->order->name($this->order_shipping_address)->where(['order_id'=>['in',$item_order_id]])->field('order_id,phone_number,street1,street2,city,state,mobile,country_code,postal_code,first_name,last_name')->select();
                  if(!empty($shiping_address)){
                          foreach ($shiping_address as $k => $v) {
                               if(!empty($v['order_id'])){
                                     $address[$v['order_id']] = $v;
                               }
                          }
                  }
                  //sales_txn表获取备注
                  $sales_txn = $this->order->name($this->sales_txn)->where(['order_id'=>['in',$item_order_id]])->field('notes,order_id')->select();
                  if(!empty($sales_txn)){
                        foreach ($sales_txn as $ky => $vy) {
                            if(!empty($vy['notes']) && !empty($vy['order_id'])){
                                  if(empty($sales_txn_notes[$vy['order_id']])){
                                        $sales_txn_notes[$vy['order_id']] = 'sales_txn表备注：'.$vy['notes'];
                                  }else{
                                        $sales_txn_notes[$vy['order_id']] .= ';'.$vy['notes'];
                                  }
                            }
                        }
                  }
                  //订单退款退货换货表order_after_sale_apply
                  $after_sale_apply = [];
                  $order_after_sale_apply = $this->order->name($this->order_after_sale_apply)->where(['order_id'=>['in',$item_order_id]])->field('remarks,order_id')->select();
                  if(!empty($order_after_sale_apply)){
                        foreach ($order_after_sale_apply as $kly => $vly) {
                            if(!empty($vly['order_id']) && !empty($vly['remarks'])){
                                 if(empty($after_sale_apply[$vly['order_id']])){
                                       $after_sale_apply[$vly['order_id']] = '订单退款退货换货表备注：'.$vly['remarks'];
                                 }else{
                                       $after_sale_apply[$vly['order_id']] .= ';'.$vly['remarks'];
                                 }
                            }
                        }
                  }
                  //remarks订单退款表售后
                  $after_sale_refund = [];
                  $order_refund = $this->order->name($this->order_refund)->where(['order_id'=>['in',$item_order_id]])->field('remarks,order_id')->select();
                  if(!empty($order_refund)){
                        foreach ($order_refund as $kf => $vf) {
                            if(!empty($vf['order_id']) && !empty($vf['remarks'])){
                                 if(empty($after_sale_refund[$vf['order_id']])){
                                       $after_sale_refund[$vf['order_id']] = '订单退款退货换货表备注：'.$vf['remarks'];
                                 }else{
                                       $after_sale_refund[$vf['order_id']] .= ';'.$vf['remarks'];
                                 }
                            }
                        }
                  }
              }
              return apiReturn(['code'=>200,
                'data'=>$list,
                'item_sku'=>$item_sku,
                'address'=>$address,
                'page'=>$list_page,
                'sales_txn_notes'=>$sales_txn_notes,
                'after_sale_apply'=>$after_sale_apply,
                'after_sale_refund'=>$after_sale_refund,
                'sql'=>$this->order->getlastsql()]);
    }

    /*
     * 获取客服订单信息
     * add kevin 20191029
     * */
    public function getOrderInformation($data){
        $where['txn_result'] = 'Success';
        if(!empty($data['currency_code'])){
            $where['ot.currency_code'] = $data['currency_code'];
        }
        if(!empty($data['payment_method'])){
            $where['ot.payment_method'] = $data['payment_method'];
        }
        if(!empty($data['order_number'])){
            $where['ot.order_number'] = ['IN',$data['order_number']];
        }
        if(!empty($data['payment_txn_id'])){
            $where['ot.payment_txn_id'] = ['IN',$data['payment_txn_id']];
        }
        if(!empty($data['third_party_txn_id'])){
            $where['ot.third_party_txn_id'] = ['IN',$data['third_party_txn_id']];
        }

        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['ot.create_on'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $where['ot.create_on'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $where['ot.create_on'] = strtotime($data['endTime']);
            }
        }
        $page_size = !empty($data['page_size'])?$data['page_size']:20;
        $page = !empty($data['page'])?$data['page']:1;
        $path = !empty($data['path'])?$data['path']:'';
        $order_list = $this->order->name($this->sales_txn)
            ->alias("ot")
            ->join($this->sales_order_item." oi","oi.order_id=ot.order_id","LEFT")
            ->join($this->order_shipping_address." osa","osa.order_id=ot.order_id","LEFT")
            ->where($where)
            ->order("txn_id desc")
            ->field('ot.order_id,ot.order_number,ot.payment_txn_id,txn_time,txn_type,ot.amount,ot.currency_code,ot.create_on,oi.product_id,oi.sku_id,oi.sku_num,oi.product_nums,oi.captured_price,oi.captured_price_usd,oi.product_name,osa.first_name,osa.last_name,osa.phone_number,osa.postal_code,osa.street1,osa.street2,osa.city,osa.state,osa.country,osa.mobile')
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($data)?$data:$where]);
        $Page = $order_list->render();
        $data = $order_list->toArray();
        $data['Page'] = $Page;
        return $data;
    }

}
