<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

use PHPMailer\PHPMailer\PHPMailer;
use think\Config;
use think\Session;
use think\Log;
use think\Request;

// 应用公共文件

/**
 * 删除空格
 * @param unknown $str
 * @return mixed
 */
function trimall($str){
    $qian=array(" ","　","\t","\n","\r");
    $hou=array("","","","","");
    return str_replace($qian,$hou,$str);
}

/**
 * 根据生日获取年龄
 * @param unknown $birth
 * @return number
 */
function get_age_by_birthday($birth){
    if($birth!="0000-00-00"){
        list($by,$bm,$bd)=explode('-',$birth);
        $cm=date('n');
        $cd=date('j');
        $age=date('Y')-$by-1;
        if ($cm>$bm || $cm==$bm && $cd>$bd) $age++;
        return $age;
    }else{
        return 0;
    }
}

/**
 * seller密码加密处理
 * @param $password
 * @param string $salt 默认为“seller”
 * @return string
 */
function get_seller_password($password, $salt='seller'){
    return md5(md5(base64_encode(md5($salt.md5($password), true)), true));
}

/**
 * 缓存-redis
 * @param $name 缓存名
 * @param null $value 缓存值，为空则获取name的值，不为空则为设值
 * @param null $expire 过期时间（value不为空生效）
 * @return mixed
 */
function cache_redis($name, $value = null, $expire = null){
    if (!config('redis_switch_on')){
        return '';
    }
    $name = config('redis_cache_default_prefix').$name;
    $redis = new \app\index\dxcommon\RedisClusterBase();
    if (empty($value)){
        return $redis->get($name);
    }else{
        return $redis->set($name, $value, $expire);
    }
}

/**
 * 清除指定缓存-redis
 * @param $key
 * @return bool
 */
function cache_redis_del($key){
    $redis = new \app\index\dxcommon\RedisClusterBase();
    return $redis->del($key);
}

/**
 * 身份证校验
 * @param $idcard_num 身份证号码
 * @return false|int
 */
function verify_idcard($idcard_num){
    $pattern = '/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/';
    return preg_match($pattern, $idcard_num);
}

/**
 * 输出调试函数
 * @param array $args
 */
function pr($args = array()) {
    $escape_html = true;
    $bg_color = '#EEEEE0';
    $txt_color = '#000000';
    $args = func_get_args();
    foreach($args as $arr){
        echo sprintf('<pre style="background-color: %s; color: %s;">', $bg_color, $txt_color);
        if($arr) {
            if($escape_html){
                echo htmlspecialchars( print_r($arr, true) );
            }else{
                print_r($arr);
            }
        }else {
            var_dump($arr);
        }
        echo '</pre>';
    }
}

/**
 * 去重已经合并（以英文,合并）二维数组
 * @param $repeat_key_flag 去重的KEY
 * @param $merge_key_flag 要合并的KEY （以英文,合并）
 * @param $data 要处理的数组
 * @return array 返回格式
 */
function deduplication_arr($repeat_key_flag, $merge_key_flag, $data){
    $result = array();
    foreach($data as $val){
        $key = $val[$repeat_key_flag];
        if(!isset($result[$key])){
            $result[$key] = $val;
        }else{
            $result[$key][$merge_key_flag] .= ','.$val[$merge_key_flag];
        }
    }
    return array_values($result);
}

/**
 * 数组转换为对象
 * @param $array
 * @return StdClass
 */
function array2object($array) {
    if (is_array($array)) {
        $obj = new \StdClass();
        foreach ($array as $key => $val){
            $obj->$key = $val;
        }
    }else {
        $obj = $array;
    }
    return $obj;
}

/**
 * 数组排序
 * @param $arrays
 * @param $sort_key
 * @param int $sort_order
 * @param int $sort_type
 * @return bool
 */
function arr_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){
    if(is_array($arrays)){
        foreach ($arrays as $array){
            if(is_array($array)){
                $key_arrays[] = $array[$sort_key];
            }else{
                return false;
            }
        }
    }else{
        return false;
    }
    array_multisort($key_arrays,$sort_order,$sort_type,$arrays);
    return $arrays;
}

/**
 * 对象转为数组
 * @param $object
 * @return mixed
 */
