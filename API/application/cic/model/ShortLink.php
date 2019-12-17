<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * ShortLink模型
 * 功能
 * 1.生成短链接url
 * 2.获取短链接对应类内容
 * @author
 * @version yxh 2019/04/08
 */
class ShortLink extends Model{
    protected $connection = 'db_cic';
    protected $table='cic_short_link';

}