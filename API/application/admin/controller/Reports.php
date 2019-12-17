<?php
namespace app\admin\controller;
use app\common\controller\BaseApi;
use think\Exception;
use vendor\aes\aes;
use app\common\params\admin\MessageParams;
use think\cache\driver\Redis;
use think\Controller;
use app\admin\model\Reports as ReportsModel;
use app\log\model\Log;

class Reports extends Controller
{
    /*每分钟允许提交次数*/
    const MinuteSubmissionFrequency = 2;

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
        //获取用户数据改用接口的形式获取，因为CIC独立出来后，API不能直接访问CIC数据库 tinghu.liu 20190727
        $customer_data_res = (new BaseApi())->getCustomerByID(['ID'=>input("customer_id")]);
        $customer_data = isset($customer_data_res['data'])?$customer_data_res['data']:[];
//        $customer_data = model("cic/Customer")->getCustomer(input("customer_id"),0);
        if(!$customer_data){
            return apiReturn(['code'=>1002,"msg"=>"customer is empty"]);
        }
        if(!in_array($paramData['report_type'],array_keys(config('report_type')))){
            return apiReturn(['code'=>1002,"msg"=>"report_type is null"]);
        }
        $paypal = input("paypal");
        if(is_email($paypal) == true) {//传入账号是邮箱
            $email_array = explode("@", $paypal);
            $EmailDomainName = $email_array[1];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $data['PayPalEU'] = $EmailUserName;
            $data['PayPalED'] = $EmailDomainName;
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
        $data['order_master_number'] = input("order_master_number");
        $data['currency_code'] = input("currency_code");
        $data['amount'] = input("amount");
        $data['SPU'] = input("SPU");
        $data['report_status'] = input("report_status",1);
        $data['add_time'] = time();

        //加密邮箱和手机号
        vendor('aes.aes');
        $aes=new aes();
        if(!empty($data['email'])){
            $data['is_update'] =1;
            $data['email'] = $aes->encrypt($data['email'],'Reports','Email');//加密邮件前缀
        }
        if(!empty($data['phone'])){
            $data['is_update'] =1;
            $data['phone'] = $aes->encrypt($data['phone'],'Reports','Phone');//加密邮件前缀
        }

        /*获取一分钟前的条数，进行限制 20190413 kevin*/
        $minute_ago_time = time()-60;
        if(!empty($data['customer_id'])){
            $where['customer_id'] = $data['customer_id'];
            $where['add_time'] = ['egt',$minute_ago_time];
            $minute_ago_count = model("Reports")->getReportCount($where);
            if($minute_ago_count >= self::MinuteSubmissionFrequency){
                return apiReturn(['code'=>1002,'msg'=>"Submitted too often, please try again later"]);
            }
        }else{
            return apiReturn(['code'=>1001,'msg'=>"Customer_id can not be empty"]);
        }

        $res = model("Reports")->saveReports($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /**
     * 更新举报【admin使用】
     * @return mixed
     */
    public function updateReportsforAdmin(){
        $params = request()->post();
        $validate = $this->validate($params,[
            ['id','require|integer'],
            ['amount','require'],
            ['paypal','require'],
            ['reason','require'],
            ['report_status','require'],
            ['operator_id','require'],
            ['operator_name','require'],
        ]);
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $paypal = $params['paypal'];
            if(is_email($paypal) == true) {//传入账号是邮箱
                $email_array = explode("@", $paypal);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $update_data['PayPalEU'] = $EmailUserName;
                $update_data['PayPalED'] = $EmailDomainName;
            }
            $update_data['amount'] = $params['amount'];
            $update_data['reason'] = $params['reason'];
            $update_data['report_status'] = $params['report_status'];
            $update_data['operator_id'] = $params['operator_id'];
            $update_data['operator'] = $params['operator_name'];
            $update_data['edit_time'] = time();
            $where['id'] = $params['id'];
            $res = model("Reports")->updateReports($where,$update_data);
            if ($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>100,'msg'=>'更新失败']);
            }
        }catch (Exception $e){
            return apiReturn(['code'=>100,'msg'=>'更新失败：'.$e->getMessage()]);
        }
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

