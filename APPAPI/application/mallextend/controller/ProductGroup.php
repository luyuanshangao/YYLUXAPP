<?php
namespace app\mallextend\controller;
use app\common\controller\Base;
use think\Exception;

/**
 * 产品分组接口
 * Class ProductGroup
 * @author tinghu.liu 2018/4/1
 * @package app\seller\controller
 */
class ProductGroup extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 获取产品列表
     * */
    public function getGroup(){
        $paramData = request()->post();
        if(!isset($paramData['user_id'])){
            return apiReturn(['code'=>1001]);
        }
        $where['user_id'] =  (int)$paramData['user_id'];
        $res = model("ProductGroup") -> getGroup($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 判断是否存在产品分组
     * */
    public function hasGroup(){
        $paramData = request()->post();
        if(isset($paramData['user_id'])){
            $where['user_id'] = (int)$paramData['user_id'];
        }
        if(isset($paramData['group_name'])){
            $where['group_name'] = $paramData['group_name'];
        }
        if(isset($paramData['group_id'])){
            $where['_id'] = (int)$paramData['group_id'];
        }

        if(empty($where)){
            return apiReturn(['code'=>1001]);
        }
        $res = model("ProductGroup") -> hasGroup($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 添加产品分组
     * */
    public function addGroup(){
        $paramData = request()->post();
        if(!isset($paramData['group_name']) || !isset($paramData['user_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['user_id'] = $paramData['user_id'];
        $data['group_name'] = $paramData['group_name'];
        $data['parent_id'] = isset($paramData['parent_id'])?$paramData['parent_id']:0;
        $data['store_open'] = isset($paramData['store_open'])?$paramData['store_open']:1;
        $res = model("ProductGroup") -> saveGroup($data);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 修改产品分组
     * */
    public function editGroup(){
        $paramData = request()->post();
        if(empty($paramData['group_id'])){
            return apiReturn(['code'=>1001]);
        }

        if(isset($paramData['group_name'])){
            $data['group_name'] = $paramData['group_name'];
        }
        if(isset($paramData['store_open'])){
            $data['store_open'] = $paramData['store_open'];
        }
        if(is_array($paramData['group_id'])){
            foreach ($paramData['group_id'] as $k=>$v){
                $paramData['group_id'] = (int)$v;
            }
        }else{
            $paramData['group_id'] = [(int)$paramData['group_id']];
        }
        $where['_id'] = ['in',$paramData['group_id']];
        $res = model("ProductGroup") -> saveGroup($data,$where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 删除产品分组
     * */
    public function delGroup(){
        $paramData = request()->post();
        if(empty($paramData['group_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(is_array($paramData['group_id'])){
            foreach ($paramData['group_id'] as $k=>$v){
                $paramData['group_id'] = (int)$v;
            }
        }else{
            $paramData['group_id'] = [(int)$paramData['group_id']];
        }
        $where['_id'] = ['in',$paramData['group_id']];
        $data['deletetime'] = time();
        $res = model("ProductGroup") -> saveGroup($data,$where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 获取产品组名称
     * */
    public function getGroupNmae(){
        $paramData = request()->post();
        if(empty($paramData['group_id'])){
            return apiReturn(['code'=>1001]);
        }
        $where['_id'] = (int)$paramData['group_id'];
        $res = model("ProductGroup") -> getGroupName($where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }

    }



    public function getGroupList(){
        try{
            $params = input();
            if(!isset($params['seller_id'])){
                return apiReturn(['code'=>1001]);
            }
            $res = (new \app\mallextend\model\ProductGroup())->getGroupList($params);
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }

    }
}
