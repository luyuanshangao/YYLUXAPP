<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use think\Paginator;
use app\admin\dxcommon\BaseApi;
/*
 * 商城会员管理
 * AddTime:2018-04-17
 * Info:
 *
 */
class MemberManagement extends Action
{
    const public_log = 'public_log';
  	public function __construct(){
         Action::__construct();
         // define('S_CONFIG', 'dx_sys_config');//mongodb数据表
         // define('PAY_CONFIG', 'dx_pay_config');//mongodb数据表
      }
  	/*
  	 *会员列表
  	 */
  	public function index()
  	{
          $page         = input('page');
          if(!$page){
              $page = 1;
          }
          // $data = request()->post();
          $data = input();
          $data_page = array();
          if(!empty($data['ID'])){
              $UserId = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',',',',',',',',',' ',',',',',','],$data['ID']);
              $pattern = '/(,)+/i';
              $UserId = preg_replace($pattern,',',$UserId);
              $data['ID'] = $where['ID'] = $UserId;
          }
          if(isset($data['UserName'])){
              $UserName = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',',',',',',',',',' ',',',',',','],$data['UserName']);
              $pattern = '/(,)+/i';
              $UserName = preg_replace($pattern,',',$UserName);
              $data['UserName'] = $where['UserName'] = $UserName;
          }
          if(isset($data['RegisterStart'])){
            $where['RegisterStart'] = strtotime($data['RegisterStart']);
          }
          if(isset($data['RegisterEnd'])){
            $where['RegisterEnd']   = strtotime($data['RegisterEnd']);
          }

          if(isset($data['CountryCode'])){
            $where['CountryCode']   = $data['CountryCode'];
          }
          if(isset($data['Email'])){
              $where['Email']   = $data['Email'];
          }
          if(isset($data['BirthdayStart'])){
              $where['BirthdayStart'] = strtotime($data['BirthdayStart']);
          }
          if(isset($data['BirthdayEnd'])){
              $where['BirthdayEnd']   = strtotime($data['BirthdayEnd']);
          }
          $where['query'] = array_filter($data);
          if(isset($data['Status'])){
              $where['Status']   = $data['Status'];
              $where['query'] = $data['Status'];
          }
          $where['page'] = $page ;
          if(!empty($data['countPage'])){
            $where['countPage'] = $data['countPage'] ;
          }
          foreach ($where as $k => $v) {
             if($v == ''){
                unset($where[$k]);
             }
          }
          $result = BaseApi::getCustomerList($where);
          if(isset($result["data"]["data"]) && !empty($result["data"]["data"])){
               foreach ($result["data"]["data"] as $key=>$value){
                   $result["data"]["data"][$key]['email'] = hideStr($value['email'],1,0,5);
               }
          }
          if(!empty($where)){
              unset($where['query']);
              foreach ($where as $key => $value) {
                  $data_page[$key] = $value;
              }
          }
          $count = $data_page['countPage'] = isset($result['data']["count"])?$result['data']["count"]:'';
          $Page = CountPage($count,$data_page,"/MemberManagement/index");
          $list = isset($result["data"]["data"])?$result["data"]["data"]:array();
          $this->assign(['list'=>$list,
                       'page'=>$Page,
          ]);
      		return View('memberList');
  	}
    /**
     * 修改会员状态
     */
    public function updateStatus(){
      if($data = request()->post()){
          if(empty($data["ID"])){
              return array('code'=>100,'mag'=>'用户ID不能为空！','result'=>'用户ID不能为空！');
          }
          if($data["Status"]  == 0){
              $data["Remarks"] = '系统后台更改为未激活状态';
          }
          if($data["Status"]  == 1){
             $data["Remarks"] = '系统后台更改未激活状态';
          }
          if($data["Status"]  == 20){
              $data["Remarks"] = isset($data['Remarks'])?$data['Remarks']:'系统后台更改为禁用';
          }
          $result = BaseApi::updateStatus($data);
          $log = [
              'type'=>"Update Customer Status",
              'before_fixing'=>isset($data['From_Status'])?$data['From_Status']:'',
              'after_modification'=>"Status:".$data["Status"],
              'result'=>!empty($result["msg"])?$result["msg"]:'',
              'remarks'=>'用户ID：'.$data["ID"]
          ];
          $log = AdminLog($log);
          if($result["code"] == 200){
              /*因为当用户被禁用的时候，更改登陆过期时间,20190408 kevin*/
              if($data["Status"]  == 20 || $data["Status"]  == 21){
                  $update_token_data['cicID'] = $data["ID"];
                  $update_token_data['timeout'] = time();
                  BaseApi::updateTokenTimeout($update_token_data);
              }

              //记录日志
              AdminInsert(self::public_log,$log);
              return array('code'=>200,'msg'=>$result["msg"],'result'=>$result["msg"]);
          }else{
              return array('code'=>100,'msg'=>$result["msg"],'result'=>$result["msg"]);
          }
      }

    }


