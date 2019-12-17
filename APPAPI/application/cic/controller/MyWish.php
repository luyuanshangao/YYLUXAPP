<?php
namespace app\cic\controller;
use app\common\controller\Base;
use app\app\services\BaseService;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class MyWish extends Base
{
    /*
* 获取用户收藏列表
* @param int CustomerID
* @Return: array
* */
    public function getWishList(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyWish.getWishList");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $where['CustomerID'] = isset($paramData['CustomerID'])?$paramData['CustomerID']:0;
        $where['CategoryID'] = isset($paramData['CategoryID'])?$paramData['CategoryID']:0;
        if(empty($where['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }

        $where['Tags'] = isset($paramData['Tags'])?$paramData['Tags']:'';
        $where = array_filter($where);
        $where['IsDelete'] = 0;
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $res = model("MyWish")->getWishList($where,$page_size,$page,$path);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
    * 新增用户收藏
    * */
    public function addWish(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"MyWish.addWish");
            if(true !== $validate){
                return apiJosn(['code'=>1002,"msg"=>$validate]);
            }
            $data['CustomerID'] = input("CustomerID");
            if(empty($data['CustomerID'])){
                return apiJosn(['code'=>1001,"msg"=>"CustomerID can not be empty"]);
            }
            $customer_data = model("cic/Customer")->getCustomer($data['CustomerID'],0);
            if(!$customer_data){
                return apiJosn(['code'=>1002,"msg"=>"customer is empty"]);
            }
            $data['Username'] = $customer_data['UserName'];
            $data['SPU'] = input("SPU",0);
            $data['Source'] = input("Source",1);
            $data['PriceWhenAdded'] = input("PriceWhenAdded",0);
            $data['ShippingWhenAdded'] = input("ShippingWhenAdded",0);
            $data['Comments'] = input("Comments");
            $data['Tags'] = input("Tags");
            $data['CategoryID'] = input("CategoryID");
            $data['CategoryName'] = input("CategoryName");
            $data['AddTime'] = time();
            $data['IsDelete'] = 0;
            $data['group_id'] = input("group_id",0);
            if($data['SPU'] >0){
                $count_where['CustomerID'] = $data['CustomerID'];
                $count_where['SPU'] = $data['SPU'];
                $count_where['IsDelete'] = 0;
                $wish_count = model("MyWish")->getWishCount($count_where);
                if($wish_count>0){
                    //处理成功
                    return apiReturn(['code'=>200,'msg'=>'Has been added to the wish list']);
                }
            }else{
                return apiReturn(['code'=>1001,"msg"=>"SPU can not be empty"]);
            }
            $res = model("MyWish")->addWish($data);
            if($res>0){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiJosn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiJosn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
    * 新增用户收藏
    * */
    public function addWishArray(){
        $data_array = request()->post();
        $delete_spu = array();
        foreach ($data_array as $key=>$value){
            $data[$key]['CustomerID'] =$value["CustomerID"];
            if(empty($value["CustomerID"])){
                return apiReturn(['code'=>1001]);
            }
            $delete_spu[$key] = $value["SPU"];
            $data[$key]['Username'] = $value["Username"];
            $data[$key]['SPU'] = $value["SPU"];
            $data[$key]['Source'] = isset($value["Source"])?$value["Source"]:1;
            $data[$key]['PriceWhenAdded'] = isset($value["PriceWhenAdded"])?$value["PriceWhenAdded"]:0;
            $data[$key]['ShippingWhenAdded'] = isset($value["ShippingWhenAdded"])?$value["ShippingWhenAdded"]:0;
            $data[$key]['Comments'] = isset($value["Comments"])?$value["Comments"]:'';
            $data[$key]['Tags'] = isset($value["Tags"])?$value["Tags"]:'';
            $data[$key]['CategoryID'] = isset($value["CategoryID"])?$value["CategoryID"]:0;
            $data[$key]['CategoryName'] = isset($value["CategoryName"])?$value["CategoryName"]:'';
            $data[$key]['AddTime'] = time();
            $data[$key]['IsDelete'] = 0;
        }
        $res = model("MyWish")->addWishAll($delete_spu,$data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 删除用户收藏商品
     * */
    public function delWish(){
        $paramData = request()->post();
        if(!isset($paramData['CustomerID']) || empty($paramData['CustomerID'])){
            return apiReturn(['code'=>1001,'msg'=>"CustomerID is not null"]);
        }
        if(!isset($paramData['ID']) && !isset($paramData["SPU"])){
            return apiReturn(['code'=>1001,'msg'=>"SPU is not null"]);
        }
        if(isset($paramData['ID']) && !empty($paramData['ID'])){
            $where['ID'] = $paramData['ID'];
        }

        if(isset($paramData["SPU"]) && !empty($paramData["SPU"])){
            $where['SPU'] = $paramData["SPU"];
        }
        $where['CustomerID'] = $paramData['CustomerID'];
        $res = model("MyWish")->delWish($where);
        Log::record('$paramData:'.json_encode($paramData).'$res'.json_encode($res));
        if($res>0){
            return apiJosn(['code'=>200,'data'=>1]);
        }else{
            return apiJosn(['code'=>1006,'msg'=>'Cancel collection failed','data'=>0]);
        }
    }

    /*
     * 判断是否收藏
     * */
    public function isWish(){
        $data['CustomerID'] = input("CustomerID");
        if(empty($data['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $data['SPU'] = input("SPU",0);
        if(empty($data['SPU'])){
            return apiReturn(['code'=>1001]);
        }
        $data['IsDelete'] = 0;
        $res = model("MyWish")->getWishId($data);
        if($res){
            $data = "true";
        }else{
            $data = "false";
        }
        return apiReturn(['code'=>200,'data'=>$data]);
    }

    /*
     * 获取收藏分类ID
     * */
    public function getWishCategoryID($data = ''){
        $data = !empty($data)?$data:request()->post();
        if(empty($data['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $data['IsDelete'] = 0;
        $res = model("MyWish")->getWishCategoryID($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
* 获取用户收藏列表
* @param int CustomerID
* @Return: array
* */
    public function getWishProductList(){
        try{
            $paramData = request()->post();
            $where['CustomerID'] = isset($paramData['CustomerID'])?$paramData['CustomerID']:0;
            $where['CategoryID'] = isset($paramData['CategoryID'])?$paramData['CategoryID']:0;
            $Currency = isset($paramData['Currency'])?$paramData['Currency']:"USD";
            $Lang = isset($paramData['Lang'])?$paramData['Lang']:"en";
            if(empty($where['CustomerID'])){
                return apiReturn(['code'=>1001]);
            }
            $where['Tags'] = isset($paramData['Tags'])?$paramData['Tags']:'';
            $where = array_filter($where);
            $where['IsDelete'] = 0;
            $page_size = input("page_size",20);
            $page = input("page",1);
            $path = input("path");
            $res = model("MyWish")->getWishList($where,$page_size,$page,$path);
            if($res){
                /*根据列表全部商品ID获取商品详情*/
                $spus = array_column($res['data'],'SPU');
                $ProductController = controller("mallextend/Product");
                if(is_array($spus)){
                    $spus = array_values(array_filter($spus));
                    $ProductLists = $ProductController->getWishProductLists(['ids'=>$spus],$Lang);
                    if($ProductLists['code'] == 200&&!empty($ProductLists['data'])&&is_array($ProductLists['data'])){
                        $BaseService = new BaseService();
                        $CurrencyArray = config("Currency");
                        foreach ($CurrencyArray as $key => $value){
                            if($Currency == $value['Name']){
                                $CurrencyCode = $value['Code'];
                                $res['CurrencyCode'] = $CurrencyCode;
                                break;
                            }
                        }
                        if($Currency!= "USD"){
                            $CurrentProductLists = $BaseService->changeCurrentRate($ProductLists['data'],$Currency);
                            $CurrentProductLists['data'] = is_array($CurrentProductLists)?array_values($CurrentProductLists):$CurrentProductLists;
                            $res['data'] = $CurrentProductLists['data'];
                        }else{
                            $res['data'] = is_array($ProductLists['data'])?array_values($ProductLists['data']):$ProductLists['data'];
                        }
                    }else{
                        $res['data'] = [];
                    }
                }else{
                    $res['data'] = [];
                }

            }
            /*$WishCategoryID = $this->getWishCategoryID(['CustomerID'=>$paramData['CustomerID']]);
            $WishCategoryID['data'] = array_filter($WishCategoryID['data']);
            if(isset($WishCategoryID['data']) && !empty($WishCategoryID['data'])){
                $ProductCategoryController = controller("mallextend/ProductCategory");
                $WishCategoryData = $ProductCategoryController->getCategoryDataByCategoryIDData($WishCategoryID['data']);
                //$data['WishCategoryData'] = $WishCategoryData['data'];
                $data['WishProductList'] = $res;
            }*/
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
