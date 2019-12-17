<?php
namespace app\cic\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 优惠券模型
 * @author
 * @version Kevin 2018/3/25
 */
class MyCoupon extends Model{
    protected $table = 'cic_my_coupon';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 新增用户优惠券
* */
    public function addCoupon($data){
        $res = $this->db->table($this->table)->insertGetId($data);
        return $res;
    }

    /*
* 删除优惠券
* */
    public function delCoupon($ID){
        $where['ID'] = $ID;
        $data['IsDelete'] = 1;
        $res = $this->db->table($this->table)->where($where)->update($data);
        return $res;
    }

    /*
    * 获取用户优惠券详情列表
    * */
    public function getCouponList($where,$page_size,$page,$path,$is_page=1){
        if($is_page){
            $res = $this->db->table($this->table)->where($where)->order("id desc")->field("id,coupon_id,coupon_sn,customer_id,is_used,order_number,order_id,start_time,end_time,add_time,edit_time,type,delete_time")->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path]);
            $Page = $res->render();
            $data = $res->toArray();
            $data['Page'] = $Page;
        }else{
            $data = $this->db->table($this->table)->where($where)->order("id desc")->field("id,coupon_id,coupon_sn,customer_id,is_used,order_number,order_id,start_time,end_time,add_time,edit_time,type,delete_time")->select();
        }

        return $data;
    }

    public function usedCoupon($data){
        $where['id'] = $data['id'];
        $update_data['order_number'] = isset($data['order_number'])?$data['order_number']:'';
        $update_data['order_id'] = isset($data['order_id'])?$data['order_id']:'';;
        $update_data['is_used'] = 2;
        $update_data['edit_time'] = time();
        $res = $this->db->table($this->table)->where($where)->update($update_data);
        return $res;
    }

    public function getCouponCount($where){
        $sqlwhere = ' where 1=1';
        if(isset($where['customer_id'])){
            $sqlwhere .= " AND customer_id = {$where['customer_id']}";
        }
        if(isset($where['is_used'])){
                $sqlwhere .= " AND is_used = {$where['is_used']}";
        }

        if(isset($where['coupon_id'])){
            if(is_array($where['coupon_id'][1])){
                $coupon_ids = implode(",",$where['coupon_id'][1]);
            }else{
                $coupon_ids = $where['coupon_id'][1];
            }

            $sqlwhere .= " AND coupon_id  {$where['coupon_id'][0]} ({$coupon_ids})";

        }
        $res = $this->db->query("SELECT coupon_id,COUNT(coupon_id) AS coupon_count FROM ".$this->table." ".$sqlwhere." GROUP BY coupon_id");
        $res_query = array();
        $has_id_arr = [];
        if($res){
            foreach ($res as $key => $value){
                $res_query[$value['coupon_id']] = $value['coupon_count'];
                $has_id_arr[] = $value['coupon_id'];
            }
        }
        //如果查不到数据，则说明没有coupon使用记录，默认数量为0 tinghu.liu 20190412
        $coupon_ids_arr = explode(',', $coupon_ids);
        $ids_diff = array_diff($coupon_ids_arr, $has_id_arr);
        if (!empty($ids_diff)){
            foreach ($ids_diff as $k=>$v){
                $res_query[$v] = 0;
            }
        }
        return $res_query;

    }

    /**
     * 查询coupon
     * @param $where
     * @return int|string
     * @throws \think\Exception
     */
    public function getUserCouponCode($where){
        $res = $this->db->table($this->table)->where($where)->field(['coupon_id','coupon_sn','is_used'])->select();
        return $res;
    }

    /**
     * 根据条件获取coupon数量
     * @param $params
     * @return int|string
     */
    public function getCouponCountByWhere($params){
        if(isset($params['customer_id'])){
            $where['customer_id'] = $params['customer_id'];
        }
        if(isset($params['end_time'])){
            foreach ($params['end_time'] as $key =>$value){
                $where['end_time'][$key] = trim($value);
            }
        }
        if(isset($params['is_used'])){
            $where['is_used'] = $params['is_used'];
        }
        return $this->db->table($this->table)->where($where)->count();
    }

    /*
     * 用优惠券编码判断是否存在，存在使用，不存在则添加
     * */
    public function usedCouponByCode($params){
        $id = !empty($params['id'])?$params['id']:0;
        $where['coupon_id'] = $params['coupon_id'];
        $where['coupon_sn'] = $params['coupon_sn'];
        $where['customer_id'] = $params['customer_id'];
        $where['is_used'] = 1;
        if($id == 0){
            $id = $this->db->table($this->table)->where($where)->value("id");
        }else{
            $id = $this->db->table($this->table)->where(['id'=>$id,'is_used'=>1])->value("id");
            if(empty($id)){
                Log::write("usedCouponByCode:ID-".$params['id']." is empty or is used");
                return 0;
            }
        }
        if($id){
            $params['is_used'] = 2;
            $params['edit_time'] = time();
            $res = $this->db->table($this->table)->where(['id'=>$id])->update($params);
        }else{
            $params['add_time'] = time();
            $params['edit_time'] = time();
            $params['is_used'] = 2;
            $res = $this->db->table($this->table)->insertGetId($params);
        }
        return $res;
    }

    /**
     * 更新coupon数据
     * @param array $up_data
     * @param array $where
     * @return int|string
     */
    public function updateCouponDataByWhere(array $up_data, array $where){
        return $this->db->table($this->table)->where($where)->update($up_data);
    }
}