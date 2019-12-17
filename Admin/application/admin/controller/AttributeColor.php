<?php
namespace app\admin\controller;

use think\Log;
use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use think\Paginator;

/**
 * 属性颜色管理
 * AddTime:2018-07-12
 * author: heng.zhang
 *
 */
class AttributeColor extends Action
{
  	public function __construct(){
         Action::__construct();
         //attribute_color 属性颜色表
         define('ATTRIBUTE_COLOR_TABLE', 'attribute_color');//mysql数据表
      }

    /**首页
     * @return \think\response\View
     */
    public function index(){
        $data = request()->post();
        if(!empty($data)){
            if(!empty($data['title_cn'])){
                $like_title_cn=$data['title_cn'];
            }
        }
        $page_size = config('paginate.list_rows');
        $result = Db::name(ATTRIBUTE_COLOR_TABLE)
                        ->whereLike('title_cn','%'.$like_title_cn.'%','OR')
                        ->whereLike('title_en','%'.$like_title_cn.'%','OR')
                        ->order('id','desc')
                        ->field('id,title_cn,title_en,color_value,add_time')
                        ->paginate($page_size);
        $list = $result->items();
        $this->assign(['list'=>$list,'page'=>$result->render()]);
        return view();
    }

    /**
     *删除
     */
    public function public_delete(){
        publicDelete(ATTRIBUTE_COLOR_TABLE);
    }

    /**
     *获取全部颜色数据
     */
    public function asyncGetAllColor(){
        $resultDB = Db::name(ATTRIBUTE_COLOR_TABLE)
                    ->where('status',1)
                    ->order('id','desc')
                    ->field('id,title_cn,title_en,color_value')
                    ->select();
            $data = array(
                'code'=>200,
                'html'=>$resultDB
            );
            echo json_encode($data);
            exit;
    }

    /**
     * 新增颜色
     */
    public function add(){
        $id = input('id');
        if(!empty($id)){
            $resultDB = Db::name(ATTRIBUTE_COLOR_TABLE)
                        ->where('id',$id)
                        ->field('id,title_cn,title_en,color_value')
                        ->find();
            $this->assign(['model'=>$resultDB]);
        }
        return view();
    }

    /**
     * 增加颜色数据
     */
    public function addPost(){
        $data = request()->post();
        if(!empty($data)){
            $id = input('id/d');
            $title_cn = trim($data['title_cn']);
            $title_en = trim($data['title_en']);
            $color_value = trim($data['color_value']);
            if(empty($title_cn)){
                return array('code'=>100,'result'=>'中文标题不可为空！');
            }
            if(empty($title_en)){
                return array('code'=>100,'result'=>'英文标题不可为空！');
            }
            $update['title_cn'] = $title_cn;
            $update['title_en'] = $title_en;
            $firstC = substr($color_value, 0, 1 );
            if($firstC ==='#'){

            }else{
                $color_value = '#'.$color_value;
            }
            $isTure=preg_match('/^[#0-9a-zA-Z]+$/',$color_value);
            if(!$isTure){
                return array('code'=>100,'result'=>'非法的颜色值2！');
            }
            if(!empty($color_value) && (strlen($color_value) > 7 || strlen($color_value)< 4)){
                return array('code'=>100,'result'=>'非法的颜色值3！'.$color_value);
            }
            $update['color_value'] = $color_value;
            $update['status'] = 1;
            if(empty($id)){

                $isRepeat = $this -> checkTitle_EN($title_en,$color_value);
                if($isRepeat){
                    return array('code'=>100,'result'=>'英文标题不可重复！');
                }
                $update['add_user'] = Session::get('username');
                $update['add_time'] = time();
                $result = Db::name(ATTRIBUTE_COLOR_TABLE)->insert($update);
            }else{
                $isRepeat = $this -> checkTitle_EN($title_en,$color_value,$id);
                if($isRepeat){
                    return array('code'=>100,'result'=>'英文标题不可重复！');
                }
                $update['edit_user'] = Session::get('username');
                $update['edit_time'] = time();
                $result = Db::name(ATTRIBUTE_COLOR_TABLE)->where('id',$id)->update($update);
            }
            if($result){
                return array('code'=>200,'result'=>'操作成功');
            }else{
                return array('code'=>100,'result'=>'操作失败');
            }
        }
        echo json_encode($data);
        exit;
    }

    /**
     * 检查标题名称--英文
     * return true 重复，false 不重复
     */
    private function checkTitle_EN($title_en,$color_value,$id=0){
        if(empty($title_en) || empty($color_value))
            return true;
        $where = [];
        if(!empty($id)){
            $where['id'] = array('<>',$id);
        }
        $where['title_en'] = $title_en;
        $where['color_value'] = $color_value;
        $resultDB = Db::name(ATTRIBUTE_COLOR_TABLE)
                        ->where($where)
                        ->count();
        return $resultDB>0;
    }

}