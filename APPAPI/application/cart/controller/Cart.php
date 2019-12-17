<?php
namespace app\cart\controller;

use app\cart\model\CartModel;
use app\common\params\cart\CartParams;
use app\demo\controller\Auth;
use app\cart\services\CartService;
use think\Request;

/**
 * 购物车接口类
 */
class Cart extends Auth
{
    public $CartService;
    public $CartModel;

    /*public function _initialize()
    {
        $this->CartService = new CartService();
    }*/

    public function __construct()
    {
        parent::__construct();
        $this->CartService = new CartService();
        $this->CartModel = new CartModel();
    }

    /**
     * 获取支付方式
     * @return mixed
     */
    public function getPayType(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CartParams())->getPayTypeRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $data = $this->CartService->getPayType($paramData);
        if(false == $data){
            return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 【同步购物车至数据库专用】
     * @return mixed
     */
    public function addTempCartKey(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new CartParams())->addTempCartKeyRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $result = $this->CartModel->addTempCartKey($paramData);
        if(true !== $result){
            return apiReturn(['code'=>1000000021, 'msg'=>'请求失败'.$result]);
        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }
}
