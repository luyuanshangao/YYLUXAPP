<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Exception;
use vendor\aes\aes;
use app\cic\model\ThirdPartyCustomer as ThirdPartyCustomerModel;

class ThirdPartyCustomer extends Base
{
    /*判断第三方用户是否存在*/
    public function IsExistAccountID(){
        try{
            $id = input("id");
            if(empty($id)){
                return apiReturn(['code'=>1001]);
            }
            $where['ThirdPartyAccountID'] = $id;
            $model = new ThirdPartyCustomerModel();
            $res = $model->IsExistAccountID($where);
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
