<?php
namespace app\common\controller;
use app\common\helpers\RedisClusterBase;
use think\Controller;
use think\Log;
use think\Request;
/**
 * 基础类
 * by chick 2018-03-15
 */
class Base extends Controller
{

    private $parameters;
    private $key = 'phoenix';
    private $password = 'fU9wboOsRx9JQDA2';
//    private $version = '8.8.8';

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
        'base/auth/makeSign',
        'common/base/makeSign',//解决doCurl问题
        'demo/auth/getAccessToken',
        'mallextend/product/queryProductId',
        'share/CacheHandle/getToken',
        'cic/Customer/LoginForToken',
        'share/SendFile/sendStreamFile',
        'mall/product/getProductListBySpus',//罗导调用--活动页面
        'mall/coupon/addCouponByActivityPage',//罗导调用--活动页面
        'mall/product/getProductListBySkus',//罗导调用--活动页面
        'mallextend/product/listByCategory',//刘锐调用
        'mallextend/product/updateSalesRank',//刘锐调用
        'log/index/operationLog',//写日志
        'share/ExchangeRate/rates',//汇率接口地址

    ];

    /**
     * 权限校验
     * @throws BusinessException
     */
    public function auth()
    {
        $request = Request::instance();
        //白名单
        if (!in_array($request->pathinfo(), self::$ignoreUrl) && THINK_ENV != CODE_RUNTIME_LOCAL) {
            //签名
            $token = input(self::TOKEN_KEY);
            if(empty($token)){
//                Log::record("authFailure_params:".json_encode(input()));
                echo json_encode(['code'=>2000011,'msg'=>'no have access_token']);die;
            }
            //跑脚本代码专用
            if($token == 'dx123'){
                return;
            }

            //检测服务器状态专用
            if($token == 'a2f72346e68978755153e4a2d0fb2d4c'){
                return;
            }
            //app的token验证
//            $appAuth = (new RedisClusterBase())->get(self::CACHE_KEY . $token);

            if($token != $this->makeSign()){
                //判断是否是前后一个小时的签名 add by zhongning 20190627
                $this->setParameter('_timestamp', date('YmdH',strtotime('-1 hour')));
                $before = $this->makeSign();
                $this->setParameter('_timestamp', date('YmdH',strtotime('+1 hour')));
                $after = $this->makeSign();
                if($token != $before && $token != $after){
//                    if($appAuth === false){
                        echo (json_encode(['code'=>2000011, 'msg'=>'The Token is expired !']));die;
//                    }
                }
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
     *
     * @return string
     */
    public function makeSign()
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
        $string = $string . '&' . $this->key;
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
    public function accessToken()
    {
//        $paramData = input();
//        //参数校验
//        $validate = $this->validate($paramData,(new TokenParams())->TokenRules());
//        if(true !== $validate){
//            return apiReturn(['code'=>1002, 'msg'=>$validate]);
//        }
//        //验证账号密码
//        //TODO admin用户接口
//        if($paramData['account'] == 'admin' && $paramData['password'] == 'admin'){
////            $token = CommonLib::createGuid(true);
//            $token = 'e3b4c395f2501d9d20d50b65c694ad43';
//            //缓存5分钟
//            (new RedisClusterBase())->set(self::CACHE_KEY.$token,$token,300);
//            echo (json_encode(['code'=>200,'access_token' => $token,'expires_in'=>300]));die;
//        }else{
//            echo (json_encode(['code'=>10001, 'msg'=>'Your account name or password is incorrect.']));die;
//        }
    }

    /**
     * 退出
     * @return array
     */
    public function Logout()
    {
        $token = input('access_token');
        if(empty($token)){
            echo (json_encode(['code'=>101, 'msg'=>'no have access_token']));die;
        }
        Cache::rm(Self::CACHE_KEY . $token);
        exit;
    }

}
