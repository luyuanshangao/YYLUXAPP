<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\app\model\ConfigDataModel;
use app\app\model\ProductClassModel;
use app\app\model\ProductModel;
use think\Cache;
use think\Db;
use think\Exception;


/**
 * 广告业务层
 */
class ProductClassService extends BaseService
{
    const CACHE_KEY = 'DX_MALL_PRODUCTCLASS_';
    const CACHE_TIME = 86400;//一天

    public $classModel;

    /**
     * 集成分类数据，用于头部分类合成
     * @param $params
     * @return array
     */
    public function getIntegrationClass($params){
        $this->classModel = new ProductClassModel();
        $data = array();
        //判断是否有缓存
        if(config('cache_switch_on')){
            $data = $this->redis->get(INTEGRATION_CLASS);
        }
        if(empty($data)){
            $result = $this->classModel->integrationClassLists(['language' => $params['lang']]);
            if(empty($result)){
                //其他没有数据，默认取英文
                $result = $this->classModel->integrationClassLists(['language' => DEFAULT_LANG]);
            }
            //集成分类数据，解析分类id,组装html代码
            foreach($result as $key => $productClass) {
                $html = '';
                //解析图标
                $icon = isset($productClass['classIconfont']) && !empty($productClass['classIconfont']) ? 'icon-'.$productClass['classIconfont'] : 'icon-erji';
                //解析一级分类数据
                $productClass['classNameHtml'] = str_replace("|","&",$productClass['classNameHtml']);

                $html =$html. '<span><i class="iconfont '.$icon.'"></i>'.$productClass['classNameHtml'].'</span>';

                //暂定content是json字符串
//                $menus = json_decode($productClass['content'], true);
//                if(!is_array($menus)){continue;}

                $html = $html.'<div class="menu-dropdown">';
                $html = $html.$productClass['content'];
//                foreach ($menus as $menu) {
//                    //分类展示头部样式，固定在数组的第一个元素
//                    $headFormat = $menu[0];
//                    //分类头部样式
//                    $html = $html . '<'.$headFormat['format'].' class="'.$headFormat['class'].'">';
//                    foreach ($menu as $submenu) {
//                        $showChild = isset($submenu['showChild']) ? $submenu['showChild'] : "false";
//                        //是否有子分类
//                        if ($showChild == "true") {
//                            //当前分类ID的详情
//                            $classDeatil = $this->getClassInfo(['class_id' => $submenu['pid']],$params['lang']);
//                            if(!empty($classDeatil)){
//                                $level = count($classDeatil);
//                                switch($level){
//                                    case 1:
//                                        $firstTitle = $classDeatil[0]['hrefTitle'];
//                                        //一级分类数据
//                                        $html = $html . '<'.$submenu['format'].'><a href="'.$path.$firstTitle. '"class="menu_subtitle">' . $classDeatil[0]['title_en'] . '</a></'.$submenu['format'].'>';
//                                        break;
//                                    case 2:
//                                        $firstTitle = $classDeatil[0]['hrefTitle'];
//                                        $secondTitle = $classDeatil[1]['hrefTitle'];
//                                        //二级分类数据
//                                        $html = $html . '<'.$submenu['format'].'><a href="'.$path.$firstTitle.'/'.$secondTitle. '"class="menu_subtitle">' . $classDeatil[1]['title_en'] . '</a></'.$submenu['format'].'>';
//                                        break;
//                                    default:
//                                        continue;
//                                }
//                            }
//                            $childsArray = explode(',', $submenu['childs']);
//                            foreach ($childsArray as $childs) {
//                                $classDeatil = $this->getClassInfo(['class_id' => $childs],$params['lang']);
//                                if(!empty($classDeatil)){
//                                    $level = count($classDeatil);
//                                    switch($level){
//                                        case 2:
//                                            $firstTitle = $classDeatil[0]['hrefTitle'];
//                                            $secondTitle = $classDeatil[1]['hrefTitle'];
//                                            $html = $html . '<'.$submenu['childformat'].'><a href="'.$path.$firstTitle.'/'.$secondTitle.'" class="menu_i">' . $classDeatil[1]['title_en'] . '</a></'.$submenu['childformat'].'>';
//                                            break;
//                                        case 3:
//                                            $firstTitle = $classDeatil[0]['hrefTitle'];
//                                            $secondTitle = $classDeatil[1]['hrefTitle'];
//                                            $thirdTitle = $classDeatil[2]['hrefTitle'];
//                                            $html = $html . '<'.$submenu['childformat'].'><a href="'.$path.$firstTitle.'/'.$secondTitle.'/'.$thirdTitle.'" class="menu_i">' . $classDeatil[2]['title_en'] . '</a></'.$submenu['childformat'].'>';
//                                            break;
//                                        case 4:
//                                            $firstTitle = $classDeatil[0]['hrefTitle'];
//                                            $secondTitle = $classDeatil[1]['hrefTitle'];
//                                            $thirdTitle = $classDeatil[2]['hrefTitle'];
//                                            $fourthTitle = $classDeatil[3]['hrefTitle'];
//                                            $html = $html . '<'.$submenu['childformat'].'><a href="'.$path.$firstTitle.'/'.$secondTitle.'/'.$thirdTitle.'/'.$fourthTitle.'" class="menu_i">' . $classDeatil[3]['title_en'] . '</a></'.$submenu['childformat'].'>';
//                                            break;
//                                        default:
//                                            continue;
//                                    }
//                                }
//                            }
//                        } else {
//                            //没有子类情况
//                            if(isset($submenu['pid'])){
//                                $pids = explode(',', $submenu['pid']);
//                                foreach ($pids as $pid) {
//                                    $classDeatil = $this->getClassInfo(['class_id' => $pid],$params['lang']);
//                                    if(!empty($classDeatil)){
//                                        $level = count($classDeatil);
//                                        switch($level){
//                                            case 1:
//                                                $firstTitle = $classDeatil[0]['hrefTitle'];
//                                                //一级分类数据
//                                                $html = $html . '<'.$submenu['format'].'><a href="'.$path.$firstTitle. '"class="menu_subtitle">' . $classDeatil[0]['title_en'] . '</a></'.$submenu['format'].'>';
//                                                break;
//                                            case 2:
//                                                $firstTitle = $classDeatil[0]['hrefTitle'];
//                                                $secondTitle = $classDeatil[1]['hrefTitle'];
//                                                //二级分类数据
//                                                $html = $html . '<'.$submenu['format'].'><a href="'.$path.$firstTitle.'/'.$secondTitle. '"class="menu_subtitle">' . $classDeatil[1]['title_en'] . '</a></'.$submenu['format'].'>';
//                                                break;
//                                            default:
//                                                continue;
//                                        }
//                                    }
//                                }
//                            }
//                        }
//                    }
//                    $html = $html . '</dl>';
//                }

                //分类右侧广告数据
                $html = $html .$productClass['content_right'];
                $html = $html.'</div>';
                $data[$key] = $html;
            }
            $this->redis->set(INTEGRATION_CLASS,$data,CACHE_DAY);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);

    }


