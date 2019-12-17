<?php
namespace app\reviews\validate;
use think\Validate;

class Reviews extends Validate{
    /**
     * 校验规则
     * @var array
     */
    protected $rule = [
        ['customer_id',     'number|length:1,20',        "customer_id Must be a number|customer_id Invalid parameter length"],
        ['review_id',     'number|length:1,20',        "review_id Must be a number|review_id Invalid parameter length"],
        ['page_size',     'number|length:0,10',    'page_size Must be a number|page_size Invalid parameter length'],
        ['page',     'number|length:0,10',"page Must be a number|page Length must be between 1-10"],
        ['path',     'length:1,150',"path Length must be between 1-150"],
        ['content',     'length:1,1500',"content Length must be between 1-1500"],
        ['month',     'number|length:1,10',                    'month Must be a number|month Invalid parameter length'],
        ['customer_name',     'length:1,30',        "customer_name Invalid parameter length"],
        ['store_id',     'number|length:1,10',                    'store_id Must be a number|store_id Invalid parameter length'],
        ['order_id',     'number|length:1,10',                    'order_id Must be a number|order_id Invalid parameter length'],
        ['price_rating',     'between:1,5',                    'price_rating between 1-5'],
        ['ease_of_use_rating',     'between:1,5',                    'ease_of_use_rating between 1-5'],
        ['build_quality_rating',     'between:1,5',                    'build_quality_rating between 1-5'],
        ['usefulness_rating',     'between:1,5',                    'usefulness_rating between 1-5'],
        ['overall_rating',     'between:1,5',                    'overall_rating between 1-5'],
        ['static_images',     'number|length:0,10',                    'static_images Must be a number|Province Invalid parameter length'],
        ['static_videos',     'number|length:0,10',                    'static_videos Must be a number|create_on_start Invalid parameter length'],
        ['store_name',     'length:0,50',                    'store_name Invalid parameter length'],
        ['product_id',     'number|length:0,15',                    'product_id Must be a number|product_id Invalid parameter length'],
        ['sku_id',     'number|length:0,15',                    'sku_id Must be a number|sku_id Invalid parameter length'],
        ['approved',     'number|length:0,15',                    'approved Must be a number|approved Invalid parameter length'],
        ['approval_staff',     'length:1,30',                   'approval_staff Invalid parameter length'],
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'addReviews'   =>  ['customer_id',"content","customer_name","store_id","order_id","price_rating","ease_of_use_rating","build_quality_rating","usefulness_rating","overall_rating","static_images","static_videos","product_id","sku_id"],
        'addReplyReviews' => ['review_id','store_id','store_name','content'],
        'addReviewsRro' => ['review_id','customer_id','customer_name'],
        'updateReviewStatus' => ['approved','approval_staff'],
        'addProductReviews' =>  ['customer_id',"content","price_rating","ease_of_use_rating","build_quality_rating","usefulness_rating","overall_rating","product_id","sku_id"],
        ];
}