<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use app\index\dxcommon\Email;
use app\index\dxcommon\User;
use app\index\model\UserModel;
use Google\Cloud\Core\RequestWrapper;
use think\captcha\Captcha;
use think\Config;
use think\Controller;
use think\Log;
use think\Request;
use think\Session;
use think\Validate;
use vendor\aes\aes;

/**
 * Class Login
 * @author tinghu.liu
 * @date 2018-03-06
 * @package app\index\controller
 *
 */
class Login extends Controller
{
    private $active_key = '1234567$%ABCEDFW';
    private $sms_key = 'dxsellersmskey2018';
    //找回密码生成token的key
    private $find_password_key = 'findPasswordFourth';
    public function _initialize()
    {
        $user_data = Session::get('user_data');
        $user_name = $user_data['user_name'];
        //登录
        if (!empty($user_name) && strpos($_SERVER['REQUEST_URI'],'m_logout')===false){
            $this->redirect('Index/index');
        }
    }

    /*
     * 登录首页
     */
    public function index()
    {
        $this->assign('referer_url', Request::instance()->param('referer'));
        $this->assign('passwordfind_url', url('login/passwordFind'));
        $this->assign('register_url', url('login/register'));
        $this->assign('title', 'DX seller登录');
        return $this->fetch('index');
    }

    /**
     * 找回密码
     */
    public function passwordFind(){
        $drag_key = md5('dragverifycode'.time().rand(500,10000));
        session('drag_key',$drag_key);
        $this->assign('drag_key', $drag_key);
        $this->assign('title', '找回密码');
        return $this->fetch();
    }

    /**
     * 找回密码2
     */
    public function passwordfindSecond(){
        $param = input();
        $is_error = false;
        if (empty($param) || empty($param['flag']) || empty($param['user_id'])){
            $is_error = true;
        }
        $flag = $param['flag'];//1-邮箱，2-电话
        $user_id = $param['user_id'];
        $user_model = new UserModel();
        $user_info = $user_model->getInfoById($user_id);
        if (empty($user_info) || ($flag != 1 && $flag != 2)){
            $is_error = true;
        }
        if ($is_error){
            $this->error('错误访问',url('Login/index'));
        }
        $this->assign('user_id', $user_id);
        $this->assign('flag', $flag);
        $this->assign('url', url('Login/PasswordfindThird', ['flag'=>$flag, 'user_id'=>$user_id]));
        $this->assign('title', '验证身份_找回密码');
        return $this->fetch();
    }

    /**
     * 找回密码3
     */
    public function passwordfindThird(){
        $param = input();
        $is_error = false;
        if (empty($param) || empty($param['flag']) || empty($param['user_id'])){
            $is_error = true;
        }
        $flag = $param['flag'];//1-邮箱，2-电话
        $user_id = $param['user_id'];
        $user_model = new UserModel();
        $user_info = $user_model->getInfoById($user_id);
        if (empty($user_info) || ($flag != 1 && $flag != 2)){
            $is_error = true;
        }
        if ($is_error){
            $this->error('错误访问',url('Login/index'));
        }
        //http://dxseller.localhost/passwordfindthird/flag/2/user_id/17.html
        $this->assign('get_data', $param);
        $this->assign('email_str', substr_replace($user_info['email'], '****', 2, 4));
        $this->assign('email', $user_info['email']);
        $this->assign('phone_num', substr_replace($user_info['phone_num'], '****', 3, 4));
        $this->assign('phone_num_all', $user_info['phone_num']);
        $this->assign('other_type_url', url('Login/passwordFind'));
        $this->assign('title', '验证身份_找回密码');
        return $this->fetch();
    }

    /**
     * 找回密码4
     */
    public function passwordfindFourth(){
        $param = input();
        $is_error = false;
        if (empty($param) || empty($param['token']) || empty($param['flag']) || empty($param['user_id'])){
            $this->error('错误访问',url('Login/index'));
        }
        $token = $param['token'];
        $flag = $param['flag'];//1-邮箱，2-电话
        $user_id = $param['user_id'];
        $user_model = new UserModel();
        $user_info = $user_model->getInfoById($user_id);
        //token校验
        $token_code = Session::get($token);
        if (empty($token_code) || $token_code != $user_id){
            $is_error = true;
        }
        if (empty($user_info) || ($flag != 1 && $flag != 2) || $user_info['is_delete'] == 1){
            $is_error = true;
        }
        if ($is_error){
            $this->error('错误访问',url('Login/index'));
        }
        $this->assign('get_data', $param);
        $this->assign('title', '找回密码');
        return $this->fetch();
    }

