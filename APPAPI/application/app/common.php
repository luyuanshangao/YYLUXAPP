<?php
/**
 * 时间校验[订单]，开始&结束时间
 * 规则：1、开始时间不能低于1970年01月01日08时00分00秒；2、不能低于现在；3、相隔时间不能大于30天
 * @param $_create_on_start
 * @param $_create_on_end
 * @return array
 */
function time_verify_real($_create_on_start, $_create_on_end){
    if (
        !empty($_create_on_start)
        && !empty($_create_on_end)
    ){
        if (
            time_verify_for_order($_create_on_start)
            && time_verify_for_order($_create_on_end)
        ){
            //相隔时间大于10年,过滤掉
            if (
                (strtotime($_create_on_end) < strtotime($_create_on_start))
                || (strtotime($_create_on_end) - strtotime($_create_on_start)) > 10*12*30*24*60*60
            ){
                $_create_on_start = '';
                $_create_on_end = '';
            }
        }else{
            //时间格式校验不通过，则过滤掉时间搜索条件
            $_create_on_start = '';
            $_create_on_end = '';
        }
    }else{
        $_create_on_start = '';
        $_create_on_end = '';
    }
    return ['create_on_start'=>$_create_on_start, 'create_on_end'=>$_create_on_end];
}

/**
 * id转换为数组键
 */
function id_arr_key ($arr, $key)
{
    $data = array();
    foreach ($arr as $k => $v) {
        $data[$v[$key]] = $v;
    }
    return $data;
}