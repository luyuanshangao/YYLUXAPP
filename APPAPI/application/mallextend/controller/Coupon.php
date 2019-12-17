<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\params\mallextend\coupon\CouponParams;
use app\mallextend\model\CouponModel;
use app\mallextend\model\ProductModel;
use think\Exception;
use think\Log;

/**
 * Coupon接口
 * Class Coupon
 * @author tinghu.liu 2018/5/11
 * @package app\seller\controller
 */
class Coupon extends Base
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 根据couponID获取coupon详情
     * @return array
     */
    public function getCouponByCouponId($paramData = ''){
        $paramData = !empty($paramData)?$paramData:request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CouponParams())->getCouponByCouponIdRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        $model = new CouponModel();
//        $data = $model->getCouponByWhere(['CouponId'=>(int)$paramData['CouponId']]);
        $data = $model->getCouponInfoByCouponId($paramData['CouponId']);
        if (!empty($data)){
            return apiReturn(['code'=>200, 'msg'=>'success', 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
    }

    /**
     * 根据couponID获取coupon code详情
     * @return array|mixed
     * [
     *   'CouponId'=>25,
     *   'flag'=>1, //1-获取全部，2-获取未领取的一个code
     * ]
     */
    public function getCouponCodeByCouponId($paramData = ''){
        try{
            $paramData = !empty($paramData)?$paramData:request()->post();
            //参数校验
            $validate = $this->validate($paramData,(new CouponParams())->getCouponCodeByCouponIdRules());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }
            $data = [];
            //标识：1-获取全部，2-获取未领取的一个code
            $paramData['flag'] = isset($paramData['flag']) && !empty($paramData['flag'])?$paramData['flag']:1;
            $model = new CouponModel();
            $coupon_id = (int)$paramData['CouponId'];
            switch ($paramData['flag']){
                case 1://获取全部
                    $data = $model->getCouponCodeDataByWhere(['CouponId'=>$coupon_id]);
                    break;
                case 2://获取未领取的一个code
                    //根据couponID读取coupon规则进行判断
                    $coupon_info = $model->getCouponInfoByCouponId($coupon_id,1);
                    //如果coupon有效则发送一个coupon code，并将这个coupon code 的状态修改为已领取 code状态：0-未领取，1-已领取
                    $code_data = $model->getCouponCodeDataByWhereForGet(['CouponId'=>$coupon_id,'Status'=>0]);
                    if (!empty($code_data)){
                        $data = $code_data[0];
                        //更新coupon code状态为已领取【如果coupon数量非“不限量”条件下，“不限量”只有一个复用code，不需修改状态】
                        if ($coupon_info['CouponNumLimit']['Type'] != 1){
                            $model->updateCouponCodeByWhere(
                                ['coupon_code'=>$data['coupon_code']],
                                ['Status'=>1]
                            );
                        }
                    }
                    break;
                default:break;
            }
            if (!empty($data)){
                return apiReturn(['code'=>200, 'msg'=>'success', 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'System exception '.$e->getMessage()]);
        }
    }


    /**
     * 根据多个couponID获取coupon详情
     * @return array
     */
    public function getCouponByCouponIds(){
        $paramData = request()->post();
        //参数校验
        /*$validate = $this->validate($paramData,(new CouponParams())->getCouponCodeByCouponIdRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }*/
        foreach ($paramData['CouponIds'] as $key=>$value){
            $paramData['CouponIds'][$key] = (int)$value;
        }
        $model = new CouponModel();
        $data = $model->getCouponDataByWhere(['CouponId'=>['in',$paramData['CouponIds']]]);
        if (!empty($data)){
            return apiReturn(['code'=>200, 'msg'=>'success', 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
    }

    /**
     * 获取coupon信息列表
     * @return array
     */
    public function getCouponList(){
        $paramData = request()->post();
        //参数校验
        if(isset($paramData['SellerId'])){
            $validate = $this->validate($paramData,(new CouponParams())->getCouponListRules());
            if(true !== $validate){
                return (['code'=>1002, 'msg'=>$validate]);
            }
            $model = new CouponModel();
            $data = $model->getCouponList($paramData);
        }else{
            $where['DiscountType.Type'] = isset($paramData['Type'])?(int)$paramData['Type']:'';
            $where['DiscountType.TypeOne'] = isset($paramData['TypeOne'])?['lt',(int)$paramData['TypeOne']+1]:'';
            $where['CouponRuleSetting.CouponRuleType'] = isset($paramData['CouponRuleType'])?(int)$paramData['CouponRuleType']:'';
            $model = new CouponModel();
            if(isset($paramData['CouponIds'])){
                $CouponId = array();
                foreach ($paramData['CouponIds'] as $key=>$value){
                    $CouponId[] = (int)$value;
                }
                $where['CouponId'] = ['in',$CouponId];
            }
            $where = array_filter($where);
            $data = $model->getExchangeCouponList($where);
            return $data;
        }

        if (!empty($data)){
            return apiReturn(['code'=>200, 'msg'=>'success', 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
    }

    /**
     * 新增coupon基本信息
     * @return array|mixed
     */
    public function addCoupon(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CouponParams())->addCouponRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }

        /**
         * 赠送券-赠送商品判断（判断产品是否属于当前seller && 产品状态为在售） start
         * 格式："SpuId:23;SkuId:1138;Qty:1,SpuId:23;SkuId:1139;Qty:1"
         */
        if (isset($paramData['DiscountType']['Type'])){
            //当coupon为赠送券类型时
            if ($paramData['DiscountType']['Type'] == 2){
                $model = new ProductModel();
                $product_id_arr = [];
                $seller_id = $paramData['SellerId'];
                $sku_str = $paramData['DiscountType']['TypeTwo']['Sku'];
                Log::record('$sku_str：'.$sku_str);
                $sku_arr = explode(',',$sku_str);
                foreach ($sku_arr as $info) {
                    $sku_arr_t = explode(';', $info);
                    $spu_str = $sku_arr_t[0];
                    //$sku_str = $sku_arr_t[1];
                    //$qty_str = $sku_arr_t[2];
                    $spu_arr = explode(':', $spu_str);
                    $product_id_arr[] = $spu_arr[1];
                }
                $product_id_arr = array_unique($product_id_arr);
                Log::record('$product_id_arr：'.print_r($product_id_arr, true));
                if (!empty($product_id_arr)){
                    $params['StoreID'] = $seller_id;
                    $params['ids'] = $product_id_arr;
                    $params['ProductStatus'] = [1,5];//已开通（正常销售）
                    $product_data = $model->getProductByParamsForCoupon($params);
                    Log::record('$params：'.print_r($params, true));
                    Log::record('$product_data：'.print_r(json_encode($product_data), true));
                    if (count($product_data) != count($product_id_arr)){
                        return apiReturn(['code'=>1003, 'msg'=>'您配置的产品没有正常销售 ，请重新配置']);
                    }
                }
            }
        }
        /**
         * 赠送券-赠送商品判断（判断产品是否属于当前seller && 产品状态为在售） end
         */

        //数据开始插入
        $model = new CouponModel();
        $res = $model->addCouponData($paramData);
        if ($res){
            return apiReturn(['code'=>200, 'msg'=>'success']);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'error']);
        }
    }

    /**
     * 新增coupon code信息
     * @return array|mixed
     */
    public function addCouponCode(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CouponParams())->addCouponCodeRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            /** 生成对应的coupon code 并保存 **/
            $all_insert_data = [];
            $time = time();
            //根据规则生成对应的coupon code
            $coupon_code_data = CommonLib::getCouponCodeData($paramData['code_num'], $paramData['rules']);
            foreach ($coupon_code_data as $info){//组装新增数据
                $temp = [];
                $temp['CouponId'] = (int)$paramData['CouponId'];
                $temp['coupon_code'] = $info;
                $temp['Status'] = 0; //code状态：0-未领取，1-已领取
                $temp['CreateBy'] = $paramData['CreateBy'];
                $temp['CreateTime'] = (int)$paramData['CreateTime'];
                $all_insert_data[] = $temp;
            }
            //保存coupon code 数据
            $model = new CouponModel();
            $res = $model->addCouponCodeAllData($all_insert_data);
            if ($res){
                return apiReturn(['code'=>200, 'msg'=>'success']);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'error']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 生成coupon code
     * @return array|mixed
     */
    public function getCouponCode(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CouponParams())->getCouponCodeRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = CommonLib::getCouponCodeData($paramData['code_num'], $paramData['rules']);
            return apiReturn(['code'=>200, 'msg'=>'success', 'data'=>$data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1003, 'msg'=>'程序异常 '.$e->getMessage()]);
        }

        /*$data = [];
        //生成的coupon code的个数
        $code_num = $paramData['code_num'];
        //规则，格式：$&*AFC。$：标识随机数字；&：表示随机字母；*：表示随机数字和字母；其他直接输出
        $rules = htmlspecialchars_decode($paramData['rules']);
        $rules_lenght = strlen($rules);
        if ($rules_lenght < 6 || $rules_lenght > 20){
            return apiReturn(['code'=>200, 'msg'=>'rules长度必须在6-20之间']);
        }
        //获取规则详情
        for ($i=1; $i<= $rules_lenght; $i++){
            $rules_arr[] = substr($rules , $i-1 , 1);
        }
        $model = new CouponModel();
        for ($j = 0; $j< $code_num; $j++){
            //生成coupon code
            $coupon_code = '';
            foreach ($rules_arr as $rules_info){
                //根据规则拼装单个字符
                $str = '';
                switch ($rules_info){
                    case '$'://随机数字
                        $str = rand(1,9);
                        break;
                    case '&'://随机字母
                        $str = get_random_key(1);
                        break;
                    case '*'://随机数字和字母
                        $flag = rand(1,2);
                        if ($flag == 1){
                            $str = rand(1,9);
                        }else{
                            $str = get_random_key(1);
                        }
                        break;
                    default://直接输出
                        $str = $rules_info;
                        break;
                }
                $coupon_code .= $str;
            }
            $coupon_code = strtoupper($coupon_code);
            //$coupon_code 做唯一性校验
            $c_data = $model->getCouponCodeDataByWhere(['coupon_code'=>$coupon_code]);
            if (!empty($c_data) || in_array($coupon_code, $data)){
                $j--;
            }else{
                $data[] = $coupon_code;
            }
        }*/

    }

    /**
     * 更新coupon信息
     * @return array
     */
    public function updateCouponData(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CouponParams())->updateDataRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new CouponModel();
            /** 当是修改为“审核通过”状态时，如果没有coupon code，则不允许修改 **/
            //更新标识：1-更新全部，2-更新coupon描述（含多语言），3-更新coupon状态
            $flag = $paramData['flag'];
            if ($flag == 3 && $paramData['CouponStatus'] == 2){
                $data = $model->getCouponCodeDataByWhere(['CouponId'=>(int)$paramData['CouponId']]);
                if (empty($data)){
                    return apiReturn(['code'=>1003, 'msg'=>'Coupon Code不能为空']);
                }
            }
            if ($model->updateCouponDataByParams($paramData)){
                return apiReturn(['code'=>200, 'msg'=>'success']);
            }else{
                return apiReturn(['code'=>1003, 'msg'=>'更新数据失败']);
            }
        }catch (\Exception $e){
            Log::record('updateCouponData 程序异常'.$e->getMessage());
            return apiReturn(['code'=>1003, 'msg'=>'程序异常'.$e->getMessage()]);
        }
    }

}
