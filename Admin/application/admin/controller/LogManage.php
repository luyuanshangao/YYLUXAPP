<?php
namespace app\admin\controller;

use app\admin\model\EDMActivityModel;
use app\admin\model\LogModel;
use app\common\helpers\CommonLib;
use think\Exception;
use think\View;
use think\Controller;
use think\Db;

/**
 * Class LogManage
 * 开发：钟宁
 * 功能：日志信息列表
 * 创建时间：2019-10-11
 */
class LogManage extends Action
{
	public function __construct(){
       Action::__construct();
    }

    /**
     * 日志列表页面
     * @return View
     */
	public function index()
	{
        $params = input();
        $model = new LogModel();
        $path_url = array();
        $this->assign('log_table', $model::$tableLogArr);
        $this->assign('log_type', $model::$logType);
        //列表信息
        $where = [];
        if (!empty($params['level'])){
            $path_url['level'] = $where['level'] = $params['level'];
        }
        if (!empty($params['functionName'])){
            $path_url['functionName'] = $where['functionName'] = $params['functionName'];
        }
        if (!empty($params['startTime']) && !empty($params['endTime'])){
            $path_url['startTime'] = $params['startTime'];
            $path_url['endTime'] = $params['endTime'];
            $where['timestamp'] = [ 'between' , [$params['startTime'],$params['endTime']]];
        }
        $path_url['table'] = $params['table'] = isset($params['table']) ? $params['table'] : $model::DB_LOG_MALL;
        $page_size = config('paginate.list_rows');
        $list = $model->getLogPaginate($params['table'],$where,$page_size,$path_url);
        $this->assign('list', $list);
        $this->assign('params', $params);
        return view();
    }
}