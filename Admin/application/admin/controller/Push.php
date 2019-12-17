<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 推送通知
 * Time: 2019/8/20 19:00
 */
namespace app\admin\controller;
use app\admin\services\Firebase;
use think\Db;
use app\admin\dxcommon\BaseApi;
use app\admin\model\MsgPush;

class Push  extends Action
{
    /*
     * 主页
     */
    public function index(){
       return $this->fetch();
    }

    /*
     * 推送
     */
    public function Save(){
        $params=input();
        $Firebase=new Firebase();
        $res= $Firebase->send($params);
        $data=[];
        if(!empty($res)){
            $code=200;
            $msg='发送成功';
        }else{
            $code=10009;
            $msg='发送失败';
        }
        $data['msg']=$msg;
        $data['code']=$code;

        return $data;
        //日志记录
    }

    public function del(){
        $id=input('id');
        $MsgPush=new MsgPush();
        $where['id']=$id;
        $re=$MsgPush->where($where)->delete();
        var_dump($re);
    }

}