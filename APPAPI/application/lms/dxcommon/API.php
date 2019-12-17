<?php
namespace app\lms\dxcommon;

// use app\lms\statics\BaseFunc;
use think\Controller;
use think\Log;

/**
 * API基类
 * Class API
 * @date 2018-04-26
 * @package app\lms\dxcommon
 */
class API extends Controller
{
    /**
     * @var $access_token_str
     */
    protected static $access_token_str;
    public function _initialize()
    {
        self::$access_token_str = '?access_token='.$this->getAccessToken();
    }

    /**
     * 获取接口access_token
     * @return mixed
     */
    public function getAccessToken(){
        //后期如果API做token请求次数限制的话，可将获取的access_token存进数据库或缓存
        $data = json_decode(BaseFunc::curl_request(config('api_base_url').'/demo/auth/accessToken'), true);
        if(!$data['access_token']){
            return "sdfgdsgdsgdsg";
        }else{
            return $data['access_token'];
        }
    }




}
