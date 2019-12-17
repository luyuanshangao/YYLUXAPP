<?php
namespace app\common\controller;
class Token
{

    private $parameters;
    private $key = 'phoenix';
    private $password = 'fU9wboOsRx9JQDA2';
    private $version = '8.8.8';

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
        //!$this->getParameter('_version') && $this->setParameter('_version', $this->version);
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






}