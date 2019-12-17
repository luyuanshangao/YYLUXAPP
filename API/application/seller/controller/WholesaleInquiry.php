<?php
namespace app\seller\controller;

use app\common\controller\Base;
use app\seller\model\WholesaleInquiry as WholesaleInquiryModel;
use app\common\params\seller\WholesaleInquiryParams;
use think\Controller;
/**
 * 批发询价接口
 * Class WholesaleInquiry
 * @author tinghu.liu 2018/06/11
 * @package app\seller\controller
 */
class WholesaleInquiry extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 新增批量询价数据
     * @return mixed
     */
    public function addData()
    {
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new WholesaleInquiryParams())->addDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new WholesaleInquiryModel();
            $param['addtime'] = time();
            if ($model->addData($param)){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1006, 'msg'=>'添加数据失败']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1007, 'msg'=>'程序异常'.$e->getMessage()]);
        }
    }

    /*
     * 后台查询商品提问提问列表
     */
    public function getAdminWholesaleInquirylist(){
        $paramData = request()->post();
        try{
            $page_size = input("page_size",20);
            $page = input("page",1);
            $path = input("path");
            $query = isset($paramData['query'])?$paramData['query']:'';
            unset($paramData['page_size']);
            unset($paramData['page']);
            unset($paramData['path']);
            unset($paramData['query']);
            unset($paramData['access_token']);
            foreach ($paramData as $key=>&$value){
                if(is_array($value)){
                    $value[0] = trim($value[0]);
                }
            }
            $model = new WholesaleInquiryModel();
            $data = $model->getAdminWholesaleInquirylist($paramData,$page_size,$page,$path,$query);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }


    /*
 * 修改商品提问提问列表
 */
    public function updateWholesaleInquiry(){
        $paramData = request()->post();
        try{
            if(!isset($paramData['id']) || empty($paramData['id'])){
                return apiReturn(['code'=>1002, 'data'=>'id empty']);
            }
            $where['id'] = $paramData['id'];
            if(isset($paramData['is_answer'])){
                $update_data['is_answer'] = $paramData['is_answer'];
            }

            if(isset($paramData['distribution_admin_id']) && !empty($paramData['distribution_admin_id'])){
                $update_data['distribution_admin_id'] = $paramData['distribution_admin_id'];
            }

            if(isset($paramData['distribution_admin']) && !empty($paramData['distribution_admin'])){
                $update_data['distribution_admin'] = $paramData['distribution_admin'];
            }
            if(isset($paramData['solve_time']) && !empty($paramData['solve_time'])){
                $update_data['solve_time'] = $paramData['solve_time'];
            }
            if(isset($paramData['is_crash']) && !empty($paramData['is_crash'])){
                $update_data['is_crash'] = $paramData['is_crash'];
            }
            if(isset($paramData['operator_admin_id']) && !empty($paramData['operator_admin_id'])){
                $update_data['operator_admin_id'] = $paramData['operator_admin_id'];
            }
            if(isset($paramData['operator_admin']) && !empty($paramData['operator_admin'])){
                $update_data['operator_admin'] = $paramData['operator_admin'];
            }
            if(isset($paramData['distribution_time']) && !empty($paramData['distribution_time'])){
                $update_data['distribution_time'] = $paramData['distribution_time'];
            }
            if(isset($paramData['aging']) && !empty($paramData['aging'])){
                $update_data['aging'] = $paramData['aging'];
            }
            if(isset($paramData['reply_time']) && !empty($paramData['reply_time'])){
                $update_data['reply_time'] = $paramData['reply_time'];
            }
            if(is_array($where['id'])){
                $where['id'][0] = trim($where['id'][0]) ;
            }
            $model = new WholesaleInquiryModel();
            $data = $model->updateWholesaleInquiry($where,$update_data);
            if($data){
                return apiReturn(['code'=>200, 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /*
    * 添加问题
    * */
    public function addWholesaleInquiryAnswer(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"WholesaleInquiry.addAnswer");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if (!isset($paramData['inquiry_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['inquiry_id'] = $paramData['inquiry_id'];
        if (isset($paramData['name'])){
            $data['name'] = $paramData['name'];
        }
        if (isset($paramData['user_id'])){
            $data['user_id'] = $paramData['user_id'];
        }
        if (isset($paramData['description'])){
            $data['description'] = $paramData['description'];
        }
        $data['addtime'] = time();
        $model = new WholesaleInquiryModel();
        try{
            $res = $model->addWholesaleInquiryAnswer($data);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$res]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002]);
        }
    }


    /*
     * 获取用户问题
     * */
    public function getWholesaleInquiryWhere(){
        $paramData = request()->post();
        try{
            unset($paramData['access_token']);
            $model = new WholesaleInquiryModel();
            $data = $model->getWholesaleInquiryWhere($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /*获取第一条问题信息*/
    public function getOneWholesaleInquiry(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"WholesaleInquiry.getOneWholesaleInquiry");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if (!isset($paramData['id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['id'] = $paramData['id'];
        try{
            $model = new WholesaleInquiryModel();
            $res = $model->getOneWholesaleInquiry($data);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$res]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002]);
        }
    }

    public function getWholesaleCountWhere(){
        $paramData = request()->post();
        try{
            $model = new WholesaleInquiryModel();
            $data = $model->getWholesaleCountWhere($paramData);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /*
 * 查询商品提问提问列表
 */
    public function getWholesalelists(){
        $paramData = request()->post();
        try{
            $page_size = input("page_size",20);
            $page = input("page",1);
            $path = input("path");
            $query = isset($paramData['query'])?$paramData['query']:'';
            $model = new WholesaleInquiryModel();
            $data = $model->wholesaleAndAnswersLists($paramData,$page_size,$page,$path,$query);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /*
     * 获取用户问题
     * */
    public function getWholesaleWhere(){
        $paramData = request()->post();
        try{
            unset($paramData['access_token']);
            $model = new WholesaleInquiryModel();
            $data = $model->getWholesaleWhere($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }
}
