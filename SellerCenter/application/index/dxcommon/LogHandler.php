<?php
namespace app\index\dxcommon;

use app\index\model\LogModel;
use think\Log;

/**
 * 日志记录统一处理类
 * @author tinghu.liu
 * @date 2018-10-19
 * @package app\index\dxcommon
 */
class LogHandler
{
    /**
     * 添加seller操作日志记录
     * @param $controller_name
     * @param $function_name
     * @param $remark
     * @param $operation_id
     * @param $operation_name
     * @param string $log_type 日志类型：error，notice，info
     * @param string $request_url
     * @param string $request_params
     * @param string $request_result
     * @param string $operation_before
     * @param string $operation_after
     */
    public static function opRecord($controller_name, $function_name,$remark, $operation_id,$operation_name, $log_type='info', $request_url='', $request_params='', $request_result='',$operation_before='', $operation_after=''){
        $data['log_type'] = $log_type;
        $data['controller_name'] = $controller_name;
        $data['function_name'] = $function_name;
        $data['operation_before'] = $operation_before;
        $data['operation_after'] = $operation_after;
        $data['request_url'] = $request_url;
        $data['request_params'] = $request_params;
        $data['request_result'] = $request_result;
        $data['remark'] = $remark;
        $data['operation_id'] = $operation_id;
        $data['operation_name'] = $operation_name;
        $data['addtime'] = time();
        try{
            $log_model = new LogModel();
            $log_model->insertOperationRecord($data);
        }catch (\Exception $e){
            Log::record('opRecord_exception_err,data:'.json_encode($data));
            Log::record('opRecord_exception_err,info:'.$e->getMessage());
        }
    }

}
