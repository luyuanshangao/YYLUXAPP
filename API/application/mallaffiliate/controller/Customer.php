<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/9/12
 * Time: 14:59
 */
namespace app\mallaffiliate\controller;

use app\common\controller\BaseOrder;
use app\mallaffiliate\model\OrderModel;
use think\Controller;
use think\Exception;
use app\mallaffiliate\dxcommon\BaseApi;

class Customer extends BaseOrder
{
    public function getCustomerList()
    {
        $post = request()->param();
        if(!empty($post['RegisterStart'])&&!empty($post['RegisterEnd'])){
            $where['RegisterStart'] = strtotime($post['RegisterStart']);
            $where['RegisterEnd'] = strtotime($post['RegisterEnd']);
        }
        if(!empty($post['ID'])){
            $where['ID'] = $post['ID'];
        }

        $where['page_size'] = input("post.page_size",20);
        $where['page'] = input("post.page",1);
        $result = (new BaseApi)->getCustomerList($where);
        if(!empty($result['data']['data'])){
            $OrderModel=new OrderModel();
            foreach($result['data']['data'] as &$value){
                $where1['customer_id'] = $value['ID'];
                $OrderDate=$OrderModel->getOrder($where1);
                if(!empty($OrderDate)){
                    $value['order_time']=$OrderDate['create_on'];
                }
            }
        }

        return $result;
    }

    public function getDay()
    {
        $post = request()->param();
        $singleRule = [
            'RegisterStart' => 'require',
            'RegisterEnd' => 'require',
        ];
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1000, $result);
        }

        $where['RegisterStart'] = strtotime($post['RegisterStart']);
        $where['RegisterEnd'] = strtotime($post['RegisterEnd']);
        $result = (new BaseApi)->getDay($where);
        return $this->result($result);
    }

    public function getUser()
    {
        $post = request()->param();
        $singleRule = [
            'id' => 'require',
        ];
        $result = $this->validate($post, $singleRule);
        $ID=$post['id'];
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1000, $result);
        }

        $where['ID'] = $ID;
        $result = (new BaseApi)->getAdminCustomerInfo($where);
        $data['RegisterOn']=date($result['data']['RegisterOn']);
        $OrderModel=new OrderModel();
        $where1['customer_id'] = $ID;
        $OrderDate=$OrderModel->getOrder($where1);
        if(!empty($OrderDate)){
            $data['order_time']=$OrderDate['create_on'];
            $data['order_count']=$OrderModel->getOrderCount($where1);;
        }
        $data['RegisterOn']=date($result['data']['RegisterOn']);
        return $this->result($data);
    }
}