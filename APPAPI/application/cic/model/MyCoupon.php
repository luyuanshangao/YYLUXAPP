<?php
namespace app\cic\model;
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
            $res = $this->db->table($this->table)->where($where)->order("id desc")->field("id,coupon_id,coupon_sn,customer_id,is_used,order_number,order_id,start_time,end_time,add_time,edit_time,type,delete_time")->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
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
        $update_data['order_id'] = $data['order_id'];
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
        if($res){
            foreach ($res as $key => $value){
                $res_query[$value['coupon_id']] = $value['coupon_count'];
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
        $where['coupon_id'] = $params['coupon_id'];
        $where['coupon_sn'] = $params['coupon_sn'];
        $where['customer_id'] = $params['customer_id'];
        $where['is_used'] = 1;
        $id = $this->db->table($this->table)->where($where)->value("id");
        if($id){
            $params['is_used'] = 2;
            $params['add_time'] = time();
            $res = $this->db->table($this->table)->where(['id'=>$id])->update($params);
        }else{
            $params['edit_time'] = time();
            $params['is_used'] = 2;
            $res = $this->db->table($this->table)->insertGetId($params);
        }
        return $res;
    }
}