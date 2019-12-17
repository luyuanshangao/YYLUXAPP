<?php
namespace app\admin\controller;
use \think\Session;
use think\View;
use think\Controller;
use think\Db;
use think\Paginator;
use think\Url;
use app\admin\model\Logisticsconfigmanage as Logistics;
use app\admin\dxcommon\BaseApi;


/**
 * 物流管理
 * author  Wang
 */
class Logisticsconfigmanage  extends Action
{
    public function __construct(){
       Action::__construct();
       define('L_M', 'LogisticsManagement');//物流管理部
       // $this->Menu_logo();
    }
    /**
     * [LogisticsList description]
     * 物流管理列表
     * author  Wang
     */
    public function LogisticsList(){
        // $Logistics = Db::name(L_M);
        $data = request()->post();
         if(input('page')){
                $data['page']   = input('page');
         }else{
               $data['page']    = 1;
         }
        if($data){//查询数据
              $data['page_size']=20;
              $Logistics_list = BaseApi::LogisticsList($data);
              $this->assign(['country'=>$data['country'],]);
        }else{
              $data['page_size']=20;
              $Logistics_list = BaseApi::LogisticsList($data);
        }
        $this->assign(['Logistics_list'=>$Logistics_list["Logistics_list"],'page'=>str_replace("seller/seller","Logisticsconfigmanage",$Logistics_list['page'])]);
        return view('LogisticsList');
    }

    /**
     * 添加物流
     */
    public function AddLogistics(){
        if($data = request()->post()){
               $Logistics = Db::name(L_M);
              //判断提交数据是否符合
              if(Logistics::LogisticsJudgment($data)){
                   $country_dada       = explode(",", $data["country"]);
                   $val['add_time']    = time();
                   $val['remarks']     = $data['remarks'];
                   $data['add_author'] = Session::get('username');
                   $result = BaseApi::AddLogistics($data);
                   echo str_replace("data","result",json_encode($result));
                   exit;
              }else{
                 echo json_encode(array('code'=>100,'result'=>'提交数据有误'));
                 exit;
              }
        }else{
            $this->LogisticsMode();
            return view('AddLogistics');
        }

    }
    /*
    物流方式配置
     */
    public function LogisticsMode(){
    	//系统字典表配置的ShippingServiceMethod,物流服务方式
         $freightData = array(
              '0'=>'标准物流运费',
              '1'=>'经济物流运费',
              '2'=>'快速物流运费',
              // '3'=>'专线物流运费',
        );
        $countries_list   =  Db::connect("db_mongo")->name("dx_region")->field('Name,Code,_id')
        							->where(['ParentID'=>0])->select();

        //系统字典表配置的KEY:ExclusiveForCountry,当前系统支持的物流专线： US:IB;AU:Toll;AR:NOCNOC
        $keyExclusiveForCountry ='ExclusiveForCountry';
        $exclusiveForCountry = model("SysConfig")->getSysCofig($keyExclusiveForCountry);
        // $exclusiveForCountry = json_decode($exclusiveForCountry["ConfigValue"],true);
        $exclusiveForCountry =$exclusiveForCountry["ConfigValue"];
        $this->assign(['freightData'=>$freightData,'ountries_list'=>$countries_list,
        		       'exclusiveForCountry' => $exclusiveForCountry]);
        return;
    }
    /**修改物流
     * [EditLogistics description]
     * auther Wang   2018-04-08
     */
    public function EditLogistics(){
       $Logistics   = Db::name(L_M);
       $countryCode = input('countryCode');
       if($data = request()->post()){
           if(Logistics::LogisticsJudgment($data)){
                  $data['type']        = 2;
                  $data['edit_author'] = Session::get('username');
                  $result = BaseApi::EditLogistics($data);
                  echo str_replace("data","result",json_encode($result));
           }
       }else{
            $countryCode = input('countryCode');
            $data['type'] = 1;
            $data['countryCode'] = $countryCode;
            $LogisticsList = BaseApi::EditLogistics($data);
            $this->LogisticsMode();
            // $LogisticsList = $Logistics->where(['countryCode'=>$countryCode])->select();
            $data = array();
            foreach ($LogisticsList as $key => $value) {
                   $data[$value["shippingServiceID"]] = $value;
                   $countryCode = $value['countryENName'].'-'.$value['countryCode'];
            }
            $this->assign(['LogisticsList'=>$data,'countryCode'=>$countryCode,]);
            return view('AddLogistics');
       }

    }
    /**
     * [public_delete description]
     * @return [type] [description]
     * 以ID删除
     */
    public function public_delete(){
          if($data = request()->post()){
              $result = BaseApi::deleteLogistics($data);
              echo str_replace("data","result",json_encode($result));
              exit;
          }
           // publicDelete(L_M);deleteLogistics
    }


    public function aaa(){

      dump(BaseApi::langs1122());

      exit;
        // $map['Name'] =array('like',array('%thinkphp%','%Afghani'));
// $data['title_cn'] = '/^Afg/';
        $whereLike ='Afghanistan';
//         $map['Name']  ='/^Afg$/is';
        $map['ParentID'] = 0;
       dump(Db::connect("db_mongo")->name("dx_region")->where(['ParentID'=>0])->find());//Name like '%-Afgh-%'
       // $a =  Db::connect("db_mongo")->name("dx_region")->where($map)->select();dump($a);dump($map);
       $a =  Db::connect("db_mongo")->name("dx_region")->where(['ParentID'=>0])->whereLike("Name",$whereLike)->select();dump($a);dump($map);
    }

}
