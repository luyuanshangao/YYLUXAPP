<?php
namespace app\index\model;

use think\Db;
use think\Log;
use think\Model;

/**
 * Created by tinghu.liu
 * Date: 2018/5/24
 * Time: 11:15
 */

class ProductQaModel extends Model{
    /**
     * 产品问答表
     * @var string
     */
    protected $table_question = 'sl_question';
    /**
     * 产品问答回答表
     * @var string
     */
    protected $table_answer = 'sl_answer';

    /**
     * 获取提问列表
     * @param $search_content 搜索内容
     * @param $seller_id 商家ID
     * @param int $page_size 分页大小
     * @return array
     */
    public function getQuestionList($search_content, $seller_id,$page_size=10){
        $query = Db::table($this->table_question);
        if (!empty($search_content)){
            $query->where('description', 'like', '%'.$search_content.'%');
        }
        $query->where('seller_id', '=', $seller_id);
        $query->where(['type'=>["neq",6]]);
        $query->order('question_id', "desc");
        $response = $query->paginate($page_size)->each(function($item, $key){
            $item['answer_data'] = $this->getAnswerByWhere(['question_id'=>$item['question_id']]);
            return $item;
        });
        $page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $page;
        return $data;
    }

    /**
     * 根据条件获取数据
     * @param array $where
     * @return array
     * @throws \think\exception\DbException
     */
    public function getMessageListByWhere(array $where){
        $query = Db::table($this->table_question);
        $page_size = config('paginate.list_rows');
        if (isset($where['page_size']) && !empty($where['page_size'])){
            $page_size = $where['page_size'];
        }
        if (isset($where['seller_id']) && !empty($where['seller_id'])){
            $query->where('seller_id', '=', $where['seller_id']);
        }
        if (isset($where['type']) && !empty($where['type'])){
            $query->where('type', '=', $where['type']);
        }
        $response = $query->paginate($page_size)->each(function($item, $key){
            //$item['answer_data'] = $this->getAnswerByWhere(['question_id'=>$item['question_id']]);
            return $item;
        });
        $page = $response->render();
        $data = $response->toArray();
        $data['page'] = $page;
        return $data;
    }

    /**
     * 更新提问数据
     * @param array $where 条件
     * @param array $up_data 更新的数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function updateQuestionData(array $where, array $up_data){
        return Db::table($this->table_question)->where($where)->update($up_data);
    }

    /**
     * 根据条件获取回答数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getAnswerByWhere(array $where){
        return Db::table($this->table_answer)->where($where)->select();
    }

    /**
     * 新增回答数据
     * @param array $data 要增加的数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function insertAnswerData(array $data){
        return Db::table($this->table_answer)->insert($data);
    }

    /**
     * 回复产品提问问题
     * @param array $data 要回复的数据
     * @return bool
     */
    public function replyQuestion(array $data){
        $rtn = true;
        // start
        Db::startTrans();
        try{
            //记录回复信息
            $this->insertAnswerData($data);
            //修改回复状态
            $this->updateQuestionData(
                ['question_id'=>$data['question_id']],
                ['is_answer'=>1]
            );
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('回复产品提问问题事务出错');
            // roll
            Db::rollback();
        }
        return $rtn;
    }


}