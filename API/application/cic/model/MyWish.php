<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 收藏模型
 * @author
 * @version Kevin 2018/3/25
 */
class MyWish extends Model{
    protected $table = 'cic_my_wish';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 新增用户收藏详情
* */
    public function addWish($data){
        $res = $this->db->table($this->table)->insertGetId($data);
        return $res;
    }

    /*
* 新增用户收藏详情
* */
    public function addWishAll($delete_spu,$data){
        $res = $this->db->transaction(function() use($delete_spu,$data){
            $where['SPU'] = ['in',$delete_spu];
            $this->db->table($this->table)->where($where)->delete();
            $res = $this->db->table($this->table)->insertAll($data);
            return $res;
        });
        return $res;
    }
    /*
     * 获取收藏个数
     * */
    public function  getWishCount($where){
        $res = $this->db->table($this->table)->where($where)->count();
        return $res;
    }

    /*
* 新增用户收藏详情
* */
    public function delWish($where){
        $data['IsDelete'] = 1;
        $res = $this->db->table($this->table)->where($where)->update($data);
        return $res;
    }

    /*
    * 获取用户收藏详情列表
    * */
    public function getWishList($where,$page_size,$page,$path){
        $res = $this->db->table($this->table)->where($where)->order("ID desc")
            ->field("ID,CustomerID,Username,SPU,Source,PriceWhenAdded,ShippingWhenAdded,Comments,Tags,AddTime")
            ->group("SPU")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path]);
        $Page = $res->render();
        $data = $res->toArray();
        foreach ($data['data'] as $key=>$value){
            if($value['SPU'] != $key){
                unset($data['data'][$key]);
            }
            $data['data'][$value['SPU']] = $value;
        }
        $group = $this->db->table($this->table)->where($where)->field("Tags,count(ID)")->group("Tags")->select();
        $data['group'] = $group;
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取收藏个数
     * */
    public function  getWishId($where){
        $res = $this->db->table($this->table)->where($where)->value("ID");
        return $res;
    }
    /*
     * 获取收藏分类ID
     * */
    public function getWishCategoryID($where){
        $res = $this->db->table($this->table)->where($where)->group("CategoryID")->column("CategoryID");
        return $res;
    }

    /*
 * 获取收藏分类ID
 * */
    public function getWishNum($where){
        $res = $this->db->table($this->table)->field('COUNT(distinct CustomerID) as customer_count')->where($where)->find();
        return $res;
    }

    public function getWishSPU($where){
        $res = $this->db->table($this->table)->where($where)->column('SPU');
        return $res;
    }
}