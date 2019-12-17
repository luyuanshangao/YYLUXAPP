<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use app\share\model\ExchangeRateModel;
use think\Cache;

/**
 * 汇率管理
 */
class ExchangeRate extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function rates(){

        $data = (new ExchangeRateModel())->selectExchangeRate();
        return json($data);
    }
}
