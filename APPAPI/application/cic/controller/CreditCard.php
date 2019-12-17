<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class CreditCard extends Base
{

    /*
 * 保存用户信用卡
 * @param int $ID
 * @param string
 * @param string
 * @Return: array
 * */
    public function saveCreditCard(){
        try{
            vendor('aes.aes');
            $aes = new aes();
            $data['ID'] = input("ID",'');
            $data['CustomerID'] = input("CustomerID");
            if(empty($data['CustomerID'])){
                return apiReturn(['code'=>1001]);
            }
            $data['FirstSixDigits'] = input("FirstSixDigits");
            $data['LastFourDigits'] = input("LastFourDigits");
            $Token = input("Token",'');
            $data['Token'] = !empty($Token)?$aes->encrypt($Token,'CreditCard','Token'):'';
            $data['Notes'] = input("Notes");
            $data['SiteID'] = input("SiteID",1);
            $data['GatewayMID'] = input("GatewayMID");
            $data['MID'] = input("MID");
            $data['TokenStatus'] = input("TokenStatus");
            $data['CardHolder'] = input("CardHolder");
            $data['TokenRef'] = input("TokenRef");
            $CreditCard = model('CreditCard');
            if($data['ID']){
                $ThirdId_where['CreditCardID'] = $data['ID'];
                $CreditCardThirdId = $CreditCard->getCreditCardThirdId($ThirdId_where);
                $third_data['id'] = $CreditCardThirdId['id'];
                if($CreditCardThirdId['ThirdId'] != input('ThirdId')){//数据库第三方ID和传入的是否一致
                    $third_data['ThirdId'] = input('ThirdId');
                    $third_data['RecordTime'] = time();
                    $third_data['Tip'] = input('Tip');
                }
            }else{
                $third_data['Cicid'] = input("CustomerID");
                $third_data['ThirdId'] = input('ThirdId');
                $third_data['RecordTime'] = time();
                $third_data['Tip'] = input('Tip');
            }
            $third_data['GatewayID'] = input("GatewayID");
            array_filter($third_data);
            $res = $CreditCard->saveCreditCard($data,$third_data);
            if($res>0){
                $addres_data['AddressID'] = input("AddressID",'');
                $addres_data['CustomerID'] = input("CustomerID");
                $addres_data['ContactName'] = input("ContactName");
                $addres_data['Street1'] = input("Street1");
                $addres_data['Street2'] = input("Street2",'');
                $addres_data['City'] = input("City");
                $addres_data['Province'] = input("Province");
                $addres_data['Country'] = input("Country");
                $addres_data['CountryCode'] = input("CountryCode");
                $addres_data['Mobile'] = input("Mobile");
                $addres_data['Phone'] = input("Phone");
                $addres_data['Email'] = input("Email");
                $addres_data['PostalCode'] = input("PostalCode");
                $addres_data['EmailUserName'] = input("EmailUserName");
                $addres_data['EmailDomainName'] = input("EmailDomainName");
                $addres_data['FirstName'] = input("FirstName");
                $addres_data['LastName'] = input("LastName");
                $addres_data['CardID'] = !empty($data['ID'])?$data['ID']:$res;
                $addres_data = array_filter($addres_data);
                if(!empty($addres_data['ContactName'])){
                    $Address = model('Address');
                    $Address->saveAddress($addres_data);
                }
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
* 获取用户信用卡
* @param int $ID
* @param int CustomerID
* @Return: array
* */
    public function getCreditCard(){
        vendor('aes.aes');
        $aes = new aes();
        $CustomerID = input("CustomerID");
        $ID = input("ID",'');
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $ChannelName = input("ChannelName");
        if(!empty($ChannelName)){//网关名称对应枚举
            switch (strtolower($ChannelName)){
                case 'none':
                    $where['GatewayMID'] = 0;
                    break;
                case 'egp':
                    $where['GatewayMID'] = 2;
                    break;
                case 'globlebill':
                    $where['GatewayMID'] = 8;
                    break;
                case 'asiabill':
                    $where['GatewayMID'] = 8;
                    break;
                case 'jcb':
                    $where['GatewayMID'] = 32;
                    break;
            }
        }
        $CreditCard = model('CreditCard');
        if(empty($ID)){
            $where['CustomerID'] = $CustomerID;
            $res = $CreditCard->getCreditCards($where);
            if(!empty($res)){
                foreach ($res as $key=>$value){
                    $res[$key]['Token'] = !empty($value['Token'])?$aes->decrypt($value['Token'],'CreditCard','Token'):'';
                }
            }
        }else{
            $res = $CreditCard->getCreditCard(['ID'=>$ID,'CustomerID'=>$CustomerID]);
            $res['Token'] = !empty($res['Token'])?$aes->decrypt($res['Token'],'CreditCard','Token'):'';
            $address = model('Address')->getCardAddress($ID);
            $res = array_merge($res,$address);
        }

        if($res>0){
            return apiJosn(['code'=>200,'data'=>$res]);
        }else{
            return apiJosn(['code'=>1006]);
        }
    }

    /*
* 删除用户信用卡
* @param int $ID
* @Return: array
* */
    public function delCreditCard(){
        $ID = input("ID");
        $CustomerID = input("CustomerID");
        if(empty($ID) || empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $CreditCard = model('CreditCard');
        $data['ID'] = ["IN",$ID];
        $data['CustomerID'] = $CustomerID;
        //$data['DeleteTime'] = time();
        $res = $CreditCard->delCreditCard($data);
        if($res>0){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 保存用户信用卡
* @param int $ID
* @param string
* @param string
* @Return: array
* */
    public function AddCreditCard(){
        vendor('aes.aes');
        $aes = new aes();
        $data['ID'] = input("ID",'');
        $data['CustomerID'] = input("CustomerID");
        $data['FirstSixDigits'] = input("FirstSixDigits");
        $data['LastFourDigits'] = input("LastFourDigits");
        $Token = input("Token",'');
        $data['Token'] = !empty($Token)?$aes->encrypt($Token,'CreditCard','Token'):'';
        $data['Notes'] = input("Notes");
        $data['SiteID'] = input("SiteID",1);
        $data['GatewayMID'] = input("GatewayMID");
        $data['MID'] = input("MID");
        $data['TokenStatus'] = input("TokenStatus",1);
        $data['CardHolder'] = input("CardHolder");
        $data['TokenRef'] = input("TokenRef");
        $CreditCard = model('CreditCard');
        if(empty($data['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        if(empty($data['ID']) && !empty($data['Token'])){//当没有传入ID但是有Token，判断数据是否已经存在
            $ID = $CreditCard->getCreditCardIDByToken($data['Token']);
            if($ID){
                $data['ID'] = $ID;
            }
        }
        if($data['ID']){
            $ThirdId_where['CreditCardID'] = $data['ID'];
            $CreditCardThirdId = $CreditCard->getCreditCardThirdId($ThirdId_where);
            if($CreditCardThirdId){
                $third_data['id'] = $CreditCardThirdId['id'];
                if($CreditCardThirdId['ThirdId'] != input('ThirdID')){//数据库第三方ID和传入的是否一致
                    $third_data['ThirdId'] = input('ThirdId');
                    $third_data['RecordTime'] = time();
                    $third_data['Tip'] = input('Tip');
                }
            }
        }else{
            $third_data['Cicid'] = input("CustomerID");
            $third_data['ThirdId'] = input('ThirdID');
            $third_data['RecordTime'] = time();
            $third_data['Tip'] = input('Tip');
        }
        $third_data['GatewayID'] = input("GatewayID");
        array_filter($third_data);
        if($third_data['GatewayID'] != 2){
            $third_data = '';
        }
        $res = $CreditCard->saveCreditCard($data,$third_data);
        if($res!=false){
            return apiReturn(['code'=>200,'CreditCardID'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }
/*
 * 更改信用卡状态
 * */
    public function UpdateTokenStatus(){
        $CreditCard = model('CreditCard');
        $paramData = input();
        if(!isset($paramData['CreditCardID'])){
            return apiReturn(['code'=>1001]);
        }
        $where['ID'] = $paramData['CreditCardID'];
        $update_data['TokenStatus'] = isset($paramData['TokenStatus'])?$paramData['TokenStatus']:0;
        try{
            $res = $CreditCard->updataCreditCard($where,$update_data);
            return apiReturn(['code'=>200,'OperationStatus'=>0,'ErrorInfos'=>"Success"]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage(),'OperationStatus'=>1,'ErrorInfos'=>$e->getMessage()]);
        }
    }

    /*
     * 获取信用卡
     * */
    public function GetCreditCardById(){
        vendor('aes.aes');
        $aes = new aes();
        $CreditCard = model('CreditCard');
        $paramData = json_decode(file_get_contents("php://input"),true);
        /*$paramData['CreditCardID'] = input('CreditCardID');
        $paramData['CustomerID'] = input('CustomerID');*/
        if(!isset($paramData['CreditCardID']) && !isset($paramData['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $where['c.ID'] = isset($paramData['CreditCardID'])?$paramData['CreditCardID']:'';
        $where['c.CustomerID'] = isset($paramData['CustomerID'])?$paramData['CustomerID']:'';
        $where = array_filter($where);
        $res = $CreditCard->getCreditCard($where);
        $res['Token'] = !empty($res['Token'])?$aes->decrypt($res['Token'],'CreditCard','Token'):'';
        if($res && isset($res['GatewayMID'])){
            if(empty($res['GatewayID'])){
                $res['GatewayID'] = $res['GatewayMID'];
            }
            return apiReturn(['code'=>200,'OperationStatus'=>0,'ErrorInfos'=>"Success",'CreditCard'=>$res]);
        }else{
            return apiReturn(['code'=>1006,'OperationStatus'=>1,'ErrorInfos'=>"operation failed"]);
        }
    }

    /*
     * 获取信用卡Token
     * */

}
