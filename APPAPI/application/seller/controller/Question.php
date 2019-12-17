<?php
namespace app\seller\controller;

use app\common\controller\Base;
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
        $customer_data = model("cic/Customer")->getCustomer($paramData['customer_id'],0);
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
    		$data = $this->model->questionsAndAnswersLists($paramData,$page_size,$page,$path,$query);
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
}
