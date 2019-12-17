<?php
namespace app\index\controller;

use app\common\params\IndexParams;
use app\index\dxcommon\BaseApi;
use app\index\dxcommon\User;
use app\index\model\UserModel;
use think\Log;

/**
 * Class Index
 * @author tinghu.liu
 * @date 2018-03-06
 * @package app\index\controller
 * seller 首页
 */
class Index extends Common
{

    /**
     * 首页
     * @return mixed
     */
    public function index()
    {
        /**
         * 站内信数量 && 留言数量功能 TODO
         *
         * 站内信：平台发布的公告、消息等；
            a. 平台公告
            b. 平台消息
            c. 系统消息
            d. 订单问题（售后订单消息、纠纷订单消息）
            e. 参加的活动报告信息
            f. 收藏店铺和收藏产品回访(给收藏的买家发站内信)
            g. 催付款(给下单的买家发站内信)
            h. 催收货(物流已经妥投的站内信回访)

           留言：买家发布的留言、咨询；
            a. 购物车对商家的咨询；
            b. 订单（Checkout）页产品的咨询；
            c. 产品详情页的产品咨询；
            d. 批发价咨询；
            e. Q&A帮助问题咨询；
            f. 催发货信息
            g. 买家反馈留言
         */
        $article_cate_id = config('article_cate_id');
        $seller_info = User::getSellerInfoBySellerID($this->login_user_id);
        $base_api = new BaseApi();
        //获取系统消息数据
        $message_data = $base_api->getAdminMessageData([
            'recive_user_id'=>$this->login_user_id,
            'recive_type'=>2,
        ]);
        $sys_message_data = isset($message_data['data']['data'])&&!empty($message_data['data']['data'])?$message_data['data']['data']:[];
        //获取最新公告
        $article_announcement_data_api = $base_api->getArticleList([
            'cate_id'=>$article_cate_id['latest_announcement']
        ]);
        $article_announcement_data = isset($article_announcement_data_api['data']['data'])&&!empty($article_announcement_data_api['data']['data'])?$article_announcement_data_api['data']['data']:[];
        //获取新手必读
        $must_read_data_api = $base_api->getArticleList([
            'cate_id'=>$article_cate_id['novice_must_read']
        ]);
        $must_read_data = isset($must_read_data_api['data']['data'])&&!empty($must_read_data_api['data']['data'])?$must_read_data_api['data']['data']:[];
        //获取站内信数量

        //获取留言数量

        $this->assign([
                'seller_info'=>$seller_info,
                'sys_message_data'=>$sys_message_data,
                'article_announcement_data'=>$article_announcement_data,
                'must_read_data'=>$must_read_data,
                'show_view_flag'=>1,
                'parent_menu'=>'my-account',
                'child_menu'=>'my-index',
            ]);
        return $this->fetch('index');
    }

}
