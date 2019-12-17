<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use think\Log;
class Article extends Action
{
    private $about_id;
	public function __construct(){
       Action::__construct();
    }
	/*
	 * 文章管理
	 */
	public function index()
	{
	    $cate_name_cn = input("cate_name_cn");
	    if(!empty($cate_name_cn)){
            $where['a.article_title'] = ['like',"%$cate_name_cn%"];
        }
        $cate_id = input("cate_id");
	    if(!empty($cate_id)){
            $cate_ids = $this->getCateChild($cate_id);
            $where['a.cate_id'] = ['in',$cate_ids];
        }
        $status = input("status");
        $query = array();
        if(!empty($status)){
        	$where['status'] = input("status");
        	$query['status'] = input("status");
        }
        $recommend = input("recommend");
        if(!empty($recommend)){
        	$where['recommend'] = input("recommend")==2?0:1; //特殊转换
        	$query['recommend'] = input("recommend");
        }
        $where['a.delete_time'] = 0;
        $page_size= input("page_size",20);
        $page = input("page",1);
        $res = model("Article")
            ->alias("a")
            ->join("dx_article_cate ac","a.cate_id = ac.cate_id")
            ->where($where)
            ->field("a.*,ac.cate_name_cn cate_name")
            ->order("a.cate_id desc")
            ->paginate($page_size,false,[ 'page' => $page,'query'=>$where]);
        //$res = model("Article")->where($where)->paginate($page_size,false,[ 'page' => $page,'query'=>$where]);
        /*foreach ($res as $key => $value){
            $res[$key]['cate_name'] = model("ArticleCate")->where("cate_id={$value['cate_id']}")->value("cate_name_cn");
        }*/
        $cate_all = $this->getCateSelect('',$cate_id,"顶级分类");
        $this->assign("cate_all",$cate_all);
        $this->assign("data",$res);
        $this->assign("query",$query);
        $this->assign('page',$res->render());
        $this->fetch();
		return View();
	}

	/*
	 * 获取文章子级ID
	 * */
	public function getCateChild($cate_id,$cate_ids = array()){
	    $res= model("ArticleCate")->where("parent_id={$cate_id}")->column("cate_id");
        if(!empty($res)){
            $cate_ids= array_merge($cate_ids,$res);
            foreach ($res as $key=>$value){
                $child=$this->getCateChild($value,$cate_ids);
                $cate_ids = array_merge($cate_ids,$child);
            }
        }
        array_push($cate_ids,$cate_id);
        return array_values(array_unique($cate_ids));
    }

    /**
     * 保存文章
     * @return multitype:number string |\think\response\View
     */
    public function saveArticle(){
        if(request()->isPost()){
            $data['article_id'] = input('article_id');
            $data['article_title'] = input('article_title');
            $data['cate_id'] = input('cate_id');
            $data['excerpt'] = input('excerpt');
            $data['status'] = input('status');
            $data['content'] = $_POST['content'];
            $data['recommend'] = input('recommend',0);
            $data['header_image'] = input('header_image');
            $data['keywords'] = $this->ArticleQueryFiltering(input('keywords'));
            $data['image'] = input('image');
            if(empty($data['article_id'])){
                unset($data['article_id']);
                $data['add_time'] = time();
                $res = model("Article")->insertGetId($data);
                $article_id = $res;
            }else{
                $data['add_author'] = input("add_author",Session::get("username"));
                $article_id = $data['article_id'];
                $data['update_author'] = Session::get("username");
                $data['update_time'] = time();
                $res = model("Article")->update($data);
                $res = 1;
            }

            if($res){
                //如果分类是帮助中心和关于我们，访问help生成静态页面
                $parent_cate_id = $this->getParentCate($data['cate_id']);
                if($parent_cate_id == 9 || $parent_cate_id == 17){
                    $baseApi = new BaseApi();
                    $makeArticlehtml = $baseApi->makeArticle(['article_id'=>$article_id]);
                    if(!empty($makeArticlehtml)){
                        Log::write("生成Article成功！".$makeArticlehtml);
                    }else{
                        Log::write("生成Article失败！");
                    }
                }
                $this->success("操作成功","Article/index");
            }else{
                $this->error("操作失败");
            }
        }else{
            $data['article_id'] = input('article_id');
            if(isset($data['article_id'])){
                $article_data = model("Article")->where($data)->find();
                $cate_all = $this->getCateSelect('',$article_data['cate_id'],"请选择类别");
                $this->assign('article_data',$article_data);
            }else{
                $cate_all = $this->getCateSelect('',0,"请选择类别");
            }
            $this->assign('cate_all',$cate_all);
            return view();
        }
    }

