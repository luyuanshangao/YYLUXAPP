<?php
namespace app\index\controller;
use app\common\params\IndexParams;
use app\index\dxcommon\User;
use app\index\model\UserModel;


/**
 * 账号管理类
 * @author tinghu.liu
 * @date 2018-06-02
 * @package app\index\controller
 * seller 首页
 */
class AccountManage extends Common
{

    /**
     * 认证详情
     * @return mixed
     */
    public function index()
    {


        $this->assign('parent_menu','account-manage');
        $this->assign('child_menu','account-manage-auth-detail');
        return $this->fetch('index');
    }

    /**
     * 银行信息管理
     */
    public function bankInfoManage(){


        $this->assign('parent_menu','account-manage');
        $this->assign('child_menu','account-manage-bank-manage');
        return $this->fetch();
    }

    /**
     * 账号信息管理
     */
    public function acctManage(){
        $seller_info = User::getSellerInfoBySellerID($this->real_login_user_data['user_id']);
        if(request()->isAjax()){
            $old_password = input("old_password");
            $password = input("password");
            $where['id'] = $this->real_login_user_data['user_id'];
            $check_password = model("UserModel")->checkPassword($where,get_seller_password($old_password));
            if(!$check_password){
                return ['code'=>1001,"msg"=>"旧密码验证不通过"];
            }
            $update_data['password'] = get_seller_password($password);
            $update_data['op_time'] = time();
            $update_data['op_desc'] = "客户端修改密码";
            $update_data['op_name'] = isset($seller_info['true_name'])?$seller_info['true_name']:'';
            $res = model("UserModel")->updateseller($where,$update_data);
            if($res){
                return ['code'=>200,"msg"=>"修改密码成功！"];
            }else{
                return ['code'=>1002,"msg"=>"修改密码失败！"];
            }
        }else{
            $this->assign("seller_info",$seller_info);
            $this->assign('parent_menu','account-manage');
            $this->assign('child_menu','account-manage-acct-manage');
            return $this->fetch();
        }
//dump($seller_info);exit;
    }

    /**
     * 用户身份审核
     */
    public function authorization(){
        $seller_info = User::getSellerInfoBySellerID($this->real_login_user_data['user_id']);
        $this->assign('seller_info', $seller_info);
        $this->assign('async_url', json_encode(
            [
                'async_submitSellerInfo'=>url('AccountManage/async_submitSellerInfo'),
            ]
        ));
        $this->assign('parent_menu','my-account');
        $this->assign('child_menu','auth-detail');

        $this->assign('parent_menu','account-manage');
        $this->assign('child_menu','account-manage-auth-detail');
        return $this->fetch();
    }

    /**
     * 子账号管理
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function childAcctManage(){
        if ($this->is_child_acct == 1){
            $this->error('当前账号是子账号');
        }
        $model = new UserModel();
        //当前登录用户的子订单数据
        $data = $model->getChildAcctDataPagenate($this->real_login_user_data['user_id']);
        $this->assign('data',$data);
        $this->assign('parent_menu','account-manage');
        $this->assign('child_menu','child-account-manage-acct-manage');
        return $this->fetch();
    }

    /**
     * 添加子账号
     * @return mixed
     */
    public function addChildAcct(){
        if ($this->is_child_acct == 1){
            $this->error('当前账号是子账号');
        }
        $this->assign('async_url',json_encode([
            'async_addChildAcct'=>url('AccountManage/async_addChildAcct'),
            'async_addChildAcctSuccessReturn'=>url('index/AccountManage/childAcctManage'),
        ]));
        $this->assign('parent_menu','account-manage');
        $this->assign('child_menu','child-account-manage-acct-manage');
        return $this->fetch();
    }

