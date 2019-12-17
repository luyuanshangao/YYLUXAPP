<?php
namespace app\admin\services;

use app\admin\model\WindControlModel;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use app\admin\dxcommon\ExcelTool;
use app\admin\model\Payment;


/**
 * 风控接口
 */
class WindControlService extends BaseService
{
    public function getRiskData($channel,$startTime,$endTime){
        $where = [];

        if( !empty($channel) )
            $where['A.PaymentChannel'] = ['=',$channel];
        if( !empty($startTime) )
            $where['A.AddTime'] = ['>=',strtotime($startTime)];
        if( !empty($endTime) )
            $where['A.AddTime'] = ['<=',strtotime($endTime)];
        if( !empty($startTime) && !empty($endTime) ){
            $where['A.AddTime'] = ['between',strtotime($startTime).','.strtotime($endTime)];
        }

        $where['A.DealWithStatus'] = ['>',0];

        $data = (new WindControlModel())->getRiskData($where);
        
        if(empty($data)){
            return [];
        }
        
        $info = [];
        $baseInfo = ['name'=>'','capture_num'=>0,'capture_amount'=>0,'void_num'=>0,'void_amount'=>0,'unkonwn_num'=>0,'unkonwn_amount'=>0,'time'=>0];
        $riskInfo = ['cybc_all_num'=>0,'cybc_all_amount'=>0,'cybc_pass_num'=>0,'cybc_pass_amount'=>0,'cybc_risky_num'=>0,'cybc_risky_amount'=>0,'cybc_reject_num'=>0,'cybc_reject_amount'=>0];

        foreach ($data as $value) {
            if( !isset($info[$value['DistributionAdminId']]) ){
                $info[$value['DistributionAdminId']] = $baseInfo;
                $info[$value['DistributionAdminId']]['name'] = $value['DistributionAdmin'];
            }
            //计算进入cybc以色列风控数量
            if( isset($value['Recommendation']) && is_numeric($value['Recommendation']) ){
                $riskInfo['cybc_all_num']++;
                $riskInfo['cybc_all_amount']+=$value['AmountUsd'];
            }
            //以色列风控判定通过数量及金额
            if( isset($value['Recommendation']) && is_numeric($value['Recommendation']) && $value['Recommendation']==0 ){
                $riskInfo['cybc_pass_num']++;
                $riskInfo['cybc_pass_amount']+=$value['AmountUsd'];
            }

            if( $value['DealWithStatus']==2 ){
                $info[$value['DistributionAdminId']]['capture_num']++;
                $info[$value['DistributionAdminId']]['capture_amount']+=$value['AmountUsd'];
                //以色列判定异常后进入人工审核结果
                if( isset($value['Recommendation']) && $value['Recommendation']>0 ){
                    $riskInfo['cybc_risky_num']++;
                    $riskInfo['cybc_risky_amount']+=$value['AmountUsd'];
                }
            }else if( $value['DealWithStatus']==3 ){
                $info[$value['DistributionAdminId']]['void_num']++;
                $info[$value['DistributionAdminId']]['void_amount']+=$value['AmountUsd'];
                //以色列判定异常后进入人工审核结果
                if( isset($value['Recommendation']) && $value['Recommendation']>0 ){
                    $riskInfo['cybc_reject_num']++;
                    $riskInfo['cybc_reject_amount']+=$value['AmountUsd'];
                }
            }else{
                $info[$value['DistributionAdminId']]['unkonwn_num']++;
                $info[$value['DistributionAdminId']]['unkonwn_amount']+=$value['AmountUsd'];
            }

            $time = intval($value['OperatingTime'])-intval($value['AddTime']);
            $time = ($time<0)?0:$time;
            $time = $time/(intval($info[$value['DistributionAdminId']]['void_num'])+intval($info[$value['DistributionAdminId']]['capture_num'])+intval($info[$value['DistributionAdminId']]['unkonwn_num']));
            $time = number_format( $time/3600,2,'.','');
            $info[$value['DistributionAdminId']]['time']+=$time;
        }

        $info['risk'] = $riskInfo;
        return $info;
    }

