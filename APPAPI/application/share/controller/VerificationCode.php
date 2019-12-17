<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use think\Controller;
use think\Exception;
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
        $data['VerificationCode'] = mt_rand(100000,999999);
        /*过期时间*/
        $data['ExpiryTime'] = isset($paramData['ExpiryTime'])?(int)$paramData['ExpiryTime']:time()+7200;
        /*验证码类型*/
        $IsDeleteOld = isset($paramData['IsDeleteOld'])?(int)$paramData['IsDeleteOld']:0;
        if($IsDeleteOld){
            $delete_where['UserId'] = $data['UserId'];
            $delete_where['UserType'] = $data['UserType'];
            $delete_where['Type'] = $data['Type'];
        }else{
            $delete_where = $data;
            unset($delete_where['ExpiryTime']);
        }
        $delete_res = model("share/VerificationCode")->deleteVerificationCode($delete_where);
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
        return apiReturn(['code'=>200]);
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
        $res =  model("VerificationCode")->checkVerificationCode($data);
        if($res>0){
            return true;
        }else{
            return false;
        }
    }
}
