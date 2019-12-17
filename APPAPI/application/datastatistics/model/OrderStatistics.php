<?php
namespace app\datastatistics\model;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use app\common\controller\Email;
use app\share\controller\Currency;

/**
 * 供应商模型
 * @author
 * @version  heng zhang 2018/3/30
 */
class OrderStatistics extends Model{
    const sales_order = 'sales_order';
    const order_statistics = 'order_statistics';
    const sys_config = 'sys_config';
    public function __construct(){
        parent::__construct();
        $this->order = Db::connect('db_order');
        $this->mongodb = Db::connect('db_mongodb');

    }
    public function OrderStatistics(){
        date_default_timezone_set('America/Managua');//美国西六区
        // date_default_timezone_set('America/New_York');
        $time = time();echo date('Y-d-y H:i:s',time());
         // date_default_timezone_set('America/New_York');
         // $time = time();echo date('Y-d-y H:i:s',time());
        for($j = 0; $j <= 6; $j++){
             $beginTime = mktime(0,0,0,date("m"),date("d")-$j,date("y"));
             $day_list = array();
             for($i = 0; $i < 24; $i++){
                  $data = array();
                  $time = time();//单前时间
                  $hour_list = array();
                  $hour_start = $beginTime + ($i * 3600);
                  $hour_end   = $beginTime + (($i+1) * 3600)-1;
                  //时间大于单前时间不查
                  if($hour_end > $time){  continue; }
                  $list = $this->OrderInquire($hour_start,$hour_end);//获取每个小时订单
                  if(!empty($list)){
                    $data['hour'] = $i;
                    $data['order_quantity'] = $list['sum'];
                    $data['hour_start']  = $hour_start;
                    $data['hour_end']  = $hour_end;
                    $data['order_amount']  = $list['order_amount'];
                    $data['average_order_amount']  = round($list['order_amount']/$list['sum'], 2);
                    $data['status']  = 2;
                    $insert_result = $this->insert_order_statistics($data);
                    if(!empty($day_list['order_quantity'])){
                       $day_list['order_quantity'] = $day_list['order_quantity'] + $list['sum'];
                    }else{
                       $day_list['order_quantity'] = $list['sum'];
                    }
                    if(!empty($day_list['order_amount'])){
                       $day_list['order_amount'] = $day_list['order_amount'] + $list['order_amount'];
                    }else{
                       $day_list['order_amount'] = $list['order_amount'];
                    }
                  }
             }
             //$day_list['average_order_amount'] = round($day_list['order_amount']/$day_list['order_quantity'], 2);//一天平均数

             // pr($day_list);
        }
        $this->order_html();

    }
    /**
     * 按条件查询
     * captured_amount字段为实收金额
     * [OrderInquire description]
     */
    public function OrderInquire($hour_start,$hour_end,$page = 1){
        $where = array();
        $list_sum  = array();
        //支付状态:100:待付款;101:事前风控;102:通过事前风控;103:第三方支付验证;104:第三方支付验证通过;105:事后风控;106:通过事后风控;107:人工风控;108:通过人工风控;109:支付失败(待付款);110:涉嫌欺诈;111:定性欺诈;180:部分付款;200:全部付款;300:付款处理中
        //->where('(payment_status = 104 OR payment_status = 180 OR payment_status = 200) AND create_on>='.$hour_start.' AND create_on<= '.$hour_end)
        $sum = $this->order->name(self::sales_order)->where('(payment_status = 104 OR payment_status = 180 OR payment_status = 200) AND create_on>='.$hour_start.' AND create_on<= '.$hour_end)->count();

        // return $this->order->name(self::sales_order)->getLastSql();
        if(!empty($sum)){
            $list_sum['sum'] = $sum;//没销售订单总量
            while (true) {
                $list  = array();
                $list = $this->order->name(self::sales_order)->where('(payment_status = 104 OR payment_status = 180 OR payment_status = 200) AND create_on>='.$hour_start.' AND create_on<= '.$hour_end)->page($page,100)->field('captured_amount,currency_code,exchange_rate')->select();
                if(empty($list)){
                     if(!empty($list_sum)){
                           return $list_sum;
                     }else{
                           return 0;
                     }
                }else{
                    foreach ($list as $k => $v) {
                        //如果不是美元转换成美元
                       if($v['currency_code'] != 'USD'){
                           $v['currency_code'] =  round($v['captured_amount']/$v['exchange_rate'], 2);
                       }
                       if(!empty($list_sum['order_amount'])){
                           $list_sum['order_amount'] = $list_sum['order_amount'] + $v['captured_amount'];
                       }else{
                           $list_sum['order_amount'] = $v['captured_amount'];
                       }
                       $list_sum['data'][] = $v;
                    }
                }
                $page++;
            }
        }else{
            return 0;
        }
    }
    /**
     * 把获取数据更新到order_statistics表
     * [insert_order_statistics description]
     * @return [type] [description]
     */
    public function insert_order_statistics($data){
        // date_default_timezone_set('America/New_York');
        $order_statistics = $this->order->name(self::order_statistics)->where(array('hour_start'=>$data['hour_start'],'hour_end'=>$data['hour_end']))->find();
        if(!empty($order_statistics)){
           if($order_statistics['order_amount'] != $data ['order_amount'] || $order_statistics['average_order_amount'] != $data ['average_order_amount'] || $order_statistics['order_quantity'] != $data ['order_quantity']){
               $data['edit_time']  = time();
               $result = $this->order->name(self::order_statistics)->where(array('hour_start'=>$data['hour_start'],'hour_end'=>$data['hour_end']))->update($data);
           }
        }else{
            $data['add_time']  = time();
            $result = $this->order->name(self::order_statistics)->insert($data);
        }
        if(!empty($result)){
           return 1;
        }else{
           return 0;
        }
    }
    public function order_html(){
        $html_time = '';
        $html_tr = '';
        $time_navigation = '';
        $html_navigation = '';
        $html_content = '';
        $html_sum = '';
        $list_sum = array();
        $Push_time = date("Y-m-d H:i:s",time());
        $time_navigation = '<tr valign="top" style="box-sizing: border-box"><td style="height: 6mm; box-sizing: border-box"></td>';
        $html_navigation = '<tr valign="top" style="box-sizing: border-box"><td style="height: 6mm; box-sizing: border-box"></td>';
        $html_sum = '<tr valign="top" style="box-sizing: border-box"><td style="height: 6mm; box-sizing: border-box"></td>';
        for ($z=0; $z < 6 ; $z++) {
           $time =  date("Y-m-d",strtotime('-'.$z. 'day'));//当天日期

            //算每天总和，由于时间是时时变化的，最新一天数据会与现时不一致
           $str_start = strtotime(date("Y-m-d",strtotime("-{$z} day"))." 0:0:0");
           $str_end   = strtotime(date("Y-m-d",strtotime("-{$z} day"))." 24:00:00");
           $list_sum = $this->order->name(self::order_statistics)->where('hour_start>= '.$str_start.' AND hour_end <= '.$str_end)->field('order_quantity,order_amount,average_order_amount')->select();


           $order_quantity_sum = 0;
           $order_amount_sum = 0;
           $average_order_amount = 0;
           foreach ($list_sum as $k => $v) {
                 $order_quantity_sum = $order_quantity_sum + $v['order_quantity'];
                 $order_amount_sum   = $order_amount_sum + $v['order_amount'];
                 $average_order_amount = $average_order_amount + $v['average_order_amount'];
           }
           // $order_quantity_sum = !empty($order_quantity_sum)?'$'.$order_quantity_sum:0;
           $order_amount_sum   = !empty($order_amount_sum)?'$'.$order_amount_sum:0;
           $average_order_amount = !empty($average_order_amount)?'$'.$average_order_amount:0;

           if($z == 0){
              $time_navigation .= '<td class="a96c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: center; box-sizing: border-box; background-color: cornflowerblue"><div class="a96" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: center; box-sizing: border-box">Hour</div></td>';
              $time_navigation .= '<td colspan="3" class="a30c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: center; box-sizing: border-box; background-color: cornflowerblue"><div class="a30" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: center; box-sizing: border-box">'.$time.'</div></td>';


               $html_navigation .= '<td class="a38c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a38" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">小时</div></td>';
               $html_navigation .= '<td class="a54c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a54" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">订单量</div></td><td class="a58c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a58" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">订单额</div></td><td class="a62c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a62" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">平均订单额</div></td>';

              $html_sum .= '<td class="a49c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a49" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">总计</div></td><td class="a80cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a80" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_quantity_sum.'</div></td><td class="a84cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a84" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_amount_sum.'</div></td><td class="a88cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a88" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$average_order_amount.'</div></td>';

           }else{
              $time_navigation .= '<td colspan="3" class="a30c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: center; box-sizing: border-box; background-color: cornflowerblue"><div class="a30" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: center; box-sizing: border-box">'.$time.'</div></td>';
              $html_navigation .= '<td class="a54c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a54" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">订单量</div></td><td class="a58c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a58" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">订单额</div></td><td class="a62c" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: khaki"><div class="a62" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: right; box-sizing: border-box">平均订单额</div></td>';

              $html_sum .= '<td class="a80cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a80" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_quantity_sum.'</div></td><td class="a84cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a84" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_amount_sum.'</div></td><td class="a88cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a88" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 700; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$average_order_amount.'</div></td>';

           }
        }
        $html_sum .= '</tr>';
        $time_navigation .= '</tr>';
        $html_navigation .= '</tr>';

        for ($j=0; $j < 24; $j++) {
           $html_content .= '<tr valign="top" style="box-sizing: border-box"><td style="height: 6mm; box-sizing: border-box"></td>';
           for ($i=0; $i < 6 ; $i++) {
                $beginTime = mktime(0,0,0,date("m"),date("d")-$i,date("y"));
                $hour_start = $beginTime + ($j * 3600);
                $hour_end   = $beginTime + (($j+1) * 3600)-1;
                $list = $this->order->name(self::order_statistics)->where('hour_start>= '.$hour_start.' AND hour_end <= '.$hour_end)->find();
                $order_quantity = isset($list["order_quantity"])?$list["order_quantity"]:"";
                $order_amount = isset($list["order_amount"])?$list["order_amount"]:"";
                if(!empty($order_quantity) && !empty($order_amount)){
                    $average_order_amount  = '$'.round($order_amount/$order_quantity, 2);
                }else{
                    $average_order_amount  = '';
                }

               $order_amount = !empty($order_amount)?'$'.$order_amount:'';
               if($i == 0){
                     $html_content .= '<td class="a43cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a43" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$j.'</div></td>';
                     $html_content .= '<td class="a67cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a67" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_quantity.'</div></td>';
                     $html_content .= '<td class="a67cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a67" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_amount.'</div></td>';
                     $html_content .= '<td class="a67cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a67" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$average_order_amount.'</div></td>';
               }else{
                     $html_content .= '<td class="a67cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a67" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_quantity.'</div></td>';
                     $html_content .= '<td class="a67cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a67" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$order_amount.'</div></td>';
                     $html_content .= '<td class="a67cr" style="vertical-align: top; padding: 2pt; border: 1pt solid rgb(211, 211, 211); text-align: right; box-sizing: border-box; background-color: transparent"><div class="a67" style="word-wrap: break-word; white-space: pre-wrap; width: 99%; overflow-x: hidden; font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box">'.$average_order_amount.'</div></td>';
               }

           }
            $html_content .= '</tr>';
        }
$email_template =  <<<EOT
            <html>
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=gb2312" />
            <title>订单统计</title>
            </head>
            <body>
<table cellspacing="0" cellpadding="0" style="box-sizing: border-box"><tbody style="box-sizing: border-box"><tr style="box-sizing: border-box"><td id="oReportCell" style="box-sizing: border-box">

<table cellspacing="0" cellpadding="0" style="box-sizing: border-box"><tbody style="box-sizing: border-box"><tr style="box-sizing: border-box"><td class="a19c" style="border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; box-sizing: border-box; background-color: transparent"><div class="a19" style="box-sizing: border-box; background-color: transparent">

<table cellspacing="0" cellpadding="0" border="0" cols="6" lang="zh-CN" class="r10" style="height: 28.58mm; width: 661.887mm; border-collapse: collapse; box-sizing: border-box"><tbody style="box-sizing: border-box"><tr height="0" style="box-sizing: border-box"><td style="width: 0px; box-sizing: border-box"></td><td style="width: 31.58mm; min-width: 31.58mm; box-sizing: border-box"></td><td style="width: 105.36mm; min-width: 105.36mm; box-sizing: border-box"></td><td style="width: 34.34mm; min-width: 34.34mm; box-sizing: border-box"></td><td style="width: 7.73mm; min-width: 7.73mm; box-sizing: border-box"></td><td style="width: 482.88mm; min-width: 482.88mm; box-sizing: border-box"></td></tr><tr valign="top" style="box-sizing: border-box"><td style="height: 10.16mm; width: 0mm; box-sizing: border-box"></td><td style="width: 31.58mm; min-width: 31.58mm; height: 10.16mm; box-sizing: border-box"></td><td colspan="2" style="width: 139.7mm; min-width: 139.7mm; box-sizing: border-box">

<table cellspacing="0" cellpadding="0" lang="zh-CN" style="box-sizing: border-box"><tbody style="box-sizing: border-box"><tr style="box-sizing: border-box"><td style="width: 139.7mm; min-width: 139.7mm; height: 10.16mm; word-wrap: break-word; white-space: pre-wrap; padding: 2pt; border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; font-style: normal; font-family: Verdana; font-size: 20pt; font-weight: 400; text-decoration: none; color: black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; text-align: center; box-sizing: border-box; background-color: transparent" class="a5">DX 站点每小时订单销量</td></tr></tbody></table></td><td rowspan="2" colspan="2" style="width: 490.6mm; min-width: 490.6mm; height: 10.16mm; box-sizing: border-box"></td></tr><tr style="box-sizing: border-box"><td style="height: 1.06mm; width: 0mm; box-sizing: border-box"></td><td colspan="3" style="width: 171.28mm; min-width: 171.28mm; height: 1.06mm; box-sizing: border-box"></td></tr><tr valign="top" style="box-sizing: border-box"><td style="height: 6.88mm; width: 0mm; box-sizing: border-box"></td><td style="width: 31.58mm; min-width: 31.58mm; height: 6.88mm; box-sizing: border-box"></td><td colspan="3" style="width: 147.43mm; min-width: 147.43mm; box-sizing: border-box">

<table cellspacing="0" cellpadding="0" lang="zh-CN" style="box-sizing: border-box"><tbody style="box-sizing: border-box"><tr style="box-sizing: border-box"><td style="width: 147.43mm; min-width: 147.43mm; height: 6.88mm; font-size: 0pt; word-wrap: break-word; white-space: pre-wrap; padding: 2pt; border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box; background-color: transparent" class="a10"><div style="overflow-x: hidden; width: 146.02mm; box-sizing: border-box"><div class="a9" style="text-align: right; box-sizing: border-box"><span class="a6" style="font-style: normal; font-family: Tahoma; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">站点:</span><span class="a7" style="font-style: normal; font-family: Tahoma; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">PC</span><span class="a8" style="font-style: normal; font-family: Tahoma; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box"> </span></div></div></td></tr></tbody></table></td><td style="width: 482.88mm; min-width: 482.88mm; height: 6.88mm; box-sizing: border-box"></td></tr><tr style="box-sizing: border-box"><td style="height: 2.89mm; width: 0mm; box-sizing: border-box"></td><td colspan="5" style="width: 661.89mm; min-width: 661.89mm; height: 2.89mm; box-sizing: border-box"></td></tr><tr valign="top" style="box-sizing: border-box"><td style="height: 6.88mm; width: 0mm; box-sizing: border-box"></td><td style="width: 31.58mm; min-width: 31.58mm; height: 6.88mm; box-sizing: border-box"></td><td style="width: 105.36mm; min-width: 105.36mm; box-sizing: border-box">

<table cellspacing="0" cellpadding="0" lang="zh-CN" style="box-sizing: border-box"><tbody style="box-sizing: border-box"><tr style="box-sizing: border-box"><td style="width: 105.36mm; min-width: 105.36mm; height: 6.88mm; font-size: 0pt; word-wrap: break-word; white-space: pre-wrap; padding: 2pt; border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; direction: ltr; unicode-bidi: normal; writing-mode: lr-tb; vertical-align: top; box-sizing: border-box; background-color: transparent" class="a17"><div style="overflow-x: hidden; width: 103.95mm; box-sizing: border-box"><div class="a16" style="text-align: right; box-sizing: border-box"><span class="a11" style="font-style: normal; font-family: Tahoma; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">时区：</span><span class="a12" style="font-style: normal; font-family: Tahoma; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">US(UTC-6)</span><span class="a13" style="font-style: normal; font-family: Tahoma; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">　</span><span class="a14" style="font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">执行时间：</span><span class="a15" style="font-style: normal; font-family: Arial; font-size: 10pt; font-weight: 400; text-decoration: none; color: black; box-sizing: border-box">{$Push_time}</span></div></div></td></tr></tbody></table></td><td rowspan="2" colspan="3" style="width: 524.94mm; min-width: 524.94mm; height: 6.88mm; box-sizing: border-box"></td></tr><tr style="box-sizing: border-box"><td style="height: 0.71mm; width: 0mm; box-sizing: border-box"></td><td colspan="2" style="width: 136.94mm; min-width: 136.94mm; height: 0.71mm; box-sizing: border-box"></td></tr></tbody></table></div></td></tr><tr style="box-sizing: border-box"><td class="a100xBc" style="border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; box-sizing: border-box; background-color: transparent">
<table cellspacing="0" cellpadding="0" border="0" class="a100xB" style="box-sizing: border-box; background-color: transparent"><tbody style="box-sizing: border-box">
 <tr style="box-sizing: border-box"><td style="vertical-align: top; box-sizing: border-box">
<table cellspacing="0" cellpadding="0" border="0" cols="6" lang="zh-CN" class="r10" style="width: 661.887mm; border-collapse: collapse; box-sizing: border-box"><tbody style="box-sizing: border-box">
<tr style="box-sizing: border-box"><td style="height: 4.55mm; width: 0mm; box-sizing: border-box"></td><td style="width: 6.58mm; min-width: 6.58mm; height: 4.55mm; box-sizing: border-box"></td><td style="width: 0mm; min-width: 0mm; height: 4.55mm; box-sizing: border-box"></td><td style="width: 253.86mm; min-width: 253.86mm; height: 4.55mm; box-sizing: border-box"></td><td style="width: 392.45mm; min-width: 392.45mm; height: 4.55mm; box-sizing: border-box"></td><td style="width: 9mm; min-width: 9mm; height: 4.55mm; box-sizing: border-box"></td></tr>








<tr valign="top" style="box-sizing: border-box"><td style="width: 0mm; box-sizing: border-box"></td>

<td style="width: 6.58mm; min-width: 6.58mm; box-sizing: border-box"></td><td colspan="3" style="width: 646.31mm; min-width: 646.31mm; box-sizing: border-box"><table cellspacing="0" cellpadding="0" cols="23" border="0" style="border-collapse: collapse; width: 646.31mm; min-width: 646.31mm; border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; box-sizing: border-box; background-color: transparent" class="a98"><tbody style="box-sizing: border-box">
<tr height="0" style="box-sizing: border-box"><td style="width: 0px; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td>
<td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td><td style="width: 38.76mm; min-width: 38.76mm; box-sizing: border-box"></td><td style="width: 25mm; min-width: 25mm; box-sizing: border-box"></td></tr>


{$time_navigation}
{$html_navigation}
{$html_content}
{$html_sum}
<tr style="box-sizing: border-box"><td class="a21c" style="border-image-source: initial; border-image-slice: initial; border-image-width: initial; border-image-outset: initial; border-image-repeat: initial; border: 1pt none black; box-sizing: border-box; background-color: transparent"><div class="a21" style="box-sizing: border-box; background-color: transparent">&nbsp;</div></td></tr></tbody></table></td><td width="100%" height="0" style="box-sizing: border-box"></td></tr><tr style="box-sizing: border-box"><td width="0" height="100%" style="box-sizing: border-box"></td></tr></tbody></table></div></div>
<hr style="box-sizing: border-box ;>
<table style="box-sizing: border-box"><tbody style="box-sizing: border-box"><tr style="box-sizing: border-box"><td style="box-sizing: border-box"><span style="box-sizing: border-box">数据来源:</span></td></tr><tr style="box-sizing: border-box"><td style="box-sizing: border-box"><a href="javascript:;" style="box-sizing: border-box" target="_blank" rel="noreferrer">DX.api.com定时统计</a></td></tr></tbody></table>


</body>
</html>
EOT;
      $dsys_config = $this->mongodb->name(self::sys_config)->where(['ConfigName'=>'OrderStatisticsMail'])->find();
    // echo  $this->mongodb->name(self::sys_config)->getLastSql();exit;
      if(!empty($dsys_config['ConfigValue'])){
          $Config_array = json_decode(htmlspecialchars_decode($dsys_config['ConfigValue']),true);
          foreach ($Config_array as $ke => $va) {
              $send_email_resp = Email::order_template($va, '订单数量记录', $email_template,$CustomerID=25);
              // pr($send_email_resp);
          }

      }

      /*TO DO 记录未发放优惠券成功日志zhangheng@comepro.com*/
      // $send_email_resp = Email::order_template('zhangheng@comepro.com', '订单数量记录', $email_template,$CustomerID=25);
      // pr($send_email_resp);

 return;
    }
    //放下以后要用代码
    public function Html_lingshi(){

//        <tr valign="top" style="box-sizing: border-box"><td style="height: 75.84mm; width: 0mm; box-sizing: border-box"></td><td colspan="2" style="width: 6.58mm; min-width: 6.58mm; height: 75.84mm; box-sizing: border-box"></td><td style="width: 253.86mm; min-width: 253.86mm; box-sizing: border-box"><div style="box-sizing: border-box"><img border="0" style="height: 75.84mm; width: 253.86mm; min-width: 253.86mm; border: 1pt solid rgb(211, 211, 211); box-sizing: border-box; background-color: white" class="a27" src="./?_task=mail&amp;_action=get&amp;_mbox=INBOX&amp;_uid=3442&amp;_token=FlxtCBCLqrmcYtq5k99uR0rBO5JuKF14&amp;_part=2&amp;_embed=1&amp;_mimeclass=image"></div></td><td colspan="2" style="width: 401.45mm; min-width: 401.45mm; height: 75.84mm; box-sizing: border-box"></td>
//</tr>


    }

}