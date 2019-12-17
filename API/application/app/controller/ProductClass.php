<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\mall\ProductClassParams;
use app\app\services\ProductClassService;
use think\Exception;
use think\Monlog;

/**
 * 分类或者分类集成接口
 */
class ProductClass extends AppBase
{

    public $redis;
    public $classService;
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
        $this->classService = new ProductClassService();
    }

    /**
     * 产品分类信息查询
     */
    public function getCategoryLists(){
        try{
            $params = input();
            $data = $this->classService->getCategoryLists($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>200000001, 'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 分类集成
     * @return mixed
     */
    public function index(){
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params,(new ProductClassParams())->Rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->classService->getIntegrationClass($params);
            return $data;
        }catch (Exception $e){
            return apiReturn(['code'=>200000002, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品分类信息查询
     */
    public function getClass(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductClassParams())->getClassRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->classService->getClass($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>200000004, 'msg'=>$e->getMessage()]);
        }

    }


    /**
     * 产品分类信息列表
     */
    public function selectClass(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductClassParams())->selectClassRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->classService->selectClass($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>200000004, 'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 根据类别ID，获取品牌信息
     */
    public function getBrand(){
        $paramData = request()->post();

        if (!isset($paramData['class_id'])) {
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
        try{
            $data = $this->classService->getProductBrand($paramData);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>200000005, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据类别ID，获取属性信息
     */
    public function getAttribute(){
        $paramData = request()->post();

        if (!isset($paramData['class_id'])) {
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
        try{
            $data = $this->classService->getProductAttribute($paramData);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>200000006, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * 根据分类id -- 获取上级节点信息
     * @return array|mixed
     */
    public function getNextCategory(){
        $data = array();
        $params = request()->post();

        if (!isset($params['class_id']) && empty($params['class_id'])){
            return apiReturn(['code'=>1003]);
        }
        try{
            if(config('cache_switch_on')){
                $result = $this->redis->get(CATEGORY_PID_INFO_.$params['class_id']);
            }
            if(empty($result)){
                $data = $this->classService->getNextCategoryInfo($params);
                if(!empty($data)){
                    $this->redis->set(CATEGORY_PID_INFO_.$params['class_id'],$data,CACHE_DAY);
                }
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>200000007, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 一级分类的品牌数据
     */
    public function getFirstCategoryBrand(){
        try{
            $params = input();

            $data = (new ProductClassService())->getFirstCategoryBrand($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>2000001, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据分类id -- 获取全部节点信息
     * @return array|mixed
     */
    public function getCategoryInfoByClassId(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductClassParams())->selectClassRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }

        try{
            $data = $this->classService->getClassInfo($params,$params['lang']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>200000007, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取configData配置 根据一级分类分组数量
     */
    public function countCategoryByConfgData(){
        try{
            $params = request()->post();

            $data = $this->classService->countCategoryByConfgData($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>200000007, 'msg'=>$e->getMessage()]);
        }
    }

    public function handleClassMap(){
        try{
            return $this->classService->handleClassMap();
        }catch (Exception $e){
            return apiReturn(['code'=>200000007, 'msg'=>$e->getMessage()]);
        }
    }

}
