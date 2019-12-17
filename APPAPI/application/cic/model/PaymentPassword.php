<?php
namespace app\cic\model;
use think\Model;
use think\Db;
use app\common\services\CommonService;
/**
 * 支付密码模型
 * @author
 * @version Kevin 2018/3/15
 */
class PaymentPassword extends Model{
    /*
     * 检测用户支付密码是否设置
     * */
    public function checkPaymentPassword($CustomerID){
        $db = Db::connect('db_cic');
        $where['CustomerID'] = $CustomerID;
        $res = $db->name('paymentpassword')->where($where)->count();
        if($res>0){
            return true;
        }else{
            return false;
        }
    }


    /*
     * 检测用户密码是否正确
     * */
    public function confirmPaymentPassword($where,$Old_Password){
        $db = Db::connect('db_cic');
        $where['PaymentPassword'] = $Old_Password;
        $res = $db->name('paymentpassword')->where($where)->count();
        if($res>0){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 修改密码
     * */
    public function savePaymentPassword($data,$where=''){
        $db = Db::connect('db_cic');
        if(empty($where)){
            $res = $db->name('paymentpassword')->insertGetId($data);
            return $res;
        }else{
            $res = $db->name('paymentpassword')->where($where)->update($data);
        }
        return $res;
    }


    /*校验密码是否正确*/
    public function PaymentPasswordCorrectnessCheck($Password,$CustomerID){
        $service_post_data = array(
            'PaymentPasswordCorrectnessCheck' => array(
                'request' => array(
                    'Password' => $Password,
                    'CustomerID' => $CustomerID,
                )
            )
        );
        $service = new CommonService();
        $res = $service->PaymentPasswordCorrectnessCheck("PaymentPasswordCorrectnessCheck", $service_post_data);
        if(isset($res['PaymentPasswordCorrectnessCheckResult'])){
            if(property_exists($res['PaymentPasswordCorrectnessCheckResult'], 'OperationStatus') && $res['PaymentPasswordCorrectnessCheckResult']->OperationStatus == 'SUCCESS'  && $res['PaymentPasswordCorrectnessCheckResult']->IsCorrect == true){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }

    }

    /*验证客户是否设置了支付密码*/
    public function PaymentPasswordExistCheck($CustomerID=''){
        $service_post_data = array(
            'PaymentPasswordExistCheck' => array(
                'request' => array(
                    'CustomerID' => $CustomerID,
                )
            )
        );
        $service = new CommonService();
        $res = $service->PaymentPasswordCorrectnessCheck("PaymentPasswordExistCheck", $service_post_data);
        if(isset($res['PaymentPasswordExistCheckResult'])){
            if(property_exists($res['PaymentPasswordExistCheckResult'], 'OperationStatus') && $res['PaymentPasswordExistCheckResult']->OperationStatus == 'SUCCESS'  && $res['PaymentPasswordExistCheckResult']->IsExisted == true){
                return true;
            }else{
                return false;
            }
        }
        //return false;
    }
}