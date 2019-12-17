<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use think\Log;

/**
 * Class MarketingPromotion
 * @author tinghu.liu
 * @date 2018-03-06
 * @package app\index\controller
 * seller 营销推广
 */
class MarketingPromotion extends Common
{
    /**
     * 报名活动
     */
    public function signUpActivity(){

        //tab切换类型：1-平台活动报名，2-待确认，3-参与中，4-已结束
        $tab_type = input('tab_type/d', 1);
        //活动类型:1专题活动;2定期活动;3节日活动4促销活动;
        $activity_type = input('activity_type/d');
        //活动状态（只有在tab_type == 1时才有）：1-可参加活动（去掉已参加的活动），2-全部（所有的数据，包含已参加）
        $activity_status = input('activity_status/d');
        /** 拼装查询条件 **/
        //每页大小
        $where['page_size'] = input('page_size/d', 5);
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('MarketingPromotion/signUpActivity', $p);
        $where['tab_type'] = $tab_type;
        $where['activity_type'] = $activity_type;
        $where['activity_status'] = $activity_status;
        $where['activity_title'] = input('activity_title');
        $where['time'] = time();
        $where['seller_id'] = $this->login_user_id;
        /** 拼装查询条件 end **/
        $base_api = new BaseApi();
        $activity_data_api = $base_api->getActivityData($where);
        $activity_data = isset($activity_data_api['data']['data'])&&!empty($activity_data_api['data']['data'])?$activity_data_api['data']['data']:[];
        $page_html = isset($activity_data_api['data']['Page'])&&!empty($activity_data_api['data']['Page'])?$activity_data_api['data']['Page']:'';
        // TODO “平台活动报名”TAB多个seller报名后出现多条重复数据修复逻辑：将“平台活动报名”TAB的数据分来拉取，和另外三个TAB分开，左侧按钮展示通过活动对应的报名信息来判断显示状态（“平台活动报名”TAB数据以活动表为准，再根据活动ID拉取seller报名相关信息，来判断状态展示对应的按钮）
        //页面赋值
        $this->assign('activity_data', $activity_data);
        $this->assign('page', $page_html);
        $this->assign('tab_type', $tab_type);
        $this->assign('activity_type', $activity_type);
        $this->assign('activity_status', $activity_status);
        $this->assign('activity_data_type', Base::getActivityType());
        //相关地址
        $this->assign('url',json_encode([
            'async_enrollActivity'=>url('MarketingPromotion/async_enrollActivity'),
            'async_outActivity'=>url('MarketingPromotion/async_outActivity'),
        ]));
        $this->assign('parent_menu','marketing-promotion');
        $this->assign('child_menu','sign-up-activity');
        return $this->fetch();
    }

