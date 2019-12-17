<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;

/**
 * 公共头部信息
 */
class Header extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 头部
     * @return mixed
     */
    public function index(){
        //语种
        $langMenu = config("lang");
        //币种
        $CurrencyMenu = config("Currency");
        //国家
        $countryMenu = (new DxRegion())->getHeaderCountry();

        $str = "<head><meta charset='UTF-8'><p><strong>这是头部</strong></p></head>";
        return apiReturn(['code'=>200,'data'=>$str]);
    }

    /**
     * 运往国家列表修改
     */
    public function shipToRegionUpdate(){

    }

    /**
     * 切换币种
     * @param
     * @return json
     */
    public function changeMoney(){
        /*收集页面所有的sku，再发送请求获取每个sku的价格*/

    }

    /**
     * 语种
     */
    public function langs(){
        $langMenu = config("Language");

        return apiReturn(['code'=>200,'data'=>$langMenu]);
    }

    public function getBaseData(){
        $data = array();
        //语种
        $data['langMenu'] = config("Language");
        //币种
        $data['currencyMenu'] = config("Currency");
        //国家
        $data['countryMenu'] = (new DxRegion())->getHeaderCountry();
        return apiReturn(['code'=>200,'data'=>$data]);
    }
}
