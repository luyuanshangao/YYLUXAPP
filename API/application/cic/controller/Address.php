<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use app\common\controller\BaseApi;

class Address extends Base
{
    /*
     * 获取地址
    */
    public function getRegion(){
        $ParentID = input("ParentID",0);
        $res = model("region")->getRegion($ParentID);
        if(empty($res)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
 * 保存用户收货地址
 * @param int $ID
 * @param string
 * @param string
 * @Return: array
 * */
    public function saveAddress(){
        try{
            vendor('aes.aes');
            $aes = new aes();
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Address.saveAddress");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $data['AddressID'] = input("post.AddressID",'');
            $data['IsDefault'] = input("post.IsDefault");
            $data = array_filter($data);
            $data['CustomerID'] = input("post.CustomerID");
            $data['ContactName'] = input("post.ContactName");
            $Street1 = input("post.Street1");
            $data['Street1'] = !empty($Street1)?$aes->encrypt($Street1,'Address','Street1'):'';
            $Street2 = input("post.Street2",'');
            $data['Street2'] = !empty($Street2)?$aes->encrypt($Street2,'Address','Street2'):'';
            $data['CityCode'] = input("post.CityCode");
            $data['City'] = input("post.City");
            $data['ProvinceCode'] = input("post.ProvinceCode");
            $data['Province'] = input("post.Province");
            $data['Country'] = input("post.Country");
            $Mobile = input("post.Mobile");
            $data['Mobile'] = !empty($Mobile)?$aes->encrypt($Mobile,'Address','Mobile'):'';
            $Phone = input("post.Phone");
            $data['Phone'] = !empty($Phone)?$aes->encrypt($Phone,'Address','Phone'):'';
            $Email = input("post.Email");
            $PostalCode = input("post.PostalCode");
            $data['PostalCode'] = !empty($PostalCode)?$aes->encrypt($PostalCode,'Address','Zip'):'';
            $CountryCode = input("post.CountryCode");
            $data['CountryCode'] = !empty($CountryCode)?trim($CountryCode):'';
            $data['FirstName'] = input("post.FirstName");
            $data['LastName'] = input("post.LastName");
            $data['CardID'] = input("post.CardID");
            $data['CPF'] = input("post.CPF");
            /*如果没有传入用户ID*/
            if(empty($data['CustomerID'])){
                return apiReturn(['code'=>1001]);
            }
            if($data['Country']){
                $base_api = new BaseApi();
                $Country_data = $base_api->getRegion(['Code'=>trim($data['CountryCode']),'Name'=>trim($data['Country'])]);
                if(empty($Country_data['data'])){
                    /*当查询不到国家信息是，记录日志 20190429 kevin*/
                    Log::write("CountryCode does not exist,paramData:".json_encode($paramData));
                    return apiReturn(['code'=>1002,'msg'=>"CountryCode does not exist"]);
                }
            }
            if($Email && is_email($Email) == true){
                $email_array = explode("@",$Email);
                $EmailDomainName = $email_array[1];
                $EmailUserName = $aes->encrypt($email_array[0],'Address','EmailUserName');//加密邮件前缀
                $data['EmailUserName'] = $EmailUserName;
                $data['EmailDomainName'] = $EmailDomainName;
            }
            $Address = model('Address');
            //获取用户地址数量
            if(!isset($data['AddressID'])){
                $addres_count = $Address->getCount($data['CustomerID']);
                if(empty($data['AddressID'])){
                    if($addres_count>=10){
                        return apiReturn(['code'=>1054]);
                    }
                }
            }else{
                $addres_data = $Address->getAddress(['AddressID'=>$data['AddressID'],'CustomerID'=>$data['CustomerID']]);
                if(!$addres_data){
                    return apiReturn(['code'=>1002,'msg'=>"Address does not exist"]);
                }
            }
            $res = $Address->saveAddress($data);
            /*当地址保存成功*/
            if($res>0){
                /*判断保存的地址是否是默认地址，是的话要将其他地址设置为非默认*/
                if(isset($data['IsDefault']) && $data['IsDefault']==1){
                    if(empty($data['AddressID'])){
                        $data['AddressID'] = $res;
                    }
                    $this->setDefault(['AddressID'=>$data['AddressID']]);
                }
                if(isset($data['AddressID']) && $data['AddressID']>0){
                    $address_id = $data['AddressID'];
                }else{
                    $address_id = $res;
                }
                return apiReturn(['code'=>200,'data'=>['address_id'=>$address_id]]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
* 获取用户收货地址
* @param int $ID
* @param int CustomerID
* @Return: array
* */
    public function getAddress(){
        vendor('aes.aes');
        $aes = new aes();
        $CustomerID = input("CustomerID");
        $AddressID = input("AddressID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $Address = model('Address');
        if(empty($AddressID)){
            $res = $Address->getAddresses($CustomerID);
            if(!empty($res)){
                foreach ($res as $key=>$value){
                    $res[$key]['Street1'] = !empty($value['Street1'])?$aes->decrypt($value['Street1'],'Address','Street1'):'';
                    $res[$key]['Street2'] = !empty($value['Street2'])?$aes->decrypt($value['Street2'],'Address','Street2'):'';
                    $res[$key]['Phone'] = !empty($value['Phone'])?$aes->decrypt($value['Phone'],'Address','Phone'):'';
                    $res[$key]['Mobile'] = !empty($value['Mobile'])?$aes->decrypt($value['Mobile'],'Address','Mobile'):'';
                    $res[$key]['PostalCode'] = !empty($value['PostalCode'])?$aes->decrypt($value['PostalCode'],'Address','Zip'):'';
                    $res[$key]['CityCode'] = !empty($value['CityCode'])?trim($value['CityCode']):'';
                    $res[$key]['ProvinceCode'] = !empty($value['ProvinceCode'])?trim($value['ProvinceCode']):'';
                    $res[$key]['CountryCode'] = trim($value['CountryCode']);
                    if(!empty($res[$key]['EmailUserName']) && !empty($res[$key]['EmailUserName'])) {
                        $EmailUserName = $aes->decrypt($res[$key]['EmailUserName'],'Address','EmailUserName');//加密邮件前缀
                        $res[$key]['Email'] = $EmailUserName.'@'.$res[$key]['EmailDomainName'];
                    }else{
                        $res[$key]['Email'] = '';
                    }
                    if(isset($res[$key]['EmailUserName'])){
                        unset($res[$key]['EmailUserName']);
                    }
                    if(isset($res[$key]['EmailUserName'])){
                        unset($res[$key]['EmailUserName']);
                    }
                }
            }
        }else{
            $res = $Address->getAddress(['AddressID'=>$AddressID,"CustomerID"=>$CustomerID]);
            if(!empty($res)){
                $res['Street1'] = !empty($res['Street1'])?$aes->decrypt($res['Street1'],'Address','Street1'):'';
                $res['Street2'] = !empty($res['Street2'])?$aes->decrypt($res['Street2'],'Address','Street2'):'';
                $res['Phone'] = !empty($res['Phone'])?$aes->decrypt($res['Phone'],'Address','Phone'):'';
                $res['Mobile'] = !empty($res['Mobile'])?$aes->decrypt($res['Mobile'],'Address','Mobile'):'';
                $res['PostalCode'] = !empty($res['PostalCode'])?$aes->decrypt($res['PostalCode'],'Address','Zip'):'';
                if($res['EmailUserName']) {
                    $EmailUserName = $aes->decrypt($res['EmailUserName'],'Address','EmailUserName');//加密邮件前缀
                    $res['Email'] = $EmailUserName.'@'.$res['EmailDomainName'];
                }else{
                    $res['Email'] = '';
                }

            }
        }

        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 获取用户默认收货地址
* @param int $ID
* @param int CustomerID
* @Return: array
* */
    public function getDefaultAddres(){
        vendor('aes.aes');
        $aes = new aes();
        $CustomerID = input("CustomerID");
        $AddressID = input("AddressID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $Address = model('Address');
        if(empty($AddressID)){
            $res = $Address->getDefaultAddres($CustomerID);
            if(!empty($res)){
                $res['Street1'] = !empty($res['Street1'])?$aes->decrypt($res['Street1'],'Address','Street1'):'';
                $res['Street2'] = !empty($res['Street2'])?$aes->decrypt($res['Street2'],'Address','Street2'):'';
                $res['Phone'] = !empty($res['Phone'])?$aes->decrypt($res['Phone'],'Address','Phone'):'';
                $res['Mobile'] = !empty($res['Mobile'])?$aes->decrypt($res['Mobile'],'Address','Mobile'):'';
                $res['PostalCode'] = !empty($res['PostalCode'])?$aes->decrypt($res['PostalCode'],'Address','Zip'):'';
                if($res['EmailUserName']) {
                    $EmailUserName = $aes->decrypt($res['EmailUserName'],'Address','EmailUserName');//加密邮件前缀
                    $res['Email'] = $EmailUserName.'@'.$res['EmailDomainName'];
                }else{
                    $res['Email'] = '';
                }
            }
        }else{
            return apiReturn(['code'=>1001]);
        }
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 删除用户收货地址
* @param int $AddressID
* @Return: array
* */
    public function delAddress(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Address.delAddress");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $CustomerID = $paramData["CustomerID"];
        $AddressID = $paramData["AddressID"];
        if(empty($AddressID) || empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $Address = model('Address');
        $where['CustomerID'] = $CustomerID;
        $where['AddressID'] = ["IN",$AddressID];
        $data['DeleteTime'] =time();
        $res = $Address->saveAddress($data,$where);
        if($res>0){
            return $this->setDefault(['CustomerID'=>$CustomerID]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 设置默认用户收货地址
* @param int $AddressID
* @Return: array
* */
    public function setDefault($address_data=''){
        try{
            $res = 1;
            $paramData = !empty($address_data)?$address_data:request()->post();
            /*$validate = $this->validate($paramData,"Address.setDefault");
            if(true !== $validate){
                return apiReturn(['code'=>1001,"msg"=>$validate]);
            }*/
            if(empty($paramData['AddressID']) && empty($paramData['CustomerID'])){
                return apiReturn(['code'=>1001]);
            }
            $Address = model('Address');
            /*如果有用户地址ID*/
            if(!empty($paramData['AddressID'])){
                $Address_data = $Address->getAddress(['AddressID'=>$paramData['AddressID']]);
                /*第一步，先将用户全部地址设置为非默认地址*/
                if($Address_data){
                    $data1['IsDefault'] = 0;
                    $Address->saveAddress($data1,['CustomerID'=>$Address_data['CustomerID']]);
                }else{
                    return apiReturn(['code'=>10012]);
                }
                /*第二步，将指定的地址设置为默认*/
                $data['AddressID'] = $paramData['AddressID'];
                $data['IsDefault'] =1;
                $res = $Address->saveAddress($data);
            }else{
                /*判断是否存在默认地址，没有则设置最新的为默认地址*/
                $default_where['CustomerID'] = $paramData['CustomerID'];
                $default_where['IsDefault'] =1;
                $default_where['DeleteTime'] = 0;
                $default_address = $Address->getAddress($default_where);
                if(empty($default_address)){
                    $new_where['CustomerID'] = $paramData['CustomerID'];
                    $new_where['DeleteTime'] = 0;
                    $new_address = $Address->getAddress($new_where);
                    if(!empty($new_address)){
                        $this->setDefault(['AddressID'=>$new_address['AddressID']]);
                    }
                }
            }
            if($res>0){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