    /**
     * 找回密码成功
     */
    public function passwordfindSuccess(){
        $param = input();
        $is_error = false;
        if (empty($param) || empty($param['token']) || empty($param['flag']) || empty($param['user_id'])){
            $this->error('错误访问',url('Login/index'));
        }
        $token = $param['token'];
        $flag = $param['flag'];//1-邮箱，2-电话
        $user_id = $param['user_id'];
        $user_model = new UserModel();
        $user_info = $user_model->getInfoById($user_id);
        //token校验
        $token_code = Session::get($token);
        if (empty($token_code) || $token_code != $user_id){
            $is_error = true;
        }
        if (empty($user_info) || ($flag != 1 && $flag != 2) || $user_info['is_delete'] == 1){
            $is_error = true;
        }
        if ($is_error){
            $this->error('错误访问',url('Login/index'));
        }
        //找回密码成功，释放session
        session($token, null);
        $this->assign('login_url', url('Login/index'));
        return $this->fetch();
    }

    /**
     * 用户注册
     * @return mixed
     */
    public function register(){
        $drag_key = md5('dragverifycode'.time().rand(500,10000));
        session('drag_key',$drag_key);
        $this->assign('seller_registration_protocol_url', config('seller_registration_protocol_url'));
        $this->assign('drag_key', $drag_key);
        $this->assign('title', '用户注册');
        return $this->fetch();
    }
    /**
     * 用户注册-账号信息
     * @return mixed
     */
    public function registerInfo(){
        $d = input('d');
        /** 参数校验 **/
        if (empty($d)){
            $this->error('错误访问',url('Index/index'));
        }
        $mail_data = json_decode(base64_decode(base64_decode($d)), true);
        if (
            !isset($mail_data['UserId'])
            || !isset($mail_data['UserType'])
            || !isset($mail_data['Type'])
            || !isset($mail_data['VerificationCode'])
            || empty($mail_data['VerificationCode'])
            //用户类型：1买家 2卖家 3管理员
            || $mail_data['UserType'] != 2
        ){
            $this->error('错误访问',url('Index/index'));
        }
        /** 安全码校验 **/
        $user_model = new UserModel();
        $base_api = new BaseApi();
        $code['UserId'] = $mail_data["UserId"];
        $code['UserType'] = $mail_data["UserType"];
        $code['Type'] = $mail_data["Type"];
        $code['VerificationCode'] = $mail_data["VerificationCode"];
        if(!$base_api->checkVerificationCode($code)){
            $this->error('邮箱验证错误',url('Index/index'));
        }
        //通过第一步回生成一个用户ID，且保存了用户有效邮箱
        //$user_id = Request::instance()->param('usid');
        $user_id = $mail_data["UserId"];
        $user_info = $user_model->getInfoById($user_id);//根据$user_id获取用户信息，主要是上一步的邮箱
        //对$user_id做正确性判断
        if (empty($user_info) || $user_info['status'] != 0 || $user_info['is_delete'] != 0){
            $this->error('用户信息错误',url('Index/index'));
        }
        $this->assign('province_info', object2array($base_api->getRegionDataWithRegionID(1)));
        $this->assign('user_info', $user_info);
        $this->assign('management_model',Base::getManageModel());
        $this->assign('online_experience',Base::getOnlineExperience());
        $this->assign('title', '用户注册账号信息');
        return $this->fetch();
    }

    /**
     * 用户注册-注册成功
     * @return mixed
     */
    public function registerFinish(){
        $user_id = Request::instance()->param('usid');
        if (empty($user_id) || !is_numeric($user_id)){
            $this->error('错误访问',url('Index/index'));
        }
        $this->assign('url', url('Login/index'));
        $this->assign('title', '用户注册成功');
        return $this->fetch();
    }

