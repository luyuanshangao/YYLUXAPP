<?php
namespace app\common\services;

use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Monlog;

/**
 * 订单类
 * Class CommonService
 * @author tinghu.liu 2018/06/07
 * @package app\common\services
 */
class CommonService
{

    /**
     * soap调用wcf-PaymentService 订单服务
     * @param $_soap_function 要调用的方法名
     * @param $_params 参数
     * @return mixed|string
     */
    public function payment($_soap_function,$_params){
        $wsdl_url_config = config('wsdl_order_url');
        $wsdl = $wsdl_url_config['lis_service_wsdl']['url'];
        $opts = $wsdl_url_config['lis_service_wsdl']['options'];
        $user_name = $wsdl_url_config['lis_service_wsdl']['user_name'];
        $password = $wsdl_url_config['lis_service_wsdl']['password'];
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($_soap_function, $_params);
            return $result;
        }catch (\Exception $e){
            Log::record('CommonService->dxSoap->error：'.$e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     *新版退款
     */
    public function refund($params, $customer_id=0, $order_master_number=''){
        if( empty($params['TransactionId'])  || empty($params['RefundAmount']) || empty($params['Note']) ){
            return ['code'=>400,'msg'=>'params error'];
        }
        $TransactionById['transaction_id']=$params['TransactionId'];
        Log::record('$TransactionById：'.json_encode($TransactionById));
        $tData = (new BaseApi)->getTransactionById($TransactionById);
        Log::record('$tData：'.json_encode($tData));
        if(!empty($tData)&&$tData['data']){
            $transactionData=$tData['data'];
        }else{
            Log::record('transactionData错误：'.json_encode($params));
            return ['code'=>400,'msg'=>'$transactionData data error'];
        }
        if(strtolower($transactionData['transaction_channel'])=='paypal'){
            if( empty($transactionData) || $transactionData['transaction_action']!='Capture' || $transactionData['response_status']!='1' ){
                return ['code'=>400,'msg'=>'transaction data error'];
            }
        }else{
            if( empty($transactionData) || $transactionData['transaction_action']!='Purchase' || $transactionData['response_status']!='1' ){
                return ['code'=>400,'msg'=>'transaction data error'];
            }
        }


        //获取用户邮箱
        $da['ID']=$transactionData['customer_id'];
        $customer_data = (new BaseApi)->getCustomerByID($da);
        Log::record('$customer_data：'.print_r(json_encode($customer_data), true));
        if( empty($customer_data['data']['email']) ){
            return ['code'=>400,'msg'=>'get email failed'];
        }
        $email = $customer_data['data']['email'];

        $data = array(
            'RefundAmount'        => $params['RefundAmount'],
            'Note'                => $params['Note'],
            'TransactionSouce'    => $transactionData['transaction_souce'],
            'CurrencyType'        => $transactionData['currency_type'],
            'TransactionId'       => $transactionData['transaction_id'],
            'CustomerIp'          => $transactionData['customer_ip'],
            'ChildrenOrderNumber' => json_encode([$params['order_number']]),//需要退款的订单
            'CustomerEmail'       => $email,
            'ChildOrderPrice'     => $params['json'],
        );
        Log::record('$data：'.print_r(json_encode($data), true));
        $result = (new BaseApi)->paymentRefund($data);

        Monlog::write(LOGS_MALL_CART.'_payment','info',__METHOD__,'refund',$data,config('payment_base_url').'unification/refund/index',$result, $customer_id, $order_master_number, $params['order_number']);

        Log::record('$result：'.print_r(json_encode($result), true));
        if(empty($result['code']) ){
            Log::record("payment interface error【transactionId-{$params['TransactionId']}】");
            $msg=!empty($result['msg'])?$result['msg']:'payment interface error';
            return ['code'=>400,'msg'=>$msg];
        }else if( $result['code']!='200' || empty($result['data']) || $result['data']['status']=='failure' ){
            Log::record("refund failed【transactionId-{$params['TransactionId']}】result:".print_r($result,1));
            $msg=!empty($result['msg'])?$result['msg']:'refund failed';
            return ['code'=>400,'msg'=>$msg];
        }
        $data1=[];
        if(!empty($result['data']) ) {
            $data1=$result['data'];
        }

        return ['code'=>200,'data'=>$data1,'msg'=>'success'];
    }

    /**
     * soap调用wcf-PaymentService 订单服务
     * @param $_soap_function 要调用的方法名
     * @param $_params 参数
     * @return mixed|string
     */
    public function CancelOrder($_soap_function,$_params){
        $wsdl_url_config = config('order_service_url');
        $wsdl = $wsdl_url_config['lis_service_wsdl']['url'];
        $opts = $wsdl_url_config['lis_service_wsdl']['options'];
        $user_name = $wsdl_url_config['lis_service_wsdl']['user_name'];
        $password = $wsdl_url_config['lis_service_wsdl']['password'];
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($_soap_function, $_params);
            return $result;
        }catch (\Exception $e){
            Log::record('CommonService->dxSoap->error：'.$e->getMessage());
            return $e->getMessage();
        }
    }

    /*
     * 检测用户是否设置了支付密码
     * */
    public function PaymentPasswordCorrectnessCheck($function_name, array $params){
        $wsdl_url_config = config('PaymentPasswordServicUrl');
        $wsdl = $wsdl_url_config['lis_service_wsdl']['url'];
        $opts = $wsdl_url_config['lis_service_wsdl']['options'];
        $user_name = $wsdl_url_config['lis_service_wsdl']['user_name'];
        $password = $wsdl_url_config['lis_service_wsdl']['password'];
//        Log::record('payissetpasswordconfig:'.json_encode($wsdl_url_config));
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }

    /**
     * soap调用wcf-FulfillmentService服务
     * @param $function_name 方法名称
     * @param array $params 参数
     * @return string
     */
    public function FulfillmentService($function_name, array $params){
        $wsdl_url_config = config('fulfillment_service_url');
        $wsdl = $wsdl_url_config['lis_service_wsdl']['url'];
        $opts = $wsdl_url_config['lis_service_wsdl']['options'];
        $user_name = $wsdl_url_config['lis_service_wsdl']['user_name'];
        $password = $wsdl_url_config['lis_service_wsdl']['password'];
        Log::record('fulfillment_service_urlconfig:'.json_encode($wsdl_url_config));
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }

    public function sendMailServiceSoap($function_name, array $params){
        $wsdl_url_config = config('wsdl_url');
        $wsdl = $wsdl_url_config['send_mail_service_wsdl']['url'];
        $opts = $wsdl_url_config['send_mail_service_wsdl']['options'];
        $user_name = $wsdl_url_config['send_mail_service_wsdl']['user_name'];
        $password = $wsdl_url_config['send_mail_service_wsdl']['password'];
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            Log::record('sendMailServiceSoap - 异常：'.$e->getMessage());
            return $e->getMessage();
        }
    }

}