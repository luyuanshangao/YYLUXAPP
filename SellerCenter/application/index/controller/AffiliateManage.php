<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use Func\IpFunction;
use think\Log;
use think\Session;

/**
 * Class AffiliateManage
 * @author hengzhang
 * @date 2018-05-25
 * @package app\index\controller
 * seller 联盟营销
 */
class AffiliateManage extends Common
{
    /**
     * 加入联盟营销
     */
    public function addAffiliate(){
    	if(request()->isAjax()){
    		$rtn = config('ajax_return_data');
    		$rtn['msg'] = '输入数据有误，请检查';
    		$default_commission = input("default_commission/f");
    		if(!empty($default_commission) && $default_commission >= 0.03 && $default_commission <= 0.05  ){
    			if(($default_commission * 1000 % 5) == 0){
    				$base_api = new BaseApi();
			        //$user_data = Session::get('user_data');
			        $user_data = $this->login_user_data;
			        $data['seller_id'] = $user_data['user_id'];
			        $data['commission'] = $default_commission;
			        $data['type'] =1;
			        $data['class_id'] = 0;
    				$result = $base_api -> addCommission($data);
    				if ($result['code'] == API_RETURN_SUCCESS){
    					$rtn['code'] = 200;
    					$rtn['msg'] = 'success';
    					$rtn['data'] = $result;
    				 }else{
    					$rtn['msg'] = '提交失败,api-code:'.$result['code'].';api-msg:'.$result['msg'];
    					Log::record('addAffiliate->加入联盟营销失败'.print_r($result, true));
    				 }
    				 return json($rtn);
    			}else{
    				return ['code'=>1003,'msg'=>"佣金比例只能是0.5的倍数！"];
    			}
    		}else{
    			return ['code'=>1002,'msg'=>"佣金比例设置错误！"];
    		}
    	}else{
    		//getDefaultCommissionBySellerID
    		$base_api = new BaseApi();
    		//$user_data = Session::get('user_data');
            $user_data = $this->login_user_data;
			$data['seller_id'] = $user_data['user_id'];
    		$result = $base_api -> getDefaultCommissionBySellerID($data);
    		if ($result['code'] != API_RETURN_SUCCESS){
    			$result['msg'] = '获取数据失败';
    			Log::record('getDefaultCommissionBySellerID->获取默认联盟佣金失败'.print_r($result, true));
    		}
    		$statusHtml ='';
    		if(!isset($result['data']['commission'])){
    			$result['data']['commission'] = 0.03;    			
    		}
    	    if(isset($result['data']['status'])){
    			$statusHtml ='<span class="status-text-wrap"> ,审核状态：<span class="status-text">'.$result['data']['status_text'].'</span></span>';  			
    		}
    		$result['data']['commission'] = $result['data']['commission'] * 100;
    		$this->assign('title', '加入联盟营销计划');	    	
	    	$this->assign('parent_menu','marketing-promotion');
	    	$this->assign('child_menu','sign-up-addAffiliate');	
	    	$this->assign('model', $result);
	    	$this->assign('statusHtml', $statusHtml);
    	}
    	return $this->fetch();
    }
    