    /**
     * 注册功能
     * PS：注册第一步、第二步、第三步，都POST数据到这，根据参数flag（1-第一步，2-第二步，3-第三步）来判断之后处理不同逻辑即可
     */
    public function m_register(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据请求错误';
        $user_model = new UserModel();
        $base_api = new BaseApi();
        if (request()->isAjax()){//限制为ajax请求
            $data = Request::instance()->param();//获取通过ajax POST过来的数据
            Log::record('register-ajax'.print_r($data, true));
            $flag = $data['flag'];//注册步骤标识：1-第一步，2-第二步，3-第三步
            switch ($flag){
                case 1: //注册第一步：设置邮箱
                    $drag_key = $data['drag_key'];//校验KEY
                    $email = trimall($data['email']);//邮箱
                    $agreement = !empty($data['agreement'])?$data['agreement']:0;//同意协议
                    $drag_key_ori = session('drag_key');
                    if (!is_email($email)){
                        $rtn['msg'] = '邮箱不正确，请重新输入';
                        return json($rtn);
                    }else if($drag_key != $drag_key_ori){
                        $rtn['msg'] = '验证码不正确，请重新输入';
                        return json($rtn);
                    }else if(empty($agreement) || $agreement != 1){
                        $rtn['msg'] = '必须同意会员协议';
                        return json($rtn);
                    }/*
                    if ($drag_key != $drag_key_ori || empty($agreement) || $agreement != 1 || !is_email($email)){
                        $rtn['msg'] = '数据有误，请检查后重新提交';
                    }*/else{//数据正确
                        //根据邮箱判断用户是否已经注册
                        $user_info = $user_model->where(['email'=>$email])->find();
                        if (empty($user_info)){
                            //根据邮箱注册会员
                            $user_id = $user_model->insertIntoUserAndExtension(['email'=>$email,'addtime'=>time()]);
                            Log::record('注册user_id：'.$user_id);
                            if (!empty($user_id)){
                                //发送邮件给用户(调用邮件模板)
                                $toemail = $email;
                                $name = $email;
//                                $subject = '继续完成您的DX Seller会员注册'; //邮件标题
//                                $content = '恭喜你，完成了第一步，请点击注册链接继续下一步注册：'.url('Login/registerInfo','usid='.$aes->encrypt_pass($user_id, $this->active_key), 'html', true); //邮件内容
//                                $content = '恭喜你，完成了第一步，请点击注册链接继续下一步注册：'.url('Login/registerInfo','usid='.$user_id, 'html', true); //邮件内容
//                                $send_email_resp = send_mail($toemail,$name,$subject,$content);
                                /** 发送注册邮件 **/
                                $code['UserId'] = $user_id;
                                //用户类型：1买家 2卖家 3管理员
                                $code['UserType'] = 2;
                                $code['Type'] = "sellerRegisterMailVerify";
                                $code['IsDeleteOld'] = 1;
                                //有效时间，默认1个小时
                                $code['ExpiryTime'] = time()+60*60;
                                $verification_data = $base_api->createVerificationCode($code);
                                $verification_code = isset($verification_data['data']['VerificationCode'])?$verification_data['data']['VerificationCode']:'';
                                if (!empty($verification_code)){
                                    $email_data = [
                                        'UserId'=>$code['UserId'],
                                        'UserType'=>$code['UserType'],
                                        'Type'=>$code['Type'],
                                        'VerificationCode' => $verification_code
                                    ];
                                    $register_url = url('Login/registerInfo',['d'=>base64_encode(base64_encode(json_encode($email_data)))],'html',true);
                                    $send_email_resp = Email::sendEmailForRegisterAccountActivation($toemail, $name,['seller_name'=>$name,'account_activation_url'=>$register_url]);
                                    /*if($send_email_resp === true){//发送成功
                                        Log::record('注册发送邮件成功'.print_r($send_email_resp, true));
                                    }else{
                                        $rtn['msg'] = '发送邮件失败，请重试';
                                        Log::record('注册发送邮件失败信息：'.print_r($send_email_resp, true));
                                    }*/
                                    $rtn['code'] = 0;
                                    $rtn['msg'] = 'success';
                                    $rtn['email'] = $email;
                                }else{
                                    $rtn['msg'] = '系统错误，请重试';
                                }
                            }else{
                                $rtn['msg'] = '注册失败，请重试';
                            }
                        }else{
                            $rtn['msg'] = '邮箱已注册';
                        }
                    }
                    break;
                case 2://注册第二步：填写基本信息
                    $user_id = $data['id'];
                    //拼装用户编码
                    //$user_code = date('Y').str_pad($data['management_model'],3,"0",STR_PAD_LEFT).str_pad($user_id,4,"0",STR_PAD_LEFT);
                    $user_code = get_seller_code($data['management_model'], $user_id);
                    $province = $data['province'];
                    $city = $data['city'];
                    $country_town = $data['country_town'];
                    $up_data = [
                        //'password'=>md5($data['password']),
                        'password'=>get_seller_password($data['password']),
                        'seller_code'=>$user_code,
                        'true_name'=>$data['true_name'],
//                        'first_name'=>$data['first_name'],
//                        'last_name'=>$data['last_name'],
                        'phone_num'=>$data['phone_num'],
                        'province'=>$province,
                        'city'=>$city,
                        'country_town'=>$country_town
                    ];
                    /** 获取区域省市县数据 start **/
                    $region_data_api = $base_api->getRegionInfoByRegionID([$province, $city, $country_town]);
                    $region_data = isset($region_data_api['data'])&&!empty($region_data_api['data'])?$region_data_api['data']:[];
                    Log::record('register_data:'.print_r($region_data, true));
                    foreach ($region_data as $val) {
                        switch ($val['REGION_ID']){
                            case $province:
                                $up_data['province_name'] = $val['REGION_NAME'];
                                break;
                            case $city:
                                $up_data['city_name'] = $val['REGION_NAME'];
                                break;
                            case $country_town:
                                $up_data['country_town_name'] = $val['REGION_NAME'];
                                break;
                        }
                    }
                    /** 获取区域省市县数据 end **/
                    $up_data_ex = [
                        'management_model'=>$data['management_model']
                    ];
                    $verify_res = User::verifyRegisterData($data);
                    if ($verify_res['code'] === 0){
                        $res = $user_model->updateUserAndExBySeller_id($user_id, $up_data, $up_data_ex);
                        if ($res){
                            $rtn['url'] = url('Login/registerFinish','usid='.$user_id);
                            $rtn['code'] = 0;
                            $rtn['msg'] = 'success';
                        }else{
                            $rtn['msg'] = '数据提交失败，请重试';
                        }
                    }else{
                        $rtn['msg'] = $verify_res['msg'];
                    }
                    break;
                case 3:
                    break;
            }
        }
        return json($rtn);
    }

