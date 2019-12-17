<?php
namespace app\seller\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\params\seller\ShippingTemplateParams;
use app\seller\model\ShippingTemplateModel;
use think\Db;
use think\Exception;


/**
 * 开发：钟宁
 * 功能：运费模板
 * 时间：2018-08-07
 */
class ShippingTemplate extends Base
{
    public $model;
    public function __construct()
    {
        parent::__construct();
        $this->model = new ShippingTemplateModel();
    }

    /*
     * 运费模板信息表
     */
    public function lists(){
        try{
            $params = input();
            //参数校验
            $validate = $this->validate($params,(new ShippingTemplateParams())->listRules());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->model->shippingTemplateList($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }

    }

}
