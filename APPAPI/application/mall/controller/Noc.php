<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2018/8/9
 * Time: 12:01
 */

namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\ProductParams;
use app\mall\services\NocService;

class Noc extends Base
{
    private $NocService;

    public function __construct()
    {
        parent::__construct();
        $this->NocService = new NocService();
    }

    /**
     * 根据NOC类别映射
     * @return mixed
     */
    public function getClassMap(){
        try{
            $result = $this->NocService->getNocClassMap();
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            return apiReturn(['code'=>100000001, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取NOC类别
     * @return mixed
     */
    public function getClass(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new ProductParams())->nocGetClassRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $class_id = $this->NocService->getNocClass($params);
            return apiReturn(['code'=>200, 'class_id'=>$class_id]);
        }catch (Exception $e){
            return apiReturn(['code'=>100000001, 'msg'=>$e->getMessage()]);
        }
    }
}