<?php

namespace app\admin\services;

use app\common\helpers\CommonLib;
use think\Exception;
use think\Model;
use think\Session;
use app\admin\dxcommon\SFTPUpload;

/**
 * EDM
 * @author zhongyang
 */
class EDMService
{


    /*
      Breacase
     上传邮件到邮件服务商
    */
    public function uploadExcel($Path, $recipientLineId)
    {
        $ftp_config = config('BroadcastConfig');

        $config = [
            'host' => $ftp_config['sftpHost'], // ftp保存目录
            'port' => $ftp_config['sftpPort'], // 保存文件的名称
            'username' => $ftp_config['sftpUsername'], // 要上传的文件
            'password' => $ftp_config['sftpPassword'], // 要上传的文件
        ];


        $ftp = new SFTPUpload($config);

        $local_file = $Path;
        $remote_file = "" . "/home/dxapi/" . $recipientLineId . ".csv";
        $upload = $ftp->uploadFile($local_file, $remote_file);

        return $remote_file;
    }

    //创建邮件任务
    public function SentEmail($ItemInfo)
    {
        $ftp_config = config('BroadcastConfig');
        // $HashPassword=hash($ftp_config['apiPassword'], 'sha1');
        /* <option value="1">DX.com / news@e.dx.com</option>
           <option value="2">DX.com / news@edm.dx.com</option>
           <option value="3">Volumerate.com / news@e.volumerate.com</option>*/
        $SenderId = '1'; //发件人      
        $Lang = $ItemInfo['Lang']; //邮件内容语言      en fr
        $encoding = 'UTF-8'; //编码格式
        $Domain = $ftp_config['linkDomain']; //  链接的域名（不要包含协议名称，例如http://）
        $ImportDelay = 1;
        $IsHtml = $ItemInfo['IsHtml'];
        $Subject = $ItemInfo['Subject']; //主题
        $Body = $ItemInfo['Body']; //邮件内容
        $ExcelName = $ItemInfo['taskID']; //485.csv   $ExcelName=485 不需要文件后缀名
        $runDate = $ItemInfo['runDate']; //格林威治时间

        $PostMessage = "<batch>
            <runDate>{$runDate}</runDate>
            <properties>
                <property key='Sender'>{$SenderId}</property>
                <property key='Language'>{$Lang}</property>
                <property key='Encoding'>{$encoding}</property>
                <property key='Domain'>{$Domain}</property>
                <property key='ImportDelay'>{$ImportDelay}</property></properties>";

        $PostMessage .= "<subject><![CDATA[{$Subject}}]]></subject>";


        if ($IsHtml) {
            $PostMessage .= "<html><![CDATA[{$Body}]]></html>";
        } else {
            $PostMessage .= "<text>{$Body}</text>";
        }
        $PostMessage .= '</batch>';



        $urls = config('BroadcastConfig')['apiBaseURL'] . '/bmapi/v2/batches/' . $ExcelName;


        //$this->doCurl($urls,$PostMessage,null,true,null);

        $result = $this->doRequest($urls, 'POST', $PostMessage);

        if ($result == "")
            return true;
        else
            return   false;

    }

    //触发发送任务
    public function ActiveEmail($ItemInfo)
    {

        $batchId = $ItemInfo['taskID']; //邮件任务ID
        $recipientListId = $ItemInfo['recipientListId']; //上传excel文件的ID

        $urls = config('BroadcastConfig')['apiBaseURL'] . "/bmapi/v2/batches/{$batchId}/import";
        $encoding = 'UTF-8'; //编码格式
        $DateInfo = "<importRequest>
        <filePath>${recipientListId}.csv</filePath>
        <properties>
            <property key='Delimiter'>,</property>
            <property key='Encoding'>$encoding</property>
        </properties>
    </importRequest>";

        $result = $this->doRequest($urls, 'POST', $DateInfo);
        if ($result == "")
            return true;
        else
            return   false;
    }

    //检查邮件任务状态
    public function SyncStatus($ItemInfo)
    {
        //$batchId=999999;
        $urls = config('BroadcastConfig')['apiBaseURL'] . "/bmapi/v2/batches/{$ItemInfo}/status";
        $result = $this->doRequest($urls, 'GET', null);
        return  $result;
    }


    private function doRequest($url, $method, $data = 0)
    {

        //init cURL in PHP
        $curl = curl_init($url);
        // curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, true );

        // set the method
        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                break;
            case 'PUT':
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                break;
        }

        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            //ask for an xmpl format
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/xml"));
        }



        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // username and password
        //curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt ( $curl, CURLOPT_USERPWD, 'dxapi:181ba182bea56eba7bf11e416ca1279741ab7e94' );


        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($curl, CURLOPT_USERPWD, "dxapi:181ba182bea56eba7bf11e416ca1279741ab7e94");
        // request URL
        $response = curl_exec($curl);

        // split the received response into the header and body part and
        // save the header for further use
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $this->_header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($status == 200) {
            return $body;
        } else {
            return $body;
        }
    }
}
