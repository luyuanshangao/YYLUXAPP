<?php
namespace app\admin\controller;
use \think\Session;
// use think\Controller as aa;
use think\View;
use think\Controller;


class Epp  extends Action
{
     public function __construct(){
       Action::__construct();


    }
    public function index()
    {

       if(Session::get('username') == 'admin'){//超级管理员
           $result = Db('NavigationBar')->where(['parent_id'=>0,'status'=>1,])->select();
       }else{//普通管理员

       }

       $this->assign('name','121212');
       $this->assign(['list'=>$result,'email'=>'121212']);
       return view('application/admin\view\iusinessManagement\index.html');
       // $this->fetch('application/admin\view\Index\index.html');

    }

    public function header(){
       $this->assign(['name'=>'121212']);
       return view('application/admin\view\public\header.shtml');
    }
}
