<?php
namespace app\orderfrontend\model;
use think\Model;
use think\Db;
use vendor\aes\aes;
use app\orderbackend\model\OrderModel;
/**
 * 订单查询模型
 * @author heng.zhang
 * @version 1.0
 * @date:2018-06-07
 */
class OrderQuery extends Model{
    private $db;
    private $order = "dx_sales_order";
    private $oms_order = "dx_oms_order";
    private $order_item = "dx_sales_order_item";
    private $order_message = "dx_sales_order_message";
    private $shipping_address = "dx_order_shipping_address";
    private $order_after_sale_apply = "dx_order_after_sale_apply";
    private $order_after_sale_apply_item = "dx_order_after_sale_apply_item";
    private $order_after_sale_apply_log = "dx_order_after_sale_apply_log";
    private $return_product_expressage = "dx_return_product_expressage";
    private $order_complaint = "dx_order_complaint";
    private $order_accuse = "dx_order_accuse";
    private $customer = "cic_customer";//cic库
    private $subscriber = "cic_subscriber";//cic库
    //包裹报表
    private $dx_order_package = "dx_order_package";
    private $dx_order_package_item = "dx_order_package_item";
    private $nocnoc_tracking = "dx_nocnoc_tracking";
    //交易明细表
    private $order_sales='dx_sales_txn';
    //订单变更历史
    private $sales_order_status_change='dx_sales_order_status_change';
    //noc请求表
    private $nocnoc_request='dx_nocnoc_request';
    private $order_other='dx_sales_order_other';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
        $this->cic = Db::connect('db_cic');
    }

    /**
    * 获取用户售后请单
    * */
    public function getOrderList($where,$page_size=10,$page=1,$path='',$order='',$PageQuery=array()){
        /*
    	$res = $this->db->table($this->order)
    	->alias("oa")
    	->join($this->shipping_address." sa","oa.order_id = sa.order_id")
    	->field("oa.order_number,oa.create_on,oa.order_status,oa.order_type,oa.store_id,oa.store_name
				        		,oa.customer_id,oa.customer_name,oa.pay_type_id,oa.payment_status,oa.fulfillment_status
				        		,oa.country,oa.goods_total,oa.currency_code,oa.total_amount,oa.captured_amount
				        		,oa.receivable_shipping_fee,oa.exchange_rate,oa.refunded_amount,oa.exchange_rate")
    	*/
      //return $path;
        $res = $this->db->table($this->order)->alias('o')
                ->join($this->order_item." oi","o.order_id=oi.order_id")
                ->field('o.order_id,order_number,order_master_number,o.create_on,o.payment_system,order_status,business_type,store_id,store_name
                        ,customer_id,customer_name,pay_type,payment_status,fulfillment_status
                        ,country,goods_total,currency_code,total_amount,captured_amount
                        ,receivable_shipping_fee,exchange_rate,refunded_amount,exchange_rate,order_type,pay_channel,order_from')
                ->where($where)
                ->where(['order_type'=>['neq',1]])
                ->group("o.order_id")
                ->order($order)
                ->paginate($page_size,false,[
                     'type' => 'Bootstrap',
                     'page' => $page,
                     'path' => $path,
                     'query'=> $PageQuery
                        ]);//return $this->db->getLastSql();return $res;
               // return $res->render();
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        $data['total'] = $res->total();
        return $data;
    }
    /**
     * 获取子订单号
     * [getOrderSubset description]
     * @return [type] [description]
     */
    public function getOrderSubset($orderNumber){
      $data=[];
      if(!empty($orderNumber)){
            $where['order_number'] = $orderNumber;
            $data = $this->db->table($this->order)
                      ->field('order_master_number')
                      ->where($where)
                      ->find();
            if($data['order_master_number']){
                $data['order_number_subset'] = $this->db->table($this->order)
                      ->field('order_number')
                      ->where(['order_master_number'=>$data['order_master_number']])
                      ->select();
            }else if($data['order_master_number'] == 0){
                $data['order_number_subset'] = $this->db->table($this->order)
                      ->field('order_number')
                      ->where(['order_master_number'=>$orderNumber])
                      ->select();
            }
      }
      return $data;
    }

    /**
     * 根据订单号码获取订单明细数据
     * @param string $orderNumber
     * @return 订单明细数据
     *
     *
     *算已发货由$vList["sku_id"]改成$vList["sku_num"]
     *@author: Wang edittime 2019-01-18
     *
     */
    public function getOrderDetail($orderNumber){
    	$data=[];
    	if(!empty($orderNumber)){
    		$where['order_number'] = $orderNumber;
    		//订单主表 这里是开发调试，等有数据后需要写个关联语句
    		$data = $this->db->table($this->order)
					    		//->alias("oa")
					    		//->join($this->order_item." soi","oa.order_id = soi.order_id",'left')
					    		//->join($this->shipping_address." sad","oa.order_id = sad.order_id",'left')
					    		->field('*') //TODO
					    		->where($where)
    							->find();

    		//return $data;
    		if(!empty($data)){
	    		//订单明细
	    		$query['order_id'] = $data['order_id'];

                $data['orderOther'] = $this->db->table($this->order_other)
					    		->field('*') //
					    		->where($query)
					    		->find();


	    		$itemList = $this->db->table($this->order_item)
					    		->field('*') //TODO
					    		->where($query)
					    		->select();
                    $data['rma_order'] = $this->db->table($this->order)
                        //->alias("oa")
                        //->join($this->order_item." soi","oa.order_id = soi.order_id",'left')
                        //->join($this->shipping_address." sad","oa.order_id = sad.order_id",'left')
                        ->field('*') //TODO
                        ->where(['parent_id'=>$data['order_id']])
                        ->find();
                $data['parent_order'] = array();
                if($data['parent_id']>0){
                    $data['parent_order'] = $this->db->table($this->order)
                        //->alias("oa")
                        //->join($this->order_item." soi","oa.order_id = soi.order_id",'left')
                        //->join($this->shipping_address." sad","oa.order_id = sad.order_id",'left')
                        ->field('*') //TODO
                        ->where(['order_id'=>$data['parent_id']])
                        ->find();
                }
          // //获取已发出的单
          // foreach ($itemList as $kList => $vList) {
          //         $order_package = $this->db->table($this->dx_order_package)->where(['order_number'=>$orderNumber])->field('package_id')->select();
          // }
	    		$data['itemList'] = $itemList;
          //订单收货地址
          //$query['order_id'] = $data['order_id'];
          $address = $this->db->table($this->shipping_address)
                          ->field('*') //TODO
                          ->where($query)
                          ->find();
          $data['address'] = $address;
          //return $data;
    			//售后订单
    			$query['order_id'] = $data['order_id'];
    			$after_sale_apply = $this->db->table($this->order_after_sale_apply)
							    			->field('*') //TODO
							    			->where($query)
                                            ->order("after_sale_id","DESC")
							    			->select();
                if(!empty($after_sale_apply)){
                    $after_sale_status = config('after_sale_status');
                    foreach ($after_sale_apply as $key=>$value){
                        foreach ($after_sale_status as $status){
                            if ($value['status'] == $status['code']){
                                $after_sale_apply[$key]['status_name'] = $status['name'];
                                $after_sale_apply[$key]['status_en_name'] = $status['en_name'];
                                break;
                            }
                        }
                    }
                }
    			$data['after_sale_apply'] = $after_sale_apply;

    			//交易明细表
    			$query['order_id'] = $data['order_id'];
    			$order_sales = $this->db->table($this->order_sales)
							    			->field('*') //TODO
							    			->where($query)
							    			->select();
    			$data['order_sales'] = $order_sales;

    			//订单变更历史
    			$query['order_id'] = $data['order_id'];
    			$order_status_change = $this->db->table($this->sales_order_status_change)
							    			->field('*') //TODO
							    			->where($query)
							    			->select();
    			$data['order_status_change'] = $order_status_change;

          //留言
          $sales_order_message_id['order_id'] = $data['order_id'];
          $sales_order_message = $this->db->table($this->order_message)
                                      ->where($sales_order_message_id)
                                      ->order("id desc")->select();
          if(!empty($sales_order_message)){
            foreach ($sales_order_message as $key=>$value){
                $sales_order_message[$key]['message'] = htmlspecialchars_decode(htmlspecialchars_decode($value['message']));
            }
          }
          $data['sales_order_message'] = $sales_order_message;
          //包裹号信息
          $tracking_number['order_number'] = $orderNumber;
          $tracking_number['is_delete'] = 0;
          // $tracking_number['order_id'] = $data['order_id'];
          $order_tracking_number = $this->db->table($this->dx_order_package)
                                      ->where($tracking_number)
                                      ->select();
          foreach ($order_tracking_number as &$val){
              $item_info = $this->db->table($this->dx_order_package_item)
                  ->where(['package_id'=>$val['package_id']])
                  ->select();
              $val['item_info'] = $item_info;

              //产品数量
              $tr_pro_num = 0;
              foreach ($item_info as $ival){
                  $tr_pro_num += $ival['sku_qty'];
              }
              $val['product_all_num'] = $tr_pro_num;
          }
          //算已发货sku
          foreach ($data['itemList'] as $kList => $vList) {
               $sum = 0;
               foreach ($order_tracking_number as $k_tracking_number => $v_tracking_number) {
                    $item_order_package = $this->db->table($this->dx_order_package_item)
                                     ->where(['package_id'=>$v_tracking_number['package_id'],'sku_id'=>$vList["sku_num"]])
                                     ->select();
                    if(!empty($item_order_package)){

                        foreach ($item_order_package as $k_item_sum => $v_item_sum) {
                             $sum += $v_item_sum['sku_qty'];
                        }
                    }
               }
               $data['itemList'][$kList]['code_sum'] = $sum;
          }

         //$this->db->table($this->order_tracking_number)->getLastSql()
          $data['order_tracking_number'] = $order_tracking_number;
    		}
        $data['order_quantity'] = $this->getOrderSubset($orderNumber);
        $after_where['order_number'] = $orderNumber;
        $data['is_after'] = $this->db->table($this->order_after_sale_apply)->where($after_where)->count();
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

    /*
  * 获取订单退款记录
  * */
    public function getOrderAfterSaleApplyLog($where){
        $res = $this->db->table($this->order_after_sale_apply_log)->where($where)->order("log_id desc")->select();
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
            ->field("oc.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,USD_captured_amount,refunded_amount,pay_time,shipments_time,shipments_complete_time")
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
     * 加锁解锁
     * [holdAndUnhold description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     * @author wang   2018-06-21
     */
    public function holdAndUnhold($data){
        if(!$data['status'] || !$data['order_id']){
           return false;
        }
        //同时更新两张表，使用事务
        // Db::startTrans();
        //TODO 需要插入状态变化历史数据

        $result = $this->db->table($this->order)->where(['order_id'=>$data['order_id']])->setField('lock_status',$data['status']);//return $result;
        // Db::commit();
        return $result;
    }
    /**
     * 投诉管理
     * [orderAccuse description]
     * @return [type] [description]
     * @author wang   2018-06-23
     */
    public function orderAccuse($data = array()){

          if(empty($data["page"])){
               $page = 1;
          }else{
               $page = $data["page"];
          }
          $page_size = $data['page_size'];


          if(empty($data['PlaceAnOrderStartTime']) || empty($data['PlaceAnOrderEndTime'])){

                  //单表查询条件
                  if(!empty($data['accuse_number'])){
                     $where['accuse_number'] = $data['accuse_number'];
                  }
                  if(!empty($data['order_number'])){
                     $where['order_number']  = $data['order_number'];
                  }
                  if(!empty($data['customer_name'])){
                     $where['customer_name'] = $data['customer_name'];
                  }
                  if(!empty($data['store_name'])){
                     $where['store_name']    = $data['store_name'];
                  }
                  if(!empty($data['accuse_status'])){
                     $where['accuse_status'] = $data['accuse_status'];
                  }
                  if(!empty($data['startTime']) && !empty($data['endTime'])){
                     $where['add_time'] = array(array('egt',$data['startTime']),array('elt',$data['endTime']));
                  }

                  if(!empty($where)){
                      $list = $this->db->table($this->order_accuse)->where($where)->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
                      // return $this->db->table($this->order_accuse)->getLastSql();
                  }else{
                      $list = $this->db->table($this->order_accuse)->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);

                  }

          }else{
                  //多表查询条件
                  if(!empty($data['accuse_number'])){
                     $where['OC.accuse_number'] = $data['accuse_number'];
                  }
                  if(!empty($data['order_number'])){
                     $where['OC.order_number']  = $data['order_number'];
                  }
                  if(!empty($data['customer_name'])){
                     $where['OC.customer_name'] = $data['customer_name'];
                  }
                  if(!empty($data['store_name'])){
                     $where['OC.store_name']    = $data['store_name'];
                  }
                  if(!empty($data['accuse_status'])){
                     $where['OC.accuse_status'] = $data['accuse_status'];
                  }
                  if(!empty($data['startTime']) && !empty($data['endTime'])){
                     $where['OC.add_time'] = array(array('egt',$data['startTime']),array('elt',$data['endTime']));
                  }
                  $where['OR.create_on']   = array(array('egt',$data['PlaceAnOrderStartTime']),array('elt',$data['PlaceAnOrderEndTime']));

                  $list = $this->db->table($this->order_accuse)->alias("OC")->join($this->order." OR","OC.order_number = OR.order_number")->where($where)->field('OC.*')->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
              //return $this->db->table($this->order_accuse)->getLastSql();
          }
          $result['data'] = $list->items();
          $result['page'] = $list->render();
          return $result;
    }
    /**
     * 退换货管理
     * [orderRefund description]
     * @param  array  $data [description]
     * @return [type]       [description]
     * @author wang   2018-06-25
     */
    public function orderRefund($data = array()){
          if(empty($data["page"])){
               $page = 1;
          }else{
               $page = $data["page"];
          }
          $page_size = $data['page_size'];//return $data;

          if(!empty($data['PlaceAnOrderStartTime']) && !empty($data['PlaceAnOrderEndTime'])){

                //多表查询条件
                if(!empty($data['after_sale_number'])){
                   $where['OA.after_sale_number'] = $data['after_sale_number'];
                }
                if(!empty($data['order_number'])){
                   $where['OA.order_number']  = $data['order_number'];
                }
                if(!empty($data['customer_name'])){
                   $where['OA.customer_name'] = $data['customer_name'];
                }
                if(!empty($data['store_name'])){
                   $where['OA.store_name']    = $data['store_name'];
                }
                if(!empty($data['after_sale_status'])){
                   $where['OA.status'] = $data['after_sale_status'];
                }
                if(!empty($data['refunded_type'])){
                   $where['OA.refunded_type'] = $data['refunded_type'];
                }
                if(!empty($data['startTime']) && !empty($data['endTime'])){
                   $where['OA.add_time'] = array(array('egt',$data['startTime']),array('elt',$data['endTime']));
                }
                $where['OR.create_on']   = array(array('egt',$data['PlaceAnOrderStartTime']),array('elt',$data['PlaceAnOrderEndTime']));

                $list = $this->db->table($this->order_after_sale_apply)->alias("OA")->join($this->order." OR","OA.order_number = OR.order_number")->where($where)->field('OA.*')->order('OA.add_time','desc')->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
                // return $this->db->table($this->order_after_sale_apply)->getLastSql();

          }else{
              //单表查询条件
              if(!empty($data['after_sale_number'])){
                 $where['after_sale_number'] = $data['after_sale_number'];
              }
              if(!empty($data['order_number'])){
                 $where['order_number'] = $data['order_number'];
              }
              if(!empty($data['customer_name'])){
                 $where['customer_name'] = $data['customer_name'];
              }
              if(!empty($data['store_name'])){
                 $where['store_name'] = $data['store_name'];
              }
              if(!empty($data['after_sale_status'])){
                 $where['status'] = $data['after_sale_status'];
              }
              if(!empty($data['refunded_type'])){
                 $where['refunded_type'] = $data['refunded_type'];
              }
              if(!empty($data['startTime']) && !empty($data['endTime'])){
                     $where['add_time'] = array(array('egt',$data['startTime']),array('elt',$data['endTime']));
              }

              if(!empty($where)){
                  $list = $this->db->table($this->order_after_sale_apply)->where($where)->order('add_time','desc')->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
                   // return $this->db->table($this->order_accuse)->getLastSql();
              }else{
                  $list = $this->db->table($this->order_after_sale_apply)->order('add_time','desc')->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
              }

              // $list = $this->db->table($this->order_after_sale_apply)->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
          }
          // return $this->db->table($this->order_after_sale_apply)->getLastSql();
          $result['data'] = $list->items();
          $result['page'] = $list->render();
          return $result;
    }
    /**
     * 售后详情
     * [afterSaleDetails description]
     * @return [type] [description]
     * @author wang   2018-06-27
     */
    public function afterSaleDetails($data){
       if(!empty($data['after_sale_number'])){
            $where['OA.after_sale_number'] = $data['after_sale_number'];
       }
       // $list = $this->db->table($this->order)->where($where)->find();
       // $list['after_sale_apply'] = $this->db->table($this->order_after_sale_apply)->where($where)->select();
       $list = $this->db->table($this->order_after_sale_apply)->alias("OA")->join($this->order." OR","OA.order_number = OR.order_number")->where($where)->field('OA.*,OR.currency_code,OR.order_type,OR.order_id')->find();
       $itemList = $this->db->table($this->order_item)->field('product_id,product_name,product_price,product_attr_desc,product_nums,discount_total,product_nums,product_price,discount_total')->where(['order_id'=>$list['order_id']])->select();
       $list['itemList'] = $itemList;
       // return $this->db->table($this->order_after_sale_apply)->getLastSql();//currency_code
       return $list;
    }
    /**
     * 仲裁
     * [arbitration description]
     * @return [type] [description]
     * @author wang   2018-08-18
     */
    public function arbitration($data){
        $page = 1;
        $page_size = 20;
// $list = $this->db->table($this->order_after_sale_apply)->where(['status'=>6])->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);
// ->field('OA.*,OR.imgs AS orimgs,OR.title,OR.user_type,OR.content,OR.user_id,OR.user_name')
        $list = $this->db->table($this->order_after_sale_apply)->alias("OA")->join($this->order_after_sale_apply_log." OR","OA.after_sale_id = OR.after_sale_id")->where(['OA.status'=>6])->field('OA.*,OR.imgs AS orimgs,OR.title,OR.user_type,OR.content,OR.user_id,OR.user_name')->paginate($page_size,false,['type' => 'Bootstrap','page' => $page]);

return $list;
        $result['data'] = $list->items();
        $result['page'] = $list->render();

        //$list = $this->db->table($this->order_after_sale_apply)->alias("OA")->join($this->order." OR","OA.order_number = OR.order_number")->where($where)->field('OA.*,OR.currency_code,OR.order_type,OR.order_id')->find();
        return $result;

    }
    /**
     * 仲裁回复
     * [applyLog description]
     * @return [type] [description]
     * @author wang   2018-08-21
     */
    public function applyLog($data){
       unset($data['access_token']);
       $data['add_time'] = time();
       $result = $this->db->table($this->order_after_sale_apply_log)->insert($data);
       if($result){
         return apiReturn(['code'=>200,'data'=>'数据提交成功']);
       }else{
         return apiReturn(['code'=>100,'data'=>'数据提交失败']);
       }

    }
    /**
     * 产品举报
     * [CustomsInsurance description]
     * @author wang   2018-09-08
     */
    public function CustomsInsurance($data){
        if(!$data['order_number']){
            return apiReturn(['code'=>100,'data'=>'空参数']);
        }
        $result = $this->db->table($this->order)->where(['order_number'=>$data['order_number']])->find();
        $OrderModel = new OrderModel();
        $where['order_number'] = $data['order_number'];
        $result['TxnID'] = $OrderModel ->getTransactionID($where);
        if($result){
           return apiReturn(['code'=>200,'data'=>$result]);
        }else{
           return apiReturn(['code'=>100,'data'=>'查不到该单号']);
        }
    }
     /**
     * 根据条件查询
     * [order description]
     * @return [type] [description]
     * @author wang   addtime 2018-09-27
     */
    public function order_query(){
      ini_set('max_execution_time', '0');
      ignore_user_abort();
      //所有条件为空时

      if($data["OrderDdata"]){
         if(!isset($data['page_size']) && !$data['page_size']){
              $page_size = 100;
         }else{
              $page_size = $data['page_size'];
         }
         if(!isset($data['page']) && !$data['page']){
             $page = 1;
         }else{
             $page = $data['page'];
         }
         vendor('aes.aes');
         $aes = new aes();
         if($data){

         }

         while (true){

                 if(isset($data['status']) && $data['status'] == 1){
                     //新库表
                     $list = $this->db->table($this->order)->where($data["OrderDdata"])->field('order_number,customer_id,order_master_number,create_on,grand_total,country_code,currency_code,exchange_rate,pay_channel,pay_type')->order('create_on desc')->page($page,$page_size)->select();
                 }else if(isset($data['status']) && $data['status'] == 2){
                     //老库表
                     $list = $this->db->table($this->oms_order)->where($data["OrderDdata"])->field('order_number,customer_id,create_on,captured_amount_usd as grand_total,country_code,currency_code,exchange_rate,pay_channel,pay_type')->order('create_on desc')->page($page,$page_size)->select();
                 }else{
                     return array('code'=>100,'data'=>'status状态必填');
                 }
                 $result = $list;
                 $customer_information = array();
                 $customer_val = array();
                 if($result){
                     if($data["userData"]){
                         $group_list = $this->array_group_by($result, 'customer_id');//对查询数据根据用户ID进行分组
                        // return array('code'=>200,'url'=>$this->db->table($this->order)->getLastSql(),'data'=>$group_list);
                         foreach ((array)$group_list as $k => $v) {
                           $data["userData"]['ID'] = $k;
                           $customer_val = redis()->get(DX_CUSTOMER_ID);
                           if(!isset($customer_val[$k])){
                               //判断是否为订阅用户
                               $subscriber = $this->cic->table($this->subscriber)->where(['CustomerId'=>$k])->field('CustomerId')->find();
                               if($subscriber){
                                   $customer = $this->cic->table($this->customer)->where($data["userData"])->field('EmailUserName,EmailDomainName')->find();
                                   $EmailUserName = $aes->decrypt($customer['EmailUserName'],'Customer','EmailUserName');//解密邮件前缀
                                   if($customer){
                                          $customer_information[$k]['customer_id'] = $k;
                                          $customer_information[$k]['mail'] = $EmailUserName.'@'.$customer['EmailDomainName'];
                                          $customer_val[$k] = $k;
                                          redis()->set(DX_CUSTOMER_ID,$customer_val);
                                   }
                               }


                           }
                         }
                         // $result = $this->array_merge_by($group_list);
                         return array('code'=>200,'url'=>$this->db->table($this->order)->getLastSql(),'data'=>$customer_information);
                     }
                 }else{
                     if($data['status'] == 2){
                          redis()->del(DX_CUSTOMER_ID);
                     }
                     return array('code'=>100,'url'=>$this->db->table($this->order)->getLastSql(),'data'=>$result);
                 }
         }




      }
    }
  /**
   * 把三维数组转换成二维数组
   * [array_merge description]
   * @return [type] [description]
   */
  public function array_merge_by($group_list=array()){
      $data = array();
      foreach ($group_list as $key => $value) {
          $data = array_merge_recursive($data,$value);
      }
      return $data;
  }
 /**
 * [array_group_by ph]
 * @param  [type] $arr [二维数组]
 * @param  [type] $key [键名]
 * @return [type]      [新的二维数组]
 */
  public function array_group_by($arr, $key){
      $grouped = array();
      foreach ($arr as $value) {
          $grouped[$value[$key]][] = $value;
      }
      if (func_num_args() > 2) {
          $args = func_get_args();
          foreach ($grouped as $key => $value) {
              $parms = array_merge($value, array_slice($args, 2, func_num_args()));
              $grouped[$key] = call_user_func_array('array_group_by', $parms);
          }
      }
      return $grouped;
  }

  /**
     * nocnoc面单地址查询
     * @param string $orderNumber
     * @return array
     * @author wangyj addtime 20190313
     */
    public function getNocOrderLabel($orderNumber='', $field='*'){
        return $this->db->table($this->nocnoc_request)->field($field)->where(['order_number'=>$orderNumber])->order('id desc')->limit(1)->find();
    }

    public function TrackingNumber($data = array()){
        return $this->db->table($this->dx_order_package)->field('order_number')->where($data)->find();
    }
    /**
     * 根据NO跟踪号获取订单号
     * [TrackingNumber description]
     * @param array $data [description]
     */
    public function TrackingNumberNOC($data = array()){
        return $this->db->table($this->nocnoc_tracking)->field('order_number')->where($data)->find();
    }
    /**
     * 获取留言历史记录(用于admin订单详情)
     * [HistoryRecordList description]
     * @auther wang  2019-04-19
     */
    public function HistoryRecordList($data){
        $where = [];
        $page_size = 20;
        $path = '';
        if(!empty($data['user_id'])){
            $where['A.user_id'] = $data['user_id'];
        }
        if(!empty($data['order_id'])){
            $where['A.order_id'] = array('neq',$data['order_id']);
        }
        if(!empty($data['page'])){
            $page = $data['page'];
        }else{
            $page = 1;
        }
        if(!empty($data['page_size'])){
            $page_size = $data['page_size'];
        }
        if(!empty($data['path'])){
            $path = $data['path'];
        }
        $list = $this->db->table($this->order_message)
        ->alias('A')
        ->join($this->order.' O','A.order_id = O.order_id')
        ->where($where)
        ->field('A.id,A.distribution_admin,A.create_on,A.is_reply,A.message,A.operator_admin,O.order_id,O.order_number,O.store_name,O.captured_amount_usd')
        ->group("O.order_id")
        ->paginate($page_size,false,[
                               'type' => 'Bootstrap',
                               'page' => $page,
                               'path' => $path,
                               // 'query'=> $PageQuery
                                    ]);//return $this->db->getLastSql();return $res;
               // return $res->render();
        $Page = $list->render();
        $data = $list->toArray();
        $data['Page'] = $Page;
        // $data['total'] = $res->total();
        return $data;
    }

    /**
     * 动态获取订单数据
     * */
    public function mapOrderList($page_size=10,$page=1){
        $res = $this->db->table($this->order)->alias('o')
            ->join($this->order_item." oi","o.order_id=oi.order_id")
            ->field('o.order_id,order_number,o.create_on,product_nums,customer_id,customer_name,country,country_code,goods_total,currency_code,total_amount,captured_amount,captured_amount_usd,add_time')
            ->where(['order_type'=>['neq',1],'order_status' => ['in',[120,200,400,407,500,600,900]]])
            ->group("o.order_id")
            ->order('create_on desc')
            ->paginate($page_size,false,[
                'type' => 'Bootstrap',
                'page' => $page,
            ])->toArray();
        return $res;
    }

    /**
     * 从时间范围获取下单后的产品
     * */
    public function topSellerOrderData($startTime = null,$endTime = null,$limit = 0){
        if(empty($startTime)){
            $startTime = strtotime("-3 months");
        }
        if(empty($endTime)){
            $endTime = strtotime(date('Ymd'));
        }

        $where['order_type'] = ['neq',1];
        $where['order_status'] = ['in',[120,200,400,407,500,600,700,900,920,1000,1100,1200,1300]];
        $where['o.create_on']   = array(array('egt',$startTime),array('elt',$endTime));

        $qurey = $this->db->table($this->order)->alias('o')
            ->join($this->order_item." oi","o.order_id=oi.order_id")
            ->field('o.create_on,product_id,item_id,count(item_id) as p_count')
            ->where($where)
            ->group("product_id")
            ->order('p_count desc');

        if(!empty($limit)){
            $qurey->limit($limit);
        }
        return $qurey->select();
    }
}