    /**
     * 分类佣金设置
     */
    public function setAffiliateClass(){
        if (!Base::AffiliateJudgeIsJoin($this->login_user_id)){
            $this->error('请先加入联盟营销', url('AffiliateManage/addAffiliate'));
        }
        $where['seller_id'] = $this->login_user_id;
        //设置类型：1 默认设置, 2 按类别设置
        $where['type'] = 2;
        //获取分类佣金设置列表 TODO
        $base_api = new BaseApi();
        $data_api = $base_api->getClassCommissionList($where);
        $data = isset($data_api['data']) && !empty($data_api['data'])?$data_api['data']:[];
        //佣金比例转换
        foreach ($data as &$info){
            $info['commission'] *= 100;
        }
        /** 获取类别名称 start **/
        //获取类别ID
        $class_arr = [];
        foreach ($data as $val){
            $parent_class = $val['parent_class'];
            $class_id = $val['class_id'];
            if ($class_id != -99){
                $class_arr[] = $class_id;
            }
            if($parent_class > 0){
                $class_arr[] = $parent_class;
            }
        }

        //根据类别ID获取类别详情
        $class_data_api = $base_api->getCategoryDataByCategoryIDData(array_unique($class_arr));
        $class_data = isset($class_data_api['data']) && !empty($class_data_api['data'])?$class_data_api['data']:[];
        $class_data_all = array();
        foreach ($class_data as $k=>$v){
            $class_data_all[$v['id']] = $v;
        }

        //获取指定类别名称
        foreach ($data as &$d_info){
            $class_id = $d_info['class_id'];
            $d_info['class_name_cn'] = '';
            $d_info['class_name_en'] = '';
            if($d_info['parent_class'] > 0 && isset($class_data_all[$d_info['parent_class']]['title_cn']) && isset($class_data_all[$d_info['parent_class']]['title_en'])){
                $d_info['class_name_cn'] .= $class_data_all[$d_info['parent_class']]['title_cn'].">";
                $d_info['class_name_en'] .= $class_data_all[$d_info['parent_class']]['title_en'].">";
            }
            $d_info['class_name_cn'] .= $class_data_all[$class_id]['title_cn'];
            $d_info['class_name_en'] .= $class_data_all[$class_id]['title_en'];
        }

       /* foreach ($data as &$d_info){
            $class_id = $d_info['class_id'];
            foreach ($class_data as $class_info){
                $class_id_info = $class_info['id'];
                if ($class_id == $class_id_info){
                    $d_info['class_name_cn'] = '';
                    $d_info['class_name_en'] = '';
                    if($d_info['parent_class'] > 0){
                        $d_info['class_name_cn'] .= $class_info['title_cn'];
                        $d_info['class_name_en'] .= $class_info['title_en'];
                    }
                    $d_info['class_name_cn'] .= $class_info['title_cn'];
                    $d_info['class_name_en'] .= $class_info['title_en'];
                    break;
                }
            }
        }*/
        /** 获取类别名称 end **/
        //按照“是否审核（status：0 待审核; 1 审核通过; 2 审核失败）”重新组装数据
        $wait_data = [];
        $success_data = [];
        $fail_data = [];
        $data_new = [];
        $tem_arr = [];
        foreach ($data as $key=>&$info){
            $status = $info['status'];
            if ($info['class_id'] == -99){
                $tem_arr = $info;
            }else{
                switch ($status){
                    case 0:
                        $wait_data[] = $info;
                        break;
                    case 1:
                        $success_data[] = $info;
                        break;
                    case 2:
                        $fail_data[] = $info;
                        break;
                }
            }
        }
        if (!empty($tem_arr)){
            $wait_data[] = $tem_arr;
            $success_data[] = $tem_arr;
            $fail_data[] = $tem_arr;
        }
        $data_new['wait_data'] = !empty($wait_data)?arr_sort($wait_data, 'class_id'):[];
        $data_new['success_data'] = !empty($success_data)?arr_sort($success_data, 'class_id'):[];
        $data_new['fail_data'] = !empty($fail_data)?arr_sort($fail_data, 'class_id'):[];
    	$this->assign('data', $data_new);
    	$this->assign('title', '分类佣金设置');
    	$this->assign('parent_menu','marketing-promotion');
    	$this->assign('child_menu','sign-up-setAffiliateClass');
    	return $this->fetch();
    }

