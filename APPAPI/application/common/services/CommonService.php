<?php
namespace app\common\services;

use think\Log;
use app\common\helpers\CommonLib;
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
        Log::record('payissetpasswordconfig:'.json_encode($wsdl_url_config));
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
            return $e->getMessage();
        }
    }
    //banner图片数据整理
    public static function getBannerInfos($data,$lang){
        $result = $currentData = $defaultData = array();
        if(isset($data['Banners']['BannerImages']['BannerFonts'])){
            $infos = $data['Banners']['BannerImages']['BannerFonts'];
            //当前语种数据
            $currentData = CommonLib::filterArrayByKey($infos,'Language',$lang);
            if($lang != DEFAULT_LANG){
                //取出默认是英文的数据
                $defaultData = CommonLib::filterArrayByKey($infos,'Language',DEFAULT_LANG);
            }
            if(empty($currentData)){
                $currentData = $defaultData;
            }
            if(isset($currentData['ImageUrl'])){
                foreach($currentData['ImageUrl'] as $key => $imgs){
                    //当切换语种的时候，其他数据为空的情况下，默认赋值英文的数据
                    $result[$key]['ImageUrl'] = $imgs;
                    if(empty($imgs) && $lang != DEFAULT_LANG){
                        $result[$key]['ImageUrl'] = isset($defaultData['ImageUrl'][$key]) ? $defaultData['ImageUrl'][$key] : '';
                    }

                    $result[$key]['LinkUrl'] = !empty($currentData['LinkUrl'][$key]) ? $currentData['LinkUrl'][$key] : '';
                    if(empty($currentData['LinkUrl'][$key]) && $lang != DEFAULT_LANG){
                        $result[$key]['LinkUrl'] = isset($defaultData['LinkUrl'][$key]) ? $defaultData['LinkUrl'][$key] : '';
                    }
                    $result[$key]['MainText'] = !empty($currentData['MainText'][$key]) ? $currentData['MainText'][$key] : '';
                    if(empty($currentData['MainText'][$key]) && $lang != DEFAULT_LANG){
                        $result[$key]['MainText'] = isset($defaultData['MainText'][$key]) ? $defaultData['MainText'][$key] : '';
                    }
                    $result[$key]['SubText'] = !empty($currentData['SubText'][$key]) ? $currentData['SubText'][$key] : '';
                    if(empty($currentData['SubText'][$key]) && $lang != DEFAULT_LANG){
                        $result[$key]['SubText'] = isset($defaultData['SubText'][$key]) ? $defaultData['SubText'][$key] : '';
                    }
                }
            }
        }
        return $result;
    }
    //文本广告数据整理
    public static function getKeywordsInfos($data,$lang){
        $result = $currentData = $defaultData = array();
        if(isset($data['Keyworks']['TextData'])){
            $infos = $data['Keyworks']['TextData'];
            //当前语种数据
            $currentData = CommonLib::filterArrayByKey($infos,'Language',$lang);
            if(empty($currentData['Value'])){
                if($lang != DEFAULT_LANG){
                    //取出默认是英文的数据
                    $defaultData = CommonLib::filterArrayByKey($infos,'Language',DEFAULT_LANG);
                    $currentData = $defaultData;
                }
            }
            $result = explode(',',$currentData['Value']);
        }
        return $result;
    }
}