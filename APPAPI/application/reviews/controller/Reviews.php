<?php
namespace app\reviews\controller;
use app\common\controller\Base;
use think\Log;
use app\common\controller\FTPUpload;
use app\app\dxcommon\BaseApi;
class Reviews extends Base
{
    /*
     * 添加商品评论
     * */
   public function addReviews(){
       $redis_Review = ReviewFiltering();
       $paramData = request()->post();
       /*需要判断参数中是否存在的值*/
       $success_count = 0;
       $error_count = 0;
       $Review_content = '';
       /*判断如果是单个评论则将评论值转为多维数组*/
       if(isset($paramData['customer_id'])){
           $tag_paramData = $paramData;
           unset($paramData);
           $paramData[0] = $tag_paramData;
       }

       foreach ($paramData as $kkey=>$vvalue){
           $data = [];
           $verification = ['customer_id','order_number','overall_rating','content'];
           /*判断参数中是否存在，不存在返回错误*/
           foreach ($verification as $value){
               if(isset($vvalue[$value])){
                   $data[$value] = $vvalue[$value];
               }else{
                   $data[$value] = '';
                   return apiReturn(['code'=>'1001','msg'=>"Lack of parameters:".$value]);
               }
           }
           /*查询数据库是否存在 20190529 kevin*/
           $customer_id = $vvalue['customer_id'];
           $baseApi = new BaseApi();
           $post['ID']=$customer_id;
           $customer_datas = $baseApi->getCustomerByID($post);

           if(empty($customer_datas) || empty($customer_datas['data']['UserName'])){
               return apiReturn(['code'=>'1001','msg'=>"Customer does not exist"]);
           }else{
               $customer_data=$customer_datas['data'];
           }
            $vvalue['customer_name'] = $customer_data['UserName'];
            /*查询订单基本信息 20190529 kevin*/
           $order_info = model("orderfrontend/OrderModel")->getBasicOrderInfo(['order_number'=>$vvalue['order_number'],'customer_id'=>$vvalue['customer_id']]);
           if(empty($order_info)){
               return apiReturn(['code'=>'1001','msg'=>"Order does not exist"]);
           }
           $validate = $this->validate($vvalue,"reviews.addReviews");
           if(true !== $validate){
               return apiReturn(['code'=>1002,"msg"=>$validate]);
           }
           //判断是否有特殊词语
           $Review_content = '';
           if($Review_content = preg_replace('/'.$redis_Review.'/i', '***', $data['content'])){
               if(strpos($Review_content,'***') ===false){

               }else{
                    $data['raw_content'] = $data['content'];
                    $data['content'] = $Review_content;
               }
           }
           if((!isset($vvalue['product_id']) || empty($vvalue['product_id'])) && ((!isset($vvalue['sku_id']) || empty($vvalue['sku_id'])))){
               return apiReturn(['code'=>'1001','msg'=>"product_id or sku_id One can't be empty"]);
           }
           $product_data = array();
           if(!empty($order_info['item'])){
               foreach ($order_info['item'] as $order_goods){
                   if($vvalue['sku_id'] == $order_goods['sku_id']){
                       $product_data = $order_goods;
                       break;
                   }
               }
           }
           if(empty($product_data)){
               return apiReturn(['code'=>'1001','msg'=>"Product does not exist"]);
           }

           /*拼装评论数据*/
           $data['customer_id'] = $vvalue['customer_id'];
           $data['customer_name'] = $customer_data['UserName'];
           $data['country_code'] = $order_info['country_code'];
           $data['order_id'] = $order_info['order_id'];
           $data['product_id'] = $product_data['product_id'];
           $data['sku_id'] = $product_data['sku_id'];
           $data['product_attr_ids'] = $product_data['product_attr_ids'];
           $data['product_attr_desc'] = $product_data['product_attr_desc'];
           $data['price_rating'] = !empty($vvalue['price_rating'])?$vvalue['price_rating']:$vvalue['overall_rating'];
           $data['ease_of_use_rating'] = !empty($vvalue['ease_of_use_rating'])?$vvalue['ease_of_use_rating']:$vvalue['overall_rating'];
           $data['build_quality_rating'] = !empty($vvalue['build_quality_rating'])?$vvalue['build_quality_rating']:$vvalue['overall_rating'];
           $data['usefulness_rating'] = !empty($vvalue['usefulness_rating'])?$vvalue['usefulness_rating']:$vvalue['overall_rating'];
           $data['overall_rating'] = $vvalue['overall_rating'];
           $data['content'] = $vvalue['content'];
           $data['is_append'] = isset($vvalue['is_append'])?$vvalue['is_append']:0;
           $data['store_id'] = $order_info['store_id'];
           $data['order_number'] = $vvalue['order_number'];
           $data['complete_on'] = $order_info['complete_on'];
           $data['shipping_model'] = $product_data['shipping_model'];
           $data['parent_id'] = isset($vvalue['parent_id'])?$vvalue['parent_id']:0;

           $reviews_label = (isset($vvalue['reviews_label']) && !empty($vvalue['reviews_label']))?array_filter($vvalue['reviews_label']):'';
           $LabelIds = array();
           if(!empty($reviews_label)){
               $redis_Review_array = explode("|",$redis_Review);
               foreach ($reviews_label as $key=>$value){
                   if(!empty($value)){
                         $label_result = $this->SensitiveWords($value,$redis_Review_array);//判断敏感词
                         if($label_result !==0){
                             return apiReturn(['code'=>'1001','msg'=>"Sensitive words:".$label_result]);
                         }
                   }
                   $addLabel['customer_id'] = $vvalue['customer_id'];
                   $addLabel['label'] = trim($value);
                   $addLabel['product_id'] = $vvalue['product_id'];
                   $LabelIds[] = model("LabelProductReviews")->addLabelProductReviews($addLabel);
               }
               if($LabelIds){
                   $data['reviews_label'] = implode(',',$LabelIds);
               }
           }
           /*默认直接通过审核*/
           $data['approved'] = isset($vvalue['approved'])?$vvalue['approved']:1;
           $data['approval_staff'] = isset($vvalue['approval_staff'])?$vvalue['approval_staff']:"system";
           $remoteUpload = $this->remoteUpload();
           $data['static_images'] = 0;
           if($remoteUpload['code'] == 200 && empty($vvalue['video'])){
               $data['static_images'] = count($remoteUpload['data']);
               /*评论文件类型*/
               $data['file_type'] = 1;//有图片文件
           }elseif($remoteUpload['code'] != 200 && !empty($vvalue['video'])){
               $data['static_videos'] = count($vvalue['video']);
               $data['file_type'] = 2;//有视频
           }elseif($remoteUpload['code'] == 200 && !empty($vvalue['video'])){
               $data['static_images'] = count($remoteUpload['data']);
               $data['static_videos'] = count($vvalue['video']);
               $data['file_type'] = 3;//两种都有
           }else{
               $data['file_type'] = 0;//没有文件
           }

           $data['shipping_model'] = isset($vvalue['shipping_model'])?$vvalue['shipping_model']:'';
           $data['add_time'] = time();
           $data['complete_on'] = time();
           $res = model("ProductReviews")->addReviews($data);
           if($res){
               if($remoteUpload['code'] == 200){//保存评论图片
                   foreach ($remoteUpload['data'] as $value){
                       $image['review_id'] = $res;
                       $image['type'] = 1;
                       $image['file_url'] = $value['url'];
                       $image['thumb_url'] = $value['url'];
                       $this->addReviewsFile($image);
                   }
                   if($data['is_append']){
                       model("ProductReviews")->incFileCount(['review_id'=>$data['parent_id']],"static_images",$data['static_images']);
                   }
               }
               if(isset($vvalue['video']) && !empty($vvalue['video'])){//保存评论图片
                   $video['review_id'] = $res;
                   $video['type'] = 2;
                   $video['file_url'] = $vvalue['video'];
                   $this->addReviewsFile($video);
                   if($data['is_append']){
                       model("ProductReviews")->incFileCount(['review_id'=>$data['parent_id']],"static_videos",$data['static_videos']);
                   }
               }
               $success_count++;
           }else{
               $error_count++;
           }
       }
       return apiReturn(['code'=>200,'data'=>['success_count'=>$success_count,'error_count'=>$error_count]]);
   }

