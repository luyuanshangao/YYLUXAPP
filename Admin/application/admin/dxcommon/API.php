<?php
namespace app\admin\dxcommon;

use think\Controller;
use think\Log;

/**
 * API基类
 * Class API
 * @author tinghu.liu
 * @date 2018-03-28
 * @package app\index\dxcommon
 */
class API extends Controller
{
    /**
     * @var $access_token_str
     */
    protected static $access_token_str;
    public function _initialize()
    {
        self::$access_token_str = Api_token();//echo 111;
        //dump(self::$access_token_str);
    }

    /**
     * 获取接口access_token
     * @return mixed
     */
    public function getAccessToken(){
        //后期如果API做token请求次数限制的话，可将获取的access_token存进数据库或缓存
        $data = json_decode(curl_request(config('api_base_url').'/demo/auth/accessToken'), true);
        return $data['access_token'];
    }


}
