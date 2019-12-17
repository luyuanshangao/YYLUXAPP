<?php
namespace app\index\model;
use app\index\dxcommon\Base;
use think\Db;
use think\Log;
use think\Model;

/**
 * 运费模板模型
 * Created by tinghu.liu
 * Date: 2018/04/03
 * Time: 16:06
 */

class LogisticsManagementModel extends Model{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'sl_logistics_management';
    protected $table_weight = 'sl_logistics_weight';

    /**
     * 根据运费模板类型ID获取对应国家
     * @param $shippingServiceID 运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
     * @param int $is_charged 是否带电：1-为普货，2-为纯点，3-为带电
     * @param int $status 状态：0-逻辑删除，1-正常
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCountryDataByShippingServiceID($shippingServiceID, $is_charged=0, $status=1){
        $where = ['shippingServiceID'=>$shippingServiceID, 'status'=>$status];
        if (!empty($is_charged) && $is_charged != 0){
            $where['isCharged'] = $is_charged;
        }
        return Db::table($this->table)->where($where)->field('id, countryENName, countryCode, areaName, shippingServiceID, shippingServiceText, freight, time_slot, first_weight, first_freight,isCharged,calculation_formula')->select();
    }

    /**
     * 根据运费模板类型ID、国家简码获取数据
     * @param $shippingServiceID 运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
     * @param $countryCode 国家简码
     * @param $status 状态：0-逻辑删除，1-正常
     * @param int $isCharged 是否带电:1-为普货，2-为纯电，3-为带电
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataByShippingServiceIDAndCountryCode($shippingServiceID, $countryCode, $status=1, $isCharged=0){
        $where = ['shippingServiceID'=>$shippingServiceID, 'countryCode'=>$countryCode, 'status'=>$status];
        if ($isCharged != 0){
            $where['isCharged'] = $isCharged;
        }
        return Db::table($this->table)->where($where)->select();
    }

    /**
     * 根据条件获取运费模板数据
     * @param $shippingServiceID 运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
     * @param $countryCode 国家简码
     * @param $shippingServiceText 物流服务名称
     * @param $isCharged 是否带电:1-为普货，2-为纯电，3-为带电
     * @param $status 状态：0-逻辑删除，1-正常
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getDataByShippingServiceIDAndCountryCode_new($shippingServiceID, $countryCode, $shippingServiceText, $isCharged, $status=1){
        return Db::table($this->table)->where(['shippingServiceID'=>$shippingServiceID, 'countryCode'=>$countryCode, 'shippingServiceText'=>$shippingServiceText, 'isCharged'=>$isCharged,'status'=>$status])->select();
    }

    /**
     * 根据物流id获取单条信息
     * @param $id 物流id
     * @return string
     */
    public function getInfoById($id){
        $data = Db::table($this->table)->where(['id'=>$id])->find();
        //拼装运费公式参数
        if (!empty($data)){
            $data['freight_data'] = $this->getWeightDataByLogisticsId($id);
        }
        return $data;
    }

    /**
     * 根据物流表id获取运费参数信息
     * @param $logistics_id 物流表id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getWeightDataByLogisticsId($logistics_id){
        return Db::table($this->table_weight)->where(['logistics_id'=>$logistics_id])->field('add_weight,add_freight,start_weight,end_weight')->select();
    }

}