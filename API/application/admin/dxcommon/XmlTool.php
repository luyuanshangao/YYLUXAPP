<?php
/**
 * Created by PhpStorm.
 * @Author: heng.zhang
 * @Date: 2018/7/17 10:25
 * @Function:Excel导出工具
 */

namespace app\admin\dxcommon;
use app\common\helpers\CommonLib;
use think\Exception;
use think\Loader;



/**
 * XML 操作工具
 * Class XML
 * @package app\admin\dxCommon
 */
class XmlTool
{
    private $xml = null;

    /**
     * @param string $version
     * @param string $encoding
     */
    public function __construct($version = '1.0',$encoding = 'utf-8')
    {
        $this->xml = new \DOMDocument('1.0', 'utf-8');
    }

    /**
     * 导出XML
     * @param string $fileName  导出的文件名称
     * @param array $headerArr  头部数据：['name'=>'名称']
     * @param array $data       数据：[['name'=>'张三'],['name'=>'李四']]
     */
    public function saveXML($fileName="XML", $headerArr = array(), $data = array(),$url='')
    {
        $filename = $url.$fileName;
        if(file_exists($filename)) {
            # 如果文件存在，则进行追加
            $this->xml->load($filename, LIBXML_NOBLANKS);//LIBXML_NOBLANKS 可以增加换行功能
            $articles = $this->xml->getElementsByTagName("products")->item(0);  //找到文件追加的位置

            foreach($data as $pkey => $product){
                $newarticles = $this->xml->createElement('product');
                $articles->appendChild($newarticles);				//进行文件追加
                foreach($headerArr as $hkey => $header){
                    $xml_AttendeeType = $this->xml->createElement($header);
                    //过滤特殊字符，不然XML会报错
                    if($hkey == 'firstClassName' || $hkey == 'secondClassName' || $hkey == 'Descriptions'|| $hkey == 'Title'){
                        $product[$hkey] = !empty($product[$hkey]) ? $this->_SpecialChar($product[$hkey]) : '';
                    }
                    $xml_AttendeeType->nodeValue = empty($product[$hkey]) ? '' : $product[$hkey];
                    $newarticles->appendChild($xml_AttendeeType);
                }
            }
        }else{
            #如果文件不存在，则进行文件写入
            $xml_MeetingAttendee = $this->xml->createElement("products");
            $this->xml->appendChild($xml_MeetingAttendee);
            foreach($data as $pkey => $product){
                $xml_Attendee = $this->xml->createElement("product");
                $xml_MeetingAttendee->appendChild($xml_Attendee);
                foreach($headerArr as $hkey => $header){
                    $xml_AttendeeType = $this->xml->createElement($header);
                    //过滤特殊字符，不然XML会报错
                    if($hkey == 'firstClassName' || $hkey == 'secondClassName' || $hkey == 'Descriptions'|| $hkey == 'Title'){
                        $product[$hkey] = !empty($product[$hkey]) ? $this->_SpecialChar($product[$hkey]) : '';
                    }
//                    if($hkey == 'Descriptions'){
//                        $product[$hkey] = '';
//                    }
                    $xml_AttendeeType->nodeValue = empty($product[$hkey]) ? '' : $product[$hkey];
                    $xml_Attendee->appendChild($xml_AttendeeType);
                }
            }
        }

        $this->xml->formatOutput = true;//新增的时候有用，但是追加的时候没有换行
        $this->xml->save($filename);
    }

    public  function _SpecialChar($strParam){
        //过滤特殊字符
        $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
        $string = preg_replace($regex," ",$strParam);
        return $string;
        //过滤空格，制表符
//        $search = array(" ","　","\n","\r","\t");
//        $replace = array("","","","","");
//        return str_replace($search, $replace, $string);
    }
}