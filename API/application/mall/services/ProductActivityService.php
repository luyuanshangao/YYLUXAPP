<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\ProductActivityModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductModel;
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
        $time = time();
        $result = array();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['current_time' => $time,'type'=>'5','status'=>3]);
        if(!empty($activity)){
            $params['activity_id'] = $activity['_id'];
            //找出活动销售第一的产品
//            $salsFirst = array();
//            $salsFirst = (new ProductModel())->findProduct(['activity_id'=>$activity['_id'],'activitySalse'=>true]);
//            $params['product_id'] = '2072160';
            $result = (new ProductModel())->selectActivityProduct($params);
            //默认值
            $result['soonTime'] = 0;
            $result['remainingTime'] = 0;
            if(!empty($result['data'])){
//                if(!empty($salsFirst)){
//                    foreach($result['data'] as $key => $v){
//                        if($v['_id'] == $salsFirst['_id']){
//                            unset($result['data'][$key]);
//                        }
//                    }
//                    array_unshift($result['data'],$salsFirst);
//                    array_values($result['data']);
//                }
                $result['data'] = $this->getFlashData($result['data'],$params);
                //当前活动剩余时间
                $result['remainingTime'] = $activity['activity_end_time'] - $time;
                //下一场活动开始时间
                $soon = (new ProductActivityModel())->getActivity(['soon_time' => $time,'type' => '5','status' => 5]);
                $result['soonTime'] = isset($soon['activity_start_time']) ? $soon['activity_start_time'] - $time : '';
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
        $country = isset($params['country']) ? trim($params['country']) : null;//国家区域售价
        $result = array();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['soon_time'=>time(),'type'=>'5','status'=>5]);
        if(!empty($activity)){
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

                        //国家区域价格
                        if(!empty($country)){
                            $regionPrice = $this->getProductRegionPrice($product['_id'],$country);
                            //这个产品有国家区域价格
                            if(!empty($regionPrice)){
                                $this->handleProductRegionPrice($product,$regionPrice);
                            }
                        }

                        //原价的价格区间
                        $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
                        $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

                        //折扣后的价格区间
                        $discountLowPrice = !empty($activityProduct['DiscountLowPrice']) ? (string)$activityProduct['DiscountLowPrice'] : '';//最低价格
                        $discountHightPrice = !empty($activityProduct['DiscountHightPrice']) ? (string)$activityProduct['DiscountHightPrice'] : '';//最高价

                        //价格逻辑处理
                        $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
                        //商品展示的销售价格
                        $flashData[$k]['SalesPrice'] = $priceArray['LowPrice'];
                        //原价
//                        $flashData[$k]['OriginalPrice'] = $priceArray['OriginalLowPrice'];
                        //如果有市场价，原价展示市场价，add by zhongning 20190507
//                        $flashData[$k]['OriginalPrice'] = !empty($activityProduct['LowListPrice']) ? (string)$activityProduct['LowListPrice'] :
//                            $priceArray['OriginalLowPrice'];
                        $flashData[$k]['OriginalPrice'] = (string)$priceArray['OriginalLowPrice'];
                        //折扣
                        $flashData[$k]['Discount'] = !empty($activityProduct['HightDiscount']) ? (string)$activityProduct['HightDiscount'] : '';
                        //折扣按市场价格算
//                        if(!empty($activityProduct['LowListPrice'])){
//                            $result[$k]['Discount'] = (string)round($priceArray['LowPrice']/$activityProduct['LowListPrice'],2);
//                        }
                        $flashData[$k]['firstClassId'] = $product['FirstCategory'];

                        //运费状态  0免邮  1MVP 24小时到货提示 2不免邮
                        $flashData[$k]['ShippingFee'] = isset($product['ShippingFee']) ? $product['ShippingFee'] : 0;//是否免邮
                        //是否是MVP产品
                        $ismvp = isset($product['IsMVP']) && $product['IsMVP'] == true ? true : false;//是否免邮
                        if($ismvp){
                            $flashData[$k]['ShippingFee'] = $flashData[$k]['ShippingFee'] == 0 ? 1 : 3;//1:免邮24小时到货提示,3:24小时到货提示
                        }else{
                            $flashData[$k]['ShippingFee'] = $flashData[$k]['ShippingFee'] != 0 ? 2 : $flashData[$k]['ShippingFee'];
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
        $country = isset($params['country']) ? trim($params['country']) : '';
        $time = time();
        $product = array();
        if(config('cache_switch_on')) {
            $product = $this->redis->get(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$country);
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
                if(isset($result['data']) && !empty($result['data'])){
                    $product['product'] = $this->getFlashData($result['data'],$params);
                    //活动剩余的时间
                    $product['time'] = $activity['activity_end_time'] - $time;
                    //缓存
                    $this->redis->set(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$country,$product,$product['time']);
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

    /**
     * 活动进行中的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function mobileHomeProducts($params){
        $time = time();
        $result = array();
        $productModel = new ProductModel();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['current_time' => $time,'type'=>'5','status'=>3]);
        $product_count = 0;
        $product_ids = array();
        $page = isset($params['page']) ? $params['page'] : 1;
        $pagesize = isset($params['pagesize']) ? $params['pagesize'] :config('paginate.list_rows') ;
        if(!empty($activity)){
            $params['activity_id'] = $activity['_id'];
            if(config('cache_switch_on')) {
                $product_ids = $this->redis->get('MOBILE_HOME_FLASHDEAL_'.$activity['_id']);
            }
            if(empty($product_ids)){
                $activity_product = $productModel->selectActivityProductids($params);
                if(!empty($activity_product)){
                    $product_count = count($activity_product);
                    $product_ids = CommonLib::getColumn('_id',$activity_product);
                }
                //如果flashDeal产品不够300，用市场价产品来拼
                if($product_count < 300){
                    $activity_product = $productModel->selectActivityProductids(['LowListPrice'=>1,'limit'=> 300 - $product_count]);
                    $listPorduct = CommonLib::getColumn('_id',$activity_product);
                    $product_ids = array_merge($product_ids,$listPorduct);
                }
                $product_ids = array_values(array_unique($product_ids));
                if(!empty($product_ids)){
                    $this->redis->set('MOBILE_HOME_FLASHDEAL_'.$activity['_id'],$product_ids,CACHE_HOUR);
                }
            }

            //翻页数据
            $result['total'] = count($product_ids);//总条数
            $result['per_page'] = $pagesize;
            $result['current_page'] = $page;
            $result['last_page'] = ceil(count($product_ids)/$pagesize);
            //数组分页
            $start=( $page - 1) * $pagesize;//偏移量，当前页-1乘以每页显示条数
            $article = array_slice($product_ids,$start,$pagesize);
            $productData = (new ProductModel())->selectActivityProduct(['product_id'=>CommonLib::supportArray($article)]);
            $result['data'] = !empty($productData['data']) ? $productData['data'] : array();
            //默认值
            $result['soonTime'] = 0;
            $result['remainingTime'] = 0;
            if(!empty($result['data'])){
                $result['data'] = $this->getFlashData($result['data'],$params);
                //当前活动剩余时间
                $result['remainingTime'] = $activity['activity_end_time'] - $time;
                //下一场活动开始时间
                $soon = (new ProductActivityModel())->getActivity(['soon_time' => $time,'type' => '5','status' => 5]);
                $result['soonTime'] = isset($soon['activity_start_time']) ? $soon['activity_start_time'] - $time : '';
            }
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * 活动产品列表
     * @param $params
     * @return array
     */
    public function getActivityProductList($params){
        $result = $activity_product = $select_product = $config_product = array();
        $product_id = isset($params['product_id']) ? (int)$params['product_id'] : '';
        unset($params['product_id']);
        $productModel = new ProductModel();
        $productActivityModel = new ProductActivityModel();
        //获取活动产品ID
        $activity = $productActivityModel->getActivity(['activity_id' => $params['activity_id']]);
        //类别映射
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            $params['firstCategory'] = $this->getMapClassByID($params['firstCategory']);
        }
        $page_size = isset($params['page_size']) ? $params['page_size'] : config('paginate.list_rows');
        //默认按销量排序
        $params['salesCounts'] =isset($params['salesCounts']) ? $params['salesCounts'] : 1;
        if(!empty($activity)){
            //判断状态,根据状态获取产品
            switch($activity['status']){
                case 3://活动进行中
                    //查找是否有人工干预的产品
                    $configSpu = $this->getConfigSpus('FlashDeals');
                    if(!empty($configSpu)){
                        $config_product = $productModel->selectProduct(['product_id' => CommonLib::supportArray($configSpu)]);
                        $config_product = array_column($config_product,null,'_id');
                    }
                    //正常活动产品
                    $activity_product = $productModel->selectActivityProduct($params);
                    //合并人工干预数据
                    if(!empty($config_product) && !empty($activity_product['data']) && $params['page'] == 1 && empty($params['firstCategory'])){
                        foreach($activity_product['data'] as $ak => $val){
                            if(!empty($config_product[$val['_id']])){
                                unset($config_product[$val['_id']]);
                            }
                        }
                        //合并
                        $activity_product['data'] = array_merge($config_product,$activity_product['data']);
                        if(count($activity_product['data']) > $page_size){
                            $activity_product['data'] = array_slice($activity_product['data'],0,$page_size);
                        }
                    }

                    //用户点击的产品，页面默认展示到第一个
                    if(empty($params['firstCategory']) && $params['page'] == 1){
                        if(!empty($product_id)){
                            $select_product = $productModel->selectProduct(['product_id' => (int)$product_id]);
                        }
                    }
                    //合并数据，第一个展示用户选中产品，只有第一页才展示
                    if(!empty($select_product[0]) && !empty($activity_product['data'])){
                        $isFirstPage = false;
                        foreach ($activity_product['data'] as $k => $product) {
                            if($product['_id'] == $product_id){
                                unset($activity_product['data'][$k]);
                                $isFirstPage = true;
                            }
                        }
                        array_unshift($activity_product['data'],$select_product[0]);
                        if(!$isFirstPage){array_pop($activity_product['data']);}
                    }
                    break;
                case 4://活动结束
                case 5://下一场活动
                    //先获取活动数据ID
                    $activityResult = $productActivityModel->getActivityProduct(['activity_id'=>$activity['_id']]);
                    if(!empty($activityResult)){
                        $product_ids = CommonLib::getColumn('SPU',$activityResult);
                        //活动结束和下一场活动的折扣数据，需要重新获取
                        $activity_product = $productModel->selectActivityProduct(['page'=>$params['page'],'product_id' => CommonLib::supportArray($product_ids),'salesCounts'=>1]);
                    }
                    break;
            }
            if(!empty($activity_product['data'])) {
                foreach ($activity_product['data'] as $k => $product) {
                    //产品ID
                    $result[$k]['id'] = $product['_id'];
                    //首图
                    $result[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
                    if (empty($result[$k]['FirstProductImage'])) {
                        $result[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
                    }
                    //链接地址组合
                    $result[$k]['LinkUrl'] = '/p/' . $product['RewrittenUrl'] . '-' . $product['_id'];//链接地址
                    //标题
                    $result[$k]['Title'] = isset($product['Title']) ? $product['Title'] : '';

                    //语言切换 --公共方法
                    if (self::DEFAULT_LANG != $params['lang']) {
                        $productMultiLang = $this->getProductMultiLang($product['_id'], $params['lang']);
                        $result[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) && !empty($productMultiLang['Title'][$params['lang']])
                            ? $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
                    }

                    //国家区域价格
                    if (!empty($params['country'])) {
                        $regionPrice = $this->getProductRegionPrice($product['_id'], $params['country']);
                        //这个产品有国家区域价格
                        if (!empty($regionPrice)) {
                            $this->handleProductRegionPrice($product, $regionPrice);
                        }
                    }

                    //flashDeals产品肯定是折扣产品，展示折扣图标
                    $result[$k]['tagName'] = 'tag-discount';

                    //销售数量
                    $result[$k]['SalesCounts'] = !empty($product['SalesCounts']) ? $product['SalesCounts'] : 0;

                    //原价的价格区间
                    $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
                    $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

                    //判断是否是当前场
                    if ($activity['status'] == 3) {
                        //折扣后的价格区间
                        $discountLowPrice = !empty($product['DiscountLowPrice']) && $product['DiscountLowPrice'] != 'NULL' ? (string)$product['DiscountLowPrice'] : '';//最低价格
                        $discountHightPrice = !empty($product['DiscountHightPrice']) && $product['DiscountHightPrice'] != 'NULL' ? (string)$product['DiscountHightPrice'] : '';//最高价

                        //价格逻辑处理
                        $priceArray = $this->commonProductPrice($originalLowPrice, $originalHightPrice, $discountLowPrice, $discountHightPrice);
                        //商品展示的销售价格
                        $result[$k]['SalesPrice'] = $priceArray['LowPrice'];
                        //原价
                        $result[$k]['OriginalPrice'] = (string)$priceArray['OriginalLowPrice'];
                        //折扣
                        $result[$k]['Discount'] = !empty($product['HightDiscount']) ? (string)$product['HightDiscount'] : '';

                        //人工干预的产品展示，可能不是falshdeal活动数据，需展示市场价
                        if(empty($product['IsActivity'])){
                            //市场折扣逆推市场价功能 add by zhongning 20191107
                            if(!empty($product['ListPriceDiscount'])){
                                $result[$k]['Discount'] = (string)(1 - $product['ListPriceDiscount']);
                                $result[$k]['OriginalPrice'] = (string)round($priceArray['LowPrice'] / (1 - $product['ListPriceDiscount']), 2);
                            }else{
                                $result[$k]['Discount'] = $result[$k]['OriginalPrice'] = 0;
                            }
//                            $result[$k]['OriginalPrice'] = !empty($product['LowListPrice']) && $product['LowListPrice'] > $priceArray['LowPrice'] ?
//                                (string)$product['LowListPrice'] : $priceArray['OriginalLowPrice'];
                            //重新计算折扣
//                            if(!empty($priceArray['LowPrice']) && !empty($result[$k]['OriginalPrice'])){
//                                if($result[$k]['OriginalPrice'] > $priceArray['LowPrice']){
//                                    $result[$k]['Discount'] = (string)(1 - round(($result[$k]['OriginalPrice'] - $priceArray['LowPrice']) / $result[$k]['OriginalPrice'],2));
//                                }
//                            }
                        }

                        //错乱商品，折扣价比原价还大 add by zhongning 20191129
                        if($result[$k]['OriginalPrice'] < $result[$k]['SalesPrice']){
                            $result[$k]['OriginalPrice'] = $result[$k]['Discount'] = '';
                        }
                    } else {
                        $thisProduct = CommonLib::filterArrayByKey($activityResult, 'SPU', $product['_id']);
                        //商品展示的销售价格
                        $result[$k]['SalesPrice'] = !empty($thisProduct['DiscountLowPrice']) ? (string)$thisProduct['DiscountLowPrice'] : 0;//最低价格
                        //原价
                        $result[$k]['OriginalPrice'] = (string)(round($thisProduct['DiscountLowPrice'] / $thisProduct['HightDiscount'],2));//最低价格
                        //折扣
                        $result[$k]['Discount'] = !empty($thisProduct['HightDiscount']) ? (string)$thisProduct['HightDiscount'] : '';
                        //错乱商品，折扣价比原价还大 add by zhongning 20191129
                        if($result[$k]['OriginalPrice'] < $result[$k]['SalesPrice']){
                            $result[$k]['OriginalPrice'] = $result[$k]['Discount'] = '';
                        }
                    }
                }
                $activity_product['data'] = $result;
            }
        }
        return apiReturn(['code'=>200, 'data'=>$activity_product]);
    }

    /**
     * 展示flashdeal的场次信息
     * @return array
     */
    public function getActivityTitle(){
        $activityModel = new ProductActivityModel();
        $result = array();
        //获取最近历史场次,只获取两个
        $activityData = $activityModel->selectActivity(['type'=>'5','status'=>4,'limit'=>2,'orderId'=>1]);
        //调换位置
        if(!empty($activityData) && count($activityData) == 2){
            $temp = $activityData[0];
            $activityData[0] = $activityData[1];
            $activityData[1] = $temp;
        }
        $result = array_merge($result,$activityData);
        //获取当前场次
        $activityData = $activityModel->selectActivity(['current_time'=>time(),'type'=>'5','status'=>3]);
        $result = array_merge($result,$activityData);
        //获取下一场次，查询全部
        $activityData = $activityModel->selectActivity(['soon_time'=>time(),'type'=>'5','status'=>5,'orderStartTime'=>1]);
        $result = array_merge($result,$activityData);
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

}
