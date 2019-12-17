<?php
namespace app\cic\model;
use think\Model;
use think\Db;
use vendor\aes\aes;
/**
 * 用户等级模型
 * @author
 * @version Kevin 2018/4/17
 */
class StoreCredit extends Model{
    protected $basic_info = 'cic_store_cardit_basic_info';
    protected $transaction = 'cic_store_credit_transaction';
    protected $customer = 'cic_customer';
    protected $points_basic_info = 'cic_points_basic_info';
    protected $points_details = 'cic_points_details';
    protected $affiliate_level = 'cic_affiliate_level';
    protected $referral_points_basic_info = 'cic_referral_points_basic_info';
    protected $referral_points_details = 'cic_referral_points_details';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    public function StoreCredit($data){
        $page_size = $data['page_size'];
        $path = $data['path'];
        $page = $data['page'];
        $where = array();
        if(!empty($data['CustomerID'])){
            $page_query['CustomerID'] = $where['BI.CustomerID'] = $data['CustomerID'];
        }
        if(!empty($data['EmailUserName'])){
            $page_query['EmailUserName'] = $data['EmailUserName'];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($data['EmailUserName'],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $where['CR.EmailUserName'] = $EmailUserName;
        }
        if(!empty($data['CurrencyType'])){
            $page_query['CurrencyType'] = $where['BI.CurrencyType'] = $data['CurrencyType'];
        }

        if($where){
            $res = $this->db->table($this->basic_info)
                ->alias("BI")
                ->join($this->customer." CR","BI.CustomerID = CR.ID")
                ->field("BI.CustomerID,BI.CurrencyType,BI.UsableAmount,BI.FreezeAmount,CR.EmailUserName,CR.EmailDomainName")
                ->where($where)
                ->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                    'page' => $page,
                    'path' => $path,
                    'query'=> $page_query
                ]);
        }else{
            $res = $this->db->table($this->basic_info)
                ->alias("BI")
                ->join($this->customer." CR","BI.CustomerID = CR.ID")
                ->field("BI.CustomerID,BI.CurrencyType,BI.UsableAmount,BI.FreezeAmount,CR.EmailUserName,CR.EmailDomainName")
                ->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                    'page' => $page,
                    'path' => $path
                ]);//return $this->db->getLastSql();return $res;
        }

        $list_data['items'] = $res->items();
        $list_data['Page'] = $res->render();
        return $list_data;
    }
    /*
     * admin StoreCredit管理用户详情
     */
    public function StoreCreditDetails($data){
        $page_size = $data['page_size'];
        if($data['id']){
            $where['BI.CustomerID'] = $data['id'];
            $res = $this->db->table($this->basic_info)
                ->alias("BI")
                ->join($this->customer." CR","BI.CustomerID = CR.ID")
                ->field("BI.CustomerID,BI.CurrencyType,BI.UsableAmount,BI.FreezeAmount,CR.EmailUserName,CR.EmailDomainName")
                ->where($where)
                ->find();
            if($res){
                $path = $data['path'];
                $page = $data['page'];
                $page_query['id'] = $where_transaction['CustomerID'] = $data['id'];
                if(!empty($data["startTime"]) && !empty($data["endTime"])){
                    $page_query["startTime"] = date("Y-m-d H:i:s",$data["startTime"]);
                    $page_query["endTime"]   = date("Y-m-d H:i:s",$data["endTime"]);
                    $where_transaction['TransactionTime'] = array('between',''.$data["startTime"].','.$data["endTime"].'');
                }
                if(!empty($data["TransactionType"])){
                    $page_query["TransactionType"]   = $where_transaction['TransactionType'] = $data["TransactionType"];
                }

                $list = $this->db->table($this->transaction)->where($where_transaction)
                                                            ->field('OrderNumber,CurrencyType,TransactionAmount,TransactionStatus,TransactionTime,TransactionType,Operator,AccountSource,Memo')
                                                            ->paginate($page_size,false,[
                                                                'type' => 'Bootstrap',
                                                                'page' => $page,
                                                                'path' => $path,
                                                                'query'=> $page_query
                                                            ]);
//                return $this->db->getLastSql();
                // $res['Sql']   = $this->db->getLastSql();
                $res['items'] = $list->items();
                $res['Page']  = $list->render();
            }
            return $res;
        }else{
            return false;
        }
    }
    /*
    *原始版
    * admin DxPoints管理
    */
    public function DxPoints1($data){
 $res['time_1'] = date("Y-m-d H:i:s",time());
        $page_size = $data['page_size'];
        $path = $data['path'];
        $page = $data['page'];
        $where = array();
        if(!empty($data['CustomerID'])){
            $page_query['CustomerID'] = $where['PBI.CustomerID'] = $data['CustomerID'];
        }
        if(!empty($data['EmailUserName'])){
            $page_query['EmailUserName'] = $data['EmailUserName'];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($data['EmailUserName'],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $where['CR.EmailUserName'] = $EmailUserName;
        }
        if(!empty($where)){
            $list = $this->db->table($this->points_basic_info)
                    ->alias("PBI")
                    ->join($this->customer." CR","PBI.CustomerID = CR.ID")
                    ->where($where)
                    ->field('PBI.CustomerID,PBI.TotalCount,PBI.UsedCount,PBI.UsableCount,PBI.InactiveCount,PBI.Memo,PBI.NewTotalCount,PBI.NewInactiveCount,CR.EmailUserName,CR.EmailDomainName')
                    ->paginate($page_size,false,[
                                                    'type' => 'Bootstrap',
                                                    'page' => $page,
                                                    'path' => $path,
                                                    'query'=> $page_query
                                                ]);
        }else{
$res['time_2'] = date("Y-m-d H:i:s",time());
$count = $this->db->table($this->points_basic_info)->count();
            $list = $this->db->table($this->points_basic_info)
                    ->alias("PBI")
                    ->join($this->customer." CR","PBI.CustomerID = CR.ID")
                    ->field('PBI.CustomerID,PBI.TotalCount,PBI.UsedCount,PBI.UsableCount,PBI.InactiveCount,PBI.Memo,PBI.NewTotalCount,PBI.NewInactiveCount,CR.EmailUserName,CR.EmailDomainName')
                     ->limit('0,20')
                    ->select();
                    // ->paginate($page_size,false,[
                    //                                 'type' => 'Bootstrap',
                    //                                 'page' => $page,
                    //                                 'path' => $path,
                    //                                 // 'query'=> $page_query
                    //                             ]);
        }
$res['time_3'] = date("Y-m-d H:i:s",time());
        $res['sql'] = $this->db->getLastSql();
        $res['count'] =$count;
        $res['items'] = $list;
        // $res['items'] = $list->items();
        // $res['Page']  = $list->render();
        return $res;
    }
    /*
    *改进后
    * admin DxPoints管理
    */
     public function DxPoints($data){
        // $res['time_1'] = date("Y-m-d H:i:s",time());
        $page_size = $data['page_size'];
        $path = $data['path'];
        $page = $data['page'];
        $where = array();
        if(!empty($data['CustomerID'])){
            $page_query['CustomerID'] = $where['ID'] = $data['CustomerID'];
        }
        if(!empty($data['EmailUserName'])){
            $page_query['EmailUserName'] = $data['EmailUserName'];
            vendor('aes.aes');
            $aes = new aes();
            $EmailUserName = $aes->encrypt($data['EmailUserName'],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $where['EmailUserName'] = $EmailUserName;
        }
        if(!empty($where)){
            $customer = $this->db->table($this->customer)->where($where)->field('ID,EmailUserName,EmailDomainName')->find();
            if($customer){
                 if(empty($data['countPage'])){
                      $count = $this->db->table($this->points_basic_info)->where(['CustomerID'=>$customer['ID']])->count();
                 }else{
                      $count = $data['countPage'];
                 }
                 $list = $this->db->table($this->points_basic_info)
                          ->field('CustomerID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount')
                          ->where(['CustomerID'=>$customer['ID']])
                          ->page($page,$page_size)
                          ->select();//return $list;
                   // $list = $this->db->table($this->points_basic_info)
                   //            ->field('CustomerID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount')
                   //            ->where(['CustomerID'=>$customer['ID']])
                   //            ->paginate($page_size,false,[
                   //                                  'type' => 'Bootstrap',
                   //                                  'page' => $page,
                   //                                  'path' => $path,
                   //                                  'query'=> $page_query
                   //                              ]);

            }
            $res['count'] = $count;
            $res['items'] = $list;
            // $res['Page']  = $list->render();
            foreach ((array)$res['items'] as $k => $v) {
                if(!empty($customer['EmailUserName'])){
                     $res['items'][$k]['EmailUserName'] = $customer['EmailUserName'];
                }
                if(!empty($customer['EmailDomainName'])){
                     $res['items'][$k]['EmailDomainName'] = $customer['EmailDomainName'];
                }
            }

        }else{
// $res['time_2'] = date("Y-m-d H:i:s",time());
             if(empty($data['countPage'])){
                  $count = $this->db->table($this->points_basic_info)->count();
             }else{
                  $count = $data['countPage'];
             }

             $list = $this->db->table($this->points_basic_info)
                              ->field('CustomerID,TotalCount,UsedCount,UsableCount,InactiveCount,Memo,NewTotalCount,NewInactiveCount')
                               ->page($page,$page_size)
                               ->select();

            // $res['time_3'] = date("Y-m-d H:i:s",time());
// $res['mysql'] = $this->db->getLastSql();
            $res['count'] = $count;
            $res['items'] = $list;

            // $res['items'] = $list->items();
            // $res['Page']  = $list->render();
            // $res['time_4'] = date("Y-m-d H:i:s",time());
            if(!empty($res['items'])){
                foreach ((array)$res['items'] as $k => $v) {
                      $customer = $this->db->table($this->customer)->where(['ID'=>$v['CustomerID']])->field('EmailUserName,EmailDomainName')->find();
                      $res['items'][$k]['EmailUserName'] = $customer['EmailUserName'];
                      $res['items'][$k]['EmailDomainName'] = $customer['EmailDomainName'];
                }
            }
             // $res['time_5'] = date("Y-m-d H:i:s",time());
        }
        // $res['sql'] = $this->db->getLastSql();
        return $res;
    }
    /*
    * admin DxPoints管理详情
    */
    public function DxPointsDetails($data){
         if(!empty($data['id'])){
                 $where['PBI.CustomerID'] = $data['id'];
                 $list = $this->db->table($this->points_basic_info)
                        ->alias("PBI")
                        ->join($this->customer." CR","PBI.CustomerID = CR.ID")
                        ->where($where)
                        ->field('PBI.CustomerID,PBI.TotalCount,PBI.UsedCount,PBI.UsableCount,PBI.InactiveCount,PBI.Memo,PBI.NewTotalCount,PBI.NewInactiveCount,CR.EmailUserName,CR.EmailDomainName')
                        ->find();
                 if($list){
                         $where_transaction = array();
                         $page_size = $data['page_size'];
                         $page = $data['page'];
                         $path = $data['path'];
                         $page_query['id'] = $where_transaction['CustomerID'] = $data['id'];
                          if(!empty($data["startTime"]) && !empty($data["endTime"])){
                                $page_query["startTime"] = $data["startTime"];
                                $page_query["endTime"]   = $data["endTime"];
                                $where_transaction['TransactionTime'] = array('between',''.strtotime($data["startTime"]).','.strtotime($data["endTime"]).'');
                          }
                          if(isset ($data["OperateType"])){
                                $page_query["OperateType"]   = $where_transaction['OperateType'] = $data["OperateType"];
                          }
                         $listDetails = $this->db->table($this->points_details)
                                                ->where($where_transaction)
                                                ->field('OrderNumber,OperateReason,Operator,OperateType,TransactionTime,ActiveFlag,Memo')
                                                ->paginate($page_size,false,[
                                                                                                'type' => 'Bootstrap',
                                                                                                'page' => $page,
                                                                                                'path' => $path,
                                                                                                'query'=> $page_query
                                                                                            ]);
                         $list['items'] = $listDetails->items();
                         $list['Page']  = $listDetails->render();
                 }

                 return $list;
         }
         return;
    }
    /*
    * admin Affililate佣金管理
    */
    public function Affililate($data){

        $page_size = $data['page_size'];
        $path = $data['path'];
        $page = $data['page'];
        $where = array();
        if(!empty($data['RCode'])){
            $page_query['RCode'] = $where['AL.RCode'] = $data['RCode'];
        }
        if(!empty($data['PayPalEU'])){
            $page_query['PayPalEU'] = $data['PayPalEU'];
            vendor('aes.aes');
            $aes = new aes();
            $PayPalEU = $aes->encrypt($data['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $where['AL.PayPalEU'] = $PayPalEU;
        }
        if(!empty($where)){
               $list = $this->db->table($this->affiliate_level)
                    ->alias("AL")
                    ->join($this->referral_points_basic_info." BPBI","AL.CustomerID = BPBI.CustomerID")
                    ->where($where)
                    ->field('AL.RCode,AL.CustomerID,AL.PayPalEU,AL.PayPalED,BPBI.NewTotalCount,BPBI.NewInactiveCount')
                    ->paginate($page_size,false,[
                                                    'type' => 'Bootstrap',
                                                    'page' => $page,
                                                    'path' => $path,
                                                    'query'=> $page_query
                                                ]);
        }else{
              $list = $this->db->table($this->affiliate_level)
                    ->alias("AL")
                    ->join($this->referral_points_basic_info." BPBI","AL.CustomerID = BPBI.CustomerID")
                    ->field('AL.RCode,AL.CustomerID,AL.PayPalEU,AL.PayPalED,BPBI.NewTotalCount,BPBI.NewInactiveCount')
                    ->paginate($page_size,false,[
                                                    'type' => 'Bootstrap',
                                                    'page' => $page,
                                                    'path' => $path,
                                                ]);
        }
        $res['items'] = $list->items();
        $res['Page']  = $list->render();
        return $res;

    }
     /*
    * admin Affililate佣金管理详情
    */
    public function AffililateDetails($data){
        if(isset($data['id'])){
              $where['AL.CustomerID'] = $data['id'];
              $list = $this->db->table($this->affiliate_level)
                    ->alias("AL")
                    ->join($this->referral_points_basic_info." BPBI","AL.CustomerID = BPBI.CustomerID")
                    ->where($where)
                    ->field('AL.RCode,AL.CustomerID,AL.PayPalEU,AL.PayPalED,BPBI.NewTotalCount,BPBI.NewInactiveCount')
                    ->find();
              if(!empty($list)){
                    $page_size = $data['page_size'];
                    $path = $data['path'];
                    $page = $data['page'];
                    $listDetails = array();
                    $where_transaction = array();
                    $page_query = array();
                    if(!empty($data['OrderNumber'])){
                       $page_query['OrderNumber'] = $where_transaction['OrderNumber'] = $data['OrderNumber'];
                    }
                    if(isset($data['OperateType'])){
                        $page_query['OperateType'] = $data['OperateType'];
                        $where_transaction['OperateType'] = $data['OperateType'];
                        // if($data['PointsCount']==0){
                        //    $where_transaction['PointsCount'] = ;
                        // }else if($data['PointsCount']==1){
                        //    $where_transaction['PointsCount'] = ;
                        // }
                    }
                    if(isset($data['ActiveFlag'])){
                        $page_query['ActiveFlag'] = $where_transaction['ActiveFlag'] = $data['ActiveFlag'];
                    }
                    if(!empty($data["startTime"]) && !empty($data["endTime"])){
                        $page_query["startTime"] = $data["startTime"];
                        $page_query["endTime"]   = $data["endTime"];
                        $where_transaction['TransactionTime'] = array('between',''.strtotime($data["startTime"]).','.strtotime($data["endTime"]).'');
                    }
                    $page_query['id'] = $where_transaction['CustomerID'] = $data['id'];
                    $listDetails = $this->db->table($this->referral_points_details)
                             ->field('CustomerID,OrderNumber,Operator,PointsCount,TransactionTime,Status,Memo,ActiveFlag,OperateType')
                             ->where($where_transaction)
                             ->paginate($page_size,false,[
                                                    'type' => 'Bootstrap',
                                                    'page' => $page,
                                                    'path' => $path,
                                                    'query'=> $page_query
                                                   ]);

                    // $list['sql'] = $this->db->getLastSql();
                    $list['items'] = $listDetails->items();
                    $list['Page']  = $listDetails->render();
              }
        }
        return $list;
    }
    /*
    * admin 修改 cic   cic_referral_points_details 表
    */
    public function WithdrawStatus($data){
        if(!empty($data['order_number']) && !empty($data['status'])){
             if($data['status'] == 3){
                  $where = array();
                  $where['ActiveFlag'] = 1;
                  $where['Status'] = 1;
                  $listDetails = $this->db->table($this->referral_points_details)->where(['OrderNumber'=>$data['order_number']])->update($where);
                  if($listDetails){
                     return apiReturn(['code'=>200,'data'=>'状态修改成功']);
                  }else{
                     return apiReturn(['code'=>100,'data'=>'状态修改失败']);
                  }
             }else{
                return apiReturn(['code'=>100,'data'=>'当前参数不被接受']);
             }
             //
        }else{
              return apiReturn(['code'=>100,'data'=>'参数传递有误']);
        }
    }
    /**
     * admin Affiliate用户统计
     * [AffiliateUserStatistics description]
     */
    public function AffiliateUserStatistics($data){
        $page_size = $data['page_size'];
        // $path = $data['path'];
        $page = $data['page'];
        // file_put_contents ('../log/lms/LogisticsUpdateSeller.log',$page.'----------------------', FILE_APPEND|LOCK_EX);
        $where = array();
        if(!empty($data['RCode'])){
           $where['AL.RCode'] = ['in',$data['RCode']];
        }
        if(!empty($data['CustomerID'])){
           $where['AL.CustomerID'] = $data['CustomerID'];
        }
        if(!empty($data['PayPalEU'])){
            vendor('aes.aes');
            $aes = new aes();
            $PayPalEU = $aes->encrypt($data['PayPalEU'],'AffiliateLevel','PayPalEU');//加密邮件前缀
            $where['AL.PayPalEU'] = $PayPalEU;
        }
        if(empty($data['countPage'])){
            $list['countPage'] = $this->db->table($this->affiliate_level)->alias("AL")->where($where)->count();
        }else{
            $list['countPage'] = $data['countPage'];
        }

        $list['list'] = $this->db->table($this->affiliate_level)
                    ->alias("AL")
                    ->join($this->referral_points_basic_info." BPBI","AL.CustomerID = BPBI.CustomerID",'left')
                    ->where($where)
                    ->page($page,$page_size)
                    ->order('RegistrationTimestamp desc')
                    ->field('AL.RCode,AL.CustomerID,AL.PayPalEU,AL.PayPalED,AL.RegistrationTimestamp,BPBI.NewTotalCount,BPBI.NewInactiveCount')
                    ->select();
                    // $list['sql'] = $this->db->table($this->affiliate_level)->getLastSql();
        return $list;
    }
    /**
     * 获取Affililate新用户数量
     * @param $data
     * @return string
     */
    public function AffiliateIdSum($data){
        $where = array();
        if(!empty($data["RCode"])){
            $affiliate_id   = implode(',',$data["RCode"]);
            $where['RCode'] = array('in',$affiliate_id);
            $where['IsNew'] = 1;
            $list   = $this->db->table($this->affiliate_level)->where($where)->field('RCode')->select();
            $sum = count($list);
            return apiReturn(['code'=>200,'data'=>array('sum'=>$sum)]);
        }else{
           return apiReturn(['code'=>100,'data'=>'参数出错']);;
        }
    }
     /**
     * admin  获取cic  id
     * [add_black description]
     */
    public function add_black($data){
         if(!empty($data['affiliate_id'])){
              $where = array();
              $data_admin =array();
              $where['RCode'] = ['in',$data['affiliate_id']];
              $list   = $this->db->table($this->affiliate_level)->where($where)->field('RCode,CustomerID')->select();
              if(!empty($list)){
                  foreach ($list as $k => $v) {
                     $data_admin[$v['RCode']] = $v['CustomerID'];
                  }
                  return apiReturn(['code'=>200,'data'=>$data_admin]);
              }else{
                  return apiReturn(['code'=>100,'data'=>'获取不到数据']);
              }
         }else{
              return apiReturn(['code'=>100,'data'=>'提交参数有误']);
         }
    }

    /*
     * 获取用户SC全部信息
     * */
    public function getAllStoreCarditBasicInfo($where){
        return $this->db->name('store_cardit_basic_info')->where($where)->column("CurrencyType,UsableAmount");
    }
}