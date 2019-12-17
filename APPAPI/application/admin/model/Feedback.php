<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class Feedback extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
        $this->table = 'dx_feedback';
        $this->feedback_reply = 'dx_feedback_reply';
    }
    /*
     * 获取列表
     * */
    public function getList($where,$page_size=10,$page=1,$path='',$query='')
    {
        $res = $this->db->table($this->table)
            ->alias("f")
            ->join("dx_feedback_reply fr","f.feedback_id=fr.feedback_id","LEFT")
            ->where($where)
            ->field("f.*,fr.read_time")->order("is_reply desc,read_time asc,f.feedback_id desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($query)?$query:$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 保存反馈信息
     * */
    public function saveFeedback($data,$where=''){
        if(empty($where)){//没有条件新增
            $res = $this->db->table($this->table)->insert($data);
        }else{
            $res = $this->db->table($this->table)->where($where)->update($data);
        }
        return $res;
    }

    /*
    * 获取反馈详情
    * */
    public function getFeedbackInfo($where=''){
        if($where['feedback_id']){
            $where['f.feedback_id'] = $where['feedback_id'];
            unset($where['feedback_id']);
        }
        $res = $this->db->table($this->table)
            ->alias("f")
            ->join($this->feedback_reply." fr","f.feedback_id=fr.feedback_id","LEFT")
            ->where($where)->field("f.*,fr.operator_name,fr.reply_content,fr.addtime as fr_addtime")
            ->find();
        return $res;
    }

    /*
     * 保存反馈回复
     * */
    public function saveFeedbackReply($data,$where=''){
        if(empty($where)){//没有条件新增
            $res = $this->db->table($this->feedback_reply)->insert($data);
        }else{
            if(isset($where['customer_id'])){
                $res = $this->db->table($this->feedback_reply)->alias("fr")->join("dx_feedback f","f.feedback_id=fr.feedback_id")->where($where)->update($data);
            }else{
                $res = $this->db->table($this->feedback_reply)->alias("fr")->where($where)->update($data);
            }
        }
        return $res;
    }

    /*
     * 获取用户反馈条数
     * */
    public function getFeedbackCountByCustomerId($where){
        return  $this->db->table($this->table)->where($where)->count();
    }

}
