<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use app\admin\dxcommon\BaseApi;
use think\Log;
use app\admin\dxcommon\FTPUpload;
use app\admin\model\Affiliate as AffiliateModel;
//use app\admin\controller\Tool;

/**
 * 员工管理
 */
class EmployeeManagement extends Action
{
	public function __construct(){
       Action::__construct();
       define('NAVIGATION_ROLE', 'navigation_role');
       define('ADMIN_USER', 'user');
       define('ADMIN_BAR', 'navigation_bar');
    }
    /**
     * 角色管理列表
     * [roleManagement description]
     * @return [type] [description]
     */
    public function roleManagement(){
        $page_size = config('paginate.list_rows');
        if($data = request()->post()){
            if($data["name"]){
               $where["name"] = array('like',$data["name"].'%');
            }
            $where["status"]  =  ['neq',3];
            $list = Db::name(NAVIGATION_ROLE)->where($where)->order('add_time desc')->paginate($page_size);
        }else{
           $list = Db::name(NAVIGATION_ROLE)->where(['status'=>['neq',3]])->order('add_time desc')->paginate($page_size);
        }
        $this->assign(['list'=>$list,'page'=>$list->render(),]);
        // dump($list);
        return View('roleManagement');
    }
    /**
     * 编辑员工资料
     * [editRole description]
     * @return [type] [description]
     * author: Wang 2018-08-15
     */
    public function editRole(){
       if($data = request()->post()){//dump($data);
         // $result_History = Db::connect("db_mongo")->name('dx_navigation_role')->insert($data_histroy);
         if($data['name']){
             $where['name'] = $data['name'];
         }else{
             echo json_encode(array('code'=>100,'result'=>'名称不能为空'));
             exit;
         }
         if($data['description']){
             $where['description'] = $data['description'];
         }else{
             echo json_encode(array('code'=>100,'result'=>'描述不能为空'));
             exit;
         }
         if($data['status'] || $data['status'] == 0){
             $where['status'] = $data['status'];
         }else{
             echo json_encode(array('code'=>100,'result'=>'状态必须选'));
             exit;
         }
         //存在id时为修改
         if($data['id']){
               $where['edit_author'] = Session::get('username');
               $where['edit_time']   = time();
               $result = Db::name(NAVIGATION_ROLE)->where(['id'=>$data["id"]])->update($where);
         }else{
               $navigation = Db::name(NAVIGATION_ROLE)->where(['name'=>$where['name']])->find();
               if($navigation){
                    echo json_encode(array('code'=>100,'result'=>'已添加过该分组'));
                    exit;
               }
               $where['add_time']   = time();
               $where['add_author'] = Session::get('username');
               $result = Db::name(NAVIGATION_ROLE)->insert($where);
         }
         if($result){
             echo  ajaxReturn(200,'数据更新成功');
             exit;
         }else{
             echo  ajaxReturn(100,'数据更新失败');
             exit;
         }
       }else{
         $id = input('id');
         if($id){
            $list = Db::name(NAVIGATION_ROLE)->where(['id'=>$id])->find();
         }
         $this->assign(['list'=>$list,]);
         return View('add_role');
       }
    }
    /**
     * 职员管理
     * [employeeManagement description]
     * @return [type] [description]
     * author: Wang 2018-08-15
     */
    public function employeeManagement(){
         if($data = request()->post()){
            $where["username"] = array('like','%'.$data["username"].'%');
         }
         $where["status"] = array('neq',2);
         $list = Db::name(ADMIN_USER)->where($where)->order('add_time desc')->paginate();
         $this->assign(['list'=>$list,'page'=>$list->render(),]);
         return View('employee');
    }
    /**
     * 编辑职员
     * [editEmployee description]
     * @return [type] [description]
     * author: Wang 2018-08-15
     */
    public function editEmployee(){
          if($data = request()->post()){
               if($data['role']){
                    $where['group_id'] = $data['role'];
               }else{
                    echo  ajaxReturn(100,'请选择用户角色');
                    exit;
               }
               if($data["username"]){
                    $where['username'] = $data['username'];
               }else{
                    echo  ajaxReturn(100,'请填写用户名');
                    exit;
               }
               if(empty($data["id"])){
                        if(!$data["password"]){
                          echo  ajaxReturn(100,'请填写密码');
                          exit;
                        }else if(!$data["confirmPassword"]){
                          echo  ajaxReturn(100,'请填确认密码');
                          exit;
                        }else if($data["password"] != $data["confirmPassword"]){
                          echo  ajaxReturn(100,'密码与确认密码不一致');
                          exit;
                        }
                        $where['password'] = md5($data["password"]);
               }else{
                        //用于修改
                        if(!empty($data["password"]) && !empty($data["confirmPassword"])){
                           if($data["password"] != $data["confirmPassword"]){
                               echo  ajaxReturn(100,'密码与确认密码不一致');
                               exit;
                           }else{
                               $where['password'] = md5($data["password"]);
                           }
                        }else if(empty($data["password"]) && empty($data["confirmPassword"])){
                           //如果同时为空则留空
                        }else{
                             echo  ajaxReturn(100,'密码与确认必须一致');
                             exit;
                        }
               }
               if($data["description"]){
                     $where['description'] = $data["description"];
               }
               if($data["status"] || $data["status"] == 0){
                     $where['status'] = $data["status"];
               }
              //id为空则为添加否则为修改
              if(empty($data["id"])){
                   $AdminUserName = Db::name(ADMIN_USER)->where(['username'=>$where['username']])->find();
                   if($AdminUserName){
                       echo  ajaxReturn(100,'该名称已添加过');
                       exit;
                   }
                   $where['add_author'] = Session::get('username');
                   $where['add_time']   = time();
                   $result = Db::name(ADMIN_USER)->insert($where);
              }else{
                   $where['edit_author'] = Session::get('username');
                   $where['edit_time']   = time();
                   $result = Db::name(ADMIN_USER)->where(['id'=>$data["id"]])->update($where);
              }
              if($result){
                   echo  ajaxReturn(200,'数据提交成功');
                   exit;
              }else{
                   echo  ajaxReturn(100,'数据提交失败');
                   exit;
              }
          }else{
            $id = input('id');
            $listRole = Db::name(NAVIGATION_ROLE)->select();
            if($id){
              $list = Db::name(ADMIN_USER)->where(['id'=>$id])->find();
            }
            $this->assign(['listRole'=>$listRole,'list'=>$list]);
            return View('add_employee');
          }
    }

