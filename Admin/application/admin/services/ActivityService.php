<?php
namespace app\admin\services;

use app\common\helpers\CommonLib;
use app\admin\dxcommon\ExcelTool;
use app\admin\model\ActivityModel;
use think\Cache;
use think\Exception;
use think\Log;

class ActivityService extends BaseService{

    private $seller_id          = 666;
    private $seller_name        = 'DX';

    private $actModel;
    public function __construct(){
        $this->actModel = new ActivityModel();
    }

    public function addActFileLog($filename,$activity_id){
        return $this->actModel->addActFileLog($filename,$activity_id);
    }

    public function saveFileData($filename,$fileId,$activity_id){
        $tool = new ExcelTool();

        //$header = ['spu'=>'spu','sku'=>'sku','discount'=>'discount','number'=>'number'];
        //$data = $tool->import($header,$filename);
        //读取第一个excel子文件
        $header = ['spu','sku','discount','number'];
        $data = $tool->getFileData($header,$filename);
        if( empty($data) ){
            return ['code'=>400,'msg'=>'读取文件错误！'];
        }
        
        return $this->actModel->saveActData($data,$fileId,$activity_id);
    }

    public function checkAct($activity_id, $type=0){
        return $this->actModel->checkAct($activity_id, $type);
    }

    public function subProduct($activity_id){
        $data = $this->actModel->getActData(['status'=>0,'activity_id'=>$activity_id]);

        if( empty($data) ){
            Log::record('没有获取到upload表status为0的信息','error');
            return ['code'=>400,'msg'=>'没有获取到对应产品信息'];
        }

        $time = time();

        foreach ($data as $value) {
            $product = $this->actModel->getProData((int)$value['spu']);

            if( empty($product) ){
                $this->actModel->updateActData($value['id'],['status'=>2]);
                Log::record('根据spu['.$value['spu'].']没有获取到product表信息','error');
                continue;
            }
            //产品表中当前spu里面的所有sku信息
            $pro_skus = $product['Skus'];

            $act_spu = array(
                'activity_id'   => $value['activity_id'],
                'product_id'    => $value['spu'],
                'status'        => 1,//审核中
                'add_time'      => $time,
                'seller_id'     => $this->seller_id,
                'seller_name'   => $this->seller_name,
            );

            $act_sku = [];
            foreach ($pro_skus as $pro_sku) {
                //如果dx_activity_upload表中sku为空，则将pro_skus中所有产品都加入到活动中
                if( empty($value['sku']) || $value['sku'] == $pro_sku['Code'] ){
                    $discount = round( (intval(100-$value['discount']))/100,2 );
                    $act_price= round( $discount*$pro_sku['SalesPrice'],2 );

                    $act_sku[] = array(
                        'activity_id'   => $value['activity_id'],
                        'sku'           => $pro_sku['_id'],
                        'product_id'    => $value['spu'],
                        'code'          => $pro_sku['Code'],
                        'sales_price'   => $pro_sku['SalesPrice'],
                        'activity_price'=> $act_price,
                        'discount'      => intval(100-$value['discount']),
                        'activity_inventory' => $value['number'],
                        'status'        => 1,//审核中
                        'set_type'      => 2,//默认是批量设置
                        'add_time'      => $time,
                        'seller_id'     => $this->seller_id,
                        'seller_name'   => $this->seller_name,
                    );
                }
            }
            
            if( empty($act_sku) ){
                $this->actModel->updateActData($value['id'],['status'=>2]);
                Log::record('根据spu['.$value['spu'].']没有获取到product表sku信息','error');
                continue;
            }

            //通过事务操作多张表
            $this->actModel->addActProData($value['id'],$act_spu,$act_sku);
        }
        return ['code'=>200,'msg'=>'发布成功'];
    }

