<?php
/**
 * Coupon服务类
 * User: tinghu.liu
 * Date: 2018/10/12
 */
namespace app\app\services;


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
        $GetCouponParams['CouponChannels'] = array(1,2);//1-全站、2-Web站
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
        $Url = MALL_API."/cic/MyCoupon/getCouponList";
        //此时领取到的coupon是经过type过滤的，要么是商家券，要么是平台券
        $CouponInfo = doCurl($Url,$Data,null,true);
        $GetManualCouponParams['CouponChannels'] = array(1,2);//1-全站、2-Web站
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
        $Url = MALL_API."/cic/MyCoupon/getCouponCount";
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
     * 使用coupon
     * @param unknown $Uid
     * @param unknown $Params
     */
    public function useCoupon($Uid,$Params){
        $StoreId = $Params['StoreId'];
        $productId = isset($Params['productId'])?$Params['productId']:0;
        $SkuId = isset($Params['SkuId'])?$Params['SkuId']:0;
        $Qty = isset($Params['Qty'])?$Params['Qty']:1;
        $DiscountLevel = $Params['DiscountLevel'];
        $CouponId = $Params['CouponId'];
		$CouponCode = $Params['CouponCode'];
        $IsSellerSku = $Params['DiscountLevel'];//如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
        $Lang = $Params['Lang'];
        $Currency = $Params['Currency'];
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
                            $AllPrice += $v1['ProductPrice'];
                            $AllQty += $v1['Qty'];
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
                $AllPrice = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['ProductPrice'];
                $AllQty = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['Qty'];
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
        foreach ($CouponInfo as $k=>$v){
            if(isset($v['CouponId']) && $v['CouponId'] == $CouponId && isset($v['coupon_code']) && $v['coupon_code']){
                $CouponCode = $v['coupon_code'];
                //单品级别的优惠
                if(isset($v['DiscountType']['Type'])){
                    switch ($v['DiscountType']['Type']){
                        case 1:
                            //代金券
                            $DiscountType['Type'] = 1;
                            if(isset($v['DiscountType']['TypeOne'])){
                                $DiscountType['code'] = 1;
                                $DiscountType['IsError'] = 0;
                                $TmpPrice = $v['DiscountType']['TypeOne'];
                                //$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$v['DiscountType']['TypeOne']);//汇率转换
                                //$TmpPrice = sprintf("%.2f", $TmpPrice);
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
                                                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);
                                                    if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
                                                        $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);//false不使用缓存
                                                        //找到赠送的sku详细信息
                                                        $select_sku_info = [];
                                                        if (isset($ProductInfo['data']['Skus'])){
                                                            foreach ($ProductInfo['data']['Skus'] as $skus){
                                                                if ($skus['_id'] == $SkuIdArr[1]){
                                                                    $select_sku_info = $skus;
                                                                    break;
                                                                }
                                                            }
                                                        }

                                                        if(isset($ProductInfo['data']['ImageSet']['ProductImg'][0])){
                                                            $DiscountType['SkuInfo'][0]['ProductImg'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        }
                                                        $DiscountType['SkuInfo'][0]['ProductId'] = $ProductId[1];
                                                        $DiscountType['SkuInfo'][0]['SkuId'] = $SkuIdArr[1];

                                                        $DiscountType['SkuInfo'][0]['SkuCode'] = isset($select_sku_info['Code'])?$select_sku_info['Code']:'';
                                                        $DiscountType['SkuInfo'][0]['Title'] = isset($ProductInfo['data']['Title'])?$ProductInfo['data']['Title']:'';
                                                        $DiscountType['SkuInfo'][0]['Qty'] = 1;
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
                                                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);
                                                    if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
                                                        $ProductInfo = $this->CommonService->ProductInfoByID($ProductId[1],$SkuIdArr[1],$Lang,$Currency);//false不使用缓存
                                                        //找到赠送的sku详细信息
                                                        $select_sku_info = [];
                                                        if (isset($ProductInfo['data']['Skus'])){
                                                            foreach ($ProductInfo['data']['Skus'] as $skus){
                                                                if ($skus['_id'] == $SkuIdArr[1]){
                                                                    $select_sku_info = $skus;
                                                                    break;
                                                                }
                                                            }
                                                        }

                                                        if(isset($ProductInfo['data']['ImageSet']['ProductImg'][0])){
                                                            $DiscountType['SkuInfo'][0]['ProductImg'] = $ProductInfo['data']['ImageSet']['ProductImg'][0];
                                                        }
                                                        $DiscountType['SkuInfo'][$sku_k]['ProductId'] = $ProductId[1];
                                                        $DiscountType['SkuInfo'][$sku_k]['SkuId'] = $SkuIdArr[1];

                                                        $DiscountType['SkuInfo'][$sku_k]['SkuCode'] = isset($select_sku_info['Code'])?$select_sku_info['Code']:'';

                                                        $DiscountType['SkuInfo'][$sku_k]['Title'] = isset($ProductInfo['data']['Title'])?$ProductInfo['data']['Title']:'';
                                                        $DiscountType['SkuInfo'][$sku_k]['Qty'] = isset($QtyArr[1])?$QtyArr[1]:1;
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
                                $TmpPrice = ($AllPrice * $AllQty)*(1-$Discount);
								//$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$TmpPrice);//汇率转换
								//$TmpPrice = sprintf("%.2f", $TmpPrice);
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
                                $TmpPrice = ($AllPrice * $AllQty)-($AllQty * $AppointPrice);
								//$TmpPrice = $this->CommonService->calculateRate('USD',$Currency,$TmpPrice);//汇率转换
								//$TmpPrice = sprintf("%.2f", $TmpPrice);
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
            $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
            $TmpPrice = sprintf("%.2f", $TmpPrice);
            $DiscountType['DiscountPrice'] = $TmpPrice;
        }
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

    public function changeProductNumsUseCoupon(&$TmpCoupon,$CartInfo,$StoreID,$ProductID,$SkuID,$Uid,$Currency){
        $IsChecked = isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['IsChecked'])?$CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['IsChecked']:0;
        $ShippModelStatusType = isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippModelStatusType'])?$CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippModelStatusType']:3;
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        //先获取该seller下的coupon，重新计算是否有可用的
        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['coupon'])){
            $TmpCoupon['sellerCoupon'] = $this->CommonService->transformToIndexArray($CartInfo[$Uid]['StoreData'][$StoreID]['coupon']);
            $Qty = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'];
            $ProductPrice = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ProductPrice'];
            //需要获取seller下的所有产品总数量和总价格，之后再判断相应规则
            $seller_coupon_all_nums = 0;
            $seller_coupon_all_prices = 0;
            foreach ($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'] as $k10=>$v10){
                foreach ($v10 as $k11=>$v11){
                    //去除没选中或没有运输方式的数据
                    if ($v11['IsChecked'] != 0 && $v11['ShippModelStatusType'] != 3){
                        $seller_coupon_all_nums += $v11['Qty'];
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
                        if(in_array($SkuID,$UsableSku)){
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
                        }
                    }else{
                        $isUsable = 0;
                    }
                }else {
                    $isUsable = 0;
                }
                $TmpCoupon['sellerCoupon'][$k]['isUsable'] = $isUsable;
                /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. start **/
                $is_update_cart = false;
                if ($isUsable == 0){
                    if (isset($CartInfo[$Uid]['StoreData'][$StoreID]['isUsedCoupon'])){
                        $is_update_cart = true;
                        unset($CartInfo[$Uid]['StoreData'][$StoreID]['isUsedCoupon']);
                    }
                }
                if ($is_update_cart){
                    $this->redis->set(SHOPPINGCART_.$Uid, $CartInfo);
                }
                /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. end **/
            }
        }
        //再获取具体sku下的coupon，重新计算是否有可用的
        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['coupon'])){
            $TmpCoupon['skuCoupon'] = $this->CommonService->transformToIndexArray($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['coupon']);
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

                    /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. start **/
                    $is_update_cart = false;
                    if ($isUsable == 0){
                        if (isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['isUsedCoupon'])){
                            $is_update_cart = true;
                            unset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['isUsedCoupon']);
                        }
                    }
                    if ($is_update_cart){
                        $this->redis->set(SHOPPINGCART_.$Uid, $CartInfo);
                    }
                    /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. end **/
                }
            }
        }
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
}