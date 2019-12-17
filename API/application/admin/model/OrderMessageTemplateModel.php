<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class OrderMessageTemplateModel extends Model
{
    protected $table = 'dx_order_message_template';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /*
     * 获取模板详情
     * add by 20190429 kevin
     * */
    public function getOrderMessageTemplateInfo($where)
    {
        $res = $this->db->table($this->table)
            ->where($where)
            ->find();
        return $res;
    }

}
