<?php
namespace app\mall\services;

use app\common\helpers\CommonLib;
use app\mall\model\ConfigDataModel;
use app\mall\model\CouponModel;
use app\mall\model\ProductClassModel;
use app\mall\model\ProductModel;
use app\mall\model\SysConfigModel;
use think\Cache;
use think\Exception;


/**
 * Coupon接口
 */
class CouponService extends BaseService
{

    /**
     * 根据商家ID，skuID过滤出可用的coupon列表
     */
    public function getAvailableCoupon($params){
        $coupon = array();
        $lang = $params['Lang'];
    	$result = (new CouponModel())->getAvailableCoupon($params);
    	if(!empty($result)){
            //查询couponCode生成数量
            $coupon_id = CommonLib::getColumn('CouponId',$result);
            $code = (new CouponModel())->getCouponCodeCount($coupon_id);
            if(!empty($code)){
                foreach($result as $key => $value){
                    //拿到CouponCode
                    $codeData = CommonLib::filterArrayByKey($code,'_id',$value['CouponId']);
                    if(!empty($codeData)){
                        $result[$key]['CodeCount'] = $codeData['count'];
                    }
                    if(isset($value['Description'])){
                        //增加判断，没有找到多语种，默认使用英文；解决多语种找不到的情况下展示为coupon标题的情况 tinghu.liu 20190822
                        $is_match_lang = false;
                        $en_details = '';
                        foreach ($value['Description'] as $k => $v){
                            if($k == $lang){
                                $result[$key]['Description'] = $v['Details'];
                                $result[$key]['Name'] = $v['Details'];
                                $is_match_lang = true;
                            }
                            if ($k == 'en'){
                                $en_details = $v['Details'];
                            }
                        }
                        //没有找到多语种，默认使用英文
                        if (!$is_match_lang){
                            $result[$key]['Description'] = $en_details;
                            $result[$key]['Name'] = $en_details;
                        }
                    }
                }
            }
            return $result;
    	}
    	return $coupon;
    }

    /**
     * 根据商家ID，skuID过滤出可用的coupon列表
     */
    public function getCouponList($params){
        //根据当前条件，获取全部coupon数据
        $result = (new CouponModel())->getCouponList([
            'store_id'=>$params['store_id'],//当前门店
            'CouponChannels'=>$params['CouponChannels'],//优惠渠道：1-全站、2-Web站、
            'CouponStrategy'=>$params['CouponStrategy'],//手动，自动
            'ActivityStrategy'=>1,////活动策略：1-线上活动、2-线下活动
//            'DiscountLevel'=>"1",//1-单品级别优惠，2-订单级别优惠
            'lang'=>$params['lang']
        ]);
        if(!empty($result)){
            //查询couponCode生成数量
            $coupon_id = CommonLib::getColumn('CouponId',$result);
            $code = (new CouponModel())->getCouponCodeCount($coupon_id);
            if(!empty($code)){
                foreach($result as $key => $value){
                    //拿到CouponCode
                    $codeData = CommonLib::filterArrayByKey($code,'_id',$value['CouponId']);
                    if(!empty($codeData)){
                        $result[$key]['CodeCount'] = $codeData['count'];
                    }
                }
            }

            $data = array();
            $blackCoupon = array();//需要过滤的coupon
            //根据规则，获取可用coupon
            if(!empty($result)){
                //后台配置获取需要过滤的couponid,add by zhongning 20190429
                if(config('cache_switch_on')) {
                    $blackCoupon = $this->redis->get('CouponidNotAllowShow');
                }
                if(empty($blackCoupon)){
                    $blackCoupon = (new SysConfigModel())->getSysCofig('CouponidNotAllowShow');
                    if(!empty($blackCoupon['ConfigValue'])){
                        $this->redis->set('CouponidNotAllowShow',$blackCoupon,CACHE_DAY);
                    }
                }
                $blackCouponArray = !empty($blackCoupon['ConfigValue']) ? json_decode($blackCoupon['ConfigValue'],true) : [79,80,81,82];
                foreach($result as $k => $coupon){
                    if(THINK_ENV == CODE_RUNTIME_ONLINE){
                        if(in_array($coupon['CouponId'],$blackCouponArray)){
                            continue;
                        }
                    }

                    //手动conpon
                    if($coupon['CouponStrategy'] == 1){
                        $couponData = $this->analysisCouon($coupon,$params);
                        if(isset($couponData['description'])){
                            $data['manual'][$k] = $couponData;
                        }
                    }
                    //自动conpon
                    else{
                        $couponData = $this->analysisCouon($coupon,$params);
                        if(isset($couponData['description'])){
                            $data['auto'][$k] = $couponData;
                        }
                    }
                }
            }
            return $data;
        }
        return array();
    }

