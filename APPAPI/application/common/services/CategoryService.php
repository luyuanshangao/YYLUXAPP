<?php
namespace app\common\services;


use app\common\helpers\CommonLib;
use think\Cache;
use think\Monlog;

class CategoryService extends Api
{
    const CACHE_KEY = 'MALL_CATEGORYSERVICE_';
    const CACHE_TIME = 3600;//一小时

    public $redis;
    public function __construct(){
        $this->redis = new RedisClusterBase();
    }

    /**
     * 获取分类数据，根据上级ID获取子列表
     * @param $params ：pid 上级ID，lang 语种简码
     * @return  array
     */
    public function getCategories($params){
        $result = array();
        $data =  array();
        if(!isset($params['pid']) || !isset($params['lang'])){
            return $result;
        }
        //判断缓存
        if(config('cache_switch_on')){
            $result = $this->redis->get(CHILD_CATEGORIES_LIST_BY_.$params['pid'].'_'.$params['lang']);
        }
        //如果缓存为空
        if(empty($result)){
            $request = doCurl(MALL_API . '/mall/productClass/getClass', $params, [
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getClass',$request);
                return $result;
            }
            $result = is_array($request['data']) ? $request['data'] : [];
        }
        if(!empty($result)){
            foreach($result as $key => $val){
                $data[$key]['title_en'] = $val['title_en'];
                $data[$key]['id'] = $val['id'];
                $data[$key]['value'] = $val['title_en'];
                //语种切换，如果当前语种数据没空，默认取英文
                if($params['lang'] != DEFAULT_LANG){
                    $data[$key]['title_en'] = isset($val['Common'][$params['lang']]) && !empty($val['Common'][$params['lang']]) ?
                        $val['Common'][$params['lang']] : $val['title_en'];
                    $data[$key]['value'] = isset($val['Common'][$params['lang']]) && !empty($val['Common'][$params['lang']]) ?
                        $val['Common'][$params['lang']] : $val['title_en'];
                }
                //格式化
                $data[$key]['hrefTitle'] = $val['rewritten_url'].'-'.$val['id'];
            }
        }
        return $data;
    }


    /**
     * 首页集成分类
     */
    public function getBaseCategories($params){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(INTEGRATION_CLASS.'_'.$params['lang']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/productClass/index',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/index',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return $result;
    }

    /**
     * 分类热度词
     * @param $params
     * @return array
     */
    public function getHotwords($params){
        $data = array();

        $request = doCurl(MALL_API.'/mall/baseConfig/getCategoryHotWord',[],[
            'access_token' => $this->getAccessToken()
        ]);
        if($request['code'] != 200){
            logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/baseConfig/getCategoryHotWord',$request);
            return $data;
        }
        if(!empty($request['data'])){
            foreach($request['data']['categories'] as $key => $val){
                $data[$key] = $val[$params['language']]['info'];
            }
        }
        return $data;
    }


    /**
     * 二级分类，三级分类页面，根据分类ID获取品牌
     * @param $params
     * 参数 class_id 分类id
     * @return array
     */
    public function getBrandLists($params){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(CATEGORY_BRAND_BY_.$params['class_id']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/productClass/getBrand',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getBrand',$request);
                return $result;
            }
            $result = $request['data'];
        }

        //默认展示两行，其余折叠，点击+号，可展示全部30个品牌
        return $result;
    }

