<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/11/5
 * Time: 13:39
 */
namespace app\app\controller;

use app\app\dxcommon\BaseApi;
use app\common\controller\AppBase;

class CreditCard extends AppBase
{
    public function saveCreditCard()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->saveCreditCard($data);
        return $res;
    }

    public function getCreditCard()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->getCreditCard($data);
        return $res;
    }

    public function delCreditCard()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->delCreditCard($data);
        return $res;
    }

    public function AddCreditCard()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->AddCreditCard($data);
        return $res;
    }

    public function UpdateTokenStatus()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->UpdateTokenStatus($data);
        return $res;
    }

    public function GetCreditCardById()
    {
        $data = input();
        $BaseApi = new BaseApi();
        $res = $BaseApi->GetCreditCardById($data);
        return $res;
    }


}