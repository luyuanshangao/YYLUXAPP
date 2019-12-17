<?php
namespace app\mall\services;

use app\mall\controller\Product;
use app\mall\model\NocModel;
use app\mall\model\NocModelModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductModel;
use think\Cache;
use think\Log;


/**
 * NOC相关业务数据接口
 */
class NocService extends BaseService
{

    /**
     * 获取NOC类别映射数据
     * @return bool
     */
    public function getNocClassMap(){
        $nocModel = new NocModel();
        $result = array();
        //判断缓存
        if(config('cache_switch_on')){
            $result = $this->redis->get(DX_NOC_CLASS_MAP_KEY);
        }
        if(empty($result)){
            $result = $nocModel->getNocClassMap();
            if(!empty($result)){
                $this->redis->set(DX_NOC_CLASS_MAP_KEY, $result , CACHE_DAY);
            }
        }
        return $result;
    }

    /**
     * 获取nocnoc分类类别
     * 20180927修改：类别不用取dx_nocnoc_class表数据，如果是历史产品数据，则需要根据末级类别找到对应ERP的二级类别传过去；若非历史数据，则直接取产品二级类别即可。【默认使用740类别】
     * @param array $params
     * @return int|mixed
     */
    public function getNocClass(array $params){
        $class_id = 740;
        $class_cache_id = null;
        $product_id = $params['product_id'];
        $class_id_cache_key = 'DX_NOC_CLASS_ID_KEY'.$product_id;
        //判断缓存
        if(config('cache_switch_on')){
            $class_cache_id = $this->redis->get($class_id_cache_key);
        }
        if(empty($class_cache_id)){
            $model = new ProductModel();
            $class_model = new ProductClassModel();
            $spu_info = $model->findProduct($params);
            if (!empty($spu_info)){
                $is_history = isset($spu_info['IsHistory'])?$spu_info['IsHistory']:0;
                $category_path_arr = explode('-', $spu_info['CategoryPath']);
                $key = count($category_path_arr) - 1;
                //20181105 直接取产品的末级分类
                $class_id = $category_path_arr[$key];
                switch ($is_history){
                    case 0:
                        //非历史数据，则直接取产品二级类别即可
                        //$class_id = $spu_info['SecondCategory'];

                        //20181105 直接取产品的末级分类
                        //$class_id = $category_path_arr[$key];
                        break;
                    case 1:
                        /** 是历史产品数据，则需要根据末级类别找到对应ERP的二级类别 **/
                        //$category_path_arr = explode('-', $spu_info['CategoryPath']);
                        //$key = count($category_path_arr) - 1;
                        //获取PDC末级类别对应的erp类别
//                        $pdc_data = $class_model->getClassDetail(['id'=>(int)$category_path_arr[$key]]);
//                        //根据erp类别找到对应的二级分类
//                        $erp_data = $class_model->getClassDetail(['id'=>(int)$pdc_data['pdc_ids'][0]]);
//                        $erp_id_arr = explode('-', $erp_data['id_path']);
//                        if (isset($erp_id_arr[1])){
//                            $class_id = $erp_id_arr[1];
//                        }


                        /**************
                         * 20181105修改
                         * 如果是历史数据，根据对应的末级分类匹配对应的ERP分类，如果不存在，再匹配上一级，如果上级不存在继续匹配，以此类推，如果都不存在，则默认值（740）
                         *****************/
                        $_relative_class_id = $class_id;
                        cycle:
                        //获取末级分类对应的PDC类别
                        $pdc_data = $class_model->getClassDetail(['id'=>(int)$_relative_class_id]);
                        //根据PDC类别找到对应的ERP分类数据
                        $erp_data = $class_model->getClassDetail(['id'=>(int)$pdc_data['pdc_ids'][0]]);
                        while (empty($erp_data)){//如果没有对应的erp分类
                            //如果存在类别
                            if ($key>=0){
                                $key--; //找到上一级类别
                                $_relative_class_id = $category_path_arr[$key];
                                goto cycle;
                            }else{
                                //没有找到则跳出循环，class id为默认设置的740
                                break;
                            }
                        }
                        //将分类ID设置为对应的ERP分类ID
                        $class_id = isset($erp_data['id'])?$erp_data['id']:740;
                        break;
                    default:break;
                }
                $this->redis->set($class_id_cache_key, $class_id , CACHE_DAY);
            }else{
                Log::record('nocnoc Class , getNocClass, findProduct is null , $params ('.json_encode($params).')');
            }
        }else{
            $class_id = $class_cache_id;
        }
        return $class_id;
    }

}
