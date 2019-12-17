<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use app\admin\dxcommon\BaseApi;
use think\Log;
use app\admin\model\AffiliateReport as AffiliateReportModel;
use app\admin\dxcommon\ExcelTool;
use app\admin\model\Businessmanagement as Business;
//use app\admin\controller\Tool;

/**
 * Add by:wang
 * AddTime:2018-12-14
 */
class AffiliateReport extends Action
{
	public function __construct(){
       Action::__construct();
       define('AFFILIATE_ORDER', 'affiliate_order');//mysql数据表
       define('MOGOMODB_P_CLASS', 'dx_product_class');//mysql数据表

    }
    /**
     * 报表统计
     * [ReportStatistics description]
     */
    public function ReportStatistics($data=array(),$export = false){
        if(empty($data)){   $data = ParameterCheck(input());  }
        $where = array();
        $where_sku = array();
        if(empty($data['page'])){    $data['page'] = 1;  }
        if(!empty($data['page_size'])){
            $data['page_size']  = $data['page_size'];
        }else{
            $data['page_size']  = config('paginate.list_rows');
        }
        if(!empty($data['affiliate_id'])){
              $affiliate_id = QueryFiltering($data['affiliate_id']);
              $data_page['affiliate_id'] = $data['affiliate_id']  = $affiliate_id;
              $where['affiliate_id'] = array('in',$affiliate_id);
        }
        if(!empty($data['order_number'])){
              $affiliate_id = QueryFiltering($data['order_number']);
              $data_page['order_number'] = $data['order_number']  = $affiliate_id;
              $where['order_number'] = array('in',$affiliate_id);
        }
        if(!empty($data['sku_id'])){
              $data['sku_code'] = $affiliate_id = QueryFiltering($data['sku_id']);
              $data_page['sku_id'] = $data['sku_code']  = $affiliate_id;
              $where_sku['sku_code'] = array('in',$affiliate_id);
        }
        if(!empty($data['settlement_status'])){
              $data_page['settlement_status'] = $data['settlement_status'];
              $where['settlement_status'] = $data['settlement_status'];
        }
        if(!empty($data['order_from'])){
            $data_page['order_from'] = $data['order_from'];
            $where['order_from'] = $data['order_from'];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $data_page['startTime'] = $data['startTime'];
            $data_page['endTime']   = $data['endTime'];
            $startTime = strtotime($data['startTime']);
            $endTime   = strtotime($data['endTime']);
            $where_sku['create_on'] = $where['create_on'] = array('between',$startTime.','.$endTime);
        }else{
            $data['startTime'] =  date('Y-m-d 00:00:00', strtotime("-1 month -1 day 00:00:00"));
            $data['endTime']   =  date('Y-m-d 23:59:59', strtotime("-1 day 23:59:59"));
            $where_sku['create_on'] = $where['create_on'] = array('between',strtotime("-1 month -1 day 00:00:00").','.strtotime("-1 day 23:59:59"));
        }
        if(!empty($where_sku['sku_code'])){
             $list = AffiliateReportModel::ReportStatistics_sku($where,$data,$where_sku);
        }else{
             $list = AffiliateReportModel::ReportStatistics($where,$data);
        }
        $order_from_data = [10=>"PC",20=>"Andrid",30=>"IOS",40=>"Pad",50=>"Mobile",];//10-PC，20-Android，30-iOS，40-Pad，50-Mobile
        if($export == false){
                $data_page['page'] = $data['page'];
                $data_page['countPage'] = $list['countPage'];
                $Page = CountPage($list['countPage'],$data_page,'/AffiliateReport/ReportStatistics');
                $this->assign(['list'=>$list['list'],'page'=>$Page,'data'=>$data,'list_sku'=>$list['list_sku'],'order_price'=>$list["order_price"],'order_from_data'=>$order_from_data]);
                return View();
        }else{
             // $time_log =  date("Ym",time());
             // file_put_contents ('../runtime/log/'.$time_log.'/affiliate_daochu_1.log',json_encode($list,true).'------------------------------------------', FILE_APPEND|LOCK_EX);
             return ['list'=>$list['list'],'list_sku'=>$list['list_sku'],'order_price'=>$list["order_price"],'invalid_order'=>$list['invalid_order']];
             // return !empty($list['list'])?$list['list']:'';
        }

    }
    /**
     * 导出报表统计 CSV格式
     * [Export_ReportStatistics description]
     */
    public function Export_ReportStatistics(){
        //获取后台配置的订单状态
        $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
        $order_status_data = array();
        if(!empty($orderStautsDict)){
            foreach ($orderStautsDict as $key=>$value){
                $order_status_data[$value[0]] = $value[1];
            }
        }else{
            return "Get OrderStatusView Error";
        }
        $data = input();
        $Export = array();
        foreach ($data as $k => $v) {
            if(empty($v)){
               unset($data[$k]);
            }
        }
        if(empty($data)){
            $data['startTime'] =  date('Y-m-d H:i:s',strtotime("-1 month"));
            $data['endTime'] = date('Y-m-d H:i:s',time());
        }else{
            if(!empty($data['startTime']) && !empty($data['endTime'])){
                if(empty($data['order_number']) && empty($data['sku_id'])){
                    /*if(strtotime('-1 month   00:00:00', strtotime($data['endTime'])) > strtotime($data['startTime'])){
                        return '导出时间间隔为一个月';
                    }*/
                }
            }else if(empty($data['startTime']) && empty($data['endTime'])){

            }else if(!empty($data['startTime']) && empty($data['endTime'])){
               $data['endTime'] = date('Y-m-d H:i:s', strtotime('+1 month', strtotime($data['startTime'])));
            }else if(empty($data['startTime']) && !empty($data['endTime'])){
               $data['startTime'] =  date('Y-m-d H:i:s', strtotime('-1 month', strtotime($data['endTime'])));
            }
        }
        $data['page'] = 1;
        $data['page_size']  = 4000;//00
        $data['time_delay']  = strtotime("-60 day");
        $fileName = 'Affiliate订单统计';
        $first_class = FirstLevelClass("id,title_en,type,pdc_ids",false);
        $first_class_data = array();
        if(!empty($first_class)){
            foreach ($first_class as $key=>$value){
                $first_class_data[$value['id']] = $value['title_en'];
            }
            foreach ($first_class as $key=>$value){
                if($value['type'] == 2){
                    $first_class_data[$key] = $first_class_data[$value['pdc_ids'][0]];
                }
            }
        }
        //$result_array = BaseApi::ReportStatistics($data);//因为要获取备注原因所有干脆全部直接到api获取
        //dump($result_array);exit;
        // 几个header函数需要放fopen之前。不然会直接在浏览器页面打印输出。而不是文件下载
        header("Content-type:application/vnd.ms-excel;charset=UTF-8");
        header('Content-Disposition: attachment;filename="'.$fileName.'.csv"');
        header('Cache-Control: max-age=0');
        //直接输出到浏览器
        $fp = fopen('php://output', 'a');
        //在写入的第一个字符串开头加 bom。
        // $bom =  chr(0xEF).chr(0xBB).chr(0xBF);\
        $type = !empty($data['type'])?$data['type']:1;
        if($type == 1){
            $header_data = [
                'Affiliate ID',
                mb_convert_encoding('订单号','gb2312','utf-8'),
                'SKU',
                mb_convert_encoding('订单创建时间','gb2312','utf-8'),
                mb_convert_encoding('订单实收总金额($)','gb2312','utf-8'),
                mb_convert_encoding('订单佣金总金额($)','gb2312','utf-8'),
                mb_convert_encoding('国家/地区','gb2312','utf-8'),
                mb_convert_encoding('是否有效','gb2312','utf-8'),
                mb_convert_encoding('订单状态','gb2312','utf-8'),
                mb_convert_encoding('取消备注','gb2312','utf-8'),
            ];
        }else{
            $header_data = [
                'Affiliate ID',
                mb_convert_encoding('订单号','gb2312','utf-8'),
                'SKU',
                mb_convert_encoding('SKU实收金额($)','gb2312','utf-8'),
                mb_convert_encoding('SKU数量','gb2312','utf-8'),
                mb_convert_encoding('SKU一级分类','gb2312','utf-8'),
                mb_convert_encoding('订单创建时间','gb2312','utf-8'),
                mb_convert_encoding('订单实收总金额($)','gb2312','utf-8'),
                mb_convert_encoding('订单佣金总金额($)','gb2312','utf-8'),
                mb_convert_encoding('国家/地区','gb2312','utf-8'),
                mb_convert_encoding('是否有效','gb2312','utf-8'),
                mb_convert_encoding('订单状态','gb2312','utf-8'),
                mb_convert_encoding('取消备注','gb2312','utf-8'),
            ];
        }

        fputcsv($fp, $header_data);
        $j = 2;
        $data['_id'] = $Affiliate_id = 0;
        while (true) {
            $result = '';
            if($data['_id'] == $Affiliate_id){ $data['_id'] = $Affiliate_id+1; }
            $data['_id'] = $Affiliate_id;
            $result_array = BaseApi::ReportStatistics($data);//因为要获取备注原因所有干脆全部直接到api获取
            $result = $result_array["data"]['list']?$result_array["data"]['list']:'';
            $order_price = $result_array["data"]["order_price"]?$result_array["data"]["order_price"]:'';
            $list_sku = $result_array["data"]['list_sku']?$result_array["data"]['list_sku']:'';
            $invalid_order = $result_array["data"]["invalid_order"]?$result_array["data"]["invalid_order"]:'';
            $applyRefund = $result_array["data"]["applyRefund"]?$result_array["data"]["applyRefund"]:'';
            $order_refund = $result_array["data"]["order_refund"]?$result_array["data"]["order_refund"]:'';
            $order_remarks = $result_array["data"]["order_remarks"]?$result_array["data"]["order_remarks"]:'';
            if($result == ''){ break; }
            if(!empty($result)){
               foreach ($result as $k => $v) {
                // file_put_contents ('../runtime/log/'.$time_log.'/affiliate_daochu_1.log',$v['affiliate_order_id'].'------------------------------------------', FILE_APPEND|LOCK_EX);
                    if($v['affiliate_order_id'] > $Affiliate_id){
                           $Affiliate_id = $v['affiliate_order_id'];
                    }
                    $settlement_status = '';
                    $order_price_val = '';
                    $order_status = '';
                    $sku = '';
                    $Export = [];
                    $change_reason = '';
                    if(!empty($invalid_order[$v['order_number']]["change_reason"])){
                         $change_reason .= mb_convert_encoding('订单日志表：'.$invalid_order[$v['order_number']]["change_reason"],'gb2312','utf-8');
                    }
                    if(!empty($applyRefund[$v['order_number']])){
                         $change_reason .= mb_convert_encoding('售后退款表：'.$applyRefund[$v['order_number']],'gb2312','utf-8');
                    }
                    if(!empty($order_refund[$v['order_number']])){
                         $change_reason .= mb_convert_encoding('后台退款表：'. $order_refund[$v['order_number']],'gb2312','utf-8');
                    }
                   if(!empty($order_remarks[$v['order_id']])){
                       $change_reason .= mb_convert_encoding('订单备注表：'. $order_remarks[$v['order_id']],'gb2312','utf-8');
                   }


                    if($v['settlement_status'] == 1){
                         $settlement_status = '未生效';
                    }else if($v['settlement_status'] == 2){
                         $settlement_status = '已生效';
                    }else if($v['settlement_status'] == 3){
                         $settlement_status = '待审核';
                    }else if($v['settlement_status'] == 4){
                        $settlement_status = '审核通过';
                    }else if($v['settlement_status'] == 5){
                        $settlement_status = '完成';
                    }else if($v['settlement_status'] == 6){
                        $settlement_status = '无效';
                    }
                    if(!empty($order_price[$v['affiliate_order_id']])){
                           $order_price_val = $order_price[$v['affiliate_order_id']];
                    }
                    if(!empty($v['order_status'])){
                        $order_status = isset($order_status_data[$v['order_status']])?$order_status_data[$v['order_status']]:'';
                    }
                    if(!empty($list_sku[$v['affiliate_order_id']])){
                         $sku = $list_sku[$v['affiliate_order_id']];
                    }
                   if($type == 1){
                       $Export = [
                           $v['affiliate_id'],
                           $v['order_number'].',',
                           $sku,
                           date('Y-m-d H:i:s', $v['create_on']),
                           !empty($v['price'])?$v['price']:$v['price'],
                           $order_price_val,
                           !empty($v['country'])?$v['country']:'',
                           mb_convert_encoding($settlement_status,'gb2312','utf-8'),
                           mb_convert_encoding( $order_status,'gb2312','utf-8'),
                           $change_reason
                       ];
                   }else{
                       $Export = [
                           $v['affiliate_id'],
                           $v['order_number'].',',
                           $v['sku_code'],
                           $v['total_amount'],
                           $v['sku_count'],
                           !empty($first_class_data[$v['first_category_id']])?$v['first_category_id']."-".$first_class_data[$v['first_category_id']]:$v['first_category_id'],
                           date('Y-m-d H:i:s', $v['create_on']),
                           !empty($v['price'])?$v['price']:$v['price'],
                           $order_price_val,
                           !empty($v['country'])?$v['country']:'',
                           mb_convert_encoding($settlement_status,'gb2312','utf-8'),
                           mb_convert_encoding( $order_status,'gb2312','utf-8'),
                           $change_reason
                       ];
                   }

                    fputcsv($fp, $Export);
               }
            }else{
                break;
            }
            // $data['page'] = $data['page'] + 1;
        }
        fclose($fp);
    }
    /**
     * 导出报表统计
     * [Export_ReportStatistics description]
     */
    public function Export_ReportStatistics_1(){
        $data = input();
        $Export = array();
        foreach ($data as $k => $v) {
            if(empty($v)){
               unset($data[$k]);
            }
        }
        if(empty($data)){
            $data['startTime'] =  date('Y-m-d H:i:s',strtotime("-1 month"));
            $data['endTime'] = date('Y-m-d H:i:s',time());
        }else{
            if(!empty($data['startTime']) && !empty($data['endTime'])){
                if(empty($data['order_number']) && empty($data['sku_id'])){
                    if(strtotime('-1 month   00:00:00', strtotime($data['endTime'])) > strtotime($data['startTime'])){
                        return '导出时间间隔为一个月';
                    }
                }
            }else if(empty($data['startTime']) && empty($data['endTime'])){

            }else if(!empty($data['startTime']) && empty($data['endTime'])){
               $data['endTime'] = date('Y-m-d H:i:s', strtotime('+1 month', strtotime($data['startTime'])));
            }else if(empty($data['startTime']) && !empty($data['endTime'])){
               $data['startTime'] =  date('Y-m-d H:i:s', strtotime('-1 month', strtotime($data['endTime'])));
            }
        }
        $data['page'] = 1;
        $data['page_size']  = 500;
        while (true) {
            $result = '';
            $result_array = $this->ReportStatistics($data,true);//dump($result_array);exit;
            $result = $result_array['list']?$result_array['list']:'';
            $order_price = $result_array["order_price"]?$result_array["order_price"]:'';
            $list_sku = $result_array['list_sku']?$result_array['list_sku']:'';
            $invalid_order = $result_array['invalid_order'];
            $time_log =  date("Ym",time());
            // file_put_contents ('../runtime/log/'.$time_log.'/affiliate_daochu_2.log',json_encode($result,true).'------------------------------------------', FILE_APPEND|LOCK_EX);
            if(!empty($result)){
                if(!empty($result) ){
                     $data['countPage'] = $result['countPage'];
                }
               foreach ($result as $k => $v) {
                    $settlement_status = '';
                    $order_price_val = '';
                    $order_status = '';
                    $sku = '';
                    $change_reason ='';
                    if($v['order_status'] == 1400){
                        $change_reason = !empty($invalid_order[$v['order_number']]["change_reason"])?$invalid_order[$v['order_number']]["change_reason"]:'';
                    }
                    if($v['settlement_status'] == 1){
                         $settlement_status = '未生效';
                    }else if($v['settlement_status'] == 2){
                         $settlement_status = '已结算';
                    }else if($v['settlement_status'] == 3){
                         $settlement_status = '已提现';
                    }
                    if(!empty($order_price[$v['affiliate_order_id']])){
                           $order_price_val = $order_price[$v['affiliate_order_id']];
                    }
                    if(!empty($v['order_status'])){
                          if($v['order_status']<200){
                              $order_status = '无效订单';
                          }else if($v['order_status'] == 1400){
                              $order_status = '无效订单';
                          }else{
                              $order_status = '有效订单';
                          }
                    }
                    if(!empty($list_sku[$v['affiliate_order_id']])){
                         $sku = $list_sku[$v['affiliate_order_id']];
                    }

                    $Export[] = ['affiliate_id'=>$v['affiliate_id'],'order_number'=>$v['order_number'].';','sku_id'=>$sku?$sku:'','add_time'=>date('Y-m-d H:i:s', $v['add_time']),'price'=>$v['price'],'order_price'=>$order_price_val?$order_price_val:'',
                        'country'=>$v['country'],'order_status'=>$order_status?$order_status:'','settlement_status'=>$settlement_status,'change_reason'=>$change_reason
                    ];
               }
            }else{
                break;
            }
            $data['page'] = $data['page'] + 1;
        }
        $header_data =['affiliate_id'=>'Affiliate ID','order_number'=>'订单号','sku_id'=>'SKU',
            'add_time'=>'订单创建时间','price'=>'实付金额（$）','order_price'=>'佣金金额（$）',
            'country'=>'国家/地区','order_status'=>'是否有效','settlement_status'=>'订单状态',
            'change_reason'=>'取消备注',
        ];
        $tool = new ExcelTool();
        if(!empty($Export)){
            $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }
    /**
     * Affiliate订单用户详情
     * [OrderUserDetails description]
     */
    public function OrderUserDetails(){
         $data = input();
         if(!empty($data["order_number"])){
              $order_number = '';
              $order_number = QueryFiltering($data['order_number']);
              if(empty($data['page'])){
                  $data['page'] = 1;
              }
              if(empty($data['page_size'])){
                $data['page_size'] = config('paginate.list_rows');
              }
              $where['AO.order_number'] = array('in',$order_number);
              $list = AffiliateReportModel::OrderUserDetails($where,$data);
         }
         if(empty($list['list'])){
             $list['list'] = array();
         }
         $this->assign(['data'=>$data,'list'=>$list['list']]);
         return View();
    }
     /**
     * Affiliate用户统计
     * [AffiliateUserStatistics description]
     */
    public function AffiliateUserStatistics($data=array(),$export = false){
        $where = array();
        $AffiliateOrder = array();
        $Affiliate_cic = array();
        $affiliate_list = array();
        $RCode = '';
        $countPage = '';
        if(empty($data)){
           $data = input();
        }
        if(!empty($data['Affiliate_id'])){
            $where['affiliate_id'] = $Affiliate_cic['RCode'] = $data['Affiliate_id'];
        }
        if(!empty($data['CustomerID'])){
           $where['cic_id'] =  $Affiliate_cic['CustomerID'] = $data['CustomerID'];
        }
        if(!empty($data['PayPalEU'])){
            $Affiliate_cic['PayPalEU'] = $data['PayPalEU'];
        }
        if(!empty($data['countPage'])){
            $countPage = $data['countPage'];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['create_on'] = array(array('egt',strtotime($data['startTime'])),array('elt',strtotime($data['endTime'])));
        }
        $aes =  aes();

        //先查佣金表
        if(empty($data["page"])){
           $page = $Affiliate_cic["page"] = $data["page"] = 1;
        }else{
           $page = $Affiliate_cic["page"] = $data["page"];
        }
        $page_size = $Affiliate_cic["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
        $list = BaseApi::AffiliateUserStatistics($Affiliate_cic);
        $affiliate_id = '';
        $CustomerID = '';
        if($list["code"] == 200 && !empty($list["data"]['list'])){
               foreach ($list["data"]['list'] as $key => $value) {
                    if(!empty($value["PayPalEU"])){
                        $PayPalEU = '';
                        $PayPalEU = $aes->decrypt($value["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                        $list['data']['list'][$key]["PayPalEU"] = $PayPalEU.'@'.$value["PayPalED"];
                        if(!empty($value["RCode"])){
                            $affiliate_id .= $value["RCode"].',';
                        }
                    }
                    $affiliate_list[$value["RCode"]] = $list['data']['list'][$key];
               }
               if(!empty($affiliate_id)){
                    $affiliate_id = rtrim($affiliate_id, ',');
                    $where['affiliate_id'] = array('in',$affiliate_id);
                    $AffiliateOrder_list = AffiliateReportModel::AffiliateUserStatistics($where,$page,$page_size);
                    if(!empty($AffiliateOrder_list['list'])){
                        foreach ($AffiliateOrder_list['list'] as $k => $v) {
                               $AffiliateOrder[$v['affiliate_id']] = $v;
                        }
                    }
                    // $AffiliateOrder = $AffiliateOrder_list['list'];
               }
        }else{
           $list["data"]['list'] = array();
           $list["data"]['countPage'] = 0;
        }

        if($export == false){
                $data_page['page'] = $data['page'];
                $data_page['page_size'] = $Affiliate_cic["page_size"];
                $data_page['countPage'] = $list["data"]['countPage'];
                $Page = CountPage($list["data"]['countPage'],$data_page,'/AffiliateReport/AffiliateUserStatistics');
                $this->assign(['list'=>$affiliate_list,'AffiliateOrder'=>$AffiliateOrder,'page'=>$Page,'data'=>$data,'affiliate_order_item'=>$AffiliateOrder_list['affiliate_order_item'],'order_invalid'=>$AffiliateOrder_list['order_invalid']]);
                return View();
        }else{
             return array('list'=>$affiliate_list,'order'=>$AffiliateOrder);
        }
    }
    /**
     * Affiliate用户统计
     * [AffiliateUserStatistics description]
     */
    public function AffiliateUserStatistics_1($data=array(),$export = false){
        $where = array();
        $AffiliateOrder = array();
        $Affiliate_cic = array();
        $affiliate_list = array();
        $RCode = '';
        $countPage = '';
        if(empty($data)){
           $data = input();
        }
        if(!empty($data['Affiliate_id'])){
            $where['affiliate_id'] = $Affiliate_cic['RCode'] = $data['Affiliate_id'];
        }
        if(!empty($data['CustomerID'])){
           $where['cic_id'] =  $Affiliate_cic['CustomerID'] = $data['CustomerID'];
        }
        if(!empty($data['PayPalEU'])){
            $Affiliate_cic['PayPalEU'] = $data['PayPalEU'];
        }
        if(!empty($data['countPage'])){
            $countPage = $data['countPage'];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['create_on'] = array(array('egt',strtotime($data['startTime'])),array('elt',strtotime($data['endTime'])));
        }
        $aes =  aes();
        if(empty($Affiliate_cic['PayPalEU'])){
                //先查佣金表
                if(empty($data["page"])){
                  $page = $Affiliate_cic["page"] = $data["page"] = 1;
                }else{
                  $page = $Affiliate_cic["page"] = $data["page"];
                }
                $page_size = $Affiliate_cic["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
                //先查订单表
                $AffiliateOrder_list = AffiliateReportModel::AffiliateUserStatistics($where,$page,$page_size,$countPage);
                $AffiliateOrder = $AffiliateOrder_list['list'];
                foreach ($AffiliateOrder["order"] as $k => $v) {
                    $RCode .= trim($v['affiliate_id']).',';
                }
                if(!empty($where['affiliate_id'])){
                      $RCode .= $where['affiliate_id'];
                }
                $Affiliate_cic['RCode'] = rtrim($RCode,',');
                $list = BaseApi::AffiliateUserStatistics($Affiliate_cic);
                if($list["code"] == 200 && !empty($list["data"]['list'])){
                       foreach ($list["data"]['list'] as $key => $value) {
                            if(!empty($value["PayPalEU"])){
                                $PayPalEU = '';
                                $PayPalEU = $aes->decrypt($value["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                                $list['data']['list'][$key]["PayPalEU"] = $PayPalEU.'@'.$value["PayPalED"];
                            }
                            $affiliate_list[$value["RCode"]] = $list['data']['list'][$key];
                       }
                }
        }else{
                // //先查佣金表
                if(empty($data["page"])){
                   $page = $Affiliate_cic["page"] = $data["page"] = 1;
                }else{
                   $page = $Affiliate_cic["page"] = $data["page"];
                }
                $page_size = $Affiliate_cic["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
                $list = BaseApi::AffiliateUserStatistics($Affiliate_cic);
                $affiliate_id = '';
                $CustomerID = '';
                if($list["code"] == 200 && !empty($list["data"]['list'])){
                       foreach ($list["data"]['list'] as $key => $value) {
                            if(!empty($value["PayPalEU"])){
                                $PayPalEU = '';
                                $PayPalEU = $aes->decrypt($value["PayPalEU"],'AffiliateLevel','PayPalEU');//解密邮件前缀
                                $list['data']['list'][$key]["PayPalEU"] = $PayPalEU.'@'.$value["PayPalED"];
                                if(!empty($value["RCode"])){
                                    $affiliate_id .= $value["RCode"].',';
                                }
                            }
                            $affiliate_list[$value["RCode"]] = $list['data']['list'][$key];
                       }
                       if(!empty($affiliate_id)){
                            $affiliate_id = rtrim($affiliate_id, ',');
                            $where['affiliate_id'] = array('in',$affiliate_id);
                            $AffiliateOrder_list = AffiliateReportModel::AffiliateUserStatistics($where,$page,$page_size);
                            $AffiliateOrder = $AffiliateOrder_list['list'];
                       }
                }else{
                   $list["data"]['list'] = array();
                   $list["data"]['countPage'] = 0;
                }
        }
        if($export == false){
                $data_page['page'] = $data['page'];
                $data_page['page_size'] = $Affiliate_cic["page_size"];
                $data_page['countPage'] = $AffiliateOrder['countPage'];
                $Page = CountPage($AffiliateOrder['countPage'],$data_page,'/AffiliateReport/AffiliateUserStatistics');
                $this->assign(['list'=>$affiliate_list,'AffiliateOrder'=>$AffiliateOrder,'page'=>$Page,'data'=>$data,'affiliate_order_item'=>$AffiliateOrder_list['affiliate_order_item'],'order_invalid'=>$AffiliateOrder_list['order_invalid']]);
                return View();
        }else{
             return array('list'=>$affiliate_list,'order'=>$AffiliateOrder['order']);
        }
    }

    /**
     * Affiliate用户统计导出
     * [Export_AffiliateUserStatistics description]
     */
    public function Export_AffiliateUserStatistics(){
          $data = input();
          $data_array = array();
          foreach ($data as $key => $value) {
             if(empty($value)){
                unset($data[$key]);
             }
          }
          $data["page"]=1;
          $data["page_size"] = 200;
          while (true) {
               $list = $this->AffiliateUserStatistics($data,true);
               if(empty($list['order'])){
                  break;
               }else{
                 foreach ($list['list'] as $k => $v) {
                     if(!empty($list["order"][$v["RCode"]]["affiliate_sum"])){
                        $QuantityOrder = $list["order"][$v["RCode"]]["affiliate_sum"];
                     }else{
                        $QuantityOrder = '';
                     }
                     if(!empty($list["order"][$v["RCode"]]["price"])){
                        $price = $list["order"][$v["RCode"]]["price"];
                     }else{
                        $price = '';
                     }
                     if(!empty($list["order"][$v["RCode"]]["create_on"])){
                        $create_on = date('Y-m-d H:i:s',$list["order"][$v["RCode"]]["create_on"]);
                     }else{
                        $create_on = '';
                     }
                     if(!empty($v["PayPalEU"])){
                        $PayPalEU = $v["PayPalEU"];
                     }else{
                        $PayPalEU = '';
                     }
                     if(!empty($v["RegistrationTimestamp"])){
                        $RegistrationTimestamp = date('Y-m-d H:i:s',$v["RegistrationTimestamp"]);
                     }else{
                        $RegistrationTimestamp = '';
                     }
                     if(!empty($list["order"][$v["RCode"]]["total_valid_commission_price"])){
                        $total_valid_commission_price = $list["order"][$v["RCode"]]["total_valid_commission_price"];
                     }else{
                        $total_valid_commission_price = '';
                     }
                     if(!empty($list["order"][$v["RCode"]]["total_invalid_commission_price"])){
                        $total_valid_commission_price = $list["order"][$v["RCode"]]["total_invalid_commission_price"];
                     }else{
                        $total_valid_commission_price = '';
                     }

                     $Export[] = ['RCode'=>$v["RCode"]?$v["RCode"]:'','cic_id'=>$v['CustomerID']?$v['CustomerID']:'',
                        'PayPalEU'=>$v['PayPalEU'],'RegistrationTimestamp'=>$RegistrationTimestamp,
                        'total_valid_commission_price'=>$total_valid_commission_price,'total_invalid_commission_price'=>$total_valid_commission_price,
                        'QuantityOrder'=>$QuantityOrder,'price'=>$price,'create_on'=>$create_on
                     ];
                 }
               }
               $data["page"] = $data["page"] + 1;
          }
          $header_data =['RCode'=>'Affiliate ID','cic_id'=>'客户ID','PayPalEU'=>'客户Email',
            'RegistrationTimestamp'=>'注册日期','total_valid_commission_price'=>'可用佣金($)','total_invalid_commission_price'=>'未生效佣金($)',
            'QuantityOrder'=>'订单总数','price'=>'订单总金额($)','create_on'=>'最后购买时间'
          ];
         $tool = new ExcelTool();
         if(!empty($Export)){
            $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
         }else{
            echo '没查到数据';
            exit;
         }
    }
    /**
     * Affiliate订单交易
     * [OrderTransaction description]
     *
     */
    public function OrderTransaction($data=array(),$export = false){
        if(empty($data)){
             $data = ParameterCheck(input());
        }
        $list = array();
        $data_array = array();
        if(!empty($data["id"]) || !empty($data["tabPageId"])){
            unset($data["id"]);
            unset($data["tabPageId"]);
        }
        if(!empty($data["affiliate_id"])){
            $data["affiliate_id"] = QueryFiltering($data["affiliate_id"]);
            $model_data["affiliate_id"] = ['in',$data["affiliate_id"]] ;
        }
        // $model_data['order_status'] = array('egt',200);
        if(!empty($data["startTime"]) && !empty($data["endTime"])){
            $PeriodTime = $this->PeriodTime($data["startTime"],$data["endTime"]);
        }else{
               $data['startTime'] = $startTime = date('Y-m-d 00:00:00', strtotime("-1 month -1 day 00:00:00"));
               $data['endTime'] = $endTime = date('Y-m-d 23:59:59', strtotime("-1 day 23:59:59"));
               $PeriodTime = $this->PeriodTime($startTime,$endTime);
        }
         if(!empty($PeriodTime)){
              foreach (array_reverse($PeriodTime) as $k => $v) {
                    $model_data['create_on'] = array(array('egt',strtotime(date('Y-m-d 00:00:00', $v))),array('elt',strtotime(date('Y-m-d 23:59:59', $v))));
                    $OrderTransaction = AffiliateReportModel::OrderTransaction($model_data);
                    $DataCombination = $this->DataCombination($OrderTransaction);
                    $combination = $DataCombination['combination'];
                    $combination['date'] = date("Y-m-d", $v);
                    $combination['startTime'] = date('Y-m-d 00:00:00', $v);
                    $combination['endTime'] = date('Y-m-d 23:59:59', $v);
                    $combination['order_num'] = count($OrderTransaction);
                    if(!empty($DataCombination['RCode'])){
                        $RCode = array_unique($DataCombination['RCode']);//去重
                        if(!empty($RCode)){
                              $where['RCode'] = $RCode;
                              $affiliateIdSum = BaseApi::AffiliateIdSum($where);//获取当天新用户数量
                              if($affiliateIdSum['code'] == 200){
                                 $combination['sum'] = $affiliateIdSum["data"]["sum"];
                              }
                        }
                        $combination['total'] = count($RCode);
                    }
                    $data_array[] = $combination;
              }
        }

        if($export == false){
            $this->assign(['list'=>$data_array,'data'=>$data]);
            return View();
        }else{
            return ['list'=>$data_array];
        }
    }
    /**
     * Affiliate订单交易 导出
     * [Export_OrderTransaction description]
     */
    public function Export_OrderTransaction(){
        $data = ParameterCheck(input());
        $data_array = array();
        $list_data = $this->OrderTransaction($data,true);
        if(!empty($list_data['list'])){
             foreach ($list_data['list'] as $k => $v) {
                     $Export[] = ['date'=>$v['date'],'order_num'=>$v["order_num"],'price'=>$v['price'],
                        'captured_amount_usd'=>isset($v['captured_amount_usd'])?$v['captured_amount_usd']:'',
                        'order_cancel'=>isset($v['order_cancel'])?$v['order_cancel']:'',
                        'captured_amount_usd_cancel'=>isset($v['captured_amount_usd_cancel'])?$v['captured_amount_usd_cancel']:'',
                        'sum'=>isset($v['sum'])?$v['sum']:0,'total'=>isset($v['total'])?$v['total']:''];
             }
             $header_data =['date'=>'日期','order_num'=>'订单总数','price'=>'订单原始总金额（$）','captured_amount_usd'=>'订单当前总金额（$）',
             'order_cancel'=>'取消订单总数','captured_amount_usd_cancel'=>'取消订单总金额（$）','sum'=>'新增Affiliate用户总数',
             'total'=>'当前Affiliate用户总数'];
             $tool = new ExcelTool();
             if(!empty($Export)){
                $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
             }else{
                echo '没查到数据';
                exit;
             }
        }else{
            echo '没查到数据';
            exit;
        }
    }
    /**
     * 一天的所有订单
     * [DailyOrderList description]
     */
    // public function ListOfDetails(){
    //     $model_data = array();
    //     $data = ParameterCheck(input());
    //     if(empty($data['page'])){
    //         $data['page'] = 1;
    //     }
    //     $data["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
    //     if(!empty($data['affiliate_id'])){
    //           $affiliate_id = QueryFiltering($data['affiliate_id']);
    //           $data_page['affiliate_id'] = $data['affiliate_id']  = $affiliate_id;
    //           $model_data['affiliate_id'] = array('in',$affiliate_id);
    //     }
    //     if(!empty($data['order_number'])){
    //           $affiliate_id = QueryFiltering($data['order_number']);
    //           $data_page['order_number'] = $data['order_number']  = $affiliate_id;
    //           $model_data['order_number'] = array('in',$affiliate_id);
    //     }
    //     if(!empty($data['settlement_status'])){
    //           $data_page['settlement_status'] = $data['settlement_status'];
    //           $model_data['settlement_status'] = $data['settlement_status'];
    //     }
    //     if(!empty($data['sku_id'])){
    //           $affiliate_id = QueryFiltering($data['sku_id']);
    //           $data_page['sku_id'] = $data['sku_id']  = $affiliate_id;
    //           $model_sku['sku_id'] = array('in',$affiliate_id);
    //     }
    //     if(!empty($data_page['countPage'])){
    //           $countPage = $data_page['countPage'];
    //     }else{
    //           $countPage = '';
    //     }

    //     if(!empty($data["startTime"]) && !empty($data["endTime"])){
    //         $model_data['add_time'] = array(array('egt',strtotime($data["startTime"])),array('elt',strtotime($data["endTime"])));
    //         if(empty($model_sku)){
    //             $OrderList = AffiliateReportModel::ListOfDetails($model_data,$data['page'],$data['page_size'],$countPage);
    //         }else{
    //             $model_sku['add_time'] = $model_data['add_time'];
    //             $OrderList = AffiliateReportModel::ListOfDetailsSku($model_data,$model_sku,$data['page'],$data['page_size'],$countPage);
    //         }
    //     }
    //     $data_page = $data;
    //     $data_page['countPage'] = isset($OrderList['countPage'])?$OrderList['countPage']:0;
    //     $Page = CountPage($data_page['countPage'],$data_page,'/AffiliateReport/ListOfDetails');
    //     $this->assign(['list'=>$OrderList['list'],'data'=>$data,'list_sku'=>$OrderList['list_sku'],'page'=>$Page]);
    //     return View();
    // }
    /**
     * 数据整合
     * [DataCombination description]
     * @param [type] $OrderTransaction [description]
     */
    public function DataCombination($OrderTransaction){
           $combination = array();
           $RCode = array();//用户存Affiliate_id
           foreach ($OrderTransaction as $k => $v) {
                //Affiliate_id
                if(!empty($v['affiliate_id'])){
                    $RCode[] = $v['affiliate_id'];
                }
                //原始价格之和
                if(!empty($v["price"])){
                    if(empty($combination["price"])){
                         $combination["price"] = $v["price"];
                    }else{
                         $combination["price"] = $combination["price"] + $v["price"];
                    }
                }
                //当前价格之和
                if(!empty($v["price"]) && $v["order_status"] != 1400){
                    if(empty($combination["captured_amount_usd"])){
                         $combination["captured_amount_usd"] = $v["price"];
                    }else{
                         $combination["captured_amount_usd"] = $combination["captured_amount_usd"] + $v["price"];
                    }
                }
                //取消订单之和
                if(!empty($v["order_status"]) && $v["order_status"] == 1400){
                    if(empty($combination["order_cancel"])){
                         $combination["order_cancel"] = 1;
                    }else{
                         $combination["order_cancel"] += 1;
                    }
                    if(empty($combination["captured_amount_usd_cancel"])){
                         $combination["captured_amount_usd_cancel"] = $v["price"];
                    }else{
                         $combination["captured_amount_usd_cancel"] += $v["price"];
                    }
                }
            }
            return array('combination'=>$combination,'RCode'=>$RCode);
    }
     /**
     *获取指定一段时间内的每天的开始时间
     * @param $startdate 开始日期
     * @param $enddate 结束日期
     * @param $format 时间格式 0：时间戳 1日期格式
     * @return array 返回一维数组
     */
    public function PeriodTime($startdate, $enddate,$format=0){
        $stimestamp = strtotime($startdate);
        $etimestamp = strtotime($enddate);    // 计算日期段内有多少天
        // $days = ($etimestamp-$stimestamp)/86400+1;    // 保存每天日期
        $days = ($etimestamp-$stimestamp)/86400;    // 保存每天日期
        $date = array();
        for($i=0; $i<$days; $i++){
            if ($format==0) {
                $date[] = $stimestamp+(86400*$i);
            }else{
                $date[] = date('Y-m-d', $stimestamp+(86400*$i));
            }
        }
        //结果
        return $date;
    }
    /**
     * SKU销售情况统计
     * [SalesStatistics description]
     */
    public function SalesStatistics($data=array(),$export = false){
        if(empty($data)){
           $data = ParameterCheck(input());
        }
        unset($data["tabPageId"],$data["id"]);
        $model_data = array();
        $model_affiliate = array();
        if(empty($data['page'])){  $data['page'] = 1; }
        if(!empty($data_page['countPage'])){
              $countPage = $data_page['countPage'];
        }else{
              $countPage = '';
        }
        if(!empty($data['affiliate_id'])){
          $affiliate_id = QueryFiltering($data['affiliate_id']);
          $data['affiliate_id']  = $affiliate_id;
          $model_affiliate['affiliate_id'] = array('in',$affiliate_id);
        }
        $data["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
        //为空时默认为一个月
        if(empty($data['startTime']) && empty($data['endTime'])){
          $model_data['create_on'] = array(array('egt',strtotime('-1 month  -1 day 00:00:00')),array('elt',strtotime('-1 day 23:59:59')));
          // $model_data['add_time'] = array(array('egt',strtotime('-1 month')),array('elt',time()));
          $data['startTime'] = date('Y-m-d H:i:s',strtotime('-1 month  -1 day 00:00:00'));
          $data['endTime'] = date('Y-m-d H:i:s',strtotime('-1 day 23:59:59'));
        }else{
           $model_data['create_on'] = array(array('egt',strtotime($data['startTime'])),array('elt',strtotime($data['endTime'])));
        }
        if(!empty($model_affiliate['affiliate_id'])){
           $model_affiliate['create_on'] = $model_data['add_time'];
           $affiliate_id = AffiliateReportModel::SalesStatistics_affiliate_id($model_affiliate);
           if(!empty($affiliate_id)){
                $model_data['affiliate_order_id'] = ['in',$affiliate_id];
           }
        }
        $OrderList = AffiliateReportModel::SalesStatistics($model_data,$data['page'],$data["page_size"],$countPage);
        if($export == false){
            $data_page = $data;
            $data_page['countPage'] = isset($OrderList['countPage'])?$OrderList['countPage']:0;
            $Page = CountPage($data_page['countPage'],$data_page,'/AffiliateReport/SalesStatistics');
            $this->assign(['list'=>$OrderList['list'],'data'=>$data,'page'=>$Page]);
            return View();
        }else{
            $data_page['countPage'] = isset($OrderList['countPage'])?$OrderList['countPage']:0;
            return array('list'=>$OrderList['list'],'countPage'=>$data_page['countPage']);
        }

    }
    /**
     * SKU销售情况统计   导出
     * [SalesStatistics description]
     */
    public function Export_SalesStatistics(){
        $data = ParameterCheck(input());
        $data_array = array();
        $data['page'] = 1;
        $data["page_size"] = 100;
        while (true) {
             $list_data = $this->SalesStatistics($data,true);
             $data['countPage'] = $list_data['countPage'];
             $data['page'] += 1;
             if(empty($list_data['list'])){
                break;
             }
             foreach ($list_data['list'] as $k => $v) {
                $Export[] = ['sku_id'=>$v['sku_id'],'sku_count'=>$v["sku_count"]];
             }
        }
        $header_data =['sku_id'=>'SKU','sku_count'=>'总计'];
        $tool = new ExcelTool();
        if(!empty($Export)){
            $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }
    /**
     * 黑名单
     * [blacklist description]
     * @return [type] [description]
     */
    public function blacklist(){
        $data = ParameterCheck(input());
        unset($data["tabPageId"],$data["id"]);
        $model_data = array();
        $blacklist = array();
        if(empty($data['page'])){  $data['page'] = 1; }
        $data["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
        if(!empty($data_page['countPage'])){
              $countPage = $data_page['countPage'];
        }else{
              $countPage = '';
        }
        if(!empty($data['affiliate_id'])){
              $affiliate_id = QueryFiltering($data['affiliate_id']);
              $data['affiliate_id']  = $affiliate_id;
              $model_data['affiliate_id'] = array('in',$affiliate_id);
        }
        $model_data['status'] = 1;
        $blacklist = AffiliateReportModel::blacklist($model_data,$data['page'],$data["page_size"],$countPage);
        $data_page = $data;
        $data_page['countPage'] = isset($blacklist['countPage'])?$blacklist['countPage']:$countPage;
        $Page = CountPage($blacklist['countPage'],$data_page,'/AffiliateReport/blacklist');
        $this->assign(['list'=>$blacklist['list'],'data'=>$data,'page'=>$Page]);
        return View();
    }
    /**
     * 添加黑名单
     * [add_black description]
     */
    public function add_black(){
        if($data = request()->post()){
            $model_data = array();
            $model_insert = array();
            $black_cic_id = array();
            $affiliate_failure = '';

            if(!empty($data['affiliate_id'])){
              $affiliate_id = QueryFiltering($data['affiliate_id']);
              $affiliate_cic_id = BaseApi::add_black(['affiliate_id'=>$affiliate_id]);//获取cic  id
              if($affiliate_cic_id['code']==200 && !empty($affiliate_cic_id['data'])){
                  $black_cic_id = $affiliate_cic_id['data'];
              }else{
                  echo json_encode(array('code'=>100,'result'=>$affiliate_cic_id['data']));
                  exit;
              }
              $affiliate_id = explode(",", $affiliate_id);
            }else{
                echo json_encode(array('code'=>100,'result'=>'affiliate_id不能为空'));
                exit;
            }

            if(empty($affiliate_id)){
                 echo json_encode(array('code'=>100,'result'=>'affiliate_id不能为空或者数据有误'));
                 exit;
            }
            //对所有数据进行验证和组装
            foreach ($affiliate_id as $k => $v) {
                    $AnExamination = AffiliateReportModel::AnExamination($v);
                    if(!empty($AnExamination)){
                       echo json_encode(array('code'=>100,'result'=>$v.'已添加过'));
                       exit;
                    }
                    if(empty($black_cic_id[$v])){
                       echo json_encode(array('code'=>100,'result'=>$v.'没有对应的用户ID'));
                       exit;
                    }
                    if(!empty($data['remarks'])){
                        $model_data['remarks'] = $data['remarks'];
                    }
                    $model_data['CustomerID'] = $black_cic_id[$v];
                    $model_data['affiliate_id'] = $v;
                    $model_data['add_author'] = Session::get('username');
                    $model_data['add_time'] = time();
                    $model_data['status'] = 1;
                    $model_insert[$v] = $model_data;
            }
            //独个把数据通过接口，更改cic  affiliate_id用户表
            foreach ($affiliate_id as $k_api => $v_api) {
               $joinBlacklist = BaseApi::joinBlacklist(['RCode'=>$v_api,'CustomerID'=>$black_cic_id[$v_api]]);//获取cic  id
               if($joinBlacklist['code'] != 200){
                 $affiliate_failure = $v_api.',';
                 unset($model_insert[$v_api]);
               }
            }

            $result = AffiliateReportModel::add_black($model_insert);
            if(!empty($result)){
                if(empty($affiliate_failure)){
                    echo json_encode(array('code'=>200,'result'=>'数据新增成功'));
                    exit;
                }else{
                    echo json_encode(array('code'=>100,'result'=>$affiliate_failure.'添加失败'));
                    exit;
                }
            }else{
                echo json_encode(array('code'=>100,'result'=>'数据新增失败'));
                exit;
            }
        }
    }
    /**
     * 移除黑名单
     * [delete_black description]
     * @return [type] [description]
     */
    public function delete_black(){
       if($data = request()->post()){
           if($data["affiliateid"] && $data["status"]){
            $model_data["affiliate_id"] = $data["affiliateid"];
            $model_data["status"] = $data["status"];
            $model_update["edit_time"] = time();
            $model_update["edit_author"] = Session::get('username');
            $model_update["status"] = 2;
            $AnExamination = AffiliateReportModel::AnExamination($data["affiliateid"]);
            if(empty($AnExamination)){
                echo json_encode(array('code'=>100,'result'=>'该数据出异常'));
                exit;
            }
            $removeBlacklist = BaseApi::removeBlacklist(['RCode'=>$data["affiliateid"],'CustomerID'=>$AnExamination['CustomerID']]);//获取cic  id
            if($removeBlacklist["code"] !=200){
                  echo json_encode(array('code'=>100,'result'=>'cic移除黑名单失败'));
                  exit;
            }

            $result = AffiliateReportModel::delete_black($model_data,$model_update);
            if(!empty($result)){
                  echo json_encode(array('code'=>200,'result'=>'移除黑名单成功'));
                  exit;
            }else{
                  echo json_encode(array('code'=>100,'result'=>'移除失败'));
                  exit;
            }
           }
       }
    }
     /**
     * 分类销售情况
     * [ClassifiedSales description]
     */
    public function ClassifiedSales($data=array(),$export = false){
        if(empty($data)){
           $data = ParameterCheck(input());
        }

        unset($data["tabPageId"],$data["id"]);
        $parent_class = '';
        $model_order = array();
        $FirstLevelClass = FirstLevelClass('id,title_en');
        if(empty($data['page'])){  $data['page'] = 1; }
        if(!empty($data['countPage'])){
              $countPage = $data['countPage'];
        }else{
              $countPage = '';
        }
        //判断分类
        if(!empty($data["fifth_level"])){
           // $model_data['four_category_id'] = $data["fifth_level"];
           $parent_class = Business::parent_class($data['fifth_level']);
        }else if(!empty($data["fourth_level"])){
           $model_data['four_category_id']   = $data["fourth_level"];
           $parent_class = Business::parent_class($data['fourth_level']);
        }else if(!empty($data["third_level"])){
           $model_data['third_category_id']  = $data["third_level"];
           $parent_class = Business::parent_class($data['third_level']);
        }else if(!empty($data["second_level"])){
           $model_data['second_category_id'] = $data["second_level"];
           $parent_class = Business::parent_class($data['second_level']);
        }else if(!empty($data["first_level"])){
           $model_data['first_category_id']  = $data["first_level"];
           $parent_class = Business::parent_class($data['first_level']);
        }
        if(!empty($data['order_number'])){
              $affiliate_id = QueryFiltering($data['order_number']);
              $data['order_number']  = $affiliate_id;
              $model_order['order_number'] = array('in',$affiliate_id);
        }
        if(!empty($data['sku_id'])){
              $affiliate_id = QueryFiltering($data['sku_id']);
              $data['sku_id']  = $affiliate_id;
              $model_data['sku_id'] = array('in',$affiliate_id);
        }
        $data["page_size"] = !empty($data["page_size"])?$data["page_size"]:config('paginate.list_rows');
         //为空时默认为一个月
        if(empty($data['startTime']) && empty($data['endTime'])){
           $model_data['create_on'] = $model_data['add_time'] = array(array('egt',strtotime('-1 month')),array('elt',time()));
           $data['startTime'] = date('Y-m-d H:i:s',strtotime('-1 month -1 day 00:00:00'));
           $data['endTime'] = date('Y-m-d H:i:s',strtotime('-1 day 23:59:59'));
        }else{
           $model_data['create_on'] = $model_data['add_time'] = array(array('egt',strtotime($data['startTime'])),array('elt',strtotime($data['endTime'])));
        }

        if(!empty($model_order) && empty($model_data['sku_id'])){
             $result = AffiliateReportModel::ClassifiedSalesOrder($model_order,$data['page'],$data["page_size"],$countPage,$model_data);
        }else{
             $result = AffiliateReportModel::ClassifiedSales($model_data,$data['page'],$data["page_size"],$countPage,$model_order);
        }
        if($export == false){
            $data_page = $data;
            $data_page['countPage'] = isset($result['countPage'])?$result['countPage']:$countPage;
            $Page = CountPage($data_page['countPage'],$data_page,'/AffiliateReport/ClassifiedSales');
            $this->assign(['list'=>$result['list'],'data'=>$data,'page'=>$Page,'FirstLevelClass'=>$FirstLevelClass,'order_array'=>$result['order_array'],'class_list'=>$result['class_list'],'parent_class'=>$parent_class]);
            return View();
        }else{
            return ['list'=>$result['list'],'FirstLevelClass'=>$FirstLevelClass,'order_array'=>$result['order_array'],'class_list'=>$result['class_list']];
        }

    }
     /**
     * 分类销售情况导出
     * [ClassifiedSales description]
     */
    public function Export_ClassifiedSales(){
        $data = ParameterCheck(input());
        $data_array = array();
        $data['page'] = 1;
        $data["page_size"] = 100;
        while (true) {
            $list_data = $this->ClassifiedSales($data,true);
            $data['page'] += 1;
            if(empty($list_data['list'])){   break;  }
            foreach ($list_data['list'] as $k => $v) {
                 $order_number       = '';
                 $category_id = '';
                 $settlement_status_name  = '';
                 $source = '';
                 if(!empty($list_data['order_array'][$v['affiliate_order_id']]['order_number'])){
                      $order_number = $list_data['order_array'][$v['affiliate_order_id']]['order_number'];
                 }
                 if(!empty($list_data['class_list'][$v['first_category_id']])){
                     $category_id .= '<'.$list_data['class_list'][$v['first_category_id']]['title_en'];
                 }
                 if(!empty($list_data['class_list'][$v['second_category_id']])){
                     $category_id .= '<'.$list_data['class_list'][$v['second_category_id']]['title_en'];
                 }
                 if(!empty($list_data['class_list'][$v['third_category_id']])){
                     $category_id .= '<'.$list_data['class_list'][$v['third_category_id']]['title_en'];
                 }
                 if(!empty($list_data['class_list'][$v['four_category_id']])){
                     $category_id .= '<'.$list_data['class_list'][$v['four_category_id']]['title_en'];
                 }

                 if(!empty($list_data['order_array'][$v['affiliate_order_id']]['settlement_status'])){
                     $settlement_status = $list_data['order_array'][$v['affiliate_order_id']]['settlement_status'];
                     if($settlement_status == 1){
                          $settlement_status_name = '未生效';
                     }else if($settlement_status == 2){
                          $settlement_status_name = '有效';
                     }else if($settlement_status == 3){
                          $settlement_status_name = '待审核';
                     }else if($settlement_status == 4){
                          $settlement_status_name = '审核通过';
                     }else if($settlement_status == 5){
                          $settlement_status_name = '完成';
                     }else if($settlement_status == 6){
                          $settlement_status_name = '无效';
                     }
                 }
                 if(!empty($list_data['order_array'][$v['affiliate_order_id']]['source'])){
                       $source = $list_data['order_array'][$v['affiliate_order_id']]['source'];
                 }
                 $Export[] = ['order_number'=>!empty($order_number)?$order_number:'','class_name'=>$category_id,
                 'sku_id'=>$v['sku_id'],'add_time'=>date('Y-m-d H:i:s',$v['add_time']),'settlement_status'=>$settlement_status_name,
                 'commission_price'=>$v['commission_price'],'price'=>$v['price'],'source'=>$source ];
            }
        }
        $header_data =['order_number'=>'订单号','class_name'=>'订单明细类别','sku_id'=>'SKU',
                       'add_time'=>'订单创建日期','settlement_status'=>'积分状态',
                       'commission_price'=>'佣金','price'=>'订单当前金额($)','source'=>'详情'
                      ];
        $tool = new ExcelTool();
        if(!empty($Export)){
            $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

}