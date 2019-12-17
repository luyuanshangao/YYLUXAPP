<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/11/8
 * Time: 16:09
 */
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\dxcommon\BaseApi;

class MyWish extends AppBase
{
    public $baseApi;
    public function __construct()
    {
        parent::__construct();
        $this->baseApi = new BaseApi();
    }

    public function isWish()
    {
        $params = request()->post();
        $data =  $this->baseApi->isWish($params);
        return $data;
    }

    public function getWishList()
    {
        $params = request()->post();
        $data =  $this->baseApi->getWishList($params);
        return $data;
    }

    public function getWishProductList()
    {
        $params = request()->post();
        $data =  $this->baseApi->getWishProductList($params);
        return $data;
    }

    public function delWish()
    {
        $params = request()->post();
        $data =  $this->baseApi->delWish($params);
        return $data;
    }

    public function addWish()
    {
        $params = request()->post();
        $data =  $this->baseApi->addWish($params);
        return $data;
    }
}
