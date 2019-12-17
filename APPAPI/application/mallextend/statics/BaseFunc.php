<?php
namespace app\mallextend\statics;
use app\mallextend\model\ProductClassModel as product_class_model;
use think\Log;

/**
 * 类别
 * @author
 * @version tinghu.liu 2018/3/20
 */
class BaseFunc {
    /**
     * 根据分类ID获取相应完整路径
     * @param $category_id 分类ID
     * @param array $data
     * @return array
     */
    public static function getCategoryStrWithID($category_id, $data=array('title_en_str'=>'','title_cn_str'=>'')){
        $product_class_model = new product_class_model();
        $cinfo = $product_class_model->getInfoWithId($category_id);
        $pid = $cinfo['pid'];
        $data['title_cn_str'] .= $cinfo['title_cn'].'>>';
        $data['title_en_str'] .= $cinfo['title_en'].'>>';
        //是否有父级
        if ($pid != 0){ //有父级
            return self::getCategoryStrWithID($pid, $data);
        }
        //将类别按照大类往低类排序
        $data['title_cn_str'] = self::handleCategoryData($data['title_cn_str'],'>>');
        $data['title_en_str'] = self::handleCategoryData($data['title_en_str'],'>>');
        return $data;
    }

    /**
     * 处理类别数据【将类别按照大类往低类排序】
     * @param $data
     * @return string
     */
    public static function handleCategoryData($data,$delimiter){
        $rtn = [];
        $title = explode($delimiter, $data);
        foreach ($title as $key=>$cninfo){//去掉为空的数据
            if (empty($title[$key])) {
                unset($title[$key]);
            }
        }
        for ($i=count($title)-1;$i>=0;$i--){//数组倒叙
            $rtn[] =  $title[$i];
        }
        //只返回四级
        $rtn_new = array();
        foreach ($rtn as $key=>$val){
            if ($key <= 3){
                $rtn_new[] = $val;
            }
        }
        return implode('>>', $rtn_new);
    }

    /**
     * 根据分类ID获取相应分类信息[根据子级获取完整类别]
     * @param $category_id 子级分类ID
     * @return array
     */
    public static function getCategoryInfoWithID($category_id, $data=[]){
        $product_class_model = new product_class_model();
        $cinfo = $product_class_model->getInfoWithId($category_id);
        $pid = $cinfo['pid'];
        $class_type = isset($cinfo['type'])?$cinfo['type']:1;
        $parent_data = $product_class_model->getInfoWithIdForPID($pid, $class_type);
        //标识所属级别
        foreach ($parent_data as &$info){
            if($info['id'] == $category_id){
                $info['is_select'] = 1;
            }else{
                $info['is_select'] = 0;
            }
            //是否是末级
            $is_children = false;
            $pdata = $product_class_model->getInfoWithIdForPID($info['id'],$class_type);
            if (empty($pdata)){
                $is_children = true;
            }
            $info['is_children'] = $is_children;

        }
        $data[] = $parent_data;
        if ($pid != 0){//不是顶级则继续递归
            return self::getCategoryInfoWithID($pid, $data);
        }
        return $data;
    }
     /**
     * 根据分类ID数据获取对应分类及它的上级中最先有映射关系的映射数据
     * @param $category_id 子级分类ID
     * @return array
     */
    public static function getMapCategoryID($category_id, $data=[]){
        $product_class_model = new product_class_model();
        $info = $product_class_model->getInfoWithId($category_id);
        if(!empty($info['pdc_ids'])){
            $cinfo = $product_class_model->getInfoWithId($info['pdc_ids'][0]);
            $data['id_path'] =$cinfo['id_path'];
            $data['cate_id'] =$cinfo['id'];
        }else{
            $pid = $info['pid'];
            if($pid != 0){//不是顶级则继续递归
                return self::getMapCategoryID($pid, $data);
            }
        }
        return $data;
    }

    /**
     * 根据分类ID获取下一个子级
     * @param $pid 父级ID
     * @param int $class_type
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public static function getCategoryNextInfoWithID($pid,$class_type=1){
        $product_class_model = new product_class_model();
        $data = $product_class_model->getInfoWithIdForPID($pid,$class_type);
        //判断级别是否为末级
        foreach ($data as &$info){
            $is_children = false;
            $id = $info['id'];
            $pdata = $product_class_model->getInfoWithIdForPID($id,$class_type);
            if (empty($pdata)){
                $is_children = true;
            }
            $info['is_children'] = $is_children;
        }
        return $data;
    }



}