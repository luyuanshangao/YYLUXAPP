<?php
namespace app\admin\model;
use think\Model;
use think\Db;
//物流模型
class Logisticsconfigmanage  extends Model
{
   /**
    * [LogisticsJudgment description]\
    * 判断提交数据是否符合
    */
   public function LogisticsJudgment($data){
        if(empty($data["country"])){
               echo json_encode(array('code'=>100,'result'=>'国家不能为空'));
               exit;
        }else{
             $country_dada      = explode(",", $data["country"]);
             foreach ($country_dada as $key => $value) {
                   $country = explode("-", $value);
                   if(empty($country[1])){
                      echo json_encode(array('code'=>100,'result'=>'所选国家中存在为空的'));
                      exit;
                   }
             }
        }
        foreach ($data["where"] as $k => $v) {
           if(!empty($v["time_slot"] && (preg_match("/^[0-9]+[\-]{1}[0-9]*$/",$v["time_slot"]) || is_numeric($v["time_slot"])))){
                    if(!empty($v["freight"]) && (preg_match("/^[0-9]+[\.]{1}[0-9]*$/",$v["freight"]) || is_numeric($v["freight"]))){

                    }else{
                        echo json_encode(array('code'=>100,'result'=>'运费为空或者格式有误'));
                        exit;
                    }
               }else{
                  echo json_encode(array('code'=>100,'result'=>'物流时效为空或者格式有误'));
                  exit;
               }
           }
           return true;
   }

}