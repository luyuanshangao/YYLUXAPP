<?php
namespace app\index\controller;

use app\index\dxcommon\FTPUpload;
use app\index\model\AlbumInfoModel;
use think\Image;
use think\Log;

/**
 * Class Uploads
 * @author tinghu.liu
 * @date 2018-03-23
 * @package app\index\controller
 */
class Uploads extends Common
{
    protected $base_upload_dir;          //文件上传基础路径
    protected $product_pic_upload_dir;          //文件上传路径

    protected $seller_upload_dir = 'seller';          //seller文件上传路径，基于文件上传基础路径下
    protected $ftp_seller_upload_dir;          //FTP seller文件上传路径，基于文件上传基础路径下

    protected $order_message_images_upload_dir = 'OrderMessageImage';          //订单消息文件上传路径，基于文件上传基础路径下
    protected $ftp_order_message_images_upload_dir;          //FTP 订单文件上传路径，基于文件上传基础路径下

    protected $order_after_sale_upload_dir = 'OrderAfterSaleImage';          //售后订单文件上传路径，基于文件上传基础路径下
    protected $ftp_order_after_sale_upload_dir;          //FTP 售后订单文件上传路径，基于文件上传基础路径下

    protected $upload_product_images_save;
    protected $cdn_url_config;
    public function _initialize(){
        parent::_initialize();
        $this->base_upload_dir = config('base_upload_dir');
        $this->product_pic_upload_dir = config('product_pic_upload_dir');
        //FTP相关上传路径设置
        $ftp_config = config('ftp_config');
        $ftp_upload_dir = $ftp_config['UPLOAD_DIR'];
        $this->ftp_seller_upload_dir = $ftp_upload_dir['SELLER_IMAGES'];
        $this->ftp_order_message_images_upload_dir = $ftp_upload_dir['ORDER_MESSAGE_IMAGES'];
        $this->ftp_order_after_sale_upload_dir = $ftp_upload_dir['ORDER_AFTER_SALE_IMAGES'];
        $this->upload_product_images_save = $ftp_upload_dir['PRODUCT_IMAGES_SAVE'];
        $this->cdn_url_config = config('cdn_url_config');
    }

