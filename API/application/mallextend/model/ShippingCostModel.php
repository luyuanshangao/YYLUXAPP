<?php
namespace app\mallextend\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

//产品运费模板
class ShippingCostModel extends Model{

    protected $db;
    protected $table = 'shipping_cost';
    protected $table_product = 'product';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 新增产品运费模板
     * @param arrar $params
     * @return true/false
     */
    public function add($params){
        $ret = $this->db->name('shipping_cost')->insert($params);
        //$ret = $this->db->table('new_dx_shipping_cost')->insert($params);
        if(!$ret){
            return false;
        }
        return true;
    }

    public function getShippingCost($params){
        $ret = $this->db->name('shipping_cost')->where(['ProductId' => $params['id']])->select();
        //$ret = $this->db->table('new_dx_shipping_cost')->where(['ProductId' => $params['id']])->select();
        if(!$ret){
            return false;
        }
        return $ret;
    }

    public function getShippingCostV2($params){
        $ret = $this->db->name('shipping_cost')->where(['ProductId' => $params['id']])->find();
        //$ret = $this->db->table('new_dx_shipping_cost')->where(['ProductId' => $params['id']])->select();
        if(!$ret){
            return false;
        }
        return $ret;
    }

    public function del($id){
        $ret = $this->db->name('shipping_cost')->where(['ProductId' => $id])->delete();
        //$ret = $this->db->table('new_dx_shipping_cost')->where(['ProductId' => $id])->delete();
        if(!$ret){
            return false;
        }
        return true;
    }

    /**
     * 根据条件删除数据【运费模板修改定时任务专用】
     * @param array $where
     * @return int
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function deleteByWhereForEditorST(array $where){
        $query = $this->db->name($this->table);
        //产品ID
        if(isset($where['product_id']) && !empty($where['product_id'])){
            $query->where('ProductId', '=', $where['product_id']);
        }
        //运费模板ID
        if(isset($where['template_id']) && !empty($where['template_id'])){
            $query->where('TempletID', '=', $where['template_id']);
        }
        if(isset($where['time']) && !empty($where['time'])){
            $query->where('AddTime', '<', $where['time']);
        }
        return $query->delete();
    }

    /**
     * 根据条件获取数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getDataByWhere(array $where){
        $query = $this->db->name($this->table);
        //运费模板ID
        if(isset($where['template_id']) && !empty($where['template_id'])){
            $query->where('TempletID', '=', $where['template_id']);
        }
        return $query->select();
    }

    /**
     * 根据条件获取数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductDataByWhere(array $where){
        $query = $this->db->name($this->table_product);
        //运费模板ID
        if(isset($where['template_id']) && !empty($where['template_id'])){
            $query->where('LogisticsTemplateId', '=', (int)$where['template_id']);
        }
        $query->where('ProductStatus', 'in', [1,5]);
        return $query->select();
    }

    /**
     * 根据条件获取运费数据【运费模板更新定时任务使用】
     * 注：将之前商城运费模板cost对应的数据（ProductId == $params["product_id"] && TempletID != $params["template_id"]）写入日志表（需要新增）记录下来；
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataByWhereForUpdate(array $params){
        $query = $this->db->name('shipping_cost');
        //产品ID
        if(isset($params['product_id']) && !empty($params['product_id'])){
            $query->where('ProductId', '=', $params['product_id']);
        }
        //运费模板ID
        if(isset($params['template_id']) && !empty($params['template_id'])){
            $query->where('TempletID', '<>', $params['template_id']);
        }
        return $query->select();
    }

    public function getDataByWhereForSTUpdate(array $params){
        $query = $this->db->name('shipping_cost');
        //产品ID
        if(isset($params['product_id']) && !empty($params['product_id'])){
            $query->where('ProductId', '=', $params['product_id']);
        }
        //运费模板ID
        if(isset($params['template_id']) && !empty($params['template_id'])){
            $query->where('TempletID', '=', $params['template_id']);
        }
        return $query->select();
    }

    /**
     * 根据条件删除运费数据【运费模板更新定时任务使用】
     * 注：再删除之前商城运费模板cost数据（ProductId == $params["product_id"] && TempletID != $params["template_id"]）
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function deleteDataByWhereForUpdate(array $params){
        $query = $this->db->name('shipping_cost');
        //产品ID
        if(isset($params['product_id']) && !empty($params['product_id'])){
            $query->where('ProductId', '=', $params['product_id']);
        }
        //运费模板ID
        if(isset($params['template_id']) && !empty($params['template_id'])){
            $query->where('TempletID', '<>', $params['template_id']);
        }
        return $query->delete();
    }

    /**
     * 批量新增shipping_cost_update_back表数据
     * @param $all_data 要新增的数据
     * @return int|string
     */
    public function addAllUpdataBackData($all_data){
        return $this->db->name('shipping_cost_update_back')->insertAll($all_data);
    }


    public function findShipping(array $params){
        $query = $this->db->name('shipping_cost');
        //产品ID
        if(isset($params['product_id']) && !empty($params['product_id'])){
            $query->where('ProductId', '=', (string)$params['product_id']);
        }

        //国家
        if(isset($params['to_country']) && !empty($params['to_country'])){
            $query->where('ToCountry', '=', $params['to_country']);
        }
        $query->field(['_id'=>false,'ProductId','ToCountry','ShippingCost']);
        $data = $query->find();
        return $data;
    }

    public function updateShipping($where,$update){
        $query = $this->db->name('shipping_cost')->where($where)->update($update);
        return $query;
    }

    public function selectShipping(array $params){
        $query = $this->db->name('shipping_cost');
        $where['ProductId'] = (string)$params['product_id'];
        $where['ToCountry'] = ['like',' '];
        $query->where($where);
        $query->field(['_id'=>true,'ToCountry']);
        $data = $query->select();
        return $data;
    }

    /**
     * 分页查询
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function paginateShipping($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 10;
        $page = isset($params['page']) ? $params['page'] : 1;

        $query = $this->db->name('shipping_cost');
        //搜索字段
        $query->field(['_id','ProductId']);

        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    public function delshippingid($id){
        $ret = $this->db->name('shipping_cost')->where(['_id' => $id])->delete();
        if(!$ret){
            return false;
        }
        return true;
    }

}