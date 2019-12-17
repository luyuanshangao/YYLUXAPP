<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\model\Cart as CartModel;
use think\Cache;
use think\Controller;
use think\Log;

class Cart extends AppBase
{
    public $model = null;
    protected $noNeedLogin = ['*'];
    public function __construct()
    {
        parent::__construct();
        $this->model = new CartModel();
    }

    /*
     * 加入购物车
     */
    public function add()
    {
        $singleRule=[
            ['product_id', 'require|number', "产品已经下架|产品已经下架|产品已经下架"],
            ['sku_id','require|number|>:0', "产品已经下架|产品已经下架|产品已经下架"],
            ['qty', 'require|integer|>:0', "数量不正确|数量不正确|数量不正确"],
        ];
        $post = $this->request->param();
        $result = $this->validate($post,$singleRule);
        if(true !== $result){
            // 验证失败 输出错误信息
            return $this->result('',1000,$result);
        }
        $CartModel=new CartModel();
        $spuId=$post['product_id'];
        $skuId=$post['sku_id'];

        /* 产品和库存判断
        $product=$CartModel->getProduct($spuId,$skuId);
        if(empty($product['product_price'])){
            Log::record('产品不存在'.json_encode($post).'$product'.json_encode($product),'error');
            $this->error('产品不存在');
        }*/
        $where['customer_id']=$this->uid;
        $where['product_id']=$post['product_id'];
        $where['sku_id']=$post['sku_id'];
        $user_cart=$this->model->get($where);
        if(!empty($user_cart)){
            $cart_data['is_check']=1;//默认选中
            $cart_data['qty']=$post['qty']+$user_cart['qty'];
            $res=$user_cart->save($cart_data);
        }else{
            $cart_data['product_id']=$post['product_id'];
            $cart_data['sku_id']=$post['sku_id'];
            $cart_data['qty']=$post['qty'];
            $cart_data['customer_id']=$this->uid;
            $cart_data['is_check']=1;//默认选中
            $res=$this->model->save($cart_data);
        }
        return $this->result($res);
    }

    /*
     * 删除购物车
     */
    public function del()
    {
        $singleRule=[
            'id' => 'require|number',
        ];
        $post = $this->request->post();
        $result = $this->validate($post,$singleRule);
        if(true !== $result){
            // 验证失败 输出错误信息
            return $this->result('',1001,$result);
        }
        $where['id']=$post['id'];
        $where['customer_id']=$this->uid;
        $count = $this->model->where($where)->delete($where);
        return $this->result($count,200,'删除成功');
    }

    /*
     * 更新购物车
     */
    public function save()
    {
        $post = $this->request->post();
        if(empty($post)){
            return $this->result('',1002,'数据不能为空');
        }
        $singleRule=[
            'id' => 'require|number',
            'qty' => 'integer|>:0',
            //'is_check' => 'integer|>:0',
        ];

        $result = $this->validate($post,$singleRule);
        if(true !== $result){
            // 验证失败 输出错误信息
            return $this->result('',1003,$result);
        }
        //禁止同时为空
        if(empty($post['qty'])&&empty($post['is_check'])){
            return $this->result('',1003,'数据错误');
        }
        if(empty($post['qty'])){
            $da['qty']=$post['qty'];
        }
        if(empty($post['is_check'])){
            $da['is_check']=$post['is_check'];
        }
        $where['id']=$post['id'];
        $where['customer_id']=$this->uid;
        $res = $this->model->save($da,$where);
        return $this->result($res);
    }

    /*
     * 获取用户购物车列表
     */
    public function index()
    {
        $where['customer_id']=$this->uid;
        $list= $this->model->getCarList($where);
        return $this->result($list);
    }

    /*
    * 去CheckOut页面
    */
    public function goToCheckOut()
    {
        $post = $this->request->post();
        $data=$post['data'];
        if(empty($data)){
            return $this->result('',1004,'数据不能为空');
        }
        foreach($data as $key=>$value){
            $singleRule=[
                //'id' => 'number',//立即购买就不是必填
                'product_id' => 'require|number',
                //'product_price' => 'require',
                'sku_id' => 'require|number|>:0',
                'qty' => 'require|integer|>:0',
            ];

            $result = $this->validate($value,$singleRule);
            if(true !== $result){
                // 验证失败 输出错误信息
                return $this->result('',1005,$result);
            }
            //判断库存
            $skuId=$value['sku_id'];
            $inventory =SkuSku::hasStock($skuId);
            if(empty($inventory)){
                $this->error('库存不足');
            }
        }

        $res=Cache::set('goToCheckOut'.$this->uid,$data,7200);
        $code=$res?200:1600;
        return $this->result($res,$code);
    }

    public function test(){
        $skuId=1103127;
        $inventory =SkuSku::hasStock($skuId);
        var_dump($inventory);die;
    }


}