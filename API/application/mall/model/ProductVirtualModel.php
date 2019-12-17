<?php
namespace app\mall\model;

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
 * 虚拟产品模型
 * @author  zhongning 20191018
 */
class ProductVirtualModel extends Model{

    protected $product = 'product_virtual';
    protected $product_virtual_inventory_record = 'product_virtual_inventory_record';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 查询单个产品
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function findProductVirtual($params){
        $where = array();
        $query = $this->db->name($this->product);
        if(isset($params['product_id']) && $params['product_id']){
            $where['_id'] = (int)$params['product_id'];
        }
        $where['ProductStatus'] = 1;
        $query->field('_id,Title,SalesPrice,Inventory,ManagementFees,Efficiency,ContractTerm,DailyExpected,Descriptions,ElectricityFee,ListPrice,VirtualCurrency');
         return $query->where($where)->find();
    }

    /**
     * 多个产品查询
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectProductVirtual($params){
        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> 1,
        ];
        $query->where($where);
        $query->field('_id,Title,SalesPrice,Inventory,ManagementFees,Efficiency,ContractTerm,DailyExpected,Descriptions,ElectricityFee,ListPrice,VirtualCurrency');
        return $query->select();
    }


    /**
     * 分页查询
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function paginateProductVirtual($params){
        $defaultPage_size = config('paginate.list_rows');
        $page_size = isset($params['page_size']) ? $params['page_size'] : $defaultPage_size;
        $page = isset($params['page']) && !empty($params['page']) ? $params['page'] : 1;

        $query = $this->db->name($this->product);
        $where = [
            'ProductStatus'=> 1,
        ];
        $query->where($where);
        $query->field('_id,Title,SalesPrice,Inventory,ManagementFees,Efficiency,ContractTerm,DailyExpected,Descriptions,ElectricityFee,ListPrice,VirtualCurrency');
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 处理库存和销量
     * @param $params
     * @return bool|false|\PDOStatement|string|\think\Collection
     */
    public function synInventoryAndSalesCount($params){
        //SalesCounts   Inventory
        $rtn = false;
        $date = date('Y-m-d H:i:s');
        $product_id = (int)$params['product_id'];
        $product_nums = (float)$params['product_nums'];
        $order_number = $params['order_number'];
        //处理标识。0-扣减库存增加销量、1-回滚库存和销量
        $flag = isset($params['flag'])?$params['flag']:0;
        if ($flag == 1){
            $product_nums = -$product_nums;
        }
        $product_data = $this->db->name($this->product)->where(['_id'=>$product_id])->field('_id,Inventory,SalesCounts')->find();
        $init_inventory = $product_data['Inventory'];
        $init_sales_counts = isset($product_data['SalesCounts'])?$product_data['SalesCounts']:0;
        $new_inventory = ($init_inventory - $product_nums)>0?(int)($init_inventory - $product_nums):0;
        $new_sales_counts = (int)($product_nums + $init_sales_counts);
        $update_data['Inventory'] = $new_inventory;
        $update_data['SalesCounts'] = $new_sales_counts;
        $update_res = $this->db->name($this->product)->where(['_id'=>$product_id])->update($update_data);
        if ($update_res){
            $rtn = true;
            $record_data['ProductId'] = $product_id;
            $record_data['ProductNums'] = $product_nums;
            $record_data['OrderNumber'] = $order_number;
            $record_data['InitInventory'] = $init_inventory;
            $record_data['NewInventory'] = $new_inventory;
            $record_data['InitSalesCounts'] = $init_sales_counts;
            $record_data['NewSalesCounts'] = $new_sales_counts;
            $record_data['AddTime'] = $date;
            $this->db->name($this->product_virtual_inventory_record)->insert($record_data);
        }
        return $rtn;
    }
}