    /**
     * 登录功能
     * @return \think\response\Json
     */
    public function m_login(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '账号或密码错误';
        $data = Request::instance()->param(); //获取登录信息
        $user_name = $data['user_name'];//邮箱/用户名/手机号
        $password = $data['password'];
        $rule = [
            'user_name'  => 'require',
            'password' => 'require'
        ];
        $validate = new Validate($rule);
        if ($validate->check($data)){
            //账号密码校验
            $suer_model = new UserModel();
            $info = array();
            if (is_email($user_name)){
                $info = $suer_model->getInfoByEmail($user_name);
            }elseif (is_phone_num($user_name)){
                $info = $suer_model->getInfoByPhoneNum($user_name);
            }
            /*else{
                $info = $suer_model->getInfoByTrueName($user_name);
            }*/
            //if (!empty($info) && $info['password'] === md5($password)){
            if (!empty($info) && $info['password'] === get_seller_password($password)){
                if (in_array($info['status'],[0,1]) && in_array($info['is_delete'],[0]) ){
                    //账号密码校验通过，记录用户信息至session
                    session('user_data', [
                        'user_id'=>$info['id'],
                        'user_name'=>$info['true_name'],
                        'is_self_support'=>$info['is_self_support'],
                    ]);
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '不被允许的账号';
                }
            }/*else{
                $rtn['msg'] = '账号信息为空';
            }*/
        }else{
            Log::record('登录失败原因：'.print_r(json($rtn), true));
            Log::record(print_r($validate->getError(), true));
        }
        return json($rtn);
    }

    /**
     * 注销登录功能
     * @return \think\response\Json
     */
    public function m_logout(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '退出系统失败，请重试';
        session('user_data', null);
        $rtn['code'] = 0;
        $rtn['msg'] = 'success';
        $rtn['url'] = url('Login/index');
        return json($rtn);
    }

    /**
     * 获取地区
     * @return \think\response\Json
     */
    public function m_getRegion(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '请求错误';
        $region_id = input('post.region_id');
        if (!empty($region_id)){
            $base_api = new BaseApi();
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $base_api->getRegionDataWithRegionID($region_id);
        }
        return json($rtn);
    }

