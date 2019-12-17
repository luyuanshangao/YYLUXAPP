<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 *
 * @author
 * @version Kevin 2018/3/25
 */
class CustomerTaxInfo extends Model{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
        $this->table = "cic_customer_tax_info";
    }
    /*
* 保存信息
* */
    public function AddOrUpdateCustomerTaxId($data){
        $where['Cicid'] = $data['Cicid'];
        if(isset($data['TaxIdType'])){
            $where['TaxIdType'] = $data['TaxIdType'];
        }
        $count = $this->db->table($this->table)->where($where)->count();
        if($count>0){
            $data['UpdateTime'] = time();
            $res = $this->db->table($this->table)->where($where)->update($data);
        }else{
            $res = $this->db->table($this->table)->insertGetId($data);
        }
        return $res;
    }


    /*
     * 获取信息
     * */
    public function FindCustomerTaxId($where){
        $res = $this->db->table($this->table)->where($where)->field("Cicid,TaxId,PersonalId,TaxIdType IdType")->order("Id desc")->find();
        return $res;
    }
}