    /**
     * 图片文件上传【产品上传】
     * 先上传至本机，异步同步至CDN
     */
    public function fileUpload(){
        Log::record('fileUpload-param-input'.print_r(input(), true));
        Log::record('fileUpload-param-upload_flag'.print_r(input('upload_flag'), true));
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '上传失败，请重试';
        $upload_flag = input('upload_flag');
        $upload_dir = $this->product_pic_upload_dir.$this->upload_product_images_save;//上传存放路径
        $water_dir = $this->product_pic_upload_dir.DS.'water';
        $file = request()->file('file');
        if($file){
            $info = $file->validate(['ext'=>'gif,jpg,jpeg,bmp,png'])->move($upload_dir);
            if($info){//上传成功 upload_product_images_save
                $base_save_name = str_replace('\\','/',$info->getSaveName());
                $base_save_name_arr = explode("/",$base_save_name);
                $save_name = $this->upload_product_images_save.$base_save_name;
                /** 来自于产品上传页面-产品图片，则保存至seller相册 **/
                if ($upload_flag == 100){
                    Log::record('fileUpload-param-productUpload');
                    $AlbumInfoModel = new AlbumInfoModel();
                    $user_data = session('user_data');
                    $picture_data = [
                        'seller_id'=>$user_data['user_id'], //商家ID
                        'picture_name'=>$save_name, //图片保存名称
                        'picture_origin_name'=>$file->getInfo('name'),//文件原名
//                        'picture_size'=>$file->getSize(), //图片大小：byte
                        //'picture_size'=>$file->getFileSize(), //图片大小：byte
                        'picture_extension'=>$info->getExtension(), //图片扩展名
                        'addtime'=>time() //写入时间
                    ];
                    $AlbumInfoModel->insertData($picture_data);
                }
                /** 生成水印 start **/
                //要生成水印的图片
                $path_name = str_replace('\\','/',$info->getPathname());
                if (!is_dir($water_dir.DS.$base_save_name_arr[0])) {
                    mkdir($water_dir.DS.$base_save_name_arr[0], 0755, true);
                }
                //水印保存地址
                $water_save_path = $water_dir.DS.$base_save_name;
                $iamge = Image::open($path_name);
                $iamge->water(
                    ROOT_PATH . 'public' . DS .'/static/img/water/logo.png',
                    rand(1,9)
                )->save($water_save_path);
                /** 生成水印 end **/
                /**
                 * $info->getSaveName();
                 * 如：20180322/42a79759f284b767dfcb2a0197904287.jpg
                 * 对应图片路径为：
                 * $upload_dir.20180322/42a79759f284b767dfcb2a0197904287.jpg
                 * $rtn['save_name'] = str_replace(['\\', '/'],'_',$info->getSaveName());
                 **/
                $rtn['save_name'] = str_replace('\\', '/', $save_name);
                /** hash_code 为了校验产品图片唯一性 暂时不做  **/
                //$rtn['hash_code'] = hash_file('sha256', $water_save_path);
                /** hash_code 为了校验产品图片唯一性 暂时不做  **/
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{// 上传失败
                $rtn['msg'] = $file->getError();
                Log::record('图片上传失败：'.$file->getError());
            }
        }
        return json($rtn);
    }
    /**
     * 图片文件批量上传【产品上传】
     * 先上传至本机，异步同步至CDN 
     *====================== 暂时不用 ========================
     */
    public function groupFileUpload(){
        Log::record('fileUpload-param-input'.print_r(input(), true));
        Log::record('fileUpload-param-upload_flag'.print_r(input('upload_flag'), true));
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '上传失败，请重试';
        $upload_flag = input('upload_flag');
        $upload_dir = $this->product_pic_upload_dir.$this->upload_product_images_save;//上传存放路径
        $files = request()->file('file');
        if(!empty($files)){
            foreach($files as $file){
                $info = $file->validate(['ext'=>'gif,jpg,jpeg,bmp,png'])->move($upload_dir);
                if($info){
                    $base_save_name = str_replace('\\','/',$info->getSaveName());
                    $save_name = $this->upload_product_images_save.$base_save_name;
                    /** 来自于产品上传页面-产品图片，则保存至seller相册 **/
                    if ($upload_flag == 100){
                        Log::record('fileUpload-param-productUpload');
                        $AlbumInfoModel = new AlbumInfoModel();
                        $user_data = session('user_data');
                        $picture_data = [
                            'seller_id'=>$user_data['user_id'], //商家ID
                            'picture_name'=>$save_name, //图片保存名称
                            'picture_origin_name'=>$file->getInfo('name'),//文件原名
    //                        'picture_size'=>$file->getSize(), //图片大小：byte
                            //'picture_size'=>$file->getFileSize(), //图片大小：byte
                            'picture_extension'=>$info->getExtension(), //图片扩展名
                            'addtime'=>time() //写入时间
                        ];
                        $AlbumInfoModel->insertData($picture_data);
                    }
                    /** 生成水印 start **/
                    //要生成水印的图片
                    $path_name = str_replace('\\','/',$info->getPathname());
                    //水印保存地址
                    $water_base = explode('.', explode('/', $base_save_name)[1]);
                    $water_save_path = $upload_dir.explode('/', $base_save_name)[0].DS.$water_base[0].'wt.'.$water_base[1];
                    $iamge = Image::open($path_name);
                    $iamge->water(
                        ROOT_PATH . 'public' . DS .'/static/img/water/logo.png',
                        rand(1,9)
                    )->save($water_save_path);
                    /** 生成水印 end **/
                    $save_name = explode('.', $save_name)[0].'wt.'.explode('.', $save_name)[1];
                    $save_names[] =str_replace('\\', '/', $save_name);
                }else{
                    $rtn['msg'] =$file->getError();
                    Log::record('图片上传失败：'.$file->getError());
                    return json($rtn);
                }    
            }
            $rtn['save_name'] =$save_names;
            /** hash_code 为了校验产品图片唯一性 暂时不做  **/
            //$rtn['hash_code'] = hash_file('sha256', $water_save_path);
            /** hash_code 为了校验产品图片唯一性 暂时不做  **/
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
        }
        return json($rtn);
    }

    /**
     * 图片上传-编辑器【产品上传】
     */
    public function fileUploadEditor(){
        header('Content-Type:text/html; charset=utf-8', true);
        $rtn = array('originalName'=>'','name'=>'','url'=>'','size'=>'','type'=>'','state'=>'');
        $rtn['state'] = '上传失败，请重试';
        $upload_dir = $this->product_pic_upload_dir.$this->upload_product_images_save;//上传存放路径
        $file = request()->file('upfile');
        $rtn['originalName'] = $file->getInfo('name');//文件原名
        $rtn['size'] = $file->getSize();//文件大小
        if($file){
            $info = $file->validate(['ext'=>'gif,jpg,jpeg,bmp,png'])->move($upload_dir);
            if($info){//上传成功
                //$file_name = $info->getFilename();
                $path_name = $info->getPathname();
                $base_save_name = str_replace('\\','/',$info->getSaveName());
                /** 生成水印 start **/
                //要生成水印的图片
                $path_name = str_replace('\\','/',$path_name);
                //水印保存地址
                $water_base = explode('.', explode('/', $base_save_name)[1]);
                $file_name = $water_base[0].'wt.'.$water_base[1];
                $water_save_path = $upload_dir.explode('/', $base_save_name)[0].DS.$file_name;
                $iamge = Image::open($path_name);
                $iamge->water(
                    ROOT_PATH . 'public' . DS .'/static/img/water/logo.png',
                    rand(1,9)
                )->save($water_save_path);
                /** 生成水印 end **/
                //直接上传到CDN
                $cdn_folder = $this->upload_product_images_save.date('Ymd');
                $res = FTPUpload::data_put([
                    'dirPath'=>$cdn_folder, // ftp保存目录
                    'romote_file'=>$file_name, // 保存文件的名称
                    'local_file'=>$water_save_path, // 要上传的文件
                ]);
                if ($res){
                    $rtn['type'] = '.'.$info->getExtension();//扩展名
                    $rtn['name'] = $info->getFilename();//纯文件名
                    $rtn['url'] = $this->cdn_url_config['url'].$cdn_folder.'/'.$file_name;//文件名【包含路径】
                    $rtn['state'] = 'SUCCESS';
                }else{
                    $rtn['msg'] = '上传失败';
                    Log::record('fileUploadEditor图片上传至CDN失败');
                }
                //删除本地水印图片，原图不删除
                @unlink($water_save_path);
            }else{// 上传失败
                $rtn['state'] = $file->getError();
                Log::record('图片上传失败-Editor：'.$file->getError());
            }
        }
        echo json_encode($rtn);exit;
    }

    /**
     * 订单消息文件上传
     */
    public function fileUploadForOrder(){
        header('Content-Type:text/html; charset=utf-8', true);
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '上传失败，请重试';
        $upload_dir = $this->base_upload_dir.$this->order_message_images_upload_dir;//上传存放路径
        $file = request()->file('file');
        $upfile = request()->file('imgFile');
        $file = !empty($file)?$file:$upfile;
        $rtn['originalName'] = $file->getInfo('name');//文件原名
        $rtn['size'] = $file->getSize();//文件大小
        if($file){
            $info = $file->validate(['ext'=>'gif,jpg,jpeg,bmp,png'])->move($upload_dir);
            if($info){//上传成功
                $path_name = $info->getPathname();
                $file_name = $info->getFilename();
                //直接上传到CDN
                $cdn_folder = $this->ftp_order_message_images_upload_dir.date('Ymd');
                $res = FTPUpload::data_put([
                    'dirPath'=>$cdn_folder, // ftp保存目录
                    'romote_file'=>$file_name, // 保存文件的名称
                    'local_file'=>$path_name, // 要上传的文件
                ]);
                if ($res){
                    if(!empty($upfile)){
                        $rtn['code'] = 200;
                        $rtn['msg'] = "Success";
                        $rtn['url'] = $cdn_folder.'/'.$file_name;
                        $rtn['complete_url'] = is_http_type().":".$this->cdn_url_config['url'].$cdn_folder.'/'.$file_name;//文件名【包含路径】
                        echo json_encode(array('error' => 0, 'url' => $rtn['complete_url']));exit;
                    }else{
                        $rtn['code'] = 0;
                        //$rtn['save_name'] = $info->getSaveName();
                        $rtn['save_name'] = $cdn_folder.'/'.$file_name;
                        $rtn['msg'] = 'success';
                    }
                    @unlink($path_name);
                }else{
                    $rtn['msg'] = $file->getError();
                    Log::record('fileUploadForOrder图片上传至CDN失败');
                }
            }else{// 上传失败
                $rtn['msg'] = $file->getError();
                Log::record('fileUploadForOrder图片上传失败：'.$file->getError());
            }
        }
        return json($rtn);
    }

    /**
     * seller资料图片文件上传
     */
    public function fileUploadForSeller(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '上传失败，请重试';
        $upload_dir = $this->base_upload_dir.$this->seller_upload_dir;//上传存放路径
        $file = request()->file('file');
        if($file){
            $info = $file->validate(['ext'=>'gif,jpg,jpeg,bmp,png'])->move($upload_dir);
            if($info){//上传成功
                /**
                 * $info->getSaveName();
                 * 如：20180322/42a79759f284b767dfcb2a0197904287.jpg
                 * 对应图片路径为：
                 * $upload_dir.20180322/42a79759f284b767dfcb2a0197904287.jpg
                 * $rtn['save_name'] = str_replace(['\\', '/'],'_',$info->getSaveName());
                 **/
                $rtn['save_name'] = $info->getSaveName();
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{// 上传失败
                $rtn['msg'] = $file->getError();
                Log::record('图片上传失败：'.$file->getError());
            }
        }
        return json($rtn);
    }

    /**
     * 订单文件上传【售后订单】
     */
    public function fileUploadForOrderAfterSale(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '上传失败，请重试';
        $upload_dir = $this->base_upload_dir.$this->order_after_sale_upload_dir;//上传存放路径
        try{
            $file = request()->file('file');
            if($file){
                $info = $file->validate(['ext'=>'gif,jpg,jpeg,bmp,png'])->move($upload_dir);
                if($info){//上传成功
                    $path_name = $info->getPathname();
                    $file_name = $info->getFilename();
                    //直接上传到CDN
                    $cdn_folder = $this->ftp_order_after_sale_upload_dir.date('Ymd');
                    $res = FTPUpload::data_put([
                        'dirPath'=>$cdn_folder, // ftp保存目录
                        'romote_file'=>$file_name, // 保存文件的名称
                        'local_file'=>$path_name, // 要上传的文件
                    ]);
                    if ($res){
                        @unlink($path_name);
                        $rtn['code'] = 0;
                        //$rtn['save_name'] = $info->getSaveName();
                        $rtn['save_name'] = $cdn_folder.'/'.$file_name;
                        $rtn['msg'] = 'success';
                    }else{
                        $rtn['msg'] = $file->getError();
                        Log::record('fileUploadForOrder图片上传至CDN失败');
                    }
                }else{// 上传失败
                    $rtn['msg'] = $file->getError();
                    Log::record('fileUploadForOrder图片上传失败：'.$file->getError());
                }
            }
        }catch (\Exception $e){
            $rtn['msg'] = '系统异常 '.$e->getMessage();
        }
        return json($rtn);
    }


}