    /**
     * 发送手机验证码【多个地方调用，修改请注意兼容性】
     * @return \think\response\Json
     */
    public function m_sendSms(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '发送短信失败，请重试';
        $phone_num = input('post.phone_num');
        if (!empty($phone_num)){
            //生成验证码
            $sms_code = rand(10000,99999);
            $params = [
                'PhoneNumbers'=>$phone_num,
                'TemplateCode'=>'SMS_127161676',
                'TemplateParam'=>[
                    'code'=>$sms_code
                ],
            ];
            $res = send_sms($params);
            if ($res['Code'] == 'OK'){
                //将验证码 存储到session
                $codedata['verify_sms_code'] = $sms_code; // 把校验码保存到session
                $codedata['verify_sms_time'] = time(); // 验证码创建时间
                $key = authcode($sms_code);
                Session::set($key, $codedata, 'sms');
                $rtn['code'] = 0;
                $rtn['msg'] = '发送成功';
            }else{
                $rtn['msg'] = '短信发送失败';
            }
        }else{
            $rtn['msg'] = '手机号不能为空';
        }
        return json($rtn);
    }

    /**
     * 短信校验【多个地方调用，修改请注意兼容性】
     * @return \think\response\Json
     */
    public function m_smsVerify(){
        $sms_code = input('post.sms_code');
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '短信校验失败，请重新输入';
        $key = authcode($sms_code);
        $code_data = Session::get($key, 'sms');
        if (!empty($code_data) && !empty($code_data['verify_sms_code'])){
            //短信验证码失效校验
            $sms_expiry_time = config('aliyun_sms.expiryTime');
            $time_flag = time() - $code_data['verify_sms_time'];
            if ($time_flag < $sms_expiry_time){
                if ($code_data['verify_sms_code'] == $sms_code){
                    if (!empty(input('post.user_id')) && !empty(input('post.flag'))){ //来自找回密码发送短信
                        $user_id = input('post.user_id');
                        $flag = input('post.flag');
                        //发送邮件或手机验证码，且验证通过之后，则记录session（为了第四步判断流程正确性），将$token和$user_id、$flag（没太大作用，只是为了记录修改密码的方式）传给第四个页面，之后读取session值进行token判断
                        //跳转到找回密码第四步URL
                        $token = authcode($this->find_password_key.$user_id); //修改密码页面校验token
                        Session::set($token, $user_id);
                        $rtn['url'] = url('Login/PasswordfindFourth', ['token'=>$token, 'user_id'=>$user_id, 'flag'=>$flag]);
                    }
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                    session($key, null, 'sms');
                }else{
                    $rtn['msg'] = '验证码输入错误';
                }
            }else{
                $rtn['msg'] = '验证码已失效，请重新获取';
            }
        }else{
            $rtn['msg'] = '验证码错误或无效';
        }
        return json($rtn);
    }

    /**
     * 发送邮箱验证码
     * @return \think\response\Json
     */
    public function m_sendEmailCode(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '邮件发送失败，请重试';
        $data = input();
        if (!empty($data) && !empty($data['user_id'])){
            $user_id = $data['user_id'];
            $user_model = new UserModel();
            $user_info = $user_model->getInfoById($user_id);
            if(!empty($user_info) && $user_info['is_delete'] != 1){
                $email = $user_info['email'];
                if (!empty($email) && is_email($email)){
                    //生成验证码
                    $email_code = rand(10000,99999);
                    //发送邮件调用邮件模板
                    $toemail = $email;
                    $name = $email;
                    //$subject = 'DX Seller 找回密码验证码'; //邮件标题
                    //$content = '您好，您发起了找回密码操作，相关校验码为：'.$email_code; //邮件内容
                    //$send_email_resp = send_mail($toemail,$name,$subject,$content);
                    $send_email_resp = Email::sendEmailForResetPassword($toemail, $name,['seller_name'=>$name,'reset_code'=>$email_code]);
                    if ($send_email_resp){
                        //将验证码 存储到session
                        $codedata['verify_email_code'] = $email_code; // 把校验码保存到session
                        $codedata['verify_email_time'] = time(); // 验证码创建时间
                        $key = authcode($email_code);
                        Session::set($key, $codedata, 'email_code');
                        $rtn['code'] = 0;
                        $rtn['msg'] = '发送成功';
                    }else{
                        $rtn['msg'] = $send_email_resp;
                    }
                }else{
                    $rtn['msg'] = '非法邮箱';
                }
            }else{
                $rtn['msg'] = '非法用户';
            }
        }else{
            $rtn['msg'] = '非法请求';
        }
        return json($rtn);
    }

