<?php
namespace app\admin\controller;

use app\admin\dxcommon\CommonLib;
use app\admin\model\WindControlForOldModel;
use think\View;
use think\Controller;
use think\Db;
use think\Session;

use app\admin\dxcommon\Email;
use app\admin\dxcommon\BaseApi;
use think\Log;
use app\admin\dxcommon\ExcelTool;
use app\admin\model\WindControlModel;
use app\admin\services\WindControlService;
/**
 * Add by:wang
 * AddTime:2019-03-23
 */
class WindControl extends Action
{
    const wind_control_special_list = 'wind_control_special_list';
    const wind_control_special_address = 'wind_control_special_address';
    const wind_control_special_country = 'wind_control_special_country';
    const wind_control_special_city = 'wind_control_special_city';
    const wind_control_special_afterwards = 'wind_control_special_afterwards';

    const region = 'dx_region';

    private $windService;

	public function __construct(){
       Action::__construct();
       define('AFFILIATE_ORDER', 'affiliate_order');//mysql数据表
       define('MOGOMODB_P_CLASS', 'dx_product_class');//mysql数据表

       $this->windService = new WindControlService();
    }
    /**
     * 特殊名单列表
     * [ReportStatistics description]
     */
    public function SpecialList(){
        $data = ParameterCheck(input());
        $where = [];

        $WindControlType = publicConfig(S_CONFIG,'WindControlType');
        $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);