    /*获取后台reports列表*/
    public function getAdminReportList(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Reports.getAdminReportList");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['report_type'] = input("report_type");
        $where['report_status'] = input("report_status");
        $where['add_time'] = input("add_time");
        $where['customer_name'] = input('customer_name');
        $where['seller_name'] = input('seller_name');
        $where['customer_id'] = input('customer_id');
        $where['seller_id'] = input('seller_id');
        $where['order_number'] = input('order_number');
        $where['from'] = input('from');
        if(isset($paramData['paypal']) && !empty($paramData['paypal'])){
            if(is_email($paramData['paypal']) == true) {//传入账号是邮箱
                $email_array = explode("@", $paramData['paypal']);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $where['PayPalEU'] = $EmailUserName;
                $where['PayPalED'] = $EmailDomainName;
            }else{
                return apiReturn(['code'=>1001,'msg'=>"PayPal格式错误！"]);
            }
        }
        $page_size = input('page_size',20);
        $page = input("page",1);
        $path = input("path");
        foreach ($where as $key=>&$value){
            if(is_array($value)){
                $value[0] = trim($value[0]);
            }
        }
        $where = array_filter($where);
        $where['delete_time'] = 0;
        $model = new ReportsModel();
        $res = $model->getList($where,$page_size,$page,$path);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /**
     * 获取后台reports列表【财务审核列表】
     * @return mixed
     */
    public function getAdminReportListForFinancial(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Reports.getAdminReportList");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['a.report_type'] = input("report_type");
        $where['a.report_status'] = input("report_status");
        $where['a.add_time'] = input("add_time");
        $where['a.customer_name'] = input('customer_name');
        $where['a.seller_name'] = input('seller_name');
        $where['a.customer_id'] = input('customer_id');
        $where['a.seller_id'] = input('seller_id');
        $where['a.order_number'] = input('order_number');
        $where['a.from'] = input('from');
        $b_status = input('b_status');
        if(isset($paramData['paypal']) && !empty($paramData['paypal'])){
            if(is_email($paramData['paypal']) == true) {//传入账号是邮箱
                $email_array = explode("@", $paramData['paypal']);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $where['a.PayPalEU'] = $EmailUserName;
                $where['a.PayPalED'] = $EmailDomainName;
            }else{
                return apiReturn(['code'=>1001,'msg'=>"PayPal格式错误！"]);
            }
        }
        $page_size = input('page_size',20);
        $page = input("page",1);
        $path = input("path");
        foreach ($where as $key=>&$value){
            if(is_array($value)){
                $value[0] = trim($value[0]);
            }
        }
        $where = array_filter($where);
        $where['a.delete_time'] = 0;
        $model = new ReportsModel();
        $res = $model->getListForFinancial($where,$page_size,$page,$path,$b_status);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*获取后台reports数据*/
    public function getReportInfo(){
        $paramData = request()->post();
        if(empty($paramData['id']) || !is_numeric($paramData['id'])){
            return apiReturn(['code'=>1001]);
        }
        $where['id'] = $paramData['id'];
        $model = new ReportsModel();
        $res = $model->getReport($where);
        if($res){
            if(!empty($res['PayPalEU'])){
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->decrypt($res['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮件前缀
                $res['PayPal'] = $EmailUserName."@".$res['PayPalED'];
            }
        }
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 批量加密邮箱和手机号
     */
    public function encrypt(){
        $ReportsModel=new ReportsModel();
        //获取未加密的数据
        $field = ['id','email', 'phone'];
        $where['is_update']=0;
        $list=$ReportsModel->getReportslist($field,$where,100);
        //var_dump($list);die;
        if(!empty($list)){
            vendor('aes.aes');
            $aes=new aes();
            foreach($list as $value){
                $data=[];
                $map=[];
                //对数据进行加密
                if(!empty($value['email'])){
                    $dataLog['email'] =$data['email'] = $aes->encrypt($value['email'],'Reports','Email');//加密邮件前缀
                }
                if(!empty($value['phone'])){
                    $dataLog['phone'] =$data['phone'] = $aes->encrypt($value['phone'],'Reports','Phone');//加密邮件前缀
                }

                //如果有数据,则保存到Reports表
                if(!empty($data)){
                       //备份存入日志中
                        $map['id']=$value['id'];
                        $data['is_update']=1;
                        $res1=$ReportsModel->updateReports($map,$data);
                        if(!empty($res1)){
                            echo $value['id'].'加密成功'."<br />";
                        }else{
                            echo $value['id'].'加密失败'."<br />";
                        }
                }
            }
        }else{
            echo '数据已全部指向完毕';
        }
    }

    /**
     * 添加举报【admin使用】
     * @return mixed
     */
    public function addReportsforAdmin(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Reports.addReports");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        //来源：1-用户MY提交，2-后台客服提交
        $from = input("from");
        if(!in_array($paramData['report_type'],array_keys(config('report_type')))){
            return apiReturn(['code'=>1002,"msg"=>"report_type is null"]);
        }
        $paypal = input("paypal");
        if(is_email($paypal) == true) {//传入账号是邮箱
            $email_array = explode("@", $paypal);
            $EmailDomainName = $email_array[1];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($email_array[0],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $data['PayPalEU'] = $EmailUserName;
            $data['PayPalED'] = $EmailDomainName;
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
        $data['order_master_number'] = input("order_master_number");
        $data['currency_code'] = input("currency_code");
        $data['amount'] = input("amount");
        $data['SPU'] = input("SPU");
        $data['report_status'] = input("report_status",1);
        $data['operator_id'] = input("operator_id","");
        $data['operator'] = input("operator_name","");
        $data['add_time'] = time();
        if (!empty($from)){
            $data['from'] = $from;
        }
        //加密邮箱和手机号
        vendor('aes.aes');
        $aes=new aes();
        if(!empty($data['email'])){
            $data['is_update'] =1;
            $data['email'] = $aes->encrypt($data['email'],'Reports','Email');//加密邮件前缀
        }
        if(!empty($data['phone'])){
            $data['is_update'] =1;
            $data['phone'] = $aes->encrypt($data['phone'],'Reports','Phone');//加密邮件前缀
        }

        /*获取一分钟前的条数，进行限制 20190413 kevin*/
        $minute_ago_time = time()-60;
        if(!empty($data['customer_id'])){
            $where['customer_id'] = $data['customer_id'];
            $where['add_time'] = ['egt',$minute_ago_time];
            $minute_ago_count = model("Reports")->getReportCount($where);
            if($minute_ago_count >= self::MinuteSubmissionFrequency){
                return apiReturn(['code'=>1002,'msg'=>"Submitted too often, please try again later"]);
            }
        }else{
            return apiReturn(['code'=>1001,'msg'=>"Customer_id can not be empty"]);
        }
        $reports_model = new \app\admin\model\Reports();
        $res = $reports_model->saveReportsForAdmin($data);
        if ($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002,'msg'=>'operation failed']);
        }

    }
}
