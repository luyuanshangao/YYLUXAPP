<?php
namespace app\common\services;


use think\Cookie;
use think\Log;
use think\Session;

class LoginService extends Api
{

    /*
 * 登录首页
 */
    public function login($params)
    {

        $user['AccountName'] = $params['user_name'];
        $user['Password'] = $params['password'];

        /*调用GetCustomerInofByAccount,验证用户名是否存在*/
        $data = doCurl(CIC_API.'/cic/Customer/GetCustomerInfoByAccount',['AccountName'=>$user['AccountName']],[
            'access_token' => $this->getAccessToken()
        ]);
        if($data['code'] != 200){
            Log::record('/cic/Customer/GetCustomerInfoByAccount:res:'.json_encode($data), Log::NOTICE);
            return false;
        }
        $LoginF['AccountName'] = $user['AccountName'];
        $LoginF['SiteID'] = 1;
        $LoginF['Password'] = $user['Password'];
        $login_res = doCurl(CIC_API.'cic/Customer/LoginForToken',$LoginF,[
            'access_token' => $this->getAccessToken()
        ]);

        switch ($login_res['code']){
            case '200':
                $expires = $login_res['data']['Token']['timeout'] - time();
                Cookie::set("DXSSO",$login_res['data']['Token']['token'],['domain'=>MALL_DOMAIN,'expire'=>$expires]);
                if(isset($login_res['data']) && !empty($login_res['data'])){
                    unset($login_res['data']['Token']);
                    Session::set('cstomer',$login_res['data']);
                }else{
                    Cookie::set('LoggingOn',0,['domain'=>MALL_DOMAIN]);
                }
                return true;
                break;
            default:
                Log::record('/cic/Customer/LoginForToken:res:'.json_encode($login_res), Log::NOTICE);
                return false;
                break;
        }
    }

    /**
     * 获取用户的TaxId
     * @param $uid cicid
     * @return array
     */
    public function findCustomerTaxIdService($uid){
        if(empty($uid))
            return [];
        $result = doCurl(CIC_API.'/cic/Customer/FindCustomerTaxId',['Cicid'=>$uid,'IdType'=>1],[
            'access_token' => $this->getAccessToken()
        ]);
        if($result['code'] != 200){
            //TODO Write log
            return [];
        }
        return $result;
    }

    /**
     * 获取用户的TaxId
     * @param $uid cicid
     * @param $taxid
     * @return array
     */
    public function editCustomerTaxIdService($uid,$taxid){
        if(empty($uid) || empty($taxid))
            return false;
        $data['Cicid'] = $uid;
        $data['IdType'] = 1;
        $data['TaxId'] = $taxid;
        $data['PersonalId'] = 0;
        $result = doCurl(CIC_API.'/cic/Customer/AddOrUpdateCustomerTaxId',$data,[
            'access_token' => $this->getAccessToken()
        ]);
        if($result['code'] != 200){
            //TODO Write log
            return false;
        }
        return true;
    }


    /**
     * 邮箱登录
     * @param $params
     * @return \think\response\Json
     */
    public function emailLogin($params)
    {
        $data = doCurl(CIC_API.'/cic/Customer/autoRegisterCustomer',['Email'=>$params['email']],[
            'access_token' => $this->getAccessToken()
        ]);
        return $data;

    }

}