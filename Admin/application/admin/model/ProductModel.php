<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;

/**
 * 产品模型
 * @author
 * @version  zhongning
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

    protected $product = 'dx_product';
    protected $product_lang = 'dx_product_multiLangs';
    protected $attr_lang ='product_attr_multiLangs';
    protected $custom_attr_lang ='product_customAttr_multiLangs';
    protected $custom_attr_lang_new ='product_attr_multiLangs_new';
    protected $unitType_lang ='product_unit_type_multiLangs';
    protected $product_regions_price ='dx_product_regions_price';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongo');
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
            $where['_id'] = (int)$params['product_id'];
        }
        if(isset($params['sku_id']) && $params['sku_id']){
            $where['Skus._id'] = (int)$params['sku_id'];
        }
        if(isset($params['sku_code']) && $params['sku_code']){
            $where['Skus.Code'] = $params['sku_code'];
        }
        if(isset($params['activity_id']) && !empty($params['activity_id'])){
            $where['IsActivity'] = (int)$params['activity_id'];
        }
        if(isset($params['activitySalse']) && !empty($params['activitySalse'])){
            $query->order('InventoryActivitySalse','desc');
        }
        if(empty($where)){
            return array();
        }
        $where['ProductStatus'] = ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]];

        $query->field('
            _id,FirstProductImage,FirstCategory,SecondCategory,ThirdCategory,SalesCounts,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,
            Discount,CategoryPath,BrandId,Title,ImageSet,ReviewCount,AvgRating,StoreName,InventoryActivity,InventoryActivitySalse,ColorCount,
            IsOnSale,IsCoupon,SalesUnitType,DeliveryEndDays,AllowBulkRate,ShippingFee,Skus,IsMVP,IsStaffPick,Tags,VideoCode,IsActivity,
            FilterOptions,PackingList,RewrittenUrl,Keywords,StoreID,BrandName,HSCode,ProductStatus,HightDiscount,
            IsHistory,LogisticsLimit
            ');
         return $query->where($where)->find();
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
            $query->whereOr(['FirstCategory'=>['in',$params['lastCategory']]])->whereOr(['SecondCategory' => ['in',$params['lastCategory']]])
                ->whereOr(['ThirdCategory' => ['in',$params['lastCategory']]])->whereOr(['FourthCategory' => ['in',$params['lastCategory']]]);
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
        $query->field('_id,CategoryPath,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,RewrittenUrl,Title,Descriptions,ImageSet
        ,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,FirstProductImage');
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
        $data = $this->db->name("product_multiLangs")->where(['_id' => (int)$product_id])->field(['Descriptions.en','Title.en','Title.'.$lang,'Descriptions.'.$lang,'SalesAttrs'])->find();
        return $data;
    }

    /**
     * 产品计量单位多语言
     * @param $params
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductUnitTypeLang($params){
        return $this->db->name($this->unitType_lang)->where(['title_en' => $params['title']])
            ->field(['_id'=>false,'title_en','Common.'.$params['lang']])->find();
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
        $data = $this->db->name($this->custom_attr_lang)->where(['AttributeDefId' => (int)$attr_id,'dx_product_id'=>(int)$product_id])
            ->field(['_id'=>false,'Title','Options.'.$key,'dx_product_id'])->find();
        return $data;
    }

    /**
     * 产品属性多语言
     * 颜色 --对应的ID --title对应翻译
     * 红色 --对应ID -- Option对应翻译
     * @param $attr_id
     * @param $key
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function getProductCustomAttrMultiLangsNew($attr_id,$key){
        $data = $this->db->name($this->custom_attr_lang_new)->where(['_id' => (int)$attr_id])
            ->field(['_id'=>false,'Title','Options.'.$key])->find();
        return $data;
    }

    /**
     * 产品列表
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function paginateProductList($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 10;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> ['in',[self::PRODUCT_STATUS_SUCCESS,self::PRODUCT_STATUS_SUCCESS_UPDATE]],
        ];

        if(isset($params['lastCategory']) && !empty($params['lastCategory'])){
            $query->whereOr(['FirstCategory'=>['in',$params['lastCategory']]])->whereOr(['SecondCategory' => ['in',$params['lastCategory']]])
                ->whereOr(['ThirdCategory' => ['in',$params['lastCategory']]])->whereOr(['FourthCategory' => ['in',$params['lastCategory']]]);
        }

        $query->where($where);

        $query->field('_id,CategoryPath,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,RewrittenUrl,Title,Descriptions,ImageSet
        ,LowPrice,HightPrice,DiscountLowPrice,DiscountHightPrice,FirstProductImage');

        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
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
     * 获取产品类别详情
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function selectClass($params){
        $query = $this->db->table('dx_product_class');
        $where = [
            'id'=> isset($params['class_id']) ? $params['class_id'] : null,
            'status'=> 1,
            'type'=> isset($params['type']) ? (int)$params['type'] : null,
        ];
        //过滤空值
        CommonLib::filterNullValue($where);
        $query->where($where);
        if(isset($params['pid'])){
            $query->where(['pid' => (int)$params['pid']]);
        }
        //sort排序
        $query->order('sort','asc');
        $data = $query->field(['_id'=>false,'id','pid','title_en','Common.'.$params['lang'],'Common.en','type',
            'rewritten_url','id_path','pdc_ids','level'])->select();
        return $data;
    }

    /**
     * 获取单个类别详情
     * @param $where
     * @param string $lang
     * @return array|false|\PDOStatement|string|Model
     */
    public function getClassDetail($where,$lang='en'){
        $where['status'] = 1;
        $query = $this->db->table('dx_product_class')->where($where)
            ->field(['_id'=>false,'id','pid','title_en','Common.'.$lang,'Common.en','rewritten_url','type',
                'id_path','pdc_ids','level'])->find();

        return $query;
    }

}