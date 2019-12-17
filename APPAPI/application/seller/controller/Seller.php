<?php
namespace app\seller\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\params\seller\seller\ResetPasswordParams;
use app\common\params\seller\seller\UpdateSellerExtensionParams;
use app\common\params\seller\seller\UpdateSellerParams;
use app\seller\model\UserInfo;
use app\seller\model\ShopData;
use think\Db;


/**
 * 供应商接口
 */
class Seller extends Base
{
    public $userInfoModel;
    public function __construct()
    {
        parent::__construct();
        $this->userInfoModel = new \app\seller\model\UserInfo();
        $this->ShopData = new ShopData();
    }

    /*
     * 查询供应商信息
     */
    public function lists(){
    	$paramData = request()->post();
    	try{
    		$data = $this->userInfoModel->sellerLists($paramData);
            //过滤敏感信息
            CommonLib::removeSensitive(['seller_id','password','first_name','last_name'], $data);
    		return apiReturn(['code'=>200, 'data'=>$data]);
    	}catch (Exception $e){
    		return apiReturn(['code'=>1002, 'data'=>'error']);
    	}
    }

    /*
     * 查询供应商详情
     */
    public function get(){
        $paramData = request()->post();
        if (!isset($paramData['user_id']) && !isset($paramData['true_name'])) {
            return apiReturn(['code'=>1002, 'data'=>'user_id or true_name one can not be empty']);
        }
        try{
            $data = $this->userInfoModel->getSeller($paramData);
            //过滤敏感信息
            CommonLib::removeSensitive(['user_id','password'], $data);

            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }



    /*
         * 查询发送信息seller
         */
    public function getSendMessageSeller(){
        $paramData = request()->post();
        if (!isset($paramData['user_data']) || !isset($paramData['field_type'])) {
            return apiReturn(['code'=>1002, 'data'=>'user_data 和 field_type 不能为空']);
        }
        try{
            switch ($paramData['field_type']){
                case 1:
                    $where['true_name'] = ['in',$paramData['user_data']];
                    break;
                case 2:
                    $where['id'] = ['in',$paramData['user_data']];
                    break;
                case 3:
                    $where['email'] = ['in',$paramData['user_data']];
            }
            $data = $this->userInfoModel->getSendMessageSeller($where);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }


    /*
     * 查询供应商详情
     */
    public function getSellerName(){
        $paramData = request()->post();
        if (!isset($paramData['user_ids'])) {
            return apiReturn(['code'=>1002, 'data'=>'user_ids require']);
        }
        try{
            $data = $this->userInfoModel->getSellerName($paramData);
            //过滤敏感信息
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    public function update(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new UpdateSellerParams())->rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'data'=>$validate]);
        }
        $validate = $this->validate($paramData,(new UpdateSellerExtensionParams())->rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'data'=>$validate]);
        }

        //过滤参数
        $serverParams['user'] = CommonLib::handleForm((new UpdateSellerParams()),$paramData);
        $serverParams['user_extension'] = CommonLib::handleForm((new UpdateSellerExtensionParams()),$paramData);

        try{
            $result = $this->userInfoModel->updateSeller($paramData['user_id'],$serverParams);
            if(false == $result){
                return apiReturn(['code'=>1002, 'data'=>[]]);
            }
            return $result;
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>$e]);
        }
    }

    public function del(){
        $paramData = request()->post();
        if (!isset($paramData['user_id'])) {
            return apiReturn(['code'=>1002, 'data'=>'user_id require']);
        }
        if (!isset($paramData['op_desc'])) {
            return apiReturn(['code'=>1002, 'data'=>'op_desc require']);
        }
        if (!isset($paramData['op_name'])) {
            return apiReturn(['code'=>1002, 'data'=>'op_name require']);
        }
        try{
            $result = $this->userInfoModel->delSeller($paramData);
            if($result){
                return apiReturn(['code'=>200, 'data'=>[]]);
            }
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    /**
     * 重置密码
     * @return bool
     */
    public function resetPassword(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new ResetPasswordParams())->rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'data'=>$validate]);
        }
        //过滤参数
        $serverParams = CommonLib::handleForm((new ResetPasswordParams()),$paramData);

        try{
            $result = $this->userInfoModel->resetPassword($paramData['user_id'],$serverParams);
            if($result == false){
                return apiReturn(['code'=>1002, 'data'=>'error']);
            }
            return $result;
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'data'=>'error']);
        }
    }

    public function LogisticsList(){
       // $data['page']=20;
       // $data['page_size']=20;
       // $result = UserInfo::LogisticsList($data);//dump($result);
       // return $result;
       if($data = request()->post()){

              $result = UserInfo::LogisticsList($data);
              if($result){
                   return $result;
              }else{
                   return apiReturn(['code'=>1002, 'data'=>'获取不到数据']);
              }
        }
       // return $result;
    }
     /**
     * 添加物流
     * auther Wang   2018-04-08
     */
    public function AddLogistics(){
//         $data = array(
//   "country" => "Albania-AL, Algeria-DZ",
//   "where" =>array(
//     "0" =>array(
//       "freight" =>"11",
//       "shippingServiceID" => "0",
//       "shippingServiceText" =>"标准物流运费",
//       "time_slot" =>"12",
//     ),
//     "1" =>array(
//       "freight" => "12",
//       "shippingServiceID" => "1",
//       "shippingServiceText" =>"经济物流运费",
//       "time_slot" => "12",
//     ),
//     "2" =>array(
//       "freight" =>"12",
//       "shippingServiceID" => "2",
//       "shippingServiceText" =>"快速物流运费",
//       "time_slot" => "12",
//     ),
//     "3" =>array(
//       "freight" => "12",
//       "shippingServiceID" =>"3",
//       "shippingServiceText" =>"专线物流运费",
//       "time_slot" =>"222",
//     ),
//   ),
//   "remarks" =>"32323",
//   "add_author" =>"admin",
//   "access_token" =>"e3b4c395f2501d9d20d50b65c694ad43",
// );$AddJudgeResult = UserInfo::AddJudge($data);dump($AddJudgeResult);$result = UserInfo::AddLogistics($data);dump($result);exit;
        if($data = request()->post()){
              $AddJudgeResult = UserInfo::AddJudge($data);
              if($AddJudgeResult){
                   $result = UserInfo::AddLogistics($data);
                   return $result;
              }else{
                   return apiReturn(['code'=>1002, 'data'=>'存在已添加数据']);
              }
        }
    }
     /**修改物流
     * [EditLogistics description]
     * auther Wang   2018-04-09
     */
    public function EditLogistics(){
//       $data=array(
//           'edit_author'=>111,
//           'country' =>'Equatorial Guinea-GQ',
//           'where' =>array(
//             '0' =>array(
//               'freight' =>'231',
//               'shippingServiceID' =>'0',
//               'shippingServiceText' => '标准物流运费',
//               'time_slot' => '23',
//             ),
//             '1' =>array(
//               'freight' =>'231',
//               'shippingServiceID' =>'1',
//               'shippingServiceText' =>'经济物流运费',
//               'time_slot' =>'23',
//             ),
//             '2' =>array(
//               'freight' =>'231',
//               'shippingServiceID' =>'2',
//               'shippingServiceText' =>'快速物流运费',
//               'time_slot' =>'23',
//             ),
//             '3' =>array(
//               'freight' =>'231',
//               'shippingServiceID' =>'3',
//               'shippingServiceText' =>'专线物流运费',
//               'time_slot' =>'23',
//             ),
//           ),
//           'remarks' =>'2323',
//           'type' =>'2',
//           'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
//         );
// $result = UserInfo::EditLogistics($data);
//             return $result;
       if($data = request()->post()){
            $result = UserInfo::EditLogistics($data);
            return $result;
       }
    }
    /**物流删除
     * [EditLogistics description]
     * auther Wang   2018-04-09
     */
    public function deleteLogistics(){
        if($data = request()->post()){
            $result = UserInfo::deleteLogistics($data);
            return $result;
        }
    }

    /**
     * 获取自营seller ID
     * @return mixed
     */
    public function getSelfSupport(){
        try{
            $data = $this->userInfoModel->getSellerByWhere(['is_self_support'=>1],'id');
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }
    /**
     * 根据店铺ID获取店铺名
     * [shop_name description]
     * @return [type] [description]
     */
    public function shop_name(){
        if($data = request()->post()){
            $result = $this->ShopData->shop_name($data);
           // $result = model("ShopData")->shop_name($data);
           return apiReturn(['code'=>200, 'data'=>$result]);
        }else{
           return apiReturn(['code'=>100, 'data'=>'空参数']);
        }

    }

}
