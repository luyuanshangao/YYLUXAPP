<?php
namespace app\common\params\mall;

class ActivityParams
{
    /**
     * FlashData数据校验
     * @return array
     */
    public function getFlashDataRules()
    {
        return[
            ['lang','require','lang require'],
        ];
    }
}