    public function export($windData,$channel,$startTime,$endTime){
        $avgTime    = 0;//平均处理时间
        $void_num   = 0;//所有被拒订单数
        $void_amount= 0;//所有被拒总金额
        $unkonwn_num   = 0;//unknown订单数
        $unkonwn_amount= 0;//unknown总金额

        $riskInfo = $windData['risk'];

        //获取平均处理时间
        foreach ($windData as $value) {
            $avgTime    += $value['time'];
            $void_num   += $value['void_num'];
            $void_amount+= $value['void_amount'];
            $unkonwn_num   += $value['unkonwn_num'];
            $unkonwn_amount+= $value['unkonwn_amount'];
        }
        $avgTime = number_format( $avgTime/count($windData),2,'.','');

        //空两行
        $windData[]=[];
        $windData[]=[];

        $where = [];

        if( !empty($channel) )
            $where['PaymentChannel'] = ['=',$channel];
        if( !empty($startTime) )
            $where['AddTime'] = ['>=',strtotime($startTime)];
        if( !empty($endTime) )
            $where['AddTime'] = ['<=',strtotime($endTime)];
        if( !empty($startTime) && !empty($endTime) ){
            $where['AddTime'] = ['between',strtotime($startTime).','.strtotime($endTime)];
        }

        $data = (new WindControlModel())->getAllData($where);
        if( $channel=='paypal' ){
            $windData[]=['name'=>'paypal交易总数','capture_num'=>$data['allData']['num']];
            $windData[]=['name'=>'paypal交易总金额','capture_num'=>number_format($data['allData']['sum'],2,'.','')];
            $windData[]=['name'=>'交易平均金额','capture_num'=>number_format( $data['allData']['avg'],2,'.','')];
            $windData[]=[];//空一行
            $windData[]=['name'=>'进风控订单总数','capture_num'=>$data['riskData']['num']];
            $windData[]=['name'=>'进风控订单总金额','capture_num'=>number_format( $data['riskData']['sum'],2,'.','')];

            $windData[]=[];//空一行
            $windData[]=['name'=>'进CYBC交易总数','capture_num'=>$riskInfo['cybc_all_num']];
            $windData[]=['name'=>'进CYBC交易总金额','capture_num'=>$riskInfo['cybc_all_amount']];
            $windData[]=[];//空一行
            $windData[]=['name'=>'Pass交易成功总数','capture_num'=>$riskInfo['cybc_pass_num']];
            $windData[]=['name'=>'Pass交易成功总金额','capture_num'=>$riskInfo['cybc_pass_amount']];
            $windData[]=[];//空一行
            $windData[]=['name'=>'Risky交易总数','capture_num'=>$riskInfo['cybc_risky_num']];
            $windData[]=['name'=>'Risky交易总金额','capture_num'=>$riskInfo['cybc_risky_amount']];
            $windData[]=[];//空一行
            $windData[]=['name'=>'Reject交易总数','capture_num'=>$riskInfo['cybc_reject_num']];
            $windData[]=['name'=>'Reject交易总金额','capture_num'=>$riskInfo['cybc_reject_amount']];
            $windData[]=[];//空一行
            $windData[]=['name'=>'unkonwn交易总数','capture_num'=>$unkonwn_num];
            $windData[]=['name'=>'unkonwn交易总金额','capture_num'=>$unkonwn_amount];
            $windData[]=[];//空一行
            $windData[]=['name'=>'团队平均处理时间','capture_num'=>$avgTime];
        }else{
            $windData[]=['name'=>'信用卡订单总笔数','capture_num'=>$data['allData']['num']];
            $windData[]=['name'=>'信用卡订单总金额','capture_num'=>number_format($data['allData']['sum'],2,'.','')];
            $windData[]=['name'=>'订单平均金额','capture_num'=>number_format( $data['allData']['avg'],2,'.','')];
            $windData[]=[];//空一行
            $windData[]=['name'=>'进风控订单总数','capture_num'=>$data['riskData']['num']];
            $windData[]=['name'=>'进风控订单总金额','capture_num'=>number_format( $data['riskData']['sum'],2,'.','')];
            $windData[]=[];//空一行
            $windData[]=['name'=>'Reject交易总数','capture_num'=>$void_num];
            $windData[]=['name'=>'Reject交易总金额','capture_num'=>$void_amount];
            $windData[]=[];//空一行
            $windData[]=['name'=>'unkonwn交易总数','capture_num'=>$unkonwn_num];
            $windData[]=['name'=>'unkonwn交易总金额','capture_num'=>$unkonwn_amount];
            $windData[]=[];//空一行
            $windData[]=['name'=>'团队平均处理时间','capture_num'=>$avgTime];
        }

        $header_data = array(
            'name'          => '风控人员',
            'capture_num'   => 'Capture数量',
            'capture_amount'=> 'Capture金额',
            'void_num'      => 'Void数量',
            'void_amount'   => 'Void金额',
            'unkonwn_num'   => 'Unkonwn数量',
            'unkonwn_amount'=> 'Unkonwn金额',
            'time'          => '订单处理时间',
        );

        $tool = new ExcelTool();
        if(!empty($windData)){
            $tool ->export('风控绩效统计_'.date('Y-m-d H:i:s'),$header_data,$windData,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

    public function getRiskDataById($paypal_ids){
        $where = ['A.ThirdPartyTxnID'=>['in',$paypal_ids]];

        $data = (new WindControlModel())->getRiskDataById($where);
        
        if( empty($data) ){
            return [];
        }

        $info = [];
        foreach ($data as $value) {
            //人工审核结果
            $result = 'Unknown';
            if( $value['DealWithStatus']==2 ){
                $result = 'ACCEPT';
            }else if( $value['DealWithStatus']==3 ){
                $result = 'REJECT';
            }

            $info[] = array(
                'Site'          => $value['SiteID'],
                'OrderNumber'   => $value['OrderNumber'],
                'PmtTxnID'      => $value['TransactionID'],
                'PpTxnID'       => $value['ThirdPartyTxnID'],
                'CreateTime'    => date('Y-m-d H:i:s',$value['AddTime']),
                'IsCybsUsing'   => '',
                'CurrencyCode'  => 'USD',
                'BillAmount'    => $value['AmountUsd'],
                'CICID'         => $value['CustomerID'],
                'CybsChkResult' => '',
                'CybsReviewer'  => ($value['Code']==1001 && !empty($value['DistributionAdmin']))?$value['DistributionAdmin']:'Unknown',
                'CybsReviewResult'  => $result,
                'CybsReqID'     => '',
                'IsCybcCheck'   => (($value['Code']==1001) && !empty($value['Recommendation']) ) ? 1:0,
                'CybcChkResult' => !empty($value['Recommendation']) ? 'Risky':'pass',
                'CybcReviewer'  => ($value['Code']==1001 && !empty($value['DistributionAdmin']))?$value['DistributionAdmin']:'Unknown',
                'CybcReviewResult'  => $result,
                'Country'       => $value['ShippAddressCountry'],
                'PayerEmail'    => '',
                'FullName'      => $value['ShippAddressFirstName'].' '.$value['ShippAddressLastName'],
            );
        }

        return $info;
    }

    public function exportDdr($windData){
        $header_data = array(
            'Site'          => 'Site',
            'OrderNumber'   => 'OrderNumber',
            'PmtTxnID'      => 'PmtTxnID',
            'PpTxnID'       => 'PpTxnID',
            'CreateTime'    => 'CreateTime',
            'IsCybsUsing'   => 'IsCybsUsing',
            'CurrencyCode'  => 'CurrencyCode',
            'BillAmount'    => 'BillAmount',
            'CICID'         => 'CICID',
            'CybsChkResult' => 'CybsChkResult',
            'CybsReviewer'  => 'CybsReviewer',
            'CybsReviewResult'  => 'CybsReviewResult',
            'CybsReqID'     => 'CybsReqID',
            'IsCybcCheck'   => 'IsCybcCheck',
            'CybcReviewer'  => 'CybcReviewer',
            'CybcReviewResult'  => 'CybcReviewResult',
            'Country'       => 'Country',
            'PayerEmail'    => 'PayerEmail',
            'FullName'      => 'FullName',
        );

        $tool = new ExcelTool();
        if(!empty($windData)){
            $tool ->export('paypal ddr数据统计_'.date('Y-m-d H:i:s'),$header_data,$windData,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

    public function getAddress($order_number){
        $where = ['OrderNumber'=>['in',$order_number]];

        $data = (new WindControlModel())->getAddressByOrdernumber($where);

        if( empty($data) ){
            return [];
        }

        $info = [];
        foreach ($data as $value) {
            $consignee = $value['ShippAddressFirstName'];
            if( !empty($value['ShippAddressLastName']) ){
                $consignee .= ' '.$value['ShippAddressLastName'];
            }

            $address = $value['ShippAddressStreet1'];
            if( !empty($value['ShippAddressStreet2']) ){
                $address .= ','.$value['ShippAddressStreet2'];
            }
            if( !empty($value['ShippAddressCity']) ){
                $address .= ','.$value['ShippAddressCity'];
            }
            if( !empty($value['ShippAddressState']) ){
                $address .= ','.$value['ShippAddressState'];
            }
            if( !empty($value['ShippAddressCountryName']) ){
                $address .= ','.$value['ShippAddressCountryName'];
            }

            $info[] = array(
                'OrderNumber'   => $value['OrderNumber'],
                'Consignee'     => $consignee,
                'Address'       => $address,
            );
        }

        return $info;
    }
    
    public function exportAddress($windData){
        $header_data = array(
            'OrderNumber'   => 'OrderNumber',
            'Consignee'     => 'Consignee',
            'Address'      => 'Address',
        );

        $tool = new ExcelTool();
        if(!empty($windData)){
            $tool ->export('收件人地址列表_'.date('Y-m-d H:i:s'),$header_data,$windData,'sheet1');
        }else{
            echo '没查到数据';
            exit;
        }
    }

    public function getPaypalCaseById($paypal_ids){
        $where = [
            'transaction_channel'   =>['=','paypal'],
            'type'                  =>['in','5'],
            'invoice_id'            =>['in',$paypal_ids],
        ];

        $data = (new Payment())->getCaseById($where);
        
        if( empty($data) ){
            return [];
        }

        $info = [];

        foreach ($data as $value) {

            $ReasonCode = 'unknown';
            $rdata = json_decode(base64_decode($value['response_data']),true);
            
            if(isset($rdata['resource']['messages'][0]['content'])){
                $ReasonCode = $rdata['resource']['messages'][0]['content'];
            }

            $CaseType = '';
            if($value['type']==5){
                $CaseType = 'Dispute';
            }

            $info[] = array(
                'TxnID'         => $value['invoice_id'],
                'ReasonCode'    => $ReasonCode,
                'CaseType'      => $CaseType,
            );

        }
        
        return $info;
    }
}