    /**
     * 添加主推产品
     * @return mixed
     */
    public function addMainProductList(){
        if (!Base::AffiliateJudgeIsJoin($this->login_user_id)){
            $this->error('请先加入联盟营销', url('AffiliateManage/addAffiliate'));
        }
        $base_api = new BaseApi();
        //产品名称或产品编号
        $search_content = input("search_content");
        if(!empty($search_content)){
            if (is_numeric($search_content)){
                $data['id'] = $search_content;
            }else{
                $search_content_str = QueryFiltering($search_content);
                if(!empty($search_content_str)){
                    $search_content_arr = explode(",",$search_content_str);
                    if(!empty($search_content_arr)){
                        $is_number = 1;
                        foreach ($search_content_arr as $key=>$value){
                            if(!is_numeric($value)){
                                $is_number = 0;
                                break;
                            }
                        }
                        if($is_number == 1){
                            $search_content = $search_content_arr;
                            $data['id'] = $search_content;
                        }else{
                            $data['Title'] = $search_content;
                        }
                    }
                }
            }
        }
        $FifthCategory = input("FifthCategory");
        if(!empty($FifthCategory)){
            $data['first_level'] = $FifthCategory;
        }
        //seller_id
        $data['UserId'] = $this->login_user_id;
        //分页 start
        $data['page_size'] = input('page_size',20);
        $data['page'] = input('page',1);
        $input = input();
        $query = [];
        foreach ($input as $k=>$v){
            if ($k != 'page' && $k != 'ProductStatus'){
                $query[$k] = $v;
            }
        }
        $data['query'] = $query;
        $data['path'] = url('AffiliateManage/addMainProductList');
        //分页 end
        //只拉取“正在销售中”、“销售中编辑”的产品
        $data['ProductStatus'] = [PRODUCT_STATUS_SUCCESS, PRODUCT_STATUS_SUCCESS_UPDATE];
        $product = $base_api->getGroupProductPost($data);
        $product_data = isset($product['data']) && !empty($product['data'])?$product['data']:[];
        /** 获取一级分类名称 start **/
        //获取一级分类ID
        $first_category_arr = [];
        $spu_arr = [];
        foreach ($product_data['data'] as $info){
            $first_category_arr[] = $info['FirstCategory'];
            $spu_arr[] = $info['_id'];
        }
        $first_category_arr = array_unique($first_category_arr);
        //获取一级分类详情
        $class_data_api = $base_api->getCategoryDataByCategoryIDData($first_category_arr);
        $class_data_all = $base_api->getNextCategoryByID(0,0);
        $class_data = isset($class_data_api['data']) && !empty($class_data_api['data'])?$class_data_api['data']:[];
        //获取指定类别名称
        foreach ($product_data['data'] as &$d_info){
            $class_id = $d_info['FirstCategory'];
            foreach ($class_data as $class_info){
                //获取产品价格
                if ($d_info['LowPrice'] == $d_info['HightPrice']){
                    $d_info['RangePrice'] = $d_info['LowPrice'];
                }else{
                    $d_info['RangePrice'] = $d_info['LowPrice'].'-'.$d_info['HightPrice'];
                }
                $class_id_info = $class_info['id'];
                if ($class_id == $class_id_info){
                    $d_info['FirstCategoryName_cn'] = $class_info['title_cn'];
                    $d_info['FirstCategoryName_en'] = $class_info['title_en'];
                    break;
                }
            }
        }
        $product_profit_data = $this->getProductProfit($spu_arr);
        $spu_product_profit_data = array();
        if(!empty($product_profit_data['data'])){
            foreach ($product_profit_data['data'] as $v){
                $spu_product_profit_data[$v['spu']] = $v['profit'];
            }
        }
        /** 获取一级分类名称 end **/
        //获取主推产品数量情况
        $num_data_api = $base_api->getMainProductNum(['seller_id'=>$this->login_user_id]);
        $num_data = isset($num_data_api['data']) && !empty($num_data_api['data'])?$num_data_api['data']:[];
        $this->assign([
            'product_data'=>$product_data,
            'class_data'=>$class_data_all['data'],
            'spu_product_profit_data'=>$spu_product_profit_data,
            'title'=>'添加主推产品',
            'parent_menu'=>'marketing-promotion',
            'child_menu'=>'add-myMainProductList',
            'ajax_url'=>json_encode([
                'async_addMainProduct'=>url('AffiliateManage/async_addMainProduct'),
            ]),
            'num_data'=>$num_data,
        ]);
        return $this->fetch();
    }

    /*获取SPU毛利率减佣金比例后的*/
    public function getProductProfit($spu){
        $base_api = new BaseApi();
        $spu = input("spu_id",$spu);
        if(is_array($spu)){
            $param['spu'] = $spu;
        }else{
            $param['spu'] = [$spu];
        }
        $profit = $base_api->getProductProfit($param);
        return $profit;
    }
    