    /**
     * 根据pid
     * @param $params
     * @return mixed
     */
    public function getClass($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $pid = $params['pid'];
        $this->classModel = new ProductClassModel();
        $result = array();
        //判断缓存
//        if(config('cache_switch_on')){
//            $result = $this->redis->get(CHILD_CATEGORIES_LIST_BY_.$params['pid'].'_'.$params['lang']);
//        }
        if(empty($result)){
            //类别映射
            if($params['pid'] != 0){
                $map = $this->getClassMap($params['pid']);
                if(is_array($map) && !empty($map)){
                    $params['pid'] = CommonLib::supportArray($map);
                }else{
                    $params['pid'] = $map;
                }
            }
            $result = $this->classModel->getClassByPid($params);
//            if(!empty($result)){
//                $this->redis->set(CHILD_CATEGORIES_LIST_BY_.$pid.'_'.$params['lang'] , $result , CACHE_DAY);
//            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * class_id 数组
     * @param $params
     * @return mixed
     */
    public function selectClass($params){
        $this->classModel = new ProductClassModel();
        $result = array();
        $key = implode('_',$params['class_id']);
        //判断缓存
        if(config('cache_switch_on')){
            $result = $this->redis->get(SELECT_CATEGORIES_LIST_BY_.$key.'_'.$params['lang']);
        }
        if(empty($result)){
            //格式化
            $params['class_id'] = CommonLib::supportArray($params['class_id']);
            $result = $this->classModel->selectClass($params);
            if(!empty($result)){
                $this->redis->set(SELECT_CATEGORIES_LIST_BY_.$key.'_'.$params['lang'] , $result , CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * 根据类别，获取该类别下的品牌
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getProductBrand($params){

        $result = $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get(CATEGORY_BRAND_BY_.$params['class_id']);
        }
        if(empty($data)){
            $class_info = (new ProductClassModel())->getClassDetail(['id'=>(int)$params['class_id']]);
            //类别映射
            if($class_info['type'] == 2){
                if(!empty($class_info['pdc_ids'])){
                    $params['class_id'] = $class_info['pdc_ids'];
                }
            }
            $data = (new ProductClassModel())->getProductBrand($params);
            if(empty($data)){
                //查找这个分类id的子集,所有品牌数据
                $params['class_id'] = is_array($params['class_id']) ? CommonLib::supportArray($params['class_id']) : $params['class_id'];
                $lists = (new ProductClassModel())->getClassByPid(['pid'=>$params['class_id'],'lang'=>'en']);
                if($lists){
                    $result = array();
                    $ids = CommonLib::getColumn('id',$lists);
                    //搜索格式化
                    $brandList = (new ProductClassModel())->selectProductBrand(['class_id'=>CommonLib::supportArray($ids)]);
                    if(!empty($brandList)){
                        foreach($brandList as $brands){
                            $result = array_merge($result,$brands['product_brand']);
                        }
                        //去重
                        $result = CommonLib::array_unset_repeat($result,'id');
                        //删除白牌
                        if(isset($result[1]) && !empty($data[1])){
                            unset($result[1]);
                        }
                        //随机抽取30个
                        $data['product_brand'] = CommonLib::getRandArray($result,30);
                    }
                }
            }else{
                //删除白牌
                if(isset($data['product_brand'][1]) && !empty($data['product_brand'][1])){
                    unset($data['product_brand'][1]);
                }
            }
            if(!empty($data)){
                $this->redis->set(CATEGORY_BRAND_BY_.$params['class_id'],$data,CACHE_DAY);
            }
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 根据类别，获取该类别下的属性
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getProductAttribute($params){
        $result = array();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        if(config('cache_switch_on')){
            $result = $this->redis->get(CATEGORY_ATTR_BY_.$params['class_id']);
        }
        try{
            if(empty($result)){
                $class_info = (new ProductClassModel())->getClassDetail(['id'=>(int)$params['class_id']]);
                //类别映射
                if($class_info['type'] == 2){
                    if(!empty($class_info['pdc_ids'])){
                        $params['class_id'] = $class_info['pdc_ids'];
                    }
                }
                $result = (new ProductClassModel())->getProductAttribute(['class_id'=>$params['class_id']]);
                if(empty($result)){
                    //查询子分类
                    $params['class_id'] = is_array($params['class_id']) ? CommonLib::supportArray($params['class_id']) : $params['class_id'];
                    $lists = (new ProductClassModel())->getClassByPid(['pid'=>$params['class_id'],'lang'=>'en']);
                    if($lists){
                        $result = $data = array();
                        $ids = CommonLib::getColumn('id',$lists);
                        //搜索格式化
                        $attributeList = (new ProductClassModel())->getProductAttribute(['class_id'=>$ids]);
                        if(!empty($attributeList)){
                            foreach($attributeList as $attribute){
                                $data = array_merge($data,$attribute['attribute']);
                            }
                            //去重
                            $data = CommonLib::array_unset_repeat($data,'id');
                            $result['attribute'] = CommonLib::getRandArray($data,8);
                        }
                    }
                }
                if (!empty($result)) {
                    $this->redis->set(CATEGORY_ATTR_BY_.$params['class_id'],$result, CACHE_DAY);
                }
            }
            //注意：多语言，属性多语种表查询
            if($lang != DEFAULT_LANG){
                //获取属性的多语言
//                if(!empty($result['attribute'])){
//                    foreach($result['attribute'] as $attrKey => $attrValue){
//                        $langData = $this->getProductAttrMultiLang($attrKey);
//                        //例：color颜色的多语言
//                        $title = isset($langData['Title'][$lang]) ? $langData['Title'][$lang] : $attrValue['ENtitle'];
//                        $result['attribute'][$attrKey]['ENtitle'] = $title;
//                        //例：color下蓝色blue的多语言
//                        foreach($attrValue as $opKey => $opValue){
//                            $options = isset($langData['Options'][$opKey][$lang]) ? $langData['Options'][$opKey][$lang] : '';
//                            //多语言为空，还是取英文
//                            if(empty($options)){
//                                $options = $opValue['name'];
//                            }
//                            $result['attribute'][$attrKey]['attribute_value'][$opKey]['name'] = $options;
//                        }
//                    }
//                }
            }
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            return apiReturn(['code'=>200, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据分类ID获取相应分类信息[根据子级获取完整类别]
     * @param array $params 分类ID
     * @return array
     */
    public function getNextCategoryInfo($params){
        $data = (new ProductClassModel())->getClassDetail(['id'=>(int)$params['class_id']]);
        if(empty($data)){
            return $data;
        }
        $data['hrefTitle'] = CommonLib::filterTitle($data['title_en']).'-'.$data['id'];
        if ($data['pid'] != 0){
            //不是顶级则继续递归
            $data['is_children'] = true ;
            $data[$data['pid']] =  self::getNextCategoryInfo(['class_id'=>(int)$data['pid']]);
        }
        return $data;
    }


    /**
     * 根据分类ID获取分类信息
     * @param array $params 分类ID
     * @param string $lang
     * @return array
     */
    public function getClassInfo($params,$lang){
        $this->classModel = new ProductClassModel();

        $result = array();
        $data = $this->classModel->getClassDetail(['id'=>(int)$params['class_id']],$lang);
        if(!empty($data)){
            if($data['level'] != 1){
                $id_path = explode('-',$data['id_path']);
                foreach($id_path as $level => $class_id){
                    $result[$level] = $this->classModel->getClassDetail(['id'=>(int)$class_id],$lang);
                    if(!empty($result[$level])){
                        $result[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                    }
                }
            }else{
                $result[0] =  $data;
                if(!empty($data)){
                    $result[0]['hrefTitle'] = $data['rewritten_url'].'-'.$data['id'];
                }
            }
            return $result;
        }
        return array();
    }

    /**
     * 分类列表
     * @param params
     * @return array
     */
    public function getCategoryLists($params){
        $this->classModel = new ProductClassModel();

        $result = array();
        $lang = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;

        if(config('cache_switch_on')){
            $result = $this->redis->get(ALL_CATEGORY_LIST_.$lang);
        }
        if(empty($result)){
            $data = $this->classModel->selectClass(['pid' => 0,'lang' => $lang,'type'=>1]);
            if(empty($data)){
                return $result;
            }
//            pr($data);die;
            foreach($data as $key => $value){
                $data[$key]['hrefTitle'] = $value['rewritten_url'].'-'.$value['id'];
                //一级标题，如果多语种没数据，默认取英文
                if($lang != DEFAULT_LANG){
                    $data[$key]['title_en'] = isset($value['common'][$lang]) && !empty($value['common'][$lang]) ?
                        $value['common'][$lang] :$value['title_en'];
                }
                //二级分类
                $second = $this->classModel->selectClass(['pid' => $value['id'],'lang' => $lang,'type'=>1]);
                if(!empty($second)){
                    foreach($second as $skey => $secondData){
                        $second[$skey]['hrefTitle'] = $secondData['rewritten_url'].'-'.$secondData['id'];
                        //二级标题，如果多语种没数据，默认取英文
                        if($lang != DEFAULT_LANG) {
                            $second[$skey]['title_en'] = isset($secondData['common'][$lang]) && !empty($secondData['common'][$lang]) ?
                                $secondData['common'][$lang] : $secondData['title_en'];
                        }
                        //三级分类
                        $third =  $this->classModel->selectClass(['pid' => $secondData['id'],'lang' => $lang,'type'=>1]);
//                        pr($third);die;

                        if(!empty($third)){
                            foreach($third as $tkey => $thirdData){
                                $third[$tkey]['hrefTitle'] = $thirdData['rewritten_url'].'-'.$thirdData['id'];
                                if($lang != DEFAULT_LANG) {
                                    //三级标题，如果多语种没数据，默认取英文
                                    $third[$tkey]['title_en'] = isset($thirdData['common'][$lang]) && !empty($thirdData['common'][$lang]) ?
                                        $thirdData['common'][$lang] : $thirdData['title_en'];
                                }

                                $fourth = $this->classModel->selectClass(['pid' => $thirdData['id'],'lang' => $lang,'type'=>1]);
                                if(!empty($fourth)){
                                    foreach($fourth as $fkey => $fourthData){
                                        $fourth[$fkey]['hrefTitle'] = $fourthData['rewritten_url'].'-'.$fourthData['id'];
                                        if($lang != DEFAULT_LANG) {
                                            //4级标题，如果多语种没数据，默认取英文
                                            $fourth[$fkey]['title_en'] = isset($fourthData['common'][$lang]) && !empty($fourthData['common'][$lang]) ?
                                                $fourthData['common'][$lang] : $fourthData['title_en'];
                                        }
                                        //默认到四级
                                        $fourth[$fkey]['is_children'] = true;
                                        $fourth[$fkey]['childrens'] = [];
                                        $data[$key]['childrens'][$skey]['childrens'][$tkey]['childrens'][$fkey] = $fourth[$fkey];
                                    }
                                    //三级数据不为空
                                    $third[$tkey]['is_children'] = true;
                                    $third[$tkey]['childrens'] = $fourth;
                                    $data[$key]['childrens'][$skey]['childrens'][$tkey] = $third[$tkey];
                                }else{
                                    //三级数据为空
                                    $third[$tkey]['is_children'] = false;
                                    $third[$tkey]['childrens'] = [];
                                    $data[$key]['childrens'][$skey]['childrens'][$tkey] = $third[$tkey];
                                }
                            }
                            //二级数据不为空
                            $second[$skey]['is_children'] = true ;
                            $second[$skey]['childrens'] = $third ;
                            $data[$key]['childrens'][$skey] = $second[$skey];
                        }else{
                            //二级数据为空
                            $second[$skey]['is_children'] = false ;
                            $second[$skey]['childrens'] = [] ;
                            $data[$key]['childrens'][$skey] = $second[$skey];
                        }
                    }
                    $data[$key]['is_children'] = true ;
                    $data[$key]['childrens'] = $second ;
                }else{
                    //二级数据为空
                    $data[$key]['is_children'] = false ;
                    $data[$key]['childrens'] = [] ;
                }
            }
            if(!empty($data)){
                $this->redis->set(ALL_CATEGORY_LIST_.$lang,$data,CACHE_DAY);
            }
        }
        return $data;
    }


    /**
     * 一级分类品牌数据
     * @return array
     */
    public function getFirstCategoryBrand(){
        $this->classModel = new ProductClassModel();

        $lang = isset($params['lang']) ? $params['lang'] : self::DEFAULT_LANG;
        $classData = $this->classModel->selectClass(['pid' => 0,'lang'=>$lang,'type'=>1]);
        //格式化一级分类ID
        foreach($classData as $key => $value){
            //查询品牌信息
            $brand = $this->classModel->getProductBrand(['class_id' => $value['id']]);
            if(empty($brand['product_brand'])){
                //类别映射
                if(!empty($value['pdc_ids'])){
                    $map_brand = array();
                    $erp_brand = $this->classModel->getProductBrand(['class_id' => $value['pdc_ids']]);
                    if($erp_brand){
                        foreach($erp_brand as $erpData){
                            $map_brand = array_unique(array_merge($map_brand,$erpData['product_brand']));
                        }
                        $brand['product_brand'] = $map_brand;
                    }else{
                        unset($classData[$key]);
                        continue;
                    }
                }else{
                    unset($classData[$key]);
                    continue;
                }
            }
            foreach($brand['product_brand'] as $k => $brands){
                if($brands['brand_name'] == 'N/A' || empty($brands['brand_name'])){
                    unset($brand['product_brand'][$k]);
                }
            }
            $classData[$key]['brand'] = array_values($brand['product_brand']);
        }
        $classData = array_values($classData);
        return $classData;
    }

    /**
     * 获取configData配置 根据一级分类分组数量
     */
    public function countCategoryByConfgData($params){
        $this->classModel = new ProductClassModel();
        $key = $params['key'];
        $class_list = array();
        if(config('cache_switch_on')){
            $class_list = $this->redis->get(COUNT_CATEGORY_BY_.$key. '_' . $params['lang']);
        }
        if(empty($class_list)){
            //获取配置的spu列表
            if(config('cache_switch_on')) {
                $spus = $this->redis->get(SPU_CONFIG_DATA_BY_.$key);
            }
            if(empty($spus)){
                $spus = (new ConfigDataModel())->getDataConfig($params);
                if(!empty($spus)){
                    $this->redis->set(SPU_CONFIG_DATA_BY_.$key,$spus,CACHE_DAY);
                }
            }
            if(isset($spus['spus']) && !empty($spus['spus'])){
                //产品id按一级类别分组
                $countData = (new ProductModel())->groupByProductCategory(CommonLib::array_string_int($spus['spus']),'$FirstCategory');

                if(!empty($countData)){
                    //object转数组
                    $countData = json_decode(json_encode($countData),true);
                    //获取类别id
                    $class_id = CommonLib::supportArray(CommonLib::getColumn('_id',$countData));
                    //获取分类详情
                    $class_list = $this->classModel->selectClass(['class_id' =>$class_id,'lang'=>$params['lang']]);
                    if(!empty($class_list)){
                        $data = array();
                        //循环赋值
                        foreach($class_list as $key => $class){
                            //PDC数据
                            if($class['type'] == 2){
                                if(!empty($class['pdc_ids'])){
                                    //类别映射
                                    $mapList = $this->classModel->selectClass(['class_id'=>CommonLib::supportArray($class['pdc_ids']),'lang'=>$params['lang']]);
                                    foreach($mapList as $map){
                                        $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                                        if(isset($data[$map['id']])){
                                            $data[$map['id']]['count'] = $data[$map['id']]['count'] + $count['count'];
                                        }else{
                                            $data[$map['id']]['count'] = $count['count'];
                                        }
                                        $data[$map['id']]['title'] = $map['title_en'];
                                        $data[$map['id']]['id'] = $class['id'];
                                    }
                                }else{
                                    //类别映射为空
                                    $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                                    $data[$class['id']]['title'] = $class['title_en'];
                                    $data[$class['id']]['count'] = $count['count'];
                                    $data[$class['id']]['id'] = $class['id'];
                                }
                            }else{
                                //erp数据
                                $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                                if(isset($data[$class['id']])){
                                    $data[$class['id']]['count'] = $data[$class['id']]['count'] + $count['count'];
                                }else{
                                    $data[$class['id']]['count'] = $count['count'];
                                }
                                $data[$class['id']]['title'] = $class['title_en'];
                                $data[$class['id']]['id'] = $class['id'];
                            }
                        }
                        $this->redis->set(COUNT_CATEGORY_BY_.$key. '_' . $params['lang'],array_values($data),CACHE_DAY);
                        return array_values($data);
                    }
                }
            }
        }

        return $class_list;

    }

    /**
     * 类别映射
     * @param $id
     * @return array|mixed
     */
    public function getClassMap($id){
        $this->classModel = new ProductClassModel();
        $result = $this->classModel->getClassDetail(['id'=>(int)$id]);
        if(!empty($result)){
            if($result['type'] == 1){
                return $id;
            }else{
                return $result['pdc_ids'];
            }
        }
        return $id;

    }

    /**
     * 映射关系
     */
    public function handleClassMap(){
        $this->classModel = new ProductClassModel();

        //查找所有类别映射为空的类别
        $classList = $this->classModel->getEmptyPdcids();

        foreach($classList as $class){
            $mapData = array();
            switch($class['level']){
                case 1:
                    //挂2级类别映射数据
                    $mapData = $this->getPdcids($class['id']);
                    if(!empty($mapData)) {
                        //插入映射
                        $this->classModel->updateClass(['pdc_ids'=>$mapData],['id'=>$class['id']]);
                    }else{
                        //挂3级类别映射数据
                        $secondList = $this->classModel->getPdcids(['pid'=>$class['id']]);
                        if(!empty($secondList)){
                            foreach($secondList as $secondData){
                                $thirdMap = $this->getPdcids($secondData['id']);
                                if(!empty($thirdMap)){
                                    $mapData = array_merge($mapData,$thirdMap);
                                }
                            }
                            if(empty($mapData)){
                                //挂4级类别映射数据
                                foreach($secondList as $secondData){
                                    $thirdList = $this->classModel->getPdcids(['pid'=>$secondData['id']]);
                                    if(!empty($thirdList)){
                                        foreach($thirdList as $thirdData){
                                            $forthMap = $this->getPdcids($thirdData['id']);
                                            if(!empty($forthMap)){
                                                $mapData = array_merge($mapData,$forthMap);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if(!empty($mapData)){
                            $mapData = array_unique($mapData);
                            //插入映射
                            $this->classModel->updateClass(['pdc_ids'=>$mapData],['id'=>$class['id']]);
                        }else{
                            if($class['type'] == 1){
                                //状态改为不可用
                                $this->classModel->updateClass(['status'=>0],['id'=>(int)$class['id']]);
                            }
                        }
                    }
                    break;
                case 2:
                    //挂3级类别映射数据
                    $mapData = $this->getPdcids($class['id']);
                    if(empty($mapData)){
                        //挂4级类别映射数据
                        $thirdList = $this->classModel->getPdcids(['pid'=>$class['id']]);
                        if(!empty($thirdList)){
                            foreach($thirdList as $thirdData){
                                $forthMap = $this->getPdcids($thirdData['id']);
                                if(!empty($forthMap)){
                                    $mapData = array_merge($mapData,$forthMap);
                                }
                            }
                        }
                    }
                    if(!empty($mapData)){
                        $mapData = array_unique($mapData);
                        //插入映射
                        $this->classModel->updateClass(['pdc_ids'=>$mapData],['id'=>$class['id']]);
                    }else{
                        if($class['type'] == 1){
                            //状态改为不可用
                            $this->classModel->updateClass(['status'=>0],['id'=>(int)$class['id']]);
                        }
                    }
                    break;
                case 3:
                    //挂4级类别映射数据
                    $mapData = $this->getPdcids($class['id']);
                    if(!empty($mapData)){
                        //插入映射
                        $this->classModel->updateClass(['pdc_ids'=>$mapData],['id'=>$class['id']]);
                    }else{
                        if($class['type'] == 1){
                            //状态改为不可用
                            $this->classModel->updateClass(['status'=>0],['id'=>(int)$class['id']]);
                        }
                    }
                    break;
                case 4:
                    if($class['type'] == 1){
                        //状态改为不可用
                        $this->classModel->updateClass(['status'=>0],['id'=>(int)$class['id']]);
                    }
                    break;
            }
        }
    }


    /**
     * 当前类别id的映射
     * @param $id
     * @return array
     */
    private function getPdcids($id){
        $this->classModel = new ProductClassModel();
        $mapData = array();
        $list = $this->classModel->getPdcids(['pid'=>$id]);
        if(!empty($list)){
            foreach($list as $class){
                if(!empty($class['pdc_ids'])){
                    $mapData = array_merge($mapData,$class['pdc_ids']);
                }
            }
        }
        if(!empty($mapData)){
            $mapData = array_unique($mapData);
        }
        return $mapData;
    }
}
