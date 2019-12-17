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
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
        $this->productService = new ProductService();
    }

    /**
     * 查询产品
     * @return mixed
     */
    public function query(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new ProductParams())->queryProductRules());
        if(true !== $validate){
            return (['errorCode'=>4, 'msg'=>'Error in input parameter']);
        }
        try{
            //查看key是否存在
            $userList = (new ProductParams())->userList();
            if(!array_search($paramData['key'],$userList)){
                return (['errorCode'=>5, 'msg'=>'System error']);
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
        $validate = $this->validate($paramData,(new ProductParams())->queryProductRules());
        if(true !== $validate){
            return (['errorCode'=>4, 'msg'=>'Error in input parameter']);
        }
        try{
            //查看key是否存在
            $userList = (new ProductParams())->userList();
            if(!array_search($paramData['key'],$userList)){
                return (['errorCode'=>5, 'msg'=>'System error']);
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
