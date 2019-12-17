<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 信用卡模型
 * @author
 * @version Kevin 2018/3/25
 */
class CreditCard extends Model{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 保存用户信用卡
* */
    public function saveCreditCard($data,$third_data=''){
        $db = Db::connect('db_cic');
        if(empty($data['ID'])){
            $res = $db->transaction(function() use ($data,$third_data){
                $data['ID'] = guid();
                $res = $this->db->name('credit_card')->insert($data);
                if(!empty($third_data)){
                    $third_data['CreditCardID'] = $data['ID'];
                    $this->db->name('credit_card_third_id')->insertGetId($third_data);
                }
                return $data['ID'];
            });
        }else{
            $res = $db->transaction(function() use ($data,$third_data){
                $res = $this->db->name('credit_card')->update($data);
                if(isset($third_data['ThirdId']) && !empty($third_data['ThirdId'])){
                    $this->db->name('credit_card_third_id')->update($third_data);
                }
                return $data['ID'];
            });

        }
        return $res;
    }

    /*
    * 获取用户全部信用卡
    * */
    public function getCreditCards($where){
        $db = Db::connect('db_cic');
        $where['DeleteTime'] = 0;
        $res = $db->name("credit_card")->where($where)->group("FirstSixDigits")->select();
        return $res;
    }

    /*
    * 获取用户单条信用卡
    * */
    public function getCreditCard($where){
        $db = Db::connect('db_cic');
        $where['DeleteTime'] = 0;
        $res = $db->name("credit_card")
            ->alias("c")
            ->join("cic_credit_card_third_id ct","c.ID = ct.CreditCardID","LEFT")
            ->field("c.*,ct.ThirdId,ct.GatewayID")
            ->where($where)->find();
        return $res;
    }

    /*
     * 获取第三方ID记录
     * */
    public function getCreditCardThirdId($where){
        $res = $this->db->name("credit_card_third_id")->where($where)->find();
        return $res;
    }

    /*
     * 更改信用卡
     * */
    public function updataCreditCard($where,$update){
        $res = $this->db->name("credit_card")->where($where)->update($update);
        return $res;
    }
    /*
     *物理删除信用卡
     * */
    public function delCreditCard($where){
        $credit_card_data = $this->db->name("credit_card")->where($where)->field("CustomerID,FirstSixDigits,LastFourDigits")->find();
        if($credit_card_data){
            $del_where = $credit_card_data;
            $res = $this->db->name("credit_card")->where($del_where)->delete();
        }else{
            $res = 1;
        }

        return $res;
    }

    /*
     *获取信用卡ID
     * */
    public function getCreditCardIDByToken($Token){
        $res = $this->db->name("credit_card")->where(['Token'=>$Token])->value("ID");
        return $res;
    }
}