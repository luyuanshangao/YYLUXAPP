<?php
namespace app\admin\controller;

use app\admin\model\EDMActivityModel;
use app\admin\model\ExchangeRateModel;
use app\common\helpers\CommonLib;
use think\Exception;
use think\Session;
use think\View;
use think\Controller;
use think\Db;


class ExchangeRate extends Action
{
	public function __construct(){
       Action::__construct();
    }

    /**
     * 汇率列表
     * @return View
     */
	public function index()
	{
        $model = new ExchangeRateModel();
        $params = input();
        $page_size = config('paginate.list_rows');
        $list = $model->getDataPaginate(array(),$page_size);
        $this->assign('list', $list);
        $this->assign('params', $params);
        $this->assign('currency_name',$model::$currency_name);
        return view();
    }

    /**
     * 新增颜色
     */
    public function update(){
        $id = input('id');
        if(!empty($id)){
            $model = new ExchangeRateModel();
            $result = $model->getExchangeRate($id);
            $this->assign('currency_name',$model::$currency_name);
            $this->assign(['model'=>$result]);
        }
        return view();
    }

    /**
     * 编辑
     * @return \think\response\Json|View
     */
    public function updateAjax(){
        $input = input();
        if(!empty($input['id'])){
            $model = new ExchangeRateModel();
            $before_result = $model->getExchangeRate($input['id']);
            if(empty($before_result)){
                return json(['code' => 1001,'msg' => '找不到数据']);
            }
            $update['Ratio'] = $input['Ratio'];
            $update['Rate'] = $input['Rate'];
            $update['Alarm'] = $input['Alarm'] / 100;
            $ret = $model->updateExchangeRate($input['id'],$update);
            if($ret){
                //操作历史
                $model->addExchangeRateLog($before_result,$update,$input['id'],Session::get('username'));
            }
            return json(['code' => 200,'msg' => '修改成功']);
        }
    }

    public function logList()
    {
        $model = new ExchangeRateModel();
        $params = input();
        if(empty($params['id'])){
            return json(['code' => 1001,'msg' => 'id不存在']);
        }
        $data = $model->getExchangeRate($params['id']);
        if(empty($data)){
            return json(['code' => 1001,'msg' => '找不到数据']);
        }

        $page_size = config('paginate.list_rows');
        $list = $model->getLogDataPaginate(['exchange_rate_id'=>$params['id']],$page_size);
        $this->assign('list', $list);
        $this->assign('data',$data);
        $this->assign('currency_name',$model::$currency_name);
        return view();
    }
}