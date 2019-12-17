<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class Article extends Model
{
    public function __construct()
    {
        parent::__construct();
        #默认绑定搜索指定类别的数据
        define('DEFAULT_CLASS_ID', 9);
        $this->db = Db::connect('db_admin');               
    }
    /*
     * 获取列表
     * */
    public function getList($where,$page_size=10,$page=1,$path='')
    {
        $where['a.delete_time']=0;
        $res = $this->db->name("article")
            ->alias("a")
            ->join("dx_article_cate c","a.cate_id = c.cate_id")
            ->where($where)
            ->field("a.article_id,a.image,a.article_title,a.excerpt,a.cate_id,c.cate_name_cn,
                     c.cate_name_en,a.href,a.keywords,a.status,a.add_time")
            ->order("a.article_id","desc")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取消息
     * */
    public function getInfo($where=''){
        $res = $this->db->name("article")
            ->alias("a")
            ->join("dx_article_cate c","a.cate_id = c.cate_id")
            ->where($where)
            ->field("a.article_id,a.image,a.article_title,a.excerpt,a.cate_id,c.cate_name_cn,
                     c.cate_name_en,a.content,a.href,a.keywords,a.status,a.add_time,a.update_time")
            ->find();
        //return $this->db->getLastSql();
        return $res;
    }
    
    /**
     * 获取推荐文章
     * add by :heng.zhang  2018-06-26
     * */
    public function getRecommendQuestions($limit){
    	$where['recommend'] =1;
        $where['delete_time'] =0;
    	$cates = $this -> getChilden();
    	$calssids = [];
    	if(count($cates) >0 ){
    		foreach ($cates as $key => $val){
    			$calssids[] = $val["cate_id"];
    		}
    	}
    	$where['cate_id'] =array('in',$calssids);   	
    	$res = $this->db->name("article")    	
				    	->where($where)
				    	->order('article_id desc')
				    	->field("article_id as id,article_title as title")
				    	->limit($limit)
    					->select();    	
    	//return $this->db->getLastSql();
    	return $res;
    }
    
    /**
     * 获取一级类别的二级和三级类别
     */
    private function getChilden(){
    	$result = $this->db->query('select cate_id from dx_article_cate where parent_id=:parent_id
									UNION ALL
									select cate_id from dx_article_cate 
									where parent_id in(
									select cate_id from dx_article_cate where parent_id=:parent_id_s
									)', ['parent_id' => DEFAULT_CLASS_ID,'parent_id_s'=>DEFAULT_CLASS_ID]);
    	return $result;
    }


    /*获取文章ID*/
    public function getArticleIds(){
        $where['status'] = 1;
        $where['delete_time'] = ['gt',0];
        $res = $this->db->name("article")->column("article_id");
        return $res;
    }
}
