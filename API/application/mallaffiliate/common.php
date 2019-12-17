<?php
use think\Db;

function getCustomerService(){
    $admin_user_where['status'] = 1;
    $admin_user_where['group_id'] = ['in','9,12'];//客服
    $admin_user = (Db::connect("db_admin")->name("user")->where($admin_user_where)->field("id,username,group_id,status,add_time")->select());
    return $admin_user;
}

/*
 * 替换掉文本内容
 * $replace_text 要替换的文本
 * $replace_data array 要替换的数据
 * */
function replaceContent($replace_text,$replace_data){
    //邮件标题替换
    foreach ($replace_data as $k => $v)
    {
        if(!is_array($v)){
            $replace_text = str_replace('{'.$k.'}', $v, $replace_text);
        }
    }
    return $replace_text;
}