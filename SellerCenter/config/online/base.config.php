<?php

/**
 * 基础配置
 * @author tinghu.liu
 * @date 2018-06-04
 */
return [

    //ajax返回配置
    'ajax_return_data'=>[
        'code'=>-1,
        'msg'=>''
    ],

    /**
     * 基础上传目录
     */
    'base_upload_dir'=>ROOT_PATH . 'public' . DS . 'uploads' . DS,

    /**
     * 产品图片上传目录
     */
    'product_pic_upload_dir'=>ROOT_PATH . 'public' . DS . 'uploads' . DS . 'product',

    /**
     * 订单留言文件上传地址
     */
    'order_message_upload_dir'=>ROOT_PATH . 'public' . DS . 'uploads' . DS . 'ordermessageimages',

    /**
     * 延长产品有效期天数
     */
    'extend_day' => 15,

    /**
     * 订单状态
     */
    'order_status_data' => [
        ['code'=>100, 'name'=>'等待付款'],
        ['code'=>120, 'name'=>'付款确认中'],
        /*['code'=>300, 'name'=>'付款处理中'],*/
        ['code'=>200, 'name'=>'付款完成'],
        ['code'=>400, 'name'=>'待发货'],
        ['code'=>500, 'name'=>'部分发货'],
        ['code'=>600, 'name'=>'已发货'],
        ['code'=>700, 'name'=>'待收货'],
        ['code'=>800, 'name'=>'妥投'],
        ['code'=>900, 'name'=>'已完成'],
        ['code'=>1000, 'name'=>'待评价'],
        ['code'=>1100, 'name'=>'已评价'],
        ['code'=>1200, 'name'=>'待追评'],
        ['code'=>1300, 'name'=>'已追评'],
        ['code'=>1400, 'name'=>'订单取消'],
        ['code'=>1500, 'name'=>'等待'],
        ['code'=>1600, 'name'=>'索赔'],
        ['code'=>1700, 'name'=>'纠纷中'],
        ['code'=>1800, 'name'=>'争端订单'],
        ['code'=>1900, 'name'=>'已关闭'],
    ],

    /**
     * 文章类别ID配置
     */
    'article_cate_id'=>[
        //最新公告
        'latest_announcement'=>15,
        //新手必读
        'novice_must_read'=>16,
    ],

    /**
     * 队列每次处理条数
     */
    'queue_handle_limit_number'=>100,

];