    public function edit(){
        $data = input();
        if(request()->post()){
            if(isset($data["id"]) && !empty($data["id"])){
                $where['ID'] = $data["id"];
            }elseif (isset($data['Email']) && !empty($data['Email'])){
                $where['Email'] = $data["Email"];
            }elseif (isset($data['UserName']) && !empty($data['UserName'])){
                $where['UserName'] = $data["UserName"];
            }else{
                $this->error("查询条件不能为空");
            }
        }else{
            $where['ID'] = $data["id"];
        }

        $result = BaseApi::getAdminCustomerInfo($where);
        if(isset($result['data']['email'])){
            /*隐藏用户邮箱*/
            //$result["data"]['email'] = hideStr($result['data']['email'],1,0,5);
        }else{
            $this->error("此用户不存在");
        }
        $order_refunded_amount = BaseApi::getRefundedAmount(['customer_id'=>$data["id"]]);
        $order = BaseApi::getAdminCustomerOrder(['customer_id'=>$data["id"]]);
        $address = BaseApi::getAddress(['CustomerID'=>$data["id"]]);
        //dump($address);exit;
        return $this->fetch("",['CustomerInfo'=>isset($result['data'])?$result['data']:$result['data'],'order'=>isset($order['data'])?$order['data']:'','address'=>isset($address['data'])?$address['data']:'','order_refunded_amount'=>$order_refunded_amount]);


    }


    public function getCustomerOrder(){
        $data = input();
        if(!isset($data['customer_id']) || empty($data['customer_id'])){
            return ['code'=>1001,'msg'=>"缺少customer_id参数"];
        }else{
            BaseApi::getOrderListForPage();
        }
    }

    /**
     * 获取订单数据
     */
    public function getOrderList(){
        $page = input('page');
        if(!$page){
            $page = 1;
        }
        $data = request()->post();
        //判断是否为分页
        if(!$data){
            $data = input();
            if($data["order_status"]){
                $data['OrderStauts'] = $data["order_status"];
            }
            if($data["order_type"]){
                $data['OrderType'] = $data["order_type"];
            }
            if($data["is_cod"]){
                $data['COD_order'] = $data["is_cod"];
            }
            if($data["lock_status"]){
                $data['Lock'] = $data["lock_status"];
            }
            if($data["pay_channel"]){
                $data['paymentMethod_name'] = $data["pay_channel"];
            }
            if($data["logistics_provider"]){
                $data['ShippingMethod'] = $data["logistics_provider"];
            }
            if($data["payment_status"]){
                $data['PaymentStatus'] = $data["payment_status"];
            }
            if($data["fulfillment_status"]){
                $data['FulfillmentStatus'] = $data["fulfillment_status"];
            }

            if(isset($data["UserID"])){
                $data['UserID'] = $data["UserID"];
            }
        }

        foreach ((array)$data as $key => $value){
            if(is_array($data[$key]) && empty($data[$key])){
                unset($data[$key]);
            }else if(!is_array($data[$key]) && trim($data[$key]) ==''){
                unset($data[$key]);
            }
        }


        $data['page'] = $page;
        //$data['page_size'] = config('paginate.list_rows');
        $data['page_size'] = input('page_size',10);
        $data['path'] ='/Order/index';
        $data['orderBy'] = 'create_on desc';//dump($data);
        if(!empty($data['paymentMethod_name'])){
            $data['paymentMethod_name'] = str_replace(array('Boleto-','Transfer-'),array('',''),$data['paymentMethod_name']);
        }
        //dump($data);
        $result = BaseApi::getOrderListForPage($data);
        if(!empty($result)){
            $data['page'] = $page;
            $this->assign(['orderList'=>$result["data"]["data"],
                'page'=>$result["data"]["Page"],
            ]);
        }
        //获取后台配置的订单状态
        $result['orderStautsDict'] = $this->dictionariesQuery('OrderStatusView');
        if(isset($result['data']['data'])){
            foreach ($result['data']['data'] as $key=>$value){
                $result['data']['data'][$key]['order_status_name'] = isset($result['orderStautsDict'][$value['order_status']][1])?$result['orderStautsDict'][$value['order_status']][1]:'';
                $result['data']['data'][$key]['create_on'] = !empty($value['create_on'])?date("Y-m-d H:i:s",$value['create_on']):'';
            }
        }
        return $result;
    }

    //字典数据的获取
    public function dictionariesQuery($val){
        $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
        $data = explode(";",$PayemtMethod['ConfigValue']);
        foreach ($data as $key => $value) {
            if(!empty($value)){
                $expdata = explode(":",$value);
                if($expdata[0]){
                    $list[$expdata[0]] = explode(":",$value);
                }

            }

        }
        return $list;
    }

