<?php
namespace app\index\dxcommon;

use think\Controller;
use think\Log;

/**
 * API基类
 * Class API
 * @author tinghu.liu
 * @date 2018-03-28
 * @package app\index\dxcommon
 */
class API extends Controller
{
    /**
     * @var $access_token_str
     */
    protected static $access_token_str;
    public $redis;

    private $parameters;
    private $key = 'phoenix';
    private $password = 'fU9wboOsRx9JQDA2';
    private $version = '8.8.8';

    public function _initialize()
    {
        self::$access_token_str = '?access_token='.$this->getAccessToken();
        $this->redis = new RedisClusterBase();
    }

    /**
     * 获取接口access_token
     * @return mixed
     */
    private function getAccessToken(){

        return $this->makeSign();

        //后期如果API做token请求次数限制的话，可将获取的access_token存进数据库或缓存
//        $data = json_decode(curl_request(config('api_base_url').'/demo/auth/accessToken'), true);
//        $access_token = 'e41246d3cb6c4e154ea3ced5bd393354';
//        if(!empty($data['access_token'])){
//            $access_token = $data['access_token'];
//        }
//        return $access_token;
    }

    /**
     * 请求API地址【所有接口请求统一地址】
     * @param $url 地址
     * @param array $params post数据
     * @param int $type 请求接口类型：1-post，2-get
     * @param int $request_nums 失败时请求次数
     * @return mixed
     */
    protected function requestApi($url, array $params=[], $type=1, $request_nums=3){
        $i = 1;
        $url .= self::$access_token_str;
        do{
//            Log::record('接口请求（'.$i.'）-> url：'.$url.' , -> params：'.json_encode($params));
            if ($i>$request_nums){
                Log::record($url.'接口超过'.$request_nums.'次请求失败');
                break;
            }
            if ($type == 1){ //post
                if (!empty($params)){
                    $data = json_decode(http_post_json($url, json_encode($params)), true);
                }else{
                    $data = json_decode(http_post_json($url), true);
                }
            }else{//get
                $data = json_decode(curl_request($url), true);
            }
//            Log::record('接口请求返回结果（'.$i.'）-> response：'.json_encode($data));
            if (isset($data['code']) && $data['code'] == API_RETURN_SUCCESS){
                break;
            }
            $i++;
            /*if ($i > $request_nums){
                Log::record('接口请求（'.$i.'）-> response：'.json_encode($data));
            }*/
        }while(1);
        return $data;
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
        //!$this->getParameter('_version') && $this->setParameter('_version', $this->version);
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

}
