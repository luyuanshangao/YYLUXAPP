<?php
namespace app\common\params\mall;

class BaseConfigParams
{
    /**
     * getProduct数据校验
     * @return array
     */
    public function getSearchWordRules()
    {
        return[
            ['lang','require','lang require'],
        ];
    }

    public function getTopSellerRules()
    {
        return[
            ['lang','require','lang require'],
        ];
    }

    public function getRule(){
        return[
            ['lang','require','lang require'],
            ['key','require','key require'],
        ];
    }

}