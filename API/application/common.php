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
use think\cache\driver\Redis;

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
    $pattern="/^(?:[a-zA-Z0-9!#$%&'*+=?\/^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&'*+=?\/^_`{|}~-]+)*|'(?:[-\b\v\f-!#-[]-]|\\[-	\v\f-])*')@(?:(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-zA-Z0-9-]*[a-zA-Z0-9]:(?:[-\b\v\f-!-ZS-]|\\[-	\v\f-])+)\])$/";
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
    if(!isset($data['data'])){
        $data['data'] = array();
    }
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

function doCurl($url,$data = null,$options = null,$isPost = false,$header = null){
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
    $data = curl_exec($ch);
    //$this->errorCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //HTTPSTAT
    curl_close($ch);

    return json_decode($data,true);
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
function getEmailTemplate($templet_value_id, array $title_values=[], array $body_values=[], $type=1,$header_footer_id=10){
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

/*获取发送优惠券新品推荐*/
function getCouponNewProducts(){
    /*判断缓存是否存在*/
    if(Rcache("CouponNewProducts")){
        $CouponNewProducts = Rcache("CouponNewProducts");
    }else{
        $NewProducts = doCurl(MALL_API.'/mallextend/ProductExtension/getNewProducts',null, null, true);
        //cic不能直接调用数据库，所以需要接口请求
//        $NewProducts = controller("mallextend/ProductExtension")->getNewProducts();
        if($NewProducts['code'] == 200){
            Rcache("CouponNewProducts",$NewProducts['data'],['expire'=>3600*24]);
            $CouponNewProducts = $NewProducts['data'];
        }

    }
    $send_products = array();
    if(isset($CouponNewProducts) && !empty($CouponNewProducts)){
        $send_products_key = array_rand($CouponNewProducts,3);
    }
    foreach ($send_products_key as $key=>$pkey){
        $send_products[$key] = $CouponNewProducts[$pkey];
    }
    return $send_products;
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
/*
* 生成GUID
 * yxh by 20190409
 */
function getGuid(){
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
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
    $Mongo = Db::connect('db_mongodb');
    // $this->NavigationBar = Db('NavigationBar');
    if(!empty($table) && !empty($ConfigName)){
        $result = $Mongo->name($table)->where(['ConfigName'=>$ConfigName])->field('ConfigValue')->find();dump($result);
        if($result){
            return array('code'=>200,'result'=>$result);
        }else{
            return array('code'=>100,'result'=>'配置信息查询不到对应的配置信息');
        }
    }else{
        return array('code'=>100,'result'=>'配置信息传递参数出错');
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
            if($info){
                $file_name = explode('.',$info->getFilename());
                $lase_nmae = strtolower(end($file_name));
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

function modSensitiveData($data){
    if( empty($data) ) return false;
    if( !is_array($data) ) return $data;

    $sData = ['email','mobile','phone','cpfno','creditcard','token','cardnumber','bin'];

    foreach ($data as $key => $value) {
        if( is_array($data[$key]) ){
            foreach ($data[$key] as $k => $v) {
                if( in_array(strtolower($k), $sData) ){
                    $data[$key][$k] = '***';
                }
            }
        }else{
            if( in_array(strtolower($key), $sData) ){
                $data[$key] = '***';
            }
        }
    }

    return $data;
}

function riskLog($level, $file,$line,$title,$content='') {
    $keyp = "risk";
    $max_size = 30000000;

    $path = RUNTIME_PATH."log".DS.date('Ym').DS;
    $log_filename = $path.$keyp."_".date('d'). ".log";

    if (file_exists($log_filename) && (abs(filesize($log_filename)) > $max_size)) {
        rename($log_filename, dirname($log_filename) . DS . $keyp ."_".date('d').'_'.date('His'). ".log");
    }

    $t = microtime(true);
    $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
    $d = new \DateTime (date('Y-m-d H:i:s.' . $micro, $t));
    if(is_array($content)){
        //删除敏感数据
        $content = modSensitiveData($content);

        $content = json_encode($content);
    }

    //错误日志要发邮件提醒
    if( 'error' == $level ){
        $email_users = config('risk_email_users');
        $email_content = <<< EOF
<div>
<p>文件 ：{$file}</p>
<p>行数 ：{$line}</p>
<p>概要 ：{$title}</p>
<p>细节 ：{$content}</p>
</div>
EOF;

        $email_data = array(
            'to_email'  => $email_users,
            'title'     => "风控错误",
            'content'   => $email_content,
        );

        $redis_cluster = new RedisClusterBase();
        $res = $redis_cluster->lPush("send_email_data_key",json_encode($email_data));
    }
    $data = array(
        'table'          => "logs_wind_control",
        'level'          => $level,
        'file'           => $file,
        'line'           => $line,
        'title'          => $title,
        'content'        => $content,
        'add_time_prc'   => date('Y-m-d H:i:s',time()),
    );
    
    $redis_cluster = new Redis(config("log_redis_cluster_config"));
    $res = $redis_cluster->lPush(
        "operation_log_key",json_encode($data)
    );

    return true;
    //file_put_contents($log_filename, $d->format('Y-m-d H:i:s') . "\r\n【level】{$level}\r\n【file 】{$file}\r\n【line 】{$line}\r\n【title】{$title}\r\n【content】{$content}\r\n---------------------------------------------\r\n\r\n", FILE_APPEND);
}