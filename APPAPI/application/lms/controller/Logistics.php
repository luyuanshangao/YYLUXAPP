<?php
namespace app\lms\controller;
use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\common\controller\Base;
use app\lms\model\DxRegion;
use app\lms\dxcommon\BaseApi;
use app\lms\model\UserInfo;
use think\Db;
use app\common\helpers\RedisClusterBase;
use app\lms\model\Logistics AS lms_logistics;//AS lms_logistics

/**
 * LMS系统 接口
 * author: Wang
 * AddTime:2018-04-26
 */
class Logistics extends Base
{
  const shipping_cost_histories = 'shipping_cost_histories';
  const db_mongodb = 'db_mongodb';
  const shipping_cost = 'shipping_cost';

  public function logistics(){

    $date = $data = $_POST;
    if(!$data){
     return json_encode(array('code'=>100,'data'=>'有空数据'));
    }
    if(empty($data['type']) || empty($data['local']) || empty($data['shipping_type'])){
          return json_encode(array('code'=>100,'data'=>'存在为空参数'));
    }
    $logistics = BaseApi::logistics($data); //币种
// return json_encode(array('code'=>200,'aa'=>111,'data'=>3434));
    if($logistics && !empty($logistics)){//return json_encode(array('code'=>200,'aa'=>111,'data'=>$logistics));
        $updateLogistics = UserInfo::update_Logistics($date,$logistics);//return json_encode(array('code'=>200,'aa'=>111,'data'=>$updateLogistics));
        if($updateLogistics){
           return json_encode(array('code'=>200,'data'=>'数据更新成功'));
        }else{
           return json_encode(array('code'=>100,'data'=>'数据更新失败'));
        }
    }else{
        return json_encode(array('code'=>100,'data'=>'LMS没有返回数据'));
    }
  }
  /**
   * 添加或修改seller数据库vat
   * [vat description]
   * @return [type] [description]
   */
  public function vat(){
      $data = request()->post();
      if(!$data){
          return array('code'=>100,'data'=>'数据有误');
      }else{
          $vat = UserInfo::vat($data);
          if($vat){
             return array('code'=>200,'data'=>'数据更新成功');
          }else{
             return array('code'=>100,'data'=>'数据更新失败');
          }
      }
  }
  /**
   * lms到商城获取产品信息
   * [product_information description]
   * @return [type] [description]
   * author: Wang
   * AddTime:2018-04-26
   */
  public function product_information(){
      $data   = request()->post();
      // $data['sku'] = 266405;
      $result = model("UserInfo")->product_information($data);
      return $result;
  }



  /**
   * 把lms数据更新到Seller数据表(改版后 第二个版本)
   * [LogisticsUpdateSeller description]
   * author: Wang
   * AddTime:2018-08-01
   */
  public function LogisticsUpdateSeller(){
     // $data = request()->post();//return $data;
     $data = input();
     $logistics = BaseApi::logistics();
     $result = model("UserInfo")->LogisticsUpdateSeller($logistics);
     return $result;
     // return json_encode($result);
    // exit;
    //  return 121212;
  }
  /**
   * 把Seller数据更新到商城数据表数据表(改版后 第二个版本)
   * [LogisticsUpdateSeller description]
   * author: Wang
   * AddTime:2018-08-02
   */
  public function LogisticsUpdateMall(){
    $result = model("UserInfo")->LogisticsUpdateMall();
    return $result;
  }

  public function redis_dequeue(){
    ignore_user_abort();
    try{
        $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->find();
        if(!empty($list)){
            $result = model("UserInfo")->shipping_cost($list['logistics'],$list['limit']);//return $result;exit;
            return 2;
        }else{
            $result = model("UserInfo")->redis_dequeue();
        }
        if($result == 1){
           return 1;
        }else{
           return 2;
        }

    }catch(Exception $e){
        return $e->getMessage()."\n";
    }
  }


  public function delete_redis(){
          $redis = new RedisClusterBase();
          // $logistics = $redis->LPOP('logistics_json');
          // echo $logistics;exit;
          while(true){
               $logistics = $redis->LPOP('logistics_json');
               // file_put_contents ('../log/lms/logistics.log', $logistics.',', FILE_APPEND|LOCK_EX);
               if(!empty($logistics)){
                   print_r($logistics) ;

               }else{
                  return 'meile';

               }
          }

  }
  /**
   * 定时把时间更新到产品历史更改记录表
   * [shipping_cost_change description]
   * @return [type] [description]
   */
  public function shipping_cost_product_histories(){
        $result = model("UserInfo")->shipping_cost_product_histories();
        return $result;
  }
   //临时测试
   public function delete_redis_1(){
          $redis = new RedisClusterBase();
          $logistics = $redis->LPOP('logistics_json');
          echo $logistics;exit;
   }
   //更改记录表
   public function delete_shipping_cost_histories(){
         $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>['in','1,2']])->update(['status'=>3]);
         if($list){
             echo '所以记录已更改';
         }

   }
   //临时测试
   public function aaa(){

         $list = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ShippingCost.ShippingServiceID'=>'40','ShippingCost.ShippingService'=>'专线'])
         ->update(['ShippingCost.ShippingService'=>'Exclusive']);
          pr(Db::connect(self::db_mongodb)->name(self::shipping_cost)->getLastSql());
          pr($list);

    exit;
    // date_default_timezone_set("PRC");
    // echo date_default_timezone_get();
       $t = ini_get("max_execution_time");
       echo $t;
       $i=0;
       try{
           while (true) {


              file_put_contents ('../log/lms/chukuyichang.log',$i.',', FILE_APPEND|LOCK_EX);
              $i++;
           }

        }catch(Exception $e){
            return $e->getMessage()."\n";
        }
   }
// public function PHP_INI_MH()
// {
//   // php启动阶段走这里
//   if (stage == PHP_INI_STAGE_STARTUP) {
//     // 将超时设置保存到EG(timeout_seconds)中
//     EG(timeout_seconds) = atoi(new_value);
//     return SUCCESS;
//   }

//   // php执行过程中的ini set则走这里
//   zend_unset_timeout(TSRMLS_C);
//   EG(timeout_seconds) = atoi(new_value);
//   zend_set_timeout(EG(timeout_seconds), 0);
//   return SUCCESS;
// }
}