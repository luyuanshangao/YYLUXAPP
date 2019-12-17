<?php
namespace app\admin\model;
use think\Model;
use think\Db;
//物流模型
class Systemmanage  extends Model
{

   /**
    * 验证提交信息是否有问题
    */
   public function add_email($data){
       if(empty($data["title"]) || empty($data["type"]) || empty($data["templetName"]) || empty($data["content"])){
                 return json_encode(array('code'=>100,'result'=>'提交数据存在为空字段'));
       }else{
                 return true;
       }
   }
}