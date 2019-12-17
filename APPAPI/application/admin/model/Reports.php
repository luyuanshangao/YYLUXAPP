<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class Reports extends Model
{
    protected $table = 'dx_reports';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }
    /*
     * 获取列表
     * */
    public function getList($where,$page_size=10,$page=1,$path='')
    {
        $res = $this->db->table($this->table)
            ->where($where)
            ->field("id,customer_id,report_type,product_url,reason,enclosure,email,phone,order_number,currency_code,amount,report_status,add_time,operator,operator_id,edit_time,seller_id,seller_name,SPU")
            ->order("id","desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $ids =  $this->db->table($this->table)->where($where)->column("id");
        $log_data = $this->db->table("dx_reports_log")->where(['reports_id'=>['in',$ids]])->select();
        foreach ($data['data'] as $key=>$value){
            $data['data'][$key]['report_type_data'] = config("report_type.".$value['report_type']);
            $data['data'][$key]['report_status_data'] = config("report_status.".$value['report_status']);
            foreach ($log_data as $lkey => $lvalue){
                if($value['id'] == $lvalue['reports_id']){
                    $data['data'][$key]['log'][] = $lvalue;
                }
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取列表
     * */
    public function getListForSeller($where,$page_size=10,$page=1,$path='')
    {
        $res = $this->db->table($this->table)
            ->where($where)
            ->field("id,customer_id,report_type,product_url,reason,enclosure,email,phone,order_number,currency_code,amount,report_status,add_time,operator,operator_id,edit_time,seller_id,seller_name,SPU")
            ->order("id","desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $ids =  $this->db->table($this->table)->where($where)->column("id");
        $log_data = $this->db->table("dx_reports_log")->where(['reports_id'=>['in',$ids]])->select();
        foreach ($data['data'] as $key=>$value){
            $data['data'][$key]['report_type_data'] = config("report_type.".$value['report_type']);
            $data['data'][$key]['report_status_data'] = config("report_status.".$value['report_status']);
            foreach ($log_data as $lkey => $lvalue){
                if($value['id'] == $lvalue['reports_id']){
                    $data['data'][$key]['log'][] = $lvalue;
                }
            }
            $data['data'][$key]['enclosure'] = json_decode(htmlspecialchars_decode($value['enclosure']), true);
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 修改消息
     * */
    public function saveReports($data,$where=''){
        if(empty($where)){//没有条件新增
            $res = $this->db->table($this->table)->insertGetId($data);
            return $res;
        }else{
            $res = $this->db->table($this->table)->where($where)->update($data);
        }
        return $res;
    }

}
