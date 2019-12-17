<?php
namespace app\sso\controller;
use app\common\controller\Base;
use think\Log;
use vendor\aes\aes;
use think\cache\driver\Redis;
use think\Db;
class Token extends Base
{
/*
 * 获取用户token是否存在，不存在新增，存在更改过期时间
 * @param int cicID
 * @param isremember 是否记住密码
 * @Return: array
 * */
    public function getToken(){
        try{
            $param_data = request()->post();
            /*验证参数*/
            $validate = $this->validate($param_data,"Token.getToken");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $cicID = input("cicID/d");
            if(empty($cicID)){
                return apiReturn(['code'=>1001]);
            }
            $isremember = input("isremember/b",false);
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
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /*
    * 判断Token是否有效
    * @param  cicID token选填
    * @Return: array
    * */
    public function checkTokenValid()
    {
        try{
            $param_data = request()->post();
            /*验证参数*/
            $validate = $this->validate($param_data,"Token.checkTokenValid");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $token = input("token");
            $Token = model('Token');
            if(empty($Token)){
                return apiReturn(['code'=>1001]);
            }
            /*获取Token*/
            $token_res = $this->getTokenByKey($token);
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
                return apiReturn(['code'=>1041]);
            }else{
                /*为了节省mysql服务器资源，暂时关闭刷新登录过期时间 20190722 kevin*/
                /*$token_data['token'] = $token_res['token'];
                Log::pathlog("updateToken","checkTokenValid updateToken StartTime:".time(),$log_name);
                $update_token = $Token->updateToken($token_data);
                Log::pathlog("updateToken","checkTokenValid updateToken EndTime:".time(),$log_name);*/
                return apiReturn(['code'=>200]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /*
    * 更改token失效时间
    * @param  cicID token选填
    * @Return: array
    * */
    public function updateTokenTimeout()
    {
        try{
            $param_data = request()->post();
            /*验证参数*/
            $validate = $this->validate($param_data,"Token.updateTokenTimeout");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $cicID = input("cicID");
            $Token = model('Token');
            if(!empty($Token)){
                $token_where['cicID'] = $cicID;
            }else{
                return apiReturn(['code'=>1001]);
            }
            $token_res = $Token->getToken($token_where);
            if(empty($token_res)){
                return apiReturn(['code'=>1041]);
            }
            $token_data['timeout'] = $param_data['timeout'];
            $where['cicID'] = $param_data['cicID'];
            $update_token = $Token->updateToken($token_data,$where);
            return apiReturn(['code'=>200,'data'=>$update_token]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /*
     * 获取用户token
     * */
    private function getTokenByKey($token){
        /*判断是否开启redis保存用户Token*/
        $token_where['token'] = $token;
        $Token = model('Token');
        if(config('cache_switch_on')){
            $redis = new Redis(config("redis_sso_config"));
            $redis_token_res = $redis->get(USERTOKEN.$token);
            $token_res = json_decode($redis_token_res,true);
            if(empty($token_res)){
                $token_res = $Token->getToken($token_where);
                if(!empty($token_res['token'])){
                    $redis->set(USERTOKEN.$token_res['token'],json_encode($token_res),3600);
                }
            }
        }else{
          /*$log_name = "sso";
            Log::pathlog("getToken","checkTokenValid getToken StartTime:".time(),$log_name);*/
            $token_res = $Token->getToken($token_where);
            //Log::pathlog("getToken","checkTokenValid getToken EndTime:".time(),$log_name);
        }
        return $token_res;
    }
}
