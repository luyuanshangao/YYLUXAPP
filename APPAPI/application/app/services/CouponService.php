<?php
/**
 * Coupon服务类
 * User: tinghu.liu
 * Date: 2018/10/12
 */
namespace app\app\services;


use app\app\model\CouponModel;
use app\app\model\ProductClassModel;
use app\app\model\ProductModel;
use app\common\helpers\CommonLib;
use app\common\services\logService;
use think\Log;

class CouponService extends BaseService
{
    private $CommonService = '';
    private $productService;
    private static $DiscountTypeAboutPrice = ['1','3','4'];
    //$enableSelectActive的描述需要做成多语言的

    public function __construct(){
        parent::__construct();
        $this->CommonService = new CommonService();//公共服务类
        $this->productService = new ProductService();
    }

    /**
     * 获取自动coupon
     * @param int $value 1表示store coupon ，2表示dx coupon
     */
    public function getAutoCoupon($Params){
        $GetCouponParams['CouponChannels'] = array(1,3);//1-全站、2-Web站
        $GetCouponParams['CouponRuleType'] = $Params['CouponRuleType'];//优惠券规则：1-全店铺使用，2-制定限制规则，3-全站使用
        $GetCouponParams['CouponStrategy'] = 2;//表示自动coupon
        $GetCouponParams['Lang'] = isset($Params['Lang'])?$Params['Lang']:'en';
        $GetCouponParams['IsAutoCouponGetCouponCode'] = 1;//自动coupon需要从该接口拿到couponCode
        //请求coupon接口，获取哪些自动动coupon是可用的
        $Url = MALL_API."/mall/Coupon/getAvailableCoupon";
        $CouponData = doCurl($Url,$GetCouponParams,null,true);
        //拿到所有的coupon_id,获取该coupon使用的次数
        if(isset($CouponData['code']) && $CouponData['code'] == 200 && isset($CouponData['data']) && count($CouponData['data']) > 0){
            //拿到所有的coupon_id,获取该coupon使用的次数
            $TmpArr = array();
            foreach ($CouponData['data'] as $k=>$v){
                $TmpArr[] = $v['CouponId'];
            }
            $ReturnData['code'] = 1;
            $CouponDataTmp = $this->calCouponUseAmount($CouponData,$TmpArr);
            //计算coupon优惠的汇率问题
            //$ReturnData['data'] = $this->calCouponRate($CouponDataTmp,$Params['Currency']);
            $ReturnData['data'] = $CouponDataTmp;
        }else{
            //没有数据
            $ReturnData['code'] = 0;
            $ReturnData['msg'] = "error";
        }
        return $ReturnData;
    }

    /**
     * 获取手动coupon
     * @param int CouponRuleType 1表示store coupon ，3表示dx coupon
     *
     */
    public function getManualCoupon($Params){
        //type=1获取store coupon type=3获取dx coupon
        $Data['customer_id'] = $Params['customer_id'];
        $Data['type'] = $Params['type'];
        $Data['is_page'] = 0;
        //到CIC获取用户已领取的coupon
        $Url = CIC_APP."/cic/MyCoupon/getCouponList";
        //此时领取到的coupon是经过type过滤的，要么是商家券，要么是平台券
        $CouponInfo = doCurl($Url,$Data,null,true);
        $GetManualCouponParams['CouponChannels'] = array(1,3);//1-全站、2-Web站
        $GetManualCouponParams['CouponRuleType'] = $Params['CouponRuleType'];//优惠券规则：1-全店铺使用，2-制定限制规则，3-全站使用
        $GetManualCouponParams['CouponStrategy'] = 1;//表示手动coupon
        $GetManualCouponParams['Lang'] = isset($Params['Lang'])?$Params['Lang']:'en';
        $GetManualCouponParams['IsAutoCouponGetCouponCode'] = 0;
        if(isset($CouponInfo['code']) && $CouponInfo['code'] == 200){
            if(isset($CouponInfo['data']) && count($CouponInfo['data']) > 0){
                $TmpArr = array();
                $TmpCounpon_code = array();
                foreach ($CouponInfo['data'] as $k=>$v){
                    $TmpArr[] = $v['coupon_id'];
                    $TmpCounpon_code[$v['coupon_id']] = $v['coupon_sn'];//coupon_code
                }
                $GetManualCouponParams['coupon_id'] = $TmpArr;
                //请求coupon接口，获取哪些手动coupon是可用的
                $Url = MALL_API."/mall/Coupon/getAvailableCoupon";
                $CouponData = doCurl($Url,$GetManualCouponParams,null,true);
                if(isset($CouponData['code']) && $CouponData['code'] == 200 && isset($CouponData['data']) && count($CouponData['data']) > 0){
                    foreach ($CouponData['data'] as $k=>$v){
                        $CouponData['data'][$k]['coupon_code'] = isset($TmpCounpon_code[$v['CouponId']]);
                    }
                    //拿到所有的coupon_id,获取该coupon使用的次数
                    $ReturnData['code'] = 1;
                    $CouponDataTmp = $this->calCouponUseAmount($CouponData,$TmpArr);
                    $ReturnData['data'] = $CouponDataTmp;
                }else{
                    //没有数据
                    $ReturnData['code'] = 0;
                    $ReturnData['data'] = "data is no exists";
                }
            }else{
                //没有数据
                $ReturnData['code'] = 0;
                $ReturnData['data'] = "data is no exists";
            }
        }else{
            //请求出错
            $ReturnData['code'] = 0;
            $ReturnData['data'] = "request is error";
        }
        return $ReturnData;
    }

