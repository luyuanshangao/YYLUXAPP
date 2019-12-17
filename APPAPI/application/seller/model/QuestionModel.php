<?php
namespace app\seller\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 买家商品提问表
 * @author
 * @version  zhongning 2018/4/28
 */
class QuestionModel extends Model{

    public $page_size = 10;
    public $page = 1;
    protected $question = 'sl_question';
    protected $answer = 'sl_answer';
	public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }

    /**
     * 问答列表
     * @param $params
     * @return array
     */
    public function questionsAndAnswersLists($params,$page_size,$page,$path,$query_where=""){
        $page_size = isset($params['page_size']) ? $params['page_size'] : $this->page_size;
        $page = isset($params['page']) ? $params['page'] : $this->page;

        $query = $this->db->table($this->question)->alias("q")->join($this->answer." a","q.question_id=a.question_id","LEFT");

        if(isset($params['product_id']) && $params['product_id']){
            $query->where([
                $this->question . '.product_id' => $params['product_id']
            ]);
        }
        if(isset($params['is_answer']) && $params['is_answer']){
            $query->where([
                $this->question . '.is_answer' => $params['is_answer']
            ]);
        }
        if(isset($params['customer_id']) && $params['customer_id']){
            $query->where([
                $this->question . '.customer_id' => $params['customer_id']
            ]);
        }
        if(isset($params['type']) && $params['type']){
            $query->where([
                $this->question . '.type' => $params['type']
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



    public function questionCount($params){
        $query = $this->db->table($this->question);

        if(isset($params['product_id']) && $params['product_id']){
            $query->where([
                $this->question . '.product_id' => $params['product_id']
            ]);
        }
        $query->field('count(question_id) as num,type');

        $ret = $query->group('type')->select();
        //全部类型问题数量
        if(!empty($ret)){
            $all['num'] = $this->db->table($this->question)->count();
            $all['type'] = 0;
            array_push($ret,$all);
        }
        return $ret;
    }

    /*
     * 获取用户提问数量
     * */
    public function getQuestionCount($params){
        $count = $this->db->table($this->question)->where($params)->count();
        return $count;
    }

    public function addQuestion($data){
        $res = $this->db->table($this->question)->insertGetId($data);
        return $res;
    }

    /*
     * 一键阅读全部
     * */
    public function answerFullRead($where){
        $res = $this->db->table($this->question)->alias("q")
            ->join($this->answer." a","q.question_id=a.question_id","LEFT")
            ->where($where)->update(['a.read_status'=>1]);
        return $res;
    }

    /*
     * 获取问题和回答
     * */
    public function getQuestionWhere($params){
        $data['question'] = $this->db->table($this->question)->where($params)->find();
        $data['answer'] = array();
        if(isset($data['question']['is_answer']) && $data['question']['is_answer'] == 1){
            $data['answer'] = $this->db->table($this->answer)->where(['question_id'=>$data['question']['question_id']])->find();
            $this->db->table($this->answer)->where(['question_id'=>$data['question']['question_id']])->update(['read_status'=>1]);
        }

        return $data;
    }
}