<?php
/**
 * User: wang
 * Date: 2019/3/05
 */
namespace app\admin\model;
use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Model;
use think\Db;
use think\Session;


/**
 * Add by:wang
 * AddTime:2019-03-27
 */
class WindControlModel  extends Model{
    protected $connection = 'db_crc';
    const wind_control_special_list = 'wind_control_special_list';
    const wind_control_special_country = 'wind_control_special_country';
    const wind_control_special_city = 'wind_control_special_city';
    const wind_control_special_address = 'wind_control_special_address';
    const wind_control_special_afterwards = 'wind_control_special_afterwards';
    const wind_control_special_manual_processing = 'wind_control_special_manual_processing';
    const wind_control_special_log_save_result = 'wind_control_special_log_save_result';
    const wind_control_special_third_party_results = 'wind_control_special_third_party_results';
    const wind_control_special_child_order = 'wind_control_special_child_order';
    const reports = 'reports';
    const user = 'user';
    /*
    public function __construct(){
        parent::__construct();
        $this->db = "db_crc";
        $this->table = "dx_wind_control_special_afterwards";
    }
    */
    /**
     * [SpecialList description]
     * @param array   $where     查询条件
     * @param integer $page      页数
     * @param integer $page_size 每页条数
     * @param string  $table     对应表
     * @author wang  2019/05/20
     */
    public static function SpecialList($where = array(),$page = 1,$page_size = 20,$table=''){
         $countPage = self::name($table)->where($where)->count();
         $list = self::name($table)->where($where)->page($page,$page_size)->order('add_time DESC')->select();
         return ['list'=>$list,'countPage'=>$countPage];
    }
    /**
     * [SpecialList_add description]
     * @param array  $where 添加或修改数据
     * @param string $id    修改ID
     * @param string $table 对应数据表
     * @param string $whereFind 检查条件
     * @author wang  2019/05/20
     *
     */
    public static function SpecialList_add($where=array(),$table='',$id = '',$whereFind=array()){
         if($id == ''){
             $list = self::name($table)->where($whereFind)->find();
             if(!empty($list)){
                return 2;
             }
             return self::name($table)->insert($where);
         }else{
             return self::name($table)->where(['id'=>$id])->update($where);
         }
    }


    /**
     * 获取一条风控特殊名单
     * [SpecialList_add description]
     * @author kevin  2019/10/23
     *
     */
    public static function getOneSpecialList($where=array()){
        $data = self::name(self::wind_control_special_list)->where($where)->find();
        if(!empty($data) && strtolower($data['list_type']) == 'email'){
            $data['value'] = aes()->decrypt($data['value']);
        }
        return $data;
    }


    /**
     * 获取一条风控国家数据
     * [SpecialList_add description]
     * @author kevin  2019/10/23
     *
     */
    public static function getOneSpecialCountry($where=array()){
        $data = self::name(self::wind_control_special_country)->where($where)->find();
        return $data;
    }

    /**
     * 获取一条风控城市数据
     * [SpecialList_add description]
     * @author kevin  2019/10/23
     *
     */
    public static function getOneSpecialCity($where=array()){
        $data = self::name(self::wind_control_special_city)->where($where)->find();
        return $data;
    }

    /**
     * 获取一条风控城市数据
     * [SpecialList_add description]
     * @author kevin  2019/10/23
     *
     */
    public static function getOneSpecialAddress($where=array()){
        $data = self::name(self::wind_control_special_address)->where($where)->find();
        return $data;
    }
    /**
     * [SpecialAddress description]
     * @param array   $where     [description]
     * @param integer $page      [description]
     * @param integer $page_size [description]
     * @author wang  2019/05/22
     */
    public static function SpecialAddress($where = array(),$page = 1,$page_size = 20){
          $countPage = self::name(self::wind_control_special_address)->where($where)->count();
          $list = self::name(self::wind_control_special_address)->where($where)->order("id DESC")->page($page,$page_size)->select();
          return ['list'=>$list,'countPage'=>$countPage];
    }
    /**
     * [SpecialAddress_add description]
     * @param [type] $where 添加或修改数据
     * @param string $id    修改ID
     * @author wang  2019/05/23
     */
    public static function SpecialAddress_add($where=[],$id=''){
          if($id == ''){
             $list = self::name(self::wind_control_special_address)->where(['street'=>$where['street'],'city'=>$where['city']])->find();
             if(!empty($list)){
                return 2;
             }
             return self::name(self::wind_control_special_address)->insert($where);
          }else{
             return self::name(self::wind_control_special_address)->where(['id'=>$id])->update($where);
          }
    }


