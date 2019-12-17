<?php
namespace app\admin\validate;
use think\Validate;

class Feedback extends Validate{
    protected $rule = [
        ['question_type',     'number|length:1,10',       'question_type Must be a number|question_type Invalid parameter length'],
        ['customer_id',     'require|number|length:1,60',       'customer_id can not empty|customer_id Must be a number|customer_id Invalid parameter length'],
        ['customer_name',     'require|length:1,150',        "customer_name can not empty|customer_name Length must be between 1-150"],
        ['order_number',     'require|number|length:1,20',       'Order number can not empty|Order number Must be a number|Order number Invalid parameter length'],
        ['description',     'require|length:1,200',                    'Message can not empty|Message Invalid parameter length'],
        ['is_reply',     'length:1,3',                    'is_reply Invalid parameter length'],
        ['feedback_id',     'require|number|length:1,60',       'feedback_id can not empty|feedback_id Must be a number|feedback_id Invalid parameter length'],
    ];
    protected $scene = [
        'addFeedback'   =>  ['customer_id',"customer_name","order_number","description"],
        'readFeedbackReply' =>['customer_id'],
        'getList' =>['question_type','customer_id'],
        'getFeedbackCountByCustomerId'=>['customer_id'],
        'getFeedbackInfo'=>['customer_id','feedback_id']
    ];
}