    /**
     * 转换coupon的汇率
     * @param $CouponData
     * @param $Currency
     * 因为折扣比率的价格是根据产品的具体价格来计算的，所以这个功能无法实现在源头转换汇率
     */
    public function calCouponRate(&$CouponData,$Currency){
        if($Currency && $Currency != 'USD'){
            if(is_array($CouponData)){
                foreach ($CouponData as $k=>$v){
                    if(is_array($v)){
                        foreach ($v as $k1=>$v1){
                            //如果是这个coupon的折扣是有关价格的，需要把相关的价格转成指定汇率的价格
                            if(in_array($v1['DiscountType']['Type'],self::$DiscountTypeAboutPrice)){

                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 过滤数据
     * @param array $CouponData
     * @param array $CounponId
     * @param array $SkuIdArr sku信息，如果coupon是有规则的，需要根据商品信息过滤掉
     * @param string $CountryCode
     *
     * */
    public function calCouponUseAmount($CouponData,$CounponId){
        $Data['coupon_ids'] = $CounponId;
        $Url = CIC_APP."/cic/MyCoupon/getCouponCount";
        //此时领取到的coupon是经过type过滤的，要么是商家券，要么是平台券
        $CouponUseAmountInfo = doCurl($Url,$Data,null,true);
        if(isset($CouponUseAmountInfo['code']) && $CouponUseAmountInfo['code'] == 200 && isset($CouponUseAmountInfo['data']) && count($CouponUseAmountInfo['data']) > 0){
            $CouponUseAmountInfo = $CouponUseAmountInfo['data'];
            foreach ($CouponData['data'] as $k=>$v){
                if(is_array($v)){
                    //foreach ($v as $k1=>$v1){
                        //过滤掉使用次数超过规定的
                        if(isset($CouponUseAmountInfo[$v['CouponId']]) && $v['CouponNumLimit'] && isset($v['CouponNumLimit']['Num']) && $CouponUseAmountInfo[$v['CouponId']] >= $v['CouponNumLimit']['Num']){
                            unset($CouponData['data'][$k]);
                        }
                    //}
                }
            }
        }else{
            //数据有误处理
        }
        return $CouponData;
    }


    /**
     * 旧的使用coupon
     * @param $Uid
     * @param $Params
     * @return array
     */
    public function OLDuseCoupon($Uid,$Params){
        $StoreId = $Params['StoreId'];
        $productId = isset($Params['productId'])?$Params['productId']:0;
        $SkuId = isset($Params['SkuId'])?$Params['SkuId']:0;
        $Qty = isset($Params['Qty'])?$Params['Qty']:1;
        //来源：0-默认正常情况，1-计算使用coupon后的数据，不用更新购物车 tinghu.liu 20191029
        $FromFlag = isset($Params['FromFlag'])?$Params['FromFlag']:0;
        //计算时候获取产品信息是否使用缓存，如果不是真正的使用coupon，可以使用缓存提升性能 tinghu.liu 20191101
        $FromForNumsAndPriceForReset = 0;
        $GetProductIsCache = false;
        if ($FromFlag == 1){
            $FromForNumsAndPriceForReset = 1;
            $GetProductIsCache = true;
        }
        $DiscountLevel = $Params['DiscountLevel'];
        $CouponId = $Params['CouponId'];
		$CouponCode = $Params['CouponCode'];
        $IsSellerSku = $Params['DiscountLevel'];//如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
        $Lang = $Params['Lang'];
        $Currency = $Params['Currency'];
        //国家定价功能 add by zhongning 20190523
        $ShipTo = isset($Params['country_code']) ? $Params['country_code'] : null;
        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        //$this->cancelCoupon($Uid,$Params);//先取消已使用的coupon，再使用已选择的coupon
        //跟数据库交互判断是否可以使用()
        $AllPrice = 0;
        $AllQty = 0;
        //获取cartInfo信息
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if($IsSellerSku == 2){
            if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'])){
                $TmpAllSku = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'];
                foreach ($TmpAllSku as $k=>$v){
                    if(is_array($v)){
                        foreach ($v as $k1=>$v1){
                            //判断是否选中，过滤掉未选中、没有运输方式的产品【初始化总价格、总数量】
                            if (
                                isset($v1['IsChecked']) && $v1['IsChecked'] == 1
                                && $v1['ShippModelStatusType'] != 3
                            ){
                                $AllPrice += ($v1['ProductPrice']*$v1['Qty']); //获取产品总价
                                $AllQty += $v1['Qty']; //产品数量
                            }

                        }
                    }
                }
            }else{
                //不存在此商品
                return false;
            }
        }else if($IsSellerSku == 1){
            //sku
            if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId])){
                $AllQty = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Qty'];
                $AllPrice = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['ProductPrice'] * $AllQty;
            }else{
                //不存在此商品
                return false;
            }
        }
        //这里可以考虑使用cart本身的coupon，这样数据才会一致性
        //或者是因为这里是使用coupon，所以直接走获取coupon的方法能获取到最新数据？
        $CouponInfo = $this->getCoupon($Params);

        //echo json_encode($CouponInfo);//die;
        if(!isset($CouponInfo['data']) || !isset($CouponInfo['code']) || $CouponInfo['code'] != 1){
            //获取信息出错
            return false;
        }
        $CouponInfo = $CouponInfo['data'];
        $DiscountType = array();
        $UseFlag = 0;//把是否可用标识设为不可用
        $DiscountType['code'] = 0;
        $DiscountType['Type'] = 0;//等于0表示赠品出错了

        $CicCouponId = 0; //初始化值为0，为了避免找不到数据报错情况 2190329 tinghu.liu

        foreach ($CouponInfo as $k=>$v){
            if(isset($v['CouponId']) && $v['CouponId'] == $CouponId && isset($v['coupon_code']) && $v['coupon_code']){
                $CouponCode = $v['coupon_code'];
                //增加cic couponID tinghu.liu 20190326
                $CicCouponId = isset($v['cic_coupon_id'])?$v['cic_coupon_id']:0;

                /**
                 * 20181206  判断coupon是否可用 重新获取订单级别 符合限定规则的产品总价格、总数量 start
                 * $IsSellerSku：1-sku级别，2-seller级别
                 */
                $_coupon_is_useable = false;
                if($IsSellerSku == 2){ //seller级别
                    if(
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'])
                        )
                        ||
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'])
                        )
                    ){
                        if (
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'][$CouponId]['isUsable'] == 1
                            )
                            ||
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['coupon'][$CouponId]['isUsable'] == 1
                            )
                        ){
                            $_coupon_is_useable = true;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else if($IsSellerSku == 1){ //sku级别
                    if(
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon']))
                        ||
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'])
                        )
                    ){
                        if (
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon'][$CouponId]['isUsable'] == 1
                            )
                            ||
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'][$CouponId]['isUsable'] == 1
                            )
                        ){
                            //coupon可用
                            $_coupon_is_useable = true;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }
                //coupon可用，重新获取订单级别 符合限定规则的产品总价格、总数量
                if ($_coupon_is_useable){
                    //【订单级别情况下】
                    if ($IsSellerSku == 2){
                        $this->getNumsAndPriceForResetCouponUseable($v, $CartInfo, $Uid, $StoreId, $ShipTo, $AllQty, $AllPrice);
                    }
                }else{
                    return false;
                }

                //获取coupon规则中的数量限制开始值，后面赠送券倍数使用时用到 20190329 tinghu.liu
                $coupon_start_num = 0;
                if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                    //查看数量是否在范围
                    $coupon_start_num = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:1;
                }

                /**
                 * 20181206  判断coupon是否可用 重新获取订单级别 符合限定规则的产品总价格、总数量 end
                 * $IsSellerSku：1-sku级别，2-seller级别
                 */

                //单品级别的优惠
                if(isset($v['DiscountType']['Type'])){
                    switch ($v['DiscountType']['Type']){
                        case 1:
                            //代金券
                            $DiscountType['Type'] = 1;
                            if(isset($v['DiscountType']['TypeOne'])){
                                $DiscountType['code'] = 1;
                                $DiscountType['IsError'] = 0;
//                                $TmpPrice = $v['DiscountType']['TypeOne'];
                                $TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$v['DiscountType']['TypeOne']);//汇率转换
                                $TmpPrice = sprintf("%.2f", $TmpPrice);
                                $DiscountType['DiscountPrice'] = $TmpPrice;
                                $DiscountType['Name'] = $v['Name'];
								$DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
								$DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                            }else{
                                $DiscountType['IsError'] = 1;
                            }
                            break;
                        case 2:
                            //echo 1;
                            //赠送券(赠送商品)
                            $DiscountType['Type'] = 2;
                            $DiscountType['Name'] = $v['Name'];
							$DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
							$DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                            if(isset($v['DiscountType']['TypeTwo']['Sku'])){

                                $TmpSkuInfo = explode(",",$v['DiscountType']['TypeTwo']['Sku']);
                                $Random = 0;
                                $IsMultiple = 0;
                                if(isset($v['DiscountType']['TypeTwo']['IsMultiple']) && $v['DiscountType']['TypeTwo']['IsMultiple'] == 1){
                                    //是否按倍数赠送
                                    $IsMultiple = 1;
                                }

                                if(count($TmpSkuInfo) > 0){

                                    //有赠品的
                                    if(isset($v['DiscountType']['TypeTwo']['IsRandom']) && $v['DiscountType']['TypeTwo']['IsRandom'] == 1){
                                        //随机赠送
                                        $Random = rand(0,(count($TmpSkuInfo)-1));
                                        if(isset($TmpSkuInfo[$Random])){
                                            $TmpSkuInfoOne = explode(";",$TmpSkuInfo[$Random]);
                                            if(isset($TmpSkuInfoOne[0]) && isset($TmpSkuInfoOne[1]) && isset($TmpSkuInfoOne[2])){
                                                //获取赠品信息
                                                $ProductId = explode(":",$TmpSkuInfoOne[0]);
                                                $SkuIdArr = explode(":",$TmpSkuInfoOne[1]);
                                                if(isset($ProductId[1]) && isset($SkuIdArr[1])){
                                                    /** coupon赠品修改为sku code tinghu.liu IN 20190119 **/
//                                                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);
                                                    //$ShipTo 区域定价 added by wangyj in 20190220
                                                    $ProductInfo = $this->CommonService->productInfoByIDAndCode($ProductId[1],$SkuIdArr[1],$Lang,$Currency,$ShipTo);
                                                    if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
//                                                        $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency,$ShipTo);//false不使用缓存
                                                        //找到赠送的sku详细信息
                                                        $select_sku_info = [];
                                                        if (isset($ProductInfo['data']['Skus'])){
                                                            foreach ($ProductInfo['data']['Skus'] as $skus){
                                                                /** 因为$SkuIdArr[1]变为了Code，所以这里要用Code来判断，tinghu.liu IN 20190121 **/
                                                                //if ($skus['_id'] == $SkuIdArr[1]){
                                                                if ($skus['Code'] == $SkuIdArr[1]){
                                                                    $select_sku_info = $skus;
                                                                    break;
                                                                }
                                                            }
                                                        }

                                                        /** 赠送券倍数处理  start tinghu.liu 20190329  **/
                                                        //满足条件后赠送券配置的赠送数量
                                                        $give_coupon_nums = $real_give_coupon_nums  = 1;
                                                        $coupon_start_num = $coupon_start_num>0?$coupon_start_num:1;
                                                        if ($IsMultiple){
                                                            $real_give_coupon_nums = $this->getCouponMultipleProNumber($AllQty, $give_coupon_nums, $coupon_start_num);
                                                        }
                                                        /** 赠送券倍数处理  end tinghu.liu 20190329  **/

                                                        if(isset($ProductInfo['data']['ImageSet']['ProductImg'][0])){
                                                            $DiscountType['SkuInfo'][0]['ProductImg'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        }
                                                        $DiscountType['SkuInfo'][0]['ProductId'] = $ProductId[1];
                                                        $DiscountType['SkuInfo'][0]['SkuId'] = $SkuIdArr[1];

                                                        $DiscountType['SkuInfo'][0]['SkuCode'] = isset($select_sku_info['Code'])?$select_sku_info['Code']:'';
                                                        $DiscountType['SkuInfo'][0]['Title'] = isset($ProductInfo['data']['Title'])?$ProductInfo['data']['Title']:'';
                                                        $DiscountType['SkuInfo'][0]['Qty'] = $real_give_coupon_nums;
                                                        $DiscountType['SkuInfo'][0]['Image'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        $DiscountType['code'] = 1;
                                                    }
                                                }
                                            }
                                        }
                                    }else{
                                        //全部赠送
                                        foreach ($TmpSkuInfo as $sku_k => $sku_v){
                                            $TmpSkuInfoOne = explode(";",$TmpSkuInfo[$sku_k]);
                                            if(isset($TmpSkuInfoOne[0]) && isset($TmpSkuInfoOne[1]) && isset($TmpSkuInfoOne[2])){
                                                //获取赠品信息
                                                $ProductId = explode(":",$TmpSkuInfoOne[0]);
                                                $SkuIdArr = explode(":",$TmpSkuInfoOne[1]);
												$QtyArr = explode(":",$TmpSkuInfoOne[2]);
                                                if(isset($ProductId[1]) && isset($SkuIdArr[1])){
                                                    /** coupon赠品修改为sku code tinghu.liu IN 20190119 **/
//                                                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);
                                                    //$ShipTo 区域定价 added by wangyj in 20190220
                                                    $ProductInfo = $this->CommonService->ProductInfoByIDAndCode($ProductId[1],$SkuIdArr[1],$Lang,$Currency,$ShipTo);

                                                    if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
//                                                        $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency,$ShipTo);//false不使用缓存
                                                        //找到赠送的sku详细信息
                                                        $select_sku_info = [];
                                                        if (isset($ProductInfo['data']['Skus'])){
                                                            foreach ($ProductInfo['data']['Skus'] as $skus){
                                                                /** 因为$SkuIdArr[1]变为了Code，所以这里要用Code来判断，tinghu.liu IN 20190121 **/
                                                                //if ($skus['_id'] == $SkuIdArr[1]){
                                                                if ($skus['Code'] == $SkuIdArr[1]){
                                                                    $select_sku_info = $skus;
                                                                    break;
                                                                }
                                                            }
                                                        }

                                                        /** 赠送券倍数处理  start tinghu.liu 20190329  **/
                                                        //满足条件后赠送券配置的赠送数量
                                                        $give_coupon_nums = $real_give_coupon_nums = isset($QtyArr[1])?$QtyArr[1]:1;
                                                        $coupon_start_num = $coupon_start_num>0?$coupon_start_num:1;
                                                        if ($IsMultiple){
                                                            $real_give_coupon_nums = $this->getCouponMultipleProNumber($AllQty, $give_coupon_nums, $coupon_start_num);
                                                        }
                                                        /** 赠送券倍数处理  end tinghu.liu 20190329  **/

                                                        if(isset($ProductInfo['data']['ImageSet']['ProductImg'][0])){
                                                            $DiscountType['SkuInfo'][0]['ProductImg'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        }
                                                        $DiscountType['SkuInfo'][$sku_k]['ProductId'] = $ProductId[1];
                                                        $DiscountType['SkuInfo'][$sku_k]['SkuId'] = $SkuIdArr[1];

                                                        $DiscountType['SkuInfo'][$sku_k]['SkuCode'] = isset($select_sku_info['Code'])?$select_sku_info['Code']:'';

                                                        $DiscountType['SkuInfo'][$sku_k]['Title'] = isset($ProductInfo['data']['Title'])?$ProductInfo['data']['Title']:'';
                                                        $DiscountType['SkuInfo'][$sku_k]['Qty'] = $real_give_coupon_nums;
                                                        $DiscountType['SkuInfo'][$sku_k]['Image'] = isset($ProductInfo['data']['ImageSet']['ProductImg'][0])?$ProductInfo['data']['ImageSet']['ProductImg'][0]:'';
                                                        $DiscountType['code'] = 1;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                }
                            }
                            break;
                        case 3:
                            //折扣券
                            $DiscountType['Type'] = 1;
                            //折扣
                            if(isset($v['DiscountType']['TypeThree']['Discount'])){
                                $DiscountType['IsError'] = 0;
                                $Discount = $v['DiscountType']['TypeThree']['Discount'];
//                                echo ($AllPrice * $AllQty);die;
//                                $TmpPrice = ($AllPrice * $AllQty)*(1-$Discount);
                                $TmpPrice = $AllPrice*($Discount/100);
//								$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$TmpPrice);//汇率转换
								$TmpPrice = sprintf("%.2f", $TmpPrice);
                                $DiscountType['DiscountPrice'] = $TmpPrice;
                                $DiscountType['code'] = 1;
                                $DiscountType['Name'] = $v['Name'];
								$DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
								$DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                            }else{
                                $DiscountType['IsError'] = 1;
                            }
                            break;
                        case 4:
                            //指定售价
                            $DiscountType['Type'] = 1;
                            if(isset($v['DiscountType']['TypeFour']['Price'])){
                                $DiscountType['IsError'] = 0;
                                $AppointPrice = $v['DiscountType']['TypeFour']['Price'];
                                $AppointPrice = $this->CommonService->calculateRate('USD',$Currency,$AppointPrice);//汇率转换
                                $AppointPrice = sprintf("%.2f", $AppointPrice);
//                                $TmpPrice = ($AllPrice * $AllQty)-($AllQty * $AppointPrice);
                                $TmpPrice = $AllPrice-($AllQty * $AppointPrice);
								//$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$TmpPrice);//汇率转换
								$TmpPrice = sprintf("%.2f", $TmpPrice);
                                $DiscountType['DiscountPrice'] = $TmpPrice;
                                $DiscountType['code'] = 1;
                                $DiscountType['Name'] = $v['Name'];
								$DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
								$DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                            }else{
                                $DiscountType['IsError'] = 1;
                            }
                            break;
                        default:

                    }
                }
                $UseFlag = 1;//把可用标识设置为可用
                break;
            }
        }
        $isUsedCoupon['CouponId'] = $CouponId;
		$isUsedCoupon['CouponCode'] = $CouponCode;
        $isUsedCoupon['CicCouponId'] = $CicCouponId;
        $isUsedCoupon['DiscountInfo'] = $DiscountType;

        //如果满足使用条件了，计算优惠的额度或是赠送的产品，记录相应的使用信息
        if($IsSellerSku == 2){
            //记录到seller级别
            $CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon'] = $isUsedCoupon;
            foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k => $v){
                foreach ($v as $k1 => $v1){
                    unset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$k][$k1]['isUsedCoupon']);
                }
            }
        }else if($IsSellerSku == 1){
            //记录到sku级别
            unset($CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon']);
            $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['isUsedCoupon'] = $isUsedCoupon;
        }
        $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);

        if(isset($DiscountType['DiscountPrice'])){
            $TmpPrice = $DiscountType['DiscountPrice'];
            //汇率转换有问题？？？？不用币种转换，因为产品数据金额已经做了转换
//            $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
            $TmpPrice = sprintf("%.2f", $TmpPrice);
            $DiscountType['DiscountPrice'] = $TmpPrice;
        }
        return $DiscountType;
    }
    /**
     * 使用coupon
     * @param $Uid
     * @param $Params
     * @return array
     */
    public function useCoupon($Uid,$Params){
        $StoreId = $Params['StoreId'];
        $productId = isset($Params['productId'])?$Params['productId']:0;
        $SkuId = isset($Params['SkuId'])?$Params['SkuId']:0;
        $Qty = isset($Params['Qty'])?$Params['Qty']:1;
        //来源：0-默认正常情况，1-计算使用coupon后的数据，不用更新购物车 tinghu.liu 20191029
        $FromFlag = isset($Params['FromFlag'])?$Params['FromFlag']:0;
        //计算时候获取产品信息是否使用缓存，如果不是真正的使用coupon，可以使用缓存提升性能 tinghu.liu 20191101
        $FromForNumsAndPriceForReset = 0;
        $GetProductIsCache = false;
        if ($FromFlag == 1){
            $FromForNumsAndPriceForReset = 1;
            $GetProductIsCache = true;
        }
        $DiscountLevel = $Params['DiscountLevel'];
        $CouponId = $Params['CouponId'];
        $CouponCode = $Params['CouponCode'];
        $IsSellerSku = $Params['DiscountLevel'];//如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
        $Lang = $Params['Lang'];
        $Currency = $Params['Currency'];
        $ShipTo = $Params['country_code'];

        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        //$this->cancelCoupon($Uid,$Params);//先取消已使用的coupon，再使用已选择的coupon
        //跟数据库交互判断是否可以使用()
        $AllPrice = 0;
        $AllQty = 0;
        //获取cartInfo信息
        $CartInfo = $this->CommonService->loadRedis()->get("ShoppingCart_".$Uid);
        /**
         * TODO 批发价使用coupon时产品新价格（实际使用coupon的价格[活动价或原售价]）数组，批发价和coupon互斥
         * TODO 前端根据这个数据重新渲染产品价格，同时更新购物车产品价格数据
         */
        $SkuNewPriceArr = [];
        $SkuNewPriceArrTemp = [];
        $IsUsedBefore = false; //标识：之前是否已经使用过此coupon，为了解决手动coupon限定一个使用一个或一天一人使用一次，导致用户使用多次不能下单情况 tinghu.liu 20190827
        foreach ($CartInfo[$Uid]['StoreData'] as $k200=>$v200){
            $StoreIdFlag = $k200;
            if(isset($CartInfo[$Uid]['StoreData'][$StoreIdFlag]['ProductInfo'])){
                $TmpAllSku1 = $CartInfo[$Uid]['StoreData'][$StoreIdFlag]['ProductInfo'];
                foreach ($TmpAllSku1 as $k100=>$v100){
                    if(is_array($v100)){
                        foreach ($v100 as $k101=>$v101){
                            if (
                                isset($v101['isUsedCoupon']['CouponId'])
                                && $v101['isUsedCoupon']['CouponId'] == $CouponId
                            ){
                                $IsUsedBefore = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            if(
                isset($CartInfo[$Uid]['StoreData'][$StoreIdFlag]['isUsedCoupon']['CouponId'])
                && $CartInfo[$Uid]['StoreData'][$StoreIdFlag]['isUsedCoupon']['CouponId'] == $CouponId
            ){
                $IsUsedBefore = true;
            }
        }

        if($IsSellerSku == 2){
            if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'])){
                $TmpAllSku = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'];
                foreach ($TmpAllSku as $k=>$v){
                    if(is_array($v)){
                        foreach ($v as $k1=>$v1){
                            //判断是否选中，过滤掉未选中、没有运输方式的产品【初始化总价格、总数量】
                            if (
                                isset($v1['IsChecked']) && $v1['IsChecked'] == 1
                                && $v1['ShippModelStatusType'] != 3
                            ){
                                /**
                                 * tinghu.liu 20190715
                                 * 价格类型：0-销售价，1-批发价，2-活动价
                                 * 如果是批发价，增加使用coupon互斥功能，使用活动价或者售价
                                 */
                                $ActiveType = $v1['active_type'];
                                if ($ActiveType == 1){
                                    $Temp = [];
                                    $Temp['ProductID'] = $v1['ProductID'];
                                    $Temp['SkuID'] = $v1['SkuID'];
                                    $Temp['SkuCode'] = isset($v1['SkuCode'])?$v1['SkuCode']:'';
                                    $ProductPriceRes = $this->CommonService->getProductPriceForCoupon($v1['ProductID'], $v1['SkuID'], $v1['Qty'], $Lang, $Currency, $v1['ShipTo'], 1, [], $GetProductIsCache);
                                    if ($ProductPriceRes === false){
                                        return false;
                                    }
                                    $ProductPrice = $ProductPriceRes['product_price'];
                                    //统一币种
                                    if(strtoupper($Currency) != DEFAULT_CURRENCY){
                                        $ProductPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$ProductPrice,$rate_source);
                                        $ProductPrice = sprintf("%.2f",$ProductPrice);
                                    }
                                    $Temp['ProductPrice'] = $ProductPrice;
                                    //记录返回给前端的使用coupon但有批发价的产品具体价格（不用批发价）
                                    $SkuNewPriceArr[($Temp['ProductID'].$Temp['SkuID'])] = $Temp;

                                    $Temp1 = [];
                                    $Temp1['Flag']              = $Uid.'-'.$StoreId.'-'.$k.'-'.$k1;
                                    $Temp1['ProductPrice']      = $ProductPrice;
                                    $Temp1['active_type']       = $ProductPriceRes['type'];
                                    $Temp1['active_type_text']  = $ProductPriceRes['type_text'];
                                    $Temp1['type_id']           = $ProductPriceRes['type_id'];
                                    $SkuNewPriceArrTemp[] = $Temp1;
                                    //更新购物车价格和类型
//                                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$k][$k1]['ProductPrice'] = $ProductPrice;
//
//                                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$k][$k1]['active_type'] = $ProductPriceRes['type'];
//                                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$k][$k1]['active_type_text'] = $ProductPriceRes['type_text'];
//                                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$k][$k1]['type_id'] = $ProductPriceRes['type_id'];

                                    //计算使用coupon的总价
                                    $AllPrice += ($ProductPrice*$v1['Qty']); //获取产品总价
                                }else{
                                    $AllPrice += ($v1['ProductPrice']*$v1['Qty']); //获取产品总价
                                }

                                $AllQty += $v1['Qty']; //产品数量
                            }
                        }
                    }
                }
            }else{
                //不存在此商品
                return false;
            }
        }else if($IsSellerSku == 1){
            //sku
            if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId])){
                $AllQty = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Qty'];

                /**
                 * tinghu.liu 20190715
                 * 价格类型：0-销售价，1-批发价，2-活动价
                 * 如果是批发价，增加使用coupon互斥功能，使用活动价或者售价
                 */
                $ActiveType = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['active_type'];
                if ($ActiveType == 1){
                    $Temp = [];
                    $Temp['ProductID'] = $productId;
                    $Temp['SkuID'] = $SkuId;
                    $Temp['SkuCode'] = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['SkuCode'];
                    $ProductPriceRes = $this->CommonService->getProductPriceForCoupon($productId, $SkuId, $AllQty, $Lang, $Currency, $ShipTo, 1, [], $GetProductIsCache);
                    if ($ProductPriceRes === false){
                        return false;
                    }
                    $ProductPrice = $ProductPriceRes['product_price'];
                    //统一币种
                    if(strtoupper($Currency) != DEFAULT_CURRENCY){
                        $ProductPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$ProductPrice,$rate_source);
                        $ProductPrice = sprintf("%.2f",$ProductPrice);
                    }
                    $Temp['ProductPrice'] = $ProductPrice;
                    //记录返回给前端的使用coupon但有批发价的产品具体价格（不用批发价）
                    $SkuNewPriceArr[($Temp['ProductID'].$Temp['SkuID'])] = $Temp;

                    //更新购物车价格和类型
                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['ProductPrice'] = $ProductPrice;

                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['active_type'] = $ProductPriceRes['type'];
                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['active_type_text'] = $ProductPriceRes['type_text'];
                    $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['type_id'] = $ProductPriceRes['type_id'];

                    //计算使用coupon的总价
                    $AllPrice += ($ProductPrice*$AllQty); //获取产品总价
                }else{
                    $AllPrice = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['ProductPrice'] * $AllQty;
                }
            }else{
                //不存在此商品
                return false;
            }
        }else{
            //目前只支持seller级别和单品级别的coupon
            return false;
        }
        //这里可以考虑使用cart本身的coupon，这样数据才会一致性
        //或者是因为这里是使用coupon，所以直接走获取coupon的方法能获取到最新数据？
        $CouponInfo = $this->getCoupon($Params);

        //echo json_encode($CouponInfo);//die;
        if(!isset($CouponInfo['data']) || !isset($CouponInfo['code']) || $CouponInfo['code'] != 1){
            //获取信息出错
            return false;
        }
        $CouponInfo = $CouponInfo['data'];
        $DiscountType = array();
        $UseFlag = 0;//把是否可用标识设为不可用
        $DiscountType['code'] = 0;
        $DiscountType['Type'] = 0;//等于0表示赠品出错了
        $CicCouponId = 0; //初始化值为0，为了避免找不到数据报错情况 2190329 tinghu.liu
        foreach ($CouponInfo as $k=>$v){
            if(
                isset($v['CouponId']) && $v['CouponId'] == $CouponId
                && isset($v['coupon_code']) && $v['coupon_code']
            ){
                $CouponCode = $v['coupon_code'];
                //增加cic couponID tinghu.liu 20190326
                $CicCouponId = isset($v['cic_coupon_id'])?$v['cic_coupon_id']:0;
                /**
                 * 20181206  判断coupon是否可用 重新获取订单级别 符合限定规则的产品总价格、总数量 start
                 * $IsSellerSku：1-sku级别，2-seller级别
                 */
                $_coupon_is_useable = false;
                if($IsSellerSku == 2){ //seller级别
                    if(
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'])
                        )
                        ||
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'])
                        )
                    ){
                        if (
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['Coupon'][$CouponId]['isUsable'] == 1
                            )
                            ||
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['coupon'][$CouponId]['isUsable'] == 1
                            )
                        ){
                            $_coupon_is_useable = true;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }else if($IsSellerSku == 1){ //sku级别
                    if(
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon']))
                        ||
                        (
                            isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'])
                            && !empty($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'])
                        )
                    ){
                        if (
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Coupon'][$CouponId]['isUsable'] == 1
                            )
                            ||
                            (
                                isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'][$CouponId]['isUsable'])
                                &&
                                $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['coupon'][$CouponId]['isUsable'] == 1
                            )
                        ){
                            //coupon可用
                            $_coupon_is_useable = true;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                }
                //coupon可用，重新获取订单级别 符合限定规则的产品总价格、总数量
                if ($_coupon_is_useable){
                    //【订单级别情况下】
                    if ($IsSellerSku == 2){
                        $this->getNumsAndPriceForResetCouponUseable($v, $CartInfo, $Uid, $StoreId, $ShipTo, $AllQty, $AllPrice, $SkuNewPriceArr, $SkuNewPriceArrTemp, $FromForNumsAndPriceForReset);
                    }
                }else{
                    return false;
                }

                /** 价格、数量规则判断 start tinghu.liu  20190314 **/
                if ($AllPrice === 0 && $AllQty === 0){
                    //不存在此商品
                    return false;
                }

                //判断领取限制是否是 2或3 且改coupon之前已经使用过，则不让再次使用."ReceiveLimit": 1, //领取限制：1-不限、2-每人一次、3-每人每天一次
                if (
                    isset($v['ReceiveLimit'])
                    && ($v['ReceiveLimit'] == 2 || $v['ReceiveLimit'] == 3)
                    && $IsUsedBefore
                ){
                    Log::record('coupon领取限制为每人一次或每人每天一次，不能重复使用。参数：'.json_encode($Params),'error');
                    //logService::write(LOGS_MALL_CART,'notice',__METHOD__,'useCoupon',$v,null, 'coupon领取限制为每人一次或每人每天一次，不能重复使用。参数：'.json_encode($Params), $Uid);
                    return false;
                }

                //价格判断
                if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                    //查看金额是否在范围
                    $TempStartPrice = isset($v['PurchaseAmountLimit']['StartPrice']) && is_numeric($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                    $TempEndPrice = isset($v['PurchaseAmountLimit']['EndPrice']) && is_numeric($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:9999999999;
                    $TempPrice = $AllPrice;
                    if(strtoupper($Currency) != DEFAULT_CURRENCY){
                        $TempPrice = $this->CommonService->calculateRate($Currency,DEFAULT_CURRENCY,$AllPrice,$rate_source);
                    }
                    if(
                        $TempPrice < $TempStartPrice
                        || $TempPrice > $TempEndPrice
                    ){
                        Log::record('This code does not meet the coupon conditions. coupon info:'.json_encode($v).', $Uid:'.$Uid.', params:'.json_encode($Params));
                        return false;
                    }
                }
                //初始化默认值，为了倍数赠送券使用coupon时使用 20190329 tinghu.liu
                if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                    //查看数量是否在范围
                    $TempStartNum = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                    $TempEndNum = isset($v['BuyGoodsNumLimit']['EndNum']) && is_numeric($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:9999999999;
                    if(
                        $AllQty < $TempStartNum
                        || $AllQty > $TempEndNum
                    ){

                        Log::record('This code does not meet the coupon conditions, please check.. coupon info:'.json_encode($v).', $Uid:'.$Uid.', params:'.json_encode($Params));
                        return false;
                    }
                }
                /************  价格、数量规则判断 end  **************/

                //获取coupon规则中的数量限制开始值，后面赠送券倍数使用时用到 20190329 tinghu.liu
                $coupon_start_num = 0;
                if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                    //查看数量是否在范围
                    $coupon_start_num = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:1;
                }

                /**
                 * 20181206  判断coupon是否可用 重新获取订单级别 符合限定规则的产品总价格、总数量 end
                 * $IsSellerSku：1-sku级别，2-seller级别
                 */
                //单品级别的优惠
                if(isset($v['DiscountType']['Type'])){
                    switch ($v['DiscountType']['Type']){
                        case 1:
                            //代金券
                            $DiscountType['Type'] = 1;
                            if(isset($v['DiscountType']['TypeOne'])){
                                $DiscountType['code'] = 1;
                                $DiscountType['IsError'] = 0;
//                                $TmpPrice = $v['DiscountType']['TypeOne'];
                                $TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$v['DiscountType']['TypeOne']);//汇率转换
                                $TmpPrice = sprintf("%.2f", $TmpPrice);
                                $DiscountType['DiscountPrice'] = $TmpPrice;
                                $DiscountType['Name'] = $v['Name'];
                                $DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
                                $DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                                //领取限制：1-不限、2-每人一次、3-每人每天一次 tinghu.liu 20191101
                                $DiscountType['ReceiveLimit'] = isset($v['ReceiveLimit'])?$v['ReceiveLimit']:0;
                            }else{
                                $DiscountType['IsError'] = 1;
                            }
                            break;
                        case 2:
                            //echo 1;
                            //赠送券(赠送商品)
                            $DiscountType['Type'] = 2;
                            $DiscountType['Name'] = $v['Name'];
                            $DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
                            $DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                            //领取限制：1-不限、2-每人一次、3-每人每天一次 tinghu.liu 20191101
                            $DiscountType['ReceiveLimit'] = isset($v['ReceiveLimit'])?$v['ReceiveLimit']:0;
                            if(isset($v['DiscountType']['TypeTwo']['Sku'])){

                                $TmpSkuInfo = explode(",",$v['DiscountType']['TypeTwo']['Sku']);
                                $Random = 0;
                                $IsMultiple = 0;
                                if(isset($v['DiscountType']['TypeTwo']['IsMultiple']) && $v['DiscountType']['TypeTwo']['IsMultiple'] == 1){
                                    //是否按倍数赠送
                                    $IsMultiple = 1;
                                }

                                if(count($TmpSkuInfo) > 0){

                                    //有赠品的
                                    if(isset($v['DiscountType']['TypeTwo']['IsRandom']) && $v['DiscountType']['TypeTwo']['IsRandom'] == 1){
                                        //随机赠送
                                        $Random = rand(0,(count($TmpSkuInfo)-1));
                                        if(isset($TmpSkuInfo[$Random])){
                                            $TmpSkuInfoOne = explode(";",$TmpSkuInfo[$Random]);
                                            if(isset($TmpSkuInfoOne[0]) && isset($TmpSkuInfoOne[1]) && isset($TmpSkuInfoOne[2])){
                                                //获取赠品信息
                                                $ProductId = explode(":",$TmpSkuInfoOne[0]);
                                                $SkuIdArr = explode(":",$TmpSkuInfoOne[1]);
                                                if(isset($ProductId[1]) && isset($SkuIdArr[1])){
                                                    /** coupon赠品修改为sku code tinghu.liu IN 20190119 **/
//                                                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);
                                                    //$ShipTo 区域定价 added by wangyj in 20190220
                                                    $ProductInfo = $this->CommonService->productInfoByIDAndCode($ProductId[1],$SkuIdArr[1],$Lang,$Currency,$ShipTo,$GetProductIsCache);
                                                    if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
//                                                        $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);//false不使用缓存
                                                        //找到赠送的sku详细信息
                                                        $select_sku_info = [];
                                                        if (isset($ProductInfo['data']['Skus'])){
                                                            foreach ($ProductInfo['data']['Skus'] as $skus){
                                                                /** 因为$SkuIdArr[1]变为了Code，所以这里要用Code来判断，tinghu.liu IN 20190121 **/
                                                                //if ($skus['_id'] == $SkuIdArr[1]){
                                                                if ($skus['Code'] == $SkuIdArr[1]){
                                                                    $select_sku_info = $skus;
                                                                    break;
                                                                }
                                                            }
                                                        }

                                                        /** 赠送券倍数处理  start tinghu.liu 20190329  **/
                                                        //满足条件后赠送券配置的赠送数量
                                                        $give_coupon_nums = $real_give_coupon_nums  = 1;
                                                        $coupon_start_num = $coupon_start_num>0?$coupon_start_num:1;
                                                        if ($IsMultiple){
                                                            $real_give_coupon_nums = $this->getCouponMultipleProNumber($AllQty, $give_coupon_nums, $coupon_start_num);
                                                        }
                                                        /** 赠送券倍数处理  end tinghu.liu 20190329  **/
                                                        if(isset($ProductInfo['data']['ImageSet']['ProductImg'][0])){
                                                            $DiscountType['SkuInfo'][0]['ProductImg'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        }
                                                        $DiscountType['SkuInfo'][0]['ProductId'] = $ProductId[1];
                                                        $DiscountType['SkuInfo'][0]['SkuId'] = isset($select_sku_info['_id'])?$select_sku_info['_id']:'';//$SkuIdArr[1];

                                                        $DiscountType['SkuInfo'][0]['SkuCode'] = $SkuIdArr[1];//isset($select_sku_info['Code'])?$select_sku_info['Code']:'';
                                                        $DiscountType['SkuInfo'][0]['Title'] = isset($ProductInfo['data']['Title'])?$ProductInfo['data']['Title']:'';
                                                        $DiscountType['SkuInfo'][0]['Qty'] = $real_give_coupon_nums;
                                                        $DiscountType['SkuInfo'][0]['Image'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        $DiscountType['code'] = 1;
                                                    }
                                                }
                                            }
                                        }
                                    }else{
                                        //全部赠送
                                        foreach ($TmpSkuInfo as $sku_k => $sku_v){
                                            $TmpSkuInfoOne = explode(";",$TmpSkuInfo[$sku_k]);
                                            if(isset($TmpSkuInfoOne[0]) && isset($TmpSkuInfoOne[1]) && isset($TmpSkuInfoOne[2])){
                                                //获取赠品信息
                                                $ProductId = explode(":",$TmpSkuInfoOne[0]);
                                                $SkuIdArr = explode(":",$TmpSkuInfoOne[1]);
                                                $QtyArr = explode(":",$TmpSkuInfoOne[2]);
                                                if(isset($ProductId[1]) && isset($SkuIdArr[1])){
                                                    /** coupon赠品修改为sku code tinghu.liu IN 20190119 **/
//                                                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);
                                                    //$ShipTo 区域定价 added by wangyj in 20190220
                                                    $ProductInfo = $this->CommonService->ProductInfoByIDAndCode($ProductId[1],$SkuIdArr[1],$Lang,$Currency,$ShipTo, $GetProductIsCache);
                                                    if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
//                                                        $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);//false不使用缓存
                                                        //找到赠送的sku详细信息
                                                        $select_sku_info = [];
                                                        if (isset($ProductInfo['data']['Skus'])){
                                                            foreach ($ProductInfo['data']['Skus'] as $skus){
                                                                /** 因为$SkuIdArr[1]变为了Code，所以这里要用Code来判断，tinghu.liu IN 20190121 **/
                                                                //if ($skus['_id'] == $SkuIdArr[1]){
                                                                if ($skus['Code'] == $SkuIdArr[1]){
                                                                    $select_sku_info = $skus;
                                                                    break;
                                                                }
                                                            }
                                                        }


                                                        /** 赠送券倍数处理  start tinghu.liu 20190329  **/
                                                        //满足条件后赠送券配置的赠送数量
                                                        $give_coupon_nums = $real_give_coupon_nums = isset($QtyArr[1])?$QtyArr[1]:1;
                                                        $coupon_start_num = $coupon_start_num>0?$coupon_start_num:1;
                                                        if ($IsMultiple){
                                                            $real_give_coupon_nums = $this->getCouponMultipleProNumber($AllQty, $give_coupon_nums, $coupon_start_num);
                                                        }
                                                        /** 赠送券倍数处理  end tinghu.liu 20190329  **/
                                                        if(isset($ProductInfo['data']['ImageSet']['ProductImg'][0])){
                                                            $DiscountType['SkuInfo'][0]['ProductImg'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        }
                                                        $DiscountType['SkuInfo'][$sku_k]['ProductId'] = $ProductId[1];
                                                        $DiscountType['SkuInfo'][$sku_k]['SkuId'] = isset($select_sku_info['_id'])?$select_sku_info['_id']:'';//$SkuIdArr[1];

                                                        $DiscountType['SkuInfo'][$sku_k]['SkuCode'] = $SkuIdArr[1];//isset($select_sku_info['Code'])

                                                        $DiscountType['SkuInfo'][$sku_k]['Title'] = isset($ProductInfo['data']['Title'])?$ProductInfo['data']['Title']:'';
                                                        $DiscountType['SkuInfo'][$sku_k]['Qty'] = $real_give_coupon_nums;
                                                        $DiscountType['SkuInfo'][$sku_k]['Image'] = isset($ProductInfo['data']['ImageSet']['ProductImg'][0])?$ProductInfo['data']['ImageSet']['ProductImg'][0]:'';
                                                        $DiscountType['code'] = 1;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                }
                            }
                            break;
                        case 3:
                            //折扣券
                            $DiscountType['Type'] = 1;
                            //折扣
                            if(isset($v['DiscountType']['TypeThree']['Discount'])){
                                $DiscountType['IsError'] = 0;
                                $Discount = $v['DiscountType']['TypeThree']['Discount'];
                                //$TmpPrice = ($AllPrice * $AllQty)*(1-($Discount/100));
                                //折扣（要减去）的金额
                                //$TmpPrice = ($AllPrice * $AllQty)*($Discount/100);
                                $TmpPrice = $AllPrice*($Discount/100);
                                //$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$TmpPrice);//汇率转换
                                //$TmpPrice = sprintf("%.2f", $TmpPrice);
                                $DiscountType['DiscountPrice'] = sprintf("%.2f", $TmpPrice);
                                $DiscountType['code'] = 1;
                                $DiscountType['Name'] = $v['Name'];
                                $DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
                                $DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                                //领取限制：1-不限、2-每人一次、3-每人每天一次 tinghu.liu 20191101
                                $DiscountType['ReceiveLimit'] = isset($v['ReceiveLimit'])?$v['ReceiveLimit']:0;
                            }else{
                                $DiscountType['IsError'] = 1;
                            }
                            break;
                        case 4:
                            //指定售价
                            $DiscountType['Type'] = 1;
                            if(isset($v['DiscountType']['TypeFour']['Price'])){
                                $DiscountType['IsError'] = 0;
                                $AppointPrice = $v['DiscountType']['TypeFour']['Price'];
                                $AppointPrice = $this->CommonService->calculateRate('USD',$Currency,$AppointPrice);//汇率转换
                                $AppointPrice = sprintf("%.2f", $AppointPrice);

                                //$TmpPrice = ($AllPrice * $AllQty)-($AllQty * $AppointPrice);
                                $TmpPrice = $AllPrice-($AllQty * $AppointPrice);
                                //$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$TmpPrice);//汇率转换
                                //$TmpPrice = sprintf("%.2f", $TmpPrice);
                                $DiscountType['DiscountPrice'] = sprintf("%.2f", $TmpPrice);
                                $DiscountType['code'] = 1;
                                $DiscountType['Name'] = $v['Name'];
                                $DiscountType['StartTime'] = isset($v['CouponTime']['StartTime'])?$v['CouponTime']['StartTime']:0;
                                $DiscountType['EndTime'] = isset($v['CouponTime']['EndTime'])?$v['CouponTime']['EndTime']:0;
                                //领取限制：1-不限、2-每人一次、3-每人每天一次 tinghu.liu 20191101
                                $DiscountType['ReceiveLimit'] = isset($v['ReceiveLimit'])?$v['ReceiveLimit']:0;
                            }else{
                                $DiscountType['IsError'] = 1;
                            }
                            break;
                        default:break;

                    }
                }
                $UseFlag = 1;//把可用标识设置为可用
                break;
            }
        }
        $isUsedCoupon['CouponId'] = $CouponId;
        $isUsedCoupon['CouponCode'] = $CouponCode;
        $isUsedCoupon['CicCouponId'] = $CicCouponId;
        $isUsedCoupon['DiscountInfo'] = $DiscountType;

        //如果满足使用条件了，计算优惠的额度或是赠送的产品，记录相应的使用信息
        if($IsSellerSku == 2){
            //记录到seller级别
            $CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon'] = $isUsedCoupon;
            foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k => $v){
                foreach ($v as $k1 => $v1){
                    unset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$k][$k1]['isUsedCoupon']);
                }
            }
        }else if($IsSellerSku == 1){
            //记录到sku级别
            unset($CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon']);
            $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['isUsedCoupon'] = $isUsedCoupon;
        }
        //只有默认情况下才更新购物车，真正的使用coupon tinghu.liu 20191029
        if ($FromFlag == 0){
            $this->CommonService->loadRedis()->set("ShoppingCart_".$Uid,$CartInfo);
        }
        if(isset($DiscountType['DiscountPrice'])){
            $TmpPrice = $DiscountType['DiscountPrice'];
            //汇率转换有问题？？？？不用币种转换，因为产品数据金额已经做了转换
            //$TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
            $TmpPrice = sprintf("%.2f", $TmpPrice);
            $DiscountType['DiscountPrice'] = $TmpPrice;
        }
        //增加新价格返回 tinghu.liu 20190716
        $DiscountType['SkuNewPriceArr'] = $SkuNewPriceArr;
        return $DiscountType;
    }

    public function getCoupon($Params){
        /*获取自动的coupon*/
        $AutoStoreCoupon = $this->getAutoCoupon($Params);
        /*获取手动的coupon*/
        $ManualStoreCoupon = $this->getManualCoupon($Params);
        $ReturnData = array();
        $ReturnData['code'] = 0;
        $ReturnData['data'] = array();
        /*返回适用的优惠券*/
        if(isset($AutoStoreCoupon['data']['data'])){
            $ReturnData['code'] = 1;
            $ReturnData['data'] = array_merge_recursive($AutoStoreCoupon['data']['data'],$ReturnData['data']);
        }
        if(isset($ManualStoreCoupon['data']['data'])){
            $ReturnData['code'] = 1;
            $ReturnData['data'] = array_merge_recursive($ManualStoreCoupon['data']['data'],$ReturnData['data']);
        }
        return $ReturnData;
    }
    /**
     *
     * @param unknown $Uid
     * @param unknown $Params
     */
    public function cancelCoupon($Uid,$Params){
        $StoreId = $Params['StoreId'];
        $productId = $Params['productId'];
        $SkuId = $Params['SkuId'];
        $Qty = $Params['Qty'];
        $DiscountLevel = $Params['DiscountLevel'];
        $CouponId = $Params['CouponId'];
        $IsSellerSku = $Params['DiscountLevel'];//如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
        //获取cartInfo信息
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);

        $ReturnData = array();
        //if($IsSellerSku == 2){
            //记录到seller级别
            if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon'])){
                //$ReturnData['UseCouponInfo'] = $CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon'];
                $ReturnData['code'] = 1;
                unset($CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon']);
            }else{
                $ReturnData['code'] = 0;
            }
       // }else{
            //记录到sku级别
            if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['isUsedCoupon'])){
                $UseCouponInfo = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['isUsedCoupon'];
                $ReturnData['code'] = 1;
                unset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['isUsedCoupon']);
            }else{
                $ReturnData['code'] = 0;
            }
        //}
        $ReturnData['IsError'] = 0;
        $ReturnData['DiscountPrice'] = 0;

        $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
        return $ReturnData;
    }

