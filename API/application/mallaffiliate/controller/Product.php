<?php
namespace app\mallaffiliate\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\mallaffiliate\ProductParams;
use app\mallaffiliate\services\ProductService;
use think\Controller;
use think\Exception;


/**
 * 开发：钟宁
 * 功能：affiliate 产品查询功能
 * 时间：2018-06-08
 */
class Product extends Controller
{
    public $productService;
    public $redis;
    protected $validateParams;
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
        $this->productService = new ProductService();
        $this->validateParams = new ProductParams();
    }

    /**
     * 查询产品 --通用
     * @return mixed
     */
    public function query(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,$this->validateParams->queryProductRules());
        if(true !== $validate){
            return (['errorCode'=>4, 'msg'=>'Error in input parameter']);
        }
        try{
            //查看key是否存在
            $userList = $this->validateParams->userList();
            if(!array_search($paramData['key'],$userList)){
                return (['errorCode'=>5, 'msg'=>'The key does not exist']);
            }
            $result = $this->productService->query($paramData['searchArgs']);
            if(!$result){
                return (['errorCode'=>5, 'msg'=>'System error']);
            }
            return $result;
        }catch (Exception $e){
            return (['errorCode'=>5, 'msg'=>'System error']);
        }
    }

    /**
     * 何元-查询产品接口
     * @return mixed
     */
    public function lists(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,$this->validateParams->queryProductRules());
        if(true !== $validate){
            return (['errorCode'=>4, 'msg'=>'Error in input parameter']);
        }
        try{
            //查看key是否存在
            $userList = $this->validateParams->userList();
            if(!array_search($paramData['key'],$userList)){
                return (['errorCode'=>5, 'msg'=>'The key does not exist']);
            }
            $result = $this->productService->lists($paramData['searchArgs']);
            if(!$result){
                return (['errorCode'=>5, 'msg'=>'System error']);
            }
            return $result;
        }catch (Exception $e){
            return (['errorCode'=>5, 'msg'=>'System error']);
        }
    }

    /**
     * 查询产品根据分类ID --中东，参数特殊
     * @return mixed
     */
    public function queryByCategory(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,$this->validateParams->queryCategoryRules());
        if(true !== $validate){
            return (['errorCode'=>4, 'msg'=>'Error in input parameter']);
        }
        try{
            //查看key是否存在
            $userList = $this->validateParams->userList();
            if(!array_search($paramData['key'],$userList)){
                return (['errorCode'=>5, 'msg'=>'The key does not exist']);
            }
            $result = $this->productService->queryByCategory($paramData);
            if(!$result){
                return (['errorCode'=>5, 'msg'=>'System error']);
            }
            return $result;
        }catch (Exception $e){
            return (['errorCode'=>5, 'msg'=>'System error']);
        }
    }

    /**
     * 查询产品根据分类ID --崇钢
     * @return mixed
     */
    public function productList(){
        $paramData = request()->post();
        $type = isset($paramData['query_type']) ? $paramData['query_type'] :1;
        //参数校验
        if(empty($paramData['key'])){
            return (['errorCode'=>4, 'msg'=>'Error in input parameter']);
        }
        try{
            //查看key是否存在
            $userList = $this->validateParams->userList();
            if(!array_search($paramData['key'],$userList)){
                return (['errorCode'=>5, 'msg'=>'The key does not exist']);
            }
            $paramData['productStatus'] = 1;
            switch($type){
                case 1:
                    $result = $this->productService->productList($paramData);
                    break;
                case 2:
                    $result = $this->productService->productListSpecial($paramData);
                    break;
            }
            if(!$result){
                return (['errorCode'=>5, 'msg'=>'System error']);
            }
            return $result;
        }catch (Exception $e){
            return (['errorCode'=>5, 'msg'=>'System error']);
        }
    }

    /**
     * 生成签名
     *
     * @return string
     */
    public function makeSign($key)
    {
        //签名步骤三：MD5加密
        $result = md5('fU9wboOsRx9JQDA2'.'&'.$key);
        //所有字符转为小写
        $sign = strtolower($result);
        return $sign;
    }

}
