<?php
namespace app\reviews\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 商品评论模型
 * @author
 * @version Kevin 2018/4/27
 */
class ProductReviews extends Model{
    protected $produc_reviews = 'dx_product_reviews';
    protected $reviews_file = 'dx_reviews_file';
    protected $reviews_videos = 'dx_reviews_file_videos';
    protected $label_product_reviews = 'dx_label_product_reviews';
    protected $dx_reply_reviews = 'dx_reply_reviews';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_reviews');
    }
    /*
    * 添加评论
    * */
    public function addReviews($data){
       return $this->db->table($this->produc_reviews)->insertGetId($data);
    }

    /*
   * 增加点赞数
   * */
    public function setIncReviewsPro($review_id){
        $where['review_id'] = $review_id;
        return $this->db->table($this->produc_reviews)->where($where)->setInc("pro_number");
    }

    /**
     * 评论查询处理方法
     * 查询评论
     * @param array
     */
    public function getReviewsList($where,$page_size=20,$page=1,$path='',$order='',$page_query=''){
        $res = $this->db->table($this->produc_reviews)
            ->where($where)
            ->field("review_id,customer_id,customer_name,country_code,order_id,product_id,sku_id,sku_num,product_attr_ids,product_attr_desc,price_rating,ease_of_use_rating,build_quality_rating,usefulness_rating,overall_rating,pro_number,approved,reviews_label,content,is_append,add_time,order_number,complete_on,shipping_model,static_images,static_videos")
            ->order($order)
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query?$page_query:$where]);
        $Page = $res->render();
        $data = $res->toArray();
        if($data['data']){
            foreach ($data['data'] as $key=>$value){
                $file_where['review_id'] = $value['review_id'];
                $reply_where['review_id'] = $value['review_id'];
                if($value['static_images']>0){
                    $data['data'][$key]['images'] = $this->db->table($this->reviews_file)->where($file_where)->select();
                }
                if($value['static_videos']>0){
                    $data['data'][$key]['videos'] = $this->db->table($this->reviews_videos)->where($file_where)->select();
                    if(!empty($data['data'][$key]['videos'])){
                        foreach ($data['data'][$key]['videos'] as $valuev){
                            $data['data'][$key]['videos']['file_url'] = "https://www.youtube.com/embed/".$valuev['file_url'];
                            $data['data'][$key]['videos']['thumb_url'] = "http://img.youtube.com/vi/".$valuev['file_url']."/3.jpg";
                        }
                    }
                }
                $data['data'][$key]['file'] = $this->db->table($this->reviews_file)->where($file_where)->field("file_id,thumb_url,file_url")->select();
                $data['data'][$key]['reply'] = $this->db->table($this->dx_reply_reviews)->where($reply_where)->find();
                $reply_review = config('reply_review_limit_day')*24*60*60;
                $data['data'][$key]['reply_surplus_time'] = ($reply_review + $value['add_time']) - time();
                $append_where['parent_id'] = $value['review_id'];
                //追评也需要审核通过，add by zhongning 20190521
                $append_where['approved'] = 1;
                /*追评*/
                $data['data'][$key]['append_review'] = $this->db->table($this->produc_reviews)
                    ->where($append_where)
                    ->field("review_id,customer_id,customer_name,country_code,order_id,product_id,sku_id,sku_num,product_attr_ids,product_attr_desc,price_rating,ease_of_use_rating,build_quality_rating,usefulness_rating,overall_rating,pro_number,approved,reviews_label,content,is_append,add_time,order_number,complete_on,shipping_model,static_images,static_videos")
                    ->find();
                if($data['data'][$key]['append_review']){
                    $append_file_where['review_id'] = $data['data'][$key]['append_review']['review_id'];
                    $append_reply_where['review_id'] = $data['data'][$key]['append_review']['review_id'];
                    $data['data'][$key]['append_review']['file'] = $this->db->table($this->reviews_file)->where($append_file_where)->field("file_id,thumb_url,file_url")->select();
                    $data['data'][$key]['append_review']['reply'] = $this->db->table($this->dx_reply_reviews)->where($append_reply_where)->find();
                    if($data['data'][$key]['append_review']['static_images']>0){
                            $data['data'][$key]['append_review']['images'] = $this->db->table($this->reviews_file)->where($append_file_where)->select();
                        }
                    if($data['data'][$key]['append_review']>0){
                        $data['data'][$key]['append_review']['videos'] = $this->db->table($this->reviews_videos)->where($append_file_where)->select();
                        if(!empty($data['data'][$key]['append_review']['videos'])){
                            foreach ($data['data'][$key]['append_review']['videos'] as $valuevv){
                                $data['data'][$key]['append_review']['videos']['file_url'] = "https://www.youtube.com/embed/".$valuevv['file_url'];
                                $data['data'][$key]['append_review']['videos']['thumb_url'] = "http://img.youtube.com/vi/".$valuevv['file_url']."/3.jpg";
                            }
                        }
                    }
                }
            }
        }
        //有图片数量
        $photos_where = $where;
        if(isset($where['static_videos'])){
            unset($photos_where['static_videos']);
        }
        $photos_where['static_images'] = ['gt',0];
        $data['photos_count'] = $this->db->table($this->produc_reviews)->where($photos_where)->count("review_id");
        //有视频数量
        $videos_where = $where;
        if(isset($where['static_images'])){
            unset($videos_where['static_images']);
        }
        $videos_where['static_videos'] = ['gt',0];
        $data['videos_count'] = $this->db->table($this->produc_reviews)->where($videos_where)->count("review_id");
        //全部数量
        if (isset($where['product_id'])){
            $data['all_count'] = $this->db->table($this->produc_reviews)->where(['parent_id'=>0,'product_id'=>$where['product_id']])->count("review_id");
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 统计评论
     * */
    public function getReviewsStatistics($where){
        $data = array();
        for($i=1;$i<=5;$i++){
            $whererating = $where;
            $whererating['overall_rating'] = $i;
            $data['overall_rating_count'][$i] = $this->db->table($this->produc_reviews)->where($whererating)->count("review_id");
        }
        $overall_avg = $this->db->table($this->produc_reviews)->where($where)->avg("overall_rating");
        $data['overall_avg'] = sprintf("%.2f",$overall_avg);
        $data['reviews_label'] =  $this->db->table($this->label_product_reviews)->where($where)->column("label");
        $data['all_count'] = $this->db->table($this->produc_reviews)->where($where)->count("review_id");
        $photos_where = $where;
        $photos_where['file_type'] = ['in','1,3'];
        $data['photos_count'] = $this->db->table($this->produc_reviews)->where($photos_where)->count("review_id");
        $videos_where = $where;
        $videos_where['file_type'] = ['in','2,3'];
        $data['videos_count'] = $this->db->table($this->produc_reviews)->where($videos_where)->count("review_id");
        //$data['reviews_label'] = "goods,very goods";
        return $data;
    }

    /*
     * 获取评论标签ID集合
     * */
    public function getReviewsLabelId($where){
        $label = $this->db->table($this->produc_reviews)->where($where)->column("reviews_label");
        $label_str = implode(",",$label);
        $label_array = array_keys(array_flip (explode(",",$label_str)));
        return $label_array;
    }

    /*
     * 上级评论
     * */
    public function getReviews($where,$is_get_static = 1){
        $Reviews = $this->db->table($this->produc_reviews)->where($where)->group("product_id")->select();
        if($is_get_static){
            if($Reviews){
                foreach ($Reviews as $key=>$value){
                    $file_where['review_id'] = $value['review_id'];
                    if($value['static_images']>0){
                        $Reviews[$key]['images'] = $this->db->table($this->reviews_file)->where($file_where)->select();
                    }
                    if($value['static_images']>0){
                        $Reviews[$key]['videos'] = $this->db->table($this->reviews_videos)->where($file_where)->select();
                    }
                }
            }
        }
        return $Reviews;
    }

    /*
     * 删除评论
     * */
    public function deleteReviews($where){
        return $this->db->table($this->produc_reviews)->where($where)->delete();
    }

    /*
     * 获取
     * */
    public function getOneProductReviews($where){
        $res = $this->db->table($this->produc_reviews)
            ->where($where)
            ->field("review_id,customer_id,customer_name,country_code,order_id,product_id,sku_id,sku_num,product_attr_ids,product_attr_desc,price_rating,ease_of_use_rating,build_quality_rating,usefulness_rating,overall_rating,pro_number,approved,reviews_label,content,is_append,add_time,order_number,complete_on,shipping_model,static_images,static_videos")
            ->find();
        if($res){
            $file_where['review_id'] = $res['review_id'];
            $reply_where['review_id'] = $res['review_id'];
            if($res['static_images']>0){
                $res['images'] = $this->db->table($this->reviews_file)->where($file_where)->select();
            }
            if($res['static_videos']>0){
                $res['videos'] = $this->db->table($this->reviews_videos)->where($file_where)->select();
            }
            $res['file'] = $this->db->table($this->reviews_file)->where($file_where)->field("file_id,thumb_url,file_url")->select();
            $res['reply'] = $this->db->table($this->dx_reply_reviews)->where($reply_where)->find();
            $reply_review = config('reply_review_limit_day')*24*60*60;
            $res['reply_surplus_time'] = ($reply_review + $res['add_time']) - time();
            $append_where['parent_id'] = $res['review_id'];
            /*追评*/
            $res['append_review'] = $this->db->table($this->produc_reviews)
                ->where($append_where)
                ->field("review_id,customer_id,customer_name,country_code,order_id,product_id,sku_id,sku_num,product_attr_ids,product_attr_desc,price_rating,ease_of_use_rating,build_quality_rating,usefulness_rating,overall_rating,pro_number,approved,reviews_label,content,is_append,add_time,order_number,complete_on,shipping_model")
                ->find();
            if($res['append_review']){
                $append_file_where['review_id'] = $res['append_review']['review_id'];
                $append_reply_where['review_id'] = $res['append_review']['review_id'];
                $res['append_review']['file'] = $this->db->table($this->reviews_file)->where($append_file_where)->field("file_id,thumb_url,file_url")->select();
                $res['append_review']['reply'] = $this->db->table($this->dx_reply_reviews)->where($append_reply_where)->find();
            }
        }
        //有图片数量
        $photos_where = $where;
        if(isset($where['static_videos'])){
            unset($photos_where['static_videos']);
        }
        $photos_where['static_images'] = ['gt',0];
        $res['photos_count'] = $this->db->table($this->produc_reviews)->where($photos_where)->count("review_id");
        //有视频数量
        $videos_where = $where;
        if(isset($where['static_images'])){
            unset($videos_where['static_images']);
        }
        $videos_where['static_videos'] = ['gt',0];
        $res['videos_count'] = $this->db->table($this->produc_reviews)->where($videos_where)->count("review_id");
        //全部数量
        if (isset($where['product_id'])){
            $res['all_count'] = $this->db->table($this->produc_reviews)->where(['parent_id'=>0,'product_id'=>$where['product_id']])->count("review_id");
        }
        return $res;
    }

    /*
     * 更改评论
     * */
    public function editReviews($where,$data){
        $res = $this->db->table($this->produc_reviews)->where($where)->update($data);
        return $res;
    }

    /*
     * 增加评论图片或视频数量
     * type 1 图片，2 视频
     * */
    public function incFileCount($where,$field,$incNumber){
        $res = $this->db->table($this->produc_reviews)->where($where)->setInc($field,$incNumber);
        return $res;
    }

    /*
     * 判断文件是否存在
     * */
    public function checkFile($where,$type=1){
        if($type == 1){
            return $this->db->table($this->reviews_file)->where($where)->count();
        }else{
            return $this->db->table($this->reviews_videos)->where($where)->count();
        }
    }

    /*
     * 删除文件
     * */
    public function delFile($where,$type=1){
        if($type == 1){
            return $this->db->table($this->reviews_file)->where($where)->delete();
        }else{
            return $this->db->table($this->reviews_videos)->where($where)->delete();
        }
    }

    /*
     * 修改评论信息
     * add 20190417
     * */
    public function updateReviewStatus($where,$update_data){
        $update_data['edit_time'] = time();
        $res = $this->db->table($this->produc_reviews)->where($where)->update($update_data);
        return $res;
    }
}