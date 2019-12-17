<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class Subscriber extends Base
{
    public function __construct(){
        parent::__construct();
        /*每月发送coupon次数*/
        $this->OneMonthSendCouponNumber = 1;
        /*指定每月发放优惠券的日期*/
        $this->SendCounponDay = 1;
    }
    /*
 * 新增用户订阅
 * @param int $ID
 * @param string
 * @param string
 * @Return: array
 * */
    public function addSubscriber($paramData = ""){
        $paramData = !empty($paramData)?$paramData:request()->post();
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
            /*获取订阅邮箱是否存在*/
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
                    if(empty($data['CustomerId'])){
                        /*根据邮箱获取用户ID*/
                        $customer_where['EmailUserName'] = $EmailUserName;
                        $customer_where['EmailDomainName'] = $EmailDomainName;
                        $CustomerId = model("Customer")->isCustomer("email",$customer_where);
                        if($CustomerId){
                            /*判断用户是否订阅,如果用户未订阅直接绑定此订阅邮箱*/
                            $user_subscriber = model("Subscriber")->getSubscriber(['CustomerId'=>$CustomerId,'Active'=>1]);
                            if(!$user_subscriber){
                                $data['CustomerId'] = $CustomerId;
                            }
                        }
                    }
                    $res = model("Subscriber")->addSubscriber($data);
                    if($res>0){
                        return apiReturn(['code'=>200]);
                    }else{
                        return apiReturn(['code'=>1002]);
                    }
            }else{
                    /*判断订阅用户是否未绑定用户ID 20190506 kevin*/
                    if(empty($user_subscriber['CustomerId']) && !empty($data['CustomerId'])){
                        $update_data['CustomerId'] = $data['CustomerId'];
                        $res = model("Subscriber")->updateSubscriber($where,$update_data);
                        if($res>0){
                            return apiReturn(['code'=>200]);
                        }else{
                            return apiReturn(['code'=>1002]);
                        }
                    }else{
                        /*根据邮箱获取用户ID*/
                        $customer_where['EmailUserName'] = $EmailUserName;
                        $customer_where['EmailDomainName'] = $EmailDomainName;
                        $CustomerId = model("Customer")->isCustomer("email",$customer_where);
                        if($CustomerId){
                            /*判断用户是否订阅,如果用户未订阅直接绑定此订阅邮箱*/
                            $user_subscriber = model("Subscriber")->getSubscriber(['CustomerId'=>$CustomerId,'Active'=>1]);
                            if(!$user_subscriber){
                                $update_data['CustomerId'] = $CustomerId;
                                $res = model("Subscriber")->updateSubscriber($where,$update_data);
                                if($res>0){
                                    return apiReturn(['code'=>200]);
                                }else{
                                    return apiReturn(['code'=>1002]);
                                }
                            }else{
                                return apiReturn(['code'=>1002,'msg'=>"The subscriber account already exists"]);
                            }
                        }else{
                            return apiReturn(['code'=>1002,'msg'=>"The subscriber account already exists"]);
                        }
                    }
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

    /*
     * 获取未发送优惠券用户并返回用户email
     * */
    public function getSendCouponSubscriberEmail(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Subscriber.GetSimpleSubscribers");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            /*站点*/
            $where['siteId'] = isset($paramData['siteId'])?$paramData['siteId']:1;
            /*订阅状态*/
            $where['Active'] = 1;
            $where['CustomerId'] = ['gt',0];
            /*本月内未发送优惠券的用户*/
            $where['EndSendEmailTime'] = ['lt',strtotime(date("Y-m-".$this->SendCounponDay))];
            /*to do 一个月多次发送*/
            $where['SendCouponNumber'] = ['gt',0];
            $pageIndex = isset($paramData['pageIndex'])?$paramData['pageIndex']:1;
            $totalRecord = isset($paramData['totalRecord'])?$paramData['totalRecord']:200;
            $CustomersData = model("Subscriber")->getSendCouponSubscriberEmail($where,$pageIndex,$totalRecord);
            vendor('aes.aes');
            $aes = new aes();
            if(isset($CustomersData['CustomersData']) && !empty($CustomersData['CustomersData'])){
                /*获取用户ID集合*/
                $CustomerIds = array();
                foreach ($CustomersData['CustomersData'] as $key=>$value){
                    array_push($CustomerIds,$value['CustomerId']);
                    $EmailUserName = $aes->decrypt($value['EmailUserName'],'Customer','EmailUserName');//加密邮件前缀
                    $CustomersData['CustomersData'][$key]['Email'] = $EmailUserName.'@'.$value['EmailDomainName'];
                    unset($CustomersData['CustomersData'][$key]['EmailUserName']);
                    unset($CustomersData['CustomersData'][$key]['EmailDomainName']);
                    unset($CustomersData['CustomersData'][$key]['SendCouponNumber']);
                }
                $updateSubscriberWhere['CustomerId'] = ["in",$CustomerIds];
                $updateSubscriberData['EndSendEmailTime'] = time();
                if($this->OneMonthSendCouponNumber == 1){
                    $updateSubscriberData['SendCouponNumber'] = 0;
                }else{//to do 当每月不止发一次优惠券时
                    return apiReturn(['code'=>1002,"msg"=>"Function to be developed"]);
                }
                $update_res = model("Subscriber")->updateSubscriber($updateSubscriberWhere,$updateSubscriberData);
                if($update_res){
                    return apiReturn(['code'=>200,'data'=>$CustomersData]);
                }else{
                    Log::write("getSendCouponSubscriberEmail update subscriber error,data:".json_encode(request()->post()));
                    return apiReturn(['code'=>1002,"msg"=>"update data is empty"]);
                }
            }else{
                return apiReturn(['code'=>1002,"msg"=>"data is empty"]);
            }
        }catch (\Exception $e){
            Log::write("getSendCouponSubscriberEmail error:".$e->getMessage());
            Log::write("getSendCouponSubscriberEmail data:".json_encode(request()->post()));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取发送coupon邮件模板
     * */
    public function getSendCouponEmailTemplate(){
        $templet_value_id = 12;
        $NewProducts = getCouponNewProducts();
        $NewProductsEndKey = array_keys(end($NewProducts));
        $mall_url = "https:".MALLDOMAIN;
        $new_products_html = '<table style="MARGIN: 0px auto" border="0" cellspacing="0" cellpadding="0" width="100%">
                        <tbody>
                            <tr>
                            <td bgcolor="#d5f1ff" width="37"></td>';
        foreach ($NewProducts as $key=>$value){
            $LowPriceArr = explode('.',$value['LowPrice']);
            $new_products_html.= '
                                <td bgcolor="#ffffff" width="205">
                                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                        <tbody>
                                            <tr>
                                                <td height="165" valign="center" align="middle"><a href="'.$mall_url.'/p/'.$value['_id'].'" target="_blank" rel="noreferrer"><img style="BORDER-BOTTOM: 0px; BORDER-LEFT: 0px; DISPLAY: block; BORDER-TOP: 0px; BORDER-RIGHT: 0px" title="'.$value['Title'].'" alt="'.$value['Title'].'" src="https:'.IMG_DXCDN.$value['FirstProductImage'].'" width="164" height="164"></a></td>
                                            </tr>
                                            <tr>
                                                <td style="PADDING-BOTTOM: 5px; LINE-HEIGHT: 14px; PADDING-LEFT: 10px; PADDING-RIGHT: 10px; FONT-FAMILY: Arial, Helvetica, sans-serif; FONT-SIZE: 12px; PADDING-TOP: 5px" height="40" align="middle"><a style="COLOR: #666; TEXT-DECORATION: none;height: 42px;overflow: hidden;display: block;margin-top: 6px;" href="'.$mall_url.'/p/'.$value['_id'].'" target="_blank" rel="noreferrer" title="'.$value['Title'].'">'.$value['Title'].'</a></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td style="LINE-HEIGHT: 12px; COLOR: black; FONT-SIZE: 18px" height="30" width="100" align="middle"><span style="FONT-SIZE: 28px">$'.$LowPriceArr[0].'</span>.'.$LowPriceArr[1].' </td>
                                                            </tr>
                                                            <tr>
                                                                <td bgcolor="#ffffff" height="10"></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>';
            if($key<$NewProductsEndKey){
                $new_products_html.= '<td bgcolor="#ffffff" width="2"><img src="http://c.dx.com/edm/201505/20150530/SubscribeSuccess/Dottedline.jpg"></td>';
            }
        }
        $new_products_html.= '<td bgcolor="#d5f1ff" width="34"></td>
                            </tr>
                        </tbody>
                    </table>';
        $data = getEmailTemplate($templet_value_id,[],['new_products_html'=>$new_products_html,'start_time'=>date("F d,Y",time()),'end_time'=>date("F d,Y",strtotime("+60 day"))]);
        if($data){
            return apiReturn(['code'=>200,'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 根据条件查询用户ID
     * */
    public function getSubscriberCustomerIds(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Subscriber.getSubscriberCustomerIds");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            /*站点*/
            $where['siteId'] = isset($paramData['siteId'])?$paramData['siteId']:1;
            /*订阅状态*/
            $where['Active'] = isset($paramData['Active'])?$paramData['Active']:1;
            $where['CustomerId'] = ['gt',0];
            /*未发送优惠券的用户*/
            if($paramData['SendCouponNumber']){
                $where['SendCouponNumber'] = ['lt',$paramData['SendCouponNumber']];
            }else{
                return apiReturn(['code'=>1002,'msg'=>"SendCouponNumber is not empty!"]);
            }
            /*一个月内已经获取信息未发送优惠券的用户*/
            $where['EndSendEmailTime'] = ['lt',strtotime('-1month')];
            /*页数*/
            $pageIndex = isset($paramData['pageIndex'])?$paramData['pageIndex']:1;
            /*返回条数*/
            $totalRecord = isset($paramData['totalRecord'])?$paramData['totalRecord']:200;
            $CustomersIds = model("Subscriber")->getSubscriberCustomerIds($where,$pageIndex,$totalRecord);
            if($CustomersIds){
                return apiReturn(['code'=>200,'data'=>$CustomersIds]);
            }else{
                return apiReturn(['code'=>1006,"msg"=>"data is empty"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*增加coupon发送次数*/
    public function incSendCouponNumber(){
        try{
            $paramData = request()->post();
            /*$validate = $this->validate($paramData,"Subscriber.incSendCouponNumber");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }*/
            if(!isset($paramData['CustomerId'])){
                return apiReturn(['code'=>1002,"msg"=>"CustomersId can not be empty"]);
            }
            $where['CustomerId'] = ["in",$paramData['CustomerId']];
            $where['Active'] = 1;
            $res = model("Subscriber")->incSendCouponNumber($where,1);
            if($res){
                return apiReturn(['code'=>200,"data"=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
      * 获取发送优惠券用户
      * @Add:20190507 kevin
      * @Return: array
      * */
    public function getSendCouponCustomersIds(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Subscriber.getSubscriberCustomerIds");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            /*发送定时任务的时间*/
            $SendCounponDayTime = strtotime(date("Y-m-".$this->SendCounponDay,time()));
            if(time() < $SendCounponDayTime){
                return apiReturn(['code'=>1006,"msg"=>"Time to send coupons is not up this month"]);
            }
            /*订阅状态*/
            $where['s.Active'] = isset($paramData['Active'])?$paramData['Active']:1;
            /*未发送优惠券的用户*/
            if($paramData['SendCouponNumber']){
                $where['s.SendCouponNumber'] = ['lt',$paramData['SendCouponNumber']];
            }else{
                return apiReturn(['code'=>1002,'msg'=>"SendCouponNumber is not empty!"]);
            }
            /*指定每月日期固定发放优惠券*/
            $where['s.EndSendEmailTime'] = ['lt',$SendCounponDayTime];
            /*页数*/
            $pageIndex = isset($paramData['pageIndex'])?$paramData['pageIndex']:1;
            /*返回条数*/
            $totalRecord = isset($paramData['totalRecord'])?$paramData['totalRecord']:200;
            /*在2015年前注册并且在2015-2019年有下过单的订阅用户*/
            $where1['c.OrderCount'] = ["gt",0];
            $where1['c.RegisterOn'] = ["elt",1420041600];
            $where_first = array_merge($where,$where1);
            $CustomersIds = model("Subscriber")->getSendCouponCustomersIds($where_first,$pageIndex,$totalRecord);
            /*如果没有数据则获取2015-2019年注册的订阅用户*/
            if(empty($CustomersIds)){
                $end_time = strtotime(date("Y-m-01"));
                $where2['c.RegisterOn'] = ["BETWEEN",[1420041600,$end_time]];
                $where_second = array_merge($where,$where2);
                $CustomersIds = model("Subscriber")->getSendCouponCustomersIds($where_second,$pageIndex,$totalRecord);
            }
            if($CustomersIds){
                return apiReturn(['code'=>200,'data'=>$CustomersIds]);
            }else{
                return apiReturn(['code'=>1006,"msg"=>"data is empty"]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
