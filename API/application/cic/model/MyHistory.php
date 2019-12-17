<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 历史模型
 * @author
 * @version Kevin 2018/3/25
 */
class MyHistory extends Model{
    protected $table = 'cic_my_history';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 新增用户历史
* */
    public function addHistory($data){
        $res = $this->db->table($this->table)->insertGetId($data);
        return $res;
    }

    /*
* 删除用户浏览历史
* */
    public function delHistory($id){
        $where['id'] = $id;
        $data['delete_time'] = time();
        $res = $this->db->table($this->table)->where($where)->update($data);
        return $res;
    }

    /*
    * 获取用户积分详情列表
    * */
    public function getHistoryList($where,$page_size,$page,$path){
        $res = $this->db->table($this->table)->where($where)->order("id desc")->field("id,customer_id,spu,store_id,add_time,edit_time,delete_time")->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }
}