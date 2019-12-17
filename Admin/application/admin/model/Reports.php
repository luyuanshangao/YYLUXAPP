<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2019/10/18
 * Time: 10:55
 */
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Model;
use think\Db;
class Reports  extends Model{

    protected $table="dx_reports";


    //获取一条reports记录
    public function getOneReports($where){
        return Db::table($this->table)->where($where)->find();
    }
}