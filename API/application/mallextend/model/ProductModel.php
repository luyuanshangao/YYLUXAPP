<?php
namespace app\mallextend\model;

use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\share\model\DxRegion;
use app\admin\model\Activity;
use app\admin\model\Message;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\mongo\Query;

/**
 * 产品模型
 * @author
 * @version  zhi gong 2018/3/22
 */
class ProductModel extends Model{

    /**
     * 产品状态
     */
    const PRODUCT_STATUS_REVIEWING = 0;  //待审核（草稿）
    const PRODUCT_STATUS_SUCCESS = 1;  //已开通（正常销售）
    const PRODUCT_STATUS_PRESALE = 2;  //预售
    const PRODUCT_STATUS_STOP_PRESALE = 3;  //暂时停售
    const PRODUCT_STATUS_DOWN = 4;  //已下架
    const PRODUCT_STATUS_SUCCESS_UPDATE = 5;  //正常销售，编辑状态
    const PRODUCT_STATUS_DELETE = 10;  //已删除
    const PRODUCT_STATUS_REJECT = 12;    //审核失败



    /**
     * 产品分类
     */
    const PRODUCT_CATEGORY_FIRST = 1;  //一级
    const PRODUCT_CATEGORY_SECOND = 2;    //二级
    const PRODUCT_CATEGORY_THIRD = 3;  //三级
    const PRODUCT_CATEGORY_FOURTH = 4;  //四级
    const PRODUCT_CATEGORY_FIFTH = 5;  //五级

    /**
     * 同步历史数据运费模板标识
     * 要和seller设置的保持一致
     */
    const ISHISTORYISSYNCSTANDIMGSFLAG = 8;

    //分类
    public static $categoryArr = [
        self::PRODUCT_CATEGORY_FIRST =>'FirstCategory',
        self::PRODUCT_CATEGORY_SECOND =>'SecondCategory',
        self::PRODUCT_CATEGORY_THIRD =>'ThirdCategory',
        self::PRODUCT_CATEGORY_FOURTH =>'FourthCategory',
        self::PRODUCT_CATEGORY_FIFTH =>'FifthCategory'
    ];

    //状态
    public static $statusArr = [
        self::PRODUCT_STATUS_REVIEWING => '待审核',
        self::PRODUCT_STATUS_REJECT => '审核不通过',
        self::PRODUCT_STATUS_SUCCESS => '正常销售',
        self::PRODUCT_STATUS_DOWN => '已下架',
        self::PRODUCT_STATUS_DELETE => '已删除'
    ];

    //自营店铺
    public static $selfStore = [
        666 => 'YB05200',
        888 => 'SKT0001',
        999 => 'SKT0001',
    ];

