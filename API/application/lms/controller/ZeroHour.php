<?php
namespace app\lms\controller;
use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\common\controller\Base;
use app\lms\model\DxRegion;
use app\lms\dxcommon\BaseApi;
use app\lms\model\ZeroHour  As ZeroHourModel;
use think\Db;
use app\common\helpers\RedisClusterBase;
use app\lms\model\Logistics AS lms_logistics;//AS lms_logistics

/**
 * LMS系统 接口
 * author: Wang
 * AddTime:2018-04-26
 */
class ZeroHour extends Base
{
  const shipping_cost_histories = 'shipping_cost_histories';
  const db_mongodb = 'db_mongodb';
  const shipping_cost = 'shipping_cost';



  public function redis_dequeue(){
      // file_put_contents ('../log/lms/shipping_cost_histories_api.log',1, FILE_APPEND|LOCK_EX);
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 1){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[1])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[1]['logistics'],$list[1]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[1]['logistics'],$list[1]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
        // $url= 'http://api.localhost.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);

          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_1(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 2){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[2])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[2]['logistics'],$list[2]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[2]['logistics'],$list[2]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_1?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_2(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 3){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[3])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[3]['logistics'],$list[3]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[3]['logistics'],$list[3]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_2?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_3(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 4){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[4])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[4]['logistics'],$list[4]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[4]['logistics'],$list[4]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_3?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_4(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 5){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[5])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[5]['logistics'],$list[5]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[5]['logistics'],$list[5]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_4?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_5(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 6){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[6])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[6]['logistics'],$list[6]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[6]['logistics'],$list[6]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_5?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_6(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 7){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[7])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[7]['logistics'],$list[7]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[7]['logistics'],$list[7]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_6?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_7(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 8){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[8])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[8]['logistics'],$list[8]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[8]['logistics'],$list[8]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_7?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_8(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 9){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[9])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[9]['logistics'],$list[9]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[9]['logistics'],$list[9]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_8?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }
  public function redis_dequeue_9(){
      ignore_user_abort();
      $result = '';
      $list = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where(['status'=>1])->field('limit,logistics')->select();
      if(!empty($list)){
          $sum = count($list);
          if($sum == 10){
             $result = model("ZeroHour")->redis_dequeue();
          }else{
             if(!empty($list[10])){
                 if(!empty($limit)){
                    $result = model("ZeroHour")->weight_template($list[10]['logistics'],$list[10]['limit']);
                 }else{
                    $result = model("ZeroHour")->weight_template($list[10]['logistics'],$list[10]['limit']);
                 }
             }
          }
      }else{
          $result = model("ZeroHour")->redis_dequeue();

      }
      if($result == 'redis_meile'){
          exit;
      }else{
           $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue_9?access_token=dx123&id='.mt_rand(000000000,999999999);
          // $url= 'https://affiliateapi.dx.com/lms/ZeroHour/redis_dequeue?access_token=dx123&id='.mt_rand(000000000,999999999);
          header("Refresh:0;url=".$url);
      }
  }


}