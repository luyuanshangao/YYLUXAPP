<?php
namespace app\common\params\mall;

class AdvertisingParams
{
    /**
     * FlashData数据校验
     * @return array
     */
    public function getRules()
    {
        return[
            ['key','require','key require'],
        ];
    }

}