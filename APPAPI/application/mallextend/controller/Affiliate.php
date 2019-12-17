<?php
namespace app\mallextend\controller;
use app\common\controller\Base;


/**
 * Affiliate接口
 * Class Affiliate
 * @author kevin 2018/4/19
 * @package app\mallextend\Affiliate
 */
class Affiliate extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 获取Banner列表
     * */
    public function getBannerList(){
        $paramData = request()->post();
        $where = array();
        if(isset($paramData['Size'])){
            $where['Size'] =  (int)$paramData['Size'];
        }
        if(isset($paramData['Site'])){
            $where['Site'] =  (int)$paramData['Site'];
        }
        if(isset($paramData['Language'])){
            $where['Language'] =  $paramData['Language'];
        }
        if(isset($paramData['StartDate'])){
            $where['StartDate'] =  ['gt',(int)$paramData['StartDate']];
        }
        if(isset($paramData['EndDate'])){
            $where['EndDate'] =  ['lt',(int)$paramData['EndDate']];
        }
        $where['Status'] = 1;
        $where['StartDate'] = ['lt',time()];
        $where['EndDate'] = ['gt',time()];
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $order = isset($paramData['order'])?$paramData['order']:"_id desc";
        $order = explode(' ',$order);
        $res = model("Affiliate") -> getBannerList($where,$page_size,$page,$path,$order);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 获取Banner详情
     * */
    public function getBannerInfo(){
        $paramData = request()->post();
        $where = array();
        if(isset($paramData['_id'])){
            $where['_id'] =  (int)$paramData['_id'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        $res = model("Affiliate") -> getBannerInfo($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
}