    /**
     * [DistributionSpecialAfterwards description]
     * @param [type] $where 修改条件
     * @param string $update_data    修改数据
     * @author kevin  2019/10/12
     */
    public static function DistributionSpecialAfterwards($where=[],$update_data){
        return self::name(self::wind_control_special_afterwards)->where($where)->update($update_data);
    }

    /**
     *  根据条件获取数据
     *  [RiskOrderList description]
     *  @author wang  2019/05/23
     */
    public static function RiskOrderList($where=[],$data=[]){
          $page_size = $data['page_size']  = config('paginate.list_rows');
          // $list = self::name(self::wind_control_special_afterwards)
          $list = self::name(self::wind_control_special_afterwards)
          ->where($where)
          ->field('Id,SiteID,CustomerID,OrderNumber,TransactionID,Amount,ThirdPartyTxnID,Code,Msg,AddTime,AllotStatus,Operator,PaymentChannel,OperatingTime,ThidPartyRiskResult,DealWithStatus,CustomerIP,ShippAddressFirstName,ShippAddressLastName,ShippAddressPhone,ShippAddressCountry,ShippAddressStreet1,ShippAddressStreet2,ShippAddressEmail,PaymentMethod,BillingAddressCountry')
          ->order('AddTime DESC')
          //->group('OrderNumber')
          ->paginate($page_size,false,['type' => 'bootstrap','var_page' => 'page','query'=>$data]);
            // echo self::name(self::wind_control_special_afterwards)->getlastsql();
          return $list;
           // echo self::name(self::wind_control_special_afterwards)->getlastsql();
    }
    /**
     * [ManualProcessing description]
     * @param [type] $where [description]
     * @param [type] $data  [description]
     * @author wang  2019/05/23
     */
    public static function ManualProcessing($where=[],$data=[]){
          $page_size = $data['page_size'];
          $list = self::name(self::wind_control_special_afterwards)
          ->alias('A')
          ->where($where)
          ->join(self::wind_control_special_manual_processing.' B','A.id = B.afterwards_id')
          ->field('A.id,A.OrderNumber,A.Amount,A.ShippAddressCountry,A.ShippAddressCountryName,A.operator,A.OperatingTime,B.result')
          ->order('B.AddTime DESC')
          ->paginate($page_size,false,['type' => 'bootstrap','var_page' => 'page','query'=>$data]);
           // echo self::name(self::wind_control_special_afterwards)->getlastsql();
          return $list;
    }
    /**
     * 获取所有客服数据
     * [CustomerServiceList description]
     * @author wang  2019/05/23
     */
    public static function CustomerServiceList($where = []){
            return Db::name(self::user)->where($where)->field('username,group_id,id')->select();
    }

    /**
    * 风控订单详情查询
    * [CustomerServiceList description]
    * @author wang  2019/05/24
    */
    public static function WindControlOrderDetails($where = []){
           return self::name(self::wind_control_special_afterwards)
           ->where($where)
           // ->field('CustomerID,OrderNumber,TransactionID,Amount,ThirdPartyTxnID,code,msg,AddTime,allot_status,operator')
           ->order('AddTime DESC')
           ->select();
    }

