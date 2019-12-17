<?php
namespace app\admin\model;

use think\Db;
use think\Model;
/**
 *s商城活动
 *auther wang   2018-04-04
 */
class PromotionManagement extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect("db_mongo");
    }
    /**
     * [judgment description]
     * @return [type] [description]
     * 判断前端提交数据
     */
    public static function judgment($data){
         $_type = isset($data['type'])?$data['type']:0;
         if(empty($data['type'])){
             echo json_encode(array('code'=>100,'result'=>'请选择活动类型'));
             exit;
         }
         if(empty($data['activity_title'])){
             echo json_encode(array('code'=>100,'result'=>'活动标题不能为空'));
             exit;
         }
         //新建活动除了flashdeals外多语种标题非必填 tinghu.liu 20191106
         if(!empty($data['common'])){
              foreach ($data['common'] as $key => $value) {
                  if(!$value["code"] || !$value["type"] || !$value["title"]){
                      echo json_encode(array('code'=>100,'result'=>'语种填写有误'));
                      exit;
                  }

              if(empty($data['common']['en'])){
                  echo json_encode(array('code'=>100,'result'=>'英文翻译内容不能为空'));
                  exit;
               }
              }

         }else if ($_type == 5){
            echo json_encode(array('code'=>100,'result'=>'语种只是要有一种'));
            exit;
         }

        //配合测试，代码注释--TODO
        //flash deals不用验证报名时间 tinghu.liu 20190903
        if (isset($data['type']) && $data['type'] != 5){

            if(!empty($data['registration_start_time']) && !empty($data['registration_end_time'])){
                $data['registration_start_time'] = strtotime($data['registration_start_time']);
                $data['registration_end_time']   = strtotime($data['registration_end_time']);
                $nowTime=date("Y-m-d h:i:s");
                if(strtotime($nowTime) >= $data['registration_start_time']){
                    echo json_encode(array('code'=>100,'result'=>'报名开始时间不可小于当前时间'));
                    exit;
                }
                if($data['registration_start_time'] >=$data['registration_end_time']){
                    echo json_encode(array('code'=>100,'result'=>'报名结束时间必须大于开始时间'));
                    exit;
                }
            }else{
                echo json_encode(array('code'=>100,'result'=>'报名时间不能为空'));
                exit;
            }
        }
        //如果flashdeals活动传了报名时间，则需要判断报名时间的格式 tinghu.liu 20191107
        if (isset($data['type']) && $data['type'] == 5){
            if(!empty($data['registration_start_time']) && !empty($data['registration_end_time'])){
                $data['registration_start_time'] = strtotime($data['registration_start_time']);
                $data['registration_end_time']   = strtotime($data['registration_end_time']);
                $nowTime=date("Y-m-d h:i:s");
                if(strtotime($nowTime) >= $data['registration_start_time']){
                    echo json_encode(array('code'=>100,'result'=>'报名开始时间不可小于当前时间'));
                    exit;
                }
                if($data['registration_start_time'] >=$data['registration_end_time']){
                    echo json_encode(array('code'=>100,'result'=>'报名结束时间必须大于开始时间'));
                    exit;
                }
            }
        }
        if(empty($data['registration_start_time'])){
            $data['registration_start_time'] = 0;
        }
        if(empty($data['registration_end_time'])){
            $data['registration_end_time'] = 0;
        }

         if(!empty($data['activity_start_time']) && !empty($data['activity_end_time'])){
             $data['activity_start_time']     = strtotime($data['activity_start_time']);
             $data['activity_end_time']       = strtotime($data['activity_end_time']);
             if($data['activity_start_time']<=$data['registration_end_time']){
             	echo json_encode(array('code'=>100,'result'=>'活动开始时间不可小于活动报名结束时间'));
             	exit;
             }
             if($data['activity_start_time'] >=$data['activity_end_time']){
                echo json_encode(array('code'=>100,'result'=>'活动结束时间必须大于开始时间'));
                exit;
             }else if($data['registration_end_time'] > $data['activity_start_time']){
                echo json_encode(array('code'=>100,'result'=>'活动时间必须大于报名时间'));
                exit;
             }
         }else{
             echo json_encode(array('code'=>100,'result'=>'活动时间不能为空'));
             exit;
         }
         if(empty($data['activity_img'])){
             echo json_encode(array('code'=>100,'result'=>'标题图片不能为空'));
             exit;
         }
         if(empty($data['description'])){
             echo json_encode(array('code'=>100,'result'=>'描述不能为空'));
             exit;
         }
         if($data['range'] == 2){
             if(empty($data["className"])){
                 echo json_encode(array('code'=>100,'result'=>'分类活动不能留空'));
                 exit;
             }else{
                 $data["className"] = json_encode($data["className"]);
             }
         }else{
            unset($data["className"]);
         }

         return $data;
    }
}