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
    protected $order_item = 'dx_affiliate_order_item';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
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
    public function getClassDefaultData(){
        $data = $this->db->table($this->affiliate_commission_default)->field('commission,class_id')->select();
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

}
