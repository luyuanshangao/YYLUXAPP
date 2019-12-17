<?php
namespace app\seller\controller;

use app\common\controller\Base;
use app\seller\model\WholesaleInquiry as WholesaleInquiryModel;
use app\common\params\seller\WholesaleInquiryParams;
use think\Controller;
/**
 * 批发询价接口
 * Class WholesaleInquiry
 * @author tinghu.liu 2018/06/11
 * @package app\seller\controller
 */
class WholesaleInquiry extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 新增批量询价数据
     * @return mixed
     */
    public function addData()
    {
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new WholesaleInquiryParams())->addDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new WholesaleInquiryModel();
            $param['addtime'] = time();
            if ($model->addData($param)){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1006, 'msg'=>'添加数据失败']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1007, 'msg'=>'程序异常'.$e->getMessage()]);
        }
    }

}
