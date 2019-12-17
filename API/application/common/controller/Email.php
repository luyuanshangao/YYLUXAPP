<?php
namespace app\common\controller;
use think\Controller;
use think\Log;
use app\common\services\CommonService;

/**
 * 邮件操作类
 * @author tinghu.liu
 * @date 2018-05-31
 * @package app\index\dxcommon
 */
class Email
{


    /**
     * 发送邮件操作
     * @param $to_email 接收者
     * @param $title 标题
     * @param $content 邮件内容
     * @return bool
     */
    private static function send_email_soap($to_email, $title, $content,$CustomerID=25){
        $rtn = false;
        $function_name = 'SendOtherMail';
        $params = [
            'SendOtherMail'=>[
                'model'=>[
                    'Body'=>$content,
                    'CustomerEmail'=>$to_email,
                    'CustomerID'=>config('send_email.CustomerID'),
                    'EmailAddressBCC'=>config('send_email.EmailAddressBCC'),
                    'EmailAddressCC'=>config('send_email.EmailAddressCC'),
                    'From'=>config('send_email.From'),
                    'MSSUserName'=>config('send_email.MSSUserName'),
                    'SiteID'=>config('send_email.SiteID'),
                    'Title'=>$title
                ]
            ]
        ];
        $service = new CommonService();
        $res = $service->sendMailServiceSoap($function_name, $params);
        if (isset($res['SendOtherMailResult']) && !empty($res['SendOtherMailResult'])){
            $rtn = true;
        }else{
            Log::record('send_email_soap: error res:'.json_encode($res));
        }
        return $rtn;
    }
    /**
     * 注册账户激活时发送邮件
     * @param $to_email 接收者
     * @param $to_name 接收邮件者名称
     * @param array $body_values 邮件内容要替换的数据
     * @param array $title_values 邮件标题要替换的数据
     * @return mixed
     */
    public static function sendEmail($to_email, $templet_value_id,$to_name, array  $body_values, array $title_values=[], $type=1, $header_footer_id=10){
        //获取配置的邮件模板
        $data = getEmailTemplate($templet_value_id, $title_values, $body_values, $type, $header_footer_id);
        return self::send_email_soap($to_email, $data['title'], $data['content']);
    }

    /**
     * 注册账户激活时发送邮件
     * @param $to_email 接收者
     * @param $to_name 接收邮件者名称
     * @param array $body_values 邮件内容要替换的数据
     * @param array $title_values 邮件标题要替换的数据
     * @return mixed
     */
    public static function sendEmailForRegisterAccountActivation($to_email, $to_name, array  $body_values, array $title_values=[]){
        //获取配置的邮件模板
        $data = getEmailTemplate(503, $title_values, $body_values);
        return send_mail($to_email, $to_name, $data['title'], $data['content']);
    }

    /**
     * 找回密码时发送邮件
     * @param $to_email 接收者
     * @param $to_name 接收邮件者名称
     * @param array $body_values 邮件内容要替换的数据
     * @param array $title_values 邮件标题要替换的数据
     * @return mixed
     */
    public static function sendEmailForResetPassword($to_email, $to_name, array  $body_values, array $title_values=[]){
        //获取配置的邮件模板
        $data = Base::getEmailTemplate(502, $title_values, $body_values);
        return send_mail($to_email, $to_name, $data['title'], $data['content']);
    }

    public static function order_template($to_email, $title, $content){
        return self::send_email_soap($to_email, $title, $content);
    }



}
