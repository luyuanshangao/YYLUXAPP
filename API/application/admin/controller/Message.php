<?php
namespace app\admin\controller;

use app\common\params\admin\MessageParams;
use think\cache\driver\Redis;
use think\Controller;
use app\admin\model\Message as MessageModel;
use think\Log;

class Message extends Controller
{
    /*
     * 获取列表
     * */
    public function getList()
    {
        $input = input();
        $title = isset($input['title'])?$input['title']:'';
        $where['type'] = input("type");
        $where['send_user'] = input("send_user");
        $where['recive_user_id'] = input("recive_user_id");
        $where['recivetype'] = input("recivetype");
        $where['read_status'] = input("read_status");
        $where['status'] = input("status");
        $where['mark'] = input("mark");
        $where['title'] = trimall($title);

        $page_size = input('page_size',20);
        $page = input("page",1);
        $path = input("path");
        $query = isset($input['query'])?$input['query']:'';
        $where = array_filter($where);
        $where['isdelete'] = 0;
        $res = model("Message")->getList($where,$page_size,$page,$path,$query);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 获取信息内容
     * */
    public function getInfoById(){
        $post_param = request()->post();
        if(!isset($post_param['id'])){
            return apiReturn(['code'=>1001]);
        }
        $where['mr.id'] = $post_param['id'];
        $where['recive_user_id'] = input("recive_user_id");
        $where['recivetype'] = input("recivetype");
        $where = array_filter($where);
        $res = model("Message")->getInfoById($where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 获取用户信息数量
     * */
    public function getMessageCount(){
        $input = input();
        $title = isset($input['title'])?$input['title']:'';
        $where['type'] = input("type");
        $where['send_user'] = input("send_user");
        $where['recive_user_id'] = input("recive_user_id");
        $where['recivetype'] = input("recivetype");
        $where['read_status'] = input("read_status");
        $where['status'] = input("status");
        $where['mark'] = input("mark");
        $where['title'] = trimall($title);
        $where = array_filter($where);
        $where['isdelete'] = 0;
        $res = model("Message")->getCount($where);
        return $res;
    }

    /*
     * 一键阅读用户信息
     * */
    public function fullMessage(){
        $where['type'] = input("type");
        $where['recive_user_id'] = input("recive_user_id");
        if(empty($where['recive_user_id'])){
            return apiReturn(['code'=>1001]);
        }
        $where = array_filter($where);
        $res = model("Message")->fullMessage($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }


    /*
     * 获取列表【seller用】
     * */
    public function getListForSeller()
    {
        $input = input();
        $title = isset($input['title'])?$input['title']:'';
        $where['type'] = input("type");
        $where['send_user'] = input("send_user");
        $where['recive_user_id'] = input("recive_user_id");
        $where['recive_type'] = input("recive_type");
        $where['read_status'] = input("read_status");
        $where['status'] = input("status");
        $where['mark'] = input("mark");
        $where['title'] = trimall($title);
        if (isset($input['month_time']) && !empty($input['month_time'])){
            $where['addtime'] = ['>=', (int)strtotime('-'.$input['month_time'].' month')];
        }
        $page_size = input('page_size',20);
        $page = input("page",1);
        $path = input("path");

        $where = array_filter($where);
        $where['isdelete'] = 0;
        $res = model("Message")->getListForSeller($where,$page_size,$page,$path);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /**
     * 更新接收消息
     * @return mixed
     * Array
        (
            [message_recive_id] => Array
            (
                [0] => 37
                [1] => 16
                [2] => 15
                [3] => 14
                [4] => 13
            )
            [action] => 3 //1-设置为已读；2-设置为未读；3-设置标记；4-取消标记
            [flag] => 1 //1-设置读状态、标记；2-删除
        )
     */
    public function updateMessageReciveData(){
        $params = request()->post();
        if (
            !isset($params['flag']) || empty($params['flag'])
            || !isset($params['message_recive_id']) || empty($params['message_recive_id'])
        ){
            return apiReturn(['code'=>1002,'msg'=>'参数错误']);
        }
        //1-设置读状态、标记；2-删除
        $flag = $params['flag'];
        $message_recive_id = $params['message_recive_id'];
        $where['id'] = ['in', $message_recive_id];
        if ($flag == 1){
            //1-设置为已读；2-设置为未读；3-设置标记；4-取消标记
            $action = $params['action'];
            //设置读状态、标记
            switch ($action){
                case 1://设置为已读
                    $up_data['read_status'] = 1;
                    $up_data['read_time'] = time();
                    break;
                case 2://设置为未读
                    $up_data['read_status'] = 2;
                    break;
                case 3://设置标记
                    $up_data['mark'] = 1;
                    break;
                case 4://取消标记
                    $up_data['mark'] = 2;
                    break;
            }
            $res = model("Message")->updateMessageReciveData($up_data, $where);
        }elseif ($flag == 2){
            //删除
            $up_data['isdelete'] = 1;
            $res = model("Message")->updateMessageReciveData($up_data, $where);
        }
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1003,'msg'=>'操作失败，请重试']);
        }
    }

    /*
     * 删除消息
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

    public function setupMessage(){
        $ids = input("ids");
        if(empty($ids)){
            return apiReturn(['code'=>1001]);
        }
        $where['message_id'] = ['in',$ids];
        $data['mark'] = 1;
        $res = model("Message")->delMessage($where,$data);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002,'data'=>$res]);
        }
    }

    /*
     * 添加消息
     * */
    public function addMessage(){
        $data['type'] = input("type");
        $data['send_user'] = input("send_user");
        $data['recive_user_id'] = input("recive_user_id");
        $data['recive_type'] = input("recive_type");
        $data['content'] = input("content/s");
        $data['read_status'] = input("read_status");
        $data['status'] = input("status");
        $data['addtime'] = time();
        $res = model("Message")->saveMessage($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
    * 更改消息状态
    * */
    public function editMessage(){
        $status = input("status");
        $read_status = input("read_status");
        $mark = input("mark");
        $ids = input("ids");
        $where['id'] = ['in',$ids];
        if(!empty($read_status)){
            $where['read_status'] = $read_status;
            if($read_status == 1){
                $data['read_time'] = time();
            }
        }
        if(!empty($status)){
            $data['status'] = $status;
        }
        if(!empty($mark)){
            $data['mark'] = $mark;
        }
        $res = model("Message")->saveMessage($data,$where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 发送消息
     * */
    public function reciveMessage(){
        $data['title'] = input("title");
        $data['type'] = input("type",2);
        $data['send_user_id'] = input("send_user_id");
        $data['send_user'] = input("send_user");
        $data['content'] = input("content");
        $data['recive_user_id'] = input("recive_user_id");
        $data['recive_user_name'] = input("recive_user_name");
        $data['recive_type'] = input("recive_type");
        $url = ADMIN_API."admin/Message/reciveMessage";
        $res = doCurl($url,null,json_encode($data),true);
        $res = json_decode($res,true);

    }

    /**
     * 根据条件获取消息数据
     * @return mixed
     */
    public function getData(){
        $param = request()->post();
        $validate = $this->validate($param,(new MessageParams())->getDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new MessageModel();
            $data = $model->getDataByPrams($param);
            return apiReturn(['code'=>200, 'data'=>$data]);
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
        }
    }

    /**
     * 增加消息数据
     * @return mixed
     */
    public function addMessageData(){
        $param = request()->post();
        $validate = $this->validate($param,(new MessageParams())->addMessageDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new MessageModel();
            $res = $model->insertMessageData($param);
            if (true === $res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1003, 'msg'=>$res]);
            }
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
        }
    }

    /**
     * 根据条件获取消息数量
     * @return mixed
     */
    public function getCountByWhere(){
        $param = request()->post();
        $validate = $this->validate($param,(new MessageParams())->getCountByWhereRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new MessageModel();
            $count = $model->countByWhere($param);
            return apiReturn(['code'=>200, 'data'=>$count]);
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
        }
    }

}
