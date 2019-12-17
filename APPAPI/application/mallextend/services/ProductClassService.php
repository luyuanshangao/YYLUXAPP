<?php
namespace app\mallextend\services;

use app\common\helpers\CommonLib;
use app\mallextend\model\ProductClassModel;
use think\Cache;
use think\Exception;


/**
 * 开发：钟宁
 * 功能：产品分类业务层
 * 时间：2018-07-09
 */
class ProductClassService
{

    private $model;
    public function __construct(){

        $this->model = new ProductClassModel();
    }

    public function getClassListBySpu(){

    }

    public function getErpClass($params){
        $data = array();
        $classData = $this->model->getCategoryByIDs($params);
        if(!empty($classData)){
            foreach($classData as $key => $class){
                if($class['type'] != 1){
                    if(!empty($class['pdc_ids'])){
                        $erpClass = $this->model->getClassDetail(['id'=>(int)$class['pdc_ids'][0]]);
                        $data[$key]['ClassId'] = $class['id'];
                        $data[$key]['MapClass']['id'] = $erpClass['id'];
                        $data[$key]['MapClass']['pid'] = $erpClass['pid'];
                        $data[$key]['MapClass']['title_en'] = $erpClass['title_en'];
                        $data[$key]['MapClass']['HSCode'] = $erpClass['HSCode'];
                        $data[$key]['MapClass']['declare_en'] = $erpClass['declare_en'];
                        $data[$key]['MapClass']['id_path'] = $erpClass['id_path'];
                    }else{
                        $data[$key]['ClassId'] = $class['id'];
                        $data[$key]['MapClass'] = '';
                    }
                }else{
                    $data[$key]['ClassId'] = $class['id'];
                    $data[$key]['MapClass']['id'] = $class['id'];
                    $data[$key]['MapClass']['pid'] = $class['pid'];
                    $data[$key]['MapClass']['title_en'] = $class['title_en'];
                    $data[$key]['MapClass']['HSCode'] = $class['HSCode'];
                    $data[$key]['MapClass']['declare_en'] = $class['declare_en'];
                    $data[$key]['MapClass']['id_path'] = $class['id_path'];
                }
            }
        }
        return $data;
    }

    public function getClassLists($params){
        $params['type'] = 1;
        $classData = $this->model->getClassLists($params);
//        if(!empty($classData)){
//            foreach($classData as $key => $class){
//
//            }
//        }
        return $classData;
    }
}
