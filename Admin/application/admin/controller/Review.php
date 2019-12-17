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
 * 评论管理
 * @author kevin   2019-02-15
 */
class Review extends Action
{
    public function __construct()
    {
        Action::__construct();
        define('ADMIN_USER', 'user');
    }

    /**
     * 评论管理
     * @author kevin   2019-04-16
     */
    public function index()
    {
        $baseApi = new BaseApi();
        $data = input();
        $param_data['path'] = url("Review/index");
        $param_data['page_size']= input("page_size",20);
        $param_data['page'] = input("page",1);
        $param_data['page_query'] = $data;
        if(isset($data['page_size'])){
            unset($data['page_size']);
        }
        if(isset($data['page'])){
            unset($data['page']);
        }
        if(isset($data['product_id']) && !empty($data['product_id'])){
            $param_data['product_id'] = $data['product_id'];
        }
        if(isset($data['sku_num']) && !empty($data['sku_num'])){
            $param_data['sku_num'] = $data['sku_num'];
        }

        if(isset($data['approved']) && $data['approved']!==''){
            $param_data['approved'] = $data['approved'];
        }

        if(isset($data['overall_rating']) && !empty($data['overall_rating'])){
            $param_data['overall_rating'] = $data['overall_rating'];
        }
        if(!empty($data['startCreateOn']) && !empty($data['endCreateOn'])){
            $param_data['add_time'] = ['BETWEEN',[strtotime($data['startCreateOn']),strtotime($data['endCreateOn'])]];
        }else{
            if(isset($data['startCreateOn']) && !empty($data['startCreateOn'])){
                $param_data['add_time'] = strtotime($data['startCreateOn']);
            }
            if(isset($data['endCreateOn']) && !empty($data['endCreateOn'])){
                $param_data['add_time'] = strtotime($data['endCreateOn']);
            }
        }
        if(isset($data['customer_name']) && !empty($data['customer_name'])){
            if(is_numeric($data['customer_name'])){
                $param_data['customer_id'] = $data['customer_name'];
            }else{
                $param_data['customer_name'] = $data['customer_name'];
            }
        }
        $data =$baseApi::getAdminReview($param_data);
        $this->assign("list",$data['data']);
        return $this->fetch('');
    }

    /**
     * 修改评论审核状态
     */
    public function updateStatus(){
        if($data = request()->post()){
            if(empty($data["ID"])){
                return array('code'=>100,'mag'=>'评论ID不能为空！','result'=>'评论ID不能为空！');
            }
            $updata_data['review_id'] = $data["ID"];
            $updata_data['approved'] = $data["Status"];
            $updata_data['approval_staff'] = session("username");
            $result = BaseApi::updateReviewStatus($updata_data);
            if(!empty($result["code"]) && $result["code"] == 200){
                return array('code'=>200,'msg'=>$result["msg"],'result'=>$result["msg"]);
            }else{
                return array('code'=>100,'msg'=>$result["msg"],'result'=>$result["msg"]);
            }
        }

    }

    /*增加评论*/
    public function addReview(){
        $sku_num = input("sku_num");
        if(!empty($sku_num)){
            $review_param = input();
            $param_data = array();
            $param_data["sku_num"] = input("sku_num");
            $param_data["customer_id"] = 5514705;
            $param_data["store_id"] = 0;
            $param_data["order_id"] = 888;
            $param_data["order_number"] = 888888888888888888;
            $param_data["overall_rating"] = input("overall_rating");
            $param_data["price_rating"] = $param_data["overall_rating"];
            $param_data["ease_of_use_rating"] = $param_data["overall_rating"];
            $param_data["build_quality_rating"] = $param_data["overall_rating"];
            $param_data["usefulness_rating"] = $param_data["overall_rating"];
            $param_data["customer_name"] = input("customer_name");
            $param_data["country_code"] = input("country_code");
            $param_data["reviews_label"][] = input("reviews_label");
            if(!empty($review_param) && !empty($param_data["images"])){
                $param_data["images"] = $review_param["images"];
                $param_data["static_images"] = count($param_data["images"]);
            }else{
                $param_data["static_images"] = 0;
            }
            $param_data["static_videos"] = 0;
            $param_data["content"] = input("content");
            $add_time = input("add_time");
            if(!empty($add_time)){
                $param_data['add_time'] = strtotime($add_time);
            }
            $res = BaseApi::addReviews($param_data);
            return $res;
        }else{
            $country = BaseApi::getRegionList();
            $this->assign('country',$country);
            return $this->fetch();
        }
    }

    /*
* 远程上传
* */
    public function remoteUpload(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = localUpload();
        /*$res['code'] = 200;
        $res['msg'] = "Success";
        $res['url'] = $localres['url'];
        $res['complete_url'] = $localres['url'];*/
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['AFFILIATE_IMAGES'].date("Ymd");
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
            echo json_encode($res);
        }
    }
}