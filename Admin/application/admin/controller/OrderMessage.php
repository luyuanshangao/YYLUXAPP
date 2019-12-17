<?php
namespace app\admin\controller;

use app\admin\model\OrderModel;
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
 * 客服留言--订单留言
 * @author kevin   2019-02-15
 */
class OrderMessage extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
    }

    /**
     * 订单留言
     * @author kevin   2019-02-15
     */
    public function index()
    {
        $baseApi = new BaseApi();
        $SellerLists = $baseApi::getStoreLists(['status'=>1]);
        $seller_data = isset($SellerLists['data'])?$SellerLists['data']:'';
        //获取后台配置的订单状态
        $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
        $countryList = $baseApi::getRegionData_AllCountryData();
        $country_data = isset($countryList['data'])?$countryList['data']:'';
        $paymentMethodDict = $this->dictionariesQuery('PaymentMethod');
        $admin_user = getCustomerService();
        $fulfillmentStatusDict = $this->dictionariesQuery('OrderStatusView');
        $message_type = $baseApi::getOrderConfig('order_message_type');
        $message_first_category_type = array();
        if(!empty($message_type['data'])){
            $message_type_data = $message_type['data'];
            foreach ($message_type_data as $key=>$value){
                $message_first_category_type[$value['code']] = $value;
            }
        }else{
            $message_type_data = "";
        }
        $data = input();
        $order_where = array();
        $where = array();
        $page_size= input("page_size",20);
        $page = input("page",1);
        $group_id = session("group_id");
        $is_restrict = 0;
        if(isset($data['page_size'])){
            unset($data['page_size']);
        }
        if(isset($data['page'])){
            unset($data['page']);
        }
        $query = $data;
        if(isset($data['store_id']) && !empty($data['store_id'])){
            $order_where['store_id'] = $data['store_id'];
        }
        if(isset($data['order_number']) && !empty($data['order_number'])){
            $data['order_number'] = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',',',',',',',',',' ',',',',',','],$data['order_number']);
            $pattern = '/(,)+/i';
            $data['order_number'] = preg_replace($pattern,',',$data['order_number']);
            $order_where['order_number'] = ["in",$data['order_number']];
        }
        if(isset($data['sku_num']) && !empty($data['sku_num'])){
            $order_where['sku_num'] = $data['sku_num'];
        }
        if(isset($data['order_status']) && !empty($data['order_status'])){
            $order_where['order_status'] = $data['order_status'];
        }

        if(isset($data['country_code']) && !empty($data['country_code'])){
            $order_where['country_code'] = $data['country_code'];
        }
        if(!empty($data['startCreateOn']) && !empty($data['endCreateOn'])){
            $order_where['o.create_on'] = ['BETWEEN',[strtotime($data['startCreateOn']),strtotime($data['endCreateOn'])]];
        }else{
            if(isset($data['startCreateOn']) && !empty($data['startCreateOn'])){
                $order_where['o.create_on'] = strtotime($data['startCreateOn']);
            }
            if(isset($data['endCreateOn']) && !empty($data['endCreateOn'])){
                $order_where['o.create_on'] = strtotime($data['endCreateOn']);
            }
        }
        if(isset($data['customer_name']) && !empty($data['customer_name'])){
            if(is_numeric($data['customer_name'])){
                $order_where['customer_id'] = $data['customer_name'];
            }else{
                $order_where['customer_name'] = $data['customer_name'];
            }
        }
        if(isset($data['email']) && !empty($data['email'])){
            $email = $data['email'];
        }
        if(isset($data['pay_type']) && !empty($data['pay_type'])){
            $order_where['pay_type'] = $data['pay_type'];
        }
        if($data['fsc_shipment'] === 0 || $data['fsc_shipment'] === '0'|| $data['fsc_shipment'] == 1){
            $order_where['fsc_shipment'] = $data['fsc_shipment'];
        }
        $a = 0;
        //dump($order_where);exit;
        if(isset($data['is_reply']) && !empty($data['is_reply'])){
            $where['is_reply'] = $data['is_reply'];
        }
        if(isset($data['message']) && !empty($data['message'])){
            $where['message'] = ["like","%".$data['message']."%"];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['om.create_on'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $where['om.create_on'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $where['om.create_on'] = strtotime($data['endTime']);
            }
        }
        if(isset($email)){
            $CustomerData = $baseApi::getCustomerInfoByAccount(['AccountName'=>$email]);
            if(isset($CustomerData['code']) && $CustomerData['code']==200){
                $order_where['customer_id'] = $CustomerData['data'];
            }
        }
        /*是否是客服账户*/
        /*if(!empty($group_id) && $group_id == 9){
            //当有是否回复条件传入
            if(isset($data['distribution_status']) && $data['distribution_status']!==''){
                if($data['distribution_status'] == 1){
                    $where['distribution_admin_id'] = session("userid");
                }else{
                    $where['distribution_admin_id'] = 0;
                }
            }else{
                if(empty($order_where) && empty($where)){
                    $is_restrict = 1;
                }
                $where['distribution_admin_id'] = session("userid");
            }
        }else{*/
            /*当有是否回复条件传入*/
            if(isset($data['distribution_status']) && $data['distribution_status']!==''){
                if($data['distribution_status'] == 1){
                    $where['distribution_admin_id'] = ['gt',0];
                }else{
                    $where['distribution_admin_id'] = 0;
                }
            }
            if(isset($data['admin_user']) && !empty($data['admin_user'])) {
                $where['distribution_admin_id'] = $data['admin_user'];
            }
            if(isset($data['first_category']) && !empty($data['first_category'])) {
                $where['first_category'] = $data['first_category'];
            }
        //}
        $where['message_type'] = 2;
        $where['is_new'] = 1;
        $order = array();
        if(!empty($data['order_order_number'])){
            if($data['order_order_number'] == 1){
                $order['order_number'] = "DESC";
            }else{
                $order['order_number'] = "ASC";
            }
        }
        if(!empty($data['order_captured_amount_usd'])){
            if($data['order_captured_amount_usd'] == 1){
                $order['captured_amount_usd'] = "DESC";
            }else{
                $order['captured_amount_usd'] = "ASC";
            }
        }
        /*if(!empty($data['order_captured_amount_usd'])){
            $order['captured_amount_usd'] = $data['order_captured_amount_usd'];
        }*/
        /*报表提交过了的参数 20190612 kevin*/
        /*是否是查询分配信息*/
            if(!empty($data['distribution_time_start']) && !empty($data['distribution_time_end'])){
                $where['om.distribution_time'] = ['BETWEEN',[strtotime($data['distribution_time_start']),strtotime($data['distribution_time_end'])]];
            }else{
                if(isset($data['distribution_time_start']) && !empty($data['distribution_time_start'])){
                    $where['om.distribution_time'] = strtotime($data['distribution_time_start']);
                }
                if(isset($data['distribution_time_end']) && !empty($data['distribution_time_end'])){
                    $where['om.distribution_time'] = strtotime($data['distribution_time_end']);
                }
            }
            if(!empty($where['om.distribution_time'])){
                unset($where['is_new']);
            }

            /*是否是查询回复信息*/
        if(!empty($data['reply_time_start']) && !empty($data['reply_time_end'])){
            $where['om.reply_time'] = ['BETWEEN',[strtotime($data['reply_time_start']),strtotime($data['reply_time_end'])]];
        }else{
            if(isset($data['reply_time_start']) && !empty($data['reply_time_start'])){
                $where['om.reply_time'] = strtotime($data['reply_time_end']);
            }
            if(isset($data['reply_time_end']) && !empty($data['reply_time_end'])){
                $where['om.reply_time'] = strtotime($data['reply_time_end']);
            }
        }
        if(!empty($data['reply_type'])){
            $where['is_reply'] = ["egt",$data['reply_type']];
        }
        if(!empty($where['om.reply_time'])){
            unset($where['is_new']);
            $where['is_earliest'] = 1;
        }
        $data = model("OrderMessage")->getOrderMessage($order_where,$where,$page_size,$page,$query,$is_restrict,$order);
        return $this->fetch('',['seller_data'=>$seller_data,'orderStautsDict'=>$orderStautsDict,'country_data'=>$country_data,'paymentMethodDict'=>$paymentMethodDict,'fulfillmentStatusDict'=>$fulfillmentStatusDict,'admin_user'=>$admin_user,'data'=>$data,'group_id'=>$group_id,'message_type_data'=>$message_type_data,'message_first_category_type'=>$message_first_category_type]);
    }

    /*分配订单消息*/
    public function distribution_order_message(){
        $query_data = request()->post();
        $ids = isset($query_data['ids'])?$query_data['ids']:'';
        $distribution_admin_id = input("distribution_admin_id",'');
        $distribution_admin = input("distribution_admin",'');

        if(!empty($ids) && !empty($distribution_admin_id) && !empty($distribution_admin)){
            $orders_where['id'] = ['in',$ids];
            $orders_where['message_type'] = 2;
            $orders_where['is_reply'] = 1;
            $order_ids_data = model("OrderMessage")->getOrderMessageIds($orders_where,"order_id");
            if($order_ids_data){
                $ids_where['order_id'] = ['in',$order_ids_data];
                $ids_where['message_type'] = 2;
                $ids_data = model("OrderMessage")->getOrderMessageIds($ids_where);
                if(!$ids_data){
                    return ['code'=>1001,'msg'=>'数据不正确'];
                }
            }else{
                return ['code'=>1001,'msg'=>'订单号不存在'];
            }
            $where['id'] = ['in',$ids_data];
            $where['is_reply'] = ['neq',3];
            $update_data['distribution_admin_id'] = $distribution_admin_id;
            $update_data['distribution_admin'] = trim($distribution_admin);
            $update_data['distribution_time'] = time();
            $update_res = model("OrderMessage")->updateOrderMessage($where,$update_data);
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
    public function crash_order_message(){
        $query_data = request()->post();
        $ids = isset($query_data['ids'])?$query_data['ids']:'';
        if(!empty($ids)){
            $where['id'] = ['in',$ids];
            $update_data['is_crash'] = isset($query_data['is_crash'])?$query_data['is_crash']:1;
            $update_res = model("OrderMessage")->updateOrderMessage($where,$update_data);
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
    public function reply_order_message(){
        if(request()->isPost()){
            $post_data = $_POST;
           if(request()->isAjax()){
               if(empty($post_data['order_id'])){
                   return ['code'=>1002,'msg'=>"订单不存在！"];
               }
               if (empty($post_data['message'])){
                   return ['code'=>1002,'msg'=>"回复内容不能为空！"];
               }
           }else{
               if(empty($post_data['order_id'])){
                   $this->error("订单不存在！");
               }
               if (empty($post_data['message'])){
                   $this->error("回复内容不能为空！");
               }
           }
           $parent_id = model("OrderMessage")->getUserNewOrderMessageId(['order_id'=>$post_data['order_id'],'message_type'=>2]);//最新一条客户留言
            $data['order_id'] = $post_data['order_id'];
            $data['message'] = $post_data['message'];
            $data['user_id'] = session("userid");
            $data['user_name'] = session("username");
            $data['message_type'] = 3;//后台留言
            $data['file_url'] = isset($post_data['file_url'])?$post_data['file_url']:"";
            $data['statused'] = -1;
            $data['create_on'] = time();
            $data['is_reply'] = 1;
            $data['parent_id'] = !empty($parent_id)?$parent_id:0;
            $is_solved = isset($post_data['is_solved'])?$post_data['is_solved']:0;
            $res = model("OrderMessage")->addOrderMessage($data,$is_solved);
            if(request()->isAjax()){
                if($res){
                    return ['code'=>200,'msg'=>"回复留言成功！"];
                }else{
                    return ['code'=>1002,'msg'=>"回复留言失败！"];
                }
            }else{
                if($res){
                    $this->success("回复留言成功！");
                }else{
                    $this->error("回复留言失败！");
                }
            }

        }else{
            $where['order_id'] = input('order_id');
            $message_data = model("OrderMessage")->getUserOrderMessage($where);
            foreach ($message_data as $key=>&$value){
                $value['message'] = htmlspecialchars_decode(htmlspecialchars_decode($value['message']));
            }
            return $this->fetch("",['message_data'=>$message_data]);
        }
    }


    /*
     * 解决问题
     * */
    public function solved_order_message(){
        $post_data = request()->post();
        if(empty($post_data['order_id'])){
            return ['code'=>1002,'msg'=>"订单不存在！"];
        }
        $data['order_id'] = $post_data['order_id'];
        /*最新回复*/
        $rep_message = model("OrderMessage")->getUserNewOrderMessageId(['order_id'=>$data['order_id'],'message_type'=>['neq',2]]);
        if(empty($rep_message)){
            return ['code'=>1002,'msg'=>"请先回复！"];
        }
        $message_data = model("OrderMessage")->getUserOneOrderMessageId(['order_id'=>$post_data['order_id'],'message_type'=>2]);//最新一条客户留言
        $where['id'] = $message_data['id'];
        $update_data['is_reply'] = 3;
        $update_data['solve_time'] = time();
        $update_res = model("OrderMessage")->updateOrderMessage($where,$update_data);
        if($update_res){
            return ['code'=>200,'msg'=>"操作成功！"];
        }else{
            return ['code'=>1002,'msg'=>"操作失败！"];
        }
    }


    /*
    * 信息导出
    */
    public function export()
    {
        $baseApi = new BaseApi();
        $admin_user_where['status'] = 1;
        $admin_user_where['group_id'] = 9;//客服
        $data = input();
        $order_where = array();
        $where = array();
        $page_size= input("page_size",10000);
        $page = input("page",1);
        if(isset($data['page_size'])){
            unset($data['page_size']);
        }
        if(isset($data['page'])){
            unset($data['page']);
        }
        $query = $data;
        if(isset($data['store_id']) && !empty($data['store_id'])){
            $order_where['store_id'] = $data['store_id'];
        }
        if(isset($data['order_number']) && !empty($data['order_number'])){
            $order_where['order_number'] = $data['order_number'];
        }
        if(isset($data['sku_num']) && !empty($data['sku_num'])){
            $order_where['sku_num'] = $data['sku_num'];
        }


        if(!empty($data['startCreateOn']) && !empty($data['endCreateOn'])){
            $order_where['o.create_on'] = ['BETWEEN',[strtotime($data['startCreateOn']),strtotime($data['endCreateOn'])]];
        }else{
            if(isset($data['startCreateOn']) && !empty($data['startCreateOn'])){
                $order_where['o.create_on'] = strtotime($data['startCreateOn']);
            }
            if(isset($data['endCreateOn']) && !empty($data['endCreateOn'])){
                $order_where['o.create_on'] = strtotime($data['endCreateOn']);
            }
        }
        if(isset($data['customer_name']) && !empty($data['customer_name'])){
            if(is_numeric($data['customer_name'])){
                $order_where['customer_id'] = $data['customer_name'];
            }else{
                $order_where['customer_name'] = $data['customer_name'];
            }
        }
        if(isset($data['email']) && !empty($data['email'])){
            $email = $data['email'];
        }
        if(isset($data['pay_type']) && !empty($data['pay_type'])){
            $order_where['pay_type'] = $data['pay_type'];
        }

        if(isset($data['distribution_status']) && $data['distribution_status']!==''){
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
            if(!empty($group_id) && $group_id == 9 && isset($data['distribution_status']) && $data['distribution_status']<1){
                $where['distribution_admin_id'] = session("userid");
            }
        }

        if(isset($data['is_reply']) && !empty($data['is_reply'])){
            $where['is_reply'] = $data['is_reply'];
        }

        if(!empty($data['startTime']) && !empty($data['endTime'])){
            if(strtotime($data['endTime'])-strtotime($data['startTime'])>7948800){
                $this->error('只能导出3个月的数据');
            }
        }else{
            $this->error('导出时间不能为空,并且只能导出3个月的数据');
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['create_on'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $where['create_on'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $where['create_on'] = strtotime($data['endTime']);
            }
        }

        if(isset($email)){
            $CustomerData = $baseApi::getCustomerInfoByAccount(['AccountName'=>$email]);
            if(isset($CustomerData['code']) && $CustomerData['code']==200){
                $order_where['customer_id'] = $CustomerData['data'];
            }
        }
        $where['message_type'] = 2;
        $list = model("OrderMessage")->getOrderMessage($order_where,$where,$page_size,$page,$query);
        $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
        $order_status_data = array();
        foreach ($orderStautsDict as $key=>$vlaue){
            $order_status_data[$vlaue[0]] = $vlaue[1];
        }
        if(isset($list['data'])&&!empty($list['data'])){
            $list_data= $list['data'];
        }else{
            $this->error('没有数据');
        }
        $da=[];
        $is_reply_data = [1=>"未回复",2=>"已回复",3=>"已解决"];
        foreach ($list_data as $item){
            $da[] = [
                'store_name' => ' '.$item['store_name'],
                'order_number' => $item['order_number'],
                'create_on' => date("d/m/Y",$item['create_on']),
                'order_status' => $order_status_data[$item['order_status']],
                'pay_type' => $item['pay_type'],
                'user_id' => $item['user_id'],
                'country' => $item['country'],
                'message_type' => (!empty($item['distribution_admin'])?"已分配":"未分配")." ".$is_reply_data[$item['is_reply']],
                'operator_admin' => $item['operator_admin'],
            ];
        }

        /*$title = ['订单号', '订单总额', '退款金额', '退款币种', '国家',
            '卖家账号', '退款备注', '退款人', '退款日期'
        ];*/
        $header_data =[
            'store_name' => '所属店铺',
            'order_number' => '订单号',
            'create_on' => '下单时间',
            'order_status' => '订单状态',
            'pay_type' =>'支付方式',
            'user_id' => '买家ID',
            'country' =>'收货国家',
            'message_type' => '留言状态',
            'message'=>'留言内容',
            'operator_admin' => '处理人员',
        ];
        $tool = new ExcelTool();
        return  $tool ->export('订单留言'.date("Ymd"),$header_data,$da);
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

    /*
     * 用户订单留言报表
     * add by 20190531 kevin
     * */
    public function report(){
        $baseApi = new BaseApi();
        /*获取客服*/
        $admin_user = getCustomerService();
        $SellerLists = $baseApi::getStoreLists(['status'=>1]);
        $seller_data = array();
        if(!empty($SellerLists['data'])){
            $seller_data = $SellerLists['data'];
        }
        $param_data = input();
        if(empty($param_data['startTime']) && empty($param_data['endTime'])){
            //默认时间一个月
           /* $startTime = strtotime("-1 month 00:00:00");
            $endTime = strtotime(date("Y-m-d 23:59:59"));
            $data['om.create_on'] = array('between',$startTime.','.$endTime);
            $param_data['startTime'] = date("Y-m-d H:i:s",$startTime);
            $param_data['endTime'] = date("Y-m-d H:i:s",$endTime);*/
        }elseif(!empty($param_data['startTime']) && !empty($param_data['endTime'])){
            $data['om.create_on'] = array('between',strtotime($param_data['startTime']).','.strtotime($param_data['endTime']));
        }elseif(!empty($param_data['startTime']) && empty($param_data['endTime'])){
            $data['om.create_on'] = array('egt',strtotime($param_data['startTime']));
        }elseif (empty($param_data['startTime']) && !empty($param_data['endTime'])){
            $data['om.create_on'] = array('elt',strtotime($param_data['endTime']));
        }
        $order_message_total[0]['order_count'] = 0;
        $order_message_total[0]['new_order_count'] = 0;
        $order_message_total[0]['new_order_pending_count'] = 0;
        $order_message_total[0]['new_order_shipment_count'] = 0;
        $order_message_total[0]['new_order_awaiting_count'] = 0;
        $order_message_total[0]['new_order_aftersales_count'] = 0;
        $order_message_total[0]['new_order_other_count'] = 0;
        $order_message_total[0]['distribution_order_count'] = 0;
        $order_message_total[0]['reply_order_count'] = 0;
        $order_message_total[0]['solve_order_count'] = 0;
        $order_message_total[0]['order_aging_avg'] = 0;
        $order_message_total[0]['reply_order_count'] = 0;
        if(!empty($data['om.create_on'])){
            foreach ($admin_user as $key=>$value){
                $data['distribution_admin_id'] = $value['id'];
                $order_message_total[$value['id']] = model("OrderMessage")->getOrderMessageTotal($data);
                if(!empty($order_message_total[$value['id']])){
                    $order_message_total[0]['order_count'] = $order_message_total[$value['id']]['order_count'];
                    $order_message_total[0]['new_order_count'] = $order_message_total[$value['id']]['new_order_count'];
                    $order_message_total[0]['new_order_pending_count'] += $order_message_total[$value['id']]['new_order_pending_count'];
                    $order_message_total[0]['new_order_shipment_count'] += $order_message_total[$value['id']]['new_order_shipment_count'];
                    $order_message_total[0]['new_order_awaiting_count'] += $order_message_total[$value['id']]['new_order_awaiting_count'];
                    $order_message_total[0]['new_order_aftersales_count'] += $order_message_total[$value['id']]['new_order_aftersales_count'];
                    $order_message_total[0]['new_order_other_count'] += $order_message_total[$value['id']]['new_order_other_count'];
                    $order_message_total[0]['distribution_order_count'] += $order_message_total[$value['id']]['distribution_order_count'];
                    $order_message_total[0]['solve_order_count'] += $order_message_total[$value['id']]['solve_order_count'];
                    $order_message_total[0]['order_aging_avg'] += $order_message_total[$value['id']]['order_aging_avg'];
                    $order_message_total[0]['reply_order_count'] += $order_message_total[$value['id']]['reply_order_count'];
                }
            }
            $data['distribution_admin_id'] = 0;
            $no_distribution_order_message_total = model("OrderMessage")->getOrderMessageTotal($data);
        }

        return $this->fetch("",['admin_user'=>$admin_user,'order_message_total'=>$order_message_total,'no_distribution_order_message_total'=>$no_distribution_order_message_total,"param_data"=>$param_data,"seller_data"=>$seller_data]);
    }

    /*
     * 获取筛选时间
     * */
    public function getQueryTime(){
        /*时间周期类型 1：天 ，2：周，3：月*/
        $time_type = input("time_type",1);
        $start_time = input("start_time");
        $end_time = input("end_time");
        $now_time = time();
        switch ($time_type){
            case 1:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",$now_time);
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime($start_time));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime($start_time));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime($end_time));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime($end_time));
                }
                break;
            case 2:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime("-6 day"));
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime($start_time));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime("+6 day",strtotime($start_time)));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime("-6 day",strtotime($end_time)));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime($end_time));
                }
                break;
            case 3:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime("-1 month")+86400);
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime($start_time));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime("+1 month",strtotime($start_time))-86400);
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime("-1 month",strtotime($end_time))+86400);
                    $end_time_str = date("Y-m-d 23:59:59",strtotime($end_time));
                }
                break;
            default:
                if(empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",$now_time);
                    $end_time_str = date("Y-m-d 23:59:59",$now_time);
                }elseif(!empty($start_time) && empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime($start_time));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime($start_time));
                }elseif (empty($start_time) && !empty($end_time)){
                    $start_time_str = date("Y-m-d 00:00:00",strtotime($end_time));
                    $end_time_str = date("Y-m-d 23:59:59",strtotime($end_time));
                }
                break;
        }
        if(strtotime($end_time_str)>strtotime(date("Y-m-d 23:59:59"))){
            $end_time_str = date("Y-m-d 23:59:59");
        }
        $data['start_time_str'] = $start_time_str;
        $data['end_time_str'] = $end_time_str;
        return $data;
    }

    /**
     * 查询订单金额【条件和每日发送订单监控邮件一致】
     *
     * INSERT INTO `DX_Phoenix_Admin`.`dx_navigation_bar` ( `name`, `parent_id`, `sort`, `url`, `status`, `controller`, `action`, `addtime`, `edittime` )
    VALUES
    ( '每日订单额查询', 97, 140, 'OrderMessage/queryOrderAmount', 1, NULL, NULL, '1', NULL );
     *
     */
    public function queryOrderAmount(){
        $start_data = input('start_time', date('Y-m-d 00:00:00'));
        $end_data = input('end_time', date('Y-m-d 23:59:59'));
        $order_model = new OrderModel();
        $start_time = strtotime($start_data);
        $end_time = strtotime($end_data);
        $data = $order_model->getQueryOrderAmount($start_time, $end_time);
        $data['start_time'] = $start_data;
        $data['end_time'] = $end_data;
        return $this->fetch("",['data'=>$data, 'param_data'=>['start_time'=>$start_data,'end_time'=>$end_data]]);
    }

}