    /**
     * 更新状态
     */
    public function updateCodeStatus($params){
        $result = (new CouponModel())->updateCodeStatus($params);
        return $result;
    }

    /**
     * 查询当前已使用的CouponCode数量
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponCode($params){
        $result = (new CouponModel())->getCodeCount($params);
        return $result;
    }


    //解析cuopon
    private function analysisCouon($coupon,$params){
        $result = array();
        $lang = $params['lang'];
        //判断coupon是否有数量限制
        if($coupon['CouponNumLimit']['Type'] != 1){
            //特殊情况，coupon数量为1的情况,表示coupon无限可用
            if(!empty($coupon['CodeCount']) && $coupon['CodeCount'] != 1){
                //查询当前已使用的CouponCode数量
                $res = $this->getCouponCode(['coupon_id'=>$coupon['CouponId'],'status'=>1]);
                //领取数量 == 生成数量，coupon不可用
                if($res >= $coupon['CodeCount'] ){
                    return $result;
                }
            }
        }
        $result['coupon_id'] = $coupon['CouponId'];
        //手动领取coupno,默认所有的sku都符合规则，如果是指定商品，下面的规则会覆盖现在的数据
        if($coupon['CouponStrategy'] == 1){
            $skus = (new ProductModel())->getSkus($params['product_id']);
            if(!empty($skus)){
                $result['skus'] = CommonLib::getColumn('Code',$skus['Skus']);
            }
        }
        //Coupon活动规则
        $couponRule = $coupon['CouponRuleSetting'];
        if($couponRule['CouponRuleType'] == 1){
            //1-全店铺使用,3-全站使用
            $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
            $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
            //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
            if((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')){
                $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
            }
            $result['expires'] = 'Expires:'.date('Y/m/d',$coupon['CouponTime']['EndTime']);
        }elseif($couponRule['CouponRuleType'] == 2){
            //2-制定限制规则
            $LimitData = isset($coupon['CouponRuleSetting']['LimitData']) ? $coupon['CouponRuleSetting']['LimitData'] : '';
            if(empty($LimitData)){
                return array();
            }
            switch($LimitData['LimitType']){
                case 1://1-指定商品
                    //查找所有SKU
                    $currentSku = array();
                    $skus = (new ProductModel())->getSkus($params['product_id']);
                    if(!empty($skus)){
                        $currentSku = CommonLib::getColumn('Code',$skus['Skus']);
                    }
                    if(strpos($LimitData['Data'],"\n") != false){
                        $isCouponSku = explode("\n",$LimitData['Data']);
                    }else{
                        $isCouponSku = explode(",",$LimitData['Data']);
                    }
                    $IsReverse = !empty($LimitData['IsReverse']) ? $LimitData['IsReverse'] : 0;
                    //判断该SKU在不在指定商品内
                    $ret = array_intersect($currentSku,$isCouponSku);
                    //不取反，必须要有交集
                    if(!empty($ret) && $IsReverse == 0){
                        $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                            $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                        //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
                        if((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')){
                            $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
                        }
                        $result['expires'] = 'Expires:'.date('Y/m/d',$coupon['CouponTime']['EndTime']);
                        $result['skus'] = $isCouponSku;
                    }
                    //取反，必须没有交集
                    if(empty($ret) && $IsReverse == 1){
                        $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                            $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                        //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
                        if((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')){
                            $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
                        }
                        $result['expires'] = 'Expires:'.date('Y/m/d',$coupon['CouponTime']['EndTime']);
                        $result['skus'] = $isCouponSku;
                    }
                    break;
                case 2://2-指定分类
                    $classModel = new ProductClassModel();
                    $classArray = explode('-',$params['categoryPath']);
                    $newArray = $classArray;
                    if(!empty($classArray)){
                        foreach($classArray as $class){
                            $classData = $classModel->getClassDetail(['id'=>(int)$class]);
                            if(isset($classData['type']) && $classData['type'] == 1){
                                break;
                            }else{
                                if(isset($classData['pdc_ids']) && !empty($classData['pdc_ids'])){
                                    $newArray = array_merge($newArray,$classData['pdc_ids']);
                                }
                            }
                        }
                    }
                    if(strpos($LimitData['Data'],"\n") != false){
                        $ruleClassArray = explode("\n",$LimitData['Data']);
                    }else{
                        $ruleClassArray = explode(",",$LimitData['Data']);
                    }
                    $IsReverse = !empty($LimitData['IsReverse']) ? $LimitData['IsReverse'] : 0;
                    $ret = array_intersect($newArray, $ruleClassArray);
                    //不取反，必须要有交集
                    if($IsReverse == 0 && !empty($ret)) {
                        $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                            $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                        //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
                        if ((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')) {
                            $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
                        }
                        $result['expires'] = 'Expires:' . date('Y/m/d', $coupon['CouponTime']['EndTime']);
                    }
                    //取反，必须没有交集
                    if($IsReverse == 1 && empty($ret)){
                        $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                            $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                        //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
                        if ((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')) {
                            $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
                        }
                        $result['expires'] = 'Expires:' . date('Y/m/d', $coupon['CouponTime']['EndTime']);
                    }
                    break;
                case 3://3-指定品牌
                    //判断该spu的品牌ID，是否在指定品牌内
                    if(in_array($params['brand_id'],explode(',',$LimitData['Data']))){
                        $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                            $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                        $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                            htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                        //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
                        if((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')){
                            $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
                        }
                        $result['expires'] = 'Expires:'.date('Y/m/d',$coupon['CouponTime']['EndTime']);
                    }
                    break;
//                case 4://4-指定产品类型
//                    $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
//                        $coupon['Description'][$lang]['Details'] :$coupon['Description']['en']['Details'];//其他语种如果为空，默认取英文
//                    $result['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
//                    break;
                case 5://5-指定国家
                    //指定国家只能在下单选择地址才能指定是否能用这个优惠
                    $result['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                        $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                    $result['description'] = isset($coupon['Description'][$lang]['Details']) ?
                        htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                    //小于30天的按正常有效截至时间展示，大于30天的按30天截至展示 --  刘凯
                    if((int)$coupon['CouponTime']['EndTime'] > strtotime('+1 month')){
                        $coupon['CouponTime']['EndTime'] = strtotime('+1 month');
                    }
                    $result['expires'] = 'Expires:'.date('Y/m/d',$coupon['CouponTime']['EndTime']);
                    break;
            }
        }
        return $result;
    }


    /**
     * 获取coupon详情
     */
    public function getCouponInfoByCouponId($params){
        $coupon = array();
        $result = (new CouponModel())->getCouponInfoByCouponId($params);
        if(!empty($result)){
            return $result;
        }
        return $coupon;
    }

