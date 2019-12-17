<?php
namespace app\cart\controller;

use app\cart\model\CartModel;
use app\common\params\cart\CartParams;
use app\demo\controller\Auth;
use app\cart\services\CartService;
use think\Request;
use think\Log;

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

    //从mongo中获取购物车信息
    public function getCartData(){
        $data = request()->post();
        
        if( empty($data['uid']) ){
            return false;
        }

        $uid = strval($data['uid']);

        $res = $this->CartService->getCartData($uid);
        if( !empty($res) ){
            return ['data'=> base64_encode(json_encode([$uid=>['StoreData'=>$res]]))];
        }
        return false;
    }

    public function setCartData(){

        $data = request()->post();
        
        if( empty($data['data']) ){
            return false;
        }
        $data = json_decode(base64_decode($data['data']),true);

        $uid = array_keys($data);
        if( count($uid)>1 ){
            return false;
        }

        $uid = strval($uid[0]);//转换成字符串
        $data = $data[$uid];

        $data = $this->specialToNomal($data);
        $data['uid'] = $uid;
        
        $res = $this->CartService->setCartData($uid,$data);
        
        if($res){
            return true;
        }

        return false;
    }

    public function delCartData(){
        $data = request()->post();
        
        if( empty($data['uid']) ){
            return false;
        }

        $uid = strval($data['uid']);

        return $this->CartService->delCartData($uid);
    }

    //去掉特殊字符 $
    private function specialToNomal($data){
        $tmp = [];

        foreach ($data as $key => $value) {
            if( $key === '$oid' ){
                $key = '___id';
            }
            if( gettype($value)=='array' || gettype($value)=='object' ){
                $tmp[$key] = $this->specialToNomal((array)$value);
            }else{
                $tmp[$key] = $value;    
            }
        }
        return $tmp;
    }
}
