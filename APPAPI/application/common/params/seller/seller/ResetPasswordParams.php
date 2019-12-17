<?php
namespace app\common\params\seller\seller;

class ResetPasswordParams
{
//    public $old_pwd;
    public $new_pwd;

    public function rules()
    {
        return[
            ['user_id','require','user_id不能为空'],
//            ['old_pwd','require','原密码不能为空'],
            ['new_pwd','require','新密码不能为空']
        ];
    }
}