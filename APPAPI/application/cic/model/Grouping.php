<?php
namespace app\cic\model;
use think\Model;
use think\Db;

class Grouping extends Model{
    protected $connection='db_cic';
    protected $table = 'cic_grouping';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = false;
}
