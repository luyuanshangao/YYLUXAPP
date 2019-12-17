<?php
namespace app\common\model;

use think\Model;
use think\Db;

/**
 * 地址模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class Address extends Model
{
    protected $connection='db_cic';
    protected $table = 'cic_address';

}