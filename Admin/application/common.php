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
use think\Db;
use vendor\Tree\Tree;
use vendor\Api\AccessToken;
use app\common\redis\RedisClusterBase;
use app\common\aes\aes;
use think\Request;
use app\admin\dxcommon\BaseApi;
//use app\common\ase\aes;



// 应用公共文件
error_reporting(E_ERROR | E_WARNING | E_PARSE);

//redis key  值定义  前缀 REDIS_
define('REDIS_PRODUCT_CLASS', 'product_class');//分类保存常量
//redis key --一级分类缓存KEY
define('REDIS_PRODUCT_FIRST_CLASS', 'product_first_class');
//redis 评论过滤关键词
define('REDIS_REVIEW_FILTERING', 'Redis_ReviewFiltering');

/**
 * redis缓存key
 */
define('DX_ORDER_QUERY', 'DX_ORDER_QUERY');//redis


define('DX_PRODUCT', 'dx_product');

// define('Mongo', $Mongo);//mongodb数据表
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
    //var_dump($data);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    curl_close($curl);
    if($returnCookie){
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
        $info['cookie']  = substr($matches[1][0], 1);
        $info['content'] = $body;
        return $info;
    }else{
    	//var_dump($data);
        return $data;
    }
}

/**
 * curl实现post【json格式】
 * @param $url
 * @param $json_data
 * @return array
 */
function http_post_json($url, $json_data) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
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
 * 加密字符串
 * @param $str
 * @return string
 */
