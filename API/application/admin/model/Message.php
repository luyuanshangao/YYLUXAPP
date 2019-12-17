<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class Message extends Model
{
    protected $table = 'dx_message';
    protected $table_recive = 'dx_message_recive';
    protected $table_reply = 'dx_message_reply';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }
    /*
     * 获取列表
     * */
    public function getList($where,$page_size=10,$page=1,$path='',$query='')
    {
        $res = $this->db->name("message")
            ->alias("m")
            ->join("dx_message_recive mr","m.id = mr.message_id","LEFT")
            ->where($where)
            ->field("m.id,mr.id mr_id,title,type,send_user,recive_user_id,content,status,addtime,recive_user_name,recive_type,read_status,read_time,mark")->order("read_status asc,mr.id desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($query)?$query:$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取列表【seller用】
     * */
    public function getListForSeller($where,$page_size=10,$page=1,$path='')
    {
        $res = $this->db->name("message")
            ->alias("m")
            ->join("dx_message_recive mr","m.id = mr.message_id")
            ->where($where)
            ->field("m.id as message_id, mr.id as message_recive_id,m.title,m.type,m.send_user,mr.recive_user_id,m.content,m.status,m.addtime,mr.recive_user_name,mr.recive_type,mr.read_status,mr.read_time,mr.mark")->order("mr.id","desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where])->each(function($item, $key){
                //读状态
                $read_status_str = '未读';
                if ($item['read_status'] == 1){
                    $read_status_str = '已读';
                }
                $item['read_status_str'] = $read_status_str;
                //收藏状态
                $mark_str = '未标记';
                if ($item['mark'] == 1){
                    $mark_str = '已标记';
                }
                $item['mark_str'] = $mark_str;
                //类型 消息类型 1：系统消息 2:手工消息
                $type_str = '手工消息';
                if ($item['type'] == 1){
                    $type_str = '系统消息';
                }
                $item['type_str'] = $type_str;
                return $item;
            });
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 修改消息
     * */
    public function saveMessage($data,$where=''){
        if(empty($where)){//没有条件新增
            $res = $this->db->name("message")->insert($data);
        }else{
            $res = $this->db->name("message")->where($where)->update($data);
        }
        return $res;
    }

    /**
     * 新增消息
     * @param array $params
     * @return bool|string
     * @throws \think\exception\PDOException
     *
     *  ['title','require|integer'],
    //消息类型 1：系统消息 2:手工消息
    ['type','require|integer'],
    //发送者ID
    ['send_user_id','integer'],
    //发送者
    ['send_user','integer'],
    //消息内容
    ['content','require'],

    ['addtime','require|integer'],
    //接收人ID
    ['recive_user_id','require|integer'],
    //接收人名称
    ['recive_user_name','require'],
    //接受者类型 1用户 2卖家
    ['recive_type','require|integer'],
     *
     */
    public function insertMessageData(array $params){
        $rtn = true;
        $this->db->startTrans();
        try{
            //添加主表信息
            $insert_data['title'] = $params['title'];
            $insert_data['type'] = $params['type'];
            $insert_data['send_user_id'] = $params['send_user_id'];
            $insert_data['send_user'] = $params['send_user'];
            $insert_data['content'] = $params['content'];
            $insert_data['addtime'] = $params['addtime'];
            $message_id = $this->db->table($this->table)->insertGetId($insert_data);
            //添加接收表信息
            $re_insert_data['message_id'] = $message_id;
            $re_insert_data['recive_user_id'] = $params['recive_user_id'];
            $re_insert_data['recive_user_name'] = $params['recive_user_name'];
            $re_insert_data['recive_type'] = $params['recive_type'];
            $this->db->table($this->table_recive)->insert($re_insert_data);
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            $this->rollback();
        }
        return $rtn;
    }

    /*
     * 删除消息
     * */
    public function delMessage($where='',$data){
        $res = $this->db->name("message_recive")->where($where)->update($data);
        return $res;
    }

    public function setupMessage($where='',$data){
        $res = $this->db->name("message_recive")->where($where)->update($data);
        return $res;
    }

    /**
     * 根据条件获取数据【分页】
     * @param null|string $params
     * @return array|mixed
     */
    public function getDataByPrams($params){
        $query = $this->db->table($this->table)->alias('m');
        $join = [
            [$this->table_recive.' mr','m.id=mr.message_id','LEFT'],
        ];
        $query->join($join);
        //接收人ID
        if (isset($params['recive_user_id'])){
            $query->where('mr.recive_user_id', '=', $params['recive_user_id']);
        }
        //接受者类型 1用户 2卖家
        if (isset($params['recive_type'])){
            $query->where('mr.recive_type', '=', $params['recive_type']);
        }
        $query->where('mr.isdelete', '=', 0);
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key){
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 根据条件获取消息数量
     * @param array $params
     * @return int|string
     */
    public function countByWhere(array $params){
        $query = $this->db->table($this->table_recive);
        //接受者类型 1用户 2卖家
        if (isset($params['recive_type'])){
            $query->where('recive_type', '=', $params['recive_type']);
        }
        //接收人ID
        if (isset($params['recive_user_id'])){
            $query->where('recive_user_id', '=', $params['recive_user_id']);
        }
        //是否已读 1已读 2未读
        if (isset($params['read_status'])){
            $query->where('read_status', '=', $params['read_status']);
        }
        return $query->count();
    }

    /**
     * 根据条件更新消息数据
     * @param array $up_data
     * @param array $where
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateMessageData(array $up_data, array $where){
        return $this->db->table($this->table)->where($where)->update($up_data);
    }

    /**
     * 根据条件更新消息接收数据
     * @param array $up_data
     * @param array $where
     * @return int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateMessageReciveData(array $up_data, array $where){
        return $this->db->table($this->table_recive)->where($where)->update($up_data);
    }

    /*
     * 获取消息数量
     * */
    public function getCount($where){
        $where['isdelete'] = 0;
        return $this->db->table($this->table_recive)->where($where)->count();
    }

    /*
     * 一键阅读
     * */
    public function fullMessage($where){
        return $this->db->table($this->table_recive)->where($where)->update(['read_status'=>1]);
    }

    /*
     * 根据ID获取信息详情
     * */
    public function getInfoById($where){
        return $this->db->table($this->table)
            ->alias("m")
            ->join($this->table_recive." mr","m.id=mr.message_id")
            ->where($where)
            ->field("m.*,mr.id mr_id,mr.read_status,read_time")
            ->find();
    }

}
