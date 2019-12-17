<?php
namespace app\app\services;

use app\app\model\RegionModel;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;


/**
 * 运费计算
 */
class ProductShippingService extends BaseService
{

    //NOCNOC国家配置
    public static $nocnoc_country = [
        'AR','BR'
    ];
    protected $Tracking_Information_price = 2;

    /**
     * 这个产品到某个国家各种运费方式的计算
     * @param $params
     * @return array
     */
    public function countProductShipping($params){

        $productService = new ProductService();
        $nocnoc = array();
        $add_shipping = 0.03;
        //判断缓存，是否有这个产品的信息
        $product = $productService->getProductToShipping($params);
        //缓存
        if(empty($product)){
            return [];
        }
        $packingList = isset($product['PackingList']) ? $product['PackingList'] : '';
        if(empty($packingList)){
            return [];
        }
        $count = $params['count'];
        $isMvp = $product['IsMVP'];

        $productWeight = $this->getPorductWeight($count,$packingList);
        //国家名称
        $arr = (new RegionModel())->getCountry(['Code'=>$params['country']]);
        //缓存
        $CountryName = $arr['Name'];

        if(empty($currentRate)){
            //币种切换
            if($params['currency'] != DEFAULT_CURRENCY){
                $currentRate = $this->getCurrencyRate($params['currency']);
            }
        }

        //NOCNOC国家配置
        if(in_array($params['country'],self::$nocnoc_country)){
            $nocnoc[0]['Cost'] = 4;
            if($params['currency'] != DEFAULT_CURRENCY){
                $nocnoc[0]['Cost'] = sprintf("%01.2f",4 * $currentRate);
            }
            $nocnoc[0]['ShippingFee'] = 2;
            $nocnoc[0]['ShippingService'] = 'NOCNOC';
            $nocnoc[0]['EstimatedDeliveryTime'] = '1-3 days';
            $nocnoc[0]['CountryCode'] = $params['country'];
            $nocnoc[0]['CountryName'] = $CountryName;
            $nocnoc[0]['TrackingInformation'] = 'Available';
        }
        //获取运费模板
        $shipping = $productService->getSpuShipping(['spu'=>$params['spu'],'country'=>$params['country']]);
        if(empty($shipping)){
            if(empty($nocnoc)){
                return array();
            }
            return $nocnoc;
        }

        //运费规则
        $rules = array_values($this->getShippingRule($shipping));
        $default = array();
        //计算运费价格
        foreach($rules as $key => $rule){
            $discount = $sWeight = $price = 0;
            if($rule['ShippingType'] != 2){
                //是否有折扣
                if(isset($rule['Discount']) && !empty($rule['Discount'])){
                    $discount = (double)$rule['Discount'];
                }
                //重量还是数量
                if(isset($rule['custom_type'])){
                    //数量规则
                    $ret = $this->countShippingByQty($key,$count,$rule,$default);
                    if(!$ret) continue;
                }else{
                    //自定义规则
                    if(isset($rule['delivery_type']) && $rule['delivery_type'] == 3){
                        if($productWeight > $rule['FirstData']){
                            //判断产品重量是否在最大阶梯范围，如果产品重量超过最大的阶梯范围，那么这个运费方式就不支持
                            $isSupportShipping = end($rule['IncreaseRule']);
                            if($productWeight > $isSupportShipping['end_data']){
                                unset($default[$key]);
                                continue;
                            }
                            //自定义重量规则
                            $firstGearPrice = $this->countShippingByCustomWeight($productWeight,$rule);
                            $default[$key]['Cost'] = sprintf("%01.2f",$firstGearPrice + $rule['FirstPrice']);
                            //折扣
                            if(!empty($discount)){
                                //运费 = 阶梯价 + 首重价格
                                $default[$key]['Cost'] = round(($firstGearPrice + $rule['FirstPrice'])* $discount / 100,2);
                            }
                        }else{
                            $default[$key]['Cost'] = sprintf("%01.2f",$rule['FirstPrice']);
                            //折扣
                            if(!empty($discount)){
                                $default[$key]['Cost'] = round($rule['FirstPrice'] * $discount / 100,2);
                            }
                        }

                    }else{
                        //LMS重量规则
                        $GOODSWEIGHT  = $productWeight;

                        if(is_array($rule["IncreaseRule"])){
                            continue;
                        }
                        $calculation_formula = '<?php '.htmlspecialchars_decode($rule["IncreaseRule"]).' ?>';
                        eval( '?>' .$calculation_formula );
                        if($price <= 0){
                            continue;
                        }
                        //人民币切换美元
                        $rate = $this->getCurrencyRate(DEFAULT_CURRENCY,'CNY');

                        $default[$key]['Cost'] = sprintf("%01.2f",$price * $rate);
                        //折扣
                        if(!empty($discount)){
                            $default[$key]['Cost'] = round($price * $rate * $discount / 100,2);
                        }
                    }

                    //币种切换
                    if($params['currency'] != DEFAULT_CURRENCY){
                        $default[$key]['Cost'] = sprintf("%01.2f",(double)$default[$key]['Cost'] * $currentRate);
                    }
                }
            }else{
                $default[$key]['Cost'] = 0;
            }
            //增加%3运费
            if($default[$key]['Cost'] != 0){
                $newCost = round($default[$key]['Cost'] * $add_shipping,2) + $default[$key]['Cost'];
                $default[$key]['Cost'] = sprintf("%01.2f",$newCost);
            }

            $default[$key]['TrackingInformation'] = 'Available';
            //运费模板跟踪信息，跟平邮，产品价格挂钩
            if($rule['ShippingService'] == 'SuperSaver'){
                //初始化价格，避免找不到SKU的情况下报错
                $skuPrice = 0;
                $sku_id = isset($product['DefaultSkuId']) ? $product['DefaultSkuId'] : $product['Skus'][0]['_id'];
                //当前产品sku价格
                if(isset($params['skuid']) && !empty($params['skuid'])){
                    $sku = CommonLib::filterArrayByKey($product['Skus'],'_id',$params['skuid']);
                    if(!empty($sku)){
                        $skuPrice = $sku['SalesPrice'];
                        if(isset($sku['ActivityInfo']) && !empty($sku['ActivityInfo'])){
                            $skuPrice = $sku['ActivityInfo']['DiscountPrice'];
                        }
                    }
                }else{
                    $sku = CommonLib::filterArrayByKey($product['Skus'],'_id',$sku_id);
                    if(!empty($sku)){
                        $skuPrice = $sku['SalesPrice'];
                        if(isset($sku['ActivityInfo']) && !empty($sku['ActivityInfo'])){
                            $skuPrice = $sku['ActivityInfo']['DiscountPrice'];
                        }
                    }
                }
                $tracking = $this->Tracking_Information_price;
                if($params['currency'] != DEFAULT_CURRENCY){
                    $tracking = sprintf("%01.2f",$tracking * $currentRate);
                }
                if($skuPrice * $count < $tracking){
                    $default[$key]['TrackingInformation'] = 'Unavailable';
                }
            }
            //免邮状态 0免邮 1MVP 24小说到货提示 2不免邮
            $default[$key]['ShippingService'] = $rule['ShippingService'];
            $default[$key]['EstimatedDeliveryTime'] = empty($rule['EstimatedDeliveryTime']) ? '7-15 days': $rule['EstimatedDeliveryTime'].' days';
            $default[$key]['CountryCode'] = $rule['Country'];
            $default[$key]['CountryName'] = $CountryName;
            $default[$key]['OldShippingService'] = '';
            //专线名称
            if(isset($rule['ShippingServiceID']) && $rule['ShippingServiceID'] == 40){
                $default[$key]['OldShippingService'] = $rule['ShippingService'];
                $default[$key]['ShippingService'] = 'Exclusive';
            }
        }

        if(!empty($nocnoc)){
            $default = array_merge($default,$nocnoc);
        }

        if(!empty($default)){
            $default = CommonLib::multiArraySort($default,'Cost','SORT_ASC');
            $SuperSaverKey = 100;
            $SuperSaverPrice = $StandardPrice = 0;
            foreach($default as $key => $v){
                if(isset($v['ShippingService']) && $v['ShippingService'] == 'SuperSaver'){
                    $SuperSaverPrice = $v['Cost'];
                    $SuperSaverKey = $key;
                }
                if(isset($v['ShippingService']) && $v['ShippingService'] == 'Standard'){
                    $StandardPrice = $v['Cost'];
                }
                $default[$key]['ShippingFee'] = 2;
                //免邮状态 0免邮 1MVP 24小说到货提示 2不免邮
                if($v['Cost'] == 0){
                    if($isMvp == true || $isMvp == 1){
                        $default[$key]['Cost'] = FREE_SHIPPING_IN_ONEDAY;
                        $default[$key]['ShippingFee'] = 1;
                    }else{
                        $default[$key]['Cost'] = FREE_SHIPPING;
                        $default[$key]['ShippingFee'] = 0;
                    }
                }
            }
//                          当价商品的价格*数量大于等于N时，不展示平邮(原规则);
//                          当价商品的价格*数量小于N时，如果平邮价格高于挂号，则不展示平邮(本次新增);
            if($SuperSaverKey != 100){
                if($SuperSaverPrice > $StandardPrice && $StandardPrice != 0){
                    unset($default[$SuperSaverKey]);
                }
            }
        }
        return array_values($default);
    }


