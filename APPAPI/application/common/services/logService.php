<?php
namespace app\common\services;

use think\Exception;
use think\exception\HttpException;

class logService extends Api{

    //日志格式
    /**
     * 写入日志信息
     * @param  string $table_name  表名
     * @param  string  $type   日志类型
     * @param  string  $method   请求类名路径
     * @param  string  $function   请求方法名称
     * @param  string  $params   请求参数
     * @param  string  $url   请求路径
     * @param  string  $data   请求结果
     * @param int $customer_id 用户ID
     * @param string $order_master_number 主订单号
     * @return bool
     */
    public static function write($table_name,$type = 'log',$method,$function,$params = null,$url = null,$data = null, $customer_id=0, $order_master_number='')
    {
        try{
            if(!$table_name)
                return false;
            //表名
            $log['table'] = $table_name;
            //日志级别
            $log['level'] = $type;
            // 函数名称
            $log['functionName'] = $function;
            //路径
            $log['method'] = $method;
            //请求路径
            $log['url'] = $url;
            if(!empty($params)){
                if(is_array($params)){
                    $params = json_encode($params);
                }
            }
            //请求参数
            $log['params'] = $params;
            if(!empty($data)){
                if(is_array($data)){
                    $data = json_encode($data);
                }
            }
            //请求结果
            $log['result'] = $data;
            //写日志时间
            $log['timestamp'] =  date('Y-m-d H:i:s',time());
            //用户ID
            $log['customerId'] =  $customer_id;
            //主订单号
            if (!empty($order_master_number)) $log['orderMasterNumber'] = $order_master_number;
            doLogCurl(MALL_API.'/log/index/operationLog',$log);
            return true;
        }catch (Exception $e){
            return true;
        }

    }

}