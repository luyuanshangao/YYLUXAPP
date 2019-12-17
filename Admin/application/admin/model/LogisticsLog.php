<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2018/4/11
 * Time: 10:55
 */
namespace app\admin\model;
use think\Model;
use think\Db;
class LogisticsLog  extends Model{
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
//    // 定义时间戳字段名
//    protected $createTime = 'create_time';
//    protected $updateTime = false;
}