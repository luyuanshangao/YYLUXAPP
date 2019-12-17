<?php
namespace app\windcontrol\controller;
use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\common\controller\Base;
use think\Db;
use think\Log;
use app\common\helpers\RedisClusterBase;
use app\common\params\windcontrol\WindControlParams;
use app\windcontrol\services\WindControlService;

/**
 * LIS系统 接口
 * author: Wang
 * AddTime:2018-12-08
 */

class SpecialList extends Base{
    const sys_config = 'sys_config';

    //'1、检查位正常，2、为白名单，3、黑名单，4、为线下免测，5为异常'
    const CHECK_NORMAL  = 1;
    const CHECK_WHITE   = 2;
    const CHECK_BLACK   = 3;
    const CHECK_OFFLINE = 4;
    const CHECK_EXCEPT  = 5;

    //
    const RETURN_CODE_SUCESS    = 200;//通过
    const RETURN_CODE_WHITE     = 201;//通过（白名单中）
    const RETURN_CODE_OFFLINE   = 202;//通过（线下支付）
    const RETURN_CODE_MISS      = 1000;//不通过（参数错误等payment那边的错误）
    const RETURN_CODE_FAILED    = 1001;//不通过（不通过或在黑名单中或进入以色列风控）
    const RETURN_CODE_EXCEPT    = 1002;//不通过（异常，风控这边的错误）

    private $windControlService;

    public function __construct(){
        parent::__construct();
        $this->admin = Db::connect('db_admin');
        $this->windControlService = new WindControlService();
    }

