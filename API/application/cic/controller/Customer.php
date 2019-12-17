<?php
namespace app\cic\controller;
use app\common\controller\Base;
use app\cic\model\ShortLink;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;
use think\Controller;
use app\cic\model\ThirdPartyCustomer as ThirdPartyCustomerModel;
use app\common\controller\Email;
use app\common\controller\FTPUpload;

class Customer extends Base
{
    public function index(){

        return $this->fetch();
    }

    /*
         * 判断用户是否存在并验证登入信息并返回用户ID
         * @param string AccountName
         * @param int SiteID 可不填
         * @param string Password
         * @Return: array
         * */
    public function LoginForToken()
    {
        try{
            $param_data = request()->post();
            /*验证参数*/
            $validate = $this->validate($param_data,"Customer.LoginForToken");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $AccountName = input("post.AccountName/s");
            $SiteID = input("post.SiteID/d",1);
            $Password =input("post.Password/s");
            $ThirdPartyAccountID =input("post.ThirdPartyAccountID/d");
            $Customer = model('Customer');
            $AccountName_len = strlen($AccountName);
            if($AccountName_len<1){
                return apiReturn(['code'=>1003]);
            }elseif($AccountName_len>50){
                return apiReturn(['code'=>1005]);
            }
            /*if(strlen($SiteID)<1 || strlen($Password)<1){
                return apiReturn(['code'=>1001]);
            }*/
            /*将用户账号或邮箱转为小写 20190418 kevin*/
            $AccountName = strtolower($AccountName);
            if(is_email($AccountName) == true){//传入账号是邮箱
                $email_array = explode("@",$AccountName);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $email['EmailUserName'] = $EmailUserName;
                $email['EmailDomainName'] = $EmailDomainName;
                if(!empty($ThirdPartyAccountID)){
                    $model = new ThirdPartyCustomerModel();
                    /*第三方ID获取用户ID*/
                    $ThirdPartyWhere['ThirdPartyAccountID'] = $ThirdPartyAccountID;
                    $ThirdPartyWhere['EmailUserName'] = $EmailUserName;
                    $ThirdPartyWhere['EmailDomainName'] = $EmailDomainName;
                    $CustomerID = $model->IsExistAccountID($ThirdPartyWhere);
                    if($CustomerID>0){
                        $CustomerS = $Customer->getCustomer($CustomerID);
                    }else{
                        //第三方id表没有数据
                        return apiReturn(['code'=>1006]);
                    }
                }else{
                    $CustomerS = $Customer->checkLogin('email',$email,$SiteID,encry_password($Password));
                }
            }else {//用户名
                $CustomerS = $Customer->checkLogin('username', $AccountName, $SiteID, encry_password($Password));
            }
            if($CustomerS){
                if($CustomerS['Status'] !=0 && $CustomerS['Status'] !=1){
                    return apiReturn(['code'=>1101]);
                }
                vendor('aes.aes');
                $aes = new aes();
                if(isset($CustomerS['EmailUserName'])) {
                    $EmailUserName = $aes->decrypt($CustomerS['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                    $CustomerS['email'] = $EmailUserName.'@'.$CustomerS['EmailDomainName'];
                }
                $TokenData = $this->getToken($CustomerS['ID']);
                if($TokenData['code'] != 200){
                    return $TokenData;
                }
                $CustomerS['Token'] = $TokenData['data'];
                return apiReturn(['code'=>200,'data'=>$CustomerS]);
            }else{
                return apiReturn(['code'=>1016]);
            }
        }catch (\Exception $e){
            Log::write("LoginForToken error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


/*
* 获取用户token是否存在，不存在新增，存在更改过期时间
* @param int cicID
* @param isremember 是否记住密码
* @Return: array
* */
    public function getToken($cicID){
        try{
            if(empty($cicID)){
                return apiReturn(['code'=>1001]);
            }
            $isremember = input("post.isremember/b",false);
            $Token = model('Token');
            if($isremember){
                $timeout = strtotime("+ 1day");
                $expires = 3600*24;
            }else{
                $timeout = strtotime("+ 7day");
                $expires = 3600*24*7;
            }
            $old_token = $Token->getToken(['cicID'=>$cicID]);
            $token_data['cicID'] = $cicID;
            $token_data['timeout'] = $timeout;
            $token_data['isremember'] = $isremember;
            $token = guid();
            $token_data['token'] = $token;
            if(empty($old_token)){
                $add_token = model("Token")->addToken($token_data);
                if($add_token){
                    return apiReturn(['code'=>200,'data'=>$token_data]);
                }else{
                    return apiReturn(['code'=>1001]);
                }
            }else{
                if($old_token['timeout']>time()){
                    $token_data['token'] = $old_token['token'];
                }
                $where['cicID'] = $cicID;
                $update_token = model("Token")->updateToken($token_data,$where);
                if($update_token){
                    $token_data['expires'] = $expires;
                    return apiReturn(['code'=>200,'data'=>$token_data]);
                }else{
                    return apiReturn(['code'=>1001]);
                }
            }
        }catch (\Exception $e){
            Log::write("getToken error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /*
 * 根据token获取用户信息
 * @param  cicID token选填
 * @Return: array
 * */
    public function getCustomerByToken()
    {
       try{
           $param_data = request()->post();
           $validate = $this->validate($param_data,"Customer.getCustomerByToken");
           if(true !== $validate){
               return apiReturn(['code'=>1002,"msg"=>$validate]);
           }
           $token = input("post.token");
           $Token = model('Token');
           if(!empty($Token)){
               $token_where['token'] = $token;
           }else{
               return apiReturn(['code'=>1001]);
           }
           $token_res = $Token->getToken($token_where);
           if(isset($token_res['timeout']) && $token_res['timeout']<time()){
               return apiReturn(['code'=>1053]);
           }
           if(empty($token_res)){
               return apiReturn(['code'=>1041]);
           }
           if($token_res['isremember']){
               $timeout = strtotime("+ 7day");
           }else{
               $timeout = strtotime("+ 1day");
           }
           $token_data['timeout'] = $timeout;
           $token_data['cicID'] = $token_res['cicID'];
           $token_data['isremember'] = $token_res['isremember'];
           if(empty($token)){
               return apiReturn(['code'=>1001]);
           }else{
               $token_data['token'] = $token_res['token'];
               $update_token = $Token->updateToken($token_data);
               //if($update_token){
               $customer = model("Customer")->getBaseCustomer($token_res['cicID']);
               vendor('aes.aes');
               $aes = new aes();
               if(isset($customer['EmailUserName'])) {
                   $EmailUserName = $aes->decrypt($customer['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                   $customer['email'] = $EmailUserName.'@'.$customer['EmailDomainName'];
               }
               if($customer){
                   return apiReturn(['code'=>200,'data'=>$customer]);
               }else{
                   return apiReturn(['code'=>1014]);
               }
               /*}else{
                   return apiReturn(['code'=>1001]);
               }*/
           }
       }catch (\Exception $e){
           Log::write("getCustomerByToken error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
       }
    }

    /*
     * 验证用户是否存在
     * @param string AccountName  用户邮箱或者用户名
     * @Return: array
     * */
    public function GetCustomerInfoByAccount($data='')
    {
        try{
            $param_data = !empty($data)?$data:request()->post();
            $validate = $this->validate($param_data,"Customer.GetCustomerInfoByAccount");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            /*将用户账号或邮箱转为小写 20190418 kevin*/
            $AccountName = strtolower($param_data["AccountName"]);
            $AccountName_len = strlen($AccountName);
            if($AccountName_len<1){
                return apiReturn(['code'=>1003]);
            }elseif($AccountName_len>50){
                return apiReturn(['code'=>1005]);
            }
            if(is_email($AccountName) == true){//传入账号是邮箱
                $email_array = explode("@",$AccountName);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $Customer = model('Customer');
                $email['EmailUserName'] = $EmailUserName;
                $email['EmailDomainName'] = $EmailDomainName;
                $res = $Customer->isCustomer('email',$email);
                if($res){
                    return apiReturn(['code'=>200,'data'=>$res]);
                }else{
                    return apiReturn(['code'=>1006]);
                }
            }else{//用户名
                $Customer = model('Customer');
                $res = $Customer->isCustomer('username',$AccountName);
                if($res){
                    return apiReturn(['code'=>200,'data'=>$res]);
                }else{
                    return apiReturn(['code'=>1006]);
                }
            }
        }catch (\Exception $e){
            Log::write("GetCustomerInfoByAccount error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取用户信息
     * @param int ID 用户ID
     * @Return: array
     * */
    public function getCustomerByID($data=''){
        try{
            $param_data = !empty($data)?$data:request()->post();
            $validate = $this->validate($param_data,"Customer.getCustomerByID");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = $param_data['ID'];
            $Customer = model('Customer');
            $type = input("post.type",1);
            $res = $Customer->getCustomer($ID,$type);
            vendor('aes.aes');
            $aes = new aes();
            if($res['EmailUserName']) {
                $EmailUserName = $aes->decrypt($res['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                $res['email'] = $EmailUserName.'@'.$res['EmailDomainName'];
            }
            if(!empty($res)){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("getCustomerByID error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取用户信息
     * @param int ID 用户ID
     * @Return: array
     * */
    public function getCustomerList(){
        try{
            $param_data = request()->post();
            $validate = $this->validate($param_data,"Customer.getCustomerList");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $Customer = model('Customer');
            $RegisterStart = input("post.RegisterStart");
            $RegisterEnd = input("post.RegisterEnd");
            $BirthdayStart = input("post.BirthdayStart");
            $BirthdayEnd = input("post.BirthdayEnd");
            $CountryCode = input("post.CountryCode");
            $query = isset($param_data['query'])?$param_data['query']:'';
            if(!empty($RegisterStart) && !empty($RegisterEnd)){
                $where['RegisterOn'] = ["between",[$RegisterStart,$RegisterEnd]];
            }else{
                /*成交开始时间*/
                if(!empty($RegisterStart)){
                    $where['RegisterOn'] = ['gt',strtotime($RegisterStart)];
                }

                /*成交结束时间*/
                if(!empty($RegisterEnd)){
                    $where['RegisterOn'] = ['lt',strtotime($RegisterEnd)];
                }
            }
            if(!empty($BirthdayStart) && !empty($BirthdayEnd)){
                $where['Birthday'] = ["between",[strtotime($BirthdayStart),strtotime($BirthdayEnd)]];
            }else{
                /*成交开始时间*/
                if(!empty($BirthdayStart)){
                    $where['Birthday'] = ['gt',strtotime($BirthdayStart)];
                }

                /*成交结束时间*/
                if(!empty($BirthdayEnd)){
                    $where['Birthday'] = ['lt',strtotime($BirthdayEnd)];
                }
            }
            if(!empty($CountryCode)){
                $where['CountryCode'] = $CountryCode;
            }
            if(isset($param_data['Email']) && !empty($param_data['Email'])){
                /*将用户邮箱转为小写 20190418 kevin*/
                $email_array = explode("@",strtolower($param_data['Email']));
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $where['EmailUserName'] = $EmailUserName;
                $where['EmailDomainName'] = $EmailDomainName;
            }

            if(!empty($param_data["ID"])){
                 $where['ID'] = ['in',$param_data["ID"]];
            }else{
                 $where['ID'] = input("post.ID/d");
            }
            if(!empty($param_data["UserName"])){
                 $where['UserName'] = ['in',$param_data["UserName"]];
            }
            // return $where;
             // $where['ID'] = input("ID/d");
            $Status = input("post.Status/d");
            // $UserName = input("UserName/s");
            // if(!empty($UserName)){
            //     $where['UserName'] = $UserName;
            // }
            $where = array_filter($where);
            if($Status===0 || !empty($Status)){
                $where['Status'] = $Status;
            }
            $page_size = input("post.page_size",20);
            $page = input("post.page",1);
            $path = input("post.path");
            $count = input("post.countPage");
            $res = $Customer->getCustomerList($where,$page_size,$page,$path,$query,$count);
            vendor('aes.aes');
            $aes = new aes();
            foreach ($res['data'] as $key=>$value){
                if($value['EmailUserName']) {
                    $EmailUserName = $aes->decrypt($value['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                    $res['data'][$key]['email'] = $EmailUserName.'@'.$value['EmailDomainName'];
                }
            }
            if(!empty($res)){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("getCustomerList error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 更改用户状态
     * */
    public function updateStatus(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.updateStatus");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            if (!isset($paramData['ID'])) {
                return apiReturn(['code'=>1001]);
            }
            if (!isset($paramData['Status'])) {
                return apiReturn(['code'=>1001]);
            }
            if (isset($paramData['Remarks'])) {
                $data['Remarks'] = $paramData['Remarks'];
            }
            $data['ID'] = $paramData["ID"];
            $data['Status'] = $paramData["Status"];
            $data['UpdateTime'] = time();
            $data['LastUpdateTime'] = time();
            $Customer = model('Customer');
            $res = $Customer->updateStatus($data);
            /*如果用户被禁用，将订阅状态改成未激活，如果改成启用时改成激活 added by kevin 2019-03-19*/
            if(!empty($res)){
                if(isset($data['Status']) && ($data['Status'] == 20 || $data['Status'] == 21)){
                    $update_subscriber_where['CustomerId'] = $data['ID'];
                    $update_subscriber_data['Active'] = 0;
                    $update_subscriber_data['EditTime'] = time();
                    model("Subscriber")->updateSubscriber($update_subscriber_where,$update_subscriber_data);
                }elseif(isset($data['Status']) && $data['Status'] == 1){
                    $update_subscriber_where['CustomerId'] = $data['ID'];
                    $update_subscriber_data['Active'] = 1;
                    $update_subscriber_data['EditTime'] = time();
                    model("Subscriber")->updateSubscriber($update_subscriber_where,$update_subscriber_data);
                }
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("updateStatus error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 验证用户注册邮箱
     * @param string $Email
     * @Return: array
     * */
    public function validateCustomer($data=''){
        try{
            $paramData = !empty($data)?$data:request()->post();
            $validate = $this->validate($paramData,"Customer.validateCustomer");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $Email = input("post.Email/s");
            if(is_email($Email) == true) {//传入账号是邮箱
                /*将邮箱转为小写 20190418 kevin*/
                $email_array = explode("@",strtolower($Email));
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $Customer = model('Customer');
                $email['EmailUserName'] = $EmailUserName;
                $email['EmailDomainName'] = $EmailDomainName;
                $SourceType = isset($paramData['SourceType'])?$paramData['SourceType']:1;
                $SiteID = isset($paramData['SiteID'])?$paramData['SiteID']:1;
                $res = $Customer->isCustomer('email',$email,$SourceType,$SiteID);
                if($res){
                    return apiReturn(['code'=>1011]);
                }else{
                    return apiReturn(['code'=>200]);
                }
            }else{
                return apiReturn(['code'=>1007]);
            }
        }catch (\Exception $e){
            Log::write("validateCustomer error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 注册用户
     * @param string Email
     * @param string FirstName
     * @param string LastName
     * @param string Password 密码
     * @param int SiteID 站点ID 可不填
     * @param int SourceType  可不填
     * @Return: array
     * */
    public function registerCustomer(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.registerCustomer");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $Email = input("post.Email/s");
            /*将用户邮箱转为小写 20190418 kevin*/
            $Email= strtolower($Email);
            $FirstName = input(("post.FirstName/s"));
            $LastName = input("post.LastName/s");
            $Password = input("post.Password/s");
            $SiteID = input("post.SiteID/d",1);
            $SourceType = input("post.SourceType",1);
            $ThirdPartyAccountID = input("post.ThirdPartyAccountID");
            $CountryCode = input("post.CountryCode/s");
            $Customer = model('Customer');
            $Status = input("post.Status/d",0);
            $ClientSource = input("post.ClientSource/d",1);
            if(empty($Email) || empty($FirstName) || empty($LastName) || (empty($Password) && empty($ThirdPartyAccountID))){
                return apiReturn(['code'=>1001]);
            }
            if(is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@",$Email);
                $data['UserName'] = $email_array[0];
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = $EmailDomainName;
                $data['FirstName'] = $FirstName;
                $data['LastName'] = $LastName;
                $data['Password'] = !empty($Password)?encry_password($Password):encry_password(get_random_key(8));
                $data['SiteID'] = $SiteID;
                $data['SourceType'] = $SourceType;
                $data['CountryCode'] = $CountryCode;
                $data['Status'] = $Status;
                $data['ClientSource'] = $ClientSource;
                /*判断是否有第三方ID传入*/
                if(!empty($ThirdPartyAccountID)){
                    $ThirdWhere['ThirdPartyAccountID'] = $ThirdPartyAccountID;
                    $model = new ThirdPartyCustomerModel();
                    $CustomerID = $model->IsExistAccountID($ThirdWhere);
                    /*当第三方数据在数据表存在，但是邮箱不一致*/
                    if($CustomerID>0){
                        /*修改用户表邮箱*/
                        $update_profile = $Customer->saveProfile($CustomerID,['EmailUserName'=>$EmailUserName,'EmailDomainName'=>$EmailDomainName]);
                        if($update_profile){
                            /*修改第三方数据表邮箱*/
                            $ThirdData['EmailUserName'] = $EmailUserName;
                            $ThirdData['EmailDomainName'] = $EmailDomainName;
                            $ThirdData['LastUpdateTime'] = time();
                            $model->saveThirdPartyCustomer($ThirdData,$ThirdWhere);
                        }else{
                            return apiReturn(["code"=>1002,"msg"=>"update profile error"]);
                        }
                        $res_data['ID'] = $CustomerID;
                        $res_data['Email'] = $Email;
                        $res_data['NotIsAdd'] = 1;
                        return apiReturn(['code'=>200,'data'=>$res_data]);
                    }else{
                        $isCustomerWhere['EmailUserName'] = $EmailUserName;
                        $isCustomerWhere['EmailDomainName'] = $EmailDomainName;
                        $CustomerID = $Customer->isCustomer('email',$isCustomerWhere);
                        /*当第三方ID传入时邮箱对应的用户存在，但是第三方数据表不存在*/
                        if($CustomerID>0){
                            $ThirdData['ThirdPartyAccountID'] = $ThirdPartyAccountID;
                            $ThirdData['CustomerID'] = $CustomerID;
                            $ThirdData['EmailUserName'] = $EmailUserName;
                            $ThirdData['EmailDomainName'] = $EmailDomainName;
                            $ThirdData['CreateOn'] = time();
                            /*添加到第三方数据表*/
                            $addThirdPartyCustomer = $model->saveThirdPartyCustomer($ThirdData);
                            if(!$addThirdPartyCustomer){
                                return apiReturn(["code"=>1002,"msg"=>"Add ThirdPartyCustomer error"]);
                            }
                            $res_data['ID'] = $CustomerID;
                            $res_data['Email'] = $Email;
                            $res_data['NotIsAdd'] = 1;
                            return apiReturn(['code'=>200,'data'=>$res_data]);
                        }else{/*用户表和第三方表都不存在，需要注册*/
                            $data['Status'] = 1;
                        }
                    }
                }else{
                    $validateCustomer = $this->validateCustomer(['Email'=>$Email]);
                    if($validateCustomer['code']!=200){
                        return $validateCustomer;
                    }
                }
                $res = $Customer->addCustomer($data);
                if($res>0){
                    if(!empty($ThirdPartyAccountID)){
                        $ThirdData['EmailUserName'] = $EmailUserName;
                        $ThirdData['EmailDomainName'] = $EmailDomainName;
                        $ThirdData['CustomerID'] = $res;
                        $ThirdData['ThirdPartySiteID'] = $SiteID;
                        $ThirdData['ThirdPartyAccountID'] = $ThirdPartyAccountID;
                        $ThirdData['CreateOn'] = time();
                        $model->saveThirdPartyCustomer($ThirdData);
                    }
                    /*用户订阅*/
                    $Subscriberdata['Email'] = $Email;
                    $Subscriberdata['CustomerId'] = $res;
                    $user_subscriber = controller("Subscriber")->addSubscriber($Subscriberdata);
                    /*添加用户积分*/
                    $PointsBasic['CustomerID'] = $res;
                    $PointsBasic['Memo'] = "System creates account automatically.";
                    $PointsBasic['NewTotalCount'] = config('registration_bonus_points');
                    model("PointsBasicInfo")->addPoints($PointsBasic);
                    $res_data['ID'] = $res;
                    $res_data['Email'] = $Email;
                    return apiReturn(['code'=>200,'data'=>$res_data]);
                }else{
                    return apiReturn(['code'=>1002]);
                }
            }else{
                return apiReturn(['code'=>1007]);
            }
        }catch (\Exception $e){
            Log::write("registerCustomer error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
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
    public function saveProfile(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.saveProfile");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = input("post.ID/d");
            $Email = input("post.Email/s",'');
            $Status = input("post.Status");
            $Password =input("post.Password");
            if(!empty($Status)){
                $data['Status'] = $Status;
            }elseif(!empty($Password)){
                $data['Password'] = encry_password($Password);
            }else{
                $data['FirstName'] = input(("post.FirstName/s"));
                $data['LastName'] = input("post.LastName/s");
                $data['Gender'] = input("post.Gender/d");
                $data['CountryCode'] = input("post.CountryCode");
                $data['PhotoPath'] = input("post.PhotoPath/s");
                $data['Birthday'] = input("post.Birthday");
            }

            $Customer = model('Customer');
            if(!$ID){
                return apiReturn(['code'=>1001]);
            }
            if(!empty($Email)){
                /*将用户邮箱转为小写 20190418 kevin*/
                $email_array = explode("@",strtolower($Email));
                $data['UserName'] = $email_array[0];
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = $EmailDomainName;
            }
            $data['UpdateTime'] = time();
            $data = array_filter($data);
            $res = $Customer->saveProfile($ID,$data);
            if($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("saveProfile error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 修改密码
     * @param int $ID
     * @param string Old_Password
     * @param string New_Password
     * @Return: array
     * */
    public function changePassword(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.changePassword");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = input("post.ID/d");
            $Old_Password = input("post.Old_Password");
            $New_Password = input(("post.New_Password"));
            if(strlen($New_Password)<6){
                return apiReturn(['code'=>1048]);
            }
            $Customer = model('Customer');
            $res = $Customer->confirmPassword(['ID'=>$ID],encry_password($Old_Password));
            if($res>0){
                /*判断是否跟支付密码相同*/
                $PaymentPassword = model('PaymentPassword');
                $confirmPaymentPassword = $PaymentPassword->confirmPaymentPassword(['CustomerID'=>$ID],paypwd_encryption($New_Password));
                if($confirmPaymentPassword){
                    return apiReturn(['code'=>1050,'msg'=>"Cann't equal to the Payment Password ."]);
                }
                $res = $Customer->changePassword(['ID'=>$ID],encry_password($New_Password));
                if($res){
                    return apiReturn(['code'=>200]);
                }else{
                    return apiReturn(['code'=>1002]);
                }
            }else{
                return apiReturn(['code'=>1009]);
            }
        }catch (\Exception $e){
            Log::write("changePassword error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 修改邮箱
     * yxh by 20190409
     * @param int $ID
     * @param string Old_Password
     * @param string New_Password
     * @Return: array
     * */
    public function changeEmail()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, "Customer.changeEmail");
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }
            $ID = input("post.ID/d");
            $Old_Password = input("post.Password");
            $Email = input("post.Email");
            /*将用户邮箱转为小写 20190418 kevin*/
            $Email = strtolower($Email);
            //验证密码
            $Customer = new \app\cic\model\Customer();
            $res = $Customer->confirmPassword(['ID' => $ID], encry_password($Old_Password));
            if ($res > 0) {
                /*判断是否新邮箱是否存在*/
                $validateCustomer = $this->validateCustomer(['Email' => $Email]);

                if ($validateCustomer['code'] != 200) {
                    return $validateCustomer;
                } else {
                    return apiReturn(['code' => 200]);
                }
            } else {
                return apiReturn(['code' => 1009]);
            }
        } catch (\Exception $e) {
            Log::write("changeEmail error,msg:".$e->getMessage());
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
     * 检测用户是否设置支付密码
     * @param int $ID
     * @Return: array
     * */
    public function checkPaymentPassword(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.checkPaymentPassword");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $CustomerID = input("post.CustomerID/d");
            if(empty($CustomerID)){
                return apiReturn(['code'=>1001]);
            }
            $PaymentPassword = model('PaymentPassword');
            $res = $PaymentPassword->checkPaymentPassword($CustomerID);
            if($res != true){
                $PaymentPasswordExistCheck = $PaymentPassword->PaymentPasswordExistCheck($CustomerID);
                if(!$PaymentPasswordExistCheck){
                    return apiReturn(['code'=>200,'data'=>false]);
                }else{
                    return apiReturn(['code'=>200,'data'=>true]);
                }
            }
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (\Exception $e){
            Log::write("checkPaymentPassword error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 修改支付密码
     * @param int $ID
     * @param string Old_Password(可选)
     * @param string Password
     * @Return: array
     * */
    public function savePaymentPassword(){
        try{
            $CustomerID = input("post.CustomerID/d");
            $Old_Password = input("post.Old_Password");
            $Password = input("post.Password");
            $validate = $this->validate(['PaymentPassword'=>$Password],"PaymentPassword.save");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            //return $this->validate(['PaymentPassword'=>$Password],"PaymentPassword.save");
            if(empty($CustomerID)){
                return apiReturn(['code'=>1001]);
            }
            if(strlen($Password)<6 || strlen($Password)>20){
                return apiReturn(['code'=>1048]);
            }

            $PaymentPassword = model('PaymentPassword');
            if(!empty($Old_Password)){
                $res = $PaymentPassword->confirmPaymentPassword(['CustomerID'=>$CustomerID],paypwd_encryption($Old_Password));
                if($res!= true){
                    return apiReturn(['code'=>1009]);
                }
                $data['PaymentPassword'] = paypwd_encryption($Password);
                $data['LastUpdateTime'] =time();
                $res = $PaymentPassword->savePaymentPassword($data,['CustomerID'=>$CustomerID]);
            }else{
                /*判断是否跟登录密码相同*/
                $login_password = encry_password($Password);
                $LoginPasswordCount = model('Customer')->confirmPassword(['ID'=>$CustomerID],$login_password);
                if($LoginPasswordCount>0){
                    return apiReturn(['code'=>1050]);
                }
                $IsSetPaymentPassword = $PaymentPassword->checkPaymentPassword($CustomerID);
                $data['CustomerID'] = $CustomerID;


                $data['PaymentPassword'] = paypwd_encryption($Password);
                if($IsSetPaymentPassword){
                    $data['LastUpdateTime'] =time();
                    $res = $PaymentPassword->savePaymentPassword($data,['CustomerID'=>$CustomerID]);
                }else{
                    $data['CreateOn'] =time();
                    $res = $PaymentPassword->savePaymentPassword($data);
                }

            }
            if($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("savePaymentPassword error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

/*判断密码是否正确*/
    public function confirmPaymentPassword($CustomerID='',$Password=''){
        try{
            $CustomerID = input("post.CustomerID/d",$CustomerID);
            $Password = input("post.Password",$Password);
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.confirmPaymentPassword");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $PaymentPassword = model('PaymentPassword');
            $Customer = model('Customer');
            $issetPayPwd = $PaymentPassword->checkPaymentPassword($CustomerID);
            /*是否在新平台设置了支付密码*/
            if($issetPayPwd){
                $res = $PaymentPassword->confirmPaymentPassword(['CustomerID'=>$CustomerID],paypwd_encryption($Password));
                if($res!= true){
                    return apiReturn(['code'=>1016,'msg'=>"The password is error"]);
                }else{
                    return apiReturn(['code'=>200]);
                }
            }
            //必须要设置支付密码后才能体现
            return apiReturn(['code'=>1002,'msg'=>"Please set the payment password."]);
            /*else{
                $IsNewData = $Customer->checkIsNewByID($CustomerID);
                if(empty($IsNewData)){
                    return apiReturn(['code'=>1021]);
                }
                //是否是新用户，不是新用户
                if($IsNewData['IsNew'] == 0){
                    $PaymentPasswordExistCheck = $PaymentPassword->PaymentPasswordExistCheck($CustomerID);
                    if(!$PaymentPasswordExistCheck){
                        return apiReturn(['code'=>1021]);
                    }
                    $res = $PaymentPassword->PaymentPasswordCorrectnessCheck($Password,$CustomerID);
                    if($res!= true){
                        return apiReturn(['code'=>1016,'msg'=>"The password is error"]);
                    }else{
                        $data['CustomerID'] = $CustomerID;
                        $data['PaymentPassword'] = paypwd_encryption($Password);
                        $data['CreateOn'] =time();
                        $PaymentPassword->savePaymentPassword($data);
                        return apiReturn(['code'=>200]);
                    }
                }else{
                    return apiReturn(['code'=>1002,'msg'=>"You have not set the payment password."]);
                }
            }*/
        }catch (\Exception $e){
            Log::write("confirmPaymentPassword error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    //SC存在判断密码是否正确
    public function confirmScPaymentPassword(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.confirmScPaymentPassword");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $CustomerID = input("post.CustomerID/d");
            $Password = input("post.Password");
            $StoreCardWhere['CustomerID'] = $CustomerID;
            $StoreCardWhere['CurrencyType'] = input("post.CurrencyType");
            $StoreCardWhere = array_filter($StoreCardWhere);
            $PaymentPassword = model('PaymentPassword');
            if(empty($CustomerID) || empty($Password)){
                return apiReturn(['code'=>1001]);
            }
            $confirmPaymentPassword = $this->confirmPaymentPassword($CustomerID,$Password);
            if($confirmPaymentPassword['code'] != 200){
                return $confirmPaymentPassword;
            }
            $res = $PaymentPassword->confirmPaymentPassword(['CustomerID'=>$CustomerID],paypwd_encryption($Password));
            if($res!= true){
                return apiReturn(['code'=>1016,"msg"=>"The password is error"]);
            }
            $sc_info = model("StoreCarditBasicInfo")->getStoreCarditBasicInfo($StoreCardWhere);
            if(!$sc_info){
                return apiReturn(['code'=>1040]);
            }else{
                if($sc_info['UsableAmount']<=0){
                    return apiReturn(['code'=>1002,'msg'=>"Store Cardit is zero"]);
                }
            }
            return apiReturn(['code'=>200,'data'=>$sc_info]);
        }catch (\Exception $e){
            Log::write("confirmScPaymentPassword error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    public function getUpdateTime(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.getUpdateTime");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where['ID'] = input("post.ID/d");
            $db = Db::connect('db_cic');
            $res = $db->name('customer')->where($where)->value('UpdateTime');
            return apiReturn(['code'=>$res]);
        }catch (\Exception $e){
            Log::write("getUpdateTime error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }



    /*
     * 添加用户其他信息
     * */
    public function addCustomerOther(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.addCustomerOther");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
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
            $res = $Customer ->addCustomerOther($data);
            if($res>0){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("addCustomerOther error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 添加系统操作日志
     * */
    public function addSystemLog(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.addSystemLog");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $CustomerID = input("post.CustomerID");
            if(empty($CustomerID)){
                return apiReturn(['code'=>1001]);
            }
            $data['CustomerID'] = $CustomerID;
            $data['IPAddress'] = input("post.IPAddress");
            $data['OperationName'] = input("post.OperationName");
            $data['DataCategory'] = input("post.DataCategory");
            $data['OperateType'] = input("post.OperateType");;
            $data['Description'] = input("post.Description");
            $data['OperateTime'] = time();
            $Customer = model('Customer');
            $res = $Customer ->addSystemLog($data);
            if($res>0){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("addSystemLog error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
    /*
    * 添加错误日志
    * */
    public function addErrorLog(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.addErrorLog");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $RefID = input("post.RefID");
            if(empty($RefID)){
                return apiReturn(['code'=>1001]);
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
            $res = $Customer ->addErrorLog($data);
            if($res>0){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("addErrorLog error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /*
 * 开通用户Affiliate模块
 * */
    public function openAffiliate(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.openAffiliate");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = input("post.ID/d");
            if(empty($ID)){
                $lastID = model("Customer")->getLastAffiliateID();
                if($lastID){
                    $data['RCode'] = 100000001+(int)$lastID;
                }else{
                    $data['RCode'] = 100000001;
                }
            }else{
                $data["ID"] = $ID;
            }
            $data['CustomerID'] = input("post.CustomerID/d");
            if(empty($data['CustomerID'])){
                return apiReturn(['code'=>1001]);
            }
            $Email = input("post.Email");
            if(is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@", $Email);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $data['PayPalEU'] = $EmailUserName;
                $data['PayPalED'] = $EmailDomainName;
                //如果此邮箱已被注册，则不允许再次注册
                $affiliate_data = model("Affiliate")->getAffiliateLevel($data);
                if(!empty($affiliate_data)){
                    return apiReturn(['code' => 1002,'msg'=>'This email has been registered.']);
                }
                $data['WebsiteURL'] = input("post.WebsiteURL");
                $data['RegistrationTimestamp'] = time();
                $data['Active'] = input("post.Active", 0);
                $data['CommissionRate'] = input("post.CommissionRate", 0);
                $data['IsPartner'] = input("post.Partner", 0);
                $data['LastChangeLevelTime'] = time();
                $data['Notes'] = input("post.Notes");
                $data['LevelIndex'] = input("post.LevelIndex", 0);
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
            }else{
                return apiReturn(['code'=>1007]);
            }
        }catch (\Exception $e){
            Log::write("openAffiliate error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     *
     * */
    public function addAffiliateLevelLog(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Customer.addAffiliateLevelLog");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $data['LevelID'] = input("post.LevelID/d");
        if(empty($data['LevelID'])){
            return apiReturn(['code'=>1001]);
        }
        $data['LevelID'] = input("post.LevelID");
        $data['OldLevelIndex'] = input("post.OldLevelIndex",0);
        $data['NewLevelIndex'] = input("post.NewLevelIndex");
        $data['CommissionRate'] = input("post.CommissionRate",0);
        $data['Notes'] = input("post.Notes");
        $data['ChangeTime'] = time();
        $res = model("Affiliate")->addAffiliateLevelLog($data);
        if($res>0){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 激活affiliate账号
     * */
    public function activeAffiliateLevel(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.activeAffiliateLevel");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = input("post.ID/d");
            if(empty($ID)){
                return apiReturn(['code'=>1001]);
            }
            $data["ID"] = $ID;
            $data['Active'] = input("post.Active",1);
            $data['LastChangeLevelTime'] = time();
            $res = model("Affiliate")->saveAffiliateLevel($data);
            if($res>0){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("activeAffiliateLevel error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 更改affiliate
     * */
    public function editAffiliateLevel(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.editAffiliateLevel");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = input("post.ID/d");
            if(empty($ID)){
                return apiReturn(['code'=>1001]);
            }
            $where["ID"] = $ID;
            $data = model("Affiliate")->getAffiliateLevel($where);
            $OldLevelIndex = $data['LevelIndex'];
            $data['CommissionRate'] = input("post.CommissionRate",0);
            $data['LastChangeLevelTime'] = time();
            $data['Notes'] = input("post.Notes");
            $data['LevelIndex'] = input("post.LevelIndex",0);
            $data = array_filter($data);
            $res = model("Affiliate")->saveAffiliateLevel($data);
            if($res>0){
                $log['LevelID'] = $ID;
                $log['OldLevelIndex'] = $OldLevelIndex;
                $log['NewLevelIndex'] = $data['LevelIndex'];
                $log['CommissionRate'] = $data['CommissionRate'];
                $log['Notes'] = "更改AffiliateLevel";
                $this->addAffiliateLevelLog($log);
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("editAffiliateLevel error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 更改affiliate
     * */
    public function editAffiliateEmail(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.editAffiliateEmail");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $ID = input("post.ID/d");
            $CustomerID = input("post.CustomerID/d");
            $Email = input("post.Email");
            if(empty($ID) && empty($CustomerID)){
                return apiReturn(['code'=>1001]);
            }
            if(empty($ID) && empty($CustomerID)){
                return apiReturn(['code'=>1001]);
            }
            if(!empty($ID)){
                $where["ID"] = $ID;
            }
            if(!empty($CustomerID)){
                $where["CustomerID"] = $CustomerID;
            }
            if(is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@", $Email);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $data['PayPalEU'] =  $EmailUserName;
                $data['PayPalED'] =  $EmailDomainName;
            }
            $Affiliate_where['PayPalEU'] =  $EmailUserName;
            $Affiliate_where['PayPalED'] =  $EmailDomainName;
            $res = model("Affiliate")->saveAffiliateLevel($data,$where);
            if($res>0){
                $log['LevelID'] = $res;
                if(isset($data['LevelIndex'])){
                    $log['NewLevelIndex'] = $data['LevelIndex'];
                }
                if(isset($data['CommissionRate'])){
                    $log['CommissionRate'] = $data['CommissionRate'];
                }
                $log['Notes'] = "更改PayPal Email Address";
                $this->addAffiliateLevelLog($log);
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>200]);
            }
        }catch (\Exception $e){
            Log::write("editAffiliateEmail error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取AffiliateLevel详情
     *
     * 因task.dx.com定时系统调用该方法获取数据，需排除黑名单，所有添加一个字段用于排除黑名单
     * @author: Wang  edittime 2019-01-19
     *
     * */
    public function getAffiliateLevel(){
        try{
            $ID = input("post.ID/d");
            $CustomerID = input("post.CustomerID/d");
            $RCode = input("post.RCode/d");
            /*****************@author: Wang addtime 2019-01-18****************************/
            $IsBlacklist = input("post.IsBlacklist/d");
            if(!empty($IsBlacklist)){
                 $where['IsBlacklist'] = $IsBlacklist;
            }
            /****************addtime 2019-01-18*********************************************/
            if(empty($ID) && empty($CustomerID) && empty($RCode)){
                return apiReturn(['code'=>1001]);
            }
            if(!empty($ID)){
                $where['ID'] = $ID;
            }
            if(!empty($CustomerID)){
                $where['CustomerID'] = $CustomerID;
            }
            if(!empty($RCode)){
                $where['RCode'] = $RCode;
            }
            $res = model("Affiliate")->getAffiliateLevel($where);
            if($res){
                vendor('aes.aes');
                $aes = new aes();
                if($res['PayPalEU']) {
                    $EmailUserName = $aes->decrypt($res['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮件前缀
                    $res['email'] = $EmailUserName.'@'.$res['PayPalED'];
                }
                return apiReturn(['code' => 200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            Log::write("getAffiliateLevel error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 通过CustomerId获取EmailId
     * */
    /*
     * 通过CustomerId获取EmailId
     * */
    public function GetEmailByCustomerID(){
        try{
            $CustomerID = input("CustomerId/d");
            if(empty($ID) && empty($CustomerID)){
                return apiReturn(['code'=>1001]);
            }
            $Customer = model('Customer');
            $CustomerData = $Customer->getCustomer($CustomerID);
            vendor('aes.aes');
            $aes = new aes();
            $email = '';
            if($CustomerData['EmailUserName']){
                $EmailUserName = $aes->decrypt($CustomerData['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                $email = $EmailUserName.'@'.$CustomerData['EmailDomainName'];
            }
            if($email){
                return apiReturn(['code'=>200,'OperationStatus'=>0,'ErrorInfos'=>"Success",'Email'=>$email]);
            }else{
                return apiReturn(['code'=>1006,'OperationStatus'=>1,'ErrorInfos'=>"operation failed"]);
            }
        }catch (\Exception $e){
            Log::write("GetEmailByCustomerID error,msg:".$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 通过CustomerId获取EmailId
     * */
    public function CheckSimpleEmail(){
        $Customer = model('Customer');
        $paramData = input();
        if(!isset($paramData['EmailAddress'])){
            return apiReturn(['code'=>1001]);
        }
        if(is_email($paramData['EmailAddress']) != true){//传入账号是否为邮箱
            return apiReturn(['code'=>1002,'msg'=>"Incorrect mailbox format",'OperationStatus'=>1,'ErrorInfos'=>"Incorrect mailbox format"]);
        }
        $email_array = explode("@",$paramData['EmailAddress']);
        $EmailDomainName = $email_array[1];
        vendor('aes.aes');
        $aes = new aes();
        $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
        $AddIfNone = isset($paramData['AddIfNone'])?$paramData['AddIfNone']:0;
        $where['EmailUserName'] = $EmailUserName;
        $where['EmailDomainName'] = $EmailDomainName;
        $CustomerEmail = $Customer->getCustomerEmail($where);
        if(!empty($CustomerEmail)){
            return apiReturn(['code'=>200,'OperationStatus'=>0,'CheckedMessage'=>"Success",'EmailID'=>$CustomerEmail['ID'],'Existe'=>1]);
        }else{
            if($AddIfNone){
                $data['CustomerID'] = isset($paramData['CustomerID'])?$paramData['CustomerID']:'';
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = isset($EmailDomainName)?$EmailDomainName:'';
                $data['EmailType'] = isset($paramData['EmailType'])?$paramData['EmailType']:0;
                $data['CreateOn'] = time();
                $data['Enabled'] = isset($paramData['Enabled'])?$paramData['Enabled']:0;
                $data['Notes'] = isset($paramData['Notes'])?$paramData['Notes']:'';
                $CustomerEmail = $Customer->addCustomerEmail($data);
                if($CustomerEmail){
                    return apiReturn(['code'=>200,'OperationStatus'=>0,'CheckedMessage'=>"Success",'EmailID'=>$CustomerEmail,'NewAdded'=>1]);
                }else{
                    return apiReturn(['code'=>1002,'OperationStatus'=>1,'CheckedMessage'=>"error",'EmailID'=>$paramData['EmailAddress'],'ExceptionOccured'=>1]);
                }
            }else{
                return apiReturn(['code'=>200,'msg'=>"no data,Not added",'OperationStatus'=>0,'CheckedMessage'=>"Success",'EmailID'=>0]);
            }
        }
    }

    /*
     * 根据用户ID获取用户详细信息
     * */
    public function GetCustomerInfoById(){
        $ID = input("Id/d");
        $Customer = model('Customer');
        if(empty($ID)){
            return apiReturn(['code'=>1001]);
        }
        //$type = input("type",1);
        $res = $Customer->getCustomer($ID,0);
        vendor('aes.aes');
        $aes = new aes();
        if($res['EmailUserName']) {
            $EmailUserName = $aes->decrypt($res['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
            $res['email'] = $EmailUserName.'@'.$res['EmailDomainName'];
        }
        if(!empty($res)){
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
            $res_data['CreateOn'] = isset($res['CreateOn'])?date("Y-m-d",$res['CreateOn']):'';
            $res_data['RegisterOn'] = isset($res['RegisterOn'])?date("Y-m-d",$res['RegisterOn']):'';
            $res_data['LastLoginDate'] = isset($res['LastLoginDate'])?date("Y-m-d",$res['LastLoginDate']):'';
            return apiReturn(['code'=>200,'data'=>$res_data]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 根据ID获取邮箱
     * */
    public function GetEmailsByCIDs(){
        try{
            $post_data = request()->post();
            if(!isset($post_data['ids'])){
                return apiReturn(['code'=>1001]);
            }
            $Customer = model('Customer');
            $IsSubscriber = isset($post_data['IsSubscriber'])?$post_data['IsSubscriber']:'';
            $res = $Customer->GetEmailsByCIDs($post_data['ids'],$IsSubscriber);
            if($res){
                vendor('aes.aes');
                $aes = new aes();
                $email_array = array();
                foreach ($res as $key=>$value){
                    if($value['EmailUserName']) {
                        $EmailUserName = $aes->decrypt($value['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                        $email_array[$key]['CustomerId'] = $value["ID"];
                        $email_array[$key]['Status'] = $value["Status"];
                        $email_array[$key]['Email'] = $EmailUserName.'@'.$value['EmailDomainName'];
                    }
                }

                return apiReturn(['code'=>200,'data'=>$email_array]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 根据ID获取邮箱
     * */
    public function getEmailsByCID(){
        try{
            $post_data = input();
            if(!isset($post_data['id'])){
                return apiReturn(['code'=>1001]);
            }
            $Customer = model('Customer');
            $IsSubscriber = isset($post_data['IsSubscriber'])?$post_data['IsSubscriber']:'';
            $res = $Customer->getEmailsByCID($post_data['id'],$IsSubscriber);
            if($res){
                vendor('aes.aes');
                $aes = new aes();
                $email = '';
                $Status = 0;
                if($res['EmailUserName']) {
                    $Status = !empty($res["Status"])?$res["Status"]:0;
                    $EmailUserName = $aes->decrypt($res['EmailUserName'],'Customer','EmailUserName');//解密邮件前缀
                    $email = $EmailUserName.'@'.$res['EmailDomainName'];
                }
                return apiReturn(['code'=>200,'data'=>$email,'user_status'=>$Status]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     *
     * */
    public function AddOrUpdateCustomerTaxId(){
        try{
            $post_data = input();
            if(!isset($post_data['Cicid'])){
                return apiReturn(['code'=>1001]);
            }
            $data['Cicid'] = $post_data['Cicid'];
            //
            if(isset($post_data['IdType'])){
                $data['TaxIdType'] = $post_data['IdType'];
            }

            //
            if(isset($post_data['TaxId'])){
                $data['TaxId'] = $post_data['TaxId'];
            }

            //
            if(isset($post_data['PersonalId'])){
                $data['PersonalId'] = $post_data['PersonalId'];
            }
            $data['CreateTime'] = time();
            $CustomerTaxInfo = model('CustomerTaxInfo');
            $res = $CustomerTaxInfo->AddOrUpdateCustomerTaxId($data);
            if($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     *根据用户ID获取用户Tax信息
     * */
    public function FindCustomerTaxId(){
        try{
            $post_data = input();
            if(!isset($post_data['Cicid'])){
                return apiReturn(['code'=>1001]);
            }
            $where['Cicid'] = $post_data['Cicid'];
            if(!isset($post_data['IdType'])){
                return apiReturn(['code'=>1001]);
            }
            $where['TaxIdType'] = $post_data['IdType'];
            $CustomerTaxInfo = model('CustomerTaxInfo');
            $res = $CustomerTaxInfo->FindCustomerTaxId($where);
            if($res !== false){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /**
     * 找回密码
     */
    public function passwordFind(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.passwordFind");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $arrays = array('AccountName'=>$paramData['Email']);
            $data = $this->GetCustomerInfoByAccount($arrays);
            if($data['code'] != 200){
                if($data['code'] == 1006){
                    $data['msg'] ='Account password error';
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
            if($send_email_resp){
                return ["code"=>200,"msg"=>"Please reset the password at the mailbox"];
            }else{
                return ["code"=>1002,"msg"=>"Mail failure"];
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 发送邮件【找回密码用】
     * */
    private function sendEmailForPasswordFind($id){
        $arrays = ['ID'=>$id];
        $data = $this->getCustomerByID($arrays);
        if($data['code'] != 200){
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
        $email_data = [
            'CustomerID' => $id,
            'UserId'=>$code['UserId'],
            'UserType'=>$code['UserType'],
            'Type'=>$code['Type'],
            'VerificationCode' => isset($verification_code['data']['VerificationCode'])?$verification_code['data']['VerificationCode']:''
        ];
        $password_reset_url = 'https:'.MYDXINTERNS.'/Users/resetPassword/d/'.base64_encode(json_encode($email_data));
        //$password_reset_url = url('Users/resetPassword',['d'=>base64_encode(json_encode($email_data))],'',true);
        $password_reset_text = "<a href='".$password_reset_url."' style='word-break:break-all'>$password_reset_url</a>";
        $send_email_resp = Email::sendEmail(
            $data['data']['email'],
            7,
            $data['data']['UserName'],
            [
                'username'=>$data['data']['UserName'],
                'password_reset_url'=>$password_reset_text
            ]
        );
        return $send_email_resp;
    }
    /*获取后台用户详情数据*/
    public function getAdminCustomerInfo(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.getAdminCustomerInfo");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            if(isset($paramData['ID']) && !empty($paramData['ID'])){
                $where['ID'] = $paramData['ID'];
            }
            if(isset($paramData['UserName']) && !empty($paramData['UserName'])){
                $where['UserName'] = $paramData['UserName'];
            }
            if(isset($paramData['Email']) && !empty($paramData['Email'])){
                $email_array = explode("@",$paramData['Email']);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $where['EmailUserName'] = $EmailUserName;
                $where['EmailDomainName'] = $EmailDomainName;
            }
            $data = model("Customer")->getAdminCustomerInfo($where);
            vendor('aes.aes');
            $aes = new aes();
            if(isset($data['EmailUserName'])) {
                $EmailUserName = $aes->decrypt($data['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                $data['email'] = $EmailUserName.'@'.$data['EmailDomainName'];
            }
            if($data){
                return ["code"=>200,"data"=>$data];
            }else{
                return ["code"=>1002];
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
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
    public function autoRegisterCustomer(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.autoRegisterCustomer");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $Email = input("Email/s");
            $Password = get_password();
            $SiteID = input("SiteID/d",1);
            $SourceType = input("SourceType",1);
            $CountryCode = input("CountryCode/s","BR");
            $Customer = model('Customer');
            $Status = input("Status/d",1);
            $ClientSource = input("ClientSource/d",1);
            if(empty($Email)){
                return apiReturn(['code'=>1001,'msg'=>'Email can not null']);
            }
            if(is_email($Email) == true) {//传入账号是邮箱
                $email_array = explode("@",$Email);
                $data['UserName'] = $email_array[0];
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = $EmailDomainName;
                $data['FirstName'] = $data['UserName'];
                $data['LastName'] = $data['UserName'];
                $data['Password'] = !empty($Password)?encry_password($Password):encry_password(get_random_key(8));
                $data['SiteID'] = $SiteID;
                $data['SourceType'] = $SourceType;
                $data['CountryCode'] = $CountryCode;
                $data['Status'] = $Status;
                $data['ClientSource'] = $ClientSource;
                $validateCustomer = $this->validateCustomer(['Email'=>$Email]);
                if($validateCustomer['code']!=200){
                    return $validateCustomer;
                }

                $res = $Customer->addCustomer($data);
                if($res>0){
                    /*用户订阅*/
                    $Subscriberdata['EmailUserName'] = $EmailUserName;
                    $Subscriberdata['EmailDomainName'] = $EmailDomainName;
                    $user_subscriber = model("Subscriber")->getSubscriber($Subscriberdata);
                    if(!$user_subscriber){
                        $Subscriberdata['CustomerId'] = $res;
                        $Subscriberdata['Active'] = 1;
                        $Subscriberdata['SiteId'] = input("SiteId",1);
                        $Subscriberdata['CreateTime']=$Subscriberdata['AddTime'] = time();
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
                    if($RegisterCouponInfo['CouponTime']['EndTime']<strtotime('+1month')){
                        $end_time = $RegisterCouponInfo['CouponTime']['EndTime'];
                    }else{
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
                            'username'=>$data['UserName'],
                            'new_email'=>$Email,
                            'password'=>$Password,
                        ]
                    );
                    return apiReturn(['code'=>200,'data'=>$res_data]);
                }else{
                    return apiReturn(['code'=>1002]);
                }
            }else{
                return apiReturn(['code'=>1007]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /*
     * 根据用户ID、账号或邮箱批量获取用户信息
     * @Return: array
     * */
    public function getAdminCustomerData(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.getAdminCustomerData");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            if($paramData['field_type'] == 3){
                $user_data = array();
                foreach ($paramData['user_data'] as $key=>$value){
                    $email_array = explode("@",$value);
                    $EmailDomainName = $email_array[1];
                    vendor('aes.aes');
                    $aes = new aes();
                    $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
                    $email_data['EmailUserName'] = $EmailUserName;
                    $email_data['EmailDomainName'] = $EmailDomainName;
                    $user_email_data = model("Customer")->getSendMsgCustomer($email_data);
                    if($user_email_data){
                        $user_data[] = model("Customer")->getSendMsgCustomer($email_data);
                    }
                }

            }elseif ($paramData['field_type'] == 2){
                $where['ID'] = ['in',$paramData['user_data']];
                $user_data = model("Customer")->getSendMsgCustomer($where,2);
            }elseif ($paramData['field_type'] == 1){
                $where['UserName'] = ['in',$paramData['user_data']];
                $user_data = model("Customer")->getSendMsgCustomer($where,2);
            }

            if($user_data){
                vendor('aes.aes');
                $aes = new aes();
                foreach ($user_data as $key=>$val){

                    if(isset($val['EmailUserName'])) {
                        $EmailUserName = $aes->decrypt($val['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                        $user_data[$key]['email'] = $EmailUserName.'@'.$val['EmailDomainName'];
                    }
                }
                return apiReturn(["code"=>200,"data"=>$user_data]);
            }else{
                return apiReturn(["code"=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
    * 生成短链接
     * yxh by 20190409
    */
    public function createShortLink()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, [
                ['long', 'require']
            ]);
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }

            $data['code'] = getGuid();
            $data['content'] = $paramData['long'];
            $res = (new ShortLink())->insert($data);
            if ($res > 0) {
                return apiReturn(['code' => 200, 'data' => $data['code']]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }

    /*
    * 获得短链接
     * yxh by 20190409
    */
    public function getShortLink()
    {
        try {
            $paramData = request()->post();
            $validate = $this->validate($paramData, [
                ['code', 'require']
            ]);
            if (true !== $validate) {
                return apiReturn(['code' => 1002, "msg" => $validate]);
            }

            $where['code']=$paramData['code'];
            $data = (new ShortLink())->get($where);
            if (!empty($data['content'])) {
                return apiReturn(['code' => 200, 'data' => $data['content']]);
            } else {
                return apiReturn(['code' => 1006]);
            }
        } catch (\Exception $e) {
            return apiReturn(['code' => 1002, 'msg' => $e->getMessage()]);
        }
    }


    /*上传用户头像*/
    public function updatePhotoPath(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.updatePhotoPath");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $res = $this->remoteUpload();
            if(isset($res['code']) && $res['code'] == 200){
                $data['ID'] = $paramData["ID"];
                $data['PhotoPath'] = $res['url'];
                $res = $this->saveProfile($data);
                if($res['code'] == 200){
                    $res['data']['PhotoPath'] = $data['PhotoPath'];
                }
                return $res;
            }
            return $res;
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
    * 远程上传
    * */
    public function remoteUpload(){
        $localres = localUpload();
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['PHOTO_IMAGES'].date("Ymd");
            $config = [
                'dirPath'=>$remotePath, // ftp保存目录
                'romote_file'=>$localres['FileName'], // 保存文件的名称
                'local_file'=>$localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            $baseurl =config('cdn_url_config.url');
            if($upload){
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['complete_url'] = $baseurl.$remotePath.'/'.$localres['FileName'];
                $res['url'] = $remotePath.'/'.$localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            return $res;
        }else{
            return $localres;
        }
    }
}
