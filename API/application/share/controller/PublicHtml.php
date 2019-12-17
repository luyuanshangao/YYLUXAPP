<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;

/**
 *获取公共页面内容
 */
class PublicHtml extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    /*
     * 获取头部
     * */
    public function getHeader(){
        $lang = input("lang");
        $lang = !empty($lang) ? $lang : 'en';
        $file_path = ROOT_PATH.'public'.DS.'static'.DS.$lang.DS."header.html";
        if(file_exists($file_path)){
            $myfile = fopen($file_path, "r") or die("Unable to open file!");
            $contents = fread($myfile,filesize($file_path));;
            return $contents;
        }
    }

    /**
     * 公共尾部数据
     */
    public function getFooter(){
        $lang = input("lang");
        $lang = !empty($lang) ? $lang : 'en';
        $file_path = ROOT_PATH.'public'.DS.'static'.DS.$lang.DS."footer.html";

        if(file_exists($file_path)){
            $contents = file_get_contents($file_path);
            return $contents;
        }
    }
}
