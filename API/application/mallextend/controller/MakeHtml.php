<?php
namespace app\mallextend\controller;
use app\common\controller\Base;


/**
 * 创建：钟宁
 * 功能：定时生成首页静态页面，头部静态页面，底部静态页面
 * 时间：2018-05-28
 */
class MakeHtml extends Base
{
    public function __construct(){
        parent::__construct();
    }

    /**
     * 生成静态头部
     */
    public function header(){
        $params = input();
        $lang = isset($params['lang']) ? $params['lang'] : '';
        if(empty($lang)){
            $langMenu = config("Language");
            foreach($langMenu as $langs){
                doCurl(MALL_DOCUMENT.'home/makeHtml/header?lang='.$langs['Code']);
            }
        }else{
            doCurl(MALL_DOCUMENT.'home/makeHtml/header?lang='.$lang);
        }

    }

    /**
     * 生成静态尾部
     */
    public function footer(){
        $params = input();
        $lang = isset($params['lang']) ? $params['lang'] : '';
        if(empty($lang)){
            $langMenu = config("Language");
            foreach($langMenu as $langs){
                doCurl(MALL_DOCUMENT.'home/makeHtml/footer?lang='.$langs['Code']);
            }
        }else{
            doCurl(MALL_DOCUMENT.'home/makeHtml/footer?lang='.$lang);
        }

    }

    /**
     * 商城首页静态化
     */
    public function index(){

        $params = input();
        $lang = isset($params['lang']) ? $params['lang'] : '';
        if(empty($lang)){
            $langMenu = config("Language");
            foreach($langMenu as $langs){
                doCurl(MALL_DOCUMENT.'home/makeHtml/index?lang='.$langs['Code']);
            }
        }else{
            doCurl(MALL_DOCUMENT.'home/makeHtml/index?lang='.$lang);
        }

    }

    /**
     * 获取源代码接口
     * @return mixed
     */
    public function getHtmlSource(){
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');
        try{
            $params = input();
            if(empty($params['url'])){
                return apiReturn(['code'=>1002, 'msg'=>'url required']);
            }
            $data = file_get_contents($params['url']);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }
}