    /**
     * 判断admin新增数据是否正确
     * [SpecialList description]
     */
    public function SpecialList(){
        $data = request()->post();
        if(empty($data)){     return $data; }
        if(!empty($data["cic_id"])){
            $result = model("SpecialListModel")->LisLogisticsDetail($data);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 风控事前
     * 流程：
     * 1、先检测渠道，下线的直接通过返回无需检测 线上的进入检测
     * 2、检测白名单、只要存在白名单则直接通过返回
     * 3、检测黑名单、只要存在黑名单则直接拒绝
     * [beforehand description]
     * @return [type] [description]
     */
    public function Beforehand(){
        
        $data = request()->post();

        if( empty($data) || empty($data['TransactionChannel']) || empty($data['TransactionType']) ){
            riskLog('error',__FILE__,__LINE__,'请求参数为空');
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'传递数据为空']);
        }

        //判断支付方式是否为线下，线下则直接通过
        $offRes = $this->windControlService->isOffline($data['TransactionChannel'],$data['TransactionType']);
        if( $offRes ){
            riskLog('info',__FILE__,__LINE__,"线下支付直接通过",$data);
            return apiReturn(['code'=>self::RETURN_CODE_OFFLINE, 'msg'=>'属于线下支付直接通过']);
            /*
            $res = $this->windControlService->addBeforehandInfo($data,'属于线下支付直接通过',self::CHECK_OFFLINE);
            if( $res ){
                riskLog('info',__FILE__,__LINE__,"线下支付直接通过",$data);
                return apiReturn(['code'=>self::RETURN_CODE_OFFLINE, 'msg'=>'属于线下支付直接通过']);
            }
            riskLog('info',__FILE__,__LINE__,"系统异常",$data);
            return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'系统异常']);
            */
            
        }

        //基本参数校验
        $validate = $this->validate($data,WindControlParams::baseRules());
        if( true !== $validate ){
            riskLog('error',__FILE__,__LINE__,'请求参数错误:'.$validate,$data);
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>$validate]);
        }   

        if( !is_array($data['SkuInfos']) ){
            riskLog('error',__FILE__,__LINE__,'sku参数格式错误',$data);
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'sku参数格式错误']);
        }

        //sku校验
        foreach ($data['SkuInfos'] as $SkuInfos) {
            $validate = $this->validate($SkuInfos,WindControlParams::skuRules());
            if( true !== $validate ){
                riskLog('error',__FILE__,__LINE__,"sku参数错误:{$validate}",$data);
                return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'sku参数错误']);
            }    
        }
        
        //ShippingAddress 校验
        $validate = $this->validate($data['ShippingAddress'],WindControlParams::shippingRules());
        if( true !== $validate ){
            riskLog('error',__FILE__,__LINE__,"ShippingAddress参数错误:{$validate}",$data);
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'ShippingAddress参数错误']);
        }


        //查询黑白名单
        $listRes = $this->windControlService->isBlackOrWihte($data);

        //如果在黑名单中
        if( RISK_BLACK_FLAG == $listRes ){

            $res = $this->windControlService->addBeforehandInfo($data,'黑名单不予通过',self::CHECK_BLACK);
            if( $res ){
                riskLog('info',__FILE__,__LINE__,"黑名单不予通过",$data);
                return apiReturn(['code'=>self::RETURN_CODE_FAILED, 'msg'=>'属于黑名单，不予通过']);
            }
            riskLog('info',__FILE__,__LINE__,"系统异常",$data);
            return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'系统异常']);

        }else if( RISK_WHITE_FLAG == $listRes ){

            $res = $this->windControlService->addBeforehandInfo($data,'白名单直接通过',self::CHECK_WHITE);
            if( $res ){
                riskLog('info',__FILE__,__LINE__,"白名单直接通过",$data);
                return apiReturn(['code'=>self::RETURN_CODE_WHITE, 'msg'=>'白名单直接通过']);
            }
            riskLog('info',__FILE__,__LINE__,"系统异常",$data);
            return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'系统异常']);

        }
        
        //查询是否在地址黑名单中
        $addressRes = $this->windControlService->inBlackAddress($data);
        if(!empty($addressRes)){

            $res = $this->windControlService->addBeforehandInfo($data,'地址在黑名单中',self::CHECK_BLACK);
            if( $res ){
                riskLog('info',__FILE__,__LINE__,"地址在黑名单中",$data);
                return apiReturn(['code'=>self::RETURN_CODE_FAILED, 'msg'=>'地址在黑名单中，不予通过']);
            }
            riskLog('info',__FILE__,__LINE__,"系统异常",$data);
            return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'系统异常']);

        }

        $res = $this->windControlService->addBeforehandInfo($data,'检测通过',self::CHECK_NORMAL);
        if( $res ){
            riskLog('info',__FILE__,__LINE__,"事前风控检测通过",$data);
            return apiReturn(['code'=>self::RETURN_CODE_SUCESS, 'msg'=>'检测通过']);
        }
        riskLog('info',__FILE__,__LINE__,"系统异常",$data);
        return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'系统异常']);
        
    }

    /**
     * 风控事后
     * [afterwards description]
     * @return [type] [description]
     */
    public function Afterwards(){

        $data = request()->post();

        if( empty($data) || empty($data['PaymentChannel']) || empty($data['PaymentMethod']) ){
            riskLog('error',__FILE__,__LINE__,'请求参数为空');
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'传递数据为空']);
        }


        $data['AmountUsd'] = 0;
        if(!empty($data['Amount']) && $data['ExchangeRate']){
            $AmountUsd = sprintf("%.2f",$data['Amount']/$data['ExchangeRate']);
            $data['AmountUsd'] = $AmountUsd;
        }
        //判断支付方式是否为线下，线下则直接通过
        $offRes = $this->windControlService->isOffline($data['PaymentChannel'],$data['PaymentMethod']);
        if( $offRes ){
            
            riskLog('info',__FILE__,__LINE__,"线下支付直接通过",$data);
            return apiReturn(['code'=>self::RETURN_CODE_OFFLINE, 'msg'=>'属于线下支付直接通过']);
            /*
            $res = $this->windControlService->addAfterhandInfo($data);
            if( $res ){
                riskLog('info',__FILE__,__LINE__,"线下支付直接通过",$data);
                return apiReturn(['code'=>self::RETURN_CODE_OFFLINE, 'msg'=>'属于线下支付直接通过']);
            }
            riskLog('info',__FILE__,__LINE__,"系统异常",$data);
            return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'系统异常']);
            */
        }

        //基本参数校验
        $validate = $this->validate($data,WindControlParams::afterBaseRules());

        if( true !== $validate ){
            riskLog('error',__FILE__,__LINE__,'请求参数错误:'.$validate,$data);
            $this->windControlService->addAfterhandInfo($data,1,'payment请求参数错误');
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'请求参数错误']);
        } 

        //ShippingAddress 校验
        $validate = $this->validate($data['ShippingAddress'],WindControlParams::afterShippingRules());
        if( true !== $validate ){
            riskLog('error',__FILE__,__LINE__,"ShippingAddress参数错误:{$validate}",$data);
            $this->windControlService->addAfterhandInfo($data,1,'payment请求参数错误');
            return apiReturn(['code'=>self::RETURN_CODE_MISS, 'msg'=>'ShippingAddress参数错误']);
        }

        //增加事后风控记录
        $afterId = $this->windControlService->addAfterhandInfo($data);
        if( !$afterId ){
            riskLog('error',__FILE__,__LINE__,"增加事后风控请求记录失败",$data);
            return apiReturn(['code'=>self::RETURN_CODE_EXCEPT, 'msg'=>'增加事后风控请求记录失败']);   
        }

        //事后风控校验
        $result = $this->windControlService->riskVerify($data,$afterId);
        riskLog('info',__FILE__,__LINE__,'事后风控校验结果,afterId:'.$afterId,$result);

        return  apiReturn($result);
    }

}