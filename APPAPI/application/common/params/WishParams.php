<?php
namespace app\common\params;

class WishParams
{
    /**
     * Product数据校验
     * @return array
     */
    public function isWishRules()
    {
        return[
            ['SPU','require|number','Productid Must be Required | Productid Must be a number'],
        ];
    }

    /**
     * @return array
     */
    public function addWishRules()
    {
        return[
            ['SPU','require|number','Productid Must be Required | Productid Must be a number'],
//            ['categoryID','require|number','categoryID Must be Required | categoryID Must be a number'],
        ];
    }

}