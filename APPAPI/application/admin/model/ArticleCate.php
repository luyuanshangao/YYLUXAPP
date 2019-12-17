<?php
namespace app\admin\model;
use think\Model;
use think\Db;


class ArticleCate extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }
    /*
     * 获取分组列表
     * */
    public function getCate($where=array()){
        $res = $this->db->name('article_cate')->where($where)->order("cate_id","desc")->select();
        if(!empty($res)){
            foreach ($res as $k=>$v){
                $where1['parent_id'] = $v['cate_id'];
                $child = $this->db->name('article_cate')->where($where1)->select();
                if(!empty($child)){
                    $res[$k]['child'] = $child;
                    foreach ($child as $key=>$value){
                        $where2['parent_id'] = $value['cate_id'];
                        $child1 = $this->db->name('article_cate')->where($where2)->select();
                        if(!empty($child1)){
                            $res[$k]['child'][$key]['child'] = $child1;
                        }
                        if(!isset($res[$k]['child'][$key]['child'])){
                            $res[$k]['child'][$key]['child'] = '';
                        }
                    }
                }
                if(!isset($res[$k]['child'])){
                    $res[$k]['child'] = '';
                }
            }
        }
        return $res;
    }

    /*
	 * 获取文章子级ID
	 * */
    public function getCateChild($cate_id,$cate_ids = array()){
        $res= $this->db->name("article_cate")->where("parent_id={$cate_id}")->column("cate_id");
        if(!empty($res)){
            $cate_ids= array_merge($cate_ids,$res);
            foreach ($res as $key=>$value){
                $child=$this->getCateChild($value,$cate_ids);
                $cate_ids = array_merge($cate_ids,$child);
            }
        }
        array_push($cate_ids,(int)$cate_id);
        return array_values(array_unique($cate_ids));

    }

}
