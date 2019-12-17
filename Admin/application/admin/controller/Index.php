<?php
namespace app\admin\controller;
use \think\Session;
// use think\Controller as aa;
use think\View;
use think\Controller;
class Index  extends Action
{
     public function __construct(){
       Action::__construct();

    }
    public function home(){
       return view('home');
    }
    public function index()
    {
       $username = Session::get('username');
       if($username == 'admin' || $username == 'usadmin'){//超级管理员
           $list = Db('NavigationBar')->where(['parent_id'=>0,'status'=>1,])->order('sort ASC')->select();
           $list_subset = Db('NavigationBar')->where(['parent_id'=>$list[0]['id'],'status'=>1,])->order('sort ASC')->select();
           $list[0]['subset'] = $list_subset;
           foreach ($list[0]['subset'] as $key => $value) {
               $list_grandson = Db('NavigationBar')->where(['parent_id'=>$value['id'],'status'=>1,])->order('sort ASC')->select();
               $list[0]['subset'][$key]['grandson'] = $list_grandson;
           }
       }else{//普通管理员
           $User_data = Db('user')->where(['username'=>$username,'status'=>1,])->field('id,group_id')->find();
           $role = Db('navigation_role')->where(['id'=>$User_data['group_id'],'status'=>1,])->find();
           $data['parent_id'] = 0;
           $data['status']    = 1;
           $data['id']    = array('in',$role['power']);
           $list = Db('NavigationBar')->where($data)->order('sort ASC')->select();
           $data['parent_id'] = $list[0]['id'];
           $list_subset = Db('NavigationBar')->where($data)->order('sort ASC')->select();
           $list[0]['subset'] = $list_subset;
           foreach ($list[0]['subset'] as $key => $value) {
               $data['parent_id'] = $value['id'];
               $list_grandson = Db('NavigationBar')->where($data)->order('sort ASC')->select();
               $list[0]['subset'][$key]['grandson'] = $list_grandson;
           }
       }
       if($list){
           $this->assign('list',$list);
           $this->assign('username',$username);
       }
       return $this->fetch('index');

    }

    public function header(){
      $classid = request()->post();
      $username = Session::get('username');
      if($username == 'admin' || $username == 'usadmin'){
              $list = Db('NavigationBar')->where(['parent_id'=>$classid['id'],'status'=>1,])->order('sort ASC')->select();
              $html = '';
              foreach ($list as $key => $value) {
                   $list_subset = Db('NavigationBar')->where(['parent_id'=>$value['id'],'status'=>1,])->order('sort ASC')->select();
                   $html .= '<li class="start">';
                   $html .= '<a href="javascript:;">';
                   $html .= '<i class="fa fa-sitemap"></i>';
                   $html .= '<span class="title">'.$value["name"].'</span>';
                   $html .= '</a>';
                   $html .= '<ul class="sub-menu">';
                   foreach ($list_subset as $k => $v) {
                       $html .= '<li class="active">';
                       $html .= '<a href="../'.$v["url"].'/id/'.$v['id'].'"target="test" data-id="'.$v['id'].'" jerichotabindex="'.$v['id'].'">'.$v["name"].'</a>';
                       $html .= '</li>';
                   }
                   $html .= '</ul>';
                   $html .= '</li>';
              }
      }else{
              $User_data = Db('user')->where(['username'=>$username,'status'=>1,])->field('id,group_id')->find();
              $role = Db('navigation_role')->where(['id'=>$User_data['group_id'],'status'=>1,])->find();
              $data['parent_id'] = $classid['id'];
              $data['status']    = 1;
              $data['id']    = array('in',$role['power']);
              $list = Db('NavigationBar')->where($data)->order('sort ASC')->select();
              $html = '';
              foreach ($list as $key => $value) {
                   $data['parent_id'] = $value['id'];
                   $list_subset = Db('NavigationBar')->where($data)->order('sort ASC')->select();
                   $html .= '<li class="start">';
                   $html .= '<a href="javascript:;">';
                   $html .= '<i class="fa fa-sitemap"></i>';
                   $html .= '<span class="title">'.$value["name"].'</span>';
                   $html .= '</a>';
                   $html .= '<ul class="sub-menu">';
                   foreach ($list_subset as $k => $v) {
                       $html .= '<li class="active">';
                       $html .= '<a href="../'.$v["url"].'/id/'.$v['id'].'"target="test" data-id="'.$v['id'].'" jerichotabindex="'.$v['id'].'">'.$v["name"].'</a>';
                       $html .= '</li>';
                   }
                   $html .= '</ul>';
                   $html .= '</li>';
              }
      }

      echo json_encode(array('code'=>200,'result'=>$html));
    }
}