    public function changeProductNumsUseCoupon(&$TmpCoupon,$CartInfo,$StoreID,$ProductID,$SkuID,$Uid,$Currency,$ShipTo=''){
        $IsChecked = isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['IsChecked'])?$CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['IsChecked']:0;
        $ShippModelStatusType = isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippModelStatusType'])?$CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippModelStatusType']:3;
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        //先获取该seller下的coupon，重新计算是否有可用的
        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['coupon'])){
            $TmpCoupon['sellerCoupon'] = $CartInfo[$Uid]['StoreData'][$StoreID]['coupon'];
            $Qty = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'];
            $ProductPrice = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ProductPrice'];
            //需要获取seller下的所有产品总数量和总价格，之后再判断相应规则
            $seller_coupon_all_nums = 0;
            $seller_coupon_all_prices = 0;
            foreach ($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'] as $k10=>$v10){
                foreach ($v10 as $k11=>$v11){
                    //去除没选中或没有运输方式的数据
                    if ($v11['IsChecked'] != 0 && $v11['ShippModelStatusType'] != 3){
                        $seller_coupon_all_nums += $v11['Qty'];//TODO 指定商品的coupon是否要过滤
                        $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                    }
                }
            }
            foreach ($TmpCoupon['sellerCoupon'] as $k=>$v){
                //循环这个store的coupon
                $isUsable = 1;
                if(isset($v['isUsable'])){
                    if(isset($v['UsableSku'])){
                        $UsableSku = explode(",",$v['UsableSku']);//拿到可以使该coupon的skuid
//                        if(in_array($SkuID,$UsableSku)){

                        $SkuNewPriceArr = [];
                        $this->getNumsAndPriceForResetCouponUseable($v, $CartInfo, $Uid, $StoreID, $ShipTo, $seller_coupon_all_nums, $seller_coupon_all_prices, $SkuNewPriceArr, [], 1);

                        if ($seller_coupon_all_nums === 0 && $seller_coupon_all_prices === 0){
                            $isUsable = 0;
                            $TmpCoupon['sellerCoupon'][$k]['isUsable'] = $isUsable;

                            //20181212将seller级别coupon使用情况更新到购物车
                            $CartInfo[$Uid]['StoreData'][$StoreID]['coupon'][$k]['isUsable'] = $isUsable;
                            continue;
                        }

                        if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                            //查看金额是否在范围
                            $TempStartPrice = isset($v['PurchaseAmountLimit']['StartPrice']) && is_numeric($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                            $TempEndPrice = isset($v['PurchaseAmountLimit']['EndPrice']) && is_numeric($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:9999999999;
                            $TempPrice = $this->CommonService->calculateRate($Currency,DEFAULT_CURRENCY,$seller_coupon_all_prices,$rate_source);
                            if(
                                $TempPrice < $TempStartPrice
                                || $TempPrice > $TempEndPrice
                            ){
                                $isUsable = 0;
                            }
                        }
                        if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                            //查看数量是否在范围
                            $TempStartNum = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                            $TempEndNum = isset($v['BuyGoodsNumLimit']['EndNum']) && is_numeric($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:9999999999;
                            if(
                                $seller_coupon_all_nums < $TempStartNum
                                || $seller_coupon_all_nums > $TempEndNum
                            ){
                                $isUsable = 0;
                            }
                        }
//                        }
                    }else{
                        $isUsable = 0;
                    }
                }else {
                    $isUsable = 0;
                }
                $TmpCoupon['sellerCoupon'][$k]['isUsable'] = $isUsable;

                //20181212将seller级别coupon使用情况更新到购物车
                $CartInfo[$Uid]['StoreData'][$StoreID]['coupon'][$k]['isUsable'] = $isUsable;

            }
            /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. start **/
            $is_update_cart = false;
            //if ($isUsable == 0){ 为了解决cart 线下Coupon使用后 改变产品数量无法取消coupon使用的问题 BY tinghu.liu IN 20190218
            if (isset($CartInfo[$Uid]['StoreData'][$StoreID]['isUsedCoupon'])){
                $is_update_cart = true;
                unset($CartInfo[$Uid]['StoreData'][$StoreID]['isUsedCoupon']);
            }
            //}
            if ($is_update_cart){
                //20181212 统一购物车放在最后操作
                //$this->CommonService->loadRedis()->set("ShoppingCart_".$Uid, $CartInfo);
            }
            /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. end **/
        }
        //再获取具体sku下的coupon，重新计算是否有可用的
        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['coupon'])){
            $TmpCoupon['skuCoupon'] = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['coupon'];
            $Qty = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'];
            $ProductPrice = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ProductPrice'];
            if(is_array($TmpCoupon['skuCoupon'])){
                foreach ($TmpCoupon['skuCoupon'] as $k=>$v){
                    //循环这个sku的coupon
                    $isUsable = 1;
                    if(isset($v['isUsable'])){
                        if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                            //商品数量限制
                            $TempStartNum = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                            $TempEndNum = isset($v['BuyGoodsNumLimit']['EndNum']) && is_numeric($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:9999999999;
                            if(
                                $Qty < $TempStartNum
                                || $Qty > $TempEndNum
                            ){
                                $isUsable = 0;
                            }
                        }
                        if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                            //金额的限制
                            $TmpPrice = $Qty*$ProductPrice;
                            //汇率转换
                            if($Currency != 'USD'){
                                $TmpPrice = $this->CommonService->calculateRate($Currency,'USD',$TmpPrice);//汇率转换
                            }
                            $TempStartPrice = isset($v['PurchaseAmountLimit']['StartPrice']) && is_numeric($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                            $TempEndPrice = isset($v['PurchaseAmountLimit']['EndPrice']) && is_numeric($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:9999999999;
                            if(
                                $TmpPrice < $TempStartPrice
                                || $TmpPrice > $TempEndPrice
                            ){
                                $isUsable = 0;
                            }
                        }
                    }else {
                        $isUsable = 0;
                    }
                    //去除没选中或没有运输方式的数据
                    if ($IsChecked == 0 || $ShippModelStatusType == 3){
                        $isUsable = 0;
                    }
                    $TmpCoupon['skuCoupon'][$k]['isUsable'] = $isUsable;

                    //20181212 将skucoupon使用情况更新到购物车
                    $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['coupon'][$k]['isUsable'] = $isUsable;

                    /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. start **/
                    $is_update_cart = false;
                    //if ($isUsable == 0){ 为了解决cart 线下Coupon使用后 改变产品数量无法取消coupon使用的问题 BY tinghu.liu IN 20190218
                    if (isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['isUsedCoupon'])){
                        $is_update_cart = true;
                        unset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['isUsedCoupon']);
                    }
                    //}
                    if ($is_update_cart){
                        //20181212 统一购物车放在最后操作
                        //$this->CommonService->loadRedis()->set("ShoppingCart_".$Uid, $CartInfo);
                    }
                    /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. end **/
                }
            }
        }

        //为了解决cart 线下Coupon使用后 改变产品数量无法取消coupon使用的问题 BY tinghu.liu IN 20190218
        if (isset($CartInfo[$Uid]['StoreData'][$StoreID]['isUsedCoupon'])){
            unset($CartInfo[$Uid]['StoreData'][$StoreID]['isUsedCoupon']);
        }
        //20181212 更新购物车，为了同步因改变数量引起的coupon相关变化同步到购物车
        $this->CommonService->loadRedis()->set("ShoppingCart_".$Uid, $CartInfo);
    }


    /**
     * 自动使用coupon
     * @param unknown $Uid
     */
    public function autoUseCoupon($Uid,$Country,&$CartInfo,$Lang,$Currency){
        /**
        if(isset($CartInfo[$Uid]['StoreData'])){
        foreach ($CartInfo[$Uid]['StoreData'] as $k=>$v){
        $Store_use_coupon = 0;
        if(isset($v['coupon'])){
        foreach ($v['coupon'] as $k1=>$v1){
        if(isset($v1['isUsable']) && $v1['isUsable'] == 1){
        //找到store可用的coupon
        $Store_use_coupon = 1;
        $Params['StoreId'] = $k;
        $Params['CouponRuleType'] = array(1,2);//用来获取coupon
        $Params['DiscountLevel'] = 2;//如果为1则表示是seller级别的coupon,如果为0则表示为sku级别的coupon
        $Params['CouponId'] = $v1['CouponId'];
        $Params['CouponCode'] = isset($v1['coupon_code'])?$v1['coupon_code']:'';
        $Params['customer_id'] = $Uid;//用来获取coupon
        $Params['type'] = 1;//表示获取店铺级别的优惠券//用来获取coupon
        $Params['CouponRuleType'] = array(1,2);//用来获取coupon
        $Params['country_code'] = $Country;//用来获取coupon
        $Params['Lang'] = $Lang;
        $Params['Currency'] = $Currency;
        //调用使用coupon方法
        $this->useCoupon($Uid, $Params);
        //在cartinfo里记录该使用信息
        }
        }
        }
        //如果没有store使用的coupon，则进产品的sku偿试使用coupon
        if(isset($v['ProductInfo'])){
        foreach ($v['ProductInfo'] as $k2=>$v2){
        if(is_array($v2)){
        foreach ($v2 as $k3=>$v3){
        if(isset($v3['coupon'])){
        foreach ($v3['coupon'] as $k4=>$v4){
        if(isset($v4['isUsable']) && $v4['isUsable'] == 1){
        $Store_use_coupon = 1;
        //调用使用coupon方法
        $Params['StoreId'] = $k;
        $Params['productId'] = $v3['ProductID'];
        $Params['SkuId'] = $v3['SkuID'];
        $Params['Qty'] = $v3['Qty'];
        $Params['DiscountLevel'] = 1;//如果为1则表示是seller级别的coupon,如果为0则表示为sku级别的coupon
        $Params['CouponId'] = $v4['CouponId'];
        $Params['CouponCode'] = isset($v4['coupon_code'])?$v4['coupon_code']:'';
        $Params['customer_id'] = $Uid;//用来获取coupon
        $Params['type'] = 1;//表示获取店铺级别的优惠券//用来获取coupon
        $Params['CouponRuleType'] = array(1,2);//用来获取coupon
        $Params['country_code'] = $Country;//用来获取coupon
        $Params['Lang'] = $Lang;
        $Params['Currency'] = $Currency;
        $res = $this->useCoupon($Uid, $Params);
        //在cartinfo里记录该使用信息
        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$k3]['coupon'][$k4]['isAutoSelect'] = 1;
        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$k3]['coupon'][$k4]['isAutoSelectData'] = $res;
        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$k3]['isUsedCoupon']['CouponId'] = $v4['CouponId'];
        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$k3]['isUsedCoupon']['CouponCode'] = isset($v4['coupon_code'])?$v4['coupon_code']:'';
        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$k3]['isUsedCoupon']['DiscountInfo'] = $res;
        break;
        }
        }
        }
        if($Store_use_coupon == 1) break;
        }
        }
        if($Store_use_coupon == 1) break;
        }
        }
        }
        }
         */

    }

    /**
     * 根据商家ID，skuID过滤出可用的coupon列表
     */
    public function getCouponList($params){
        //根据当前条件，获取全部coupon数据
        $result = (new CouponModel())->getCouponList([
            'store_id'=>$params['store_id'],//当前门店
            'CouponChannels'=>$params['CouponChannels'],//优惠渠道：1-全站、2-Web站、
            'CouponStrategy'=>$params['CouponStrategy'],//手动，自动
            'ActivityStrategy'=>1,////活动策略：1-线上活动、2-线下活动
//            'DiscountLevel'=>"1",//1-单品级别优惠，2-订单级别优惠
            'lang'=>$params['lang']
        ]);
        if(!empty($result)){
            //查询couponCode生成数量
            $coupon_id = CommonLib::getColumn('CouponId',$result);
            $code = (new CouponModel())->getCouponCodeCount($coupon_id);
            if(!empty($code)){
                foreach($result as $key => $value){
                    //拿到CouponCode
                    $codeData = CommonLib::filterArrayByKey($code,'_id',$value['CouponId']);
                    if(!empty($codeData)){
                        $result[$key]['CodeCount'] = $codeData['count'];
                    }
                }
            }

            $data = array();
            //根据规则，获取可用coupon
            if(!empty($result)){
                foreach($result as $k => $coupon){
                    if(THINK_ENV == CODE_RUNTIME_ONLINE){
                        if(in_array($coupon['CouponId'],[79,80,81,82])){
                            continue;
                        }
                    }

                    //手动conpon
                    if($coupon['CouponStrategy'] == 1){
                        $couponData = $this->analysisCouon($coupon,$params);
                        if(isset($couponData['description'])){
                            $data['manual'][$k] = $couponData;
                        }
                    }
                    //自动conpon
                    else{
                        $couponData = $this->analysisCouon($coupon,$params);
                        if(isset($couponData['description'])){
                            $data['auto'][$k] = $couponData;
                        }
                    }
                }
            }
            if(!empty($data) && is_array($data)){
                $data['auto'] = isset($data['auto']) ? array_values($data['auto']) : [];
                $data['manual'] = isset($data['manual']) ? array_values($data['manual']) : [];
            }
            return $data;
        }
        return array();
    }


    //解析cuopon
    private function analysisCouon($coupon,$params){
        $result = array();
        $lang = $params['lang'];
        //判断coupon是否有数量限制
        if($coupon['CouponNumLimit']['Type'] != 1){
            //特殊情况，coupon数量为1的情况,表示coupon无限可用
            if($coupon['CodeCount'] != 1){
                //查询当前已使用的CouponCode数量
                $res = $this->getCouponCode(['coupon_id'=>$coupon['CouponId'],'status'=>1]);
                //领取数量 == 生成数量，coupon不可用
                if($res >= $coupon['CodeCount'] ){
                    return $result;
                }
            }
        }
        $result['coupon_id'] = $coupon['CouponId'];
        //Coupon活动规则
        $couponRule = $coupon['CouponRuleSetting'];
        if($couponRule['CouponRuleType'] == 1){
            if($coupon['CouponStrategy'] == 1){
                $skus = (new ProductModel())->getSkus($params['product_id']);
                if(!empty($skus)){
                    $result['skus'] = CommonLib::getColumn('Code',$skus['Skus']);
                }
            }
            //1-全店铺使用,3-全站使用
            $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                $coupon['Description'][$lang]['Details'] : $coupon['Description'][DEFAULT_LANG]['Details'];
            $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
        }elseif($couponRule['CouponRuleType'] == 2){
            //2-制定限制规则
            $LimitData = isset($coupon['CouponRuleSetting']['LimitData']) ? $coupon['CouponRuleSetting']['LimitData'] : '';
            if(empty($LimitData)){
                return array();
            }
            switch($LimitData['LimitType']){
                case 1://1-指定商品
                    //查找所有SKU
                    $currentSku = array();
                    $skus = (new ProductModel())->getSkus($params['product_id']);
                    if(!empty($skus)){
                        $currentSku = CommonLib::getColumn('Code',$skus['Skus']);
                    }
                    if(strpos($LimitData['Data'],"\n") != false){
                        $isCouponSku = explode("\n",$LimitData['Data']);
                    }else{
                        $isCouponSku = explode(",",$LimitData['Data']);
                    }
                    //判断该SKU在不在指定商品内
                    $ret = array_intersect($currentSku,$isCouponSku);
                    if(!empty($ret)){
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            $coupon['Description'][$lang]['Details'] : $coupon['Description'][DEFAULT_LANG]['Details'];
                        $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
                        $result['skus'] = $isCouponSku;
                    }
                    break;
                case 2://2-指定分类
                    $classModel = new ProductClassModel();
                    $classArray = explode('-',$params['categoryPath']);
                    $newArray = $classArray;
                    if(!empty($classArray)){
                        foreach($classArray as $class){
                            $classData = $classModel->getClassDetail(['id'=>(int)$class]);
                            if(isset($classData['type']) && $classData['type'] == 1){
                                break;
                            }else{
                                if(isset($classData['pdc_ids']) && !empty($classData['pdc_ids'])){
                                    $newArray = array_merge($newArray,$classData['pdc_ids']);
                                }
                            }
                        }
                    }
                    if(strpos($LimitData['Data'],"\n") != false){
                        $ruleClassArray = explode("\n",$LimitData['Data']);
                    }else{
                        $ruleClassArray = explode(",",$LimitData['Data']);
                    }
                    $ret = array_intersect($newArray,$ruleClassArray);
                    if($ret){
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            $coupon['Description'][$lang]['Details'] : $coupon['Description'][DEFAULT_LANG]['Details'];
                        $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
                    }
                    break;
                case 3://3-指定品牌
                    //判断该spu的品牌ID，是否在指定品牌内
                    if(in_array($params['brand_id'],explode(',',$LimitData['Data']))){
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            $coupon['Description'][$lang]['Details'] : $coupon['Description'][DEFAULT_LANG]['Details'];
                        $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
                    }
                    break;
//                case 4://4-指定产品类型
//                    $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
//                        $coupon['Description'][$lang]['Details'] :$coupon['Description']['en']['Details'];//其他语种如果为空，默认取英文
//                    $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
//                    break;
                case 5://5-指定国家
                    //指定国家只能在下单选择地址才能指定是否能用这个优惠
                    $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                        $coupon['Description'][$lang]['Details'] : $coupon['Description'][DEFAULT_LANG]['Details'];
                    $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
                    break;
            }
        }
        return $result;
    }

    /**
     * 查询当前已使用的CouponCode数量
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponCode($params){
        $result = (new CouponModel())->getCodeCount($params);
        return $result;
    }


    public function addCoupon($params){
        $couponCode = '';
        //获取coupon规则
        $couponRule = (new CouponModel())->findCoupon(['coupon_id'=>$params['coupon_id'],'lang'=>$params['lang']]);
        if(!empty($couponRule)){
            //领取限制
            $type = $couponRule['ReceiveLimit'];
            switch($type){
                case 1://1-不限
                    $CodeCount = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>[0,1]]);
                    //特殊情况，coupon数量为1的情况,表示coupon无限可用
                    if(count($CodeCount) == 1){
                        $couponCode = end($CodeCount)['coupon_code'];
                    }else{
                        $codeList = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>0]);
                        $couponCode = end($codeList)['coupon_code'];
                    }
                    break;
                case 2://2-每人一次
                    $res = doCurl(API_URL.'cic/MyCoupon/getUserCouponCode',
                        ['customer_id'=>$params['customer_id'],'coupon_id'=>$params['coupon_id']],null,true);
                    if($res['code'] == 200){
                        if(!empty($res['data'])){
                            return apiReturn(['code'=>5010004,'msg'=>"You've received the coupon already!"]);
                        }
                    }
                    $codeList = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>0]);
                    $couponCode = end($codeList)['coupon_code'];
                    break;
                case 3://3-每人每天一次
                    //判断今天内是否领取
                    $start_time = strtotime(date('Y-m-d',time()));
                    $end_time = strtotime(date('Y-m-d',strtotime('+1 days')));
                    $res = doCurl(API_URL.'cic/MyCoupon/getUserCouponCode',
                        ['customer_id'=>$params['customer_id'],'coupon_id'=>$params['coupon_id'],'add_time'=>['between',[$start_time,$end_time]]],null,true);
                    if($res['code'] == 200) {
                        if (!empty($res['data'])) {
                            return apiReturn(['code' => 5010003, 'msg' => "You've received the coupon today, please come back tomorrow!"]);
                        }
                    }
                    //判断领取过的coupon_code不能重复
                    $codeList = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>0]);
                    $couponCode = end($codeList)['coupon_code'];
                    break;
            }
        }
        if(empty($couponCode)){
            return apiReturn(['code'=>5010002,'msg'=>'领取失败']);
        }
        //coupon已领取
        (new CouponModel())->updateCodeStatus(['coupon_code'=>$couponCode,'coupon_id'=>$params['coupon_id']]);

        //新增coupo
        $res = doCurl(API_URL.'cic/MyCoupon/mallAddCoupon', [
            'coupon_id'=>$params['coupon_id'],
            'customer_id'=>$params['customer_id'],
            'coupon_code'=>$couponCode,
            'EndTime'=>$couponRule['CouponTime']['EndTime'],
            'CouponChannels'=>$couponRule['CouponChannels'],
        ],null,true);
        if($res['code'] == 200) {
            return apiReturn(['code'=>5010001,'msg'=>'Congratulations! You received this coupon!']);
        }else{
            return apiReturn(['code'=>5010002,'msg'=>'Sorry, you failed to get the coupon!']);
        }
    }

    /**
     * 购物车 - seller级别coupon - 获取符合coupon规则的产品总数量和总价格
     * @param $v 单个coupon
     * @param $CartInfo 购物车数据
     * @param $Uid 用户ID
     * @param $StoreId 店铺ID
     * @param $ShipTo 国家
     * @param $seller_coupon_all_nums seller级别符合规则的产品总数量
     * @param $seller_coupon_all_prices seller级别符合规则的产品总价格
     */
    public function getNumsAndPriceForResetCouponUseable($v, $CartInfo,$Uid,$StoreId,$ShipTo,&$seller_coupon_all_nums,&$seller_coupon_all_prices){
        //限定规则，需要根据限定的规则重新计算指定的产品总数量和总价格
        if(isset($v['CouponRuleSetting']['CouponRuleType']) && $v['CouponRuleSetting']['CouponRuleType'] == 2){
            $seller_coupon_all_nums = 0;
            $seller_coupon_all_prices = 0;
            //获取SKU信息,与规则相匹配
            if(isset($v['CouponRuleSetting']['LimitData']['Data'])) {
                if (strpos($v['CouponRuleSetting']['LimitData']['Data'], "\n") != false) {
                    $LimitData = explode("\n", $v['CouponRuleSetting']['LimitData']['Data']);
                } else {
                    $LimitData = explode(",", $v['CouponRuleSetting']['LimitData']['Data']);
                }
                if(isset($v['CouponRuleSetting']['LimitData']['LimitType'])){
                    switch ($v['CouponRuleSetting']['LimitData']['LimitType']){
                        case 1:
                            //指定商品
                            if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 1){
                                //取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){

                                        //获取产品SKU code start
                                        $_sku_code = '';
                                        //$v11['ShipTo'] 区域定价
                                        $_ProductInfo = $this->CommonService->ProductInfoByID($v11['ProductID'],$v11['SkuID'],'en',null,$v11['ShipTo']);
                                        if(
                                            isset($_ProductInfo['data']['Skus'])
                                            && is_array($_ProductInfo['data']['Skus'])
                                        ){
                                            foreach ($_ProductInfo['data']['Skus'] as $k20=>$v20){
                                                if ($v20['_id'] == $v11['SkuID']){
                                                    $_sku_code = $v20['Code'];
                                                    break;
                                                }
                                            }
                                        }
                                        //获取产品SKU code end

                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
//                                            && !in_array($v11['SkuID'],$LimitData)
                                            && !in_array($_sku_code,$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }else{
                                //不取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){

                                        //获取产品SKU code start
                                        $_sku_code = '';
                                        //$v11['ShipTo'] 区域定价
                                        $_ProductInfo = $this->CommonService->ProductInfoByID($v11['ProductID'],$v11['SkuID'],'en',null,$v11['ShipTo']);
                                        if(
                                            isset($_ProductInfo['data']['Skus'])
                                            && is_array($_ProductInfo['data']['Skus'])
                                        ){
                                            foreach ($_ProductInfo['data']['Skus'] as $k20=>$v20){
                                                if ($v20['_id'] == $v11['SkuID']){
                                                    $_sku_code = $v20['Code'];
                                                    break;
                                                }
                                            }
                                        }
                                        //获取产品SKU code end

                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && in_array($_sku_code,$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }
                            break;
                        case 2:
                            //指定分类
                            if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 1){
                                //取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //获取一级分类对应的ERP类别ID
                                        $first_map = $this->CommonService->getErpCategoryMapById($v11['FirstCategory']);
                                        Log::record('$first_map20190104:'.print_r($first_map, true));
                                        $rules_res = array_intersect($first_map,$LimitData);
                                        Log::record('$rules_res20190104:'.print_r($rules_res, true));
                                        Log::record('$LimitData20190104:'.print_r($LimitData, true));
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
//                                            && !in_array($v11['FirstCategory'],$LimitData)
                                            && empty($rules_res)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }else{
                                //不取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //获取一级分类对应的ERP类别ID
                                        $first_map = $this->CommonService->getErpCategoryMapById($v11['FirstCategory']);
                                        Log::record('$first_map20190104:'.print_r($first_map, true));
                                        $rules_res = array_intersect($first_map,$LimitData);
                                        Log::record('$rules_res20190104:'.print_r($rules_res, true));
                                        Log::record('$LimitData20190104:'.print_r($LimitData, true));
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
//                                            && in_array($v11['FirstCategory'],$LimitData)
                                            && !empty($rules_res)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }
                            break;
                        case 3:
                            //指定品牌
                            if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 1){
                                //取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && !in_array($v11['BrandId'],$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }

                            }else{
                                //不取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && in_array($v11['BrandId'],$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }
                            break;
                        case 4:
                            //指定产品类型 TODO ？？？？
                            if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 1){
                                //取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && !in_array($v11['ProductType'],$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }else{
                                //不取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && in_array($v11['ProductType'],$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }
                            break;
                        case 5:
                            //指定国家
                            if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 1){
                                //取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && !in_array($ShipTo,$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }

                            }else{
                                //不取反
                                foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                                    foreach ($v10 as $k11=>$v11){
                                        //去除没选中或没有运输方式的数据
                                        if (
                                            $v11['IsChecked'] != 0
                                            && $v11['ShippModelStatusType'] != 3
                                            && in_array($ShipTo,$LimitData)
                                        ){
                                            $seller_coupon_all_nums += $v11['Qty'];
                                            $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                                        }
                                    }
                                }
                            }
                            break;
                    }
                }
            }
        }
    }

    /**
     * 获取coupon赠送券，倍数情况下赠送商品的数量
     * @param $p1 满足赠送券产品的总数量
     * @param $p2 赠送券配置的赠送券商品数量
     * @param int $p3 数量规则限定，默认为1（在没有指定数量规则情况下），如在3~20之间的数量才能使用优惠券，这里的$p3指的是起始值3
     * @return float|int
     * 20190329 tinghu.liu
     */
    public function getCouponMultipleProNumber($p1, $p2, $p3=1){
        //默认初始化为满足coupon一次的赠送数量
        $num = $p2;
        //容错判断
        $p3 = $p3>0?$p3:1;
        if ($p1 < $p3){
            return $num;
        }
        $num = floor($p1/$p3)*$p2;
        return $num;
    }
}