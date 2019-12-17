<?php
/**
 * EXCEL操作类
 * Created by PhpStorm.
 * User: yxh
 * Date: 2017/6/21
 * Time: 14:14
 */
namespace app\index\dxcommon;
class Phpexcel{

    public function import_excel($filename){
        // 判断文件是什么格式
         $file=ROOT_PATH.'runtime'. DS .$filename;
        //$file=ROOT_PATH.'a.xlsx';

        $type = pathinfo($file);
        $type = strtolower($type["extension"]);
        if($type==='xls'){
            $type='Excel5';
        }else{
            $type='Excel2007';
        }

        ini_set('max_execution_time', '0');
        Vendor('Classes.PHPExcel');
        // 判断使用哪种格式
        $objReader = \PHPExcel_IOFactory::createReader($type);
        $objPHPExcel = $objReader->load($file);
        $sheet = $objPHPExcel->getSheet(0);
        // 取得总行数
        $highestRow = $sheet->getHighestRow();

        // 取得总列数
        $highestColumn = $sheet->getHighestColumn();
        ++$highestColumn;
       //循环读取excel文件,读取一条,插入一条
        $data=array();
        //从第二行开始读取数据
        for($j=2;$j<=$highestRow;$j++){
            //从A列读取数据
            for($k='A';$k!=$highestColumn;++$k){
                // 读取单元格
                $data[$j][]=$objPHPExcel->getActiveSheet()->getCell("$k$j")->getValue();
            }
        }
        //die;
        return $data;
    }

    public function create($data=[],$filename=''){
        ini_set('max_execution_time', '0');
        Vendor('Classes.PHPExcel');
        $filename=str_replace('.xls', '', $filename).'.xls';
        $phpexcel = new \PHPExcel();
        $phpexcel->getProperties()
            ->setCreator("Maarten Balliauw")
            ->setLastModifiedBy("Maarten Balliauw")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("Test result file");
        $phpexcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $phpexcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中
        $phpexcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $phpexcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $phpexcel->getActiveSheet()->fromArray($data);
        $phpexcel->getActiveSheet()->setTitle('Sheet1');
        $phpexcel->setActiveSheetIndex(0);
        $letters1 = range('A','Z');
        foreach ($letters1 as $k=>$v){
            $letters2[] = 'A'.$v;
        }
        $letters = array_merge($letters1,$letters2);
        $count =0;
        if(!empty($data[0])){
            foreach($data[0] as $key=>$tittle)
            {
                $cell_name = $letters[$count]."1";
                $count++;
                $phpexcel->getActiveSheet()->getStyle($cell_name)->getFont()->setBold(true);
            }
        }
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=$filename");
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0
        return $objwriter = \PHPExcel_IOFactory::createWriter($phpexcel, 'Excel5');
        $objwriter->save('php://output');
        exit;
    }

    public function create1($data=[],$filename=''){

        ini_set('max_execution_time', '0');
        Vendor('Classes.PHPExcel');
        $filename=str_replace('.xls', '', $filename).'.xlsx';
        $phpexcel = new \PHPExcel();
        $phpexcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $phpexcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中
        $phpexcel->getActiveSheet()->getDefaultColumnDimension('B')->setWidth(25);//设置宽度
        $phpexcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=$filename");
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0
        return $objwriter = \PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
        $objwriter->save('php://output');
        exit;
    }
}