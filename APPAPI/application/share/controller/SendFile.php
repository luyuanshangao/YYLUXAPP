<?php
namespace app\share\controller;
use app\common\helpers\RedisClusterBase;
use app\common\controller\Base;
use think\Log;
/**
 *获取配置
 */
class SendFile extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    public function sendStreamFile(){
        $redis = new RedisClusterBase();
        $length = $redis->lLen('seller_product_image');
        if($length>0){
            $url=config('base_seller_url').'/task/Crontab/receiveStreamFile';
            for($i=0;$i<$length;$i++){
                $param =json_decode($redis->LPOP('seller_product_image'),true);
                log::record('图像转移队列数据：'.print_r($param, true));
                if(file_exists($param['file'])){ 
                    $data = array( 
                      'content' => base64_encode(file_get_contents($param['file'])),
                      'name'=>$param['file_name'],
                      'date'=>$param['date']
                    );
                    log::record('图像转移请求数据：'.print_r($data, true));
                    $ch = curl_init();//初始化一个cURL会话
                    curl_setopt($ch,CURLOPT_URL, $url);//抓取url
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//这个是重点。

                    //设置请求方式是post方式
                    curl_setopt($ch,CURLOPT_POST,1);
                    //设置post请求提交的表单信息
                    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
                    $response= curl_exec($ch);// 执行一个cURL会话
                    $error = curl_error($ch);//返回一条最近一次cURL操作明确的文本的错误信息。
                    if($response){
                        @unlink($param['file']);
                    }else{
                        $redis->lPush( 'seller_product_image',json_encode($param));
                    }
                    if($response){
                        @unlink($param['file']);
                    }else{
                        $redis->lPush( 'seller_product_image',json_encode($param));
                    }
                }
            } 
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'暂无数据']);
        }
    }   
      
}