function authstr($str)
{
    $key = substr(md5($this->seKey), 5, 8);
    $str = substr(md5($str), 8, 10);
    return md5($key . $str);
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

/*
     *ajax 返回
     *$code 返回状态
     *$result 返回内容
     */
function ajaxReturn($code,$result){
    return json_encode(array('code'=>$code,'result'=>$result));
}

/**
 * [public_delete description]
 * @return [type] [description]
 * 公用删除  以ID删除   物理删除
 * $table  mysql表明  和monodb
 * $val 1删除  monodb 2 mysql
 */
function publicDelete($table,$val=1){
    if($data = request()->post()){
        if($val==2){//mongodb
            $result = Db::connect("db_mongo")->name($table)->where(['_id'=>$data['id']])->delete();
        }else{//mysql
            $Logistics   = Db::name($table);
            $result = $Logistics->where(['id'=>$data['id']])->delete();
        }
        if($result){
            echo json_encode(array('code'=>200,'result'=>'删除成功'));
            exit;
        }else{
            echo json_encode(array('code'=>100,'result'=>'删除失败'));
            exit;
        }
    }
}

/**
 * 查询配置文件表信息 mongodb库
 * [publicConfig description]
 * @param  [type] $table       对应数据表      [description]
 * @param  [type] $ConfigName  需要查询对应名称[description]
 * @return [type]                              [description]
 * author  Wang
 */
function publicConfig($table,$ConfigName){
    $Mongo = Db::connect("db_mongo");
    // $this->NavigationBar = Db('NavigationBar');
    if(!empty($table) && !empty($ConfigName)){
        $result = $Mongo->name(S_CONFIG)->where(['ConfigName'=>$ConfigName])->find();
        if($result){
            return array('code'=>200,'result'=>$result);
        }else{
            return array('code'=>100,'result'=>'查询不到所选择数据');
        }
    }else{
        return array('code'=>100,'result'=>'出现为空的参数');
    }
}
/*
 * 查询配置文件表信息 mongodb库
 * json格式
 * author  Wang
 */
//function ConfigJson($ConfigName){
//    $Mongo = Db::connect("db_mongo");
//    if(!empty($ConfigName)){
//    if(!empty($ConfigName)){
//
//    }
//}
/**
 * [scoso description]
 * @return [type] [description]
 * ftp链接远程服务器
 */
function scoso(){
    $server = config("ftp_config.DX_FTP_SERVER_ADDRESS");
    $port = config("ftp_config.DX_FTP_SERVER_PORT");
    $user_name = config("ftp_config.DX_FTP_USER_NAME");
    $password = config("ftp_config.DX_FTP_USER_PSD");
    $conn_id = ftp_connect(config("ftp_config.SBN_FTP_SERVER_ADDRESS"), config("ftp_config.SBN_FTP_SERVER_PORT"));
    if (!$conn_id) {
        echo "Error: Could not connect to ftp. Please try again later.\n";
        return false;
    }
    $login_result = ftp_login($conn_id, $user_name, $password);
    if (!$login_result) {
        echo "Error: Could not login to ftp. Please try again later.\n";
        return false;
    }
    //SET FTP TO PASSIVE MODE
    $pasv_result = ftp_pasv($conn_id, TRUE);
    if (!$pasv_result) {
        return false;
    }
    return $conn_id;
}


/**
 * 创建目录并将目录定位到当请目录
 *
 * @param resource $connect 连接标识
 * @param string $dirPath 目录路径
 * @return mixed
 *       2：创建目录失败
 *       true：创建目录成功
 */
function makeDir($connect, $dirPath){
    //处理目录
    $dirPath = '/' . trim($dirPath, '/');
    $dirPath = explode('/', $dirPath);
    foreach ($dirPath as $dir){
        if($dir == '') $dir = '/';
        //判断目录是否存在
        if(@ftp_chdir($connect, $dir) == false){
            //判断目录是否创建成功
            if(@ftp_mkDir($connect, $dir) == false){
                return 2;
            }
            @ftp_chdir($connect, $dir);
        }
    }
    return true;
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
//修改
function publicUpdate($data,$table,$type)
{
    $id = $data['id'];
    unset($data['id']);
    if ($type == 1) {
        $result = Db::connect("db_mongo")->name($table)->where(['id' => (int)$id])->update($data);
        if ($result) {
            return true;
        } else {
            return false;
        }
    } else if ($type == 2) {
        $result = Db::name($table)->where(['id' => (int)$id])->update($data);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }
}

function getTree($data,$options=[],$level=0){
    vendor('Tree.Tree');
    return new Tree($data,$options,$level);
}

function doCurl($url,$header = null,$msg = null,$isPost = false){
    if (is_array($msg)) {
        $msg = json_encode($msg);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT,60);
    if (is_null($header)) {
        $header = array(
            "Content-type:application/json"
        );
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //设置头信息的地方
    if ($isPost) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
    }
    $data = curl_exec($ch);
    //$this->errorCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //HTTPSTAT
    curl_close($ch);
    return $data;
}
function accessTokenToCurl($url,$header = null,$msg = null,$isPost = false){
    $url = $url."?access_token=".Api_token();
    return json_decode(doCurl($url,$header,$msg,$isPost),true);
}
/**
 * redis
 * [redis description]
 * @return [type] [description]
 * @author: Wang
 * AddTime:2018-06-13
 */
function redis($options = []){

    return new RedisClusterBase();
 // $redis = new \Redis();
 // $redis->connect('127.0.0.1', 6379);
 // return $redis;
}

/**
 * 数据加入队列
 * [redis_set description]
 * @return [type] [description]
 * @author: Wang
 * AddTime:2018-06-13
 */
function redis_set($key='',$value='',$time=''){
     $redis = redis();
     if($key == '' || $value == ''){
        return false;
     }
     if($time == ''){
        $result = $redis->set($key,$value);
     }else{
        $result =  $redis->set($key,$value,$time);
     }
     return $result;
}
/**
 * [redis_get description]
 * @param  string $key [description]
 * @return [type]      [description]
 * @author: Wang
 * AddTime:2018-06-13
 */
function redis_get($key=''){
    $redis = redis();
    if($key != ''){
      return $redis->get($key);
    }else{
       return false;
    }
}

/**
 * [redis_get description]
 * @param  string $key [description]
 * @return [type]      [description]
 * @author: heng.zhang
 * AddTime:2018-07-20
 */
function redis_del($key=''){
    $redis = redis();
    if($key != ''){
        return $redis->del($key);
    }else{
        return false;
    }
}

/**
 * [redis_get description]
 * @param  string $key [description]
 * @return [type]      [description]
 * @author: heng.zhang
 * AddTime:2018-07-20
 */
function redis_exists($key=''){
    $redis = redis($options = []);
    if($key != ''){
        return $redis->exists($key);
    }else{
        return false;
    }
}

/**
 * 输出调试函数
 *
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

/*
 * 本地上传图片
 * */
function localUpload($isProcessing = true){
    // 获取表单上传文件 例如上传了001.jpg
    $file = request()->file('file');
    // 移动到框架应用根目录/public/uploads/ 目录下
    if($file){
        $path = "public".DS . 'uploads';
        $upload_path = ROOT_PATH . $path;
        $info = $file->move($upload_path);
        if($info){
            $file_name = explode('.',$info->getFilename());
            $lase_nmae = strtolower(end($file_name));
            $img_type = ['jpg', 'jpeg', 'png','gif'];
            if(!in_array($lase_nmae,$img_type)){
                $res['code'] = 100;
                $res['msg'] = "The format of uploaded pictures is incorrect";
            }else{
                $file_path= 'uploads'. DS .$info->getSaveName();
                $res['code'] = 200;
                $res['msg'] = "上传成功";
                $res['url'] = $file_path;
                $res['FileName'] = $info->getFilename();
                if($isProcessing){
                    processingPictures($file_path,$lase_nmae);
                }
            }
            return $res;
        }else{
            // 上传失败获取错误信息
            $res['code'] = 100;
            $res['msg'] = $file->getError();
            return $res;
        }
    }else{
        $res['code'] = 100;
        $res['msg'] = "上传图片超过尺寸";
        return $res;
    }
}

/*缩放图片，防止木马*/
function processingPictures($file_path,$type){
    /*
   步骤：
    1.打开图片源文件资源
    2.获得源文件的宽高
    3.使用固定的公式计算新的宽高
    4.生成目标图像资源
    5.进行缩放
    6.保存图像
    7.释放资源
    */
    //1.打开图片源文件资源
    switch($type)
    {
        case "png":
            $im=imagecreatefrompng($file_path);
            break;

        case "jpeg":
            $im=imagecreatefromjpeg($file_path);
            break;

        case "jpg":
            $im=imagecreatefromjpeg($file_path);
            break;
    }
    //$im = imagecreatefromjpeg($file_path);

    //2.获得源文件的宽高
    $fx = imagesx($im); // 获取宽度
    $fy = imagesy($im); // 获取高度


    //3.使用固定的公式计算新的宽高
    $sx = $fx;
    $sy = $fy;
    //4.生成目标图像资源
    $small = imagecreatetruecolor($sx,$sy);
    //5.进行缩放
    imagecopyresampled($small,$im,0,0,0,0,$sx,$sy,$fx,$fy);

    //6.保存图像
    if(imagejpeg($small,$file_path)) {
        //7.释放资源
        imagedestroy($im);
        imagedestroy($small);
        return true;
    } else {
        //7.释放资源
        imagedestroy($im);
        imagedestroy($small);
        return false;
    }
}

/**
 *                等比例压缩图片
 * @param String $src_imagename 源文件名        比如 “source.jpg”
 * @param int    $maxwidth      压缩后最大宽度
 * @param int    $maxheight     压缩后最大高度
 * @param String $savename      保存的文件名    “d:save”
 * @param String $filetype      保存文件的格式 比如 ”.jpg“
 * @version 1.0
 */
function resizeImage($src_imagename,$maxwidth,$maxheight,$savename,$filetype)
{

    $typeArr=explode(".",$filetype);
    $type=$typeArr;
    switch($type)
    {
        case "png":
            $im=imagecreatefrompng($src_imagename);
            break;

        case "jpeg":
            $im=imagecreatefromjpeg($src_imagename);
            break;

        case "gif":
            $im=imagecreatefromgif($src_imagename);
            break;
    }

    $current_width = imagesx($im);
    $current_height = imagesy($im);

    if(($maxwidth && $current_width > $maxwidth) || ($maxheight && $current_height > $maxheight))
    {
        if($maxwidth && $current_width>$maxwidth)
        {
            $widthratio = $maxwidth/$current_width;
            $resizewidth_tag = true;
        }

        if($maxheight && $current_height>$maxheight)
        {
            $heightratio = $maxheight/$current_height;
            $resizeheight_tag = true;
        }

        if($resizewidth_tag && $resizeheight_tag)
        {
            if($widthratio<$heightratio)
                $ratio = $widthratio;
            else
                $ratio = $heightratio;
        }

        if($resizewidth_tag && !$resizeheight_tag)

            $ratio = $widthratio;
        if($resizeheight_tag && !$resizewidth_tag)
            $ratio = $heightratio;

        $newwidth = $current_width * $ratio;
        $newheight = $current_height * $ratio;

        if(function_exists("imagecopyresampled"))
        {
            $newim = imagecreatetruecolor($newwidth,$newheight);
            imagecopyresampled($newim,$im,'','','','',$newwidth,$newheight,$current_width,$current_height);
        }
        else
        {
            $newim = imagecreate($newwidth,$newheight);
            imagecopyresized($newim,$im,'','','','',$newwidth,$newheight,$current_width,$current_height);
        }

        $savename = $savename.$filetype;
        imagejpeg($newim,$savename);
        imagedestroy($newim);
    }
    else
    {
        $savename = $savename.$filetype;
        imagejpeg($im,$savename);
    }
    die;

}
/**
 * 通往Api access_token
 * [Api_token description]
 * @author: wang
 * AddTime:2018-08-11
 */
function Api_token(){
    vendor('Api.AccessToken');
    $api = new Token();
    return $api->makeSign();
}

/**
+----------------------------------------------------------
 * 将一个字符串部分字符用*替代隐藏
+----------------------------------------------------------
 * @param string    $string   待转换的字符串
 * @param int       $bengin   起始位置，从0开始计数，当$type=4时，表示左侧保留长度
 * @param int       $len      需要转换成*的字符个数，当$type=4时，表示右侧保留长度
 * @param int       $type     转换类型：0，从左向右隐藏；1，从右向左隐藏；2，从指定字符位置分割前由右向左隐藏；3，从指定字符位置分割后由左向右隐藏；4，保留首末指定字符串
 * @param string    $glue     分割符
+----------------------------------------------------------
 * @return string   处理后的字符串
+----------------------------------------------------------
 */
function hideStr($string, $bengin = 0, $len = 4, $type = 0, $glue = "@") {
    if (empty($string))
        return false;
    $array = array();
    if($len == 0){
        $len = strlen($string)-$bengin;
    }
    if ($type == 0 || $type == 1 || $type == 4) {
        $strlen = $length = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string, 0, 1, "utf8");
            $string = mb_substr($string, 1, $strlen, "utf8");
            $strlen = mb_strlen($string);
        }
    }
    if ($type == 0) {
        for ($i = $bengin; $i < ($bengin + $len); $i++) {
            if (isset($array[$i]))
                $array[$i] = "*";
        }
        $string = implode("", $array);
    } else if ($type == 1) {
        $array = array_reverse($array);
        for ($i = $bengin; $i < ($bengin + $len); $i++) {
            if (isset($array[$i]))
                $array[$i] = "*";
        }
        $string = implode("", array_reverse($array));
    } else if ($type == 2) {
        $array = explode($glue, $string);
        $array[0] = hideStr($array[0], $bengin, $len, 1);
        $string = implode($glue, $array);
    } else if ($type == 3) {
        $array = explode($glue, $string);
        $array[1] = hideStr($array[1], $bengin, $len, 0);
        $string = implode($glue, $array);
    } else if ($type == 4) {
        $left = $bengin;
        $right = $len;
        $tem = array();
        for ($i = 0; $i < ($length - $right); $i++) {
            if (isset($array[$i]))
                $tem[] = $i >= $left ? "*" : $array[$i];
        }
        $array = array_chunk(array_reverse($array), $right);
        $array = array_reverse($array[0]);
        for ($i = 0; $i < $right; $i++) {
            $tem[] = $array[$i];
        }
        $string = implode("", $tem);
    }else if ($type == 5) {
        $array = explode($glue, $string);
        $len = $len - strlen($array[1]);
        $array[0] = hideStr($array[0], $bengin, $len, 0);
        $string = implode($glue, $array);
    }
    return $string;
}

