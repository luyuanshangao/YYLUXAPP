<?php
namespace app\admin\controller;

use app\log\model\Log;
use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use think\Paginator;
use app\admin\dxcommon\BaseApi;
use app\admin\model\PaymentSetting as PaymentSette;
/*
 * 后台管理-支付配置
 * AddTime:2018-03-25
 * Info:
 *
 */
class PaymentSetting extends Action
{
  	public function __construct(){
         Action::__construct();
         $this->Menu_logo();

         empty(defined('S_CONFIG'))?define('S_CONFIG', 'dx_sys_config'):'';//mongodb数据表
         define('PAY_CONFIG', 'dx_pay_config');//mongodb数据表
      }
  	/*
  	 *
  	 */
  	public function index()
  	{
        if($data = request()->post()){
            $where = array();
             // if(!empty($data['payname'])){
             //     $where['payname'] = $data['payname'];
             // }
             // if(!empty($data['status'])){
             //     $where['status']  = (int)$data['status'];
             // }
             // if($where && empty($data['Currency'])){
             //      $this->assign(['where'=>$where,'result'=>'币种不能为空',]);
             //      return View('configurationList');
             // }else{
             //      $where['Currency'] = $data['Currency'];
             // }
             if(!empty($data['Currency'])){
                 $where['Currency'] = $data['Currency'];
             }

             if($where){
                $list    = Db::connect("db_mongo_Cart")->name(PAY_CONFIG)->where($where)->select();//dump($list);
                 // $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>'PayemtMethod'])->find();
                 // $data = explode(";",$PayemtMethod['ConfigValue']);
                 // foreach ($data as $key => $value) {
                 //    $list[] = explode(":",$value);
                 // }
                 // rsort($list);
                 // foreach ( $list as $k => $v) {
                 //      $result   = Db::connect("db_mongo_Cart")->name(PAY_CONFIG)->where(['_id'=>(int)$v[0]])->find();
                 //      $list[$k]['pay_config'] = $result;
                 // }
                 $this->assign(['where'=>$where,]);
             }else{
                 $list    = Db::connect("db_mongo_Cart")->name(PAY_CONFIG)->select();

             }

        }else{
            // $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>'PayemtMethod'])->find();
            // $data = explode(";",$PayemtMethod['ConfigValue']);
            // foreach ($data as $key => $value) {
            //     $list[] = explode(":",$value);
            // }
            // rsort($list);
            // foreach ( $list as $k => $v) {
            //       $result   = Db::connect("db_mongo_Cart")->name(PAY_CONFIG)->where(['_id'=>(int)$v[0]])->find();
            //       $list[$k]['pay_config'] = $result;
            // }

          $list   = Db::connect("db_mongo_Cart")->name(PAY_CONFIG)->where($where)->select();
        }
        $this->assign(['list'=>$list,]);
  		  return View('configurationList');
  	}
     /**添加修改支付方式
     * [eidt_config description]
     * @return [type] [description]
     * author: Wang
     * AddTime:2018-04-20
     */
    public function add_config(){
      if($data = request()->post()){
           $data_array = PaymentSette::judge(json_decode(htmlspecialchars_decode(json_encode($data)), true));//判断提交数据
           $Currency = $data_array['Currency'];

           if($data['start'] == 'add'){
              //检查是否存在已添加数据
              foreach ($Currency as $k => $v) {
                     $PayType = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['Currency'=>$v,])->field('_id,PayType')->find();
                     if($PayType){
                        foreach ($data_array['PayType'] as $ke => $va) {
                            if($PayType['PayType'][$ke]){
                               echo json_encode(array('code'=>100,'result'=>$v.'币种中'.$ke.'渠道已经存在'));
                               exit;
                            }
                        }
                     }
              }
           }

           unset($data_array['Currency']);
           $final_result = '';
           foreach ($Currency as $key => $value) {
               $data_array['_id']      =  $value;
               $data_array['Currency'] =  $value;
               $_id = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['Currency'=>$value,])->field('_id,PayType')->find();
               if($_id){
                    $result = PaymentSetting::submission($data_array,$_id);
               }else{
                    $result = PaymentSetting::submission($data_array);
               }
               if($result === false){
                   $final_result = '失败';
              }
           }
           if($final_result == ''){
              echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
              exit;
           }else{
              echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
              exit;
           }


      }else{
           $id      = input('id');
           $payname = input('payname');
           if($id && $payname){
              $list = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['_id'=>$id])->field('_id,PayType,Currency')->find();
//               dump($list);
              foreach ($list['PayType'] as $key => $value) {
                  if($key == $payname){
                     $data['PayType'] = $value;
                  }
              }
              $data['id']       = $id;
              $data['Currency'] = $payname;
              if($data['PayType']["channel"]){
                $data["configuration"] = $this->ergodic_configuration($data['PayType']["channel"]);
              }
              $data["Currency"] = $list['Currency'];
           }
           //$currency = config('Currency');//币种
           $currency = BaseApi::getCurrencyList();
           //获取支付方式
           $PaymentMethod = $this->ergodic('PaymentMethod',$payname);
           //获取支付 对应渠道
           $ChannelDisbursement = $this->ergodic('ChannelDisbursement',$payname);
           $this->assign(['currency'=>$currency['data'],'html'=>$ChannelDisbursement,'PaymentMethod'=>$PaymentMethod,'list'=>$data]);
           return View('eidt_config');
      }
    }
    /**数据更新
     * [submission description]
     * @return [type] [description]
     * author: Wang
     */
    public function submission($data_array=array(),$_id=array()){
           if(!$_id){
                foreach ($data_array["PayType"] as $key => $value) {
                   $data_array["PayType"][$key]["Addtime"]    =  time();
                   $data_array["PayType"][$key]["add_person"] = Session::get('username');
                }
                $data_array["Addtime"]     = time();
                $data_array["add_person"]  = Session::get('username');
                $data_array["edittime"]    = '';
                $data_array["edit_person"] = '';
                $result = Db::connect("db_mongo_Cart")->name('dx_pay_config')->insert($data_array);
                if($result){
                    //变更币种对应支付方式时，需要清空缓存让修改生效 tinghu.liu 20190725
                    (redis())->rm('PayType'.$data_array['Currency']);
                   return true;
                   // echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
                   // exit;
                }else{
                   return false;
                   // echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
                   // exit;
                }
           }else{
                foreach ($data_array["PayType"] as $key => $value) {
                     foreach ($_id["PayType"] as $k => $v) {
                          if($key == $k){
                            $_id["PayType"][$key]   = $data_array["PayType"][$key];
                          }else{
                            $_id["PayType"][$key]   = $data_array["PayType"][$key];
                          }
                     }
                    $_id["PayType"][$key]['edittime']     = time();
                    $_id["PayType"][$key]['edit_person']  = Session::get('username');
                }
                $_id['edittime']        = time();
                $_id['edit_person']     = Session::get('username');
                unset($_id['_id']);
                $result = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['Currency'=>$data_array['Currency']])->update($_id);
                if($result){
                    //变更币种对应支付方式时，需要清空缓存让修改生效 tinghu.liu 20190725
                    (redis())->rm('PayType'.$data_array['Currency']);
                   return true;
                  // echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
                  // exit;
                }else{
                  return false;
                  // echo json_encode(array('code'=>100,'result'=>'该数据已填添加过'));
                  // exit;
                }
           }
    }
    /**遍历字典数据
     * [ergodic description]
     * @return [type] [description]
     * author: Wang
     * AddTime:2018-04-20
     */
    public function ergodic($val,$payname=''){
          $data = $this->dictionariesQuery($val);
          $html = '';
          $selected = '';
          foreach ($data as $key => $value) {
              if($value[0] && $value[1]){
                 if($value[1] == $payname){
                  $selected = 'selected="selected"';
                 }
                 $html .= '<option '.$selected.' value="'.$value[0].'&&&&'.$value[1].'">'.$value[1].'</option>';
                 $selected = '';
              }
          }
          return $html;
    }
    /**修改支付方式
     * [eidt_config description]
     * @return [type] [description]
     * author: Wang
     * AddTime:2018-04-20
     */
    // public function eidt_config(){
    //     if($data = request()->post()){
    //          foreach ($data["channel"] as $key => $value) {
    //               $channel = explode("-",$value["channel"]);
    //               $data["channel"][$key]['channelName'] = $channel[1];
    //               $data["channel"][$key]['channelId']   = (int)$channel[0];
    //               unset($data["channel"][$key]["channel"]);
    //          }
    //          $data['status'] = (int)$data['status'];
    //          $data['_id']    = (int)$data['_id'];
    //          $_id = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['_id'=>$data['_id'],])->field('_id')->find();
    //          if($_id){
    //              $data["edittime"]    = time();
    //              $data["edit_person"]  = Session::get('username');
    //              $result = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['_id'=>$data['_id'],])->update($data);

    //          }else{
    //              $data["Addtime"]     = time();
    //              $data["add_person"]  = Session::get('username');
    //              $data["edittime"]  = '';
    //              $data["edit_person"] = '';
    //              $result = Db::connect("db_mongo_Cart")->name('dx_pay_config')->insert($data);
    //          }
    //          if($result){
    //            echo json_encode(array('code'=>200,'result'=>'数据更新成功'));
    //            exit;
    //          }else{
    //            echo json_encode(array('code'=>100,'result'=>'数据更新失败'));
    //            exit;
    //          }

    //     }else{
    //         $currency = config('Currency');//币种
    //         // dump($currency);

    //         $data['id']      = input('id');
    //         $data['payname'] = input('payname');

    //         $list = Db::connect("db_mongo_Cart")->name('dx_pay_config')->where(['_id'=>(int)$data['id'],])->find();
    //         //获取支付 对应渠道
    //         $ChannelDisbursement = $this->dictionariesQuery('ChannelDisbursement');
    //         $html = '';
    //         foreach ($ChannelDisbursement as $key => $value) {
    //             if($value[0] && $value[1]){
    //                $html .= '<option value="'.$value[0].'-'.$value[1].'">'.$value[1].'</option>';
    //             }
    //         }
    //         $list['id'] = $data['id'];
    //         $list['payname'] = $data['payname'];
    //         if($list["channel"]){
    //             $list["configuration"] = $this->ergodic_configuration($list["channel"]);
    //         }

    //         $this->assign(['list'=>$list,'html'=>$html,'currency'=>$currency]);
    //         return View('eidt_config');
    //     }
    // }

 /**
   * [ergodic_Configure description]
   * @return [type] [description]
   *支付需要遍历数据 数据
   *
   *channel  渠道数组
   */
  public function ergodic_configuration($channel=array()){
          //获取支付 对应渠道
          $ChannelDisbursement = $this->dictionariesQuery('ChannelDisbursement');
          $Channel = '';

          $i = 1;
          $classHtml = '';
          $select = '';
          $html = '';
          $eidt_config = "'eidt_config'";
          $Ssum = count($channel);
          foreach ($channel as $key => $value) {
                foreach ($ChannelDisbursement as $k => $v) {
                    if($v[0] && $v[1]){
                         if($v[0] == $value["channelId"]){
                             $Channel .= '<option  selected = "selected" value="'.$v[0].'&&&&'.$v[1].'">'.$v[1].'</option>';
                         }else{
                             $Channel .= '<option value="'.$v[0].'&&&&'.$v[1].'">'.$v[1].'</option>';
                         }
                    }
                }
                $sum = count($value);
                if( $sum == 2 && $Ssum == 1 ){
                        $html .= '<div class="input-icon right mb10">';
                        $html .= '<select name="channel[1][channel]"  class="form-control input-small inline inline_block">';
                        $html .= '<option value="">请选择</option>';
                        $html .= $Channel;
                        $html .= '</select><a class="btn btn-qing add-payment-btn ml10" data-index="'.$i.'" href="javascript:;">添加新项</a><span class="ml10-relative-color tips">(默认渠道，若没有其他渠道分配，则全部走默认渠道)</span>';
                        $html .= '</div>';
                }else if($sum == 2){
                        $html .= '<div class="input-icon right mb10">';
                        $html .= '<select name="channel[1][channel]"  class="form-control input-small inline inline_block">';
                        $html .= '<option value="">请选择</option>';
                        $html .= $Channel;
                        $html .= '</select><span class="ml10-relative-color tips">(默认渠道，若没有其他渠道分配，则全部走默认渠道)</span>';
                        $html .= '</div>';
                }else if($Ssum == $i){
                        $html .= '<div class="mb10 input-icon right"><select name="channel['.$i.'][channel]" class="form-control input-small inline"><option value="">请选择</option>'.$Channel.'</select><input value="'.$value['restriction'].'" name="channel['.$i.'][restriction]" class="bgc-h29-border input-medium" type="text"><a class="btn btn-qing add-payment-btn ml10" data-index="'.$i.'"  href="javascript:;">添加新项</a><a class="btn btn-qing delete-payment-btn ml10" data-index="'.$i.'" href="javascript:;">删除</a></div>';
                }else{

                        $html .= '<div class="mb10 input-icon right"><select name="channel['.$i.'][channel]" class="form-control input-small inline"><option value="">请选择</option>'.$Channel.'</select><input value="'.$value['restriction'].'" name="channel['.$i.'][restriction]" class="bgc-h29-border input-medium" type="text"><a class="btn btn-qing delete-payment-btn ml10" data-index="'.$i.'" href="javascript:;">删除</a></div>';

                }
                $i++;
                $Channel = '';

          }
          return $html;
  }


}