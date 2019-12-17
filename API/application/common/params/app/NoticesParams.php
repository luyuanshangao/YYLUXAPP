<?php
namespace app\common\params\app;

class NoticesParams
{
    /**
     * getIsNotRead数据校验
     * @return array
     */
    public function getIsNotReadRules()
    {
        return[
            ['CustomerID','require']
        ];
    }

    public function noticeCustomerSave()
    {
        return[
            ['CustomerID','require'],
            ['NoticeID','require']
        ];
    }

}