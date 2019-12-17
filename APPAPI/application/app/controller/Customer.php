<?php
namespace app\app\controller;

use app\app\dxcommon\BaseApi;
use app\common\controller\AppBase;
use app\app\dxcommon\CicApi;
use think\Exception;
use think\Log;
use think\Request;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;
use think\Controller;
use app\cic\model\ThirdPartyCustomer as ThirdPartyCustomerModel;
use app\common\controller\Email;
use app\common\controller\FTPUpload;

class Customer extends AppBase
{
    /*
    * 获取用户token是否存在，不存在新增，存在更改过期时间
    * @param int cicID
    * @param isremember 是否记住密码
    * @Return: array
    * */
    public function getToken($cicID)
    {
        try {
            if (empty($cicID)) {
                return apiReturn(['code' => 1001]);
            }
            $isremember = input("post.isremember/b", false);
            $Token = model('Token');
            if ($isremember) {
                $timeout = strtotime("+ 1day");
                $expires = 3600 * 24;
            } else {
                $timeout = strtotime("+ 7day");
                $expires = 3600 * 24 * 7;
            }
            $old_token = $Token->getToken(['cicID' => $cicID]);
            $token_data['cicID'] = $cicID;
            $token_data['timeout'] = $timeout;
            $token_data['isremember'] = $isremember;
            $token = guid();
            $token_data['token'] = $token;
            if (empty($old_token)) {
                $add_token = model("Token")->addToken($token_data);
                if ($add_token) {
                    return apiReturn(['code' => 200, 'data' => $token_data]);
                } else {
                    return apiReturn(['code' => 1001]);
                }
            } else {
                if ($old_token['timeout'] > time()) {
                    $token_data['token'] = $old_token['token'];
                }
                $where['cicID'] = $cicID;
                $update_token = model("Token")->updateToken($token_data, $where);
                if ($update_token) {
                    $token_data['expires'] = $expires;
                    return apiReturn(['code' => 200, 'data' => $token_data]);
                } else {
                    return apiReturn(['code' => 1001]);
                }
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*
     * 获取用户信息
     * @param int ID 用户ID
     * @Return: array
     * */
    public function getCustomerList()
    {
        try {

            $param_data = request()->post();
            $validate = $this->validate($param_data, "Customer.getCustomerList");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $Customer = model('Customer');
            $RegisterStart = input("post.RegisterStart");
            $RegisterEnd = input("post.RegisterEnd");
            $BirthdayStart = input("post.BirthdayStart");
            $BirthdayEnd = input("post.BirthdayEnd");
            $CountryCode = input("post.CountryCode");
            $query = isset($param_data['query']) ? $param_data['query'] : '';
            if (!empty($RegisterStart) && !empty($RegisterEnd)) {
                $where['RegisterOn'] = ["between", [strtotime($RegisterStart), strtotime($RegisterEnd)]];
            } else {
                /*成交开始时间*/
                if (!empty($RegisterStart)) {
                    $where['RegisterOn'] = ['gt', strtotime($RegisterStart)];
                }

                /*成交结束时间*/
                if (!empty($RegisterEnd)) {
                    $where['RegisterOn'] = ['lt', strtotime($RegisterEnd)];
                }
            }
            if (!empty($BirthdayStart) && !empty($BirthdayEnd)) {
                $where['Birthday'] = ["between", [strtotime($BirthdayStart), strtotime($BirthdayEnd)]];
            } else {
                /*成交开始时间*/
                if (!empty($BirthdayStart)) {
                    $where['Birthday'] = ['gt', strtotime($BirthdayStart)];
                }

                /*成交结束时间*/
                if (!empty($BirthdayEnd)) {
                    $where['Birthday'] = ['lt', strtotime($BirthdayEnd)];
                }
            }
            if (!empty($CountryCode)) {
                $where['CountryCode'] = $CountryCode;
            }
            if (isset($param_data['Email']) && !empty($param_data['Email'])) {
                $email_array = explode("@", $param_data['Email']);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'Customer', 'EmailUserName');//加密邮件前缀
                $where['EmailUserName'] = $EmailUserName;
                $where['EmailDomainName'] = $EmailDomainName;
            }

            if (!empty($param_data["ID"])) {
                $where['ID'] = ['in', $param_data["ID"]];
            } else {
                $where['ID'] = input("post.ID/d");
            }
            if (!empty($param_data["UserName"])) {
                $where['UserName'] = ['in', $param_data["UserName"]];
            }
            // return $where;
            // $where['ID'] = input("ID/d");
            $Status = input("post.Status/d");
            // $UserName = input("UserName/s");
            // if(!empty($UserName)){
            //     $where['UserName'] = $UserName;
            // }
            $where = array_filter($where);
            if ($Status === 0 || !empty($Status)) {
                $where['Status'] = $Status;
            }
            $page_size = input("post.page_size", 20);
            $page = input("post.page", 1);
            $path = input("post.path");
            $count = input("post.countPage");
            $res = $Customer->getCustomerList($where, $page_size, $page, $path, $query, $count);
            vendor('aes.aes');
            $aes = new aes();
            foreach ($res['data'] as $key => $value) {
                if ($value['EmailUserName']) {
                    $EmailUserName = $aes->decrypt($value['EmailUserName'], 'Customer', 'EmailUserName');//加密邮件前缀
                    $res['data'][$key]['email'] = $EmailUserName . '@' . $value['EmailDomainName'];
                }
            }
            if (!empty($res)) {
                return apiReturn(['code' => 200, 'data' => $res]);
            } else {
                return apiReturn(['code' => 1006]);
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
                return apiReturn(['code' => 2016]);
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


    /**
     * 找回密码
     */
    public function passwordFind()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.passwordFind");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $arrays = array('AccountName' => $paramData['Email']);
            $data = $this->GetCustomerInfoByAccount($arrays);
            if ($data['code'] != 200) {
                if ($data['code'] == 1006) {
                    $data['msg'] = 'Email does not exist';//
                }
                return $data;
            }
            vendor('aes.aes');
            /*$aes = new aes();
            $name = $Email;
            $subject = 'Retrieve the password'; //邮件标题
            $content = 'Please click the link to continue looking for the reset password:'.url('users/resetPassword','','',true).'?resetPasswordID='.urlencode($aes->encrypt($data['data'])); //邮件内容
            $send_email_resp = send_mail($Email,$name,$subject,$content);*/
            $send_email_resp = $this->sendEmailForPasswordFind($data['data']);
            if ($send_email_resp) {
                return ["code" => 200, "msg" => "Please reset the password at the mailbox"];
            } else {
                return ["code" => 1002, "msg" => "Mail failure"];
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 发送邮件【找回密码用】
     * */
    private function sendEmailForPasswordFind($id)
    {
        $arrays = ['ID' => $id];
        $data = $this->getCustomerByID($arrays);
        if ($data['code'] != 200) {
            /*if($data['code'] == 1006){
                $data['msg'] ='UserName does not exist';
            }*/
            return false;
        }
        $code['UserId'] = $id;
        $code['UserType'] = 1;
        $code['Type'] = "SendEmailForPasswordFind";
        $code['IsDeleteOld'] = 1;
        $createVerificationCode = controller("share/VerificationCode");
        $verification_code = $createVerificationCode->createVerificationCode($code);
        vendor('aes.aes');
        $aes = new aes();
        $email_data = [
            'CustomerID' => urlencode($aes->encrypt($id)),
            'UserId' => urlencode($aes->encrypt($id)),
            'UserType' => $code['UserType'],
            'Type' => $code['Type'],
            'VerificationCode' => isset($verification_code['data']['VerificationCode']) ? $verification_code['data']['VerificationCode'] : ''
        ];
        $password_reset_url = 'https:' . MYDXINTERNS . '/Users/resetPassword/d/' . base64_encode(json_encode($email_data));
        //$password_reset_url = url('Users/resetPassword',['d'=>base64_encode(json_encode($email_data))],'',true);
        $password_reset_text = "<a href='" . $password_reset_url . "' style='word-break:break-all'>$password_reset_url</a>";
        $send_email_resp = Email::sendEmail(
            $data['data']['email'],
            7,
            $data['data']['UserName'],
            [
                'username' => $data['data']['UserName'],
                'password_reset_url' => $password_reset_text
            ]
        );
        return $send_email_resp;
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

    public function login()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->login($data);
        return $res;
    }

    public function registerCustomer()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->register($data);
        return apiReturn($res);
    }

    public function validateCustomer()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->validateCustomer($data);
        return $res;
    }

    public function LoginForToken()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->LoginForToken($data);
        if(!empty($res['data']['PhotoPath']) && (false === strpos($res['data']['PhotoPath'], 'http'))){
            $res['data']['PhotoPath']=IMG_DXCDN.$res['data']['PhotoPath'];
        }
        return apiReturn($res);
    }

    public function getCustomerByToken()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->getCustomerByToken($data);
        return $res;
    }

    public function addLoginHistory()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->addLoginHistory($data);
        return $res;
    }

    public function updateStatus()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->updateStatus($data);
        return $res;
    }

    public function updatePhotoPath()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $data1['ID'] = $data["ID"];
        $data1['PhotoPath'] = $data['image'][0];
        $res = $BaseApi->saveProfile($data1);
        return $res;
    }

    public function saveProfile()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->saveProfile($data);
        return $res;
    }

    public function GetCustomerInfoByAccount()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->GetCustomerInfoByAccount($data);
        return $res;
    }

    public function getCustomerByID()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->getCustomerByID($data);
        return $res;
    }

    public function GetEmailsByCIDs()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->GetEmailsByCIDs($data);
        return $res;
    }

    public function getEmailsByCID()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->getEmailsByCID($data);
        return $res;
    }

    public function changePassword()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->changePassword($data);
        return $res;
    }

    public function changepasswordHistory()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->changepasswordHistory($data);
        return $res;
    }

    public function confirmPaymentPassword()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->confirmPaymentPassword($data);
        return $res;
    }

    public function savePaymentPassword()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->savePaymentPassword($data);
        return $res;
    }

    public function addSystemLog()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->addSystemLog($data);
        return $res;
    }

    public function addErrorLog()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->addErrorLog($data);
        return $res;
    }


}