    public $product_record;
    public $region;
    public $redis;
    protected $db;
    protected $product = 'product';
    protected $shipping ='shipping_cost';
    protected $product_lang = 'product_multiLangs';
    protected $attr_lang ='product_attr_multiLangs';
    protected $custom_attr_lang ='product_customAttr_multiLangs';
    protected $historyProudct = 'product_histories';
    protected $get_product_lists_params;
    protected $product_regions_price = 'product_regions_price';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
        $this->product_record = new ProductRecordModel();
        $this->region = new DxRegion();
        $this->redis = new RedisClusterBase();
    }

    /**
     * 新增产品类
     * 需要同时插入到dx_product,dx_shipping_cost
     * @param arrar $productData
     * @return true/false
     */
    public function addProduct($productData){
        //价格数组，查询最大值，最小值
        $price = array();
        $flag = isset($productData['from_flag']) ? 1 : 2;

        //搜索SKU增值表
        $incrment = $this->getAutoIncrement();
        if(empty($incrment)){
            return false;
        }

        //缓存自增product_id product_sku_id
        if($this->redis->has('product_id_incr')){
            if($incrment['SKU'] != $this->redis->get('product_id_incr')){
                //数据库为基准
                $this->redis->set('product_id_incr',$incrment['SKU']);
            }
            if($incrment['SubSKU'] != $this->redis->get('product_sku_id_incr')){
                //数据库为基准
                $this->redis->set('product_sku_id_incr',$incrment['SubSKU']);
            }
        }else{
            $this->redis->set('product_id_incr',$incrment['SKU']);
            $this->redis->set('product_sku_id_incr',$incrment['SubSKU']);
        }

        $this->redis->incr('product_id_incr');
        $product_id = $this->redis->get('product_id_incr');

        //插入产品表数据
        $productData['_id'] = (int)$product_id ;

        $listPriceDiscount = CommonLib::getListPriceDiscount();

        //SKU循环给ID
        foreach($productData['Skus'] as $key => $sku){
            $this->redis->incr('product_sku_id_incr');
            $product_sku_id = $this->redis->get('product_sku_id_incr');
            $productData['Skus'][$key]['_id'] = (int)$product_sku_id;
            $productData['Skus'][$key]['Inventory'] = (int)$sku['Inventory'];
            $productData['Skus'][$key]['SalesPrice'] = (double)$sku['SalesPrice'];
            if(isset($sku['BulkRateSet']['Discount']) && !empty($sku['BulkRateSet']['Discount'])){
                if(isset($productData['SourceCode']) && $productData['SourceCode'] == 'ERP'){
                    $productData['Skus'][$key]['BulkRateSet']['Discount'] = $sku['BulkRateSet']['Discount'];
                }else{
                    $productData['Skus'][$key]['BulkRateSet']['Discount'] = $sku['BulkRateSet']['Discount'] / 100;
                }
                $bulkSalePrice = $sku['SalesPrice'] - $productData['Skus'][$key]['BulkRateSet']['Discount'] * $sku['SalesPrice'];
                $productData['Skus'][$key]['BulkRateSet']['SalesPrice']= (double)round($bulkSalePrice,2);
            }
            //市场价
            if($listPriceDiscount != 0){
                $productData['Skus'][$key]['ListPrice'] = (double)round($sku['SalesPrice'] / (1 - $listPriceDiscount), 2);
            }
            $price[$key] = (double)$sku['SalesPrice'];
        }

        //价格区间
        $productData['LowPrice'] = min($price);
        $productData['HightPrice'] = max($price);

        //市场价格区间
        $listPriceArray = CommonLib::countListPrice($productData['LowPrice'],$productData['HightPrice'],$listPriceDiscount);
        $productData['LowListPrice'] = (double)$listPriceArray['LowListPrice'];
        $productData['HighListPrice'] = (double)$listPriceArray['HighListPrice'];
        $productData['ListPriceDiscount'] = (double)$listPriceArray['ListPriceDiscount'];

        //维护sku表
        $newSku = CommonLib::getColumn('_id',$productData['Skus']);
        $oldSku = $this->db->name('product_skus')->where(['_id'=>(int)$product_id])->field(['_id'])->find();
        if(empty($oldSku)){
            //新增sku表
            $this->db->name('product_skus')->insert(['_id' => (int)$product_id,'Skus' => $newSku,'AddTime'=>date('Y-m-d H:i:s',time())]);
        }

        //如果redis自增KEY 比原来的还小，肯定有问题，不更新
        if((int)$this->redis->get('product_id_incr') < (int)$incrment['SKU']){
            return false;
        }
        //更新SKU增值表
        $updateResult = $this->updateAutoIncrement(
            ['SKU'=>(int)$this->redis->get('product_id_incr'), 'SubSKU' => (int)$this->redis->get('product_sku_id_incr')],
            ['SKU'=>(int)$incrment['SKU'],'SubSKU' => (int)$incrment['SubSKU']]);
        if(!$updateResult){
            return false;
        }
        $ret = $this->db->name('product')->insert($productData);
        if(!$ret){
            return false;
        }

        //erp上传产品，重新计算产品售价，免运费 add zhongning 20190409
        if(isset($productData['SourceCode']) && $productData['SourceCode'] == 'ERP'){
            //task处理队列
            $this->redis->lPush('ErpUploadProductQueue',$product_id);
        }

        if($flag != 1) {
            /* 异步处理产品运费模板数据 start */
            //将产品ID，产品带电属性，所选运费模板ID，写入队列
            $this->redis->lPush(
                'addProductShippingTemplateList',
                json_encode(
                    [
                        'product_id' => $product_id,
                        'product_is_charged' => $productData['LogisticsLimit'][0],
                        'template_id' => $productData['LogisticsTemplateId'],
                        'from_flag' => 1 //来源标识：1-新增产品，2-修改产品信息
                    ]
                )
            );
            /* 异步处理产品运费模板数据 end */

            //属性图片
            if(isset($productData['ImageSet']['AttributeImg']) && is_array($productData['ImageSet']['AttributeImg'])){
                if(!empty($productData['ImageSet']['AttributeImg'])){
                    $productData['ImageSet']['ProductImg'] = array_merge($productData['ImageSet']['ProductImg'],$productData['ImageSet']['AttributeImg']);
                }
            }

            //队列：上传产品-产品图片
            $this->redis->lPush(
                'addProductMainImagesList',
                json_encode(
                    [
                        'product_id' => $product_id,
                        'imgs' => $productData['ImageSet']['ProductImg'],
                        'from_flag' => 2
                    ]
                )
            );
        }

        //产品变更队列
        if(isset($productData['Descriptions'])){
            unset($productData['Descriptions']);
        }
        $productData['IsSync'] = false;
        $productData['Note'] = '新增产品';
        CommonLib::productHistories($productData['_id'].'-add',$productData);

        //推荐数据队列
        $this->redis->lPush('ProductRecommend',$product_id);
        return $product_id;
    }

    /**
     * 产品更新
     */
    public function updateProduct($params){
        //价格数组，查询最大值，最小值
        $price = array();
        $product_id = $params['id'];
        unset($params['id'],$params['lang']);

        //是否存在修改SKUS
        if (isset($params['Skus']) && !empty($params['Skus'])) {

            //搜索SKU增值表
            $incrment = $this->getAutoIncrement();
            $product_sku_id = $incrment['SubSKU'];
            //缓存自增 product_sku_id
            if($this->redis->has('product_sku_id_incr')){
                if($incrment['SubSKU'] != $this->redis->get('product_sku_id_incr')){
                    //数据库为基准
                    $this->redis->set('product_sku_id_incr',$incrment['SubSKU']);
                }
            }else{
                $this->redis->set('product_sku_id_incr',$incrment['SubSKU']);
            }

            $newSku = array();
            //按规则组装数据
            foreach ($params['Skus'] as $pkey => $productSkus) {
                $sku_id = isset($productSkus['_id']) && !empty($productSkus['_id']) ? $productSkus['_id'] : $pkey;
                $newSku[] = $sku_id;
            }

            //sku表
            $oldSkuAttr = $this->db->name('product_skus')->where(['_id'=>(int)$product_id])->find();
            //没有同步到sku表格
            if(empty($oldSkuAttr)){
                $thisProduct = $this->db->name('product')->where(['_id' => (int)$product_id])->field(['Skus._id'])->find();
                $oldSkuAttr['Skus'] = CommonLib::getColumn('_id',$thisProduct);
                //新增sku表格
                $this->db->name('product_skus')->insert(['_id'=>(int)$product_id,'Skus' => $oldSkuAttr['Skus'],'AddTime'=>date('Y-m-d H:i:s',time())]);
            }
            $oldSku = $oldSkuAttr['Skus'];

            //有差异的sku,可以用在修改中没有sku_id中
            $diffSku = array_diff($oldSku,$newSku);

//            $listPriceDiscount = CommonLib::getListPriceDiscount();
            $total_inventory = 0;
            //判断SKU是否有新增
            foreach ($params['Skus'] as $sk => $sku) {

                $sku_id = isset($sku['_id']) && !empty($sku['_id']) ? $sku['_id'] : null;
                if(!empty($diffSku)){
                    $sku_id = array_shift($diffSku);
                }

                //原sku
                $params['Skus'][$sk]['_id'] = (int)$sku_id;
                $params['Skus'][$sk]['Inventory'] = isset($sku['Inventory']) ? (int)$sku['Inventory'] : 0;
                $params['Skus'][$sk]['SalesPrice'] = isset($sku['SalesPrice']) ? (double)$sku['SalesPrice'] : 0;

                //新增的sku
                if (empty($sku_id)){
                    $this->redis->incr('product_sku_id_incr');
                    $product_sku_id = $this->redis->get('product_sku_id_incr');
                    $params['Skus'][$sk]['_id'] = (int)$product_sku_id;
                }

                //处理批发价格
                if(isset($sku['BulkRateSet']['Discount']) && !empty($sku['BulkRateSet']['Discount'])){
                    if(isset($params['SourceCode']) && $params['SourceCode'] == 'ERP'){
                        $params['Skus'][$sk]['BulkRateSet']['Discount'] = $sku['BulkRateSet']['Discount'];
                    }else{
                        $params['Skus'][$sk]['BulkRateSet']['Discount'] = $sku['BulkRateSet']['Discount'] / 100;
                    }
                    $bulkSalePrice = $sku['SalesPrice'] - $params['Skus'][$sk]['BulkRateSet']['Discount'] * $sku['SalesPrice'];
                    $params['Skus'][$sk]['BulkRateSet']['SalesPrice']= (double)round($bulkSalePrice,2);
                }
                //市场价
//                if($listPriceDiscount != 0){
//                    $params['Skus'][$sk]['ListPrice'] = (double)round($sku['SalesPrice'] / (1 - $listPriceDiscount), 2);
//                }
                //获取区间价
                $price[$sk] = (double)$sku['SalesPrice'];
                //总库存
                $total_inventory += $params['Skus'][$sk]['Inventory'];
            }
            if($total_inventory == 0){
                //状态改为停售
                $params['ProductStatus'] = self::PRODUCT_STATUS_STOP_PRESALE;
            }
            //区间价
            $params['LowPrice'] = min($price);
            $params['HightPrice'] = max($price);

            //重新计算市场价
//            $listPriceArray = CommonLib::countListPrice($params['LowPrice'],$params['HightPrice'],$listPriceDiscount);
//            $params['LowListPrice'] = (double)$listPriceArray['LowListPrice'];
//            $params['HighListPrice'] = (double)$listPriceArray['HighListPrice'];
//            $params['ListPriceDiscount'] = (double)$listPriceArray['ListPriceDiscount'];
        }

        //原生写法
        $mongo = new Mongo('dx_product');
        $ret = $mongo->update(['_id' =>(int)$product_id], ['$set'=>$params]);
        if($ret){
            if(isset($params['Descriptions'])){
                unset($params['Descriptions']);
            }
            $params['IsSync'] = false;
            $params['Note'] = 'updateProduct修改产品';
            CommonLib::productHistories($product_id.'-updateProduct',$params);
            //更新SKU增值表
            if (isset($params['Skus'])) {
                if ($product_sku_id != $incrment['SubSKU']) {
                    $this->updateAutoIncrement(['SubSKU' => (int)$product_sku_id], ['SKU' => (int)$incrment['SKU'], 'SubSKU' => (int)$incrment['SubSKU']]);
                }
                //维护sku表
                $newSku = CommonLib::getColumn('_id',$params['Skus']);
                sort($newSku);
                //有新的SKU产生
                if(count($newSku) > count($oldSku)){
                    $this->db->name('product_skus')->where(['_id'=>(int)$product_id])->update(['Skus' => $newSku,'UpdateTime'=>date('Y-m-d H:i:s',time())]);
                }
            }
        }
        return apiReturn(['code'=>200]);
    }

    /**
     * 根据StoreID更新佣金数据
     * @param $store_id
     * @param $up_data
     * @return bool
     */
    public function updateProductCommission($store_id, $up_data){
        //查询更新产品是否存在
        $proudct = $this->db->name('product')->where(['StoreID' =>(int)$store_id])->find();
        if(empty($proudct)){
            return false;
        }
        $up_data['CommissionType'] = (int)$up_data['CommissionType'];
        $up_data['Commission'] = (float)$up_data['Commission'];
        $ret = $this->db->name('product')->where(['StoreID' =>(int)$store_id])->update($up_data);
        if(!$ret){
            return false;
        }
        return true;
    }

    /**
     * 根据一级分类ID更新佣金数据
     * @param $store_id
     * @param $first_category_id
     * @param $up_data
     * @return bool
     */
    public function updateProductCommissionByFirstCategory($store_id, $first_category_id, $up_data, $second_gategorys=''){
        //查询更新产品是否存在$
        $proudct = $this->db->name('product')->where(['StoreID' =>(int)$store_id])->find();
        if(empty($proudct)){
            return false;
        }
        $up_data['CommissionType'] = (int)$up_data['CommissionType'];
        $up_data['Commission'] = (float)$up_data['Commission'];
        $where['StoreID'] = (int)$store_id;
        $where['FirstCategory'] = (int)$first_category_id;
        if(!empty($second_gategorys)){
            foreach ($second_gategorys as $key=>$value){
                $second_gategorys[$key] = (int)$value;
            }
            $where['SecondCategory'] = ["not in",$second_gategorys];
        }
        $ret = $this->db->name('product')->where($where)->update($up_data);
        if(!$ret){
            return false;
        }
        return true;
    }

    /**
     * 根据二级分类ID更新佣金数据
     * @param $store_id
     * @param $first_category_id
     * @param $up_data
     * @return bool
     */
    public function updateProductCommissionBySecondCategory($store_id, $first_category_id, $second_gategory_id, $up_data){
        //查询更新产品是否存在$
        $proudct = $this->db->name('product')->where(['StoreID' =>(int)$store_id])->find();
        if(empty($proudct)){
            return false;
        }
        $up_data['CommissionType'] = (int)$up_data['CommissionType'];
        $up_data['Commission'] = (float)$up_data['Commission'];
        $where['StoreID'] = (int)$store_id;
        $where['FirstCategory'] = (int)$first_category_id;
        $where['SecondCategory'] = (int)$second_gategory_id;
        $ret = $this->db->name('product')->where($where)->update($up_data);
        if(!$ret){
            return false;
        }
        return true;
    }

    /**
     * 获取auto_increment，主要运用在SKU键值的维护
     */
    public function getAutoIncrement(){
        return $this->db->name('auto_increment')->find();
    }

    /**
     * 更新auto_increment，主要运用在SKU键值的维护
     */
    private function updateAutoIncrement($data,$where){

        return $this->db->name('auto_increment')->where($where)->update($data);
    }


    /**
     * 获取产品信息
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getProduct($params){
        $where = array();
        $query = $this->db->name('product');
        if(isset($params['product_id']) && $params['product_id']){
            if(is_array($params['product_id'])){
                foreach ($params['product_id'] as $key=>&$value){
                    $value = (int)$value;
                }
                $where['_id'] = ["IN",$params['product_id']];
            }else{
                $params['product_id'] = (int)$params['product_id'];
                $where['_id'] = $params['product_id'];
            }

        }
        if(isset($params['store_id']) && !empty($params['store_id'])){
            $where['StoreID'] = (int)$params['store_id'];
        }
        if(isset($params['sku_id']) && $params['sku_id']){
            $where['Skus._id'] = (int)$params['sku_id'];
        }
        if(isset($params['sku_code']) && $params['sku_code']){
            $where['Skus.Code'] = (string)$params['sku_code'];
        }
        if(isset($params['sku_search']) && !empty($params['sku_search'])){
            $query->whereOr(['Skus.Code'=>(string)$params['sku_search']])->whereOr(['Skus._id'=>(int)$params['sku_search']]);
        }
        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $query->whereOr(['FirstCategory'=>$params['lastCategory']])->whereOr(['SecondCategory'=>$params['lastCategory']])
                ->whereOr(['ThirdCategory'=>$params['lastCategory']])->whereOr(['FourthCategory'=>$params['lastCategory']]);
        }
        if(isset($params['status']) && $params['status']){
            if(is_array($params['status'])){
                $where['ProductStatus'] = CommonLib::supportArray($params['status']);
            }else{
                $where['ProductStatus'] = (int)$params['status'];
            }
        }
        if(empty($where)){
            return array();
        }
        if(isset($params['field'])){
            $query->field($params['field']);
        }
        /*如果传入多个产品ID，则返回多个产品数据*/
        //判断是否存在，不然致命错误，add zhognning 20190329
        if(isset($params['product_id']) && is_array($params['product_id'])){
            return $query->where($where)->select();
        }else{
            return $query->where($where)->find();
        }
    }


    /**
     * 根据SKU获取成本价
     * @param $params
     * @return mixed
     * add by 20190711 kevin
     */
    public function getProductPurchasePrice($params){
        $where = array();
        $query = $this->db->name('product_purchase_price');
        if(isset($params['skus']) && $params['skus']){
            if(is_array($params['skus'])){
                foreach ($params['skus'] as $key=>&$value){
                    $value = (int)$value;
                }
                $where['SKU'] = ["IN",$params['skus']];
            }else{
                $params['SKU'] = (int)$params['skus'];
                $where['_id'] = $params['SKU'];
            }
        }
        if(empty($where)){
            return array();
        }
        if(isset($params['skus']) && is_array($params['skus'])){
            return $query->where($where)->select();
        }else{
            return $query->where($where)->find();
        }
    }

    /**
     * code唯一
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getCode($params){
        $query = $this->db->name('product');
        if(isset($params['product_id']) && $params['product_id']){
            $query->where(['_id' => ['<>',(int)$params['product_id']]]);
        }
        if(isset($params['seller_id']) && !empty($params['seller_id'])){
            $query->where(['StoreID' => $params['seller_id']]);
        }
        if(isset($params['Code']) && !empty($params['Code'])){
            if(is_array($params['Code'])){
                $query->where(['Skus.Code' => ['in',$params['Code']]]);
            }else{
                $query->where(['Skus.Code' => $params['Code']]);
            }
        }
        $query->where(['ProductStatus' => ['<>',10]]);
        $productArray = $query->value('_id');
        return $productArray;
    }
    /**
     * 根据类别ID，获取产品信息
     */
    public function getProductBrand($classId, $status=1){
        $result =  $this->db->name('brand_attribute')
            ->where(array("_id"=>(int)$classId))
            ->where(array("status"=>(int)$status))
            ->field(array('product_brand'=>true,'addtime'=>true))->find();
        return $result;
    }

    /**
     * 根据类别ID，获取属性
     * @param $classId 分类ID
     * @param int $status 状态：1-正常数据（非删除的数据）
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductAttribute($classId, $status=1){
        $result =  $this->db->name('brand_attribute')
            ->where(array("_id"=>(int)$classId))
            ->where(array("status"=>(int)$status))
            ->field(array('attribute'=>true,'addtime'=>true))->find();
        /** 属性排序 **/
        if (!empty($result) && isset($result['attribute'])){
            array_multisort(array_column($result['attribute'], 'sort'), SORT_ASC, $result['attribute']);
            foreach ($result['attribute'] as $k=>&$v){
                array_multisort(array_column($v['attribute_value'], 'sort'), SORT_ASC, $v['attribute_value']);
            }
        }
        return $result;
    }

    /**
     * 产品列表
     */
    public function productLists($params){
        $default_page_size = config('paginate.page_size');
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : $default_page_size;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        $where = array();
        $sku_Code = array();
        $query = $this->db->name('product');

        if(!empty($params['Code'])){
            // foreach ($params['Code'] as $kCode => $vCode) {
            //    $sku_Code[] = (int)$vCode;
            // }
            $sku_Code = ['in',$params['Code']];
        }

        $whereParams = [
            'FirstCategory'=> isset($params['first_level'])&& !empty($params['first_level']) ? (int)$params['first_level'] : null,
            'SecondCategory'=> isset($params['second_level']) && !empty($params['second_level']) ? (int)$params['second_level'] : null,
            'ThirdCategory'=> isset($params['third_level']) && !empty($params['third_level']) ? (int)$params['third_level'] : null,
            'FourthCategory'=> isset($params['fourth_level']) && !empty($params['fourth_level']) ? (int)$params['fourth_level'] : null,
            '_id'=> isset($params['id']) && !empty($params['id']) ? CommonLib::supportArray($params['id']) : null,
            // '_id'=> isset($params['id']) && !empty($params['id']) ? (int)$params['id'] : null,
            'BrandId'=> isset($params['BrandId']) && !empty($params['BrandId']) ? (int)$params['BrandId'] : null,
            'Title'=> isset($params['Title']) && !empty($params['Title']) ? ['like',$params['Title']] : null,
            'BrandName'=> isset($params['BrandName']) && !empty($params['BrandName']) ? ['like',$params['BrandName']] : null,
            'StoreName'=> isset($params['UserName']) && !empty($params['UserName']) ? ['like',$params['UserName']] : null,
            'StoreID'=> isset($params['UserId']) && !empty($params['UserId']) ? (int)$params['UserId'] : null,
            'ShippingFee'=> isset($params['ShippingFee']) && !empty($params['ShippingFee']) ? (int)$params['ShippingFee'] : null,
            'AvgRating'=> isset($params['AvgRating']) && !empty($params['AvgRating']) ? (int)$params['AvgRating'] : null,
            'Skus.Code'=> isset($sku_Code) && !empty($sku_Code) ? $sku_Code : null,
        ];

        // if(!empty($params["startTime"]) && !empty($params["endTime"])){
        //       $startTime = strtotime($params["startTime"]);
        //       $endTime   = strtotime($params["endTime"]);
        //       $addtime['AddTime'] = array(array('egt',$startTime),array('elt',$endTime));
        //       $whereParams ['AddTime'] = $addtime['AddTime'];
        // }
        // return $whereParams;
        //过滤空值
        CommonLib::filterNullValue($whereParams);
        $where = $whereParams;

        //去除已经参加活动的产品数据（seller获取添加活动产品）【活动报名产品特殊处理】
        if(isset($params['activityFlag']) && $params['activityFlag'] && $params['activityFlag'] == 1){
            unset($where['StoreID']);
            unset($whereParams['StoreID']);
            //$where['activityFlag'] =  1;
            $this->get_product_lists_params = $params;
            //$query->where(['ProductStatus' => CommonLib::supportArray([self::PRODUCT_STATUS_SUCCESS, self::PRODUCT_STATUS_SUCCESS_UPDATE])]);
            //$query->where('IsActivityEnroll', '=', 0);

            //获取没有报名活动的情况（IsActivityEnroll不存在或为空）
            $query->whereOr(function ($q){
                $q->where(
                    [
                        'StoreID'=>(int)$this->get_product_lists_params['UserId'],
                        'ProductStatus' => CommonLib::supportArray([self::PRODUCT_STATUS_SUCCESS, self::PRODUCT_STATUS_SUCCESS_UPDATE]),
                    ])
                    ->whereOr(['IsActivityEnroll'=>null]);
            });
            //获取没有报名活动的情况（IsActivityEnroll字段存在且为0）
            $query->whereOr(function ($q){
                $q->where(
                    [
                        'StoreID'=>(int)$this->get_product_lists_params['UserId'],
                        'ProductStatus' => CommonLib::supportArray([self::PRODUCT_STATUS_SUCCESS, self::PRODUCT_STATUS_SUCCESS_UPDATE]),
                        'IsActivityEnroll'=>0
                    ]);
            });
            //获取已经报名活动，但活动时间不冲突的情况1
            $query->whereOr(function ($q){
                $q->where(
                    [
                        'StoreID'=>(int)$this->get_product_lists_params['UserId'],
                        'ProductStatus' => CommonLib::supportArray([self::PRODUCT_STATUS_SUCCESS, self::PRODUCT_STATUS_SUCCESS_UPDATE]),
                        'IsActivityEnroll'=>1,
                        'IsActivityEnrollStartTime'=>['>=', (int)$this->get_product_lists_params['activityEndTime']],

                    ]);
            });
            //获取已经报名活动，但活动时间不冲突的情况2
            $query->whereOr(function ($q){
                $q->where(
                    [
                        'StoreID'=>(int)$this->get_product_lists_params['UserId'],
                        'ProductStatus' => CommonLib::supportArray([self::PRODUCT_STATUS_SUCCESS, self::PRODUCT_STATUS_SUCCESS_UPDATE]),
                        'IsActivityEnroll'=>1,
                        'IsActivityEnrollEndTime'=>['<=', (int)$this->get_product_lists_params['activityStartTime']],

                    ]);
            });
        }else{

            if(isset($params['ProductStatus'])){
                $where['ProductStatus'] =  $params['ProductStatus'];
                if(is_array($params['ProductStatus'])){
                    $query->where(['ProductStatus' => CommonLib::supportArray($params['ProductStatus'])]);
                }else{
                    $query->where(['ProductStatus' => (int)$params['ProductStatus']]);
                }
            }
        }

        //审核产品不通过类型
        if(isset($params['RejectType']) && !empty($params['RejectType'])){
            $query->where(['RejectType' => (int)$params['RejectType']]);
        }


        //有货或者无货
        if(isset($params['Inventory']) && $params['Inventory']){
            $where['Inventory'] =  $params['Inventory'];
            if(1 == $params['Inventory']){
                $query->where('Availability.Inventory','>',(int)0);
            }else{
                $query->where(['Availability.Inventory' => (int)0]);
            }
        }
        //分组ID
        if(isset($params['GroupId']) && $params['GroupId']){
            $where['GroupId'] =  $params['GroupId'];
            if(is_array($params['GroupId'])){
                foreach($params['GroupId'] as $id){
                    $ids[] = (int)$id;
                }
                $query->where('GroupId','in',$ids);
            }else{
                $query->where(['GroupId' => (int)$params['GroupId']]);
            }
        }

        //价格起始值大于结束值
        if(isset($params['lowPrice']) && $params['heightPrice']){
            if($params['lowPrice'] > $params['heightPrice']){
                $tmp = $params['lowPrice'];
                $params['lowPrice'] = $params['heightPrice'];
                $params['heightPrice'] = $tmp;
            }
        }
        //价格筛选
        if(isset($params['lowPrice']) && $params['lowPrice']){
            $query->where(['LowPrice'=>['gte',(double)$params['lowPrice']]]);
        }
        if(isset($params['heightPrice']) && $params['heightPrice']){
            $query->where(['LowPrice'=>[ 'between' , [(double)$params['lowPrice'],(double)$params['heightPrice']]]]);
        }

        //是否是affiliate商品
        if(isset($params['IsAffiliate']) && $params['IsAffiliate'] == 1){
            $where['Commission'] =  [ 'gt' , (double)0 ];
            $query->where(['Commission'=>[ 'gt' , (double)0 ]]);
        }
        if(isset($params['startTime']) && !empty($params['startTime']) && isset($params['endTime']) && !empty($params['endTime'])){
            $where['startTime'] = $params['startTime'];
            $where['endTime']   = $params['endTime'];
            $params['startTime'] = strtotime($params["startTime"]);
            $params['endTime'] = strtotime($params["endTime"]);
            $map['AddTime'] = array('between',[(int)$params['startTime'],(int)$params['endTime']]);
            $query->where($map);
            //$map['AddTime'] = array(array('egt',(int)$params['startTime']),array('elt',(int)$params['endTime']));//return $map['AddTime'];
            // $query->where(['AddTime'=>[ 'lt' , (int)$params['endTime'] ]]);
        }else if(isset($params['startTime']) && !empty($params['startTime'])){
            $where['startTime'] = $params['startTime'];
            $map['AddTime'] = array('>=',strtotime($params["startTime"]));
            $query->where($map);
        }else if(isset($params['endTime']) && !empty($params['endTime'])){
            $where['endTime']   = $params['endTime'];
            $map['AddTime'] = array('<=',(int)strtotime($params["endTime"]));
            $query->where($map);
        }


        // if(isset($params['endTime']) && !empty($params['endTime'])){
        //     $params['endTime'] = strtotime($params["endTime"]);
        //     $query->where(['AddTime'=>[ '<=' , (int)$params['endTime'] ]]);
        // }
// pr($query->getLastSql());
        //ExpiryTime过期时间搜索条件：3-3天内到期，7-7天内到期，30-30天内到期
        if (isset($params['ExpiryTime']) && !empty($params['ExpiryTime'])){
            //$query->where('ExpiryDate','elt', (int)strtotime('+'.$params['ExpiryTime'].' days'));
//            Log::record('$params-ExpiryTime：'.$params['ExpiryTime']);
            $query->where(['ExpiryDate'=>[ '<=' , (int)$params['ExpiryTime'] ]]);
        }

        $query->where($whereParams);

        //Commission排序
        if(isset($params['CommissionOrder'])){
            $query->order('Commission','desc');
        }

        //Commission排序
        if(isset($params['SalesCountsOrder'])){
            $query->order('SalesCounts','desc');
        }
        //如果是按照销售价格排序，必须先排序IsDefault
        if(isset($params['SalesPriceOrder']) && $params['SalesPriceOrder']){
            $where['SalesPriceOrder'] =  $params['SalesPriceOrder'];
            $order = $params['SalesPriceOrder'] ? 'asc' : 'desc';
            $query->order('Skus.IsDefault','desc')->order('Skus.SalesPrice',$order);
        }

        //按更新时间排序
        if(isset($params['sort_time']) && $params['sort_time']){
            if ($params['sort_time'] == 1){
                $query->order('EditorTime', 'desc');
            }else{
                $query->order('EditorTime', 'asc');
            }
        }

        //SalesRank
        if(isset($params['SalesRankOrder']) && $params['SalesRankOrder']){
            $where['SalesRankOrder'] =  $params['SalesRankOrder'];
            $order = $params['SalesRankOrder'] ? 'asc' : 'desc';
            $query->order('SalesRank',$order);
        }
        //默认上架时间排序
        $query->order('AddTime','desc');

        //查询字段
        $query->field(["IsHistory", "BrandId","BrandName","StoreID","StoreName",'GroupId','GroupName','ProductType','CategoryPath','FirstCategory','SecondCategory'
            ,'ThirdCategory','FourthCategory','ImageSet','ProductStatus','Days','Title',
            'VideoCode','GTINs','LogisticsLimit','SalesUnitType','LogisticsTemplateId','LogisticsTemplateName','SalesMode','AllowBulkRate','FirstProductImage','FilterOptions','Supplier','DeclarationName',
            'AddTime','Skus._id','Skus.Code','Skus.SalesPrice','Skus.Inventory','LowPrice','HightPrice','SalesRank','Commission','EditorTime','RejectReason','ExpiryDate'
        ]);
        //分页信息
        if(!empty($where['_id'])){
            $where['spu'] = $where['_id'][1];
            if(is_array($where['_id'][1])){
                $where['spu'] = implode(",", $where['_id'][1]);
            }
            unset($where['_id']);
        }
        if(!empty($where['Skus.Code'])){
            $where['Code'] = $where['Skus.Code'][1];
            if(is_array($where['Skus.Code'][1])){
                $where['Code'] = implode(",", $where['Skus.Code'][1]);
            }
            unset($where['Skus.Code']);
        }
        $where_query = isset($params['query'])?$params['query']:$where;//return $where_query;
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where_query]);//return $ret;
        $Page = $ret->render();
        $data = $ret->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 修改产品分组
     * @param $params
     * @return bool
     */
    public function updateProductField($params){
        $product_id = $params['id'];
        $ids = [];
        if(is_array($product_id)){
            foreach($product_id as $id){
                //清除缓存
                CommonLib::rmProductCache($id);
                $ids[] = (int)$id;
            }
            $where = ['_id' => ['in',$ids]];
        }else{
            //清除缓存
            CommonLib::rmProductCache($product_id);
            $where = ['_id' => (int)$params['id']];
        }

        //查询更新产品是否存在
        $proudct = $this->db->name('product')->where($where)->select();
        if(empty($proudct)){
            return false;
        }

        //修改产品分组
        if(isset($params['GroupId'])){
            //查询更新产品分组是否存在
            $group_name = $this->db->name('product_group')->where(['_id' =>(int)$params['GroupId']])->value("group_name");
            if(empty($group_name)){
                return false;
            }
            $ret = $this->db->name('product')->where($where)->update(['GroupId' =>(int)$params['GroupId'],'GroupName'=>$group_name]);
            if(!$ret){
                return false;
            }
        }

        //延长有效期
        if(isset($params['days'])){
            $proudct['ExpiryDate'] = time() + (86400 * $params['days']);
            //判断有效期是否在三天内
            $where['ExpiryDate'] = ['<',strtotime('+3 days')];
            $ret = $this->db->name('product')->where($where)->update(['ExpiryDate' =>(int)$proudct['ExpiryDate']]);
            if(!$ret){
                return false;
            }
        }

        //修改产品状态
        if(isset($params['status'])){
            $ret = $this->db->name('product')->where($where)->update(['ProductStatus' =>(int)$params['status']]);
            if(!$ret){
                return true;
            }
            //如果是“上架”，则需要将状态修改为上架，且产品有效期自动在当前时间基础上+有效期（产品字段上的Days字段）
            if ($params['status'] == self::PRODUCT_STATUS_SUCCESS){
                foreach ($proudct as $product_info){
                    $product_info_expiryDate = time() + (86400 * $product_info['Days']);
                    $ret = $this->db->name('product')->where(['_id'=>(int)$product_info['_id']])->update(['ExpiryDate' =>(int)$product_info_expiryDate]);
                    if(!$ret){
                        return false;
                    }
                }
            }
            //如果是下架，并且是活动的情况下，需要清空相关的活动数据
            if ($params['status'] == self::PRODUCT_STATUS_DOWN){
                foreach ($proudct as $product_info){
                    if (isset($product_info['IsActivity']) && $product_info['IsActivity'] > 0){
                        $this->updateActivityStatus(['product_id_arr'=>[$product_info['_id']]]);
                    }
                }
            }
        }
        //记录变更历史
        if(is_array($product_id)){
            foreach($product_id as $id){
                $params['IsSync'] = true;
                $params['Note'] = 'updateProductField-修改产品状态-延长有效期-产品分组';
                CommonLib::productHistories($id.'-updateProductField',$params,'true');
            }
        }else{
            $params['IsSync'] = true;
            $params['Note'] = 'updateProductField-修改产品状态-延长有效期-产品分组';
            CommonLib::productHistories($product_id.'-updateProductField',$params,'true');
        }
        return true;
    }

    /**
     * 卖家ID统计商品数量
     */
    public function countProdcut($paramData){
        $data = [];
        $query = $this->db->name('product');
        $where['StoreID'] = (int)$paramData['seller_id'];



        if(isset($paramData['status'])){
            //tp mongodb不支持group by
            if(is_array($paramData['status'])){
                foreach( $paramData['status'] as $status){
                    $where['ProductStatus'] = (int)$status;
                    if($status == self::PRODUCT_STATUS_REJECT && isset($paramData['reject_type']) && !empty($paramData['reject_type'])){
                        if(is_array($paramData['reject_type'])){
                            foreach( $paramData['reject_type'] as $type){
                                $query->where(['RejectType' => (int)$type]);
                                $data[$status][$type] = $query->where($where)->count('_id');
                            }
                        }else{
                            $query->where(['RejectType' => (int)$paramData['reject_type']]);
                            $data[$status][$where['RejectType']] = $query->where($where)->count('_id');
                        }

                    }else{
                        $data[$status] = $query->where($where)->count('_id');
                    }
                }
            }else{
                $where['ProductStatus'] = (int)$paramData['status'];
                if($paramData['status'] == self::PRODUCT_STATUS_REJECT && isset($paramData['reject_type']) && !empty($paramData['reject_type'])){
                    if(is_array($paramData['reject_type'])){
                        foreach( $paramData['reject_type'] as $type){
                            $query->where(['RejectType' => (int)$type]);
                            $data[$paramData['status']][$type] = $query->where($where)->count('_id');
                        }
                    }else{
                        $query->where(['RejectType' => (int)$paramData['reject_type']]);
                        $data[$paramData['status']][$where['RejectType']] = $query->where($where)->count('_id');
                    }
                }else{
                    $data[$paramData['status']] = $query->where($where)->count('_id');
                }
            }
        }
        return $data;

    }

    /**
     * 产品审核
     */
    public function auditProduct($params){
        $product_id = $params['id'];

        if(is_array($product_id)){
            foreach($product_id as $id){
                //清除缓存
                CommonLib::rmProductCache($id);

                $ids[] = (int)$id;
            }
            $where = ['_id' => ['in',$ids]];
        }else{
            //清除缓存
            CommonLib::rmProductCache($product_id);

            $where = ['_id' => (int)$params['id']];
        }

        if($params['status'] == self::PRODUCT_STATUS_REJECT){
            $ret = $this->db->name('product')->where($where)->update(['ProductStatus' =>(int)$params['status'],'RejectReason'=>$params['reason'],'RejectType'=>(int)$params['type']]);
            if(!$ret){
                return false;
            }
        }else{
            //张恒：添加时间，以审核时间为准 add by zhongning 20190902
            $ret = $this->db->name('product')->where($where)->update(['ProductStatus' =>(int)$params['status'],'AddTime'=>time()]);
            if(!$ret){
                return false;
            }
        }

        //记录变更历史
        //edit by zhangheng 2019-01-10 15:09 此处数据需要翻译
        if(is_array($product_id)){
            foreach($product_id as $id){
                $params['IsSync'] = false;
                $params['Note'] = 'auditProduct-产品审核';
                CommonLib::productHistories($id.'-auditProduct',$params,'false');
            }
        }else{
            $params['IsSync'] = false;
            $params['Note'] = 'auditProduct-产品审核';
            CommonLib::productHistories($product_id.'-auditProduct',$params,'false');
        }
        return true;
    }

    /**
     * my wish 产品
     * @param $params
     * @return array
     */
    public function WishProductLists($where){
        /*$defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : 8;
        $page = isset($params['page']) ? $params['page'] : 1;*/

        $query = $this->db->name('product');
        /*$where = [
            //'ProductStatus'=> self::PRODUCT_STATUS_SUCCESS,
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
        ];*/
        //过滤空值
        CommonLib::filterNullValue($where);
        $query->where($where);
        //上架时间排序
        $query->order('AddTime','desc');
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title,FirstProductImage,
        AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,ProductStatus');
        return $query->select();
    }

    /**
     * EIP选品 产品
     * @param $params
     * @return array
     */
    public function getSkuProductBySkuCode($where){
        /*$defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : 8;
        $page = isset($params['page']) ? $params['page'] : 1;*/

        $query = $this->db->name('product');
        /*$where = [
            //'ProductStatus'=> self::PRODUCT_STATUS_SUCCESS,
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
        ];*/
        //过滤空值
        CommonLib::filterNullValue($where);
        $query->where($where);
        //上架时间排序
        //$query->order('AddTime','desc');
        $query->field('Skus');
        return $query->select();
    }

    /**
     * Sku 产品
     * @param $params
     * @return array
     */
    public function SkuSelectionProductLists($where){
        /*$defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : 8;
        $page = isset($params['page']) ? $params['page'] : 1;*/

        $query = $this->db->name('product');
        /*$where = [
            //'ProductStatus'=> self::PRODUCT_STATUS_SUCCESS,
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
        ];*/
        //过滤空值
        CommonLib::filterNullValue($where);
        $query->where($where);
        //上架时间排序
        $query->order('AddTime','desc');
        $query->field('_id,Skus,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title,FirstProductImage,
        AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,ProductStatus,SalesUnitType,AddTime,ListPriceDiscount,
        Keywords,ReviewCount,AvgRating,FirstCategory,SecondCategory,ThirdCategory,IsActivity');
        return $query->select();
    }

    /**
     * 根据条件获取产品数据
     * @param array $params
     * @param int $flag 1-根据条件判断是否存在产品数据
     * @return array|false|\PDOStatement|string|\think\Collection|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductByParams(array $params, $flag=0){
        $data = [];
        $query = $this->db->name('product');
        //产品ID
        if(isset($params['product_id']) && $params['product_id']){
            $query->where(['_id' => (int)$params['product_id']]);
        }
        //StoreID
        if(isset($params['StoreID']) && $params['StoreID']){
            $query->where(['StoreID' => (int)$params['StoreID']]);
        }
        //产品状态
        if(isset($params['ProductStatus']) && $params['ProductStatus']){
            if (is_array($params['ProductStatus'])){
                if (!empty($params['ProductStatus'])){
                    $status_arr = [];
                    foreach ($params['ProductStatus'] as $status){
                        $status_arr[] = (int)$status;
                    }
                    $query->where('ProductStatus', 'in', $status_arr);
                }
            }else{
                $query->where(['ProductStatus' => (int)$params['ProductStatus']]);
            }
        }
        if ($flag == 1){
            $data = $query->find();
        }else{
            $data = $query->select();
        }
        return $data;
    }

    /**
     * 根据条件获取产品数据[coupon使用]
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductByParamsForCoupon(array $params){
        $query = $this->db->name('product');
        //产品ID
        if(isset($params['ids']) && $params['ids']){
            $ids = [];
            foreach ($params['ids'] as $id){
                $ids[] = (int)$id;
            }
            $query->where('_id', 'in', $ids);
        }
        //StoreID
        if(isset($params['StoreID']) && $params['StoreID']){
            $query->where(['StoreID' => (int)$params['StoreID']]);
        }
        //产品状态
        if(isset($params['ProductStatus']) && $params['ProductStatus']){
            if (is_array($params['ProductStatus'])){
                $status = [];
                foreach ($params['ProductStatus'] as $info){
                    $status[] = (int)$info;
                }
                $query->where('ProductStatus','in',$status);
            }else{
                $query->where(['ProductStatus' => (int)$params['ProductStatus']]);
            }
        }
        return $query->select();

    }

    /**
     * 根据产品ID更新产品数据【产品编辑-异步更新运费模板使用】
     * @param array $params 要更新的数据
     * @return int|string
     */
    public function updateDataForTemplate(array $params){
        $data = [];
        $where = [];
        //产品ID
        if(isset($params['product_id']) && $params['product_id']){
            $where = ['_id' => (int)$params['product_id']];
        }
        //template_id
        if(isset($params['template_id']) && $params['template_id']){
            $data['LogisticsTemplateId'] = (int)$params['template_id'];
        }
        //template_name
        if(isset($params['template_name']) && $params['template_name']){
            $data['LogisticsTemplateName'] = $params['template_name'];
        }
        $params['IsSync'] = true;
        $params['Note'] = 'updateDataForTemplate-异步更新运费模板';
        CommonLib::productHistories($params['product_id'].'-updateDataForTemplate',$params,'true');
        return $this->db->name('product')->where($where)->update($data);
    }

    /**
     * 同步库存和销量数据【定时任务用】
     * @param array $params
     * @return bool|string
     */
    public function synInventoryAndSalesCounts(array $params){
        $rtn = true;
        try{
            //标识：0-扣减库存，增加销量（支付成功时）；1-回滚库存、销量（支付失败、事后风控等情况时）
            $flag = (isset($params['flag']) && !empty($params['flag']))?$params['flag']:0;
            $product_id = $params['spu_id'];
            $sku_id = $params['sku_id'];
            if ($flag == 0){
                $sku_inventory = $params['sku_inventory'];//要减掉的库存或要增加的销量
            }elseif ($flag == 1){
                $sku_inventory = -$params['sku_inventory'];//要回滚的库存量（增加）和销量（减少）
            }
            /** 1/获取产品数据 **/
            $proudct_info = $this->db->name('product')->where(['_id' =>(int)$product_id])->find();
            //是否是活动
            $is_activity = false;
            if (isset($proudct_info['IsActivity']) && !empty($proudct_info['IsActivity']) && $proudct_info['IsActivity'] >0){
                //判断活动数据是否正常，为了解决一个spu有多个sku但只有部分sku参加活动的情况 tinghu.liu 20190810
                if (isset($proudct_info['Skus'])){
                    foreach ($proudct_info['Skus'] as $k100=>$v100){
                        if (
                            $v100['_id'] == $sku_id
                            && isset($v100['ActivityInfo']['SalesLimit'])
                            && isset($v100['ActivityInfo']['DiscountPrice'])
                        ){
                            $is_activity = true;
                            break;
                        }
                    }
                }
            }
            /** 2/修改库存 **/
            $new_skus = [];
            if (isset($proudct_info['Skus'])){
                foreach ($proudct_info['Skus'] as $info){
                    $o_inventory = $info['Inventory']; //现有库存
                    $deducting_sku_inventory = $o_inventory;
                    //找到SPU对应的下的SKU进行修改
                    if ($info['_id'] == $sku_id){
                        $deducting_sku_inventory = ($o_inventory - $sku_inventory)>0?($o_inventory - $sku_inventory):0;
                        //如果是活动，则同步修改活动库存
                        if ($is_activity){
                            $inventory_flag = (int)($info['ActivityInfo']['SalesLimit'] - $sku_inventory);
                            $info['ActivityInfo']['SalesLimit'] = $inventory_flag>0?$inventory_flag:0;
                        }
                    }
                    //库存计算
                    $info['Inventory'] = (int)$deducting_sku_inventory;
                    $new_skus[] = $info;
                }
            }
            if (!empty($new_skus)){
                //库存同步 因为修改有问题，所以用自己封装的Mongo类来操作修改
                //$res1 = $this->db->name('product')->where(['_id' =>(int)$product_id])->update(['Skus'=>(object)$new_skus]);
                $_all_inventory =0;
                foreach ($new_skus as &$sku_info){
                    //数据类型处理
                    $sku_info['_id'] = (int)$sku_info['_id'];
                    $sku_info['Inventory'] = (int)$sku_info['Inventory'];
                    $_all_inventory += $sku_info['Inventory'];
                }
                $mongo = new Mongo('dx_product');
                $_skus_arr = ['Skus'=>$new_skus];
                if($_all_inventory==0){
                    $_skus_arr['ProductStatus'] = 3; //全部库存卖完，则停售
                }
                $res1 = $mongo->update(
                    ['_id' =>(int)$product_id],
                    ['$set'=>$_skus_arr]
                );
                if ($res1 || (int)$res1 === 0){
                    /** 3/修改销量 **/
                    //产品销量
                    $sales_counts = isset($proudct_info['SalesCounts'])?$proudct_info['SalesCounts']:0;
                    $new_sales_counts = (int)($sales_counts + $sku_inventory);
                    /** 增加总销量判断：不能小于0 BY tinghu.liu IN 20190121 **/
                    $new_sales_counts = $new_sales_counts>0?(int)$new_sales_counts:0;
                    $sales_up_data = [];
                    $sales_up_data['SalesCounts'] = $new_sales_counts;
                    if ($is_activity){
                        //如果是活动产品，需要同步活动产品销量
                        $sales_activity_counts = isset($proudct_info['InventoryActivitySalse'])?$proudct_info['InventoryActivitySalse']:0;
                        $new_sales_activity_counts = (int)($sales_activity_counts + $sku_inventory);
                        /** 增加活动总销量判断：不能小于0 BY tinghu.liu IN 20190121 **/
                        $new_sales_activity_counts = $new_sales_activity_counts>0?(int)$new_sales_activity_counts:0;
                        $sales_up_data['InventoryActivitySalse'] = $new_sales_activity_counts;
                    }
                    //销量同步
                    //$res2 = $this->db->name('product')->where(['_id' =>(int)$product_id])->update(['SalesCounts'=>$new_sales_counts]);
                    $res2 = $this->db->name('product')->where(['_id' =>(int)$product_id])->update($sales_up_data);
                    if (!$res2){
                        $msg = '销量修改失败，原销量：'.$sales_counts.'， 更新条件：'.$product_id.'，数据：'.json_encode($sales_up_data).'，params：'.json_encode($params).'，res：'.$res2;
                        $rtn = '销量修改失败';
                        Log::record($msg, Log::NOTICE);
                    }
                    if($_all_inventory==0){
                        //产品变更历史缓存
                        $params['IsSync'] = true;
                        $params['Note'] = 'synInventoryAndSalesCounts-同步库存和销量';
                        CommonLib::productHistories($product_id.'-synInventoryAndSalesCounts',$params,'true');
                    }
                }else{
                    $rtn = '库存修改失败';
                    Log::record($rtn.print_r(json_encode($new_skus), true));
                }
            }else{
                $rtn = 'Skus数据错误';
                Log::record($rtn.print_r(json_encode($new_skus), true));
            }
            //缓存清理
            CommonLib::rmProductCache($params['spu_id']);
        }catch (\Exception $e){
            $rtn = '同步库存和销量数据系统异常 '.$e->getMessage();
            Log::record('同步库存和销量数据系统异常 '.$e->getMessage());
        }
        return $rtn;
    }

    /**
     * 同步处理SKU库存
     * @param array $params
     * @return bool|string
     * added by wangyj 20190123
     */
    public function synInventoryAndSalesCountsV2(array $params){
        $rtn = true;
        try{
            //标识：0-扣减库存（支付成功时）；1-回滚库存（支付失败、事后风控等情况时）
            $flag           = (isset($params['flag']) && !empty($params['flag']))?$params['flag']:0;
            $product_id     = $params['spu_id'];
            $sku_id         = $params['sku_id'];
            $order_number   = $params['order_number'];
            unset($params['order_number']);

            if ($flag == 0){
                $sku_inventory = $params['sku_inventory'];//要减掉的库存或要增加的销量
            }elseif ($flag == 1){
                $sku_inventory = -$params['sku_inventory'];//要回滚的库存量（增加）和销量（减少）
            }

            /** 1/获取产品数据 **/
            $proudct_info = $this->db->name('product')->where(['_id' =>(int)$product_id])->find();

            if(empty($proudct_info)){

                $rtn = '产品信息获取失败';
                Log::record($rtn." order_number:{$order_number}");
            }

            //是否是活动
            $is_activity = false;
            if (isset($proudct_info['IsActivity']) && !empty($proudct_info['IsActivity']) && $proudct_info['IsActivity'] >0){

                //判断活动数据是否正常，为了解决一个spu有多个sku但只有部分sku参加活动的情况 tinghu.liu 20190810
                if (isset($proudct_info['Skus'])){
                    foreach ($proudct_info['Skus'] as $k100=>$v100){
                        if (
                            $v100['_id'] == $sku_id
                            && isset($v100['ActivityInfo']['SalesLimit'])
                            && isset($v100['ActivityInfo']['DiscountPrice'])
                        ){
                            $is_activity = true;
                            break;
                        }
                    }
                }
            }

            $activity_saleslimit_count  = 0;//活动库存数量统计
            $_all_inventory             =0; //所有库存

            /** 2/修改库存 **/
            if (isset($proudct_info['Skus'])){//Log::record($sku_id.print_r($proudct_info, true));
                $skus = [];
                foreach ($proudct_info['Skus'] as $info){

                    //数据类型处理
                    $info['_id']                = (int)$info['_id'];

                    $o_inventory                = $info['Inventory']; //现有库存
                    $deducting_sku_inventory    = $o_inventory;
                    //找到SPU对应的下的SKU进行修改
                    if ($info['_id'] == $sku_id){
                        $deducting_sku_inventory = ($o_inventory - $sku_inventory)>0?($o_inventory - $sku_inventory):0;
                        //如果是活动，则同步修改活动库存
                        if ($is_activity){
                            $inventory_flag = (int)($info['ActivityInfo']['SalesLimit'] - $sku_inventory);
                            $info['ActivityInfo']['SalesLimit'] = $inventory_flag>0?$inventory_flag:0;

                            $activity_saleslimit_count += ((int)($info['ActivityInfo']['SalesLimit']));
                        }
                    }else{
                        if (
                            $is_activity
                            && isset($info['ActivityInfo']['SalesLimit'])
                        ){
                            $activity_saleslimit_count += ((int)($info['ActivityInfo']['SalesLimit']));
                        }
                    }
                    //库存计算
                    $info['Inventory']   = (int)$deducting_sku_inventory;
                    $_all_inventory     += $info['Inventory'];

                    $skus[] = $info;
                }

                $mongo = new Mongo('dx_product');
                $_skus_arr = ['Skus'=>$skus];
                if($_all_inventory==0){
                    $_skus_arr['ProductStatus'] = 3; //全部库存卖完，则停售
                }
                $res1 = $mongo->update(
                    ['_id' =>(int)$product_id],
                    ['$set'=>$_skus_arr]
                );

                if ($res1 || (int)$res1 === 0){

                    $syn_sales_counts_res = $this->synSalesCounts($proudct_info, $is_activity, $sku_inventory, $order_number);
                    if($syn_sales_counts_res!==true)$rtn = $syn_sales_counts_res;

                    if($_all_inventory==0){
                        //产品变更历史缓存
                        $params['IsSync'] = true;
                        $params['Note'] = 'synInventoryAndSalesCounts-同步库存和销量';
                        CommonLib::productHistories($product_id.'-synInventoryAndSalesCounts',$params,'true');
                    }

                    //缓存清理
                    CommonLib::rmProductCache($params['spu_id']);

                }else{

                    $rtn = '库存修改失败';
                    Log::record($rtn.json_encode($_skus_arr)." order_number:{$order_number}");
                }

                if ($is_activity && $activity_saleslimit_count<=0){//减库存成功后判断活动销量若为0则退出活动

                    $handel_activity_over = $this->handleActivityOver($order_number, $proudct_info);
                    if($handel_activity_over!==true){
                        $rtn = $handel_activity_over;
                    }
                }

            }else{
                $rtn = 'Skus数据错误';
                Log::record($rtn.print_r(json_encode($proudct_info['Skus']), true)." order_number:{$order_number}");
            }

        }catch (\Exception $e){
            $rtn = '同步库存系统异常';
            Log::record('同步库存系统异常 '.$e->getMessage());
        }
        return $rtn;
    }

    /**
     * 同步销量数据
     * @param array $proudct_info
     * @param bool $is_activity
     * @param int $sku_inventory
     * @param string $order_number
     * @return bool|string
     * added by wangyj 20190123
     */
    private function synSalesCounts(&$proudct_info, $is_activity, $sku_inventory, $order_number=''){

        $rtn = true;
        try{

            //产品销量
            $sales_counts = isset($proudct_info['SalesCounts'])?$proudct_info['SalesCounts']:0;
            $new_sales_counts = (int)($sales_counts + $sku_inventory);
            /** 增加总销量判断：不能小于0 BY tinghu.liu IN 20190121 **/
            $new_sales_counts = $new_sales_counts>0?(int)$new_sales_counts:0;
            $sales_up_data = [];
            $sales_up_data['SalesCounts'] = $new_sales_counts;
            if ($is_activity){
                //如果是活动产品，需要同步活动产品销量
                $sales_activity_counts = isset($proudct_info['InventoryActivitySalse'])?$proudct_info['InventoryActivitySalse']:0;
                $new_sales_activity_counts = (int)($sales_activity_counts + $sku_inventory);
                /** 增加活动总销量判断：不能小于0 BY tinghu.liu IN 20190121 **/
                $new_sales_activity_counts = $new_sales_activity_counts>0?(int)$new_sales_activity_counts:0;
                $sales_up_data['InventoryActivitySalse'] = $new_sales_activity_counts;
            }
            //销量同步
            $res2 = $this->db->name('product')->where(['_id' =>(int)$proudct_info['_id']])->update($sales_up_data);
            if (!$res2){
                $rtn = '销量修改失败';
                Log::record($rtn.json_encode($sales_up_data).($order_number?" order_number:{$order_number}":'').(isset($proudct_info['_id'])?" proudct_info_id:{$proudct_info['_id']}":''));
            }

        }catch (\Exception $e){
            $rtn = '同步销量数据系统异常';
            Log::record('同步销量数据系统异常 '.($e->getMessage()).($order_number?" order_number:{$order_number}":''));
        }
        return $rtn;

    }

    /**
     * 处理活动结束并发站内信
     * @param array $proudct_info
     * @param string $order_number
     * @return bool|string
     * added by wangyj 20190123
     */
    private function handleActivityOver($order_number='', &$proudct_info){

        $rtn        = true;
        $product_id = (int)$proudct_info['_id'];
        $upd_activity_status = $this->updateActivityStatus(['product_id_arr'=>[$product_id]]);
        if($upd_activity_status){

            $activity_model = new Activity();
            $activity_data = $activity_model->getActivityDataByWhere(['id'=>$proudct_info['IsActivity']])[0];
            if(!empty($activity_data)){

                $activity_title = isset($activity_data['activity_title'])?$activity_data['activity_title']:'';
                $activity_title_str = '平台活动“'.$activity_title.'“信息';
                $message_content = <<<MCONT
亲爱的朋友，
       您参加的“ $activity_title ”活动商品：“ {$proudct_info['Title']} ” 库存已售完；商品将恢复原价销售；活动未结束时商品将继续获得活动曝光谢谢您的参与。如果您有任何问题,请随时与我联系。并希望再次与您合作!
MCONT;
                $message_data['title'] = $activity_title_str;
                $message_data['type'] = 1;//消息类型 1：系统消息 2:手工消息
                $message_data['send_user_id'] = -1;
                $message_data['send_user'] = 'TaskSystem';
                $message_data['content'] = $message_content;
                $message_data['addtime'] = time();
                $message_data['recive_user_id'] = $proudct_info['StoreID'];
                $message_data['recive_user_name'] = $proudct_info['StoreName'];
                $message_data['recive_type'] = 2;//接受者类型 1用户 2卖家
                $message_model = new Message();
                $msg_res = $message_model->insertMessageData($message_data);
                if ($msg_res!==true){
                    //添加站内信 失败日志记录 TODO
                    $rtn = '添加站内信失败';
                    Log::record('添加站内信失败'.json_encode($msg_res).json_encode($message_data).($order_number?" order_number:{$order_number}":''));
                }
            }else{
                $rtn = '活动信息获取失败';
                Log::record("{$rtn} activity_id:{$proudct_info['IsActivity']}".($order_number?" order_number:{$order_number}":''));
            }

        }else{
            $rtn = '更改活动结束失败';
            Log::record("{$rtn} product_id:{$proudct_info['_id']}".($order_number?" order_number:{$order_number}":''));
        }
        return $rtn;
    }

    /**
     * 更新产品活动状态【活动定时任务专用】
     * @param $params
     * @return int|string
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function updateActivityStatus($params){
        $id_arr = [];
        foreach ($params['product_id_arr'] as $id) {
            $id_arr[] = (int)$id;
            //清除产品缓存
            CommonLib::rmProductCache($id);
            //产品变更历史缓存
            $params['IsSync'] = true;
            $params['Note'] = 'updateActivityStatus-更新产品活动状态';
            CommonLib::productHistories($id.'-updateActivityStatus',$params,'true');
        }
        $up_data['IsActivity'] = 0;
        $up_data['IsActivityEnroll'] = 0;
        $up_data['IsActivityEnrollStartTime'] = 0;
        $up_data['IsActivityEnrollEndTime'] = 0;
        //HightDiscount，DiscountLowPrice，DiscountHightPrice
        $up_data['HightDiscount'] = 0;
        $up_data['DiscountLowPrice'] = 0;
        $up_data['DiscountHightPrice'] = 0;
        $up_data['EditTime'] = time();

        /**
         * 增加活动ID字段条件（只有在同步活动结束定时任务时会有） tinghu.liu 20190822
         *
         * activity_product_arr 格式：
         *
         * Array
            (
                [311] => Array              ---- 活动ID
                (
                    [0] => 620549           --- 产品spuID
                    [1] => 620534           --- 产品spuID
                )

                [339] => Array
                (
                    [0] => 2028678
                )
            )
         *
         */
        if (isset($params['activity_product_arr']) && !empty($params['activity_product_arr'])){
            $res = 0;
            foreach ($params['activity_product_arr'] as $k=>$v) {
                //组装更新条件
                $activity_id = (int)$k;
                $product_id_arr = [];
                foreach ($v as $k1=>$v1){
                    $product_id_arr[] = (int)$v1;
                }
                //更新已经结束的活动产品
                $res = $this->db->name('product')
                    ->where('_id', 'in', $product_id_arr)
                    ->where(['IsActivity'=>$activity_id])
                    ->update($up_data);
            }
            //以最后一个修改结果为准返回
            return $res;
        }else{

            return $this->db->name('product')->where('_id', 'in', $id_arr)->update($up_data);
        }
    }

    /**
     * 初始化sku，spu自增ID
     * @return int|string
     */
    public function initSpuIncrement(){
        $incrment = $this->getAutoIncrement();
//        $spu =  $this->db->name('product')->order('_id','desc')->limit(1)->value('_id');
//        if(!empty($spu)){
        //更新SKU增值表
        $updateResult = $this->updateAutoIncrement(
            ['SKU'=>(int)1000000, 'SubSKU' => 1000000],
            ['SKU'=>(int)$incrment['SKU'],'SubSKU' => (int)$incrment['SubSKU']]);
        if($updateResult){
            return true;
        }
//        }
        return false;
    }

    /**
     * 初始化BrandId，自增ID
     * @return int|string
     */
    public function initBrandIncrement(){
        $incrment = $this->getAutoIncrement();
//        $BrandId =  $this->db->name('brands')->order('BrandId','desc')->limit(1)->value('BrandId');
//        if(!empty($BrandId)){
        //更新SKU增值表
        $updateResult = $this->updateAutoIncrement(
            ['BrandId'=>(int)1000000],
            ['BrandId'=>(int)$incrment['BrandId']]);
        if($updateResult){
            return true;
        }
//        }
        return false;
    }

    /**
     * 根据多个产品ID获取产品数据
     * @param $params
     * @return int|string
     */
    public function getMorePruductData($params){
        $id_arr = [];
        foreach ($params['product_id_arr'] as $id) {
            $id_arr[] = (int)$id;
        }
        return $this->db->name('product')->where('_id', 'in', $id_arr)->select();
    }

    /**
     * 获取产品浏览历史数据【my使用】
     * @param array $params
     * @return array
     * @throws \think\exception\DbException
     */
    public function getProductViewHistoryDataForMy(array $params){
        $query = $this->db->name('product');
        //产品ID
        if (isset($params['product_id_arr']) && !empty($params['product_id_arr'])){
            $id_arr = [];
            foreach ($params['product_id_arr'] as $info){
                $id_arr[] = (int)$info;
            }
            $query->where('_id', 'in', $id_arr);
        }
        //分类ID（一级分类）
        if (isset($params['category_id']) && !empty($params['category_id'])){
            $query->where('FirstCategory', '=', (int)$params['category_id']);
        }
        //产品状态
        if (isset($params['product_status']) && $params['product_status'] != -1){
            $query->where('ProductStatus', '=', (int)$params['product_status']);
        }

        $default_page_size = config('paginate.page_size');
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : $default_page_size;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;

        //查询字段
        $query->field(["BrandId","BrandName","StoreID","StoreName",'GroupId','GroupName','ProductType','CategoryPath','FirstCategory','SecondCategory'
            ,'ThirdCategory','FourthCategory','ImageSet','ProductStatus','Days','Title',
            'VideoCode','GTINs','LogisticsLimit','SalesUnitType','LogisticsTemplateId','LogisticsTemplateName','SalesMode','AllowBulkRate','FirstProductImage','FilterOptions','Supplier','DeclarationName',
            'AddTime','Skus._id','Skus.Code','Skus.SalesPrice','Skus.Inventory','LowPrice','HightPrice','DiscountLowPrice','DiscountHightPrice','SalesRank','Commission','EditorTime','RejectReason','AvgRating','ReviewCount','IsActivity'
        ]);

        //分页信息
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path]);
        $Page = $ret->render();
        $data = $ret->toArray();
        $data['Page'] = $Page;
        return $data;

    }

    /**
     * 更新产品，根据某个条件
     * @param $where
     * @param $upate
     * @return int|string
     * @throws Exception
     */
    public function updateProductKey($where,$upate){
        //产品变更缓存
        $params = array();
        $params['where'] = $where;
        $params['update'] = $upate;
        $params['IsSync'] = true;
        $params['Note'] = 'updateProductKey-根据条件修改产品数据通用';
        CommonLib::productHistories($where['_id'].'-updateProductKey',$params,'true');

        return $this->db->name('product')->where($where)->update($upate);
    }

    /**
     * 更新产品SR
     * @param $where
     * @param $upate
     * @return int|string
     * @throws Exception
     */
    public function updateProductSalesRank($where,$upate){
        //产品变更缓存
        return $this->db->name('product')->where($where)->update($upate);
    }

    /**
     * 产品列表
     */
    public function getProductListsByCategory($params){
        $default_page_size = config('paginate.page_size');
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : $default_page_size;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        $where = array();
        $query = $this->db->name('product');

        if(isset($params['first_category'])&& !empty($params['first_category'])){
            if(is_array($params['first_category'])){
                $whereParams['FirstCategory'] = $params['first_category'];
            }else{
                $whereParams['FirstCategory'] = (int)$params['first_category'];
            }
        }
        if(isset($params['product_id'])&& !empty($params['product_id'])){
            $whereParams['_id'] = (int)$params['product_id'];
        }
        if(isset($params['isUpdateSaleRank'])){
            $whereParams['IsUpdateSaleRank'] = ['<>',(int)$params['isUpdateSaleRank']];
        }
        $query->where($whereParams);
        //默认上架时间排序
        $query->order('AddTime','desc');

        //查询字段
        $query->field(['AddTime','Skus.Code','CategoryPath','Reviews']);
        //分页信息
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$whereParams]);
        $Page = $ret->render();
        $data = $ret->toArray();
        $data['Page'] = $Page;
        return $data;
    }


    /**
     * 产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getProductLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'FirstCategory'=> isset($params['firstCategory']) ? (int)$params['firstCategory'] : null,
            'SecondCategory'=> isset($params['secondCategory']) ? (int)$params['secondCategory'] : null,
            'ThirdCategory'=> isset($params['thirdCategory']) ? (int)$params['thirdCategory'] : null,
            'FourthCategory'=> isset($params['fourthCategory']) ? (int)$params['fourthCategory'] : null,
            'IsMVP'=> isset($params['isMvp']) ? $params['isMvp'] : null,
            'IsStaffPick'=> isset($params['isStaffPick']) ? $params['isStaffPick'] : null,
            'Tags.IsPresale'=> isset($params['isPresale']) ? $params['isPresale'] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);

        //价格起始值大于结束值
        if(isset($params['lowPrice']) && $params['hightPrice']){
            if($params['lowPrice'] > $params['hightPrice']){
                $tmp = $params['lowPrice'];
                $params['lowPrice'] = $params['hightPrice'];
                $params['hightPrice'] = $tmp;
            }
        }
        //价格筛选
        if(isset($params['lowPrice']) && $params['lowPrice']){
            $where['LowPrice'] = ['gte',(double)$params['lowPrice']];
        }
        if(isset($params['hightPrice']) && $params['hightPrice']){
            $where['LowPrice'] = [ 'between' , [(double)$params['lowPrice'],(double)$params['hightPrice']]];
        }

        if(isset($params['newArrivals'])){
            $where['AddTime'] = ['>=',strtotime('-15 day')];
        }

        $query->where($where);

        //SR排序
        if(isset($params['salesRank']) && $params['salesRank'] == 'true'){
            $query->order('SalesRank','desc');
        }
        //销量排行
        if(isset($params['salesCounts']) && $params['salesCounts'] == 'true'){
            $query->order('SalesCounts','desc');
        }
        //评论数排序
        if(isset($params['reviewCount']) && $params['reviewCount'] == 'true'){
            $query->order('ReviewCount','desc');
        }
        //价格排序
        if(isset($params['priceSort']) && $params['priceSort']){
            $priceSort = $params['priceSort'] == 'true' ? 'asc' : 'desc';
            $query->order('LowPrice',$priceSort);
        }

        //添加时间排序
        $query->order('AddTime','desc');

        //搜索字段
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title,Supplier,DeclarationName
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 多语言
     * 产品标题，产品说明
     * --title
     * --descriptions
     * @param $product_id
     * @param $lang
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductMultiLang($product_id,$lang){
        return $this->db->name($this->product_lang)->where(['_id' => (int)$product_id])
            ->field(['Descriptions.en','Title.en','Keywords.en','Title.'.$lang,'Descriptions.'.$lang,'Keywords.'.$lang])->find();
    }
    /**
     * 获取产品所有多语言
     * @param $product_id
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductAllMultiLangs($product_id){
        return $this->db->name($this->product_lang)->where(['_id' => (int)$product_id])->find();
    }
    /*
     * 多语言
     * */
    public function addProductMultiLang($data){
        return $this->db->name($this->product_lang)->insert($data);
    }
    /**
     *多语言
     * 产品属性
     * 颜色 --对应的ID --title对应翻译
     * 红色 --对应ID -- Option对应翻译
     * 计量单位 -- 件/包
     * @param $attr_id
     * @param $key
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductAttrDefsLang($attr_id,$key){
        return $this->db->name($this->attr_lang)->where(['_id' => (int)$attr_id])
            ->field(['Title','Options.'.$key])->find();
    }

    /**
     * 大于10000的option_id
     *多语言
     * 产品属性
     * 颜色 --对应的ID --title对应翻译
     * 红色 --对应ID -- Option对应翻译
     * @param $attr_id
     * @param $key
     * @param $product_id
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductCustomAttrMultiLangs($attr_id,$key,$product_id){
        return $this->db->name($this->custom_attr_lang)->where(['AttributeDefId' => (int)$attr_id,'dx_product_id'=>$product_id])
            ->field(['Title','Options.'.$key])->find();
    }


    /**
     * 修改产品有效期
     * @param $params
     * @return bool
     */
    public function updateProlongExpiry($params){
        $error = 0 ;
        $success = 0;
        $product_id = is_array($params['id']) ? $params['id'] : [$params['id']];
        foreach($product_id as $id){
            //清除缓存
            CommonLib::rmProductCache($id);

            //判断有效期是否在三天内
            $proudct = $this->db->name('product')->where(['_id'=>(int)$id,'ExpiryDate'=>['<',strtotime('+3 days')]])->find();
            if(empty($proudct)){
                $error++;
                continue;
            }
            $expiryDate = $proudct['ExpiryDate'] + (86400 * $params['days']);
            $ret = $this->db->name('product')->where(['_id'=>(int)$id])->update(['ExpiryDate' =>(int)$expiryDate]);
            if($ret){
                //变更历史
                $params['IsSync'] = true;
                $params['Note'] = 'updateProlongExpiry-修改产品有效期';
                CommonLib::productHistories($id.'-updateProlongExpiry',$params,'true');
            }
            $success++;
        }

        return ['error'=>$error,'success'=>$success];
    }

    /**
     * 产品列表 --  变更历史
     */
    public function getProductByIDs($params){
        $query = $this->db->name('product');
        if(isset($params['id']) && !empty($params['id'])){
            if(count($params['id']) > 100){
                array_splice($params['id'],0,100);
            }
            $where['_id'] = CommonLib::supportArray($params['id']);
        }
        if(isset($params['status']) && !empty($params['status'])){
            $where['ProductStatus'] = CommonLib::supportArray($params['status']);
        }
        if(!empty($params['is_mvp'])){
            $where['IsMVP'] = 1;
        }
        $query->field(['Title','Skus.Inventory','Skus._id','Skus.Code','Skus.SalesPrice','SalesRank','BrandId','RewrittenUrl','ImageSet.ProductImg',
            'FirstCategory','PackingList.Weight','HSCode','LogisticsLimit','AddTime','EditTime','ProductStatus','DeclarationName','SalesCounts',
            'SecondCategory','ThirdCategory','FourthCategory','IsHistory','Skus.SalesAttrs','Skus.UnitCost','BrandName','LowPrice','HightPrice',
            'DiscountLowPrice','DiscountHightPrice','HightDiscount','IsActivity','GTINs','SalesUnitType','Supplier','IsMVP','LowListPrice','HighListPrice'
        ]);
        return $query->where($where)->select();
    }

    /**
     * 查找是否是自营店铺商品
     * @param $product_id
     * @return bool
     */
    public function selfSupportProduct($product_id){

        //自营店铺编码
        $store = self::$selfStore;
        $ret = $this->db->name('product')->where(['_id'=>(int)$product_id])->field('StoreID')->find();
        if(isset($store[$ret['StoreID']])){
            return true;
        }
        return false;
    }

    /**
     * 获取历史产品数据【同步运费模板&&历史产品图片专用】
     * @param int $page_size
     * @param int $start_spu_id
     * @param int $end_spu_id
     * @param array $us_spus
     * @param int $check_flag 是否是数据检查标识：1-是，0-否
     * @return \think\Paginator
     * @throws \think\exception\DbException
     */
    public function getHistoryDataForAsyncShippingTemplateAndImgs($page_size=12, $start_spu_id=0, $end_spu_id=0, $us_spus=[], $check_flag=0){
        $data = [];
        //历史数据、没有同步运费模板的数据
        if ($start_spu_id != 0 && $end_spu_id !=0){
            if (!empty($us_spus)){
                $us_spus_new = [];
                foreach ($us_spus as $info){
                    $us_spus_new[] = (int)$info;
                }
                $data = $this->db->name('product')
                    //20180927同步新数据修改
                    //$data = $this->db->table('new_dx_product')
                    /*->where('_id', '>=', (int)$start_spu_id)
                    ->where('_id', '<=', (int)$end_spu_id)*/

                    ->where('_id','in',$us_spus_new)

                    ->where(['IsHistory'=>1])
                    ->where(['ProductStatus'=>self::PRODUCT_STATUS_SUCCESS])
                    //->where(['IsHistoryIsSyncST'=>null])
                    //->where('IsHistoryIsSyncSTAndImgs', '<>', 2)
                    ->where('IsHistoryIsSyncSTAndImgs', '<>', self::ISHISTORYISSYNCSTANDIMGSFLAG)
                    //->where('LogisticsTemplateId', '=', 0)


                    ->field([
                        'StoreID','IsHistory','IsHistoryIsSyncSTAndImgs','ImageSet','ProductStatus','LogisticsLimit','LogisticsTemplateId','LogisticsTemplateName'
                    ])
                    ->paginate($page_size)->toArray();
            }else{
                $data = $this->db->name('product')
                    //20180927同步新数据修改
                    //$data = $this->db->table('new_dx_product')
                    /*->where('_id', '>=', (int)$start_spu_id)
                    ->where('_id', '<=', (int)$end_spu_id)*/

                    ->where('_id',['>=', (int)$start_spu_id],['<=',(int)$end_spu_id])

                    ->where(['IsHistory'=>1])
                    ->where(['ProductStatus'=>self::PRODUCT_STATUS_SUCCESS])
                    //->where(['IsHistoryIsSyncST'=>null])
                    //->where('IsHistoryIsSyncSTAndImgs', '<>', 2)
                    ->where('IsHistoryIsSyncSTAndImgs', '<>', self::ISHISTORYISSYNCSTANDIMGSFLAG)
                    //->where('LogisticsTemplateId', '=', 0)
                    ->field([
                        'StoreID','IsHistory','IsHistoryIsSyncSTAndImgs','ImageSet','ProductStatus','LogisticsLimit','LogisticsTemplateId','LogisticsTemplateName'
                    ])
                    ->paginate($page_size)->toArray();
            }
            //是数据检查
            if ($check_flag == 1) {
                //1、判断如果符合条件的产品已经在cost表存在，则不处理
                foreach ($data['data'] as $k=>$v){
                    $product_id = $v['_id'];
                    $tem_data = $this->db->name('shipping_cost')->where(['ProductId'=>$product_id])->find();
                    //20180927同步新数据修改
                    //$tem_data = $this->db->table('new_dx_shipping_cost')->where(['ProductId'=>$product_id])->find();
                    if (!empty($tem_data)){
                        unset($data['data'][$k]);
                    }
                }
            }
        }
        return $data;
    }


    /**
     * 查找spu sku id code
     * @param $params
     * @return bool
     */
    public function queryProductId($params){
        $where = array();
        if(isset($params['code']) && !empty($params['code'])){
            if(is_array($params['code'])){
                $where['Skus.Code'] = CommonLib::supportArrayString($params['code']);//多加了一个查询字符串的方法
            }else{
                $where['Skus.Code'] = $params['code'];
            }
        }
        if(isset($params['sku']) && !empty($params['sku'])){
            if(is_array($params['sku'])){
                $where['Skus._id'] = CommonLib::supportArray($params['sku']);
            }else{
                $where['Skus._id'] = (int)$params['sku'];
            }
        }
        if(isset($params['spu']) && !empty($params['spu'])){
            if(is_array($params['spu'])){
                $where['_id'] = CommonLib::supportArray($params['spu']);
            }else{
                $where['_id'] = (int)$params['spu'];
            }
            unset($where['Skus._id']);
        }
        //店铺
        if(isset($params['seller_id']) && !empty($params['seller_id'])){
            $where['StoreID'] = (int)$params['seller_id'];
        }
        if(empty($where)){
            return array();
        }
        $ret = $this->db->name('product')->where($where)->field(['_id','Skus._id','Skus.Code','ProductStatus'])->select();
        return $ret;
    }


    /**
     * 查找spu sku id
     * @param $params
     * @return bool
     */
    public function queryProductUpdateFor916($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 20;
        $page = isset($params['page']) ? $params['page'] : 1;
        $query = $this->db->name('product');
        $where = array();
        $where['ProductStatus'] = ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]];
        //店铺
        if(isset($params['seller_id']) && !empty($params['seller_id'])){
            $where['StoreID'] = (int)$params['seller_id'];
        }
        if(isset($params['_id']) && !empty($params['_id'])){
            $where['_id'] = (int)$params['_id'];
        }
        if(isset($params['ids']) && !empty($params['ids'])){
            $where['_id'] = CommonLib::supportArray($params['ids']);
        }
        if(isset($params['skus']) && !empty($params['skus'])){
            $where['Skus._id'] = CommonLib::supportArray($params['skus']);
        }
        if(empty($where)) {
            return array();
        }
