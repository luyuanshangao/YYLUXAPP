<?php
namespace app\admin\controller;
use think\Controller;
use \think\Session;
use think\Db;
use think\Request;
use  app\admin\dxcommon\Auth;

//共用控制器
class Action extends controller
{
    /**
     * 权限控制类
     * @var Auth
     */
    protected $auth = null;
    /**
     * 无需鉴权的方法
     * 小写字母
     * @var array
     */
    protected $noNeedRight = [];

    public function _initialize(){
          //echo request()->controller();exit;
           $username = Session::get('username');

           //判断是否有登录
           if(!empty($username)){
               if($username == 'admin'){//超级管理员

               }else{//普通管理员
                    $this->auth();
               }
           }else{
                // $url = $_SERVER["HTTP_HOST"].url('admin/login/index');
                // echo "window.location.href='login/index'";
               $controller = strtolower(Request::instance()->controller());
                if(strpos($_SERVER['PHP_SELF'],'login')===false && $controller!='login'){
                    Header("HTTP/1.1 303 See Other");
                    Header("Location: ".url('admin/Login/index'));
                }
           }

        $this->Mongo = Db::connect("db_mongo");
        $this->NavigationBar = Db('NavigationBar');
        define('P_CLASS', 'ProductClass');
        define('PRO_CLASS', 'dx_product_class');//Nosql数据表
        define('S_CONFIG', 'dx_sys_config');//Nosql数据表
        $this->assign('cdn_base_url', config('cdn_photo_url_config'));
    }

    /*
    * 进行鉴权
    */
    public function auth(){
        // 判断是否需要验证权限
        $modulename = $this->request->module();
        $controllername = strtolower($this->request->controller());
        $actionname = strtolower($this->request->action());
        //不支持模块 '/' . $modulename .
        $path = str_replace('.', '/', $controllername) . '/' . $actionname;
        $this->auth = new Auth();

        if (!$this->auth->match($this->noNeedRight))
        {
            $userid = Session::get('userid');
            // 判断控制器和方法判断是否有对应权限
            if (!$this->auth->check($path,$userid))
            {
                $this->error('你没有权限访问', 'index/index');
            }
        }
    }

    /*
     * 获取当前url
     */
    public function getPath(){

    }

    /**
     * 菜单指标
     */
    public function Menu_logo(){
       $id = input('id');
       if(!is_numeric ($id)){
           $id = input('Navigation');
       }
       $val_third  = $this->NavigationBar->where(['id'=>$id,'status'=>1,])->field('name,parent_id')->find();
       $val_second = $this->NavigationBar->where(['id'=>$val_third['parent_id'],'status'=>1,])->field('name,parent_id')->find();
       $val_first  = $this->NavigationBar->where(['id'=>$val_second['parent_id'],'status'=>1,])->field('name,parent_id')->find();
       $this->assign(['menu_logo'=>$val_first['name'].'>'.$val_second['name'].'>'.$val_third['name'],'Navigation'=>$id]);
    }
     //字典数据的获取
    public function dictionariesQuery($val){
          $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
          $data = explode(";",$PayemtMethod['ConfigValue']);
        $list = array();
          foreach ($data as $key => $value) {
             if(!empty($value)){
                 $list[] = explode(":",$value);
             }

          }
          return $list;
    }

    /**
     * 获取配置
     * @param $val
     * @return mixed
     */
    public function getSysConfig($val){
        $data=(Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find());
        return $data['ConfigValue'];
    }

    /**
    * 获取下一级分类nomql 和mysql
    */
   public function catalogNext($sql=''){
     $id          = input('id');//P_CLASS
     $val         = input('class_level') + 1;
     $select_data = array(
              '1'=>'second_level'.$sql,
              '2'=>'third_level'.$sql,
              '3'=>'fourth_level'.$sql,
              '4'=>'fifth_level'.$sql,
          );
     if($sql != ''){
       $list  = Db::connect("db_mongo")->name(PRO_CLASS)->where(['pid'=>(int)$id])->select();
     }else{
       $Pclass = Db::name(P_CLASS);
       $list  = $Pclass->where(['pid'=>$id])->select();
     }
     if(!$list){
        return;
     }
     $html = '';
     $html .= ' <select id="'.$select_data[$val].'" name="'.$select_data[$val].'" class="form-control input-small inline">';
     $html .= '<option value ="">请选择</option>';
     foreach ($list as $key=>$value){
        $html .= '<option value ="'.$value['id'].'">'.$value['title_en'].'</option>';
     }
     $html .= '</select>';
     echo $html;
     exit;
  }

}
