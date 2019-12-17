<?php
namespace app\cic\validate;
use think\Validate;

class PaymentPassword extends Validate{
	 protected $rule = [
         ['PaymentPassword',       'require|length:6,50',    'Payment password can not be empty|Incorrect payment password format'],
        //['PaymentPassword',       ['regex'=>'/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[$@$!%*?&._-])[A-Za-z\d$@$!%*?&._-]{6,20}/','require'],    'Incorrect payment password format|Payment password can not be empty'],
    ];
    protected $scene = [
        'save'   =>  ['PaymentPassword'],
    ];
}