    /**
     * 获取产品重量
     * @param $count 产品数量
     * @param array $packingList 产品规格
     * @return mixed
     */
    private function getPorductWeight($count,$packingList){
        $productWeight = 0;
        if($count == 1){
            return $packingList['Weight'];
        }
        //获取产品重量
        if(empty($packingList['CustomeWeightInfo'])){
            //如果没有重量规则，那么就重量*数量
            $productWeight = $packingList['Weight'] * $count;
        }else{
            if(isset($packingList['CustomeWeightInfo']['Qty']) && !empty($packingList['CustomeWeightInfo']['Qty'])){
                //买家购买规定数量以内，按照产品重量计算运费
                if($count <= $packingList['CustomeWeightInfo']['Qty']){
                    $productWeight = $packingList['Weight'] * $count;
                }else{
                    //在此基础上，买家每多买?个，重量增加？KG
                    $overWeight = ceil(($count - $packingList['CustomeWeightInfo']['Qty']) / $packingList['CustomeWeightInfo']['IncreaseQty']) * $packingList['CustomeWeightInfo']['IncreaseWeight'];
                    $productWeight = ($packingList['Weight'] * $packingList['CustomeWeightInfo']['Qty']) + $overWeight;
                }
            }else{
                $productWeight = $packingList['Weight'] * $count;
            }
        }
        return $productWeight;
    }

