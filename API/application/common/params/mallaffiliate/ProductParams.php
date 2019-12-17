<?php
namespace app\common\params\mallaffiliate;

class ProductParams
{

    //affiliate 用户接口
    public function userList(){
        return [
        'user001' =>'user001',//测试专用
        '25707742' =>'01e7f019fd7d70fe7a1139a3c056a83d',
        '10874200' =>'16a0bb188759e6295087f5b64b15d695',//中东，dropship网站使用
        '10100001' =>'be448f99429196d26fea1c4fd4bdef0a',//崇钢，erp使用
        '10100002' =>'170cb2dd-d2e9-4ca7-a563-12020a933440',
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

    /**
     * affiliate 类别接口
     * @return array
     */
    public function queryCategoryRules()
    {
        return[
            ['key','require','key required'],
            ['categoryid','require|number','categoryid required | categoryid Must be a number'],
        ];
    }
}