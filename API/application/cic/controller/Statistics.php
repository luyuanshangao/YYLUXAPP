<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/9/12
 * Time: 16:41
 */
namespace app\cic\controller;

use app\common\controller\Base;
use app\cic\model\CustomerModel;
use think\Exception;
use think\Log;
use think\Db;
use think\Controller;

class Statistics extends Base
{

    public function getDay()
    {
        $post = request()->param();
        $singleRule = [
            'RegisterStart' => 'require',
            'RegisterEnd' => 'require',
        ];
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1000, $result);
        }

        $RegisterStart = $post['RegisterStart'];
        $RegisterEnd = $post['RegisterEnd'];
        $sql = 'select FROM_UNIXTIME(RegisterOn,\'%Y%m%d\') days,count(*) count from cic_customer
                where `RegisterOn` BETWEEN '.$RegisterStart.' AND '.$RegisterEnd.'
                group by days order by days';
        $CustomerModel=new CustomerModel();
        $data=$CustomerModel->query($sql);
        return $data;
    }
}