    /**
     * 我的主推产品
     */
    public function myMainProductList(){
        if (!Base::AffiliateJudgeIsJoin($this->login_user_id)){
            $this->error('请先加入联盟营销', url('AffiliateManage/addAffiliate'));
        }
    	$base_api = new BaseApi();
    	$where['seller_id'] = $this->login_user_id;
        //数据类型:1 非主推产品; 2 主推产品;
    	$where['type'] = 2;
        //审核状态:0 待审核; 1 审核通过; 2 审核不通过;
        $status = input('status', 0);
        $where['status'] = $status;
        //spu搜索条件
        $where['spu'] = input('spu');

        //分页 start
        $where['page_size'] = input('page_size',10);
        $where['page'] = input('page',1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('AffiliateManage/myMainProductList', $p, config('default_return_type'), true);
        //分页 end
    	$list_api = $base_api->getAffiliateProductList($where);
        $list = isset($list_api['data']['data'])&&!empty($list_api['data']['data'])?$list_api['data']['data']:[];
        $page_html = isset($list_api['data']['Page'])&&!empty($list_api['data']['Page'])?$list_api['data']['Page']:'';

    	/*print_r($list);
    	print_r($page_html);die;*/
//    	print_r($list_api);

        $this->assign([
            'list'=>$list,
            'page'=>$page_html,
            'status'=>$status,
            'child_menu'=>'sign-up-myMainProductList',
            'parent_menu'=>'marketing-promotion',
            'title'=>'我的主推产品',
            'ajax_url'=>json_encode([
                'async_updateAffiliateProduct'=>url('AffiliateManage/async_updateAffiliateProduct'),
            ]),
        ]);
        return $this->fetch();
    }

    /**
     * 联盟营销订单列表
     * @return mixed
     */
    public function affiliateOrderList(){
        $base_api = new BaseApi();
        //商家ID
        $where['store_id'] = $this->login_user_id;
        //订单状态
        $where['order_status'] = input('order_status');
        //添加时间
        $where['create_on_start'] = strtotime(input('create_on_start'));
        $where['create_on_end'] = strtotime(input('create_on_end'));
        //订单编号
        $where['order_number'] = input('order_number');
        //分页 start
        $where['page_size'] = input('page_size',10);
        $where['page'] = input('page',1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('AffiliateManage/affiliateOrderList', $p, config('default_return_type'), true);
        //分页 end
        $list_api = $base_api->getAffiliateOrderList($where);
        $list = isset($list_api['data']['data'])&&!empty($list_api['data']['data'])?$list_api['data']['data']:[];
        $page_html = isset($list_api['data']['Page'])&&!empty($list_api['data']['Page'])?$list_api['data']['Page']:'';
        $this->assign([
            'list'=>$list,
            'page'=>$page_html,
            'order_status_data'=>Base::getOrderStatus(),
            'settlement_status_data'=>Base::getSettlementStatus(),
            'child_menu'=>'orders-affiliate-list',
            'parent_menu'=>'order',
            'title'=>'联盟营销订单列表',
            'ajax_url'=>json_encode([
                'async_exportAffiliateOrder'=>url('AffiliateManage/async_exportAffiliateOrder'),
            ]),
        ]);
        return $this->fetch();
    }

    /**
     * 联盟营销订单详情页面
     * @return mixed
     */
    public function affiliateOrderDetail(){
        $base_api = new BaseApi();
        $affiliate_order_id = input('affiliate_order_id');
        if (empty($affiliate_order_id) || !is_numeric($affiliate_order_id) || $affiliate_order_id <0){
            $this->error('错误访问',url('AffiliateManage/affiliateOrderList'));
        }
        $base_api = new BaseApi();
        $data_api = $base_api->getAffiliateOrderInfoById(['affiliate_order_id'=>$affiliate_order_id]);
        $data = isset($data_api['data'])&&!empty($data_api['data'])?$data_api['data']:[];
        $this->assign([
            'data'=>$data,
            'child_menu'=>'orders-affiliate-list',
            'parent_menu'=>'order',
            'title'=>'联盟营销订单列表',
        ]);
        return $this->fetch();
    }

    /**
     * 导出联盟营销订单数据【excel格式】
     */
    public function async_exportAffiliateOrder(){
        $base_api = new BaseApi();
        //商家ID
        $where['store_id'] = $this->login_user_id;
        //订单状态
        $where['order_status'] = input('order_status');
        //添加时间
        $where['create_on_start'] = strtotime(input('create_on_start'));
        $where['create_on_end'] = strtotime(input('create_on_end'));
        //订单编号
        $where['order_number'] = input('order_number');
        //分页 start
        $where['page_size'] = input('page_size',10);
        $where['page'] = input('page',1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('AffiliateManage/affiliateOrderList', $p, config('default_return_type'), true);
        //分页 end
        $list_api = $base_api->getAffiliateOrderList($where);
        $data = isset($list_api['data']['data'])&&!empty($list_api['data']['data'])?$list_api['data']['data']:[];

        $objPHPExcel = new \PHPExcel();

        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中

        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '订单编号')
            ->setCellValue('B1', '订单金额($)')
            ->setCellValue('C1', '佣金金额（$）')
            ->setCellValue('D1', '结算金额($)')
            ->setCellValue('E1', '订单来源')
            ->setCellValue('F1', '订单状态')
            ->setCellValue('G1', '结算状态')
            ->setCellValue('H1', '提交时间');
        $objPHPExcel->getActiveSheet()->setTitle('联盟营销订单列表');
        //设置数据
        $i = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        foreach ($data as $vo){
            $objActSheet->setCellValue('A'.$i, $vo["order_number"]);
            $objActSheet->setCellValue('B'.$i, $vo["price"]);
            $objActSheet->setCellValue('C'.$i, $vo["commission_price"]);
            $objActSheet->setCellValue('D'.$i, $vo["settlement_price"]);
            $objActSheet->setCellValue('E'.$i, $vo["affiliate_id"]);
            $objActSheet->setCellValue('F'.$i, $vo["order_status_str"]);
            $objActSheet->setCellValue('G'.$i, $vo["settlement_status_str"]);
            $objActSheet->setCellValue('H'.$i, date('Y-m-d H:i:s', $vo['add_time']));
            $i++;
        }
        // excel头参数
        $fileName = "联盟营销订单汇总表".date('_YmdHis');
        $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        ob_end_clean();
        header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".xls'");
        header("Content-Disposition: attachment;filename=$xlsTitle.xls");
        header('Cache-Control: max-age=0');
        //excel5为xls格式，excel2007为xlsx格式
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    /**
     * 增加主推产品
     * @return \think\response\Json
     */
    public function async_addMainProduct(){
        $param = input();
        $data = isset($param['data'])?$param['data']:[];
        Log::record('async_addMainProduct->'.print_r($data, true));
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '增加主推产品失败';
        if (!empty($data)){
            $time = time();
            //数据格式校验
            foreach ($data as &$info) {
                $class_id = $info['class_id'];//分类ID
                $spu = $info['spu'];//spu
                $commission = $info['commission'];//佣金比例 5.0%-50.0%
                $effect_time = $info['effect_time'];//生效时间
                if (
                    !is_numeric($class_id) || empty($class_id) || $class_id < 0
                    || !is_numeric($spu) || empty($spu) || $spu < 0
                    || empty($commission) || $commission < 3 || $commission > 50
                    || empty($effect_time)
                ){
                    $rtn['msg'] = '格式错误，请选择有效的佣金、时间、分类、SPU';
                    return json($rtn);
                }
                $info['effect_time'] = strtotime($info['effect_time']);
                $info['commission'] = $commission/100;
                $info['type'] = 2; //数据类型:1 非主推产品; 2 主推产品;
                $info['seller_id'] = $this->login_user_id;
                $info['add_time'] = $time;
            }
            $base_api = new BaseApi();
            $res = $base_api->addAffiliateProduct($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = $res['msg'];
                Log::record('async_addMainProduct->增加主推产品失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 获取分类默认佣金比例数据
     * @return \think\response\Json
     */
    public function async_getClassDefaultCommission(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $base_api = new BaseApi();
        $res = $base_api->getClassDefaultCommission();
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $res['data'];
        }else{
            $rtn['msg'] = $res['msg'];
            Log::record('async_getClassDefaultCommission->获取分类默认佣金比例数据失败'.print_r($res, true));
        }
        return json($rtn);
    }

    /**
     * 获取二级分类默认佣金比例数据
     * @return \think\response\Json
     */
    public function async_getSecondClassDefaultCommission(){
        $parent_id = input("parent_id");
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        if($parent_id>0){
            $base_api = new BaseApi();
            $res = $base_api->getNextCategoryByID($parent_id,0);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
                $rtn['data1'] = $base_api->getClassDefaultCommission(['parent_id'=>$parent_id]);
            }else{
                $rtn['msg'] = $res['code']."->".$res['msg'];
            }
        }
        return json($rtn);
    }

    /**
     * 添加分类佣金数据
     * @return \think\response\Json
     */
    public function async_addClassCommission(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $data = input();
        if (
            !empty($data)
            && isset($data['class_id']) && !empty($data['class_id'])
            && isset($data['commission']) && !empty($data['commission'])
            && isset($data['effect_time']) && !empty($data['effect_time'])
        ){
            $second_class_id = isset($data['second_class_id'])?$data['second_class_id']:0;
            if($second_class_id>0){
                $data['parent_class'] = $data['class_id'];
                $data['class_id'] = $data['second_class_id'];
            }
            $base_api = new BaseApi();
            $data['seller_id'] = $this->login_user_id;
            $data['type'] = 2; //设置类型：1 默认设置, 2 按类别设置
            $data['op_type'] = 1; //1-添加，2-修改
            $data['effect_time'] = strtotime($data['effect_time']);
            $data['add_time'] = time();
            $res = $base_api->addCommission($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res['data'];
            }else{
                $rtn['msg'] = $res['msg'];
                Log::record('async_addClassCommission->添加分类佣金数据失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 修改分类佣金数据
     * @return \think\response\Json
     */
    public function async_editorClassCommission(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $data = input();
        if (
            !empty($data)
            && isset($data['id']) && !empty($data['id'])
            && isset($data['class_id']) && !empty($data['class_id'])
            && isset($data['commission']) && !empty($data['commission'])
            && isset($data['effect_time']) && !empty($data['effect_time'])
        ){
            $base_api = new BaseApi();
            $data['effect_time'] = strtotime($data['effect_time']);
            $data['update_time'] = time();
            $data['seller_id'] = $this->login_user_id;
            $data['operater_user'] = $this->login_user_name;
            $data['op_type'] = 2; //1-添加，2-修改
            $data['type'] = 2; //设置类型：1 默认设置, 2 按类别设置
            $res = $base_api->addCommission($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res['data'];
            }else{
                $rtn['msg'] = $res['msg'];
                Log::record('async_editorClassCommission->修改分类佣金数据失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 通过ID删除联盟营销佣金数据
     * @return \think\response\Json
     */
    public function async_deleteCommissionById()
    {
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $data = input();
        if (isset($data['id']) && !empty($data['id']) && is_numeric($data['id']) && $data['id'] > 0 ){
            $base_api = new BaseApi();
            $res = $base_api->deleteCommissionById($data['id']);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = $res['msg'];
                Log::record('async_deleteCommissionById->通过ID删除联盟营销佣金数据失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 修改联盟营销产品数据
     * @return \think\response\Json
     */
    public function async_updateAffiliateProduct(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $params = input();
        $data = $params['data'];
        if (!empty($data)){
            //数据完善
            foreach ($data as &$info){
                $info['commission'] /= 100;
                $info['effect_time'] = strtotime($info['effect_time']);
                $info['update_time'] = time();
            }
            $base_api = new BaseApi();
            $res = $base_api->updateAffiliateProduct($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = $res['msg'];
                Log::record('async_updateAffiliateProduct->修改联盟营销产品数据失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }




}
