<?php
namespace app\index\model;
use app\index\dxcommon\Base;
use think\Db;
use think\Exception;
use think\Log;
use think\Model;

/**
 * 运费模板模型
 * Created by tinghu.liu
 * Date: 2018/04/02
 * Time: 17:57
 */

class ShippingTemplateModel extends Model{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'sl_shipping_template';
    protected $table_type = 'sl_shipping_tamplate_type';
    protected $table_country = 'sl_shipping_tamplate_country';
    protected $table_country_freight = 'sl_shipping_tamplate_country_freight';
    /**
     * 编辑运费模板时的模板ID
     * @var
     */
    protected $editor_template_id;

    /**
     * 新增运费模板数据
     * @param $seller_id
     * @param $data 模板表sl_shipping_template数据
     * @return int|string
     */
    public function insertData($seller_id, $data){
        $template_id = null;
        // start
        Db::startTrans();
        try{
            $add_time = time();
            /** 写入sl_shipping_template表 **/
            $insert_data['seller_id'] = $seller_id;
            $insert_data['template_name'] = $data['template_name'];
            $insert_data['delivery_area'] = $data['delivery_area'];
            $insert_data['is_charged'] = $data['is_charged'];
            $insert_data['addtime'] = $add_time;
            Db::table($this->table)->insert($insert_data);
            $template_id = Db::table($this->table)->getLastInsID();//返回新增数据的自增主键
            /** 写入sl_shipping_tamplate_type扩展表 **/
            $type_data = $data['data'];
            foreach ($type_data as $type_info){
                //shipping_type = 1 ,才有
                $discount = 0;
                //运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
                $template_type = $type_info['logisticsServices'];
                //运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
                $shipping_type = $type_info['freightSettings'];
                if ($shipping_type == 1){
                    $discount = $type_info['relief'];
                }
                $insert_data_type['template_id'] = $template_id;
                $insert_data_type['template_type'] = $template_type;
                $insert_data_type['shipping_type'] = $shipping_type;
                $insert_data_type['discount'] = $discount;
                $insert_data_type['addtime'] = $add_time;
                Db::table($this->table_type)->insert($insert_data_type);
                $type_id = Db::table($this->table_type)->getLastInsID();//获取主键ID
                //如果选择“自定义运费”则记录相应的运费类型对应的国家、送货类型信息
                /** 写入sl_shipping_tamplate_country扩展表 **/
                if ($shipping_type == 3){
                    if ($template_type == 40){ //专线数据格式不一样，需要特殊处理
                        $country_data = $type_info['country_data'][0]['country'];
                        foreach ($country_data as $country_info){
                            $delivery_type = $country_info['freightType'];
                            $shipping_service_text = isset($country_info['shipping_service_text'])?$country_info['shipping_service_text']:'';
                            //分解专线数据，如果存在国家对应几个专线的情况，则需要分开存储
                            $insert_data_country['type_id'] = $type_id;
                            $insert_data_country['logistics_id'] = $country_info['logistics_id'];
                            $insert_data_country['iso_code'] = trim($country_info['name']);
                            $insert_data_country['country_name'] = $country_info['country_name'];
                            $insert_data_country['area_name'] = $country_info['area'];
                            $insert_data_country['shipping_service_text'] = $shipping_service_text;
                            $insert_data_country['delivery_type'] = $delivery_type;
                            $insert_data_country['discount'] = $country_info['relief'];
                            $insert_data_country['addtime'] = $add_time;
                            Db::table($this->table_country)->insert($insert_data_country);
                            $stc_id = Db::table($this->table_country)->getLastInsID();//获取主键ID
                            //保存自定义运费计算数据
                            /** 写入sl_shipping_tamplate_country_freight表 **/
                            if ($delivery_type == 3){
                                $custom_freight_data = $country_info['custom_freight_data'];
                                $custom_freight_type = $custom_freight_data['custom_freight_type'];
                                $first_data = $custom_freight_data['first_data'];
                                $first_freight_type = $custom_freight_data['first_freight_type'];
                                $first_freight = $custom_freight_data['first_freight'];
                                //自定义区间数据
                                $increase_data = $custom_freight_data['increase_data'];
                                foreach ($increase_data as $increase_info){
                                    $insert_data_country_freight['stc_id'] = $stc_id;
                                    $insert_data_country_freight['custom_freight_type'] = $custom_freight_type;
                                    $insert_data_country_freight['first_data'] = $first_data;
                                    $insert_data_country_freight['first_freight_type'] = $first_freight_type;
                                    $insert_data_country_freight['first_freight'] = $first_freight;
                                    $insert_data_country_freight['start_data'] = $increase_info['start_data'];
                                    $insert_data_country_freight['end_data'] = $increase_info['end_data'];
                                    $insert_data_country_freight['add_data'] = $increase_info['add_data'];
                                    $insert_data_country_freight['add_freight_type'] = $increase_info['add_freight_type'];
                                    $insert_data_country_freight['add_freight'] = $increase_info['add_freight'];
                                    $insert_data_country_freight['addtime'] = $add_time;
                                    Db::table($this->table_country_freight)->insert($insert_data_country_freight);
                                }
                            }
                        }
                    }else{
                        $country_data = $type_info['country_data'][0]['country'];
                        //发货类型：1-标准运费，2-卖家承担运费，3-自定义[设置按重量或按数量计算运费]
                        $delivery_type = $type_info['country_data'][0]['freightType'];
                        $country_discount = $type_info['country_data'][0]['relief'];
                        //自定义运费计算数据
                        foreach ($country_data as $country_info){
                            $shipping_service_text = isset($country_info['shipping_service_text'])?$country_info['shipping_service_text']:'';
                            //分解专线数据，如果存在国家对应几个专线的情况，则需要分开存储
//                        $shipping_service_text_arr = explode(',' ,$shipping_service_text);
//                        foreach ($shipping_service_text_arr as $shipping_service_text_info){
                            $insert_data_country['type_id'] = $type_id;
                            $insert_data_country['iso_code'] = trim($country_info['name']);
                            $insert_data_country['logistics_id'] = $country_info['logistics_id'];
                            $insert_data_country['country_name'] = $country_info['country_name'];
                            $insert_data_country['area_name'] = $country_info['area'];
                            //$insert_data_country['shipping_service_text'] = $shipping_service_text_info;
                            $insert_data_country['shipping_service_text'] = $shipping_service_text;
                            $insert_data_country['delivery_type'] = $delivery_type;
                            $insert_data_country['discount'] = $country_discount;
                            $insert_data_country['addtime'] = $add_time;
                            Db::table($this->table_country)->insert($insert_data_country);
                            $stc_id = Db::table($this->table_country)->getLastInsID();//获取主键ID
                            //保存自定义运费计算数据
                            /** 写入sl_shipping_tamplate_country_freight表 **/
                            if ($delivery_type == 3){
                                $custom_freight_data = $type_info['country_data'][0]['custom_freight_data'];
                                $custom_freight_type = $custom_freight_data['custom_freight_type'];
                                $first_data = $custom_freight_data['first_data'];
                                $first_freight_type = $custom_freight_data['first_freight_type'];
                                $first_freight = $custom_freight_data['first_freight'];
                                //自定义区间数据
                                $increase_data = $custom_freight_data['increase_data'];
                                foreach ($increase_data as $increase_info){
                                    $insert_data_country_freight['stc_id'] = $stc_id;
                                    $insert_data_country_freight['custom_freight_type'] = $custom_freight_type;
                                    $insert_data_country_freight['first_data'] = $first_data;
                                    $insert_data_country_freight['first_freight_type'] = $first_freight_type;
                                    $insert_data_country_freight['first_freight'] = $first_freight;
                                    $insert_data_country_freight['start_data'] = $increase_info['start_data'];
                                    $insert_data_country_freight['end_data'] = $increase_info['end_data'];
                                    $insert_data_country_freight['add_data'] = $increase_info['add_data'];
                                    $insert_data_country_freight['add_freight_type'] = $increase_info['add_freight_type'];
                                    $insert_data_country_freight['add_freight'] = $increase_info['add_freight'];
                                    $insert_data_country_freight['addtime'] = $add_time;
                                    Db::table($this->table_country_freight)->insert($insert_data_country_freight);
                                }
                            }
//                        }
                        }
                    }
                }
            }
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $template_id = null;
            Log::record('执行新增运费模板事务出错 '.$e->getMessage());
            // roll
            Db::rollback();
        }
        return $template_id;
    }

