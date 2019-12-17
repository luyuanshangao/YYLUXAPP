<?php
/**
 * Created by PhpStorm.
 * @Author: heng.zhang
 * @Date: 2018/7/17 10:25
 * @Function:Excel导出工具
 */

namespace app\admin\dxcommon;
use think\Loader;


//PHPExcel/PHPExcel.php
//Loader::import('PHPExcel.PHPExcel',EXTEND_PATH);
//PHPExcel/PHPExcel/IOFactory.php
//Loader::import('PHPExcel.PHPExcel.IOFactory',EXTEND_PATH);

/**
 * Excel 操作工具
 * Class Excel
 * @package app\admin\dxCommon
 */
class ExcelTool
{
    private $excelCopy = 'Excel5'; //导出excel版本
    private $suffix = 'xls';
    private $objPHPExcel = null; //PHPExcel对象
    private $objIOFactory = null; //PHPExcel对象

    /**
     * @param string $excelCopy
     */
    public function __construct($excelCopy = 'Excel5')
    {
//        vendor("PHPExcel.PHPExcel");
        $this->objPHPExcel  = new \PHPExcel();
        $this->excelCopy = $excelCopy;
        //判断后缀名
        if ($this->excelCopy == 'Excel5') {
            $this->suffix = '.xls';
        } else if ($this->excelCopy == 'Excel2007') {
            $this->suffix = '.xlsx';
        } else if($this->excelCopy == 'CSV') {
            $this->suffix = '.csv';
        }
    }


    /**
     * 导出Excel文件
     * @param string $fileName  导出的文件名称
     * @param array $headerArr  头部数据：['name'=>'名称']
     * @param array $data       数据：[['name'=>'张三'],['name'=>'李四']]
     * @param string $sheet     工作表的名称
     */
    public function export($fileName="Excel", $headerArr = array(), $data = array(),$sheet = 'Sheet1',$url='')
    {
        $this->objPHPExcel->setActiveSheetIndex(0); //设置当前的sheet
        $objSheet = $this->objPHPExcel->getActiveSheet(); //获取当前活动sheet
        $objSheet->setTitle($sheet); //设置标题

        //获取列字母,设置第一行表头
        $headCharArr = $this->getHeaderChar($headerArr); //A B C
        foreach ($headCharArr as $k => $v) {
            $objStyle = $objSheet->getStyle($headCharArr[$k] . '1');
            $objAlign = $objStyle->getAlignment();
            $objAlign->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //上下居中
            $objAlign->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT); //左对齐
            $objSheet->setCellValue($headCharArr[$k] . '1', $headerArr[$k]);
        }

        //导出数据
        $j = 2;
        foreach ($data as $k => $v) {
            foreach ($headerArr as $k1 => $v1) {
                $objStyle = $objSheet->getStyle($headCharArr[$k1] . $j);
                $objAlign = $objStyle->getAlignment();
                $objAlign->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //上下居中
                $objAlign->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                $val = $v[$k1].'';
                $objSheet->setCellValueExplicit($headCharArr[$k1] . $j, $val);
            }
            $j++;
        }

