<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 收货地址模型
 * @author
 * @version Kevin 2018/3/25
 */
class Address extends Model{
    public function getCount($CustomerID){
        $db = Db::connect('db_cic');
        $where['CustomerID'] = $CustomerID;
        $where['DeleteTime'] = 0;
        $res = $db->name("address")->where($where)->count();
        return $res;
    }

    /*
    * 保存用户收货地址
    * */
    public function saveAddress($data,$where=''){
        $db = Db::connect('db_cic');
        if(empty($data['AddressID']) && empty($where)){
            $count = $this->getCount($data['CustomerID']);
            if($count<1){
                $data['IsDefault'] = 1;
            }
            $data['CreateTime'] = time();
            $res = $db->name('address')->insertGetId($data);
        }else{
            $data['UpdateTime'] = time();
            $res = $db->name('address')->where($where)->update($data);
        }
        return $res;
    }

    /*
    * 获取用户全部收货地址
    * */
    public function getAddresses($CustomerID){
        $db = Db::connect('db_cic');
        $where['CustomerID'] = $CustomerID;
        $where['DeleteTime'] = 0;
        $where['Country'] = ["NEQ",""];
        $res = $db->name("address")->where($where)->field("AddressID,CustomerID,ContactName,EmailUserName,EmailDomainName,
        FirstName,LastName,Street1,Street2,City,Province,Country,CountryCode,Mobile,Phone,Email,PostalCode,IsDefault,CardID,
        ProvinceCode,CityCode,CreateTime,UpdateTime,DeleteTime,CPF")->order("IsDefault desc,AddressID desc")->select();
        return $res;
    }

    /*
    * 获取用户全部收货地址
    * */
    public function getDefaultAddres($CustomerID){
        $db = Db::connect('db_cic');
        $where['CustomerID'] = $CustomerID;
        $where['IsDefault'] = 1;
        $where['DeleteTime'] = 0;
        $where['Country'] = ["EXP","IS NOT NULL"];
        $where['Country'] = ["NEQ",""];
        $res = $db->name("address")->where($where)->field("AddressID,CustomerID,ContactName,EmailUserName,EmailDomainName,FirstName,LastName,Street1,Street2,City,Province,Country,CountryCode,Mobile,Phone,Email,PostalCode,IsDefault,CardID,ProvinceCode,CityCode,CPF")->find();
        return $res;
    }

    /*
    * 获取用户单条收货地址
    * */
    public function getAddress($where){
        $db = Db::connect('db_cic');
        $res = $db->name("address")->where($where)->order("AddressID","DESC")->field("AddressID,CustomerID,ContactName,EmailUserName,EmailDomainName,FirstName,LastName,Street1,Street2,City,Province,Country,CountryCode,Mobile,Phone,Email,PostalCode,IsDefault,CardID,ProvinceCode,CityCode,CPF")->find();
        return $res;
    }

    /*
    * 获取信用卡用户单条收货地址
    * */
    public function getCardAddress($CardID){
        $db = Db::connect('db_cic');
        $where['CardID'] = $CardID;
        $res = $db->name("address")->where($where)->field("AddressID,CustomerID,ContactName,EmailUserName,EmailDomainName,FirstName,LastName,Street1,Street2,City,Province,Country,CountryCode,Mobile,Phone,Email,PostalCode,IsDefault,CardID,CPF")->find();
        return $res;
    }
}