    public function addCoupon($params){
        $couponCode = '';
        //获取coupon规则
        $couponRule = (new CouponModel())->findCoupon(['coupon_id'=>$params['coupon_id'],'lang'=>$params['lang']]);
        if(!empty($couponRule)){
            //领取限制
            $type = $couponRule['ReceiveLimit'];
            switch($type){
                case 1://1-不限
                    $CodeCount = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>[0,1]]);
                    //特殊情况，coupon数量为1的情况,表示coupon无限可用
                    if(count($CodeCount) == 1){
                        $couponCode = end($CodeCount)['coupon_code'];
                    }else{
                        $codeList = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>0]);
                        $couponCode = end($codeList)['coupon_code'];
                    }
                    break;
                case 2://2-每人一次
                    $res = doCurl(CIC_API.'cic/MyCoupon/getUserCouponCode',
                        ['customer_id'=>$params['customer_id'],'coupon_id'=>$params['coupon_id']],null,true);
                    if($res['code'] == 200){
                        if(!empty($res['data'])){
                            return apiReturn(['code'=>5010004,'msg'=>"You've received the coupon already!"]);
                        }
                    }
                    $codeList = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>0]);
                    $couponCode = end($codeList)['coupon_code'];
                    break;
                case 3://3-每人每天一次
                    //判断今天内是否领取
                    $start_time = strtotime(date('Y-m-d',time()));
                    $end_time = strtotime(date('Y-m-d',strtotime('+1 days')));
                    $res = doCurl(CIC_API.'cic/MyCoupon/getUserCouponCode',
                        ['customer_id'=>$params['customer_id'],'coupon_id'=>$params['coupon_id'],'add_time'=>['between',[$start_time,$end_time]]],null,true);
                    if($res['code'] == 200) {
                        if (!empty($res['data'])) {
                            return apiReturn(['code' => 5010003, 'msg' => "You've received the coupon today, please come back tomorrow!"]);
                        }
                    }
                    //判断领取过的coupon_code不能重复
                    $codeList = (new CouponModel())->getCouponCode(['coupon_id'=>$params['coupon_id'],'status'=>0]);
                    $couponCode = end($codeList)['coupon_code'];
                    break;
            }
        }
        if(empty($couponCode)){
            return apiReturn(['code'=>5010002,'msg'=>'Sorry, you failed to get the coupon!']);
        }
        //coupon已领取
        (new CouponModel())->updateCodeStatus(['coupon_code'=>$couponCode,'coupon_id'=>$params['coupon_id']]);

        //新增coupo
        $res = doCurl(CIC_API.'cic/MyCoupon/mallAddCoupon', [
            'coupon_id'=>$params['coupon_id'],
            'customer_id'=>$params['customer_id'],
            'coupon_code'=>$couponCode,
            'EndTime'=>$couponRule['CouponTime']['EndTime'],
            'CouponChannels'=>$couponRule['CouponChannels'],
        ],null,true);
        if($res['code'] == 200) {
            return apiReturn(['code'=>5010001,'msg'=>'Congratulations! You received this coupon!']);
        }else{
            return apiReturn(['code'=>5010002,'msg'=>'Sorry, you failed to get the coupon!']);
        }
    }

    /**
     * 商城首页conpon
     */
    public function getHomeCouponList($params){
        $couponData = array();
        if(!empty($params['coupon_ids'])){
            $couponData = (new CouponModel())->getCouponList([
                'coupon_id'=> $params['coupon_ids'],
                'lang'=>$params['lang']
            ]);
            $couponData = array_column($couponData, null, 'CouponId');
        }
        if(count($couponData) < 3){
            $result = (new CouponModel())->getCouponList([
                'CouponChannels'=> [1,2],//优惠渠道：1-全站、2-Web站、
                'CouponStrategy'=> 1,//手动
                'ActivityStrategy'=>1,//活动策略：1-线上活动、2-线下活动
                'lang'=>$params['lang']
            ]);
            if(!empty($result)){
                foreach($result as $val){
                    if(count($couponData) < 3){
                        if(!isset($couponData[$val['CouponId']])){
                            $couponData[$val['CouponId']] = $val;
                        }
                    }
                }
            }
        }
        if(!empty($couponData)){
            $lang = $params['lang'];
            $result = array();
            foreach($couponData as $k => $coupon){
                $result[$k]['coupon_id'] = $coupon['CouponId'];
                $result[$k]['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                    $coupon['Description'][$lang]['Brief'] : $coupon['Description'][DEFAULT_LANG]['Brief'];
                $result[$k]['description'] = isset($coupon['Description'][$lang]['Details']) ?
                    $coupon['Description'][$lang]['Details'] : $coupon['Description'][DEFAULT_LANG]['Details'];
                $result[$k]['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
            }
            return $result;
        }else{
            return array();
        }
    }

    /**
     * 商城首页conpon文案展示
     */
    public function getCouponListByIds($params){
        $couponData = array();
        if(!empty($params['coupon_ids'])){
            $couponData = (new CouponModel())->selectCouponInfo([
                'coupon_id'=> $params['coupon_ids'],
                'lang'=>$params['lang']
            ]);
            $couponData = array_column($couponData, null, 'CouponId');
        }
        if(!empty($couponData)){
            $lang = $params['lang'];
            $result = array();
            foreach($couponData as $k => $coupon){
                $result[$k]['coupon_id'] = $coupon['CouponId'];
                $result[$k]['brief'] = isset($coupon['Description'][$lang]['Brief']) ?
                    htmlspecialchars_decode($coupon['Description'][$lang]['Brief']) : $coupon['Description'][DEFAULT_LANG]['Brief'];
                $result[$k]['description'] = isset($coupon['Description'][$lang]['Details']) ?
                    htmlspecialchars_decode($coupon['Description'][$lang]['Details']) : $coupon['Description'][DEFAULT_LANG]['Details'];
                $result[$k]['expires'] = date('Y/m/d',$coupon['CouponTime']['EndTime']);
                //手动conpon
                if($coupon['CouponStrategy'] == 1){
                    $result[$k]['coupon_type'] = 1;
                }else{
                    $result[$k]['coupon_type'] = 2;
                }
            }
            return $result;
        }else{
            return array();
        }
    }
}
