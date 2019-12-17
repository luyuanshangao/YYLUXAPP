<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/10/31
 * Time: 14:15
 */
namespace app\app\controller;

use app\admin\model\Img as ImgModel;
use think\Controller;

class Img extends  Controller{
    public function del(){
        $url=input('url');
        $Img=new Img();
        $where['url']=$url;
        $data= $Img->get($where);
        $data['result']='删除失败';
        if(!empty($data['fileUrl'])){
            $res=curl_del($data['fileUrl']);
            //插入数据库
            if(!empty($res)){
                $Img->where($where)->delete();
                $data['result']='删除成功';
            }
        }
        $data['code']='200';
        return json($data);
    }

    public function upload(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('image');
        // 移动到框架应用根目录/public/uploads/ 目录下
        $upload_dir=config('upload_dir');
        if($file){
            $info = $file->move($upload_dir . 'user');
            if($info){
                //输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                $name=IMG_USER . 'user/'.$info->getSaveName();
                $data['name']=$name;
                $data['dir']='user/'.$info->getSaveName();;
                $this->result($data,200);
            }else{
                // 上传失败获取错误信息
                $msg=$file->getError();
                $this->result('',900,$msg);
            }
        }
    }

}