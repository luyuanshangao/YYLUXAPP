<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use app\index\model\ProductQaModel;
use app\index\model\WholesaleInquiryModel;
use think\Config;
use think\Log;

/**
 * 消息管理中心控制器
 * @author heng zhang
 * @date 2018-03-29
 * @package app\index\controller
 */
class Message extends Common
{
    /*
     * 查看留言
     */
    public function index()
    {
        //tab类型：1-购物车留言，2-Price Match，3-Report Error
        $tab_type = input('tab_type', 1);
        $base_api = new BaseApi();
        switch ($tab_type){
            case 1:
                //购物车留言
                $where['seller_id'] = $this->login_user_id;
                $where['type'] = 6;
                //$where['page_size'] = 1;
                $model = new ProductQaModel();
                $data = $model->getMessageListByWhere($where);
                $this->assign([
                    'data'=>$data,
                ]);
                break;
            case 2://Price Match
            case 3://Report Error
                $where['seller_id'] = $this->login_user_id;
                $where['flag'] = $tab_type;
                /** 分页条件 start **/
                $where['page_size'] = input('page_size/d', 20);
                $where['page'] = input('page/d', 1);
                $input = input();
//                pr($input);
                $p = [];
                foreach ($input as $k=>$v){
                    if (
                        $k != 'page'
                        && $k != 'page_size'
                        && $k != 'report_type'
                        && $k != 'delete_time'
                    ){
                        $p[$k] = $v;
                    }
                }
                $where['path'] = url('Message/index', $p, config('default_return_type'), true);
                /** 分页条件 end **/
                $data = $base_api->getReportsListForSeller($where);
//                pr($data);
                $this->assign([
                    'data'=>isset($data['data'])?$data['data']:[],
                ]);
                break;
        }
    	$this->assign([
    	    'tab_type'=>$tab_type,
    	    'child_menu'=>'message-center-leave-message',
    	    'parent_menu'=>'message-center',
        ]);
        return $this->fetch();
    }

    /**
     * 站内信
     * @return mixed
     */
    public function internalLetter()
    {
        $where['title'] = trimall(input('title'));
        $where['mark'] = input('mark');
        $where['read_status'] = input("read_status");
        //发送时间:最近1个月，2个月等
        $where['month_time'] = input("month_time");

        $where['recive_user_id'] = $this->login_user_id;
        //接受者类型 1用户 2卖家
        $where['recive_type'] = 2;
        /** 分页条件 start **/
        $where['page_size'] = input('page_size/d', config('paginate.list_rows'));
        $where['page'] = input('page/d', 1);
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if (
                $k != 'page'
                && $k != 'page_size'
                && $k != 'addtime'
                && $k != 'isdelete'
            ){
                $p[$k] = $v;
            }
        }
        $where['path'] = url('Message/internalLetter', $p, config('default_return_type'), true);
        /** 分页条件 end **/
        $base_api = new BaseApi();
        $data = $base_api->getMessageListForSeller($where);
//        print_r($data);

    	$this->assign([
    	    'data'=>isset($data['data'])?$data['data']:[],
    	    'ajax_url'=>json_encode([
    	        'async_actionHandle'=>url('Message/async_actionHandle'),
            ]),
    	    'child_menu'=>'message-center-sitemail',
    	    'parent_menu'=>'message-center',
        ]);
        return $this->fetch();
    }

    /**
     * Wholesale Inquiry
     * @return mixed
     */
    public function wholesaleInquiry(){
        $shipping_method = input('shipping_method', 0);
        $model = new WholesaleInquiryModel();
        $data = $model->getList($this->login_user_id, $shipping_method);
        $this->assign([
            'shipping_method_data'=>Base::getWholesaleInquiryShippingMethod(),
            'list'=>$data['data'],
            'page'=>$data['Page'],
            'child_menu'=>'message-center-wholesaleinquiry',
            'parent_menu'=>'message-center',
        ]);
        return $this->fetch();
    }

    /**
     * 设置状态、删除处理
     * @return \think\response\Json
     */
    public function async_actionHandle(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $data = input();
        $base_api = new BaseApi();
        $res = $base_api->updateMessageReciveData($data);
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 0;
        }else{
            $rtn['msg'] = '操作失败，请重试';
        }
        return json($rtn);
    }
}
