<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\params\mallextend\emailtemplate\EmailTemplateParams;
use app\mallextend\model\EmailtemplateModel;
use think\Controller;
/**
 * 邮件模板接口类
 * Class EmailTemplate
 * @author tinghu.liu 2018/5/31
 * @package app\mallextend\EmailTemplate
 */
class EmailTemplate extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 根据条件获取邮件模板信息
     * @return array|mixed
     */
    public function getData($params_data=''){
        $params = !empty($params_data)?$params_data:request()->post();
        //参数校验
        $validate = $this->validate($params,(new EmailTemplateParams())->getDataRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new EmailtemplateModel();
            $data = $model->getDataByParams($params);
            if (!empty($data)){
                return apiReturn(['code'=>200, 'msg'=>'success', 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'没有符合条件的数据']);
            }
        }catch(\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'system error:'.$e->getMessage()]);
        }
    }

}