    /**
     * 编辑职员
     * [editEmployee description]
     * @return [type] [description]
     * author: Wang 2018-08-15
     */
    public function editEmployeeheader(){
        if($data = request()->post()){
            if($data["username"]){
                $where['username'] = $data['username'];
            }else{
                echo  ajaxReturn(100,'请填写用户名');
                exit;
            }
            if(empty($data["id"])){
                if(!$data["password"]){
                    echo  ajaxReturn(100,'请填写密码');
                    exit;
                }else if(!$data["confirmPassword"]){
                    echo  ajaxReturn(100,'请填确认密码');
                    exit;
                }else if($data["password"] != $data["confirmPassword"]){
                    echo  ajaxReturn(100,'密码与确认密码不一致');
                    exit;
                }
                $where['password'] = md5($data["password"]);
            }else{
                //用于修改
                if(!empty($data["password"]) && !empty($data["confirmPassword"])){
                    if($data["password"] != $data["confirmPassword"]){
                        echo  ajaxReturn(100,'密码与确认密码不一致');
                        exit;
                    }else{
                        $where['password'] = md5($data["password"]);
                    }
                }else if(empty($data["password"]) && empty($data["confirmPassword"])){
                    //如果同时为空则留空
                }else{
                    echo  ajaxReturn(100,'密码与确认必须一致');
                    exit;
                }
            }
            if($data["description"]){
                $where['description'] = $data["description"];
            }
            if($data["status"] || $data["status"] == 0){
                $where['status'] = $data["status"];
            }
            //id为空则为添加否则为修改
            if(empty($data["id"])){
                $AdminUserName = Db::name(ADMIN_USER)->where(['username'=>$where['username']])->find();
                if($AdminUserName){
                    echo  ajaxReturn(100,'该名称已添加过');
                    exit;
                }
                $where['add_author'] = Session::get('username');
                $where['add_time']   = time();
                $result = Db::name(ADMIN_USER)->insert($where);
            }else{
                $where['edit_author'] = Session::get('username');
                $where['edit_time']   = time();
                $result = Db::name(ADMIN_USER)->where(['id'=>$data["id"]])->update($where);
            }
            if($result){
                echo  ajaxReturn(200,'数据提交成功');
                exit;
            }else{
                echo  ajaxReturn(100,'数据提交失败');
                exit;
            }
        }else{
            $id = input('id');
            $listRole = Db::name(NAVIGATION_ROLE)->select();
            if($id){
                $list = Db::name(ADMIN_USER)->where(['id'=>$id])->find();
            }
            $this->assign(['listRole'=>$listRole,'list'=>$list]);
            return View('add_employeeheader');
        }
    }

