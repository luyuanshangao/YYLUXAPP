<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class Subscriber extends Base
{

    /*
 * 新增用户订阅
 * @param int $ID
 * @param string
 * @param string
 * @Return: array
 * */
    public function addSubscriber(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Subscriber.addSubscriber");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $data['CustomerId'] = input("CustomerId");
        $Email = input("Email/s");
        if(is_email($Email) == true) {//验证邮箱格式
            $email_array = explode("@",$Email);
            $EmailDomainName = $email_array[1];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
            $data['EmailUserName'] = $EmailUserName;
            $data['EmailDomainName'] = $EmailDomainName;
            $where['Active'] = 1;
            $where['EmailUserName'] = $EmailUserName;
            $where['EmailDomainName'] = $EmailDomainName;
            $user_subscriber = model("Subscriber")->getSubscriber($where);
                if(!$user_subscriber){
                    $data['Active'] = input("Active",1);
                    $data['SiteId'] = input("SiteId",1);
                    $data['ActiveCode'] = input("ActiveCode");
                    $data['CancelActiveCode'] = input("CancelActiveCode");
                    $data['CreateTime'] = time();
                    $data['AddTime'] = time();
                    $data['CancelReasonID'] = input("CancelReasonID");
                    $data['OtherCancelReason'] = input("OtherCancelReason");
                    $data['CancelReasonIDs'] = input("CancelReasonIDs");
                    $res = model("Subscriber")->addSubscriber($data);
                    if($res>0){
                        return apiReturn(['code'=>200]);
                    }else{
                        return apiReturn(['code'=>1002]);
                    }
            }else{
                return apiReturn(['code'=>1002,'msg'=>"The subscriber account already exists"]);
            }
        }else{
            return apiReturn(['code'=>1007]);
        }
    }

    /*
     * 更改用户订阅激活状态
     * */
    public function editSubscriberActive(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Subscriber.editSubscriberActive");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $Active = input("Active",1);
        $res = model("PointsBasicInfo")->editSubscriberActive($CustomerID,$Active);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     *取消订阅
     * */
    public function cancelSubscriber(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Subscriber.cancelSubscriber");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $data['CustomerId'] = input("CustomerId");
        $Email = input("Email/s");
        if(empty($data['CustomerId']) && empty($Email)){
            return apiReturn(['code'=>200]);
        }
        if(!empty($data['CustomerId'])){
            $where['CustomerId'] = $data['CustomerId'];
        }else{
            $email_array = explode("@",$Email);
            $EmailDomainName = $email_array[1];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
            $where['EmailUserName'] = $EmailUserName;
            $where['EmailDomainName'] = $EmailDomainName;
        }
        $data['CancelActiveCode'] = input("CancelActiveCode");
        $data['EditTime'] = time();
        $data['CancelReasonID'] = input("CancelReasonID");
        $data['OtherCancelReason'] = input("OtherCancelReason");
        $data['CancelReasonIDs'] = input("CancelReasonIDs");
        $data = array_filter($data);
        $data['Active'] = 0;
        $res = model("Subscriber")->updateSubscriber($where,$data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 判断用户是否订阅
     * */
    public function checkSubscriber(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Subscriber.checkSubscriber");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        vendor('aes.aes');
        $aes = new aes();
        $CustomerId = input("CustomerId");
        $Email = input("Email/s");
        $SiteID = input("SiteID/d",1);
        if(empty($CustomerId) && empty($Email)){
            return apiReturn(['code'=>200]);
        }
        if(!empty($CustomerId)){
            $where['CustomerId'] = $CustomerId;
        }else{
            $email_array = explode("@",$Email);
            $EmailDomainName = $email_array[1];
            $EmailUserName = $aes->encrypt($email_array[0],'Customer','EmailUserName');//加密邮件前缀
            $where['EmailUserName'] = $EmailUserName;
            $where['EmailDomainName'] = $EmailDomainName;
        }
        $where['SiteID'] = $SiteID;
        $where['Active'] = 1;
        $res = model("Subscriber")->checkSubscriber($where);
        if($res){
            $res['email'] = $aes->decrypt($res['EmailUserName'],'Customer','EmailUserName')."@".$res['EmailDomainName'];
        }
        return $res;
    }

    /*获取订阅信息*/
    public function getSubscriber(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Subscriber.getSubscriber");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if(isset($paramData['type']) && $paramData['type'] == 2){
            $type = 2;
        }else{
            $type = 1;
        }
        vendor('aes.aes');
        $aes = new aes();
        $CustomerId = input("CustomerId");
        $Subscriber = model("Subscriber")->getSubscriber(['CustomerId'=>$CustomerId,'Active'=>1],$type);
        if($Subscriber){
            if($type == 1){
                $EmailUserName = $aes->decrypt($Subscriber['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                $Subscriber['Email'] = $EmailUserName.'@'.$Subscriber['EmailDomainName'];
                unset($Subscriber['EmailUserName']);
                unset($Subscriber['EmailDomainName']);
            }else{
                foreach ($Subscriber as $key=>$value){
                    $EmailUserName = $aes->decrypt($value['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                    $Subscriber[$key]['Email'] = $EmailUserName.'@'.$value['EmailDomainName'];
                    unset($Subscriber[$key]['EmailUserName']);
                    unset($Subscriber[$key]['EmailDomainName']);
                }
            }
            return apiReturn(['code'=>200,'data'=>$Subscriber]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
      * 获取用户订阅用户
      * @param: array
      * @Return: array
      * */
    public function getSubscriberCustomers(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Subscriber.getSubscriberCustomers");
            if(true !== $validate || empty($paramData)){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            if(isset($paramData['EndSendCoupon']) && !empty($paramData['EndSendCoupon'])){
                $where['EndSendCoupon'] = ['lt',$paramData['EndSendCoupon']];
            }
            if(isset($paramData['Active'])){
                $where['Active'] = $paramData['Active'];
            }
            $where['CustomerId'] = ['gt',0];
            $limit = isset($paramData['limit'])?$paramData['limit']:100;
            vendor('aes.aes');
            $aes = new aes();
            $CustomersData = model("Subscriber")->getSubscriberCustomers($where,$limit);
            if($CustomersData){
                foreach ($CustomersData as $key=>$value){
                    $EmailUserName = $aes->decrypt($value['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                    $CustomersData[$key]['Email'] = $EmailUserName.'@'.$value['EmailDomainName'];
                    unset($CustomersData[$key]['EmailUserName']);
                    unset($CustomersData[$key]['EmailDomainName']);
                }
                return apiReturn(['code'=>200,'data'=>$CustomersData]);
            }else{
                return apiReturn(['code'=>1002,"msg"=>"data is empty"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*获取订阅用户信息*/
    public function GetSimpleSubscribers(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Subscriber.GetSimpleSubscribers");
        if(true !== $validate || empty($paramData)){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['siteId'] = isset($paramData['siteId'])?$paramData['siteId']:1;
        $where['Active'] = 1;
        if(isset($paramData['Begin']) && !empty($paramData['Begin'])){
            $where['LastUpdate'] = ['egt',strtotime($paramData['Begin'])];
        }
        if(isset($paramData['End']) && !empty($paramData['End'])){
            $where['LastUpdate'] = ['lt',strtotime($paramData['End'])];
        }
        $pageIndex = isset($paramData['pageIndex'])?$paramData['pageIndex']:1;
        $totalRecord = isset($paramData['totalRecord'])?$paramData['totalRecord']:20;
        $CustomersData = model("Subscriber")->GetSimpleSubscribers($where,$pageIndex,$totalRecord);
        vendor('aes.aes');
        $aes = new aes();
        if(isset($CustomersData['CustomersData']) && !empty($CustomersData['CustomersData'])){
            foreach ($CustomersData['CustomersData'] as $key=>$value){
                $EmailUserName = $aes->decrypt($value['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                $CustomersData['CustomersData'][$key]['Email'] = $EmailUserName.'@'.$value['EmailDomainName'];
                unset($CustomersData['CustomersData'][$key]['EmailUserName']);
                unset($CustomersData['CustomersData'][$key]['EmailDomainName']);
            }
            return apiReturn(['code'=>200,'data'=>$CustomersData]);
        }else{
            return apiReturn(['code'=>1002,"msg"=>"data is empty"]);
        }
    }

    /*
     * 修改最后发送优惠券时间
     * */
    public function updateSubscriberEndSendCoupon(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Subscriber.updateSubscriberEndSendCoupon");
            if(true !== $validate || empty($paramData)){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where['CustomerId'] = $paramData['CustomerId'];
            $CustomersData = model("Subscriber")->updateSubscriberEndSendCoupon($where);
            if($CustomersData){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
