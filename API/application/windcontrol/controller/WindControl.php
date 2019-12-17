<?php
namespace app\windcontrol\controller;
use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\common\controller\Base;
use app\windcontrol\model\WindControlModel;
use think\Config;
use think\Db;
use app\windcontrol\model\SpecialListModel;
use app\common\helpers\RedisClusterBase;
use think\Log;
use vendor\aes\aes;

/**
 * LIS系统 接口
 * author: Wang
 * AddTime:2018-12-08
 */
class WindControl extends Base
{

  // public function __construct(){
  //       define('SQL_ORSER_PACKAGE', 'order_package');
  //       define('SQL_ORSER_PACKAGE_TRACK', 'order_package_track');
  //       $this->db = Db::connect('db_order');
  // }

   /**
   * 判断admin新增数据是否正确(直接admin数据)
   * [SpecialList description]
   */
  public function SpecialList(){
      $data = request()->post();
      if(empty($data)){     return $data; }
      if(!empty($data["cic_id"]) && is_numeric($data['cic_id'])){
          $result = model("SpecialListModel")->SpecialList(['ID'=>$data["cic_id"]],1);
      }else if(!empty($data["EmailUserName"])){
          $preg_email='/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims';
          if(preg_match($preg_email,$data["EmailUserName"])){
               $email = explode("@",$data["EmailUserName"]);
               if(!empty($email[0]) && !empty($email[1])){
                   vendor('aes.aes');
                   $aes = new aes();
                   $EmailUserName = $aes->encrypt($email[0],'Customer','EmailUserName');
                   $result = model("SpecialListModel")->SpecialList(['EmailUserName'=>$EmailUserName,'EmailDomainName'=>$email[1]],2);
               }else{
                   return apiReturn(['code'=>1001, 'msg'=>'邮箱有误']);
               }
          }else{
               return apiReturn(['code'=>1001, 'msg'=>'邮箱有误']);
          }
      }

      if($result){
            return apiReturn(['code'=>200]);
      }else{
            return apiReturn(['code'=>100, 'msg'=>'没有数据']);
      }

      // if(empty($data['status'])){
      //      $data['status'] = 0;//默认为0，指卖家
      // }
      // $result = model("logisticsDetail")->LisLogisticsDetail($data);
      // return $result;
  }

