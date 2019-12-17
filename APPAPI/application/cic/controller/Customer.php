<?php
namespace app\cic\controller;

use app\common\controller\AppBase;
use app\common\helpers\TokenHelper;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;
use think\Controller;
use app\cic\model\ThirdPartyCustomer as ThirdPartyCustomerModel;
use app\common\controller\Email;
use app\common\controller\FTPUpload;
use app\common\model\Customer as CustomerModel;
use AlibabaCloud\Client\AlibabaCloud;

class Customer extends AppBase
{
    protected $noNeedLogin = ['login', 'register', 'validatecustomer','passwordfind','sendsms'];
    /*
     * 登陆
     */
    public function login()
    {
        $request = request();
        $rule = [
            ['Telephone', 'require', "账号不能为空"],
            ['Password', 'length:6,20', "请输入6-20位密码"],
            ['validateCode', 'length:1,10', "验证码长度无效"],
            ['isvalidateCode', 'require|in:0,1,2', "isvalidateCode不能为空|isvalidateCode必须为数字"],//0 不需要要验证码 1 需要验证码 2 短信验证码
        ];
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return $this->result((object)null, 610, $validate);
        }
        $accountName = $params["Telephone"];

        $isvalidateCode = $params['isvalidateCode'];
        $validateCode = $params['validateCode'];
        $where = [];

        if ($isvalidateCode == 2) {
            //短信验证码登录
            $code = cache('VerificationCode_' . $accountName);
            if ($code !== md5($validateCode)) {
                return $this->result((object)null, 611, '验证码无效');
            };
            cache('VerificationCode_' . $accountName, null);
            $where['Telephone'] = $accountName;
        } else {
            $pwd = $params["Password"];
            $pwd = md5($pwd);
            $where['Telephone'] = $accountName;
            $where['Password'] = $pwd;
        }

        $userModel = new CustomerModel();
        $field = 'ID,NickName,Telephone';
        $user = $userModel->field($field)->where($where)->find();
        if (empty($user['ID'])) {
            $errorNum = cache("LOGIN_ERROR_NUM_" . $accountName);
            if (empty($errorNum)) {
                cache("LOGIN_ERROR_NUM_" . $accountName, 1, 60 * 5);
            } else {
                $errorNum = (int)$errorNum;
                $errorNum++;
                cache("LOGIN_ERROR_NUM_" . $accountName, $errorNum, 60 * 5);
            }
            return $this->result((object)null, 612, '用户名或者密码错误');
        }
        $TokenModel = new TokenHelper();
        $customerData = $user->toArray();
        $uid = $user['ID'];
        $customerData['token'] = $TokenModel->get_token($uid);
        return $this->result($customerData);
    }

    /*
     * 注册用户
     * @param string Email
     * @param string FirstName
     * @Return: array
     * */
    public function register()
    {
        $request = request();
        $rule = [
            ['Telephone', 'require|/^1\d{10}/', '手机号不能为空|用户名长度必须是6-50'],
            ['Password', 'require|length:6,50', '密码长度无效'],
            ['validateCode', 'require|length:1,10', "验证码长度无效"],
        ];
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return json(['code' => 1020, 'msg' => $validate]);
        }
        $regdata['Telephone'] = input("Telephone/s", '');
        $regdata['NickName'] = $regdata['Telephone'];
        $regdata['Password'] = input("Password/s");
        $validateCode = input("validateCode");
        $regdata['CreateOn'] = time();
        /*验证码是否正确*/
//        $code = cache('VerificationCode_' . $params['Telephone']);
//        if ($code !== md5($validateCode)) {
//            $data = ['code' => 1020, 'msg' => '验证码无效'];
//            return json($data);
//        };
        $regdata['Password'] = md5($regdata['Password']);

        $userModel = new CustomerModel();
        $old = $userModel->where('Telephone', $regdata['Telephone'])->find();
        if (!empty($old)) {
            return $this->result([], 601, '手机号已存在');
        }
        $res = $userModel->create($regdata);
        $uid = $res->ID;
        if ($res) {
            $TokenModel = new TokenHelper();
            cache('VerificationCode_' . $regdata['Telephone'], null);
            $field = 'ID,NickName,Telephone';
            $customer = $userModel->field($field)->where('Telephone', $regdata['Telephone'])->find();
            $customerData = $customer->toArray();
            $customerData['token'] = $TokenModel->get_token($uid);
            return $this->result($customerData);
        } else {
            return $this->result([], 602, '注册失败');
        }
    }

    /**
     * 找回密码
     */
    public function passwordFind()
    {
        $request = request();
        $rule = [
            ['Telephone', 'require|/^1\d{10}/', '手机号不能为空|用户名长度必须是6-50'],
            ['Password', 'require|length:6,50', '密码长度无效'],
            ['validateCode', 'require|length:1,10', "验证码长度无效"],
        ];
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return $this->result([], 606, $validate);
        }
        /*验证码是否正确*/