    /**
     * 数量运费规则
     * @param int $key 键值
     * @param $count  产品数量
     * @param $rule 运费规则
     * @param $default 结果数组
     * @return true
     */
    private function countShippingByQty($key,$count,$rule,&$default){
        if($count > $rule['FirstData']){
            //产品数量没有阶梯计算，只有一条数据
            $IncreaseRule = end($rule['IncreaseRule']);

            //判断购买的产品数量，是否大于规定内的数量范围
            if($count > $IncreaseRule['start_data']){
                unset($default[$key]);
                return false;
            }
            //数量阶梯计算
            $firstGearPrice = ceil(($count - $rule['FirstData']) / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];

            $default[$key]['Cost'] = sprintf("%01.2f",$firstGearPrice + $rule['FirstPrice']);
        }elseif($count == $rule['FirstData']){
            $default[$key]['Cost'] = sprintf("%01.2f",$rule['FirstPrice']);
        }else{
            //判断数量是否在最低采购量
            unset($default[$key]);
            return false;
        }
        return true;
    }

    /**
     * 自定义重量运费规则
     * @param $productWeight  产品重量
     * @param $rule 运费规则
     * @return price
     */
    private function countShippingByCustomWeight($productWeight,$rule){
        $firstGearPrice = 0;
        //减去首重后，阶梯计算
        foreach($rule['IncreaseRule'] as $inKey => $IncreaseRule){
            //1百分比、2金额
//            $freight_type = $IncreaseRule['first_freight_type'];
            //自增的价格
//            if($freight_type == 1){
//                $IncreaseRule['add_freight'] = $rule['LMSFirstPrice'] * $IncreaseRule['add_freight'] / 100;
//                $IncreaseRule['first_freight'] = $rule['LMSFirstPrice'] * $IncreaseRule['first_freight'] / 100;
//            }

            //产品重量大于当前阶梯的结束值
            if($productWeight >= $IncreaseRule['end_data']){
                //价格算法，（阶梯结束 - 阶梯开始 ）/ 每增加多少kg  * 增加多少钱
                $price = ceil(($IncreaseRule['end_data'] - $IncreaseRule['start_data']) / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];
                //剩余重量
                $sWeight = $productWeight - $IncreaseRule['end_data'];
            }else{
                //没有剩余重量，说明产品重量在第一个阶梯范围
                if(empty($sWeight)){
                    $price = ceil(($productWeight - $rule['FirstData']) / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];
                }else{
                    $price = ceil($sWeight / $IncreaseRule['add_data']) * $IncreaseRule['add_freight'];
                }
            }
            //当前阶梯重量的价格
            $firstGearPrice = $firstGearPrice + $price;
            //不必进入下一个阶梯
            if($productWeight <= $IncreaseRule['end_data']){
                break;
            }
        }
        return $firstGearPrice;
    }

