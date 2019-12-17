<?php
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use think\Model;
use think\Db;
/**
 * affiliate订单模型
 * @author tinghu.liu 2018/06/02
 * @version v1.0
 */
class AffiliateOrder extends Model
{
    private  $db = '';
    protected $table = 'dx_affiliate_order';
    protected $table_item = 'dx_affiliate_order_item';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /**
     * 根据affiliate订单ID获取affiliate订单详情
     * @param $affiliate_order_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderInfoById($affiliate_order_id){
        $data = $this->db->table($this->table)->where('affiliate_order_id', '=', $affiliate_order_id)->find();
        //获取订单详情数据
        $item_data = $this->getOrderItemByAffiliateOrderId($affiliate_order_id);
        foreach ($item_data as &$val) {
            //佣金比例
            $val['commission_rate'] *= 100;
            //结算金额
            $val['settlement_price'] = round($val['total_amount'] - $val['commission_price'], 2);
        }
        $data['item_data'] = $item_data;
        //佣金金额(每个产品佣金金额相加)
        $commission_price = 0;
        foreach ($item_data as $info){
            $commission_price += $info['commission_price'];
        }
        $data['commission_price'] = $commission_price;
        //结算金额
        $data['settlement_price'] = round($data['price'] - $commission_price, 2);
        //获取订单状态
        $order_status = CommonLib::getOrderStatus($data['order_status']);
        $data['order_status_str'] = $order_status['name'];
        //结算状态：1 未生效、2 已结算、3 已提现
        switch ($data['settlement_status']){
            case 1:
                $settlement_status_str = '未生效';
                break;
            case 1:
                $settlement_status_str = '已结算';
                break;
            case 1:
                $settlement_status_str = '已提现';
                break;
            default:
                $settlement_status_str = '-';
                break;
        }
        $data['settlement_status_str'] = $settlement_status_str;
        return $data;
    }

    /**
     * 获取订单列表【分页】
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderList(array $params){
        $base_api = new BaseApi();
        $query = $this->db->table($this->table);
        //是否去掉未激活的数据？？ TODO
        //查询条件store_id
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $query->where('store_id', '=', $params['store_id']);
        }
        //订单编码
        if (isset($params['order_number']) && !empty($params['order_number'])){
            $query->where('order_number', '=', $params['order_number']);
        }
        //订单状态
        if (isset($params['order_status']) && !empty($params['order_status'])){
            $query->where('order_status', '=', $params['order_status']);
        }
        //生成时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('add_time', '>=', $params['create_on_start']);
            $query->where('add_time', '<=', $params['create_on_end']);
        }
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;

        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])->each(function ($item, $key) use ($base_api){
            //获取订单详情数据
            $item_data = $this->getOrderItemByAffiliateOrderId($item['affiliate_order_id']);
            $item['item_data'] = $item_data;
            //佣金金额(每个产品佣金金额相加)
            $commission_price = 0;
            foreach ($item_data as $info){
                $commission_price += $info['commission_price'];
            }
            $item['commission_price'] = $commission_price;
            //结算金额
            $item['settlement_price'] = round($item['price'] - $commission_price, 2);
            //获取订单状态
            $order_status = CommonLib::getOrderStatus($item['order_status']);
            $item['order_status_str'] = $order_status['name'];
            //结算状态：1 未生效、2 已结算、3 已提现
            switch ($item['settlement_status']){
                case 1:
                    $settlement_status_str = '未生效';
                    break;
                case 1:
                    $settlement_status_str = '已结算';
                    break;
                case 1:
                    $settlement_status_str = '已提现';
                    break;
                default:
                    $settlement_status_str = '-';
                    break;
            }
            $item['settlement_status_str'] = $settlement_status_str;
            return $item;
        });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 根据affiliate订单ID获取订单详情数据
     * @param $affiliate_order_id affiliate订单ID
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderItemByAffiliateOrderId($affiliate_order_id){
        $where = ['affiliate_order_id'=>$affiliate_order_id];
        $data = $this->db->table($this->table_item)->where($where)->select();
        return $data;
    }


}
