<?php
namespace app\admin\controller;

use app\admin\dxcommon\BaseApi;
use app\admin\model\Activity as activity_model;
use app\admin\statics\BaseFunc;
use app\common\params\admin\ActivityParams;
use think\Controller;
use think\Log;

/**
 * 活动接口
 * Class Activity
 * @author tinghu.liu 2018/4/19
 * @package app\admin\controller
 */
class Activity extends Controller
{
    /**
     * 获取活动列表
     * @return mixed
     */
    public function getActivityData()
    {
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new ActivityParams())->getActivityRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $activity_model = new activity_model();
        ////获取数据类型：1-平台活动报名（全部活动数据），2-待确认（seller报名信息），3-参与中（seller报名信息），4-已结束（seller报名信息）
        $tab_type = $param['tab_type'];
        if ($tab_type == 1){
            $data = $activity_model->getActivityDataTabOne($param);
        }else{
            $data = $activity_model->getActivityData($param);
        }
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 根据活动ID获取单条活动详情
     * @return mixed
     */
    public function getActivityByActivityID(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new ActivityParams())->getActivityByActivityIDRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $activity_model = new activity_model();
        $data = $activity_model->getActivityDataByWhere(['id'=>$param['activity_id']])[0];
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 获取活动报名信息
     * @return mixed
     */
    public function getEnrollActivityData(){
        $param = request()->post('', []);
        $activity_model = new activity_model();
        $data = $activity_model->getActivityEnrollData($param);
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 报名参加活动
     * @return mixed
     */
    public function enrollActivity(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new ActivityParams())->enrollActivityRules());
        if(true !== $validate){
        	return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        /*$isOK =false;
        $msg ='';
        $play_number =$param['play_number'];
        #----------------特殊验证  Start---------------------------
        if(isset($param['activity_type']) && $param['activity_type']==5){
        	if(!empty($play_number)){
        		$play_number_arr=explode(',',$play_number,2);
        		if(count($play_number_arr) ==2){
        			$playNumber = $base_api->getSysCofig(['ConfigName' => 'ActivityPlayNumber']);
	            	if(!empty($playNumber) && isset($playNumber['data']['key'])){
	            		$playNumberArr=explode(',',$playNumber['data']['key']);
        				foreach ($playNumberArr as $value) {
        					$arr = explode('-',$value,2);
        					if(!empty($arr) && count($play_number_arr) ==2){
        						if($play_number_arr[0] ==$v[0]){
        							$isOK = true;
        						}
        					}
        				}
        			}else{
        				$msg ='获取系统配置数据为空';
        			}
        		}else{
        			$msg ='活动场次参数错误';
        		}
        	}else{
        		$msg ='活动场次为空';
        	}
        }
        if(true !== $isOK){
        	return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
        #----------------特殊验证  End---------------------------*/
        
        $activity_model = new activity_model();
        $enroll = $activity_model->getActivityEnrollData(['seller_id'=>$param['seller_id'], 'activity_id'=>$param['activity_id']]);
        //报名状态：1-已报名，2-退出活动
        $enroll_status = 0;
        if (!empty($enroll)){
            $enroll_status = $enroll[0]['status'];
        }
        //已经报过名
        if (!empty($enroll) && $enroll_status == 1){
            return apiReturn(['code'=>1002, 'msg'=>'您已经报过名']);
        }
        //退出之后再次报名
        if ($enroll_status == 2){
            $res = $activity_model->updateActivityEnrollData(
                    ['status'=>1],
                    ['seller_id'=>$param['seller_id'], 'activity_id'=>$param['activity_id']]
                );
            if ($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }else{
            //第一次报名
            if ($activity_model->insertActivityEnrollData($param)){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }
    }

    /**
     * 商家退出活动
     * @return mixed
     */
    public function quitActivity(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new ActivityParams())->quitActivityRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $activity_model = new activity_model();
        //只有在报名中的活动才能退出，正在活动中、活动结束的不能退出
        $activity_info = $activity_model->getActivityDataByWhere(['id'=>$param['activity_id']])[0];
        Log::record('$activity_info'.print_r($activity_info, true));
        //在报名中
        if (
            $activity_info['registration_start_time'] < $param['edit_time']
            &&$activity_info['registration_end_time'] > $param['edit_time']
        ){
            //退出活动后台处理
            if ($activity_model->outActicityForSeller($param)){
                //释放已经绑定的产品 start
                $spu_data = $activity_model->getActivitySPUDataByWhere(['seller_id'=>$param['seller_id'], 'activity_id'=>$param['activity_id']]);
                $product_id_arr = [];
                foreach ($spu_data as $spu){
                    $product_id_arr[] = $spu['product_id'];
                }
                $product_id_arr = array_unique($product_id_arr);
                $base_api = new BaseApi();
                $res = $base_api->updateActivityFortask(['product_id_arr'=>$product_id_arr]);
                Log::record('quitActivity:res:'.print_r($res, true));
                //释放已经绑定的产品 end
                if ($res['code'] != 200){
                    return apiReturn(['code'=>1005, 'msg'=>'产品信息同步失败']);
                }else{
                    return apiReturn(['code'=>200]);
                }
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'退出活动失败']);
            }
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'不符合退出活动条件']);
        }
    }

    /**
     * 获取活动SKU数据
     * @return mixed
     */
    public function getActivitySKUData(){
        $param = request()->post();
        $activity_model = new activity_model();
        $data = $activity_model->getActivitySKUData($param);
        if (!empty($data)){
            return apiReturn(['code'=>200 , 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'获取活动SKU数据失败']);
        }
    }

    /**
     * 获取活动SKU数据【列表页分页】
     * @return mixed
     */
    public function getActivitySKUDataForList(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new ActivityParams())->getActivitySKUDataForListRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $activity_model = new activity_model();
        $data = $activity_model->getActivitySKUDataForList($param);
        if (!empty($data)){
            return apiReturn(['code'=>200, 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'获取活动SKU数据失败']);
        }
    }

    /**
     * 增加活动SKU数据
     * @return mixed
     */
    public function addActivitySKU(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new ActivityParams())->addActivitySKURules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        //如果计算后的最终活动价格保留两位小数为0的话不让参加活动
        if ($param['activity_price'] == 0 || empty($param['activity_price'])) {
            return apiReturn(['code'=>1002, 'msg'=>'活动价格不能为0，请重新设置']);
        }
        $activity_id = $param['activity_id'];
        $product_id = $param['product_id'];
        $activity_model = new activity_model();
        /** 如果是按分类的活动，则需要检查该sku是否满足指定分类 start **/
        //获取活动类型
        $activity_info = $activity_model->getActivityDataByWhere(['id'=>$activity_id])[0];
        //活动按分类进行
        if ($activity_info['range'] == 2){
            //如果是按分类的活动，获取产品数据对应的分类
            foreach ($activity_info['className'] as $a_class){
                $classid_arr[] = $a_class['classid'];
            }
            //获取产品对应分类
            $base_api = new BaseApi();
            $product_info = $base_api->getProductInfoByID($product_id);
            $category_arr = $product_info['data']['CategoryArr'][0];
            foreach ($category_arr as $category_info){
                if (!empty($category_info)){
                    $category_arr_new[] = $category_info;
                }
            }
            $pro_category_str = implode('>',$category_arr_new);
            //判断产品分类是否在分类活动指定的分类中，如果不在则提示该产品不符合活动规则
            if (!in_array($pro_category_str, $classid_arr)){
                return apiReturn(['code'=>1002, 'msg'=>'该产品不符合活动规则（活动分类不包含）']);
            }
        }
        /** 如果是按分类的活动，则需要检查该sku是否满足指定分类 end **/
        $a_info = $activity_model->getActivitySKUData([
            'activity_id'=>$activity_id,
            'seller_id'=>$param['seller_id'],
            'product_id'=>$product_id,
            'sku'=>$param['sku'],
            'code'=>$param['code']
        ]);
        if (empty($a_info)){
            if ($activity_model->addActivitySKUData($param)){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'增加活动SKU数据失败']);
            }
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'该sku已存在']);
        }
    }

    /**
     * 增加活动SKU数据
     * @return mixed
     */
    public function addActivitySKU_new(){
        $param = request()->post();
        $data = $param['data'];
        foreach ($data as $info){
            //参数校验
            $validate = $this->validate($info,(new ActivityParams())->addActivitySKURules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //如果计算后的最终活动价格保留两位小数为0的话不让参加活动
            if ($info['activity_price'] == 0 || empty($info['activity_price'])) {
                return apiReturn(['code'=>1002, 'msg'=>'活动价格不能为0，请重新设置']);
            }
            //库存为0的不能参加活动
            if (
                $info['activity_inventory'] == 0 || empty($info['activity_inventory'])
            ){
                return apiReturn(['code'=>1002, 'msg'=>'库存不能为0，请重新设置']);
            }
        }
        $activity_id = $data[0]['activity_id'];
        $product_id = $data[0]['product_id'];
        $activity_model = new activity_model();
        /** 如果是按分类的活动，则需要检查该sku是否满足指定分类 start **/
        //获取活动类型
        $activity_info = $activity_model->getActivityDataByWhere(['id'=>$activity_id])[0];
        //判断seller是否已经报名此活动
        $seller_id = $data[0]['seller_id'];
        $enroll_data = $activity_model->getActivityEnrollData(['seller_id'=>$seller_id, 'activity_id'=>$activity_id, 'status'=>1]);
        if (empty($enroll_data)){
            return apiReturn(['code'=>1002, 'msg'=>'请先报名']);
        }
        //判断是否处于活动报名时间
        $add_time = $time = $data[0]['add_time'];
        if (
            $add_time < $activity_info['registration_start_time']
            || $add_time >$activity_info['registration_end_time']
        ){
            return apiReturn(['code'=>1002, 'msg'=>'已结束报名']);
        }
        //活动按分类进行
        if ($activity_info['range'] == 2){
            //如果是按分类的活动，获取产品数据对应的分类
            foreach ($activity_info['className'] as $a_class){
                $classid_arr[] = $a_class['classid'];
            }
            //获取产品对应分类
            $base_api = new BaseApi();
            $product_info = $base_api->getProductInfoByID($product_id);
            $category_arr = $product_info['data']['CategoryArr'][0];
            foreach ($category_arr as $category_info){
                if (!empty($category_info)){
                    $category_arr_new[] = $category_info;
                }
            }
            $pro_category_str = implode('>',$category_arr_new);
            //判断产品分类是否在分类活动指定的分类中，如果不在则提示该产品不符合活动规则
            if (!in_array($pro_category_str, $classid_arr)){
                return apiReturn(['code'=>1002, 'msg'=>'该产品不符合活动规则（活动分类不包含）']);
            }
        }
        /** 如果是按分类的活动，则需要检查该sku是否满足指定分类 end **/
        $a_info = $activity_model->getActivitySPUDataByWhere([
            'product_id'=>$product_id,
            'activity_id'=>$activity_id,
        ]);
        if (empty($a_info)){
            if ($activity_model->addActivitySPUData($data)){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'增加活动SKU数据失败']);
            }
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'该产品已存在']);
        }
    }

    /**
     * 修改活动SKU数据
     */
    public function updateActivitySKU(){
        $param = request()->post();
        //参数校验
        foreach ($param as $info){
            $validate = $this->validate($info,(new ActivityParams())->updateActivitySKURules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        $activity_model = new activity_model();
        //如果不是“审核中”状态则不能修改
        $spu_info = $activity_model->getActivitySPUDataByWhere(['id'=>$param[0]['spu_id']])[0];
        if ($spu_info['status'] != 1){
            return apiReturn(['code'=>1002, 'msg'=>'审核中的才能修改']);
        }
        if ($activity_model->updateActivityAllData($param)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'修改活动SKU失败']);
        }
    }

    /**
     * 重新提交审核活动产品
     * @return mixed
     */
    public function resubmitActivitySKU(){
        $param = request()->post();
        //参数校验
        foreach ($param as $info){
            $validate = $this->validate($info,(new ActivityParams())->updateActivitySKURules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        $activity_model = new activity_model();
        if ($activity_model->resubmitActivitySKU($param)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'修改活动SKU失败']);
        }

    }

}