    /**
     * 选择活动产品
     * @return mixed
     */
    public function selectPro(){
        $activity_id = input('activity_id/d');
        if (empty($activity_id) || $activity_id <=0){
            $this->error('错误访问',url('MarketingPromotion/signUpActivity'));
        }
        $base_api = new BaseApi();
        //获取活动信息
        $activity_data = $base_api->getActivityByActivityID($activity_id);
        $activity_info = isset($activity_data['data'])&&!empty($activity_data['data'])?$activity_data['data']:[];
        //tab类型：1-添加产品，2-已添加（状态为‘审核中’，‘审核通过’），3-已驳回（状态为‘审核不通过’）
        $tab_type = input('tab_type');
        /** 查询公共参数参数 start **/
        $where['Code'] = input('Code');
        //分页参数
        $where['page_size'] = input('page_size/d', 10);
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page'){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('MarketingPromotion/selectPro', $p);
        /** 查询公共参数参数 end **/
        //添加产品
        if ($tab_type == 1)
        {
        	$play_number = input('play_number');
        	$activity_type= input('activity_type');
            $where['UserId'] = $this->login_user_id;
            $where['Title'] = input('Title');
            //去除已经参加活动的产品
            $where['activityFlag'] = 1;
            $where['activityStartTime'] = $activity_info['activity_start_time'];
            $where['activityEndTime'] = $activity_info['activity_end_time'];
            //只拉取“正在销售中”、“销售中编辑”的产品
            $product = $base_api->getGroupProductPost($where);
            $product_data = isset($product['data']['data'])&&!empty($product['data']['data'])?$product['data']['data']:[];
            $page_html = isset($product['data']['Page'])&&!empty($product['data']['Page'])?$product['data']['Page']:'';
            //判断产品下的所有SKU价格是否相等，为了简化前端用户输入
            foreach ($product_data as &$product_info){
                $sku_data = isset($product_info['Skus'])?$product_info['Skus']:[];
                $sku_price_arr = [];
                foreach ($sku_data as $sku_info){
                    $sku_price_arr[] = isset($sku_info['SalesPrice'])?$sku_info['SalesPrice']:0;
                }
                $sku_price_arr_unique = array_unique($sku_price_arr);
                //如果产品下的SKU价格一致
                if (count($sku_price_arr) > 1  && count($sku_price_arr_unique) == 1){
                    $product_info['skuPriceUnique'] = 1;
                }else{
                    $product_info['skuPriceUnique'] = 0;
                }
                //获取产品价格
                if ($product_info['LowPrice'] == $product_info['HightPrice']){
                    $product_info['RangePrice'] = $product_info['LowPrice'];
                }else{
                    $product_info['RangePrice'] = $product_info['LowPrice'].'-'.$product_info['HightPrice'];
                }
            }
            $this->assign('product', $product_data);
            $this->assign('page', $page_html);
            $this->assign('title', '选择活动产品');
        }
        elseif ($tab_type == 2 || $tab_type == 3)
        {
            $where['tab_type'] = $tab_type;
            $where['activity_id'] = $activity_id;
            $where['seller_id'] = $this->login_user_id;
            $product = $base_api->getActivitySKUDataForList($where);
            $product_data = isset($product['data']['data'])&&!empty($product['data']['data'])?$product['data']['data']:[];
            $page_html = isset($product['data']['Page'])&&!empty($product['data']['Page'])?$product['data']['Page']:'';
            //获取产品标题
            foreach ($product_data as &$product_info){
                $p_data = $base_api->getProductInfoByID((int)$product_info['product_id']);
                $pro_title = '';
                $range_price = 0;
                if (isset($p_data['data']['Title'])){
                    $pro_title = $p_data['data']['Title'];
                }
                if (isset($p_data['data']['LowPrice']) && isset($p_data['data']['HightPrice'])){
                    if ($p_data['data']['LowPrice'] == $p_data['data']['HightPrice']){
                        $range_price = $p_data['data']['LowPrice'];
                    }else{
                        $range_price = $p_data['data']['LowPrice'].'-'.$p_data['data']['HightPrice'];
                    }
                }
                $product_info['pro_title'] = $pro_title;
                $product_info['range_price'] = $range_price;
            }
            $this->assign('product', $product_data);
            $this->assign('page', $page_html);
            if ($tab_type == 2){
                $this->assign('title', '已添加活动的产品');
            }else{
                $this->assign('title', '已驳回的产品');
            }
        }

        $this->assign('activity_info', $activity_info);
        $this->assign('url', json_encode([
            'async_addActivitySKU'=>url('MarketingPromotion/async_addActivitySKU'),
            'async_updateActivitySKU'=>url('MarketingPromotion/async_updateActivitySKU'),
            'async_resubmitActivitySKU'=>url('MarketingPromotion/async_resubmitActivitySKU'),
        ]));
        $this->assign('activity_id', $activity_id);
        $this->assign('tab_type', $tab_type);
        $this->assign('parent_menu','marketing-promotion');
        $this->assign('child_menu','sign-up-activity');
        return $this->fetch();
    }

    /**
     * 活动详情页
     * @return mixed
     */
    public function activityDetail(){
        $activity_id = input('activity_id/d');
        if (empty($activity_id) || $activity_id <=0){
            $this->error('错误访问',url('MarketingPromotion/signUpActivity'));
        }
        $base_api = new BaseApi();
        $activity_data = $base_api->getActivityByActivityID($activity_id);
        $activity_info = isset($activity_data['data'])&&!empty($activity_data['data'])?$activity_data['data']:[];
        $this->assign('activity_info', $activity_info);
        $this->assign('return_url', url('MarketingPromotion/signUpActivity'));
        $this->assign('title', $activity_info['activity_title'].'-活动详情页');
        $this->assign('parent_menu','marketing-promotion');
        $this->assign('child_menu','sign-up-activity');
        /*$PlayNumberHtml ='';
        #如果是Flash Deals活动  Start-----------------------------
        #思路：根据活动的开始日期和后台系统配置的每天活动场次遍历，拼接处每个场次的信息供前端页面显示       
        $html ='';
        if(isset($activity_info['type']) && $activity_info['type']==5 ){
        	//页面上HTML模板
        	$html ='<dl class="dl-layout">
                    <dt class="w120 tright">
                                                           可报名的活动：
                    </dt>
                    <dd>
                        <div>
                          <table>
                             {roundHtml}
                          </table>
                        </div>
                    </dd>
                </dl>';
        	//取得场次数据
        	$playNumberData = $base_api->getSysCofig(['ConfigName' => 'ActivityPlayNumber']);
        	$playCount = 0;
        	if(!empty($playNumberData) && isset($playNumberData['data']['key'])){
        		$activityArr = explode(';',$playNumberData['data']['key']);
        		$playCount = count($activityArr);
        	}
        	$daysBetween=Base::daysBetween($activity_info['activity_start_time'],$activity_info['activity_end_time']);
        	$roundHtml ='';
        	if($daysBetween==0){//如果活动只有一天，那就使用开始时间去遍历拼接数据
        		$roundHtml =$this->appendHtml($activity_info['activity_start_time'],$activity_info['activity_title'],$playCount);
        	}elseif($daysBetween > 0){//如果活动持续N天，就要使用每天去遍历每场次的数据
        		//遍历天数
        		for($i=1;$i<=$daysBetween;$i++){
        			if($i == 1){
        				$startDate = $activity_info['activity_start_time'];        				
        			}else{
        				$x = $i-1;
        				$startDate = strtotime("+$x day",$activity_info['activity_start_time']);
        			}  			
        			$roundHtml .= $this->appendHtml($startDate,$activity_info['activity_title'],$playCount);
        			//dump($roundHtml);
        		}
        	}
        	$html = str_replace('{roundHtml}',$roundHtml,$html);
        }
        $this->assign('PlayNumberHtml',$html);
        #如果是Flash Deals活动  End-----------------------------     */
        return $this->fetch();
    }    
    
