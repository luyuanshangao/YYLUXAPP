<?php
namespace app\admin\model;
use think\Model;
use think\Db;
/**
 * 用户模型
 * @author
 * @version Kevin 2018/3/15
 */
class Customer extends Model{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
     * 查询判断用户是否存在
     * */
    public function isCustomer($type, $AccountName,$SourceType = 1,$SiteID = 1){
        $db = Db::connect('db_cic');
        if($type == 'email'){
            $where['EmailUserName'] = $AccountName['EmailUserName'];
            $where['EmailDomainName'] = $AccountName['EmailDomainName'];
        }else{
            $where['UserName'] = $AccountName;
        }
        $where['SourceType'] = $SourceType;
        $where['SiteID'] = $SiteID;
        $ID = $db->name('customer')->where($where)->value("ID");
        return $ID;
    }



    /*
      * 查询判断用户是否存在
      * */
    public function checkLogin($type, $AccountName,$SiteID = 1,$Password){
        $db=Db::connect('db_cic');
        if($type == 'email'){
            $where['EmailUserName'] = $AccountName['EmailUserName'];
            $where['EmailDomainName'] = $AccountName['EmailDomainName'];
        }else{
            $where['UserName'] = $AccountName;
        }
        $where['SiteID'] = $SiteID;
        $where['Password'] = $Password;
        $CustomerS = $db->name('customer')->where($where)->field("ID,UserName,SiteID,EmailUserName,EmailDomainName,Status,UpdateTime,FirstName,LastName,Gender,PhotoPath,Birthday,Telephone")->find();
        return $CustomerS;
    }

    /*
      * 获取用户信息
      * @param int $ID 用户ID
      * @Return: array
      * */
    public function getCustomer($ID,$type=1){
        $db = Db::connect('db_cic');
        $where['ID'] = $ID;
        if($type == 1){
            $Customer = $db->name('customer')->where($where)->field("ID,UserName,SiteID,EmailUserName,EmailDomainName,Status,UpdateTime,FirstName,LastName,Gender,PhotoPath,Birthday,Telephone")->find();
        }else{
            $Customer = $db->name('customer')->where($where)->field("ID,UserName,SiteID,EmailUserName,EmailDomainName,Status,FirstName,MiddleName,LastName,Gender,Education,MaritalStatus,Birthday,CountryCode,PhotoPath,Hobby,Income,RegisterOn,CreateOn,UpdateTime")->find();
        }
        return $Customer;
    }

    /*
     *
     * 获取用户基本信息
     * */
    public function getBaseCustomer($ID){
        $db = Db::connect('db_cic');
        $where['ID'] = $ID;
        $Customer = $db->name('customer')->where($where)->field("ID,UserName,SiteID,EmailUserName,EmailDomainName,Status,UpdateTime,FirstName,LastName,Gender,PhotoPath")->find();
        return $Customer;
    }

    /*
     * 添加用户
     * */
    public function addCustomer($data){
        $db = Db::connect('db_cic');
        $data['CreateOn'] = time();
        $data['RegisterOn'] = time();
        $res = $db->name('customer')->insertGetId($data);
        return  $res;
    }

    /*
     * 修改用户资料
     * */
    public function saveProfile($ID,$data){
        $db = Db::connect('db_cic');
        $where['ID'] = $ID;
        $res = $db->name('customer')->where($where)->update($data);
        return $res;
    }

    /*
     * 检测用户密码是否正确
     * */
    public function confirmPassword($where,$Old_Password){
        $db = Db::connect('db_cic');
        $where['Password'] = $Old_Password;
        $res = $db->name('customer')->where($where)->count();
        return $res;
    }

    /*
     * 修改密码
     * */
    public function changePassword($where,$New_Password){
        $db = Db::connect('db_cic');
        $data['Password'] = $New_Password;
        $res = $db->name('customer')->where($where)->update($data);
        return $res;
    }

    /*
     * 添加密码更改记录
     * */
    public function changepasswordHistory($data){
        $db = Db::connect('db_cic');
        $res = $db->name('changepassword_history')->insertGetId($data);
        return  $res;
    }

    /*
     * 添加用户其他信息
     * */
    public function addCustomerOther($data){
        $db = Db::connect('db_cic');
        $res = $db->name('customer_other')->insertGetId($data);
        return  $res;
    }

    /*
     * 添加用户登录信息
     * */
    public function addLoginHistory($data){
        $db = Db::connect('db_cic');
        $res = $db->name('login_history')->insertGetId($data);
        $c_where['ID'] = $data['CustomerID'];
        $c_data['LastLoginDate'] = time();
        $db->name('customer')->where($c_where)->update($c_data);
        return  $res;
    }

    /*
    * 添加系统操作信日志
    * */
    public function addSystemLog($data){
        $db = Db::connect('db_cic');
        $res = $db->name('system_log')->insertGetId($data);
        return  $res;
    }
    /*
        * 添加错误日志
        * */
    public function addErrorLog($data){
        $db = Db::connect('db_cic');
        $res = $db->name('error_log')->insertGetId($data);
        return  $res;
    }

    /*
    * 判断是否存在
    * */
    public function getCount($where){
        $db = Db::connect('db_cic');
        $count = $db->name('customer_app')->where($where)->count();
        return  $count;
    }

    /*
    * 添加用户APP推送令牌
    * */
    public function addCustomerAPP($data){
        $db = Db::connect('db_cic');
        $res = $db->name('customer_app')->insertGetId($data);
        return  $res;
    }
    /*
    * 用户APP推送令牌
    * */
    public function updateCustomerAPP($data,$where){
        $db = Db::connect('db_cic');
        $res = $db->name('customer_app')->where($where)->update($data);
        return  $res;
    }