    /**
     * 二级分类，三级分类页面，根据分类ID获取属性
     * @param $params
     * 参数
     * class_id 分类id
     * lang 语种
     * @return array
     */
    public function getAttributeLists($params){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(CATEGORY_ATTR_BY_.$params['class_id']);
        }
        if(empty($request)){
            $request = doCurl(MALL_API.'/mall/productClass/getAttribute',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getAttribute',$request);
                return $result;
            }
            $result = $request['data'];
        }
        //按照销售属性顺序，默认展示前三个销售属性，其余折叠
        return $result;

    }

    /**
     * 根据分类id 倒推分类节点数据
     */
    public function getNextCategoryByClassId($params){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(CATEGORY_PID_INFO_.$params['class_id']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/productClass/getNextCategory',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getNextCategory',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return $result;
    }

    /**
     * 根据分类id 获取分类节点下全部信息
     */
    public function getCategoryByClassId($params){
        $result = [];
        //代码优化 ，判断参数是否为空，addby zhongning 20190730
        if(empty($params['class_id'])){
            return $result;
        }
        if(config('cache_switch_on')){
            $result = $this->redis->get(CATEGORY_INFO_BY_.$params['class_id'].'_'.$params['lang']);
        }
        if(empty($result)){
            //需去除
            $request = doCurl(MALL_API.'/mall/productClass/getCategoryInfoByClassId',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getCategoryInfoByClassId',$request);
                return $result;
            }
            $result = $request['data'];
            if(!empty($result) && is_array($result)){
                $this->redis->set(CATEGORY_INFO_BY_.$params['class_id'].'_'.$params['lang'],$result,CACHE_DAY);
            }
        }
        if(!empty($result) && is_array($result)){
            //错误日志：beauty-health-3/51/paper-napkins-serviettes-1799643，因为51是禁用状态，报错
            foreach ($result as $key => $class) {
                //数组中，有一个类别为空，都是用问题的类别搜索
                if(empty($class['hrefTitle'])){
                    return array();
                }
            }
            if(DEFAULT_LANG != $params['lang']) {
                foreach ($result as $key => $class) {
                    //如果多语种没数据，默认取英文
                    $result[$key]['title_en'] = isset($class['Common'][$params['lang']]) && !empty($class['Common'][$params['lang']]) ?
                        $class['Common'][$params['lang']] : $class['title_en'];
                }
            }
        }
        return $result;
    }

    /**
     * 分类列表
     */
    public function getCategoryLists($params){
        $result = array();
        if(config('cache_switch_on')){
            $result = $this->redis->get(ALL_CATEGORY_LIST_.$params['lang']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/productClass/getCategoryLists',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getCategoryLists',$request);
                return $result;
            }
            $result = $request['data'];
        }
        return $result;
    }

    /**
     * 一级分类下的所有品牌
     */
    public function getFisrtCategoryBrands($params){
        $data = array();
        if(config('cache_switch_on')){
            $data = $this->redis->get('FIRSTCATEGORY_BRAND_'.$params['lang']);
        }
        if(empty($data)){
            $request = doCurl(MALL_API.'/mall/productClass/getFirstCategoryBrand',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getFirstCategoryBrand',$request);
                return $data;
            }
            if(isset($request['data']) && !empty($request['data'])){
                $this->redis->set('FIRSTCATEGORY_BRAND_'.$params['lang'],$request['data'],CACHE_HOUR);
            }
            $data = $request['data'];
        }
        return array_values($data);
    }

    /**
     * 根据class_id 查询
     */
    public function selectCategory($params){
        $result = array();
        $key = implode('_',$params['class_id']);
        if(config('cache_switch_on')){
            $result = $this->redis->get(SELECT_CATEGORIES_LIST_BY_.$key.'_'.$params['lang']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/productClass/selectClass',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/selectClass',$request);
                return $result;
            }
            $result = $request['data'];
        }
        $classData = array();
        if(!empty($result)){
            foreach($result as $class){
                $title = $class['title_en'];
                $classData[$class['id']]['hrefTitle']  = CommonLib::filterTitle($class['title_en']).'-'.$class['id'];
                //如果多语种没数据，默认取英文
                if(DEFAULT_LANG != $params['lang']) {
                    $title = isset($class['Common'][$params['lang']]) && !empty($class['Common'][$params['lang']]) ?
                        $class['Common'][$params['lang']] : $title;
                }
                $classData[$class['id']]['title'] = $title;
                $classData[$class['id']]['id'] = $class['id'];
            }
        }
        return $classData;
    }

    /**
     * 根据confgData配置，查找出分类的数量
     */
    public function getCategoryCountListsData($params){
        $class_list = array();
        $key = $params['key'];
        //判断是否有缓存
        if(config('cache_switch_on')){
            $class_list = $this->redis->get(COUNT_CATEGORY_BY_.$key. '_' . $params['lang']);
        }
        if(empty($class_list)){
            $result = doCurl(MALL_API.'/mall/productClass/countCategoryByConfgData',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/countCategoryByConfgData',$result);
                return $class_list;
            }
            $class_list = $result['data'];
        }
        return $class_list;
    }

    /**
     * 根据分类详情
     */
    public function getClassDetail($params){
        $result = [];
        if(config('cache_switch_on')){
            $result = $this->redis->get('ClASSDETAILS'.$params['class_id'].'_'.$params['lang']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API.'/mall/productClass/getClassDetail',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getCategoryInfoByClassId',$request);
                return $result;
            }
            $result = $request['data'];
            if(!empty($result) && is_array($result)){
                $this->redis->set('ClASSDETAILS'.$params['class_id'].'_'.$params['lang'],$result,CACHE_DAY);
            }
        }
        if(!empty($result) && is_array($result)){
            if(DEFAULT_LANG != $params['lang']) {
                foreach ($result as $key => $class) {
                    //如果多语种没数据，默认取英文
                    $result[$key]['title_en'] = isset($class['Common'][$params['lang']]) && !empty($class['Common'][$params['lang']]) ?
                        $class['Common'][$params['lang']] : $class['title_en'];
                }
            }
        }
        return $result;
    }

    /**
     * 获取分类数量
     */
    public function getCatetoryProductCount($params){
        $cache_key = CommonLib::getCacheKey($params);
        $class_list = array();
        //判断是否有缓存
        if(config('cache_switch_on')){
            $class_list = $this->redis->get(COUNT_CATEGORY_BY_.$cache_key);
        }
        if(empty($class_list)){
            $result = doCurl(MALL_API.'/mall/productClass/getCatetoryProductCount',$params,[
                'access_token' => $this->getAccessToken()
            ]);
            if($result['code'] != 200){
                logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productClass/getCatetoryCount',$result);
                return $class_list;
            }
            //按照数量排序
            array_multisort(array_column($result['data'], 'count'),SORT_DESC,$result['data']);
            $class_list = $result['data'];
            $this->redis->set(COUNT_CATEGORY_BY_.$cache_key,$result['data'],CACHE_DAY);
        }
        return $class_list;
    }
}