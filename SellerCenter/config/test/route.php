<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    '__pattern__' => [
        'name' => '\w+',
    ],
    '[hello]'     => [
        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
        ':name' => ['index/hello', ['method' => 'post']],
    ],

    'passwordfind'=>'login/passwordFind',//找回密码
    'passwordfindsecond'=>'login/passwordfindSecond',//找回密码2
    'passwordfindthird'=>'login/passwordfindThird',//找回密码3
    'passwordfindfourth'=>'login/passwordfindFourth',//找回密码4
    'passwordfindsuccess'=>'login/passwordfindSuccess',//找回密码-成功

    'register'=>'login/register',//用户注册
    'register_info'=>'Login/registerInfo',//用户注册-账号信息
    'register_finish'=>'login/registerFinish',//用户注册-成功

];
