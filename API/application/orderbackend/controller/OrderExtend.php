<?php
namespace app\orderbackend\controller;

use app\common\params\orderbackend\OrderExtendParams;
use app\demo\controller\Auth;
use app\orderbackend\model\OrderExtendModel;
use think\Exception;
use think\Log;

/**
 * 订单扩展类
 * Class Order
 * @author tinghu.liu 2018/7/9
 * @package app\orderbackend\controller
 */
class OrderExtend extends Auth
{
    protected $order_extend_model;
    protected $order_extend_params;
    public function __construct()
    {
        parent::__construct();
        $this->order_extend_model = new OrderExtendModel();
        $this->order_extend_params = new OrderExtendParams();
    }

    /**
     * 根据OrderNumber获取订单数据
     * {
            "order_number":"", //订单号（子单）
            "load_order_lines":"", //是否返回订单明细：0-不返回，1-返回
            "load_order_status_history":"", //是否返回订单状态变更历史：0-不返回，1-返回
     * }
     */
    public function getByOrderNumber(){
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params, $this->order_extend_params->getByOrderNumberRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->order_extend_model->getByOrderNumber($params);
            if (empty($data)){
                return apiReturn(['code'=>2001, 'msg'=>'订单数据为空']);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }catch (Exception $e){
            $msg = '系统异常：'.$e->getMessage().', file：'.$e->getFile().'['.$e->getLine().']';
            Log::record($msg);
            return apiReturn(['code'=>3001, 'msg'=>$msg]);
        }
    }

    /**
     * 根据用户ID获取用户订单
     */
    public function getByCustomerId(){
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params, $this->order_extend_params->getByCustomerIdRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->order_extend_model->getByCustomerId($params);
            if (empty($data)){
                return apiReturn(['code'=>2001, 'msg'=>'订单数据为空']);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }catch (Exception $e){
            $msg = '系统异常：'.$e->getMessage().', file：'.$e->getFile().'['.$e->getLine().']';
            Log::record($msg);
            return apiReturn(['code'=>3001, 'msg'=>$msg]);
        }

    }

    /**
     * 根据多个OrderNumber获取订单数据
     */
    public function getByOrderNumbers(){
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params, $this->order_extend_params->getByOrderNumbersRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->order_extend_model->getByOrderNumbers($params);
            if (empty($data)){
                return apiReturn(['code'=>2001, 'msg'=>'订单数据为空']);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }catch (Exception $e){
            $msg = '系统异常：'.$e->getMessage().', file：'.$e->getFile().'['.$e->getLine().']';
            Log::record($msg);
            return apiReturn(['code'=>3001, 'msg'=>$msg]);
        }

    }


}
