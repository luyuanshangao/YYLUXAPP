<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use app\common\redis\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;

/**
 * datafeed 下载任务
 * @author zhongning
 */
class CrontabModel extends Model{

    protected $table_crontab = 'dx_crontab_list';

    protected $redis;
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
    }

    public function selectCrontab(){
        $page_size = config('paginate.list_rows');
        return Db::table($this->table_crontab)->order('id desc')->paginate($page_size);
    }

    public function createCrontab($params){
        $insert['filename'] = $params['platform'].'_'.$params['format'].'_'.$params['currency'].'_'.$params['lang'];
        $insert['platform'] = $params['platform'];
        $insert['category_id'] = $params['category_id'];
        $insert['format'] = $params['format'];
        $insert['currency'] = $params['currency'];
        $insert['country'] = $params['country'];
        $insert['lang'] = $params['lang'];
        //转换时区
        date_default_timezone_set('PRC');
        $insert['add_time'] = time();
        $ret =  Db::table($this->table_crontab)->insertGetId($insert);
        $insert['id'] = $ret;
        //加入任务队列
        $this->redis->lPush('AffiliateFeedDownLoadQueue',json_encode($insert));
        return $ret;
    }

    public function deleteCrontab($id){
        return Db::table($this->table_crontab)->where(['id' => $id])->delete();
    }

    public function findCrontab($id){
        return Db::table($this->table_crontab)->where(['id' => $id])->find();
    }

    public function updateCrontab(array $where,array $update){
        return Db::table($this->table_crontab)->where($where)->update($update);
    }

}