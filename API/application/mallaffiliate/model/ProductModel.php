<?php
namespace app\mallaffiliate\model;

use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use think\mongo\Query;

/**
 * 开发：钟宁
 * 功能：affiliate 产品模型
 * 时间：2018-06-08
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
    protected $S_CONFIG ='sys_config';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 查询产品
     * --spu int 根据产品ID搜索
     * categoryPath
     * brandId
     * language
     * productStatus
     * discountAbove
     * discountBelow
     * priceAbove
     * priceBelow
     * pageSize
     * pageIndex
     * orderByDate -- 上架时间排序
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectProduct($params){
        //每页最大50条数据，默认为20条
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] >50 ? 50 : (int)$params['pageSize'] : 20;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> isset($params['productStatus']) && !empty($params['productStatus']) ? (int)$params['productStatus'] : null,
            'CategoryPath'=> isset($params['categoryPath']) && !empty($params['categoryPath']) ? $params['categoryPath'] : null,
            'brandId'=> isset($params['brandId']) && !empty($params['brandId']) ? (int)$params['brandId'] : null,
            'DiscountLowPrice'=> isset($params['discountAbove']) && !empty($params['discountAbove']) ? ['gte',(double)$params['discountAbove']] : null,
            'DiscountHightPrice'=> isset($params['discountBelow']) && !empty($params['discountBelow']) ? ['lte',(double)$params['discountBelow']] : null,
            'LowPrice'=> isset($params['priceAbove']) && !empty($params['priceAbove']) ? ['gte',(double)$params['priceAbove']] : null,
            'HightPrice'=> isset($params['priceBelow']) && !empty($params['priceBelow']) ? ['lte',(double)$params['priceBelow']] : null,
            'StoreID'=> !empty($params['store_id']) ? (int)$params['store_id'] : null,
        ];

        if(!empty($params['store_id'])){
            if(is_array($params['store_id'])){
                $where['StoreID'] = $params['store_id'];
            }else{
                $where['StoreID'] = (int)$params['store_id'];
            }
        }
        //过滤空值
        CommonLib::filterNullValue($where);
        if(isset($params['spus']) && !empty($params['spus'])){
            if(is_array($params['spus'])){
                $where['_id'] = CommonLib::supportArray($params['spus']);
            }else{
                $where['_id'] = (int)$params['spus'];
            }
        }
        if(isset($params['skus']) && !empty($params['skus'])){
            if(is_array($params['skus'])){
                $where['Skus.Code'] = ['in',$params['skus']];
            }else{
                $where['Skus.Code'] = (string)$params['skus'];
            }
        }

        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $query->whereOr(['FirstCategory'=>(int)$params['lastCategory']])->whereOr(['SecondCategory'=>(int)$params['lastCategory']])
                ->whereOr(['ThirdCategory'=>(int)$params['lastCategory']])->whereOr(['FourthCategory'=>(int)$params['lastCategory']]);
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
        //排序
        if(!empty($params['orderByDate'])){
            $query->order('AddTime','desc');
        }
        if(!empty($params['orderByID'])){
            $query->order('_id','desc');
        }
        $query->field(['_id','CategoryPath','Title','BrandId',"BrandName",'ImageSet','AddTime','LowPrice','HightPrice','HightDiscount',
            'DiscountLowPrice','DiscountHightPrice','Skus._id','Skus.Code','Skus.SalesPrice','Skus.SalesAttrs','Skus.Inventory'
            ,'ProductStatus','RewrittenUrl','ImageSet','Descriptions','HightDiscount','HighListPrice','ListPriceDiscount','LowListPrice',
            'PackingList.Weight','PackingList.Dimensions'
        ]);
        return $query->limit($pageSize)->page($pageIndex)->select();
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
     * 查询产品分页
     * --spu int 根据产品ID搜索
     * categoryPath
     * brandId
     * language
     * productStatus
     * discountAbove
     * discountBelow
     * priceAbove
     * priceBelow
     * pageSize
     * pageIndex
     * orderByDate -- 上架时间排序
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function paginateProduct($params){
        //每页最大50条数据，默认为20条
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] >50 ? 50 : (int)$params['pageSize'] : 20;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> isset($params['productStatus']) && !empty($params['productStatus']) ? (int)$params['productStatus'] : null,
            'CategoryPath'=> isset($params['categoryPath']) && !empty($params['categoryPath']) ? $params['categoryPath'] : null,
            'brandId'=> isset($params['brandId']) && !empty($params['brandId']) ? (int)$params['brandId'] : null,
            'DiscountLowPrice'=> isset($params['discountAbove']) && !empty($params['discountAbove']) ? ['gte',(double)$params['discountAbove']] : null,
            'DiscountHightPrice'=> isset($params['discountBelow']) && !empty($params['discountBelow']) ? ['lte',(double)$params['discountBelow']] : null,
            'LowPrice'=> isset($params['priceAbove']) && !empty($params['priceAbove']) ? ['gte',(double)$params['priceAbove']] : null,
            'HightPrice'=> isset($params['priceBelow']) && !empty($params['priceBelow']) ? ['lte',(double)$params['priceBelow']] : null,
            'StoreID'=> !empty($params['store_id']) ? (int)$params['store_id'] : null,
        ];

        if(!empty($params['store_id'])){
            if(is_array($params['store_id'])){
                $where['StoreID'] = $params['store_id'];
            }else{
                $where['StoreID'] = (int)$params['store_id'];
            }
        }
        //过滤空值
        CommonLib::filterNullValue($where);
        if(isset($params['spus']) && !empty($params['spus'])){
            if(is_array($params['spus'])){
                $where['_id'] = CommonLib::supportArray($params['spus']);
            }else{
                $where['_id'] = (int)$params['spus'];
            }
        }
        if(isset($params['skus']) && !empty($params['skus'])){
            if(is_array($params['skus'])){
                $where['Skus._id'] = CommonLib::supportArray($params['skus']);
            }else{
                $where['Skus._id'] = (int)$params['skus'];
            }
        }

        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $query->whereOr(['FirstCategory'=>(int)$params['lastCategory']])->whereOr(['SecondCategory'=>(int)$params['lastCategory']])
                ->whereOr(['ThirdCategory'=>(int)$params['lastCategory']])->whereOr(['FourthCategory'=>(int)$params['lastCategory']]);
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
        //排序
        if(!empty($params['orderByDate'])){
            $query->order('AddTime','desc');
        }
        if(!empty($params['orderByID'])){
            $query->order('_id','desc');
        }
        $query->field(['_id','CategoryPath','Title','BrandId',"BrandName",'ImageSet','AddTime','LowPrice','HightPrice','HightDiscount',
            'DiscountLowPrice','DiscountHightPrice','Skus._id','Skus.Code','Skus.SalesPrice','Skus.SalesAttrs','Skus.Inventory'
            ,'ProductStatus','RewrittenUrl','ImageSet','Descriptions','HightDiscount','HighListPrice','ListPriceDiscount','LowListPrice',
            'PackingList.Weight','PackingList.Dimensions'
        ]);
        return $query->paginate($pageSize,false,['type' => 'Bootstrap', 'page' => $pageIndex])->toArray();
    }

    /**
     * 查询产品
     * --spu int 根据产品ID搜索
     * categoryPath
     * brandId
     * language
     * productStatus
     * discountAbove
     * discountBelow
     * priceAbove
     * priceBelow
     * pageSize
     * pageIndex
     * orderByDate -- 上架时间排序
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectProductSku($params){
        $query = $this->db->name($this->product);

        if(isset($params['spus']) && !empty($params['spus'])){
            if(is_array($params['spus'])){
                $where['_id'] = CommonLib::supportArray($params['spus']);
            }else{
                $where['_id'] = (int)$params['spus'];
            }
            $query->where($where);
        }
        $query->field(['_id','Skus.Code','Skus.SalesPrice']);
        $result = $query->select();
        return $result;
    }

    //字典数据的获取
    public function dictionariesQuery($val){
        $PayemtMethod = $this->db->name($this->S_CONFIG)->where(['ConfigName'=>$val])->cache(6000)->find();
        $data = explode(";",$PayemtMethod['ConfigValue']);
        $list = array();
        foreach ($data as $key => $value) {
            if(!empty($value)){
                $array = explode(":",$value);
                $list[$array[0]]=!empty($array[1])?$array[1]:'';
            }
        }
        return $list;
    }
}