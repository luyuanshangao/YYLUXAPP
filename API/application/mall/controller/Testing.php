<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\AdvertisingParams;
use app\mall\services\AffiliateService;
use think\Db;
use think\Monlog;


/**
 * 开发：钟宁
 * 功能：Affiliate js模板获取
 * 时间：2018-06-21
 */
class Testing
{
    public function get(){
        //$ret = Db::connect("db_mongodb")->name('test')->insert(['abc'=>111]);
        //pr($ret);
        //Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__);

        $ret = Db::connect("db_mongodb")->name('test')->insert(['abc'=>111]);
        pr($ret);

        $ret = Db::connect("db_mongodb")->name('test')->where(['id'=>111])->update(['abc'=>333]);
        pr($ret);

        $find = Db::connect("db_mongodb")->name('test')->find();
        pr($find);

        $ret = Db::connect("db_mongodb")->name('test')->where(['_id'=>$find['_id']])->delete();
        pr($ret);

        $find = Db::connect("db_mongodb")->name('test')->paginate(10);
        pr($find);

    }

    public function header(){
        $path = '/data/public_uploads/mall/public/static';
        if(!is_dir($path)){
            pr('/data/public_uploads/mall/public/static');
            mkdir($path,0777,true);
        }
    }
}
