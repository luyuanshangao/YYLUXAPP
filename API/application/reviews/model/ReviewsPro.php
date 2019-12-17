<?php
namespace app\reviews\model;
use think\Model;
use think\Db;
/**
 * 模型
 * @author
 * @version Kevin 2018/4/27
 */
class ReviewsPro extends Model{
    protected $table = 'dx_reviews_pro';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_reviews');
    }
    /*
    *
    * */
    public function addReviewsPro($data){
       return $this->db->table($this->table)->insertGetId($data);
    }

    /*
     * 获取当前用户是否点赞
     * */
    public function getIsPro($where){
        $count = $this->db->table($this->table)->where($where)->count("pro_id");
        if($count>0){
            return true;
        }else{
            return false;
        }
    }

    public function getReviewsProCount($where){
        return $this->db->table($this->table)->where($where)->count();
    }
}