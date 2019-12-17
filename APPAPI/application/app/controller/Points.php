<?php
/**
 * 用户积分
 * Created by PhpStorm.
 * User: Kevin
 * Date: 2018/3/8
 * Time: 16:55
 */
namespace app\app\controller;

use app\app\dxcommon\BaseApi;
use \think\Session;
use \think\Cookie;
use \think\Request;
use vendor\aes\aes;
use app\common\controller\AppBase;
class Points extends AppBase{

    public function addPoints(){
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->addPoints($data);
        return $res;
    }

    public function getPointsBasicInfo(){
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->getPointsBasicInfo($data);
        return $res;
    }

    public function getPointsDetailsList(){
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->getPointsDetailsList($data);
        return $res;
    }

    public function IncPoints(){
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->IncPoints($data);
        return $res;
    }

    public function DecPoints(){
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->DecPoints($data);
        return $res;
    }

    /*
    * SC首页
    */
    public function MyPoints()
    {
        $coupon_id = input("CustomerID");
        $baseApi = new BaseApi();
        /*获取用户积分详情*/
        //月份
        $_month = month_verify_special(input("month"));
//            $points_type = (int)input("points_type",1);
        $PointsBasicInfo = $baseApi->getPointsBasicInfo(['CustomerID'=>$coupon_id,'type'=>1]);
        $param['CustomerID'] = $coupon_id;
        $param['IsNewDx'] = 1;

        $param['page_size'] = (int)input('page_size',20);
        $param['page'] = (int)input('page',1);
        $param['path'] = url("Points/MyPoints");
        $month = $_month;

        if(!empty($month)){
            $param["CreateTime"]=array('gt',strtotime("-$month month"));
        }
        $param = array_filter($param);

        $data = $baseApi->getPointsDetailsList($param);
        if(!empty($data['data'])&&!empty($PointsBasicInfo['data'])){
            $data['data']['code']=200;
            $data['data']['PointsBasicInfo']=$PointsBasicInfo['data'];
            return $data['data'];
        }else{
            return $this->result([]);
        }
    }

    /*
     * 积分兑换优惠券
     * */
    public function ExchangeCoupon(){
        $cstomer=input("");
        $coupon_id = $cstomer['CouponId'];
        $cstomer_id = $cstomer['CustomerID'];
        $points_type = input("points_type",1);
        $baseApi = new BaseApi();
        /*获取用户积分详情*/
        $PointsBasicInfo = $baseApi->getPointsBasicInfo(['CustomerID'=>$cstomer_id,'type'=>$points_type]);
        /*用户可用积分*/
        $UsableCount = isset($PointsBasicInfo['data']['UsableCount'])?$PointsBasicInfo['data']['UsableCount']:0;
        if(empty($coupon_id)){
            $param["CouponIds"] = config("ExchangeCoupons");
            /*$param['Type'] = 1;//优惠类型：1-代金券、2-赠送券、3-折扣券、4-指定售价
            $param['TypeOne'] = $UsableCount/10;//Type=1(代金券)时，优惠券面值，价格单位$
            $param['CouponRuleType'] = 3;//优惠券规则：1-全店铺使用，2-制定限制规则，3-全站使用*/
            $data = $baseApi->getMallCouponList($param);
            return $this->fetch('',['data'=>$data,'PointsBasicInfo'=>$PointsBasicInfo['data'],"points_type"=>$points_type]);
        }else{
            /*获取优惠券详情*/
            $CouponInfo = $baseApi->getCouponByCouponId(["CouponId"=>$coupon_id]);

            if(isset($CouponInfo['data']['DiscountType']['TypeOne']) && $UsableCount >= $CouponInfo['data']['DiscountType']['TypeOne']*10){
                $PaymentPassword['CustomerID'] = $cstomer_id;
                $PaymentPassword['Password'] = input("PaymentPassword");
                $confirmPaymentPassword = $baseApi->confirmPaymentPassword($PaymentPassword);
                if($confirmPaymentPassword['code'] != 200){
                    return $confirmPaymentPassword;
                }
                $CouponParam['CouponId'] = $coupon_id;
                $CouponParam['flag'] = 2;
                $Coupondata = $baseApi->getCouponCodeByCouponId($CouponParam);
                $addCoupon['coupon_sn'] = $Coupondata['data']['coupon_code'];
                $CouponInfo['data']['DiscountType']['TypeOne']*10;
                $addCoupon['customer_id'] = $cstomer_id;
                $addCoupon['coupon_id'] = $coupon_id;
                $addCoupon['start_time'] = time();
                if($CouponInfo['data']['CouponTime']['EndTime']<strtotime('+1month')){
                    $addCoupon['end_time'] = $CouponInfo['data']['CouponTime']['EndTime'];
                }else{
                    $addCoupon['end_time'] = strtotime('+1month');
                }
                $addCoupon['type'] = 1;
                $addCoupon_res = $baseApi->addCoupon($addCoupon);
                if($addCoupon_res['code'] == 200){
                    $DecPoints['CustomerID'] = $cstomer_id;
                    if($points_type == 1){
                        $DecPoints['dx_points'] = $CouponInfo['data']['DiscountType']['TypeOne']*10;
                    }else{
                        $DecPoints['referral_points'] = $CouponInfo['data']['DiscountType']['TypeOne']*10;
                    }
                    $DecPoints['Memo'] = "ExchangeCoupon";
                    $DecPoints['IsNewDx'] = 0;
                    $DecPoints['Operator'] = $cstomer['UserName'];
                    $DecPoints['OperateReason'] = 18;
                    $DecPoints['ReasonDetail'] = "ExchangeCoupon";
                    $DecPoints['Status'] = 1;
                    $decPoints_res = $baseApi->decPoints($DecPoints);
                    return $decPoints_res;
                }else{
                    return $addCoupon_res;
                }
            }else{
                return ['code'=>1002,'msg'=>'Sorry, your points are not enough to redeem!'];
            }
        }
    }
}
