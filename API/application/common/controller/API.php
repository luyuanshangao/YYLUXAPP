<?php
namespace app\common\controller;

use app\admin\statics\BaseFunc;
use app\common\controller\Base;
use think\Controller;
use think\Log;

/**
 * API基类
 * Class API
 * @author tinghu.liu
 * @date 2018-04-23
 * @package app\admin\dxcommon
 */
class API extends Controller
{
    /**
     * @var $access_token_str
     */
    protected static $access_token_str;
    public function _initialize()
    {
//        self::$access_token_str = '?access_token='.$this->getAccessToken();
        self::$access_token_str = '';
    }

    /**
     * 获取接口access_token
     * @return mixed
     */
    public function getAccessToken(){
        return (new Base())->makeSign();
        //后期如果API做token请求次数限制的话，可将获取的access_token存进数据库或缓存
        /*$data = json_decode(BaseFunc::curl_request(config('api_base_url').'/demo/auth/accessToken'), true);
        if(!$data['access_token']){
            return "sdfgdsgdsgdsg";
        }else{
            return $data['access_token'];
        }*/
    }




}