    public function checkActData($activity_id){
        $data = $this->actModel->getActData(['status'=>1,'activity_id'=>$activity_id]);

        if( empty($data) ){
            Log::record('没有获取到upload表status为1的信息','error');
            return ['code'=>400,'msg'=>'没有获取到对应产品信息'];
        }

        $time = time();

        foreach ($data as $value) {
            $product = $this->actModel->getProData((int)$value['spu']);

            if( empty($product) ){
                $this->actModel->updateActData($value['id'],['status'=>2]);
                Log::record('根据spu['.$value['spu'].']没有获取到product表信息','error');
                continue;
            }
            //产品表中当前spu里面的所有sku信息
            $pro_skus = $product['Skus'];
            //这个既用于审核组装下面数据使用，也作为更新activity_spu，activity_sku的条件
            $act_spu = array(
                'activity_id'   => $value['activity_id'],
                'product_id'    => $value['spu'],
                'status'        => 1,
                'seller_id'     => $this->seller_id,
            );

            $act_sku = [];
            foreach ($pro_skus as $pro_sku) {
                //如果dx_activity_upload表中sku为空，则将pro_skus中所有产品都加入到活动中
                if( empty($value['sku']) || $value['sku'] == $pro_sku['Code'] ){
                    $discount = round( (intval(100-$value['discount']))/100,2 );
                    $act_price= round( $discount*$pro_sku['SalesPrice'],2 );

                    $act_sku[] = array(
                        'activity_id'   => $value['activity_id'],
                        'sku'           => $pro_sku['_id'],
                        'product_id'    => $value['spu'],
                        'code'          => $pro_sku['Code'],
                        'sales_price'   => $pro_sku['SalesPrice'],
                        'activity_price'=> $act_price,
                        'discount'      => intval(100-$value['discount']),
                        'activity_inventory' => $value['number'],
                        'status'        => 2,
                        'set_type'      => 2,//默认是批量设置
                        'add_time'      => $time,
                        'seller_id'     => $this->seller_id,
                        'seller_name'   => $this->seller_name,
                    );
                }
            }
            
            if( empty($act_sku) ){
                $this->actModel->updateActData($value['id'],['status'=>2]);
                Log::record('根据spu['.$value['spu'].']没有获取到product表sku信息','error');
                continue;
            }

            //组装数据，用于插入product_active
            $data = $this->comProActData($act_spu,$act_sku,$product);
            if( empty($data) ){
                $this->actModel->updateActData($value['id'],['status'=>2]);
                Log::record('根据spu['.$value['spu'].']组装product_active表数据失败','error');
                continue;
            }
            //插入product_active表
            $res = $this->actModel->addProActData($data);
            if( !$res ){
                $this->actModel->updateActData($value['id'],['status'=>2]);
                Log::record('根据spu['.$value['spu'].']增加product_active表记录失败','error');
                continue;
            }

            //通过事务操作多张表
            $this->actModel->uptActProData($act_spu);
        }
        return ['code'=>200,'msg'=>'审核成功'];
    }

    //组装插入product_active表的数据
    private function comProActData($spuModel,$list_sku,$productModel){

        $DiscountPrice = array();
        $Discount      = array();
        $spu_activity_inventory =0;
        //待更新入库的数组
        $updateModel['ActivityID'] = (int)$spuModel['activity_id'];
        $updateModel['SPU'] = (int)$spuModel['product_id'];

        foreach ($list_sku as $k => $v) {
            $sku_data = $v;
            foreach ((array)$productModel["Skus"] as $ke => $va) {
                if($v['sku'] == $va["_id"]){
                    //活动库存不可以大于在售库存
                    if($sku_data['activity_inventory'] > $va["Inventory"]){
                        //用实际库存作为活动库存
                        $sku_data['activity_inventory']=$va["Inventory"];
                    }

                    $sku_discount = round($sku_data['discount']/100,2);
                    //商城直接使用 N*0.6 这样的方式计算
                    $Discount[] = $sku_discount; 

                    $sku_discount_price = round($sku_data['sales_price'] * $sku_discount,2);
                    if(isset($sku_data['set_type'])  && $sku_data['set_type'] == 2){
                        $sku_discount_price = $sku_data['activity_price'];
                    }

                    $DiscountPrice[] = $sku_discount_price;
                    
                    $spu_activity_inventory += $sku_data['activity_inventory'];

                    $data_array = array(
                        'Discount'      => $sku_discount,
                        'DiscountPrice' => $sku_discount_price,
                        'SalesLimit'    => $sku_data['activity_inventory'],
                        'SetType'       => $sku_data['set_type'],
                    );
                    
                    if(empty($data_array['Discount']) || empty($data_array['DiscountPrice']) || empty($data_array['SalesLimit'])|| empty($data_array['SetType'])){
                        break;
                    }

                    $updateModel["Skus"][$ke]['_id'] = $va["_id"];
                    $updateModel["Skus"][$ke]['Code'] = $va["Code"];
                    $updateModel["Skus"][$ke]['ActivityInfo'] = $data_array;
                }
            }
        }
        if( empty($updateModel["Skus"]) ){
            return false;
        }
        $updateModel['DiscountLowPrice'] = min($DiscountPrice);//折扣后最低价格 --搜索使用
        $updateModel['DiscountHightPrice'] = max($DiscountPrice);//折扣后最高价 --搜索使用
        $updateModel['HightDiscount'] = min($Discount);//最高折扣比例 --按SPU打折，不按skus
        $updateModel['InventoryActivity'] = $spu_activity_inventory; //SPU总的参与活动的库存量
        $updateModel['InventoryActivitySalse'] = (int)0;
        $updateModel['AddTime'] = time();

        unset($productModel);

        return $updateModel;
    }
}
