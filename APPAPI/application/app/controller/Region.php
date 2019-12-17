<?php
namespace app\app\controller;

use app\app\model\RegionModel;
use app\common\controller\AppBase;
use think\Controller;
use think\Exception;

/**
 * 国家区域接口
 */
class Region extends AppBase
{
    public $regionModel;
    public function __construct()
    {
        parent::__construct();
        $this->regionModel = new RegionModel();
    }

    /**
     * 获取商城头部国家数据
     */
    public function getCountry(){
        try{
            $result = $this->regionModel->getHeaderCountry();
            return apiReturn(['code'=>200,'data'=>$result]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

}
