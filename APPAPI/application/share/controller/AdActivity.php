<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;

/**
 *广告管理
 */
class AdActivity extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    /*
     * 获取广告
     * */
    public function getAdActivityByKey(){
        $Key = input("Key");
        $res = model("AdActivity")->getAdActivityByKey($Key);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
}
