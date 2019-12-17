<?php
namespace app\lms\dxcommon;

use app\admin\statics\BaseFunc;
use think\Controller;
use think\Log;

/**
 * 基础API类
 * Class BaseApi
 * @author tinghu.liu
 * @date 2018-04-23
 * @package app\admin\dxcommon
 */
class BaseApi extends API
{
   /**
   * [LMS description]
   * 同步LMS系统渠道数据数据接口
   * author: Wang
   * AddTime:2018-04-26
   */
    public static function logistics($data=array()){
         $logistics      = config('logistics');//var_dump(config('lms_base_url').'/'.$logistics['url']);exit;
         // var_dump(curl_request_lms(config('lms_base_url').'/'.$logistics['url'],$data));
         return curl_request_lms(config('lms_base_url').'/'.$logistics['url'],$data);
    }
    public static function LogisticsUpdateSeller($data){
         $LogisticsUpdateSeller      = config('LogisticsUpdateSeller');
         // $data['access_token'] = $logistics['access_token'];
         return curl_request(config('api_base_url').'/'.$LogisticsUpdateSeller['url'],$data);
    }




}
