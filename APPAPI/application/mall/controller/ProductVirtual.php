<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\ProductVirtualParams;
use app\mall\services\BaseService;
use app\mall\services\ProductVirtualService;
use think\Db;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * 产品接口
 */
class ProductVirtual extends Base
{
    public $productVirtualService;
    public function __construct()
    {
        parent::__construct();
        $this->productVirtualService = new ProductVirtualService();
    }

    /**
     * 查询产品单个信息
     * @return mixed
     */
    public function getProductVirtual(){
        $paramData = request()->post();
        $paramData['product_id'] = 10010;
        if(!isset($paramData['product_id'])){
            return apiReturn(['code'=>1000000021, 'msg'=>'参数有误']);
        }
        try{
            $data = $this->productVirtualService->getProductVirtual($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,'ling = '.$e->getLine().'msg = '.$e->getMessage());

            return apiReturn(['code'=>1000000022, 'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 查询多个虚拟产品
     */
    public function getProductVirtualList(){
        $paramData = request()->post();
        try{
            $result = $this->productVirtualService->selectProductVirtual($paramData);
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>100000001, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 处理库存和销量处理
     * @return mixed
     * {
        "product_id":1001,
        "product_nums":0.2,
        "order_number":"",
        "flag":1, //回滚库存

     * }
     * mall/ProductVirtual/synInventoryAndSalesCount
     */
    public function synInventoryAndSalesCount(){
        $paramData = request()->post();
        $paramData = input();
        try{
            $validate = $this->validate($paramData,(new ProductVirtualParams())->synInventoryAndSalesCountRules());
            if(true !== $validate){
                Log::record('区块链处理库存和销量处理错误，validate：'.$validate,'notice');
                return apiReturn(['code'=>1001, 'msg'=>$validate]);
            }
            $result = $this->productVirtualService->synInventoryAndSalesCount($paramData);
            if ($result){
                return apiReturn(['code'=>200, 'data'=>'success']);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'operation failed.']);
            }
        }catch (Exception $e){
            $err = $e->getMessage().', '.$e->getFile().'['.$e->getLine().']';
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$err);
            return apiReturn(['code'=>2001, 'msg'=>$e->getMessage()]);
        }
    }

}
