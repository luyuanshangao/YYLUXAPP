<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use app\common\redis\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\Session;

/**
 * EDM
 * @author zhongyang
 */
class EDMRecipientModel extends Model{

    protected $tb_recipient_list = 'dx_recipient_list';
    protected $tb_recipient_list_line = 'dx_recipient_list_line';
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getRecipientDataPaginate($where = array(), $page_size=10){
        return Db::table($this->tb_recipient_list)->where($where)->order('id','desc')->paginate($page_size);
    }

    public function getRecipientDataInsert($params = array()){

        Db::table($this->tb_recipient_list)->insert($params);
        $LineId = Db::name($this->tb_recipient_list)->getLastInsID();
        return $LineId;
    }

    /**
     * 列表
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function selectRecipientData($where = array()){
        return Db::table($this->tb_recipient_list)->where($where)->field(['id','title'])->order('id desc')->limit(50)->select();
    }

     //获取详细信息
    public function getRecipientLineDataPaginate($RecipientId){

        return Db::table($this->tb_recipient_list_line)->where(['RecipientID'=>$RecipientId])->order('id','desc')->select();
    }

    public function getRecipientLineById($LineId){
        return Db::table($this->tb_recipient_list_line)->where(['id'=>$LineId])->order('id','desc')->select();
    }

    public function updateRecipientLine($where,$update)
    {
        return Db::table($this->tb_recipient_list_line)->where($where)->update($update);
    }

    /**
     * 查找单条数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRecipient($where = array()){
        return Db::table($this->tb_recipient_list)->where($where)->find();
    }

    /**
     * 更新
     * @param $where
     * @param $update
     * @return int|string
     * @throws Exception
     */
    public function updateRecipient($where,$update){
        return Db::table($this->tb_recipient_list)->where($where)->update($update);
    }

    /**
     * 删除
     * @param $id
     * @return int
     * @throws Exception
     */
    public function delRecipient($id){
        return Db::table($this->tb_recipient_list)->where(['id' => $id])->delete();
    }


}