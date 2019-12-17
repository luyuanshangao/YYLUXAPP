<?php
namespace app\reviews\controller;
use app\common\controller\Base;
use app\common\controller\BaseApi;
use app\common\helpers\CommonLib;
use think\Exception;
use think\Log;

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
       if(isset($paramData['customer_id'])){
           $tag_paramData = $paramData;
           unset($paramData);
           $paramData[0] = $tag_paramData;
       }
       foreach ($paramData as $kkey=>$vvalue){
           $data = [];
           $verification = ['customer_id','customer_name','store_id','order_id','price_rating','ease_of_use_rating','build_quality_rating','usefulness_rating','overall_rating','content','static_images','static_videos'];
           /*判断参数中是否存在，不存在返回错误*/
           foreach ($verification as $value){
               if(isset($vvalue[$value])){
                   $data[$value] = $vvalue[$value];
               }else{
                   $data[$value] = '';
                   return apiReturn(['code'=>'1001','msg'=>"Lack of parameters:".$value]);
               }
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
           if((!isset($vvalue['product_id']) || empty($vvalue['product_id'])) && empty($vvalue['sku_id']) && empty($vvalue['sku_num'])){
               return apiReturn(['code'=>'1001','msg'=>"product_id or sku One can't be empty"]);
           }
            if(!empty($vvalue['sku_id']) || !empty($vvalue['sku_num'])){
               if(!empty($vvalue['sku_num'])){
                   $sku_where['Skus.Code'] = $vvalue['sku_num'];
               }else{
                   $sku_where['Skus._id'] = (int)$vvalue['sku_id'];
               }
                $product_data = model("mallextend/ProductModel")->getProductInField($sku_where,"_id,StoreID,Skus");
                if(empty($product_data) && !empty($product_data['Skus'])){
                    return apiReturn(['code'=>'1001','msg'=>"sku_id does not exist"]);
                }else{
                    $data['product_id'] = $product_data['_id'];
                    $vvalue['product_id'] = $product_data['_id'];
                    $data['store_id'] = $product_data['StoreID'];
                    $vvalue['store_id'] = $product_data['StoreID'];
                    if(!empty($product_data['Skus'])){
                        foreach ($product_data['Skus'] as $skuk=>$skuv){
                            if((!empty($vvalue['sku_id']) && $vvalue['sku_id'] == $skuv['_id']) || (!empty($vvalue['sku_num']) && $vvalue['sku_num'] == $skuv['Code'])){
                                $data['sku_id'] = $skuv['_id'];
                                $vvalue['sku_id'] = $skuv['_id'];
                                $data['sku_num'] = $skuv['Code'];
                                $vvalue['sku_num'] = $skuv['Code'];
                                break;
                            }
                        }
                    }else{
                        Log::record('addReviews1'.json_encode($paramData),'error');
                    }

                }
            }else{
                $product_data = model("mallextend/ProductModel")->getProduct(['product_id'=>$vvalue['product_id'],'field'=>"_id"]);
                if(!$product_data){
                    return apiReturn(['code'=>'1001','msg'=>"product does not exist"]);
                }
                $data['product_id'] = $vvalue['product_id'];
            }

           if(isset($vvalue['sku_id']) && !empty($vvalue['sku_id'])){
               $data['sku_id'] = $vvalue['sku_id'];
           }
           if(isset($vvalue['country_code']) && !empty($vvalue['country_code'])){
               $data['country_code'] = $vvalue['country_code'];
           }
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
           $data['order_number'] = isset($vvalue['order_number'])?$vvalue['order_number']:'';
           /*默认直接通过审核,但是当评论为一星或二星则默认不审核中*/
           if(!empty($data['overall_rating']) && ($data['overall_rating'] == 1 || $data['overall_rating'] == 2)){
               $data['approved'] = 2;
           }else{
               $data['approved'] = isset($vvalue['approved'])?$vvalue['approved']:1;
           }
           $data['parent_id'] = isset($vvalue['parent_id'])?$vvalue['parent_id']:0;
           $data['approval_staff'] = isset($vvalue['approval_staff'])?$vvalue['approval_staff']:"system";
           /*是否追评*/
           $data['is_append'] = isset($vvalue['is_append'])?$vvalue['is_append']:0;
           /*评论文件类型*/
           $data['file_type'] = isset($vvalue['file_type'])?$vvalue['file_type']:0;
           $data['shipping_model'] = isset($vvalue['shipping_model'])?$vvalue['shipping_model']:'';
           $data['add_time'] = !empty($vvalue['add_time'])?$vvalue['add_time']:time();
           $data['complete_on'] = time();
           $res = model("ProductReviews")->addReviews($data);
           if($res){
               if(isset($vvalue['images']) && !empty($vvalue['images'])){//保存评论图片
                   foreach ($vvalue['images'] as $value){
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

        //获取用户数据改用接口的形式获取，因为CIC独立出来后，API不能直接访问CIC数据库 tinghu.liu 20190727
        $customer_data_res = (new BaseApi())->getCustomerByID(['ID'=>$paramData['customer_id']]);
        $customer_data = isset($customer_data_res['data'])?$customer_data_res['data']:[];
//        $customer_data = model("cic/Customer")->getCustomer($paramData['customer_id']);
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
        //是否要展示人工评论
        if(isset($paramData['review_source'])){
            $where['review_source'] = $paramData['review_source'];
        }
        $where['approved'] = 1;
        $where['is_append'] = 0;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $page_query = isset($paramData['page_query'])?$paramData['page_query']:'';
        $order = isset($paramData['order'])?$paramData['order']:"pro_number desc,add_time desc";
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
     * 后台回去评论信息
     * add 20190416 kevin
     * */
    public function getAdminReview(){
        $paramData = request()->post();
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        $where = array();
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }
        if(isset($paramData['customer_name'])){
            $where['customer_name'] = $paramData['customer_name'];
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
        if(isset($paramData['overall_rating'])){
            $where['overall_rating'] = $paramData['overall_rating'];
        }
        if(isset($paramData['approved'])){
            $where['approved'] = $paramData['approved'];
        }
        if(isset($paramData['add_time'])){
            $where['add_time'] = $paramData['add_time'];
            if(is_array($paramData['add_time'])){
                $where['add_time'][0] = trim($paramData['add_time'][0]);
            }
        }
        if(isset($where['sku_num'])){
            $where['sku_num'] = $paramData['sku_num'];
        }
        //后台展示过滤人工添加的评论 --add by zhongning 20190522
        $where['review_source'] = 1;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $page_query = isset($paramData['page_query'])?$paramData['page_query']:'';
        $order = isset($paramData['order'])?$paramData['order']:"review_id desc";
        $list = model("ProductReviews")->getReviewsList($where,$page_size,$page,$path,$order,$page_query);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

    /*
     *更改评论审核状态
     * add 20190417
     * */
    public function updateReviewStatus(){
        try{
            $paramData = request()->post();
            //todo 参数校验
            $validate = $this->validate($paramData,"reviews.updateReviewStatus");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where = array();
            if(isset($paramData['review_id'])){
                $where['review_id'] = ['in',$paramData['review_id']];
                $update_data['approved'] = isset($paramData['approved'])?$paramData['approved']:0;
                $update_data['approval_staff'] = isset($paramData['approval_staff'])?$paramData['approval_staff']:'';
                $res = model("ProductReviews")->updateReviewStatus($where,$update_data);
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>200,'msg'=>$e->getMessage()]);
        }
    }

    public function addProductReviews(){
        $redis_Review = ReviewFiltering();
        $paramData = request()->post();

        //校验参数
        $validate = $this->validate($paramData,"reviews.addProductReviews");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $data = array();

        //验证用户ID
        if(!empty($paramData['customer_id'])){

            //获取用户数据改用接口的形式获取，因为CIC独立出来后，API不能直接访问CIC数据库 tinghu.liu 20190727
            $customer_data_res = (new BaseApi())->getCustomerByID(['ID'=>$paramData['customer_id']]);
            $customerInfo = isset($customer_data_res['data'])?$customer_data_res['data']:[];
//            $customerInfo = model("cic/Customer")->getCustomer($paramData['customer_id']);
            if(empty($customerInfo)){
                return apiReturn(['code'=>'1001','msg'=>$paramData['customer_id'] . " User does not exist"]);
            }else{
                $data['customer_name'] = !empty($customerInfo['UserName']) ? $customerInfo['UserName'] : '';
            }
        }
        $data['customer_id'] = $paramData['customer_id'];
        $Review_content = preg_replace('/'.$redis_Review.'/i', '***', $paramData['content']);
        //过滤特殊字符
        $data['raw_content'] = $paramData['content'];
        $data['content'] = $Review_content;

        //判断产品是否存在
        $product_data = model("mallextend/ProductModel")->getProduct(['product_id'=>$paramData['product_id'],'field'=>"StoreID,_id,Skus._id,Skus.SalesAttrs,Skus.Code"]);
        if(!$product_data){
            return apiReturn(['code'=>'1001','msg'=>"product does not exist"]);
        }
        $data['product_id'] = $paramData['product_id'];
        $data['sku_id'] = $paramData['sku_id'];
        $data['store_id'] = $product_data['StoreID'];
        //查找属性
        $skuInfo = CommonLib::filterArrayByKey($product_data['Skus'],'_id',$paramData['sku_id'],'Code',$paramData['sku_id']);
        if(!empty($skuInfo['SalesAttrs'])){
            $attr_string = '';
            foreach($skuInfo['SalesAttrs'] as $saleAttr){
                if(empty($saleAttr['CustomValue'])){
                    $name = isset($saleAttr['DefaultValue']) && !empty($saleAttr['DefaultValue']) ? $saleAttr['DefaultValue'] : $saleAttr['Value'];
                }else{
                    $name = $saleAttr['CustomValue'];
                }
                $attr_string = $attr_string.' '.$saleAttr['Name']." : ".$name;
            }
            //产品属性描述组
            $data['product_attr_desc'] = $attr_string;
        }
        $data['country_code'] = !empty($paramData['country_code']) ?  $paramData['country_code'] : '';
        $reviews_label = (isset($paramData['reviews_label']) && !empty($paramData['reviews_label']))? array_filter($paramData['reviews_label']):'';
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
                $addLabel['customer_id'] = $paramData['customer_id'];
                $addLabel['label'] = trim($value);
                $addLabel['product_id'] = $paramData['product_id'];
                $LabelIds[] = model("LabelProductReviews")->addLabelProductReviews($addLabel);
            }
            if($LabelIds){
                $data['reviews_label'] = implode(',',$LabelIds);
            }
        }
        $data['price_rating'] = $paramData['price_rating'];
        $data['ease_of_use_rating'] = $paramData['ease_of_use_rating'];
        $data['build_quality_rating'] = $paramData['build_quality_rating'];
        $data['usefulness_rating'] = $paramData['usefulness_rating'];
        $data['overall_rating'] = $paramData['overall_rating'];

        /*默认直接通过审核,但是当评论为一星或二星则默认不审核中*/
        if(!empty($data['overall_rating']) && ($data['overall_rating'] == 1 || $data['overall_rating'] == 2)){
            $data['approved'] = 2;
        }else{
            $data['approved'] = isset($paramData['approved'])?$paramData['approved']:1;
        }
        $data['approval_staff'] = "heyuan"; //操作人
        /*评论文件类型*/
        $data['file_type'] = !empty($paramData['reviews_file'])? 1 : 0; //暂时提供图片
        //图片个数
        $data['static_images'] = !empty($paramData['reviews_file'])? count($paramData['reviews_file']) : 0; //暂时提供图片

        $data['add_time'] = !empty($paramData['review_addtime'])? $paramData['review_addtime'] : time();
        $data['review_source'] = 2;
        $res = model("ProductReviews")->addReviews($data);
        if($res && !empty($paramData['reviews_file'])){//保存评论图片
            foreach ($paramData['reviews_file'] as $value){
                $image['review_id'] = $res;
                $image['type'] = 1;
                $image['file_url'] = $value;
                $image['thumb_url'] = $value;
                $this->addReviewsFile($image);
            }
        }
        if($res){
            return apiReturn(['code'=>200]);
        }
        return apiReturn(['code'=>1001,'msg'=>"add reviews failed"]);
    }

}
