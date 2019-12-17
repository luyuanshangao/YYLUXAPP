<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\User;
use think\Controller;
use think\Request;
use think\Session;
use think\Url;

/**
 * Class Common
 * @author tinghu.liu
 * @date 2018-03-06
 * @package app\index\controller
 */

class Common extends Controller
{
    //登录sellerID【若是子账号则为父账号ID】
    protected $login_user_id;
    //登录seller名称【若是子账号则为父账号名称】
    protected $login_user_name;
    //是否是自营
    protected $is_self_support;
    //是否是子账号
    protected $is_child_acct;
    //登录用户数据【session】【若是子账号则为父账号信息】
    protected $login_user_data;
    //页面展示的登录用户信息【真正的登录用户信息】
    protected $real_login_user_data;
    protected $real_login_user_id;
    protected $real_login_user_name;
    protected $static_version_number = '2018121902';
    protected $warehouse;
    /**
     * 登录用户信息
     * @var array
     */
    protected $login_user_info = array();
    
    public function _initialize()
    {
        $request = Request::instance();
        $user_data = Session::get('user_data');
        $user_name = isset($user_data['user_name'])?$user_data['user_name']:'';
        $user_id = isset($user_data['user_id'])?$user_data['user_id']:0;
        //未登录
        if (empty($user_name) && empty($user_id)){
            $url = Url::build('/index/Login/index').'?referer='.$request->url();
            $this->redirect($url);
        }
        $this->login_user_id = $this->real_login_user_id = $user_id;
        $this->login_user_name = $this->real_login_user_name = $user_name;
        $this->is_self_support = isset($user_data['is_self_support'])?$user_data['is_self_support']:0;

        $this->login_user_data = $this->real_login_user_data = $user_data;

        $seller_info = User::getSellerInfoBySellerID($user_id);
        $verify_flag = $seller_info['status'];//用户状态:0-未认证审核,1-已认证审核,2-冻结,3-禁用

        //如果是子账号，需要拉取父级账号对应的seller ID ，name等信息
        $this->is_child_acct = $this->isChildAcct($seller_info);
        if ($this->is_child_acct == 1){
            $parent_info = User::getSellerInfoBySellerID($seller_info['parent_id']);
            $this->login_user_id = $parent_info['id'];
            $this->is_self_support = $parent_info['is_self_support'];
            $this->login_user_name = $parent_info['true_name'];
            //重置user_data
            $user_data = [];
            $user_data['user_id'] = $parent_info['id'];
            $user_data['user_name'] = $parent_info['true_name'];
            $user_data['is_self_support'] = $parent_info['is_self_support'];
            $this->login_user_data = $user_data;
        }

        /*echo $this->login_user_id;
        pr($user_data);*/

        $this->assign('is_child_acct',$this->is_child_acct);

        //设置菜单
        $this->assign('menu_info',Base::getMenuInfo());

        //图片地址配置
        $cnd_url = $this->cdnUrlConfig();
        $this->assign('product_images_url_config', $cnd_url['product_images_url']);

        /**
         * 产品上架页面控制
         * 0-不是产品上架页面
         * 1-是产品上架页面（则隐藏公共二级菜单、页面拉伸）
         */
        $this->assign('is_shelfpro', 0);
        /**
         * 页面显示控制
         * 0-显示公共二级菜单
         * 1-不显示公共二级菜单
         */
        $this->assign('show_view_flag',0);
        //用户登录信息
        $this->assign('login_user_data',$this->login_user_data);//主要用于产品管理方面
        $this->assign('real_login_user_data',$this->real_login_user_data);//主要用于页面展示

        $this->login_user_info = User::getSellerInfoBySellerID($this->login_user_id);
        //当前用户身份未审核
        if (
            $verify_flag != 1
            && strpos(strtolower($_SERVER['REQUEST_URI']),'authorization')===false
            && strpos(strtolower($_SERVER['REQUEST_URI']),'fileuploadforseller')===false
            && strpos(strtolower($_SERVER['REQUEST_URI']),'async_submitsellerinfo')===false
        ){
            $this->redirect('AccountManage/authorization');
        }
        $this->assignParams();
    }

    /**
     * 获取CDN预览地址配置
     * @return array
     */
    final public function cdnUrlConfig(){
        $rtn = [];
        $cdn_url_config = config('cdn_url_config');
        $base_url = $cdn_url_config['url'];
        $dir = $cdn_url_config['dir'];
        //产品图片预览地址
        $rtn['product_images_url'] = $base_url.$dir['product_imgs'];
        //订单消息图片预览地址
        $rtn['order_message_imgs_url'] = $base_url.$dir['order_message_imgs'];
        //售后订单图片预览地址
        $rtn['order_after_sale_imgs_url'] = $base_url.$dir['order_after_sale_imgs'];
        //seller图片预览地址
        $rtn['seller_imgs_url'] = $base_url.$dir['seller_imgs'];
        return $rtn;
    }

    /**
     * 参数注册
     */
    private function assignParams(){
        $this->assign([
            'cdn_base_url_config'=>config('cdn_url_config.url'),
            'mall_index_url'=>config('mall_index_url'),
            'platform_rules_url'=>config('platform_rules_url'),
            'platform_notice_more_url'=>config('platform_notice_more_url'),
            'novice_must_read_more_url'=>config('novice_must_read_more_url'),
            'seller_registration_protocol_url'=>config('seller_registration_protocol_url'),
            'static_version_number'=>$this->static_version_number,
        ]);
    }

    /**
     * 是否是子账号
     * @param $seller_info 用户信息
     * @return int
     */
    private function isChildAcct($seller_info){
        $is_child = 2; //是否是子账号：1-是，2-不是
        if (
            isset($seller_info['parent_id'])
            && !empty($seller_info['parent_id'])
        ){
            $is_child = 1;
        }
        return $is_child;
    }
}