        $objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, $this->excelCopy);

        $fileName = $fileName . ($this->suffix);//dump($fileName);exit;
        $copy = $this->excelCopy;
        $this->browser_export($copy, $fileName);
        if($url != ''){
           $objWriter->save($url.$fileName);
        }else{
           $objWriter->save('php://output'); //输出到浏览器
        }

    }
     /**
     * 导出Excel文件(数据从redis获取)
     * @param string $fileName  导出的文件名称
     * @param array $headerArr  头部数据：['name'=>'名称']
     * @param array $data       数据：[['name'=>'张三'],['name'=>'李四']]
     * @param string $sheet     工作表的名称
     */
    public function redis_export($fileName="Excel", $headerArr = array(), $data = array(),$sheet = 'Sheet1',$url='',$order_query){
            ini_set('max_execution_time', '0');
            ignore_user_abort();
            $this->objPHPExcel->setActiveSheetIndex(0); //设置当前的sheet
            $objSheet = $this->objPHPExcel->getActiveSheet(); //获取当前活动sheet
            $objSheet->setTitle($sheet); //设置标题

            //获取列字母,设置第一行表头
            $headCharArr = $this->getHeaderChar($headerArr); //A B C
            foreach ($headCharArr as $k => $v) {
                $objStyle = $objSheet->getStyle($headCharArr[$k] . '1');
                $objAlign = $objStyle->getAlignment();
                $objAlign->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //上下居中
                $objAlign->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT); //左对齐
                $objSheet->setCellValue($headCharArr[$k] . '1', $headerArr[$k]);
            }

            //导出数据
           try {
               $j = 2;
               while ( $v_json = redis()->LPOP($order_query) ) {

                    $v = json_decode($v_json,true);
                    foreach ($headerArr as $k1 => $v1) {
                        $objStyle = $objSheet->getStyle($headCharArr[$k1] . $j);
                        $objAlign = $objStyle->getAlignment();
                        $objAlign->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //上下居中
                        $objAlign->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                        $val = $v[$k1].'';
                        $objSheet->setCellValue($headCharArr[$k1] . $j, $val);
                    }
                    if($j%2500 == 0){
                         sleep(5);
                    }
                    $j++;
                }
           } catch (Exception $e) {
              sleep(10);
              $this->redis_export($fileName, $headerArr, $data,$sheet,$url);
           }


            $objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, $this->excelCopy);

            $fileName = $fileName . ($this->suffix);//dump($fileName);exit;
            $copy = $this->excelCopy;
            @ob_end_clean();
            // $this->browser_export($copy, $fileName);
            if($url != ''){
               //$objWriter->save('php://output'); //输出到浏览器
              $objWriter->save($url.$fileName);//'uploads/orderfile/'

            }else{
              $objWriter->save('php://output'); //输出到浏览器
            }
    }

    /**
     * 获取excel列数字母
     * @param array $data
     * @return array
     */
    private function getHeaderChar($data = array())
    {
        $index = 65; //A标签
        $char = '';
        $charArr = array();
        foreach ($data as $k => $v) {
            $charArr[$k] = $char . chr($index++);
            if ($index == 91) {
                $index = 65;
                $char .= 'A';
            }
        }
        return $charArr;
    }

    /**
     * 输出到浏览器
     * @param $copy
     * @param $fileName
     */
    private function browser_export($copy, $fileName)
    {
        ob_end_clean();//清除缓冲区,避免乱码
        if ($copy == 'Excel5') {
            header('Content-Type: application/vnd.ms-excel;'); //告诉浏览器输出Excel2003文件
        } else {
            //告诉浏览器输出Excel2007文件
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        header('Content-Disposition: attachment;filename="' . iconv('utf-8', 'gb2312', $fileName) . '"'); //文件名
        header('Cache-Control: max-age=0'); //禁止浏览器缓存
    }


    /**
     * 导入文件通过上传的文件
     * @param array $format  格式：['名称'=>'name']  会把列名转化成name
     * @param string $inputname  上传输入框的名称name
     * @param string $sheetName  工作表名称，默认：Sheet1
     * @return array  放回指定的数据
     */
    public function importByInput($format = array(),$inputname='excel',  $sheetName = 'Sheet1')
    {

        $data = $this->importExcel($_FILES[$inputname]['tmp_name'], $sheetName);
        return $this->dealImportData($format,$data);

    }

    /**
     * 导入指定excel
     * @param array $format     格式：['名称'=>'name']  会把列名转化成name
     * @param string $filename  excel在本机的绝对路径
     * @param string $sheetName 工作表名称，默认：Sheet1
     * @return array
     */
    public function import($format = array(),$filename='',  $sheetName = 'Sheet1')

    {

        $data = $this->importExcel($filename, $sheetName);
        return $this->dealImportData($format,$data);

    }

    /**
     * 处理导入数据
     * @param $format
     * @param $data
     * @return array
     */
    private function dealImportData($format,$data){
        if (!$format) {
            return $data;
        } else {
            $newdata=array();
            foreach($data as $k=>$v){
                $row=array();
                foreach($v as $k2=>$v2){
                    //$format[$k2]  获取key
                    if($format[trim($k2)]){//去除数据的两端空格
                        $row[$format[trim($k2)]]=trim($v2);
                    }

                }
                $newdata[]=$row;
            }
            return $newdata;
        }
    }


    /**
     * 导入excel,返回原始二维数据
     * @param $filename  文件绝对路径
     * @param string $sheetName 工作表名称，默认：Sheet1
     * @return array
     */
    public function importExcel($filename, $sheetName = 'Sheet1')
    {
        header("Content-Type:text/html;charset=utf-8");
        $fileType = \PHPExcel_IOFactory::identify($filename);//自动获取文件的类型提供给phpexcel用
        $objReader = \PHPExcel_IOFactory::createReader($fileType);//获取文件读取操作对象
        $sheetName = array($sheetName);
        $objReader->setLoadSheetsOnly($sheetName);//只加载指定的sheet
        $objPHPExcel = $objReader->load($filename);//加载文件
        $key = array();
        $value = array();
        foreach ($objPHPExcel->getWorksheetIterator() as $sheet) {//循环取sheet
            foreach ($sheet->getRowIterator() as $row) {//逐行处理
                $temp = array();
                foreach ($row->getCellIterator() as $kk=> $cell) {//逐列读取
                    if ($row->getRowIndex() < 2) {
                        $key[$kk] = $cell->getValue();
                    } else {
                        $data = $cell->getValue();//获取单元格数据
                        $temp[$kk] = $data;
                    }
                }
                if (!empty($temp)) {
                    $value[] = $temp;
                }
            }
        }
        $data = array();
        foreach ($value as $k => $v) {
            $temp = array();
            foreach ($v as $k1 => $v1) {
                $temp[$key[$k1]] = $v1;
            }
            $data[] = $temp;
        }
        return $data;
    }
    /**
     * 读取excel数据
     * [load description]
     * @return [type] [description]
     */
    public function load($url){
      return   \PHPExcel_IOFactory::load($url);
      // return  $this->objIOFactory->load($url);
    }

    /**
     * 本地文件下载到浏览器
     * @param $url
     * @param $file_name
     * @return bool
     */
    public function down($url,$file_name){
        if(!file_exists($url)){
            return false;
        }
        $contents = file_get_contents($url);
        $file_size = filesize($url);
        header("Content-type: application/octet-stream;charset=utf-8");
        header("Accept-Ranges: bytes");
        header("Accept-Length: $file_size");
        header("Content-Disposition: attachment; filename=".$file_name);
        exit($contents);
    }

    /**
     * 追加数据
     * @param $filename 文件路径
     * @param $headerArr 头部数据
     * @param $data 新增数据
     * @param $highestRow = 0 当前行
     * @return bool
     */
    public function append($filename,$headerArr,$data,$highestRow = 0){
        //获取列字母,设置第一行表头
        $headCharArr = $this->getHeaderChar($headerArr); //A B C

//        vendor("PHPExcel.PHPExcel");
        $inputFileName = $filename;//excel文件路径
//        date_default_timezone_set('PRC');
        // 读取excel文件
        try {
//            $inputFileType = \PHPExcel_IOFactory::identify($inputFileName);
            $objReader = \PHPExcel_IOFactory::createReader('CSV');
            $objPHPExcel = $objReader->load($inputFileName);
        } catch(\Exception $e) {
            die('加载文件发生错误："'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
        }
        $baseRow = $highestRow;      //指定插入行
        foreach ($data as $k => $v) {

            $row = $baseRow + $k;    //$row是循环操作行的行号
            $objPHPExcel->getActiveSheet()->insertNewRowBefore($row, 1);  //在操作行的号前加一空行，这空行的行号就变成了当前的行号
            foreach($headCharArr as $c => $char){
                //对应的列都附上数据和编号
                $val = empty($v[$c]) ? '' : $v[$c].'';
                $objPHPExcel->getActiveSheet()->setCellValue($char . $row,$val);
            }
        }
        if (ob_get_length()) ob_end_clean();//清除缓冲区,避免乱码
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $this->excelCopy);
        $objWriter->save($inputFileName);
    }

    /**
     * 导出Excel文件
     * @param string $fileName  导出的文件名称
     * @param array $headerArr  头部数据：['name'=>'名称']
     * @param array $data       数据：[['name'=>'张三'],['name'=>'李四']]
     * @param string $sheet     工作表的名称
     */
    public function save($fileName="Excel", $headerArr = array(), $data = array(),$sheet = 'Sheet1',$url='')
    {
        $this->objPHPExcel->setActiveSheetIndex(0); //设置当前的sheet
        $objSheet = $this->objPHPExcel->getActiveSheet(); //获取当前活动sheet
        $objSheet->setTitle($sheet); //设置标题

        //获取列字母,设置第一行表头
        $headCharArr = $this->getHeaderChar($headerArr); //A B C
        foreach ($headCharArr as $k => $v) {
            $objStyle = $objSheet->getStyle($headCharArr[$k] . '1');
            $objAlign = $objStyle->getAlignment();
            $objAlign->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //上下居中
            $objAlign->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT); //左对齐
            $objSheet->setCellValue($headCharArr[$k] . '1', $headerArr[$k]);
        }

        //导出数据
        $j = 2;
        foreach ($data as $k => $v) {
            foreach ($headerArr as $k1 => $v1) {
                $objStyle = $objSheet->getStyle($headCharArr[$k1] . $j);
                $objAlign = $objStyle->getAlignment();
                $objAlign->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER); //上下居中
                $objAlign->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                $val = empty($v[$k1]) ? '' : $v[$k1].'';
                $objSheet->setCellValueExplicit($headCharArr[$k1] . $j, $val);
            }
            $j++;
        }

        $objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, $this->excelCopy);

        $fileName = $fileName . ($this->suffix);//dump($fileName);exit;
        if (ob_get_length()) ob_end_clean();//清除缓冲区,避免乱码
        $objWriter->save($url.$fileName);
    }
}