//        $code = cache('VerificationCode_' . $params['Telephone']);
//        if ($code !== md5($validateCode)) {
//            $data = ['code' => 1020, 'msg' => '验证码无效'];
//            return json($data);
//        };
        $arrays = array('Telephone' => $params['Telephone']);
        $userModel = new CustomerModel();
        $where['Telephone'] = $params['Telephone'];
        $Customer = $userModel->get($where);
        if (empty($Customer)) {
            return $this->result([], 608, '用户不存在');
        }
        $regdata['Password'] = md5($params['Password']);
        $res = $Customer->save($regdata);
        if($res!==false){
            return $this->result($res);
        }else{
            return $this->result(0, 605, '修改密码失败');
        }


    }

    /*
     * 验证用户手机号
     * @param string $Email
     * @Return: array
     * */
    public function validateCustomer()
    {
        $request = request();
        $rule = [
            ['Telephone', 'require|/^1\d{10}/', '手机号不能为空|用户名长度必须是6-50'],
        ];
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return $this->result([], 606, $validate);
        }
        $telephone = $params['Telephone'];
        $where['Telephone'] = $telephone;
        $CustomerModel = new CustomerModel();
        $res = $CustomerModel->get($where);
        if (!empty($res)) {
            //手机号已经存在
            return $this->result(1);
        } else {
            return $this->result(0,200,'账户不存在');
        }
    }

    /*
    * 根据token获取用户信息
    * @param  cicID token选填
    * @Return: array
    * */
    public function getCustomerByToken()
    {
        $where['ID'] = $this->uid;
        $userModel = new CustomerModel();
        $field = 'ID,NickName,Telephone,PhotoPath';
        $user = $userModel->field($field)->where($where)->find();
        if (empty($user)) {
            $this->result('', 605, '用户信息不存在');
        }
        $userData = $user->toArray();
        $userData['PhotoPath'] = IMG_USER . $userData['PhotoPath'];
        return $this->result($user);
    }

    /*
    * 保存用户信息
    * @param  cicID token选填
    * @Return: array
    * */
    public function saveUserData()
    {
        $request = request();
        $rule = [
            ['NickName', 'length:1,50', "昵称长度必须是1-50"],
            ['PhotoPath', 'length:1,300', "头像路径无效"],
        ];
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return $this->result(0, 603, $validate);
        }
        $customerModel = (new CustomerModel())->get($this->uid);
        $customer = [];
        $nickname = trim(input("NickName/s", ''));
        if (!empty($nickname)) {
            $customer['NickName'] = $nickname;
        }
        $photo_path = trim(input("PhotoPath/s", ""));
        if (!empty($photo_path)) {
            //删除原图片
            if (!empty($customerModel->PhotoPath)) {
                $upload_dir = config('upload_dir');
                unlink($upload_dir . $customerModel->PhotoPath);
            }
            $customer['PhotoPath'] = $photo_path;
        }
        $res = $customerModel->save($customer);
        if ($res !== false) {
            return $this->result(1);
        } else {
            return $this->result(0, 604, '保存失败');
        }
    }

    public function sendSms()
    {
        $request = request();
        $rule = [
            ['telephone', 'require|/^1\d{10}$/', '手机号不能为空|用户名长度必须是6-50'],
        ];
        $params = $request->param();
        $validate = $this->validate($params, $rule);
        if (true !== $validate) {
            return json(['code' => 1020, 'msg' => '请输入有效的手机号码']);
        }
        $phone = input("telephone");
        $phoneSendNumber = cache("SEND_SMS_NUMBER_MOBILE_" . $phone);
        if (empty($phoneSendNumber)) {
            $phoneSendNumber = 0;
        } else {
            $phoneSendNumber = (int)$phoneSendNumber;
        }
        if ($phoneSendNumber >= 10) {
            return $this->result(0,1020,'每天只能发生十条');
        }
        $code = mt_rand(100000, 999999);
        AlibabaCloud::accessKeyClient('LTAI4Fc3DDNCGREyJeP93GSA', 'mfJ2kMEX2uASu2ANtYHBmO5P1qLBXH')
            ->regionId('cn-hangzhou')// replace regionId as you need
            ->asDefaultClient();
        $result = AlibabaCloud::rpc()
            ->product('Dysmsapi')
            // ->scheme('https') // https | http
            ->version('2017-05-25')
            ->action('SendSms')
            ->method('POST')
            ->connectTimeout(5)
            ->timeout(5)
            ->host('dysmsapi.aliyuncs.com')
            ->options([
                'query' => [
                    'RegionId' => "default",
                    'PhoneNumbers' => "$phone",
                    'SignName' => "易购",
                    'TemplateCode' => "SMS_174026802",
                    'TemplateParam' => "{\"code\":\"$code\"}",
                ],
            ])
            ->request();
        if (isset($result['Code']) && $result['Code'] == "OK") {
//          $ipSendNumber++;
            $phoneSendNumber++;
//          cache("SEND_SMS_NUMBER_IP_" . $ip, $ipSendNumber, 60 * 60 * 24);
            cache("SEND_SMS_NUMBER_MOBILE_" . $phone, $phoneSendNumber, 60 * 60 * 24);
            cache('VerificationCode_' . $phone, md5($code), 5 * 60);
            return $this->result(1);
        } else {
            return $this->result(0,1020,'系统繁忙，请重试');
        }

    }

    /*
     * 更改用户状态
     * */
    public function updateStatus()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.updateStatus");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            if (!isset($paramData['ID'])) {
                return apiReturn(['code' => 1001]);
            }
            if (!isset($paramData['Status'])) {
                return apiReturn(['code' => 1001]);
            }
            if (isset($paramData['Remarks'])) {
                $data['Remarks'] = $paramData['Remarks'];
            }
            $data['ID'] = $paramData["ID"];
            $data['Status'] = $paramData["Status"];
            $data['UpdateTime'] = time();
            $data['LastUpdateTime'] = time();
            $Customer = model('Customer');
            $res = $res = $Customer->updateStatus($data);
            if (!empty($res)) {
                return apiReturn(['code' => 200, 'data' => $res]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }




    /*
     * 用户资料设置
     * @param string Email
     * @param string FirstName
     * @param string LastName
     * @param int Gender 性别
     * @param date  Birthday 生日
     * @param string  CountryCode 国家编号
     * @param srting RegionCode  地区编号
     * @param string PhotoPath  头像地址
     * @Return: array
     * */
    public function saveProfile($profile_data = '')
    {
        try {
            $paramData = !empty($profile_data) ? $profile_data : request()->post();
            $validate = $this->validate($paramData, "Customer.saveProfile");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = $paramData['ID'];
            $Email = !empty($paramData['Email']) ? $paramData['Email'] : '';
            $Status = isset($paramData['Status']) ? $paramData['Status'] : '';
            $Password = !empty($paramData['Password']) ? $paramData['Password'] : '';
            if (!empty($Status)) {
                $data['Status'] = $Status;
            } elseif (!empty($Password)) {
                $data['Password'] = encry_password($Password);
            } else {
                if (!empty($paramData['FirstName'])) {
                    $data['FirstName'] = $paramData['FirstName'];
                }
                if (!empty($paramData['LastName'])) {
                    $data['LastName'] = $paramData['LastName'];
                }
                if (!empty($paramData['Gender'])) {
                    $data['Gender'] = $paramData['Gender'];
                }
                if (!empty($paramData['CountryCode'])) {
                    $data['CountryCode'] = $paramData['CountryCode'];
                }
                if (!empty($paramData['PhotoPath'])) {
                    $data['PhotoPath'] = $paramData['PhotoPath'];
                }
                if (!empty($paramData['Birthday'])) {
                    $data['Birthday'] = $paramData['Birthday'];
                }
            }

            $Customer = model('Customer');
            if (!$ID) {
                return apiReturn(['code' => 1001]);
            }
            if (!empty($Email)) {
                $email_array = explode("@", $Email);
                $data['UserName'] = $email_array[0];
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'Customer', 'EmailUserName');//加密邮件前缀
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = $EmailDomainName;
            }
            $data['UpdateTime'] = time();
            $data = array_filter($data);
            $res = $Customer->saveProfile($ID, $data);
            if ($res !== false) {
                return apiJosn(['code' => 200]);
            } else {
                return apiJosn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiJosn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 修改密码
     * @param int $ID
     * @param string Old_Password
     * @param string New_Password
     * @Return: array
     * */
    public function changePassword()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.changePassword");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = input("post.ID/d");
            $Old_Password = input("post.Old_Password");
            $New_Password = input(("post.New_Password"));
            if (strlen($New_Password) < 6) {
                return apiReturn(['code' => 1048]);
            }
            $Customer = model('Customer');
            $res = $Customer->confirmPassword(['ID' => $ID], encry_password($Old_Password));
            if ($res > 0) {
                /*判断是否跟支付密码相同*/
                $PaymentPassword = model('PaymentPassword');
                $confirmPaymentPassword = $PaymentPassword->confirmPaymentPassword(['CustomerID' => $ID], paypwd_encryption($New_Password));
                if ($confirmPaymentPassword) {
                    return apiReturn(['code' => 1050, 'msg' => "Cann't equal to the Payment Password ."]);
                }
                $res = $Customer->changePassword(['ID' => $ID], encry_password($New_Password));
                if ($res) {
                    return apiReturn(['code' => 200]);
                } else {
                    return apiReturn(['code' => 1002]);
                }
            } else {
                return apiReturn(['code' => 1009, 'msg' => 'wrong password']);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 检测用户是否设置支付密码
     * @param int $ID
     * @Return: array
     * */
    public function checkPaymentPassword()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.checkPaymentPassword");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $CustomerID = input("post.CustomerID/d");
            if (empty($CustomerID)) {
                return apiReturn(['code' => 1001]);
            }
            $PaymentPassword = model('PaymentPassword');
            $res = $PaymentPassword->checkPaymentPassword($CustomerID);
            if ($res != true) {
                $PaymentPasswordExistCheck = $PaymentPassword->PaymentPasswordExistCheck($CustomerID);
                if (!$PaymentPasswordExistCheck) {
                    return apiReturn(['code' => 200, 'data' => false]);
                } else {
                    return apiReturn(['code' => 200, 'data' => true]);
                }
            }
            return apiReturn(['code' => 200, 'data' => $res]);
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 修改支付密码
     * @param int $ID
     * @param string Old_Password(可选)
     * @param string Password
     * @Return: array
     * */
    public function savePaymentPassword()
    {
        try {
            $CustomerID = input("post.CustomerID/d");
            $Old_Password = input("post.Old_Password");
            $Password = input("post.Password");
            $validate = $this->validate(['PaymentPassword' => $Password], "PaymentPassword.save");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            //return $this->validate(['PaymentPassword'=>$Password],"PaymentPassword.save");
            if (empty($CustomerID)) {
                return apiReturn(['code' => 1001]);
            }
            if (strlen($Password) < 6 || strlen($Password) > 20) {
                return apiReturn(['code' => 1048]);
            }

            $PaymentPassword = model('PaymentPassword');
            if (!empty($Old_Password)) {
                $res = $PaymentPassword->confirmPaymentPassword(['CustomerID' => $CustomerID], paypwd_encryption($Old_Password));
                if ($res != true) {
                    return apiReturn(['code' => 1009]);
                }
                $data['PaymentPassword'] = paypwd_encryption($Password);
                $data['LastUpdateTime'] = time();
                $res = $PaymentPassword->savePaymentPassword($data, ['CustomerID' => $CustomerID]);
            } else {
                /*判断是否跟登录密码相同*/
                $login_password = encry_password($Password);
                $LoginPasswordCount = model('Customer')->confirmPassword(['ID' => $CustomerID], $login_password);
                if ($LoginPasswordCount > 0) {
                    return apiReturn(['code' => 1050]);
                }
                $IsSetPaymentPassword = $PaymentPassword->checkPaymentPassword($CustomerID);
                $data['CustomerID'] = $CustomerID;


                $data['PaymentPassword'] = paypwd_encryption($Password);
                if ($IsSetPaymentPassword) {
                    $data['LastUpdateTime'] = time();
                    $res = $PaymentPassword->savePaymentPassword($data, ['CustomerID' => $CustomerID]);
                } else {
                    $data['CreateOn'] = time();
                    $res = $PaymentPassword->savePaymentPassword($data);
                }

            }
            if ($res) {
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*判断密码是否正确*/
    public function confirmPaymentPassword($CustomerID = '', $Password = '')
    {
        try {
            $CustomerID = input("post.CustomerID/d", $CustomerID);
            $Password = input("post.Password", $Password);
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.confirmPaymentPassword");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $PaymentPassword = model('PaymentPassword');
            $Customer = model('Customer');
            $issetPayPwd = $PaymentPassword->checkPaymentPassword($CustomerID);
            /*是否在新平台设置了支付密码*/
            if ($issetPayPwd) {
                $res = $PaymentPassword->confirmPaymentPassword(['CustomerID' => $CustomerID], paypwd_encryption($Password));
                if ($res != true) {
                    return apiReturn(['code' => 1016]);
                } else {
                    return apiReturn(['code' => 200]);
                }
            } else {
                $IsNewData = $Customer->checkIsNewByID($CustomerID);
                if (empty($IsNewData)) {
                    return apiReturn(['code' => 1021]);
                }
                /*是否是新用户，不是新用户*/
                if ($IsNewData['IsNew'] == 0) {
                    $PaymentPasswordExistCheck = $PaymentPassword->PaymentPasswordExistCheck($CustomerID);
                    if (!$PaymentPasswordExistCheck) {
                        return apiReturn(['code' => 1021]);
                    }
                    $res = $PaymentPassword->PaymentPasswordCorrectnessCheck($Password, $CustomerID);
                    if ($res != true) {
                        return apiReturn(['code' => 1016]);
                    } else {
                        $data['CustomerID'] = $CustomerID;
                        $data['PaymentPassword'] = paypwd_encryption($Password);
                        $data['CreateOn'] = time();
                        $PaymentPassword->savePaymentPassword($data);
                        return apiReturn(['code' => 200]);
                    }
                } else {
                    return apiReturn(['code' => 1002, 'msg' => "You have not set the payment password."]);
                }
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    //SC存在判断密码是否正确
    public function confirmScPaymentPassword()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.confirmScPaymentPassword");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $CustomerID = input("post.CustomerID/d");
            $Password = input("post.Password");
            $StoreCardWhere['CustomerID'] = $CustomerID;
            $StoreCardWhere['CurrencyType'] = input("post.CurrencyType");
            $StoreCardWhere = array_filter($StoreCardWhere);
            $PaymentPassword = model('PaymentPassword');
            if (empty($CustomerID) || empty($Password)) {
                return apiReturn(['code' => 1001]);
            }
            $confirmPaymentPassword = $this->confirmPaymentPassword($CustomerID, $Password);
            if ($confirmPaymentPassword['code'] != 200) {
                return $confirmPaymentPassword;
            }
            $res = $PaymentPassword->confirmPaymentPassword(['CustomerID' => $CustomerID], paypwd_encryption($Password));
            if ($res != true) {
                return apiReturn(['code' => 1016]);
            }
            $sc_info = model("StoreCarditBasicInfo")->getStoreCarditBasicInfo($StoreCardWhere);
            if (!$sc_info) {
                return apiReturn(['code' => 1040]);
            } else {
                if ($sc_info['UsableAmount'] <= 0) {
                    return apiReturn(['code' => 1002, 'msg' => "Store Cardit is zero"]);
                }
            }
            return apiReturn(['code' => 200, 'data' => $sc_info]);
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    public function getUpdateTime()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.getUpdateTime");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $where['ID'] = input("post.ID/d");
            $db = Db::connect('db_cic');
            $res = $db->name('customer')->where($where)->value('UpdateTime');
            return apiReturn(['code' => $res]);
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*
     * 添加用户其他信息
     * */
    public function addCustomerOther()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.addCustomerOther");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $data['CustomerID'] = input("post.CustomerID");
            $data['BrowserAttr'] = input("post.BrowserAttr");
            $data['BrowserVersion'] = input("post.BrowserVersion");
            $data['system'] = input("post.system");
            $data['IPAddress'] = input("post.IPAddress");
            $data['Country'] = input("post.Country");
            $data['Province'] = input("post.Province");
            $data['City'] = input("post.City");
            $data['CreateTime'] = time();
            $Customer = model('Customer');
            $res = $Customer->addCustomerOther($data);
            if ($res > 0) {
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 添加系统操作日志
     * */
    public function addSystemLog()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.addSystemLog");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $CustomerID = input("post.CustomerID");
            if (empty($CustomerID)) {
                return apiReturn(['code' => 1001]);
            }
            $data['CustomerID'] = $CustomerID;
            $data['IPAddress'] = input("post.IPAddress");
            $data['OperationName'] = input("post.OperationName");
            $data['DataCategory'] = input("post.DataCategory");
            $data['OperateType'] = input("post.OperateType");;
            $data['Description'] = input("post.Description");
            $data['OperateTime'] = time();
            $Customer = model('Customer');
            $res = $Customer->addSystemLog($data);
            if ($res > 0) {
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
    * 添加错误日志
    * */
    public function addErrorLog()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.addErrorLog");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $RefID = input("post.RefID");
            if (empty($RefID)) {
                return apiReturn(['code' => 1001]);
            }
            $data['RefID'] = $RefID;
            $data['Email'] = input("post.Email");
            $data['ErrorInfo'] = input("post.ErrorInfo");
            $data['BatchNumber'] = input("post.BatchNumber");
            $data['SourceType'] = input("post.SourceType");
            $data['ActivityType'] = input("post.ActivityType");
            $data['OperationType'] = input("post.OperationType");
            $data['TimeStamp'] = time();
            $Customer = model('Customer');
            $res = $Customer->addErrorLog($data);
            if ($res > 0) {
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*
    * 开通用户Affiliate模块
    * */
    public function openAffiliate()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.openAffiliate");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = input("post.ID/d");
            if (empty($ID)) {
                $lastID = model("Customer")->getLastAffiliateID();
                if ($lastID) {
                    $data['RCode'] = 100000001 + (int)$lastID;
                } else {
                    $data['RCode'] = 100000001;
                }
            } else {
                $data["ID"] = $ID;
            }
            $data['CustomerID'] = input("post.CustomerID/d");
            if (empty($data['CustomerID'])) {
                return apiReturn(['code' => 1001]);
            }
            $Email = input("post.Email");
            if (is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@", $Email);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'AffiliateLevel', 'PayPalEU');//加密邮件前缀
                $data['WebsiteURL'] = input("post.WebsiteURL");
                $data['RegistrationTimestamp'] = time();
                $data['Active'] = input("post.Active", 0);
                $data['CommissionRate'] = input("post.CommissionRate", 0);
                $data['IsPartner'] = input("post.Partner", 0);
                $data['LastChangeLevelTime'] = time();
                $data['Notes'] = input("post.Notes");
                $data['LevelIndex'] = input("post.LevelIndex", 0);
                $data['PayPalEU'] = $EmailUserName;
                $data['PayPalED'] = $EmailDomainName;
                $data['IsNew'] = input("post.IsNew", 1);
                $res = model("Affiliate")->saveAffiliateLevel($data);
                if ($res > 0) {
                    $log['LevelID'] = $res;
                    $log['OldLevelIndex'] = 0;
                    $log['NewLevelIndex'] = $data['LevelIndex'];
                    $log['CommissionRate'] = $data['CommissionRate'];
                    $log['Notes'] = "开通AffiliateLevel";
                    $this->addAffiliateLevelLog($log);
                    return apiReturn(['code' => 200]);
                } else {
                    return apiReturn(['code' => 1002]);
                }
            } else {
                return apiReturn(['code' => 1007]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     *
     * */
    public function addAffiliateLevelLog()
    {
        $paramData = request()->post();
        $validate = $this->validate($paramData, "Customer.addAffiliateLevelLog");
        if (true !== $validate) {
            return apiReturn(['code' => 1002, "msg" => $validate]);
        }
        $data['LevelID'] = input("post.LevelID/d");
        if (empty($data['LevelID'])) {
            return apiReturn(['code' => 1001]);
        }
        $data['LevelID'] = input("post.LevelID");
        $data['OldLevelIndex'] = input("post.OldLevelIndex", 0);
        $data['NewLevelIndex'] = input("post.NewLevelIndex");
        $data['CommissionRate'] = input("post.CommissionRate", 0);
        $data['Notes'] = input("post.Notes");
        $data['ChangeTime'] = time();
        $res = model("Affiliate")->addAffiliateLevelLog($data);
        if ($res > 0) {
            return apiReturn(['code' => 200]);
        } else {
            return apiReturn(['code' => 1002]);
        }
    }

    /*
     * 激活affiliate账号
     * */
    public function activeAffiliateLevel()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.activeAffiliateLevel");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = input("post.ID/d");
            if (empty($ID)) {
                return apiReturn(['code' => 1001]);
            }
            $data["ID"] = $ID;
            $data['Active'] = input("post.Active", 1);
            $data['LastChangeLevelTime'] = time();
            $res = model("Affiliate")->saveAffiliateLevel($data);
            if ($res > 0) {
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 更改affiliate
     * */
    public function editAffiliateLevel()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.editAffiliateLevel");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = input("post.ID/d");
            if (empty($ID)) {
                return apiReturn(['code' => 1001]);
            }
            $where["ID"] = $ID;
            $data = model("Affiliate")->getAffiliateLevel($where);
            $OldLevelIndex = $data['LevelIndex'];
            $data['CommissionRate'] = input("post.CommissionRate", 0);
            $data['LastChangeLevelTime'] = time();
            $data['Notes'] = input("post.Notes");
            $data['LevelIndex'] = input("post.LevelIndex", 0);
            $data = array_filter($data);
            $res = model("Affiliate")->saveAffiliateLevel($data);
            if ($res > 0) {
                $log['LevelID'] = $ID;
                $log['OldLevelIndex'] = $OldLevelIndex;
                $log['NewLevelIndex'] = $data['LevelIndex'];
                $log['CommissionRate'] = $data['CommissionRate'];
                $log['Notes'] = "更改AffiliateLevel";
                $this->addAffiliateLevelLog($log);
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 更改affiliate
     * */
    public function editAffiliateEmail()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.editAffiliateEmail");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = input("post.ID/d");
            $CustomerID = input("post.CustomerID/d");
            $Email = input("post.Email");
            if (empty($ID) && empty($CustomerID)) {
                return apiReturn(['code' => 1001]);
            }
            if (empty($ID) && empty($CustomerID)) {
                return apiReturn(['code' => 1001]);
            }
            if (!empty($ID)) {
                $where["ID"] = $ID;
            }
            if (!empty($CustomerID)) {
                $where["CustomerID"] = $CustomerID;
            }
            if (is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@", $Email);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'AffiliateLevel', 'PayPalEU');//加密邮件前缀
                $data['PayPalEU'] = $EmailUserName;
                $data['PayPalED'] = $EmailDomainName;
            }
            $Affiliate_where['PayPalEU'] = $EmailUserName;
            $Affiliate_where['PayPalED'] = $EmailDomainName;
            $res = model("Affiliate")->saveAffiliateLevel($data, $where);
            if ($res > 0) {
                $log['LevelID'] = $res;
                if (isset($data['LevelIndex'])) {
                    $log['NewLevelIndex'] = $data['LevelIndex'];
                }
                if (isset($data['CommissionRate'])) {
                    $log['CommissionRate'] = $data['CommissionRate'];
                }
                $log['Notes'] = "更改PayPal Email Address";
                $this->addAffiliateLevelLog($log);
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 200]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 获取AffiliateLevel详情
     * */
    public function getAffiliateLevel()
    {
        try {
            $ID = input("post.ID/d");
            $CustomerID = input("post.CustomerID/d");
            $RCode = input("post.RCode/d");
            if (empty($ID) && empty($CustomerID) && empty($RCode)) {
                return apiReturn(['code' => 1001]);
            }
            if (!empty($ID)) {
                $where['ID'] = $ID;
            }
            if (!empty($CustomerID)) {
                $where['CustomerID'] = $CustomerID;
            }
            if (!empty($RCode)) {
                $where['RCode'] = $RCode;
            }
            $res = model("Affiliate")->getAffiliateLevel($where);
            if ($res) {
                vendor('aes.aes');
                $aes = new aes();
                if ($res['PayPalEU']) {
                    $EmailUserName = $aes->decrypt($res['PayPalEU'], 'AffiliateLevel', 'PayPalEU');//加密邮件前缀
                    $res['email'] = $EmailUserName . '@' . $res['PayPalED'];
                }
                return apiReturn(['code' => 200, 'data' => $res]);
            } else {
                return apiReturn(['code' => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 通过CustomerId获取EmailId
     * */
    /*
     * 通过CustomerId获取EmailId
     * */
    public function GetEmailByCustomerID()
    {
        try {
            $CustomerID = input("CustomerId/d");
            if (empty($ID) && empty($CustomerID)) {
                return apiReturn(['code' => 1001]);
            }
            $Customer = model('Customer');
            $CustomerData = $Customer->getCustomer($CustomerID);
            vendor('aes.aes');
            $aes = new aes();
            $email = '';
            if ($CustomerData['EmailUserName']) {
                $EmailUserName = $aes->decrypt($CustomerData['EmailUserName'], 'Customer', 'EmailUserName');//加密邮件前缀
                $email = $EmailUserName . '@' . $CustomerData['EmailDomainName'];
            }
            if ($email) {
                return apiReturn(['code' => 200, 'OperationStatus' => 0, 'ErrorInfos' => "Success", 'Email' => $email]);
            } else {
                return apiReturn(['code' => 1006, 'OperationStatus' => 1, 'ErrorInfos' => "operation failed"]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 通过CustomerId获取EmailId
     * */
    public function CheckSimpleEmail()
    {
        $Customer = model('Customer');
        $paramData = input();
        if (!isset($paramData['EmailAddress'])) {
            return apiReturn(['code' => 1001]);
        }
        if (is_email($paramData['EmailAddress']) != true) {//传入账号是否为邮箱
            return apiReturn(['code' => 1002, 'msg' => "Incorrect mailbox format", 'OperationStatus' => 1, 'ErrorInfos' => "Incorrect mailbox format"]);
        }
        $email_array = explode("@", $paramData['EmailAddress']);
        $EmailDomainName = $email_array[1];
        vendor('aes.aes');
        $aes = new aes();
        $EmailUserName = $aes->encrypt($email_array[0], 'Customer', 'EmailUserName');//加密邮件前缀
        $AddIfNone = isset($paramData['AddIfNone']) ? $paramData['AddIfNone'] : 0;
        $where['EmailUserName'] = $EmailUserName;
        $where['EmailDomainName'] = $EmailDomainName;
        $CustomerEmail = $Customer->getCustomerEmail($where);
        if (!empty($CustomerEmail)) {
            return apiReturn(['code' => 200, 'OperationStatus' => 0, 'CheckedMessage' => "Success", 'EmailID' => $CustomerEmail['ID'], 'Existe' => 1]);
        } else {
            if ($AddIfNone) {
                $data['CustomerID'] = isset($paramData['CustomerID']) ? $paramData['CustomerID'] : '';
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = isset($EmailDomainName) ? $EmailDomainName : '';
                $data['EmailType'] = isset($paramData['EmailType']) ? $paramData['EmailType'] : 0;
                $data['CreateOn'] = time();
                $data['Enabled'] = isset($paramData['Enabled']) ? $paramData['Enabled'] : 0;
                $data['Notes'] = isset($paramData['Notes']) ? $paramData['Notes'] : '';
                $CustomerEmail = $Customer->addCustomerEmail($data);
                if ($CustomerEmail) {
                    return apiReturn(['code' => 200, 'OperationStatus' => 0, 'CheckedMessage' => "Success", 'EmailID' => $CustomerEmail, 'NewAdded' => 1]);
                } else {
                    return apiReturn(['code' => 1002, 'OperationStatus' => 1, 'CheckedMessage' => "error", 'EmailID' => $paramData['EmailAddress'], 'ExceptionOccured' => 1]);
                }
            } else {
                return apiReturn(['code' => 200, 'msg' => "no data,Not added", 'OperationStatus' => 0, 'CheckedMessage' => "Success", 'EmailID' => 0]);
            }
        }
    }

    /*
     * 根据用户ID获取用户详细信息
     * */
    public function GetCustomerInfoById()
    {
        $ID = input("Id/d");
        $Customer = model('Customer');
        if (empty($ID)) {
            return apiReturn(['code' => 1001]);
        }
        //$type = input("type",1);
        $res = $Customer->getCustomer($ID, 0);
        vendor('aes.aes');
        $aes = new aes();
        if ($res['EmailUserName']) {
            $EmailUserName = $aes->decrypt($res['EmailUserName'], 'Customer', 'EmailUserName');//加密邮件前缀
            $res['email'] = $EmailUserName . '@' . $res['EmailDomainName'];
        }
        if (!empty($res)) {
            $res_data['CustomerId'] = $res['ID'];
            $res_data['Email'] = $res['email'];
            $res_data['UserName'] = $res['UserName'];
            $res_data['SiteId'] = $res['SiteID'];
            $res_data['Status'] = $res['Status'];
            $res_data['FirstName'] = $res['FirstName'];
            $res_data['LastName'] = $res['LastName'];
            $res_data['MiddleName'] = $res['MiddleName'];
            $res_data['Gender'] = $res['Gender'];
            $res_data['Education'] = $res['Education'];
            $res_data['MaritalStatus'] = $res['MaritalStatus'];
            $res_data['CountryCode'] = $res['CountryCode'];
            $res_data['Hobby'] = $res['Hobby'];
            $res_data['Income'] = $res['Income'];
            $res_data['Birthday'] = $res['Birthday'];
            $res_data['CreateOn'] = isset($res['CreateOn']) ? date("Y-m-d", $res['CreateOn']) : '';
            $res_data['RegisterOn'] = isset($res['RegisterOn']) ? date("Y-m-d", $res['RegisterOn']) : '';
            $res_data['LastLoginDate'] = isset($res['LastLoginDate']) ? date("Y-m-d", $res['LastLoginDate']) : '';
            return apiReturn(['code' => 200, 'data' => $res_data]);
        } else {
            return apiReturn(['code' => 1006]);
        }
    }

    /*
     * 根据ID获取邮箱
     * */
    public function GetEmailsByCIDs()
    {
        try {
            $post_data = request()->post();
            if (!isset($post_data['ids'])) {
                return apiReturn(['code' => 1001]);
            }
            $Customer = model('Customer');
            $IsSubscriber = isset($post_data['IsSubscriber']) ? $post_data['IsSubscriber'] : '';
            $res = $Customer->GetEmailsByCIDs($post_data['ids'], $IsSubscriber);
            if ($res) {
                vendor('aes.aes');
                $aes = new aes();
                $email_array = array();
                foreach ($res as $key => $value) {
                    if ($value['EmailUserName']) {
                        $EmailUserName = $aes->decrypt($value['EmailUserName'], 'Customer', 'EmailUserName');//加密邮件前缀
                        $email_array[$key]['CustomerId'] = $value["ID"];
                        $email_array[$key]['Email'] = $EmailUserName . '@' . $value['EmailDomainName'];
                    }
                }

                return apiReturn(['code' => 200, 'data' => $email_array]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 根据ID获取邮箱
     * */
    public function getEmailsByCID()
    {
        try {
            $post_data = input();
            if (!isset($post_data['id'])) {
                return apiReturn(['code' => 1001]);
            }
            $Customer = model('Customer');
            $IsSubscriber = isset($post_data['IsSubscriber']) ? $post_data['IsSubscriber'] : '';
            $res = $Customer->getEmailsByCID($post_data['id'], $IsSubscriber);
            if ($res) {
                vendor('aes.aes');
                $aes = new aes();
                if ($res['EmailUserName']) {
                    $EmailUserName = $aes->decrypt($res['EmailUserName'], 'Customer', 'EmailUserName');//解密邮件前缀
                    $email = $EmailUserName . '@' . $res['EmailDomainName'];
                }
                return apiReturn(['code' => 200, 'data' => $email]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     *
     * */
    public function AddOrUpdateCustomerTaxId()
    {
        try {
            $post_data = input();
            if (!isset($post_data['Cicid'])) {
                return apiReturn(['code' => 1001]);
            }
            $data['Cicid'] = $post_data['Cicid'];
            //
            if (isset($post_data['IdType'])) {
                $data['TaxIdType'] = $post_data['IdType'];
            }

            //
            if (isset($post_data['TaxId'])) {
                $data['TaxId'] = $post_data['TaxId'];
            }

            //
            if (isset($post_data['PersonalId'])) {
                $data['PersonalId'] = $post_data['PersonalId'];
            }
            $data['CreateTime'] = time();
            $CustomerTaxInfo = model('CustomerTaxInfo');
            $res = $CustomerTaxInfo->AddOrUpdateCustomerTaxId($data);
            if ($res) {
                return apiReturn(['code' => 200]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     *根据用户ID获取用户Tax信息
     * */
    public function FindCustomerTaxId()
    {
        try {
            $post_data = input();
            if (!isset($post_data['Cicid'])) {
                return apiReturn(['code' => 1001]);
            }
            $where['Cicid'] = $post_data['Cicid'];
            if (!isset($post_data['IdType'])) {
                return apiReturn(['code' => 1001]);
            }
            $where['TaxIdType'] = $post_data['IdType'];
            $CustomerTaxInfo = model('CustomerTaxInfo');
            $res = $CustomerTaxInfo->FindCustomerTaxId($where);
            if ($res !== false) {
                return apiReturn(['code' => 200, 'data' => $res]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*获取后台用户详情数据*/
    public function getAdminCustomerInfo()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.getAdminCustomerInfo");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            if (isset($paramData['ID']) && !empty($paramData['ID'])) {
                $where['ID'] = $paramData['ID'];
            }
            if (isset($paramData['UserName']) && !empty($paramData['UserName'])) {
                $where['UserName'] = $paramData['UserName'];
            }
            if (isset($paramData['Email']) && !empty($paramData['Email'])) {
                $email_array = explode("@", $paramData['Email']);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'Customer', 'EmailUserName');//加密邮件前缀
                $where['EmailUserName'] = $EmailUserName;
                $where['EmailDomainName'] = $EmailDomainName;
            }
            $data = model("Customer")->getAdminCustomerInfo($where);
            vendor('aes.aes');
            $aes = new aes();
            if (isset($data['EmailUserName'])) {
                $EmailUserName = $aes->decrypt($data['EmailUserName'], 'Customer', 'EmailUserName');//加密邮件前缀
                $data['email'] = $EmailUserName . '@' . $data['EmailDomainName'];
            }
            if ($data) {
                return ["code" => 200, "data" => $data];
            } else {
                return ["code" => 1002];
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*
     * 自动注册用户
     * @param string Email
     * @param string FirstName
     * @param string LastName
     * @param string Password 密码
     * @param int SiteID 站点ID 可不填
     * @param int SourceType  可不填
     * @Return: array
     * */
    public function autoRegisterCustomer()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.autoRegisterCustomer");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $Email = input("Email/s");
            $Password = get_password();
            $SiteID = input("SiteID/d", 1);
            $SourceType = input("SourceType", 1);
            $CountryCode = input("CountryCode/s", "BR");
            $Customer = model('Customer');
            $Status = input("Status/d", 1);
            $ClientSource = input("ClientSource/d", 1);
            if (empty($Email)) {
                return apiReturn(['code' => 1001, 'msg' => 'Email can not null']);
            }
            if (is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@", $Email);
                $data['UserName'] = $email_array[0];
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'Customer', 'EmailUserName');//加密邮件前缀
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = $EmailDomainName;
                $data['FirstName'] = $data['UserName'];
                $data['LastName'] = $data['UserName'];
                $data['Password'] = !empty($Password) ? encry_password($Password) : encry_password(get_random_key(8));
                $data['SiteID'] = $SiteID;
                $data['SourceType'] = $SourceType;
                $data['CountryCode'] = $CountryCode;
                $data['Status'] = $Status;
                $data['ClientSource'] = $ClientSource;
                $validateCustomer = $this->validateCustomer(['Email' => $Email]);
                if ($validateCustomer['code'] != 200) {
                    return $validateCustomer;
                }

                $res = $Customer->addCustomer($data);
                if ($res > 0) {
                    /*用户订阅*/
                    $Subscriberdata['EmailUserName'] = $EmailUserName;
                    $Subscriberdata['EmailDomainName'] = $EmailDomainName;
                    $user_subscriber = model("Subscriber")->getSubscriber($Subscriberdata);
                    if (!$user_subscriber) {
                        $Subscriberdata['CustomerId'] = $res;
                        $Subscriberdata['Active'] = 1;
                        $Subscriberdata['SiteId'] = input("SiteId", 1);
                        $Subscriberdata['CreateTime'] = $Subscriberdata['AddTime'] = time();
                        model("Subscriber")->addSubscriber($Subscriberdata);
                    }
                    /*添加用户积分*/
                    $PointsBasic['CustomerID'] = $res;
                    $PointsBasic['Memo'] = "System creates account automatically.";
                    $PointsBasic['NewTotalCount'] = config('registration_bonus_points');
                    model("PointsBasicInfo")->addPoints($PointsBasic);
                    $res_data['ID'] = $res;
                    $res_data['Email'] = $Email;
                    $res_data['Password'] = $Password;
                    /*赠送优惠券*/
                    $RegisterCouponInfo = getRegisterCouponInfo();
                    if ($RegisterCouponInfo['CouponTime']['EndTime'] < strtotime('+1month')) {
                        $end_time = $RegisterCouponInfo['CouponTime']['EndTime'];
                    } else {
                        $end_time = strtotime('+1month');
                    }
                    $CouponParam['CouponId'] = config("register_coupon");
                    $CouponParam['flag'] = 2;
                    $Coupon = controller("mallextend/Coupon");
                    $Coupondata = $Coupon->getCouponCodeByCouponId($CouponParam);
                    $CouponCode = $Coupondata['data']['coupon_code'];
                    $addCoupon['customer_id'] = $res;
                    $addCoupon['coupon_id'] = config("register_coupon");
                    $addCoupon['coupon_sn'] = $CouponCode;
                    $addCoupon['is_used'] = 1;
                    $addCoupon['start_time'] = time();
                    $addCoupon['end_time'] = $end_time;
                    $addCoupon['type'] = $RegisterCouponInfo['DiscountType']['Type'];
                    $MyCoupon = controller("cic/MyCoupon");
                    $CouponRes = $MyCoupon->addCoupon($addCoupon);
                    /*TO DO 记录未发放优惠券成功日志*/
                    $send_email_resp = Email::sendEmail(
                        $Email,
                        11,
                        $data['UserName'],
                        [
                            'username' => $data['UserName'],
                            'new_email' => $Email,
                            'password' => $Password,
                        ]
                    );
                    return apiReturn(['code' => 200, 'data' => $res_data]);
                } else {
                    return apiReturn(['code' => 1002]);
                }
            } else {
                return apiReturn(['code' => 1007]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*
     * 根据用户ID、账号或邮箱批量获取用户信息
     * @Return: array
     * */
    public function getAdminCustomerData()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.getAdminCustomerData");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            if ($paramData['field_type'] == 3) {
                $user_data = array();
                foreach ($paramData['user_data'] as $key => $value) {
                    $email_array = explode("@", $value);
                    $EmailDomainName = $email_array[1];
                    vendor('aes.aes');
                    $aes = new aes();
                    $EmailUserName = $aes->encrypt($email_array[0], 'Customer', 'EmailUserName');//加密邮件前缀
                    $email_data['EmailUserName'] = $EmailUserName;
                    $email_data['EmailDomainName'] = $EmailDomainName;
                    $user_email_data = model("Customer")->getSendMsgCustomer($email_data);
                    if ($user_email_data) {
                        $user_data[] = model("Customer")->getSendMsgCustomer($email_data);
                    }
                }

            } elseif ($paramData['field_type'] == 2) {
                $where['ID'] = ['in', $paramData['user_data']];
                $user_data = model("Customer")->getSendMsgCustomer($where, 2);
            } elseif ($paramData['field_type'] == 1) {
                $where['UserName'] = ['in', $paramData['user_data']];
                $user_data = model("Customer")->getSendMsgCustomer($where, 2);
            }

            if ($user_data) {
                vendor('aes.aes');
                $aes = new aes();
                foreach ($user_data as $key => $val) {

                    if (isset($val['EmailUserName'])) {
                        $EmailUserName = $aes->decrypt($val['EmailUserName'], 'Customer', 'EmailUserName');//加密邮件前缀
                        $user_data[$key]['email'] = $EmailUserName . '@' . $val['EmailDomainName'];
                    }
                }
                return apiReturn(["code" => 200, "data" => $user_data]);
            } else {
                return apiReturn(["code" => 1002]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*上传用户头像*/
    public function updatePhotoPath()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.updatePhotoPath");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $res = $this->remoteUpload();
            if (isset($res['code']) && $res['code'] == 200) {
                $data['ID'] = $paramData["ID"];
                $data['PhotoPath'] = $res['url'];
                $res = $this->saveProfile($data);

                if ($res['code'] == 200) {
                    $res['data']['PhotoPath'] = $data['PhotoPath'];
                }
                return $res;
            }
            return $res;
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
    * 远程上传
    * */
    public function remoteUpload()
    {
        $localres = localUpload();
        if ($localres['code'] == 200) {
            $remotePath = config("ftp_config.UPLOAD_DIR")['PHOTO_IMAGES'] . date("Ymd");
            $config = [
                'dirPath' => $remotePath, // ftp保存目录
                'romote_file' => $localres['FileName'], // 保存文件的名称
                'local_file' => $localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            $baseurl = config('cdn_url_config.url');
            if ($upload) {
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['complete_url'] = $baseurl . $remotePath . '/' . $localres['FileName'];
                $res['url'] = $remotePath . '/' . $localres['FileName'];
            } else {
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            return $res;
        } else {
            return $localres;
        }
    }

}
