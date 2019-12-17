<?php
namespace app\app\model;

use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
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

    protected $product = 'product';
    protected $shipping ='shipping_cost';
    protected $product_lang = 'product_multiLangs';
    protected $attr_lang ='product_attr_multiLangs';
    protected $custom_attr_lang ='product_customAttr_multiLangs';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 产品是否正常使用
     * @param $product_id
     * @return bool
     */
    public function checkProduct($product_id){
        $ret = $this->db->name($this->product)->where([
            '_id'=>(int)$product_id,
            'ProductStatus'=>['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]]])->value('_id');
        if(empty($ret)){
            return false;
        }
        return true;
    }

    /**
     * 查询产品 --单个
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function findProduct($params){
        $where = array();
        $query = $this->db->name($this->product);
        if(isset($params['product_id']) && $params['product_id']){
            $where = ['_id' => (int)$params['product_id']];
        }
        if(isset($params['sku_id']) && $params['sku_id']){
            $where = ['Skus._id' => (int)$params['sku_id']];
        }
        if(isset($params['activity_id']) && !empty($params['activity_id'])){
            $where['IsActivity'] = (int)$params['activity_id'];
        }
        if(isset($params['activitySalse']) && !empty($params['activitySalse'])){
            $query->order('InventoryActivitySalse','desc');
        }
        if(empty($where)){
            return [];
        }
        $where['ProductStatus'] = ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]];

        $query->field('
            _id,FirstProductImage,FirstCategory,SecondCategory,ThirdCategory,SalesCounts,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,
            Discount,CategoryPath,BrandId,Title,ImageSet,ReviewCount,AvgRating,StoreName,InventoryActivity,InventoryActivitySalse,ColorCount,
            IsOnSale,IsCoupon,SalesUnitType,DeliveryEndDays,AllowBulkRate,ShippingFee,Skus,IsMVP,IsStaffPick,Tags,VideoCode,IsActivity,
            FilterOptions,PackingList,RewrittenUrl,Keywords,StoreID,BrandName,HSCode,ProductStatus,HightDiscount
            ');
        return $query->where($where)->find();
    }

    /**
     * 获取产品运费模板
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function getShipping($params){
        $where = array();
        $query = $this->db->name($this->shipping);

        if(isset($params['product_id']) && $params['product_id']){
            if(is_array($params['product_id'])){
                foreach($params['product_id'] as $id){
                    $ids[] = (int)$id;
                }
                $where = ['ProductId' => ['in',$ids]];
            }else{
                $where = ['ProductId' => (int)$params['product_id']];
            }
        }
        return $query->where($where)->select();
    }

    /**
     * 查询产品
     * --sku_id根据skuid搜索
     * --product_id根据产品ID搜索
     * --AddTime 上架时间搜索
     * --ProductStatus 产品状态搜索
     * --FirstCategory 一级分类搜索
     * --SecondCategory 二级分类搜索
     * --ThirdCategory 三级分类搜索
     * @param $params
     * @param $self_id
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectProduct($params,$self_id=null){
        $query = $this->db->name($this->product);
        $where = [
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
            'Skus._id'=> isset($params['sku_id']) ? $params['sku_id'] : null,
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'IsActivity'=> isset($params['activity_id']) ? (int)$params['activity_id'] : null,
            'AddTime'=> isset($params['isNewProduct']) ? ['>=',strtotime('-15 day')] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);
        if(empty($where)){
            return false;
        }

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

        if(isset($params['secondCategory']) && !empty($params['secondCategory'])){
            if(is_array($params['secondCategory'])){
                $where['SecondCategory'] = $params['secondCategory'];
            }else{
                $where['SecondCategory'] = (int)$params['secondCategory'];
            }
        }

        if(isset($params['thirdCategory']) && !empty($params['thirdCategory'])){
            if(is_array($params['thirdCategory'])){
                $where['ThirdCategory'] = $params['thirdCategory'];
            }else{
                $where['ThirdCategory'] = (int)$params['thirdCategory'];
            }
        }

        if(isset($params['fourthCategory']) && !empty($params['fourthCategory'])){
            if(is_array($params['fourthCategory'])){
                $where['FourthCategory'] = $params['fourthCategory'];
            }else{
                $where['FourthCategory'] = (int)$params['fourthCategory'];
            }
        }
        $query->where($where);

        //上架时间排序
        if(isset($params['addTimeSort']) && $params['addTimeSort']){
            $query->order('AddTime','desc');
        }
        //SR排序
        if(isset($params['salesRank']) && $params['salesRank']){
            $query->order('SalesRank','desc');
        }
        //销量排行
        if(isset($params['salesCounts']) && $params['salesCounts']){
            $query->order('SalesCounts','desc');
        }
        // 查询数量
        if(isset($params['limit'])){
            $query->limit($params['limit']);
        }

        //查询字段
        $query->field('_id,BrandId,BrandName,SalesRank,SalesCounts,AddTime,StoreID,StoreName
        ,ShippingFee,CategoryPath,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,RewrittenUrl,Title,ReviewCount,
        AvgRating,VideoCode,SalesUnitType,ColorCount,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,FirstProductImage,IsStaffPick,IsMVP,IsActivity
        Tags
        ');
        return $query->select();
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
        $data = $this->db->name("product_multiLangs")->where(['_id' => (int)$product_id])->field(['Descriptions.en','Title.en','Title.'.$lang,'Descriptions.'.$lang])->find();
        return $data;
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
     * 二三级级分类页面，分类产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function categoryPageLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

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

        if(isset($params['secondCategory']) && !empty($params['secondCategory'])){
            if(is_array($params['secondCategory'])){
                $where['SecondCategory'] = $params['secondCategory'];
            }else{
                $where['SecondCategory'] = (int)$params['secondCategory'];
            }
        }

        if(isset($params['thirdCategory']) && !empty($params['thirdCategory'])){
            if(is_array($params['thirdCategory'])){
                $where['ThirdCategory'] = $params['thirdCategory'];
            }else{
                $where['ThirdCategory'] = (int)$params['thirdCategory'];
            }
        }

        if(isset($params['fourthCategory']) && !empty($params['fourthCategory'])){
            if(is_array($params['fourthCategory'])){
                $where['FourthCategory'] = $params['fourthCategory'];
            }else{
                $where['FourthCategory'] = (int)$params['fourthCategory'];
            }
        }

        //品牌筛选
        if(isset($params['brandId']) && $params['brandId']){
            $brandArray = CommonLib::supportArray($params,'brandId');
            $where['BrandId'] = $brandArray;
        }

        //属性筛选
        if(isset($params['attribute']) && $params['attribute'] && is_array($params['attribute'])){
            foreach($params['attribute'] as $key => $attribute){
                $option = [];
                foreach($attribute as $k => $attr){
                    $option[$k] = 'FilterOptions.'.$attr;
                }
                $filterOptions = implode('|',$option);
                $where[$filterOptions] = '1';
            }
        }

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

        //是否免邮商品
        if(isset($params['freeShipping']) && $params['freeShipping'] == 'true'){
            $where['ShippingFee'] = (double)0;
        }

        //是否是新品筛选
        if(isset($params['newArrivals']) && $params['newArrivals'] == 'true'){
            $where['AddTime'] = ['>=',strtotime('-15 day')];
        }

        //是否是4星以上
        if(isset($params['isFourStar']) && $params['isFourStar'] == 'true'){
            $where['AvgRating'] = ['>=',(double)4];
        }

        //是否是活动筛选
        if(isset($params['isActivity']) && $params['isActivity'] == 'true'){
            $where['IsActivity'] = ['>',0];
        }

        $query->where($where);

        //添加时间排序
        if(isset($params['addTimeSort']) && $params['addTimeSort'] == 'true'){
            $query->order('AddTime','desc');
        }
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
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 基本的产品详情
     * @param $spu
     * @return array
     */
    public function getBaseSpuDetail($spu){

        $data = $this->db->name($this->product)
            ->where(['_id' => (int)$spu])
            ->field('
            _id,StoreID,FirstProductImage,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,SalesCounts,LowPrice,HightPrice,DiscountLowPrice,
            DiscountHightPrice,BrandName,HightDiscount,
            CategoryPath,BrandId,Title,ImageSet,ReviewCount,AvgRating,SalesUnitType,AllowBulkRate,
            ShippingFee,Skus,IsMVP,IsStaffPick,Tags,VideoCode,FilterOptions,RewrittenUrl,Keywords,IsActivity,ProductStatus,WishCount
            ')
            ->find();
        return $data;
    }


    /**
     * 获取产品描述
     * @param $spu
     * @return array
     */
    public function getSpuDescriptions($spu){
        $data = $this->db->name($this->product)
            ->where(['_id' => (int)$spu])
            ->field('_id,Descriptions,PackingList,RewrittenUrl,SalesUnitType')
            ->find();
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
            $query->where(['ProductId' => $params['spu']]);
        }
        if(isset($params['country'])){
            $query->where(['ToCountry' => $params['country']]);
        }
        $data = $query->field('ProductId,ToCountry,ShippingCost,VAT')->find();
        return $data;
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
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title,IsActivity
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * topsellsr 产品列表
     * @param $params
     * @return array
     */
    public function topSellerProductLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
        ];

        //一级类别
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            if(is_array($params['firstCategory'])){
                $where['FirstCategory'] = $params['firstCategory'];
            }else{
                $where['FirstCategory'] = (int)$params['firstCategory'];
            }
        }

        if(isset($params['product_id']) && !empty($params['product_id'])){
            $where['_id'] = $params['product_id'];
        }

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

        $query->where($where);

        //添加时间排序
        if(isset($params['addTimeSort']) && $params['addTimeSort'] == 'true'){
            $query->order('AddTime','desc');
        }
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
            $query->order('LowPrice',$priceSort)->order('HightPrice',$priceSort)->order('DiscountLowPrice',$priceSort);
        }

        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title,IsActivity
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }


    /**
     * 首页基础配置，或低价页面
     * @param $params
     * @return array
     */
    public function underPriceProductLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'FirstCategory'=> isset($params['first_category']) ? (int)$params['first_category'] : null,
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);
        $query->where($where);

        //SR排序
        if(isset($params['salesRank'])){
            $query->order('SalesRank','desc');
        }

        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 首页基础配置查询
     * @param $params
     * @return array
     */
    public function configProductLists($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : 8;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'FirstCategory'=> isset($params['first_category']) ? (int)$params['first_category'] : null,
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);

        $query->where($where);
        //上架时间排序
        if(isset($params['addTimeSort']) && $params['addTimeSort']){
            $query->order('AddTime','desc');
        }
        //查询字段
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }


    /**
     * 产品评论星级详情
     * 产品详情页
     * @param $spu
     * @return array
     */
    public function getSpuReviewsDetail($spu){
        $data = $this->db->name($this->product)
            ->where(['_id' => (int)$spu])
            ->field('_id,ReviewCount,AvgRating,Reviews,Impression')
            ->find();
        return $data;
    }


    /**
     * 推荐产品规则--查询产品
     * --sku_id（id，array）根据skuid搜索
     * --product_id（id，array） 根据产品ID搜索
     * --ProductStatus 产品状态搜索
     * --FirstCategory 一级分类搜索
     * --SecondCategory 二级分类搜索
     * --ThirdCategory 三级分类搜索
     *
     * addTimeSort -- 上架时间排序
     * @param $params
     * @param $self_id = 0 自身ID
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectRecommendProduct($params,$self_id = 0){
        $query = $this->db->name($this->product);

        $where = [
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
            'Skus._id'=> isset($params['sku_id']) ? CommonLib::supportArray($params,'sku_id') : null,
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'BrandId'=> isset($params['brandId']) ? (int)$params['brandId'] : null,
            'IsStaffPick'=> isset($params['isStaffPick']) ? 1 : null,
        ];

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

        //过滤空值
        CommonLib::filterEmptyData($where);
        if(empty($where)){
            return false;
        }
        $query->where($where);

        //价格排序
        if(isset($params['startPrice']) && isset($params['endPrice'])){
            $query->where(['LowPrice'=>[ 'between' , [(double)$params['startPrice'],(double)$params['endPrice']]]]);
        }

        //排序
        if(isset($params['addTimeSort']) && $params['addTimeSort']){
            $query->order('AddTime','desc');
        }
        //SR排序
        if(isset($params['salesRank']) && $params['salesRank']){
            $query->order('SalesRank','desc');
        }
        //销量排行
        if(isset($params['salesCounts']) && $params['salesCounts']){
            $query->order('SalesCounts','desc');
        }
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');

        //取数
        if(isset($params['limit']) && $params['limit']){
            $query->limit($params['limit']);
        }
        return $query->select();
    }

    /**
     * 查询活动产品
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectActivityProduct($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);

        $where = [
            '_id'=> isset($params['product_id']) ? $params['product_id'] : null,
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'IsActivity'=> isset($params['activity_id']) ? (int)$params['activity_id'] : null,
            'FirstCategory'=> isset($params['firstCategory']) ? (int)$params['firstCategory'] : null,
        ];

        //价格排序
        if(isset($params['startPrice']) && isset($params['endPrice'])){
            $query->where(['LowPrice'=>[ 'between' , [(double)$params['startPrice'],(double)$params['endPrice']]]]);
        }

        if(isset($params['addTimeSort']) && $params['addTimeSort']){
            $query->order('AddTime','desc');
        }
        //过滤空值
        CommonLib::filterEmptyData($where);
        if(empty($where)){
            return false;
        }

        $query->where($where);

        //SR排序
        if(isset($params['salesRank']) && $params['salesRank']){
            $query->order('SalesRank','desc');
        }
        //销量排行
        if(isset($params['salesCounts']) && $params['salesCounts']){
            $query->order('SalesCounts','desc');
        }
        //查询字段
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,ColorCount,Title,InventoryActivity,InventoryActivitySalse
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,HightDiscount,FirstCategory,IsActivity,ImageSet
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 查询品牌产品
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectBrandProduct($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);

        if(isset($params['product_id']) && !empty($params['product_id'])){
            $where['_id'] = $params['product_id'];
        }
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]]
        ];

        //一级类别
        if(isset($params['firstCategory']) && !empty($params['firstCategory'])){
            if(is_array($params['firstCategory'])){
                $where['FirstCategory'] = $params['firstCategory'];
            }else{
                $where['FirstCategory'] = (int)$params['firstCategory'];
            }
        }

        //品牌
        if(isset($params['brandId']) && !empty($params['brandId'])){
            $brandArray = CommonLib::supportArray($params,'brandId');
            $where['BrandId'] = $brandArray;
        }else{
            $where['BrandId'] = ['>',1];
        }

        $query->where($where);
        //价格排序
        if(isset($params['priceSort']) && $params['priceSort']){
            $priceSort = $params['priceSort'] == 'true' ? 'asc' : 'desc';
            $query->order('LowPrice',$priceSort);
        }
        //SR排序
        if(isset($params['salesRank']) && $params['salesRank'] == 'true'){
            $query->order('SalesRank','desc');
        }

        //查询字段
        $query->field('_id,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,Discount,ColorCount,Title
        ,FirstProductImage,AvgRating,ReviewCount,ShippingFee,RewrittenUrl,IsStaffPick,IsMVP,Tags,VideoCode,FirstCategory
        ');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }
    
    /**
     	根据SsKUID，获取产品ID
     */
    public function getProductIdBySkuId($params){
    	$where = [
    	'Skus._id'=> isset($params) ? (int)$params : null
    	];

    	$data = $this->db->name($this->product)
    	->where($where)
    	->field('_id')
    	->find();
    	return $data;
    }
    
    /**
     根据SKUIDs，获取产品IDs
     */
    public function getProductIdBySkuIds($params){
    	if(is_array($params)){
    		foreach($params['SkuIds'] as $id){
    			$ids[] = (int)$id;
    		}
    		$where = ['Skus._id' => ['in',$ids]];
    	}
    	
    
    	$data = $this->db->name($this->product)
    	->where($where)
    	->field('_id')
    	->select();
    	return $data;
    }



    /**
     * 获取最新待审核产品ID
     */
    public function getAuditProduct($params){
        $data = $this->db->name($this->product)
            ->where(['ProductStatus'=>0])
            ->order('AddTime','desc')
            ->field('_id')
            ->find();
        return $data;
    }
    
    /**
     * 更新库存
     */
    public function editInventoryBySkuIdArr($params){
    	
    	return true;
    }
    
    public function getAffiliateInfo($params){
    	if(is_array($params)){
    		foreach($params as $id){
    			$ids[] = (int)$id;
    		}
    		$where = ['_id' => ['in',$ids]];
    	}    	     
    	$data = $this->db->name($this->product)
    	->where($where)
    	->field('Commission,CommissionType,FirstCategory,SecondCategory,ThirdCategory,FourthCategory')
    	->select();
    	return $data;
    }

    /**
     * 搜索到的产品，根据一级分类分组
     * @param array $spus spu 数组
     * @param array $group
     * @param $activity_id
     * @return array
     */
    public function groupByProductCategory($spus=null,$group,$activity_id=null){
        $where = [
            'ProductStatus'=> ['$in'=>[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
            'FirstCategory'=> ['$ne'=>0],
        ];
        if(!empty($spus)){
            $where['_id'] = ['$in'=>$spus];
        }
        if(!empty($activity_id)){
            $where['IsActivity'] = (int)$activity_id;
        }
        //原生写法
        $mongo = new Mongo('dx_product');
        return $mongo->group($group,$where);
    }

    /**
     *   获取SKU_ID
     * @param $product_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getSkus($product_id){
        $data = $this->db->name($this->product)
            ->where(['_id'=>(int)$product_id])->field('Skus._id')->find();
        return $data;
    }

    /**
     *
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCartProductList($params){
        $data = $this->db->name($this->product)
            ->where(['_id' => $params['spu']])
            ->field('_id,Skus._id,Skus.SalesPrice,Skus.ActivityInfo,IsMVP,PackingList')->select();
        return $data;
    }


    /**
     * 计算运费使用的产品字段
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getProductByShipingUse($params){
        $data = $this->db->name($this->product)
            ->where(['_id' => (int)$params['spu']])
            ->field('_id,Skus._id,Skus.SalesPrice,Skus.ActivityInfo,IsMVP,PackingList')->find();
        return $data;
    }

}