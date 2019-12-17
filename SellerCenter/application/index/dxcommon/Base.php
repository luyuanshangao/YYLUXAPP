<?php
namespace app\index\dxcommon;

use think\Log;

/**
 * Class Base
 * @author tinghu.liu
 * @date 2018-03-09
 * @package app\index\dxcommon
 */
class Base
{
    /**
     * 获取经营模式
     * @param int $id 经营模式ID：1-个人及贸易SOHO，2-贸易公司（小于10人），3-贸易公司（大于10人），4-工厂，5-其他
     * @return array|mixed
     */
    public static function getManageModel($id=0){
        $rtn = [
            ['id'=>1,'name'=>'个人及贸易SOHO'],
            ['id'=>2,'name'=>'贸易公司（小于10人）'],
            ['id'=>3,'name'=>'贸易公司（大于10人）'],
            ['id'=>4,'name'=>'工厂'],
            ['id'=>5,'name'=>'其他']
        ];
        if ($id!=0 && !empty($id) && $id>0){
            $rtn = $rtn[$id-1];
        }
        return $rtn;
    }

    /**
     * 获取在线经验
     * @param int $id 经营模式ID：1-淘宝等国内在线零售平台，2-eBay等国际在线零售平台，3-阿里巴巴中国站等内贸平台，4-阿里巴巴国际站等外贸平台
     * @return array|mixed
     */
    public static function getOnlineExperience($id=0){
        $rtn = [
            ['id'=>1,'name'=>'淘宝等国内在线零售平台'],
            ['id'=>2,'name'=>'eBay等国际在线零售平台'],
            ['id'=>3,'name'=>'阿里巴巴中国站等内贸平台'],
            ['id'=>4,'name'=>'阿里巴巴国际站等外贸平台']
        ];
        if ($id!=0 && !empty($id) && $id>0){
            $rtn = $rtn[$id-1];
        }
        return $rtn;
    }

    /**
     * 获取Seller菜单
     * @param int $flag：标识 ''-获取全部菜单信息，不为空-获取指定flag的菜单信息
     * @return array
     */
    public static function getMenuInfo($flag=''){
        $menu_info = config('menus.menu_config');
        $rtn = $menu_info;
        if (!empty($flag)){
            foreach ($menu_info as $k=>$v){
                if ($v['parent_menu']['flag'] == $flag){
                    $rtn = $menu_info[$k];
                }
            }
        }
        return $rtn;
    }

    /**
     * 获取运费模板类型
     * 运费模板类型：10-Standard[标准物流运费]挂号，20-SuperSaver[经济物流运费]平邮，30-Expedited[快速物流运费]快递，40-Exclusive[专线物流运费]专线
     * @param null $id
     * @return array
     */
    public static function getShippingTamplateType($id=-1){
        $rtn = [
            ['id'=>10,'en_name'=>'Standard', 'cn_name'=>'标准物流运费'],
            ['id'=>20,'en_name'=>'SuperSaver', 'cn_name'=>'经济物流运费'],
            ['id'=>30,'en_name'=>'Expedited', 'cn_name'=>'快速物流运费'],
            ['id'=>40,'en_name'=>'Exclusive', 'cn_name'=>'专线物流运费']
        ];
        if ($id != -1 && $id>=0){
            foreach ($rtn as $key=>$val){
                if ($val['id'] == $id){
                    $rtn = $rtn[$key];
                    break;
                }
            }
        }
        return $rtn;
    }

