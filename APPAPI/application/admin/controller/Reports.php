<?php
namespace app\admin\controller;

use app\common\params\admin\MessageParams;
use think\cache\driver\Redis;
use think\Controller;
use app\admin\model\Message as MessageModel;

class Reports extends Controller
{
    /*
     * 获取列表
     * */
    public function getList()
    {
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Reports.getList");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['customer_id'] = input("customer_id");
        $where['report_type'] = input("report_type");
        $where['report_status'] = input("report_status");
        $where['add_time'] = input("add_time");
        $page_size = input('page_size',5);
        $page = input("page",1);
        $path = input("path");
        $where = array_filter($where);
        $where['delete_time'] = 0;
        $res = model("Reports")->getList($where,$page_size,$page,$path);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 获取列表【seller使用】
     * */
    public function getListForSeller()
    {
        $where['seller_id'] = input("seller_id");
        //2-Price Match，3-Report Error
        $flag = input("flag");
        if ($flag == 2){
            $where['report_type'] = 100;
        }elseif ($flag == 3){
            $where['report_type'] = ['<', 100];
        }
        $where['report_status'] = input("report_status");
        $where['add_time'] = input("add_time");
        $page_size = input('page_size',5);
        $page = input("page",1);
        $path = input("path");
        $where = array_filter($where);
        $where['delete_time'] = 0;
        $res = model("Reports")->getListForSeller($where,$page_size,$page,$path);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 添加举报
     * */
    public function addReports(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Reports.addReports");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $customer_data = model("cic/Customer")->getCustomer(input("customer_id"),0);
        if(!$customer_data){
            return apiReturn(['code'=>1002,"msg"=>"customer is empty"]);
        }
        if(!in_array($paramData['report_type'],array_keys(config('report_type')))){
            return apiReturn(['code'=>1002,"msg"=>"report_type is null"]);
        }
        $data['customer_id'] = input("customer_id");
        $data['customer_name'] = input("customer_name");
        $data['report_type'] = input("report_type");
        $data['seller_id'] = input("seller_id");
        $data['seller_name'] = input("seller_name");
        $data['report_small_type'] = input("report_small_type");
        $data['product_url'] = input("product_url");
        $data['reason'] = input("reason");
        $data['enclosure'] = input("enclosure");
        $data['email'] = input("email");
        $data['phone'] = input("phone");
        $data['order_number'] = input("order_number");
        $data['currency_code'] = input("currency_code");
        $data['amount'] = input("amount");
        $data['SPU'] = input("SPU");
        $data['report_status'] = input("report_status",1);
        $data['add_time'] = time();
        $res = model("Reports")->saveReports($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 删除
     * */
    public function deleteReport(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Reports.deleteReport");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $paramData = request()->post();
        if(!isset($paramData['id'])){
            return apiReturn(['code'=>1001]);
        }
        $where['id'] = input("id");
        $where['customer_id'] = input("customer_id");
        $update['delete_time'] = time();
        $res = model("Reports")->saveReports($update,$where);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002,'msg'=>"Report does not exist"]);
        }

    }

    /**
     * 获取相关配置
     * @return mixed
     */
    public function getReportConfig()
    {
        return apiReturn([
            'code'=>200,
            'data'=>[
                'report_type'=>config('report_type'),
                'report_status'=>config('report_status'),
            ]
        ]);
    }
}