   /*
    * 修改评论
    * */
   public function editReviews(){
       $paramData = request()->post();
       /*需要判断参数中是否存在的值*/
       $reviews_label = array_filter($paramData['reviews_label']);
       $LabelIds = array();
       if(!empty($reviews_label)){
           foreach ($reviews_label as $key=>$value){
               $addLabel['customer_id'] = $paramData['customer_id'];
               $addLabel['label'] = trim($value);
               $addLabel['product_id'] = $paramData['product_id'];
               $LabelIds[] = model("LabelProductReviews")->addLabelProductReviews($addLabel);
           }
       }
       if($LabelIds){
           $data['reviews_label'] = implode(',',$LabelIds);
       }
       $data['customer_id'] = $paramData['customer_id'];
       $data['overall_rating'] = $paramData['overall_rating'];
       $data['review_id'] = $paramData['review_id'];
       $data['content'] = $paramData['content'];
       $data['edit_time'] = time();
       $data = array_filter($data);
       $res = model("ProductReviews")->editReviews(['review_id'=>$paramData['review_id']],$data);
       if($res){
           if(isset($paramData['images']) && !empty($paramData['images'])){//保存评论图片
               model("ProductReviews")->delFile(['review_id'=>$paramData['review_id']],1);
               foreach ($paramData['images'] as $value){
                   $image['review_id'] = $paramData['review_id'];
                   $image['type'] = 1;
                   $image['file_url'] = $value;
                   $image['thumb_url'] = $value;
                   $this->addReviewsFile($image);
               }
           }
           if(isset($paramData['video']) && !empty($paramData['video'])){//保存评论图片
               model("ProductReviews")->delFile(['review_id'=>$paramData['review_id']],2);
               $video['review_id'] = $paramData['review_id'];
               $video['type'] = 2;
               $video['file_url'] = $paramData['video'];
               $this->addReviewsFile($video);
           }
           return apiReturn(['code'=>200]);
       }else{
           return apiReturn(['code'=>1002]);
       }
   }

