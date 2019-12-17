<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\params\mall\CouponParams;
use app\app\model\CouponModel;
use app\app\services\CouponService;
use think\Exception;
use think\Log;
use think\Monlog;
use app\app\dxcommon\BaseApi;

/**
 * Coupon接口
 * @author gz
 * 2018-05-25
 */
class Coupon extends AppBase
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
            $params = input();
            $paramData = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,//当前语种
                'store_id' => isset($params['store_id']) ? $params['store_id'] : null,
                'categoryPath' => isset($params['categoryPath']) ? $params['categoryPath'] : null,
                'brand_id' => isset($params['brand_id']) ? $params['brand_id'] : null,
                'product_id' => isset($params['product_id']) ? $params['product_id'] : null,
                'CouponStrategy' => [1,3],//手动，自动
                'CouponChannels'=>[1,3],//优惠渠道：1-全站、2-Web站、
                'DiscountLevel'=>1,//1-单品级别优惠，2-订单级别优惠
            ];
            $result = $this->CouponService->getCouponList($paramData);
            //初始化，保持数据结构一直
            if(empty($result)){
                $result['manual'] = array();
                $result['auto'] = array();
            }
            return apiReturn(['code'=>200, 'data'=> $result]);
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
            $params = request()->post();
            $CustomerID = isset($paramsRequest['customer_id']) ? $paramsRequest['customer_id'] : 0;
            $params = input();
            $paramsRequest = [
                'lang' => isset($params['lang']) ? $params['lang'] : $this->lang,//当前语种
                'coupon_id' => isset($params['coupon_id']) ? $params['coupon_id'] : null,
                'customer_id' => $CustomerID,
            ];
            $Rules=[
                ['coupon_id','require|number','coupon_id Must be Required | coupon_id Must be a number'],
                ['lang','require|max:2','Lang Must be Required | Lang Invalid parameter length'],
                ['customer_id','require|number','Customer_id Must be Required | Customer_id Must be a number'],
            ];
            //参数校验
            $validate = $this->validate($paramsRequest, $Rules);
            if (true !== $validate) {
                return json(['code' => 1002, 'msg' => $validate]);
            }
            $resData = $this->productActivityService->addCustomerCoupon($paramsRequest);
            return json($resData);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

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

    /*
    * 优惠券列表
    */
    public function index()
    {
        $baseApi = new BaseApi();
        $post_param = input();
        $lang =isset($post_param['lang']) ? $post_param['lang'] : DEFAULT_LANG;
        $param['path'] = url("Coupon/index",$post_param);
        $param['customer_id'] = $post_param['customer_id'];
        $CouponDatas=[];
        if(isset($post_param['is_used']) && $post_param['is_used'] == 2){
            $param['is_used'] = $post_param['is_used'];
            //$param['end_time'] = ['gt',time()] ;
        }elseif(isset($post_param['is_used']) && $post_param['is_used'] == 1){
                $param['is_used'] = $post_param['is_used'];
                $param['end_time'] = ['gt',time()] ;
        }else{
            $param['end_time'] = ['lt',time()] ;
        }

        $param['page_size'] = (int)input('page_size',21);
        $param['page'] = (int)input('page',1);

        $data = $baseApi->getCouponList($param);
        if($data['code']==200 && !empty($data['data']['data'])){
            $CouponIds = array();
            $SellerId = array();
            foreach ($data['data']['data'] as $key=>$value){
                if($value['end_time']<time()){
                    $data['data']['data'][$key]['is_expired'] = 0;
                }else{
                    $data['data']['data'][$key]['is_expired'] = 1;
                }
                if(!in_array($value['coupon_id'],$CouponIds)){
                    $CouponIds[] = $value['coupon_id'];
                }

            }
            $CouponData = $baseApi->getCouponByCouponIds(['CouponIds'=>$CouponIds]);//根据优惠券ID数组获取商城优惠券

            if($CouponData['code'] == 200 && !empty($CouponData['data'])){
                foreach ($CouponData['data'] as $key=>$value){
                    $CouponData['data'][$key]['DescriptionBrief'] = isset($value['Description'][cookie('DXGlobalization_lang')]['Brief'])?$value['Description'][cookie('DXGlobalization_lang')]['Brief']:$value['Description']['en']['Brief'];
                    $CouponData['data'][$key]['CouponRuleType'] = isset($value['CouponRuleSetting']['CouponRuleType'])?$value['CouponRuleSetting']['CouponRuleType']:1;
                    if(!in_array($value['SellerId'],$SellerId)){
                        $SellerId[] = $value['SellerId'];
                    }
                }
                Log::record('SellerId:'.json_encode($SellerId));
                $SellerName = $baseApi->getSellerName($SellerId);//根据优惠券ID数组获取商城优惠券
                Log::record('SellerName:'.json_encode($SellerName));
                foreach ($data['data']['data'] as $key=>$value){
                    if(isset($CouponData['data'][$value['coupon_id']]) && isset($CouponData['data'][$value['coupon_id']]['CouponStatus']) && $CouponData['data'][$value['coupon_id']]['CouponStatus'] == 3){
                        //$data['data']['data'][$key]['CouponData'] = $CouponData['data'][$value['coupon_id']];
                        $type=$data['data']['data'][$key]['type'] = $CouponData['data'][$value['coupon_id']]['DiscountType']['Type'];
                        $CouponRuleType=$data['data']['data'][$key]['CouponRuleType'] = isset($CouponData['data'][$value['coupon_id']]['CouponRuleSetting']['CouponRuleType'])?$CouponData['data'][$value['coupon_id']]['CouponRuleSetting']['CouponRuleType']:1;

                        if($type==1){
                            if($CouponRuleType==1){
                                $title='All items in store';
                            }elseif($CouponRuleType==2){
                                $title='Limited product';
                            }else{
                                $title='Full station use';
                            }
                        }else{
                            $title='Full station use';
                        }
                        $data['data']['data'][$key]['title'] = $title;
                        $data['data']['data'][$key]['DescriptionBrief'] = isset($CouponData['data'][$value['coupon_id']]['Description']['en']['Brief'])?$CouponData['data'][$value['coupon_id']]['Description']['en']['Brief']:'';
                        $data['data']['data'][$key]['DescriptionDetails'] = isset($CouponData['data'][$value['coupon_id']]['Description']['en']['Details'])?$CouponData['data'][$value['coupon_id']]['Description']['en']['Details']:'';
                        $data['data']['data'][$key]['SellerName'] = $SellerName['data'][$CouponData['data'][$value['coupon_id']]['SellerId']];
                        unset($data['data']['data'][$key]['delete_time'],$data['data']['data'][$key]['is_expired']);
                    }else{
                        unset($data['data']['data'][$key]);
                    }
                }
            }
        }
        $data['data']['code']=200;
        $data['data']['data']=array_values($data['data']['data']);
        return $data['data'];
    }


}
