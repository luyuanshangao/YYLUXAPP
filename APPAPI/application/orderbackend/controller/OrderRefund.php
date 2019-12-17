<?php
namespace app\orderbackend\controller;

use app\common\params\orderbackend\OrderParams;
use app\demo\controller\Auth;
use app\orderbackend\model\OrderRefundModel;

/**
 * 订单类
 * Class OrderRefund
 * @author tinghu.liu 2018/5/16
 * @package app\orderbackend\controller
 */
class OrderRefund extends Auth
{
    /**
     * 获取订单退款列表数据（含分页）
     * @return mixed
     */
    public function getLists(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->orderRefunGetListsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $data = $model->getListDataForPage($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 获取RAM提交数据
     * @return mixed
     */
    public function getRamPostData(){
        try{
            $param = request()->post();
            //参数校验
            $validate = $this->validate($param,(new OrderParams())->getRamPostDataRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $model = new OrderRefundModel();
            $data = $model->getRamPostData($param['after_sale_id']);
            if (!empty($data)){
                return apiReturn(['code'=>200, 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 更新订单退款退货换货数据
     * @return mixed
     */
    public function updateApplyData(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->updateApplyDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $after_sale_id = $param['after_sale_id'];
        unset($param['after_sale_id']);
        $res = $model->updateApplyDataByWhere(['after_sale_id'=>$after_sale_id], $param);
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 增加订单售后申请操作记录数据
     * @return mixed
     */
    public function addApplyLogData(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->addApplyLogDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $res = $model->addApplyLogData($param);
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 获取纠纷列表（含分页）
     * @return mixed
     */
    public function getComplaintLists(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getComplaintListsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $data = $model->getComplaintDataForPage($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

}
