<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use app\app\model\ProductActivityModel;
use app\app\model\ProductClassModel;
use app\app\model\ProductModel;
use think\Cache;
use think\Exception;
use think\Request;


/**
 * 活动产品数据
 */
class ProductActivityService extends BaseService
{
    const RESULT_COUNT = 20;//取数缓存

    /**
     * 活动进行中的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getOnSaleList($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;

        $result = array();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['current_time'=>time(),'type'=>'5','status'=>3]);
        $activity['_id'] = 100;
        $activity['activity_end_time'] = 1539964800;
        if(!empty($activity)){
            $params['activity_id'] = $activity['_id'];
            //找出活动销售第一的产品
            $salsFirst = (new ProductModel())->findProduct(['activity_id'=>$activity['_id'],'activitySalse'=>true]);
            $result = (new ProductModel())->selectActivityProduct($params);
            if(!empty($result['data'])){
                if(!empty($salsFirst)){
                    foreach($result['data'] as $key => $v){
                        if($v['_id'] == $salsFirst['_id']){
                            unset($result['data'][$key]);
                        }
                    }
                    array_unshift($result['data'],$salsFirst);
                    array_values($result['data']);
                }
                $result['data'] = $this->getFlashData($result['data'],$params);
                //币种切换
                if(!empty($result['data']) && is_array($result['data'])){
                    if($params['currency'] != DEFAULT_CURRENCY){
                        $currentRate =  $this->getCurrencyRate($params['currency']);
                        if(!empty($currentRate)){
                            foreach($result['data'] as $key => $val){
                                if(isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00'){
                                    $result['data'][$key]['OriginalPrice'] = sprintf("%01.2f",(double)$val['OriginalPrice'] * $currentRate);
                                }
                                if(isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00'){
                                    $result['data'][$key]['SalesPrice'] = sprintf("%01.2f",(double)$val['SalesPrice'] * $currentRate);
                                }
                            }
                        }
                    }
                }
                //当前活动剩余时间
                $result['remainingTime'] = $activity['activity_end_time'] - time();
            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }


    /**
     * 下一场活动开始的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getComingSoonLists($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;

        $result = array();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['soon_time'=>time(),'type'=>'5','status'=>5]);
        $activity['_id'] = 101;
        $activity['activity_start_time'] = 1539964800;
        if(!empty($activity)){
            $currentRate = '';
            if($params['currency'] != DEFAULT_CURRENCY){
                $currentRate =  $this->getCurrencyRate($params['currency']);
            }

            $activityResult = (new ProductActivityModel())->getActivityProduct(['activity_id'=>$activity['_id']]);
            if($activityResult){
                $flashData = array();
                $spu = CommonLib::getColumn('SPU',$activityResult);
                $params['product_id'] = CommonLib::supportArray($spu);
                $result = (new ProductModel())->selectActivityProduct($params);
                if(!empty($result['data'])){
                    $lang = $params['lang'];
                    foreach($result['data'] as $k => $product){
                        $activityProduct = CommonLib::filterArrayByKey($activityResult,'SPU',$product['_id']);
                        //产品id
                        $flashData[$k]['id'] = isset($product['_id']) ? $product['_id'] : '';
                        //首图
                        $flashData[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
                        if(empty($flashData[$k]['FirstProductImage'])){
                            $flashData[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
                        }
                        //链接地址组合
                        $flashData[$k]['LinkUrl'] ='/p/'.$product['RewrittenUrl'].'-'.$product['_id'];//链接地址
                        //标题
                        $flashData[$k]['Title'] = isset($product['Title']) ? $product['Title'] : '';
                        //语言切换 --公共方法
                        if(self::DEFAULT_LANG != $lang){
                            $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);
                            $flashData[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) ? $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
                        }
                        //原价的价格区间
                        $originalLowPrice = isset($product['LowPrice']) ? sprintf('%01.2f',$product['LowPrice']) : '';//最低价格
                        $originalHightPrice = isset($product['HightPrice']) ? sprintf('%01.2f',$product['HightPrice']) : '';//最高价

                        //折扣后的价格区间
                        $discountLowPrice = isset($activityProduct['DiscountLowPrice']) ? sprintf('%01.2f',$activityProduct['DiscountLowPrice']) : '';//最低价格
                        $discountHightPrice = isset($activityProduct['DiscountHightPrice']) ? sprintf('%01.2f',$activityProduct['DiscountHightPrice']) : '';//最高价

                        //价格逻辑处理
                        $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
                        //商品展示的销售价格
                        $flashData[$k]['SalesPrice'] = $priceArray['LowPrice'];
                        //原价
                        $flashData[$k]['OriginalPrice'] = $priceArray['OriginalLowPrice'];


                        if($params['currency'] != DEFAULT_CURRENCY){
                            if(!empty($currentRate)){
                                //商品展示的销售价格
                                $flashData[$k]['SalesPrice'] = sprintf("%01.2f",(double)$flashData[$k]['SalesPrice'] * $currentRate);
                                //原价
                                $flashData[$k]['OriginalPrice'] = sprintf("%01.2f",(double)$flashData[$k]['SalesPrice'] * $currentRate);
                            }
                        }

                        //折扣
                        $flashData[$k]['Discount'] = isset($activityProduct['HightDiscount']) ? sprintf('%01.2f',$activityProduct['HightDiscount']) : '';

                        $flashData[$k]['firstClassId'] = (int)$product['FirstCategory'];
                        $flashData[$k]['isActivity'] = (int)$product['IsActivity'];
                        $flashData[$k]['isMvp'] = (int)$product['IsMVP'];
                        //币种符号
                        $flashData[$k]['currencyCode'] = DEFAULT_CURRENCY;
                        $flashData[$k]['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
                        if(self::DEFAULT_CURRENCY != $params['currency']) {
                            $flashData[$k]['currencyCode'] = $params['currency'];
                            $flashData[$k]['currencyCodeSymbol'] = $this->getCurrencyCode($params['currency']);
                        }
                        //flashDeals产品肯定是折扣产品，展示折扣图标
                        $flashData[$k]['tagName'] = 'tag-discount';
                        //下一场活动未开始，进度条为0
                        $flashData[$k]['TimeGone'] = 0;
                    }
                }
                $result['data'] = $flashData;
                $result['soonTime'] = isset($activity['activity_start_time']) ? $activity['activity_start_time'] - time() : '';
            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * 首页活动进行中的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getHomeFlash($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ?  $params['currency'] : DEFAULT_CURRENCY;
        $time = time();
        $product = array();
        if(config('cache_switch_on')) {
            $product = $this->redis->get(HOME_FLASH_DATA.'_'.$params['lang']);
        }
        if(empty($product)){
            //当前时间是否有活动
            $activity = (new ProductActivityModel())->getActivity(['current_time'=>$time,'type'=>'5','status'=>3]);
            if(!empty($activity)){
                //格式化产品ID
                $params['activity_id'] = $activity['_id'];
                $params['page_size'] = self::RESULT_COUNT;
                $params['salesCounts'] = true;
                $params['salesRank'] = true;
                $result = (new ProductModel())->selectActivityProduct($params);
                if(!empty($result)){
                    $product['product'] = $this->getFlashData($result['data'],$params);
                    //活动剩余的时间
                    $product['time'] = $activity['activity_end_time'] - $time;
                }
                $this->redis->set(HOME_FLASH_DATA.'_'.$params['lang'],$product,$product['time']);
            }
        }

        //币种切换
        if(isset($product['product']) && !empty($product['product'])){
            if($params['currency'] != DEFAULT_CURRENCY){
                //币种切换费率
                $currentRate =  $this->getCurrencyRate($params['currency']);
                if(!empty($currentRate)){
                    foreach($product['product'] as $key => $val){
                        if(isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00'){
                            $product['product'][$key]['OriginalPrice'] = sprintf("%01.2f",(double)$val['OriginalPrice'] * $currentRate);
                        }
                        if(isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00'){
                            $product['product'][$key]['SalesPrice'] = sprintf("%01.2f",(double)$val['SalesPrice'] * $currentRate);
                        }
                    }
                }
            }
        }

        return apiReturn(['code'=>200, 'data'=>$product]);

    }

    /**
     * 第一场次的剩余时间
     * 第二场次的开始时间
     * @return mixed
     */
    public function getActivityTime($params){

        $time = time();
        //当前时间是否有活动
        $sale = (new ProductActivityModel())->getActivity(['current_time'=>$time,'type'=>'5','status'=>3]);

        $result['saleTime'] = isset($sale['activity_end_time']) ? $sale['activity_end_time'] - $time : '';
//        if(!empty($sale)){
//            $params['activity_id'] = $sale['_id'];
//            $product = (new ProductModel())->selectActivityProduct($params);
//            if(empty($product['data'])){
//                $result['saleTime'] = '';
//            }
//        }
        //一级分类，产品数量列表
//        if(!empty($result['saleTime'])){
//            $result['saleClass'] = $this->getActivityCategoryCount($sale,$params['lang']);
//        }
        $soon = (new ProductActivityModel())->getActivity(['soon_time'=>$time,'type'=>'5','status'=>5]);
        $result['soonTime'] = isset($soon['activity_start_time']) ? $soon['activity_start_time'] - $time : '';
//        if(!empty($soon)){
//            $params['activity_id'] = $soon['_id'];
//            $product = (new ProductModel())->selectActivityProduct($params);
//            if(empty($product['data'])){
//                $result['soonTime'] = '';
//            }
//        }
        //一级分类，产品数量列表
//        if(!empty($result['soonTime'])){
//            $result['soonClass'] = $this->getActivityCategoryCount($soon,$params['lang']);
//        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * flashDeals活动 对应的产品分类数量
     * @param $activity
     * @param $lang
     * @return array|false|\PDOStatement|string|\think\Model
     */
    private function getActivityCategoryCount($activity,$lang){
        $class_list = array();
        $productModel = new ProductModel();
        if(!empty($activity)){
            $countData = $productModel->groupByProductCategory(null,'$FirstCategory',$activity['_id']);
            if(!empty($countData)){
                $countData = json_decode(json_encode($countData),true);
                //格式化
                $class_id = CommonLib::supportArray(CommonLib::getColumn('_id',$countData));
                //获取分类详情
                $class_list = (new ProductClassModel())->selectClass(['class_id' =>$class_id,'lang'=>$lang]);
                if(!empty($class_list)){
                    foreach($class_list as $key => $class){
                        $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                        //后面多语言判断，更换
                        $class_list[$key]['title'] = $class['title_en'];
                        $class_list[$key]['count'] = $count['count'];
                    }
                }
            }
        }
        return $class_list;
    }
}
