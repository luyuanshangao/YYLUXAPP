<?php
namespace app\common\controller;

use think\Controller;
use think\Log;
use think\Request;
use think\Db;
/**
 * 订单查询接口统一验证类
 * by tinghu.liu 2019-09-03
 */
class BaseOrder extends Controller
{
    private $userName = 'dxApiRoot';
    private $password = 'Dx+Root159357';

    //前置操作
    protected $beforeActionList = [
        'auth',
    ];

    /**
     * 可以不用检验登录信息的url
     * @var array
     */
    protected static $ignoreUrl = [
//        'base/auth/makeSign',

    ];

    const TOKEN_KEY = 'access_token';

    public function __construct(){

        parent::__construct();
        define('P_CLASS', 'ProductClass');
        define('PRO_CLASS', 'dx_product_class');//Nosql数据表
        define('S_CONFIG', 'dx_sys_config');//Nosql数据表

    }

    /**
     * 权限校验
     * @throws BusinessException
     */
    public function auth()
    {
        $request = Request::instance();
        //白名单
        if (
            !in_array($request->pathinfo(), self::$ignoreUrl)
        ) {

            $token = input(self::TOKEN_KEY);
            //get访问专用
            if($token == 'a465d669be1d874be3cca' && $request->isGet()){
                return;
            }

            $authorization = $request->header('Authorization');
            $auth_passed = false;
            $auth_user_name = '';
            $auth_user_password = '';
            if (strpos(strtolower($authorization), 'basic') === 0){
                $auth_arr = explode(' ',$authorization);
                if (
                    isset($auth_arr[1])
                    && !empty($auth_arr[1])
                ){
                    $auth_info = explode(':', base64_decode($auth_arr[1]));
                    $auth_user_name = isset($auth_info[0])?$auth_info[0]:$auth_user_name;
                    $auth_user_password = isset($auth_info[1])?$auth_info[1]:$auth_user_password;
                }
                if ($auth_user_name == $this->userName && $auth_user_password == $this->password){
                    $auth_passed = true;
                }
            }
            if (!$auth_passed){
                Log::record('baseOrder_authorization_fail:'.$authorization);
                echo json_encode(['code'=>3000, 'msg'=>'No access rights.']);die;
            }
        }
    }

    /**
     * 获取字典配置的某项值的文本
     */
    final function getDictValue(array $dict,$currentVale){
        $result ='';
        if(!empty($dict)){
            foreach ($dict as $key => $value){
                if(count($value) ==2){
                    if($value[0] == $currentVale){
                        $result = $value[1];
                    }
                }
            }
        }
        return $result;
    }

    //字典数据的获取
    public function dictionariesQuery($val){

        $PayemtMethod = Db::connect("db_mongodb")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
        $data = explode(";",$PayemtMethod['ConfigValue']);
        $list = array();
        foreach ($data as $key => $value) {
            if(!empty($value)){
                $list[] = explode(":",$value);
            }

        }
        return $list;
    }

    /**
     * 获取配置
     * @param $val
     * @return mixed
     */
    public function getSysConfig($val){
        $data=(Db::connect("db_mongodb")->table(S_CONFIG)->where(['ConfigName'=>$val])->find());
        return $data['ConfigValue'];
    }

    /**
     * 过滤查找
     */
    final static function filterArrayByKey($input,$key,$val,$key1=null,$val1=null){
        $retArray = array_filter($input, function($t) use ($key,$val,$key1,$val1){
            if($t[$key] == $val){
                if(!is_null($key1)){
                    return $t[$key1] == $val1;
                }
                return $t[$key] == $val;
            }
        });
        if(count($retArray)== count($retArray, 1)){
            return $retArray;
        }else{
            return array_shift($retArray);
        }
    }
}
