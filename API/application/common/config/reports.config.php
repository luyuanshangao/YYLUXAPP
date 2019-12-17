<?php

/**
 * 应用配置
 * @author tinghu.liu
 * @date 2018-05-29
 */

return [
    /*举报类型*/
    'report_type'=>[
        1=>['code'=>1,'name'=>'举报','en_name'=>'Report (For Non-Rights Holders)','en_xplain'=>"If you are not the owner of the intellectual property rights but would like to report a case of a party infringing on another trademark, please enter the button below to file a complaint."],
        2=>['code'=>2,'name'=>'限制或禁止产品','en_name'=>'Restricted or Prohibited Products','en_xplain'=>'The seller has listed products that are restricted, prohibited or not suitable for delivery.'],
        3=>['code'=>3,'name'=>'搜索相关违规','en_name'=>'Search Related Violations','en_xplain'=>'The seller uses an illegal approach to gain exposure, e.g. posting misleading prices, posting duplicate listings etc.If you have already made your order, please click here to find the relevant orders and click Open Dispute.'],
        4=>['code'=>4,'name'=>'关税赔保','en_name'=>'Customs insurance','en_xplain'=>'If you have bought the Import Tax&VAT Insurance, and have paid the taxes, please kindly upload the tax document, invoice or receipt, please enter the button below to apply for a claim.'],
        5=>['code'=>5,'name'=>'信用卡证明','en_name'=>'Credit card certificate','en_xplain'=>'please provide us the information we need, click here to go on'],
        100=>['code'=>100,'name'=>'价格比较','en_name'=>'Price Match','en_xplain'=>'If the reported product is identical to the DX product, and lower priced sources are found, prices on product pages will be updated within 48 hours (Mon-Fri) after price match is received.'],
        101=>['code'=>101,'name'=>'报错报告','en_name'=>'BUG feedback','en_xplain'=>'If you encounter a bug with no response or page faults when shopping on DX.com, please enter the following button for  feedback. You will receive the reward we give you.'],
    ],
    /*举报状态*/
    'report_status'=>[
        1=>['code'=>1,'name'=>'待处理','en_name'=>'waiting process'],
        2=>['code'=>2,'name'=>'处理中','en_name'=>'processing'],
        3=>['code'=>3,'name'=>'已处理','en_name'=>'case closed(has been established)'],
        4=>['code'=>4,'name'=>'驳回处理关闭','en_name'=>'case closed(has not been established)'],
        5=>['code'=>5,'name'=>'撤销','en_name'=>'case withdraw'],
    ],
    /*举报小类
    key对应大类code
    */
    'report_small_type'=>[
        1=>[
            1=>['explain'=>"Report intellectual property rights infringements",'tips'=>''],
        ],
        2=>[
            1=>['explain'=>"Offensive Weapons &Offensive Materials",'tips'=>'Firearms, Police Equipment, Offensive Weapons and Offensive Materials'],
            2=>['explain'=>"Illicit Drugs, Precursors and Drug Paraphernalia",'tips'=>'Illicit Drugs; Drug Precursors; Other Controlled Substances; Products used for Manufacturing, Concealing or Using these Substances, etc'],
            3=>['explain'=>"Flammable, Explosive&Hazardous Chemicals",'tips'=>'Explosives；Explosive and flammable chemicals；Poisonous chemicals；etc.'],
            4=>['explain'=>"Obscene and Adult Materials",'tips'=>''],
            5=>['explain'=>"Medical Drugs and Devices",'tips'=>'Medical drugs (e.g. prescription drugs, OTC drugs, hormones, radioactive drugs)；Veterinary drugs and devices；Medical counseling and medical services；etc.'],
            6=>['explain'=>"Human Parts&Remains and Protected Flora and Fauna",'tips'=>'Plants and animals covered by national laws or regulations or listed under CITES; other plant and animal products prohibited by Alibaba.com, etc.'],
            7=>['explain'=>"Software, Tools and Devices Used for Illegal Purposes",'tips'=>''],
            8=>['explain'=>"Other Prohibited Products",'tips'=>''],
        ],
        3=>[
            1=>['explain'=>"Posting under an Incorrect Product Category",'tips'=>'Refers to instances when products in a listing are not consistent with the product category it is published under.'],
            2=>['explain'=>"Unmatched Actual and Listed Shipping Costs",'tips'=>'Refers to cases when the title, picture, attributes or description of a product show a false shipping price.'],
            3=>['explain'=>"Incorrect Posting of Five-Category",'tips'=>''],
            4=>['explain'=>"Duplicate Listings",'tips'=>'includes but not limited to exact the same pattern of product and similar heading and properties, or different pattern of product (taken from different angles), but the heading, properties, and price are highly similar.'],
            5=>['explain'=>"Incorrect Measuring Units",'tips'=>'refers to instances when the seller uses inconsistent measuring units for the product in the headings and descriptions which mislead buyers.'],
            6=>['explain'=>"Incorrect Category Title",'tips'=>'refers to cases where a product listing is placed in an incorrect product category.'],
            7=>['explain'=>"Prices too Low",'tips'=>'refers to circumstances where the seller publishes products at extremely low prices to gain advantage in the search results page.'],
            8=>['explain'=>"Prices too High",'tips'=>'refers to circumstances where the seller publishes products at extremely high prices to gain advantage in the search results page.'],
            9=>['explain'=>"SKU Related Infringements",'tips'=>'refers to cases when the seller intentionally avoids the rules regarding the correct practices of SKUs. This includes, but is not limited to, using false product properties (such as packaging and accessories), setting extremely low or incorrect prices to improve the search rank and/or placing different or incorrect attributes under one product category.'],
        ],
        4=>[
            1=>['explain'=>"Customs insurance",'tips'=>'']
        ],
        5=>[
            1=>['explain'=>"Case Withdraw",'tips'=>'']
        ],
        100=>[

        ],
        101=>[

        ],

    ],
    /*举报状态*/
    'question_type'=>[
        1=>['type'=>1,'name'=>'产品信息','en_name'=>'Product Information'],
        2=>['type'=>2,'name'=>'库存状况','en_name'=>'Stock Status'],
        3=>['type'=>3,'name'=>'支付','en_name'=>'Payment'],
        4=>['type'=>4,'name'=>'关于航运','en_name'=>'About Shipping'],
        5=>['type'=>5,'name'=>'其他','en_name'=>'Others'],
        6=>['type'=>6,'name'=>'购物车问题','en_name'=>'Cart Question'],
    ],

];