    /**
     * 根据帮助ID获取产品帮助数据
     * @param $id ID
     * @return array|mixed
     */
    public static function getProductHelpDataByID($id=-1){
        $rtn = [
            ['id'=>1,'title'=>'产品品牌', 'content'=>'产品品牌描述'],
            ['id'=>2,'title'=>'产品标题', 'content'=>'产品标题描述'],
            ['id'=>3,'title'=>'产品图片', 'content'=>'产品图片描述'],
            ['id'=>4,'title'=>'产品关键字', 'content'=>'产品关键字描述'],
            ['id'=>5,'title'=>'计量单位', 'content'=>'计量单位描述'],
            ['id'=>6,'title'=>'销售方式', 'content'=>'销售方式描述'],
            ['id'=>7,'title'=>'零售价', 'content'=>'零售价描述'],
            ['id'=>8,'title'=>'产品详细描述', 'content'=>'产品详细描述描述'],
            ['id'=>9,'title'=>'产品包装后的重量', 'content'=>'产品包装后的重量描述'],
            ['id'=>10,'title'=>'自定义计重量', 'content'=>'自定义计重量描述'],
            ['id'=>11,'title'=>'产品包装后的尺寸', 'content'=>'产品包装后的尺寸描述'],
            ['id'=>12,'title'=>'产品组', 'content'=>'产品组描述'],
            ['id'=>13,'title'=>'产品有效期 ', 'content'=>'产品有效期描述'],
//            ['id'=>8,'title'=>'库存', 'content'=>'库存描述'],
        ];
        if ($id != -1 && $id>=0){
            foreach ($rtn as $info){
                if ($info['id'] == $id){
                    $rtn = $info;
                    break;
                }
            }
        }
        return $rtn;
    }

    /**
     * 根据产品类别数组获取产品分类ID
     * @param array $category 类别数组，格式如下：
     * Array
        (
            [FirstCategory] => 1
            [SecondCategory] => 2
            [ThirdCategory] => 5
            [FourthCategory] => 0
            [FifthCategory] => 0
        )
     * @return int
     */
    public static function getProductCategoryIdByCategoryArr(array $category){
        $new_arr = [];
        foreach ($category as $key=>$info){
            if ($info != 0 ){
                $new_arr[] = $info;
            }
        }
        if (!empty($new_arr)){
            return $new_arr[count($new_arr)-1];
        }else{
            return 0;
        }
    }

    /**
     * 获取活动类型
     * 1专题活动;2定期活动;3节日活动4促销活动
     * @return array
     */
    public static function getActivityType(){
        $rtn = [
            ['id'=>1, 'name'=>'专题活动'],
            ['id'=>2, 'name'=>'定期活动'],
            ['id'=>3, 'name'=>'节日活动'],
            ['id'=>4, 'name'=>'促销活动'],
            ['id'=>5, 'name'=>'Falsh Deals活动'],
        ];
        return $rtn;
    }

    /**
     * 获取结算状态
     * 结算状态：1 未生效、2 已结算、3 已提现
     * settlement_status
     * @return array
     */
    public static function getSettlementStatus($id=-1){
        $rtn = [
            ['id'=>1, 'name'=>'未生效'],
            ['id'=>2, 'name'=>'已结算'],
            ['id'=>3, 'name'=>'已提现'],
        ];
        if ($id !== -1){
            foreach ($rtn as $val) {
                if ($val['id'] == $id){
                    $rtn = $val;
                    continue;
                }
            }
        }
        return $rtn;
    }

    /**
     * 获取订单状态
     * @param int $code 订单状态码
     * @return mixed
     */
    public static function getOrderStatus($code=-1){
        //$rtn = config('order_status_data');
        $rtn = [];
        $base_api = new BaseApi();
        $status_data = $base_api->getSysCofig(['ConfigName' => 'OrderStatusView']);
        if (!empty($status_data)){
            /** 订单状态处理 start **/
            $OrderStatusViewStr = explode(";", $status_data);
            if(!empty($OrderStatusViewStr)){
                foreach ($OrderStatusViewStr as $key=>$value){
                    $OrderStatusViewArr[$key] = explode(":",$OrderStatusViewStr[$key]);
                    if($OrderStatusViewArr){
                        $rtn[$key]['code'] = $OrderStatusViewArr[$key][0];
                        $NameValue = explode('-',$OrderStatusViewArr[$key][1]);
                        $rtn[$key]['en_name'] = $NameValue[0];
                        $rtn[$key]['name'] = $NameValue[1];
                    }
                }
            }
            /** 订单状态处理 end **/
        }else{
            $rtn = config('order_status_data');
        }
        if ($code !== -1 && is_numeric($code)){
            $tem = [];
            foreach ($rtn as $key=>$val) {
                if ($val['code'] == $code){
                    $tem = $rtn[$key];
                    continue;
                }
            }
            $rtn = $tem;
        }
        return $rtn;
    }