function object2array($object) {
    if (is_object($object)) {
        foreach ($object as $key => $value) {
            $array[$key] = $value;
        }
    } else {
        $array = $object;
    }
    return $array;
}

/**
 * 获取IP地址
 * @return array|false|string
 */
function get_ip_address(){
    if(!empty($_SERVER["HTTP_X_REAL_IP"]))
    {
        $ip = $_SERVER["HTTP_X_REAL_IP"];
    }
    elseif ($_SERVER["REMOTE_ADDR"])
    {
        $ip = $_SERVER["REMOTE_ADDR"];
    }
    elseif (getenv("HTTP_X_FORWARDED_FOR"))
    {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    }
    elseif (getenv("HTTP_CLIENT_IP"))
    {
        $ip = getenv("HTTP_CLIENT_IP");
    }
    elseif (getenv("REMOTE_ADDR"))
    {
        $ip = getenv("REMOTE_ADDR");
    }
    else
    {
        $ip = "Unknown";
    }
    return $ip;
}

/**
 * 获取IP地址
 * @return array|false|string
 */
function get_ip(){
    $realip = '';
    $unknown = 'unknown';
    if (isset($_SERVER)){
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)){
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach($arr as $ip){
                $ip = trim($ip);
                if ($ip != 'unknown'){
                    $realip = $ip; break;
                }
            }
        }else if(isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], $unknown)){
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        }else if(isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)){
            $realip = $_SERVER['REMOTE_ADDR'];
        }else{
            $realip = $unknown;
        }
    }else{
        if(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), $unknown)){
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        }else if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), $unknown)){
            $realip = getenv("HTTP_CLIENT_IP");
        }else if(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), $unknown)){
            $realip = getenv("REMOTE_ADDR");
        }else{
            $realip = $unknown;
        }
    }
    $realip = preg_match("/[\d\.]{7,15}/", $realip, $matches) ? $matches[0] : $unknown;
    return $realip;
}

/**
 * 获取字符串长度
 * @param $str 字符串
 * @return int 对应长度
 */
function get_str_length($str){
    return mb_strlen($str,'utf-8');
}

/**
 * 二维数组根据某字段去重
 * @param $arr 目标处理数组
 * @param $key 要去重的KEY
 * @return array 返回的新数组
 */
function array_unset_repeat($arr,$key){
    $res = array();
    foreach ($arr as $k=>$value) {
        if(isset($res[$value[$key]])){
            unset($value[$key]);
        }
        else{
            $res[$value[$key]] = $value;
        }
    }
    return $res;
}

/**
 * 二维数组根据某字段去重[返回的数据不是关联数组]
 * @param $arr 要处理的数组
 * @param $key 要去重的key
 * @return mixed
 */
function array_unset_repeat_nokey($arr, $key) {
    $tmp_arr = [];
    foreach ($arr as $k => $v) {
        if (in_array($v[$key], $tmp_arr)) {//搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true
            unset($arr[$k]);
        } else {
            $tmp_arr[] = $v[$key];
        }
    }
    sort($arr); //sort函数对数组进行排序
    return $arr;
}

/**
 * 生成随机字符串
 * @param number $length
 * @return string
 */
function get_rand_str($length=5){
    $str = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randString = '';
    $len = strlen($str)-1;
    for($i = 0;$i < $length;$i ++){
        $num = mt_rand(0, $len);
        $randString .= $str[$num];
    }
    return $randString;
}

/**
 * 判断字符串中是否有中文
 * @param $str
 * @return bool
 */
function is_have_chinese($str){
    $rtn = false;
    //preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $str)>0 全部是中文
    //有中文
    if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $str) > 0){
        $rtn = true;
    }
    return $rtn;
}

/**
 * 发送POST请求
 * @param mixed $url
 * @param mixed $post_data
 * @return string
 */