    /**
     * 编辑模板信息
     * @param $op_name 编辑人姓名
     * @param $data 要编辑的模板信息
     * @return bool 编辑结果
     */
    public function editorData($op_name, $data){
        $rtn = true;
        //start
        Db::startTrans();
        try{
            /** 修改sl_shipping_template表 直接修改 **/
            $template_id = $data['template_id'];
            $this->editor_template_id = $template_id;
            $time = time();
            $up_data['template_name'] = $data['template_name'];
            $up_data['delivery_area'] = $data['delivery_area'];
            $up_data['is_charged'] = $data['is_charged'];
            $up_data['op_name'] = $op_name;
            $up_data['op_desc'] = '修改';
            $up_data['op_time'] = $time;
            //更新sl_shipping_template表数据
            Db::table($this->table)->where(['template_id'=>$template_id])->update($up_data);
            /** sl_shipping_tamplate_type、sl_shipping_tamplate_country表的修改采用先删除之前数据，再新增的步骤 start **/
            //删除sl_shipping_tamplate_country_freight表信息
            Db::table($this->table_country_freight)
                ->where('stc_id', 'IN', function($query){
                    $query->table($this->table_country)->where('type_id', 'IN', function($query){
                        $query->table($this->table_type)->where(['template_id'=>$this->editor_template_id])->field('type_id');
                    })->field('stc_id');
                })
                ->delete();
            //删除sl_shipping_tamplate_country表信息
            Db::table($this->table_country)
                ->where('type_id', 'IN', function($query){
                    $query->table($this->table_type)->where(['template_id'=>$this->editor_template_id])->field('type_id');
                })
                ->delete();
            //删除sl_shipping_tamplate_type表信息
            Db::table($this->table_type)->where(['template_id'=>$template_id])->delete();
            /** sl_shipping_tamplate_type、sl_shipping_tamplate_country表的修改采用先删除之前数据，再新增的步骤 end **/

            /** 新增sl_shipping_tamplate_type扩展表 **/
            $type_data = $data['data'];
            foreach ($type_data as $type_info){
                $discount = 0; //shipping_type = 1 ,才有
                $template_type = $type_info['logisticsServices'];//运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
                $shipping_type = $type_info['freightSettings'];//运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
                if ($shipping_type == 1){
                    $discount = $type_info['relief'];
                }
                $insert_data_type['template_id'] = $template_id;
                $insert_data_type['template_type'] = $template_type;
                $insert_data_type['shipping_type'] = $shipping_type;
                $insert_data_type['discount'] = $discount;
                $insert_data_type['addtime'] = $time;
                Db::table($this->table_type)->insert($insert_data_type);
                $type_id = Db::table($this->table_type)->getLastInsID();//获取主键ID
                //如果选择“自定义运费”则记录相应的运费类型对应的国家、送货类型信息
                /** 新增sl_shipping_tamplate_country扩展表 **/
                if ($shipping_type == 3){
                    if ($template_type == 40){ //专线数据格式不一样，需要特殊处理
                        $country_data = $type_info['country_data'][0]['country'];
                        foreach ($country_data as $country_info){
                            $delivery_type = $country_info['freightType'];
                            $shipping_service_text = $country_info['shipping_service_text'];
                            //分解专线数据，如果存在国家对应几个专线的情况，则需要分开存储
                            $insert_data_country['type_id'] = $type_id;
                            $insert_data_country['iso_code'] = trim($country_info['name']);
                            $insert_data_country['logistics_id'] = $country_info['logistics_id'];
                            $insert_data_country['country_name'] = $country_info['country_name'];
                            $insert_data_country['area_name'] = $country_info['area'];
                            $insert_data_country['shipping_service_text'] = $shipping_service_text;
                            $insert_data_country['delivery_type'] = $delivery_type;
                            $insert_data_country['discount'] = $country_info['relief'];
                            $insert_data_country['addtime'] = $time;
                            Db::table($this->table_country)->insert($insert_data_country);
                            $stc_id = Db::table($this->table_country)->getLastInsID();//获取主键ID
                            //保存自定义运费计算数据
                            /** 写入sl_shipping_tamplate_country_freight表 **/
                            if ($delivery_type == 3){
                                $custom_freight_data = $country_info['custom_freight_data'];
                                $custom_freight_type = $custom_freight_data['custom_freight_type'];
                                $first_data = $custom_freight_data['first_data'];
                                $first_freight_type = $custom_freight_data['first_freight_type'];
                                $first_freight = $custom_freight_data['first_freight'];
                                //自定义区间数据
                                $increase_data = $custom_freight_data['increase_data'];
                                foreach ($increase_data as $increase_info){
                                    $insert_data_country_freight['stc_id'] = $stc_id;
                                    $insert_data_country_freight['custom_freight_type'] = $custom_freight_type;
                                    $insert_data_country_freight['first_data'] = $first_data;
                                    $insert_data_country_freight['first_freight_type'] = $first_freight_type;
                                    $insert_data_country_freight['first_freight'] = $first_freight;
                                    $insert_data_country_freight['start_data'] = $increase_info['start_data'];
                                    $insert_data_country_freight['end_data'] = $increase_info['end_data'];
                                    $insert_data_country_freight['add_data'] = $increase_info['add_data'];
                                    $insert_data_country_freight['add_freight_type'] = $increase_info['add_freight_type'];
                                    $insert_data_country_freight['add_freight'] = $increase_info['add_freight'];
                                    $insert_data_country_freight['addtime'] = $time;
                                    Db::table($this->table_country_freight)->insert($insert_data_country_freight);
                                }
                            }
                        }
                    }else{
                        $country_data = $type_info['country_data'][0]['country'];
                        //发货类型：1-标准运费，2-卖家承担运费，3-自定义[设置按重量或按数量计算运费]
                        $delivery_type = $type_info['country_data'][0]['freightType'];
                        $country_discount = $type_info['country_data'][0]['relief'];
                        //自定义运费计算数据
                        foreach ($country_data as $country_info){
                            $shipping_service_text = $country_info['shipping_service_text'];
                            //分解专线数据，如果存在国家对应几个专线的情况，则需要分开存储
//                        $shipping_service_text_arr = explode(',' ,$shipping_service_text);
//                        foreach ($shipping_service_text_arr as $shipping_service_text_info){
                            $insert_data_country['type_id'] = $type_id;
                            $insert_data_country['iso_code'] = trim($country_info['name']);
                            $insert_data_country['logistics_id'] = $country_info['logistics_id'];
                            $insert_data_country['country_name'] = $country_info['country_name'];
                            $insert_data_country['area_name'] = $country_info['area'];
                            //$insert_data_country['shipping_service_text'] = $shipping_service_text_info;
                            $insert_data_country['shipping_service_text'] = $shipping_service_text;
                            $insert_data_country['delivery_type'] = $delivery_type;
                            $insert_data_country['discount'] = $country_discount;
                            $insert_data_country['addtime'] = $time;
                            Db::table($this->table_country)->insert($insert_data_country);
                            $stc_id = Db::table($this->table_country)->getLastInsID();//获取主键ID
                            //保存自定义运费计算数据
                            /** 写入sl_shipping_tamplate_country_freight表 **/
                            if ($delivery_type == 3){
                                $custom_freight_data = $type_info['country_data'][0]['custom_freight_data'];
                                $custom_freight_type = $custom_freight_data['custom_freight_type'];
                                $first_data = $custom_freight_data['first_data'];
                                $first_freight_type = $custom_freight_data['first_freight_type'];
                                $first_freight = $custom_freight_data['first_freight'];
                                //自定义区间数据
                                $increase_data = $custom_freight_data['increase_data'];
                                foreach ($increase_data as $increase_info){
                                    $insert_data_country_freight['stc_id'] = $stc_id;
                                    $insert_data_country_freight['custom_freight_type'] = $custom_freight_type;
                                    $insert_data_country_freight['first_data'] = $first_data;
                                    $insert_data_country_freight['first_freight_type'] = $first_freight_type;
                                    $insert_data_country_freight['first_freight'] = $first_freight;
                                    $insert_data_country_freight['start_data'] = $increase_info['start_data'];
                                    $insert_data_country_freight['end_data'] = $increase_info['end_data'];
                                    $insert_data_country_freight['add_data'] = $increase_info['add_data'];
                                    $insert_data_country_freight['add_freight_type'] = $increase_info['add_freight_type'];
                                    $insert_data_country_freight['add_freight'] = $increase_info['add_freight'];
                                    $insert_data_country_freight['addtime'] = $time;
                                    Db::table($this->table_country_freight)->insert($insert_data_country_freight);
                                }
                            }
//                        }
                        }
                    }
                }
            }
            //submit
            Db::commit();
        }catch (\Exception $e){
            $rtn = false;
            Log::record('editorData-Exception:'.$e->getMessage(),'error');
            // roll
            Db::rollback();
        }
        return $rtn;
    }

