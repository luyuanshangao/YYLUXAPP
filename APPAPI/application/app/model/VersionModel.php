<?php
namespace app\app\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 版本控制
 */
class VersionModel extends Model
{
    protected $connection = 'db_admin';
    protected $table='dx_version';

    /**
     * 获取对应版本信息
     * @param $params
     * @return data
     */
    public function getVersion($where)
    {
        $data=$this->where($where)->order('id desc')->find();
        return $data;
    }

}