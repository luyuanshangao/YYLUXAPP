<?php
namespace app\mall\controller;

use app\common\controller\Base;
use think\Exception;
use app\common\helpers\RedisClusterBase;


/**
 * Cart接口
 * @author gz
 * 2018-07-23
 */
class Cart extends Base
{
    private $redis;
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
    }

    /**
     * 获取cartInfo
     */
    public function getCartInfo(){
        $paramData = request()->post();
        $CustomerId = $paramData['customer_id'];
        $result = $this->redis->get('ShoppingCart_'.$CustomerId);

        if(isset($result[$CustomerId]['StoreData'])){
            $data = array();
            foreach ($result[$CustomerId]['StoreData'] as $k => $v){
                foreach ($v['ProductInfo'] as $k1 => $v1){
                    $data[] = $k1;
                }
            }
            return apiReturn(['code'=>200, 'data'=>$data]);
        }else{
            return apiReturn(['code'=>1000000021, 'msg'=>'the data is empty']);
        }
    }

   

}
