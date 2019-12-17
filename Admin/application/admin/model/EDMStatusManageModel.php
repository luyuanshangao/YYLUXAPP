<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use think\Exception;
use think\Model;
use think\Session;

/**
 * EDM
 * @author zhongyang
 */
class EDMStatusManageModel
{


    public static $RecipientStatus = array(
        "0"=>"取消",
        "1" => '初始化',
        "2" => '正在拆分',
        "3" => '拆分失败',
        "4" => '拆分成功',
        "5"=>'正在上传',
        "100" => '就绪',
        "99" => '错误'
    );

    public static $RecipientLineStatus = array(
        "1" => '等待上传',
        "2" => '正在上传',
        "3" => '上传失败',
        "4" => '上传成功',
        "5"=>'生成Excel失败',
        "99" => '其它错误'
    );


    public static $EmailTaskStatus = array(
        "1" => '正在请求数据',
        "2" => '正在创建任务',
        "3" => '正在激活任务',
        "100" => '完成',
        "99" => '错误'
    );


    public static $EmailTaskLineStatus = array(
        "1" => '等待请求',
        "2" => '正在请求',
        "10"=>'请求中',
        "3" => '请求失败',
        "4" => '请求成功',
        "5" => '邮件任务创建成功',
        "6" => '邮件任务创建失败',
        "7" => '激活邮件任务成功',
        "8" => '激活邮件任务失败',
        "9" => '发送成功',
        "99" => '错误'
    );

    ///邮件服务商配置
    public static $EmailService=array(
        "Bc"=>"Broadcast",
        "BcNew"=>"BroadcastNew",
        "Extreme"=>"Extreme"
    );

     ///邮件发送者信息
     public static $EmailSender=array(
        "1"=>"DX.com / news@e.dx.com",
        "2"=>"DX.com / news@edm.dx.com"
    );
}