    /*
     * 获取用户列表
     * */
    public function getCustomerList($where,$page_size=20,$page=1,$path='',$query = '',$count){
        $db = Db::connect('db_cic');
        // $query = !empty($query)?$query:$where;
        $data = array();
        // $Customer = $db->name('customer')
        //             // ->alias("c")
        //             ->order("ID desc")
        //             ->where($where)->field("ID,UserName,SiteID,EmailUserName,EmailDomainName,FirstName,MiddleName,LastName,Gender,OrderCount,Status,RegisterOn,CountryCode,Birthday,Telephone,OrderCount,LastLoginDate,ClientSource")->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$query]);
        if(empty($count)){
          $count = $db->name('customer')->where($where)->count();
        }else{
          $count = $count;
        }
        $Customer = $db->name('customer')
                    // ->alias("c")
                    ->order("ID desc")
                    ->where($where)
                    ->field("ID,UserName,SiteID,EmailUserName,EmailDomainName,FirstName,MiddleName,LastName,Gender,OrderCount,Status,RegisterOn,CountryCode,Birthday,Telephone,OrderCount,LastLoginDate,ClientSource")
                    ->page($page,$page_size)
                    ->select();

        $data['data'] = $Customer;
        // $Page = $count;
        $data["count"] = $count;
        return $data;
    }

    /*
     * 获取用户
     * */

    /*
     * 修改用户状态
     * */
    public function updateStatus($data){
        $db = Db::connect('db_cic');
        $Customer = $db->name('customer')->update($data);
        return $Customer;
    }

    /*
     * 获取affiliate最后一个ID
     * */
    public function getLastAffiliateID(){
        $db = Db::connect('db_cic');
        return $db->name('affiliate_level')->order("ID desc")->value("ID");
    }

    /*
     * 获取用户邮箱表数据
     * */
    public function getCustomerEmail($where){
        $db = Db::connect('db_cic');
        return $db->name('customer_email')->where($where)->find();
    }
    /*
     * 添加用户邮箱
     * */
    public function addCustomerEmail($data){
        $db = Db::connect('db_cic');
        return $db->name('customer_email')->insertGetId($data);
    }
    /*
     * 根据ID获取邮箱
     * */
    public function GetEmailsByCIDs($ids,$IsSubscriber=''){
        $db = Db::connect('db_cic');
        if($IsSubscriber == true){
            $where['s.Active'] = 1;
            $where['c.ID'] = ['in',$ids];
            $res = $db->name('customer')
                    ->alias('c')
                    ->join("cic_subscriber s","c.ID = s.CustomerId","LEFT")
                    ->where($where)
                    ->field("c.EmailUserName,c.EmailDomainName,c.ID")
                    ->group("c.ID")
                    ->select();
        }else{
            $where['ID'] = ['in',$ids];
            $res = $db->name('customer')->where($where)->field("EmailUserName,EmailDomainName,ID")->select();
        }
        return $res;
    }

    /*
     * 根据ID获取单个用户邮箱
     * */
    public function getEmailsByCID($id,$IsSubscriber=''){
        $db = Db::connect('db_cic');
        if($IsSubscriber == true){
            $where['c.ID'] = $id;
            $res = $db->name('customer')
                ->alias('c')
                ->join("cic_subscriber s","c.ID = s.CustomerId")
                ->where($where)
                ->field("c.EmailUserName,c.EmailDomainName,c.ID")
                ->find();
        }else{
            $where['ID'] = $id;
            $res = $db->name('customer')->where($where)->field("EmailUserName,EmailDomainName,ID")->find();
        }
        return $res;
    }

    /*
     * 根据ID获取用户是否是新用户
     * */
    public function checkIsNewByID($id){
        $db = Db::connect('db_cic');
        $where['ID'] = $id;
        $res = $db->name('customer')->where($where)->field("ID,IsNew")->find();
        return $res;
    }

    /*获取后台用户详情*/
    public function getAdminCustomerInfo($where)
    {
        $customer = $this->db->name('customer')->where($where)->find();
        if ($customer) {
            $affiliate = $this->db->name('affiliate_level')->where(['CustomerID'=>$customer['ID']])->value("RCode");
            $customer['affiliate'] = !empty($affiliate)?$affiliate:"false";
            $subscriber = $this->db->name('subscriber')->where(['CustomerId'=>$customer['ID']])->value("Active");
            if($subscriber){
                $customer['is_subscriber'] = true;
            }else{
                $customer['is_subscriber'] = false;
            }
            $customer['points'] = $this->db->name('points_basic_info')->where(['CustomerID'=>$customer['ID']])->value("UsableCount");
            $customer['referral_points'] = $this->db->name('referral_points_basic_info')->where(['CustomerID'=>$customer['ID']])->value("UsableCount");
            $customer['store_cardit'] = $this->db->name('store_cardit_basic_info')->where(['CustomerID'=>$customer['ID']])->column("CurrencyType,UsableAmount");
            if($customer['store_cardit']){
                foreach ($customer['store_cardit'] as $key=>&$value){
                    $value = getCurrency('',$key)." ".$value;
                }
            }

        }
        return $customer;
    }

    /*
     * 获取发送站内信用户
     * type 1.单条 2.多条
     * */
    public function getSendMsgCustomer($where,$type=1){
        if($type == 1){
            return $this->db->name('customer')->where($where)->field('ID,UserName,EmailUserName,EmailDomainName')->find();
        }else{
            return $this->db->name('customer')->where($where)->field('ID,UserName,EmailUserName,EmailDomainName')->select();
        }

    }
}