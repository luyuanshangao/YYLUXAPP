<?php
namespace app\common\params\admin;

/**
 * Message接口参数校验
 * @author tinghu.liu 2018/6/4
 * @package app\common\params\admin
 */
class MessageParams
{

    /**
     * 获取分类佣金配置列表数据校验
     * @return array
     */
    public function getDataRules()
    {
        return[
            ['recive_user_id','integer'],
            ['recive_type','integer'],
            //分页参数
            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 增加消息数据数据校验
     * @return array
     */
    public function addMessageDataRules()
    {
        return[
            ['title','require'],
            //消息类型 1：系统消息 2:手工消息
            ['type','require|integer'],
            //发送者ID
            ['send_user_id','require|integer'],
            //发送者
            ['send_user','require'],
            //消息内容
            ['content','require'],

            ['addtime','require|integer'],
            //接收人ID
            ['recive_user_id','require|integer'],
            //接收人名称
            ['recive_user_name','require'],
            //接受者类型 1用户 2卖家
            ['recive_type','require|integer'],
        ];
    }

    /**
     * 增加消息数据数据校验
     * @return array
     */
    public function getCountByWhereRules()
    {
        return[
            //接受者类型 1用户 2卖家
            ['recive_type','require|integer'],
            //接收人ID
            ['recive_user_id','require|integer'],
            //是否已读 1已读 2未读
            ['read_status','require|integer'],
        ];
    }

}