function send_post($url, $post_data=null) {
    if($post_data){
        $post_data = http_build_query($post_data);
    }
    $options = array(
        'http' => array(
            'method' => 'POST',//or GET
            'header' => 'Content-type:application/x-www-form-urlencoded',
            'content' => $post_data,
            'timeout' => 15 * 60, // 超时时间（单位:s）,
//            'ignore_errors'=>true
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $index=strpos($result, "\xEF\xBB\xBF");
    if(!is_bool($index))
        $result=substr($result,$index+3);
    return $result;
}

/**
 * curl进行地址请求
 * @param $url 访问的URL
 * @param string $post post数据(不填则为GET)
 * @param string $cookie 提交的$cookies
 * @param int $returnCookie 是否返回$cookies
 * @return mixed|string
 */
function curl_request($url,$post='',$cookie='', $returnCookie=0){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt($curl, CURLOPT_REFERER, "http://XXX");
    if($post) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    if($cookie) {
        curl_setopt($curl, CURLOPT_COOKIE, $cookie);
    }
    curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10*60);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    curl_close($curl);
    if($returnCookie){
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
        if(isset($matches[1][0])){
            $info['cookie']  = substr($matches[1][0], 1);
        }
        $info['content'] = $body;
        $info['header'] = $header;
        return $info;
    }else{
        return $data;
    }
}

/**
 * curl实现post【json格式】
 * @param $url
 * @param $json_data
 * @return array
 */
function http_post_json($url, $json_data=null) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!empty($json_data)){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json; charset=utf-8",
            "Content-Length: " . strlen($json_data))
    );
    ob_start();
    curl_exec($ch);
    $return_content = ob_get_contents();
    ob_end_clean();
    $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//    return array($return_code, $return_content);
    return $return_content;
}

/**
 * 根据日期获取星期
 * @param mixed $date1
 * @return Ambigous <string>
 */
function get_week_by_dt($date1){
    $datearr = explode("-",$date1);//将传来的时间使用“-”分割成数组
    $year = $datearr[0];//获取年份
    $month = sprintf('%02d',$datearr[1]);//获取月份
    $day = sprintf('%02d',$datearr[2]);//获取日期
    $hour = $minute = $second = 0;//默认时分秒均为0
    $dayofweek = mktime($hour,$minute,$second,$month,$day,$year);//将时间转换成时间戳
    $shuchu = date("w",$dayofweek);//获取星期值
    $weekarray=array("周日","周一","周二","周三","周四","周五","周六");
    return $weekarray[$shuchu];
}

/**
 * 邮箱判断
 * @param $email：邮箱
 * @return bool：true-是邮箱，false-不是邮箱
 */
function is_email($email){
    $rtn = false;
    $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
    if (preg_match($pattern, $email)){
        $rtn = true;
    }
    return $rtn;
}

/**
 * 检查手机号码合法性
 * @param unknown $phone_num
 * @return boolean
 */
function is_phone_num($phone_num){
    if(
        preg_match("/^1[23456789]\d{9}$/", $phone_num)
        || preg_match("/^((0\d{2,3})-)?(\d{7,8})(-(\d{3,}))?$/", $phone_num)
    ){
        return true;
    }else{
        return false;
    }
}

function authcode($str, $seKey='dxseller')
{
    $key = substr(md5($seKey), 5, 8);
    $str = substr(md5($str), 8, 10);
    return md5($key . $str);
}

/**
 * 加/解密函数
 * @param $string 要加密的字符串
 * @param $operation 操作：E-加密，D-解密
 * @param string $key KEY值
 * @return bool|mixed|string 处理返回结果
 */
function encrypt($string,$operation,$key=''){
    $key=md5($key);
    $key_length=strlen($key);
    $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
    $string_length=strlen($string);
    $rndkey=$box=array();
    $result='';
    for($i=0;$i<=255;$i++){
        $rndkey[$i]=ord($key[$i%$key_length]);
        $box[$i]=$i;
    }
    for($j=$i=0;$i<256;$i++){
        $j=($j+$box[$i]+$rndkey[$i])%256;
        $tmp=$box[$i];
        $box[$i]=$box[$j];
        $box[$j]=$tmp;
    }
    for($a=$j=$i=0;$i<$string_length;$i++){
        $a=($a+1)%256;
        $j=($j+$box[$a])%256;
        $tmp=$box[$a];
        $box[$a]=$box[$j];
        $box[$j]=$tmp;
        $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
    }
    if($operation=='D'){
        if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){
            return substr($result,8);
        }else{
            return'';
        }
    }else{
        return str_replace('=','',base64_encode($result));
    }
}

/**
 * 根据字节转换文件大小
 * @param $file_size 字节byte
 * @return string
 */