    /**
     * 获取后台配置的状态
     * @return mixed
     */
    public static function getConfigStatus($ConfigName){
        //$rtn = config('order_status_data');
        $rtn = [];
        $base_api = new BaseApi();
        $status_data = $base_api->getSysCofig(['ConfigName' => $ConfigName]);
        if (!empty($status_data)){
            $StatusViewStr = explode(";", $status_data);
            if(!empty($StatusViewStr)){
                foreach ($StatusViewStr as $key=>$value){
                    $OrderStatusViewArr[$key] = explode(":",$StatusViewStr[$key]);
                    if($OrderStatusViewArr){
                        $rtn[$key]['code'] = $OrderStatusViewArr[$key][0];
                        $rtn[$key]['name'] = $OrderStatusViewArr[$key][1];
                    }
                }
            }
        }
        return $rtn;
    }

    /**
     * 根据币种简码获取币种符号
     * @param $currency_code 币种简码
     * @return string
     */
    public static function getCurrencyCodeStr($currency_code){
        $currency_code_str = '';
        $base_api = new BaseApi();
        if ($currency_code != 'USD'){
            $currency_info_api = $base_api->getCurrencyList();
            $currency_info = isset($currency_info_api['data'])&&!empty($currency_info_api['data'])?$currency_info_api['data']:[];
            foreach ($currency_info as $c_info){
                if ($c_info['Name'] == $currency_code){
                    $currency_code_str = $c_info['Code'];
                    break;
                }
            }
        }else{
            $currency_code_str = '$';
        }
        return $currency_code_str;
    }

    /**
     * 获取订单售后类型
     * @param int $type 类型：1换货，2退货 3退款
     * @return array|mixed
     */
    public static function getOrderAfterSaleType($type=-1){
        $rtn = [
            ['id'=>1,'name'=>'换货'],
            ['id'=>2,'name'=>'退货'],
            ['id'=>3,'name'=>'退款'],
        ];
        if ($type != -1 && $type>=0){
            foreach ($rtn as $info){
                if ($info['id'] == $type){
                    $rtn = $info;
                    break;
                }
            }
        }
        return $rtn;
    }

    /**
     * 获取订单售后-待处理倒计时
     * @param int $id
     * @return array|mixed
     */
    public static function getOrderAfterSaleCountDown($id=-1){
        //12天及以下、9天及以下、6天及以下，3天及以下、1天及以下
        $rtn = [
            ['id'=>1,'name'=>'12天及以下'],
            ['id'=>2,'name'=>'9天及以下'],
            ['id'=>3,'name'=>'6天及以下'],
            ['id'=>4,'name'=>'3天及以下'],
            ['id'=>5,'name'=>'1天及以下'],
        ];
        if ($id != -1 && $id>=0){
            foreach ($rtn as $info){
                if ($info['id'] == $id){
                    $rtn = $info;
                    break;
                }
            }
        }
        return $rtn;
    }

    /**
     * 获取纠纷状态
     * @param int $id 纠纷订单状态(1-待处理、2-卖家退款、3-生成退货单、4-申请失败)
     * @return array|mixed
     */
    public static function getOrderComplaintStatus($id=-1){
        $rtn = [
            ['id'=>1,'name'=>'待处理'],
            ['id'=>2,'name'=>'卖家退款'],
            ['id'=>3,'name'=>'生成退货单'],
            ['id'=>4,'name'=>'申请失败'],
        ];
        if ($id != -1 && $id>=0){
            foreach ($rtn as $info){
                if ($info['id'] == $id){
                    $rtn = $info;
                    break;
                }
            }
        }
        return $rtn;
    }

