<?php
namespace app\common\params\mall;

class CreateWishParams
{
    public $UserId;
    public $ProductId;
    public $SkuId;


    public function rules()
    {
        return[
            ['UserId','require|integer','UserId不能为空|UserId必须为整型'],
            ['ProductId','require','ProductId不能为空'],
            ['SkuId','require','SkuId不能为空']
        ];
    }
}