    //权限选择
    public function EditPermissions(){
           if($data = request()->post()){
                if(!$data['id']){
                   echo  ajaxReturn(100,'获取对应id失败请刷新重试');
                   exit;
                }
                $where['power'] = implode(',',$data['where']);
                $result = Db::name(NAVIGATION_ROLE)->where(['id'=>$data["id"]])->update($where);
                if($result){
                   echo  ajaxReturn(200,'数据提交成功');
                   exit;
                }else{
                   echo  ajaxReturn(100,'数据提交失败');
                   exit;
                }
           }else{
                $id = input('id');
                $list =  $this->NavigationRecursion();
                $navigation_role = Db::name(NAVIGATION_ROLE)->where(['id'=>$id])->field('power')->find();
                $navigation_data = explode(',',$navigation_role['power']);
                // dump($navigation_data);
                // dump(in_array("1",$navigation_data, true));
                $this->assign(['list'=>$list,'id'=>$id,'navigation_data'=>$navigation_data]);
                return View('edit_permissions');
           }
    }
    //导航递归
    public function NavigationRecursion($id = 0,$data = array()){
        if($id == 0){
           $data = Db::name(ADMIN_BAR)->where(['parent_id'=>$id])->select();
           if($data){
                foreach ($data as $ke => $ve) {
                   $subset = $this->NavigationRecursion($ve['id']);//dump($subset);
                   if($subset){
                       $data[$ke]['subset'] = $subset;
                       $subset = array();
                   }
                }
           }else{
                // return $data;
           }
        }else{
             $data = Db::name(ADMIN_BAR)->where(['parent_id'=>$id])->select();
             if($data){
                   foreach ($data as $k => $v) {
                       $subset = $this->NavigationRecursion($v['id']);
                       if($subset){
                          $data[$k]['subset'] = $subset;
                          $subset = array();
                       }
                   }
             }else{
                // return $data;
             }
        }
        return $data;
    }
    /**
     * 删除会员
     * [del description]
     * @return [type] [description]
     */
    public function del(){
        if($data = request()->post()){
             if(!empty($data['id'])){
                if($data['id'] == 7){
                   echo  ajaxReturn(100,'超级管理员不允许删除');
                   exit;
                }
                $where['status'] = 2;
                $result = Db::name(ADMIN_USER)->where(['id'=>$data["id"]])->update($where);
                if($result){
                     echo  ajaxReturn(200,'数据删除成功');
                     exit;
                }else{
                     echo  ajaxReturn(100,'数据删除失败');
                     exit;
                }
             }else{
                echo  ajaxReturn(100,'数据传递有误');
                exit;
             }
        }
    }
    /**
     * 删除角色
     * [delete_role description]
     * @return [type] [description]
     * @author: Wang addtime 2018-11-19
     */
    public function delete_role(){
         if($data = request()->post()){
              if(!empty($data['id'])){
                   if($data['id'] == 7){
                     echo  ajaxReturn(100,'超级管理员不允许删除');
                     exit;
                   }
                   $where['status'] = 3;
                   $result = Db::name(NAVIGATION_ROLE)->where(['id'=>$data["id"]])->update($where);
                   if($result){
                       $result_user = Db::name(ADMIN_USER)->where(['group_id'=>$data["id"]])->update(['status'=>2]);
                       if($result_user){
                            echo  ajaxReturn(200,'数据删除成功');
                            exit;
                       }else{
                            echo  ajaxReturn(100,'对应会员数据删除失败');
                            exit;
                       }

                   }else{
                       echo  ajaxReturn(100,'数据删除失败');
                       exit;
                   }
              }else{
                echo  ajaxReturn(100,'数据传递有误');
                exit;
             }
         }
    }
    //会员状态修改
    // public function user_status(){
    //     if($data = request()->post()){

    //     }
    // }
    //获取子集导航
    // public function subset($id){
    //    $list = Db::name(ADMIN_BAR)->where(['parent_id'=>$id])->select();
    //    return $list;
    // }

}