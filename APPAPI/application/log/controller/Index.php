<?php
namespace app\log\controller;
use app\common\controller\Base;
use app\common\helpers\RedisClusterBase;
use think\cache\driver\Redis;
use think\Db;
class Index extends Base
{
    public function operationLog($data = ''){
        try{
            $param_data = !empty($data) ? $data : request()->post();
            $redis_cluster = new Redis(config("log_redis_cluster_config"));
            if(is_array($param_data) && !empty($param_data)){
                $res = $redis_cluster->lPush(
                    "operation_log_key",json_encode($param_data)
                );
                if($res){
                    return apiReturn(['code'=>'200']);
                }
            }
            return apiReturn(['code'=>'1002','msg'=>"Queue Redis Failure"]);
        }catch (\Exception $e){
            return apiReturn(['code'=>'1002','msg'=>$e->getMessage()]);
        }
    }
}
