<?php
namespace app\mallextend\model;

use think\Model;
use think\Db;

/**
 * 邮件模板模型类
 * @author tinghu.liu 2018/5/31
 */
class EmailtemplateModel extends Model{
    private  $db = '';
    protected $table = 'dx_email_templet';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /*
     * 获取列表
     * */
    public function getDataByParams(array $params){
        //去除已删除的数据
        $where['isdelete'] = 0;
        //邮件模板类型：1-Buyer，2-Seller
        if (isset($params['type']) && !empty($params['type'])){
            $where['type'] = (int)$params['type'];
        }
        //邮件模板类型：1-Buyer，2-Seller
        if (isset($params['templetValueID']) && !empty($params['templetValueID'])){
            $where['templetValueID'] = (int)$params['templetValueID'];
        }
        return $this->db->table($this->table)->where($where)->select();
    }
}