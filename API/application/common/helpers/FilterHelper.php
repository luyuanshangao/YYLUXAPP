<?php

/**

 * Created by JetBrains PhpStorm.

 * User: lsl

 * Date: 18-8-21

 * Time: 下午2:58

 * 敏感词过滤工具类

 * 使用方法

 * echo FilterTools::filterContent("你妈的我操一色狼杂种二山食物","*",DIR."config/word.txt",$GLOBALS["p_memcache"]["bad_words"]);

 */
namespace app\common\helpers;
use app\common\helpers\RedisClusterBase;
use think;


class FilterHelper {

    public static $keyword = array();

    /**

     * 从文件中加载敏感词

     * @param $filename

     * @return array

     */

    static function getBadWords($filename){

        $file_handle = fopen($filename, "r");

        while (!feof($file_handle)) {

            $line = trim(fgets($file_handle));

            array_push(self::$keyword,$line);

        }

        fclose($file_handle);

        return self::$keyword;

    }

    /**

     * @param $content 待处理字符串

     * @param $target 替换后的字符

     * @param $filename 敏感词配置文件

     * @param $memconfig 缓存配置文件

     * @return 处理后的字符串
     *
     * $type 1判断是否存在敏感词，2将文本中敏感词替换

     */

    static function filterContent($content,$filename,$target=''){
        $Redis = new RedisClusterBase();
        //$keyword = $Redis->get("BadWords");
        $keyword = '';
        if($keyword ==false || count($keyword) == 0){
            $keyword = self::getBadWords($filename);
            return $keyword;
            $Redis->set("BadWords",$keyword);
        }
        if(is_array($keyword)){
            //$keyword = array_filter($keyword);
            return $keyword;
            $blacklist="/".implode("|",$keyword)."/i";
            if(empty($target)){
                if(preg_match($blacklist, $content, $matches)){
                    $data['code'] = 200;
                    $data['data'] = false;
                } else {
                    $data['code'] = 200;
                    $data['data'] = true;
                }
                return $data;
            }else{
                return strtr($content, array_combine( $keyword, array_fill(0,count($keyword), $target)));
            }
        }else{
            $data['code'] = 1002;
            return $data;
        }
    }

}

