<?php
namespace app\admin\controller;

use app\admin\dxcommon\BaseApi;
use app\admin\model\AffiliateOrder;
use app\admin\statics\BaseFunc;
use app\common\helpers\CommonLib;
use app\common\params\admin\ActivityParams;
use app\common\params\admin\AffiliateParams;
use think\Controller;
use think\Exception;
use think\Log;
use app\admin\model\Affiliate as AffiliateModel;

/**
 * 联盟营销接口
 * Class Affiliate
 * @author heng.zhang 2018/5/25
 * @package app\admin\controller
 */
class Affiliate extends Controller
{
    /**
     * 插入佣金数据--默认佣金
     * @return mixed
     */
    public function addCommission()
    {
    	try{
        $param = request()->post();
        if(empty($param)){
        	return apiReturn(['code'=>1001,'msg'=>'参数错误']);
        }

        $id = 0; //type=2且add=2，则ID不可以为空--编辑数据
        $type =  1; //默认是默认佣金数据
        $sellerID = 0;
        $class_id = 0; //类别ID
        $effect_time = 0; //生效时间
        $min_commission = 0.03;
        $max_commission = 0.05;

        if(isset($param['type']) && $param['type']>0){
            $type = $param['type'];
        }else{
            return apiReturn(['code'=>1004,'msg'=>'参数错误-数据类型type错误']);
        }

        if(isset($param['seller_id'])){
            $sellerID = $param['seller_id'];
        }else{
            return apiReturn(['code'=>1005,'msg'=>'参数错误-系统异常']);
        }

        //判断seller下是否有在售产品，没有则不能添加
        $base_api = new BaseApi();
        $have_pro = $base_api->judgeIsHaveProductByParams(['StoreID'=>$sellerID, 'ProductStatus'=>[1,5]]);
        Log::record('$have_pro->'.print_r($have_pro, true));
        if (!isset($have_pro['is_have'])){
            return apiReturn(['code'=>1012,'msg'=>'内部错误，请重试'.$have_pro['msg']]);
        }else{
            if ($have_pro['is_have'] == 2){
                return apiReturn(['code'=>1013,'msg'=>'错误操作，没有符合条件的产品']);
            }
        }

        if($type ==2){ //按类别设置佣金类别ID和生效时间必传
            if(isset($param['class_id'])){
                $class_id = $param['class_id'];
            }else{
                return apiReturn(['code'=>1006,'msg'=>'参数错误-类别ID错误']);
            }

        	if(isset($param['effect_time']) && $param['effect_time']>0){
        		$effect_time = $param['effect_time'];
        	}else{
        		return apiReturn(['code'=>1007,'msg'=>'参数错误-有效期错误']);
        	}

        	if(isset($param['op_type']) && $param['op_type']==2){
        		if(isset($param['id']) && $param['id']>0){
        			$id = $param['id'];
        		}else{
        			return apiReturn(['code'=>1008,'msg'=>'参数错误-ID错误']);
        		}
        	}
        	//佣金比例：文本框，必填,值的限制范围5.0%-50.0%；佣金比例只能是0.5的倍数；设置的分类佣金比例不能低于平台分类佣金比例（在非默认值情况下）
            if ($class_id != -99){
                $min_commission= 0.03;
                $max_commission= 0.5;

                //根据分类判断，佣金不能低于默认分类佣金比例
                $model = new AffiliateModel();
                $default_class = $model->getClassDefaultInfoByClassId($class_id);
                if ($param['commission'] < $default_class['commission']){
                    return apiReturn(['code'=>1009,'msg'=>'佣金比例不能低于分类佣金比例'.($default_class['commission']*100).'%']);
                }
            }
        }

        $default_commission = $param['commission'];
        if($default_commission >= $min_commission && $default_commission <= $max_commission  ){
        	if(($default_commission*1000 % 5) == 0){
        		try{
        			$data = null;
        			$affiliateModel = new AffiliateModel();
        			if($type ==1){
        				$data = $affiliateModel -> addDefaultCommission($param);
        			}elseif($type ==2){
        				$data = $affiliateModel -> addClassCommission($param);
        			}
        			if($data ===200){
        		    	return apiReturn(['code'=>200, 'msg'=>$data]);
        			}else{
        				Log::record('操作失败：'.print_r($data, true));
        				return apiReturn(['code'=>1011, 'msg'=>'操作失败:'.$data.',$type:'.$type]);
        			}
        		}catch (\Exception $e){
        			return apiReturn(['code'=>2001, 'msg'=>$e -> getMessage()]);
        		}
        	}else{
        		return apiReturn(['code'=>1003,'msg'=>'佣金比例只能是0.5的倍数！']);
        	}
        }else{
        	return apiReturn(['code'=>1002,'msg'=>'佣金比例设置错误！']);
        }
      }catch (\Exception $e){
         return apiReturn(['code'=>5000, 'msg'=>$e -> getMessage()]);
     }
    }