    public function getPoints()
    {
        $data = input();
        /*获取用户积分详情*/
        /*$points_type = input("points_type",1);
        $PointsBasicInfo = BaseApi::getPointsBasicInfo(['CustomerID'=>$data['customer_id'],'type'=>$points_type]);*/
        $param['CustomerID'] = $data['customer_id'];
        $param['SiteID'] = $data['SiteID'];
        $param['page_size'] = input('page_size',10);
        $param['page'] = input('page',1);
        $param['path'] = url("Points/MyPoints");
        $param = array_filter($param);
        $data = BaseApi::getPointsDetailsList($param);
        $points_details_status=['Pending','Available','deduction'];
        if(isset($data['data']['data'])){
            foreach ($data['data']['data'] as $key=>$value){
                $data['data']['data'][$key]['CreateTime'] = !empty($value['CreateTime'])?date("Y-m-d H:i:s",$value['CreateTime']):'';
                $data['data']['data'][$key]['Status'] = isset($points_details_status[$value['Status']])?$points_details_status[$value['Status']]:'';
            }
        }else{
            $data['code'] = 1002;
            $data['msg'] = "查询不到信息";
        }
        return $data;
    }

    public function getStoreCredit()
    {
        $data = input();
        /*获取用户积分详情*/
        /*$points_type = input("points_type",1);
        $PointsBasicInfo = BaseApi::getPointsBasicInfo(['CustomerID'=>$data['customer_id'],'type'=>$points_type]);*/
        $param['CustomerID'] = $data['customer_id'];
        $param['CurrencyType'] = input("CurrencyType");
        $param['page_size'] = input('page_size',10);
        $param['page'] = input('page',1);
        $param['path'] = url("StoreCredit/index");
        $param = array_filter($param);
        $data = BaseApi::getStoreCreditDetailsList($param);
        //dump($data);exit;
        if(isset($data['data']['data'])){
            foreach ($data['data']['data'] as $key=>$value){
                if($value['TransactionAmount']>=0){
                    $value['Positive'] = 1;
                }else{
                    $value['Positive'] = 0;
                }
                $data['data']['data'][$key]['CreateTime'] = !empty($value['CreateTime'])?date("Y-m-d H:i:s",$value['CreateTime']):'';
                $data['data']['data'][$key]['RequestClientID'] = $value['RequestClientID']==1?"DX.COM":'站点'.$value['RequestClientID'];
                $value['TransactionAmount'] = sprintf("%01.2f",abs($value['TransactionAmount']));
                if($value['CurrencyType'] == 1){
                    $data['data']['data'][$key]['CurrencyTypeVal'] = 'US'.$value['CurrencyCode'].$value['TransactionAmount'];
                }else{
                    $data['data']['data'][$key]['CurrencyTypeVal'] = $value['CurrencyCode'].$value['TransactionAmount'];
                }
                if($value['Positive'] != 1){
                    $data['data']['data'][$key]['CurrencyTypeVal'] = '-'.$data['data']['data'][$key]['CurrencyTypeVal'];
                }
            }
        }
        return $data;
    }

    /*获取订阅详情*/
    public function getSubscribe()
    {
        $data = input();
        $param['CustomerId'] = $data['customer_id'];
        $data = BaseApi::getSubscriber($param);
        if($data['code'] == 200){
            $data['data']['CreateTime'] = !empty($data['data']['CreateTime'])?date("Y-m-d H:i:s",$data['data']['CreateTime']):'';
            $data['data']['Active'] = $data['data']['Active']?'true':'false';
            $data['data']['SiteId'] = $data['data']['SiteId'] == 1?"DX":"站点".$data['data']['SiteId'];
        }
        return $data;
    }

    /**
     * 重设密码
     */
    public function resetPassword(){
            $data = input();
            $resetPasswordID = $data['customer_id'];
            $Password = input("Password");
            //vendor('aes.aes');
            //$aes = new aes();
            //$data['ID'] = $aes->decrypt(urldecode($resetPasswordID));
            $data['ID'] = $resetPasswordID;
            $data['Password'] = $Password;

            $res = BaseApi::saveMemberProfile($data);
            if($res['code'] == 200){
                $data['CustomerID'] = Session::get('userid');
                $data['OperateStatus'] = 1;
                $data['IPAddress'] = $_SERVER['REMOTE_ADDR'];
                $data['OperationName'] = Session::get('username');;
                $data['IPNumber'] = $_SERVER['REMOTE_ADDR'];
                BaseApi::changepasswordHistory($data);

            }
        $log = [
            'type'=>"Update Customer Reset Password",
            'result'=>!empty($res["msg"])?$res["msg"]:'',
            'remarks'=>'用户ID：'.$data['customer_id']
        ];
        $log = AdminLog($log);
        //记录日志
        AdminInsert(self::public_log,$log);
        return $res;
    }


    /**
     * 重设支付密码
     */
    public function resetpaymentPassword(){
        $data = input();
        $resetPasswordID = $data['customer_id'];
        $Password = input("Password");
        //vendor('aes.aes');
        //$aes = new aes();
        //$data['ID'] = $aes->decrypt(urldecode($resetPasswordID));
        $data['CustomerID'] = $resetPasswordID;

        $data['Password'] = $Password;
        $res = BaseApi::savePaymentPassword($data);
        $log = [
            'type'=>"Update Customer Reset Payment Password",
            'result'=>!empty($res["msg"])?$res["msg"]:'',
            'remarks'=>'用户ID：'.$data['customer_id']
        ];
        $log = AdminLog($log);
        //记录日志
        AdminInsert(self::public_log,$log);
        return $res;
    }
}