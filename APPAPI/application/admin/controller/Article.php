<?php
namespace app\admin\controller;
use think\cache\driver\Redis;
use app\common\controller\Base;
class Article extends Base
{
    /*
     * 获取列表
     * */
    public function getList()
    {
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Article.getList");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $cate_id = input("cate_id");

        if(empty($cate_id)){
            return apiReturn(['code'=>1001]);
        }
        $cate_ids = model('ArticleCate')->getCateChild($cate_id);
        $where['a.cate_id'] = ['in',$cate_ids];
        $article_title = input("article_title");
        if(!empty($article_title)){
            $where['article_title'] = ['like',"%$article_title%"];
        }
        $status = input("status");
        if($status){
            $where['status'] = $status;
        }
        $page_size = input('page_size',20);
        $page = input("page",1);
        $path = input("path");
        $where = array_filter($where);
        $where['delete_time'] = 0;
        $res = model("Article")->getList($where,$page_size,$page,$path);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 获取列表
     * */
    public function getInfo()
    {
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Article.getInfo");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $article_id = input("article_id");
        $article_title = input("article_title");
        if(!empty($article_title)){
            $where['a.article_title'] = ['like',"%$article_title%"];
        }
        $where['a.article_id'] = $article_id;
        $where = array_filter($where);
        $res = model("Article")->getInfo($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 获取文章分类
     * */
    public function getArticleCate(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Article.getArticleCate");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $cate_id = input('cate_id');
        /*
        if(empty($cate_ids)){
        	$cate_ids=[9,17,18,19];
        }
        */
        $where['cate_id'] = $cate_id;
        $res = model("ArticleCate")->getCate($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
    
    /**
     * 获取推荐文章--最新的$limit条
     * add by :heng.zhang  2018-06-26
     * */
    public function getRecommendQuestions(){
    	//return 'dddd';
    	$limit = input("limit");
    	//return $limit;
    	$res = model("Article")->getRecommendQuestions($limit);
    	return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 获取显示的文章全部ID
     * */
    public function getArticleIds(){
        $res = model("Article")->getArticleIds();
        return apiReturn(['code'=>200,'data'=>$res]);
    }
}
