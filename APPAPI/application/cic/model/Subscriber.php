<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 用户订阅模型
 * @author
 * @version Kevin 2018/5/24
 */
class Subscriber extends Model{
    protected $table = 'cic_subscriber';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 新增用户订阅
* */
    public function addSubscriber($data){
        $where['EmailUserName'] = $data['EmailUserName'];
        $where['EmailDomainName'] = $data['EmailDomainName'];
        $count = $this->db->table($this->table)->where($where)->count();
        if($count>0){
            $res = $this->db->table($this->table)->where($where)->update($data);
        }else{
            $res = $this->db->table($this->table)->insertGetId($data);
        }
        return $res;
    }

    /*
     * 更改订阅激活状态
     * */
    public function editSubscriberActive($CustomerID,$Active){
        $where['CustomerId'] = $CustomerID;
        $update_data['Active'] = $Active;
        $update_data['EditTime'] = time();
        $res = $this->db->table($this->table)->where($where)->update($update_data);
        return $res;
    }

    /*
     * 更改订阅数据
     * */
    public function updateSubscriber($where,$data){
        $data['EditTime'] = time();
        return $this->db->table($this->table)->where($where)->update($data);
    }

    /*
    * 获取用户订阅详情
    * */
    public function getSubscriber($where,$type=1){
        if($type==1){
            $res = $this->db->table($this->table)->where($where)->field("ID,CustomerId,Active,EmailUserName,EmailDomainName,SiteId,CreateTime")->find();
        }else{
            $res = $this->db->table($this->table)->where($where)->field("ID,CustomerId,Active,EmailUserName,EmailDomainName,SiteId,CreateTime")->select();
        }
        return $res;
    }

    /*
     * 检测是否已订阅
     * */
    public function checkSubscriber($where){
        $res = $this->db->table($this->table)->where($where)->field("CustomerId,EmailUserName,EmailDomainName")->find();
        return $res;
    }

    /*
      * 获取用户订阅用户
      * @param: array
      * @Return: array
      * */
    public function getSubscriberCustomers($where,$limit){
        $data = $this->db->name('subscriber')->where($where)->limit($limit)->field("CustomerId,EmailUserName,EmailDomainName")->select();
        return $data;
    }

    /*
      * 获取用户订阅用户
      * @param: array
      * @Return: array
      * */
    public function GetSimpleSubscribers($where,$pageIndex,$totalRecord){
        $subscriber_data = $this->db->name('subscriber')->where($where)->field("CustomerId,EmailUserName,EmailDomainName")->page($pageIndex,$totalRecord)->select();
        $data['CustomersData'] = $subscriber_data;
        // $Page = $count;
        $count = $this->db->name('subscriber')->where($where)->count();
        $data["TotalRecord"] = $count;
        return $data;
    }

    /*
      * 更改优惠券最后发送时间
      * @param: array
      * @Return: array
      * */
    public function updateSubscriberEndSendCoupon($where){
        $update_data['EndSendCoupon'] = time();
        $data = $this->db->name('subscriber')->where($where)->update($update_data);
        return $data;
    }

}