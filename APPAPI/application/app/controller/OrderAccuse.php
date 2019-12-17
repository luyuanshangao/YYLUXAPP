<?php
/**
 * 投诉订单
 * Created by PhpStorm.
 * User: Kevin
 * Date: 2018/3/8
 * Time: 16:55
 */
namespace app\app\controller;
use app\app\dxcommon\BaseApi;
use app\common\controller\FTPUpload;
use \think\Request;
use app\common\controller\AppBase;
class OrderAccuse  extends AppBase
{
    public $baseApi;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->baseApi = new BaseApi();
    }

    /*
     * 投诉订单列表
     */
    public function index()
    {
        $baseApi = new BaseApi();
        $_accuse_reason = $baseApi->getOrderConfig("accuse_reason");
        $accuse_reason = $_accuse_reason['data'];

        /** 参数校验 20171127 start **/
        $_accuse_reason = input("accuse_reason");
        $_accuse_status = input("accuse_status");
        $customer_id= input("customer_id");
        $_page_size = (int)input("page_size",20);
        $_page = (int)input("page",1);
        //Accuse reason校验
        if (!empty($_accuse_reason)){
            $reason_flag = false;
            foreach ($accuse_reason as $v1){
                if ($_accuse_reason == $v1['code']){
                    $reason_flag = true;
                    break;
                }
            }
            if (!$reason_flag){
                $_accuse_reason = '';
            }
        }
        //Status校验
        if (!empty($_accuse_status)){
            if (!in_array($_accuse_status, [1,2,3])){
                $_accuse_status = '';
            }
        }
        /** 参数校验 20171127 end **/
        $param['customer_id'] = $customer_id;
        $param['accuse_reason'] = $_accuse_reason;
        $param['accuse_status'] = $_accuse_status;

        $param['page_size'] = $_page_size;
        $param['page'] = $_page;
        $param['path'] = url("OrderAccuse/index");
        $param = array_filter($param);
        $data = $baseApi->getOrderAccuseList($param);
        $Status=['All','Buyer complaint merchant','Intervention treatment of customer service','Complains'];
        if($data['data']){
            foreach ($data['data']['data'] as $key=>&$value){

                $data['data']['data'][$key]['imgs']=str_replace('"','',$value['imgs']);
                if(!empty($data['data']['data'][$key]['imgs'])){
                    $data['data']['data'][$key]['imgs']= json_decode($data['data']['data'][$key]['imgs']);
                }else{
                    $data['data']['data'][$key]['imgs']= [];
                }
                $value['accuse_reason']=!empty($accuse_reason[$value['accuse_reason']]['en_name'])?$accuse_reason[$value['accuse_reason']]['en_name']:'';
                $value['accuse_status']=!empty($Status[$value['accuse_status']])?$Status[$value['accuse_status']]:'';
            }
        }
        $data['data']['code']=200;
        //var_dump($data['data']['data']);
        $list=$data['data']['data'];
        unset($data['data']['data'],$data['data']['page']);
        $data['data']['data']['list']=$list;

        $data['data']['data']['accuse_reason']=$accuse_reason;

        $data['data']['data']['Status']=$Status;
        return json($data['data']);//
    }

    /*
     * 订单投诉
     * */
    public function OrderAccuse(){
            $order_number = input("o_number/d");
            $cstomer['ID'] = input("customer_id");
            $baseApi = new BaseApi();
            $OrderBasics = $baseApi->getOrderBasics(['order_number'=>$order_number],$cstomer['ID']);
        //{$data['currency_value']} {$data['receivable_shipping_fee']

            if(isset($OrderBasics['code']) && $OrderBasics['code']==200 && !empty($OrderBasics['data'])){
                $baseApi = new BaseApi();
                $accuse_reason = $baseApi->getOrderConfig("accuse_reason");
                $currency_value = getCurrency('', $OrderBasics['data']['currency_code']);
                $OrderBasics['data']['currency_value']=$currency_value;
                $data['code']=200;
                $data['data']=$OrderBasics['data'];
                $data['data']['accuse_reason']=$accuse_reason['data'];
                return json($data);
            }else{
                return json(['code'=>1001,'data'=>$OrderBasics['data'],'msg'=>"Order does not exist"]);
            }

    }

    /*
     * 订单投诉ing
     */
    public function OrderAccuseIng(){
            $post_data = input();
            $customer_id = input("customer_id");
            $UserName = input("UserName");
            $order_number = input("o_number/d");
            $OrderBasics = $this->baseApi->getOrderBasics(['order_number'=>$order_number],$customer_id);
            if(isset($OrderBasics['code']) && $OrderBasics['code']==200){
                $data['order_id'] = $OrderBasics['data']['order_id'];
                $data['order_number'] = $OrderBasics['data']['order_number'];
                $data['customer_id'] = $customer_id;
                $data['customer_name'] = $UserName;
                $data['store_id'] = $OrderBasics['data']['store_id'];
                $data['store_name'] = $OrderBasics['data']['store_name'];
                $data['accuse_reason'] = input("accuse_reason");
                $data['accuse_status'] = 1;
                $data['imgs'] = isset($post_data['imgs'])?$post_data['imgs']:'';
                $data['remarks'] = input("remarks");
                $res = $this->baseApi->saveOrderAccuse($data);
                return $res;
            }else{
                $res['code'] = 1002;
                $res['msg'] = "Order does not exist";
                return $res;
            }

    }

    /*
    * 远程上传
    * */
    public function remoteUpload(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = localUpload();
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['ORDER_ACCUSE_IMAGES'].date("Ymd");
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
                $res['complete_url'] = DX_FTP_ACCESS_URL.$remotePath.'/'.$localres['FileName'];
                $res['url'] = $remotePath.'/'.$localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            echo json_encode($res);
        }else{
            return json_encode($localres);
        }
    }

    /*
     *投诉卖家
     * */
    public function createOrderComplaint(){

    }
}
