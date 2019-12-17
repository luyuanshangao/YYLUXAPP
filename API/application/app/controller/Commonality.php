<?php
namespace app\app\controller;

use app\common\controller\AppBase;


/**
 * 开发：钟宁
 * 功能：公共基类
 * 时间：2018-09-04
 */
class Commonality extends AppBase
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取语种列表
     * @return mixed
     */
    public function getLangs(){
        try{
            $langMenu = config("Language");
            return apiReturn(['code'=>200,'data'=>$langMenu]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取币种信息
     * @return mixed
     */
    public function getCurrency(){
        try{
            $currencyList = config("Currency");
            $support = config('dx_support_currency');
            //增加字段”CanShow”和“CanPay”
            if(!empty($currencyList)){
                foreach($currencyList as $key => $val){
                    if(in_array($val['Name'] , $support)){
                        $currencyList[$key]['CanPay'] = 1;
                    }else{
                        $currencyList[$key]['CanShow'] = 1;
                    }
                }
            }
            return apiReturn(['code'=>200,'data'=>$currencyList]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }


}