   /*
    * 回复评论
    * */
   public function addReplyReviews(){
       $paramData = request()->post();
       $validate = $this->validate($paramData,"reviews.addReplyReviews");
       if(true !== $validate){
           return apiReturn(['code'=>1002,"msg"=>$validate]);
       }
       if(isset($paramData['review_id'])){
           $data['review_id'] = $paramData['review_id'];
       }else{
           return apiReturn(['code'=>'1001']);
       }
       if(isset($paramData['store_id'])){
           $data['store_id'] = $paramData['store_id'];
       }else{
           return apiReturn(['code'=>'1001']);
       }
       if(isset($paramData['store_name'])){
           $data['store_name'] = $paramData['store_name'];
       }else{
           return apiReturn(['code'=>'1001']);
       }
       if(isset($paramData['content'])){
           $data['content'] = $paramData['content'];
       }else{
           return apiReturn(['code'=>'1001']);
       }
       $reviews_data = model("ProductReviews")->getReviews(['review_id'=>$paramData['review_id']],0);
       if(!$reviews_data){
           return apiReturn(['code'=>'1002','msg'=>"Reviews does not exist"]);
       }
       $data['add_time'] = time();
       $res = model("ReplyReviews")->addReplyReviews($data);
       if($res){
           return apiReturn(['code'=>200, 'data'=>$data]);
       }else{
           return apiReturn(['code'=>1002]);
       }
   }

   /*
    * 添加评论文件
    * */
   public function addReviewsFile($paramData){
       if(isset($paramData['review_id'])){
           $data['review_id'] = $paramData['review_id'];
       }else{
           return apiReturn(['code'=>'1001']);
       }
       $data['type'] = isset($paramData['type'])?$paramData['type']:1;
       $data['thumb_url'] = isset($paramData['thumb_url'])?$paramData['thumb_url']:'';
       $data['file_url'] = isset($paramData['file_url'])?$paramData['file_url']:'';
       $res = model("ReviewsFile")->addReviewsFile($data);
       if($res){
           return apiReturn(['code'=>200, 'data'=>$data]);
       }else{
           return apiReturn(['code'=>1002]);
       }
   }

