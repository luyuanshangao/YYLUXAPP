<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 集成分类
 */
class IntegrationCategoryModel extends Model{

    const TableIntegrationClass = 'integration_class';
    const TableProductClass = 'product_class';

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
    public function lists($params){
        $query = $this->db->name(self::TableIntegrationClass);
        //默认语言
        $query->where(['language' => isset($params['language']) ? $params['language'] : 'EN']);

        //启用状态
        $query->where(['status' => (int)1]);

        //默认升序排序
        $query->order('sort','asc');

        return $query->select();
    }

    /**
     * 获取pid下的子分类
     */
    public function getClass($params){
        $query = $this->db->name(self::TableProductClass);

        $query->where(['pid' => (int)$params['pid'],'status' => (int)$this->class_status]);

        //默认升序排序
        $query->order('sort','asc');

        return $query->select();
    }
}