<?php
namespace app\mallextend\controller;
use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\params\mallextend\product\ProductParams;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductHistoryModel;
use think\Exception;


/**
 * 开发：钟宁
 * 功能：变更历史接口
 * 时间：2018-07-21
 */
class ProductHistory extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 获取产品列表
     * */
    public function getClassHistories(){
        try{
            $paramData = input();
            //参数校验
            $validate = $this->validate($paramData,(new ProductParams())->historyRule());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = (new ProductHistoryModel())->classHistoryLists($paramData);
            if(!empty($data['data'])){
                $class = array_unique(CommonLib::getColumn('EntityId',$data['data']));
                $timeArray = array_unique(CommonLib::getColumn('CreatedDateTime',$data['data']));
                $data['created_time'] = max($timeArray);
                unset($data['data']);
                $data['data'] = implode(',',$class);
            }else{
                return apiReturn(['code'=>200,'data'=>'']);
            }
            return apiReturn(['code'=>200,'data'=>$data]);

        }catch (Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }

    }


    /*
     * 获取产品列表
     * */
    public function getActivityHistories(){
        try{
            $class = '';
            $paramData = input();
            //参数校验
            $validate = $this->validate($paramData,(new ProductParams())->historyRule());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = (new ProductHistoryModel())->activityHistoryLists($paramData);
            if(!empty($data)){
                $class = array_unique(CommonLib::getColumn('EntityId',$data));
                $class = implode(',',$class);
            }
            return apiReturn(['code'=>200,'data'=>$class]);
        }catch (Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }

    }


    /*
     * 获取产品列表
     * 姚遥 --获取ishistory == 1的产品变更日志
     * */
    public function getProductHistories(){
        try{
            $class = '';
            $paramData = input();
            //参数校验
            $validate = $this->validate($paramData,(new ProductParams())->historyRule());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = (new ProductHistoryModel())->productHistoryLists($paramData);
            if(!empty($data['data'])){
                $class = array_unique(CommonLib::getColumn('EntityId',$data['data']));
                $timeArray = array_unique(CommonLib::getColumn('CreatedDateTime',$data['data']));
                $data['created_time'] = max($timeArray);
                unset($data['data']);
                $data['data'] = implode(',',$class);
            }else{
                $data['data'] = '';
            }
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 添加ERP类别历史记录
     */
    public function addErpClassHistory(){
        $time = time();
        $classData = (new ProductClassModel())->queryClass(['status'=>1,'type'=>1]);
        $history = new ProductHistoryModel();
        if(!empty($classData)){
            foreach($classData as $class){
                //新增类别映射
                $insert['EntityId'] = $class['id'];
                $insert['CreatedDateTime'] = $time;
                $insert['IsSync'] = false;
                $insert['Note'] = '新增类别';
                $history->addClassHistory($insert);
            }
        }

    }
}
