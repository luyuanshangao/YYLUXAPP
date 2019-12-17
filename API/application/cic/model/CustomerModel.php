<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 用户模型
 * @author
 * @version yxh 2019/09/12
 */
class CustomerModel extends Model{
    protected $connection = 'db_cic';
    protected $table='cic_customer';

}