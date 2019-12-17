<?php
namespace app\common\controller;
use app\common\helpers\RedisClusterBase;
use think\Controller;
use think\Request;
/**
 * 开发：钟宁
 * 功能：token验证
 * 时间：2018-09-02
 */
class AppBase extends Controller
{

    //前置操作
    protected $beforeActionList = [
        'auth',
    ];

    /**
     * 缓存KEY
     */
    const TOKEN_KEY = 'access_token';
    const CACHE_KEY = "TOKEN_";

    /**
     * 可以不用检验登录信息的url
     * @var array
     */
    protected static $ignoreUrl = [
        'demo/auth/getAccessToken',
    ];

    /**
     * 权限校验
     * @throws BusinessException
     */
    public function auth()
    {
        $request = Request::instance();

        //白名单
        if (!in_array($request->pathinfo(), self::$ignoreUrl)) {
            $token = input(self::TOKEN_KEY);
            if(empty($token)){
                echo json_encode(['code'=>2000011,'msg'=>'no have access_token']);die;
            }
            $auth = (new RedisClusterBase())->get(self::CACHE_KEY . $token);
            if (!$auth) {
                echo json_encode(['code'=>2000011,'msg'=>'The Token is expired !']);die;
            }
        }

    }

}
