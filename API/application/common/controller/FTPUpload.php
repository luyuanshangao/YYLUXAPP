<?php
namespace app\common\controller;

use think\Controller;

/**
 * FTP上传类：将产品图片通过FTP方式同步到CDN服务器
 * Class FTPUpload
 * @author tinghu.liu
 * @date 2018-03-29
 * @package app\index\dxcommon
 */
class FTPUpload extends Controller
{
    /**
     * ftp链接远程服务器
     * @return resource
     */
    private static function initFTP(){
        $server = config('ftp_config.DX_FTP_SERVER_ADDRESS');
        $port = config('ftp_config.DX_FTP_SERVER_PORT');
        $server_sbn = config('ftp_config.SBN_FTP_SERVER_ADDRESS');
        $port_sbn = config('ftp_config.SBN_FTP_SERVER_PORT');
        $user_name = config('ftp_config.DX_FTP_USER_NAME');
        $password = config('ftp_config.DX_FTP_USER_PSD');
        $conn_id = ftp_connect($server_sbn, $port_sbn);
        if (!$conn_id) {
            //echo "Error: Could not connect to ftp. Please try again later.\n";
            return 100;
        }
        $login_result = ftp_login($conn_id, $user_name, $password);
        if (!$login_result) {
            //echo "Error: Could not login to ftp. Please try again later.\n";
            return 101;
        }
        //SET FTP TO PASSIVE MODE
        $pasv_result = ftp_pasv($conn_id, TRUE);
        if (!$pasv_result) {
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
    public static function data_put(array $config){

        $connect =  self::initFTP();
        self::makeDir($connect, $config['dirPath']);
        //上传到远程服务器
        return self::uploadFile($connect, $config['romote_file'], $config['local_file']);
    }

}