    /**
     * 获取批发询价送货方式
     * @param int $id 送货方式(1-Standard，2-Expedited，3-Other(e.g By own fowarder))
     * @return array|mixed
     */
    public static function getWholesaleInquiryShippingMethod($id=-1){
        $rtn = [
            ['id'=>1,'name'=>'Standard'],
            ['id'=>2,'name'=>'Expedited'],
            ['id'=>3,'name'=>'Other'],
        ];
        if ($id != -1 && $id>=0){
            foreach ($rtn as $info){
                if ($info['id'] == $id){
                    $rtn = $info;
                    break;
                }
            }
        }
        return $rtn;
    }

    /**
     * info:计算两个日期之间相差多少天
     * @param string $dayStart  开始时间
     * @param string $dayEnd   结束时间
     * @return number 两个日期相差天数
     */
    public static function daysBetween($dayStart,$dayEnd){
    	$a_dt = getdate($dayStart);
    	$b_dt = getdate($dayEnd);
    	$a_new = mktime(12, 0, 0, $a_dt['mon'], $a_dt['mday'], $a_dt['year']);
    	$b_new = mktime(12, 0, 0, $b_dt['mon'], $b_dt['mday'], $b_dt['year']);
    	return round(abs($a_new-$b_new)/86400);
    }

    /**
     * 获取邮件模板数据
     * @param $templet_value_id 模板ID【后台配置】
     * @param array $title_values 邮件标题要替换的数据
     * @param array $body_values 邮件内容要替换的数据
     * @param $type 邮件模板类型：1-Buyer，2-Seller
     * @return mixed
     */
    public static function getEmailTemplate($templet_value_id, array $title_values, array $body_values, $type=2){
        $base_api = new BaseApi();
        $where['type'] = $type;
        $where['templetValueID'] = $templet_value_id;
        $res = $base_api->getEmailTemplateData($where);
        $data = $res['data'][0];
        //邮件标题替换
        foreach ($title_values as $k => $v)
        {
            $data['title'] = str_replace('{'.$k.'}', $v, $data['title']);
        }
        //邮件内容替换
        foreach ($body_values as $k => $v)
        {
            $data['content'] = str_replace('{'.$k.'}', $v, $data['content']);
        }
        //邮件内容拼装公用头和尾
        $header_footer_where['templetValueID'] = 10;
        $header_footer_where['type'] = 1;
        $header_footer = $base_api->getEmailTemplateData($header_footer_where);
        //print_r($header_footer);die;
        $email_all = $header_footer['data'][0]['content'];
        $header_footer_values = ['sendtime'=>date("Y-m-d H:i:s"),'email_content'=>$data['content']];
        //邮件内容替换
        //$email_all = $header_footer_content;
        foreach ($header_footer_values as $k => $v)
        {
            $email_all = str_replace('{'.$k.'}', $v, $email_all);
        }
        $data['content'] = $email_all;
        Log::record('email-data:'.print_r($data, true));
        return $data;
    }

    /**
     * 根据seller_id判断是否已经参加联盟营销
     * @param $seller_id
     * @return bool
     */
    public static function AffiliateJudgeIsJoin($seller_id){
        $is_join = false;
        $base_api = new BaseApi();
        $data = $base_api->AffiliateJudgeIsJoin(['seller_id'=>$seller_id]);
        if ($data['code'] == API_RETURN_SUCCESS){
            if ($data['data'] == 1){
                $is_join = true;
            }
        }
        return $is_join;
    }

    /**
     * 订单产品描述特殊处理
     * @param $product_attr_desc
     * @return string
     * ram:8GB,color:Grey green|//photo.dxinterns.com/productimages/20180719/2332a466cea9bb36ec6569c675671797.png
     */
    public static function handleOrderProductaAttrDesc($product_attr_desc){
        $new_arr = [];
        $product_attr_desc_arr = explode(',', $product_attr_desc);
        foreach ($product_attr_desc_arr as $attr_info){
            $arr = explode('|', $attr_info);
            if (count($arr) >= 2){
                $arr[1] = '<img src="'.$arr[1].'">';
                $new_arr[] = implode('',$arr);
            }else{
                $new_arr[] = $attr_info;
            }
        }
        return implode(',',$new_arr);
    }
}