    /**
     * Lms规则重量运费规则
     * @param $productWeight  产品重量
     * @param $rule 运费规则
     * @return price
     */
    private function countShippingByLmsWeight($productWeight,$rule){
        $firstGearPrice = 0;
        //阶梯计算
        foreach($rule['IncreaseRule'] as $inKey => $IncreaseRule){
            //产品重量大于当前阶梯的结束值
            if($productWeight >= $IncreaseRule['end_weight']){
                //价格算法，（阶梯结束 - 阶梯开始 ）/ 每增加多少kg  * 增加多少钱
                $price = ceil(($IncreaseRule['end_weight'] - $IncreaseRule['start_weight']) / $IncreaseRule['add_weight']) * $IncreaseRule['add_freight'];
                //产品剩下的重量
                $sWeight = $productWeight - $IncreaseRule['end_weight'];
            }else{
                if(!empty($sWeight)){
                    $price = ceil($sWeight / $IncreaseRule['add_weight']) * $IncreaseRule['add_freight'];
                }else{
                    //没有剩余重量，说明产品重量在第一个阶梯范围
                    $price = ceil(($productWeight-$rule['FirstData']) / $IncreaseRule['add_weight']) * $IncreaseRule['add_freight'];
                }
            }
            //首重价格 + 当前阶段重量的价格
            $firstGearPrice = $firstGearPrice + $price;
            //不必进入下一个阶梯
            if($productWeight <= $IncreaseRule['end_weight']){
                break;
            }
        }
        return $firstGearPrice;
    }

    /**
     * 默认最优惠的运费
     * @param $isMvp=false 是否是mvp，mvp24小时到货
     * @param array $shipping 当前产品邮费信息
     * @param $currency = USD 币种
     * @return array
     */
    public function getDefaultShippingCost($shipping,$isMvp=false,$currency = 'USD'){
        $default = array();
        $service = new IndexService();
        if($currency != DEFAULT_CURRENCY){
            $rateService = new rateService();
            $currentRate = $rateService->getCurrentRate($currency);
        }
        $countryMenu = $service->getCountryList();
        $arr = CommonLib::filterArrayByKey($countryMenu,'Code', $shipping['ToCountry']);
        $CountryName = $arr['Name'];

        //运费规则
        $rules = array_values($this->getShippingRule($shipping));
        //循环取出运费
        if(!empty($rules)){
            foreach($rules as $key => $value){
                if($currency != DEFAULT_CURRENCY){
                    $default[$key]['Cost'] = sprintf("%01.2f",$value['Cost'] * $currentRate);
                }else{
                    $default[$key]['Cost'] = sprintf("%01.2f",$value['Cost']);
                }
                $default[$key]['ShippingService'] = $value['ShippingService'];
                $default[$key]['EstimatedDeliveryTime'] = $value['EstimatedDeliveryTime'];
                $default[$key]['CountryCode'] = $shipping['ToCountry'];
                $default[$key]['CountryName'] = $CountryName;
            }
        }

        //运费排序
//        $default = CommonLib::multiArraySort($default,'Cost','SORT_ASC');
        //默认取第一个
        $default = array_shift($default);
        $cost = (double)$default['Cost'];
        if(empty($cost)){
            $default['Cost'] = $isMvp == true ? FREE_SHIPPING_IN_ONEDAY : FREE_SHIPPING;
            $default['ShippingFee'] = $isMvp == true ? 1 : 0;
        }else{
            $default['ShippingFee'] = 2;
        }
        return $default;
    }

