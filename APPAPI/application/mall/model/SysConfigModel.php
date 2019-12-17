<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 开发：钟宁
 * 功能：系统基础数据配置
 */
class SysConfigModel extends Model{

    const tableName = 'sys_config';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 商城首页顶部通栏广告图片地址配置
     */
    public function getTopBanner(){
        $query = $this->db->name(self::tableName)->where(['ConfigName' => 'TopBannerImgSrc']);
        $query->field(['ConfigValue','LinkUrl','_id'=>false]);
        return $query->find();
    }

    /**
     * 商城LOGO图片地址
     */
    public function getLogo(){
        $query = $this->db->name(self::tableName)->where(['ConfigName' => 'LogoImgSrc']);
        $query->field(['ConfigValue','LinkUrl','_id'=>false]);
        return $query->find();
    }

    /**
     * 获取指定配置信息
     * @param $ConfigName
     * @return array|false|\PDOStatement|string|Model
     */
    public function getSysCofig($ConfigName){
        $where['ConfigName'] = $ConfigName;
        return $this->db->name(self::tableName)->where($where)->field(['ConfigValue','_id'=>false])->find();
    }
}