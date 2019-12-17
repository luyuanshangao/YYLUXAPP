<?php
namespace app\admin\controller;

use app\admin\model\ProductClass as product_class_model;
use app\admin\statics\BaseFunc;
use app\admin\statics\Category;

/**
 * 产品类别接口
 * Class ProductCategory
 * @author tinghu.liu 2018/3/20
 * @package app\seller\controller
 */
class ProductCategory
{
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
    public function searchByTitle($search_content)
    {
        $rtn = array();
        $type = empty(input('type/d'))?1:input('type/d');//类型：1-按照英文名搜索【默认】，2-按照中文名搜索
        $search_content = input('search_content/s');
        if (empty($search_content)){
            $rtn = apiReturn(['code'=>1003]);
        }else{
            $data = array();
            $product_class_model = new product_class_model();
            $tmp_data = $product_class_model->getDataWithTitle($search_content, $type);
            //只要末级的数据
            foreach ($tmp_data as $k=>$info){
                $pdata = $product_class_model->getInfoWithIdForPID($info['id']);
                if (empty($pdata)){//如果此类别不是其他类别的父级，即是末级
                    $data[] = $tmp_data[$k];
                }
            }
            if (empty($data)){
                $rtn = apiReturn(['code'=>1006]);
            }else{
                //增加手机分类完全路径
                foreach ($data as &$val){ //拼接完整类别路径
                    $category_id = $val['id']; //类别ID
                    $str = BaseFunc::getCategoryStrWithID($category_id);
                    $val['title_cn_str'] = $str['title_cn_str'];
                    $val['title_en_str'] = $str['title_en_str'];
                }
                $rtn = apiReturn(['code'=>200, 'data'=>$data]);
            }
        }
        return $rtn;
    }

    /**
     * 根据子[末级]分类ID获取分类完整数据【倒推】
     * @return array|mixed
     */
    public function getCategoryByID(){
        $rtn = array();
        $category_id = input('id/d');
        if (empty($category_id)){
            $rtn = apiReturn(['code'=>1003]);
        }else{
            $new_data = array();
            $data = array_reverse(BaseFunc::getCategoryInfoWithID($category_id));
            //只要四级
            foreach ($data as $key=>$val){
                if ($key <= 3){
                    $new_data[] = $val;
                }
            }
            if (empty($new_data)){
                $rtn = apiReturn(['code'=>1006]);
            }else{
                $rtn = apiReturn(['code'=>200, 'data'=>$new_data]);
            }
        }
        return $rtn;
    }

    /**
     * 根据分类ID获取下一个子级
     * @return array|mixed
     */
    public function getNextCategoryByID(){
        $rtn = array();
        $category_id = input('id/d');
        if ($category_id != 0 && empty($category_id)){
            $rtn = apiReturn(['code'=>1003]);
        }else{
            $data = BaseFunc::getCategoryNextInfoWithID($category_id);
            if (empty($data)){
                $rtn = apiReturn(['code'=>1006]);
            }else{
                $rtn = apiReturn(['code'=>200, 'data'=>$data]);
            }
        }
        return $rtn;
    }
}
