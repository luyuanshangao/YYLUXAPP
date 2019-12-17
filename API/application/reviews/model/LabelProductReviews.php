<?php
namespace app\reviews\model;
use think\Model;
use think\Db;
/**
 * 模型文件
 * @author
 * @version Kevin 2018/4/27
 */
class LabelProductReviews extends Model{
    protected $table = 'dx_label_product_reviews';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_reviews');
    }
    /*
    * 获取商品标签
    * */
    public function getLabelProductReviews($where){

       $label = $this->db->table($this->table)->where($where)->field("id,product_id,label,customer_id,add_time,host")
           ->group("label")->order("host desc")
           ->select();
        return $label;
    }

    /*
     * 添加标签
     * */
    public function addLabelProductReviews($data){
        $where['label'] = $data['label'];
        $id = $this->db->table($this->table)->where($where)->value("id");
        /*如果存在，热度加1，否则增加标签*/
        if($id){
            $res = $this->db->table($this->table)->where($where)->setInc("host");
        }else{
            $data['add_time'] = time();
            $data['host'] = 1;
            $id = $this->db->table($this->table)->insertGetId($data);
        }
        return $id;
    }
}