    /**
     * 风控获取订单列表
     * [WindControlOrderDetails description]
     */
    public function RiskOrderList()
    {
    try{
        $post = $_POST;
        $page=!empty($post['page'])?!empty($post['page']):1;
        $WindControlModel=new WindControlModel();
        unset($post['access_token']);
        if(!empty($post['DealWithStatus'])){
            $post['DealWithStatus'][0]=trim($post['DealWithStatus'][0]);
        }
        if(!empty($post['Msg'])){
            $post['Msg'][0]=trim($post['Msg'][0]);
        }
        if(!empty($post['page_data'])){
            $page_data=json_decode($post['page_data'],true);
            unset($post['page_data']);
        }else{
            $page_data=[];
        }
        if(!empty($post['AmountUsd_1']) && !empty($post['AmountUsd_2'])){
            $post['AmountUsd'] = [['gt',$post['AmountUsd_1']],['elt',$post['AmountUsd_2']]];
            unset($post['AmountUsd_1']);
            unset($post['AmountUsd_2']);
        }else if(!empty($post['AmountUsd_1'])){
            $post['AmountUsd'] = ['egt',$post['AmountUsd_1']];
            unset($post['AmountUsd_1']);
        }else if(!empty($post['AmountUsd_2'])){
            $post['AmountUsd'] = ['elt',$post['AmountUsd_2']];
            unset($post['AmountUsd_2']);
        }
        $data=$WindControlModel->RiskOrderList($post,$page_data);
        $page=$data->render();

        return apiReturn([
            'code'=>200,
            'data'=>$data,
            'page'=>$page,
        ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控获取订单列表
     * [WindControlOrderDetails description]
     */
    public function RiskOrderListForHistory()
    {
        try{
            $post = $_POST;
            vendor('aes.aes');
            $aes = new aes();
            $WindControlModel=new WindControlModel();
            unset($post['access_token']);
            if(!empty($post['DealWithStatus'])){
                $post['DealWithStatus'][0]=trim($post['DealWithStatus'][0]);
            }
            if( !empty($post['ShippAddressEmail']) ){
                $post['ShippAddressEmail'] = $aes->encrypt($post['ShippAddressEmail'],'Address','Phone');
            }
            if( !empty($post['ShippAddressPhone']) ){
                $post['ShippAddressPhone'] = $aes->encrypt($post['ShippAddressPhone'],'Address','Phone');
            }
            if( !empty($post['BillingAddressEmail']) ){
                $post['BillingAddressEmail'] = $aes->encrypt($post['BillingAddressEmail'],'Address','Phone');
            }
            if( !empty($post['BillingAddressPhone']) ){
                $post['BillingAddressPhone'] = $aes->encrypt($post['BillingAddressPhone'],'Address','Phone');
            }
            $data=$WindControlModel->RiskOrderListForHistory($post);
            if(!empty($data['data']['data'])){
                foreach ($data['data']['data'] as $key=>$value){
                    //$data['data']['data'][$key]['ShippAddressStreet1'] = !empty($value['ShippAddressStreet1'])?$aes->decrypt($value['ShippAddressStreet1'],'Address','Street1'):'';
                    //$data['data']['data'][$key]['ShippAddressStreet2'] = !empty($value['ShippAddressStreet2'])?$aes->decrypt($value['ShippAddressStreet2'],'Address','Street2'):'';
                    $data['data']['data'][$key]['ShippAddressPhone'] = !empty($value['ShippAddressPhone'])?$aes->decrypt($value['ShippAddressPhone'],'Address','Phone'):'';
                    //$data['data']['data'][$key]['ShippAddressZipCode'] = !empty($value['ShippAddressZipCode'])?$aes->decrypt($value['ShippAddressZipCode'],'Address','Zip'):'';
                    //$data['data']['data'][$key]['BillingAddressStreet1'] = !empty($value['BillingAddressStreet1'])?$aes->decrypt($value['BillingAddressStreet1'],'Address','Street1'):'';
                    //$data['data']['data'][$key]['BillingAddressStreet2'] = !empty($value['BillingAddressStreet2'])?$aes->decrypt($value['BillingAddressStreet2'],'Address','Street2'):'';
                    $data['data']['data'][$key]['BillingAddressPhone'] = !empty($value['BillingAddressPhone'])?$aes->decrypt($value['BillingAddressPhone'],'Address','Phone'):'';
                    //$data['data']['data'][$key]['BillingAddressZipCode'] = !empty($value['BillingAddressZipCode'])?$aes->decrypt($value['BillingAddressZipCode'],'Address','Zip'):'';
                    $data['data']['data'][$key]['ShippAddressEmail'] = !empty($value['ShippAddressEmail'])?$aes->decrypt($value['ShippAddressEmail'],'Address','EmailUserName'):'';
                    $data['data']['data'][$key]['BillingAddressEmail'] = !empty($value['BillingAddressEmail'])?$aes->decrypt($value['BillingAddressEmail'],'Address','EmailUserName'):'';
                }
            }
            return apiReturn([
                'code'=>200,
                'data'=>$data
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控获取订单
     * [WindControlOrderDetails description]
     */
    public function WindControlOrderList()
    {
        try{
            $post = request()->post();
            vendor('aes.aes');
            $aes = new aes();
            Log::record('风控获取1post: '.json_encode($post));
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            if(!empty($post['Code'])){
                $post['Code'][0]=trim($post['Code'][0]);
            }
            Log::record('风控获取2post: '.json_encode($post));
            $data=$WindControlModel->WindControlOrderList($post);
            if(!empty($data)){
                foreach ($data as $key=>$value){
                    //$data[$key]['ShippAddressStreet1'] = !empty($value['ShippAddressStreet1'])?$aes->decrypt($value['ShippAddressStreet1'],'Address','Street1'):'';
                    //$data[$key]['ShippAddressStreet2'] = !empty($value['ShippAddressStreet2'])?$aes->decrypt($value['ShippAddressStreet2'],'Address','Street2'):'';
                    $data[$key]['ShippAddressPhone'] = !empty($value['ShippAddressPhone'])?$aes->decrypt($value['ShippAddressPhone'],'Address','Phone'):'';
                    //$data[$key]['ShippAddressZipCode'] = !empty($value['ShippAddressZipCode'])?$aes->decrypt($value['ShippAddressZipCode'],'Address','Zip'):'';
                    $data[$key]['ShippAddressEmail'] = !empty($value['ShippAddressEmail'])?$aes->decrypt($value['ShippAddressEmail'],'Address','EmailUserName'):'';
                    //$data[$key]['BillingAddressStreet1'] = !empty($value['BillingAddressStreet1'])?$aes->decrypt($value['BillingAddressStreet1'],'Address','Street1'):'';
                    //$data[$key]['BillingAddressStreet2'] = !empty($value['BillingAddressStreet2'])?$aes->decrypt($value['BillingAddressStreet2'],'Address','Street2'):'';
                    $data[$key]['BillingAddressPhone'] = !empty($value['BillingAddressPhone'])?$aes->decrypt($value['BillingAddressPhone'],'Address','Phone'):'';
                    //$data[$key]['BillingAddressZipCode'] = !empty($value['BillingAddressZipCode'])?$aes->decrypt($value['BillingAddressZipCode'],'Address','Zip'):'';
                    $data[$key]['BillingAddressEmail'] = !empty($value['BillingAddressEmail'])?$aes->decrypt($value['BillingAddressEmail'],'Address','EmailUserName'):'';
                }
            }
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控获取订单关联查询
     * [WindControlOrderDetails description]
     */
    public function WindControlOrderJoin()
    {
        try{
            $post = request()->post();
            Log::record('风控获取1post: '.json_encode($post));
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }

            $where['A.OrderNumber']=$post['OrderNumber'];
            $data=$WindControlModel->WindControlOrderJoin($where);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控银行卡信息
     */
    public function getBrank()
    {
        try{
            $post = request()->post();
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }

            $where['Number']=$post['Number'];
            $data=$WindControlModel->getBrank($where);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 以色列风控
     * [WindControlOrderDetails description]
     */
    public function ThirdPartyResults()
    {
        try{
            $post = request()->post();
            $validate=[
                'AfterwardsId'   => 'require',
            ];
            $result = $this->validate($post,$validate);
            if(true !== $result){
                // 验证失败 输出错误信息
                return apiReturn([
                    'code'=>1200,
                    'msg'=>$result,
                    'data'=>[],
                ]);
            }
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $data=$WindControlModel->ThirdPartyResults($post);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控历史
     * [WindControlOrderDetails description]
     */
    public function WindControlOrderLog()
    {
        try{
            $post = request()->post();
            $validate=[
                'CustomerID'   => 'require',
            ];
            $result = $this->validate($post,$validate);
            if(true !== $result){
                // 验证失败 输出错误信息
                return apiReturn([
                    'code'=>200,
                    'msg'=>$result,
                    'data'=>[],
                ]);
            }
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $data=$WindControlModel->WindControlOrderLog($post);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控历史
     * [WindControlOrderDetails description]
     */
    public function getAfterwards()
    {
        try{
            $post = request()->post();
            $validate=[
                'ID'   => 'require',
            ];
            $result = $this->validate($post,$validate);
            if(true !== $result){
                // 验证失败 输出错误信息
                return apiReturn([
                    'code'=>200,
                    'msg'=>$result,
                    'data'=>[],
                ]);
            }
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $data=$WindControlModel->getAfterwards($post);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 交易往来信息
     * [WindControlOrderDetails description]
     */
    public function TransactionInformation($order)
    {
        try{
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $url=config('payment_base_url');
            $data = doCurl($url.'unification/query/queryTable', $order,null,true);
            if(!empty($data['code']==200)){
                $da=$data['data'];
            }else{
                $da=[];
            }
            return $da;
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控历史
     * [WindControlOrderDetails description]
     */
    public function HistoricalWindControlOrders()
    {
        try{
            $post = request()->post();
            /*$validate=[
                'CustomerID'   => 'require',
            ];
            $result = $this->validate($post,$validate);
            if(true !== $result){
                // 验证失败 输出错误信息
                return apiReturn([
                    'code'=>200,
                    'msg'=>$result,
                    'data'=>[],
                ]);
            }*/
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $data=$WindControlModel->HistoricalWindControlOrders($post);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控修改
     * [WindControlOrderDetails description]
     */
    public function RiskManageSave()
    {
        try{
            $post = request()->post();

            $id = request()->get('id');
            /*
            $validate=[
                'CustomerID'   => 'require',
            ];
            $result = $this->validate($post,$validate);
            if(true !== $result){
                // 验证失败 输出错误信息
                return apiReturn([
                    'code'=>200,
                    'msg'=>$result,
                    'data'=>[],
                ]);
            }*/

            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }

            $data=$WindControlModel->RiskManageSave($post,$id);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

    /**
     * 风控修改
     * [WindControlOrderDetails description]
     */
    public function RiskManageSaveOrder()
    {
        try{
            $post = request()->post();
            $OrderNumber = request()->get('OrderNumber');
            if(empty($OrderNumber)){
                return apiReturn([
                    'code'=>1200,
                    'msg'=>'OrderNumber不能为空',
                ]);
            }
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $where['OrderNumber']=$OrderNumber;
            $data=$WindControlModel->RiskManageSaveOrder($post,$where);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

  /**
   * 风控详情获取订单详情
   * [WindControlOrderDetails description]
   */
  public function WindControlOrderDetails(){
      $data = request()->post();
      $UserWhere = [];
      $OrderWhere = [];
      $UserDetails = [];
      $OrderStatusChange = [];
      $OrderNumber = '';
      $Transaction = [];
      $OrderProduct = [];
      $ChildOrder = [];

      if(!empty($data['CustomerID'])){
         $UserWhere['ID'] = $data['CustomerID'];
      }
      if(!empty($data['OrderNumber'])){
         $OrderNumber = $OrderWhere['A.order_number'] = $data['OrderNumber'];
         // $OrderNumber = $OrderWhere['A.order_number'] = $data['OrderNumber'];
      }
      //获取用户信息
      if(!empty($UserWhere)){

            //$UserDetails = model("WindControlModel")->UserDetails($UserWhere);
          $da['ID']=$data['CustomerID'];
          $da['type']=2;
          $customer_data = (new BaseApi())->getCustomerByID($da);
          if(!empty($customer_data['data'])){
              $UserDetails=$customer_data['data'];
          }else{
              Log::record('$UserDetails无法获取'.json_encode($customer_data),'error');
          }

          //根据用户获取历史订单
          $HistoricalOrder = model("WindControlModel")->HistoricalOrder(['A.customer_id'=>$UserWhere['ID']]);


      }


      //获取订单信息
      if(!empty($OrderWhere)){
            $OrserDetails = model("WindControlModel")->OrserDetails($OrderWhere);
            //获取子订单信息
            $ChildOrder = model("WindControlModel")->ChildOrderDetails($OrderNumber);
          //或取订单产品
          if(!empty($ChildOrder)){
              $order_id=array_column($ChildOrder, 'order_id');
              $OrderProduct = model("WindControlModel")->OrderProduct(['order_id'=>['in',$order_id]]);
              foreach($ChildOrder as &$value){
                  $value['OrderStatusChange']= model("WindControlModel")->SalesOrderStatusChange(['order_id'=>$value['order_id']]);
              }
          }else{
              $OrderProduct = [];
              $OrderStatusChange= [];
          }
            $Transaction = $this->TransactionInformation(['order_master_number'=>$OrderNumber]);
      }
      $BlacklistWhere['value']=$data['CustomerID'];
      $BlacklistWhere['type']=1;
      $BlacklistWhere['list_type'] = "CICID";
      $Blacklist = model("WindControlModel")->getOneBlacklist($BlacklistWhere);
      return apiReturn([
        'code'=>200,
        'UserDetails'=>$UserDetails,
        'OrserDetails'=>$OrserDetails,
        'OrderStatusChange'=>$OrderStatusChange,
        'order_status'=>config('order_status'),
        'OrderProduct'=>$OrderProduct,
        'HistoricalOrder'=>$HistoricalOrder,
        'Transaction'=>$Transaction,
        'ChildOrder'=>$ChildOrder,
          'Blacklist'=>$Blacklist
        ]);
  }

    /**
     * 风控日志添加
     * [WindControlOrderDetails description]
     */
    public function logSaveResult()
    {
        try{
            $post = request()->post();
            $WindControlModel=new WindControlModel();
            if(isset($post['access_token'])){
                unset($post['access_token']);
            }
            $data=$WindControlModel->logSaveResult($post);
            return apiReturn([
                'code'=>200,
                'data'=>$data,
            ]);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }

}