    /**
     * 运费规则
     * @param $result
     * @return array
     */
    public function getShippingRule($result){
        $rule = array();
        if(!empty($result)){
            $shippings = $result['ShippingCost'];
            foreach($shippings as $skey => $shipping){
                //运费类型：1-标准运费[有折扣，单位%，如：10，对应折扣10%，打九折]，2-卖家承担运费，3-自定义运费
                switch($shipping['ShippingType']){
                    case 1:
                        $rule[$skey]['Country'] = $result['ToCountry'];
                        $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                        $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                        $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                        $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                        $rule[$skey]['Discount'] = $shipping['discount'];
                        //标准规则
                        $rule[$skey]['IncreaseRule'] = $shipping['LmsRuleInfo'];
                        $rule[$skey]['ShippingType'] = $shipping['ShippingType'];
                        break;
                    case 2:
                        $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                        $rule[$skey]['Country'] = $result['ToCountry'];
                        $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                        $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                        $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                        $rule[$skey]['ShippingType'] = $shipping['ShippingType'];
                        break;
                    //卖家自定义物流运费计算规则
                    case 3:
                        //发货类型：1-标准运费，2-卖家承担运费, 3-自定义
                        $customerType = $shipping['ShippingTamplateRuleInfo']['delivery_type'];
                        switch($customerType){
                            case 1:
                                $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                                $rule[$skey]['Country'] = $result['ToCountry'];
                                $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                                $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                                $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                                $rule[$skey]['Discount'] = $shipping['ShippingTamplateRuleInfo']['discount'];
                                $rule[$skey]['IncreaseRule'] = $shipping['LmsRuleInfo'];
                                $rule[$skey]['ShippingType'] = 1;
                                //标准规则
                                break;
                            case 2:
                                $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                                $rule[$skey]['Country'] = $result['ToCountry'];
                                $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                                $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                                $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                                $rule[$skey]['ShippingType'] = 2;
                                break;
                            case 3:
                                $rule[$skey]['ShippingServiceID'] = $shipping['ShippingServiceID'];
                                $rule[$skey]['ShippingType'] = 3;
                                $rule[$skey]['Country'] = $result['ToCountry'];
                                $rule[$skey]['ShippingService'] = $shipping['ShippingService'];
                                $rule[$skey]['EstimatedDeliveryTime'] = $shipping['EstimatedDeliveryTime'];
                                $rule[$skey]['TrackingInformation'] = $shipping['TrackingInformation'];
                                //自定义运费
                                $customShipping = $shipping['ShippingTamplateRuleInfo']['CustomShipping'];
                                $rule[$skey]['FirstData'] = $customShipping[0]['first_data'];
                                $rule[$skey]['FirstPrice'] = $customShipping[0]['first_freight'];
                                $rule[$skey]['Cost'] = $rule[$skey]['FirstPrice'];
                                //自定义运费规则
                                $rule[$skey]['delivery_type'] = 3;
                                $rule[$skey]['IncreaseRule'] = $customShipping;
                                //按1重量还是2数量
                                if($customShipping[0]['custom_freight_type'] == 2){
                                    $rule[$skey]['custom_type'] = 2;
                                }
                                break;
                        }
                        break;
                }
            }
        }
        return $rule;
    }

}
