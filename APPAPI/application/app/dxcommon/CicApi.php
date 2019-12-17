<?php
namespace app\app\dxcommon;

use think\Controller;
use think\Log;

/**
 * 专门给cic使用的
 * Class BaseApi
 * @author tinghu.liu
 * @date 2018-04-23
 * @package app\admin\dxcommon
 */
class CicApi extends API
{
    /**
     * 验证用户注册邮箱
     * @param $data
     * @return $data
     */
    public static function validateCustomer($data){
        $url = CIC_API."cic/Customer/validateCustomer";
        return json_decode(accessTokenToCurl($url,null,json_encode($data),true), true);
    }

    /**
     * 登录接口
     * @param $data
     * @return $data
     */
    public static function LoginForToken($data){
        $url = CIC_API."cic/Customer/LoginForToken";
        return json_decode(accessTokenToCurl($url,null,json_encode($data),true), true);
    }

}
