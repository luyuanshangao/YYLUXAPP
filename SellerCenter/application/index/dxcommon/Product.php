<?php
namespace app\index\dxcommon;

use app\index\model\LogisticsManagementModel;
use app\index\model\ShippingTemplateModel;

/**
 * Class Product
 * @author tinghu.liu
 * @date 2018-04-08
 * @package app\index\dxcommon
 */
class Product
{
    /**
     * 运费模板名称格式判断
     * @param $template_name 运费模板名称
     * @return bool
     */
    public static function checkShippingTemplateName($template_name){
        $rtn = false;
        $pattern = '/^(\d|[a-zA-Z]|\&|\-|\'|\"|\*)+$/';
        if (preg_match($pattern, $template_name)){
            $rtn = true;
        }
        return $rtn;
    }

    /**
     * 根据模板类型、带电属性获取对应运费国家信息
     * @param $template_type 运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
     * @param $is_charged 是否带电：1-为普货，2-为纯电，3-为带电
     * @return array
     */
    public static function getSTCountryByTemplateType($template_type, $is_charged){
        $manage_model = new LogisticsManagementModel();
        $data = $manage_model->getCountryDataByShippingServiceID($template_type, $is_charged);
        //最终组装的数据
        $country_group_result = array();
        //是Exclusive时数据个数不一样
        if ($template_type == 40){
            foreach ($data as $info){
                $temp_arr = [
                    'logistics_id'=>$info['id'],
                    'c_name'=>$info['countryENName'],
                    'c_code'=>$info['countryCode'],
                    'area_name'=>$info['areaName'],
                    'shipping_service_text'=>$info['shippingServiceText'],
                    'show_text'=>$info['countryCode'].':'.$info['shippingServiceText']
                ];
                $country_group_result[] = $temp_arr;
            }
            //对国家进行去重操作
            $country_group_result = array_unset_repeat_nokey($country_group_result, 'c_code');
        }else{
            //组装数据格式
            $country_group = array();//以洲分组
            //获取按洲分组
            foreach ($data as $info){
                $areaName = $info['areaName'];
                if (!in_array($areaName, $country_group)){
                    $country_group[] = $areaName;
                }
            }
            //根据洲分组组装对应洲的国家信息
            if (!empty($country_group)){
                foreach ($country_group as $info){
                    $temp = array('name'=>$info);
                    foreach ($data as $val){
                        if ($info == $val['areaName']){
                            $temp2 = ['logistics_id'=>$val['id'],'c_name'=>$val['countryENName'], 'c_code'=>$val['countryCode'], 'shipping_service_text'=>$val['shippingServiceText']];
                            $temp['country_info'][] = $temp2;
                        }
                    }
                    $country_group_result[] = $temp;
                }
            }
            //物流服务下一个国家对应多条记录时，以国家为唯一性，进行组合，以英文“,”分开
            /*foreach ($country_group_result as &$res_info){
                $res_info['country_info'] = deduplication_arr('c_code', 'shipping_service_text', $res_info['country_info']);
            }*/
            //对国家进行去重操作
            foreach ($country_group_result as &$c_info){
                $c_info['country_info'] = array_unset_repeat_nokey($c_info['country_info'], 'c_code');
            }
        }
        return $country_group_result;
    }

    /**
     * 根据运费模板ID获取运费模板对应国家数据
     * @param $template_id 模板ID
     * @return array
     */
    public static function getSTCountryByTemplateId($template_id){
        $country = array();
        $model = new ShippingTemplateModel();
        $logi_model = new LogisticsManagementModel();
        $template_type_data = $model->getTemplateTypeDataByTemplateID($template_id);
//            print_r($template_type_data);
        //根据模板类型获取对应国家信息，如果是自定义[shipping_type == 3]，则拉取自定的国家信息
        foreach ($template_type_data as $type_info){
            $type_id = $type_info['type_id'];
            $template_type = $type_info['template_type'];//运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
            $shipping_type = $type_info['shipping_type'];//运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
            if ($shipping_type == 3){ //获取自定义模板类型对应的国家
                $country_type_data = $model->getTemplateCountryDataByTypeID($type_id);
                foreach ($country_type_data as $ctype_info){
                    $temp = array();
                    $temp['iso_code'] = $ctype_info['iso_code'];
                    $temp['country_name'] = $ctype_info['country_name'];
                    $country[] = $temp;
                }
            }else{ //获取标准模板类型对应的国家
                $service_data = $logi_model->getCountryDataByShippingServiceID($template_type);
                foreach ($service_data as $c_info){
                    $temp = array();
                    $temp['iso_code'] = $c_info['countryCode'];
                    $temp['country_name'] = $c_info['countryENName'];
                    $country[] = $temp;
                }
            }
        }
        //国家去重
        return array_unset_repeat($country, 'iso_code');
    }

