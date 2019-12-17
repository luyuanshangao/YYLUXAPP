<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/10/30
 * Time: 14:43
 */
namespace app\admin\controller;

use app\admin\model\EDMActivityModel;
use app\admin\model\Img;
use app\common\helpers\CommonLib;
use think\Exception;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use think\Log;

class Image extends Action
{
    public function __construct(){
        parent::__construct();
    }

    public function add(){
       return $this->fetch();
    }

    public function upload(){
        $new_imgs_host_config = config("new_imgs_host_config");
        $url=$new_imgs_host_config['SERVER_ADDRESS'].':'.$new_imgs_host_config['SERVER_PORT'].'/submit?collection=phoenix';
        $path_name = $_FILES['imgFile']['name'];
        $path = $_FILES['imgFile']['tmp_name'];
        $type=$_FILES['imgFile']['type'];
        $result=crul_submit($url,$path);
        //var_dump($result);
        $id=0;
        if(!empty($result['fileUrl'])&&!empty($result['fid'])){
            $img_type = substr($type,strripos($type,"/")+1);
            $fid=str_replace(',','/',$result['fid']);
            $url=$new_imgs_host_config['IMG_URl'].$fid.'/'.$path_name;
            //插入数据库
            $Img=new Img();
            $data1['url']=$url;
            $data1['fid']=$result['fid'];
            $data1['fileUrl']=$result['fileUrl'];
            $data1['username']=Session::get('username');
            $data1['add_time']=time();
            $res= $Img->save($data1);
           // var_dump($res);
            $id= $Img->id;
        }else{
            $url='';
        }
        $da['error']=0;
        $da['url']=$url;
        $da['id']=$id;
        return json($da);
    }

    public function index(){
        $page=input('page',1);
        $path ='/imgae/index';
        $Img=new Img();
        $page_size=20;
        $res=$Img->paginate($page_size,false,[
            'type' => 'Bootstrap',
            'page' => $page,
            'path' => $path,
            'query'=> []
        ]);

        $Page = $res->render();
        $data = $res->toArray();

        $data['total'] = $res->total();
        $this->assign(['orderList'=>$data['data'],
            'page'=>$Page,
            'total'=>$data['total'],
        ]);
        return $this->fetch();
    }

    public function del(){
        $id=input('id');
        $Img=new Img();
        $data= $Img->get($id);
        $data['result']='删除失败';
        if(!empty($data['fileUrl'])){
            $res=curl_del($data['fileUrl']);
            //插入数据库
            if(!empty($res)){
                $where['id']=$id;
                $Img->where($where)->delete();
                $data['result']='删除成功';
            }
        }
        $data['code']='200';
        return json($data);
    }


}