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
use think\Db;
use app\common\helpers\RedisClusterBase;
use app\common\controller\Token;
use app\app\dxcommon\BaseApi;
use think\Log;
use think\Request;
// 应用公共文件
define('MYSQL_ADMIN', 'db_admin');//admin数据库名
define('OPERA_LOG', 'operation_log');//admin数据库名
define('MY_REVIEW_FILTERING', 'review_filtering');//Mysql数据表
/*
 * 生成guid
 * */
function guid(){
    if (function_exists('com_create_guid')){
        $guid = com_create_guid();
        $guid = preg_replace("/({|})/","",$guid);
        if(empty($guid)){
           $this->guid();
        }
        return $guid;
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
        return $uuid;
    }
}

/**
 * 获取随机字母
 * @param $length 长度
 * @return string
 */
function get_random_key($length) {
    $returnStr='';
    $pattern = 'ABCDEFGHIJKLOMNOPQRSTUVWXYZ';
    for($i = 0; $i < $length; $i ++) {
        $returnStr .= $pattern {mt_rand ( 0, 25 )};
    }
    return $returnStr;
}

/*
 * 判断是不是邮箱
 * */
function is_email($email){
    $pattern="/([a-z0-9]*[-_.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[.][a-z]{2,3}([.][a-z]{2})?/i";
    if(preg_match($pattern,$email)){
        return true;
    } else{
        return false;
    }
}
/*
 * 接口数据统一返回方法
 * 如果是返回给.net等强类型语言一定要商量好返回类型数据，避免对接数据类型报错
 * */
function apiReturn($data){
    $apicode = config("apicode");
    foreach ($apicode as $key => $value){
        if($data['code'] == $key && empty($data['msg'])){
            $data['msg'] = $value;
            break;
        }
    }
    if(empty($data['data'])){
        $data['data'] = (object)null;
    }
    return $data;
}

/*
 * 接口数据统一返回方法 默认返回空数组
 * 如果是返回给.net等强类型语言一定要商量好返回类型数据，避免对接数据类型报错
 * */
function apiJosn($data){
    $apicode = config("apicode");
    foreach ($apicode as $key => $value){
        if($data['code'] == $key && empty($data['msg'])){
            $data['msg'] = $value;
            break;
        }
    }
    if(!isset($data['data'])){
        $data['data'] = [];
    }
    return $data;
}

/**
 * app 结构要统一
 */
function getDefaultData(){
    $data['total'] = 0;
    $data['per_page'] = 0;
    $data['current_page'] = 0;
    $data['last_page'] = 0;
    $data['data'] = array();
    return $data;
}

/*
 * 加密密码
 * */