    /**
     * 根据运费模板ID、seller ID获取运费模板信息
     * @param $template_id 运费模板ID
     * @param $seller_id sellerID
     * @return array
     */
    public static function getShippingTemplateByID($template_id, $seller_id){
        $rtn = [];
        $model = new ShippingTemplateModel();
        /** 1、获取运费模板信息 **/
        $template_info = $model->getInfoForTemplateByTemplateID($template_id, $seller_id);
        if (!empty($template_info)) {
            $rtn['template_id'] = $template_info['template_id'];
            $rtn['template_name'] = $template_info['template_name'];
            $rtn['delivery_area'] = $template_info['delivery_area'];
            $rtn['is_charged'] = $template_info['is_charged'];
            /** 2、获取运费模板类型信息 **/
            $template_type_info = $model->getTemplateTypeDataByTemplateID($template_id);
            foreach ($template_type_info as $type_info) {
                $temp_arr = [];
                $type_id = $type_info['type_id'];
                $template_type = $type_info['template_type'];//运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
                $shipping_type = $type_info['shipping_type'];//运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
                $discount = $type_info['discount'];
                $temp_arr['logisticsServices'] = $template_type;
                $temp_arr['freightSettings'] = $shipping_type;
                if ($shipping_type == 1) {
                    $temp_arr['relief'] = $discount;
                }
                if ($shipping_type == 3) {
                    /** 3、获取运费模板国家信息 **/
                    $template_country_info = $model->getTemplateCountryDataByTypeID($type_id);
                    if ($template_type == 40){ //专线数据格式不一样，需要特殊处理
                        $temp_arr['country_data'] = [];
                        foreach ($template_country_info as $country_info) {
                            //发货类型：1-标准运费，2-卖家承担运费，3-自定义[custom_freight-type字段才生效]
                            $delivery_type = $country_info['delivery_type'];
                            $stc_id = $country_info['stc_id'];
                            $c_temp_arr = [];
                            $temp_arr_freight = [];
                            $c_temp_arr['logistics_id'] = $country_info['logistics_id'];
                            $c_temp_arr['name'] = $country_info['iso_code'];
                            $c_temp_arr['country_name'] = $country_info['country_name'];
                            $c_temp_arr['area'] = $country_info['area_name'];
                            $c_temp_arr['shipping_service_text'] = $country_info['shipping_service_text'];
                            $c_temp_arr['freightType'] = $delivery_type;
                            $c_temp_arr['relief'] = $country_info['discount'];

                            if ($delivery_type == 3){//自定义
                                $custom_freight_data = $model->getTemplateCountryFreightDataByStcId($stc_id);
                                $temp_arr_freight['custom_freight_type'] = $custom_freight_data[0]['custom_freight_type'];
                                $temp_arr_freight['first_data'] = $custom_freight_data[0]['first_data'];
                                $temp_arr_freight['first_freight_type'] = $custom_freight_data[0]['first_freight_type'];
                                $temp_arr_freight['first_freight'] = $custom_freight_data[0]['first_freight'];
                                //自增定义区间
                                $increase_data = [];
                                foreach ($custom_freight_data as $custom_freight_info){
                                    $increase_data_temp = [];
                                    $increase_data_temp['start_data'] = $custom_freight_info['start_data'];
                                    $increase_data_temp['end_data'] = $custom_freight_info['end_data'];
                                    $increase_data_temp['add_data'] = $custom_freight_info['add_data'];
                                    $increase_data_temp['add_freight_type'] = $custom_freight_info['add_freight_type'];
                                    $increase_data_temp['add_freight'] = $custom_freight_info['add_freight'];
                                    $increase_data[] = $increase_data_temp;
                                }
                                $temp_arr_freight['increase_data'] = $increase_data;
                            }
                            $c_temp_arr['custom_freight_data'] = $temp_arr_freight;
                            $temp_arr['country_data']['country'][] = $c_temp_arr;
                        }
                    }else{
                            $temp_arr2 = [];
                            $temp_arr_freight = [];
                            //因为每个国家的自定义数据相同，所以只需要取其中一个数据即可
                            $stc_id = $template_country_info[0]['stc_id'];
                            foreach ($template_country_info as $country_info) {
                                //发货类型：1-标准运费，2-卖家承担运费，3-自定义[custom_freight-type字段才生效]
                                $delivery_type = $country_info['delivery_type'];
                                $temp_arr2[] = [
                                    'logistics_id' => $country_info['logistics_id'],
                                    'name' => $country_info['iso_code'],
                                    'country_name'=>$country_info['country_name'],
                                    'area' => $country_info['area_name'],
                                    'shipping_service_text' => $country_info['shipping_service_text']
                                ];
                            }
                            $temp_arr['country_data'] = [];
                            $temp_arr['country_data']['freightType'] = $delivery_type;
                            $temp_arr['country_data']['relief'] = $country_info['discount'];
                            $temp_arr['country_data']['country'] = $temp_arr2;
                            //获取自定义价格公式参数,因为除了专线外，其他的国家都是共用一个公式参数，所以只需要拉取其中一个国家对应的参数定义返回即可 start
                            if ($delivery_type == 3){
                                $custom_freight_data = $model->getTemplateCountryFreightDataByStcId($stc_id);
                                //对数据进行重组，为了和新增数据格式保持一致
                                $temp_arr_freight['custom_freight_type'] = $custom_freight_data[0]['custom_freight_type'];
                                $temp_arr_freight['first_data'] = $custom_freight_data[0]['first_data'];
                                $temp_arr_freight['first_freight_type'] = $custom_freight_data[0]['first_freight_type'];
                                $temp_arr_freight['first_freight'] = $custom_freight_data[0]['first_freight'];
                                $increase_data = [];
                                foreach ($custom_freight_data as $custom_freight_info){
                                    $increase_data_temp = [];
                                    $increase_data_temp['start_data'] = $custom_freight_info['start_data'];
                                    $increase_data_temp['end_data'] = $custom_freight_info['end_data'];
                                    $increase_data_temp['add_data'] = $custom_freight_info['add_data'];
                                    $increase_data_temp['add_freight_type'] = $custom_freight_info['add_freight_type'];
                                    $increase_data_temp['add_freight'] = $custom_freight_info['add_freight'];
                                    $increase_data[] = $increase_data_temp;
                                }
                                $temp_arr_freight['increase_data'] = $increase_data;
                            }
                            $temp_arr['country_data']['custom_freight_data'] = $temp_arr_freight;
                            //获取自定义价格公式参数,因为除了专线外，其他的国家都是共用一个公式参数，所以只需要拉取其中一个国家对应的参数定义返回即可 start
                    }
                }
                $rtn['data'][] = $temp_arr;
            }
        }
        return $rtn;
    }