    /**
     * 获取分类默认佣金比例
     * @return mixed
     */
    public function getClassDefaultCommission(){
        $param = request()->post();
        $where = array();
        if(!empty($param['parent_id'])){
            $where['parent_class'] = $param['parent_id'];
        }
        $model = new AffiliateModel();
        $data = $model->getClassDefaultData($where);
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 删除佣金比例数据
     * @return mixed
     */
    public function deleteCommission(){
        $param = request()->post();
        if(empty($param) || !isset($param['id']) || empty($param['id']) || $param['id'] < 0 ){
            return apiReturn(['code'=>1001,'msg'=>'参数错误']);
        }
        $model = new AffiliateModel();
        if ($model->deleteCommissionById($param['id'])){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'删除失败']);
        }
    }

    /**
     *根据seller id 获取默认佣金比例
     *return 单条数据
     */
    public function getDefaultCommissionBySellerID(){
    	$param = request()->post();
        if(empty($param) || !isset($param['seller_id']) || empty($param['seller_id']) || $param['seller_id'] < 0 ){
            return apiReturn(['code'=>1001,'msg'=>'参数错误']);
        }
    	$model = new AffiliateModel();
    	$data = $model->getDefaultCommissionBySellerID($param['seller_id']);
    	if (!empty($data)){
    		$status_text ='';
    		switch($data['status']){
    			case 0:
    				$status_text = '待审核';
    				break;
    			case 1:
    				$status_text = '审核通过';
    				break;
    			case 2:
    				$status_text = '审核不通过';
    				break;
    		}
    		$data['status_text'] = $status_text;
    		return apiReturn(['code'=>200, 'data'=>$data]);
    	}else{
    		return apiReturn(['code'=>1002, 'msg'=>'获取数据异常']);
    	}
    }

    /**
     * 获取分类佣金配置列表
     * @return mixed
     */
    public function getClassCommissionList(){
        $param = request()->post();
        $validate = $this->validate($param,(new AffiliateParams())->getClassCommissionListRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new AffiliateModel();
        $data = $model->getClassCommissionList($param);
        if (!empty($data)){
            return apiReturn(['code'=>200, 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'获取数据失败']);
        }
    }

    /**
     * 增加联盟营销产品数据
     * @return mixed
     */
    public function addAffiliateProduct(){
        $param = request()->post();
        $model = new AffiliateModel();
        foreach ($param as $info) {
            //数据类型:1 非主推产品; 2 主推产品;
            $type = $info['type'];
            $validate = $this->validate($info,(new AffiliateParams())->addAffiliateProductRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //根据分类判断，佣金不能低于默认分类佣金比例
            $default_class = $model->getClassDefaultInfoByClassId($info['class_id']);
            if ($info['commission'] < $default_class['commission']){
                return apiReturn(['code'=>1002,'msg'=>'佣金比例不能低于分类佣金比例']);
            }
            //判断主推产品是否超过数量限制
            if ($type == 2){
                $main_product_limit = config('affiliate_main_product_num_limit');
                $count = $model->getProductCountByWhere(['seller_id'=>$info['seller_id'], 'type'=>2]);
                if ($count >= $main_product_limit){
                    return apiReturn(['code'=>1002,'msg'=>'主推产品数量不能超过'.$main_product_limit]);
                }
            }
        }
        if ($model->addAffiliateProductForBatch($param)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'新增失败']);
        }
    }

    /**
     * 更新联盟营销产品数据
     * @return mixed
     */
    public function updateAffiliateProduct(){
        $param = request()->post();
        $model = new AffiliateModel();
        foreach ($param as $info) {
            $validate = $this->validate($info,(new AffiliateParams())->updateAffiliateProductRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //根据分类判断，佣金不能低于默认分类佣金比例
            $default_class = $model->getClassDefaultInfoByClassId($info['class_id']);
            if ($info['commission'] < $default_class['commission']){
                return apiReturn(['code'=>1002,'msg'=>'佣金比例不能低于分类佣金比例']);
            }
        }
        if ($model->updateAffiliateProductForBatch($param)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>'修改失败']);
        }
    }

    /**
     * 获取主推产品数量情况
     * @return mixed
     */
    public function getMainProductNum(){
        $param = request()->post();
        $validate = $this->validate($param,(new AffiliateParams())->getMainProductNumRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $seller_id = $param['seller_id'];
            //$main_product_num_limit = config('affiliate_main_product_num_limit');
            $data = [];
            $model = new AffiliateModel();
            $base_api = new BaseApi();
            $count = $model->getProductCountByWhere(['seller_id'=>$seller_id, 'type'=>2]);
            //数量上限
            $limit_data = $base_api->getSysCofig(['ConfigName' => 'AffiliateMainProductPumLimit']);
            $main_product_num_limit = isset($limit_data['data']) && !empty($limit_data['data'])?$limit_data['data']:60;
            $data['limit_num'] = $main_product_num_limit;
            //已经有的数量
            $data['have_num'] = $count;
            //还能添加的数量
            $data['can_num'] = ($main_product_num_limit - $count)>0?$main_product_num_limit - $count:0;
            return apiReturn(['code'=>200, 'data'=>$data]);
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常']);
        }
    }

    /**
     * 获取联盟营销产品列表【分页】
     * @return mixed
     */
    public function getAffiliateProductList(){
        $param = request()->post();
        $validate = $this->validate($param,(new AffiliateParams())->getAffiliateProductListRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new AffiliateModel();
            $data = $model->getProductList($param);
            if (!empty($data)){
                return apiReturn(['code'=>200, 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'获取数据失败']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 统计卖家上架产品的数据量（审核通过）
     */
    public function countAffiliateProduct(){
    	$param = request()->post();
    	try{
    		$seller_id = $param['seller_id'];
    		if(empty($seller_id)){
    			return apiReturn(['code'=>1003,  'msg'=>'参数错误']);
    		}
    		$main_product_num_limit = config('affiliate_main_product_num_limit');
    		$data = [];
    		$model = new AffiliateModel();
    		$count = $model->getProductCountByWhere(['seller_id'=>$seller_id, 'ProductStatus'=>1]);#正常在售SKU
    		//已经有的数量
    		$data['have_num'] = $count;
    		return apiReturn(['code'=>200, 'data'=>$data]);
    	} catch (\Exception $e){
    		return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
    	}
    }

    /**
     * 判断seller是否已经加入联盟营销
     * @return mixed
     */
    public function judgeIsJoin(){
        $param = request()->post();
        $validate = $this->validate($param,(new AffiliateParams())->judgeIsJoinRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $seller_id = $param['seller_id'];
            $model = new AffiliateModel();
            $data = $model->getCommissionDataByWhere(['seller_id'=>$seller_id, 'type'=>1,'status'=>1]);
            if (!empty($data)){
                //已加入
                $is_join = 1;
            }else{
                //未加入
                $is_join = 2;
            }
            return apiReturn(['code'=>200, 'data'=>$is_join]);
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
        }
    }

    /**
     * 获取affiliate订单列表【分页】
     * @return mixed
     */
    public function getAffiliateOrderList(){
        $param = request()->post();
        $validate = $this->validate($param,(new AffiliateParams())->getAffiliateOrderListRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new AffiliateOrder();
            $data = $model->getOrderList($param);
            return apiReturn(['code'=>200, 'data'=>$data]);
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
        }
    }

    /**
     * 根据affiliate订单ID获取affiliate订单详情
     * @return mixed
     */
    public function getAffiliateOrderInfoById(){
        $param = request()->post();
        $validate = $this->validate($param,(new AffiliateParams())->getAffiliateOrderInfoByIdRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new AffiliateOrder();
            $data = $model->getOrderInfoById($param['affiliate_order_id']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        } catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常'.$e->getMessage()]);
        }
    }

    /**
     * 获取分类默认佣金比例
     * @return mixed
     */
    public function getCommissionByClassId(){
    	$params = request()->post();
    	$model = new AffiliateModel();
    	//return $params;
    	$data = $model->getCommissionByClassId($params);
    	if($data){
    		return apiReturn(['code'=>200, 'data'=>$data]);
    	}else{
    		return apiReturn(['code'=>1002, 'msg'=>'系统异常']);
    	}

    }

    /**
     * 插入佣金信息
     * @return mixed
     */
    public function insertAffiliateData(){
    	$params = request()->post();
    	$model = new AffiliateModel();
    	//
    	$data = $model->insertAffiliateData($params);
    	if($data){
    		return apiReturn(['code'=>200, 'data'=>$data]);
    	}else{
    		return apiReturn(['code'=>1002, 'msg'=>'系统异常']);
    	}

    }

    /**
     * 判断佣金是否已写入
     * @return mixed
     */
    public function checkAffiliateByOrderNumber(){
        try {
            $params = request()->post();
            $model = new AffiliateModel();
            //
            $data = $model->checkAffiliateByOrderNumber($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1003, 'msg'=>'select data exception:'.$e->getMessage()]);
        }
    }

    /**
     * 更新affiliate订单状态
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateAffiliateOrderStatus(){
        try{
            $params = request()->post();
            if (
                !isset($params['order_number']) || empty($params['order_number'])
                || !isset($params['order_status']) || empty($params['order_status'])
            ){
                return apiReturn(['code'=>1003, 'msg'=>'缺少参数']);
            }
            $model = new AffiliateModel();
            $where['order_number'] = $params['order_number'];
            $up_data['order_status'] = $params['order_status'];
            $data = $model->updateAffiliateOrder($up_data, $where);
            if($data){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'修改失败']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003, 'msg'=>'修改affiliate状态异常 '.$e->getMessage()]);
        }
    }

    /**
     * 获取affiliate佣金列表
     * @return mixed
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function getAffiliateCommission(){
        try{
            $params = request()->post();
            if (
                !isset($params['affiliate_id']) || empty($params['affiliate_id'])
            ){
                return apiReturn(['code'=>1001]);
            }
            $model = new AffiliateModel();
            $where['affiliate_id'] = $params['affiliate_id'];
            if(isset($params['order_number']) && !empty($params['order_number'])){
                $where['order_number'] = $params['order_number'];
            }
            if(isset($params['settlement_status']) && !empty($params['settlement_status'])){
                $where['settlement_status'] = $params['settlement_status'];
            }
            if(isset($params['first_category_id']) && !empty($params['first_category_id'])){
                $where['first_category_id'] = $params['first_category_id'];
            }
            if(isset($params['create_on_start']) && isset($params['create_on_end'])){
                $where['o.add_time'] = ["between",[strtotime($params['create_on_start']),strtotime($params['create_on_end'])]];
            }else{
                /*成交开始时间*/
                if(isset($params['create_on_start'])){
                    $where['o.add_time'] = ['gt',strtotime($params['create_on_start'])];
                }
                /*成交结束时间*/
                if(isset($params['create_on_end'])){
                    $where['o.add_time'] = ['lt',strtotime($params['create_on_end'])];
                }
            }
            $is_page = input("is_page",1);
            $page_size = input('page_size',20);
            $page = input("page",1);
            $path = input("path");
            $data = $model->getAffiliateCommission($where,$page_size,$page,$path,$is_page);
            if($data){
                return apiReturn(['code'=>200,'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }
    /*获取affiliate佣金集合*/
    public function getAffililateCommissionData(){
        try{
            $params = request()->post();
            if (
                !isset($params['affiliate_id']) || empty($params['affiliate_id'])
            ){
                return apiReturn(['code'=>1001]);
            }
            if(is_array($params['affiliate_id'])){
                $where['affiliate_id'] = ['in',$params['affiliate_id']];
            }else{
                $where['affiliate_id'] = $params['affiliate_id'];
            }
            $model = new AffiliateModel();
            $data = $model->getAffililateCommissionData($where);
            if($data){
                return apiReturn(['code'=>200,'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }
    public function InvalidOrder(){
        $params = request()->post();
        if(empty($params['order_number'])){
            return apiReturn(['code'=>1002]);
        }
        $model = new AffiliateModel();
        $result = $model->InvalidOrder($params);
        return $result;
    }
    /**
     * 后台admin导出
     * [ReportStatistics description]
     * @auther wang  2019-05-09
     */
    public function ReportStatistics(){
           $data = request()->post();
           if(empty($data)){
                  return apiReturn(['code'=>1002,'msg'=>'空参数']);
           }

           if(!empty($data['affiliate_id'])){
                  $affiliate_id = QueryFiltering($data['affiliate_id']);
                  $data_page['affiliate_id'] = $data['affiliate_id']  = $affiliate_id;
                  $where['affiliate_id'] = array('in',$affiliate_id);
                  if(empty($data['_id'])){ $data['_id'] = 0; }
                  $where['affiliate_order_id'] = ['gt',$data['_id']];
            }else{
                  if(empty($data['_id'])){
                       $where['affiliate_order_id'] = ['gt',0];
                  }else{
                       $where['affiliate_order_id'] = ['gt',$data['_id']];
                  }
            }
            if(!empty($data['order_number'])){
                  $affiliate_id = QueryFiltering($data['order_number']);
                  $data_page['order_number'] = $data['order_number']  = $affiliate_id;
                  $where['order_number'] = array('in',$affiliate_id);
            }
            if(!empty($data['sku_id'])){
                  $data['sku_code'] = $affiliate_id = QueryFiltering($data['sku_id']);
                  $data_page['sku_id'] = $data['sku_code']  = $affiliate_id;
                  $where_sku['sku_code'] = array('in',$affiliate_id);
            }
            if(!empty($data['settlement_status'])){
                  $data_page['settlement_status'] = $data['settlement_status'];
                  $where['settlement_status'] = $data['settlement_status'];
            }
            if(!empty($data['startTime']) && !empty($data['endTime'])){
                $data_page['startTime'] = $data['startTime'];
                $data_page['endTime']   = $data['endTime'];
                $startTime = strtotime($data['startTime']);
                $endTime   = strtotime($data['endTime']);
                $where_sku['create_on'] = $where['create_on'] = array('between',$startTime.','.$endTime);
            }else{
                $data['startTime'] =  date('Y-m-d 00:00:00', strtotime("-1 month -1 day 00:00:00"));
                $data['endTime']   =  date('Y-m-d 23:59:59', strtotime("-1 day 23:59:59"));
                $where_sku['create_on'] = $where['create_on'] = array('between',strtotime("-1 month -1 day 00:00:00").','.strtotime("-1 day 23:59:59"));
            }
            $model = new AffiliateModel();
            if(!empty($where_sku['sku_code'])){
                $data = $model->ReportStatistics_sku($where,$data,$where_sku);
            }else{
                $type = isset($data['type'])?$data['type']:1;
                $data = $model->ReportStatistics($where,$data,$where_sku,$type);
            }
            return apiReturn(['code'=>200,'data'=>$data]);
    }

    public function OrderAffiliateCredit(){
        $Affiliate_id = input("Affiliate_id");
        $res = model("Affiliate")->OrderAffiliateCredit($Affiliate_id);
        return $res;
    }


    /**
     *  获取毛利率
     *  return 单条数据
     */
    public function getProductProfit(){
        $param = input();
        $model = new AffiliateModel();

        $payfees = 0.045;
        $exchangerate_us = 7;
        $result = array();
        if(empty($param) || !isset($param['spu']) || !is_array($param['spu'])){
            return apiReturn(['code'=>1001,'msg'=>'参数错误']);
        }

        foreach($param['spu'] as $spu){
            $result[$spu]['spu'] = $spu;
            $result[$spu]['profit'] = 0;
            $sale_price_arr = array();
            $data = $model->getProductProfitNew(['spu' => $spu]);
            if(!empty($data)){
                foreach($data as $p => $productProfitNew){
                    $sale_price = 0;
                    if(!empty($productProfitNew['country_prices'])){
                        $country_prices = explode('|',$productProfitNew['country_prices']);
                        foreach($country_prices as $ckey => $country_val){
                            $country_prices[$ckey] = explode(',',$country_val);
                        }
                        $es_price = CommonLib::filterArrayByKey($country_prices,'0','ES');
                        $sale_price = !empty($es_price[1]) ? $es_price[1] : 0;
                    }
                    $sale_price_arr[$sale_price]['sale_price'] = $sale_price;
                    $sale_price_arr[$sale_price]['weight'] = $productProfitNew['weight'];
                    $sale_price_arr[$sale_price]['unitcost'] = $productProfitNew['unitcost'];
                }
                ksort($sale_price_arr);
                $curr_data = array_shift($sale_price_arr);
            }else{
                $data = $model->getProductProfit(['spu' => $spu]);
                if(!empty($data)){
                    foreach($data as $p => $productProfitNew) {
                        if (!empty($productProfitNew['country_prices'])) {
                            $country_prices = explode('|', $productProfitNew['country_prices']);
                            foreach ($country_prices as $ckey => $country_val) {
                                $country_prices[$ckey] = explode(',', $country_val);
                            }
                            $es_price = CommonLib::filterArrayByKey($country_prices, '0', 'ES');
                            $sale_price = !empty($es_price[1]) ? $es_price[1] : 0;
                        }
                        $sale_price_arr[$sale_price]['sale_price'] = $sale_price;
                        $sale_price_arr[$sale_price]['sku'] = $productProfitNew['sku_code'];
                    }
                    ksort($sale_price_arr);
                    $curr_data = array_shift($sale_price_arr);
                    //获取采购价
                    $purchase = $model->getProductPurchaseCost(['sku' => $curr_data['sku']]);
                    if(!empty($purchase)){
                        $curr_data['unitcost'] = $purchase['now_unitcost'];
                        $curr_data['weight'] = $purchase['now_weight'];
                    }
                }
            }
            if(!empty($curr_data['sale_price']) && !empty($curr_data['unitcost'])){
                $result[$spu]['profit'] = (string)round(($curr_data['sale_price'] * $exchangerate_us - $curr_data['unitcost'] - ($curr_data['weight'] * 82 + 2) -
                        $curr_data['sale_price'] * $exchangerate_us * $payfees) / ($curr_data['sale_price'] * $exchangerate_us * (1 - $payfees)),2);
            }
        }
        return apiReturn(['code'=>200, 'data'=>array_values($result)]);
    }
}