    /**
     * 根据运费模板ID获取运费模板数据
     * @param $template_id
     * @param int $is_delete 是否删除：0-未删除，1-已删除
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoForTemplateByTemplateID($template_id, $seller_id=0, $is_delete=0){
        if ($seller_id === 0){
            $where = ['template_id'=>$template_id, 'is_delete'=>$is_delete];
        }else{
            $where = ['template_id'=>$template_id, 'seller_id'=>$seller_id,'is_delete'=>$is_delete];
        }
        return Db::table($this->table)->where($where)->find();
    }

    /**
     * 根据运费模板名称获取运费模板数据
     * @param $template_name
     * @param int $is_delete 是否删除：0-未删除，1-已删除
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoForTemplateByTemplateName($template_name, $is_delete=0){
        return Db::table($this->table)->where(['template_name'=>$template_name, 'is_delete'=>$is_delete])->select();
    }

    /**
     * 获取运费模板信息【分页】
     * @param $seller_id 用户ID（seller）
     * @param $page_size 每页大小
     * @param $is_delete 是否删除：0-未删除，1-已删除
     * @return \think\Paginator
     */
    public function getTemplateData($seller_id, $page_size=10, $is_delete=0){
        return Db::table($this->table)->where(['seller_id'=>$seller_id, 'is_delete'=>$is_delete])->order(['addtime'=>'desc'])->paginate($page_size)->each(function($item, $key){
            $template_id = $item['template_id'];
            $template_type_arr = [];
            //获取运费模板对应的类型
            $type_data = $this->getTemplateTypeDataByTemplateID($template_id);
            foreach ($type_data as $type_info){
                $type_base = Base::getShippingTamplateType($type_info['template_type']);
                $template_type_arr[] = isset($type_base['en_name'])?$type_base['en_name']:'-';
            }
            $item['template_type_str'] = implode('、', $template_type_arr);
            return $item;
        });
    }

