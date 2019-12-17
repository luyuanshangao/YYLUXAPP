<?php
namespace app\seller\controller;
use app\common\controller\BaseApi;
use app\common\helpers\RedisClusterBase;
use app\common\helpers\CommonLib;
use app\common\controller\Base;
use think\Image;
use think\Log;

/**
 * Class Uploads
 * @author liuyuan
 * @date 2018-09-11
 * @package app\seller\controller
 */
class Uploads extends Base{
    private $queue_handle_number_limit;
    private $is_open_all_log;
	public function __construct()
    {
        $this->is_open_all_log = false;
        $this->queue_handle_number_limit = 100;
        $this->queue_key = "api_upload_product_image";
        //新上传图片redis队列
        $this->new_queue_key = "new_api_upload_product_image";
    }

	 /**
     * 文件上传
  
     */
    public function fileUpload(){
        try{
            $paramData = request()->post();
            $ftp_upload_dir = config('ftp_config.UPLOAD_DIR');
            $dir_path = $ftp_upload_dir['PRODUCT_IMAGES_SAVE'].date("Ymd");
            $water_dir_path = DS."water".DS.date("Ymd");
            $path = config('product_pic_upload_dir').$dir_path;
            $water_path = config('product_pic_upload_dir').$water_dir_path;
            if(!file_exists($path)){//检测文
                mkdir($path, 0777 , true );
            }
            if(!file_exists($water_path)){//检测文
                mkdir($water_path, 0777 , true );
            }
            if(isset($paramData['type'])&&$paramData['type']==1){//文件路径
                $url = $paramData['url'];
                $ch = curl_init();//初始化一个cURL会话
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT,60);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch,CURLOPT_SSLVERSION,3);//传递一个包含SSL版本的长参数
                $data = curl_exec($ch);// 执行一个cURL会话
                $error = curl_error($ch);//返回一条最近一次cURL操作明确的文本的错误信息。
                curl_close($ch);//关闭一个cURL会话并且释放所有资源
                $file_basename =rand(10,10000).time();
                $file_name =$file_basename.'.jpg';
                $dir = $path.'/'.$file_name;
                /*判断获取数据是否成功 20190412 kevin*/
                if($data === false){
                    Log::record('获取源图片失败:paramData'.json_encode($paramData));
                    return apiReturn(['code'=>1002, 'msg'=>'获取源图片失败!']);
                }
                $file = fopen($dir,"w+");
                $fputs_res = fputs($file,$data);//写入文件
                fclose($file);
                if($fputs_res){
                    if($this->is_open_all_log){
                        Log::record('原图上传本地服务器成功:'.$dir);
                    }
                }else{
                    Log::record('原图上传本地服务器失败:'.$dir);
                }
            }else{
                /*base64位图片上传 20190417 kevin*/
                if(!empty($paramData['image'])){
                    $img_data = $this->uploadBase64($paramData['image'],$path);
                    if(!empty($img_data['file_basename']) || !empty($img_data['file_name']) || !empty($img_data['dir'])){
                        $file_basename = $img_data['file_basename'];
                        $file_name = $img_data['file_name'];
                        $dir = $img_data['dir'];
                    }else{
                        Log::record('获取源图片失败:paramData'.json_encode($paramData));
                        return apiReturn(['code'=>1002, 'msg'=>'获取源图片失败!']);
                    }
                }else{
                    Log::record('获取源图片失败:paramData'.json_encode($paramData));
                    return apiReturn(['code'=>1002, 'msg'=>'获取源图片失败!']);
                }
            }
            $redis_cluster = new RedisClusterBase();
            /******生成水印图片start******/
            $new_file_name = $file_name;//水印文件名称
            $newdir =$water_path.'/'.$file_name;//水印文件路径
            $iamge = Image::open($dir);
            $water_res = $iamge->water(
                ROOT_PATH . 'public' . DS .'img/water/logo.png',
                rand(1,9)
            )->save($newdir);
            if($water_res){
                if($this->is_open_all_log){
                    Log::record('水印图上传本地服务器成功:'.$newdir);
                }
            }else{
                Log::record('水印图上传本地服务器失败:'.$newdir);
            }
            /******生成水印图片end******/
            /*异步上传产品原图*/
            $upload_data = [
                'dirPath'=>$dir_path,
                'romote_file'=>$file_name,
                'water_romote_file'=>$new_file_name,
                'local_file'=>$dir,
                'water_local_file'=>$newdir,
            ];
            $res = $redis_cluster->lPush(
                $this->queue_key,
                json_encode($upload_data)
            );
            $baseurl =config('cdn_url_config.url');
            if($res){
                if($this->is_open_all_log){
                    Log::record('加入redis队列成功:'.json_encode($upload_data));
                }
                return apiReturn(['code'=>200,'data'=>['save_name'=>$dir_path.'/'.$new_file_name,'baseurl'=>$baseurl]]);
            }else{
                Log::record('写入redis错误:'.json_encode($upload_data));
                return apiReturn(['code'=>1002, 'data'=>'上传失败!']);
            }
        }catch (\Exception $e){
            Log::record('msg:'.'上传图片不正常'.$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>'上传图片不正常'.$e->getMessage().'<br><br>']);
        }
    }


    /**
     * 多文件上传
     * add by 20191007 kevin
     */
    public function fileBatchUrlUpload(){
        try{
            $paramData = request()->post();
            $url = $paramData['url'];
            $ftp_upload_dir = config('ftp_config.UPLOAD_DIR');
            $dir_path = $ftp_upload_dir['PRODUCT_IMAGES_SAVE'].date("Ymd");
            $water_dir_path = DS."water".DS.date("Ymd");
            $path = config('product_pic_upload_dir').$dir_path;
            $water_path = config('product_pic_upload_dir').$water_dir_path;
            if(!file_exists($path)){//检测文
                mkdir($path, 0777 , true );
            }
            if(!file_exists($water_path)){//检测文
                mkdir($water_path, 0777 , true );
            }
            //判断是否是多图上传
            if(!is_array($url)){
                $url[] = $url;
            }
            $success_img_arr = array();//上传成功图片数组
            $fail_img_arr = array();//上传失败图片数组
            $redis_cluster = new RedisClusterBase();
            foreach ($url as $key=>$value){
                $validate = $this->validate(['img_url'=>$value],"Uploads.fileBatchUrlUpload");
                if(true !== $validate){
                    $fail_img_arr[$key]['error'] = $validate;
                    $fail_img_arr[$key]['url'] = $value;
                    Log::record($validate);
                    continue;
                }
                $file_basename =rand(10,10000).time();
                $file_name =$file_basename.'.jpg';
                $new_file_name = $file_name;//水印文件名称
                /*异步上传产品原图*/
                $upload_data = [
                    'file_name'=>$file_name,
                    'dir_path'=>$dir_path,
                    'water_dir_path'=>$water_dir_path,
                    'url'=>$value,
                ];
                $res = $redis_cluster->lPush(
                    $this->new_queue_key,
                    json_encode($upload_data)
                );
                $baseurl =config('cdn_url_config.url');
                if($res){
                    if($this->is_open_all_log){
                        Log::record('加入redis队列成功:'.json_encode($upload_data));
                    }
                    $success_img_arr[$key]['url'] = $value;
                    $success_img_arr[$key]['save_name'] = $dir_path.'/'.$new_file_name;
                    $success_img_arr[$key]['baseurl'] = $baseurl;
                }else{
                    $fail_img_arr[$key]['error'] = '写入redis错误';
                    $fail_img_arr[$key]['url'] = $value;
                    Log::record('写入redis错误:'.json_encode($upload_data));
                    continue;
                    // return apiReturn(['code'=>1002, 'data'=>'上传失败!']);
                }
            }
            return apiReturn(['code'=>200,'data'=>['success_data'=>$success_img_arr,'fail_data'=>$fail_img_arr]]);
        }catch (\Exception $e){
            Log::record('msg:'.'上传图片不正常'.$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>'上传图片不正常'.$e->getMessage().'<br><br>']);
        }
    }


    /**
     * 异步-处理产品上传，产品主图同步至CDN，且生成小图（70*70,210*210）
     */
    public function asyncProductImags(){
        //队列名称
        $redis_base = new RedisClusterBase();
        //队列长度
        $list_length = $redis_base->lLen($this->queue_key);
        //print_r(json_encode($redis_base->lRange($queue_key, 0, -1)));die;
        if ($list_length == 0){
            return apiReturn(['code'=>1001,'msg'=>'<span style="color: red;">The data is empty...</span>']);
        }
        if ($list_length < $this->queue_handle_number_limit){
            $this->queue_handle_number_limit = $list_length;
        }
        $success_number = 0;
        $fail_number = 0;
        $msg = "";
        //循环处理队列所有数据
        for ($i=0; $i<$this->queue_handle_number_limit; $i++){
            $data = json_decode($redis_base->rPop($this->queue_key), true);
            try{
                $flag = false;
                /**
                 * Array
                (
                [product_id] => 145
                [imgs] => Array
                (
                [0] => /newprdimgs/20180531/87b040ada109fe390977ca8f6c02c3ab.png
                [1] => /newprdimgs/20180531/1e7bf8e5003d1755ebdbe5b0e49fac1e.png
                )
                [from_flag] => 1-seller端上传图片（默认），2-erp上传图片（因为大图已经提前上传至CDN，所以只需要生成对应小图即可）
                )
                 */
                //1-seller端上传图片（默认），2-erp上传图片（因为大图已经提前上传至CDN，所以只需要生成对应小图即可）
                //$from_flag = isset($data['from_flag'])?$data['from_flag']:1;
                if (!empty($data)){
                    $ftp_put_data = [
                        'dirPath'=>$data['dirPath'],
                        'romote_file'=>$data['romote_file'], // 保存文件的名称
                        'local_file'=>$data['local_file']
                    ];
                    $water_ftp_put_data= [
                        'dirPath'=>$data['dirPath'],
                        'romote_file'=>$data['water_romote_file'], // 保存文件的名称
                        'local_file'=>$data['water_local_file']
                    ];
                    $res = self::data_put($ftp_put_data,config('original_ftp_config'));
                    if($res == true){
                        if($this->is_open_all_log){
                            Log::record('msg:'.'异步上传原图图成功,res:'.json_encode($ftp_put_data));
                        }
                        $res = self::data_put($water_ftp_put_data);
                        if($res == true){
                            if($this->is_open_all_log){
                                Log::record('msg:'.'异步上传水印图成功,res:'.json_encode($water_ftp_put_data));
                            }
                            $success_number++;
                            $flag = true;
                        }else{
                            $fail_number++;
                            Log::record('msg:'.'异步上传水印图不正常,res:'.$res);
                        }
                    }else{
                        $fail_number++;
                        Log::record('msg:'.'异步上传原图不正常,res:'.$res);
                    }

                    //处理失败或异常则重新加入队列
                    if (!$flag){
                        $queue_times = isset($data['queue_times'])?$data['queue_times']:0;
                        if ($queue_times <= 5){
                            $data['queue_times'] = $queue_times+1;
                            $redis_base->lPush($this->queue_key, json_encode($data));
                        }
                    }else{
                        //处理成功删除本地图片
                        @unlink($data['local_file']);
                        @unlink($data['water_local_file']);
                        if($this->is_open_all_log){
                            Log::record('msg:'.'上传成功删除图片,原图：'.$data['local_file'].",水印图：".$data['water_local_file']);
                        }
                        $msg.= "<br><br>============<span style='color: blue;'>处理成功的数据：</span>=============<br><br>";
                    }
                }
            }catch (\Exception $e){
                $fail_number++;
                $queue_times = isset($data['queue_times'])?$data['queue_times']:0;
                if ($queue_times <= 5){
                    $data['queue_times'] = $queue_times+1;
                    $redis_base->lPush($this->queue_key, json_encode($data));
                }
                Log::record('msg:'.'异步上传图片不正常'.$e->getMessage());
                $msg.='异步上传图片不正常'.$e->getMessage().'<br>data:'.json_encode($data).'<br>';
            }
        }
        return apiReturn(['code'=>200,'msg'=>$msg,'data'=>"success_number:".$success_number.",fail_number:".$fail_number]);
    }

    /**
     * 新异步-处理产品上传，产品主图同步至CDN，且生成小图（70*70,210*210）
     * add by 20191008 kevin
     */
    public function asyncNewProductImags(){
        //队列名称
        $redis_base = new RedisClusterBase();
        //队列长度
        $list_length = $redis_base->lLen($this->new_queue_key);
        //print_r(json_encode($redis_base->lRange($queue_key, 0, -1)));die;
        if ($list_length == 0){
            return apiReturn(['code'=>1001,'msg'=>'<span style="color: red;">The data is empty...</span>']);
        }
        if ($list_length < $this->queue_handle_number_limit){
            $this->queue_handle_number_limit = $list_length;
        }
        $success_number = 0;
        $fail_number = 0;
        $msg = "";
        $baseApi = new BaseApi();
        //循环处理队列所有数据
        for ($i=0; $i<$this->queue_handle_number_limit; $i++){
            $data = json_decode($redis_base->rPop($this->new_queue_key), true);
            try{
                $path = config('product_pic_upload_dir').$data['dir_path'];
                $water_path = config('product_pic_upload_dir').$data['water_dir_path'];
                if(!file_exists($path)){//检测文
                    mkdir($path, 0777 , true );
                }
                if(!file_exists($water_path)){//检测文
                    mkdir($water_path, 0777 , true );
                }
                $upload_msg = '';
                $flag = false;
                //根据图片url获取图片信息
                $ch = curl_init();//初始化一个cURL会话
                curl_setopt($ch, CURLOPT_URL, $data['url']);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT,60);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch,CURLOPT_SSLVERSION,3);//传递一个包含SSL版本的长参数
                $img_res = curl_exec($ch);// 执行一个cURL会话
                $error = curl_error($ch);//返回一条最近一次cURL操作明确的文本的错误信息。
                curl_close($ch);//关闭一个cURL会话并且释放所有资源
                $file_name = $data['file_name'];
                $dir = $path.'/'.$file_name;
                /*判断获取数据是否成功 20190412 kevin*/
                if($img_res === false){
                    $upload_msg = "获取源图片失败";
                    Log::record('获取源图片失败:paramData'.json_encode($data));
                    $email_data['title'] = "异步上传图片异常,位置:api.dx.com/seller/Uploads/asyncProductImags";
                    $email_data['content'] = '获取源图片失败，执行数据：'.json_encode($data);
                    $send_email = $baseApi->sendEmail($email_data);
                }else{
                    $file = fopen($dir,"w+");
                    $fputs_res = fputs($file,$img_res);//写入文件
                    fclose($file);
                    if($fputs_res){
                        if($this->is_open_all_log){
                            Log::record('原图上传本地服务器成功:'.$dir);
                        }
                    }else{
                        $upload_msg = "获取源图片失败";
                        Log::record('原图上传本地服务器失败:'.$dir);
                        $email_data['title'] = "异步上传图片异常,位置:api.dx.com/seller/Uploads/asyncProductImags";
                        $email_data['content'] = '原图上传本地服务器失败，执行数据：'.json_encode($data);
                        $send_email = $baseApi->sendEmail($email_data);
                    }
                    /******生成水印图片start******/
                    //$new_file_name = $file_name;//水印文件名称
                    $newdir =$water_path.'/'.$file_name;//水印文件路径
                    $iamge = Image::open($dir);
                    $water_res = $iamge->water(
                        ROOT_PATH . 'public' . DS .'img/water/logo.png',
                        rand(1,9)
                    )->save($newdir);
                    if($water_res){
                        if($this->is_open_all_log){
                            Log::record('水印图上传本地服务器成功:'.$newdir);
                        }
                    }else{
                        $upload_msg = "获取源图片失败";
                        Log::record('水印图上传本地服务器失败:'.$newdir);
                        $email_data['title'] = "异步上传图片异常,位置:api.dx.com/seller/Uploads/asyncProductImags";
                        $email_data['content'] = '水印图上传本地服务器失败，执行数据：'.json_encode($data);
                        $send_email = $baseApi->sendEmail($email_data);
                    }
                    $ftp_put_data = [
                        'dirPath'=>$data['dir_path'],
                        'romote_file'=>$file_name, // 保存文件的名称
                        'local_file'=>$dir
                    ];
                    $water_ftp_put_data= [
                        'dirPath'=>$data['dir_path'],
                        'romote_file'=>$file_name, // 保存文件的名称
                        'local_file'=>$newdir
                    ];
                    $res = self::data_put($ftp_put_data,config('original_ftp_config'));
                    if($res == true){
                        if($this->is_open_all_log){
                            Log::record('msg:'.'异步上传原图图成功,res:'.json_encode($ftp_put_data));
                        }
                        $res = self::data_put($water_ftp_put_data);
                        if($res == true){
                            if($this->is_open_all_log){
                                Log::record('msg:'.'异步上传水印图成功,res:'.json_encode($water_ftp_put_data));
                            }
                            $success_number++;
                            $flag = true;
                        }else{
                            $fail_number++;
                            $upload_msg = "获取源图片失败";
                            Log::record('msg:'.'异步上传水印图不正常,res:'.$res);
                            $email_data['title'] = "异步上传图片异常,位置:api.dx.com/seller/Uploads/asyncProductImags";
                            $email_data['content'] = '异步上传水印图不正常，执行数据：'.json_encode($data);
                            $send_email = $baseApi->sendEmail($email_data);
                        }
                    }else{
                        $fail_number++;
                        $upload_msg = "获取源图片失败";
                        Log::record('msg:'.'异步上传原图不正常,res:'.$res);
                        $email_data['title'] = "异步上传图片异常,位置:api.dx.com/seller/Uploads/asyncProductImags";
                        $email_data['content'] = '异步上传水印图不正常，执行数据：'.json_encode($data);
                        $send_email = $baseApi->sendEmail($email_data);
                    }
                }
                //处理失败或异常则重新加入队列
                if (!$flag){
                    $queue_times = !empty($data['queue_times'])?$data['queue_times']:0;
                    if ($queue_times <= 5){
                        $data['queue_times'] = $queue_times+1;
                        $redis_base->lPush($this->new_queue_key, json_encode($data));
                    }else{
                        $log['Url'] = $data['url'];
                        $log['Msg'] = $upload_msg ;
                        $log['OperateTime'] = time();
                        $log['table'] = "upload_product_image";
                        CommonLib::doLogCurl(config('api_base_url').'/log/index/operationLog',$log);
                    }
                }else{
                    //处理成功删除本地图片
                    @unlink($data['local_file']);
                    @unlink($data['water_local_file']);
                    if($this->is_open_all_log){
                        Log::record('msg:'.'上传成功删除图片,原图：'.$data['local_file'].",水印图：".$data['water_local_file']);
                    }
                    $msg.= "<br><br>============<span style='color: blue;'>处理成功的数据：</span>=============<br><br>";
                }
            }catch (\Exception $e){
                $fail_number++;
                $queue_times = !empty($data['queue_times'])?$data['queue_times']:0;
                if ($queue_times <= 5){
                    $data['queue_times'] = $queue_times+1;
                    $redis_base->lPush($this->new_queue_key, json_encode($data));
                }else{
                    $log['Url'] = $data['url'];
                    $log['Msg'] = $e->getMessage() ;
                    $log['OperateTime'] = time();
                    $log['table'] = "upload_product_image";
                    CommonLib::doLogCurl(config('api_base_url').'/log/index/operationLog',$log);
                }
                Log::record('msg:'.'异步上传图片不正常'.$e->getMessage());
                $msg.='异步上传图片不正常'.$e->getMessage().'<br>data:'.json_encode($data).'<br>';
                $email_data['title'] = "异步上传图片异常,位置:api.dx.com/seller/Uploads/asyncProductImags";
                $email_data['content'] = '异步上传水印图不正常，执行数据：'.json_encode($data);
                $send_email = $baseApi->sendEmail($email_data);
            }
        }
        return apiReturn(['code'=>200,'msg'=>$msg,'data'=>"success_number:".$success_number.",fail_number:".$fail_number]);
    }

    /**
     * ftp链接远程服务器
     * @return resource
     */
    private static function initFTP($ftp_config = ''){
        $ftp_config = !empty($ftp_config)?$ftp_config:config('ftp_config');
        $server = $ftp_config['DX_FTP_SERVER_ADDRESS'];
        $port = $ftp_config['DX_FTP_SERVER_PORT'];
        $server_sbn = $ftp_config['SBN_FTP_SERVER_ADDRESS'];
        $port_sbn = $ftp_config['SBN_FTP_SERVER_PORT'];
        $user_name = $ftp_config['DX_FTP_USER_NAME'];
        $password = $ftp_config['DX_FTP_USER_PSD'];
        $conn_id = ftp_connect($server_sbn, $port_sbn);
        if (!$conn_id) {
            //echo "Error: Could not connect to ftp. Please try again later.\n";
            Log::record('initFTP错误,ftp_config:'.json_encode($ftp_config));
            return 100;
        }
        $login_result = ftp_login($conn_id, $user_name, $password);
        if (!$login_result) {
            //echo "Error: Could not login to ftp. Please try again later.\n";
            Log::record('initFTP ftp_login错误,ftp_config:'.json_encode($ftp_config));
            return 101;
        }
        //SET FTP TO PASSIVE MODE
        $pasv_result = ftp_pasv($conn_id, TRUE);
        if (!$pasv_result) {
            Log::record('initFTP ftp_pasv错误,ftp_config:'.json_encode($ftp_config));
            return 102;
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
    private static function makeDir($connect, $dirPath){
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
     * 上传文件至FTP
     * @param $connect
     * @param $romote_file
     * @param $local_file
     * @param string $mode
     * @return bool
     */
    private static function uploadFile($connect, $romote_file, $local_file){
        $rtn = false;
        $rtn = ftp_put($connect, $romote_file, $local_file, FTP_BINARY);
        ftp_close($connect);
        return $rtn;
    }

    /**
     * 推送数据至CDN
     * @param array $config 配置，如下：
     * [
     *  'dirPath'=>'productImage/'.date('Ymd'), // ftp保存目录
     *  'romote_file'=>'test.jpg', // 保存文件的名称
     *  'local_file'=>'uploads\product\20180323/4b71b238fab435e853512a384c8b321a.jpg', // 要上传的文件
     * ]
     * @return bool
     */
    public static function data_put(array $config,$ftp_config=''){
        $connect =  self::initFTP($ftp_config);
        self::makeDir($connect, $config['dirPath']);
        //上传到远程服务器
        return self::uploadFile($connect, $config['romote_file'], $config['local_file']);
    }
    /** php 发送流文件
    * @param  String  $url  接收的路径
    * @param  String  $data 要发送的文件流
    * @return boolean
    */
    function sendStreamFile($url, $file,$name,$date){ 
      if(file_exists($file)){ 
        $data = array( 
          'content' => base64_encode(file_get_contents($file)),
          'name'=>$name,
          'date'=>$date
        ); 
        
        $ch = curl_init();//初始化一个cURL会话
        curl_setopt($ch,CURLOPT_URL,$url);//抓取url
        //设置请求方式是post方式
        curl_setopt($ch,CURLOPT_POST,1);
        //设置post请求提交的表单信息
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);


        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);//是否显示头信息
        curl_setopt($ch,CURLOPT_SSLVERSION,3);//传递一个包含SSL版本的长参数
        $response= curl_exec($ch);// 执行一个cURL会话
        $error = curl_error($ch);//返回一条最近一次cURL操作明确的文本的错误信息。
        return $response;
      }else{ 
        return false; 
      } 
    }

    /**
     *上传base64编码
     * @par  $base64   base64编码
     * @par  $path  文件夹名字 相对于/upload; 比如"Headimg"
     * @by pengqiang
     */
    function uploadBase64($base64,$up_dir){
        if(!file_exists($up_dir)){
            mkdir($up_dir,0777,true);
        }
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)){
            $type = $result[2];//图片格式
            if(in_array($type,array('jpeg','jpg','gif','bmp','png'))){
                $data['file_basename'] = rand(10,10000).time();
                $type = ($type == "jpeg")?"jpg":$type;
                $data['file_name'] = $data['file_basename'].'.'.$type;
                $new_file = $up_dir.DS.$data['file_name'];  //time()时间戳作为图片名字
                //print_r(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64))));exit;
                if(file_put_contents($new_file, base64_decode(str_replace($result[1], '',$base64)))){
                    $img_path = str_replace($up_dir, '', $new_file);//提取图片名字 和 格式
                    $data['dir'] = $up_dir.$img_path;//用作保存的图片路径和名字
                    return  $data;
                }
            }
        }
    }
}