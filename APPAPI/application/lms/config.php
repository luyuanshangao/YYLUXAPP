<?php

/**
 * 应用配置
 * @author wang
 * @date 2018-04-26
 */
return [
    /**
     * 调用API地址
     */
    'api_base_url'=>'http://api.dxinterns.com/',
    'lms_base_url'=>'https://lms-erp.tradeglobals.com/',
   /**
   * [LMS description]
   * 同步LMS系统渠道数据数据接口
   * author: Wang
   * AddTime:2018-04-26
   */
  'logistics'=>[
        'url'=>'index.php?a=DxLogisticsUpdate&f=UpdateSeller',//列表
        // 'url'=>'http://lms-erp.dxnew.com/index.php?a=LogisticsSync&f=index',//列表
        // 'access_token' =>'19cee9b7e54c5f83cdf1bdf6d4fe0ab3',
   ],

   //更新数据到seller
   'LogisticsUpdateSeller'=>[
        // 'url'=>'index.php?a=DxLogisticsUpdate&f=UpdateSeller',//列表
        'url'=>'lms/logistics/LogisticsUpdateSeller',//列表
        // 'url'=>'http://lms-erp.dxnew.com/index.php?a=LogisticsSync&f=index',//列表
        // 'access_token' =>'19cee9b7e54c5f83cdf1bdf6d4fe0ab3',
   ],
];
