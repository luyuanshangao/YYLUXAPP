<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\queue\Job;
use \think\Session;
use app\admin\dxcommon\ExcelTool;
use app\admin\dxcommon\BaseApi;
//use app\common\ase\aes;

/*
 * 运营管理-虚拟币挂管理
 * author: Wang
 * AddTime:2018-10-31
 */
class VirtualCurrencyManagement extends Action
{
	public function __construct(){
        Action::__construct();
          define('S_CONFIG', 'dx_sys_config');//Nosql数据表

    }
    /*
     * 积分管理及导出
     * $data 默认不为空时为导出
     * $Export 判断是否为导出，空为查询数据
     */
    public function StoreCredit($data = array(),$Export=''){
        //不为空为导出，为空为查询
        if(empty($data)){
            if($data = request()->post()){
            }else{
                $data = input();
            }
            $currency = BaseApi::getCurrencyList();
            $data['page_size'] = config('paginate.list_rows');
        }

        $data['path'] = '/VirtualCurrencyManagement/StoreCredit';
        $data = ParameterCheck($data);
        if(!$data['page']){
            $data['page'] = 1;
        }
        $list = BaseApi::StoreCredit($data);
        if(!empty($list['data']['items'])){
            $aes =  aes();
            foreach($list['data']['items'] as $k=>$v){
                if($v["EmailUserName"]){
                    $EmailUserName = $aes->decrypt($v['EmailUserName'],'AffiliateLevel','PayPalEU');//解密邮件前缀
                    $list['data']['items'][$k]['EmailUserName'] = $EmailUserName.'@'.$v['EmailDomainName'];
                }
            }
        }
        if($Export == ''){
            $this->assign(['list'=>$list['data']['items'],'page'=>$list['data']['Page'],'currency'=>$currency["data"],'data'=>$data]);
            return View();
        }else{
            return $list['data']['items'];
        }
    }
    /*
    * 积分管理详情及导出
    * $data 默认不为空时为导出
    * $Export 判断是否为导出，空为查询数据
    */
    public function StoreCreditDetails($data=array(),$Export=''){
        if(empty($data)){
           $data = input();
           $data = ParameterCheck($data);
        }
        if(!empty($data["id"])){
            if(!$data['page']){
                $where['page'] = 1;
            }else{
                $where['page'] = $data['page'];
            }
            if(!empty($data["startTime"]) && !empty($data["endTime"])){
                $where['startTime'] = strtotime($data['startTime']);
                $where['endTime']   = strtotime($data['endTime']);
            }
            if(!empty($data["TransactionType"])){
                $where['TransactionType'] = $data["TransactionType"];
            }
            $where["page_size"] = config('paginate.list_rows');
            $where['id'] = $data["id"];
            $where['path'] = '/VirtualCurrencyManagement/StoreCreditDetails';
            $list = BaseApi::StoreCreditDetails($where);//dump($list);
            $aes =  aes();
            if(!empty($list['data']["EmailUserName"])){
                $list['data']["EmailUserName"] = $aes->decrypt($list['data']["EmailUserName"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                $list['data']["EmailUserName"] = $list['data']["EmailUserName"].'@'.$list['data']["EmailDomainName"];
            }
        }
        if($Export==''){
            $this->assign(['list'=>$list['data'],'page'=>$list['data']['Page'],'data'=>$data]);
            return View();
        }else{
            return $list['data']['items'];
        }
    }
    /*
     *StoreCredit管理数据导出
     */
    public function Export(){
        $data = input();
        $data_array = array();
        $data['page_size'] = 100;
        $page = 1;
        while(true){
            $data['page'] = $page;
            $list = $this->StoreCredit($data,1);
            $page++;
            if($list){
                foreach($list as $k=>$v){
                    $data_array[] = ['CustomerID'=>$v['CustomerID'],'EmailUserName'=>$v['EmailUserName'],
                        'CurrencyType'=>$v['CurrencyType'],'UsableAmount'=>$v['UsableAmount'],'FreezeAmount'=>$v['FreezeAmount']
                    ];
                }
            }else{
                break;
            }
        }
        $header_data =['CustomerID'=>'客户ID','EmailUserName'=>'客户Email',
            'CurrencyType'=>'币种','UsableAmount'=>'可用余额','FreezeAmount'=>'待生效余额'
        ];
        $tool = new ExcelTool();
        if($data_array){
            $tool ->export(date('YmdHis'),$header_data,$data_array,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }
    /*
     * StoreCredit详情导出
     */
    public function DetailsExport(){
        $data = input();
        $ConfigStatus =  publicConfig(S_CONFIG,'TransactionType');
        $Config = json_decode($ConfigStatus["result"]["ConfigValue"],true);
        $Transaction =  publicConfig(S_CONFIG,'TransactionStatus');
        $TransactionStatus = json_decode($Transaction["result"]["ConfigValue"],true);
        $data = ParameterCheck($data);
        if(empty($data['id'])){
            echo '确少对应用户ID';
            exit;
        }
        $data['page_size'] = 100;
        $page = 1;
        while(true){
            $data['page'] = $page;
            $list = $this->StoreCreditDetails($data,1);
            $page++;
            if($list){
                foreach($list as $k=>$v){
                    $TransactionType = '';
                    $Status = '';
                    foreach($Config as $ke=>$ve){
                         if($ke == $v['TransactionType']){
                             $TransactionType = $ve;
                         }
                    }
                    foreach($TransactionStatus as $kStatus=>$vStatus){
                        if($kStatus == $v['TransactionStatus']){
                            $Status = $vStatus;
                        }
                    }
                    $data_array[] = ['id'=>$data['id'],'OrderNumber'=>(string)$v['OrderNumber'].',','AccountSource'=>$v['AccountSource'],
                        'Operator'=>$v['Operator'],'TransactionType'=>$TransactionType,'TransactionTime'=>date("Y-m-d H:i:s",$v['TransactionTime']),
                        'TransactionAmount'=>$v['TransactionAmount'],'TransactionStatus'=>$Status,'Memo'=>$v['Memo']
                    ];
                }
            }else{
                break;
            }
        }
        $header_data =['id'=>'用户ID','OrderNumber'=>'订单号','AccountSource'=>'数据来源',
            'Operator'=>'操作者','TransactionType'=>'交易类型','TransactionTime'=>'交易时间',
            'TransactionAmount'=>'交易金额','TransactionStatus'=>'交易状态','Memo'=>'备注'
        ];
        $tool = new ExcelTool();
        if($data_array){
            $tool ->export(date('YmdHis'),$header_data,$data_array,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

    /*
     * DxPoints管理
     */
    public function DxPoints(){
        $data = input();
        $data = ParameterCheck($data);
        $data["page_size"] = config('paginate.list_rows');
        $data["path"] = '/VirtualCurrencyManagement/DxPoints';

        if(empty($data['page'])){
           $data['page'] = 1;
        }

        $list = BaseApi::DxPoints($data);
        // echo '--'.date("Y-m-d H:i:s",time());
        $aes =  aes();
        if(!empty($list['data']["items"])){
           foreach ($list['data']["items"] as $key => $value) {
                if(!empty($value["EmailUserName"])){
                    $EmailUserName = $aes->decrypt($value["EmailUserName"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                    $list['data']["items"][$key]["EmailUserName"] = $EmailUserName.'@'. $value["EmailDomainName"];
                }
           }
        }

        $data_page['page'] = $data['page'];
        $data_page['countPage'] = $list['data']["count"];

        $Page = CountPage($list['data']["count"],$data_page,$data["path"]);
        $this->assign(['list'=>$list['data']["items"],'page'=>$Page,'data'=>$data]);
        return View();
    }
    /**
     * 自定义分页
     * [countPage description]
     * @return [type] [description]
     */
    // public function CountPage($count,$data_page,$url){

    //     if(!empty($count)){
    //          $html .= '<ul class="pagination">';
    //          $page_size = 7;
    //          // $page_size = config('paginate.list_rows');
    //          $totalPage = ceil($count/$page_size);//算总页数
    //          $url_centre = '';//中间翻页
    //          if(empty($data_page['page'])){
    //               $page = 1;
    //          }else{
    //               $page = $data_page['page'];
    //               unset($data_page['page']);
    //          }
    //          if(!empty($data_page)){
    //             foreach ($data_page as $key => $value) {
    //                $url_centre .= '&'.$key.'='.$value;
    //             }
    //          }

    //          if($totalPage<=11){
    //             for($i=1;$i<=$totalPage;$i++){
    //                 //上一页
    //                 if($page<=1 && $i==1){
    //                    $html .= '<li class="disabled"><span>«</span></li>';
    //                 }else if($i==1){
    //                    $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
    //                 }
    //                 //中间数字页
    //                 if($page == $i){
    //                     $html .= '<li class="active"><span>'.$i.'</span></li>';
    //                 }else{
    //                     $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
    //                 }

    //                 //下一页
    //                 if($page>=$i && $totalPage == $i){
    //                    $html .= '<li class="disabled"><span>»</span></li>';
    //                 }else if($totalPage == $i){
    //                    $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
    //                 }
    //             }
    //          }else if($totalPage>11){
    //             $endPage = $totalPage-6;
    //             //作为末级
    //             if($page<=6){
    //                 $end =11;
    //                 for ($i=1; $i<=11 ; $i++) {
    //                     //上一页
    //                     if($page<=1 && $i==1){
    //                        $html .= '<li class="disabled"><span>«</span></li>';
    //                     }else if($i==1){
    //                        $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
    //                     }
    //                     //中间数字页
    //                     if($i<=8){
    //                         if($page == $i){
    //                             $html .= '<li class="active"><span>'.$i.'</span></li>';
    //                         }else{
    //                             $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
    //                         }
    //                     }else if($i==9){
    //                         $html .= '<li class="disabled"><span>...</span></li>';
    //                     }else if($i==10){
    //                         $html .= '<li><a href="'.$url.'?page='.($totalPage-2).$url_centre.'">'.($totalPage-2).'</a></li>';
    //                     }else if($i==11){
    //                         $html .= '<li><a href="'.$url.'?page='.($totalPage-1).$url_centre.'">'.($totalPage-1).'</a></li>';
    //                     }

    //                      //下一页
    //                     if($page>=$i && $end == $i){
    //                        $html .= '<li class="disabled"><span>»</span></li>';
    //                     }else if($end == $i){
    //                        $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
    //                     }
    //                 }
    //             }else if($page>6 && $page<$endPage){
    //                  $end =13;
    //                  for ($i=1; $i<=13 ; $i++) {
    //                     //上一页
    //                     if($page<=1 && $i==1){
    //                        $html .= '<li class="disabled"><span>«</span></li>';
    //                     }else if($i==1){
    //                        $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
    //                     }
    //                     //中间数字页
    //                     if($i<=2){
    //                         if($page == $i){
    //                             $html .= '<li class="active"><span>'.$i.'</span></li>';
    //                         }else{
    //                             $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
    //                         }
    //                     }else if($i==3){
    //                         $html .= '<li class="disabled"><span>...</span></li>';
    //                     }else if($i==4){
    //                         $html .= '<li><a href="'.$url.'?page='.($page-3).$url_centre.'">'.($page-2).'</a></li>';
    //                     }else if($i==5){
    //                         $html .= '<li><a href="'.$url.'?page='.($page-2).$url_centre.'">'.($page-2).'</a></li>';
    //                     }else if($i==6){
    //                         $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">'.($page-1).'</a></li>';
    //                     }else if($i==7){
    //                         $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                     }else if($i==8){
    //                         $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">'.($page+1).'</a></li>';
    //                     }else if($i==9){
    //                         $html .= '<li><a href="'.$url.'?page='.($page+2).$url_centre.'">'.($page+2).'</a></li>';
    //                     }else if($i==10){
    //                         $html .= '<li><a href="'.$url.'?page='.($page+3).$url_centre.'">'.($page+3).'</a></li>';
    //                     }else if($i==11){
    //                         $html .= '<li class="disabled"><span>...</span></li>';
    //                     }else if($i==12){
    //                         $html .= '<li><a href="'.$url.'?page='.($totalPage-2).$url_centre.'">'.($totalPage-2).'</a></li>';
    //                     }else if($i==13){
    //                         $html .= '<li><a href="'.$url.'?page='.($totalPage-1).$url_centre.'">'.($totalPage-1).'</a></li>';
    //                     }

    //                      //下一页
    //                     if($page>=$i && $end == $i){
    //                        $html .= '<li class="disabled"><span>»</span></li>';
    //                     }else if($end == $i){
    //                        $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
    //                     }
    //                 }
    //             }else if($page>=$endPage){
    //                  $end =12;
    //                  for ($i=1; $i<=12 ; $i++) {
    //                     //上一页
    //                     if($page<=1 && $i==1){
    //                        $html .= '<li class="disabled"><span>«</span></li>';
    //                     }else if($i==1){
    //                        $html .= '<li><a href="'.$url.'?page='.($page-1).$url_centre.'">«</a></li>';
    //                     }
    //                     //中间数字页
    //                     if($i<=2){
    //                         if($page == $i){
    //                             $html .= '<li class="active"><span>'.$i.'</span></li>';
    //                         }else{
    //                             $html .= '<li><a href="'.$url.'?page='.$i.$url_centre.'">'.$i.'</a></li>';
    //                         }
    //                     }else if($i==3){
    //                         $html .= '<li class="disabled"><span>...</span></li>';
    //                     }else if($i==4){
    //                         if($page != ($totalPage-8)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-8).$url_centre.'">'.($totalPage-8).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==5){
    //                         if($page != ($totalPage-7)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-7).$url_centre.'">'.($totalPage-7).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==6){
    //                         if($page != ($totalPage-6)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-6).$url_centre.'">'.($totalPage-6).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==7){
    //                         if($page != ($totalPage-5)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-5).$url_centre.'">'.($totalPage-5).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==8){
    //                         if($page != ($totalPage-4)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-4).$url_centre.'">'.($totalPage-4).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==9){
    //                         if($page != ($totalPage-3)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-3).$url_centre.'">'.($totalPage-3).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==10){
    //                         if($page != ($totalPage-2)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-2).$url_centre.'">'.($totalPage-2).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==11){
    //                         if($page != ($totalPage-1)){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage-1).$url_centre.'">'.($totalPage-1).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }else if($i==12){
    //                         if($page != $totalPage){
    //                             $html .= '<li><a href="'.$url.'?page='.($totalPage).$url_centre.'">'.($totalPage).'</a></li>';
    //                         }else{
    //                             $html .= '<li class="active"><span>'.$page.'</span></li>';
    //                         }

    //                     }

    //                      //下一页
    //                     if($page>=$i && $end == $i){
    //                        $html .= '<li class="disabled"><span>»</span></li>';
    //                     }else if($end == $i){
    //                        $html .= '<li><a href="'.$url.'?page='.($page+1).$url_centre.'">»</a></li>';
    //                     }
    //                 }
    //             }//
    //          }
    //          $html .= '</ul>';
    //          return $html;
    //     }else{
    //       return;
    //     }
    // }
    /**
     * DxPoints用户详情
     * [DxPointsDetails description]
     */
    public function DxPointsDetails($data=array(),$Export=''){
       $DxPointsStatus = array();
       if(empty($data)){
          $data = input();
          $data = ParameterCheck($data);
          $data["page_size"] = config('paginate.list_rows');
          $ConfigStatus =  publicConfig(S_CONFIG,'DxPointsStatus');
          $DxPointsStatus = json_decode($ConfigStatus["result"]["ConfigValue"],true);
          $Operate =  publicConfig(S_CONFIG,'OperateReason');
          $OperateReason = json_decode($Operate["result"]["ConfigValue"],true);
       }

       if(!empty($data['id'])){

            $data["path"] = '/VirtualCurrencyManagement/DxPointsDetails';
            if(empty($data['page'])){
               $data['page'] = 1;
            }
            $list = BaseApi::DxPointsDetails($data);
            $aes =  aes();
            if(!empty($list['data']["EmailUserName"])){
                $EmailUserName = $aes->decrypt($list['data']["EmailUserName"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                $list['data']["EmailUserName"] = $EmailUserName.'@'. $list['data']["EmailDomainName"];
            }
       }
       if($Export == ''){
           $this->assign(['list'=>$list['data'],'page'=>$list['data']['Page'],'data'=>$data,'DxPointsStatus'=>$DxPointsStatus,'OperateReason'=>$OperateReason]);
           return View();
       }else{
           return $list['data']['items'];
       }

    }
    /**
     * DxPointsDetailsExport 导出用户详情
     */
    public function DxPointsDetailsExport(){
          $data = input();
          $data = ParameterCheck($data);//dump($data);exit;
          if(isset($data["id"])){
              $data_array = array();
              $header_data = array();
              $Operate =  publicConfig(S_CONFIG,'OperateReason');
              $OperateReason = json_decode($Operate["result"]["ConfigValue"],true);
              $ConfigStatus =  publicConfig(S_CONFIG,'DxPointsStatus');
              $DxPointsStatus = json_decode($ConfigStatus["result"]["ConfigValue"],true);
              $data['page_size'] = 1;
              $page = 1;
              while(true){
                 $data['page'] = $page;
                 $list = $this->DxPointsDetails($data,1);
                 $page++;
                 if($list){
                    foreach($list as $k=>$v){
                        $TransactionType = '';
                        $Reason = '';
                        $Status = '';
                        $OperateTypeValue = '';
                        $OperateType = '';
                        foreach($OperateReason as $ke=>$ve){
                             if($ke == $v['OperateReason']){
                                 $Reason = $ve;
                             }
                        }
                        // foreach ($DxPointsStatus as $kStatus => $vStatus) {
                        //      if($kStatus == $v['Status']){
                        //          $Status = $vStatus;
                        //      }
                        // }
                        if($v['ActiveFlag'] == 0){
                           $OperateTypeValue = '无效';
                        }else if($v['ActiveFlag'] == 1){
                           $OperateTypeValue = '有效';
                        }
                        if($v['OperateType'] == 0){
                             $OperateType = '扣除';
                        }else if ($v['OperateType'] == 1){
                             $OperateType = '添加';
                        }else{
                             $OperateType = '12';
                        }

                        $data_array[] = ['id'=>$data['id'],'OrderNumber'=>$v['OrderNumber'].',','OperateReason'=>$Reason,
                            'Operator'=>$v['Operator'],'OperateType'=>$OperateType,'TransactionTime'=>date("Y-m-d H:i:s",$v['TransactionTime']),
                            'PointsCount'=>$v['PointsCount'],'ActiveFlag'=>$OperateTypeValue,'Memo'=>$v['Memo']
                        ];
                    }

                 }else{
                    break;
                 }
              }
              $header_data =['id'=>'用户ID','OrderNumber'=>'订单号','OperateReason'=>'数据来源',
                        'Operator'=>'操作者','OperateType'=>'交易类型','TransactionTime'=>'交易时间',
                        'PointsCount'=>'DxPoints','ActiveFlag'=>'是否生效','Memo'=>'备注'
                    ];
              $tool = new ExcelTool();
              if($data_array){
                   $tool ->export(date('YmdHis'),$header_data,$data_array,'sheet1');
              }else{
                   echo '没查到数据';
                   exit;
              }
          }
    }
    /**
     * Affililate佣金管理
     * [Affililate description]
     */
    public function Affililate(){
        $DxPointsStatus = array();
        $data = input();
        $data = ParameterCheck($data);

        // $data["page_size"] = 1;
        $data["page_size"] = config('paginate.list_rows');
        $data["path"] = '/VirtualCurrencyManagement/Affililate';
        if(empty($data['page'])){
           $data['page'] = 1;
        }
        $list = BaseApi::Affililate($data);
        $RCode_arr = array();
        if(!empty($list['data']["items"])){
            $RCode_arr = array_column($list['data']["items"],'RCode');
            //$commission_data = BaseApi::getAffililateCommissionData($data);
            $aes =  aes();
            foreach ($list['data']["items"] as $key => $value) {
                    if(!empty($value["PayPalEU"])){
                        $PayPalEU = '';
                        $PayPalEU = $aes->decrypt($value["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                        $list['data']["items"][$key]["PayPalEU"] = $PayPalEU.'@'.$value["PayPalED"];
                    }
            }
        }
        $this->assign(['list'=>$list['data']["items"],'page'=>$list['data']['Page'],'data'=>$data]);
        return View();
    }
     /**
     * AffililateDetails佣金管理详情
     * [AffililateDetails description]
     */
    public function AffililateDetails($data=array(),$Export=''){
        if(empty($data)){
            $data = input();
            $data = ParameterCheck($data);
            $data["page_size"] = config('paginate.list_rows');
        }

        if(isset($data["id"])){
            $data["path"] = '/VirtualCurrencyManagement/AffililateDetails';
            if(empty($data['page'])){
               $data['page'] = 1;
            }

            $list = BaseApi::AffililateDetails($data);
            if(!empty($list['data']["PayPalEU"])){
                $PayPalEU = '';
                $aes =  aes();
                $PayPalEU = $aes->decrypt($list['data']["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                $list['data']["PayPalEU"] = $PayPalEU.'@'.$list['data']["PayPalED"];
            }
            if($Export == ''){
               $this->assign(['list'=>$list['data'],'page'=>$list['data']['Page'],'data'=>$data]);
            }else{
               return $list['data']['items'];
            }


        }

        return View();
    }
     /**
     * AffililateDetails佣金管理详情导出
     * [AffililateDetails description]
     */
    public function AffililateDetailsExport(){
        $data = input();
        $data = ParameterCheck($data);
        $data_array = array();
        $page = 1;
        $data["page_size"] = 100;
        if(!empty($data['id'])){
             while(true){
                 $data['page'] = $page;
                 $list = $this->AffililateDetails($data,1);
                 $page++;
                 if($list){
                       foreach($list as $k=>$v){
                            $PointsCountType = '';
                            $ActiveFlag = '';
                            if($v['OperateType'] == 0 ){
                                $PointsCountType = '扣除';
                            }else if($v['OperateType'] == 1){
                                $PointsCountType = '添加';
                            }
                            if($v['ActiveFlag'] === 0 ){
                                $ActiveFlag = '未生效';
                            }else if($v['ActiveFlag'] == 1){
                                $ActiveFlag = '已生效';
                            }
                            $data_array[] = ['id'=>$data['id'],'OrderNumber'=>$v['OrderNumber'].',','Operator'=>$v['Operator'],
                                'OperateType'=>$PointsCountType,'ActiveFlag'=>$ActiveFlag,'TransactionTime'=>date("Y-m-d H:i:s",$v['TransactionTime']),
                                'PointsCount'=>$v['PointsCount'],'Memo'=>$v['Memo']
                            ];
                       }
                 }else{
                    break;
                 }
             }
             $header_data = ['id'=>'用户ID','OrderNumber'=>'订单号','Operator'=>'操作人',
                        'OperateType'=>'交易类型','ActiveFlag'=>'是否生效','TransactionTime'=>'交易时间',
                        'PointsCount'=>'佣金($)','Memo'=>'备注'
                        ];
             $tool = new ExcelTool();
             if($data_array){
                $tool ->export(date('YmdHis'),$header_data,$data_array,'sheet1');
             }else{
                echo '没查到数据';
                exit;
             }
        }
    }

}