<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\mallextend\model\ProductClassModel as product_class_model;
use app\mallextend\model\ProductClassModel;
use app\mallextend\services\ProductClassService;
use app\mallextend\statics\BaseFunc;
use think\Exception;
use think\Log;

/**
 * 产品类别接口
 * Class ProductCategory
 * @author tinghu.liu 2018/3/20
 * @package app\seller\controller
 */
class ProductCategory extends Base
{

    public function __construct(){
        parent::__construct();
    }

    /**
     * 根据类别标题模糊匹配类别信息[用于产品类别快速选择，公用一个API]
     * 入参：search_content string
     * 返回数据格式：
     * {
            "code": 200,
            "data": [
                {
                "id": 5,
                "pid": 2,
                "title_cn": "华为p系列",
                "title_en": "HUAWEI  p10",
                "status": 1,
                "sort": 255,
                "title_cn_str": "手机>>华为手机1>>华为p系列",
                "title_en_str": "Mobile phone>>HUAWEI mobile phone>>HUAWEI  p10"
                }
            ],
            "message": "Success"
        }
     */
    public function searchByTitle()
    {
        $params = request()->post();
        $type = !isset($params['type']) ? 1 : $params['type'];//类型：1-按照英文名搜索【默认】，2-按照中文名搜索
        $class_type = !isset($params['class_type']) ? 1 : $params['class_type'];//类别类型：1-erp数据，2-pdc数据
        if (!isset($params['search_content']) || empty($params['search_content'])){
            return apiReturn(['code'=>1003]);
        }else{
            $data = array();
            $product_class_model = new product_class_model();
            $tmp_data = $product_class_model->getDataWithTitle($params['search_content'], $type, $class_type);
            //只要末级的数据
            foreach ($tmp_data as $k=>$info){
                $class_type_temp = isset($info['class_type']) ? $info['class_type'] : 1;
                $pdata = $product_class_model->getInfoWithIdForPID($info['id'],$class_type_temp);
                if (empty($pdata)){//如果此类别不是其他类别的父级，即是末级
                    $data[] = $tmp_data[$k];
                }
            }
            if (empty($data)){
                return apiReturn(['code'=>1006]);
            }else{
                //增加手机分类完全路径
                foreach ($data as &$val){ //拼接完整类别路径
                    $category_id = $val['id']; //类别ID
                    $str = BaseFunc::getCategoryStrWithID($category_id);
                    $val['title_cn_str'] = $str['title_cn_str'];
                    $val['title_en_str'] = $str['title_en_str'];
                }
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }
    }

    /**
     * 根据子[末级]分类ID获取分类完整数据【倒推】
     * @return array|mixed
     */
    public function getCategoryByID(){
        $params = request()->post();
        if (!isset($params['id'])){
            return apiReturn(['code'=>1003]);
        }else{
            $new_data = array();
            $data = array_reverse(BaseFunc::getCategoryInfoWithID($params['id']));
            //只要四级
            foreach ($data as $key=>$val){
                if ($key <= 3){
                    $new_data[] = $val;
                }
            }
            if (empty($new_data)){
                return apiReturn(['code'=>1006]);
            }else{
                return apiReturn(['code'=>200, 'data'=>$new_data]);
            }
        }
    }

    /**
     * 根据分类ID获取下一个子级
     * @return array|mixed
     */
    public function getNextCategoryByID(){
        $params = request()->post();
        if (!isset($params['id']) && empty($params['id'])){
            return apiReturn(['code'=>1003]);
        }else{
            //类别类型：1-erp数据，2-pdc数据
            $class_type = isset($params['class_type'])?$params['class_type']:1;
            $data = BaseFunc::getCategoryNextInfoWithID($params['id'], $class_type);
            if (empty($data)){
                return apiReturn(['code'=>1006]);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }
    }

    /**
     * 根据分类ID获取单条分类信息
     * @return mixed
     */
    public function getCategoryInfoByCategoryID(){
        $params = request()->post();
        if (!isset($params['id']) || empty($params['id'])){
            return apiReturn(['code'=>1003]);
        }else{
            $model = new product_class_model();
            $data = $model->getInfoWithId($params['id']);
            if (empty($data)){
                return apiReturn(['code'=>1006]);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }
    }

    /**
     * 根据分类ID数据获取对应分类信息
     * @return mixed
     */
    public function getCategoryDataByCategoryIDData($params = ''){
        $params = !empty($params)?$params:request()->post();
        if (!empty($params)){
            foreach ($params as $id){
                if (!is_numeric($id) || $id < 0 ){
                    return apiReturn(['code'=>1004, 'msg'=>'参数错误']);
                }
            }
            $model = new product_class_model();
            $data = $model->getDataWithIdArray($params);
            if (empty($data)){
                return apiReturn(['code'=>1006]);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'参数错误']);
        }
    }

    /**
     * 根据分类ID数据获取对应分类及它的上级中最先有映射关系的映射数据
     * @return mixed
     */
    public function getMapDataByCategoryID(){
        $params = request()->post();
        if (!empty($params)){
            $data = BaseFunc::getMapCategoryID($params['id']);
            if (empty($data)){
                return apiReturn(['code'=>1006]);
            }else{
                return apiReturn(['code'=>200, 'data'=>$data]);
            }
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'参数错误']);
        }
    }

