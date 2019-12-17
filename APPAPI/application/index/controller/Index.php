<?php
namespace app\index\controller;
use vendor\aes\aes;
use think\Db;
class Index
{
    public function index()
    {
        //echo "hello word!";
        /*$db=Db::connect('db_cic');
        $data = $db->name('customer')->select();
        dump($data);exit;*/
        //$res=Db::connect('db_user');//->name("customer")->select()
        vendor('aes.aes');
        $aes = new aes();
        $str = $aes->decrypt('RrO9ZJnCNnqyBaCArQgyEw==');//加密邮件
        dump($str);exit;
        $str =$aes-> AESDecryptResponse('1234567$%ABCEDFW','MuqHKCgAnAVATABv4Y3xeg==');
        dump(strtoupper(SHA1('Dx+12345')));exit;///加密密码
        /*Db::connect("")
        db();*/
    }


}
