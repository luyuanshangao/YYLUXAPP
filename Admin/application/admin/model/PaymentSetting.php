<?php
namespace app\admin\model;
use think\Model;
use think\Db;
/**
 * 支付设置模型
 * author: Wang
 * AddTime:2018-04-20
 */
class PaymentSetting  extends Model
{
    /**对提交数据进行判断
     * [judge description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function judge($data){
         if(is_array($data["Currency"])){
            // $data_array['_id']   = $data["Currency"];
            $data_array['Currency'] = $data["Currency"];
         }else{
            echo json_encode(array('code'=>100,'result'=>'请选择币种'));
            exit;
         }
         // if($data["Currency"]){
         //    $data_array['_id']   = $data["Currency"];
         //    $data_array['Currency'] = $data["Currency"];
         // }else{
         //    echo json_encode(array('code'=>100,'result'=>'请选择币种'));
         //    exit;
         // }
         if(!empty($data["payname"])){
            $payname = explode("&&&&",htmlspecialchars_decode($data["payname"]));
            $data_array['PayType'][$payname[1]]['payname'] = $payname[1];
         }else{
            echo json_encode(array('code'=>100,'result'=>'请选择支付方式'));
            exit;
         }
         //增加支付方式别名
         if(!empty($data["payname_alias"])){
            $data_array['PayType'][$payname[1]]['paynameAlias'] = $data["payname_alias"];
         }
         if(!empty($data["defaultImg"])){
            $data_array['PayType'][$payname[1]]['defaultImg'] = $data["defaultImg"];
         }else{
            echo json_encode(array('code'=>100,'result'=>'默认支付背景图不能为空'));
            exit;
         }
         if(!empty($data["selectedImg"])){
            $data_array['PayType'][$payname[1]]['selectedImg'] = $data["selectedImg"];
         }else{
            echo json_encode(array('code'=>100,'result'=>'选择支付背景图不能为空'));
            exit;
         }
         if(!empty($data["introduction"])){
            $data_array['PayType'][$payname[1]]['introduction'] = $data["introduction"];
         }else{
            echo json_encode(array('code'=>100,'result'=>'支付方式描述不能留空'));
            exit;
         }
         if(!empty($data["channel"])){
            foreach ($data["channel"] as $key => $value) {
                $channel = explode("&&&&",$value["channel"]);
                $data_array['PayType'][$payname[1]]["channel"][$key]['channelName'] = $channel[1];
                $data_array['PayType'][$payname[1]]["channel"][$key]['channelId']   = (int)$channel[0];
                if(isset($value["restriction"])){
                    $data_array['PayType'][$payname[1]]["channel"][$key]['restriction']   = $value["restriction"];
                }

            }
         }else{
            echo json_encode(array('code'=>100,'result'=>'渠道不能留空'));
            exit;
         }
         if(!empty($data["IconImg"])){
            $data_array['PayType'][$payname[1]]['IconImg'] = $data["IconImg"];
         }else{
            echo json_encode(array('code'=>100,'result'=>'支付方式图标不能留空'));
            exit;
         }

         $data_array['PayType'][$payname[1]]['status'] = (int)$data['status'];


         return $data_array;


    }
}