<?php
namespace app\reviews\model;
use think\Model;
use think\Db;
/**
 * 模型文件
 * @author
 * @version Kevin 2018/4/27
 */
class ReplyReviews extends Model{
    protected $table = 'dx_reply_reviews';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_reviews');
    }
    /*
    * 回复评论
    * */
    public function addReplyReviews($data){
       return $this->db->table($this->table)->insertGetId($data);
    }

}