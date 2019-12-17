<?php
namespace app\index\dxcommon;

use app\index\model\UserModel;

/**
 * Class User
 * @author tinghu.liu
 * @date 2018-03-09
 * @package app\index\dxcommon
 */
class User
{
    /**
     * 注册数据校验
     * @param $data 要校验的数据
     * @return array
     */
    public static function verifyRegisterData($data){
        $rtn = ['code'=>-1,'msg'=>''];
        $password = trimall($data['password']);
        $confirm_password = trimall($data['confirm_password']);
        /*$first_name = trimall($data['first_name']);
        $last_name = trimall($data['last_name']);*/
        $true_name = trimall($data['true_name']);
        $phone_num = trimall($data['phone_num']);
        if (empty($password) || empty($confirm_password) || empty($true_name) || empty($phone_num)){
            $rtn['msg'] = '密码或真实姓名或手机为必填项';
        }else{
            if ($password != $confirm_password || (get_str_length($password) < 6 || get_str_length($password) > 20)){
                $rtn['msg'] = '两次密码不正确或大小不在6-20内';
            }else{
                if (!is_phone_num($phone_num)){
                    $rtn['msg'] = '手机号错误';
                }else{
                    $rtn['code'] = 0;
                }
            }
        }
        return $rtn;
    }

    /**
     * 根据商家ID获取商家信息
     * @param $seller_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getSellerInfoBySellerID($seller_id){
        $seller_img_url = config('seller_img_url');
        $model = new UserModel();
        $data = $model->getInfoById($seller_id);
        //经营模式
        $management_model = Base::getManageModel($data['extension']['management_model']);
        $data['extension']['management_model_str'] = isset($management_model['name']) && !empty($management_model['name'])?$management_model['name']:'';
        //拼装图片地址
        $data['extension']['business_license_pic_url'] = $seller_img_url.'/seller/'.$data['extension']['business_license_pic'];
        $data['extension']['corporation_idcard_facade_url'] = $seller_img_url.'/seller/'.$data['extension']['corporation_idcard_facade'];
        $data['extension']['corporation_idcard_reverse_url'] = $seller_img_url.'/seller/'.$data['extension']['corporation_idcard_reverse'];
        $data['extension']['idcard_facade_url'] = $seller_img_url.'/seller/'.$data['extension']['idcard_facade'];
        $data['extension']['idcard_reverse_url'] = $seller_img_url.'/seller/'.$data['extension']['idcard_reverse'];
        return $data;
    }

    /**
     * 新增子账号数据验证
     * @param array $params
     * @return array
     */
    public static function verifyAddchildAcctData(array $params){
        $rtn = ['code'=>-1,'msg'=>''];
        //邮箱唯一性、电话号码唯一性、seller code唯一性判断
        $parent_info = self::getSellerInfoBySellerID($params['parent_id']);
        $model = new UserModel();
        $email_data = $model->getInfoByPhoneNum($params['email']);
        $phone_num_data = $model->getInfoByPhoneNum($params['phone_num']);
        if (!empty($email_data)){
            $rtn['msg'] = '邮箱已存在';
        }elseif (!empty($phone_num_data)){
            $rtn['msg'] = '电话号码已存在';
        }else{
            $rtn['code'] = 0;
            $rtn['management_model'] = $parent_info['extension']['management_model'];
            $rtn['msg'] = 'success';
        }
        return $rtn;
    }



}
