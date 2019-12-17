<?php
namespace app\services\controller;

/**
 * WCF服务调用类
 * Class WcfService
 * @author tinghu.liu
 * @date 2018-04-28
 * @package app\services\controller
 */
use app\index\controller\Common;
use think\Controller;

class WcfService
{
    /**
     * WCF LisService 服务调用方法
     * @param $function_name 要调用的方法名，如：GetPackageTrace
     * @param $params 参数，如：
     * $_params = array(
     *      'GetPackageTrace' => array(
     *          'request' => array(
     *          'HasAll' => true,
     *              'TrackingNos' => array(
     *                  'string'=>array('RI256778026CN')
     *              )
     *          )
     *      )
     * )
     * @return array|\Exception
     */
    public function lisServiceSoap($function_name, $params){
        $wsdl_url_config = config('wsdl_url');
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
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }

    /**
     * 发送邮件服务方法调用
     * @param $function_name 要调用的方法名：如 SendOtherMail
     * @param array $params 参数：如
     * [
        'SendOtherMail'=>[
            'model'=>[
                'Body'=>'content',
                'CustomerEmail'=>'liuth@volumerate.com',
                'CustomerID'=>18,
                'EmailAddressBCC'=>'',
                'EmailAddressCC'=>'',
                'From'=>'dx.com',
                'MSSUserName'=>'admin',
                'SiteID'=>1,
                'Title'=>'test title'
            ]
        ]
     * ]
     * @return array|strin
     * 成功返回如下：guid
     * Array
        (
            [SendOtherMailResult] => a082350f-1d0f-43b4-897c-216e9e18bd65
        )
     */
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

    public function test(){
        $function_name = 'SendOtherMail';
        $params = [
            'SendOtherMail'=>[
                'model'=>[
                    'Body'=>'content1',
                    'CustomerEmail'=>'liuth@volumerate.com',
                    'CustomerID'=>18,
                    'EmailAddressBCC'=>'',
                    'EmailAddressCC'=>'',
                    'From'=>'dx.com',
                    'MSSUserName'=>'',
                    'SiteID'=>1,
                    'Title'=>'test title20'
                ]
            ]
        ];
        $res = $this->sendMailServiceSoap($function_name, $params);
        print_r($res);die;
        $function_name = 'GetPackageTrace';
        $params = array(
            'GetPackageTrace' => array(
                'request' => array(
                    'HasAll' => true,
                    'TrackingNos' => array(
                        'string'=>array('RI256778026CN')
                    )
                )
            )
        );
        //print_r($this->lisServiceSoap($function_name, $params));/*die;*/

        libxml_disable_entity_loader(false);
        $opts = array(
            'ssl'   => array(
                'verify_peer'          => false
            ),
            'https' => array(
                'curl_verify_ssl_peer'  => false,
                'curl_verify_ssl_host'  => false
            )
        );
        $streamContext = stream_context_create($opts);
        //https://svcml01.dxqas.com/LIS/V4.0_WCFByUSA/Wcf/LisService.svc?wsdl
        $url = 'https://apiml01.dxqas.com/OMS/v4.5.5/api/Order/Create?wsdl';
        $client = new \SoapClient($url,
            array(
                'stream_context'    => $streamContext
            ));

        print_r($client);
        echo '<xmp>';
        echo "提供的方法\n";
        print_r( $client->__getFunctions ());
        echo "相关的数据结构\n";
        print_r($client->__getTypes () );
        echo '</xmp>';

//        $result = $client->__soapCall('GetPackageTrace',['HasAll'=>true, 'TrackingNos'=>'RI256779137CN']);
        dump($result);exit;

    }



}