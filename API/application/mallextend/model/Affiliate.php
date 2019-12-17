<?php
namespace app\mallextend\model;
use think\Model;
use think\Db;
/**
 * 产品分组模型
 * @author
 * @version kevin 2018/4/1
 */
class Affiliate extends Model{
    private  $db;
    protected $table = 'dx_affiliate_banner';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /*
     * 获取列表
     * */
    public function getBannerList($where,$page_size,$page,$path,$order,$query){
        $where['IsDelete'] = (int)0;
        $res = $this->db->table($this->table)->where($where)->order($order[0], $order[1])->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$query]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取列表
     * */
    public function getBannerInfo($where){
        $res = $this->db->table($this->table)->where($where)->find();
        return $res;
    }
}