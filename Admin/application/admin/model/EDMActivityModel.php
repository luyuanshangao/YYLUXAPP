<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use app\common\redis\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\Session;

/**
 * EDM
 * @author zhongning
 */
class EDMActivityModel extends Model{

    public static $emailService = array(
        "bc" => 'Broadcast',
        "cm" => 'Cheetahmail',
    );

    public static $templateType = array(
        "edm" => 'EDM模板',
        "collection" => '专题模板',
        "landing_pg" => 'Landing Page 模板',
        "m_landing_pg" => '移动端模板'
    );

    public static $langCode = array(
        "en" => '英语',
        "es" => '西班牙语',
        "pt" => '葡萄牙语',
        "ru" => '俄语',
        "fr" => '法语',
        "de" => '德语',
        "nl" => '荷兰语',
        "cs" => '捷克语',
        "fi" => '芬兰语',
        "it" => '意大利语',
        "sv" => '瑞典语',
        "no" => '挪威语',
        "ja" => '日本语',
        "ar" => '阿拉伯语'
    );

    public static $currencyCode = array(
        "USD" => 'USD',
        "GBP" => 'GBP',
        "CAD" => 'CAD',
        "EUR" => 'EUR',
        "BRL" => 'BRL',
        "RUB" => 'RUB',
        "AUD" => 'AUD',
        "CZK" => 'CZK',
        "CLP" => 'CLP',
        "JPY" => 'JPY',
        "ILS" => 'ILS',
        "ARS" => 'ARS',
        "UAH" => 'UAH',
        "TRY" => 'TRY',
        "CHF" => 'CHF',
        "ZAR" => 'ZAR',
        "DKK" => 'DKK',
        "NOK" => 'NOK',
        "SEK" => 'SEK',
        "INR" => 'INR',
        "SGD" => 'SGD',
        "MXN" => 'MXN',
        "KRW" => 'KRW',
        "PLN" => 'PLN'
    );

    protected $table_activity_edm = 'dx_activity_edm';
    protected $table_activity_template = 'dx_email_template';
    protected $table_email_task = 'dx_email_task';
    protected $table_email_task_line = 'dx_email_task_line';

    protected $redis;
    public function __construct()
    {
        parent::__construct();
//        $this->redis = new RedisClusterBase();
    }

    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getActivityDataPaginate($where = array(), $page_size=10,$params = array()){
        return Db::table($this->table_activity_edm)->where($where)->order('CreateTime','desc')->paginate($page_size,false,['query'=>$params]);
    }

    /**
     * 新增
     * @param $params
     * @return int|string
     */
    public function createEdmActivity($params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['CreateTime'] = date('Y-m-d H:i:s',time());
        $data['CreateBy'] = Session::get('username');
        return Db::table($this->table_activity_edm)->insertGetId($params);
    }

    /**
     * 修改
     * @param $params
     * @return int|string
     */
    public function updateEdmActivity($id,$params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['UpdateTime'] = date('Y-m-d H:i:s',time());
        $params['UpdateBy'] = Session::get('username');
        return Db::table($this->table_activity_edm)->where(['id' => $id])->update($params);
    }

    /**
     * 获取详情
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getEdmActivity($id){
        return Db::table($this->table_activity_edm)->where(['id' => $id])->find();
//        return Db::table($this->table_activity_edm)->alias('a')->join('dx_email_template temp','temp.id=a.TemplateID')->where(['a.id' => $id])->find();
    }
    public function getEdmActivityByWhere($Where){
        return Db::table($this->table_activity_edm)->where($Where)->select();
    }


    /**
     * 删除
     * @param $id
     * @return int
     * @throws Exception
     */
    public function del($id){
        return Db::table($this->table_activity_edm)->where(['id' => $id])->delete();
    }


    //==========================table_activity_edm end=============================================

    /**
     * 获取模板详情
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getEdmActivityTemplate($id){
        return Db::table($this->table_activity_template)->where(['id' => $id])->find();
    }

    /**
     * 新增
     * @param $params
     * @return int|string
     */
    public function updateEdmActivityTemplate($id,$params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['UpdateTime'] = date('Y-m-d H:i:s',time());
        $params['UpdateBy'] = Session::get('username');
        return Db::table($this->table_activity_template)->where(['id' => $id])->update($params);
    }

    /**
     * 新增
     * @param $params
     * @return int|string
     */
    public function createEdmActivityTemplate($params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['CreateTime'] = date('Y-m-d H:i:s',time());
        $data['CreateBy'] = Session::get('username');
        return Db::table($this->table_activity_template)->insertGetId($params);
    }


    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getActivityTemplatePaginate($where = array(), $page_size=10){
        return Db::table($this->table_activity_template)->where($where)->order('Sort','asc')->paginate($page_size);
    }

    /**
     * 获取模板
     * @param array $where
     * @return $this
     */
    public function selectActivityTemplatePaginate($where = array()){
        return Db::table($this->table_activity_template)->where($where)->field(['id','Title','PreviewImage','LangCode'])->order('Sort','desc')->select();
    }

    /**
     * 删除
     * @param $id
     * @return int
     * @throws Exception
     */
    public function delTemplate($id){
        return Db::table($this->table_activity_template)->where(['id' => $id])->delete();
    }

    //==========================table_activity_template end=============================================


    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getEmailTaskPaginate($where = array(), $page_size=10){
        return Db::table($this->table_email_task)->where($where)->order('id','desc')->paginate($page_size);
    }

    public function updateEdmTask($id,$params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['UpdateTime'] = date('Y-m-d H:i:s',time());
        $params['CreateBy'] = Session::get('username');
        return Db::table($this->table_email_task)->where(['id' => $id])->update($params);
    }

    /**
     * 新增
     * @param $params
     * @return int|string
     */
    public function createEmailTask($params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['CreateTime'] = date('Y-m-d H:i:s',time());
        $data['CreateBy'] = Session::get('username');
        return Db::table($this->table_email_task)->insertGetId($params);
    }

    /**
     * 修改
     * @param $params
     * @return int|string
     */
    public function updateEmailTask($id,$params){
        //转换时区
        date_default_timezone_set('PRC');
        $params['UpdateTime'] = date('Y-m-d H:i:s',time());
        $params['UpdateBy'] = Session::get('username');
        return Db::table($this->table_email_task)->where(['id' => $id])->update($params);
    }

    /**
     * 获取详情
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getEmailTask($id){
        return Db::table($this->table_email_task)->where(['id' => $id])->find();
    }

    /**
     * 删除
     * @param $id
     * @return int
     * @throws Exception
     */
    public function delEmailTask($id){
        return Db::table($this->table_email_task)->where(['id' => $id])->delete();
    }


    //===============================================
    /**
     * 获取详细信息
     * @param $taskid
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function selectEmailTaskLine($taskid){
        return Db::table($this->table_email_task_line)->where(['taskid'=>$taskid])->order('id','desc')->select();
    }

    public function getEdmBak($page = 1,$page_size = 10){
        return Db::table('dx_activity_edm_bk')->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
//        return Db::table('dx_activity_edm_bk')->limit($page,$page_size)->select();
    }
    public function insertAllEdmBak($data){
        return Db::table('dx_activity_edm')->insertAll($data);
    }
}