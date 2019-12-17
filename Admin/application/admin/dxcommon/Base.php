<?php
namespace app\admin\dxcommon;

use app\admin\model\AdvertisementManage;
use think\Controller;
use think\Db;
use think\Log;
use app\admin\dxcommon\Common;

/**
 * 通用基础处理类
 * @author tinghu.liu
 * @date 2018-05-25
 * @package app\admin\dxCommon
 */
class Base extends Controller
{


    /**
     * 根据开始结束时间获取起价你的天数
     * @param $begin_time_stamp 开始时间【时间戳】
     * @param $end_time_stamp 结束时间【时间戳】
     * @return array|string
     */
    public static function getTimeList($begin_time_stamp, $end_time_stamp){
        $rtn = [];
        if(!is_numeric($begin_time_stamp) || !is_numeric($end_time_stamp) || ($end_time_stamp <= $begin_time_stamp)){
            return $rtn;
        }
        for($i=$begin_time_stamp; $i<=$end_time_stamp; $i+=(24*3600)){
            $rtn["time_stamp_list"][]=$i;
            $rtn["day_list"][]=date("Y-m-d",$i);
        }
        return $rtn;
    }

    /**
     * 根据开始结束时间获取起价你的天数
     * @param $begin_time_stamp
     * @param $end_time_stamp
     * @return array
     */
    public static function getActivityFlashDealsData($begin_time_stamp, $end_time_stamp){
        $rtn = [];
        $activity_play_number_arr = [];
        $base_api = new BaseApi();
        //获取活动场次配置
//        $activity_play_number_api = $base_api->getSysCofig(['ConfigName'=>'ActivityPlayNumber']);
        $activity_play_number_api = Db::connect("db_mongo")->name('dx_sys_config')->where(['ConfigName'=>'ActivityPlayNumber'])->find();
        $activity_play_number_db = json_decode($activity_play_number_api["ConfigValue"],true);
        if (
            !empty($activity_play_number_api)
            && isset($activity_play_number_db['key'])
        ){
            //统一将分号转换为英文格式
            $activity_play_number = str_replace('；', ';', $activity_play_number_db['key']);
            //活动场次数组
            $activity_play_number_arr = explode(';', $activity_play_number);
        }
        //根据活动开始、结束时间获取区间天数据
        //$begin_time_stamp = $begin_time_stamp-(8 * 3600);
        $time_list = self::getTimeList($begin_time_stamp, $end_time_stamp);
        //var_dump($activity_play_number_arr);
//die();
        //拼装flash deals数据
        if (!empty($time_list)){
            if (!empty($time_list) && isset($time_list['day_list'])){
                foreach ($time_list['day_list'] as $time){
                    foreach ($activity_play_number_arr as $key=>$play_numbe){
                        if($play_numbe != ''){
                            $tem = [];
                            $tem['day'] = $time;
                            $tem['name'] = date('m月d日', strtotime($time)).'场第'.($key+1).'期';
                            //var_dump($play_numbe);
                            //$play_numbe：0-12， 从0点开始，持续12小时
                            $play_numbe_arr = explode('-', $play_numbe);
                            //var_dump($play_numbe_arr);
                            $start_time = $play_numbe_arr[0];
                            $time_long = $play_numbe_arr[1];
                            $end_time = $start_time+$time_long;
                            //flash deals活动开始时间
                            $tem['start_dt'] = date('Y-m-d H:i:s',strtotime("+$start_time hour",strtotime($time)));
                            //flash deals结束开始时间
                            $tem['end_dt'] = date('Y-m-d H:i:s',strtotime("+$end_time hour",strtotime($time)));
                            $rtn[] = $tem;
                            //var_dump($tem);
                        }
                    }
                }
            }
        }
        //var_dump($rtn);
        //die();
        return $rtn;
    }


    /**
     * 根据币种简码获取币种符号
     * @param $currency_code 币种简码
     * @return string
     */
    public static function getCurrencyCodeStr($currency_code){
        $currency_code_str = '';
        $base_api = new BaseApi();
        if ($currency_code != 'USD'){
            $currency_info_api = $base_api->getCurrencyList();
            $currency_info = isset($currency_info_api['data'])&&!empty($currency_info_api['data'])?$currency_info_api['data']:[];
            foreach ($currency_info as $c_info){
                if ($c_info['Name'] == $currency_code){
                    $currency_code_str = $c_info['Code'];
                    break;
                }
            }
        }else{
            $currency_code_str = '$';
        }
        return $currency_code_str;
    }

}
