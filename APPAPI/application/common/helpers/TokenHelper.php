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

use think\Cache;
use think\exception\HttpException;


class TokenHelper {
    public $time=604800;//7天

    //注意只适合单点登陆
    public function get_token($uid)
    {
        //生成新的token
        $token = md5($uid . strval(NOW_TIME) .uniqid(). strval(rand(0, 999999)));
        $res =Cache::set($token,$uid,$this->time);
        if(!empty($res)){
            return $token;
        }else{
            throw new HttpException(298, 'token生成失败');
        }

    }

    //获取用户id
    public function getUid($key='')
    {
        if ($key) {
            $uid =Cache::get($key);
            if (!empty($uid)) {
                return $uid;
            }
        }
        return false;
    }

}

