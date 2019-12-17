<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use think\Log;

/**
 * Class Coupon
 * @author tinghu.liu
 * @date 2018-05-10
 * @package app\index\controller
 * seller 订单
 */
class Coupon extends Common
{
    /**
     * coupon列表
     */
    public function index(){
        /** 查询公共参数参数 start **/
        $where['SellerId'] = $this->login_user_id;
        $where['CouponStatus'] = input('CouponStatus');
        $where['CouponStrategy'] = input('CouponStrategy');
        $where['CouponChannels'] = input('CouponChannels');
        $where['DiscountLevel'] = input('DiscountLevel');
        $where['Name'] = input('Name');

        $where['create_on_start'] = input('create_on_start', 0) !== 0?strtotime(input('create_on_start')):0;
        $where['create_on_end'] = input('create_on_end', 0) !== 0?strtotime(input('create_on_end')):0;

        //分页参数
        $where['page_size'] = input('page_size/d', 20);
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('Coupon/index', $p);
        $base_api = new BaseApi();
        $res = $base_api->getCouponList($where);
        $coupon_data = isset($res['data']['data'])&&!empty($res['data']['data'])?$res['data']['data']:[];
        $page_html = isset($res['data']['Page'])&&!empty($res['data']['Page'])?$res['data']['Page']:'';
        /** 获取Coupon使用数量 start **/
        if (!empty($coupon_data)){
            //组装coupon ID
            $counpon_id_arr = [];
            foreach ($coupon_data as $cinfo){
                if (isset($cinfo['CouponId'])){
                    $counpon_id_arr[] = $cinfo['CouponId'];
                }
            }
            //根据coupon ID获取已经使用的数量
            $count_data_api = $base_api->getCouponCount(['is_used'=>1,'coupon_ids'=>$counpon_id_arr]);
            $count_data = isset($count_data_api['data'])?$count_data_api['data']:[];
            //根据coupon ID和已使用数量进行对应
            if (!empty($count_data)){
                foreach ($coupon_data as &$val){
                    $is_used_num = 0;
                    foreach ($count_data as $key=>$tval){
                        if (isset($val['CouponId']) && $val['CouponId'] == $key){
                            $is_used_num = $tval;
                        }
                    }
                    $val['is_used_num'] = $is_used_num;
                }
            }
        }
        /** 获取Coupon使用数量 end **/
        $this->assign('coupon_data', $coupon_data);
        $this->assign('page_html', $page_html);
        $this->assign('parent_menu','marketing-promotion');
        $this->assign('child_menu','coupon-index');
        return $this->fetch();
    }

    /**
     * 新增coupon
     */
    public function add(){
        $SellerCouponAllowStore = $this->getSellerCouponAllowStore();
        $this->assign('SellerCouponAllowStore',$SellerCouponAllowStore);
        $this->assign('title','新增coupon');
        $this->assign('parent_menu','marketing-promotion');
        $this->assign('child_menu','coupon-index');
        return $this->fetch();
    }

    /**
     * 编辑coupon
     */
    public function editor(){
        $coupon_id = input('coupon_id');
        //tab类型：1-设置格则，2-多语言，3-Coupon Code，4-Coupon Code使用情况
        $tab_type = input('tab_type',1);
        if (empty($coupon_id) || !is_numeric($coupon_id)){
            $this->error('错误访问',url('Coupon/index'));
        }
        $base_api = new BaseApi();
        //coupon 信息
        $c_res = $base_api->getCouponByCouponId($coupon_id);
        $coupon_data = isset($c_res['data'])&&!empty($c_res['data'])?$c_res['data']:[];
        switch ($tab_type){
            case 1://设置格则
                break;
            case 2://多语言
                break;
            case 3://Coupon Code
                break;
            case 4: //Coupon Code使用情况
                $params = [];
                $params['coupon_id'] = $coupon_id;
                /** 分页条件 start **/
                $params['page_size'] = input('page_size/d', config('paginate.list_rows'));
                $params['page'] = input('page/d', 1);
                $input = input();
                $p = [];
                foreach ($input as $k=>$v){
                    if ($k != 'page' && $k != 'page_size'){
                        $p[$k] = $v;
                    }
                }
                $params['path'] = url('Coupon/editor', $p, config('default_return_type'), true);
                /** 分页条件 end **/
                $coupon_used_info_api = $base_api->getCouponUsedInfoByCouponId($params);
                $coupon_used_info = (isset($coupon_used_info_api['data'])&&!empty($coupon_used_info_api['data']))?$coupon_used_info_api['data']:[];
                $this->assign('coupon_used_info',$coupon_used_info);
                break;
        }
        $SellerCouponAllowStore = $this->getSellerCouponAllowStore();
        $this->assign('SellerCouponAllowStore',$SellerCouponAllowStore);
        $this->assign('tab_type',$tab_type);
        $this->assign('coupon_data',$coupon_data);
        $this->assign('title','编辑coupon');
        $this->assign('parent_menu','marketing-promotion');
        $this->assign('child_menu','coupon-index');
        return $this->fetch();
    }