    /**
     * 根据条件获取运费模板基础信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTemplateDataByWhere(array $where){
        return Db::table($this->table)
            ->where($where)
            ->field(['template_id', 'seller_id', 'template_name', 'delivery_area', 'is_charged','is_default'])
            ->select();
    }

    /**
     * 根据sellerID获取所有模板数据
     * @param $seller_id
     * @param $is_delete 是否删除：0-未删除，1-已删除
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTemplateAllData($seller_id, $is_delete=0){
        return Db::table($this->table)
            ->where(['seller_id'=>$seller_id, 'is_delete'=>$is_delete])
            ->field(['template_id', 'seller_id', 'template_name', 'delivery_area', 'is_charged','is_default'])
            ->select();
    }

    /**
     * 根据运费模板ID获取运费模板类型数据
     * @param $template_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTemplateTypeDataByTemplateID($template_id){
        return Db::table($this->table_type)->where(['template_id'=>$template_id])->select();
    }

    /**
     * 根据运费模板类型ID获取运费模板对应国家信息
     * @param $type_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTemplateCountryDataByTypeID($type_id){
        return Db::table($this->table_country)->where(['type_id'=>$type_id])->select();
    }

    /**
     * 根据模板ID更新运费模板信息
     * @param $template_id 模板ID
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateTemplateData($template_id, array $up_data){
        return Db::table($this->table)->where('template_id', $template_id)->update($up_data);
    }

    /**
     * 设置默认运费模板
     * @param $seller_id 用户ID（seller）
     * @param $template_id 模板ID
     * @return bool
     */
    public function setDefaultTemplate($seller_id, $template_id){
        $rtn = true;
        // start
        Db::startTrans();
        try{
            //将seller其他模板设置为不默认
            Db::table($this->table)->where(['seller_id'=>$seller_id])->update(['is_default'=>0]);
            //将指定模板设置为默认
            Db::table($this->table)->where(['template_id'=>$template_id])->update(['is_default'=>1]);
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('执行运费模板默认设置事务出错');
            // roll
            Db::rollback();
        }
        return $rtn;
    }

