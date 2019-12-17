<?php
namespace app\common\params\mall;

class ElectronicCoilParams
{
    public function Rules()
    {
        return[
            ['user_id',       'require',                       'user_id require'],
            ['coil_id',       'require',                       'coil_id require'],
        ];
    }
}