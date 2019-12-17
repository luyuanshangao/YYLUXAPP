<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\RedisClusterBase;

/**
 * 缓存处理接口类
 *
 * 步骤：
 * 1、获取access_token
 * http://api.localhost.com/share/CacheHandle/getToken?sign=dda3f60f7830a99df118e3bdc3a8a0b0344f9b01bdb2f0732069927dbd6fc675
 *
 * 2、根据access_token和指定的key清除缓存
 * http://api.localhost.com/share/CacheHandle/rmRedis?access_token=2bc5becefebb146284bcb844150f852b&key=PayTypeUSD
 *
 * 3、redis_key汇总
 * （1）支付方式：'PayType'.$Currency，如：'PayTypeUSD'
 *
 * @author tinghu.liu
 * @version
 * 2018-08-25
 */
class CacheHandle extends Base
{
    protected $redisHandle;
    protected $sign;
    public function __construct()
    {
        parent::__construct();
        $this->redisHandle = new RedisClusterBase();
        //dda3f60f7830a99df118e3bdc3a8a0b0344f9b01bdb2f0732069927dbd6fc675
        $this->sign = hash('sha256',md5(base64_encode('dx_api_CacheHandle_sign')));
    }

    /**
     * 清除redis缓存数据
     * @return \think\response\Json
     */
    public function rmRedis(){
        $key = input('key');
        try{
            if (
                !empty($key)
                && $this->redisHandle->rm($key)
            ){
                return json(['code'=>200, 'msg'=>'success']);
            }else{
                return json(['code'=>0, 'msg'=>'fail']);
            }
        }catch (\Exception $e){
            return json(['code'=>0, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取清除缓存access token
     * @return \think\response\Json
     */
    public function getToken(){
        $sign = input('sign', '');
        if (
            $sign != ''
            && $sign == $this->sign
        ){
            return json([self::TOKEN_KEY=>$this->makeSign()]);
        }else{
            return json(['code'=>0, 'msg'=>'sign error']);
        }
    }
}