    /**
     * 每个日期拼接html
     * @param string $start_time
     * @param string $title
     * @param int $playCount
     * @return string
     */
    private function appendHtml($start_time,$title,$playCount){
    	$h ='';
    	for($i=1;$i<=$playCount;$i++){
    		$h .='<tr>
                     <td>
        				 <a class="selected" href="#"
        					 data-play-number="'.date("Y-m-d",$start_time).',0'.$i.'">
        					  '.$title.date("m月d日",$start_time).'
        					        场第'.$i.'期
        				  </a>
        			  </td>
                 </tr>';
    	}
    	return $h;
    }

    /**
     * 报名参加活动
     * @return \think\response\Json
     */
    public function async_enrollActivity(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '输入数据有误，请检查';
        $activity_id = input('post.activity_id/d');
        if (!empty($activity_id)){            
            $param['activity_id'] = $activity_id;
            $param['seller_id'] = $this->login_user_id;
            $param['add_time'] = time();
            $param['add_user_name'] = $this->login_user_name.'（seller）';            
            /*$play_number = input('post.play_number');
            $activity_type = input('post.activity_type/d');
            $isOk= false;
            if($activity_type ==5){
            	if(!empty($play_number)){
            		$play_number_arr=explode(',',$play_number,2);
            		if(count($play_number_arr) ==2){   		
	            		$base_api = new BaseApi();
	            		$playNumber = $base_api->getSysCofig(['ConfigName' => 'ActivityPlayNumber']);
	            		if(!empty($playNumber) && isset($playNumber['data']['key'])){
	            			$playNumberArr=explode(',',$playNumber['data']['key']);
	            			foreach ($playNumberArr as $value) {
	            				$arr = explode('-',$value,2);
	            				if(!empty($arr) && count($play_number_arr) ==2){
	            					if($play_number_arr[0] ==$v[0]){
	            						$isOk= true;
	            					}
	            				}
	            			}
	            		}else{
	            			$rtn['msg'] = '系统异常'.$res['msg'];
	            			Log::record('async_enrollActivity->系统异常-获取系统配置的活动场次异常(ActivityPlayNumber)'.print_r($res, true));
	            		}	            		           		
            	    }else{
            	    	$rtn['msg'] = '活动场次参数格式错误'.$res['msg'];
            	    }
            	}else{
            		$rtn['msg'] = '活动场次参数为空'.$res['msg'];
            	}
            }
            if(!$isOk){
            	$rtn['msg'] = '活动场次参数错误'.$res['msg'];
            	Log::record('async_enrollActivity->活动场次参数错误'.print_r($res, true));
            	return json($rtn);
            }   */
            $base_api = new BaseApi();
            $res = $base_api->enrollActivity($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '参加活动失败 '.$res['msg'];
                Log::record('async_enrollActivity->报名参加活动'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 退出活动【批量】
     * @return \think\response\Json
     */
    public function async_outActivity(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '输入数据有误，请检查';
        $activity_id = input('post.activity_id/d');
        if (!empty($activity_id)){
            $base_api = new BaseApi();
            $param['activity_id'] = $activity_id;
            $param['seller_id'] = $this->login_user_id;
            $param['edit_time'] = time();
            $param['edit_user_name'] = $this->login_user_name.'（seller）';
            //修改后台-报名表&&sku表
            $res = $base_api->quitActivity($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '退出活动失败 '.$res['msg'];
                Log::record('async_outActivity->退出参加活动'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 新增活动SKU
     * @return \think\response\Json
     */
    public function async_addActivitySKU(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '输入数据有误，请检查';
        $param = input();
        if (!empty($param)){
            $data = $param['data'];
            $base_api = new BaseApi();
            //处理数据
            $flag = false;
            $flag_pro_id = 0;
            $time = time();
            foreach ($data as &$info){
                $info['seller_id'] = $this->login_user_id;
                $info['seller_name'] = $this->login_user_name.'（seller）';
                $info['add_time'] = $time;

                if ($info['set_type'] == 1){ //统一活动折扣价
                    if (
                        (empty(trimall($info['discount'])) && trimall($info['discount']) != 0)
                        || empty(trimall($info['discount']))
                    ){
                        $flag = true;
                        $flag_pro_id = $info['product_id'];
                        break;
                    }
                }else{ //单个指定折扣价
                    if (
                        (empty(trimall($info['activity_price'])) && trimall($info['activity_price']) != 0)
                        || empty(trimall($info['activity_price']))
                    ){
                        $flag = true;
                        $flag_pro_id = $info['product_id'];
                        break;
                    }
                    //根据活动价格、销售价格或者实收百分比
                    $sales_price = $info['sales_price'];
                    $activity_price = $info['activity_price'];
                    $info['discount'] = round(($activity_price/$sales_price) * 100, 2);
                }
            }
            if ($flag){
                $rtn['msg'] = '产品号为'.$flag_pro_id.' 参数必填';
                return json($rtn);die;
            }
            //增加活动SKU数据
            $product_id = $data[0]['product_id'];
            $activity_id = $data[0]['activity_id'];

            $res = $base_api->addActivitySKU($data);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                //获取活动信息
                $activity_data = $base_api->getActivityByActivityID($activity_id);
                $activity_info = isset($activity_data['data'])&&!empty($activity_data['data'])?$activity_data['data']:[];
                //Log::record('async_addActivitySKU->$activity_info：'.print_r($activity_info, true));
                //将产品标识为已参加活动，并写入活动开始结束时间
                $up_product_res = $base_api->updateProductInfoPost(json_encode(['id'=>(int)$product_id, 'IsActivityEnroll'=>1,'IsActivityEnrollStartTime'=>$activity_info['activity_start_time'],'IsActivityEnrollEndTime'=>$activity_info['activity_end_time']]));
                //Log::record('async_addActivitySKU->$up_product_params：'.json_encode(['id'=>(int)$product_id, 'IsActivityEnroll'=>1,'IsActivityEnrollStartTime'=>$activity_info['activity_start_time'],'IsActivityEnrollEndTime'=>$activity_info['activity_end_time']]));
                Log::record('async_addActivitySKU->$up_product_res：'.print_r($up_product_res, true));
            }else{
                $rtn['msg'] = '新增失败（商品编号'.$product_id.'） '.$res['msg'].'，请重试';
                Log::record('async_addActivitySKU->新增活动SKU失败'.print_r($res, true));
            }
            /*$api_flag = true;
            $err = '';
            foreach ($data as $val){
                $res = $base_api->addActivitySKU($val);
                if ($res['code'] != API_RETURN_SUCCESS){
                    $api_flag = false;
                    $err = $res['msg'];
                    Log::record('async_addActivitySKU->新增活动SKU失败'.print_r($res, true));
                    break;
                }
            }
            if ($api_flag){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                //将产品标识为已参加活动
                $base_api->updateProductInfoPost(json_encode(['id'=>(int)$product_id, 'isActivity'=>1]));
            }else{
                $rtn['msg'] = '新增失败（商品编号'.$product_id.'） '.$err.'，请重试';
            }*/
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 修改活动SKU
     * @return \think\response\Json
     */
    public function async_updateActivitySKU(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '输入数据有误，请检查';
        $data = input();
        $param = $data['data'];
        if (!empty($param)){
            $base_api = new BaseApi();
            //组装数据
            foreach ($param as &$info){
                $info['edit_time'] = time();
                $info['edit_user_name'] = $this->login_user_name.'（seller）';
            }
            $res = $base_api->updateActivitySKU($param);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '修改失败 '.$res['msg'];
                Log::record('async_updateActivitySKU->修改活动SKU失败'.print_r($res, true));
            }
        }else{
            $rtn['msg'] = '输入参数错误';
        }
        return json($rtn);
    }

    /**
     * 重新提交审核活动SKU
     * @return \think\response\Json
     */
    public function async_resubmitActivitySKU(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '输入数据有误，请检查';
        $data = input();
        $param = $data['data'];
        $base_api = new BaseApi();
        //组装数据
        foreach ($param as &$info){
            $info['edit_time'] = time();
            $info['edit_user_name'] = $this->login_user_name.'（seller）';
        }
        $res = $base_api->resubmitActivitySKU($param);
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $res;
        }else{
            $rtn['msg'] = '提交失败 '.$res['msg'];
            Log::record('async_resubmitActivitySKU->重新提交审核活动SKU失败'.print_r($res, true));
        }
        return json($rtn);
    }
}
