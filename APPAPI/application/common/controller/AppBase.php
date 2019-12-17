<?php
namespace app\common\controller;

use app\common\helpers\TokenHelper;
use think\Controller;
use think\exception\HttpException;
use think\Request;
use think\exception\HttpResponseException;
use think\Response;

/**
 * 开发：yxh
 * 功能：token验证
 * 时间：2018-09-02
 */
class AppBase extends Controller
{
    protected $uid = NULL;
    protected $code = 200;
    protected $data = '';
    protected $msg = '';
    protected $noNeedLogin = [];

    public function _initialize()
    {
        $key = Request::instance()->param('token');
        if ($key) {
            $Token = new TokenHelper();
            $this->uid = $Token->getUid($key);

            if (empty($this->uid)) {
                $message = '登录失效,请重新登录';
                throw new HttpException(299, $message);
            }
        } else {
            if (($this->noNeedLogin!=['*']) && !$this->match($this->noNeedLogin)) {
                $message = 'token不能为空';
                throw new HttpException(299, $message);
            }
        }
    }

    /*
     * 是否需要登陆
     */
    public function match($arr = [])
    {
        $request = Request::instance();
        // 是否存在
        if (in_array(strtolower($request->action()), $arr)) {
            return true;
        } else {
            return false;
        }
    }

    protected function result($data, $code = 200, $msg = '', $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            //'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        $type     = $type ?: $this->getResponseType();
        $response = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }

    /*
     * 数据转换
     */
    protected function getParam(&$dest, $destKey, $src, $srcKey, $default = null)
    {
        if (isset($src[$srcKey]) && !empty($src[$srcKey])) {
            $dest[$destKey] = $src[$srcKey];
        }

        if (!empty($default)) {
            if (!isset($src[$srcKey]) || empty($src[$srcKey])) {
                $dest[$destKey] = $default;
            }
        }
    }

}
