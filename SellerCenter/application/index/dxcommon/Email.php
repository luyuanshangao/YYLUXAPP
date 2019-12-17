<?php
namespace app\index\dxcommon;

use app\services\controller\WcfService;
use think\Log;

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
    private static function send_email_soap($to_email, $title, $content){
        $rtn = sendEmail($to_email,$title,$content);
        if( $rtn=='success' ){
            return true;
        }
        return false;

        $rtn = false;
        $function_name = 'SendOtherMail';
        $params = [
            /*'SendOtherMail'=>[
                'model'=>[
                    'Body'=>$content,
                    'CustomerEmail'=>$to_email,
                    'CustomerID'=>18,
                    'EmailAddressBCC'=>'',
                    'EmailAddressCC'=>'',
                    'From'=>'seller.dx.com',
                    'MSSUserName'=>'',
                    'SiteID'=>1,
                    'Title'=>$title
                ]
            ]*/
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
        $service = new WcfService();
        $res = $service->sendMailServiceSoap($function_name, $params);
        if (isset($res['SendOtherMailResult']) && !empty($res['SendOtherMailResult'])){
            $rtn = true;
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
    public static function sendEmailForRegisterAccountActivation($to_email, $to_name, array  $body_values, array $title_values=[]){
        //获取配置的邮件模板
        $data = Base::getEmailTemplate(503, $title_values, $body_values);
        //return send_mail($to_email, $to_name, $data['title'], $data['content']);
        return self::send_email_soap($to_email, $data['title'], $data['content']);
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
        //return send_mail($to_email, $to_name, $data['title'], $data['content']);
        return self::send_email_soap($to_email, $data['title'], $data['content']);
    }



}
