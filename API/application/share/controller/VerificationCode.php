<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use think\Controller;
use think\Exception;
use think\Log;
use think\Validate;

/**
 * 验证码接口
 */
class VerificationCode extends Base
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 验证码新增
     * @return mixed
     */
    public function createVerificationCode($pdata=''){
        /*每分钟最多发送次数*/
        $OneMinuteSendNum = 20;
        $paramData = !empty($pdata)?$pdata:request()->post();
        //过滤不必要的参数
        if(!isset($paramData['UserId'])){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
        if(is_numeric($paramData['UserId'])){
            $data['UserId'] = (int)$paramData['UserId'];
        }else{
            $data['UserId'] = $paramData['UserId'];
        }
        /*用户类型：1买家 2卖家 3管理员*/
        $data['UserType'] = isset($paramData['UserType'])?(int)$paramData['UserType']:1;
        $data['Type'] = isset($paramData['Type'])?$paramData['Type']:"";
        $count_where = $data;
        $count_where['AddTime'] = ['gt',time()-60];
        $verification_code_count = model("share/VerificationCode")->getVerificationCodeCount($count_where);
        if($verification_code_count>$OneMinuteSendNum){
            return apiReturn(['code'=>1002, 'msg'=>'The frequency of acquiring authentication code is too high. Please try again later.']);
        }
        $data['VerificationCode'] = mt_rand(100000,999999);
        $data['AddTime'] = time();
        /*逻辑删除，删除时间，默认是0，表示未删除*/
        $data['DeleteTime'] = 0;
        /*过期时间*/
        $data['ExpiryTime'] = isset($paramData['ExpiryTime'])?(int)$paramData['ExpiryTime']:time()+7200;
        /*验证码类型*/
        $IsDeleteOld = isset($paramData['IsDeleteOld'])?(int)$paramData['IsDeleteOld']:0;
        /*判断是否删除旧验证*/
        if($IsDeleteOld){
            $update_where['UserId'] = $data['UserId'];
            $update_where['UserType'] = $data['UserType'];
            $update_where['Type'] = $data['Type'];
            $update_data['DeleteTime'] = time();
            $delete_res = model("share/VerificationCode")->updateVerificationCode($update_where,$update_data);
            if(!$delete_res){
                //Log::write("CreateVerificationCode updateVerificationCode error,delete_where:".json_encode($update_where));
            }
        }
        $res = model("share/VerificationCode")->createVerificationCode($data);
        if($res){
            $ret = apiReturn(['code'=>200, 'data'=>$data]);
        }else{
            $ret = apiReturn(['code'=>1002, 'data'=>'error']);
        }
        return $ret;
    }

    /*
     * 删除验证码
     * */
    public function deleteVerificationCode(){
        $paramData = request()->post();
        //过滤不必要的参数
        if(!isset($paramData['UserId'])){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
        if(is_numeric($paramData['UserId'])){
            $data['UserId'] = (int)$paramData['UserId'];
        }else{
            $data['UserId'] = $paramData['UserId'];
        }
        /*用户类型：1买家 2卖家 3管理员*/
        $data['UserType'] = isset($paramData['UserType'])?(int)$paramData['UserType']:1;
        $data['Type'] = isset($paramData['Type'])?$paramData['Type']:"";
        $res =  model("VerificationCode")->deleteVerificationCode($data);
        if($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 验证验证码
     * */
    public function checkVerificationCode(){
        $paramData = request()->post();
        //过滤不必要的参数
        if(!isset($paramData['UserId'])){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
        if(is_numeric($paramData['UserId'])){
            $data['UserId'] = (int)$paramData['UserId'];
        }else{
            $data['UserId'] = $paramData['UserId'];
        }
        /*用户类型：1买家 2卖家 3管理员*/
        $data['UserType'] = isset($paramData['UserType'])?(int)$paramData['UserType']:1;
        $data['Type'] = isset($paramData['Type'])?$paramData['Type']:"";
        $data['VerificationCode'] = isset($paramData['VerificationCode'])?(int)$paramData['VerificationCode']:"";
        /*过期时间*/
        $data['ExpiryTime'] = ['>',time()];
        $data['DeleteTime'] = 0;
        $res =  model("VerificationCode")->checkVerificationCode($data);
        if($res>0){
            unset($data['ExpiryTime']);
            unset($data['DeleteTime']);
            $delete_res =  model("VerificationCode")->deleteVerificationCode($data);
            if(!empty($delete_res)){
                //Log::write("checkVerificationCode deleteVerificationCode error,data".json_encode($data));
            }
            return true;
        }else{
            return false;
        }
    }
}
