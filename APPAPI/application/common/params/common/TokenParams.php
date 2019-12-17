<?php
namespace app\common\params\common;

class TokenParams
{
    /**
     * FlashData数据校验
     * @return array
     */
    public function TokenRules()
    {
        return[
            ['account','require','account require'],
            ['password','require','password require'],
        ];
    }
}