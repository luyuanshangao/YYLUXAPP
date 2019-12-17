<?php
namespace app\orderfrontend\controller;
use app\demo\controller\Auth;
use app\common\helpers\RedisClusterBase;
use think\Log;
use think\Exception;

/**
 * edm数据查询
 * @author wang
 * 2018-12-20
 */
class OrderEdm extends Auth
{
    /**
     * 根据条件查询
     * [order description]
     * @return [type] [description]
     * @author wang   addtime 2018-09-27
     */
    public function order_query(){
        $result = model("OrderEdm")->order_query();
        return $result;
    }
    /**
     * 数据导出
     * [export description]
     * @return [type] [description]
     */
    public function export(){
       $j = 1;
       $data = [];
       for($i=0;$i<200;$i++){
           $list = model("OrderEdm")->export();
           if(empty($list)){
               if($data){
                  $result = $this->edm_export_location($data,$j);
               }
               exit;
           }else{
             foreach ($list as $k => $v) {
                $data[] = $v;
             }
           }
       }
       // return $result;
    }
    public function edm_export_location($data = [],$sum){
        ini_set('max_execution_time', '0');

        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中

        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '用户ID')
            ->setCellValue('B1', '用户邮箱');
            // ->setCellValue('C1', '店铺ID')
            // ->setCellValue('D1', '类别路径')
            // ->setCellValue('E1', 'EPR类别');
        $objPHPExcel->getActiveSheet()->setTitle('sheet1');
        // //设置数据
        $i = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        foreach ($data as $vo){
            if(!empty($vo['TopicName'])){
                $TopicName = $vo['TopicName'];
            }
            $objActSheet->setCellValue('A'.$i, $vo['customer_id']);
            $objActSheet->setCellValue('B'.$i, $vo["mailbox"]);
            // $objActSheet->setCellValue('C'.$i, $vo["StoreID"]);
            // $objActSheet->setCellValue('D'.$i, $vo["CategoryPath"]);
            // $objActSheet->setCellValue('E'.$i, $vo["erpClass"]);
            $i++;
        }
        // excel头参数
        $fileName = $TopicName.'_'.$sum;
        $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        //ob_end_clean();
        // header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".xls'");exit;
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=$xlsTitle.xls");
        header('Cache-Control: max-age=0');
        //excel5为xls格式，excel2007为xlsx格式
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        // $objWriter->save('php://output');
        $objWriter->save('uploads/'.$xlsTitle.'.xls');
        return ;
       // exit;

    }
}
