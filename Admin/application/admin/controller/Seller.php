<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon;

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;
/*
 * 商城管理--卖家管理--卖家管理
 * Add by:zhangheng
 * AddTime:2018-03-30
 * Info:
 *     1.商城管理--卖家管理--卖家管理:查询，修改，删除
 */
class Seller extends Action
{
	public function __construct(){
       Action::__construct();
       define('REGION', 'Region');//物流管理部
       $this->Menu_logo();
    }

	/**
	 * 卖家管理--查询
	 */
	public function index()
	{
		$baseApi = new BaseApi();
		if($data = request()->post()){
			//var_dump($data);
			//die();
			$statusDefault = $data['status'];
			$management_model_Default = $data['management_model'];
            if($data["country_town"]){
	            $data['country_town_data']  = $this->NationalIndex($data["country_town"]);
			}
			if($data["city"]){
	            $data['city_data']          = $this->NationalIndex($data["city"]);
			}
			if($data["province"]){//dump($data["province"]);
	            $data['province_data']      = $this->NationalIndex($data["province"]);
			}
            $this->assign(['data'=>$data,]);
		}
		$data['page']=1;
		$data['page_size']=config('paginate.list_rows');
		//调用API方法获取数据
		$result = $baseApi::getSellerData($data);
		//状态条件数据组装
		$stautsDict = $this->dictionariesQuery('Seller_Status');
		$commonClass = new \app\admin\dxcommon\Common();
		$stautsHtml = $commonClass::outSelectHtml($stautsDict,'status',$statusDefault);

		//经营模式数据组装
		$management_model_Dict = $this->dictionariesQuery('Seller_Management_Model');
		$management_model_Html = $commonClass::outSelectHtml($management_model_Dict,'management_model',$management_model_Default);

		$provinceData = DB::name(REGION)->where(['PARENT_ID'=>1])->select();
		$useData = $result['data']['data'];//dump($useData);
		$this->assign(['list'=>$useData,'province'=>$provinceData,'stautsHtml'=>$stautsHtml,'management_model_Html'=>$management_model_Html,]);
		//绑定列表区域字段的枚举值
		$this->assign(['stautsDict'=>$stautsDict,
					   'management_model_Dict'=>$management_model_Dict
					  ]);
		return View('index');
	}

	 //下级找上级
     public function NationalIndex($val=''){

   	  	     // $val = $province;
           $PARENT_ID = DB::name('region')->where(['REGION_ID'=>$val])->field('PARENT_ID')->find();
	         $list      = DB::name('region')->where(['PARENT_ID'=>$PARENT_ID['PARENT_ID']])->select();
	         $html = '';
	         $html .= '<option value="">请选择</option>';
             foreach ($list as $key => $value) {
 	            if($val == $value['REGION_ID']  && $val !=''){
                    $select = 'selected = "selected"';
 	            }else{
                    $select = '';
                }
                $html .= '<option '.$select.'  value="'.$value["REGION_ID"].'">'.$value['REGION_NAME'].'</option>';
             }
             return $html;
	         exit;

   }

	/*
	 * 卖家管理--编辑
	 * $id:供应商信息ID
	 * auther wang   2018-04-01
	 */
	public function edit($id=''){

		if($data = request()->post()){//dump($data);
			// $date['user_id'] = '48';
			      $data['op_name'] = Session::get('username');;
            //dump($data);
            //die();
            $result = BaseApi::merchantEdit($data);
            //dump(json_encode($result,true));
            //die();
            echo str_replace("msg","result",json_encode($result));
            exit;
		}else{
			//调用API方法获取数据
			$result = BaseApi::getSellerByID($id);
			$useData = $result['data'];
			if($useData["country_town"]){
	            $useData['country_town_data']  = $this->NationalRecursion($useData["country_town"]);
			}
			if($useData["city"]){
	            $useData['city_data']          = $this->NationalRecursion($useData["city"]);
			}
			if($useData["province"]){
	            $useData['province_data']      = $country_town = $this->NationalRecursion($useData["province"]);
			}
            if(empty($useData["country_town_data"]) && empty($useData["city_data"]) && empty($useData["province_data"])){
               $useData['province_data'] = DB::name(REGION)->where(['PARENT_ID'=>1])->select();//dump($provinceData);
            }

			$this->assign(['seller'=>$useData,]);//dump($useData);
			return View('edit');
		}

	}
   //下级找上级auther wang   2018-04-03
   public function NationalRecursion($val=''){
   	  $province  = input('province');
   	  if($province){
   	  	     $val = $province;
             $list      = DB::name('region')->where(['PARENT_ID'=>$val])->select();
             // $PARENT_ID = DB::name('region')->where(['REGION_ID'=>$val])->field('PARENT_ID')->find();
             // $list      = DB::name('region')->where(['PARENT_ID'=>$PARENT_ID['PARENT_ID']])->select();
             $html = '';
             $html .= '<option value="">请选择</option>';
             foreach ($list as $key => $value) {
                $html .= '<option  value="'.$value["REGION_ID"].'">'.$value['REGION_NAME'].'</option>';
             }
             echo $html;
	         exit;
   	  }else{

   	  	 if($val!=''){
   	  	     $PARENT_ID = DB::name('region')->where(['REGION_ID'=>$val])->field('PARENT_ID')->find();
	         $list      = DB::name('region')->where(['PARENT_ID'=>$PARENT_ID['PARENT_ID']])->select();
	         return $list;
   	  	 }

   	  }
   }
   //上级找下级auther wang   2018-04-03
   public function NationalSubordinate($val=''){
   	  $province  = input('province');
   	  if($province){
   	  	     $val = $province;
             // $PARENT_ID = DB::name('region')->where(['REGION_ID'=>$val])->field('PARENT_ID')->find();
	         $list   = DB::name('region')->where(['PARENT_ID'=>$province])->select();
	         $html   = '';
	         $html  .= '<option value="">请选择</option>';
             foreach ($list as $key => $value) {
                $html .= '<option  value="'.$value["REGION_ID"].'">'.$value['REGION_NAME'].'</option>';
             }
             echo $html;
	         exit;
   	  }
   }
  /**卖家逻辑删除
   * [MerchantDelete description]
   * auther wang   2018-04-03
   */
  public function MerchantDelete(){
      if($data = request()->post()){//dump($data);
		     $data['op_name'] = Session::get('username');
		     $result = BaseApi::MerchantDelete($data);
		     if($result["code"] == 200){
               echo json_encode($result);
               exit;
		     }else{
               echo json_encode($result);
               exit;
		     }
      }
  }
  /**
 * 重置密码
 * auther wang   2018-04-04
 */
  public function reset_password(){
        if($data = request()->post()){
            $reset_password = config('reset_password');
            $dataType['user_id'] = $data['user_id'];
            $dataType['new_pwd'] = rand(00000000,99999999);
            $result =  BaseApi::reset_password($dataType);
            if($result['code'] == 200){
               $reset_password['body'] = str_replace("xxx",$dataType['new_pwd'],$reset_password['body']);
               $resultList = send_mail($data['email'],'东方巨人', $reset_password['subject'], $reset_password['body'], $attachment = null);
               if($resultList){
                echo json_encode(array('code'=>200,'result'=>'重置成功,新密码已经发生给用户,请提醒用户查收邮箱'));
                exit;
               }
            }else{
                echo json_encode($result);
                exit;
            }
        }
  }




}