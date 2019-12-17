<?php
namespace app\seller\model;

use think\Model;
use think\Db;
/**
 * 批发询价模型
 * Class WholesaleInquiry
 * @author tinghu.liu 2018/06/11
 * @package app\seller\model
 */
class WholesaleInquiry extends Model{
    /**
     * sl_wholesale_inquiry 表
     * @var string
     */
    protected $table = 'sl_wholesale_inquiry';
    protected $answer = 'sl_wholesale_inquiry_answer';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }

    /**
     * 新增数据
     * @param array $data 要新增的数据
     * @return int|string 新增后的主键ID
     */
    public function addData(array $data){
        return $this->db->table($this->table)->insertGetId($data);
    }

    public function getAdminWholesaleInquirylist($params,$page_size=20,$page=1,$path,$query_where=""){
        $query = $this->db->table($this->table)->where($params)->order("is_answer asc,is_crash ASC,id desc");
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($query_where)?$query_where:$params]);
        $Page = $ret->render();
        $ret = $ret->toArray();
        $ret['Page'] = $Page;
        return $ret;
    }

    /*更改问题数据*/
    public function updateWholesaleInquiry($where,$data){
        $res = $this->db->table($this->table)->where($where)->update($data);
        return $res;
    }

    /*添加回答*/
    public function addWholesaleInquiryAnswer($data){
        $res = $this->db->table($this->answer)->insert($data);
        return $res;
    }

    /*
   * 获取问题和回答
   * */
    public function getWholesaleInquiryWhere($params){
        $data['question'] = $this->db->table($this->table)->where($params)->find();
        $data['answer'] = array();
        if(isset($data['question']['is_answer']) && $data['question']['is_answer'] > 0){
            $data['answer'] = $this->db->table($this->answer)->where(['id'=>$data['question']['id']])->find();
            $this->db->table($this->answer)->where(['id'=>$data['question']['id']])->update(['read_status'=>1]);
        }

        return $data;
    }

    /*获取问题*/
    public function getOneWholesaleInquiry($where){
        $data = $this->db->table($this->table)->where($where)->field("id,seller_id,product_name,product_id,addtime,aging")->find();
        return $data;
    }

    /*
 * 获取用户提问数量
 * */
    public function getWholesaleCountWhere($params){
        $count = $this->db->table($this->table)->where($params)->count();
        return $count;
    }

    /**
     * 问答列表
     * @param $params
     * @return array
     */
    public function wholesaleAndAnswersLists($params,$page_size,$page,$path,$query_where=""){
        $page_size = isset($params['page_size']) ? $params['page_size'] : $this->page_size;
        $page = isset($params['page']) ? $params['page'] : $this->page;

        $query = $this->db->table($this->table)->alias("q")->join($this->answer." a","q.id=a.inquiry_id","LEFT");

        if(isset($params['product_id']) && $params['product_id']){
            $query->where([
                $this->table . '.product_id' => $params['product_id']
            ]);
        }
        if(isset($params['is_answer']) && $params['is_answer']){
            $query->where([
                $this->table . '.is_answer' => $params['is_answer']
            ]);
        }
        if(isset($params['customer_id']) && $params['customer_id']){
            $query->where([
                $this->table . '.customer_id' => $params['customer_id']
            ]);
        }
        if(isset($params['addtime']) && $params['addtime']){
            if(is_array($params['addtime'])){
                foreach ($params['addtime'] as $key=>$value){
                    $params['addtime'][$key] = trim($value);
                }
            }
            $query->where(['q.addtime' => $params['addtime']
            ]);
        }
        $query->field("q.*,a.read_status,a.description as answer");
        $query->order("a.read_status desc,q.addtime desc");
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($query_where)?$query_where:$params]);
        $Page = $ret->render();
        $ret = $ret->toArray();
        $ret['Page'] = $Page;
        return $ret;
    }

    /*
     * 获取问题和回答
     * */
    public function getWholesaleWhere($params){
        $data['question'] = $this->db->table($this->table)->where($params)->find();
        $data['answer'] = array();
        if(isset($data['question']['is_answer']) && $data['question']['is_answer'] >0){
            $data['answer'] = $this->db->table($this->answer)->where(['inquiry_id'=>$data['question']['id']])->find();

            $this->db->table($this->answer)->where(['inquiry_id'=>$data['question']['id']])->update(['read_status'=>1]);
        }

        return $data;
    }}