//        $query->where('_id',['>=', (int)$start_spu_id],['<=',(int)$end_spu_id]);
//        $ret = $query->where($where)->field(['_id','Skus._id','Skus.SalesPrice','LowPrice'])->select();
        //        return $ret;
        $query->where($where)->field(['_id','Skus._id','Skus.Code','Skus.SalesPrice','LowPrice','HightPrice','Skus.BulkRateSet']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 指定条件，更新字段，更新产品表
     * @param $where
     * @param $update
     * @return int|string
     * @throws Exception
     */
    public function updateProductSkuPrice($where,$update){
        $ret = $this->db->name('product')->where($where)->update($update);
        return $ret;
    }

    /**
     * 指定条件，字段查找产品
     * @param $where
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getProductInField($where,$field){
        return $this->db->name($this->product)->where($where)->field($field)->find();
    }


    /**
     * 指定spu范围查找
     * @param $start_spu_id
     * @param $end_spu_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getProductId($start_spu_id,$end_spu_id){
        $query = $this->db->name('product');
        $where['ProductStatus'] = ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]];
        $query->where('_id',['>=', (int)$start_spu_id],['<=',(int)$end_spu_id]);
        $ret = $query->where($where)->field(['_id'])->select();
        return $ret;
    }



    /**
     * 产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function queryProduct916($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 10;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        if(isset($params['seller_id'])){
            $where['StoreID'] = $params['seller_id'];
        }
        if(isset($params['product_id']) && !empty($params['product_id'])){
            $where['_id'] = $params['product_id'];
        }

        if(isset($params['IsHistory']) && !empty($params['IsHistory'])){
            $where['IsHistory'] = 0;
        }

        if(isset($params['start_time']) && !empty($params['start_time'])){
            $where['AddTime'] = ['gte',(int)$params['start_time']];
        }

        if(isset($params['is_split']) && !empty($params['is_split'])){
            $where['is_split'] = (string)$params['is_split'];
        }

        //区间价搜索
//        if(isset($params['hightPrice']) && $params['hightPrice']){
//            $where['LowPrice'] = [ 'between' , [(double)$params['lowPrice'],(double)$params['hightPrice']]];
//        }

        $where['ProductStatus'] = 1;
        $where['PackingList.Title'] = ['like',"amp;gt"];
        $query->where($where);
        //$query->order('AddTime','desc');
        //搜索字段
        $query->field(['_id','Title','LowPrice','HightPrice','Skus._id','Skus.SalesPrice','PackingList']);
//        $query->select();
//        pr($query->getLastSql());die;
//        $query->field(['_id','IsHistory','RewrittenUrl','LowPrice','HightPrice','Skus.SalesPrice','Skus.BulkRateSet','Skus._id','Skus.SalesAttrs','CategoryPath','AddTime','EditTime','PackingList']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }


    /*
     * 添加历史
     * */
    public function addProductHistory($data){
        return $this->db->name($this->historyProudct)->insert($data);
    }

    /**
     * 计算运费使用的产品字段
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getProductByShipingUse($params){
        $data = $this->db->name($this->product)
            ->where(['_id' => (int)$params['spu']])
            ->field('_id,Skus._id,Skus.SalesPrice,Skus.ActivityInfo,IsMVP,PackingList,LogisticsTemplateId,LogisticsTemplateName,LogisticsLimit')->find();
        return $data;
    }

    /**
     *
     * 产品运费模板
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getSpuShipping($params){
        $query = $this->db->name($this->shipping);
        if(isset($params['spu'])){
            $query->where(['ProductId' => (string)$params['spu']]);
        }
        if(isset($params['country'])){
            $query->where(['ToCountry' => trim($params['country'])]);
        }
        $data = $query->field('ProductId,ToCountry,ShippingCost,VAT')->find();
        return $data;
    }

    /**
     * 修改产品数据【同步历史产品运费模板数据专用】
     * @param array $params
     * @return int|string
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function updateForSyncHistoryProductSTAndImgs(array $params){
        $product_id = $params['id'];
        unset($params['id']);
        if (isset($params['IsHistoryIsSyncSTAndImgs'])){
            $params['IsHistoryIsSyncSTAndImgs'] = (int)$params['IsHistoryIsSyncSTAndImgs'];
        }
        $params['EditorTime'] = time();
        //return $this->db->table('new_dx_product')
        return $this->db->name('product')
            ->where(['_id'=>(int)$product_id])
            ->update($params);
    }


    public function updatePrdouctmMultiLangs($where,$update){
        $ret = $this->db->name('product_multiLangs')->where($where)->update($update);
        return $ret;
    }

    /**
     * 多语言
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getPrdouctmMultiLangs($params){
        return $this->db->name('product_multiLangs')->where(['_id'=>(int)$params['id']])->find();
    }

    /*根据SKU获取SPU*/
    public function skuToSpu($sku){
        $where['Skus._id'] = (int)$sku;
        return $this->db->name($this->product)->where($where)->value("_id");
    }

    /**
     * 产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function queryProductImg($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 100;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        if(isset($params['seller_id'])){
            $where['StoreID'] = $params['seller_id'];
        }
        if(isset($params['product_id']) && !empty($params['product_id'])){
            $where['_id'] = $params['product_id'];
        }
        $where['ProductStatus'] = ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]];
        if(isset($params['AddTime']) && !empty($params['AddTime'])){
            $where['AddTime'] = ['>=',1543161600];
        }
        $query->where($where);
        //搜索字段
        $query->field(['_id','ImageSet.ProductImg','FirstProductImage']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }


    /**
     * 新品页面，产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function newArrivalsProductLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'IsMVP'=> isset($params['isMvp']) ? 1 : null,
            'IsStaffPick'=> isset($params['isStaffPick']) ? 1 : null,
            'Tags.IsPresale'=> isset($params['isPresale']) ? 1 : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);

        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $query->whereOr(['FirstCategory'=>$params['lastCategory']])->whereOr(['SecondCategory'=>$params['lastCategory']])
                ->whereOr(['ThirdCategory'=>$params['lastCategory']])->whereOr(['FourthCategory'=>$params['lastCategory']]);
        }

        //一级类别
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            if(is_array($params['firstCategory'])){
                $where['FirstCategory'] = $params['firstCategory'];
            }else{
                $where['FirstCategory'] = (int)$params['firstCategory'];
            }
        }

        //二级类别
        if(isset($params['secondCategory']) && !empty($params['secondCategory'])){
            if(is_array($params['secondCategory'])){
                $where['SecondCategory'] = $params['secondCategory'];
            }else{
                $where['SecondCategory'] = (int)$params['secondCategory'];
            }
        }

        //三级类别
        if(isset($params['thirdCategory']) && !empty($params['thirdCategory'])){
            if(is_array($params['thirdCategory'])){
                $where['ThirdCategory'] = $params['thirdCategory'];
            }else{
                $where['ThirdCategory'] = (int)$params['thirdCategory'];
            }
        }

        //四级类别
        if(isset($params['fourthCategory']) && !empty($params['fourthCategory'])){
            if(is_array($params['fourthCategory'])){
                $where['FourthCategory'] = $params['fourthCategory'];
            }else{
                $where['FourthCategory'] = (int)$params['fourthCategory'];
            }
        }

        //价格起始值大于结束值
        if(isset($params['lowPrice']) && isset($params['hightPrice'])){
            if($params['lowPrice'] > $params['hightPrice']){
                $tmp = $params['lowPrice'];
                $params['lowPrice'] = $params['hightPrice'];
                $params['hightPrice'] = $tmp;
            }
        }
        //价格筛选
        if(isset($params['lowPrice']) && $params['lowPrice']){
            $where['LowPrice'] = ['gte',(double)$params['lowPrice']];
        }
        if(isset($params['hightPrice']) && $params['hightPrice']){
            $where['LowPrice'] = [ 'between' , [(double)$params['lowPrice'],(double)$params['hightPrice']]];
        }

        if(isset($params['newArrivals'])){
            $where['AddTime'] = ['>=',strtotime('-15 day')];
        }

        $query->where($where);

        //SR排序
        if(isset($params['salesRank']) && $params['salesRank'] == 'true'){
            $query->order('SalesRank','desc');
        }
        //销量排行
        if(isset($params['salesCounts']) && $params['salesCounts'] == 'true'){
            $query->order('SalesCounts','desc');
        }
        //评论数排序
        if(isset($params['reviewCount']) && $params['reviewCount'] == 'true'){
            $query->order('ReviewCount','desc');
        }
        //价格排序
        if(isset($params['priceSort']) && $params['priceSort']){
            $priceSort = $params['priceSort'] == 'true' ? 'asc' : 'desc';
            $query->order('LowPrice',$priceSort);
        }

        //添加时间排序
        $query->order('AddTime','desc');

        //搜索字段
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }


    /**
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getProductListsByClass($params){
        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
        ];
        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $query->whereOr(['FirstCategory'=>$params['lastCategory']])->whereOr(['SecondCategory'=>$params['lastCategory']])
                ->whereOr(['ThirdCategory'=>$params['lastCategory']])->whereOr(['FourthCategory'=>$params['lastCategory']]);
        }

        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            if(is_array($params['firstCategory'])){
                $where['FirstCategory'] = $params['firstCategory'];
            }else{
                $where['FirstCategory'] = (int)$params['firstCategory'];
            }
        }
        if(isset($params['LogisticsLimit'])){
            $where['LogisticsLimit.0'] = (string)$params['LogisticsLimit'];
        }
        $query->where($where);

        //搜索字段
        $query->field('_id,CategoryPath,StoreID,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,Title,FirstProductImage,Skus.Code');

        return $query->select();
    }

    /**
     * 第一次同步customvalue，临时表
     * @param $productData
     * @return int|string
     */
    public function addTempCustomeValueLang($productData){
        return $this->db->name('product_custom_value_temp')->insert($productData);
    }

    /**
     * 多语言
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function getPrdouctmMultiLangsByFiled($params){
        return $this->db->name('product_multiLangs')->where(['_id'=>(int)$params['id']])->field(['Descriptions'])->find();
    }

    /**
     * 指定条件，更新字段，更新产品表
     * @param $where
     * @param $update
     * @return int|string
     * @throws Exception
     */
    public function updatePrdouctmMultiLangsByWhere($where,$update){
        $ret = $this->db->name('product_multiLangs')->where($where)->update($update);
        return $ret;
    }

    /**
     * 第一次同步customvalue，临时表
     * @param $productData
     * @return int|string
     */
    public function addTempPackingTitleLang($productData){
        return $this->db->name('product_packing_title_temp')->insert($productData);
    }

    /**
     * 包装清单翻译
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function product_packing_title_list($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 10;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name('product_packing_title_temp');

//        $query->where(['_id'=>(int)620425]);

        //搜索字段
        $query->field(['_id','Title']);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 第一次同步customvalue，临时表
     * @param $productData
     * @return int|string
     */
    public function add_product_skus($productData){
        return $this->db->name('product_skus')->insert($productData);
    }
    public function find_product_skus($id){
        return $this->db->name('product_skus')->where(['_id'=>(int)$id])->find();
    }


    public function getShipping($params){
        $where = array();
        $query = $this->db->name($this->shipping);

        if(isset($params['product_id']) && $params['product_id']){
            if(is_array($params['product_id'])){
                foreach($params['product_id'] as $id){
                    $ids[] = (string)$id;
                }
                $where = ['ProductId' => ['in',$ids]];
            }else{
                $where = ['ProductId' => (string)$params['product_id']];
            }
        }
        return $query->where($where)->select();
    }

    /**
     * 国家区域售价
     * @param $product_id
     * @param $country
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductRegionPrice($product_id,$country){
        $data = $this->db->name($this->product_regions_price)->where(['Spu' => (int)$product_id,'Country'=> $country])
            ->field(['LowPrice','HightPrice','Skus','Spu','_id'=>false])->find();
        return $data;
    }


    /**
     * 原生写法，更新产品
     * @param $product_id
     * @param $upate
     * @return int|null
     */
    public function primevalUpdateProduct($product_id,$upate){
        //原生写法
        $mongo = new Mongo('dx_product');
        return $mongo->update(['_id' =>(int)$product_id], ['$set'=>$upate]);
    }
}