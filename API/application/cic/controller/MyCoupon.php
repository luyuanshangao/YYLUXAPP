<?php
namespace app\cic\controller;
use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\params\cic\MyCouponParams;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class MyCoupon extends Base
{
    /*
* 获取用优惠券列表
* @param int CustomerID
* @Return: array
* */
    public function getCouponList(){
        $paramData = request()->post();
        $where['customer_id'] = isset($paramData['customer_id'])?$paramData['customer_id']:0;
        if(empty($where['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(isset($paramData['is_used'])){
            $where['is_used'] = isset($paramData['is_used'])?$paramData['is_used']:'';
            if($paramData['is_used'] == 1){
                $where['start_time'] = ["elt",time()];
                $paramData['end_time'] = ["gt",time()];
            }
        }
        if(isset($paramData['start_time'])){
            $where['start_time'] = ["gt",$paramData['start_time']];
        }
        if(isset($paramData['end_time'])){
            foreach ($paramData['end_time'] as $key =>$value){
                $paramData['end_time'][$key] = trim($value);
            }
            $where['end_time'] = $paramData['end_time'];
        }

        if(isset($paramData['type'])){
            $where['type'] =['like',"%".$paramData['type']."%"];
        }
        $is_page = isset($paramData['is_page'])?$paramData['is_page']:1;
        $where['delete_time'] = 0;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $res = model("MyCoupon")->getCouponList($where,$page_size,$page,$path,$is_page);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }


    /*
* 获取用优惠券列表
* @param int CustomerID
* @Return: array
* */
    public function getCouponByCouponId(){
        $paramData = request()->post();
        $where['coupon_id'] = isset($paramData['coupon_id'])?$paramData['coupon_id']:0;
        if(empty($where['coupon_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(isset($paramData['is_used'])){
            $where['is_used'] = isset($paramData['is_used'])?$paramData['is_used']:'';
        }
        if(isset($paramData['start_time'])){
            $where['start_time'] = ["gt",$paramData['start_time']];
        }
        if(isset($paramData['end_time'])){
            $where['end_time'] = ["lt",$paramData['end_time']];
        }
        if(isset($paramData['type'])){
            $where['type'] = $paramData['type'];
        }
        $where['delete_time'] = 0;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $res = model("MyCoupon")->getCouponList($where,$page_size,$page,$path);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }


    /*
* 新增用户优惠券
* */
    public function addCoupon($data = ''){
        $data = !empty($data)?$data:request()->post();
        if(empty($data['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['coupon_id'] = input("coupon_id");
        if(empty($data['coupon_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['type'] = input("type",1);
        $data['order_id'] = input("order_id");
        $data['coupon_sn'] = input("coupon_sn");
        $data = array_filter($data);
        $data['is_used'] = input("is_used",1);
        $data['start_time'] = input("start_time");
        $data['end_time'] = input("end_time");
        $data['add_time'] = time();
        $res = model("MyCoupon")->addCoupon($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 删除用户优惠券
     * */
    public function delCoupon(){
        $paramData = request()->post();
        if(empty($paramData['id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['coupon_id'] = input("coupon_id");
        if(empty($data['coupon_id'])){
            return apiReturn(['code'=>1001]);
        }
        $res = model("MyCoupon")->delCoupon($paramData['id']);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 使用优惠券
     * */
    public function usedCoupon(){
        $paramData = request()->post();
        if(empty($paramData['id'])){
            return apiReturn(['code'=>1001]);
        }
        if(empty($data['order_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['id'] = input("id");

        $data['order_id'] = input("order_id");
        $data['order_number'] = input("order_number");
        $res = model("MyCoupon")->usedCoupon($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 使用优惠券
     * */
    public function usedCouponByCode(){
        $paramData = request()->post();
        if(isset($paramData['id'])){
            $data['id'] = $paramData['id'];
        }
        if(!isset($paramData['coupon_code'])){
            return apiReturn(['code'=>1001]);
        }
        $data['coupon_sn'] = $paramData['coupon_code'];
        if(!isset($paramData['coupon_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['coupon_id'] = $paramData['coupon_id'];
        if(isset($paramData['type'])){
            $data['type'] = $paramData['type'];
        }
        if(isset($paramData['customer_id'])){
            $data['customer_id'] = $paramData['customer_id'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        if(isset($paramData['order_id'])){
            $data['order_id'] = $paramData['order_id'];
        }
        if(isset($paramData['order_number'])){
            $data['order_number'] = $paramData['order_number'];
        }
        /*if(isset($paramData['end_time'])){
            $data['end_time'] = $paramData['end_time'];
        }*/

        $res = model("MyCoupon")->usedCouponByCode($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 获取优惠券使用个数
     * */
    public function getCouponCount(){
        $paramData = request()->post();
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }
        if(isset($paramData['is_used'])){
            $where['is_used'] = $paramData['is_used'];
        }
        if(isset($paramData['coupon_ids'])){
            $where['coupon_id'] = ['in',$paramData['coupon_ids']];
        }
        if(isset($paramData['coupon_sns'])){
            $where['coupon_sn'] = ['in',$paramData['coupon_sns']];
        }
        $res = model("MyCoupon")->getCouponCount($where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }


    /*
     * 获取优惠券使用个数
     * */
    public function getUserCouponCode(){
        try{
            $paramData = request()->post();
            $data = model("MyCoupon")->getUserCouponCode($paramData);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1006,'msg'=>$e->getMessage()]);
        }
    }



    /**
     * mall商城添加用户可用coupon
     */
    public function mallAddCoupon(){
        try{
            $params = request()->post();
            //接口领取
            $data['coupon_id'] = $params['coupon_id'];
            $data['customer_id'] = $params['customer_id'];
            $data['type'] = implode(',',$params['CouponChannels']);
            $data['coupon_sn'] = $params['coupon_code'];
            $data['is_used'] = 1;
            $data['start_time'] = time();
            $end_time = strtotime('+1 month');
            if($end_time > $params['EndTime'] ){
                $end_time = $params['EndTime'];
            }
            $data['end_time'] = $end_time;
            $data['add_time'] = time();

            $res = model("MyCoupon")->addCoupon($data);
            if(empty($res)){
                return apiReturn(['code'=>20000001,'msg'=>'领取失败']);
            }
            return apiReturn(['code'=>200,'msg'=>'领取成功']);
        }catch (Exception $e){
            return apiReturn(['code'=>20000001,'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 根据条件获取优惠券使用个数
     * @return mixed
     */
    public function getCouponCountByWhere(){
        $paramData = request()->post();
        $res = model("MyCoupon")->getCouponCountByWhere($paramData);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /**
     * 更新my coupon数据
     * @return mixed
     * cic/MyCoupon/updateCouponForOrder
     */
    public function updateCouponForOrder(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new MyCouponParams())->updateCouponForOrderRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $time = time();
            $coupon_model = new \app\cic\model\MyCoupon();
            $where['coupon_id']         = $param['coupon_id'];
            $where['coupon_sn']         = $param['coupon_code'];
            $where['order_number']      = $param['order_number'];
            $up_data['order_number']    = $param['new_order_number'];
            $up_data['edit_time']       = $time;
            if ($coupon_model->updateCouponDataByWhere($up_data, $where)){
                return apiReturn(['code'=>200, 'msg'=>'Success']);
            }else{
                return apiReturn(['code'=>200, 'msg'=>'Failed']);
            }
        }catch (Exception $e){
            $msg = 'updateCouponForOrderException:'.$e->getMessage().','.$e->getFile().'['.$e->getLine().']';
            Log::record($msg, Log::ERROR);
            return apiReturn(['code'=>2000, 'msg'=>'更新失败，'.$e->getMessage()]);
        }
    }


}