    /**
     * 新增coupon信息
     * @return \think\response\Json
     */
    public function async_addCoupon(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增失败';
        $data = input();
        if (!empty($data)){
            $data['SellerId'] = $this->login_user_id;
            $data['CreateBy'] = $this->login_user_name;
            $data['CreateTime'] = time();
            $data['CouponTime']['StartTime'] = strtotime($data['CouponTime']['StartTime']);
            $data['CouponTime']['EndTime'] = strtotime($data['CouponTime']['EndTime']);
            $base_api = new BaseApi();
            $res = $base_api->addCoupon($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '新增失败，请重试。'.$res['msg'];
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 获取coupon code
     * @return \think\response\Json
     */
    public function async_getCouponCode(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取失败';
        $data = input();
        if (!empty($data)){
            $base_api = new BaseApi();
            $res = $base_api->getCouponCode($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['data'] = $res['data'];
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '获取失败，请重试';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 获取coupon详细数据
     * @return \think\response\Json
     */
    public function async_getCouponData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取失败';
        $coupon_id = input('CouponId');
        if (!empty($coupon_id)){
            $base_api = new BaseApi();
            $res = $base_api->getCouponByCouponId($coupon_id);
            if ($res['code'] == API_RETURN_SUCCESS){
                $data = $res['data'];
                //处理coupon开始结束时间，转为2018-10-27 15:40:23 格式
                $data['CouponTime']['StartTime'] = date('Y-m-d H:i:s', $data['CouponTime']['StartTime']);
                $data['CouponTime']['EndTime'] = date('Y-m-d H:i:s', $data['CouponTime']['EndTime']);
                $data['DesignatedStore'] = isset($data['DesignatedStore'])?$data['DesignatedStore']:"";
                if(!empty($data['DesignatedStore'])){
                    $data['DesignatedStore'] = implode(",",$data['DesignatedStore']);
                }
                foreach ($data['Description'] as $k=>$v){
                    if (isset($data['Description'][$k]['Brief']))
                    $data['Description'][$k]['Brief'] = htmlspecialchars_decode(htmlspecialchars_decode($v['Brief']));
                    if (isset($data['Description'][$k]['Details']))
                    $data['Description'][$k]['Details'] = htmlspecialchars_decode(htmlspecialchars_decode($v['Details']));
                }
                $rtn['code'] = 0;
                $rtn['data'] = $data;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '获取失败，请重试'.$res['msg'];
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 新增coupon code信息
     * @return \think\response\Json
     */
    public function async_addCouponCode(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增失败';
        $data = input();
        if (
            !empty($data)
            || !isset($data['CouponId']) || empty($data['CouponId'])
            || !isset($data['code_num']) || empty($data['code_num'])
            || !isset($data['rules']) || empty($data['rules'])
        ){
            $data['CreateBy'] = $this->login_user_name;
            $data['CreateTime'] = time();
            $base_api = new BaseApi();
            $res = $base_api->addCouponCode($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '新增失败，请重试。'.$res['msg'];
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 更新coupon信息
     * @return \think\response\Json
     */
    public function async_updateCouponData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增失败';
        $data = input();
        //flag 更新标识：1-更新全部，2-更新coupon描述（含多语言），3-更新coupon状态
        if (
            !empty($data)
            || !isset($data['CouponId']) || empty($data['CouponId'])
            || !isset($data['flag']) || empty($data['flag'])
        ){
            $base_api = new BaseApi();
            $flag = $data['flag'];
            if ($flag == 1){
                $data['CouponTime']['StartTime'] = strtotime($data['CouponTime']['StartTime']);
                $data['CouponTime']['EndTime'] = strtotime($data['CouponTime']['EndTime']);
            }
            if (isset($data['Description'])){
                foreach ($data['Description'] as $k=>$v){
                    if (isset($data['Description'][$k]['Brief'])){
                        $data['Description'][$k]['Brief'] = htmlspecialchars_decode(htmlspecialchars_decode(htmlspecialchars_decode($data['Description'][$k]['Brief'])));
                    }
                    if (isset($data['Description'][$k]['Details'])){
                        $data['Description'][$k]['Details'] = htmlspecialchars_decode(htmlspecialchars_decode(htmlspecialchars_decode($data['Description'][$k]['Details'])));
                    }
                }
            }
            $res = $base_api->updateCouponData($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '操作失败 '.$res['msg'];
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 删除coupon code
     * @return \think\response\Json
     */
    public function async_deleteCouponCode(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '删除失败';
        $data = input();
        if (
            !empty($data)
            || !isset($data['CouponId']) || empty($data['CouponId'])
            || !isset($data['CouponCode']) || empty($data['CouponCode'])
        ){
            $base_api = new BaseApi();
            $params['CouponId'] = $data['CouponId'];
            $params['CouponCode'] = $data['CouponCode'];
            $res = $base_api->deleteCouponCode($params);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '操作失败 '.$res['msg'];
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /*判断当前用户是否有权限指定coupon店铺*/
    private function getSellerCouponAllowStore(){
        $base_api = new BaseApi();
        $SellerCouponAllowStore = $base_api->getSysCofig(['ConfigName'=>'SellerCouponAllowStore']);
        $is_allow = false;
        if(!empty($SellerCouponAllowStore)){
            $store_ids = explode(",",$SellerCouponAllowStore);
            if(in_array($this->login_user_id,$store_ids)){
                $is_allow = true;
            }
        }else{
            $is_allow = false;
        }
        return $is_allow;
    }

}