/**
 * 获取首级分类
 * [Api_token description]
 * @author: wang
 * AddTime:2018-10-25
 */
function FirstLevelClass($field='',$type=true){
    if($type){
        $where = ['pid'=>0,'type'=>1,'status'=>1];
    }else{
        $where = ['pid'=>0,'status'=>1];
    }
    if($field != ''){
        return Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where($where)->field($field)->select();
    }else{
        return Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where($where)->select();
    }

}

/**
 * 获取全部分类数据
 * [Api_token description]
 * @author: wang
 * AddTime:2018-10-25
 */
function getAllProductClass($field='',$type=true){
    if($type){
        $where = ['type'=>1,'status'=>1];
    }else{
        $where = ['status'=>1];
    }
    if($field != ''){
        return Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where($where)->field($field)->select();
    }else{
        return Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where($where)->select();
    }

}

/**
 * 去除空参数
 * [Api_token description]
 * @author: wang
 * AddTime:2018-10-27
 */
function ParameterCheck($data=array()){
    if(!empty($data)){
        foreach($data as $k=>$v){
            if(empty($v) && $v!='0'){
                unset($data[$k]);
            }
        }
        return $data;
    }
    return false;
}
/*
 * 加密解密
 **/
function aes(){
    return new aes();
}
/**
 * 自定义分页
 * [countPage description]
 * @return [type] [description]
 */
