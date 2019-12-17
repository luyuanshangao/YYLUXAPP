<?php
namespace app\admin\controller;

use think\Exception;
use think\Log;
use think\View;
use think\Controller;
use think\Db;
use think\Cookie;
use think\queue\Job;
use \think\Session;
use app\admin\dxcommon\ExcelTool;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use app\admin\model\OrderModel;
//use app\common\ase\aes;

/*
 * 运营管理-财务管理
 * author: Wang
 * AddTime:2018-10-24
 */
class FinancialManagement extends Action
{
  const affiliate_order_item = 'affiliate_order_item';
	public function __construct(){
        Action::__construct();
        define('MY_WITHDRAW', 'withdraw');//mysql数据表
        define('AFFILIATE_APPLY', 'affiliate_apply');//mysql数据表
        define('AFFILIATE_ORDER', 'affiliate_order');//mysql数据表
        define('REPORTS', 'reports');//Mysql数据表
        define('REPORTS_CUSTOMS_INSURANCE', 'reports_customs_insurance');//Mysql数据表
        define('REPORTS_LOG', 'reports_log');//Mysql数据表
       // define('S_CONFIG', 'dx_sys_config');//Nosql数据表
        // $this->db_order = Db::connect("db_order");
//       define('MESSAGE', 'Message');//mysql数据表
//       define('M_RECIVE', 'MessageRecive');//mysql数据表
    }
    /**
     * 提款申请审核
     * @author: wang
     * @AddTime:2019-01-28
     */
    public function FinancialAudit(){
        $page_size = config('paginate.list_rows');
        $Config =  publicConfig(S_CONFIG,'MemberType');
        $ConfigStatus =  publicConfig(S_CONFIG,'WithdrawalStatus');
        $where = array();
        $list_items = array();
        $list_render = array();
        $Affiliate_ID = '';
        $Affiliate_data = [];
        $cic_data = [];
        $affiliateid_cic = '';
        $list = array();
        $data = input();
        $data = ParameterCheck($data);
          //如果是cic_id查询，只要么联表要么先查出对应的Affiliate_id
          if(!empty($data['cic_ID'])){
              $affiliateid_cic = $this->ApplyForCicInquiry($data);
              if($affiliateid_cic !=false){
                   $data['Affiliate_ID'] = $affiliateid_cic;
              }
          }

          $query = $this->ParameterCheckAudit($data);
          $where = !empty($query)?$query:'';
          $list = Db::name(AFFILIATE_APPLY)->where($where)->order('add_time desc')->paginate($page_size,false,[
              'type' => 'Bootstrap',
              'query'=> $data
          ]);
          if(!empty($list)){
              $list_items  = $list->items();
              $list_render = $list->render();
          }
          $affiliate_price = [];
          if(!empty($list_items)){
                  $aes =  aes();
                  foreach($list_items as $k=>$v){
                      $Affiliate_data[] = $v['affiliate_id'];
                  }
                  //到api获取用户信息
                  if(!empty($Affiliate_data)){
                      $Affiliate_data = array_unique($Affiliate_data);
                      $Affiliate_ID   = implode(",",$Affiliate_data);
                      $affiliate_list = BaseApi::FinancialAudit(['affiliate_id'=>$Affiliate_ID]);
                      if(!empty($affiliate_list['code']) && $affiliate_list['code'] == 200 && !empty($affiliate_list["data"]["list"])){
                           foreach ($affiliate_list["data"]["list"] as $ke => $va){
                                $va["EmailUserName"] = $aes->decrypt($va["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                                $va["EmailUserName"] .= '@'.$va['PayPalED'];
                                $cic_data[$va["RCode"]] = $va;
                           }
                      }

                      //已生效佣金,未生效佣金
                      $total_valid_commission =  Db::name(AFFILIATE_ORDER)
                          ->alias('AO')
                          ->where(['AO.affiliate_id'=>['in',$Affiliate_ID],'AO.settlement_status'=>['in','2']])
                          ->group('AO.affiliate_id')
                          ->column('sum(AO.total_valid_commission_price)','AO.affiliate_id');

                      $affiliate_effective = Db::name(AFFILIATE_ORDER)
                          ->alias('AO')
                          ->join(self::affiliate_order_item.' AOI','AO.affiliate_order_id = AOI.affiliate_order_id')
                          ->where(['AO.affiliate_id'=>['in',$Affiliate_ID],'AO.settlement_status'=>['in','1,2']])
                          ->group('AO.affiliate_id')
                          ->field('AO.affiliate_id,AO.settlement_status,sum(AOI.commission_price) AS TotalCommission')
                          ->select();
                      if(!empty($affiliate_effective)){
                          foreach ($affiliate_effective as $k_price => $v_price) {
                              $affiliate_price[$v_price['affiliate_id']] = $v_price;
                              if(isset($total_valid_commission[$v_price['affiliate_id']])){
                                  $affiliate_price[$v_price['affiliate_id']]['TotalValidCommission'] = $total_valid_commission[$v_price['affiliate_id']];
                                  $affiliate_price[$v_price['affiliate_id']]['TotalIneffectiveCommission'] = $v_price['TotalCommission'] - $affiliate_price[$v_price['affiliate_id']]['TotalValidCommission'];
                              }
                          }
                      }
                  }
          }
          $ConfigStatus = json_decode(htmlspecialchars_decode($ConfigStatus["result"]["ConfigValue"]) ,true);
          // unset($ConfigStatus[1]);
          $this->assign(['list'=>$list_items,'page'=>$list_render,'data'=>$data,'Config'=>json_decode($Config["result"]["ConfigValue"],true),'ConfigStatus'=>$ConfigStatus,'cic_data'=>$cic_data,'affiliate_price'=>$affiliate_price]);
          return View();
    }
    /**
     * 因申请表没有cic字段查询，所有当有cic时从关联affiliate订单表进行查询
     * [ApplyForCicInquiry description]
     */
    public function ApplyForCicInquiry($data = []){
           $where = [];
           if(!empty($data)){
              if(isset($data['customer_type'])){
                  $where['AO.customer_type'] = $data['customer_type'];
              }
              if(isset($data['status'])){
                  $where['AO.status'] = $data['status'];
              }
              if(isset($data['Affiliate_ID'])){
                  $where['AO.affiliate_id'] = $data['Affiliate_ID'];
              }
              if(isset($data['startTime']) && isset($data['endTime'])){
                  $startTime = strtotime($data['startTime']);
                  $endTime = strtotime($data['endTime']);
                  $where['AO.add_time'] = array('between',''.$startTime.','.$endTime.'');
              }
           }

           $affiliate_effective = Db::name(AFFILIATE_APPLY)
                      ->alias('AO')
                      ->join(AFFILIATE_ORDER.' AOI','AO.affiliate_id = AOI.affiliate_id')
                      ->where($where)
                      ->field('AO.affiliate_id')
                      ->find();
                      //dump($affiliate_effective);dump(Db::name(AFFILIATE_APPLY)->getLastSql()) ;
           if(!empty($affiliate_effective)){
               return $affiliate_effective['affiliate_id'];
           }
           return false;
    }
    public function AffiliateOrder(){
          $order_id = input('order_id');
          $id = input('id');
          $order_id = trim($order_id,',');
          $affiliate_order_id = '';
          $affiliate_commission_price = [];
          $apply = [];
          $page_size = config('paginate.list_rows');
          $page = !empty(input('page'))?input('page'):1;
          if(!empty($order_id)){
                $character = '/select|insert|update|CR|document|LF|eval|delete|script|alert|\'|\/\*|\#|\--|\，|\;|\:|\；|\/|\*|\-|\+|\=|\~|\*@|\*!|\$|\%|\^|\&|\(|\)|\/|\/\/|\.\.\/|\.\/|union|into|load_file|outfile/';
                if(preg_match($character,$order_id)){
                      echo '有特殊字符';
                }else{
                      //已生效佣金,未生效佣金
                      $affiliate_order = Db::name(AFFILIATE_ORDER)->where(['affiliate_order_id'=>['in',$order_id]])->field('affiliate_order_id,order_number,price,settlement_status,create_on,source,order_status,cic_id')->page($page)->paginate($page_size);
                      if(!empty($affiliate_order->items())){
                           $list_items  = $affiliate_order->items();
                           foreach ($list_items as $k => $v) {
                                if($affiliate_order_id == ''){
                                    $affiliate_order_id = $v['affiliate_order_id'];
                                }else{
                                    $affiliate_order_id .= ','.$v['affiliate_order_id'];
                                }
                           }

                           if(!empty($affiliate_order_id)){
                                $affiliate_order_item = Db::name(self::affiliate_order_item)
                                                        ->where(['affiliate_order_id'=>['in',$affiliate_order_id]])
                                                        ->field('affiliate_order_id,commission_price,sum(commission_price) AS total_commission_price')
                                                        ->group('affiliate_order_id')
                                                        ->select();
                                if(!empty($affiliate_order_item)){
                                      foreach ($affiliate_order_item as $ke => $ve) {
                                           $affiliate_commission_price[$ve['affiliate_order_id']] = $ve['total_commission_price']?$ve['total_commission_price']:0.00;
                                      }
                                }
                           }
                      }
                }
          }

          //获取对应客户操作人员
          if(!empty($id)){
              $apply = Db::name(AFFILIATE_APPLY)->where(['id'=>$id])->field('check_user_name,affiliate_id')->find();
          }
          // dump($affiliate_order);dump($affiliate_order_item);dump($apply);
          $page =  $affiliate_order->render()?$affiliate_order->render():'';
          $this->assign(['list'=>$affiliate_order,'affiliate_commission_price'=>$affiliate_commission_price,'apply'=>$apply,'page'=>$page]);
          return View();
    }

    public function affiliateApplyInfo(){
        $id = input('id');

        //获取对应客户操作人员
        if(!empty($id)){
            $apply = Db::name(AFFILIATE_APPLY)->where(['id'=>$id])->find();
            if(!empty($apply) && !empty($apply['enclosure'])){
                $apply['enclosure'] = json_decode($apply['enclosure']);
            }
        }
       // dump($apply);exit;
        $this->assign(['apply'=>$apply]);
        return View();
    }

    /*
     * 佣金提现导出
     * [AffiliateOrderExport description]
     */
    public function AffiliateOrderExport(){
          $order_id = input('order_id');
          $id = input('id');
          $order_id = trim($order_id,',');
          if(empty($order_id)){echo '空参数'; exit;}
          $page = 1;
          $page_size = 5000;
          $sort = 0;
          $orderlist = [];

          $character = '/select|insert|update|CR|document|LF|eval|delete|script|alert|\'|\/\*|\#|\--|\，|\;|\:|\；|\/|\*|\-|\+|\=|\~|\*@|\*!|\$|\%|\^|\&|\(|\)|\/|\/\/|\.\.\/|\.\/|union|into|load_file|outfile/';
          if(preg_match($character,$order_id)){     echo '有特殊字符'; exit;   }
          //获取对应客户操作人员
          if(!empty($id)){
              $apply = Db::name(AFFILIATE_APPLY)->where(['id'=>$id])->field('check_user_name,affiliate_id')->find();
          }
          while (true) {
                  $affiliate_order_id = '';
                  $affiliate_commission_price = [];
                  $operator = '';
                  $settlement_status = '';
                  $order_status = '';

                  //已生效佣金,未生效佣金
                  $affiliate_order = Db::name(AFFILIATE_ORDER)->where(['affiliate_order_id'=>['in',$order_id]])->field('affiliate_order_id,order_number,price,settlement_status,create_on,source,order_status,cic_id')->page($page,$page_size)->select();
                  if(empty($affiliate_order)){ break; };
                  foreach ($affiliate_order as $k => $v) {
                      if($affiliate_order_id == ''){
                          $affiliate_order_id = $v['affiliate_order_id'];
                      }else{
                          $affiliate_order_id .= ','.$v['affiliate_order_id'];
                      }
                  }
                  if(!empty($affiliate_order_id)){
                      $affiliate_order_item = Db::name(self::affiliate_order_item)
                                              ->where(['affiliate_order_id'=>['in',$affiliate_order_id]])
                                              ->field('affiliate_order_id,commission_price,sum(commission_price) AS total_commission_price')
                                              ->group('affiliate_order_id')
                                              ->select();
                      if(!empty($affiliate_order_item)){
                            foreach ($affiliate_order_item as $ke => $ve) {
                                 $affiliate_commission_price[$ve['affiliate_order_id']] = $ve['total_commission_price']?$ve['total_commission_price']:0.00;
                            }
                      }
                  }
                  foreach ($affiliate_order as $ki => $vi) {
                          if($vi['settlement_status'] == 3){
                               $operator =  $vi['cic_id'];
                          }else{
                               $operator =  $apply["check_user_name"];
                          }

                          if($vi['settlement_status'] == 1){
                               $settlement_status = '未生效';
                          }else if($vi['settlement_status'] == 2){
                               $settlement_status = '有效';
                          }else if($vi['settlement_status'] == 3){
                               $settlement_status = '待审核';
                          }else if($vi['settlement_status'] == 4){
                               $settlement_status = '审核通过';
                          }else if($vi['settlement_status'] == 5){
                               $settlement_status = '完成';
                          }else if($vi['settlement_status'] == 5){
                               $settlement_status = '无效';
                          }
                          if($vi['order_status'] < 200 || $vi['order_status'] == 1400){
                               $order_status = '无效订单';
                          }else {
                               $order_status = '已生效';
                          }
                          $orderlist[] =   [
                             'order_number'=>$vi['order_number'],
                             'source'=>$vi['source'],
                             'operator'=>$operator,
                             'settlement_status'=>$settlement_status,
                             'create_on'=>date("Y-m-d H:i:s",$vi['create_on']),
                             'price'=>!empty($vi['price'])?$vi['price']:0.00,
                             'affiliate_commission_price'=>!empty($affiliate_commission_price[$vi['affiliate_order_id']])?$affiliate_commission_price[$vi['affiliate_order_id']]:0.00,
                             'order_status'=>$order_status
                          ];
                 }
                 $page++;
          }

          $header_data =[
                           'order_number'=>'客户订单号',
                           'source'=>'数据来源',
                           'operator'=>'操作者',
                           'settlement_status'=>'交易类型',
                           'create_on'=>'交易时间',
                           'price'=>'实付金额($)',
                           'affiliate_commission_price'=>'佣金金额($)',
                           'order_status'=>'佣金状态'
                       ];
          $tool = new ExcelTool();
          if($orderlist){
             $tool ->export('Affiliate提现申请详情',$header_data,$orderlist,'sheet1');
          }
    }
    /*
    * 参数检查去除空的查询条件
    * @author: wang
    * @AddTime:2018-10-27
    */
    public function ParameterCheckAudit($data=array()){
        $where = [];
        if(!empty($data)){
            if(isset($data['customer_type'])){
                $where['customer_type'] = $data['customer_type'];
            }
            if(isset($data['status'])){
                $where['status'] = $data['status'];
            }
            if(isset($data['Affiliate_ID'])){
                $where['affiliate_id'] = $data['Affiliate_ID'];
            }
            if(isset($data['startTime']) && isset($data['endTime'])){
                $startTime = strtotime($data['startTime']);
                $endTime = strtotime($data['endTime']);
                $where['add_time'] = array('between',''.$startTime.','.$endTime.'');
            }
            return $where;
        }
        return false;
    }
    /*
   *提款再次审核
   * @author: wang
   * @AddTime:2018-10-25
   */
    public function FinancialReview(){
        $page_size = config('paginate.list_rows');
        $Config =  publicConfig(S_CONFIG,'MemberType');
        $ConfigStatus =  publicConfig(S_CONFIG,'WithdrawalStatus');
        $where = array();
        $list_items = array();
        $list_render = array();
        if($data = request()->post()){
            $data = ParameterCheck($data);
            //如果是cic_id查询，只要么联表要么先查出对应的Affiliate_id
            if(!empty($data['cic_ID'])){
                $affiliateid_cic = $this->ApplyForCicInquiry($data);
                if($affiliateid_cic !=false){
                     $data['Affiliate_ID'] = $affiliateid_cic;
                }
            }
            $where = $this->ParameterCheckAudit($data);
            if(empty($data['status'])){
               $where['status'] =  array('in','2,3,5');
            }else{
               $where['status'] = $data['status'];
            }

            if(empty($data['apply_type'])){
                $where['apply_type'] =  $data['apply_type'];
            }else{
                $where['apply_type'] = $data['apply_type'];
            }
            if($where){
                $list = Db::name(AFFILIATE_APPLY)->where($where)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
                //echo Db::name(MY_WITHDRAW)->getLastSql();
                $list_items  = $list->items();
                $list_render = $list->render();
            }
        }else{
            $data = input();
            $data = ParameterCheck($data);
            //如果是cic_id查询，只要么联表要么先查出对应的Affiliate_id
            if(!empty($data['cic_ID'])){
                $affiliateid_cic = $this->ApplyForCicInquiry($data);
                if($affiliateid_cic !=false){
                     $data['Affiliate_ID'] = $affiliateid_cic;
                }
            }
            $where = $this->ParameterCheckAudit($data);
            $where['status'] =  array('in','2,3,5');
            if($data){
                $list = Db::name(AFFILIATE_APPLY)->where($where)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
            }else{
                $list = Db::name(AFFILIATE_APPLY)->where($where)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                ]);
            }
        }
        if(!empty($list)){
            $list_items  = $list->items();
            $list_render = $list->render();
        }
        if(!empty($list_items)){
                $aes =  aes();
                foreach($list_items as $k=>$v){
                    $Affiliate_data[] = $v['affiliate_id'];
                }
                //到api获取用户信息
                if(!empty($Affiliate_data)){
                    $Affiliate_data = array_unique($Affiliate_data);
                    $Affiliate_ID   = implode(",",$Affiliate_data);
                    $affiliate_list = BaseApi::FinancialAudit(['affiliate_id'=>$Affiliate_ID]);
                    if(!empty($affiliate_list['code']) && $affiliate_list['code'] == 200 && !empty($affiliate_list["data"]["list"])){
                         foreach ($affiliate_list["data"]["list"] as $ke => $va){
                              $va["EmailUserName"] = $aes->decrypt($va["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                              $va["EmailUserName"] .= '@'.$va['PayPalED'];
                              $cic_data[$va["RCode"]] = $va;
                         }
                    }
                    //已生效佣金,未生效佣金
                    $total_valid_commission =  Db::name(AFFILIATE_ORDER)
                        ->alias('AO')
                        ->where(['AO.affiliate_id'=>['in',$Affiliate_ID],'AO.settlement_status'=>['in','2']])
                        ->group('AO.affiliate_id')
                        ->column('sum(AO.total_valid_commission_price)','AO.affiliate_id');

                    $affiliate_effective = Db::name(AFFILIATE_ORDER)
                        ->alias('AO')
                        ->join(self::affiliate_order_item.' AOI','AO.affiliate_order_id = AOI.affiliate_order_id')
                        ->where(['AO.affiliate_id'=>['in',$Affiliate_ID],'AO.settlement_status'=>['in','1,2']])
                        ->group('AO.affiliate_id')
                        ->field('AO.affiliate_id,AO.settlement_status,sum(AOI.commission_price) AS TotalCommission')
                        ->select();
                    if(!empty($affiliate_effective)){
                        foreach ($affiliate_effective as $k_price => $v_price) {
                            $affiliate_price[$v_price['affiliate_id']] = $v_price;
                            if(isset($total_valid_commission[$v_price['affiliate_id']])){
                                $affiliate_price[$v_price['affiliate_id']]['TotalValidCommission'] = $total_valid_commission[$v_price['affiliate_id']];
                                $affiliate_price[$v_price['affiliate_id']]['TotalIneffectiveCommission'] = $v_price['TotalCommission'] - $affiliate_price[$v_price['affiliate_id']]['TotalValidCommission'];
                            }
                        }
                    }
                }
          }
        $ConfigStatus = json_decode(htmlspecialchars_decode($ConfigStatus["result"]["ConfigValue"]) ,true);
        unset($ConfigStatus[1]);
        unset($ConfigStatus[4]);
        $this->assign(['list'=>$list_items,'page'=>$list_render,'data'=>$data,'Config'=>json_decode($Config["result"]["ConfigValue"],true),'ConfigStatus'=>$ConfigStatus,'cic_data'=>$cic_data,'affiliate_price'=>$affiliate_price]);
        return View();
    }
   /*
    * 业务审核提款 改方法只审核
    * mark等于1为物业审核，等于2为财务审核
    * @author: Wang
    * @AddTime:2018-10-25
    */

   public function WithdrawalStatus(){
       if($data = request()->post()){
           $where = array();
           $api_data =array();
           $resultEdit = array();
           $settlement_status = '';
           if(!empty($data['id']) && !empty($data['status'])){
               $where['status'] = $data['status'];
               if($data['mark'] == 1){
                    $where['business_edit_time'] = time();
                    // $where['business_operator']  = Session::get('username');
                    $where['business_operator_id']  = Session::get('userid');
                    $where['business_operator_ip']  = $_SERVER["REMOTE_ADDR"];
                    $where['check_user_name']       = Session::get('username');
               }else if($data['mark'] == 2){
                    $where['finance_edit_time'] = time();
                    $where['finance_operator_id']  = Session::get('userid');
                    $where['finance_operator_ip']  = $_SERVER["REMOTE_ADDR"];
                    $where['finance_operator_name']= Session::get('username');
               }

               if($where['status'] == 4 || $where['status'] == 5){
                   if(!empty($data['remark'])){
                       $where['remark'] = $data['remark'];
                   }else{
                        echo json_encode(array('code'=>100,'result'=>'理由不能为空'));
                        exit;
                   }
               }
              $list = Db::name(AFFILIATE_APPLY)->where(['id'=>$data['id']])->field('order_id,apply_type')->find();
              Db::startTrans();
              try{
                   $where['status'] = $data['status'];
                   $result = Db::name(AFFILIATE_APPLY)->where(['id'=>$data['id']])->update($where);
                   if($result){
                       if($list['order_id']){
                           if($where['status'] == 4 || $where['status'] == 5){
                                      $settlement_status = 2;
                           }else if($where['status'] == 2){
                                      $settlement_status = 4;
                           }else if($where['status'] == 3){
                                      $settlement_status = 5;
                           }
                           if(!empty($list)){
                               $result_data = Db::name(AFFILIATE_ORDER)->where(['affiliate_order_id'=>['in',$list['order_id']]])->update(['settlement_status'=>$settlement_status]);
                               if(!empty($result_data)){
                                   //提交事务
                                   Db::commit();
                                   echo json_encode(array('code'=>200,'result'=>'操作成功'));
                                   exit;
                               }else{
                                   Db::rollback();
                                   echo json_encode(array('code'=>100,'result'=>'数据处理出异常'));
                                   exit;
                               }
                           }else{
                               //提交事务
                               Db::commit();
                               echo json_encode(array('code'=>200,'result'=>'操作成功'));
                               exit;
                           }
                       }else{
                           if($list['apply_type'] == 2){
                               Db::commit();
                               echo json_encode(array('code'=>200,'result'=>'操作成功'));
                               exit;
                           }else{
                               Db::rollback();
                               echo json_encode(array('code'=>100,'result'=>'数据处理出异常'));
                               exit;
                           }
                       }
                   }else{
                       Db::rollback();
                       echo json_encode(array('code'=>100,'result'=>'操作失败'));
                       exit;
                   }

              } catch (\Exception $e) {
                   // 回滚事务
                   Db::rollback();
              }
           }else{
               echo json_encode(array('code'=>100,'result'=>'传递参数出错'));
               exit;
           }
       }
   }
   /**
    * 审核成功后修改cic状态
    * [CicStatusEdit description]
    */
   public function CicStatusEdit(){
         return  BaseApi::WithdrawStatus($data);
   }
   /*
    * 导出
    * @author: Wang
    * @AddTime:2018-10-30
    */
   public function Export(){
       $where = array();
       $data_array = array();
       $data = input();
       foreach($data as $key=>$val){
           if(!empty($val)){
               $where[$key] = $val;
           }
       }
       $mark = $where['mark'];
       unset($where['mark']);
       if(!$where){
           $where['add_time'] = array('between',''.strtotime("-1 month").','.time().'');
       }else{
           if($data["startTime"] && $data["endTime"]){
               $startTime = strtotime($data["startTime"]);
               $endTime = strtotime($data["endTime"]);
               if($startTime>$endTime){
                  echo '结束时间必须大于开始时间';
                  exit;
               }
               $where['add_time'] = array('between',''.$startTime.','.$endTime.'');
           }
       }

       //如果是财务导出 mark为2，1为物业导出
       if($mark == 2){
            if(!$where['status']){
                $where['status'] =  array('in','2,3,5');
            }
       }
       if(!empty($where['cic_ID'])){
          $affiliateid_cic = $this->ApplyForCicInquiry($where);
          if($affiliateid_cic !=false){
               $where['Affiliate_ID'] = $affiliateid_cic;
          }
       }
       unset($where['cic_ID']);
       unset($where["startTime"]);
       unset($where["endTime"]);
       $aes =  aes();
       if($where){
           $Config =  publicConfig(S_CONFIG,'MemberType');
           $Config =  json_decode(htmlspecialchars_decode($Config["result"]["ConfigValue"]),true);
           $ConfigStatus =  publicConfig(S_CONFIG,'WithdrawalStatus');
           $ConfigStatus =json_decode(htmlspecialchars_decode($ConfigStatus["result"]["ConfigValue"]),true);

               $page = 1;
               while(true){
                   $Affiliate_data = [];
                   $list = $this->CircularQuery($where,$page);
                   if(empty($list["list"])){
                       break;
                   }
                   $cic_data = $list['cic_data'];
                   $affiliate_price = $list['affiliate_price'];
                   $TotalCommission1 = 0.00;
                   $TotalCommission2 = 0.00;
                   // dump($list);exit;
                   $page++;
                   if($list){
                       foreach($list["list"] as $ke=>$ve){
                           $status = '';
                           $email = '';
                           $starting_time = '';
                           $business_edit_time = '';
                           $ID = '';
                           foreach($ConfigStatus as $kStatus=>$vStatus){
                               if($ve['status'] == $kStatus){
                                   $status = $vStatus;
                               }
                           }
                           if(!empty($cic_data[$ve["affiliate_id"]]['ID'])){
                                $ID = $cic_data[$ve["affiliate_id"]]['ID'];
                           }
                           if(!empty($cic_data[$ve["affiliate_id"]]['EmailUserName'])){
                                $email = $cic_data[$ve["affiliate_id"]]['EmailUserName'];
                           }//dump($email);exit;
                           if(!empty($affiliate_price[$ve['affiliate_id']]["settlement_status"]) && $affiliate_price[$ve['affiliate_id']]["settlement_status"] == 1){
                                $TotalCommission1 = $affiliate_price[$ve['affiliate_id']]["TotalCommission"];
                           }
                           if(!empty($affiliate_price[$ve['affiliate_id']]["settlement_status"]) && $affiliate_price[$ve['affiliate_id']]["settlement_status"] == 2){
                                $TotalCommission2 = $affiliate_price[$ve['affiliate_id']]["TotalCommission"];
                           }
                           if($mark == 2){
                               $business_edit_time  = '付款时间';
                               if(!empty($ve['finance_edit_time'])){
                                   $starting_time = date("Y-m-d H:i:s",$ve['finance_edit_time']);
                               }

                           }else{
                               $business_edit_time  = '审核时间';
                               if(!empty($ve['business_edit_time'])){
                                   $starting_time = date("Y-m-d H:i:s",$ve['business_edit_time']);
                               }

                           }

                           $data_array[] = [
                                           'affiliate_id'=>$ve['affiliate_id'],
                                           'CIC_ID'=>$ID,
                                           'email'=>$email,
                                           'TotalCommission1'=>$TotalCommission1,
                                           'TotalCommission2'=>$TotalCommission2,
                                           'amount'=>!empty($ve['amount'])?$ve['amount']:'',
                                           'add_time'=>$starting_time,
                                           'business_edit_time'=>$starting_time,
                                           // 'finance_edit_time'=>'',
                                           'status'=>$status,
                                           'check_user_name'=>$ve['check_user_name'],
                                           'finance_operator_name'=>$ve['finance_operator_name'],
                           ];
                       }


                       $header_data =[
                           'affiliate_id'=>'Affiliate_ID',
                           'CIC_ID'=>'客户ID',
                           'email'=>'会员邮箱',
                           'TotalCommission1'=>'可用佣金',
                           'TotalCommission2'=>'未生效佣金',
                           'amount'=>'提现金额',
                           'add_time'=>'申请时间',
                           'business_edit_time'=>$business_edit_time,
                           'status'=>'状态',
                           'check_user_name'=>'提现审核操作者',
                           'finance_operator_name'=>'财务付款操作者'
                       ];
                   }else{
                       break;
                   }
               }

                $tool = new ExcelTool();
                if($data_array){
                   $tool ->export('Affiliate提现申请',$header_data,$data_array,'sheet1');
                }else{
                    echo '没查到数据';
                    exit;
                }
       }
   }
   /*
    * 循环查出数据
    * @author: Wang
    * @AddTime:2018-10-30
    */
    public function CircularQuery($where=array(),$page=1){
        // $page_size = config('paginate.list_rows');
        $page_size = 2000;
        $affiliate_price = [];
        if($where){
              $list = Db::name(AFFILIATE_APPLY)->where($where)->order('add_time desc')->paginate($page_size,false,[
                'page' => $page,
                'type' => 'Bootstrap',
              ]);
              // echo Db::name(AFFILIATE_APPLY)->getLastSql();
              $aes =  aes();
              $Affiliate_list = $list->items();
              foreach($Affiliate_list as $k=>$v){
                  $Affiliate_data[] = $v['affiliate_id'];
              }
              //到api获取用户信息
              if(!empty($Affiliate_data)){
                  $Affiliate_data = array_unique($Affiliate_data);
                  $Affiliate_ID   = implode(",",$Affiliate_data);
                  $affiliate_list = BaseApi::FinancialAudit(['affiliate_id'=>$Affiliate_ID]);
                  if(!empty($affiliate_list['code']) && $affiliate_list['code'] == 200 && !empty($affiliate_list["data"]["list"])){
                       foreach ($affiliate_list["data"]["list"] as $ke => $va){
                            $va["EmailUserName"] = $aes->decrypt($va["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                            $va["EmailUserName"] .= '@'.$va['PayPalED'];
                            $cic_data[$va["RCode"]] = $va;
                       }
                  }

                  //已生效佣金,未生效佣金
                  $affiliate_effective = Db::name(AFFILIATE_ORDER)
                  ->alias('AO')
                  ->join(self::affiliate_order_item.' AOI','AO.affiliate_order_id = AOI.affiliate_order_id')
                  ->where(['AO.affiliate_id'=>['in',$Affiliate_ID],'AO.settlement_status'=>['in','1,2']])
                  ->group('AO.affiliate_id,settlement_status')
                  ->field('AO.affiliate_id,AO.settlement_status,AOI.commission_price,sum(AOI.commission_price) AS TotalCommission')
                  ->select();
                  if(!empty($affiliate_effective)){
                      foreach ($affiliate_effective as $k_price => $v_price) {
                            if(empty($affiliate_price[$v_price['affiliate_id']])){
                                $affiliate_price[$v_price['affiliate_id']] = $v_price;
                            }
                      }
                  }
              }
            //echo Db::name(MY_WITHDRAW)->getLastSql();exit;
            return array('list'=>$Affiliate_list,'cic_data'=>$cic_data,'affiliate_price'=>$affiliate_price);
        }
        return false;


    }
    /*
    * 资金管理
    * @author: Wang
    * @AddTime:2018-10-29
    */
    public function FundManagement(){
        $data = (new OrderModel())->getOrderInfo();
        $this->assign($data);
        return View();
    }
    /*
     * 结算管理
     * @author: Wang
     * @AddTime:2018-10-31
     */
   public function SettlementManagement(){
       echo '未做';
       exit;
       return View();
   }

   /*关税赔保审核*/
   public function CustomsInsuranceReview(){
       $riskConfig = BaseApi::RiskConfig();
       $data = input();
       if (!empty($data['customer_name'])) {
           $where['customer_name'] = array('like', '%' . $data['customer_name'] . '%');
       }
       if (!empty($data['seller_name'])) {
           $where['seller_name'] = array('like', '%' . $data['seller_name'] . '%');
       }
       if (!empty($data['customer_id'])) {
           $where['customer_id'] = $data['customer_id'];
       }
       if(isset($data['report_status']) && !empty($data['report_status'])){
           $where['report_status'] = $data['report_status'];
       }/*else{
           $where['report_status'] = 2;
       }*/
       if (!empty($data['seller_id'])) {
           $where['seller_id'] = $data['seller_id'];
       }
       if (!empty($data['startTime']) && !empty($data['endTime'])) {
           $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
       }
       if (!empty($data['order_number'])) {
           $where['order_number'] = $data['order_number'];
       }
       if(!empty($data['paypal'])){
           $where['paypal'] = $data['paypal'];
       }
       Cookie::set('RiskManagement', $where, 3600);
       $status = input('status');
       if (!$where && $status) {
           $where = Cookie::get('RiskManagement');
       }
       $where['report_type'] = 4;
       $where['page_size'] = input('page_size',20);
       $where['page'] = input("page",1);
       $where['path'] = url("FinancialManagement/CustomsInsuranceReview");
       $where['from'] = 2;
       $list = BaseApi::getAdminReportListForFinancial($where);
//       print_r($list);die;
       /*$list_items = $list->items();*/
       $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
       $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
       /*foreach ((array)$list_items as $key => $value) {
           foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
               if ($value["report_status"] == $v["code"]) {
                   $list_items[$key]["report_name"] = $v["name"];
               }
           }
       }*/
       $this->assign([
           'list' => $list['data'],
           'statusSelectHtml' => $statusSelectHtml,
           'data' => $data,
           'riskConfig' => $riskConfig["data"]['report_type'],
           'op_ajax_url'=>url('FinancialManagement/async_opCustomersInsurance')
       ]);
       return View();
   }


    /**
     * 关税赔保列表（财务确认打款）
     */
    public function InsuranceReview(){
        $riskConfig = BaseApi::RiskConfig();
        $data = input();
        if (!empty($data['customer_name'])) {
            $where['customer_name'] = array('like', '%' . $data['customer_name'] . '%');
        }
        if (!empty($data['seller_name'])) {
            $where['seller_name'] = array('like', '%' . $data['seller_name'] . '%');
        }
        if (!empty($data['customer_id'])) {
            $where['customer_id'] = $data['customer_id'];
        }
        if(isset($data['report_status']) && !empty($data['report_status'])){
            $where['report_status'] = $data['report_status'];
        }/*else{
           $where['report_status'] = 2;
       }*/
        if (!empty($data['seller_id'])) {
            $where['seller_id'] = $data['seller_id'];
        }
        if (!empty($data['startTime']) && !empty($data['endTime'])) {
            $where['add_time'] = array(array('egt', strtotime($data['startTime'])), array('elt', strtotime($data['endTime'])));
        }
        if (!empty($data['order_number'])) {
            $where['order_number'] = $data['order_number'];
        }
        if(!empty($data['paypal'])){
            $where['paypal'] = $data['paypal'];
        }
        Cookie::set('RiskManagement', $where, 3600);
        $status = input('status');
        if (!$where && $status) {
            $where = Cookie::get('RiskManagement');
        }
        $where['report_type'] = 4;
        $where['page_size'] = input('page_size',20);
        $where['page'] = input("page",1);
        $where['path'] = url("FinancialManagement/CustomsInsuranceReview");
        $where['from'] = 2;
        $where['b_status'] = 1;
        $list = BaseApi::getAdminReportListForFinancial($where);
//       print_r($list);die;
        /*$list_items = $list->items();*/
        $report_status = $data['report_status'] ? $data['report_status'] : ($where['report_status'] ? $where['report_status'] : '');
        $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'], 'report_status', $report_status);
        /*foreach ((array)$list_items as $key => $value) {
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if ($value["report_status"] == $v["code"]) {
                    $list_items[$key]["report_name"] = $v["name"];
                }
            }
        }*/
        $this->assign([
            'list' => $list['data'],
            'statusSelectHtml' => $statusSelectHtml,
            'data' => $data,
            'riskConfig' => $riskConfig["data"]['report_type'],
            'op_ajax_url'=>url('FinancialManagement/async_opCustomersInsurance')
        ]);
        return View();

    }

    /**
     * 财务-关税陪保审核
     * @return \think\response\Json
     */
   public function async_opCustomersInsurance(){
       $rtn = ['code'=>100, 'msg'=>'操作失败，请重试'];
       if (request()->isAjax()){
           $params = request()->post();
           $validate = $this->validate($params,[
               ['id','require','参数错误'],
               //1-关税赔保审核,2-财务确认打款
               ['from','require','参数错误'],
               ['insurance_id','require','参数错误'],
               ['type','require|in:1,2','参数错误']
           ]);
           if(true !== $validate){
               $rtn['msg'] = $validate;
               return json($rtn);
           }
           $time = time();
           $operator_id = session("userid");
           $operator_name = session("username");
           $id = $params['id'];
           //来源：1-关税赔保审核,2-财务确认打款
           $from = $params['from'];
           $insurance_id = $params['insurance_id'];
           //1-通过（或确认打款），2-不通过
           $type = $params['type'];
           try{
               $res = 0;
               if ($from == 1){ //关税赔保审核
                   $res = Db::name(REPORTS_CUSTOMS_INSURANCE)
                       ->where(['id' =>$insurance_id])->update([
                           'status'=>$type,
                           'update_user_id'=>$operator_id,
                           'update_user_name'=>$operator_name,
                           'update_time'=>$time,
                       ]);
                   if ($type == 2){
                       //审核不通过需要同步reports状态
                       $res2 = Db::name(REPORTS)
                           ->where(['id' =>$id])->update([
                               //状态 在api配置: 1-待处理（waiting process）、2-处理中（processing）、3-（已处理）接受处理关闭（case closed(has been established)）、4-驳回处理关闭（case closed(has not been established)）、5-撤销（case withdraw）
                               'report_status'=>4,
                               'operator_id'=>$operator_id,
                               'operator'=>$operator_name,
                               'edit_time'=>$time,
                           ]);
                       $res = $res && $res2;
                   }
               }else if($from == 2){ //财务确认打款
                   //1、更新财务打款状态
                   $res1 = Db::name(REPORTS_CUSTOMS_INSURANCE)
                       ->where(['id' =>$insurance_id])->update([
                           'finance_status'=>$type,
                           'update_user_id'=>$operator_id,
                           'update_user_name'=>$operator_name,
                           'update_time'=>$time,
                       ]);
                   //2、更新reports处理状态
                   $res2 = Db::name(REPORTS)
                       ->where(['id' =>$id])->update([
                           //状态 在api配置: 1-待处理（waiting process）、2-处理中（processing）、3-（已处理）接受处理关闭（case closed(has been established)）、4-驳回处理关闭（case closed(has not been established)）、5-撤销（case withdraw）
                           'report_status'=>3,
                           'operator_id'=>$operator_id,
                           'operator'=>$operator_name,
                           'edit_time'=>$time,
                       ]);
                   $res = $res1 && $res2;
               }
               if ($res){
                   $rtn['code'] = 200;
                   $rtn['msg'] = '操作成功';
               }
           }catch (Exception $e){
               $msg = '操作异常：'.$e->getMessage();
               $log_msg = '操作异常：'.$e->getMessage().', File:'.$e->getFile().'['.$e->getLine().']';
               $rtn['msg'] = $msg;
               Log::record($log_msg, Log::ERROR);
           }
       }else{
           $rtn['msg'] = '错误的访问';
       }
       return json($rtn);
   }


    /**
     * 遍历风控状态
     * [statusSelect description]
     * @return [type] [description]
     * @author wang   2018-08-04
     */
    public function statusSelect($data = array(), $selectId = '', $status)
    {
        $html = '';
        $select = '';
        $html .= '<select name="' . $selectId . '" id="' . $selectId . '" class="form-control input-small inline">';
        $html .= '<option value="">请选择</option>';
        foreach ((array)$data as $key => $value) {
            if ($status == $value["code"]) {
                $select = 'selected = "selected"';
            }
            $html .= '<option ' . $select . ' value="' . $value["code"] . '">' . $value["name"] . '</option>';
            $select = '';
        }
        $html .= '</select>';
        return $html;
    }

    /*
     * 赔保审核确定付款
     * */
    public function PaymentCompleted(){
        $data = request()->post();
        $where['order_number'] = $str['order_number'] = input("order_number");
        $where['id'] = input("id");
        if (!$where['order_number']) {
            echo json_encode(array('code' => 100, 'result' => '订单号不能为空'), true);
            exit;
        }
        if (!$where['id']) {
            echo json_encode(array('code' => 100, 'result' => '订单赔保号不能为空'), true);
            exit;
        }
        $data_data['report_status'] = 3;
        $data_data['edit_time'] = time();
        $data_data['operator_id'] = Session::get('userid');
        $data_data['operator'] = Session::get('username');
        $report_status = Db::name(REPORTS)->where(['id' => $data['id']])->value("report_status");
        $list = Db::name(REPORTS)->where($where)->update($data_data);
        $update_report_status = 3;
        if($list){
            $reports_log['reports_id'] = $data["id"];
            $reports_log['operation'] = "后台关税赔保审核确定退款";
            $reports_log['operator'] = Session::get('userid');
            $reports_log['operator_name'] = Session::get('username');
            $reports_log['operator_type'] = 3;
            $reports_log['add_time'] = time();
            $reports_log['order_status_from'] = $report_status;
            $reports_log['order_status'] = $update_report_status;
            $add_log = Db::name(REPORTS_LOG)->insert($reports_log);
            if(!$add_log){
                Log::write("add_log error,data:".json_encode($add_log));
            }
            echo json_encode(array('code' => 200, 'result' => '操作成功'), true);
            exit;
        }else{
            echo json_encode(array('code' => 200, 'result' => '操作失败'), true);
            exit;
        }


    }

    /**
     * 添加affiliate奖金
     * @return [type] [description]
     * @author kevin   2019-09-23
     */
    public function addAffiliateBonus(){
        if(request()->isPost()){
            $input_data = input();
            $param_data = array();
            $param_data['affiliate_id'] = input("affiliate_id");
            if(empty($param_data['affiliate_id'])){
                return array('code'=>100,'result'=>'affiliate ID 不能为空');
            }
            $affiliate_list = BaseApi::FinancialAudit(['affiliate_id'=>$param_data['affiliate_id']]);
            if(empty($affiliate_list['data']['list'])){
                return array('code'=>100,'result'=>'affiliate用户不存在');
            }
            $param_data['amount'] = input("amount");
            if(empty($param_data['amount'])){
                return array('code'=>100,'result'=>'奖金金额不能为空');
            }
            $enclosure = $input_data["enclosure"];
            if(!empty($enclosure)){
                $param_data["enclosure"] = json_encode($enclosure);
            }else{
                return array('code'=>100,'result'=>'必须上传审核截图');
            }
            $param_data['apply_type'] = 2;
            $param_data['status'] = 0;
           /* $param_data['business_edit_time'] = time();
            $where['business_operator']  = Session::get('username');
            $param_data['business_operator_id']  = Session::get('userid');
            $param_data['business_operator_ip']  = $_SERVER["REMOTE_ADDR"];
            $param_data['check_user_name']       = Session::get('username');
            $param_data['check_reason']       = '后台添加奖金自动审核';
            $param_data['check_time']       = time();*/
            $param_data['start_time'] = time();
            $param_data['end_time'] = time()+3600*24*30;
            $param_data['add_time'] = time();
            $res = Db::name(AFFILIATE_APPLY)->insert($param_data);
            if($res){
                return array('code'=>200,'result'=>'操作成功');
            }else{
                return array('code'=>100,'result'=>'操作失败');
            }
        }else{
            return view();
        }
    }


    /*
* 远程上传
* */
    public function remoteUpload(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = localUpload();
        /*$res['code'] = 200;
        $res['msg'] = "Success";
        $res['url'] = $localres['url'];
        $res['complete_url'] = $localres['url'];*/
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['AFFILIATE_IMAGES'].date("Ymd");
            $config = [
                'dirPath'=>$remotePath, // ftp保存目录
                'romote_file'=>$localres['FileName'], // 保存文件的名称
                'local_file'=>$localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            if($upload){
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['url'] = $remotePath.'/'.$localres['FileName'];
                $res['complete_url'] = DX_FTP_ACCESS_URL.'/'.$remotePath.'/'.$localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            echo json_encode($res);
        }
    }

    /*
     * 订单退款统计
     * add by 20191022 kevin
     * */
    public function orderRefund(){
        /*获取币种*/
        $baseApi = new BaseApi();
        $currency_info_api = $baseApi::getCurrencyList();
        $currency_info_data = $currency_info_api['data'];
        $param_data['page_size'] = input('page_size',20);
        $param_data['page'] = input("page",1);
        //if(request()->isPost()){
            $param_data = [];
            $data = input();
            if(!empty($data['payment_txn_id'])){
                $param_data['payment_txn_id'] = QueryFiltering($data['payment_txn_id']);
            }

            if(!empty($data['order_number'])){
                $param_data['order_number'] = QueryFiltering($data['order_number']);
            }
            if(!empty($data['currency_code'])){
                $param_data['currency_code'] = $data['currency_code'];
            }
            if(!empty($data['startTime'])){
                $param_data['startTime'] = $data['startTime'];
            }
            if(!empty($data['endTime'])){
                $param_data['endTime'] = $data['endTime'];
            }
            $RefundOrder = BaseApi::getOrderRefundSummary($param_data);
        //}
 /*       if(!empty($data['status']) && $data['status'] == 1){
            if(!empty($ExportRefundOrder['data'])){
                foreach ((array)$ExportRefundOrder['data'] as $k => $v) {
                    $order_master_number = !empty($v['order_master_number'])?$v['order_master_number']:'';
                    if($order_master_number == ''){
                        continue;
                    }
                    $order_number = !empty($v["order_number"])?$v["order_number"]:'';

                    if(!empty($v['refunded_amount'])  && !empty($v['exchange_rate'])){
                        $refunded_amount = abs(sprintf("%.2f", $v['amount']/$v['exchange_rate']));
                    }else{
                        $refunded_amount = '';
                    }
                    $pay_channel = !empty($v['pay_channel'])?$v['pay_channel']:'';
                    $country_code = !empty($v['country_code'])?$v['country_code']:'';
                    $pay_time = !empty($v['pay_time'])?date('Y-m-d h:i:s',$v['pay_time']):'';

                    $Export[] = ['payment_txn_id'=>$v['payment_txn_id'],'order_master_number'=>$order_master_number,
                        'order_number'=>$order_number,'reason'=>$v['remarks'],'refunded_amount'=>$refunded_amount,'pay_channel'=>$pay_channel,'txn_type'=>!empty($v['txn_type'])?$v['txn_type']:'',
                        'country_code'=>$country_code,'Operator'=>$v['operator_name'],'pay_time'=>$pay_time,'txn_time'=>!empty($v["txn_time"])?$v["txn_time"]:''
                    ];
                }
                // dump($Export);exit;
                $header_data =['payment_txn_id'=>'Invoice ID','order_master_number'=>'订单号','order_number'=>'子订单号',
                    'reason'=>'退款原因','refunded_amount'=>'退款金额（换算为USD）','pay_channel'=>'支付渠道','txn_type'=>'交易类型',
                    'country_code'=>'国家','Operator'=>'操作人','pay_time'=>'收款时间','txn_time'=>'退款时间',
                ];
                $tool = new ExcelTool();
                if(!empty($Export)){
                    $tool ->export('Refund',$header_data,$Export,'sheet1');
                }else{
                    echo '没查到数据';
                    exit;
                }

            }

        }*/
        $this->assign(['RefundOrder'=>!empty($RefundOrder['data'])?$RefundOrder['data']:'','currency_info_data'=>$currency_info_data]);
        return View();
    }
}