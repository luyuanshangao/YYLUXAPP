<?php
namespace app\admin\controller;

use app\admin\model\Region as region_model;

/**
 * 地区接口
 * Class Region
 * @author tinghu.liu 2018/3/19
 * @package app\seller\controller
 */
class Region
{
    /**
     * 根据地区上级ID获取地区信息
     */
    public function getRegionInfo()
    {
        $parent_id = input('parent_id/d');
        if (empty($parent_id)){
            return apiReturn(['code'=>1003]);
        }
        $region_model = new region_model();
        $data = $region_model->getDataWithParent_id($parent_id);
        $region_info = $region_model->getInfoWithRegion_id($parent_id);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'region_info'=>$region_info, 'data'=>$data]);
    }

    /**
     * 根据地区ID获取地区数据
     * @return mixed
     */
    public function getRegionInfoByRegionID(){
        $params = input();
        $region_id = $params['region_id'];
        if (empty($region_id)){
            return apiReturn(['code'=>1003]);
        }
        $region_model = new region_model();
        $region_info = $region_model->getInfoWithRegion_id($region_id);
        if (empty($region_info)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$region_info]);
    }
}