    /**
     * 复制运费模板（复制后的模板名称为：模板名称+‘副本’，其他信息完全一致）
     * @param $template_id 要复制的模板ID
     * @return bool
     */
    public function copyShippingTemplateData($template_id){
        $rtn = true;
        // start
        Db::startTrans();
        try{
            $add_time = time();
            /** 获取模板信息 **/
            $template_info = $this->getInfoForTemplateByTemplateID($template_id);
            if (empty($template_info)){
                return false;
            }
            $template_name = $template_info['template_name'].'副本';
            //运费模板名称唯一性判断
            $t_info = Db::table($this->table)
                ->where('template_name','like',$template_name.'%')
                ->where('is_delete',0)
                ->order('addtime', 'desc')
                ->select();
            if (!empty($t_info)){
                $template_name = $t_info[0]['template_name'].'副本';
            }
            $template_insert_data['seller_id'] = $template_info['seller_id'];
            $template_insert_data['template_name'] = $template_name;
            $template_insert_data['delivery_area'] = $template_info['delivery_area'];
            $template_insert_data['is_charged'] = $template_info['is_charged'];
            $template_insert_data['is_default'] = 0;
            $template_insert_data['is_delete'] = $template_info['is_delete'];
            $template_insert_data['addtime'] = $add_time;
            //复制运费模板
            Db::table($this->table)->insert($template_insert_data);
            $new_template_id = Db::table($this->table)->getLastInsID();//复制成功后的新模板ID
            /** 获取模板类型信息 **/
            $template_type_info = $this->getTemplateTypeDataByTemplateID($template_id);
            foreach ($template_type_info as $type_info){
                $type_id = $type_info['type_id'];
                $shipping_type = $type_info['shipping_type'];
                $template_type_insert_data['template_id'] = $new_template_id;
                $template_type_insert_data['template_type'] = $type_info['template_type'];
                $template_type_insert_data['shipping_type'] = $shipping_type;
                $template_type_insert_data['discount'] = $type_info['discount'];
                $template_type_insert_data['addtime'] = $add_time;
                //复制运费模板类型数据
                Db::table($this->table_type)->insert($template_type_insert_data);
                $new_type_id = Db::table($this->table_type)->getLastInsID();//复制成功后的新模板类型ID
                /** 存在自定义运费，则复制自定义运费对应的国家信息 **/
                if ($shipping_type == 3){
                    $country_data = $this->getTemplateCountryDataByTypeID($type_id);
                    foreach ($country_data as $country_info){
                        $stc_id = $country_info['stc_id'];
                        //发货类型：1-标准运费，2-卖家承担运费，3-自定义[sl_shipping_tamplate_country_freight有相关运费自定义数据]
                        $delivery_type = $country_info['delivery_type'];
                        $template_type_country_insert_data['type_id'] = $new_type_id;
                        $template_type_country_insert_data['iso_code'] = trim($country_info['iso_code']);
                        $template_type_country_insert_data['logistics_id'] = $country_info['logistics_id'];
                        $template_type_country_insert_data['country_name'] = $country_info['country_name'];
                        $template_type_country_insert_data['area_name'] = $country_info['area_name'];
                        $template_type_country_insert_data['shipping_service_text'] = $country_info['shipping_service_text'];
                        $template_type_country_insert_data['delivery_type'] = $delivery_type;
                        $template_type_country_insert_data['discount'] = $country_info['discount'];
                        $template_type_country_insert_data['addtime'] = $add_time;
                        //复制运费模板类型对应自定义国家信息
                        Db::table($this->table_country)->insert($template_type_country_insert_data);
                        $new_stc_id = Db::table($this->table_country)->getLastInsID();
                        //复制sl_shipping_tamplate_country_freight表数据
                        if ($delivery_type == 3){
                            $freight_data = $this->getTemplateCountryFreightDataByStcId($stc_id);
                            foreach ($freight_data as $freight_info){
                                $insert_data_country_freight['stc_id'] = $new_stc_id;
                                $insert_data_country_freight['custom_freight_type'] = $freight_info['custom_freight_type'];
                                $insert_data_country_freight['first_data'] = $freight_info['first_data'];
                                $insert_data_country_freight['first_freight_type'] = $freight_info['first_freight_type'];
                                $insert_data_country_freight['first_freight'] = $freight_info['first_freight'];
                                $insert_data_country_freight['start_data'] = $freight_info['start_data'];
                                $insert_data_country_freight['end_data'] = $freight_info['end_data'];
                                $insert_data_country_freight['add_data'] = $freight_info['add_data'];
                                $insert_data_country_freight['add_freight_type'] = $freight_info['add_freight_type'];
                                $insert_data_country_freight['add_freight'] = $freight_info['add_freight'];
                                $insert_data_country_freight['addtime'] = $add_time;
                                Db::table($this->table_country_freight)->insert($insert_data_country_freight);
                            }
                        }
                    }
                }
            }
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('执行运费模板复制事务出错 '.$e->getMessage());
            // roll
            Db::rollback();
        }
        return $rtn;
    }

    /**
     * 根据运费模板类型、国家简码获取运费模板类型对应自定义国家信息
     * @param $type_id 运费模板类型ID
     * @param $iso_code 国家简码
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTemplateCountryDataByTypeIDAndIsoCode($type_id, $iso_code){
        return Db::table($this->table_country)->where(['type_id'=>$type_id, 'iso_code'=>trim($iso_code)])->select();
    }

    /**
     * 根据自定义国家运费ID获取具体运费信息
     * @param $stc_id 运费模板国家信息ID
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTemplateCountryFreightDataByStcId($stc_id){
        return Db::table($this->table_country_freight)->where(['stc_id'=>$stc_id])->field('custom_freight_type,first_data,first_freight_type,first_freight,start_data,end_data,add_data,add_freight_type,add_freight')->select();
    }
}