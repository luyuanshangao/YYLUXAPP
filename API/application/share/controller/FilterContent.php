<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\FilterHelper;

/**
 * 过滤敏感词汇
 */
class FilterContent extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(){
        try{
            $Content = input("Content");
            $Target = input("Target");
            if(empty($Content)){
                return apiReturn(['code'=>1001]);
            }
            $Filter = new FilterHelper();
            $res = $Filter::filterContent($Content,ROOT_PATH ."public/CensorWords.txt",$Target);
            return $res;
            return apiReturn($res);
        }catch (\Exception $e){
            return apiReturn(['code'=>200,'msg'=>$e->getMessage()]);
        }

    }


}
