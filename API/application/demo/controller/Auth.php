<?php
// +----------------------------------------------------------------------
// | When work is a pleasure, life is a joy!
// +----------------------------------------------------------------------
// | User: ShouKun Liu  |  Email:24147287@qq.com  | Time:2017/3/26 14:24
// +----------------------------------------------------------------------
// | TITLE: this to do?
// +----------------------------------------------------------------------

namespace app\demo\controller;

use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\common\TokenParams;
use think\Cache;
use think\Controller;
use think\Request;

class Auth extends Controller
{

    private $parameters;
    private $key = 'phoenix';
    private $password = 'fU9wboOsRx9JQDA2';
    private $version = '8.8.8';

    //前置操作
//    protected $beforeActionList = [
//        'auth',
//    ];

    /**
     * 登录用户信息
     * @var array
     */
    protected static $userInfo = [];
    /**
     * 缓存KEY
     */
    const CACHE_KEY = "TOKEN_";
    const TOKEN_KEY = 'access_token';
    /**
     * 可以不用检验登录信息的url
     * @var array
     */
    protected static $ignoreUrl = [
        'demo/auth/getAccessToken',
        'demo/auth/makeSign',
        'orderfrontend/SynOrder/status',
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
            //签名
            $token = input(self::TOKEN_KEY);
            if($token != $this->makeSign()){
                echo (json_encode(['code'=>2000011, 'msg'=>'The Token is expired !']));die;
            }

//            $token = input(self::TOKEN_KEY);
//            if(empty($token)){
//                echo (json_encode(['code'=>2000010, 'msg'=>'no have access_token']));die;
//            }
//            $auth = (new RedisClusterBase())->get(self::CACHE_KEY . $token);
//            if (!$auth) {
//                echo (json_encode(['code'=>2000011, 'msg'=>'The Token is expired !']));die;
//            }
        }


    }


    /**
     * 生成签名
     * @param string $flag 生成签名标识
     * @return string
     */
    public function makeSign($flag='')
    {
        // 设置校验时间戳
        !$this->getParameter('_timestamp') && $this->setParameter('_timestamp', date('YmdH'));
        // 设置密码
        !$this->getParameter('_password') && $this->setParameter('_password', $this->password);
        // 设置版本号
//        !$this->getParameter('_version') && $this->setParameter('_version', $this->version);
        //签名步骤一：按字典序排序参数
        ksort($this->parameters);
        $string = $this->toUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . '&' . $this->key.$flag;
        //签名步骤三：MD5加密
        $result = md5($string);
        //所有字符转为小写
        $sign = strtolower($result);

        return $sign;
    }

    /**
     * 设置参数
     * @param string $key
     * @param string $value
     */
    public function setParameter($key = '', $value = '')
    {
        if (!is_null($value) && !is_bool($value)) {
            $this->parameters[$key] = $value;
        }
    }

    /**
     * 获取参数值
     * @param $key
     * @return string
     */
    public function getParameter($key)
    {
        return isset($this->parameters[$key]) ? $this->parameters[$key] : '';
    }


    /**
     * @return string
     * @internal param array $params
     */
    public function toUrlParams()
    {
        $buff = "";
        foreach ($this->parameters as $k => $v) {
//            if ($k != "sign" && !is_null($v) && $k != '_url' && $k != '_file') {
                $buff .= $k . "=" . (is_array($v) ? json_encode($v) : $v) . "&";
//            }
        }
        $buff = trim($buff, "&");

        return $buff;
    }

    /**
     * 客户端获取access_token
     * @return array
     */
    public function getAccessToken()
    {
        $paramData = input();
        //参数校验
        $validate = $this->validate($paramData,(new TokenParams())->TokenRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        //验证账号密码
        if($paramData['account'] == 'phoenix' && $paramData['password'] == 'fU9wboOsRx9JQDA2'){
            $token = CommonLib::createGuid(true);
            //缓存2小时10分钟
            (new RedisClusterBase())->set(self::CACHE_KEY.$token,$token,7500);
            return apiReturn(['code'=>200,'data'=>['access_token'=>$token,'expires_in'=>7200]]);
        }else{
            return apiReturn(['code'=>10001,'msg'=>'Your account name or password is incorrect.']);
        }
    }

    /**
     * 退出
     * @return array
     */
    public function Logout()
    {
        $token = input('access_token');
        if(empty($token)){
            return apiReturn(['code'=>10001,'msg'=>'no have access_token.']);
        }
        (new RedisClusterBase())->rm(Self::CACHE_KEY . $token);
        exit;
    }


}