    /*
     * 点赞评论
     * */
    public function addReviewsRro(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"reviews.addReviewsRro");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $model = model('ReviewsPro');
        if(isset($paramData['review_id'])){
            $data['review_id'] = $paramData['review_id'];
        }else{
            return apiReturn(["code"=>'1001']);
        }
        if(isset($paramData['customer_id'])){
            $data['customer_id'] = $paramData['customer_id'];
        }else{
            return apiReturn(['code'=>'1001']);
        }
        if(isset($paramData['customer_name'])){
            $data['customer_name'] = $paramData['customer_name'];
        }
        $customer_data = model("cic/Customer")->getCustomer($paramData['customer_id']);
        if(!$customer_data){
            return apiReturn(['code'=>'1002','msg'=>"Customer does not exist"]);
        }
        $reviews_data = model("ProductReviews")->getReviews(['review_id'=>$paramData['review_id']],0);
        if(!$reviews_data){
            return apiReturn(['code'=>'1002','msg'=>"Reviews does not exist"]);
        }
        $is_pro_where['review_id'] = $data['review_id'];
        $is_pro_where['customer_id'] = $data['customer_id'];
        $is_pro = $model ->getIsPro($is_pro_where);
        if($is_pro){
            return apiReturn(['code'=>1301]);
        }
        $res = $model->addReviewsPro($data);
        if($res){
            model("ProductReviews")->setIncReviewsPro($paramData['review_id']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 评论列表
     */
    public function getReviewsList(){
        $paramData = request()->post();
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }
        /*订单ID*/
        if(isset($paramData['order_id'])){
            $where['order_id'] = ["in",$paramData['order_id']];
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $where['order_number'] = $paramData['order_number'];
        }
        if(isset($paramData['sku_id'])){
            $where['sku_id'] = $paramData['sku_id'];
        }
        if(isset($paramData['product_id'])){
            $where['product_id'] = $paramData['product_id'];
        }
        if(isset($paramData['store_id'])){
            $where['store_id'] = $paramData['store_id'];
        }
        if(isset($paramData['is_automatic'])){
            $where['is_automatic'] = $paramData['is_automatic'];
        }
        if(isset($paramData['content'])){
            $where['content'] = $paramData['content'];
        }
        if(isset($where['add_time']) && is_array($where['add_time'])){
            foreach ($where['add_time'] as $key=>$value){
                $where['add_time'][$key] = trim($value);
            }
        }
        if(isset($paramData['file_type'])){
            if($paramData['file_type'] == 1){
                $where['static_images'] = ['gt',0];
            }elseif($paramData['file_type'] == 2){
                $where['static_videos'] = ['gt',0];
            }
        }
        if(isset($paramData['parent_id'])){
            $where['parent_id'] = $paramData['parent_id'];
        }
        if(isset($paramData['content'])){
            if(is_array($where['content'])){
                $where['content'] = TrimArray($paramData['content']);
            }
        }
        $where['approved'] = 1;
        $where['is_append'] = 0;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $page_query = isset($paramData['page_query'])?$paramData['page_query']:'';
        $order = isset($paramData['order'])?$paramData['order']:"pro_number desc,review_id desc";
        $list = model("ProductReviews")->getReviewsList($where,$page_size,$page,$path,$order,$page_query);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

    /*
     * 删除评论
     * */
    public function deleteReviews(){
        $review_id = input("review_id");
        if(empty($review_id)){
            return apiReturn(['code'=>1001]);
        }
        $where['review_id'] = $review_id;
        $res = model("ProductReviews")->deleteReviews($where);
        if($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 统计评论
     * */
    public function getReviewsStatistics(){
        $paramData = request()->post();
        //todo 参数校验
        //return $paramData;
        if(isset($paramData['product_id'])){
            $where['product_id'] = $paramData['product_id'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        $data = model("ProductReviews")->getReviewsStatistics($where);
        return apiReturn(['code'=>200,'data'=>$data]);
    }

    /*
     * 获取评论标签
     * */
    public function getLabelProductReviews(){
        $paramData = request()->post();
        //todo 参数校验
        if(isset($paramData['product_id'])){
            $where['product_id'] = $paramData['product_id'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        $where['reviews_label'] = array('neq','');
        $ReviewsLabelId = model("ProductReviews")->getReviewsLabelId($where);
        $label_where['id'] =  ['in',$ReviewsLabelId];
        $data = model("LabelProductReviews")->getLabelProductReviews($label_where);
        return apiReturn(['code'=>200,'data'=>$data]);
    }

    /*
     * 添加评论标签
     * */
    public function addLabelProductReviews(){
        $paramData = request()->post();
        //todo 参数校验
        if(isset($paramData['product_id'])){
            $data['product_id'] = $paramData['product_id'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        if(isset($paramData['label'])){
            $data['label'] = $paramData['label'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        $data['customer_id'] = isset($paramData['customer_id'])?$paramData['customer_id']:'';
        $data['add_time'] = time();
        $res =model("LabelProductReviews")->addLabelProductReviews($data);
        if($res){
            return apiReturn(['code'=>200, 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 获取评论
     * */
    public function getProductReviews(){
        $paramData = request()->post();
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }
        /*订单ID*/
        if(isset($paramData['order_id'])){
            $where['order_id'] = ["in",$paramData['order_id']];
        }
        if(isset($paramData['sku_id'])){
            $where['sku_id'] = $paramData['sku_id'];
        }
        if(isset($paramData['is_append'])){
            $where['is_append'] = $paramData['is_append'];
        }
        $Reviews = model("ProductReviews")->getReviews($where);
        return apiReturn(['code'=>200,'data'=>$Reviews]);
    }

    /*
     * 获取单条评论
     * */
    public function getOneProductReviews(){
        $paramData = request()->post();
        //todo 参数校验
        //return $paramData;
        if(isset($paramData['review_id'])){
            $where['review_id'] = $paramData['review_id'];
        }
        $Reviews = model("ProductReviews")->getOneProductReviews($where);
        return apiReturn(['code'=>200,'data'=>$Reviews]);
    }
     /**
     * 敏感词检查
     */
    public function SensitiveWords($content='',$arr=array()){
        if(empty($content) && empty($arr)){
           return 0;
        }
        $num = 0;
        for($i = 0; $i < count($arr); $i ++) {
            if(!empty($arr[$i])){
                //计算子串在字符串中出现的次数
                if (substr_count ($content, $arr[$i]) > 0) {
                   return $arr[$i];
                }
            }
        }
        return 0;
    }

    /*
        * 远程上传
        * */
    public function remoteUpload(){
        $localres = localUpload("static_images",2);
        if($localres['code']==200){
            foreach ($localres['data'] as $key=>$value){
                $remotePath = config("ftp_config.UPLOAD_DIR")['REVIEWS_IMAGES'].date("Ymd");
                $config = [
                    'dirPath'=>$remotePath, // ftp保存目录
                    'romote_file'=>$value['FileName'], // 保存文件的名称
                    'local_file'=>$value['file_url'], // 要上传的文件
                ];
                $ftp = new FTPUpload();
                $upload = $ftp->data_put($config);
                $baseurl =config('cdn_url_config.url');
                if($upload){
                    unlink($localres['data'][$key]['file_url']);
                    $res['data'][$key]['complete_url'] = $baseurl.$remotePath.'/'.$value['FileName'];
                    $res['data'][$key]['url'] = $remotePath.'/'.$value['FileName'];
                }
                if(!empty($res['data'])){
                    $res['code'] = 200;
                    $res['msg'] = "Success";
                } else {
                    $res['code'] = 100;
                    $res['msg'] = "Remote Upload Fail";
                }
            }
            return $res;
        }else{
            return $localres;
        }
    }

    /*
    * 添加商品评论
    * */
    public function addReviewsV2(){
        $redis_Review = ReviewFiltering();
        $paramData = request()->post();
        /*需要判断参数中是否存在的值*/
        $success_count = 0;
        $error_count = 0;
        $Review_content = '';
        /*判断如果是单个评论则将评论值转为多维数组*/
        if(isset($paramData['customer_id'])){
            $tag_paramData = $paramData;
            unset($paramData);
            $paramData[0] = $tag_paramData;
        }

        foreach ($paramData as $kkey=>$vvalue){
            $data = [];
            $verification = ['customer_id','order_number','overall_rating','content'];
            /*判断参数中是否存在，不存在返回错误*/
            foreach ($verification as $value){
                if(isset($vvalue[$value])){
                    $data[$value] = $vvalue[$value];
                }else{
                    $data[$value] = '';
                    return apiReturn(['code'=>'1001','msg'=>"Lack of parameters:".$value]);
                }
            }
            /*查询数据库是否存在 20190529 kevin*/
            $customer_id = $vvalue['customer_id'];
            $post['ID']=$customer_id;
            $baseApi = new BaseApi();
            $customer_datas = $baseApi->getCustomerByID($post);

            if(empty($customer_datas) || empty($customer_datas['data']['UserName'])){
                return apiReturn(['code'=>'1001','msg'=>"Customer does not exist"]);
            }else{
                $customer_data=$customer_datas['data'];
            }
            $vvalue['customer_name'] = $customer_data['UserName'];
            /*查询订单基本信息 20190529 kevin*/
            $order_info = model("orderfrontend/OrderModel")->getBasicOrderInfo(['order_number'=>$vvalue['order_number'],'customer_id'=>$vvalue['customer_id']]);
            if(empty($order_info)){
                return apiReturn(['code'=>'1001','msg'=>"Order does not exist"]);
            }
            $validate = $this->validate($vvalue,"reviews.addReviews");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            //判断是否有特殊词语
            $Review_content = '';
            if($Review_content = preg_replace('/'.$redis_Review.'/i', '***', $data['content'])){
                if(strpos($Review_content,'***') ===false){

                }else{
                    $data['raw_content'] = $data['content'];
                    $data['content'] = $Review_content;
                }
            }
            if((!isset($vvalue['product_id']) || empty($vvalue['product_id'])) && ((!isset($vvalue['sku_id']) || empty($vvalue['sku_id'])))){
                return apiReturn(['code'=>'1001','msg'=>"product_id or sku_id One can't be empty"]);
            }
            $product_data = array();
            if(!empty($order_info['item'])){
                foreach ($order_info['item'] as $order_goods){
                    if($vvalue['sku_id'] == $order_goods['sku_id']){
                        $product_data = $order_goods;
                        break;
                    }
                }
            }
            if(empty($product_data)){
                return apiReturn(['code'=>'1001','msg'=>"Product does not exist"]);
            }

            /*拼装评论数据*/
            $data['customer_id'] = $vvalue['customer_id'];
            $data['customer_name'] = $customer_data['UserName'];
            $data['country_code'] = $order_info['country_code'];
            $data['order_id'] = $order_info['order_id'];
            $data['product_id'] = $product_data['product_id'];
            $data['sku_id'] = $product_data['sku_id'];
            $data['product_attr_ids'] = $product_data['product_attr_ids'];
            $data['product_attr_desc'] = $product_data['product_attr_desc'];
            $data['price_rating'] = !empty($vvalue['price_rating'])?$vvalue['price_rating']:$vvalue['overall_rating'];
            $data['ease_of_use_rating'] = !empty($vvalue['ease_of_use_rating'])?$vvalue['ease_of_use_rating']:$vvalue['overall_rating'];
            $data['build_quality_rating'] = !empty($vvalue['build_quality_rating'])?$vvalue['build_quality_rating']:$vvalue['overall_rating'];
            $data['usefulness_rating'] = !empty($vvalue['usefulness_rating'])?$vvalue['usefulness_rating']:$vvalue['overall_rating'];
            $data['overall_rating'] = $vvalue['overall_rating'];
            $data['content'] = $vvalue['content'];
            $data['is_append'] = isset($vvalue['is_append'])?$vvalue['is_append']:0;
            $data['store_id'] = $order_info['store_id'];
            $data['order_number'] = $vvalue['order_number'];
            $data['complete_on'] = $order_info['complete_on'];
            $data['shipping_model'] = $product_data['shipping_model'];
            $data['parent_id'] = isset($vvalue['parent_id'])?$vvalue['parent_id']:0;

            $reviews_label = (isset($vvalue['reviews_label']) && !empty($vvalue['reviews_label']))?array_filter($vvalue['reviews_label']):'';
            $LabelIds = array();
            if(!empty($reviews_label)){
                $redis_Review_array = explode("|",$redis_Review);
                foreach ($reviews_label as $key=>$value){
                    if(!empty($value)){
                        $label_result = $this->SensitiveWords($value,$redis_Review_array);//判断敏感词
                        if($label_result !==0){
                            return apiReturn(['code'=>'1001','msg'=>"Sensitive words:".$label_result]);
                        }
                    }
                    $addLabel['customer_id'] = $vvalue['customer_id'];
                    $addLabel['label'] = trim($value);
                    $addLabel['product_id'] = $vvalue['product_id'];
                    $LabelIds[] = model("LabelProductReviews")->addLabelProductReviews($addLabel);
                }
                if($LabelIds){
                    $data['reviews_label'] = implode(',',$LabelIds);
                }
            }
            /*默认直接通过审核*/
            $data['approved'] = isset($vvalue['approved'])?$vvalue['approved']:1;
            $data['approval_staff'] = isset($vvalue['approval_staff'])?$vvalue['approval_staff']:"system";

            if(!empty($vvalue['reviews_file'])){
                $remoteUpload['code'] = 200;
                $remoteUpload['data']  = $vvalue['reviews_file'];
            }else{
                $remoteUpload['code'] = 1;
                $remoteUpload['data']  = '';
            }

            $data['static_images'] = 0;
            if($remoteUpload['code'] == 200 && empty($vvalue['video'])){
                $data['static_images'] = count($remoteUpload['data']);
                /*评论文件类型*/
                $data['file_type'] = 1;//有图片文件
            }elseif($remoteUpload['code'] != 200 && !empty($vvalue['video'])){
                $data['static_videos'] = count($vvalue['video']);
                $data['file_type'] = 2;//有视频
            }elseif($remoteUpload['code'] == 200 && !empty($vvalue['video'])){
                $data['static_images'] = count($remoteUpload['data']);
                $data['static_videos'] = count($vvalue['video']);
                $data['file_type'] = 3;//两种都有
            }else{
                $data['file_type'] = 0;//没有文件
            }

            $data['shipping_model'] = isset($vvalue['shipping_model'])?$vvalue['shipping_model']:'';
            $data['add_time'] = time();
            $data['complete_on'] = time();
            $res = model("ProductReviews")->addReviews($data);
            if($res){
                if($remoteUpload['code'] == 200){//保存评论图片
                    foreach ($remoteUpload['data'] as $value){
                        $image['review_id'] = $res;
                        $image['type'] = 1;
                        $image['file_url'] = $value;
                        $image['thumb_url'] = $value;
                        $this->addReviewsFile($image);
                    }
                    if($data['is_append']){
                        model("ProductReviews")->incFileCount(['review_id'=>$data['parent_id']],"static_images",$data['static_images']);
                    }
                }
                if(isset($vvalue['video']) && !empty($vvalue['video'])){//保存评论图片
                    $video['review_id'] = $res;
                    $video['type'] = 2;
                    $video['file_url'] = $vvalue['video'];
                    $this->addReviewsFile($video);
                    if($data['is_append']){
                        model("ProductReviews")->incFileCount(['review_id'=>$data['parent_id']],"static_videos",$data['static_videos']);
                    }
                }
                $success_count++;
            }else{
                $error_count++;
            }
        }
        return apiReturn(['code'=>200,'data'=>['success_count'=>$success_count,'error_count'=>$error_count]]);
    }
}