        $page_size = $data['page_size']  = config('paginate.list_rows');//config('paginate.list_rows')
        if(!empty($data['page'])){
           $page = $data['page'];
        }else{
           $page = 1;
        }
        if(!empty($data['type'])){
             $where['type'] = $data['type'];
        }
        if(!empty($data['list_type'])){
             $where['list_type'] = $data['list_type'];
        }
        if(!empty($data['value'])){
            $preg_email='/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims';
            if(preg_match($preg_email,$data['value'])){
                $where['value'] = aes()->encrypt($data['value']);
            }else{
                $where['value'] = $data['value'];
            }
        }
        if(!empty($data['status'])){
             $where['status'] = $data['status'];
        }
        $SpecialList = WindControlModel::SpecialList($where,$page,$page_size,self::wind_control_special_list);
        if(!empty($SpecialList['list'])){
            foreach ($SpecialList['list'] as $key=>$value){
                //如果是Email,需要解密
                if($value['list_type'] == 'Email'){
                    $SpecialList['list'][$key]['value'] = aes()->decrypt($value['value']);
                }
            }
        }
        $data_page = $where;
        $data_page['page'] = $page;
        $data_page['countPage'] = $SpecialList['countPage'];
        $data_page['page_size'] = $page_size;
        $Page = CountPage($data_page['countPage'],$data_page,'/WindControl/SpecialList');
        $this->assign(['list'=>$SpecialList['list'],'page'=>$Page,'WindControlType'=>$WindControlType,'data'=>$data]);
        return View();
    }
    /**
     * 特殊用户黑白名单
     * [ReportStatistics description]
     */
    public function SpecialList_add(){
          $list = [];
          if($data = request()->post()){
              if(empty($data['value'])){
                  echo json_encode(array('code'=>100,'result'=>'值不能为空'),true);
                  exit;
              }
              if(empty($data['type'])){
                  echo json_encode(array('code'=>100,'result'=>'类型不能为空'),true);
                  exit;
              }
              if(empty($data['list_type'])){
                  echo json_encode(array('code'=>100,'result'=>'特殊类型不能为空'),true);
                  exit;
              }else{
                  if(strtolower($data['list_type']) == 'cicid'){
                     if(is_numeric($data['value'])){
                        $list_type = BaseApi::getCustomerByID($data['value']);
                         if(!empty($list_type['code']) && $list_type['code'] != 200){
                             echo json_encode(array('code'=>100,'result'=>'用户ID不存在'),true);
                             exit;
                         }else if(empty($list_type['code'])){
                             echo json_encode(array('code'=>100,'result'=>'调用验证出异常'),true);
                             exit;
                         }
                     }else{
                        echo json_encode(array('code'=>100,'result'=>'CIC数据错误'),true);
                        exit;
                     }
                  }
                  if(strtolower($data['list_type']) == 'email'){
                         $preg_email='/^[a-zA-Z0-9]+([-_.][a-zA-Z0-9]+)*@([a-zA-Z0-9]+[-.])+([a-z]{2,5})$/ims';
                         if(!preg_match($preg_email,$data['value'])){
                             echo json_encode(array('code'=>100,'result'=>'邮件格式有误'),true);
                             exit;
                         }else{
                             $data['value'] = aes()->encrypt($data['value']);
                         }
                  }
                  if(strtolower($data['list_type']) == 'ip'){
                        $ip = preg_match('/^((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1 -9]?\d))))$/', $data['value']);
                        if(empty( $data['value']) || !$ip) {
                            echo json_encode(array('code'=>100,'result'=>'ip地址有误'),true);
                            exit;
                        }
                        $list_type['code'] = 200;
                  }
              }

              $where['type'] = $data['type'];
              $where['list_type'] = $data['list_type'];
              $where['value'] = $data['value'];
              $where['status'] = $data['status'];

              $id = !empty($data['id'])?$data['id']:'';
              if(!empty($id)){
                 $where['edit_time'] = time();
                 $where['edit_operator'] = Session::get("username");
              }else{
                 $where['operator'] = Session::get("username");
                 $where['add_time'] = time();
                 date_default_timezone_set('PRC');
                 $where['add_time_date'] = date('Y-m-d H:i:s',time());
              }
              $result = WindControlModel::SpecialList_add($where,self::wind_control_special_list,$id,['value'=>$where['value'],'list_type'=>$where['list_type']]);
              // $result = WindControlModel::SpecialList_add($where,$id);
              if(empty($result)){
                  echo json_encode(array('code'=>100,'result'=>'数据提交失败'),true);
                  exit;
              }else if($result == 2){
                  echo json_encode(array('code'=>100,'result'=>'该数据添加过'),true);
                  exit;
              }
              echo json_encode(array('code'=>200,'result'=>'数据提交成功'),true);
              exit;
          }else{
              $id = input('id');
              $WindControlType = publicConfig(S_CONFIG,'WindControlType');
              $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
              $special_list_data = WindControlModel::getOneSpecialList(['id'=>$id]);
              $this->assign(['WindControlType'=>$WindControlType,'id'=>$id,'list'=>$special_list_data]);
              return View();
          }
    }
     /**
     * 高风险国家管理
     */
    public function HighRiskCountryList(){
            $data = ParameterCheck(input());
            $where = [];
            $WindControlType = publicConfig(S_CONFIG,'WindControlType');
            $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
            $page_size = $data['page_size']  = config('paginate.list_rows');
            if(!empty($data['page'])){
               $page = $data['page'];
            }else{
               $page = 1;
            }

            if(!empty($data['country_code'])){
                 $where['country_code'] = $data['country_code'];
            }
            if(!empty($data['country_name'])){
                 $where['country_name'] = $data['country_name'];
            }
            if(!empty($data['risk_level'])){
                 $where['risk_level'] = $data['risk_level'];
            }
            if(!empty($data['status'])){
                 $where['status'] = $data['status'];
            }
            $SpecialList = WindControlModel::SpecialList($where,$page,$page_size,self::wind_control_special_country);
            $data_page = $where;
            $data_page['page'] = $page;
            $data_page['countPage'] = $SpecialList['countPage'];
            $data_page['page_size'] = $page_size;
            $Page = CountPage($data_page['countPage'],$data_page,'/WindControl/HighRiskCountryList');
            $this->assign(['list'=>$SpecialList['list'],'page'=>$Page,'WindControlType'=>$WindControlType,'data'=>$data]);
            return View();
    }
    /**
     * 特殊用户黑白名单
     * [ReportStatistics description]
     */
    public function AddRiskCountry(){
        if($data = request()->post()){
            if(empty($data['country_code'])){
                echo json_encode(array('code'=>100,'result'=>'国家简码不能为空'),true);
                exit;
            }else{
                $country = AdminFind(self::region,['Code'=>$data['country_code']],2,'Code');
                if(empty($country)){
                     echo json_encode(array('code'=>100,'result'=>'国家简码有误'),true);
                     exit;
                }
            }
            if(empty($data['country_name'])){
                echo json_encode(array('code'=>100,'result'=>'国家名称不能为空'),true);
                exit;
            }
            if(empty($data['risk_level'])){
                echo json_encode(array('code'=>100,'result'=>'风险级别不能为空'),true);
                exit;
            }
            if(empty($data['status'])){
                echo json_encode(array('code'=>100,'result'=>'是否使用必选'),true);
                exit;
            }

            $where['country_code'] = $data['country_code'];
            $where['country_name'] = $data['country_name'];
            $where['risk_level'] = $data['risk_level'];
            $where['status'] = $data['status'];

            $id = !empty($data['id'])?$data['id']:'';
            if(!empty($id)){
                $where['edit_time'] = time();
                $where['edit_operator'] = Session::get("username");
            }else{
                $where['operator'] = Session::get("username");
                $where['add_time'] = time();
                date_default_timezone_set('PRC');
                $where['add_time_date'] = date('Y-m-d H:i:s',time());

            }
            $result = WindControlModel::SpecialList_add($where,self::wind_control_special_country,$id,['country_code'=>$where['country_code']]);
            if(empty($result)){
                echo json_encode(array('code'=>100,'result'=>'数据提交失败'),true);
                exit;
            }else if($result == 2){
                echo json_encode(array('code'=>100,'result'=>'该数据添加过'),true);
                exit;
            }
            echo json_encode(array('code'=>200,'result'=>'数据提交成功'),true);
            exit;
        }else{
            $list = [];
            $data = ParameterCheck(input());
            $WindControlType = publicConfig(S_CONFIG,'WindControlType');
            $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
            //收货国家
            $baseApi = new BaseApi();
            $countryList = $baseApi::getRegionData_AllCountryData();
            $CountryListData = !empty($countryList['data'])?$countryList['data']:'';
            $id = $data['id'];
            if(!empty($id)){
                $list = WindControlModel::getOneSpecialCountry(['id'=>$id]);
            }
            $this->assign(['WindControlType'=>$WindControlType,'id'=>$id,'list'=>$list,'CountryListData'=>$CountryListData]);
            return View();
        }
    }
    /**
     * 展示城市地址
     * [HighRiskCityList description]
     */
    public function HighRiskCityList(){
            $data = ParameterCheck(input());
            $where = [];
            $WindControlType = publicConfig(S_CONFIG,'WindControlType');
            $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
            $page_size = $data['page_size']  = config('paginate.list_rows');
            if(!empty($data['page'])){
               $page = $data['page'];
            }else{
               $page = 1;
            }

            if(!empty($data['city_name'])){
                 $where['city_name'] = $data['city_name'];
            }
            if(!empty($data['risk_level'])){
                 $where['risk_level'] = $data['risk_level'];
            }
            if(!empty($data['status'])){
                 $where['status'] = $data['status'];
            }

            $SpecialList = WindControlModel::SpecialList($where,$page,$page_size,self::wind_control_special_city);
            $data_page = $where;
            $data_page['page'] = $page;
            $data_page['countPage'] = $SpecialList['countPage'];
            $data_page['page_size'] = $page_size;
            $Page = CountPage($data_page['countPage'],$data_page,'/WindControl/HighRiskCityList');
            $this->assign(['list'=>$SpecialList['list'],'page'=>$Page,'WindControlType'=>$WindControlType,'data'=>$data]);
            return View();
    }
    /**
     * 编辑城市地址
     * [AddRiskCity description]
     */
    public function AddRiskCity(){
         if($data = request()->post()){
                 $id = '';
                 $where = [];

                 if(empty($data['city_name'])){
                      echo json_encode(array('code'=>100,'result'=>'城市不能留空'),true);
                      exit;
                 }
                 if(empty($data['risk_level'])){
                      echo json_encode(array('code'=>100,'result'=>'错误级别必须选'),true);
                      exit;
                 }
                 if(empty($data['status'])){
                      echo json_encode(array('code'=>100,'result'=>'是否启用必须选'),true);
                      exit;
                 }

                 $where["risk_level"] = $data['risk_level'];
                 $where["city_name"]  = $data['city_name'];
                 $where["city_name_lower_case"]  = strtolower($data['city_name']);
                 $where["status"] = $data['status'];
                 if(empty($data["id"])){
                     $where['operator'] = Session::get("username");
                     $where["add_time"] = time();
                     date_default_timezone_set('PRC');
                     $where['add_time_date'] = date('Y-m-d H:i:s',time());
                 }else{
                     $where["edit_time"] = time();
                     $where['edit_operator'] = Session::get("username");
                     $id = $data["id"];
                 }
                 $result = WindControlModel::SpecialList_add($where,self::wind_control_special_city,$id,['city_name'=>$where["city_name"]]);
                 if(empty($result)){
                      echo json_encode(array('code'=>100,'result'=>'数据提交失败'),true);
                      exit;
                 }else if($result == 2){
                      echo json_encode(array('code'=>100,'result'=>'该数据添加过'),true);
                      exit;
                 }
                 echo json_encode(array('code'=>200,'result'=>'数据提交成功'),true);
                 exit;

         }else{
                $list = [];
                $data = ParameterCheck(input());
                $WindControlType = publicConfig(S_CONFIG,'WindControlType');
                $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
                $id = $data['id'];
                if(!empty($id)){
                $list = WindControlModel::getOneSpecialCity(['id'=>$id]);
                }
                $this->assign(['WindControlType'=>$WindControlType,'id'=>$id,'list'=>$list]);
                return View();
         }
    }

    /**高风险地址
     * [SpecialAddress description]
     */
    public function SpecialAddress(){
         $where = [];
         $data = ParameterCheck(input());
         $WindControlType = publicConfig(S_CONFIG,'WindControlType');
         $page_size = $data['page_size']  = config('paginate.list_rows');
         if(!empty($data['page'])){
           $page = $data['page'];
         }else{
           $page = 1;
         }
         if(!empty($data['type'])){
             $where['type'] = $data['type'];
         }
         if(!empty($data['street'])){
             $where['street'] = $data['street'];
         }
         if(!empty($data['city'])){
             $where['city'] = $data['city'];
         }
         if(!empty($data['status'])){
             $where['status'] = $data['status'];
         }
         $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
         $SpecialAddress = WindControlModel::SpecialAddress($where,$page,$page_size);
         $data_page['page'] = $page;
         $data_page['countPage'] = $SpecialAddress['countPage'];
         $data_page['page_size'] = $page_size;
         $Page = CountPage($data_page['countPage'],$data_page,'/WindControl/SpecialAddress');
         $this->assign(['list'=>$SpecialAddress['list'],'page'=>$Page,'WindControlType'=>$WindControlType,'data'=>$data]);
         return View();
    }
    /**
     * 编辑风控地址
     * [SpecialAddress_add description]
     */
    public function SpecialAddress_add(){
         if($data = request()->post()){
             $id = '';
             $where = [];
             if(empty($data['type'])){
                  echo json_encode(array('code'=>100,'result'=>'类型必须选'),true);
                  exit;
             }
             if(empty($data['value'])){
                  echo json_encode(array('code'=>100,'result'=>'地址不能留空'),true);
                  exit;
             }
             if(empty($data['city'])){
                  echo json_encode(array('code'=>100,'result'=>'城市不能留空'),true);
                  exit;
             }
             if(empty($data['status'])){
                  echo json_encode(array('code'=>100,'result'=>'是否启用必须选'),true);
                  exit;
             }
             $where["type"] = $data['type'];
             $where["street"] = $data['value'];
             $where["city"] = $data['city'];
             $where["status"] = $data['status'];
             if(empty($data["id"])){
                 $where['operator'] = Session::get("username");
                 $where["add_time"] = time();
                 date_default_timezone_set('PRC');
                 $where['add_time_date'] = date('Y-m-d H:i:s',time());
             }else{
                 $where["edit_time"] = time();
                 $where['edit_operator'] = Session::get("username");
                 $id = $data["id"];
             }
             $result = WindControlModel::SpecialAddress_add($where,$id);
             if(empty($result)){
                  echo json_encode(array('code'=>100,'result'=>'数据提交失败'),true);
                  exit;
             }else if($result == 2){
                  echo json_encode(array('code'=>100,'result'=>'该数据添加过'),true);
                  exit;
             }
             echo json_encode(array('code'=>200,'result'=>'数据提交成功'),true);
             exit;
         }else{
             $list = [];
             $data = ParameterCheck(input());
             $WindControlType = publicConfig(S_CONFIG,'WindControlType');
             $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
             $id = $data['id'];
             if(!empty($id)){
                $list = WindControlModel::getOneSpecialAddress(['id'=>$id]);
             }
             $this->assign(['WindControlType'=>$WindControlType,'id'=>$id,'list'=>$list]);
             return View();
         }
    }
    /**
     * [RiskOrderList description]风控列表
     */
    public function RiskOrderList(){
        $data = [];
        $where = [];
        $Channel = [];
        $ChannelDisbursement = publicConfig(S_CONFIG,'ChannelDisbursement');
        $ChannelDisbursement = htmlspecialchars_decode($ChannelDisbursement["result"]["ConfigValue"]);
        $ChannelDisbursement = explode(';', $ChannelDisbursement);
        if(!empty($ChannelDisbursement)){
            foreach ($ChannelDisbursement as $k => $v) {
                if(!empty($v)){
                      $Channel[] = explode(':', $v);
                }
            }
        }

        //获取所有所有客服名单
        $CustomerServiceList = WindControlModel::CustomerServiceList(['group_id'=>['exp','in(9,12)']]);
        $DealWithStatus=config('DealWithStatus');
        if($data = request()->param()){
              if(!empty($data['Channel'])){
                   $where['PaymentChannel'] = ['exp','in("'.$data['Channel'].'","'.strtolower($data['Channel']).'")'];
              }

              if(isset($data['Status']) && $data['Status']==1){
                $where['DealWithStatus'] = 0;
              }elseif(isset($data['Status']) && $data['Status']==2){
                  $where['DealWithStatus'] = ['neq',0];
              }

              if(isset($data['DealWithStatus']) && $data['DealWithStatus']!=''){
                $where['DealWithStatus'] = $data['DealWithStatus'];
              }

              if(!empty($data['DistributionAdminId'])){
                  $where['DistributionAdminId'] = $data['DistributionAdminId'];
              }
              if(!empty($data['OrderNumber'])){
                $where['OrderNumber'] = $data['OrderNumber'];
              }
              if(!empty($data['TransactionID'])){
                   $where['TransactionID'] = $data['TransactionID'];
              }
              if(!empty($data['AmountUsd_1'])){
                   $where['AmountUsd_1'] = $data['AmountUsd_1'];
              }
              if(!empty($data['AmountUsd_2'])){
                   $where['AmountUsd_2'] = $data['AmountUsd_2'];
              }

              if(!empty($data['startTime']) && !empty($data['endTime'])){
                 $where['AddTime'] =  array('between',strtotime($data['startTime']).','.strtotime($data['endTime']));
              }else if(!empty($data['startTime'])){
                 $where['AddTime'] =  array('egt',strtotime($data['startTime']));
              }else if(!empty($data['startTime'])){
                 $where['AddTime'] =  array('elt',strtotime($data['endTime']));
              }
              if(!empty($data['operatorId'])){
                 $where['OperatorId'] = $data['operatorId'];
              }
              if(isset($data['allot_status']) && $data['allot_status']!=null ){
                  $where['AllotStatus'] = $data['allot_status'];
              }
              if(isset($data['IsEmail']) && $data['IsEmail']!=null){
                  $where['IsEmail'] = $data['IsEmail'];
              }
              
        }
        //过滤空值
        foreach($data as $key=>$item){
            if($item===''){
                unset($data[$key]);
            }
        }

        $data['page']=!empty($data['page'])?$data['page']:1;
        $data['page_size'] = 50;
        $SiteID=Config('SiteID');
        $where['SiteID']=['in',$SiteID];
        $where['Code']=['neq','200'];
        if(!empty($data['is_export']) && $data['is_export'] == 1){
            $data['page_size'] = 100000000;
            $where['page_data']=json_encode($data);//分页数据
            //不通过原因,1：黑名单拒绝，2:客户要求取消 3未传资料_高风险订单4、已传资料_高风险订单5、重复订单6、欺诈客户、7其他'
            $NoPassReasonData = [1=>"黑名单拒绝",2=>"客户要求取消",3=>"未传资料_高风险订单",4=>"已传资料_高风险订单",5=>"重复订单",6=>"欺诈客户",7=>"其他"];
            $data1 = BaseApi::RiskOrderList($where);
            if(!empty($data1['data']['data'])){
                $export_data = array();
                foreach ($data1['data']['data'] as $key=>$value){
                    $export_data[$key] = $value;
                    $export_data[$key]['IsEmail'] = !empty($value['IsEmail'])?"已认证":"未认证";
                    $export_data[$key]['DealWithStatusStr'] = !empty($value['DealWithStatus'])?"已判定":"未判定";
                    if(empty($value['NoPassReason'])){
                        $export_data[$key]['description'] = $value['pay_channel']?$value['PaymentMethod']:''.'('.$value['Code']?$value['Code']:''.')'.'['.$value['Msg']?$value['Msg']:''.']';
                    }else{
                        $export_data[$key]['description'] = $NoPassReasonData[$value['NoPassReason']];
                    }
                    $export_data[$key]['OperatingTimeStr'] = !empty($value['OperatingTime'])?date("Y-m-d H:i:s",$value['OperatingTime']):"";
                }
            }else{
                $this->error("没有数据");
            }
            $header_data =['OrderNumber'=>'订单号',
                'Amount'=>'金额','CurrencyCode'=>'币种','ShippAddressCountry'=>'国家缩写','ShippAddressCountryName'=>'国家',
                'Operator'=>'操作人','IsEmail'=>'是否认证','DealWithStatusStr'=>'是否判定','description'=>'拒绝原因','OperatingTimeStr'=>'拒绝时间'
            ];
            $tool = new ExcelTool();
            if(!empty($export_data)){
                $tool ->export('风控订单数据'.time(),$header_data,$export_data,'sheet1');
            }else{
                echo '没查到数据';
                exit;
            }
            /*$header_data =['Id'=>'风控ID','CustomerID'=>'客户ID','OrderNumber'=>'订单号',
                'AmountUsd'=>'金额($)','TransactionID'=>'PmtTxnID','description'=>'Risk Description','AddTimeStr'=>'提交时间',
                'country_code'=>'分配状态','Operator'=>'操作人','pay_time'=>'认证邮件','txn_time'=>'判定','txn_time'=>'判定结果'
            ];*/
        }
        $where['page_data']=json_encode($data);//分页数据
        $data1 = BaseApi::RiskOrderList($where);
        if(!empty($data1['data'])){
            $list =$data1['data'];
        }else{
            $list=[];
        }

     //   var_dump($data['page']);die();
    //   if(!empty($data['data'])){
    //
    //        }

        $this->assign(['list'=>!empty($list['data'])?$list['data']:[],'page'=>!empty($data1['page'])?$data1['page']:[],
            'Channel'=>$Channel,'CustomerServiceList'=>$CustomerServiceList,'data'=>$data,'DealWithStatus'=>$DealWithStatus]);
        return View();
//        $list=   WindControlModel::RiskOrderList($where);
//
//        $this->assign(['list'=>$list->items(),'page'=>$list->render(),'Channel'=>$Channel,'CustomerServiceList'=>$CustomerServiceList,'data'=>$data]);
//        return View();
    }

    /*
     * 分配风控售后
     * */
    public function DistributionSpecialAfterwards(){
        $query_data = request()->post();
        $ids = isset($query_data['Ids'])?$query_data['Ids']:'';
        $distribution_admin_id = input("DistributionAdminId",'');
        $distribution_admin = input("DistributionAdmin",'');
        if(!empty($ids) && !empty($distribution_admin_id) && !empty($distribution_admin)){
            $param_where['Id'] = ['IN',$ids];
            $param_data['DistributionAdminId'] = $distribution_admin_id;
            $param_data['DistributionAdmin'] = $distribution_admin;
            $param_data['AllotStatus'] = 1;
            $param_data['DistributionTime'] = time();
            $update_res = WindControlModel::DistributionSpecialAfterwards($param_where,$param_data);
            if(!$update_res){
                return ['code'=>1002,'msg'=>'分配失败！'];
            }else{
                return ['code'=>200,'msg'=>'分配成功！'];
            }
        }else{
            if(!$ids){
                return ['code'=>1001,'msg'=>'请勾选数据'];
            }
            if(!$distribution_admin_id){
                return ['code'=>1001,'msg'=>'请选择分配人员'];
            }
        }
    }

    /**
     * PaymentProcessing订单查询(只获取进入风控后，处理发生异常的订单)
     * [PaymentProcessing description]
     */
    public function PaymentProcessing(){
        $where = [];
        $where['DealWithStatus'] = 1;//获取一次状态
        $where['code'] = array('neq',200);
        if($data = request()->post()){
            if(!empty($data['Channel'])){
                   $where['PaymentChannel'] = strtolower($data['Channel']);
            }
            if(!empty($data['OrderNumber'])){
                   $where['OrderNumber'] = strtolower($data['OrderNumber']);
            }
            if(!empty($data['TransactionID'])){
                   $where['TransactionID'] = strtolower($data['TransactionID']);
            }
            if(!empty($data['deal_with_status'])){
                   $where['DealWithStatus'] = strtolower($data['deal_with_status']);
            }
            if(!empty($data['startTime']) && !empty($data['endTime'])){
               $where['operating_time'] =  array('between',strtotime($data['startTime']).','.strtotime($data['endTime']));
            }else if(!empty($data['startTime'])){
               $where['OperatingTime'] =  array('egt',strtotime($data['startTime']));
            }else if(!empty($data['startTime'])){
               $where['OperatingTime'] =  array('elt',strtotime($data['endTime']));
            }
        }

        $WindControlType = publicConfig(S_CONFIG,'WindControlType');
        //获取支付 对应渠道
        $Channel = $this->dictionariesQuery('ChannelDisbursement');
        $WindControlType = json_decode(htmlspecialchars_decode($WindControlType["result"]["ConfigValue"]),true);
        $list = WindControlModel::RiskOrderList($where);
        $this->assign(['list'=>$list->items(),'page'=>$list->render(),'Channel'=>$Channel,'data'=>$data]);
        return View();
    }

    /**
     * 成功订单查询
     * [OrderSucceeded description]
     */
    public function OrderSucceeded(){
        $where = [];
        $data = [];
        $where['code'] = 200;//获取一次状态
        $where['DealWithStatus'] = 2;
        if($data = ParameterCheck(input())){

            if(!empty($data['OrderNumber'])){
                 $where['OrderNumber'] = $data['OrderNumber'];
            }
            if(!empty($data['CustomerID'])){
                 $where['CustomerID'] = $data['CustomerID'];
            }
            if(!empty($data['TransactionID'])){
                 $where['TransactionID'] = $data['TransactionID'];
            }
            if(!empty($data['ThirdPartyTxnID'])){
                 $where['ThirdPartyTxnID'] = $data['ThirdPartyTxnID'];
            }
            if(!empty($data['CustomerIP'])){
                 $where['CustomerIP'] = $data['CustomerIP'];
            }
            if(!empty($data['deal_with_status'])){
                 $where['DealWithStatus'] = $data['deal_with_status'];
            }
            if(!empty($data['ShippAddressCountry'])){
                 $where['ShippAddressCountry'] = $data['ShippAddressCountry'];
            }
            if(!empty($data['ShippAddressState'])){
                 $where['ShippAddressState'] = $data['ShippAddressState'];
            }
            if(!empty($data['ShippAddressCity'])){
                 $where['ShippAddressCity'] = $data['ShippAddressCity'];
            }
            if(!empty($data['startTime']) && !empty($data['endTime'])){
                 $where['OperatingTime'] =  array('between',strtotime($data['startTime']).','.strtotime($data['endTime']));
            }else if(!empty($data['startTime'])){
                 $where['OperatingTime'] =  array('egt',strtotime($data['startTime']));
            }else if(!empty($data['startTime'])){
                 $where['OperatingTime'] =  array('elt',strtotime($data['endTime']));
            }
            if(!empty($data['currency'])){
                 $where['CurrencyCode'] = $data['currency'];
            }
            if(!empty($data['captured_amount_usd_1']) && !empty($data['captured_amount_usd_2'])){
                 $where['Amount'] = array(array('egt',$data['captured_amount_usd_1']),array('elt',$data['captured_amount_usd_2']));
            }else if(!empty($data['captured_amount_usd_1'])){
                 $where['Amount'] = array('egt',strtotime($data['captured_amount_usd_1']));
            }else if(!empty($data['captured_amount_usd_2'])){
                 $where['Amount'] = array('elt',strtotime($data['captured_amount_usd_2']));
            }
        }
        // dump($where);
        $getCurrencyList = BaseApi::getCurrencyList();//dump($getCurrencyList);
        $list = WindControlModel::RiskOrderList($where,$data);
        $this->assign([
          'list'=>$list->items(),
          'page'=>$list->render(),
          'data'=>$data,
          'getCurrencyList'=>$getCurrencyList["data"]
          ]);
        return View();
    }
    /**
     * 人工风控拒绝订单
     * [OrderReject description]
     */
    public function OrderReject(){
       $where = [];
       $data = [];
       $ChannelDisbursement = $this->dictionariesQuery('ChannelDisbursement');

       if($data = ParameterCheck(input())){
             if(!empty($data['PaymentChannel'])){
                 $where['A.PaymentChannel'] =  strtolower($data['PaymentChannel']);
             }
             if(!empty($data['startTime']) && !empty($data['endTime'])){
                 $where['A.operating_time'] =  array('between',strtotime($data['startTime']).','.strtotime($data['endTime']));
             }else if(!empty($data['startTime'])){
                 $where['A.operating_time'] =  array('egt',strtotime($data['startTime']));
             }else if(!empty($data['startTime'])){
                 $where['A.operating_time'] =  array('elt',strtotime($data['endTime']));
             }
       }
       $data['page_size']  = config('paginate.list_rows');
       $where['A.deal_with_status'] = 3;//人工确定拒绝状态
       $list = WindControlModel::ManualProcessing($where,$data);
       $order_afterwards = $list->items();
       $this->assign([
        'list'=>$order_afterwards,
        'page'=>$list->render(),
        'data'=>$data,
        'ChannelDisbursement'=>$ChannelDisbursement,
        ]);
       return View();
    }
    /**
     * 历史订单信息综合搜索
     * [SearchFor description]
     */
    public function SearchFor(){

        $where = [];
        $list = [];
        $data = [];
        $msg = '';
        if($data = ParameterCheck(input())){
             if(!empty($data["search_condition"]) && !empty($data["search_condition_value"])){
                  //IP
                  if($data["search_condition"] == 'Same IP'){
                      $where['CustomerIP'] = $data["search_condition_value"];
                  }
                  // if($data["search_condition"] == 'Same Card Number'){

                  // }
                  if($data["search_condition"] == 'Same Billing Phone'){
                      $where['BillingAddressPhone'] = $data["search_condition_value"];
                  }
                  // if($data["search_condition"] == 'Same Billing Email'){

                  // }
                  // if($data["search_condition"] == 'Same Billing Address'){

                  // }
                  if($data["search_condition"] == 'Same Shipping Phone'){
                      $where['ShippAddressPhone'] = $data["search_condition_value"];
                  }
                  if($data["search_condition"] == 'Same Shipping Email'){
                      $where['ShippAddressEmail'] = $data["search_condition_value"];
                  }
                  if($data["search_condition"] == 'Same Shipping Address'){
                      $where['ShippAddressStreet1'] = $data["search_condition_value"];
                  }
                  // if($data["search_condition"] == 'Same Card Holder'){

                  // }
                  // if($data["search_condition"] == 'Same Consignee'){

                  // }
             }else if(empty($data["search_condition"]) && empty($data["search_condition_value"])){
             }else{
                 $msg = '搜索条件要么都填不要都不填';
             }
             if(!empty($data['startTime']) && !empty($data['endTime'])){
                 $where['add_time'] =  array('between',strtotime($data['startTime']).','.strtotime($data['endTime']));
             }else if(!empty($data['startTime'])){
                 $where['add_time'] =  array('egt',strtotime($data['startTime']));
             }else if(!empty($data['startTime'])){
                 $where['add_time'] =  array('elt',strtotime($data['endTime']));
             }
        }
        $order_afterwards = [];
        $page = [];
        if(!empty($where)){
            $list = WindControlModel::RiskOrderList($where,$data);
            $order_afterwards = $list->items();
            $page = $list->render();
           /* dump($list);*/
        }

        $this->assign([
        'list'=>$order_afterwards,
        'page'=>$page,
        'data'=>$data
        ]);
        return View();
    }
    /**
     * Decline统计
     * [DeclineStatistics description]
     */
    public function DeclineStatistics(){
        return View();
    }
    /**
     * 检测统计
     * [DetectionStatistics description]
     */
    public function DetectionStatistics(){

        return View();
    }
    /**
     *  Decline原因类型统计
     * [DeclineTypeStatistics description]
     */
    public function DeclineTypeStatistics(){
       $ChannelDisbursement = $this->dictionariesQuery('ChannelDisbursement');dump($ChannelDisbursement);
       if($data = ParameterCheck(input())){
            dump($data);
       }
       //某段时间按天遍历，得到每个天数
       // for($date=$begdate;$date <=$enddate;$date+=86400){
       //      echo date("Y-m-d",$date)."<br>"; //打印
       // }

       // select S.syctime_day,
       //   sum(case when S.o_source = 'CDE' then 1 else 0 end) as 'CDE',
       //   sum(case when S.o_source = 'SDE' then 1 else 0 end) as 'SDE',
       //   sum(case when S.o_source = 'PDE' then 1 else 0 end) as 'PDE',
       //   sum(case when S.o_source = 'CSE' then 1 else 0 end) as 'CSE',
       //   sum(case when S.o_source = 'SSE' then 1 else 0 end) as 'SSE'
       // from statistic_order S where S.syctime_day > '2015-05-01' and S.syctime_day < '2016-08-01'
       // GROUP BY S.syctime_day order by S.syctime_day asc;


       $list = Db::query("select SUM(CASE code WHEN 200 THEN 1 ELSE 0 END) '星期一' from dx_".self::wind_control_special_afterwards." where 1");
        dump($list);
       $this->assign([
        'ChannelDisbursement'=>$ChannelDisbursement
       ]);
       return View();
    }

    /**
     *  风控处理绩效统计
     */
    public function PerformanceStatistics(){
        $channel_list = ['egp','paypal','asiabill','dlocal'];
        $this->assign(['Channel'=>$channel_list]);

        $params     = request()->post();

        $channel    = !empty($params['channel'])?$params['channel']:'';
        $startTime  = !empty($params['startTime'])?$params['startTime']:'';
        $endTime    = !empty($params['endTime'])?$params['endTime']:'';
        $isExport   = !empty($params['is_export'])?$params['is_export']:0;

        if( empty($channel) ){
            return View();
        }

        if( empty($channel)&&empty($startTime)&&empty($endTime) ){
            return View();
        }
        
        $windData = $this->windService->getRiskData($channel,$startTime,$endTime);

        if( $isExport ){
            $this->windService->export($windData,$channel,$startTime,$endTime);
        }

        $this->assign(['windData'=>$windData]);
        
        return View();
    }
    /**
     *  交易情况统计
     */
    public function Statistics(){
      return View();
    }
    /**
     *  CRC处理情况统计
     */
    public function CRCProcessingStatistics(){
       return View();
    }
    /**
     *  风控订单详情
     */
    public function WindControlOrderDetails(){
       $input=$data = ParameterCheck(input());
       $aes =  aes();
       $list = [];
       $UserDetails = [];
       $OrserDetails = [];
       $OrderStatusChange = [];
       $OrderStatus = [];
       $Transaction = [];
       $OrderProduct = [];
       $WindControlOrderLog = [];
       $ThirdPartyResults = [];
       $RiskControlCertificate = [];
       $CustomerID = '';
       $RawData = [];
       $ChildOrder = [];
        $OrderNumber= $data['OrderNumber'];
        if(!empty($data['OrderNumber'])){
           //$WindControlOrder = WindControlModel::WindControlOrderDetails(['OrderNumber'=>$OrderNumber]);
           $data = BaseApi::WindControlOrderJoin(['OrderNumber'=>$OrderNumber]);
           if(!empty($data['data'])){
               foreach($data['data'] as &$item){
                   if($item['ThirdPartyRiskStatus']==2){
                       $item['ThirdPartyRiskStatus']='进入第三方风控';
                   }elseif($item['ThirdPartyRiskStatus']==1){
                       $item['ThirdPartyRiskStatus']='正常';
                   }else{
                       $item['ThirdPartyRiskStatus']='无数据';
                   }
                   //账单国家
                   if(!empty($item['BillingAddressCountry'])){
                       $item['BillingAddressCountryName']=$this->getCountryName($item['BillingAddressCountry']);
                   }
                   if(!empty($item['BillingAddressPhone'])){
                       $item['BillingAddressPhone']= $aes->decrypt($item['BillingAddressPhone'],'Customer','EmailUserName');//解密邮件前缀
                   }
                   if(!empty($item['BillingAddressEmail'])){
                       $item['BillingAddressEmail']= $aes->decrypt($item['BillingAddressEmail'],'Customer','EmailUserName');//解密邮件前缀
                   }

               }
               $WindControlOrder=$data['data'];
           }else{
               $WindControlOrder=[];
           }
            //var_dump($WindControlOrder);die();
           $list = $WindControlOrder[0];//获取他的基本信息

           //如果paypal则需要获取以色列风控结果dx_wind_control_special_third_party_results

           if($list['PaymentChannel'] == 'paypal'){
                   //$ThirdPartyResults = WindControlModel::ThirdPartyResults(['AfterwardsId'=>$list['Id']]);
                   $data = BaseApi::ThirdPartyResults(['AfterwardsId'=>$list['Id']]);
                   $ThirdPartyResults=!empty($data['data'])?$data['data']:[];
                   if(!empty($ThirdPartyResults['RawData'])){
                        $RawData = json_decode($ThirdPartyResults['RawData'],true);
                        $ThirdPartyResults['RawData'] = end($RawData['content']['resource']);
                   }
           }

           if(!empty($list)){
                $data = BaseApi::WindControlOrderLog(['CustomerID'=>$list['CustomerID']]);

                $WindControlOrderLog=!empty($data['data'])?$data['data']:[];
                //通过接口获取该用户的相关信息
               //var_dump(['CustomerID'=>$list['CustomerID'],'OrderNumber'=>$OrderNumber]);
                $OrderDetails = BaseApi::WindControlOrderDetails(['CustomerID'=>$list['CustomerID'],'OrderNumber'=>$OrderNumber]);

                if(!empty($OrderDetails['code']) && $OrderDetails['code'] == 200){
                      $UserDetails  = $OrderDetails['UserDetails'];
                      $OrserDetails = $OrderDetails['OrserDetails'];
                      $OrderStatusChange = $OrderDetails['OrderStatusChange'];
                      $order_status = $OrderDetails['order_status'];
                      $Transaction  = $OrderDetails['Transaction'];
                      $OrderProduct = $OrderDetails['OrderProduct'];
                      $HistoricalOrder = !empty($OrderDetails['HistoricalOrder'])?$OrderDetails['HistoricalOrder']:[];
                      $ChildOrder = $OrderDetails['ChildOrder'];
                      $Blacklist = $OrderDetails['Blacklist'];
                      foreach ($order_status as $k => $v) {
                           $OrderStatus[$v['code']] = $v['en_name'];
                      }

                      if(!empty($UserDetails)){
                            if(!empty($UserDetails['EmailUserName'])){
                                $EmailUserName = $aes->decrypt($UserDetails['EmailUserName'],'Customer','EmailUserName');//解密邮件前缀
                                $UserDetails['EmailUserName'] = $EmailUserName.'@'.$UserDetails["EmailDomainName"];
                            }
                      }
                }
                // dump($list);
           }

           //所有订单号，包括所有子订单号及主订单号
            $OrderNumbers = [$OrderNumber];
            if(!empty($ChildOrder)){
                foreach ($ChildOrder as $value) {
                    $OrderNumbers[] = $value['order_number'];
                }
            }
            $RiskControlCertificate = WindControlModel::getRiskControlCertificate($OrderNumbers);
           //风控凭证 ??有问题 暂时这样
           //$RiskControlCertificate = WindControlModel::RiskControlCertificate(['OrderMasterNumber'=>$OrderNumber]);
           if(!empty($RiskControlCertificate)){
               foreach ((array)$RiskControlCertificate as $key => $value) {
                   if(!empty($value['enclosure'])){
                       $RiskControlCertificate[$key]['enclosure'] = json_decode(htmlspecialchars_decode($value['enclosure']));
                   }
               }
           }

       }

        //Decline记录(Top20)
        $where2['CustomerID']=$list['CustomerID'];
        $where2['Code']=['neq',200];
        $Decline = BaseApi::WindControlOrderList($where2);
        //var_dump($Decline);
        if(!empty($Decline['data'])){
            $DeclineList=$Decline['data'];
        }

        if(!empty($UserDetails['CountryCode'])){
            $UserDetails['CountryCode']=$this->getCountryName($UserDetails['CountryCode']);
        }

        $TransactionID = isset($list['TransactionID'])?$list['TransactionID']:0;
        $PaymentChannel = isset($list['PaymentChannel'])?$list['PaymentChannel']:'';
        $user_status_data = [-1=>"匿名账户不允许激活",0=>"未激活",1=>"正常",-3=>"账户不存在",10=>"未指定",20=>"禁用",21=>"禁用"];
        $this->assign([
        'data'=>$input,
        'list'=>$list,//风控事后记录表的第一条数据
        'UserDetails'=>$UserDetails,
        'OrserDetails'=>$OrserDetails,
        'OrderStatusChange'=>$OrderStatusChange,
        'OrderStatus'=>$OrderStatus,
        'OrderProduct'=>$OrderProduct,
        'DeclineList'=>$DeclineList,
        'HistoricalOrder'=>$HistoricalOrder,
        'WindControlOrderLog'=>$WindControlOrderLog,
        'Transaction'=>$Transaction,
        'ThirdPartyResults'=>$ThirdPartyResults,
        'RiskControlCertificate'=>$RiskControlCertificate,
        'ChildOrder'=>$ChildOrder,
        'WindControlOrder'=>$WindControlOrder,
        'TransactionID'=>$TransactionID,
        'PaymentChannel'=>$PaymentChannel,
        'OrderNumber'=>$OrderNumber,
        'ChildOrderNumber'=>!empty($ChildOrder[0]['order_number'])?$ChildOrder[0]['order_number']:$OrderNumber,
        'user_status_data'=>$user_status_data,
        'Blacklist'=>$Blacklist,
        'HistoryInfoUrl'=>url('WindControl/HistoryInfo', 'TransactionID='.$TransactionID.'&OrderNumber='.$OrderNumber.'&TransactionType='.$PaymentChannel),
       ]);
       return View();
    }

    /**
     * 风控银行卡信息
     */
    public function getBrank()
    {
        try{
            $Number = request()->param('Number');
            $data = BaseApi::getBrank(['Number'=>$Number]);
            return json($data);
        }catch(\Exception $e){
            Log::record('风控获取订单列表: '.$e,'error');
            return ['code'=>1000, 'msg'=>$e->getMessage(),'data'=>[]];
        }
    }
    /**
     * 写入进风控日志
     * [logSaveResult description]
     * @return [type] [description]
     */
    public function logSave($data){
           if($data){
                $logSaveResult = BaseApi::logSaveResult($data);
               if(!empty($logSaveResult)&&$logSaveResult['code']==200){
                    return json(array('code'=>200,'result'=>'数据操作成功'));
                }else{
                   return json(array('code'=>1200,'result'=>'数据操作失败'));
               }
           }else{
               return json(array('code'=>1200,'result'=>'数据操作失败'));
           }
    }

    /**
     * 风控判定结果
     * [RiskManage description]
     */
    public function RiskManage(){
        if($data = request()->post()){
            if(empty($data['status']) || empty($data['transactionid']) || empty($data['id']) || empty($data['siteid'])){
                return json(['code'=>100,'result'=>'获取参数发生异常']);
            }
            $where = [];
            $condition = [];
            $wind_control_pop_content = config('wind_control_pop_content');
            $no_pass_season=!empty($data['no_pass_season'])?$data['no_pass_season']:0;
            $decision_interface=!empty($wind_control_pop_content[$no_pass_season])?$wind_control_pop_content[$no_pass_season]:'';
            $where['TransactionSouce'] =  $data['siteid'];
            $where['TransactionId'] = $data['transactionid'];
            if($data['status'] == 1){
                 $where['AuthType'] = 1;
                 $deal_with_status=2;//确定通过

                 $condition['Remarks'] = $where['Desc'] = '通过';
            }else if($data['status'] == 2){
                 $deal_with_status=3;//拒绝
                 $where['AuthType'] = 2;
                 $condition['NoPassReason'] =$no_pass_season;
                 $condition['Remarks'] = $where['Desc'] = !empty($data['remarks'])?$data['remarks']:'不通过';
            }else{
                return json(['code'=>102,'result'=>'status参数发生异常']);
            }
            $where['CustomerIp'] = getIp();
            Log::record('$where->'.json_encode($where));
            $result = BaseApi::RiskManage($where);
            Log::record('$result->'.json_encode($result));
            //获取风控记录详情
            $where2['ID']=$data['id'];
            $afterwards_data = BaseApi::HistoricalWindControlOrders($where2);
            if(!empty($afterwards_data['data'])){
                $afterwards=$afterwards_data['data'];
            }else{
                return json(['code'=>101,'result'=>'风控记录详情获取失败']);
            }
            //相关操作人及结果
            $condition['Operator']       = Session::get('username');
            $condition['OperatorId']     = Session::get('userid');
            $condition['OperatingTime'] = time();
            if(!empty($result['code']) && $result['code'] == 200 && (in_array($result['data']['status'],['success','pending']))){
                $condition['DealWithStatus'] = $deal_with_status;
                //$res = WindControlModel::RiskManage($condition,$data['id']);
                Log::record('风控数据修改'.$data['id'].json_encode($condition));
                if($data['status'] == 1){
                    $Desc='通过';
                }elseif($data['status'] == 2){
                    $Desc='不通过';
                }else{
                    $Desc='状态异常';
                }
                $res = BaseApi::RiskManageSave($condition,$data['id']);
                Log::record('风控数据返回结果'.$data['id'].json_encode($res));
                $da9=['code'=>200,'result'=>'数据提交成功'];
            } else {
                $Desc='异常';
                 $condition['DealWithStatus'] = 1;//异常
                 $res = BaseApi::RiskManageSave($condition,$data['id']);
                $da9=['code'=>1001,'result'=>$result['msg']];
            }
            $remarks=!empty($data['remarks'])?$data['remarks']:'';
            $Remarks='ThirdPartyTxnID:'.$afterwards['ThirdPartyTxnID'].','.'TransactionID:'.$afterwards['TransactionID'].','
                .'涉及金额:'.($afterwards['CurrencyCode']).$afterwards['Amount'].','
                .'判定结果:'.$Desc.','
                .'备注:'.$remarks.','
                .'原因:'.$decision_interface;
            //风控日志记录
            $data2['OrderNumber'] = $data['OrderNumber'];
            $data2['CustomerID'] = $data['CustomerID'];
            $data2['ProcessingStaff'] = Session::get('username');
            $data2['Description'] = $Remarks;//日志描述
            $data2['PaymentTypes'] = $data['transaction_channel'];
            $data2['RecordType'] = '判定动作';
            $data2['AddTime'] = time();
            $res3=$this->logSave($data2);
            //获取用户信息
            $CustomerDetails = BaseApi::getCustomerByID($data['CustomerID']);
            if(!empty($CustomerDetails['data'])){
                $RiskControlCertificate = WindControlModel::RiskControlCertificate(['OrderMasterNumber'=>$data['OrderNumber']]);
                if($data['status'] == 1){//风控判定通过
                    if(!empty($RiskControlCertificate)){//已上传风控凭证
                        $RiskControlCertificateId = array_column($RiskControlCertificate,'id');
                       $send_email_res = $this->sendEmailByTplId(16,$CustomerDetails['data']['email'],$CustomerDetails['data']['UserName'],['order_number'=>$data['OrderNumber']]);
                       if($send_email_res){
                           $update_where['id'] = ['in',$RiskControlCertificateId];
                           $update_data['report_status'] = 3;
                           $update_data['operator']       = Session::get('username');
                           $update_data['operator_id']     = Session::get('userid');
                           $update_data['edit_time'] = time();
                           $UpdateControlCertificateRes = WindControlModel::UpdateControlCertificate($update_where,$update_data);
                           if(!$UpdateControlCertificateRes){
                               Log::record('风控判定已上传资料通过，修改风控凭证失败，where_data:'.json_encode($update_where));
                           }
                       }
                    }else{
                        $this->sendEmailByTplId(17,$CustomerDetails['data']['email'],$CustomerDetails['data']['UserName'],['order_number'=>$data['OrderNumber']]);
                    }
                }elseif($data['status'] == 2){//不通过
                    if(!empty($no_pass_season) && ($no_pass_season == 3 || $no_pass_season == 4)){//已上传风控凭证
                        if(!empty($RiskControlCertificate)){
                            $RiskControlCertificateId = array_column($RiskControlCertificate,'id');
                            $update_where['id'] = ['in',$RiskControlCertificateId];
                            $update_data['report_status'] = 4;
                            $update_data['operator']       = Session::get('username');
                            $update_data['operator_id']     = Session::get('userid');
                            $update_data['edit_time'] = time();
                            $UpdateControlCertificateRes = WindControlModel::UpdateControlCertificate($update_where,$update_data);
                            if(!$UpdateControlCertificateRes){
                                Log::record('风控判定已上传资料拒绝，修改风控凭证失败，where_data:'.json_encode($update_where));
                            }
                        }
                        $this->sendEmailByTplId(18,$CustomerDetails['data']['email'],$CustomerDetails['data']['UserName'],['order_number'=>$data['OrderNumber']]);
                    }else if(!empty($no_pass_season) && $no_pass_season == 2){//客户要求取消
                        $this->sendEmailByTplId(19,$CustomerDetails['data']['email'],$CustomerDetails['data']['UserName'],['order_number'=>$data['OrderNumber']]);
                    }
                }
            }
            //发送邮件
            return json($da9);
        }
    }

    /**
     * 写入进风控日志
     * [logSaveResult description]
     * @return [type] [description]
     */
    public function logSaveResult(){
        if($data = request()->post()){
            $where = [];
            if(!empty($data['OrderNumber'])){
                $where['OrderNumber'] = $data['OrderNumber'];
            }
            if(!empty($data['CustomerID'])){
                $where['CustomerID'] = $data['CustomerID'];
            }
            if(!empty($data['txtDesc'])){
                $where['Description'] = $data['txtDesc'];
            }
            if(!empty($data['ucContactProcess_ddlContactType'])){
                $where['ContactInformation'] = $data['ucContactProcess_ddlContactType'];
            }
            if(!empty($data['ucContactProcess_ddlPaymentMethod'])){
                $where['PaymentTypes'] = $data['ucContactProcess_ddlPaymentMethod'];
            }
            if(!empty($data['ucContactProcess_cblAuthTypes'])){
                $where['AuthenticationType'] = rtrim($data['ucContactProcess_cblAuthTypes'],'|');
            }
            // dump($where);exit;
            if(!empty($data['ucContactProcess_rblAuthResult'])){
                $where['CertificationResult'] = $data['ucContactProcess_rblAuthResult'];
            }

            $where['RecordType'] = '联系过程';
            $where['ProcessingStaff'] = Session::get('username');
            $where['AddTime'] = time();
            $where['Description']=$where['ContactInformation'].':'.$where['Description'];
            $logSaveResult = BaseApi::logSaveResult($where);
            $condition['IsEmail']=1;
            $res = BaseApi::RiskManageSaveOrder($condition,$data['OrderNumber']);
            if(!empty($logSaveResult['code'])&&$logSaveResult['code']==200){
                echo json_encode(array('code'=>200,'result'=>'数据操作成功'));
                exit;
            }
        }
        echo json_encode(array('code'=>100,'result'=>'数据操作失败'));
    }

    public function test(){
        $condition['Remarks'] = $where['Desc'] = '通过';
        $condition['DealWithStatus'] =2;
        $condition['Operator']       = 'admin';
        $condition['OperatorId']     = 1;
        $condition['OperatingTime'] = time();
        $data['id']=33;
        $res = BaseApi::RiskManageSave($condition,$data['id']);
        var_dump($res);
        Log::record('风控数据返回结果'.$data['id'].json_encode($res));
    }

    /**
     * 历史信息页面
     * @return \think\response\View
     */
    public function HistoryInfo(){
        $time = time();
        $where_old = [
            'SearchType'=>'',
            'SingleValue'=>'null',
            'IsClearText'=>'null',
            'ClearCardNumberHash'=>'null',
            'BinCode'=>'null',
            'Last4Dig'=>'null',
            'CountryCode'=>'null',
            'City4Check'=>'null',
            'State4Check'=>'null',
            'Street4Check'=>'null',
            'StartTime'=>'',
            'EndTime'=>'',
            'PageSize'=>1000,
            'PageIndex'=>1,
        ];
        $params = input();
        $transactionID = $params['TransactionID'];
        $page = isset($params['page'])?$params['page']:1;
        $pageSize = 10;
        $orderNumber = $params['OrderNumber'];
        $transactionType = $params['TransactionType'];
        $radioType = isset($params['RadioType'])?$params['RadioType']:'rdIP';
        $startTime = isset($params['OrderstartTime']) && !empty($params['OrderstartTime'])?$params['OrderstartTime']:date('Y-m-d', strtotime('-2month'));
        $endTime = isset($params['OrderEndTime']) && !empty($params['OrderEndTime'])?$params['OrderEndTime']:date('Y-m-d', strtotime('+1day'));

        //获取单选数据
        $resData = BaseApi::WindControlOrderList(['OrderNumber'=>$orderNumber, 'TransactionID'=>$transactionID,'PaymentChannel'=>$transactionType]);
        $data = isset($resData['data'][0])?$resData['data'][0]:[];
        Log::record('HistoryInfo'.json_encode($resData).json_encode($data));
        if (
            isset($data['AddTime'])
            && $data['AddTime'] <= strtotime($startTime)
        ){
            $startTime = date('Y-m-d', $data['AddTime']);
        }

        $where_old['StartTime'] = $startTime;
        $where_old['EndTime'] = $endTime;
        /********** 获取列表数据 start ************/

        $where['SiteID'] = ['in',Config('SiteID')];
        $where['StartTime'] = strtotime($startTime);
        $where['EndTime'] = strtotime($endTime);
        $where['page'] = $page;
        $where['page_size'] = $pageSize;

        switch ($radioType){
            case 'rdIP':
                $where['CustomerIP'] = $data['CustomerIP'];

                $where_old['SearchType'] = 'IP';
                $where_old['SingleValue'] = $data['CustomerIP'];
                break;
            case 'rdCardNumber': //TODO 信用卡号，没有
                break;
            case 'rdBillPhone':
                $where['BillingAddressPhone'] = $data['BillingAddressPhone'];

                $where_old['SearchType'] = 'BillingPhone';
                $where_old['SingleValue'] = $data['BillingAddressPhone'];
                break;
            case 'rdBillEmail':
                $where['BillingAddressEmail'] = $data['BillingAddressEmail'];

                $where_old['SearchType'] = 'ShippingEmail';
                $where_old['SingleValue'] = $data['BillingAddressEmail'];
                break;
            case 'rdBillAddress': //<!--(国家简码+市、区+省份+地址一+地址二)-->
                $where['BillingAddressCountry'] = $data['BillingAddressCountry'];
                $where['BillingAddressCity'] = $data['BillingAddressCity'];
                $where['BillingAddressState'] = $data['BillingAddressState'];
                $where['BillingAddressStreet1'] = $data['BillingAddressStreet1'];
                $where['BillingAddressStreet2'] = $data['BillingAddressStreet2'];

                $where_old['SearchType'] = 'BillingAddress';
                $where_old['CountryCode'] = $data['BillingAddressCountry'];
                $where_old['City4Check'] = $data['BillingAddressCity'];
                $where_old['State4Check'] = $data['BillingAddressState'];
                $where_old['Street4Check'] = $data['BillingAddressStreet1'];
                break;
            case 'rdShipPhone':
                $where['ShippAddressPhone'] = $data['ShippAddressPhone'];

                $where_old['SearchType'] = 'ShippingPhone';
                $where_old['SingleValue'] = $data['ShippAddressPhone'];
                break;
            case 'rdShipEmail':
                $where['ShippAddressEmail'] = $data['ShippAddressEmail'];

                $where_old['SearchType'] = 'ShippingEmail';
                $where_old['SingleValue'] = $data['ShippAddressEmail'];
                break;
            case 'rdShipAddress': //<!--(国家简码+市、区+省份+地址一+地址二)-->
                $where['ShippAddressCountry'] = $data['ShippAddressCountry'];
                $where['ShippAddressCity'] = $data['ShippAddressCity'];
                $where['ShippAddressState'] = $data['ShippAddressState'];
                $where['ShippAddressStreet1'] = $data['ShippAddressStreet1'];
                $where['ShippAddressStreet2'] = $data['ShippAddressStreet2'];

                $where_old['SearchType'] = 'ShippingAddress';
                $where_old['CountryCode'] = $data['ShippAddressCountry'];
                $where_old['City4Check'] = $data['ShippAddressCity'];
                $where_old['State4Check'] = $data['ShippAddressState'];
                $where_old['Street4Check'] = $data['ShippAddressStreet1'];
                break;
            case 'rdCardHolder': //TODO 持卡人 没有

                break;
            case 'rdConsignee': //TODO 没有
                break;
        }

        $p = [];
        $p_where = $where;
        $p_where['TransactionID'] = $transactionID;
        $p_where['OrderNumber'] = $orderNumber;
        $p_where['TransactionType'] = $transactionType;
        $p_where['RadioType'] = $radioType;
        $p_where['OrderstartTime'] = $startTime;
        $p_where['OrderEndTime'] = $endTime;
        $p_where['SiteID'] = Config('SiteID');
        foreach ($p_where as $k=>$v){
            if ($k != 'page' && $k != 'page_size'){
                $p[$k] = $v;
            }
        }
        $where['Path'] = url('WindControl/HistoryInfo', $p, config('default_return_type'), true);

        $list=[];
        if (!empty($data)){
            Log::record('HistoryInfo$dataList$where'.json_encode($where));
            $dataList = BaseApi::RiskOrderListForHistory($where);
            Log::record('HistoryInfo$dataList'.json_encode($dataList));
            if(!empty($dataList['data'])){
                foreach ($dataList['data']['data']['data'] as $k100=>$v100){
                    $dataList['data']['data']['data'][$k100]['JsonData'] = json_encode($v100);
                }
                $list = $dataList['data'];
            }
        }
        /********** 获取列表数据 end ************/

        //////////////////// 风控列表增加旧CRC数据 start ////////////////////
        ///
        $windControlForOldModel = new WindControlForOldModel();

        $dataListOld = $windControlForOldModel->getHistoryInfoDetails($where_old);

       //pr($dataListOld);

        //////////////////// 风控列表增加旧CRC数据 end ////////////////////
        $this->assign([
            'startTime'=>$startTime,
            'currCustomerID'=>isset($data['CustomerID'])?$data['CustomerID']:0,
            'endTime'=>$endTime,
            'data'=>$data,
            'listHisData'=>$list,
            'listHisDataOld'=>$dataListOld,
            'currUrl'=>url('WindControl/HistoryInfo'),
            'go4Url'=>'https://crcforphoenix.tradeglobals.com:2445/RiskControl/TransAnalysisSingle.aspx?TransacrionID='.$transactionID.'&OrderNumber='.$orderNumber.'&Type='.$transactionType,
            'ajaxUrl'=>json_encode([
                'asyncGetHistoryDetail'=>url('WindControl/asyncGetHistoryDetail')
            ]),
        ]);
        return View();
    }

    /**
     * 查看历史信息 - 查看页面
     * @return \think\response\View
     */
    public function asyncGetHistoryDetail(){
        $params = input();
        //来源标识：1-新CRC，2-旧CRC
        $flag = isset($params['flag'])?$params['flag']:1;
        $currencyCode = isset($params['currencyCode'])?$params['currencyCode']:'';

        $searchId = $params['searchId']; //$flag==1，为风控ID；==2时，为旧paymentID
        $data = [];
        if ($flag == 2){
            $commonLib = new CommonLib();
            $queryParams = [
                'QueryTransactionForRisk'=>[
                    'request'=>[
                        'TransactionID'=>$searchId
                    ]
                ]
            ];
            $riskResData = $commonLib->riskProcessService('QueryTransactionForRisk', $queryParams);
            $riskResData = json_decode(json_encode($riskResData), true);

            $riskData = isset($riskResData['QueryTransactionForRiskResult']['RiskTransaction'])?$riskResData['QueryTransactionForRiskResult']['RiskTransaction']:[];
            $data['OrderNumber'] = isset($riskData['RelatedOrderNumber'])?$riskData['RelatedOrderNumber']:'';
            $data['TransactionID'] = isset($riskData['TxnID'])?$riskData['TxnID']:'';
            $data['SiteID'] = '';
            $data['CustomerID'] = isset($riskData['CicID'])?$riskData['CicID']:'';
            $data['CustomerIP'] = isset($riskData['IP'])?$riskData['IP']:'';
            $data['CurrencyCode'] = $currencyCode; //TODO
            $data['OrderType'] = isset($riskData[''])?$riskData['']:'';//TODO
            $data['Amount'] = isset($riskData['Amount'])?$riskData['Amount']:'';
            $data['PaymentChannel'] = isset($riskData['PaymentThirdParty'])?$riskData['PaymentThirdParty']:'';
            $data['PaymentMethod'] = isset($riskData[''])?$riskData['']:'';//TODO
            $data['ThirdPartyTxnID'] = isset($riskData['ThirdPartyTxnID'])?$riskData['ThirdPartyTxnID']:'';
            $data['TxnResult'] = isset($riskData['CurrentTxnStatus'])?$riskData['CurrentTxnStatus']:'';
            $data['IsMvp'] = isset($riskData[''])?$riskData['']:'';//TODO
            $data['ThirdPartyRiskStatus'] = isset($riskData[''])?$riskData['']:'';//TODO
            $data['ThidPartyRiskResult'] = isset($riskData[''])?$riskData['']:'';//TODO
            $data['ShippAddressCountry'] = isset($riskData['ShipAddress']['CountryCode'])?$riskData['ShipAddress']['CountryCode']:'';
            $data['ShippAddressState'] = isset($riskData['ShipAddress']['State'])?$riskData['ShipAddress']['State']:'';
            $data['ShippAddressCity'] = isset($riskData['ShipAddress']['City'])?$riskData['ShipAddress']['City']:'';
            $data['ShippAddressCountryName'] = '';
            $data['ShippAddressEmail'] = isset($riskData['ShipAddress']['Email'])?$riskData['ShipAddress']['Email']:'';
            $data['ShippAddressFirstName'] = isset($riskData['ShipAddress']['FirstName'])?$riskData['ShipAddress']['FirstName']:'';
            $data['ShippAddressLastName'] = isset($riskData['ShipAddress']['LastName'])?$riskData['ShipAddress']['LastName']:'';
            $data['ShippAddressPhone'] = isset($riskData['ShipAddress']['Phone'])?$riskData['ShipAddress']['Phone']:'';
            $data['ShippAddressZipCode'] = isset($riskData['ShipAddress']['PostalCode'])?$riskData['ShipAddress']['PostalCode']:'';
            $data['ShippAddressStreet1'] = isset($riskData['ShipAddress']['Street1'])?$riskData['ShipAddress']['Street1']:'';
            $data['ShippAddressStreet2'] = isset($riskData['ShipAddress']['Street2'])?$riskData['ShipAddress']['Street2']:'';
            $data['ShippAddressRate'] = '';
            $data['BillingAddressCountry'] = isset($riskData['BillAddress']['CountryCode'])?$riskData['BillAddress']['CountryCode']:'';
            $data['BillingAddressState'] = isset($riskData['BillAddress']['State'])?$riskData['BillAddress']['State']:'';
            $data['BillingAddressCity'] = isset($riskData['BillAddress']['City'])?$riskData['BillAddress']['City']:'';
            $data['BillingAddressCountryName'] = '';
            $data['BillingAddressEmail'] = isset($riskData['BillAddress']['Email'])?$riskData['BillAddress']['Email']:'';
            $data['BillingAddressFirstName'] = isset($riskData['BillAddress']['FirstName'])?$riskData['BillAddress']['FirstName']:'';
            $data['BillingAddressLastName'] = isset($riskData['BillAddress']['LastName'])?$riskData['BillAddress']['LastName']:'';
            $data['BillingAddressPhone'] = isset($riskData['BillAddress']['Phone'])?$riskData['BillAddress']['Phone']:'';
            $data['BillingAddressZipCode'] = isset($riskData['BillAddress']['PostalCode'])?$riskData['BillAddress']['PostalCode']:'';
            $data['BillingAddressStreet1'] = isset($riskData['BillAddress']['Street1'])?$riskData['BillAddress']['Street1']:'';
            $data['BillingAddressStreet2'] = isset($riskData['BillAddress']['Street2'])?$riskData['BillAddress']['Street2']:'';
            $data['BillingAddressRate'] = '';
            $data['AddTime'] = '';
            $data['Msg'] = '';
            $data['Code'] = '';
            $data['AllotStatus'] = '';
            $data['DealWithStatus'] = '';
            $data['Operator'] = '';
            $data['OperatorId'] = '';
            $data['Remarks'] = '';
            $data['OperatingTime'] = '';
            $data['OrderStatus'] = '';
            $data['SetTxnCountMax'] = '';
            $data['NoPassReason'] = '';
        }else{
            $resData = BaseApi::WindControlOrderList(['Id'=>$searchId]);
            $data = isset($resData['data'][0])?$resData['data'][0]:[];
        }
        $this->assign([
            'data'=>$data
        ]);
        return View();
    }

    public function getCountryName($BillingAddressCountry){
        //收货国家
        $baseApi = new BaseApi();
        $countryList = $baseApi::getRegionData_AllCountryData();
        $countrydata=$countryList['data'];
        $Code=array_column($countrydata, 'Code');
        $BillingAddressCountry=strtoupper(trim($BillingAddressCountry));
        $found_key = array_search($BillingAddressCountry,$Code);
        $name=!empty($countrydata[$found_key]['Name'])?$countrydata[$found_key]['Name']:'';
        return $name;
    }

    public function test256(){

        try{
            $where_old = [
                'SearchType'=>'IP',
                'SingleValue'=>input('IP'),
                'IsClearText'=>'null',
                'ClearCardNumberHash'=>'null',
                'BinCode'=>'null',
                'Last4Dig'=>'null',
                'CountryCode'=>'null',
                'City4Check'=>'null',
                'State4Check'=>'null',
                'Street4Check'=>'null',
                'StartTime'=>'2010-01-01 00:00:00',
                'EndTime'=>date('Y-m-d H:i:s'),
                'PageSize'=>20,
                'PageIndex'=>1,
            ];
            $windControlForOldModel = new WindControlForOldModel();
            $dataListOld = $windControlForOldModel->getHistoryInfoDetails($where_old);

            print_r($dataListOld);

       //(new WindControlForOldModel())->test();

        }catch (\Exception $e){
            print_r('异常');
            print_r($e->getMessage().','.$e->getFile().','.$e->getLine());
        }

    }

    /*发送认证邮件*/
    public function sendAuthenticationEmail(){
        $param_data = input();
        if(empty($param_data['CustomerID'])){
            return ['code'=>1001,'msg'=>"客户ID错误"];
        }
        $pay_type = input("pay_type");

        if($pay_type != 'paypal' && $pay_type != 'creditcard' && $pay_type != 'creditcard-token' && $pay_type!='dlocalCreditCard'){
            return ['code'=>1001,'msg'=>"现在只支持paypal和信用卡发送验证邮件"];
        }
        $CustomerDetails = BaseApi::getCustomerByID($param_data['CustomerID']);
        if(!empty($CustomerDetails) && $CustomerDetails['code'] == 200){
            if($pay_type == 'creditcard' || $pay_type == 'creditcard-token' || $pay_type=='dlocalCreditCard'){
                $templetValueID = 14;
            }else{
                $templetValueID = 15;
            }
            if(empty($CustomerDetails['data']['email'])){
                return ['code'=>1001,'msg'=>"客户邮箱获取失败"];
            }
            if(empty($param_data['OrderNumber'])){
                return ['code'=>1001,'msg'=>"订单编号获取失败"];
            }
            $send_email_resp = Email::sendEmail($CustomerDetails['data']['email'],$templetValueID, $CustomerDetails['data']['UserName'],[],['order_number'=>$param_data['OrderNumber']]);
            if($send_email_resp){
                return ["code"=>200,"msg"=>"发送成功"];
            }else{
                return ["code"=>1002,"msg"=>"发送失败."];
            }
        }else{
            return ['code'=>1001,'msg'=>"客户信息获取失败"];
        }
    }

    /*根据模板ID发送邮件*/
    public function sendEmailByTplId($templetValueID,$email,$username = '',$title_values=[]){
        $send_email_resp = Email::sendEmail($email,$templetValueID, $username,[],$title_values);
        if($send_email_resp){
            return ["code"=>200,"msg"=>"发送成功"];
        }else{
            return ["code"=>1002,"msg"=>"发送失败."];
        }
    }

    //查询导出PayPal ddr数据
    public function ddrList(){
        $params = request()->post();

        if( empty($params['payment_txn_id']) ){
            $this->assign(['windData'=>[]]);
            return View();
        }

        $this->assign(['payment_txn_id'=>$params['payment_txn_id']]);

        $paypal_ids = QueryFiltering($params['payment_txn_id']);
        
        $windData = $this->windService->getRiskDataById($paypal_ids);

        $isExport   = !empty($params['is_export'])?$params['is_export']:0;
        if( $isExport ){
            $this->windService->exportDdr($windData);
        }

        $this->assign(['windData'=>$windData]);

        return View();
    }

    //查询导出地址
    public function addressList(){
        $params = request()->post();

        if( empty($params['order_number']) ){
            $this->assign(['windData'=>[]]);
            return View();
        }

        $this->assign(['order_number'=>$params['order_number']]);

        $order_number = QueryFiltering($params['order_number']);
        
        $windData = $this->windService->getAddress($order_number);

        $isExport   = !empty($params['is_export'])?$params['is_export']:0;
        if( $isExport ){
            $this->windService->exportAddress($windData);
        }

        $this->assign(['windData'=>$windData]);

        return View();
    }

    //paypal case 查询
    public function caseList(){
        $params = request()->post();

        if( empty($params['payment_txn_id']) ){
            $this->assign(['windData'=>[]]);
            return View();
        }

        $this->assign(['payment_txn_id'=>$params['payment_txn_id']]);

        $paypal_ids = QueryFiltering($params['payment_txn_id']);
        
        $windData = $this->windService->getPaypalCaseById($paypal_ids);
        /*
        $isExport   = !empty($params['is_export'])?$params['is_export']:0;
        if( $isExport ){
            $this->windService->exportDdr($windData);
        }
        */
        
        $this->assign(['windData'=>$windData]);

        return View();
    }

    /*
    * 支付等处理订单
    * add by kevin 20191101
    * */
    public function paymentProcessingOrder(){
        $data = input();
        $where = [];
        //获取所有所有客服名单
        $CustomerServiceList = WindControlModel::CustomerServiceList(['group_id'=>['exp','in(9,12)']]);
        $DealWithStatus=config('DealWithStatus');
        $resultTime = true;
        if(empty($data['OrderNumber']) && empty($data['TransactionID'])){
            if(!empty($data["startTime"]) && !empty($data["endTime"])){
                $resultTime =  $this->TimeDetection($data["startTime"],$data["endTime"]);//时间限制验证
            }else{
                /*默认传入查询三个月内时间参数*/
                $data['startTime'] = $startTime = date("Y-m-d H:i:s",strtotime('-3 month'));
                $data['endTime'] = $endTime   = date("Y-m-d H:i:s",time()-1);
                $this->assign("startTime",$startTime);
                $this->assign("endTime",$endTime);
            }
        }
        if($resultTime === true){
            if(!empty($data['Channel'])){
                $where['PaymentChannel'] = ['exp','in("'.$data['Channel'].'","'.strtolower($data['Channel']).'")'];
            }
            if($data['DealWithStatus']!=''){
                $where['DealWithStatus'] = ["eq",$data['DealWithStatus']];
            }else{
                $where['DealWithStatus'] = ['eq',2];
            }

            if(!empty($data['DistributionAdminId'])){
                $where['DistributionAdminId'] = $data['DistributionAdminId'];
            }
            if(!empty($data['OrderNumber'])){
                $where['OrderNumber'] = $data['OrderNumber'];
            }
            if(!empty($data['TransactionID'])){
                $where['TransactionID'] = $data['TransactionID'];
            }
            if(!empty($data['OperatorId'])){
                $where['OperatorId'] = $data['OperatorId'];
            }
            if(!empty($data['startTime']) && !empty($data['endTime'])){
                $where['AddTime'] =  array('between',strtotime($data['startTime']).','.strtotime($data['endTime']));
            }else if(!empty($data['startTime'])){
                $where['AddTime'] =  array('egt',strtotime($data['startTime']));
            }else if(!empty($data['startTime'])){
                $where['AddTime'] =  array('elt',strtotime($data['endTime']));
            }
            if(!empty($data['operatorId'])){
                $where['OperatorId'] = $data['operatorId'];
            }

        }else{
            $result = json_decode($resultTime,true);
            $this->assign(['error'=>$result["data"],]);
        }

        $data = [];
        $Channel = [];
        $ChannelDisbursement = publicConfig(S_CONFIG,'ChannelDisbursement');
        $ChannelDisbursement = htmlspecialchars_decode($ChannelDisbursement["result"]["ConfigValue"]);
        $ChannelDisbursement = explode(';', $ChannelDisbursement);
        if(!empty($ChannelDisbursement)){
            foreach ($ChannelDisbursement as $k => $v) {
                if(!empty($v)){
                    $Channel[] = explode(':', $v);
                }
            }
        }
        $data['page_size'] = 5000;
        $data['page']=!empty($data['page'])?$data['page']:1;
        $SiteID=Config('SiteID');
        $where['SiteID']=['in',$SiteID];
        /*if(!empty($data['is_export']) && $data['is_export'] == 1){
            $data['page_size'] = 100000000;
            $where['page_data']=json_encode($data);//分页数据
            //不通过原因,1：黑名单拒绝，2:客户要求取消 3未传资料_高风险订单4、已传资料_高风险订单5、重复订单6、欺诈客户、7其他'
            $NoPassReasonData = [1=>"黑名单拒绝",2=>"客户要求取消",3=>"未传资料_高风险订单",4=>"已传资料_高风险订单",5=>"重复订单",6=>"欺诈客户",7=>"其他"];
            $data1 = BaseApi::RiskOrderList($where);
            if(!empty($data1['data']['data'])){
                $export_data = array();
                foreach ($data1['data']['data'] as $key=>$value){
                    $export_data[$key] = $value;
                    $export_data[$key]['DealWithStatusStr'] = !empty($value['DealWithStatus'])?"已判定":"未判定";
                    if(empty($value['NoPassReason'])){
                        $export_data[$key]['description'] = $value['pay_channel']?$value['PaymentMethod']:''.'('.$value['Code']?$value['Code']:''.')'.'['.$value['Msg']?$value['Msg']:''.']';
                    }else{
                        $export_data[$key]['description'] = $NoPassReasonData[$value['NoPassReason']];
                    }
                    $export_data[$key]['OperatingTimeStr'] = !empty($value['OperatingTime'])?date("Y-m-d H:i:s",$value['OperatingTime']):"";
                }
            }else{
                $this->error("没有数据");
            }
            $header_data =['OrderNumber'=>'订单号',
                'Amount'=>'金额','CurrencyCode'=>'币种','ShippAddressCountry'=>'国家缩写','ShippAddressCountryName'=>'国家',
                'Operator'=>'操作人','DealWithStatusStr'=>'是否判定','description'=>'拒绝原因','OperatingTimeStr'=>'拒绝时间'
            ];
            $tool = new ExcelTool();
            if(!empty($export_data)){
                $tool ->export('风控订单数据'.time(),$header_data,$export_data,'sheet1');
            }else{
                echo '没查到数据';
                exit;
            }
        }*/
        $where['page_data']=json_encode($data);//分页数据
        $processing_data = array();
        $data1 = BaseApi::RiskOrderList($where);
        if(!empty($data['order_status'])){
            $order_where['order_status'] = 120;
            if(!empty($data1['data']['data'])){
                $order_numbers = array_column($data1['data']['data'],'OrderNumber');

                $order_where['order_number'] = $order_numbers;
                $processing_order = BaseApi::getWindControlOrderList($order_where);
                if(!empty($processing_order['data'])){
                    foreach ($data1['data']['data'] as $key=>$value){
                        if(!isset($processing_order['data'][$value['OrderNumber']])){
                            unset($data1['data']['data'][$key]);
                        }
                    }
                }
            }
        }
        if(!empty($data1['data']['data'])){
            $processing_data = $data1['data']['data'];
        }
        $this->assign(['list'=>$processing_data,'Channel'=>$Channel,'CustomerServiceList'=>$CustomerServiceList,'data'=>$data,'DealWithStatus'=>$DealWithStatus]);
        return View();
    }

    /*
	 *  订单查询时间的限制
	 *  @author wang   2018-09-13
	 */
    public  function  TimeDetection($startTime,$endTime){
        //只能查询当前到三年前的时间，同时每次查询起始间隔为三个月
        if(!empty($startTime) && !empty($startTime)){
            $minTime = strtotime('-3 year');
            $startTime = strtotime($startTime);
            $endTime   = strtotime($endTime);
            if($startTime < $minTime || $endTime < $minTime){
                return json_encode(array('code'=>100,'data'=>'只能查询三年内的数据'));
            }else if($startTime>$endTime){
                return json_encode(array('code'=>100,'data'=>'结束时间必须大于开始时间'));
            }else {
                //获取三个月后的时间戳
                $intervalTime = strtotime("-3 month",$endTime );
                if($startTime < $intervalTime){
                    return json_encode(array('code'=>100,'data'=>'时间间隔相差超过三个月'));
                }
            }
        }
        return true;
    }


    public function testurl(){
        $url = config('test_url');
        var_dump($url);
        $cic_api_url = config('cic_api_url');
        var_dump(cic_api_url);
    }
}