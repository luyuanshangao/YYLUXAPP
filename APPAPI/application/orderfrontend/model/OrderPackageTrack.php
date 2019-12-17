<?php
namespace app\orderfrontend\model;

use think\Cache;
use think\Config;
use think\Exception;
use think\Model;
use think\Db;
use think\Log;

class OrderPackageTrack extends Model
{
    // 数据库配置
    protected $connection = 'db_order';
    // 数据表名称
    protected $table='dx_order_package_track';
}