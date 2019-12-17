<?php
namespace app\common\params\mallaffiliate;

class ProductParams
{

    //affiliate 用户接口
    public function userList(){
        return [
        'user001' =>'user001',//测试专用
        '25707742' =>'01e7f019fd7d70fe7a1139a3c056a83d'
    ];
    }



    /**
     * affiliate 产品接口
     * @return array
     */
    public function queryProductRules()
    {
        return[
            ['key','require','key required'],
            ['searchArgs.language','require','language required'],
        ];
    }
}