    /**
     * 根据PID查询类别信息
     * @return mixed
     */
    public function getClassListByPid(){
        $params = input();
        if(!isset($params['access_token'])){
            return apiReturn(['code'=>1003,'msg'=>'access_token required']);
        }
        if (!isset($params['pid'])){
            return apiReturn(['code'=>1003,'msg'=>'pid required']);
        }
        try{
            $model = new product_class_model();
            $data = $model->selectClass($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据PID查询类别信息
     * @return mixed
     */
    public function getClassListByPidForSalesRank(){
        $params = input();
        if(!isset($params['access_token'])){
            return apiReturn(['code'=>1003,'msg'=>'access_token required']);
        }
        if (!isset($params['pid'])){
            return apiReturn(['code'=>1003,'msg'=>'pid required']);
        }
        try{
            $model = new product_class_model();
            $data = $model->selectClassForSr($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取分类信息，ids
     */
    public function  getCategoryByIDs(){
        $params = input();
        if (!isset($params['id'])){
            return apiReturn(['code'=>1003]);
        }
        try{
            $model = new product_class_model();
            $data = $model->getCategoryByIDs(['class_id'=>explode(',',$params['id'])]);
            if(!empty($data)){
                foreach($data as $key => $val){
                    if(!isset($val['rewritten_url'])){
                        $data[$key]['rewritten_url'] = '';
                    }
                    if(!isset($val['level'])){
                        $data[$key]['level'] = '';
                    }
                    if(!isset($val['id_path'])){
                        $data[$key]['id_path'] = '';
                    }
                    if(!isset($val['isleaf'])){
                        $data[$key]['isleaf'] = '';
                    }
                }
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    public function getErpClass(){
        $params = input();
        try{
            if (!isset($params['class_id']) || empty($params['class_id'])){
                return apiReturn(['code'=>1003]);
            }
            $data = (new ProductClassService())->getErpClass($params);
            if(empty($data)){
                $data = '';
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * erp新增产品接口对接
     * @return mixed
     */
    public function getClassList(){
        try{
            $params = input();
            $data = (new ProductClassService())->getClassLists($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }


    public function syncClassCommon(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductClassModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
//        $product_ids = $productModel->queryProduct916(['seller_id'=>888,'page'=>$param['page']]);
        $product_ids = $productModel->getClassListsCommon(['page'=>$param['page']]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateClassCommon($product_ids['data']);
        }
        $url = url('productCategory/syncClassCommon', ['page'=>$param['page']+1,'access_token'=>'123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    /**
     * 更新
     * 店铺id 888
     * skuid 916开头
     */
    public function updateClassCommon($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductClassModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['id']);
                if(isset($product_id['Common'])){
                    $ret = $productModel->updateClassCommon(['id'=>(int)$product_id['id']],['Common'=>$product_id['Common']]);
                    pr($ret);
                }
            }
        }
        pr('success');


    }

}
