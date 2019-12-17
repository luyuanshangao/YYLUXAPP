<?php
namespace app\common\params\mallextend\emailtemplate;

/**
 * EmailTemplate参数校验类
 * Class EmailTemplateParams
 * @author tinghu.liu 2018/5/31
 * @package app\common\params\mallextend\emailtemplate
 */
class EmailTemplateParams
{
    /**
     * 根据条件获取邮件模板信息参数校验规则
     * @return array
     */
    public function getDataRules()
    {
        return[
            ['type','integer'],
            ['templetValueID','integer'],
        ];
    }

}