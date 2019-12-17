<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\CouponParams;
use app\common\params\seller\product\CreateProductParams;
use app\common\params\seller\product\CreateProductSkuParams;
use app\common\params\seller\product\UpdateProductStatusParams;
use app\mall\model\CouponModel;
use app\mall\services\CouponService;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * Coupon接口
 * @author gz
 * 2018-05-25
 */
class Coupon extends Base
{
    public $CouponService;
    public $CouponModel;

    public function __construct()
    {
        parent::__construct();
        $this->CouponService = new CouponService();
        $this->CouponModel = new CouponModel();
    }
    
    /**
     * 根据商家ID，skuID过滤出可用的coupon列表
     * @return mixed
     */
    public function getAvailableCoupon(){
        try{
            $paramData = request()->post();

            $data = $this->CouponService->getAvailableCoupon($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据商家ID，过滤出可用的coupon列表
     * @return mixed
     */
    public function getCouponList(){
        try{
            $paramData = request()->post();

            $data = $this->CouponService->getCouponList($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    public function updateCodeStatus(){
        try{
            $paramData = request()->post();

            $this->CouponService->updateCodeStatus($paramData);
            return apiReturn(['code'=>200, 'data'=>[]]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 新增coupon
     */
    public function addCoupon(){
        try{
            $paramData = request()->post();

            $data = $this->CouponService->addCoupon($paramData);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取coupon信息,根据coupon id
     */
    public function getCouponInfoByCouponId(){
        try{
            $paramData = request()->post();

            $data = $this->CouponService->getCouponInfoByCouponId($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
//        $paramData = request()->post();
//        $res = model("MyCoupon")->getCouponInfoByCouponId($paramData);
//        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /**
     * 根据coupon code获取coupon数据
     * @return mixed
     */
    public function getCouponInfoByCouponCode(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CouponParams())->getCouponInfoByCouponCodeRule());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->CouponModel->getCouponByCouponCode($paramData);
            if ($data['code'] == 1){
                return apiReturn(['code'=>200, 'data'=>$data['data']]);
            }else{
                return apiReturn(['code'=>1003, 'msg'=>$data['msg']]);
            }
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,'getCouponInfoByCouponCode'.$paramData['CouponCode'],$paramData,null,$e->getMessage());
            Log::record('getCouponInfoByCouponCode异常（'.$paramData['CouponCode'].'）：'.$e->getMessage());
            return apiReturn(['code'=>1004, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 商城首页coupon
     * @return mixed
     */
    public function getHomeCouponList(){
        try{
            $paramData = request()->post();

            $data = $this->CouponService->getHomeCouponList($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据ID获取coupon详情
     * @return mixed
     */
    public function getCouponListByIds(){
        try{
            $paramData = request()->post();

            $data = $this->CouponService->getCouponListByIds($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 活动页面，新增coupon add by zhongning 20191121
     */
    public function addCouponByActivityPage(){
        try{
            $paramData = input();
            $paramData['lang'] = DEFAULT_LANG;
            if(empty($paramData['coupon_id']) || empty($paramData['customer_id'])){
                return jsonp(['code'=>1002, 'msg'=>'params error']);
            }
            $data = $this->CouponService->addCoupon($paramData);
            if(!empty($data['code']) && $data['code'] == 5010001){
                $data['code'] = 200;
            }
            return jsonp($data);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return jsonp(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

   

}
