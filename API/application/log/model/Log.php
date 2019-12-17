<?php
namespace app\log\model;
use think\Model;
use think\Db;
/**
 * Log模型
 * @author
 * @version Kevin 2018/3/15
 */
class Log extends Model{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_log');
    }

    /**
     * 添加日志数据
     * @param $data
     * @return bool|string
     */
    public function operationLog(array $data,$table){
        return $this->db->table($table)->insert($data);
    }

}