<?php
namespace app\seller\controller;

use app\common\controller\Base;
use app\common\controller\BaseApi;
use app\common\helpers\CommonLib;
use app\seller\model\QuestionModel;
use think\Db;


/**
 * 买家商品提问数据
 * @author
 * @version  zhongning 2018/4/28
 */
class Question extends Base
{
    public $model;
    public function __construct()
    {
        parent::__construct();
        $this->model = new QuestionModel();
    }

    /*
     * 添加问题
     * */
    public function addQuestion(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Question.addQuestion");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if (!isset($paramData['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['customer_id'] = $paramData['customer_id'];
        if (isset($paramData['name'])){
            $data['name'] = $paramData['name'];
        }
        if (isset($paramData['seller_id'])){
            $data['seller_id'] = $paramData['seller_id'];
        }
        if (isset($paramData['seller_name'])){
            $data['seller_name'] = $paramData['seller_name'];
        }
        if (isset($paramData['product_id'])){
            $data['product_id'] = $paramData['product_id'];
        }
        if (isset($paramData['product_img'])){
            $data['product_img'] = $paramData['product_img'];
        }
        if (isset($paramData['product_name'])){
            $data['product_name'] = $paramData['product_name'];
        }
        if (isset($paramData['product_attr_ids'])){
            $data['product_attr_ids'] = $paramData['product_attr_ids'];
        }
        if (isset($paramData['product_attr_desc'])){
            $data['product_attr_desc'] = $paramData['product_attr_desc'];
        }
        if (isset($paramData['email'])){
            $data['email'] = $paramData['email'];
        }
        if (isset($paramData['description'])){
            $data['description'] = $paramData['description'];
        }
        if (isset($paramData['type'])){
            $data['type'] = $paramData['type'];
        }
        if (isset($paramData['is_answer'])){
            $data['is_answer'] = $paramData['is_answer'];
        }

        //获取用户数据改用接口的形式获取，因为CIC独立出来后，API不能直接访问CIC数据库 tinghu.liu 20190727
        $customer_data_res = (new BaseApi())->getCustomerByID(['ID'=>$paramData['customer_id']]);
        $customer_data = isset($customer_data_res['data'])?$customer_data_res['data']:[];
//        $customer_data = model("cic/Customer")->getCustomer($paramData['customer_id'],0);
        if(!$customer_data){
            return apiReturn(['code'=>1002,"msg"=>"customer is empty"]);
        }
        $data['addtime'] = time();
        try{
            $seller_info = model("UserInfo")->getSendMessageSeller(['id'=>$paramData['seller_id']]);
            if(!$seller_info){
                return apiReturn(['code'=>1002,"msg"=>"seller does not exist"]);
            }
            $res = $this->model->addQuestion($data);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$res]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002]);
        }
    }
    /*
     * 查询商品提问提问列表
     */
    public function getQuestionlists(){
    	$paramData = request()->post();
    	try{
            $page_size = input("page_size",20);
            $page = input("page",1);
            $path = input("path");
            $query = isset($paramData['query'])?$paramData['query']:'';
            unset($paramData['query']);
            foreach ($paramData as $key=>&$value){
                if(is_array($value)){
                    $value[0] = trim($value[0]);
                }
            }
    		$data = $this->model->questionsAndAnswersLists($paramData,$page_size,$page,$path,$query);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
    		return apiReturn(['code'=>200, 'data'=>$data]);
    	}catch (Exception $e){
    		return apiReturn(['code'=>1002, 'data'=>'error']);
    	}
    }

    /*
     * 后台查询商品提问提问列表
     */
    public function getAdminQuestionlist(){
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
            $data = $this->model->getAdminQuestionlist($paramData,$page_size,$page,$path,$query);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    public function getQuestionCount(){
        $paramData = request()->post();
        try{
            $data = $this->model->questionCount($paramData);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    public function getQuestionCountWhere(){
        $paramData = request()->post();
        try{
            foreach ($paramData as $key=>&$value){
                if(is_array($value)){
                    $value[0] = trim($value[0]);
                }
            }
            $data = $this->model->getQuestionCount($paramData);
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
    public function getQuestionWhere(){
        $paramData = request()->post();
        try{
            unset($paramData['access_token']);
            $data = $this->model->getQuestionWhere($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /*
     * 一键阅读全部回答
     * */
    public function answerFullRead(){
        $paramData = request()->post();
        try{
            $data = $this->model->answerFullRead($paramData);
            return apiReturn(['code'=>200]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /*
 * 修改商品提问提问列表
 */
    public function updateQuestion(){
        $paramData = request()->post();
        try{
            if(!isset($paramData['question_id']) || empty($paramData['question_id'])){
                return apiReturn(['code'=>1002, 'data'=>'question_id empty']);
            }
            $where['question_id'] = $paramData['question_id'];
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
            if(is_array($where['question_id'])){
                $where['question_id'][0] = trim($where['question_id'][0]) ;
            }
            $data = $this->model->updateQuestion($where,$update_data);
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
    public function addAnswer(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Question.addAnswer");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if (!isset($paramData['question_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['question_id'] = $paramData['question_id'];
        if (isset($paramData['product_id'])){
            $data['product_id'] = $paramData['product_id'];
        }
        if (isset($paramData['name'])){
            $data['name'] = $paramData['name'];
        }
        if (isset($paramData['user_id'])){
            $data['user_id'] = $paramData['user_id'];
        }
        if (isset($paramData['description'])){
            $data['description'] = $paramData['description'];
        }
        if (isset($paramData['product_id'])){
            $data['product_id'] = $paramData['product_id'];
        }
        $data['addtime'] = time();
        try{
            $res = $this->model->addAnswer($data);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$res]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002]);
        }
    }

    /*获取第一条问题信息*/
    public function getOneQuestion(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Question.getOneQuestion");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if (!isset($paramData['question_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['question_id'] = $paramData['question_id'];
        try{
            $res = $this->model->getOneQuestion($data);
            //过滤敏感信息
//            CommonLib::removeSensitive(['password'], $data);
            return apiReturn(['code'=>200, 'data'=>$res]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002]);
        }
    }

}
