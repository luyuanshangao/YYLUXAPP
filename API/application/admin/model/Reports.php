<?php
namespace app\admin\model;
use app\log\model\Log;
use think\Model;
use think\Db;
use vendor\aes\aes;
class Reports extends Model
{
    protected $table = 'dx_reports';
    protected $table_reports_customs_insurance = 'dx_reports_customs_insurance';
    protected $db = 'dx_reports';

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
            ->field("id,customer_id,report_type,product_url,reason,enclosure,email,phone,order_number,currency_code,amount,report_status,add_time,operator,operator_id,edit_time,seller_id,seller_name,customer_name,SPU,reply,PayPalEU,PayPalED")
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
            if(!empty($value['PayPalEU'])){
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->decrypt($value['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $data['data'][$key]['PayPal'] = $EmailUserName."@".$value['PayPalED'];
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取列表
     * @param $where
     * @param int $page_size
     * @param int $page
     * @param string $path
     * @return array
     */
    public function getListForFinancial($where,$page_size=10,$page=1,$path='',$b_status=-1)
    {
        if ($b_status != -1 && !empty($b_status)){
            $where['b.status'] = $b_status;
        }
        $res = $this->db->table($this->table)
            ->alias("a")
            ->join($this->table_reports_customs_insurance." b","a.id = b.reports_id")
            ->where($where)
            ->field("a.id,a.customer_id,a.report_type,a.product_url,a.reason,a.enclosure,a.email,a.phone,a.order_number,a.currency_code,a.amount,a.report_status,a.add_time,a.operator,a.operator_id,a.edit_time,a.seller_id,a.seller_name,a.customer_name,a.SPU,a.reply,a.PayPalEU,a.PayPalED,a.from,b.id as b_id, b.reports_id as b_reports_id,b.status as b_status,b.finance_status as b_finance_status, b.update_user_name as b_update_user_name, b.update_time as b_update_time, b.add_user_name as b_add_user_name, b.add_time as b_add_time")
            ->order("id","desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $ids = [];
        foreach ($data['data'] as $k=>$v){
            $ids[] = $v['id'];
        }
        $log_data = $this->db->table("dx_reports_log")->where(['reports_id'=>['in',$ids]])->select();
        foreach ($data['data'] as $key=>$value){
            $data['data'][$key]['report_type_data'] = config("report_type.".$value['report_type']);
            $data['data'][$key]['report_status_data'] = config("report_status.".$value['report_status']);
            foreach ($log_data as $lkey => $lvalue){
                if($value['id'] == $lvalue['reports_id']){
                    $data['data'][$key]['log'][] = $lvalue;
                }
            }
            if(!empty($value['PayPalEU'])){
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->decrypt($value['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $data['data'][$key]['PayPal'] = $EmailUserName."@".$value['PayPalED'];
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

    /**
     * 新增【admin使用】
     * @param $data
     * @return bool
     */
    public function saveReportsForAdmin($data){
        $rtn = true;
        $this->db->startTrans();
        try{
            //1、新增reports
            $reports_id = $this->db->table($this->table)->insertGetId($data);
            $insert_data['reports_id'] = $reports_id;
            $insert_data['status'] = 0;
            $insert_data['finance_status'] = 0;
            $insert_data['add_user_id'] = '';
            $insert_data['add_user_name'] = '';
            $insert_data['add_user_id'] = $data['operator_id'];
            $insert_data['add_user_name'] = $data['operator'];
            $insert_data['add_time'] = $data['add_time'];
            //2、新增dx_reports_customs_insurance表数据
            $this->db->table($this->table_reports_customs_insurance)->insertGetId($insert_data);
            $this->db->commit();
        } catch (\Exception $e) {
            // roll
            $rtn = false;
            $this->db->rollback();
        }
        return $rtn;
    }

    /*获取详情*/
    public function getReport($where){
        $res = $this->db->table($this->table)->where($where)->find();
        return $res;
    }

    /*获取指定条件Report数量*/
    public function getReportCount($where){
        return $this->db->table($this->table)->where($where)->count();
    }

    public function getReportslist($field='*',$where=[],$limit=1){
        $list=$this->db->table($this->table)->field($field)->where($where)->limit($limit)->select();
        return $list;
    }

    public function updateReports($where,$data){
        $res=$this->db->table($this->table)->where($where)->update($data);
        return $res;
    }

}
