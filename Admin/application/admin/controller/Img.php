<?php
namespace app\admin\controller;
use \think\Session;
use think\View;
use think\Controller;
use think\Paginator;
use think\Log;
use think\Db;
use app\admin\dxcommon\BaseApi;
use app\admin\model\Businessmanagement as Business;
use app\admin\dxcommon\ExcelTool;

/**
 * 工具类:历史数据同步:
 * 1.产品类别，产品数据;
 * 2.品牌数据;
 * 3.销售属性数据;
 * @author heng.zhang 2018-05-20
 *
 */
class Img
{
    public function __construct(){
      define('BRANDS', 'dx_brands');//mongodb品牌表
      define('BRAND_IMG_URL', 'http://scs.dxcdn.com/brand/');//http://scs.dxcdn.com/brand/brand_12.jpg
    }

    /**
     *生成品牌图片  零时使用
     *
     */
    public function index($isView='')
    {
        // $t = ini_get("max_execution_time");
        // echo $t;exit;
        ini_set('max_execution_time', '0');
        $server = 'scs.dxcdn.com';
        $port = '21990';
        $server_sbn = 'scs.dxcdn.com';
        $port_sbn = '21990';
        $user_name = 'Phoenixftp';
        $password = 'zQ4UXeoFJJ514hso';

        $conn_id = ftp_connect($server_sbn, $port_sbn);
        if (!$conn_id) {
        echo "Error: Could not connect to ftp. Please try again later.\n";
        return 100;
        }
        $login_result = ftp_login($conn_id, $user_name, $password);
        if (!$login_result) {
        echo "Error: Could not login to ftp. Please try again later.\n";
        return 101;
        }
        //SET FTP TO PASSIVE MODE
        $pasv_result = ftp_pasv($conn_id, TRUE);
        if (!$pasv_result) {
        return 102;
        }
        $font ="c:/windows/fonts/JDJHCU.TTF";
        $result = self::blandImg($conn_id,$font);
        // dump($result);


    }
    public function blandImg($conn_id,$font,$limit1 = 0,$limit2 = 50){
        ini_set('max_execution_time', '0');
        $Con = file_get_contents ( 'id and name.txt' );
// $Con = file_get_contents ( 'pingpai.txt' );
        $ConArr = explode ( "},", $Con );
        foreach ($ConArr as $key => $va) {ini_set('max_execution_time', '0');
            $data = explode ( ",", $va );
            $value["BrandId"] = $v = trim($data[0]);//dump($value);exit;
            echo $value["BrandId"];

            $img = self::file_exists(BRAND_IMG_URL.'brand_'.$value["BrandId"].'.jpg');
            // exit;
            if($img){
                file_put_contents ('cunzai.log',$value['BrandId'].';', FILE_APPEND|LOCK_EX);
                 // echo '存在';
                 continue;
            }
            // exit;
            //字体大小
            $size = 14;
            //字体类型，本例为宋体
            // $font ="c:/windows/fonts/simsun.ttc";
            // $font = './JDJHCU.TTF';
            //显示的文字
            $text = trim($data[1]);echo $text;
            $fontarea = ImageTTFBBox($size,0,$font,$text);//var_dump($fontarea);echo abs($fontarea[4] - $fontarea[3]).';';
            $fontWidth  =  abs($fontarea[4] - $fontarea[3]);//宽
            $fontHeight =  abs($fontarea[1] - $fontarea[0]);//宽
            $x = round((160 - $fontWidth)/2,3);//echo $x;exit;
            $y = round((50 - $fontHeight)/1.5,3);//echo $y;

            //创建一个长为500高为80的空白图片
            $img = imagecreate(160, 50);
            //给图片分配颜色
            imagecolorallocate($img, 255, 255, 255);


            //设置字体颜色
            $black = imagecolorallocate($img, 0, 0, 0);//echo ceil($leng/2) -$long;
            //将ttf文字写到图片中
            imagettftext($img, $size,0,$x,$y, $black, $font, $text);
            imagejpeg ($img );
            imagegif ( $img, "upload/img/brand/brand_".$value['BrandId'].".jpg" );

            self::makeDir($conn_id, "brand");
            $result = self::uploadFile($conn_id, "brand_".$value['BrandId'].".jpg", 'upload/img/brand/brand_'.$value["BrandId"].'.jpg');
            echo  $result.'----'.$value["BrandId"];
            if($result){
              file_put_contents ('chenggong.log',$value['BrandId'].';', FILE_APPEND|LOCK_EX);
            }else{
              file_put_contents ('shibai.log',$value['BrandId'].';', FILE_APPEND|LOCK_EX);
            }
 // exit;
        }
        // $limit1 = $limit2;
        // $limit2 = $limit2 + 20;
//         if($limit2 > 71){
// exit;
//         }
        // exit;
       ftp_close($conn_id);
       return ;
       // return self::blandImg($conn_id,$font,$limit1,$limit2);

    }
    //判断图片是否存在
    public function file_exists($url) {
      if( @fopen( $url, 'r' ) ){
          return true;
      }else{
          return false;
      }
    }
    // // //判断图片是否存在
    // public function file_exists($url) {
    //     ini_set('max_execution_time', '0');
    //     $curl = curl_init($url);
    //     // 不取回数据
    //     curl_setopt($curl, CURLOPT_NOBODY, true);
    //     // 发送请求
    //     $result = curl_exec($curl);echo $result.'<br/>';
    //     $found = false;
    //     // 如果请求没有发送失败
    //     if ($result !== false) {
    //         return true;
    //     // 再检查http响应码是否为200
    //     }else{
    //        return false;
    //     }
    // }

    public function aaaa(){
       $result = self::file_exists('http://scs.dxcdn.com/brand/brand_12.jpg');
       //$result = self::file_exists('http://scs.dxcdn.com/brand/brand_789.jpg');
       dump($result);
    }
    public  function uploadFile($connect, $romote_file, $local_file){
        $rtn = false;
         try {
           echo $local_file;
           $rtn = ftp_put($connect, $romote_file, $local_file, FTP_BINARY);
            // $rtn = ftp_put($connect, $romote_file, 'upload/img/brand/brand_33.jpg', FTP_BINARY);
           $connect = '';
         } catch (Exception $e){
             // echo $e->getMessage();
            file_put_contents ('shibai.log',$e->getMessage().'------------------------------;', FILE_APPEND|LOCK_EX);
         }

        // ftp_close($connect);
        return $rtn;
    }
    public function makeDir($connect, $dirPath){
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




}
