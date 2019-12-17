<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/8/13
 * Time: 14:40
 */
namespace app\app\controller;

use app\admin\model\Customer;
use app\app\dxcommon\BaseApi;
use app\common\controller\AppBase;
use app\common\params\mall\ProductParams;
use app\app\services\ProductService;
use think\Db;
use think\Exception;
use vendor\aes\aes;
use think\Log;
use app\common\controller\Email;

/**
 * 产品接口
 */
class Users extends AppBase
{
    public $baseApi;

    public function __construct()
    {
        parent::__construct();
        $this->baseApi=new BaseApi();
        defined('MALLDOMAIN') or define('MALLDOMAIN', '//mall.dx.com');
        defined('HELPDOMAIN') or define('HELPDOMAIN', '//help.dx.com');
    }

    /**
     * 找回密码
     */
    public function passwordFind(){
            $Email = input("Email");
            $result = $this->validate(
                ['email' => $Email],
                ['email'   => 'email']
            );
            if(true !== $result){
                return ["code"=>1002, "msg"=>"The email address you entered is incorrect, please reconfirm!"];
            }
            /*调用GetCustomerInofByAccount,验证用户名是否存在*/
            $url = CIC_API.'cic/Customer/GetCustomerInfoByAccount';
            $arrays = array('AccountName'=>$Email);
            $customer_data = doCurl($url,$arrays, null, true);
            log::record($Email.'$customer_data:'.json_encode($customer_data));
            if(!empty($customer_data['code']) && $customer_data['code'] == 200){
                $code_data = getSubmitCode("PasswordResetCode",$customer_data['data'],1,1);
                if(empty($code_data)){
                    Log::write("passwordFind getSubmitCode error,customer_data param:".json_encode($customer_data));
                    return ["code"=>1002, "msg"=>"System error, please try again later!"];
                }
                log::record($Email.'$customer_data:'.json_encode($customer_data).$code_data);
                $send_email_resp = $this->sendEmailForPasswordFind($customer_data['data'],$code_data);
                if($send_email_resp){
                    $res_data['CustomerID'] = $customer_data['data'];
                    return ["code"=>200,"msg"=>"Send Code Success.",'data'=>$res_data];
                }else{
                    return ["code"=>1002,"msg"=>"Mail failure."];
                }
            }else{
                return ["code"=>1002, "msg"=>"The email address you entered is incorrect, please reconfirm!"];
            }
        return $this->fetch();
    }

    /*
    * 发送邮件【找回密码用】
    * */
    private function sendEmailForPasswordFind($id,$send_code){
        $url = CIC_API.'cic/Customer/getCustomerByID';
        $arrays = ['ID'=>$id];
        $data = doCurl($url,$arrays, null, true);
        if($data['code'] != 200){
            return false;
        }
        vendor('aes.aes');
        $shop_url = '<a target="_blank" href="https:' . MALLDOMAIN . '" style="COLOR:#3f7bc6">Shop on DX now!</a>';
        $help_url = '<a target="_blank" href="https:' . HELPDOMAIN . '" style="COLOR:#3f7bc6">Help Center</a>';
        $send_email_resp = Email::sendEmail(
            $data['data']['email'],
            7,
            $data['data']['UserName'],
            [
                'username'=>$data['data']['UserName'],
                'send_code'=>$send_code,
                'shop_url'=>$shop_url,
                'help_url'=>$help_url
            ]
        );
        return $send_email_resp;
    }