    /**
     * 根据运费模板ID、国家简码获取运费模板详情
     * @param $template_id 运费模板ID
     * @param $iso_code 国家简码
     * @return array
     */
    public static function getSTDetailByIDAndCountry($template_id, $iso_code){
        $rtn = array();
        $model = new ShippingTemplateModel();
        $logi_model = new LogisticsManagementModel();
        $type_data = $model->getTemplateTypeDataByTemplateID($template_id);
        $template_info = $model->getInfoForTemplateByTemplateID($template_id);
        $is_charged = $template_info['is_charged'];
        foreach ($type_data as $type_info){
            $temp = array();
            $have_country = false;
            $type_id = $type_info['type_id'];
            $discount = $type_info['discount'];
            //运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
            $template_type = $type_info['template_type'];
            //运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
            $shipping_type = $type_info['shipping_type'];
            if ($shipping_type == 3){ //自定义运费
                $c_data = $model->getTemplateCountryDataByTypeIDAndIsoCode($type_id, $iso_code);
            }else{//选择标准配置国家信息
                $c_data = $logi_model->getDataByShippingServiceIDAndCountryCode($template_type, $iso_code, 1, $is_charged);
            }
            if (!empty($c_data)){//说明运费模板此类型有这个国家配置
                $have_country = true;
            }
            //组装运费模板下指定国家对应的运费模板类型数据
            if ($have_country){
                $template_type_arr = Base::getShippingTamplateType($template_type);
                $logi_data = $logi_model->getDataByShippingServiceIDAndCountryCode($template_type, $iso_code, 1, $is_charged);
                $price = $lsm_price = $logi_data[0]['first_freight']; //标准运费模板首重价格
                $type_str = '';
                switch ($shipping_type){
                    case 1://标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]
                        if (!empty($discount) && $discount >0){
                            $price = $price*($discount/100);
                        }
                        $type_str = '标准运费';
                        break;
                    case 2://卖家承担运费
                        $price = 0;
                        $type_str = '卖家承担运费';
                        break;
                    case 3://自定义运费
                        $custom_country_info = $c_data[0]; //自定义国家数据
                        //发货类型：1-标准运费，2-卖家承担运费，3-自定义[sl_shipping_tamplate_country_freight表才生效]
                        $delivery_type = $custom_country_info['delivery_type'];
                        if ($delivery_type == 3){
                            $stcf_data = $model->getTemplateCountryFreightDataByStcId($custom_country_info['stc_id']);
                            $stcf_info = $stcf_data[0];
                            //首重 || 规定内的数量  的价格 单位：1-按照百分比，2-单位为美元
                            $price = $stcf_info['first_freight'];
                            if ($stcf_info['first_freight_type'] == 1){
                                $price = ($stcf_info['first_freight']/100)*$lsm_price;
                            }
                        }elseif($delivery_type == 1){
                            if (!empty($custom_country_info['discount']) && $custom_country_info['discount'] >0){
                                $price = $price*($custom_country_info['discount']/100);
                            }
                        }elseif($delivery_type == 2){
                            $price = 0;
                        }
                        $type_str = '自定义运费';
                        break;
                    default:break;
                }
                $temp['iso_code'] = $logi_data[0]['countryCode'];
                $temp['name'] = $template_type_arr['en_name'];
                $temp['type_str'] = $type_str;
                $temp['price'] = round($price, 2);
                $temp['shipping_day'] = $logi_data[0]['time_slot'];
            }
            if (!empty($temp)){
                $rtn[] = $temp;
            }
        }
        return $rtn;
    }

