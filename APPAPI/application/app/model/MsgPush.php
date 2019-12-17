<?php
namespace app\app\model;

use think\Model;
use think\Db;

/**
 * 订单统计模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class MsgPush extends Model
{
    protected $connection='db_admin';
    protected $table = 'dx_msg_push';


    /**
     * 获取用户消息列表
     * */
    public function getList($where=[], $page_size = 10, $page = 1, $path = '', $order = 'id desc', $PageQuery = array())
    {
        $data = $this->where($where)
            ->order($order)
            ->paginate($page_size);
        return $data;
    }
}