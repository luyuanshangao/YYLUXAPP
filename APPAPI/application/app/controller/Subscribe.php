<?php
/**
 * 用户订阅控制器
 * Created by PhpStorm.
 * User: Kevin
 * Date: 2018/3/8
 * Time: 16:55
 */
namespace app\app\controller;

use app\app\dxcommon\BaseApi;
use app\common\controller\AppBase;
use think\Request;
class Subscribe extends AppBase
{
    public $baseApi;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->baseApi = new BaseApi();
    }

    /*
     * 订阅首页
     */
    public function index()
    {
        $customer_id = input('customer_id');
        //$email = $this->baseApi->getSubscriber($customer_id, 2);

        $where['CustomerId'] = $customer_id;
        $Subscriber = $this->baseApi->checkSubscriber($where);
        $res=!empty($Subscriber)?1:0;
        return $this->result($res);
    }


    /*
     * 订阅
     * */
    public function addSubscribe()
    {

        $data['Email'] = input("Email");
        $data['CustomerId'] = input('customer_id');
        $Subscriber = $this->baseApi->checkSubscriber($data);
        if ($Subscriber) {
            $res['code'] = 200;
            $res['msg'] = "";
        } else {
            $res = $this->baseApi->addSubscriber($data);
        }
        return $res;
    }

    /*
     * 取消订阅
     * */
    public function cancelSubscribe()
    {
        $paramData = input();
        if (isset($paramData['CancelReasonIDs']) && !empty($paramData['CancelReasonIDs'])) {
            $data['CancelReasonIDs'] = implode(",", $paramData['CancelReasonIDs']);
        }
        $data['OtherCancelReason'] = $paramData['OtherCancelReason'];
        $customer_id = input('customer_id');
        $email = $this->baseApi->getSubscriber($customer_id, 2);

        if(!empty($email['data'])){
            foreach($email['data'] as $value){
                if(!empty($value['Email'])){
                    $data['Email'] = $value['Email'];
                }
                $res = $this->baseApi->cancelSubscriber($data);
            }
        }


        if (isset($res['code']) && $res['code'] == 200) {
            return $res;
        } else {
            $res['msg'] = "Email does not exist!";
            return $res;
        }
    }
}
