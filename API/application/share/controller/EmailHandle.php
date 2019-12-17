<?php
namespace app\share\controller;
use app\common\controller\Base;
use app\common\helpers\RedisClusterBase;
use think\Log;

class EmailHandle extends Base
{
    const SEND_EMAIL_DATA_KEY = 'send_email_data_key';
    const SEND_EMAIL_DATA_KEY2 = 'send_email_data_key2';
    const SEND_EMAIL_DATA_KEY3 = 'send_email_data_key3';
    const SEND_HTML_EMAIL_DATA_KEY = 'send_html_email_data_key';
    const SEND_HTML_EMAIL_DATA_KEY2 = 'send_html_email_data_key2';
    public function sendEmail($paramData=[])
    {
        try{
            /*获取发送邮件配置是否打开*/
            $BugFeedbackSendEmailStatus = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmailStatus"]);
            if(isset($BugFeedbackSendEmailStatus['code']) && $BugFeedbackSendEmailStatus['code'] == 200){
                if($BugFeedbackSendEmailStatus['data']){
                    $paramData = !empty($paramData)?$paramData:request()->post();
                    $validate = $this->validate($paramData,"share/EmailHandle.sendEmail");

                    if(true !== $validate){
                        return apiReturn(['code'=>1002,"msg"=>$validate]);
                    }
                    if(!empty($paramData['to_email'])){
                        $email_data = $paramData['to_email'];
                    }else{
                        $BugFeedbackSendEmail = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmail"]);
                        $email_data = $BugFeedbackSendEmail['data'];
                    }
                    if(!empty($email_data)){
                        /*是否是数组*/
                        if(is_array($email_data)){
                            foreach ($email_data as $key=>$value){
                                /*验证邮箱格式*/
                                if(!is_email($value)){
                                    Log::write("email_data:".json_encode($value)."The mailbox format is incorrect.");
                                    unset($email_data[$key]);
                                }
                            }
                        }else{
                            /*验证邮箱格式*/
                            if(!is_email($email_data)){
                                Log::write("email_data:".json_encode($email_data)."The mailbox format is incorrect.");
                                return apiReturn(['code'=>1002,"msg"=>"The mailbox format is incorrect."]);
                            }
                        }
                        if(!empty($email_data)){
                            $redis_cluster = new RedisClusterBase();
                            $send_email_data['to_email'] = $email_data;
                            $send_email_data['title'] = $paramData['title'];
                            $send_email_data['content'] = $paramData['content'];
                            $res = $redis_cluster->lPush(self::SEND_EMAIL_DATA_KEY,json_encode($send_email_data));
                            if($res){
                                $ret = apiReturn(['code'=>200]);
                            }else{
                                $ret = apiReturn(['code'=>1002, 'msg'=>'Push Redis Error']);
                            }
                        }else{
                            $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                        }
                    }else{
                        $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                    }
                    return $ret;
                }else{
                    return apiReturn(['code'=>1002, 'msg'=>"Closed Email Send"]);
                }
            }else{
                return apiReturn(['code'=>1002, 'msg'=>"BugFeedbackSendEmailStatus Configure Error"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    public function sendEmail2($paramData=[])
    {
        try{
            /*获取发送邮件配置是否打开*/
            $BugFeedbackSendEmailStatus = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmailStatus"]);
            if(isset($BugFeedbackSendEmailStatus['code']) && $BugFeedbackSendEmailStatus['code'] == 200){
                if($BugFeedbackSendEmailStatus['data']){
                    $paramData = !empty($paramData)?$paramData:request()->post();
                    $validate = $this->validate($paramData,"share/EmailHandle.sendEmail");

                    if(true !== $validate){
                        return apiReturn(['code'=>1002,"msg"=>$validate]);
                    }
                    if(!empty($paramData['to_email'])){
                        $email_data = $paramData['to_email'];
                    }else{
                        $BugFeedbackSendEmail = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmail"]);
                        $email_data = $BugFeedbackSendEmail['data'];
                    }
                    if(!empty($email_data)){
                        /*是否是数组*/
                        if(is_array($email_data)){
                            foreach ($email_data as $key=>$value){
                                /*验证邮箱格式*/
                                if(!is_email($value)){
                                    Log::write("email_data:".json_encode($value)."The mailbox format is incorrect.");
                                    unset($email_data[$key]);
                                }
                            }
                        }else{
                            /*验证邮箱格式*/
                            if(!is_email($email_data)){
                                Log::write("email_data:".json_encode($email_data)."The mailbox format is incorrect.");
                                return apiReturn(['code'=>1002,"msg"=>"The mailbox format is incorrect."]);
                            }
                        }
                        if(!empty($email_data)){
                            $redis_cluster = new RedisClusterBase();
                            $send_email_data['to_email'] = $email_data;
                            $send_email_data['title'] = $paramData['title'];
                            $send_email_data['content'] = $paramData['content'];
                            $res = $redis_cluster->lPush(self::SEND_EMAIL_DATA_KEY2,json_encode($send_email_data));
                            if($res){
                                $ret = apiReturn(['code'=>200]);
                            }else{
                                $ret = apiReturn(['code'=>1002, 'msg'=>'Push Redis Error']);
                            }
                        }else{
                            $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                        }
                    }else{
                        $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                    }
                    return $ret;
                }else{
                    return apiReturn(['code'=>1002, 'msg'=>"Closed Email Send"]);
                }
            }else{
                return apiReturn(['code'=>1002, 'msg'=>"BugFeedbackSendEmailStatus Configure Error"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    public function sendEmail3($paramData=[])
    {
        try{
            /*获取发送邮件配置是否打开*/
            $BugFeedbackSendEmailStatus = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmailStatus"]);
            if(isset($BugFeedbackSendEmailStatus['code']) && $BugFeedbackSendEmailStatus['code'] == 200){
                if($BugFeedbackSendEmailStatus['data']){
                    $paramData = !empty($paramData)?$paramData:request()->post();
                    $validate = $this->validate($paramData,"share/EmailHandle.sendEmail");

                    if(true !== $validate){
                        return apiReturn(['code'=>1002,"msg"=>$validate]);
                    }
                    if(!empty($paramData['to_email'])){
                        $email_data = $paramData['to_email'];
                    }else{
                        $BugFeedbackSendEmail = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmail"]);
                        $email_data = $BugFeedbackSendEmail['data'];
                    }
                    if(!empty($email_data)){
                        /*是否是数组*/
                        if(is_array($email_data)){
                            foreach ($email_data as $key=>$value){
                                /*验证邮箱格式*/
                                if(!is_email($value)){
                                    Log::write("email_data:".json_encode($value)."The mailbox format is incorrect.");
                                    unset($email_data[$key]);
                                }
                            }
                        }else{
                            /*验证邮箱格式*/
                            if(!is_email($email_data)){
                                Log::write("email_data:".json_encode($email_data)."The mailbox format is incorrect.");
                                return apiReturn(['code'=>1002,"msg"=>"The mailbox format is incorrect."]);
                            }
                        }
                        if(!empty($email_data)){
                            $redis_cluster = new RedisClusterBase();
                            $send_email_data['to_email'] = $email_data;
                            $send_email_data['title'] = $paramData['title'];
                            $send_email_data['content'] = $paramData['content'];
                            $res = $redis_cluster->lPush(self::SEND_EMAIL_DATA_KEY3,json_encode($send_email_data));
                            if($res){
                                $ret = apiReturn(['code'=>200]);
                            }else{
                                $ret = apiReturn(['code'=>1002, 'msg'=>'Push Redis Error']);
                            }
                        }else{
                            $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                        }
                    }else{
                        $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                    }
                    return $ret;
                }else{
                    return apiReturn(['code'=>1002, 'msg'=>"Closed Email Send"]);
                }
            }else{
                return apiReturn(['code'=>1002, 'msg'=>"BugFeedbackSendEmailStatus Configure Error"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    //接收html格式邮件(base64)
    public function sendHtmlEmail()
    {
        try{
            /*获取发送邮件配置是否打开*/
            $BugFeedbackSendEmailStatus = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmailStatus"]);
            if(isset($BugFeedbackSendEmailStatus['code']) && $BugFeedbackSendEmailStatus['code'] == 200){
                if($BugFeedbackSendEmailStatus['data']){
                    $paramData = request()->post();

                    $validate = $this->validate($paramData,"EmailHandle.sendEmail");
                    if(true !== $validate){
                        return apiReturn(['code'=>1002,"msg"=>$validate]);
                    }

                    $paramData['content'] = base64_decode($paramData['content']);

                    if(!empty($paramData['to_email'])){
                        $email_data = $paramData['to_email'];
                    }else{
                        $BugFeedbackSendEmail = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmail"]);
                        $email_data = $BugFeedbackSendEmail['data'];
                    }
                    if(!empty($email_data)){
                        /*是否是数组*/
                        if(is_array($email_data)){
                            foreach ($email_data as $key=>$value){
                                /*验证邮箱格式*/
                                if(!is_email($value)){
                                    Log::write("email_data:".json_encode($value)."The mailbox format is incorrect.");
                                    unset($email_data[$key]);
                                }
                            }
                        }else{
                            /*验证邮箱格式*/
                            if(!is_email($email_data)){
                                Log::write("email_data:".json_encode($email_data)."The mailbox format is incorrect.");
                                return apiReturn(['code'=>1002,"msg"=>"The mailbox format is incorrect."]);
                            }
                        }
                        if(!empty($email_data)){
                            $redis_cluster = new RedisClusterBase();
                            $send_email_data['to_email'] = $email_data;
                            $send_email_data['title'] = $paramData['title'];
                            $send_email_data['content'] = $paramData['content'];
                            $send_email_data['from'] = !empty($paramData['from'])?$paramData['from']:'sales';
                            $res = $redis_cluster->lPush(self::SEND_HTML_EMAIL_DATA_KEY,json_encode($send_email_data));
                            if($res){
                                $ret = apiReturn(['code'=>200]);
                            }else{
                                $ret = apiReturn(['code'=>1002, 'msg'=>'Push Redis Error']);
                            }
                        }else{
                            $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                        }
                    }else{
                        $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                    }
                    return $ret;
                }else{
                    return apiReturn(['code'=>1002, 'msg'=>"Closed Email Send"]);
                }
            }else{
                return apiReturn(['code'=>1002, 'msg'=>"BugFeedbackSendEmailStatus Configure Error"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    //接收html格式邮件(base64)
    public function sendHtmlEmail2()
    {
        try{
            /*获取发送邮件配置是否打开*/
            $BugFeedbackSendEmailStatus = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmailStatus"]);
            if(isset($BugFeedbackSendEmailStatus['code']) && $BugFeedbackSendEmailStatus['code'] == 200){
                if($BugFeedbackSendEmailStatus['data']){
                    $paramData = request()->post();

                    $validate = $this->validate($paramData,"EmailHandle.sendEmail");
                    if(true !== $validate){
                        return apiReturn(['code'=>1002,"msg"=>$validate]);
                    }
                    
                    $paramData['content'] = base64_decode($paramData['content']);

                    if(!empty($paramData['to_email'])){
                        $email_data = $paramData['to_email'];
                    }else{
                        $BugFeedbackSendEmail = controller("mallextend/SysConfig")->getSysCofigValue(["ConfigName"=>"BugFeedbackSendEmail"]);
                        $email_data = $BugFeedbackSendEmail['data'];
                    }
                    if(!empty($email_data)){
                        /*是否是数组*/
                        if(is_array($email_data)){
                            foreach ($email_data as $key=>$value){
                                /*验证邮箱格式*/
                                if(!is_email($value)){
                                    Log::write("email_data:".json_encode($value)."The mailbox format is incorrect.");
                                    unset($email_data[$key]);
                                }
                            }
                        }else{
                            /*验证邮箱格式*/
                            if(!is_email($email_data)){
                                Log::write("email_data:".json_encode($email_data)."The mailbox format is incorrect.");
                                return apiReturn(['code'=>1002,"msg"=>"The mailbox format is incorrect."]);
                            }
                        }
                        if(!empty($email_data)){
                            $redis_cluster = new RedisClusterBase();
                            $send_email_data['to_email'] = $email_data;
                            $send_email_data['title'] = $paramData['title'];
                            $send_email_data['content'] = $paramData['content'];
                            $send_email_data['from'] = !empty($paramData['from'])?$paramData['from']:'sales';
                            $res = $redis_cluster->lPush(self::SEND_HTML_EMAIL_DATA_KEY2,json_encode($send_email_data));
                            if($res){
                                $ret = apiReturn(['code'=>200]);
                            }else{
                                $ret = apiReturn(['code'=>1002, 'msg'=>'Push Redis Error']);
                            }
                        }else{
                            $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                        }
                    }else{
                        $ret = apiReturn(['code'=>1002, 'msg'=>'To Email Error']);
                    }
                    return $ret;
                }else{
                    return apiReturn(['code'=>1002, 'msg'=>"Closed Email Send"]);
                }
            }else{
                return apiReturn(['code'=>1002, 'msg'=>"BugFeedbackSendEmailStatus Configure Error"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

}