    /*
     * 文章删除
     * */
    public function delArticle(){
        $data['article_id'] = input('article_id');
        $data['delete_time'] = time();
        $data['update_author'] = Session::get("username");
        $res = model("Article")->update($data);
        if($res){
            return array('code'=>200,'result'=>'操作成功');
        }else{
            return array('code'=>100,'result'=>'操作失败');
        }

    }

	//文章类别
   public function articleCate(){
       $class_data = Db('ArticleCate')->where(['parent_id'=>0])->select();
       $this->assign(['class_data'=>$class_data]);
	    return view();
   }

    //文章类别
    public function saveCate(){
	    if(request()->isPost()){
	        $type = input("type",'add');
            $data['cate_id'] = input('cate_id');
            $data['parent_id'] = input('parent_id',0);
            $data['cate_name_cn'] = input('cate_name_cn');
            $data['cate_name_en'] = input('cate_name_en');
            $data['sort'] = input('sort',255);
	        if(empty($data['cate_id']) || $type=="add"){
	            unset($data['cate_id']);
                $res = model("ArticleCate")->save($data);
            }else{
                $data['cate_id'] = input('cate_id');
                $res = model("ArticleCate")->update($data);
                $res = 1;
            }
            if($res){
                return array('code'=>200,'result'=>'操作成功');
            }else{
                return array('code'=>100,'result'=>'操作失败');
            }
        }else{
            $type = input('type','add');
            $data['cate_id'] = input('cate_id');
            if(isset($data['cate_id']) && $type=='edit'){
                $cate_data = model("ArticleCate")->where($data)->find();
                $cate_all = $this->getCateSelect('parent_id',$cate_data['parent_id'],"顶级分类");
                $this->assign('cate_data',$cate_data);
            }else{
                $add_data['parent_id'] = input('cate_id',0);
                $cate_all = $this->getCateSelect('parent_id',$add_data['parent_id'],"顶级分类");
                $this->assign('cate_data',$add_data);
            }
            $this->assign('cate_all',$cate_all);
            return view();
        }
    }

    public function delCate(){
        $cate_id = input("cate_id");
        $child_count = Db('ArticleCate')->where("parent_id=$cate_id")->count();
        if($child_count>0){
            return array('code'=>100,'result'=>'还有子级ID，不能删除');
        }
        $res = Db('ArticleCate')->where("cate_id=$cate_id")->delete();
        if($res){
            return array('code'=>200,'result'=>'操作成功');
        }else{
            return array('code'=>100,'result'=>'操作失败');
        }
    }

    /*
    * 获取分组列表
    * */
    public function getCate($where=array()){
        $where['parent_id'] = 0;
        $res = Db('ArticleCate')->where($where)->order("cate_id","desc")->select();
        if(!empty($res)){
            foreach ($res as $k=>$v){
                $where1['parent_id'] = $v['cate_id'];
                $child = Db('ArticleCate')->where($where1)->select();
                if(!empty($child)){
                    $res[$k]['child'] = $child;
                }
                if(!isset($res[$k]['child'])){
                    $res[$k]['child'] = '';
                }
            }
        }
        return $res;
    }

    public function getCateSelect($form_name='cate_id',$select=0,$placeholder){
        $res = Db('ArticleCate')->order("cate_id","desc")->select();
        $select = getTree($res,['primary_key'=>'cate_id','class_name'=>'','form_name'=>$form_name,'parent_key'=>'parent_id'])->makeSelect($select,'cate_name_cn',$placeholder);
        return $select;
    }

