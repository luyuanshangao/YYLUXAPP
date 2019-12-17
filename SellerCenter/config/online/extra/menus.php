<?php
use think\Url;
/**
 * 菜单信息配置【注：*********** 所有的flag值要保证唯一性 ***********】
 * @author tinghu.liu
 * @date 2018-06-04
 */
return [
    'menu_config'=>[
        [
            'parent_menu'=>[//一级菜单
                'name'=>'我的账号', /* 一级菜单名称 */
                'url'=>Url::build('index/Index/index'),/* 一级菜单地址 */
                'flag'=>'my-account-top'/* 一级菜单标识（唯一标识，☆不能重复），下同*/
            ],
            'child_menu'=>[//二级菜单
                /*[
                    'name'=>'首页',//二级菜单名称
                    'url'=>Url::build('index/Index/index'),//二级菜单地址
                    'flag'=>'my-index'//二级菜单标识（唯一标识，☆不能重复，下同）
                ],*/
                /*[
                    'name'=>'银行信息管理',
                    'url'=>url(''),
                    'flag'=>''
                ],
                [
                    'name'=>'账户信息管理',
                    'url'=>url('Index/acctManage'),
                    'flag'=>'acct-manage'
                ],*/
                [
                    'parent_two_menu'=>[ //二级菜单
                        'name'=>'我的账号',
                        'url'=>'',
                        'flag'=>'my-account' /* 二级菜单标识（唯一标识，☆不能重复），下同*/
                    ],
                    'child_menu'=>[ //子级菜单
                        [
                            'name'=>'首页',//三级菜单名称
                            'url'=>Url::build('index/Index/index'),//二级菜单地址
                            'flag'=>'my-index'//三级菜单标识（唯一标识，☆不能重复），下同
                        ],
                    ]
                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'产品管理',
                'url'=>Url::build('index/Product/index'),
                'flag'=>'product-management-top'
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'产品管理',
                        'url'=>'',
                        'flag'=>'product-management'
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'产品管理',
                            'url'=>Url::build('index/Product/index'),
                            'flag'=>'pro-manage'
                        ],
                        [
                            'name'=>'上架产品',
                            'url'=>Url::build('index/Product/category'),
                            'flag'=>'shelf-product'
                        ],
                        [
                            'name'=>'产品分组管理',
                            'url'=>Url::build('index/Product/productGroup'),
                            'flag'=>'shelf-productGroup'
                        ],
                        [
                            'name'=>'产品信息模块管理',
                            'url'=>Url::build(''),
                            'flag'=>''
                        ],
                        [
                            'name'=>'运费模板管理',
                            'url'=>Url::build('index/Product/shippingTemplate'),
                            'flag'=>'shipping-templates'
                        ],
                        [
                            'name'=>'服务模块管理',
                            'url'=>Url::build(''),
                            'flag'=>''
                        ],
                    ],
                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'订单管理',
                'url'=>Url::build('index/Orders/all'),
                'flag'=>'order-top'
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'订单管理',
                        'url'=>'',
                        'flag'=>'order'
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'所有订单',
                            'url'=>Url::build('index/Orders/all'),
                            'flag'=>'all'
                        ],
                        [
                            'name'=>'评价管理',
                            'url'=>Url::build('index/Orders/evaluate'),
                            'flag'=>'evaluate'
                        ],
//                        [
//                            'name'=>'Coupon',
//                            'url'=>Url::build('index/Coupon/index'),
//                            'flag'=>'coupon-index'
//                        ],
                        [
                            'name'=>'退款&纠纷',
                            'url'=>Url::build('index/Orders/refundAll'),
                            'flag'=>'orders-refund-all'
                        ],
                        [
                            'name'=>'联盟营销订单',
                            'url'=>Url::build('index/AffiliateManage/affiliateOrderList'),
                            'flag'=>'orders-affiliate-list'
                        ],
                    ],
                ],
