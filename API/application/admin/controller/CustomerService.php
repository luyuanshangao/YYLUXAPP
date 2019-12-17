<?php
namespace app\admin\controller;
use app\admin\model\CustomerService as CustomerServiceModel;
use app\common\params\admin\CustomerServiceParams;
use think\cache\driver\Redis;
use think\Controller;
use think\Exception;
use vendor\aes\aes;
use think\log;
class CustomerService extends Controller
{
  /**
   * 后台admin获取
   * 根据订单号查询获取产品的信息
   * [OrderProductExport description]
   * @auther wang  2019-05-21
   */
  public function OrderProductExport(){
      if($data = request()->post()){
          $where = [];
          if(!empty($data['order_number']) ){
              $where['order_number'] = ['in',$data['order_number']] ;
          }

          $CustomerService = model("CustomerService")->OrderProductExport($where,$data);
          return $CustomerService;
      }
      return apiReturn(['code'=>1002,'msg'=>'空参数']);
  }

    /**
     * 获取订单信息
     * [OrderProductExport description]
     * @auther wang  2019-05-21
     */
    public function getOrderInformation(){
        $params = request()->post();
        $validate = $this->validate($params,(new CustomerServiceParams())->getOrderInformation());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        $CustomerServiceModel = new CustomerServiceModel();
        $result = $CustomerServiceModel->getOrderInformation($params);
        return apiReturn(['code'=>200,'data'=>$result]);
    }

}
