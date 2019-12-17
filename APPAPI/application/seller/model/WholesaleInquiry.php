<?php
namespace app\seller\model;

use think\Model;
use think\Db;
/**
 * 批发询价模型
 * Class WholesaleInquiry
 * @author tinghu.liu 2018/06/11
 * @package app\seller\model
 */
class WholesaleInquiry extends Model{
    /**
     * sl_wholesale_inquiry 表
     * @var string
     */
    protected $table = 'sl_wholesale_inquiry';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }

    /**
     * 新增数据
     * @param array $data 要新增的数据
     * @return int|string 新增后的主键ID
     */
    public function addData(array $data){
        return $this->db->table($this->table)->insertGetId($data);
    }
}