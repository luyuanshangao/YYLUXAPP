<?php
namespace app\index\controller;

use app\index\dxcommon\BaseApi;
use app\index\model\ProductQaModel;

/**
 * 产品问答类
 * @author tinghu.liu
 * @date 2018-05-23
 * @package app\index\controller
 */
class ProductQa extends Common
{
    /**
     * 产品问答首页
     */
    public function index(){
        $search_content = input('search_content');
        $model = new ProductQaModel();
        $list = $model->getQuestionList($search_content, $this->login_user_id, 10);
        $this->assign([
            'child_menu'=>'product-qa-index',
            'parent_menu'=>'message-center',
            'list'=>$list,
            'ajax_url'=>json_encode([
                'async_replyQuestion'=>url('ProductQa/async_replyQuestion'),
            ]),
        ]);
        return $this->fetch();
    }

    /**
     * 回复产品问题
     * @return \think\response\Json
     */
    public function async_replyQuestion(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        $param = input();
        if (
            isset($param['question_id']) && !empty($param['question_id'])
            && isset($param['description']) && !empty($param['description'])
            && isset($param['product_id']) && !empty($param['product_id'])
        ){
            $model = new ProductQaModel();
            $data['question_id'] = $param['question_id'];
            $data['product_id'] = $param['product_id'];
            $data['name'] = $this->login_user_name;
            $data['description'] = $param['description'];
            $data['addtime'] = time();
            if ($model->replyQuestion($data)){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '回复失败，请重试';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }


    

}
