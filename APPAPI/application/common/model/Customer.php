<?php
namespace app\common\model;

use think\Model;
use think\Db;

/**
 * 订单统计模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class Customer extends Model
{
    protected $connection='db_cic';
    protected $table = 'cic_customer';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'CreateOn';
    protected $updateTime = 'UpdateTime';

}