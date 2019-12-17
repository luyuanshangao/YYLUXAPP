<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cache;
use think\Log;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\ExcelTool;
// use app\admin\model\Interface;

/**
 * 客服留言--WholesaleInquiry
 * @author kevin   2019-02-21
 */
class WholesaleInquiry extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
        define('S_CONFIG', 'dx_sys_config');//Nosql数据表
    }

    /**
     * 产品WholesaleInquiry
     * @author kevin   2019-02-28
     */
    public function index()
    {
        $baseApi = new BaseApi();
        $SellerLists = $baseApi::getSellerLists(['status'=>1]);
        $seller_data = isset($SellerLists['data']['data'])?$SellerLists['data']['data']:'';
        $admin_user = getCustomerService();
        $paymentMethodDict = $this->dictionariesQuery('PaymentMethod');
        $data = input();
        $param_data['page_size']= input("page_size",20);
        $param_data['page'] = input("page",1);
        $param_data['path'] = url("WholesaleInquiry/index");
        if(isset($data['seller_id']) && !empty($data['seller_id'])){
            $param_data['seller_id'] = $data['seller_id'];
        }
        if(isset($data['sku_id']) && !empty($data['sku_id'])){
            $param_data['sku_id'] = $data['sku_id'];
        }
        if(isset($data['name']) && !empty($data['name'])){
            if(is_numeric($data['name']) && $data['name']<100000000){
                $param_data['customer_id'] = $data['name'];
            }else{
                $param_data['customer_name'] = $data['name'];
            }
        }
        if(isset($data['distribution_status'])  && $data['distribution_status']!==''){
            if($data['distribution_status'] == 1){
                $param_data['distribution_admin_id'] = ['gt',0];
            }else{
                $param_data['distribution_admin_id'] = 0;
            }
        }
        $group_id = session("group_id");
        if(isset($data['admin_user']) && !empty($data['admin_user'])){
            $param_data['distribution_admin_id'] = $data['admin_user'];
        }else{
            if(!empty($group_id) && $group_id == 9 && $data['distribution_status']<1){
                $param_data['distribution_admin_id'] = session("userid");
            }
        }

        if(isset($data['is_answer']) && !empty($data['is_answer'])){
            $param_data['is_answer'] = $data['is_answer'];
        }
        if(isset($data['startTime']) && !empty($data['endTime'])){
            $param_data['addtime'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $param_data['addtime'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $param_data['addtime'] = strtotime($data['endTime']);
            }
        }
        $data = $baseApi::getAdminWholesaleInquirylist($param_data);
        if($data['code'] == 200){
            $list_data = $data['data'];
        }
        return $this->fetch('',['seller_data'=>$seller_data,'admin_user'=>$admin_user,'data'=>$list_data,'paymentMethodDict'=>$paymentMethodDict,'group_id'=>$group_id]);
    }

    /*分配订单消息*/
    public function distribution_order_message(){
        $query_data = request()->post();
        $ids = isset($query_data['ids'])?$query_data['ids']:'';
        $distribution_admin_id = input("distribution_admin_id",'');
        $distribution_admin = input("distribution_admin",'');
        $baseApi = new BaseApi();
        if(!empty($ids) && !empty($distribution_admin_id) && !empty($distribution_admin)){
            $update_data['id'] = ['in',$ids];
            $update_data['distribution_admin_id'] = $distribution_admin_id;
            $update_data['distribution_admin'] = trim($distribution_admin);
            $update_data['distribution_time'] = time();
            $update_res = $baseApi::updateWholesaleInquiry($update_data);
            if(!$update_res){
                return ['code'=>1002,'msg'=>'分配失败！'];
            }else{
                return ['code'=>200,'msg'=>'分配成功！'];
            }
        }else{
            return ['code'=>1001,'msg'=>'参数错误'];
        }
    }

    /*设置紧急订单消息*/
    public function crash_message(){
        $query_data = request()->post();
        $ids = isset($query_data['ids'])?$query_data['ids']:'';
        $baseApi = new BaseApi();
        if(!empty($ids)){
            $update_data['is_crash'] = 1;
            $update_data['id'] = ['in',$ids];
            $update_res = $baseApi::updateWholesaleInquiry($update_data);
            if(!$update_res){
                return ['code'=>1002,'msg'=>'设置失败！'];
            }else{
                return ['code'=>200,'msg'=>'设置成功！'];
            }
        }else{
            return ['code'=>1001,'msg'=>'参数错误'];
        }
    }

    /*
     * 回复信息
     * */
    public function reply_message(){
        $baseApi = new BaseApi();
        if(request()->isPost()){
            $post_data = $_POST;
            if(empty($post_data['id'])){
                return ['code'=>1002,'msg'=>"此条产品询价不存在！"];
            }
            if (empty($post_data['description'])){
                return ['code'=>1002,'msg'=>"回复内容不能为空！"];
            }
            $data['inquiry_id'] = $post_data['id'];
            $data['description'] = $post_data['description'];
            $data['user_type'] = 3;
            $data['user_id'] = session("userid");
            $data['name'] = session("username");
            $data['addtime'] = time();
            $res = $baseApi::addWholesaleInquiryAnswer($data);
            $update_res = false;
            if($res){
                $update_data['id'] = $post_data['id'];
                $update_data['is_answer'] = 1;
                $update_data['operator_admin_id'] = session("userid");;
                $update_data['operator_admin'] = session("username");
                $update_data['reply_time'] = time();
                if($post_data['distribution_admin_id'] == 0){
                    $update_data['distribution_admin_id'] = session("userid");
                    $update_data['distribution_admin'] = session("username");
                    $update_data['distribution_time'] = time();
                }
                $question_data = $baseApi::getOneWholesaleInquiry(['id'=>$post_data['id']]);
                if(isset($question_data['code']) && !empty($question_data['code'])){
                    if(isset($question_data['data']['aging']) && $question_data['data']['aging'] == 0){
                        $aging = time()-$question_data['data']['addtime'];
                        $update_data['aging'] = sprintf("%01.2f", $aging/3600);
                    }
                }
                $update_res = $baseApi::updateWholesaleInquiry($update_data);
            }
            if($update_res){
                return ['code'=>200,'msg'=>"回复问题成功！"];
            }else{
                return ['code'=>1002,'msg'=>"回复问题失败！"];
            }
        }else{
            $where['id'] = input('inquiry_id');
            $message_data = $baseApi::getWholesaleInquiryWhere($where);
            if($message_data['code'] == 200){
                $question_data = $message_data['data'];
            }else{
                $question_data = [];
            }
            return $this->fetch("",['question_data'=>$question_data]);
        }
    }

    /*
  * 解决问题
  * */
    public function solved_message(){
        $post_data = request()->post();
        if(empty($post_data['id'])){
            return ['code'=>1002,'msg'=>"回复数据不存在！"];
        }
        $update_data['id'] = $post_data['id'];
        $update_data['is_answer'] = 2;
        $update_data['solve_time'] = time();
        $baseApi = new BaseApi();
        $update_res = $baseApi::updateWholesaleInquiry($update_data);
        if($update_res){
            return ['code'=>200,'msg'=>"操作成功！"];
        }else{
            return ['code'=>1002,'msg'=>"操作失败！"];
        }
    }


    /*
* 远程上传
* */
    public function remoteUpload(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = $this->localUpload();
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['ARTICLE_IMAGES'].date("Ymd");
            $config = [
                'dirPath'=>$remotePath, // ftp保存目录
                'romote_file'=>$localres['FileName'], // 保存文件的名称
                'local_file'=>$localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            if($upload){
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['url'] = $remotePath.'/'.$localres['FileName'];
                $res['complete_url'] = DX_FTP_ACCESS_URL.'/'.$remotePath.'/'.$localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            echo json_encode(array('error' => 0, 'url' => $res['complete_url']));
        }
    }

    /*
* 本地上传图片
* */
    public function localUpload(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file("imgFile");
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $path = "public".DS . 'uploads';
            $upload_path = ROOT_PATH . $path;
            $info = $file->move($upload_path);
            if($info){
                $file_path= 'uploads'. DS .$info->getSaveName();
                $res['code'] = 200;
                $res['msg'] = "上传成功";
                $res['url'] = $file_path;
                $res['FileName'] = $info->getFilename();
                return $res;
            }else{
                // 上传失败获取错误信息
                $res['code'] = 100;
                $res['msg'] = $file->getError();
                return $res;
            }
        }else{
            $res['code'] = 100;
            $res['msg'] = "上传图片超过尺寸";
            return $res;
        }
    }

    //字典数据的获取
    public function dictionariesQuery($val){
        $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
        $data = explode(";",$PayemtMethod['ConfigValue']);
        foreach ($data as $key => $value) {
            if(!empty($value)){
                $config_data = explode(":",$value);
                $list[$config_data[0]] = $config_data[1];
            }
        }
        return $list;
    }

    /*
    * 信息导出
    */
    public function export()
    {
        $baseApi = new BaseApi();
        $SellerLists = $baseApi::getStoreLists(['status'=>1]);
        $seller_data = array();
        if(!empty($SellerLists['data'])){
            foreach ($SellerLists['data'] as $key=>$value){
                $seller_data[$value['id']] = $value['true_name'];
            }
        }else{
            $this->error("店铺信息错误！");
        }
        $admin_user_where['status'] = 1;
        $admin_user_where['group_id'] = 9;//客服
        /*$admin_user = Db::name(ADMIN_USER)->where($admin_user_where)->select();
        $paymentMethodDict = $this->dictionariesQuery('PaymentMethod');*/
        $data = input();
        $param_data['page_size']= input("page_size",10000);
        $param_data['page'] = input("page",1);
        $param_data['path'] = url("WholesaleInquiry/index");
        if(isset($data['seller_id']) && !empty($data['seller_id'])){
            $param_data['seller_id'] = $data['seller_id'];
        }
        if(isset($data['sku_id']) && !empty($data['sku_id'])){
            $param_data['sku_id'] = $data['sku_id'];
        }
        if(isset($data['name']) && !empty($data['name'])){
            if(is_numeric($data['name']) && $data['name']<100000000){
                $param_data['customer_id'] = $data['name'];
            }else{
                $param_data['customer_name'] = $data['name'];
            }
        }
        if(isset($data['distribution_status'])  && $data['distribution_status']!==''){
            if($data['distribution_status'] == 1){
                $where['distribution_admin_id'] = ['gt',0];
            }else{
                $where['distribution_admin_id'] = 0;
            }
        }

        if(isset($data['admin_user']) && !empty($data['admin_user'])){
            $where['distribution_admin_id'] = $data['admin_user'];
        }else{
            $group_id = session("group_id");
            if(!empty($group_id) && $group_id == 9 && $data['distribution_status']<1){
                $where['distribution_admin_id'] = session("userid");
            }
        }

        if(isset($data['is_answer']) && !empty($data['is_answer'])){
            $param_data['is_answer'] = $data['is_answer'];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            if(strtotime($data['endTime'])-strtotime($data['startTime'])>7948800){
                $this->error('只能导出3个月的数据');
            }
        }else{
            $this->error('导出时间不能为空,并且只能导出3个月的数据');
        }
        if(isset($data['startTime']) && !empty($data['endTime'])){
            $param_data['addtime'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $param_data['addtime'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $param_data['addtime'] = strtotime($data['endTime']);
            }
        }
        $list = $baseApi::getAdminWholesaleInquirylist($param_data);

        if(isset($list['data']['data'])&&!empty($list['data']['data'])){
            $list_data= $list['data']['data'];
        }else{
            $this->error('没有数据');
        }
        $da=[];
        $is_reply_data = [0=>"未回复",1=>"已回复",2=>"已解决"];
        foreach ($list_data as $item){
            $da[] = [
                'store_name' => ' '.$seller_data[$item['seller_id']],
                'sku_id' => $item['sku_id'],
                'product_name' => $item['product_name'],
               // 'product_attr_desc' => $item['product_attr_desc'],
                'details' => $item['details'],
                'addtime' => date("Y-m-d H:i:s",$item['addtime']),
                'customer_id' => $item['customer_id'],
                'email_address' => $item['email_address'],
                'country' => $item['country'],
                'is_answer' => (!empty($item['distribution_admin'])?"已分配":"未分配")." ".$is_reply_data[$item['is_answer']],
                'operator_admin' => $item['operator_admin'],
            ];
        }

        /*$title = ['订单号', '订单总额', '退款金额', '退款币种', '国家',
            '卖家账号', '退款备注', '退款人', '退款日期'
        ];*/
        $header_data =[
            'store_name' => '所属店铺',
            'sku_id' => 'SKU',
            'product_name' => '产品名称',
            'details' => '咨询内容',
            'addtime' =>'提交时间',
            'customer_id' => '买家ID',
            'email_address' =>'买家邮箱',
            'country' =>'收货国家',
            'is_answer' => '留言状态',
            'operator_admin' => '处理人员',
        ];
        $tool = new ExcelTool();
        return  $tool ->export('Wholesale Inquiry'.date("Ymd"),$header_data,$da);
    }
}