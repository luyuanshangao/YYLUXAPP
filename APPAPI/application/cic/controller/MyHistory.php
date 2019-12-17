<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class MyHistory extends Base
{
    /*
* 获取用户浏览历史列表
* @param int CustomerID
* @Return: array
* */
    public function getHistoryList(){
        $paramData = request()->post();
        $where['customer_id'] = isset($paramData['customer_id'])?$paramData['customer_id']:0;
        if(empty($where['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        $where['customer_id'] = isset($paramData['customer_id'])?$paramData['customer_id']:'';
        $where = array_filter($where);
        $where['delete_time'] = 0;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $res = model("MyHistory")->getHistoryList($where,$page_size,$page,$path);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }



    /*
* 新增用户浏览历史
* */
    public function addHistory(){
        $data['customer_id'] = input("customer_id");
        if(empty($data['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        $data['spu'] = input("spu");
        $data['store_id'] = input("store_id");
        $data['add_time'] = time();
        $res = model("MyHistory")->addHistory($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 删除用户浏览历史
     * */
    public function delHistory(){
        $paramData = request()->post();
        if(empty($paramData['id'])){
            return apiReturn(['code'=>1001]);
        }
        $res = model("MyHistory")->delHistory($paramData['id']);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }
}