    /**
     * 处理运费模板价格
     * @param $shipping_type 运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
     * @param $discount 折扣
     */
    public static function handleShippingTemplatePrice($shipping_type, $price, $discount){
        $rtn = ['price'=>$price, 'type_str'=>''];
        switch ($shipping_type){
            case 1://标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]
                if (!empty($discount) && $discount >0){
                    $price = $price*($discount/100);
                }
                $type_str = '标准运费';
                break;
            case 2://卖家承担运费
                $price = 0;
                $type_str = '卖家承担运费';
                break;
            case 3://自定义运费
                $type_str = '自定义运费';
                break;
            default:break;
        }
        $rtn['price'] = round($price, 2);
        $rtn['type_str'] = $type_str;
        return $rtn;
    }

    /**
     * 根据运费模板ID获取详细信息【上传/编辑产品，提交组装数据用】
     * @param $template_id 运费模板
     * @return array
     */
    public static function getShippingTemplateByIDForPost($template_id){
        $rtn = array();
        $rtn['template_id'] = $template_id;
        $model = new ShippingTemplateModel();
        $logi_model = new LogisticsManagementModel();
        //模板基本信息
        $template_info = $model->getInfoForTemplateByTemplateID($template_id);
        $is_charged = $template_info['is_charged'];
        $rtn['template_name'] = $template_info['template_name'];
        $rtn['delivery_area'] = $template_info['delivery_area'];
        $rtn['is_charged'] = $is_charged;
        $rtn['is_free_shipping'] = 2; //是否免邮：1-免邮，2-不免邮
        //模板详细信息
        $type_data = $model->getTemplateTypeDataByTemplateID($template_id);
        foreach ($type_data as $type_info){
            $temp = [];
            $temp_c = [];
            $type_id = $type_info['type_id'];
            $discount = $type_info['discount'];
            //运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
            $template_type = $type_info['template_type'];
            //运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
            $shipping_type = $type_info['shipping_type'];
            if ($shipping_type == 2){ //有一个不要运费，则是免邮
                $rtn['is_free_shipping'] = 1;
            }
            //template_type_name，如果$template_type==40，则要区别对待
            $template_type_data = Base::getShippingTamplateType($template_type);
            $temp['template_type_name'] = isset($template_type_data['en_name'])?$template_type_data['en_name']:'';
            $temp['template_type'] = $template_type;
            $temp['shipping_type'] = $shipping_type;
            $temp['discount'] = $discount; //$shipping_type == 1时有效
            //获取国家信息
            if ($shipping_type == 3){ //自定义运费时
                $country_data = $model->getTemplateCountryDataByTypeID($type_id);
                foreach ($country_data as $country_info){
                    $stc_id = $country_info['stc_id'];
                    //发货类型：1-标准运费，2-卖家承担运费，3-自定义(sl_shipping_tamplate_country_freight表有数据)
                    $delivery_type = $country_info['delivery_type'];
                    if ($delivery_type == 2){ //有一个不要运费，则是免邮
                        $rtn['is_free_shipping'] = 1;
                    }
                    $temp_d = [];
                    //根据国家和模板类型获取标准基础数据
                    $base_data = $logi_model->getInfoById($country_info['logistics_id']);
                    if (!empty($base_data)){
                        $temp_d['delivery_type'] = $delivery_type;
                        $temp_d['discount'] = $country_info['discount']; //$delivery_type == 1时有效
                        $temp_d['shipping_service_text'] = $country_info['shipping_service_text'];
                        $temp_d['country_code'] = $country_info['iso_code'];
                        $temp_d['country_name'] = $country_info['country_name'];
                        $temp_d['first_weight'] = $base_data['first_weight'];
                        $temp_d['first_freight'] = $base_data['first_freight'];
                        $temp_d['isCharged'] = $base_data['isCharged'];
                        $temp_d['shipping_day'] = $base_data['time_slot'];
                        $temp_d['lms_freight_data'] = $base_data['freight_data'];
                        //if公式
                        $temp_d['lms_calculation_formula'] = $base_data['calculation_formula'];
                    }
                    if ($delivery_type == 3 && !empty($temp_d)){ //自定义，则取自定义的运费计算参数
                        $temp_d['custom_freight_data'] = $model->getTemplateCountryFreightDataByStcId($stc_id);
                    }
                    //根据国家ID获取自定义运费公式参数数据 TODO
                    if (!empty($temp_d)){
                        $temp_c[] = $temp_d;
                    }
                }
            }else{
                $base_data = $logi_model->getCountryDataByShippingServiceID($template_type, $is_charged);
                if (!empty($base_data)){
                    foreach ($base_data as $base_info){
                        $logistics_id = $base_info['id'];
                        $temp_d = [];
                        $temp_d['shipping_service_text'] = $base_info['shippingServiceText'];
                        $temp_d['country_code'] = $base_info['countryCode'];
                        $temp_d['country_name'] = $base_info['countryENName'];
                        $temp_d['first_weight'] = $base_info['first_weight'];
                        $temp_d['first_freight'] = $base_info['first_freight'];
                        $temp_d['isCharged'] = $base_info['isCharged'];
                        $temp_d['shipping_day'] = $base_info['time_slot'];
                        $temp_d['lms_freight_data'] = $logi_model->getWeightDataByLogisticsId($logistics_id);
                        //if公式
                        $temp_d['lms_calculation_formula'] = $base_info['calculation_formula'];
                        $temp_c[] = $temp_d;
                    }
                }
            }
            if(!empty($temp_c)){
                $temp['country_data'] = $temp_c;
            }
            $rtn['data'][] = $temp;
        }
        //$template_type == 40 时，一个国家有可能有对应多个专线（后期需求，本期不做），要将具体专线拉出来和10,20,30同级（思路：先遍历除专线，再根据专线重新组装对应国家数据） TODO （修改时记得检查异步同步运费模板功能）
        return $rtn;
    }

