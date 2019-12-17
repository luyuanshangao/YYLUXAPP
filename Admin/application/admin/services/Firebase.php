<?php
namespace app\admin\services;
use app\admin\model\MsgPush;
use think\Log;

/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/8/14
 * Time: 14:05
 */
class Firebase
{
    private $url;
    public function __construct()
    {
        $this->url = 'https://fcm.googleapis.com/fcm/send';
        defined('FIREBASE_API_KEY') or define('FIREBASE_API_KEY', 'AAAAhgluCGM:APA91bEUY1P_sNCRCcajZoNGSwzQeD0nyxMc0pGvVBhHvxZ6pYdimDuREoWHm45s2uDF1tsgZepdeCR2hlmcbGNq3UqSkbKAqfxKkhUwftSr8E89tEYEL9rF8F3QDi8dXCqESU0F0zcR');
    }

    public function send($data){
        //校验
        $type=!empty($data['type'])?$data['type']:'1';
        $da['title']=$message['gcm.notification.title']=$data['title'];
        $da['body']= $message['gcm.notification.body']=$data['body'];
        $message_data=$message['data']=!empty($data['data'])?$data['data']:'';
        $da['to']=$to=$data['to'];//推送目标
        $res=[];
        if($type==1){
            //单个推送
            $res=$this->sendOne($to, $message);
        }else{
            //主题推送
            $res=$this->sendToTopic($to, $message);
        }

        //群发消息,以后单个消息需要修改
        $da['type']=!empty($message_data['type'])?$message_data['type']:0;
        $da['complex_id']=!empty($message_data['id'])?$message_data['id']:0;
        $da['class_name']=!empty($message_data['class_name'])?$message_data['class_name']:'';
        $da['activity_url']=!empty($message_data['activity_url'])?$message_data['activity_url']:'';
        $da['activity_img']=!empty($message_data['activity_url'])?$message_data['activity_url']:'';

        $da['request_data']=json_encode($res);
        $da['add_time']=time();
        $MsgPush=new MsgPush();
        $re=$MsgPush->insert($da);
        return $res;
        //var_dump($re);die;
    }

    public function sendOne($to, $message)
    {
        $fields = array('to' => $to, 'data' => $message,);
        return $this->sendPushNotification($fields);
    }

    public function sendToTopic($to, $message)
    {
        $fields = array('to' => '/topics/' . $to, 'data' => $message,);
        return $this->sendPushNotification($fields);
    }

    public function sendMultiple($registration_ids, $message)
    {
        $fields = array('to' => $registration_ids, 'data' => $message,);
        return $this->sendPushNotification($fields);
    }

    private function sendPushNotification($fields)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
//        Log::record('FIREBASE_API_KEY:'.FIREBASE_API_KEY);
//        Log::record('$fields:'.json_encode($fields));
        $headers = array('Authorization: key=' . FIREBASE_API_KEY, 'Content-Type: application/json');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }
}
