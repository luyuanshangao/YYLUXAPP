<?php
namespace app\windcontrol\model;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;


/**
 * 供应商模型
 * @author wang  2019/03/29
 * @version
 */
class WindControlModel extends Model{
    const customer = 'cic_customer';
    const bank_info = 'dx_bank_info';
    const wind_control_special_list = 'dx_wind_control_special_list';
    const wind_control_special_address = 'dx_wind_control_special_address';
    const wind_control_special_beforehand = 'dx_wind_control_special_beforehand';
    const wind_control_special_afterwards = 'dx_wind_control_special_afterwards';
    const wind_control_special_city = 'dx_wind_control_special_city';
    const wind_control_special_sku = 'dx_wind_control_special_sku';
    const wind_control_special_Card = 'dx_wind_control_special_Card';
    const wind_control_special_third_party_results = 'dx_wind_control_special_third_party_results';
    const sales_order_status_change = 'dx_sales_order_status_change';//订单状态变更表
    const order_shipping_address = 'dx_order_shipping_address';//订单状态变更表
    const sales_order = 'dx_sales_order';//订单状态变更表
    const sales_order_item = 'dx_sales_order_item';//订单状态变更表
    const transaction = 'transaction';//订单状态变更表
    const wind_control_special_child_order = 'dx_wind_control_special_child_order';//订单状态变更表
    const wind_control_special_log_save_result = 'dx_wind_control_special_log_save_result';


    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
        $this->cic = Db::connect('db_cic');
        $this->crc = Db::connect('db_crc');
        //$this->payment = Db::connect('db_payment');
    }
    /**
     * 用户详情
     * [UserDetails description]
     * @param [type] $UserWhere [description]
     * @author wang  2019/03/29
     */
    public function UserDetails($where){
         return $this->cic->table(self::customer)->where($where)->field('*')->find();
    }
    /**
     * 获取订单数据
     * [OrserDetails description]
     * @author wang  2019/03/29
     */
    public function OrserDetails($where){
         $list = $this->db->table(self::sales_order)
          ->alias('A')
          ->where($where)
          ->join(self::order_shipping_address.' B','A.order_id = B.order_id')
          ->field('A.order_id,A.customer_id,A.captured_amount_usd,A.create_on,A.customer_name,A.refunded_amount,A.currency_code,A.exchange_rate,A.goods_total,A.order_status,B.first_name,B.last_name,B.country AS ShippingCountry,B.city AS ShippingCity,B.email,B.mobile')
          // ->order('B.add_time DESC')$this->db->getLastSql();
          ->find();
          return $list;
         // return $this->db->table(self::sales_order)->where($where)->field('order_id,customer_id,captured_amount_usd,create_on,customer_name')->find();
    }
    /**
     * 获取订单变化状态
     * [OrderShippingAddress description]
     * @author wang  2019/05/24
     */
    public function SalesOrderStatusChange($where){
         $list = $this->db->table(self::sales_order_status_change)->where($where)->order('create_on DESC')->select();
         return $list;
    }

    /**
     * 交易往来信息
     * [TransactionInformation description]
     * @author wang  2019/05/24
     */
    public function TransactionInformation($where){
         $list = $this->payment->table(self::transaction)->where($where)->order('transaction_request_time DESC')->field('transaction_id,note,invoice_id,transaction_account_id,balance,transaction_action,risk_control_status_summary,response_status,transaction_request_time_prc,transaction_type')->select();
         return $list;
    }
    /**
     *  [HistoricalOrder description]
     *  @param [type] $where [description]
     *  @author wang  2019/05/24
     */
    public function HistoricalOrder($where){
        return $this->db->table(self::sales_order)
            ->alias('A')
            ->where($where)
            ->join(self::order_shipping_address.' B','A.order_id = B.order_id')
            ->field('A.*,B.first_name,B.last_name,B.country AS ShippingCountry,B.city AS ShippingCity,B.email,B.mobile')
            //->order('A.create_on desc')
            ->page(1,20)->select();
    }
    /*
    * 获取订单产品
    * @author wang  2019/05/24
    */
    public function OrderProduct($where){
        return $this->db->table(self::sales_order_item)->where($where)->field('product_nums,sku_num,product_name,captured_price_usd,shipping_model')->select();
    }
    /**
     * 获所有子订单
     * [RiskControlCertificate description]
     */
    public function ChildOrder($where){
          $list = $this->crc->table(self::wind_control_special_child_order)->where($where)->field('OrderNumber')->select();

          $OrderNumber = '';
          if(!$list){
             return;
          }
          foreach ($list as $k => $v) {
                if(!empty($v['OrderNumber'])){
                    if($OrderNumber == ''){
                        $OrderNumber =  $v['OrderNumber'];
                    }else{
                        $OrderNumber .= ','.$v['OrderNumber'];
                    }
                }
          }
          return $this->db->table(self::sales_order)->where(['order_number'=>['in',$OrderNumber]])->field('order_number,create_on,order_status,customer_id,customer_name,goods_total,grand_total,refunded_amount,total_amount,exchange_rate,currency_code,captured_amount_usd')->select();
    }

    /**
     * 获所有子订单
     * [RiskControlCertificate description]
     */
    public function ChildOrderDetails($order_number){
        //$where['order_number']=['<>',$order_number];
        $where['order_master_number']=$order_number;
        return $this->db->table(self::sales_order)->where($where)->field('order_id,order_number,create_on,order_status,customer_id,customer_name,goods_total,grand_total,refunded_amount,total_amount,exchange_rate,currency_code,captured_amount,captured_amount_usd')->select();
    }

    /**
     *  根据条件获取数据
     *  [RiskOrderList description]
     *  @author wang  2019/05/23
     */
    public  function RiskOrderList($where=[],$data=[]){

        $page_size = !empty($data['page_size'])?$data['page_size']:20;
        $page = $data['page'];
        $list = $this->crc->table(self::wind_control_special_afterwards)
            ->where($where)
            //->field('Id,SiteID,CustomerID,OrderNumber,TransactionID,Amount,ThirdPartyTxnID,Code,Msg,AddTime,AllotStatus,Operator,PaymentChannel,OperatingTime,ThidPartyRiskResult,DealWithStatus,CustomerIP,ShippAddressFirstName,ShippAddressLastName,ShippAddressPhone,ShippAddressCountry,ShippAddressStreet1,ShippAddressStreet2,ShippAddressEmail,PaymentMethod,BillingAddressCountry')
            ->field('*')
            ->order('IsEmail desc,AddTime DESC')
            ->group('OrderNumber')
            ->paginate($page_size,false,
                [   'type' => 'bootstrap',
                    'page' => $page,
                    'var_page' => 'page',
                    'path'=>'/WindControl/RiskOrderList',
                    'query'=>$data]);
        return $list;
        // echo Db::name(self::wind_control_special_afterwards)->getlastsql();
    }

    /**
     *  根据条件获取数据
     *  [RiskOrderList description]
     *  @author tinghu.liu  2019/09/05
     */
    public  function RiskOrderListForHistory($where=[],$data=[]){
        $start_time = isset($where['StartTime'])?$where['StartTime']:'';
        $end_time = isset($where['EndTime'])?$where['EndTime']:'';


        $page = isset($where['page'])?$where['page']:1;
        $page_size = isset($where['page_size'])?$where['page_size']:1;
        $path = isset($where['Path'])?$where['Path']:1;

        unset($where['StartTime'], $where['EndTime'],$where['page'], $where['page_size'], $where['Path']);

        $query = $this->crc->table(self::wind_control_special_afterwards)->where($where);
        if (!empty($start_time) && !empty($end_time)){
            $query->where('AddTime', '>=', $start_time)
                ->where('AddTime', '<', $end_time);
        }
//        $page_size = $data['page_size']  = 5;
//        $page = $data['page'];
        $list = $query
            ->field('Id,SiteID,CustomerID,OrderNumber,TransactionID,Amount,ThirdPartyTxnID,Code,Msg,AddTime,AllotStatus,Operator,PaymentChannel,OperatingTime,ThidPartyRiskResult,DealWithStatus,CustomerIP,ShippAddressFirstName,ShippAddressLastName,ShippAddressPhone,ShippAddressCountry,ShippAddressStreet1,ShippAddressStreet2,ShippAddressEmail,PaymentMethod,BillingAddressCountry,CurrencyCode')
            ->order('AddTime DESC')
            //->group('OrderNumber')
            ->paginate($page_size,false,['type' => 'bootstrap','page' => $page,'var_page' => 'page', 'path'=>$path,'query'=>$data])
        ;
        $page = $list->render();
        $data = $list->toArray();

        return ['data'=>$data, 'page'=>$page];
    }

    /**
     * 风控订单详情查询
     * [CustomerServiceList description]
     * @author wang  2019/05/24
     */
    public  function WindControlOrderList($where = []){
        return $this->crc->table(self::wind_control_special_afterwards)
            ->where($where)
            // ->field('CustomerID,OrderNumber,TransactionID,Amount,ThirdPartyTxnID,code,msg,AddTime,allot_status,operator')
            ->order('AddTime DESC')
            ->group('TransactionID')
            ->select();
    }

    /**
     * 风控订单详情查询
     * [CustomerServiceList description]
     * @author
     */
    public  function WindControlOrderJoin($where = []){
       $data=$this->crc->table(self::wind_control_special_afterwards)
            ->alias('A')
            ->where($where)
            ->join(self::wind_control_special_Card.' B','A.OrderNumber = B.OrderNumber','LEFT')
            ->field('A.*,B.BinCode,B.LastFourDigit')
            ->order('A.AddTime DESC')
            ->group('A.TransactionID')
            ->select();
        //echo $this->crc->table(self::wind_control_special_afterwards)->getLastSql();die;
        return $data;
    }

    /**
     * [ThirdPartyResults description]
     * @param [type] $where [description]
     */
    public  function ThirdPartyResults($where){
        return $this->crc->table(self::wind_control_special_third_party_results)->where($where)->field('Decision,Recommendation,RawData')->find();
    }

    /**
     * 获取进入风控日志备注
     * [WindControlOrderLog description]
     */
    public  function WindControlOrderLog($where = []){
        return $this->crc->table(self::wind_control_special_log_save_result)->where($where)->limit(20)->order('AddTime desc')->select();
    }

    /**
     *风控判定结果
     */
    public  function RiskManageSave($data=[],$id){
        return $this->crc->table(self::wind_control_special_afterwards)->where(['id'=>$id,'code'=>['neq',200]])->update($data);
    }

    /**
     *风控判定结果修改
     */
    public  function RiskManageSaveOrder($data=[],$where){
        return $this->crc->table(self::wind_control_special_afterwards)->where($where)->update($data);
    }

    /**
     * 根据用户名获取该用户的所有风控订单
     * [HistoricalWindControlOrders description]
     * @author wang  2019/05/24
     */
    public function HistoricalWindControlOrders($where = []){
        return $this->crc->table(self::wind_control_special_afterwards)
            ->where($where)
            // ->field('CustomerID,OrderNumber,TransactionID,Amount,ThirdPartyTxnID,code,msg,AddTime,allot_status,operator')
            ->order('AddTime DESC')
            ->find();
    }

    /**
     * 写入进风控日志备注
     * [logSaveResult description]
     * @return [type] [description]
     */
    public  function logSaveResult($data){
        return $this->crc->table(self::wind_control_special_log_save_result)->insert($data);
    }

    /**
     * 获取银行卡信息
     * [logSaveResult description]
     * @return [type] [description]
     */
    public  function getBrank($where){
        return $this->crc->table(self::bank_info)->where($where)->find();
    }

    /**
     * 获取黑名单
     * [logSaveResult description]
     * @return [type] [description]
     */
    public  function getOneBlacklist($where){
        return $this->crc->table(self::wind_control_special_list)->where($where)->find();
    }
}