    /**
     * 根据带电属性、sellerID获取运费模板数据
     * @param $is_charged
     * @param $seller_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getShippingTemplateByIsChargedAndSellerId($is_charged, $seller_id){
        $model = new ShippingTemplateModel();
        return $model->getTemplateDataByWhere(['seller_id'=>$seller_id,'is_charged'=>$is_charged,'is_delete'=>0]);
    }

    /**
     * 根据末级分类获取分类中文
     * @param $category_id
     * @param $type 类型：1-获取中文名，2-获取ID
     * @return string
     */
    public static function getCategoryStr($category_id, $type=1){
        $category_str = '';
        if (is_numeric($category_id) && $category_id > 0){
            $base_api = new BaseApi();
            $category_data = $base_api->getCategoryDataWithID($category_id);
            $category_array = array();
            $category_id_array = array();
            if (
                isset($category_data['data'])
                && !empty($category_data['data'])
            ){
                foreach ($category_data['data'] as $cdata){
                    foreach ($cdata as $info){
                        if ($info['is_select'] == 1){
                            $category_array[] = $info['title_cn'];
                            $category_id_array[] = $info['id'];
                        }
                    }
                }
            }
            switch ($type){
                case 1:
                    $category_str = implode('>>', $category_array);
                    break;
                case 2:
                    $category_str = implode('-', $category_id_array);
                    break;
                default:break;
            }
        }
        return $category_str;
    }

}