function CountPage($count,$data_page,$url){
    $html = '';
    if(!empty($count)){
         $html .= '<ul class="pagination">';
         // $page_size = 7;
         if(!empty($data_page['page_size']) ){
              $page_size = $data_page['page_size'];
         }else{
              $page_size = config('paginate.list_rows');
         }

         $totalPage = ceil($count/$page_size);//算总页数
         $url_centre = '';//中间翻页
         if(empty($data_page['page'])){
              $page = 1;
         }else{
              $page = $data_page['page'];
              unset($data_page['page']);
         }
         if(!empty($data_page)){
            foreach ($data_page as $key => $value) {
               $url_centre .= '&'.$key.'='.$value;
            }
         }

         if($totalPage<=11){
            for($i=1;$i<=$totalPage;$i++){
                //上一页
                if($page<=1 && $i==1){
                   $html .= '<li class="disabled"><span>«</span></li>';
                }else if($i==1){
                   $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
                }
                //中间数字页
                if($page == $i){
                    $html .= '<li class="active"><span>'.$i.'</span></li>';
                }else{
                    $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
                }

                //下一页
                if($page>=$i && $totalPage == $i){
                   $html .= '<li class="disabled"><span>»</span></li>';
                }else if($totalPage == $i){
                   $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
                }
            }
         }else if($totalPage>11){
            $endPage = $totalPage-6;
            //作为末级
            if($page<=6){
                $end =11;
                for ($i=1; $i<=11 ; $i++) {
                    //上一页
                    if($page<=1 && $i==1){
                       $html .= '<li class="disabled"><span>«</span></li>';
                    }else if($i==1){
                       $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
                    }
                    //中间数字页
                    if($i<=8){
                        if($page == $i){
                            $html .= '<li class="active"><span>'.$i.'</span></li>';
                        }else{
                            $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
                        }
                    }else if($i==9){
                        $html .= '<li class="disabled"><span>...</span></li>';
                    }else if($i==10){
                        $html .= '<li><a href="'.$url.'?page='.($totalPage-2).$url_centre.'">'.($totalPage-2).'</a></li>';
                    }else if($i==11){
                        $html .= '<li><a href="'.$url.'?page='.($totalPage-1).$url_centre.'">'.($totalPage-1).'</a></li>';
                    }

                     //下一页
                    if($page>=$i && $end == $i){
                       $html .= '<li class="disabled"><span>»</span></li>';
                    }else if($end == $i){
                       $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
                    }
                }
            }else if($page>6 && $page<$endPage){
                 $end =13;
                 for ($i=1; $i<=13 ; $i++) {
                    //上一页
                    if($page<=1 && $i==1){
                       $html .= '<li class="disabled"><span>«</span></li>';
                    }else if($i==1){
                       $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
                    }
                    //中间数字页
                    if($i<=2){
                        if($page == $i){
                            $html .= '<li class="active"><span>'.$i.'</span></li>';
                        }else{
                            $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
                        }
                    }else if($i==3){
                        $html .= '<li class="disabled"><span>...</span></li>';
                    }else if($i==4){
                        $html .= '<li><a href="'.$url.'?page='.($page-3).$url_centre.'">'.($page-2).'</a></li>';
                    }else if($i==5){
                        $html .= '<li><a href="'.$url.'?page='.($page-2).$url_centre.'">'.($page-2).'</a></li>';
                    }else if($i==6){
                        $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">'.($page-1).'</a></li>';
                    }else if($i==7){
                        $html .= '<li class="active"><span>'.$page.'</span></li>';
                    }else if($i==8){
                        $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">'.($page+1).'</a></li>';
                    }else if($i==9){
                        $html .= '<li><a href="'.$url.'?page='.($page+2).$url_centre.'">'.($page+2).'</a></li>';
                    }else if($i==10){
                        $html .= '<li><a href="'.$url.'?page='.($page+3).$url_centre.'">'.($page+3).'</a></li>';
                    }else if($i==11){
                        $html .= '<li class="disabled"><span>...</span></li>';
                    }else if($i==12){
                        $html .= '<li><a href="'.$url.'?page='.($totalPage-2).$url_centre.'">'.($totalPage-2).'</a></li>';
                    }else if($i==13){
                        $html .= '<li><a href="'.$url.'?page='.($totalPage-1).$url_centre.'">'.($totalPage-1).'</a></li>';
                    }

                     //下一页
                    if($page>=$i && $end == $i){
                       $html .= '<li class="disabled"><span>»</span></li>';
                    }else if($end == $i){
                       $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
                    }
                }
            }else if($page>=$endPage){
                 $end =12;
                 for ($i=1; $i<=12 ; $i++) {
                    //上一页
                    if($page<=1 && $i==1){
                       $html .= '<li class="disabled"><span>«</span></li>';
                    }else if($i==1){
                       $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
                    }
                    //中间数字页
                    if($i<=2){
                        if($page == $i){
                            $html .= '<li class="active"><span>'.$i.'</span></li>';
                        }else{
                            $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
                        }
                    }else if($i==3){
                        $html .= '<li class="disabled"><span>...</span></li>';
                    }else if($i==4){
                        if($page != ($totalPage-8)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-8).$url_centre.'">'.($totalPage-8).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==5){
                        if($page != ($totalPage-7)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-7).$url_centre.'">'.($totalPage-7).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==6){
                        if($page != ($totalPage-6)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-6).$url_centre.'">'.($totalPage-6).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==7){
                        if($page != ($totalPage-5)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-5).$url_centre.'">'.($totalPage-5).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==8){
                        if($page != ($totalPage-4)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-4).$url_centre.'">'.($totalPage-4).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==9){
                        if($page != ($totalPage-3)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-3).$url_centre.'">'.($totalPage-3).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==10){
                        if($page != ($totalPage-2)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-2).$url_centre.'">'.($totalPage-2).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==11){
                        if($page != ($totalPage-1)){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage-1).$url_centre.'">'.($totalPage-1).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }else if($i==12){
                        if($page != $totalPage){
                            $html .= '<li><a href="'.$url.'?page='.($totalPage).$url_centre.'">'.($totalPage).'</a></li>';
                        }else{
                            $html .= '<li class="active"><span>'.$page.'</span></li>';
                        }

                    }

                     //下一页
                    if($page>=$i && $end == $i){
                       $html .= '<li class="disabled"><span>»</span></li>';
                    }else if($end == $i){
                       $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
                    }
                }
            }//
         }
         $html .= '</ul>';
         return $html;
    }else{
      return;
    }
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

function getCustomerService(){
    $admin_user_where['status'] = 1;
    $admin_user_where['group_id'] = ['in','9,12'];//客服
    $admin_user = Db::name("user")->where($admin_user_where)->field("id,username,group_id,status,add_time")->select();
    return $admin_user;
}
/**
 * [AdminFind description]
 * @param [type] $table 数据表
 * @param [type] $where 查询条件
 */
function AdminFind($table,$where,$status = 1,$field = ''){
    if($status == 2){
        return  Db::connect("db_mongo")->name($table)->where($where)->field($field)->find();
    }else{
        return Db::name($table)->where($where)->find();
    }
}
/**
 * [AdminFind description]
 * @param [type] $table 数据表
 * @param [type] $where 查询条件
 */
function AdminSelect($table,$where,$status = 1,$field = '',$page=1,$page_size=20){
    if($status == 2){
        return  Db::connect("db_mongo")->name($table)->where($where)->page($page,$page_size)->field($field)->select();
    }else{
        return Db::name($table)->where($where)->page($page,$page_size)->field($field)->select();
    }
}
/**
 * [AdminFind description]
 * @param [type] $table 数据表
 * @param [type] $where 添加数据
 */
function AdminInsert($table,$where){
    if(empty($table) || empty($where)){
        return;
    }
    return Db::name($table)->insert($where);
}
function AdminLog($data){
    $log = [
           'operator'=>Session::get("username"),
           'operator_id'=>Session::get("userid"),
           'ip'=>$_SERVER['REMOTE_ADDR'],
           'add_time'=>time(),
          ];
    return array_merge($log,$data);
}


/**
 * 获取邮件模板数据
 * @param $templet_value_id 模板ID【后台配置】
 * @param array $title_values 邮件标题要替换的数据
 * @param array $body_values 邮件内容要替换的数据
 * @param $type 邮件模板类型：1-Buyer，2-Seller
 * @return mixed
 */
function getEmailTemplate($templet_value_id, array $title_values, array $body_values, $type=1,$header_footer_id=10){
    $header_footer_where['templetValueID'] = $header_footer_id;
    $header_footer_where['type'] = $type;
    $header_footer = BaseApi::getEmailTemplateData($header_footer_where);
    $header_footer_content = $header_footer['data'][0]['content'];

    $where['type'] = $type;
    $where['templetValueID'] = $templet_value_id;
    $res = BaseApi::getEmailTemplateData($where);
    $data = $res['data'][0];
    //邮件标题替换
    foreach ($title_values as $k => $v)
    {
        $data['title'] = str_replace('{'.$k.'}', $v, $data['title']);
    }
    //邮件内容替换
    if(!empty($body_values)){
        foreach ($body_values as $k => $v)
        {
            $data['content'] = str_replace('{'.$k.'}', $v, $data['content']);
        }
    }

    $header_footer_values = ['sendtime'=>date("Y-m-d H:i:s"),'email_content'=>$data['content']];
    //邮件内容替换
    $email_all = $header_footer_content;
    foreach ($header_footer_values as $k => $v)
    {
        $email_all = str_replace('{'.$k.'}', $v, $email_all);
    }
    $data['content'] = $email_all;
    return $data;
}

/*
 * 替换掉文本内容
 * $replace_text 要替换的文本
 * $replace_data array 要替换的数据
 * */
function replaceContent($replace_text,$replace_data){
    //邮件标题替换
    foreach ($replace_data as $k => $v)
    {
        if(!is_array($v)){
            $replace_text = str_replace('{'.$k.'}', $v, $replace_text);
        }
    }
    return $replace_text;
}

function curl_del($url,$header= []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    //设置头
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //设置请求头
    curl_setopt($ch, CURLOPT_USERAGENT,  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//SSL认证。
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function crul_file($url,$path){
    $curl = curl_init();
//        if (class_exists('\CURLFile')) {
    curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
    $data1 = array('file' => new \CURLFile(realpath($path)));//>=5.5
//        } else {
//            if (defined('CURLOPT_SAFE_UPLOAD')) {
//                curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
//            }
//            $data1 = array('file' => '@' . realpath($path));//<=5.5
//        }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1 );
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_USERAGENT,"TEST");
    $result = curl_exec($curl);
    $data = json_decode($result,true);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    if(!empty($data['size'])){
        return true;
    }else{
        return false;
    }
}

function crul_submit($url,$path){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SAFE_UPLOAD, true);
    $data1 = array('file' => new \CURLFile(realpath($path)));//>=5.5
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1 );
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_USERAGENT,"TEST");
    $result = curl_exec($curl);
    $data = json_decode($result,true);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    curl_close($curl);
    return $data;
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