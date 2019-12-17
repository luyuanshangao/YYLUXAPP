<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use think\Log;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\Common;

// use app\admin\model\Interface;


/**
 * 平台管理--Bug反馈
 * @author wang   2018-09-06
 */
class BugFeedback extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('REPORTS', 'reports');//Mysql数据表
        define('REPORTS_LOG', 'reports_log');//Mysql数据表
        define('MY_REVIEW_FILTERING', 'review_filtering');//Mysql数据表
        define('USER', 'user');//Mysql数据表
        define('APPLY_LOG', 'order_after_sale_apply_log');//mysql数据表 仲裁回复表
        define('FEEDBACK', 'feedback');
        define('FEEDBACKREPLY', 'feedback_reply');
        define('MY_WITHDRAW', 'withdraw');//mysql数据表
        define('S_CONFIG', 'dx_sys_config');//Nosql数据表


    }

    /**
     * 产品举报
     * [ProductReport description]
     */
    public function index()
    {
        $riskConfig = BaseApi::RiskConfig();
        if ($data = request()->post()) {
            if ($data['customer_name']) {
                $where['customer_name'] = array('like', '%' . $data['customer_name'] . '%');
            }
            if ($data['seller_name']) {
                $where['seller_name'] = array('like', '%' . $data['seller_name'] . '%');
            }
            if ($data['customer_id']) {
                $where['customer_id'] = $data['customer_id'];
            }
            if ($data['report_status']) {
                $where['report_status'] = $data['report_status'];
            }
            if ($data['report_type']) {
                $where['report_type'] = $data['report_type'];
            }
            if ($data['seller_id']) {
                $where['seller_id'] = $data['seller_id'];
            }
            if ($data['startTime'] && $data['endTime']) {
                $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
            }
            Cookie::set('RiskManagement', $where, 3600);
        }
        $status = input('status');
        if (!$where && $status) {
            $where = Cookie::get('RiskManagement');
        }
        $where['report_type'] = 101;

            $list = Db::name(REPORTS)->where($where)->order('add_time DESC')->paginate(20);
            $page = str_replace("page", "status=1&page", $list->render());
        $list_items = $list->items();
        $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
        $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
        foreach ((array)$list_items as $key => $value) {
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if ($value["report_status"] == $v["code"]) {
                    $list_items[$key]["report_name"] = $v["name"];
                }
            }
            if(!empty($value['enclosure'])){
                $list_items[$key]['enclosure'] = json_decode(htmlspecialchars_decode($value['enclosure']));
            }
        }
        unset($riskConfig["data"]["report_type"][4], $riskConfig["data"]["report_type"][5], $riskConfig["data"]["report_type"][100]);
        $this->assign(['list' => $list_items, 'page' => $page, 'statusSelectHtml' => $statusSelectHtml, 'data' => $data, 'riskConfig' => $riskConfig["data"]['report_type']]);
        return View();
    }


    /**
     * 遍历风控状态
     * [statusSelect description]
     * @return [type] [description]
     * @author wang   2018-08-04
     */
    public function statusSelect($data = array(), $selectId = '', $status)
    {
        $html = '';
        $select = '';
        $html .= '<select name="' . $selectId . '" id="' . $selectId . '" class="form-control input-small inline">';
        $html .= '<option value="">请选择</option>';
        foreach ((array)$data as $key => $value) {
            if ($status == $value["code"]) {
                $select = 'selected = "selected"';
            }
            $html .= '<option ' . $select . ' value="' . $value["code"] . '">' . $value["name"] . '</option>';
            $select = '';
        }
        $html .= '</select>';
        return $html;
    }

}