function get_file_size($file_size) {
    if($file_size >= 1073741824) {
        $file_size = round($file_size / 1073741824 * 100) / 100 . ' G';
    } elseif($file_size >= 1048576) {
        $file_size = round($file_size / 1048576 * 100) / 100 . ' M';
    } elseif($file_size >= 1024) {
        $file_size = round($file_size / 1024 * 100) / 100 . ' KB';
    } else {
        $file_size = $file_size . ' Byte';
    }
    return $file_size;
}

/**
 * 系统邮件发送函数
 * @param string $tomail 接收邮件者邮箱
 * @param string $name 接收邮件者名称
 * @param string $subject 邮件主题
 * @param string $body 邮件内容
 * @param string $attachment 附件列表
 * @return boolean
 */
function send_mail($tomail, $name, $subject = '', $body = '', $attachment = null) {
    $email_config = Config::get('email');
    $mail = new PHPMailer();           //实例化PHPMailer对象
    $mail->CharSet = 'UTF-8';           //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->IsSMTP();                    // 设定使用SMTP服务
    $mail->SMTPDebug = 0;               // SMTP调试功能 0=关闭 1 = 错误和消息 2 = 消息
    $mail->SMTPAuth = true;             // 启用 SMTP 验证功能
//    $mail->SMTPSecure = 'ssl';          // 使用安全协议
    $mail->Host = $email_config['host']; // SMTP 服务器 "mail.comepro.com"
    $mail->Port = 25;                  // SMTP服务器的端口号
    $mail->Username = $email_config['username'];    // SMTP服务器用户名 "liuth@volumerate.com"
    $mail->Password = $email_config['password'];     // SMTP服务器密码 "vr654321"
    $mail->SetFrom($email_config['setform_address'], $email_config['setform_name']); //'liuth@volumerate.com' ,'DX Seller 测试'
    $replyEmail = '';                   //留空则为发件人EMAIL
    $replyName = '';                    //回复名称（留空则为发件人名称）
    $mail->AddReplyTo($replyEmail, $replyName);
    $mail->Subject = $subject;
    $mail->MsgHTML($body);
    $mail->AddAddress($tomail, $name);
    if (is_array($attachment)) { // 添加附件
        foreach ($attachment as $file) {
            is_file($file) && $mail->AddAttachment($file);
        }
    }
    return $mail->Send() ? true : $mail->ErrorInfo;
}

/**
 * 发送短信
 * @param array $params 发送短信参数，说明如下：
 *
 *      fixme 必填: 短信接收号码
 *      $params["PhoneNumbers"] = "17000000000";
 *
 *      fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
 *      $params["SignName"] = "短信签名";
 *
 *      fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
 *      $params["TemplateCode"] = "SMS_0000001";
 *
 *      fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
 *      $params['TemplateParam'] = Array (
 *              "code" => "12345",
 *              "product" => "阿里通信"
 *      );
 *
 *      fixme 可选: 设置发送短信流水号
 *      $params['OutId'] = "12345";
 *
 *      fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
 *      $params['SmsUpExtendCode'] = "1234567";
 *
 * @return bool|stdClass 返回数据格式
 *      stdClass Object
 *      (
 *          [Message] => OK //状态码的描述
 *          [RequestId] => 8B311C68-23C7-4450-B387-BB19D3C75FB4 //请求ID
 *          [BizId] => 649005121429413252^0 //发送回执ID,可根据该ID查询具体的发送状态
 *          [Code] => OK  //状态码-返回OK代表请求成功,其他错误码详见错误码列表:https://help.aliyun.com/knowledge_detail/57717.html?spm=a2c4g.11186623.6.586.f447sH
 *      )
 */
