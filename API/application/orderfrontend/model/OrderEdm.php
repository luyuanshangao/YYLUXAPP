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
class OrderEdm extends Model{
    private $db;
    private $order = "dx_sales_order";
    private $oms_order = "dx_oms_order";
    private $order_item = "dx_sales_order_item";
    private $order_message = "dx_sales_order_message";
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
    //交易明细表
    private $order_sales='dx_sales_txn';
    //订单变更历史
    private $sales_order_status_change='dx_sales_order_status_change';
    //后台admin  edm表
    private $screening_management ='dx_screening_management';
    private $edm_query_result ='dx_edm_query_result';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
        $this->cic = Db::connect('db_cic');
        $this->admin = Db::connect('db_admin');
        $this->mongodb = Db::connect('db_mongodb');
    }


     /**
     * 根据条件查询
     * [order description]
     * @return [type] [description]
     * @author wang   addtime 2018-12-20
     */
    public function order_query(){
      ini_set('max_execution_time', '0');
      ignore_user_abort();
      vendor('aes.aes');
      $aes = new aes();
      $list = $this->admin->table($this->screening_management)->where(['status'=>3])->order('add_time ASC')->find();
      if(empty($list)){
          $list = $this->admin->table($this->screening_management)->where(['status'=>1])->order('add_time ASC')->find();
          if(empty($list)){
               return;
          }else{
               $this->admin->table($this->screening_management)->where(['status'=>1,'id'=>$list['id']])->update(['status'=>3]);
          }
      }

      // if(!$list){  return; }
      $QueryCombination = $this->QueryCombination($list);
      // return $QueryCombination;
      if(empty($list['page'])){
            $page=1;
      }else{
            $page = $list['page'];
      }
      //当全部为空时查询所有
      if(empty($QueryCombination['OrderDdata']) && empty($QueryCombination['userData']) && empty($QueryCombination['OrderDdataItem']) && !empty($list['TopicName']) && empty($QueryCombination['quantity'])){
          return $this->AllSubscribers($list,$page,$aes);
      }else if(!empty($QueryCombination['OrderDdata']) || !empty($QueryCombination['quantity']) || !empty($QueryCombination['OrderDdataItem'])){
          return $this->SalesDdataItem($list,$QueryCombination,$page,$aes);
      }

      return 2;

    }
    //从订单主表开始
    public function SalesDdataItem($TopicNamek,$QueryCombination,$page,$aes){
         // $QueryCombination['OrderDdata']['payment_status'] = array(array('egt',200),array('neq',1400));
         $status = true;
         $ItemStatus = false;
         for ($i=1; $i <=100; $i++) {
              $order_customer_id = '';
              //是否有购买次数
              if(empty($QueryCombination['quantity'])){
                 if(!empty($QueryCombination['OrderDdataItem']) && empty($QueryCombination['OrderDdata'])){
                      $ItemStatus = true;
                      $Order_item_id = $this->OrderDdataItem($QueryCombination,$page);
                      if(empty($Order_item_id)){
                         $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['status'=>4]);
                         return 2;
                      }
                      $QueryCombination['OrderDdata']['order_id'] = ['in',$Order_item_id];
                      $list = $this->db->table($this->order)->where($QueryCombination['OrderDdata'])->field('order_id,order_number,customer_id,create_on')->group('customer_id')->order('create_on ASC')->select();
                 }else{
                      $list = $this->db->table($this->order)->where($QueryCombination['OrderDdata'])->field('order_id,order_number,customer_id,create_on')->group('customer_id')->page($page,100)->order('create_on ASC')->select();
                 }
              }else{
                 $order_count = 'count(customer_id)'.$QueryCombination['quantity'][0].$QueryCombination['quantity'][1];
                 $list = $this->db->table($this->order)->where($QueryCombination['OrderDdata'])->field('order_id,order_number,customer_id,create_on')->group('customer_id')->having($order_count)->page($page,100)->order('create_on ASC')->select();
                 // var_dump( $this->db->table($this->order)->getLastSql());
              }
              if(empty($list)){
                $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['status'=>4]);
                return 2;
              }
              //如果有产品分类ID
              if(!empty($QueryCombination['OrderDdataItem']) && $ItemStatus === false){
                  $order_category_id = $this->order_category_id($list,$QueryCombination['OrderDdataItem']);
                  if(!empty($order_category_id)){
                      $list = $order_category_id;
                  }else{
                      $page++;
                      $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['page'=>$page]);
                      continue;
                  }
              }
              foreach ($list as $k => $v) {
                  if(!empty($v['customer_id'])){
                        $order_customer_id .= $v['customer_id'].',';
                  }
              }
              $order_customer_id = rtrim($order_customer_id,',');
              $subscriber = [];
              $subscriber['CustomerId'] =  array('in',$order_customer_id);
              $subscriber['Active'] =  1;
              //获取有订阅的用户
              $subscriber_list = $this->cic->table($this->subscriber)->where($subscriber)->field('CustomerId')->select();
              if(empty($subscriber_list)){
                $page++;
                $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['page'=>$page]);
                continue;
              }
              $subscriber_customer_id = '';
              foreach ($subscriber_list as $k_subscriber => $v_subscriber) {
                 if(!empty($v_subscriber)){
                    $subscriber_customer_id .= $v_subscriber['CustomerId'].',';
                 }
              }
              $subscriber_customer_id = rtrim($subscriber_customer_id,',');
              $customer_id = [];
              if(!empty($QueryCombination['userData'])){
                   $customer_id = $QueryCombination['userData'];
              }

              $customer_id['ID'] = array('in',$subscriber_customer_id);
              $customer_list = $this->cic->table($this->customer)->where($customer_id)->field('ID,EmailUserName,EmailDomainName')->select();
              // var_dump($this->cic->table($this->customer)->getLastSql());
              if(!empty($customer_list)){
                 $result = $this->Storage($customer_list,$TopicNamek,$aes);
                 if($result === false){
                    $status = false;
                 }else{
                    $page++;
                    $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['page'=>$page]);
                 }
              }else{
                $page++;
                $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['page'=>$page]);
              }
         }
         return 1;
    }
    //所有订阅用户cic_subscriber
    public function AllSubscribers($TopicNamek = array(),$page=1,$aes){
      $i=1;
      while (true) {
           $data = array();
           $CustomerId = '';
           $list = $this->cic->table($this->subscriber)->where(['Active'=>1])->field('CustomerId')->page($page,100)->order('AddTime ASC')->select();
           if(!empty($list)){
                foreach ($list as $k=> $v) {
                  if(!empty($v['CustomerId'])){
                      $CustomerId .= $v['CustomerId'].',';
                  }
                }
                $CustomerId = rtrim($CustomerId, ',');
                if(!empty($CustomerId)){
                    $data['ID'] = array('in',$CustomerId);
                    $customer_list = $this->cic->table($this->customer)->where($data)->field('EmailUserName,EmailDomainName')->select();
                    if(!empty($customer_list)){
                        foreach ($customer_list as $ke => $va) {
                            $customer_mail = '';
                            if(!empty($va['EmailUserName']) && !empty($va['EmailDomainName'])){
                                $where = array();
                                $EmailUserName = $aes->decrypt($va['EmailUserName'],'Customer','EmailUserName');
                                $customer_mail = $EmailUserName.'@'.$va['EmailDomainName'];
                                $where['mailbox'] = $customer_mail;
                                $where['id'] = (int)$TopicNamek['id'];
                                $where['TopicName'] = $TopicNamek['TopicName'];
                                $where['add_time'] = time();
                                // $does_it_exist = $this->mongodb->table($this->edm_query_result)->where(['id'=>(int)$where['id'],'TopicName'=>$where['TopicName']])->find();
                                // if(empty($does_it_exist)){}
                                $this->mongodb->table($this->edm_query_result)->insert($where);
                            }
                        }
                    }
                }
           }else{
               $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['status'=>4]);
               return 1;
           }
           if($i == 150){
                 $this->admin->table($this->screening_management)->where(['status'=>3,'id'=>$TopicNamek['id']])->update(['page'=>$page]);
                 return 1;
           }
           $i++;
           $page++;
      }
    }
    //把到导出数据先入库
    public function Storage($customer_list = array(),$TopicNamek = array(),$aes){
        // file_put_contents ('../runtime/log/201901/1212.log',json_encode($customer_list).',', FILE_APPEND|LOCK_EX);
        $status = true;
        foreach ($customer_list as $ke => $va) {
            $customer_mail = '';
            if(!empty($va['EmailUserName']) && !empty($va['EmailDomainName'])){
                $where = array();
                try {
                      $EmailUserName = $aes->decrypt($va['EmailUserName'],'Customer','EmailUserName');
                      $customer_mail = $EmailUserName.'@'.$va['EmailDomainName'];
                      $where['customer_id'] = (int)$va['ID'];
                      $where['mailbox'] = $customer_mail;
                      $where['id'] = (int)$TopicNamek['id'];
                      $where['TopicName'] = $TopicNamek['TopicName'];
                      $where['add_time'] = time();
                      $does_it_exist = $this->mongodb->table($this->edm_query_result)->where(['id'=>(int)$where['id'],'TopicName'=>$where['TopicName'],'customer_id'=>(int)$va['ID']])->find();
                      if(!empty($does_it_exist)){continue;}
                      // var_dump($where);exit;
                      $result = $this->mongodb->table($this->edm_query_result)->insert($where);
                      if(empty($result)){
                        $status = false;
                      }
                } catch (Exception $e){
                     // file_put_contents ('../runtime/log/201901/1212.log',json_encode($where).',', FILE_APPEND|LOCK_EX);
                     echo $e->getMessage();
                }
            }
        }
        if($status === false){    return false;   }
        return true;
    }
    /**
     * 对产品分类进行查询
     * [order_category_id description]
     * @return [type] [description]
     */
    public function order_category_id($list,$OrderDdataItem){
          $order_id = '';
          $data = [];
          $data_array = [];
          foreach ($list as $k => $v) {
               if(!empty($v['order_id'])){
                  $order_id .= $v['order_id'].',';
               }
          }
          $order_id = rtrim($order_id,',');
          if(!empty($QueryCombination['OrderDdataItem'])){
              $data = $QueryCombination['OrderDdataItem'];
          }
          $data['order_id'] = ['in',$order_id];
          $list_order_item = $this->db->table($this->order_item)->where($data)->group('order_id')->field('order_id')->select();

          if(empty($list_order_item)){return ;}
          foreach ($list as $ke => $ve) {
              if(!empty($list_order_item)){
                      foreach ($list_order_item as $ky => $vy) {
                         if($ve['order_id'] == $vy['order_id']){
                            $data_array[] = $ve;
                            unset($list_order_item[$ky]);
                            break;
                         }
                      }
              }else{
                break;
              }
          }
          return $data_array;
    }
    /**
     * 从sku表开始查
     * [OrderDdataItem description]
     */
    public function OrderDdataItem($QueryCombination = array(),$page){
          $data = [];
          $orders_id = '';
          $data = $QueryCombination['OrderDdataItem'];
          $list_order_item = $this->db->table($this->order_item)->where($data)->group('order_id')->page($page,100)->field('order_id')->select();
          if(!empty($list_order_item)){
              foreach ($list_order_item as $k => $v) {
                  $orders_id .= $v['order_id'].',';
              }
              $orders_id = rtrim($orders_id,',');
              return $orders_id;
          }
          return;
    }
    /**
     * 根据已有数据组合条件
     * @param [type] $data [description]
     */
    public function QueryCombination($data){
         $result = array();
         $OrderDdata = array();
         $OrderDdataItem = array();
         $userData = array();

         if(!empty($data['order_create_start_time']) && !empty($data['order_create_end_time'])){
             $OrderDdata['create_on'] = array('between',''.$data["order_create_start_time"].','.$data["order_create_end_time"].'');
         }else if(!empty($data['order_create_start_time'])){
             $OrderDdata['create_on'] = array('egt',$data['order_create_start_time']);
         }else if(!empty($data['order_create_end_time'])){
             $OrderDdata['create_on'] = array('elt',$data['order_create_end_time']);
         }
         if(!empty($data['order_pay_mode'])){
             // $order_pay_mode = explode(",",$data['order_pay_mode']);
             $OrderDdata['pay_type'] = ['in',$data['order_pay_mode']];
             // $order_pay_mode = json_decode($data['order_pay_mode'],true);
             // foreach ((array)$order_pay_mode as $k => $v) {
             //      $OrderDdata['pay_type'][] = ['=',$v];
             // }
             // $OrderDdata['pay_type'][] = "or";
         }
         if(!empty($data['class_id'])){
             $OrderDdataItem['first_category_id'] = ['in',$data['class_id']];
         }
         if(!empty($data['national'])){
             $OrderDdata['country_code'] = ['in',$data['national']];
         }
         if(!empty($data['orders_of_limit'])){
             if($data['orders_of_limit'] == 1 && !empty($data['orders'])){
                // $OrderDdata['country_code'] = ['lt',$data['orders']];
                $result['quantity'][]   =  '<';
                $result['quantity'][]   =  $data['orders'];

             }else if($data['orders_of_limit'] == 2 && !empty($data['orders'])){
                $result['quantity'][]   =  '>';
                $result['quantity'][]   =  $data['orders'];
                // $result['quantity']   =  ['egt',$data['orders']];
             }

         }
         //订单实收金额
         if(!empty($data['order_amount_of_start']) && !empty($data['order_amount_of_end'])){
             $OrderDdata['captured_amount'] = array('between',''.$data['order_amount_of_start'].','.$data['order_amount_of_end'].'');;
         }else if(!empty($data['order_amount_of_start'])){
             $OrderDdata['captured_amount'] = array('egt',$data['order_amount_of_start']);
         }else if(!empty($data['order_amount_of_end'])){
             $OrderDdata['captured_amount'] = array('elt',$data['order_amount_of_end']);
         }
         //注册时间
         if(!empty($data['reg_start_time']) && !empty($data['reg_end_time'])){
             $userData['CreateOn'] = array('between',''.$data['reg_start_time'].','.$data['reg_end_time'].'');
         }else if(!empty($data['reg_start_time'])){
             $userData['CreateOn'] = array('egt',$data['reg_start_time']);
         }else if(!empty($data['reg_end_time'])){
             $userData['CreateOn'] = array('elt',$data['reg_end_time']);
         }
         //登录时间
         if(!empty($data['login_start_time']) && !empty($data['login_end_time'])){
             $userData['LastLoginDate'] = array('between',''.$data['login_start_time'].','.$data['login_end_time'].'');
         }else if(!empty($data['login_start_time'])){
             $userData['LastLoginDate'] = array('egt',$data['login_start_time']);
         }else if(!empty($data['login_end_time'])){
             $userData['LastLoginDate'] = array('elt',$data['login_end_time']);
         }
         $result['OrderDdata'] = $OrderDdata;
         $result['userData']   = $userData;
         $result['OrderDdataItem']   = $OrderDdataItem;
         return  $result;

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
  //导出
  public function export(){
       $list = array();
       $screening_management = array();
       $screening_management = $this->admin->table($this->screening_management)->where(['status'=>4])->order('add_time ASC')->find();
       if(empty($screening_management)){   return;  }
       $list = $this->mongodb->table($this->edm_query_result)->where(['id'=>(int)$screening_management['id']])->limit($screening_management['export_location'],300)->select();
       if(empty($list)){
           $this->admin->table($this->screening_management)->where(['status'=>4,'id'=>$screening_management['id']])->update(['status'=>2]);
           return;
       }else{
           $screening_management['export_location'] += count($list);
           $this->admin->table($this->screening_management)->where(['status'=>4,'id'=>$screening_management['id']])->update(['export_location'=>$screening_management['export_location']]);
           return $list;
       }
  }
}