//                [
//                    'parent_two_menu'=>[
//                        'name'=>'其他',
//                        'url'=>'',
//                        'flag'=>'order-other'
//                    ],
//                    'child_menu'=>[
//                        [
//                            'name'=>'Coupon',
//                            'url'=>Url::build('index/Coupon/index'),
//                            'flag'=>'coupon-index'
//                        ],
//                    ],
//                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'消息中心',
                'url'=>Url::build('index/ProductQa/index'),
                'flag'=>'message-center-top'
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'消息中心',
                        'url'=>'',
                        'flag'=>'message-center'
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'产品Q&A',
                            'url'=>Url::build('index/ProductQa/index'),
                            'flag'=>'product-qa-index'
                        ],
                        [
                            'name'=>'Wholesale Inquiry',
                            'url'=>Url::build('index/Message/wholesaleInquiry'),
                            'flag'=>'message-center-wholesaleinquiry'
                        ],
                        [
                            'name'=>'留言',
                            'url'=>Url::build('index/Message/index'),
                            'flag'=>'message-center-leave-message'
                        ],
                        [
                            'name'=>'站内信',
                            'url'=>Url::build('index/Message/internalLetter'),
                            'flag'=>'message-center-sitemail'
                        ]
                    ],
                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'账户管理',
                'url'=>Url::build('index/AccountManage/authorization'),
                'flag'=>'account-manage-top'
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'账户管理',
                        'url'=>'',
                        'flag'=>'account-manage'
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'认证详情',//二级菜单名称
                            'url'=>Url::build('index/AccountManage/authorization'),//二级菜单地址
                            'flag'=>'account-manage-auth-detail'//二级菜单标识（唯一标识，☆不能重复）
                        ],
                        [
                            'name'=>'银行信息管理',
                            'url'=>Url::build('index/AccountManage/bankInfoManage'),
                            'flag'=>'account-manage-bank-manage'
                        ],
                        [
                            'name'=>'账户信息管理',
                            'url'=>Url::build('index/AccountManage/acctManage'),
                            'flag'=>'account-manage-acct-manage'
                        ],
                    ],
                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'营销推广',
                'url'=>Url::build('index/MarketingPromotion/signUpActivity'),
                'flag'=>'marketing-promotion-top'
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'营销推广',
                        'url'=>'',
                        'flag'=>'marketing-promotion'
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'报名活动',
                            'url'=>Url::build('index/MarketingPromotion/signUpActivity'),
                            'flag'=>'sign-up-activity'
                        ],
                        [
                            'name'=>'Coupon',
                            'url'=>Url::build('index/Coupon/index'),
                            'flag'=>'coupon-index'
                        ],
//                        [
//                            'name'=>'联盟营销',
//                            'url'=>Url::build('index/MarketingPromotion/affiliate'),
//                            'flag'=>'sign-up-affiliate'
//                        ]
//                        ,
//                        [
//                            'name'=>'加入联盟营销',
//                            'url'=>Url::build('index/AffiliateManage/addAffiliate'),
//                            'flag'=>'sign-up-addAffiliate'
//                        ]
//                        ,
//                        [
//                            'name'=>'分类佣金设置',
//                            'url'=>Url::build('index/AffiliateManage/setAffiliateClass'),
//                            'flag'=>'sign-up-setAffiliateClass'
//                        ],
//                        [
//                            'name'=>'添加主推产品',
//                            'url'=>Url::build('index/AffiliateManage/addMainProductList'),
//                            'flag'=>'add-myMainProductList'
//                        ],
//                        [
//                            'name'=>'我的主推产品',
//                            'url'=>Url::build('index/AffiliateManage/myMainProductList'),
//                            'flag'=>'sign-up-myMainProductList'
//                        ]
                    ],
                ],
                [
                    'parent_two_menu'=>[
                        'name'=>'联盟营销',
                        'url'=>'',
                        'flag'=>'alliance-marketing'
                    ],
                    'child_menu'=>[
//                        [
//                            'name'=>'报名活动',
//                            'url'=>Url::build('index/MarketingPromotion/signUpActivity'),
//                            'flag'=>'sign-up-activity'
//                        ],
//                        [
//                            'name'=>'联盟营销',
//                            'url'=>Url::build('index/MarketingPromotion/affiliate'),
//                            'flag'=>'sign-up-affiliate'
//                        ]
//                        ,
                        [
                            'name'=>'加入联盟营销',
                            'url'=>Url::build('index/AffiliateManage/addAffiliate'),
                            'flag'=>'sign-up-addAffiliate'
                        ]
                        ,
                        [
                            'name'=>'分类佣金设置',
                            'url'=>Url::build('index/AffiliateManage/setAffiliateClass'),
                            'flag'=>'sign-up-setAffiliateClass'
                        ],
                        [
                            'name'=>'添加主推产品',
                            'url'=>Url::build('index/AffiliateManage/addMainProductList'),
                            'flag'=>'add-myMainProductList'
                        ],
                        [
                            'name'=>'我的主推产品',
                            'url'=>Url::build('index/AffiliateManage/myMainProductList'),
                            'flag'=>'sign-up-myMainProductList'
                        ]
                    ],
                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'经营数据',
                'url'=>Url::build(''),
                'flag'=>''
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'经营数据',
                        'url'=>'',
                        'flag'=>''
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'',
                            'url'=>Url::build(''),
                            'flag'=>''
                        ]
                    ],
                ],
            ],
        ],
        [
            'parent_menu'=>[
                'name'=>'违规管理',
                'url'=>Url::build(''),
                'flag'=>''
            ],
            'child_menu'=>[
                [
                    'parent_two_menu'=>[
                        'name'=>'违规管理',
                        'url'=>'',
                        'flag'=>''
                    ],
                    'child_menu'=>[
                        [
                            'name'=>'',
                            'url'=>Url::build(''),
                            'flag'=>''
                        ]
                    ],
                ],
            ],
        ],
    ],

];
