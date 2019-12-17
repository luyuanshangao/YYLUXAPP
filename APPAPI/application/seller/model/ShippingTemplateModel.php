<?php
namespace app\seller\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 开发：钟宁
 * 功能：运费模板
 * 时间：2018-08-07
 */
class ShippingTemplateModel extends Model{

    public $page_size = 20;
    public $page = 1;
    protected $table = 'sl_shipping_template';

	public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }

    /**
     * 问答列表
     * @param $params
     * @return array
     */
    public function shippingTemplateList($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : $this->page_size;
        $page = isset($params['page']) ? $params['page'] : $this->page;

        $query = $this->db->table($this->table);

        $where['is_delete'] = 0;
        if(isset($params['seller_id']) && !empty($params['seller_id'])){
            $where['seller_id'] = $params['seller_id'];
        }

        $query->where($where);

        $query->field(['template_id','seller_id','template_name','is_charged','addtime']);
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page]);
        return $ret->toArray();
    }
}