    /**
     * 添加子账号
     * @return mixed
     */
    public function async_addChildAcct(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '添加失败';
        $param = input();
        $validate = $this->validate($param,(new IndexParams())->async_addChildAcctRules());
        if(true === $validate){
            try{
                if ($param['password'] !== $param['confirm_password']){
                    $rtn['msg'] = '输入的密码不一致';
                }else{
                    if ($this->is_child_acct == 2){
                        $param['parent_id'] = $this->login_user_id;
                        $verify_res = User::verifyAddchildAcctData($param);
                        if ($verify_res['code'] == 0){
                            $param['management_model'] = $verify_res['management_model'];
                            $res = (new UserModel())->addChildAcct($param);
                            if (true === $res){
                                $rtn['code'] = 0;
                                $rtn['msg'] = 'success';
                            }else{
                                $rtn['msg'] = $res;
                            }
                        }else{
                            $rtn['msg'] = $verify_res['msg'];
                        }
                    }else{
                        $rtn['msg'] = '已是子账号，不能再添加';
                    }
                }
            }catch (\Exception $e){
                $rtn['msg'] = 'Exception:'.$e->getMessage();
            }
        }else{
            $rtn['msg'] = $validate;
        }
        return json($rtn);
    }

    /**
     * 异步处理seller提交审核信息
     * @return \think\response\Json
     */
    public function async_submitSellerInfo(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '提交失败';
        $param = input();
        //当前登录的用户ID
        $seller_id = isset($this->real_login_user_data['user_id'])?$this->real_login_user_data['user_id']:0;
        //当前登录的用户名
        $seller_name = isset($this->real_login_user_data['user_name'])?$this->real_login_user_data['user_name']:'';
        if (isset($param['flag']) && is_numeric($param['flag']) && !empty($param['flag']) && is_numeric($seller_id) && !empty($seller_id) && !empty($user_name)){
            //标识：1-个人，2-企业
            $flag = $param['flag'];
            //参数校验
            if ($flag == 1){
                $validate = $this->validate($param,(new IndexParams())->async_submitSellerInfoRulesOne());
                if (!verify_idcard($param['idcard_num'])){
                    $rtn['msg'] = '身份证格式错误';
                    return json($rtn);
                }
            }else{
                $validate = $this->validate($param,(new IndexParams())->async_submitSellerInfoRulesTwo());
                if (!is_phone_num($param['company_phone'])){
                    $rtn['msg'] = '公司联系电话错误';
                    return json($rtn);
                }
                if (!is_phone_num($param['company_contact_phone'])){
                    $rtn['msg'] = '公司联系人电话错误';
                    return json($rtn);
                }
            }
            if(true === $validate){
                $param['seller_id'] = $seller_id;
                $param['op_name'] = $seller_name;
                $param['op_desc'] = '提交审核信息';
                $param['op_time'] = time();
                $model = new UserModel();

                $seller_info = User::getSellerInfoBySellerID($seller_id);
                if (
                    $seller_info['status'] == 2
                    || $seller_info['status'] == 3
                ){
                    $rtn['msg'] = '非法用户';
                    return json($rtn);
                }
                /** 公司名称、社会信用代码、公司联系人电话判断重复 ，重复信息不予提交 **/
                if ($flag == 2){
                    //公司名称
                    $seller_info_cname = $model->getInfoByIdForSubmitSellerInfo($seller_id,$param['company_name']);
                    if (!empty($seller_info_cname)){
                        $rtn['msg'] = '公司名称不能重复';
                        return json($rtn);
                    }
                    //社会信用代码
                    $seller_info_cc = $model->getInfoByIdForSubmitSellerInfo($seller_id,'', $param['social_credit_code']);
                    if (!empty($seller_info_cc)){
                        $rtn['msg'] = '社会信用代码不能重复';
                        return json($rtn);
                    }
                    //公司联系人电话
                    $seller_info_cp = $model->getInfoByIdForSubmitSellerInfo($seller_id,'', '', $param['company_contact_phone']);
                    if (!empty($seller_info_cp)){
                        $rtn['msg'] = '公司联系人电话不能重复';
                        return json($rtn);
                    }
                }
                $res = $model->submitSellerInfo($param);
                if (true === $res){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '提交失败，请重试';
                }
            }else{
                $rtn['msg'] = $validate;
            }
        }else{
            $rtn['msg'] = '参数错误';
        }
        return json($rtn);
    }



}