function encry_password($password){
    return strtoupper(SHA1($password));
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



/**
 * @desc  对接口中必须的参数进行验证，如果接口所必须的参数都有设置，则返回true，否则返回相应的错误信息给客户端
 * 说明：调用方法为: checkParam('param_1','param_2'...);参数个数无限制
 **/
function checkParam($arg_list,$data){
	foreach ($arg_list as $param){
		if(!isset($data[$param])){
			return false;
		}else{
			return true;
		}
	}
}

/*
 * 加密支付密码
 * */
function paypwd_encryption($str){
    $re = md5(bin2hex(hash('sha256', ("CIC_".$str), true)));
    $str1 = sha1($re);
    return $str1;
}

/**
 * 删除空格
 * @param unknown $str
 * @return mixed
 */
function trimall($str){
    $qian=array(" ","　","\t","\n","\r");
    $hou=array("","","","","");
    if(is_array($str)){
        foreach ($str as $k=>$v){
            $str[$k] = str_replace($qian,$hou,$v);
        }
        return $str;
    }
    return str_replace($qian,$hou,$str);
}

function doCurl($url,$data = null,$options = null,$isPost = true,$header = null){
    if (is_array($data)) {
        $data = json_encode($data);
    }

    $ch = curl_init();
    if (!empty($options)) {
        $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
    }else{
        $access_token = (new \app\common\controller\Base())->makeSign();
        $url = $url.'?access_token='.$access_token;
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT,60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (is_null($header)) {
        $header = array(
            "Content-type:application/json"
        );
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //设置头信息的地方
    if ($isPost) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    if (curl_errno($ch)) {
        return curl_error($ch);
    }
    $data = curl_exec($ch);
    //$this->errorCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //HTTPSTAT
    curl_close($ch);

    return json_decode($data,true);
}


function doCurlUser($url,$header = null,$msg = null,$isPost = false,$curlopt_timeout = 60){
    if (is_array($msg)) {
        $msg = json_encode($msg);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT,$curlopt_timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt ($ch, CURLOPT_SSLVERSION, 6); //Integer NOT string TLS v1.2
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
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
    // var_dump($curl);
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
 * curl进行地址请求
 * @param $url 访问的URL
 * @param string $post post数据(不填则为GET)
 * @param string $cookie 提交的$cookies
 * @param int $returnCookie 是否返回$cookies
 * @return mixed|string
 */
function curl_request_lms($url,$post='',$cookie='', $returnCookie=0){
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
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    // var_dump($curl);
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

/**系统操作日志
   * $status  状态 1为成功，2为更新中，3为失败
   * $content 内容
   * $system 所属系统 1为admin,2为API，3为help,4为lms，5为mall，6为SellerCenter，7为StaticContent，8为StaticFile，9为UserCenter，10为Task
   *
   * [operation_log description]
   * @return [type] [description]
   * author: Wang
   * AddTime:2018-05-03
   */
 function operation_log($system ='',$content ='',$status ='',$add_user_name = ''){
     $data['add_user_name'] = $add_user_name;
     $data['add_time']      = time();
     $data['status']        = $status;
     $data['system']        = $system;
     $data['content']       = $content;
     $result = Db::connect(MYSQL_ADMIN)->name(OPERA_LOG)->insert($data);
  }

/**
 * 随机生成规则号码
 * 1804 0012 5523658925
 * 年月日去掉前两位，4位站点数字，10位随机字符
 */
function createNumner(){
    $_time = substr(date("Ymd"),2,4);
    $_machine_id = config("machine_id");

    $_rand = rand(intval(pow(10,(10-1))),intval(pow(10,10)-1));

    return $_time.$_machine_id.$_rand;

    //echo $_time.rand(100,999);
}
/**redis 服务必须开启否则会报错
 * [redis description]
 * @return [type] [description]
 */
function redis(){
    return new RedisClusterBase();
     // $redis = new \Redis();

}

/**
 * 获取币种
 * @param int $code 币种状态码，带此参数返回币种名称
 * @param int $name 币种名称，带此参数返回币种币种状态码
 * @return array|string
 */
function getCurrency($code='',$name=''){
    $CurrencyList = config("Currency");
    if(empty($code) && empty($name)){
        return $CurrencyList;
    }
    $data = '';
    foreach ($CurrencyList as $key=>$value){
        if(!empty($code)){
            if($value['Code'] == $code){
                $data =  $value['Name'];
                break;
            }
        }else{
            if($value['Name'] == $name){
                $data = $value['Code'];
                break;
            }
        }
    }
    return $data;
}

/*清除数组空格*/
function TrimArray($arr){
    if (!is_array($arr))
        return trim($arr);
    return array_map('TrimArray', $arr);
}
   /**redis入队
    * [redis_rudui description]
    * @return [type] [description]
    * @author wang 2018/06/07
    */
function redis_enqueue($value='',$key){
      $redis = new RedisClusterBase();
      if($value==''){
        return;
      }
      try{
       return $redis->LPUSH('logistics_json',$value);//左边添加 元素
        // return $redis->LPOP('logistics_json');
      }catch(Exception $e){
        echo $e->getMessage()."\n";
      }
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
    $header_footer_where['type'] = 1;
    $EmailTemplate = controller("mallextend/EmailTemplate");
    $header_footer = $EmailTemplate->getData($header_footer_where);
    $header_footer_content = $header_footer['data'][0]['content'];
    $where['type'] = $type;
    $where['templetValueID'] = $templet_value_id;
    $res = $EmailTemplate->getData($where);
    $data = $res['data'][0];
    //邮件标题替换
    foreach ($title_values as $k => $v)
    {
        $data['title'] = str_replace('{'.$k.'}', $v, $data['title']);
    }
    //邮件内容替换
    foreach ($body_values as $k => $v)
    {
        $data['content'] = str_replace('{'.$k.'}', $v, $data['content']);
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

/*获取随机密码*/
function get_password( $length = 8 )
{
    $str = substr(md5(time()), 0, $length);
    return $str;
}

/*获取注册奖励优惠券详情*/
function getRegisterCouponInfo(){
    /*判断缓存是否存在*/
    if(Rcache("RegisterCouponInfo")){
        $RegisterCouponInfo = Rcache("RegisterCouponInfo");
    }else{
        $Coupon = controller("mallextend/Coupon");
        $apiCoupon= $Coupon->getCouponByCouponId(['CouponId'=>config("register_coupon")]);
        $RegisterCouponInfo = $apiCoupon['data'];
        Rcache("RegisterCouponInfo",$RegisterCouponInfo,['expire'=>3600*24]);
    }
    return $RegisterCouponInfo;
}


/**
 * 缓存管理
 * @param mixed     $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed     $value 缓存值
 * @param mixed     $options 缓存参数
 * @return mixed
 */
function Rcache($name='', $value = '', $options = null, $tag = null){
        $redis_cluster = new RedisClusterBase();
        if($value === ''){
            return $redis_cluster->get($name);
        }elseif(is_null($value)) {
            // 删除缓存
            return $redis_cluster->rm($name);
        } else {
            // 缓存数据
            if (is_array($options)) {
                $expire = isset($options['expire']) ? $options['expire'] : 0; //修复查询缓存无法设置过期时间
            } else {
                $expire = is_numeric($options) ? $options : 0; //默认快捷缓存设置过期时间
            }
            return $redis_cluster->set($name, $value, $expire);
        }
}
/**
* 评论过考虑缓存
* [ReviewFiltering description]
*  @author wang   2018-11-13
*/
function ReviewFiltering(){
    $redis = redis();//$redis->get('Redis_ReviewFiltering');
    $redis_Review = $redis->get(REDIS_REVIEW_FILTERING);//过滤数据
    if(empty($redis_Review)){
        $list = Db::connect('db_admin')->name(MY_REVIEW_FILTERING)->where(['id'=>1])->find();
        $redis->set(REDIS_REVIEW_FILTERING,$list['KeyWord'],1*24*3600);
        return $list['KeyWord'];
    }else{
        return $redis_Review;
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

/*
 * 本地上传图片
 * $image_name 图片名称参数
 * $type 1单图  2多图
 * */
function localUpload($image_name = 'image',$type=1){
    // 获取表单上传文件 例如上传了001.jpg
    $file = request()->file($image_name);
    // 移动到框架应用根目录/public/uploads/ 目录下
    if($file){
        $path = "public".DS . 'uploads';
        $upload_path = ROOT_PATH . $path;
        if($type != 1){
            $res = array();
            $file_url[] = array();
            $FileName[] = array();
            foreach($file as $key=> $value){
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $value->move($upload_path);
                if($info){
                    $file_name = explode('.',$info->getFilename());
                    $lase_nmae = strtolower(end($file_name));
                    $img_type = ['jpg', 'jpeg', 'png'];
                    if(!in_array($lase_nmae,$img_type)){
                        Log::write("The format of uploaded pictures is incorrect,file type:".$lase_nmae);
                    }else{
                        $file_path= 'uploads'. DS .$info->getSaveName();
                        $res['data'][$key]['file_url'] = $file_path;
                        $res['data'][$key]['FileName'] = $info->getFilename();
                        processingPictures($file_path,$lase_nmae);
                    }
                }else{
                    Log::write($file->getError());
                }
            }
            if(!empty($res['data'])){
                $res['code'] = 200;
                $res['msg'] = "上传成功";
            }else{
                $res['code'] = 100;
                $res['msg'] = "上传失败";
            }
            return $res;
        }else{
            $info = $file->move($upload_path);
            $name=$upload_path.'/'.$info->getSaveName();
            $getimagesize=getimagesize($name);

            if($info){
                $file_name = explode('.',$info->getFilename());
                if(!empty($getimagesize['mime'])){
                    $lase_nmae=substr($getimagesize['mime'],strripos($getimagesize['mime'],"/")+1);
                }else{
                    $lase_nmae = strtolower(end($file_name));
                }

                $img_type = ['jpg', 'jpeg', 'png'];
                if(!in_array($lase_nmae,$img_type)){
                    $res['code'] = 100;
                    $res['msg'] = "The format of uploaded pictures is incorrect";
                }else{
                    $file_path= 'uploads'. DS .$info->getSaveName();
                    $res['code'] = 200;
                    $res['msg'] = "上传成功";
                    $res['url'] = $file_path;
                    $res['FileName'] = $info->getFilename();
                    processingPictures($file_path,$lase_nmae);
                }
                return $res;
            }else{
                // 上传失败获取错误信息
                $res['code'] = 100;
                $res['msg'] = $file->getError();
                return $res;
            }
        }
    }else{
        $res['code'] = 100;
        $res['msg'] = "上传图片参数有误";
        return $res;
    }
}

/*生成提交防刷验证码
 * @param $UserId 用户ID
 * @param $Type 提交类型
 * @param $UserType 用户类型 1买家
 * @param $IsDeleteOld 获取新的验证码是否删除旧的
 * */
function getSubmitCode($Type="SendLoginSubmit",$UserId='',$UserType=1,$IsDeleteOld=1){
    $code['UserId'] = !empty($UserId)?$UserId:Cookie::get('PHPSESSID');
    $code['UserType'] = $UserType;
    $code['Type'] = $Type;
    $code['IsDeleteOld'] = $IsDeleteOld;
    /*防止别人刷，创建一个验证码*/
    $baseApi = new BaseApi();
    $verification_code = $baseApi->createVerificationCode($code);
    if(isset($verification_code['code']) || $verification_code['code']==200){
        return $verification_code['data']['VerificationCode'];
    }
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
 * 图片拼接
 */
function handleProductImgBySize($products,$size = 210){
    if(!empty($products)){
        foreach($products as $p => $product){
            if(!empty($product['FirstProductImage'])){
                $img = explode('.',$product['FirstProductImage']);
                if(!empty($img[0]) && !empty($img[1])){
                    $products[$p]['FirstProductImage'] = $img[0].'_'.$size.'x'.$size.'.'.$img[1];
                }
            }
        }
    }
    return $products;
}

function accessTokenToCurl($url,$header = null,$msg = null,$isPost = false,$curlopt_timeout = 60){
    $AccessToken = new Token();
    $Token = $AccessToken->makeSign();
    $url = $url."?access_token=".$Token;
    return doCurlUser($url,$header,$msg,$isPost,$curlopt_timeout);
}

/**
 * 写日志请求
 * @param $url
 * @param null $data 传的数据
 * @param null $options access_token
 * @param bool $isPost post
 * @param null $header 头部
 * @param null $from 主调函数
 * @return mixed
 */
function doLogCurl($url,$data = null,$options = null,$isPost = true,$header = null,$from = null,$user_agent = null){
    try{
        if (is_array($data)) {
            $data = json_encode($data);
        }
        $ch = curl_init();
        if (!empty($options)) {
            $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
        curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);
        if (is_null($header)) {
            $header = array(
                "Content-type:application/json"
            );
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //设置头信息的地方
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_exec($ch);
        curl_close($ch);
        return true;
    }catch (\think\Exception $e){
        return true;
    }
}

/**
 * 月份判断【非通用】，判断月份是否在[1,2,3,6,9,12]这几个月份中
 * @param $month
 * @return string
 */
function month_verify_special($month){
    if (!empty($month)){
        if (is_numeric($month)){
            if (!in_array($month, [1,2,3,6,9,12])){
                $month = '';
            }
        }else{
            $month = '';
        }
    }
    return $month;
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
    curl_close($curl);
    if(!empty($data['size'])){
        return true;
    }else{
        return false;
    }
}

function crul_submit($url,$path){
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