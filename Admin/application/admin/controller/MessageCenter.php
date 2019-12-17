<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
// use think\queue\Job;
use \think\Session;
use app\admin\dxcommon\Email;
use app\admin\dxcommon\BaseApi;

/*
 * 平台管理--系统管理--消息管理
 * Add by:zhangheng
 * AddTime:2018-03-29
 * Info:
 *     1.平台管理--系统管理--消息管理:查询，修改，删除
 */
class MessageCenter extends Action
{
	public function __construct(){
       Action::__construct();
       define('MESSAGE', 'Message');//mysql数据表
       define('M_RECIVE', 'MessageRecive');//mysql数据表
       $this->Menu_logo();
    }
	/*
	 * 消息管理--查询
	 * auther Wang 2018-03-30
	 */
	public function index()
	{
        $message = DB::name(MESSAGE);
        $page = input("page",1);
        $data = input();
        $query = $data;
        	if(!empty($data["type"])){
                $where['a.type'] = $data["type"];
        	}
        	if(!empty($data["content"])){
                // $where['a.content'] = array('like','Admin%');$data["content"];
                $where['a.content'] = array('like','%'.$data["content"].'%');
        	}
        	if(!empty($data["recive_user_name"])){
                $where['b.recive_user_name'] = $data["recive_user_name"];
        	}
        	if(!empty($data["read_status"])){
                $where['b.read_status'] = $data["read_status"];
        	}
        	if(!empty($data["addtime_start"]) && !empty($data["addtime_end"])){
                $addtime = 'a.addtime';
                $where[$addtime] = array(array('egt',strtotime($data["addtime_start"])),array('elt',strtotime($data["addtime_end"])));
        	}
          if(!empty($data["mark"])){
                if($data["mark"] == 1){
                  $where['b.mark'] = array('gt',0);
                }else if($data["mark"] == 2){
                  $where['b.mark'] = array('eq',0);
                }
          }
          $list = DB::name('Message as a, dx_message_recive as b')
        	->where($where)
          ->where('a.id = b.message_id ')
        	->field('a.title,a.type,a.send_user,b.recive_type,a.content,a.addtime,b.recive_user_name,b.read_status,b.read_time,b.mark')
        	->order('a.addTime','desc')
        	->paginate(15,false,[ 'page' => $page,'query'=>$query]);//echo DB::name('Message as a, dx_message_recive as b')->getlastsql();
          $this->assign(['type'=>$data["type"],'content'=>$data["content"],'recive_user_name'=>$data["recive_user_name"],'mark'=>$data["mark"],'addtime_start'=>$data["addtime_start"],'addtime_end'=>$data["addtime_end"],]);

		$this->assign(['list'=>$list->items(),'Page'=>$list->render(),]);
		return View('index');
	}

	/*
	 * 消息管理--新增消息
	 * auther Wang  2018-03-30
	 *
	 */
	public function addMessage()
	{
		$message = DB::name(MESSAGE);
        $recive  = DB::name(M_RECIVE);
		//dump($date);
		//die();
        if(request()->isAjax()){//
        	$sendType = input("sendType");//账户类型 1 会员， 2 商家
            $userType = input("userType");
            $MessageType = input("MessageType");
            $userData = input("userData");
            $content = input("content");
            $Remark = input("Remark");
            $title = input("title");
            $user["type"] = $sendType;
            $userDataArray['user_data'] = explode(",",$userData);
            $userDataArray['field_type'] = $userType;
            if($sendType == 1){
                $CustomerInfo = BaseApi::getAdminCustomerData($userDataArray);
                if($CustomerInfo['code'] != 200){
                    return array('code'=>100,'msg'=>'未查询到用户相关信息');
                }
            }else{
                $CustomerInfo = BaseApi::getSendMessageSeller($userDataArray);
                if($CustomerInfo['code'] != 200){
                    return array('code'=>100,'msg'=>'未查询到用户相关信息');
                }
            }
            $send_number = 0;
            $send_fail = array();
            if($MessageType == 1){
                    $message_data['title'] = $title;
                    $message_data['type'] = 2;
                    $message_data['send_user_id'] = session('userid');
                    $message_data['send_user'] = session('username');
                    $message_data['content'] = $Remark;
                    $message_data['addtime'] = time();
                    $message_id = $message->insertGetId($message_data);//dump($message);
                    if($message_id){
                        foreach ($CustomerInfo['data'] as $key=>$value){
                            if($sendType == 1){
                                $user['id'] = $value['ID'];
                                $user['user_name'] = $value['UserName'];
                                $user['email'] = $value['email'];
                            }else{
                                $user['id'] = $value['id'];
                                $user['user_name'] = $value['true_name'];
                                $user['email'] = $value['email'];
                            }
                            $recive_array['message_id']     = $message_id;
                            $recive_array['recive_user_name'] = $user['user_name'];
                            $recive_array['recive_type']     = $sendType;//接受者类型 1用户 2卖家
                            $recive_array['read_status']    = 2;
                            $recive_array['recive_user_id'] = $user['id'];
                            $array_data = $recive_array;
                            $result = $recive->insert($array_data);
                            if($result){
                                $send_number++;
                            }else{
                                $send_fail[] = $user['id'];
                            }
                        }
                    }
            }else{
                foreach ($CustomerInfo['data'] as $key=>$value){
                    if($sendType == 1){
                        $user['id'] = $value['ID'];
                        $user['user_name'] = $value['UserName'];
                        $user['email'] = $value['email'];
                    }else{
                        $user['id'] = $value['id'];
                        $user['user_name'] = $value['true_name'];
                        $user['email'] = $value['email'];
                    }
                    $send_email = Email::send_email_soap($user['email'],$title,$content);
                    if($send_email){
                        $send_number++;
                    }else{
                        $send_fail[] = $user['id'];
                    }
                }
            }
            if($send_number >0 ){
                if(!empty($send_fail)){
                    return array('code'=>200,'msg'=>"成功发送信息 {$send_number} 条消息,发送失败用户ID有".implode(",",$send_fail));
                }else{
                    return array('code'=>200,'msg'=>"成功发送信息 {$send_number} 条消息.");
                }
            }else{
                return array('code'=>100,'msg'=>"信息发送失败");
            }
        }else{
            //echo $this->getlastsql();
            //$this->assign(['list'=>$result,'areaID'=>$areaID,'codeOrName'=>$whereLike]);
            return View('addMessage');
        }
	}
}