function send_sms($params){
    //短信防刷：同一个手机号一分钟只能发送一次
    $phone_num = $params['PhoneNumbers'];
    $time = time();
    if (
        !Session::has($phone_num) || //第一次发
        (Session::has($phone_num) && (($time - 60) > session($phone_num)))
    ){
        // *** 需用户填写部分 ***
        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $accessKeyId = config('aliyun_sms.accessKeyId');
        $accessKeySecret = config('aliyun_sms.accessKeySecret');
        // *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
        if(!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }
        // 此处可能会抛出异常，注意catch
        $sms_helper = new \Aliyun\DySDKLite\SignatureHelper();
        //签名
        $params['SignName'] = config('aliyun_sms.SignName');
        $content = $sms_helper->request(
            $accessKeyId,
            $accessKeySecret,
            "dysmsapi.aliyuncs.com",
            array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25",
            ))
        );
        $content = object2array($content);
        if ($content['Code'] == 'OK'){ //发送成功记录SESSION，为了实现1分钟限制
            session($phone_num, $time);
        }
    }else{
        $content = ['Message'=>'一分钟只能发送一次', 'Code'=>'-1'];
    }
    Log::record('调用短信服务-response：'.print_r($params, true).print_r($content, true));
    return $content;
}

/**
 * 获取seller code
 * @param $management_model 经营模式
 * @param $seller_id
 * @return string
 */
function get_seller_code($management_model, $seller_id){
    return date('Y').str_pad($management_model,3,"0",STR_PAD_LEFT).str_pad($seller_id,4,"0",STR_PAD_LEFT);
}


function GetIp(){
    $header = Request::instance()->header();
    if(isset($header['real-ip']) && !empty($header['real-ip'])){
        $ip = $header['real-ip'];
    }elseif (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")){
        $ip = getenv("HTTP_CLIENT_IP");
    }
    elseif (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")){
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    }
    elseif (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown")){
        $ip = getenv("REMOTE_ADDR");
    }
    elseif (isset ($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")){
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    else{
        $ip = "unknown";
    }
    //20181211 解决使用代理出现多IP情况
    $ip_arr = explode(',', str_replace('，', ',', $ip));
    $ip = isset($ip_arr[0])?$ip_arr[0]:'0.0.0.0';
    return ($ip);
}

/**
 * PHP判断当前协议是否为HTTPS
 */
function is_http_type()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return 'https';
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return 'https';
    } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return 'https';
    }
    return 'http';
}

/**
 * 查询字符转换
 * [QueryFiltering description]
 * @author: wang
 * AddTime:2018-12-17
 */
function QueryFiltering($value=''){
    if(!empty($value)){
        $result = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',',',',',',',',',' ',',',',',','],$value);
        $pattern      = '/(,)+/i';
        $result = preg_replace('/[(\xc2\xa0)|\s]+/','',preg_replace($pattern,',',$result));
        return $result;
    }
    return ;
}

function sendEmail($tomail, $subject = '', $body = '', $from = 'sales',$uconfig=null,$attachment = null){

    $config = config('send_email_config');

    if( isset($config[$from]) ){
        $config = $config[$from];
    }else{
        $config = $config['sales'];
    }

    if( !empty($uconfig) ){
        $config = array_merge($config,$uconfig);
    }

    $mail = new PHPMailer();           //实例化PHPMailer对象
    $mail->CharSet = 'UTF-8';           //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->IsSMTP();                    // 设定使用SMTP服务
    $mail->SMTPAuth = false;             // 启用 SMTP 验证功能

    $mail->SMTPDebug = $config['debug'];               // SMTP调试功能 0=关闭 1 = 错误和消息 2 = 消息
    $mail->IsHTML($config['isHtml']);
    //$mail->SMTPSecure = $config['secureType'];          // 使用安全协议
    $mail->Host = $config['host']; // SMTP 服务器
    $mail->Port = $config['port'];                  // SMTP服务器的端口号
    $mail->Username = $config['serverName'];        // SMTP服务器用户名
    $mail->Password = $config['serverPassword'];     // SMTP服务器密码
    $mail->SetFrom($config['fromEmail'],$config['fromName']);

    $replyEmail = '';                   //留空则为发件人EMAIL
    $replyName = '';                    //回复名称（留空则为发件人名称）
    $mail->AddReplyTo($replyEmail, $replyName);
    $mail->Subject = $subject;
    $mail->MsgHTML($body);

    if( is_array($tomail) ){
        foreach ($tomail as $value) {
            $mail->AddAddress($value);
        }
    }else{
        $mail->AddAddress($tomail);
    }


    if (is_array($attachment)) { // 添加附件
        foreach ($attachment as $file) {
            is_file($file) && $mail->AddAttachment($file);
        }
    }
    return $mail->Send() ? 'success' : $mail->ErrorInfo;
}

