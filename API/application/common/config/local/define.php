<?php
//mall api接口地址
defined('MALL_API') or define('MALL_API','http://api.localhost.com/');
//mall api接口地址
defined('CIC_API') or define('CIC_API','http://api.localhost.com/');
//物流跟踪号是否可用
defined('Tracking_Information_price') or define('Tracking_Information_price',2);
//产品图片地址
defined('IMG_DXCDN') or define('IMG_DXCDN', '//photo.dxinterns.com');
//mall商城路径
defined('MALLDOMAIN') or define('MALLDOMAIN', '//mall.dxinterns.com');
//my站路径
defined('MYDXINTERNS') or define('MYDXINTERNS', '//home.dxinterns.com');
//api接口地址--NOCNOC
defined('API_NOC_URL') or define('API_NOC_URL','https://sandbox.nocnocgroup.com/api/order/dx/quote');
//api接口地址--NOCNOC——KEY
defined('API_NOC_KEY') or define('API_NOC_KEY','$2y$10$tE5CIBgUp0VuKWEcoN.xaOObVHw/R3XS2XVNlooHFAGvdXF4.MNbW');


//payment channel
defined('TRANSACTION_CHANNEL_PAYPAL') 		or define('TRANSACTION_CHANNEL_PAYPAL','paypal');
defined('TRANSACTION_CHANNEL_EGP') 			or define('TRANSACTION_CHANNEL_EGP','egp');
defined('TRANSACTION_CHANNEL_IDEAL') 		    or define('TRANSACTION_CHANNEL_IDEAL','ideal');
defined('TRANSACTION_CHANNEL_PAGSMILE') 		or define('TRANSACTION_CHANNEL_PAGSMILE','pagsmile');
defined('TRANSACTION_CHANNEL_ASTROPAY') 		or define('TRANSACTION_CHANNEL_ASTROPAY','astropay');
defined('TRANSACTION_CHANNEL_MERCADOPAGO') 	or define('TRANSACTION_CHANNEL_MERCADOPAGO','mercadopago');
defined('TRANSACTION_CHANNEL_ASIABILL') 		or define('TRANSACTION_CHANNEL_ASIABILL','asiabill');
defined('TRANSACTION_CHANNEL_SC') 			or define('TRANSACTION_CHANNEL_SC','sc');
defined('TRANSACTION_CHANNEL_PAYSSION') 			or define('TRANSACTION_CHANNEL_PAYSSION','payssion');
defined('TRANSACTION_CHANNEL_DLOCAL') 			or define('TRANSACTION_CHANNEL_DLOCAL','dlocal');

//PAYMENT TYPE
defined('TRANSACTION_TYPE_PAYPAL_PAYPAL') or define('TRANSACTION_TYPE_PAYPAL_PAYPAL','paypal');

defined('TRANSACTION_TYPE_EGP_CREDITCARD') or define('TRANSACTION_TYPE_EGP_CREDITCARD','creditcard');
defined('TRANSACTION_TYPE_EGP_CREDITCARDTOKEN') or define('TRANSACTION_TYPE_EGP_CREDITCARDTOKEN','creditcard-token');

defined('TRANSACTION_TYPE_IDEAL_IDEAL') or define('TRANSACTION_TYPE_IDEAL_IDEAL','ideal');

defined('TRANSACTION_TYPE_PAGSMILE_BOLETO') or define('TRANSACTION_TYPE_PAGSMILE_BOLETO','boleto');
defined('TRANSACTION_TYPE_PAGSMILE_LOTTERY') or define('TRANSACTION_TYPE_PAGSMILE_LOTTERY','lottery');
defined('TRANSACTION_TYPE_PAGSMILE_CREDITCARD') or define('TRANSACTION_TYPE_PAGSMILE_CREDITCARD','creditcard');
defined('TRANSACTION_TYPE_PAGSMILE_FLASHPAY') or define('TRANSACTION_TYPE_PAGSMILE_FLASHPAY','flashpay');

defined('TRANSACTION_TYPE_ASTROPAY_ONLINE') or define('TRANSACTION_TYPE_ASTROPAY_ONLINE','AstropayTransfer');
defined('TRANSACTION_TYPE_ASTROPAY_CASH') or define('TRANSACTION_TYPE_ASTROPAY_CASH','AstropayBoleto');
defined('TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD') or define('TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD','AstropayCreditCard');

defined('TRANSACTION_TYPE_MERCADOPAGO_CREDITCARD') or define('TRANSACTION_TYPE_MERCADOPAGO_CREDITCARD','creditcard');

defined('TRANSACTION_TYPE_ASIABILL_CREDITCARD') or define('TRANSACTION_TYPE_ASIABILL_CREDITCARD','creditcard');
defined('TRANSACTION_TYPE_ASIABILL_CREDITCARDTOKEN') or define('TRANSACTION_TYPE_ASIABILL_CREDITCARDTOKEN','creditcard-token');

defined('TRANSACTION_TYPE_SC_SC') or define('TRANSACTION_TYPE_SC_SC','sc');

defined('TRANSACTION_TYPE_PAYSSION_YAMONEY') or define('TRANSACTION_TYPE_PAYSSION_YAMONEY','yamoney');
defined('TRANSACTION_TYPE_PAYSSION_YAMONEYAC') or define('TRANSACTION_TYPE_PAYSSION_YAMONEYAC','yamoneyac');
defined('TRANSACTION_TYPE_PAYSSION_YAMONEYGP') or define('TRANSACTION_TYPE_PAYSSION_YAMONEYGP','yamoneygp');

defined('TRANSACTION_TYPE_DLOCAL_ONLINE') or define('TRANSACTION_TYPE_DLOCAL_ONLINE','dlocalTransfer');
defined('TRANSACTION_TYPE_DLOCAL_CASH') or define('TRANSACTION_TYPE_DLOCAL_CASH','dlocalBoleto');
defined('TRANSACTION_TYPE_DLOCAL_CREDIT_CARD') or define('TRANSACTION_TYPE_DLOCAL_CREDIT_CARD','dlocalCreditCard');

//定义风控黑白名单标志
defined('RISK_BLACK_FLAG') OR define('RISK_BLACK_FLAG',1);
defined('RISK_WHITE_FLAG') OR define('RISK_WHITE_FLAG',2);
//图片CDN路径配置
defined('IMG_DXCDN_URL') OR define('IMG_DXCDN_URL','https://img.dxcdn.com/');
//mall商城完整路径
defined('MALL_DOMAIN_URL') or define('MALL_DOMAIN_URL', 'http://mall.localhost.com/');