    /*
    * 验证修改密码和激活用户验证码
    * */
    public function verifyUserCode(){
        $type = input("type/d",1);
        $CustomerID = input("CustomerID/d");
        $VerificationCode = input("VerificationCode/d");
        $Password= input("Password");
        $input = input();
        $code_type_data = [1=>"PasswordResetCode",2=>"ActivationAccountCode",3=>"ChangPaymentPasswordCode",4=>"ActivationAffiliateCode",5=>"ChangeEmailCode"];
        if(empty($code_type_data[$type])){
            return ['code'=>1002,'msg'=>"Send code type error"];
        }
        if(empty($VerificationCode)){
            return ['code'=>1002,'msg'=>"Verification code cannot be empty"];
        }
        /*验证码*/
        $code['UserId'] = $CustomerID;
        $code['UserType'] = 1;
        $code['Type'] = $code_type_data[$type];
        $code['VerificationCode'] = $VerificationCode;
        $CheckCodeRes = $this->baseApi->checkVerificationCode($code);

        //链接过期时间
        $expiration_time = time()+1800;
        $data = array();
        vendor('aes.aes');
        $aes = new aes();
        if($type == 1){
            $data['jump_url'] = url('users/resetPassword','','',true).'?resetPasswordID='.urlencode($aes->encrypt($CustomerID."_".$expiration_time));
        }elseif ($type == 3){//更改支付密码
            $data['jump_url'] = url('PaymentPassword/resetpaymentPassword','','',true).'?resetPasswordID='.urlencode($aes->encrypt($CustomerID."_".$expiration_time));
        }elseif ($type == 4){//激活affiliate账号
            $data['jump_url'] = url('Affiliate/activation','','',true).'?activationAffiliateID='.urlencode($aes->encrypt($CustomerID."_".$expiration_time));
        }elseif ($type == 5){//激活新邮箱账号

        }

        if($CheckCodeRes){
            //验证通过
            $data['ID'] = $CustomerID;
            $data['Password'] = $Password;
            $url = CIC_API.'cic/Customer/saveProfile';
            $res = doCurl($url,$data, null, true);

            if($res['code'] == 200){
                $url = CIC_API.'cic/Customer/getCustomerByID';
                $Customer = doCurl($url,["ID"=>$data['ID']], null, true);
                $data1['CustomerID'] = $data['ID'];
                $data1['OperateStatus'] = isset($Customer['data']['Status'])?$Customer['data']['Status']:1;
                $data1['IPAddress'] = GetIp();
                $data1['OperationName'] = isset($Customer['data']['UserName'])?$Customer['data']['UserName']:'';
                $data1['IPNumber'] = GetIp();
                $data1['OperateTime'] = time();
                $data1['table'] = "cic_changepassword_history";
                $url1= config('api_base_url').'/log/Index/operationLog';
                $re = doCurl($url1,$data1, null, true);
            }
            return $res;
            //return ['code'=>200,'msg'=>"Success",'data'=>$data];
        }else{
            Log::record('verifyUserCode'.json_encode($input).'$CheckCodeRes'.json_encode($CheckCodeRes));
            return ['code'=>1002,'msg'=>"Verification code error"];
        }
    }

    /*
     * 设置用户推送令牌
     */
    public function setRegId(){
        $ID=input('CustomerID');
        $RegId=input('RegId');
        //验证通过
        $data['RegId'] = $RegId;
        /*
        $url = CIC_API.'cic/Customer/saveProfile';
        $res = doCurl($url,$data, null, true);*/
        $Customer = new Customer();
        $da['customer_id'] = $ID;
        $da['reg_id'] = $RegId;
        $da['create_time'] = time();
        $where['customer_id'] = $ID;
        $count=$Customer->getCount($where);
        if(!empty($count)){
            $da1['customer_id'] = $ID;
            $da1['reg_id'] = $RegId;
            $da1['update_time'] = time();
            $res = $Customer->updateCustomerAPP($da1,$where);
        }else{
            $res = $Customer->addCustomerAPP($da);
        }

        if($res!==false){
            return ['code'=>200,'msg'=>"Success",'data'=>$res];
        }else{
            return ['code'=>1002,'msg'=>"setRegId error"];
        }
    }

    /*
     * 修改用户邮箱
     *
     * */
    public function changeEmail(){
        vendor('aes.aes');
        $aes = new aes();
        $cstomer['ID']=input('CustomerID');
        $cstomer['UserName']=input('UserName');
        $cstomer['email']=input('AccountName');
        $RegId=input('RegId');
        $post=input();
            $da=[
                'AccountName'  => 'require|email',
                'email'   => 'require|email',
                'Password'   => 'require',
            ];
            //验证参数
            $result= $this->validate($post,$da);
            if(true !== $result){
                // 验证失败 输出错误信息
                return ['code'=>1001,'msg'=>$result];
            }

            //修改邮箱
            $data['ID'] = $cstomer['ID'];
            $data['AccountName'] = input("AccountName");
            $data['Password'] = input("Password");
            $data['Email'] = input(("email"));
            $url = CIC_API."cic/Customer/changeEmail";
            $res = accessTokenToCurl($url,null,json_encode($data),true);
            $res = json_decode($res,true);
            if($res['code'] == 200){
                //直接修改
                $data['ID'] = $cstomer['ID'];
                $data['Email'] =$data['Email'];
                $url = CIC_API.'cic/Customer/saveProfile';
                $res = accessTokenToCurl($url,null,json_encode($data),true);
                $res = json_decode($res,true);
                if($res['code']==200){
                    return ['code'=>200,'msg'=>'The e-mail was successfully modified.','data'=>''];
                }else{
                    Log::record('changeEmail'.json_encode($res).'$data'.json_encode($data));
                    return ['code'=>1005,'msg'=>'email error'];
                }
            }else{
                return ['code'=>1006,'msg'=>'email error'];
            }

    }


}