    /**
     * 邮箱验证码校验
     * @return \think\response\Json
     */
    public function m_emailCodeVerify(){
        $email_code = input('post.email_code');
        $user_id = input('post.user_id');//用于修改密码token校验
        $flag = input('post.flag');//标识：//1-来源于邮箱方式，2-来源于电话方式
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '校验失败，请重新输入';
        $key = authcode($email_code);
        $code_data = Session::get($key, 'email_code');
        if (!empty($code_data) && !empty($code_data['verify_email_code'])){
            if ($code_data['verify_email_code'] == $email_code){
                if (time() - $code_data['verify_email_time'] > 15*60){ /** 邮箱验证码有效期：15分钟 **/
                    $rtn['msg'] = '邮箱验证码已失效（超过15分钟），请重新重新获取';
                }else{
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                    //发送邮件或手机验证码，且验证通过之后，则记录session（为了第四步判断流程正确性），将$token和$user_id、$flag（没太大作用，只是为了记录修改密码的方式）传给第四个页面，之后读取session值进行token判断
                    //跳转到找回密码第四步URL
                    $token = authcode($this->find_password_key.$user_id); //修改密码页面校验token
                    Session::set($token, $user_id);
                    $rtn['url'] = url('Login/PasswordfindFourth', ['token'=>$token, 'user_id'=>$user_id, 'flag'=>$flag]);
                }
                session($key, null, 'email_code'); //校验完毕，删除session
            }else{
                $rtn['msg'] = '验证码输入错误，请重新输入';
            }
        }else{
            $rtn['msg'] = '验证码错误或无效';
        }
        return json($rtn);
    }

    /**
     * 找回密码
     * @return \think\response\Json
     */
    public function m_PasswordFind(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '输入数据有误，请检查';
        $data = input();
        if (empty($data) || empty($data['type'])){
            $rtn['msg'] = '请输入必要数据';
        }else{
            $type = $data['type']; //类型：1-找回密码第一步
            $user_model = new UserModel();
            switch ($type){
                case 1://找回密码第一步
                    $name = trimall($data['name']);
                    $drag_key = $data['drag_key'];
                    if (!is_phone_num($name) && !is_email($name)){
                        $rtn['msg'] = '输入的邮箱或手机号错误';
                    }elseif(session('drag_key') != $drag_key){
                        $rtn['msg'] = '验证码错误';
                    }else{
                        $flag = 0;
                        if (is_email($name)){
                            $flag = 1;
                            $info = $user_model->getInfoByEmail($name);
                        }elseif (is_phone_num($name)){
                            $flag = 2;
                            $info = $user_model->getInfoByPhoneNum($name);
                        }
                        if (empty($info) || $info['is_delete'] == 1){
                            $rtn['msg'] = '无效的用户';
                        }else{
                            $rtn['code'] = 0;
                            $rtn['msg'] = 'success';
                            $rtn['url'] = url('Login/PasswordfindSecond', ['flag'=>$flag,'user_id'=>$info['id']]);
                        }
                    }
                    break;
                case 4: //找回密码操作
                    $token = trimall($data['token']);
                    $flag = trimall($data['flag']);
                    $user_id = trimall($data['user_id']);
                    $password = trimall($data['password']);
                    $confirm_password = trimall($data['confirm_password']);
                    if (
                        !empty($password)
                        && !empty($token)
                        && !empty($flag)
                        && !empty($user_id)
                        && !empty($confirm_password)
                        && $password == $confirm_password
                        && (get_str_length($password) >=6 && get_str_length($password)<=20)
                    ){
                        $token_code = Session::get($token);
                        if ($token_code == $user_id){ //校验token
                            $up_data = [
                                'password'=>get_seller_password($password),
                            ];
                            if ($user_model->updateUserAndExBySeller_id($user_id, $up_data)){
                                $rtn['code'] = 0;
                                $rtn['msg'] = 'success';
                                $rtn['url'] = url('Login/PasswordfindSuccess', ['token'=>$token, 'flag'=>$flag,'user_id'=>$user_id]);
                            }else{
                                $rtn['msg'] = '找回密码失败，请重试';
                            }
                        }else{
                            $rtn['msg'] = '非法访问';
                        }
                    }else{
                        $rtn['msg'] = '密码输入错误，请检查';
                    }
                    break;
                default:
                    break;
            }
        }
        return json($rtn);
    }

    public function test2(){
        Email::sendEmailForResetPassword('1007360726@qq.com', 'langzihu',['seller_name'=>'lthseller','reset_url'=>'http://langzihu.com']);
        /*$res = Base::getEmailTemplate(502)[0];
        print_r($res);*/
        /*print_r($_SESSION);
        echo session('18285110558');*/
    }
}