    /**
     * 根据用户名获取该用户的所有风控订单
     * [HistoricalWindControlOrders description]
     * @author wang  2019/05/24
     */
    public static function HistoricalWindControlOrders($where = []){
          return self::name(self::wind_control_special_afterwards)
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
    public static function logSaveResult($where = []){
          return self::name(self::wind_control_special_log_save_result)->insert($where);
    }
    /**
     * 获取进入风控日志备注
     * [WindControlOrderLog description]
     */
    public static function WindControlOrderLog($where = []){
          return self::name(self::wind_control_special_log_save_result)->where($where)->limit(20)->order('AddTime desc')->select();
    }

    /**
     * [ThirdPartyResults description]
     * @param [type] $where [description]
     */
    public static function ThirdPartyResults($where = []){
          return self::name(self::wind_control_special_third_party_results)->where($where)->field('Decision,Recommendation,RawData')->find();
    }
    /**
     * 风控凭证
     * [RiskControlCertificate description]
     */
    public static function RiskControlCertificate($where = []){
         $OrderNumber = '';
         $reports = [];
         $list = self::name(self::wind_control_special_child_order)->where($where)->field('OrderNumber')->select();
         if(!empty($list)){
             foreach ($list as $k => $v) {
                if(!empty($v['OrderNumber'])){
                    if($OrderNumber == ''){
                        $OrderNumber =  $v['OrderNumber'];
                    }else{
                        $OrderNumber .= ','.$v['OrderNumber'];
                    }
                }
             }
             $reports = Db::name(self::reports)->where(['order_number'=>['in',$OrderNumber],'report_type'=>5])->field('id,reason,product_url,order_number,enclosure')->select();
         }
         return $reports;
         // echo self::name(self::wind_control_special_child_order)->getlastsql();
         // dump($list);
    }

    /**
     * 风控凭证
     * [RiskControlCertificate description]
     */
    public static function getRiskControlCertificate($OrderNumbers){
        $OrderNumbers = implode(',', $OrderNumbers);
        
        return Db::name(self::reports)->where(['order_number'=>['in',$OrderNumbers],'report_type'=>5])->field('id,reason,product_url,order_number,enclosure,report_status')->select();
    }

    //修改风控凭证状态
    public static function UpdateControlCertificate($where = [],$update_data){
        return Db::name(self::reports)->where($where)->update($update_data);
    }
    /**
     * [ManualProcessing description]
     * @param [type] $where [description]
     * @param [type] $data  [description]
     */
    public static function SearchFor($where=[],$data=[]){
    }

    /**
    *风控判定结果
    */
    public static function RiskManage($data=[],$id){
        return self::name(self::wind_control_special_afterwards)->where(['id'=>$id,'code'=>['neq',200]])->update($data);
    }

    public function getRiskData($where){
        return self::name(self::wind_control_special_afterwards)->alias('A')->where($where)->join(self::wind_control_special_third_party_results.' B','A.Id = B.AfterwardsId','left')->field("A.DistributionAdminId,A.DistributionAdmin,A.AmountUsd,A.OperatingTime,A.AddTime,A.DealWithStatus,B.Decision,B.Recommendation")->select();
    }

    public function getAllData($where){
        $allData = self::name(self::wind_control_special_afterwards)->where($where)->field("count(*) as num,sum(AmountUsd) as sum,avg(AmountUsd) as avg")->find();   

        $where['Code'] = ['=','1001'];//进入风控

        $riskData = self::name(self::wind_control_special_afterwards)->where($where)->field("count(*) as num,sum(AmountUsd) as sum,avg(AmountUsd) as avg")->find();

        return ['allData'=>$allData,'riskData'=>$riskData];
    }

    public function getRiskDataById($where){
        return self::name(self::wind_control_special_afterwards)->alias('A')->where($where)->join(self::wind_control_special_third_party_results.' B','A.Id = B.AfterwardsId','left')->field('A.*,B.Decision,B.Recommendation')->select(); 
    }

    public function getAddressByOrdernumber($where){
        return self::name(self::wind_control_special_afterwards)->where($where)->field("OrderNumber,ShippAddressFirstName,ShippAddressLastName,ShippAddressStreet1,ShippAddressStreet2,ShippAddressCity,ShippAddressState,ShippAddressCountryName")->select(); 
    }
}