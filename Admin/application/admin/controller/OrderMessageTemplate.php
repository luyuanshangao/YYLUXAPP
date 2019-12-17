<?php
namespace app\admin\controller;

use app\admin\dxcommon\CommonLib;
use app\admin\model\OrderMessageTemplateModel;
use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use app\admin\dxcommon\Common;
/*
 * 后台管理-订单留言回复模板管理
 * Add by:kevin
 * AddTime:2018-04-24
 */
class OrderMessageTemplate extends Action
{
	/*
	 * 订单留言回复模板管理
	 */
	public function index()
	{
		$where = array();
		$data = request()->post();
        //获取后台配置的订单状态
        $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
        $order_status_data = array();
        foreach($orderStautsDict as $key=>$value){
            $order_status_data[$value[0]] = $value;
        }
        if(!empty($data['order_status'])){
            $where['order_status'] = $data['order_status'];
        }

        $where['type'] = !empty($data['type'])?$data['type']:1;

        if(!empty($data['status'])){
            $where['status'] = $data['status'];
        }

        $result = (new OrderMessageTemplateModel())->getOrderMessageTemplate($where);
		$this->assign(['list'=>$result,'order_status_data'=>$order_status_data]);
		return View('index');
	}

	/*
	 * 添加模板
	 */
	public function add_template(){
		if($data = request()->post()){//是否提交
			$remark = trim($data['remark']);
			if(empty($remark)){
				echo json_encode(array('code'=>101,'result'=>'备注说明不可为空'));
				exit;
			}
			if(empty($data['content_en'])){
				echo json_encode(array('code'=>102,'result'=>'英文回复不可为空'));
				exit;
			}
            $type = !empty($data['type'])?$data['type']:0;
            if($type == 1){
                if(empty($data['order_status'])){
                    echo json_encode(array('code'=>100,'result'=>'订单状态不能为空'));
                    exit;
                }
                $data['number_reply'] = 1;
            }elseif ($type == 2){
                $data['order_status'] = 0;
            }elseif($type < 1){
                echo json_encode(array('code'=>100,'result'=>'模板类型不能为空'));
                exit;
            }
            $res = (new OrderMessageTemplateModel())->saveOrderMessageTemplate($data);
			if($res){
				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
				exit;
			}else{
			    //TODO write log
				echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
				exit;
			}
		}else{
            //获取后台配置的订单状态
            $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
            $id = input("id");
            if(!empty($id)){
                $where['id'] = $id;
                $data = (new OrderMessageTemplateModel())->getOrderMessageTemplateInfo($where);
                $this->assign('data',$data);
            }
            $this->assign("order_status_data",$orderStautsDict);
			return View('add_template');
		}
	}

	/*
	 * 删除订单留言模板
	 * */
	public function delete_template(){
	    $id = input("id/d");
	    if(empty($id)){
            echo json_encode(array('code'=>101,'result'=>'订单留言模板ID不存在'));
            exit;
        }
        $where['id'] = $id;
        $res = (new OrderMessageTemplateModel())->deleteOrderMessageTemplate($where);
        if($res){
            echo json_encode(array('code'=>200,'result'=>'删除成功'));
            exit;
        }else{
            echo json_encode(array('code'=>100,'result'=>'删除失败'));
            exit;
        }
    }

}