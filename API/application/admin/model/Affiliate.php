<?php
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use app\mall\controller\Product;
use think\Log;
use think\Model;
use think\Db;
/**
 * 联盟营销模型
 * @author heng.zhang 2018/5/25
 * @version v1.0
 */
class Affiliate extends Model
{
    private  $db = '';
    protected $table_affiliate_commission= 'dx_affiliate_commission';
    protected $affiliate_product = 'dx_affiliate_product';
    protected $affiliate_commission_default = 'dx_affiliate_commission_default';
    protected $order = 'dx_affiliate_order';
    protected $sales_order = 'dx_sales_order';
    protected $order_item = 'dx_affiliate_order_item';
    protected $sales_order_status_change = 'dx_sales_order_status_change';
    protected $order_after_sale_apply = 'dx_order_after_sale_apply';
    protected $order_refund = 'dx_order_refund';
    protected $affiliate_apply = 'dx_affiliate_apply';

    protected $product_newprice_new = 'fs_newprice_new';
    protected $product_newprice = 'fs_newprice';
    protected $product_purchase_cost = 'fs_erp_purchase_cost';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
        $this->or = Db::connect('db_order');
    }
     
    /**
	 *插入默认的佣金比例数据
     */
    public function addDefaultCommission(array $param){
    	if(empty($param)){
    		return '参数为空'; //参数为空
    	}
    	$sellerID= 0;
        if(isset($param['seller_id']) && $param['seller_id']>0){
        	$sellerID = $param['seller_id'];
        }else{
        	return 'seller_id不合法'; //seller_id 不合法
        }
        $commission =0.0;
        if(isset($param['commission']) && $param['commission']>0){
        	$commission = $param['commission'];
        }else{
        	return 'commission不合法'; //commission 不合法
        }        
        /*$count = $this -> getProductCountByWhere(['seller_id'=>$sellerID, 'ProductStatus'=>1]);#正常在售SKU
        if($count <1){
        	return '未添加产品或未审核通过'; //未添加产品或未审核通过
        }*/
    	$dataDB = $this -> getDefaultCommissionBySellerID($sellerID);
    	if(empty($dataDB)){
    		$updateData['seller_id'] =$sellerID;
    		$updateData['add_time']=time();
    		$updateData['type']=1;
    		$updateData['status']=1;//默认佣金数据默认状态是审核通过
    		$updateData['class_id']=0;
    		$updateData['commission']=$commission;
    		$updateData['effect_time'] = time();
    		$result = $this->db->table($this->table_affiliate_commission)->insert($updateData);
    		if($result){
				$productModel = new Porduct();
				$productResult= $productModel -> updateCommissionBySellerID($sellerID, $commission);
				if($productResult){
					return 200; //成功200
				}else{
					return '更新产品表失败';
				}
    		}else{
    			return '插入后台数据失败';
    		}
    	}else{
    		return $this -> updateDefaultCommission($sellerID,$commission);
    	}
    }
    
    /**
     *插入分类的佣金比例数据
     */
    public function addClassCommission(array $param){
    	if(empty($param)){
    		return '参数为空'; //参数为空
    	}
        $sellerID= 0;
        $calss_id= 0;
        $parent_class = isset($param['parent_class'])?$param['parent_class']:0;
        if(isset($param['seller_id']) && $param['seller_id']>0){
            $sellerID = $param['seller_id'];
        }else{
            return 'seller_id不合法'; //seller_id 不合法
        }

        //op_type：1-添加，2-修改
        if(isset($param['op_type']) && $param['op_type']==1){
            if(isset($param['class_id']) && $param['class_id']>0){
                $calss_id = $param['class_id'];
            }else{
                return 'calss_id不合法'; //calss_id 不合法
            }
        }

    	$commission =0.0;
    	if(isset($param['commission']) && $param['commission']>0){
    		$commission = $param['commission'];
    	}else{
    		return 'commission不合法'; //commission 不合法
    	}
        //编辑时对参数ID做校验（op_type：1-添加，2-修改）
    	$id= 0;
    	if(isset($param['op_type']) && $param['op_type'] == 2){
            if (isset($param['id']) && $param['id'] > 0){
                $id = $param['id'];
            }else{
                return 'id不合法'; //id 不合法
            }
    	}
    	/*$count = $this -> getProductCountByWhere(['seller_id'=>$sellerID, 'ProductStatus'=>1]);#正常在售SKU
    	if($count <1){
    		return '未添加产品或加产品未审核通过'; //未添加产品或未审核通过
    	}*/
    	
    	$dataDB = $this -> getCommissionBySellerAndClassID($sellerID,$id);
    	if(empty($dataDB)){
    		$updateData['seller_id'] =$sellerID;
    		$updateData['commission']=$commission;
    		$updateData['type']=2;
    		$updateData['class_id']=$calss_id;
            $updateData['parent_class']=$parent_class;
    		$updateData['effect_time']=$param['effect_time'];
    		$updateData['status']=0;
    		$updateData['add_time']=time();
    		$result= $this->db->table($this->table_affiliate_commission)->insert($updateData);
    		if($result){
    			return 200;
    		}else{
    			Log::record('======数据插入后台失败======='.print_r($param, true));
    			return '数据插入后台失败';    			
    		}
    	}else{
            $where = ['id'=>$param['id']];
            $up_data = [
                'commission'=>$param['commission'],
                'effect_time'=>$param['effect_time'],
                'update_time'=>$param['update_time'],
                'operater_user'=>$param['operater_user'],
                'type'=>$param['type'],
            ];
    		if($dataDB['status'] === 0 ){ //审核中时更新
    			$updateResult= $this -> updateClassCommission($where, $up_data);
    			if($updateResult){
    				return  200;
    			}else{
    				return '审核中的数据更新失败';
    			}
    		}else{ //审核通过时更新
                //是否审核:0 待审核; 1 审核通过; 2 审核不通过
                $up_data['status'] = 0;
                //是否已同步更新至商城产品表：0-未同步，1-已同步 tinghu.liu 20190802
                $up_data['is_update'] = 0;
                $updateResult= $this -> updateClassCommission($where, $up_data);
                if($updateResult){
                	return  200;
                }else{
                	return '审核通过状态的数据更新失败';
                }
            }
    	}
    }

    /**
     * 批量新增联盟营销，产品数据
     * @param array $data 要添加的数据
     * @return int|string
     */
    public function addAffiliateProductForBatch(array $data){
        $rtn = true;
        // start
        $this->db->startTrans();
        try{
            foreach ($data as $info){
                Log::record('执行事务'.print_r($info, true));
                //已存在的产品不再添加
                $pro_data = $this->getAffiliateProductByWhere(['seller_id'=>$info['seller_id'],'spu'=>$info['spu']]);
                if (empty($pro_data)){
                    $this->db->table($this->affiliate_product)->insert($info);
                }else{
                    $rtn = false;
                }
            }
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('执行批量新增联盟营销，产品数据事务出错');
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 批量更新联盟营销，产品数据
     * @param array $data 要添加的数据
     * @return int|string
     */
    public function updateAffiliateProductForBatch(array $data){
        $rtn = true;
        // start
        $this->db->startTrans();
        try{
            foreach ($data as $info){
                $id = $info['id'];
                //已审核通过不允许修改
                $pro_data = $this->getAffiliateProductByWhere(['id'=>$id])[0];
                if ($pro_data['status'] != 1){
                    $where = ['id'=> $id];
                    $up_data = [
                        'commission'=>$info['commission'],
                        'effect_time'=>$info['effect_time'],
                        'update_time'=>$info['update_time']
                    ];
                    //已驳回则修改为审核中：审核状态:0 待审核; 1 审核通过; 2 审核不通过;
                    if ($pro_data['status'] == 2){
                        $up_data['status'] = 0;
                    }
                    $this->db->table($this->affiliate_product)->where($where)->update($up_data);
                }
            }
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('执行批量更新联盟营销，产品数据事务出错');
            // roll
            $this->db->rollback();
        }
        Log::record('执行批量更新联盟营销=====:'.$rtn);
        return $rtn;
    }

    /**
     * 根据条件获取佣金产品
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getAffiliateProductByWhere(array $where){
        return $this->db->table($this->affiliate_product)->where($where)->select();
    }

    /**
     * 根据条件更新联盟营销佣金比例数据
     * @param array $where 条件
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateClassCommission(array $where, array $up_data){
        return $this->db->table($this->table_affiliate_commission)->where($where)->update($up_data);
    }

    /**
     * 根据条件获取分类佣金数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCommissionDataByWhere(array $where){
        return $this->db->table($this->table_affiliate_commission)->where($where)->select();
    }
    
    /**
	 *根据seller id 获取默认佣金比例
	 *return 单条数据
     */
    public function getDefaultCommissionBySellerID($sellerid){
    	$where['seller_id'] = $sellerid; 
    	$where['type'] = 1;
    	$query = $this->db->table($this->table_affiliate_commission)->where($where)->find();
		return $query;
    }
    
    /**
     *检查seller是否设置了某个类别的佣金比例--只可以设置一个值
     *return true-数据已存在; false-未存在
     */
    private function getCommissionBySellerAndClassID($sellerid,$id){
    	$where['seller_id'] = $sellerid;
    	$where['type'] = 2;
    	$where['id'] = $id;
    	$query = $this->db->table($this->table_affiliate_commission)->where($where)->find();
    	return $query;
    }
    
    /**
	 *更新默认的佣金比例数据
     */
    private function updateDefaultCommission($sellerid,$commission){
        $time = time();
    	$where['seller_id'] = $sellerid;
    	$where['type'] = 1;    	
    	$data['class_id']=0;
    	$data['commission'] = $commission;
    	$data['update_time'] = $time;
    	$data['effect_time'] = $time;
    	$result= $this->db->table($this->table_affiliate_commission)
		    	       ->where($where)
		    	       //->where('status','<>',1) 
		    		   ->update($data);
    	if($result){
	    	$productModel = new Porduct();
	    	$productResult= $productModel -> updateCommissionBySellerID($sellerid, $commission);
	    	if($productResult){
	    		return 200;
	    	}else{
	    		return  '更新产品佣金数据失败,$sellerid, $commission:'.$sellerid.','.$commission;
	    	}
    	}else{
    		return '更新默认佣金的数据失败';
    	}
    }

    /**
     * 获取分类默认佣金比例数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getClassDefaultData($where = ''){
        $data = $this->db->table($this->affiliate_commission_default)->field('commission,class_id')->where($where)->select();
        foreach ($data as &$info){
            //获取分类名称
            $class_id = $info['class_id'];
            $base_api = new BaseApi();
            $class_data = $base_api->getCategoryInfoByCategoryID($class_id);
            $class_name_cn = 'None';
            $class_name_en = 'None';
            if (isset($class_data['data']) && !empty($class_data['data'])){
                $class_name_cn = $class_data['data']['title_cn'];
                $class_name_en = $class_data['data']['title_en'];
            }
            $info['name_cn'] = $class_name_cn;
            $info['name_en'] = $class_name_en;
        }
        return $data;
    }

    /**
     * 根据条件获取默认分类佣金数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getClassDefaultDataByWhere(array $where){
        return $this->db->table($this->affiliate_commission_default)->where($where)->select();
    }

    /**
     * 根据分类ID获取默认分类佣金数据
     * @param array $class_id 分类ID
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getClassDefaultInfoByClassId($class_id){
        return $this->db->table($this->affiliate_commission_default)->where(['class_id'=>$class_id])->find();
    }

    /**
     * 根据ID删除佣金设置数据
     * @param $id ID
     * @return int
     */
    public function deleteCommissionById($id){
        return $this->db->table($this->table_affiliate_commission)->where(['id'=>$id])->delete();
    }

    /**
     * 获取佣金设置数据列表数据
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getClassCommissionList(array $params){
        $query = $this->db->table($this->table_affiliate_commission);
        //查询条件seller_id
        if (isset($params['seller_id']) && !empty($params['seller_id'])){
            $query->where('seller_id', '=', $params['seller_id']);
        }
        //查询条件type：设置类型：1 默认设置, 2 按类别设置
        if (isset($params['type']) && !empty($params['type'])){
            $query->where('type', '=', $params['type']);
        }
        //查询条件status：是否审核:0 待审核; 1 审核通过; 2 审核失败;
        if (isset($params['status']) && !empty($params['status'])){
            $query->where('status', '=', $params['status']);
        }
        return $query->select();
    }

    /**
     * 根据条件获取佣金产品数量
     * @param array $where 条件
     * @return int|string
     */
    public function getProductCountByWhere(array $where){
        return $this->db->table($this->affiliate_product)->where($where)->count();
    }

    /**
     * 获取主推产品列表【分页】
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getProductList(array $params){
        $base_api = new BaseApi();
        $query = $this->db->table($this->affiliate_product);
        //去除已删除数据
        $query->where('is_delete','<>', 1);
        //查询条件seller_id
        if (isset($params['seller_id']) && !empty($params['seller_id'])){
            $query->where('seller_id', '=', $params['seller_id']);
        }
        //数据类型:1 非主推产品; 2 主推产品;
        if (isset($params['type']) && !empty($params['type'])){
            $query->where('type', '=', $params['type']);
        }
        //spu
        if (isset($params['spu']) && !empty($params['spu'])){
            $query->where('spu', '=', $params['spu']);
        }
        //审核状态:0 待审核; 1 审核通过; 2 审核不通过;
        if (isset($params['status'])){
            $query->where('status', '=', $params['status']);
        }
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        //参数过滤
        $filed = 'id,seller_id,class_id,spu,commission,type,status,effect_time,remark';
        $response = $query->field($filed)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])->each(function ($item, $key) use ($base_api){
            /** 获取产品相关信息 start **/
            $spu = $item['spu'];
            $status = $item['status'];
            $pro_api = $base_api->getProductInfoByID($spu);
            //$item['product_range_price'] = isset($pro_api['data']['RangePrice'])&&!empty($pro_api['data']['RangePrice'])?$pro_api['data']['RangePrice']:'-';
            $LowPrice = isset($pro_api['data']['LowPrice'])&&!empty($pro_api['data']['LowPrice'])?$pro_api['data']['LowPrice']:'-';
            $HightPrice = isset($pro_api['data']['HightPrice'])&&!empty($pro_api['data']['HightPrice'])?$pro_api['data']['HightPrice']:'-';
            if ($LowPrice == $HightPrice){
                $item['product_range_price'] = $LowPrice;
            }else{
                $item['product_range_price'] = $LowPrice.'-'.$HightPrice;
            }

            $item['product_title'] = isset($pro_api['data']['Title'])&&!empty($pro_api['data']['Title'])?$pro_api['data']['Title']:'-';
            $item['product_img'] = isset($pro_api['data']['ImageSet']['ProductImg'][0])&&!empty($pro_api['data']['ImageSet']['ProductImg'][0])?$pro_api['data']['ImageSet']['ProductImg'][0]:'';
            /** 获取产品相关信息 end **/
            //申请状态 审核状态:0 待审核; 1 审核通过; 2 审核不通过;
            $status_str = '-';
            switch ($status){
                case 0:
                    $status_str = '待审核';
                    break;
                case 1:
                    $status_str = '已审核';
                    break;
                case 2:
                    $status_str = '已驳回';
                    break;
            }
            $item['status_str'] = $status_str;
            //佣金比例转换为百分比
            $item['commission'] *= 100;
            return $item;
        });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        /** 获取一级分类名称 start **/
        //获取一级分类数据
        $class_arr = [];
        foreach ($data['data'] as $info){
            $class_arr[] = $info['class_id'];
        }
        //获取一级分类详情
        $class_data_api = $base_api->getCategoryDataByCategoryIDData(array_unique($class_arr));
        $class_data = isset($class_data_api['data']) && !empty($class_data_api['data'])?$class_data_api['data']:[];
        //获取指定类别名称
        foreach ($data['data'] as &$d_info){
            $class_id = $d_info['class_id'];
            foreach ($class_data as $class_info){
                $class_id_info = $class_info['id'];
                if ($class_id == $class_id_info){
                    $d_info['FirstCategoryName_cn'] = $class_info['title_cn'];
                    $d_info['FirstCategoryName_en'] = $class_info['title_en'];
                    break;
                }
            }
        }
        /** 获取一级分类名称 end **/
        return $data;
    }

    /**
     * 获取分类默认佣金比例
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCommissionByClassId($params){    
    	try{
    		$data = $this->db->table($this->affiliate_commission_default)
    		->field('commission,class_id')
    		->where('class_id','in',$params)
    		->select();
    	}catch (\Exception $e){   			
   			return $e->getMessage();
   		}
    	   	 	
    	return $data;
    }
    
    /**
     * 插入Affiliate信息
     * @param unknown $params
     * @return unknown
     */
    public function insertAffiliateData($params){   	
    	$order = $params['order'];
    	$order_item = $params['item'];
    	
    	$this->db->startTrans();
    	try {
    		//插入master订单
    		$this->db->table($this->order)->insert($order);
    		$affiliateId = $this->db->getLastInsID();
    		foreach ($order_item as $k=>$v){
    			$item = $v;
    			$item['affiliate_order_id'] = $affiliateId;
    			$this->db->table($this->order_item)->insert($item);   				
    		}
    		$this->db->commit();
	
    		}catch (\Exception $e){   			
    			$this->db->rollback();
   				return $e->getMessage();
   			}
    	return true;
    }
    
    /**
     * 检查Affiliate信息
     * @param unknown $params
     * @return unknown
     */
    public function checkAffiliateByOrderNumber($params){
    	try{
    		$data = $this->db->table($this->order)
    		->field('affiliate_order_id,order_number')
    		->where('order_number','in',$params)
    		->select();
    	}catch (\Exception $e){   			
   			return $e->getMessage();
   		}
    	   	 	
    	return $data;
    }

    /**
     * 更新affiliate订单数据
     * @param array $up_data
     * @param array $where
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateAffiliateOrder(array $up_data, array $where){
        return $this->db->table($this->order)->where($where)->update($up_data);
    }

    /**
     * 获取affiliate佣金列表
     * @param array $where
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function getAffiliateCommission($where,$page_size=20,$page=1,$path='',$is_page=1){
        if($is_page){
            $res = $this->db->table($this->order)
                ->alias("o")
                ->join($this->order_item." oi","o.affiliate_order_id = oi.affiliate_order_id","LEFT")
                ->field("o.*")
                ->group("o.affiliate_order_id")
                ->order("o.affiliate_order_id desc")
                ->where($where)
                ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);$Page = $res->render();
            $data = $res->toArray();
            foreach ($data['data'] as $key=>$value){
                $order_item_where['affiliate_order_id'] = $value['affiliate_order_id'];
                $data['data'][$key]['commission_price'] = $this->db->table($this->order_item)->where($order_item_where)->sum("commission_price");
            }
            $data['Page'] = $Page;
        }else{
            $where['commission_price'] = ['gt',0];
            $data = $this->db->table($this->order)
                ->alias("o")
                ->join($this->order_item." oi","o.affiliate_order_id = oi.affiliate_order_id","RIGHT")
                ->field("o.*,oi.product_id,oi.product_title,oi.product_img,oi.sku_id,oi.sku_code,oi.sku_count,oi.price product_price,oi.commission_rate,oi.total_amount product_total_amount,oi.commission_price,oi.first_category_id")
                ->order("o.affiliate_order_id desc")
                ->where($where)
                ->select();
        }
        return $data;
    }
    public function InvalidOrder($params=[]){
          $where = [];
          $data = [];
          $where['O.order_number'] = ['in',$params['order_number']];
          $where['O.order_status'] = ['in','1400,1700'];//A.order_status = 1400 AND B.order_status = 1400
          $where['C.order_status'] = ['in','1400,1700'];
          $list = $this->or->table($this->sales_order)
                ->alias("O")
                ->join($this->sales_order_status_change." C","O.order_id = C.order_id")
                ->field("O.order_number,C.change_reason_id,C.change_reason")
                ->where($where)
                ->select();
                 // file_put_contents ('../runtime/log/201904/1212.log',$this->or->getLastSql().',', FILE_APPEND|LOCK_EX);
// return  apiReturn(['code'=>200,'data'=>$list]);
          if(!empty($list)){
              $order_cancel_reason = config('order_cancel_reason');
              foreach ($list as $k => $v) {
                  if(!empty($v['change_reason_id'])  && empty($v['change_reason'])){
                       foreach ($order_cancel_reason as $ke => $ve) {
                           if($ve['code'] == $v['change_reason_id']){
                                    $v['change_reason'] = $ve['en_name'];
                           }
                       }
                  }
                  $data[$v['order_number']] = $v;
              }
              return  apiReturn(['code'=>200,'data'=>$data]);
          }
          return apiReturn(['code'=>1002,'msg'=>'没数据']);
    }

     /**
     * 报表统计导出
     * [ReportStatistics description]
     */
    public  function ReportStatistics($where=array(),$data=array(),$order_item = array(),$type=1){

        $affiliate_id = array();
        $sku_data = array();
        $list_data = array();
        $order_price = array();
        $InvalidOrder = array();
        $InvalidOrderID = array();
        $invalid_order = [];
        $applyRefund = [];
        $order_refund = [];
        $OrderRemarks = [];
        $page = 1;
        if(empty($data['page_size'])){    $data['page_size'] = 30;  }
        if(!empty($data['page'])){   $page = $data['page'];  }
        if(isset($where['affiliate_order_id'])){
            $where['o.affiliate_order_id'] = $where['affiliate_order_id'];
            unset($where['affiliate_order_id']);
        }
        if(isset($where['create_on'])){
            $where['o.create_on'] = $where['create_on'];
            unset($where['create_on']);
        }
        if($type == 1){
            $list = $this->db->table($this->order)
                ->alias("o")
                ->where($where)
                ->page($page,$data['page_size'])
                ->order('affiliate_order_id asc')
                ->select();
        }else{
            $list = $this->db->table($this->order)
                ->alias("o")
                ->join($this->order_item." oi","o.affiliate_order_id=oi.affiliate_order_id")
                ->where($where)
                ->page($page,$data['page_size'])
                ->order('o.affiliate_order_id asc')
                ->field("o.*,oi.sku_code,oi.sku_id,oi.sku_count,oi.price as item_price,oi.total_amount,oi.dollar_price,oi.commission_price,oi.commission_rate,oi.commission_type,oi.first_category_id")
                ->select();
        }
        $time_log =  date("Ym",time());
        //file_put_contents ('../runtime/log/'.$time_log.'/affiliate_daochu_2.log',$this->db->getLastSql().'------------------------------------------', FILE_APPEND|LOCK_EX);
        if(!empty($list)){
            foreach ($list as $k => $v) {
                if(!in_array($v['affiliate_order_id'],$affiliate_id)){
                    $affiliate_id[] = $v['affiliate_order_id'];
                    $InvalidOrder[] = $v['order_number'];
                    $InvalidOrderID[] = $v['order_id'];
                }
                // if($v['order_status'] == 1400){
                //     $InvalidOrder .= $v['order_number'].',';
                // }
            }

            //获取订单日志表备注订单日志
            $invalid_order = $this->InvalidOrder(['order_number' =>$InvalidOrder]);
            if($invalid_order['code']==200){
                $invalid_order = $invalid_order['data'];
            }else{
                $invalid_order = [];
            }
            if(!empty($InvalidOrderID)){
                $OrderRemarks = model("OrderRemarks")->getOrderRemarks(['order_id' =>["IN",$InvalidOrderID]]);
            }

            //订单退款退货换货表记录
            $applyRefund = $this->RrderAfterSaleApply(['order_number' =>['in',$InvalidOrder],'status'=>5]);
            //订单退款退货换货表记录
            $order_refund = $this->OrderRefund(['order_number' =>['in',$InvalidOrder],'status'=>2]);
            //订单备注

            if(!empty($affiliate_id)){
                   $order_item['affiliate_order_id'] = ['in',$affiliate_id];
                   $list_sku = $this->db->table($this->order_item)->where($order_item)->field('affiliate_order_id,sku_id,sku_code,commission_price')->select();
                   if(!empty($list_sku)){
                       foreach ($list_sku as $ke => $va) {
                              if(!empty($va['sku_code'])){
                                  if(!empty($va['affiliate_order_id']) && empty($sku_data[$va['affiliate_order_id']])){
                                     $sku_data[$va['affiliate_order_id']] = '';
                                  }
                                  if(empty($order_price[$va['affiliate_order_id']])){
                                      $order_price[$va['affiliate_order_id']] = $va['commission_price'];
                                  }else{
                                      $order_price[$va['affiliate_order_id']] = $order_price[$va['affiliate_order_id']] + $va['commission_price'];
                                  }
                                  $sku_data[$va['affiliate_order_id']] .= $va['sku_code'].',';
                              }
                       }
                   }
            }
        }
        // $list['sql'] = Db::name(AFFILIATE_ORDER)->getLastSql();
        return array('list'=>$list,'list_sku'=>$sku_data,'order_price'=>$order_price,'invalid_order'=>$invalid_order,'applyRefund' => $applyRefund,'order_refund'=>$order_refund,'order_remarks'=>$OrderRemarks);
    }
    /**
     * 从sku表开始查
     * 报表统计
     * [ReportStatistics description]
     */
    public  function ReportStatistics_sku($where = array(),$data = array(),$order_item = array()){
         $list_sku = $list_sku = $this->db->table($this->order_item)->where($order_item)->field('affiliate_order_id,sku_id,sku_code,commission_price')->order('add_time desc')->select();
          // dump(Db::name(self::affiliate_order_item)->getLastSql());
         $affiliate_id = '';
         $list = array();
         $order_price = array();
         $sku_data = [];
         $InvalidOrder = '';
         if(!empty($list_sku)){
             foreach ($list_sku as $ke => $va) {
                   $affiliate_id .= $va['affiliate_order_id'].',';
                   if(empty($sku_data[$va['affiliate_order_id']])){
                      $sku_data[$va['affiliate_order_id']]  = $va['sku_code'].',';
                   }else{
                      $sku_data[$va['affiliate_order_id']] .= $va['sku_code'].',';
                   }

                   if(empty($order_price[$va['affiliate_order_id']])){
                      $order_price[$va['affiliate_order_id']] = $va['commission_price'];
                   }else{
                      $order_price[$va['affiliate_order_id']] = $order_price[$va['affiliate_order_id']] + $va['commission_price'];
                   }
             }
             if(!empty($affiliate_id)){
                  $where['affiliate_order_id'] = ['in',rtrim($affiliate_id,',')];
             }
             $list = $this->db->table($this->order)->where($where)->page($data['page'],$data['page_size'])->order('create_on desc')->select();
             if(!empty($list)){
                  foreach ($list as $k => $v) {
                     $InvalidOrder .= $v['order_number'].',';
                     // if($v['order_status'] == 1400){  }
                  }

                  $InvalidOrder = rtrim($InvalidOrder, ",");
                  $invalid_order = $this->InvalidOrder(['order_number' =>$InvalidOrder]);
                  if($invalid_order['code']==200){
                        $invalid_order = $invalid_order['data'];
                  }else{
                        $invalid_order = [];
                  }
                  //订单退款退货换货表记录
                  $applyRefund = $this->RrderAfterSaleApply(['order_number' =>['in',$InvalidOrder],'status'=>5]);
                  //订单退款退货换货表记录
                  $order_refund = $this->OrderRefund(['order_number' =>['in',$InvalidOrder],'status'=>2]);
             }
             // dump(Db::name(AFFILIATE_ORDER)->getLastSql());

         }
         return array('list'=>$list,'list_sku'=>$sku_data,'order_price'=>$order_price,'invalid_order'=>$invalid_order,'applyRefund' => $applyRefund,'order_refund'=>$order_refund);
    }
    //订单退款退货换货表记录
    public function RrderAfterSaleApply($data = []){
         if(empty($data)){ return false;}
         $data['remarks'] = ['neq',''];
         $applyRefund = [];
         $list = $this->or->table($this->order_after_sale_apply)->where($data)->field('order_number,remarks')->select();//return $this->or->getLastSql();
         // return $list;
         if(!empty($list)){
              foreach ($list as $k => $v) {
                     if(empty($applyRefund[$v['order_number']])){  $applyRefund[$v['order_number']] = '';  }
                     $applyRefund[$v['order_number']] .= $v['remarks'].';';
              }
              return $applyRefund;
         }
         return false;
    }
    /**
     * 订单退款表
     * [OrderRefund description]
     */
    public function OrderRefund($data = []){
         if(empty($data)){ return false;}
         $data['remarks'] = ['neq',''];
         $list = $this->or->table($this->order_refund)->where($data)->field('order_number,remarks')->select();//return $this->or->getLastSql();
         // return $list;
         if(!empty($list)){
              foreach ($list as $k => $v) {
                     if(empty($applyRefund[$v['order_number']])){  $applyRefund[$v['order_number']] = '';  }
                     $applyRefund[$v['order_number']] .= $v['remarks'].';';
              }
              return $applyRefund;
         }
         return false;
    }

    /*
* 获取用户选择订单积分
* */
    public function OrderAffiliateCredit($data){
        $one_month_withdrawal_max_amount = 1000;
        $one_month_withdrawal_min_amount = 30;
        $cash_withdrawal = 0;
        $withdrawal_amount = 0;//最多提取金额
        $cumulative_amount = 0;//累积金额
        $res_array = [];
        $time = strtotime(date("Y-m-01 00:00:00"));
// return $time;
        $cash_withdrawal = $this->db->table($this->affiliate_apply)->where(['affiliate_id'=>$data,'status'=>["in","0,2"],'add_time'=>['egt',$time]])->sum("amount");
        $cash_withdrawal = !empty($cash_withdrawal)?$cash_withdrawal:0;
        $withdrawal_amount = $one_month_withdrawal_max_amount - $cash_withdrawal;//一个月最多只能体现1000美元，每次大于等于30美元
        if($withdrawal_amount < $one_month_withdrawal_min_amount){
            return apiReturn(['code'=>101,'msg'=>'Your monthly quota has reached the limit and you cannot withdraw it again!']);
        }elseif($withdrawal_amount>$one_month_withdrawal_max_amount){//如果可提现接没有超出最大提现金额，获取全部提现订单
            $list = $this->db->table($this->order)
                ->where(['affiliate_id'=>$data,'settlement_status'=>2])
                ->field("affiliate_order_id,order_number,total_valid_commission_price")
                ->select();
        }else{//如果是可提现金额超过了最大提现金额，则获取最大1500个订单
            $list = $this->db->table($this->order)
                ->where(['affiliate_id'=>$data,'settlement_status'=>2])
                ->field("affiliate_order_id,order_number,total_valid_commission_price")
                ->limit(0,1500)
                ->select();
        }
        $where = '';
        $where_array = [];
        if(empty($list)){
            return apiReturn(['code'=>101,'msg'=>'The withdrawal amount has not been reached.']);
        }
        $affiliate_order_id = '';
        foreach ($list as $k => $v) {
            $where .= $v['affiliate_order_id'].',';
            $affiliate_order_id .= $v['affiliate_order_id'].',';
            $cumulative_amount += $v['total_valid_commission_price'];
            if($cumulative_amount <= $withdrawal_amount){
                $res_array['cumulative_amount'] = $cumulative_amount;
                $res_array['affiliate_order_id'] = $affiliate_order_id;
            }else{
                break;
            }
        }
        $where = rtrim($where,',');
        $res = $this->db->table($this->order_item)->where(['affiliate_order_id'=>['in',$where]])->group('affiliate_order_id')->field("affiliate_order_id,sum(commission_price) AS commission_price")->select();
        if(!empty($res)){
            // return apiReturn(['code'=>101,'msg'=>$res,'as'=>$res_array,'sql'=>$this->db->table($this->order_item)->getlastsql()]);
            if($res_array['cumulative_amount'] >= $one_month_withdrawal_min_amount){
                return apiReturn(['code'=>200,'data'=>$res_array]);
            }else{
                return apiReturn(['code'=>101,'msg'=>'Withdrawal amount must be greater than $30.']);
            }
        }else{
            return apiReturn(['code'=>101,'msg'=>'The withdrawal amount has not been reached.']);
        }
        return $list;
    }

    /**
     *获取产品毛利率（何元接口）
     *return 单条数据
     */
    public function getProductProfit($where){
        return $this->db->table($this->product_newprice)->where($where)->select();
    }

    /**
     *获取产品采购价（何元接口）
     *return 单条数据
     */
    public function getProductPurchaseCost($where){
        return $this->db->table($this->product_purchase_cost)->where($where)->find();
    }

    /**
     *获取产品毛利率（张总接口）
     *return 单条数据
     */
    public function getProductProfitNew($where){
        return $this->db->table($this->product_newprice_new)->where($where)->select();
    }
}