    public function class_name(){
        $classid = request()->post();
        $html = '';
        if(!empty($classid['cate_id'])){
            $list = Db('ArticleCate')->where(['parent_id'=>$classid['cate_id']])->select();
            if($list){
                foreach ($list as $key => $value) {
                    $class_table = Db('ArticleCate')->where(['cate_id'=>$value['cate_id']])->select();
                    if($class_table){
                        $html .= '<li "><div onclick="classid('.$value["cate_id"].')" class="hitarea closed-hitarea collapsable-hitarea expandable-hitarea  hitarea'.$value["cate_id"].'"></div><span  onclick="classid('.$value["cate_id"].')"  data-id ="'.$value['cate_id'].'" title-cn ="'.$value["cate_name_cn"].'" title-en = "'.$value["cate_name_en"].'" parent-id = "'.$classid['cate_id'].'" sort = "'.$value["sort"].'"  class="folder classid cursor-pointer cursor-pointer'.$value['cate_id'].'" >'.$value['cate_id'].$value['cate_name_cn'].'['.$value['cate_name_en'].']'.'</span><input name="sort" class="form-inline w50 ml10" value="'.$value['sort'].'" type="text"><b class="edito btn btn-orange ml10 pd2"   onclick="save_public(\''.url('/Article/saveCate',array('type'=>'edit','cate_id'=>$value['cate_id'])).'\')" >修改</b>
               <b  onclick="save_public(\''.url('/Article/saveCate',array('type'=>'add','cate_id'=>$value['cate_id'])).'\')" class="btn btn-qing pd2">新增</b> <b  onclick="delArticleCate('.$value['cate_id'].')"  class="btn btn-orange pd2"  href="javascript:;">删除</b><ul  class="class'.$value["cate_id"].'"></ul></li>';
                    }else{
                        $html .= '<li><span class="folder classid'.$value['cate_id'].'"  data-id ="'.$value['cate_id'].'" title-cn ="'.$value["cate_name_cn"].'" title-en = "'.$value["cate_name_en"].'" parent-id = "'.$classid['cate_id'].'" sort = "'.$value["sort"].'"'.$value['cate_id'].$value['cate_name_cn'].'['.$value['cate_name_en'].']'.'<input name="sort" class="form-inline w50 ml10" value="'.$value['sort'].'" type="text"><b class="btn btn-orange ml10 pd2"  onclick="save_public(\''.url('/Article/saveCate',array('type'=>'edit','cate_id'=>$value['cate_id'])).'\')"  >修改</b>
               <b onclick="save_public('.url('\'/Article/saveCate',array('type'=>'add','cate_id'=>$value['cate_id'])).'\')" class="btn btn-qing pd2">新增</b><b  onclick="delArticleCate('.$value['cate_id'].')"  class="btn btn-orange pd2"  href="javascript:;">删除</b></span></li>';
                    }
                }
            }
            $data = array(
                'code'=>200,
                'html'=>$html
            );
            echo json_encode($data);
            exit;
        }
    }

    /*
     * 获取父级分类
     * */
    public function getParentCate($cate_id,$level = 0){
        $i = 0;
        if(!empty($cate_id)){
            $parent_id = Db('ArticleCate')->where(['cate_id'=>$cate_id])->value("parent_id");
            if($level != 1){
                if($parent_id == 0){
                    return $cate_id;
                }else{
                    if($level == 0){
                        return $this->getParentCate($parent_id);
                    }else{
                        return $this->getParentCate($parent_id,$level-1);
                    }
                }
            }else{
                return $parent_id;
            }
        }else{
            return '';
        }
    }

    /*
* 远程上传
* */
    public function remoteUpload(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = $this->localUpload();
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['ARTICLE_IMAGES'].date("Ymd");
            $config = [
                'dirPath'=>$remotePath, // ftp保存目录
                'romote_file'=>$localres['FileName'], // 保存文件的名称
                'local_file'=>$localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            if($upload){
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['url'] = $remotePath.'/'.$localres['FileName'];
                $res['complete_url'] = DX_FTP_ACCESS_URL.$remotePath.'/'.$localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            echo json_encode(array('error' => 0, 'url' => $res['complete_url']));
        }
    }


    /*
 * 本地上传图片
 * */
    public function localUpload(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file();
        if(!empty($file)){
            $file = array_shift($file);
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $path = "public".DS . 'uploads';
            $upload_path = ROOT_PATH . $path;
            $info = $file->move($upload_path);
            if($info){
                $file_path= 'uploads'. DS .$info->getSaveName();
                $res['code'] = 200;
                $res['msg'] = "上传成功";
                $res['url'] = $file_path;
                $res['FileName'] = $info->getFilename();
                return $res;
            }else{
                // 上传失败获取错误信息
                $res['code'] = 100;
                $res['msg'] = $file->getError();
                return $res;
            }
        }else{
            $res['code'] = 100;
            $res['msg'] = "上传图片超过尺寸";
            return $res;
        }
    }

    /**
     * 查询字符转换
     * [QueryFiltering description]
     * @author: wang
     * AddTime:2018-12-17
     */
    function ArticleQueryFiltering($value=''){
        if(!empty($value)){
            $result = str_replace(['，',';','；'],[',',',',','],$value);
            $pattern      = '/(,)+/i';
            $result = preg_replace($pattern,',',$result);
            return $result;
        }
        return ;
    }
}