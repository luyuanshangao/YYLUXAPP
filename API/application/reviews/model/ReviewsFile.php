<?php
namespace app\reviews\model;
use think\Model;
use think\Db;
/**
 * 模型文件
 * @author
 * @version Kevin 2018/4/27
 */
class ReviewsFile extends Model{
    protected $table = 'dx_reviews_file';
    protected $table_videos = 'dx_reviews_file_videos';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_reviews');
    }
    /*
    * 保存评论图片
    * */
    public function addReviewsFile($data){
        if($data['type'] == 1){
            return $this->db->table($this->table)->insertGetId($data);
        }else{
            return $this->db->table($this->table_videos)->insertGetId($data);
        }
    }

}