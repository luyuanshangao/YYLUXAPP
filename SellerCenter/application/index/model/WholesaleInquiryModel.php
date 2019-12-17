<?php
namespace app\index\model;

use app\index\dxcommon\Base;
use think\Db;
use think\Log;
use think\Model;
/**
 * 批发询价模型类
 * Created by tinghu.liu
 * Date: 2018/06/11
 * Time: 15:14
 */
class WholesaleInquiryModel extends Model{
    /**
     * 批发询价表
     * @var string
     */
    protected $table = 'sl_wholesale_inquiry';

    /**
     * 获取列表
     * @param $seller_id 商家ID
     * @param int $shipping_method 送货方式 1-Standard，2-Expedited，3-Other(e.g By own fowarder)
     * @param int $page_size 分页大小
     * @return array
     */
    public function getList($seller_id, $shipping_method=0, $page_size=10){
        $where['seller_id'] = $seller_id;
        if (!empty($shipping_method) && $shipping_method !== 0){
            $where['shipping_method'] = $shipping_method;
        }
        $response = Db::table($this->table)
            ->where($where)
            ->paginate($page_size)->each(function($item, $key){
                $shipping_method_arr = Base::getWholesaleInquiryShippingMethod($item['shipping_method']);
                $item['shipping_method_str'] = isset($shipping_method_arr['name'])?$shipping_method_arr['name']:'Other';
                return $item;
            });
        $page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $page;
        return $data;
    }



}