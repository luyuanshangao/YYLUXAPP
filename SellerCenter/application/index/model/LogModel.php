<?php
namespace app\index\model;

use think\Db;
use think\Model;

/**
 * 日志模型
 * Created by tinghu.liu
 * Date: 2018/10/19
 */

class LogModel extends Model{
    protected $db;
    // 设置当前模型对应的完整数据表名称
    protected $log_seller_operation_record = 'log_seller_operation_record';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_log');
    }

    /**
     * 添加seller操作日志数据
     * @param $data
     * @return bool|string
     */
    public function insertOperationRecord(array $data){
        return $this->db->table($this->log_seller_operation_record)->insert($data);
    }

}