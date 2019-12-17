<?php
namespace app\admin\controller;
use \think\Session;
use \think\Controller;
use think\Request;
use \traits\controller\Jump;
use think\Db;
// load_trait('controller/Jump'); // 引入traits\controller\Jump
class Login  extends Action
{
     public function __construct(){
       Action::__construct();


    }


    public function index()
    {
        // $b = Db::table('User');
        // // Db('admin') -> insert($data)input('username')
        // $data = array(
        //         'username' => 'admin',
        //         'password' => md5('admin'),
        //     );

        // $result =  Db('user')-> insert($data);dump($result);exit;
        // $result = Db::execute('insert into dx_user (username, password ) values (1,2)');dump($result);
        // $result = $User-> insert($data);
        $this->fetch('Login');
         // $this->fetch('application/admin\view\login\Login.html');
    }
    /*后台登录
     * [login description]
     * @return [type] [description]
     */
    public function login(){
         $userData = request()->post();
         if (request()->isPost()){
             $data=[
                'username'=>$userData['username'],
                'password'=>md5($userData['password'])
             ];
             if($userData['username'] == 'admin'){//超级管理员
                 $result = Db('user')->where($data)->find();
                 if($result){
                      Session::set('group_id',$result['group_id']);
                      Session::set('username',$userData['username']);
                      Session::set('userid',$result['id']);
                      $username = Session::get('username');
                      return $this->redirect('admin/index/index');
                 }else{
                      return $this->error('登录失败', 'admin/index/index','',0,[]);
                 }
             }else{//普通管理员
                $result = Db('user')->where($data)->find();
                 if($result){
                     if($result['status']==0){
                         return $this->error('账号被禁用请联系管理员', 'admin/Login/index','',5,[]);
                     }
                      Session::set('group_id',$result['group_id']);
                      Session::set('username',$result['username']);
                      Session::set('userid',$result['id']);
                      $username = Session::get('username');
                      return $this->redirect('admin/index/index');
                      // return $this->success('登录成功', 'admin/index/index');
                 }else{
                      return $this->error('登录失败', 'admin/index/index','',0,[]);
                 }
             }
         }else{
            echo '不能提交空数据';
         }
    }

    /**
     * 后台退出
     */
    public function logout(){
         Session::delete('username');
         $name = Session::get('username');
         if(empty($name)){
            echo json_encode(array('code' =>200,'result' =>'退出成功'));
         }else{
            echo json_encode(array('code' =>100,'result' =>'退出失败'));
         }
    }
}
