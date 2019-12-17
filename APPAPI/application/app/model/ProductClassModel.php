<?php
namespace app\app\model;

use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 产品分类
 */
class ProductClassModel extends Model{

    const TableIntegrationClass = 'integration_class';//集成分类--手动配置
    const TableProductClass = 'product_class';//产品分类
    const TableBrandAttribute = 'brand_attribute';//产品品牌属性
    const TableAttribute = 'attribute';//产品属性

    protected $db;
    protected $class_status=1; //产品分类状态：1为开启，2为关闭

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 集成分类列表
     */
    public function integrationClassLists($params){
        $query = $this->db->name(self::TableIntegrationClass);
        //默认语言
        $query->where(['language' => isset($params['language']) ? $params['language'] : 'en']);

        //启用状态
        $query->where(['status' => (int)1]);

        //默认升序排序
        $query->order('sort','asc');

        $query->field(['_id'=>false,'content','content_right','character','classIconfont','classIconImg','classNameHtml']);
        return $query->select();
    }

    /**
     * 集成分类列表
     */
    public function integrationClassName($params){
        $query = $this->db->name(self::TableIntegrationClass);
        //默认语言
        $query->where(['language' => isset($params['language']) ? $params['language'] : 'en']);

        //启用状态
        $query->where(['status' => (int)1]);

        //默认升序排序
        $query->order('sort','asc');

        $query->field(['_id'=>false,'classId','className','language','classNameHtml']);
        return $query->select();
    }

    /**
     * 根据类别ID，获取产品信息
     */
    public function getProductBrand($params){
        $query =  $this->db->name(self::TableBrandAttribute);
        if(is_array($params['class_id'])){
            $query->where(['_id'=> CommonLib::supportArray($params['class_id'])]);
        }else{
            $query->where(['_id'=>(int)$params['class_id']]);
        }
        $query->field(array('product_brand'=>true,'addtime'=>true));

        if(is_array($params['class_id'])){
            return $query->select();
        }
        return $query->find();
    }

    /**
     * 根据类别ID，获取产品信息
     */
    public function selectProductBrand($params){
        //缺少排序
        $result =  $this->db->name(self::TableBrandAttribute)
            ->where(array("_id"=>is_array($params['class_id']) ? $params['class_id'] : (int)$params['class_id']))
            ->field(array('product_brand'=>true,'addtime'=>true));
        return $result->select();
    }

    /**
     * 根据类别ID，获取属性
     */
    public function getProductAttribute($params){
        $query = $this->db->name(self::TableBrandAttribute);
        if(is_array($params['class_id'])){
            $query->where(['_id' => CommonLib::supportArray($params['class_id'])]);
        }else{
            $query->where(['_id' => (int)$params['class_id']]);
        }
        $query->field(array('attribute'=>true,'addtime'=>true));
        if(is_array($params['class_id'])){
            return $query->select();
        }
        return $query->find();
    }

    /**
     * 获取pid下的子分类
     */
    public function getClassByPid($params){
        $query = $this->db->name(self::TableProductClass);

        if(is_array($params['pid'])){
            if(empty($params['pid'])){
                return array();
            }
            $query->where(['pid' => $params['pid']]);
        }else{
            $query->where(['pid' => (int)$params['pid']]);
        }

        $query->where(['status' => (int)$this->class_status,'type'=>1]);

        //默认升序排序
        $query->order('sort','asc');

        $query->field(['_id'=>false,'id','pid','title_en','Common.'.$params['lang'],'common.en','type',
            'rewritten_url','id_path','pdc_ids','level','isleaf','classimg']);
        return $query->select();
    }

    /**
     * 获取单个类别详情
     * @param $where
     * @param string $lang
     * @return array|false|\PDOStatement|string|Model
     */
    public function getClassDetail($where,$lang=DEFAULT_LANG){
        $where['status'] = 1;
        $query = $this->db->name(self::TableProductClass)->where($where)
            ->field(['_id'=>false,'id','pid','title_en','common.'.$lang,'common.en','rewritten_url','type',
                'id_path','pdc_ids','level'])->find();

        return $query;
    }

    /**
     * 获取产品类别详情
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function selectClass($params){
        $query = $this->db->name(self::TableProductClass);
        $where = [
            'id'=> isset($params['class_id']) ? $params['class_id'] : null,
            'status'=> (int)$this->class_status,
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
        $data = $query->field(['_id'=>false,'id','pid','title_en','common.'.$params['lang'],'common.en','type',
            'rewritten_url','id_path','pdc_ids','level'])->select();

        return $data;
    }


    /**
     * 获取空的映射列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getEmptyPdcids(){
        return $this->db->name(self::TableProductClass)->where(['pdc_ids'=>['=',array()],'status'=>1])->field(['id','type','level','pid','_id'=>false])->select();
    }

    /**
     * 获取映射
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getPdcids($params){
        return $this->db->name(self::TableProductClass)->where(['pid'=>(int)$params['pid']])->field(['id','pdc_ids','_id'=>false])->select();
    }

    /**
     * 获取空的映射列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function gePDCPdcids(){
        return $this->db->name(self::TableProductClass)->where(['pdc_ids'=>['ne',array()],'status'=>1,'type'=>2])
            ->field(['id','type','level','pid','_id'=>false,'pdc_ids'])->select();
    }


    /**
     * 获取空的映射列表
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getErpClassCloseStatus(){
        return $this->db->name(self::TableProductClass)->where(['status'=>0,'type'=>1])->field(['id','_id'=>false])->select();
    }

    /**
     * 更新类别
     * @param $params
     * @param $where
     * @return int|string
     * @throws Exception
     */
    public function updateClass($params,$where){
        if(isset($params['pdc_ids'])){
            $params['pdc_ids'] = array_values($params['pdc_ids']);
        }
        return $this->db->name(self::TableProductClass)->where($where)->update($params);
    }

    /**
     * 根据分类数组ID获取数据
     * @param array $id_arr
     * @param int $class_type 类别类型：1-erp数据，2-pdc数据
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDataWithIdArray(array $id_arr, $class_type = 1){
        $id_str = implode(',', $id_arr);
        foreach ($id_arr as $val){
            $id_arr_new[] = (int)$val;
        }
        return $this->db->name(self::TableProductClass)->where('id', 'in', $id_arr_new)->where(['type'=>$class_type])->select();
    }

    /**
     * 根据分类ID获取对应的ERP分类ID
     * @param array $params
     * @return array|mixed
     */
    public function getErpClassIdByClassId(array $params){
        $rtn = [];
        $class_id = (int)$params['class_id'];
        $classData = $this->getClassDetail(['id'=>(int)$class_id]);
        if(isset($classData['type']) && $classData['type'] == 1){
            //如果是erp分类则直接返回
            $rtn[] = $class_id;
        }else{
            //如果不是erp分类（即PDC分类），则返回映射的erp分类数据
            if(isset($classData['pdc_ids']) && !empty($classData['pdc_ids'])){
                $rtn = $classData['pdc_ids'];
            }
        }
        return $rtn;
    }

    /**
     * 根据类别ID，获取属性
     */
    public function findAttributeByWhere($where,$field){
        return $this->db->name(self::TableBrandAttribute)->where($where)->field($field)->find();
    }
}