<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\services\Firebase;
use think\Log;

class Test extends AppBase
{

    public function test(){
        $params=input();
        Log::record('$params'.json_encode($params));
        var_dump($params);
        $Firebase=new Firebase();
        $res= $Firebase->send($params);
        Log::record('$params'.json_encode($res));
        $this->result($res);
    }
}
