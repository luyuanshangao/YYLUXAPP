<?php
/**
 * Created by PhpStorm.
 * User: yxh
 * Date: 2017/6/17
 * Time: 19:14
 */
namespace app\app\exception;

use think\exception\Handle;
use think\exception\HttpException;
use think\Request;
class Http extends Handle
{
    public function render(\Exception $e)
    {
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
        }

        if (!isset($statusCode)) {
            $statusCode = 280;
        }
        $request = Request::instance();
        $result = [
            'code' => $statusCode,
            'msg'  => $e->getMessage(),
            'data' => $request = $request->url()
        ];
        return json($result, $statusCode);
    }
}