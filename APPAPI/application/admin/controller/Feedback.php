<?php
namespace app\admin\controller;

use think\cache\driver\Redis;
use think\Controller;

class Feedback extends Controller
{
    /*
     * 获取列表
     * */
    public function getList()
    {
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Feedback.getList");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['f.question_type'] = input("post.question_type");
        $where['f.customer_id'] = input("post.customer_id");
        $where['f.customer_name'] = input("post.customer_name");
        $where['f.order_number'] = input("post.order_number");
        $where['f.is_reply'] = input("post.is_reply");
        $where['f.addtime'] = input("post.addtime");
        $query = isset($paramData['query'])?$paramData['query']:'';
        $where = array_filter($where);
        $page_size = input('page_size',20);
        $page = input("page",1);
        $path = input("path");
        $res = model("Feedback")->getList($where,$page_size,$page,$path,$query);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 删除反馈
     * */
    public function delMessage(){
        $ids = input("ids");
        if(empty($ids)){
            return apiReturn(['code'=>1001]);
        }
        $where['message_id'] = ['in',$ids];
        $data['isdelete'] = 1;
        $res = model("Message")->delMessage($where,$data);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002,'data'=>$res]);
        }

    }
    

    /*
     * 添加反馈
     * */
    public function addFeedback(){
        $data['subject'] = input("post.subject");
        $data['is_send_email'] = input("post.is_send_email",0);
        $data['question_type'] = input("post.question_type");
        $data['customer_id'] = input("post.customer_id");
        $data['customer_name'] = input("post.customer_name");
        $data['order_number'] = input("post.order_number");
        $data['description'] = input("post.description");
        $data['enclosure'] = input("post.enclosure");
        $data['addtime'] = time();
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Feedback.addFeedback");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if(empty($data['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(empty($data['order_number'])){
            return apiReturn(['code'=>1001]);
        }
        $res = model("Feedback")->saveFeedback($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
    * 更改反馈阅读状态
    * */
    public function readFeedbackReply(){
        $ids = input("feedback_ids");
        $customer_id = input("customer_id");
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Feedback.readFeedbackReply");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if(!empty($customer_id)){
            $where['customer_id'] = $customer_id;
        }
        if(!empty($ids)){
            $where['fr.feedback_id'] = ['in',$ids];
        }
        $data['read_time'] = time();
        $res = model("Feedback")->saveFeedbackReply($data,$where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }


    /*
    * 添加反馈
    * */
    public function addFeedbackReply(){
        $data['feedback_id'] = input("feedback_id");
        $data['operator_id'] = input("operator_id");
        $data['operator_name'] = input("operator_name");
        $data['reply_content'] = input("reply_content");
        $data['addtime'] = time();
        if(empty($data['feedback_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(empty($data['operator_id'])){
            return apiReturn(['code'=>1001]);
        }
        $res = model("Feedback")->saveFeedbackReply($data);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }

    }

    /*
     * 获取用户反馈条数
     * */
    public function getFeedbackCountByCustomerId(){
        $customer_id = input("customer_id");
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Feedback.getFeedbackCountByCustomerId");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['customer_id'] = $customer_id;
        $res = model("Feedback")->getFeedbackCountByCustomerId($where);
        return $res;
    }

    /*
     * 获取用户反馈详情
     * */
    public function getFeedbackInfo(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Feedback.getFeedbackInfo");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['customer_id'] = $paramData['customer_id'];
        $where['feedback_id'] = $paramData['feedback_id'];
        $res = model("Feedback")->getFeedbackInfo($where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }
}
