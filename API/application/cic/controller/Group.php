<?php
namespace app\cic\controller;

use app\common\controller\Base;
use app\cic\model\Grouping as GroupingModel;
use app\cic\model\MyWish as MyWishModel;
use think\Cache;
use think\Controller;
use think\Log;
use app\app\services\BaseService;

class Group extends Base
{
    public $model = null;

    public function __construct()
    {
        parent::__construct();
        $this->model = new GroupingModel();
        $this->MyWish = new MyWishModel();
    }

    /*
     * 新增收藏
     */
    public function addProduct()
    {
        $singleRule = [
            ['product_id', 'require|number', "产品已经下架|产品已经下架|产品已经下架"],
            ['sku_id', 'require|number|>:0', "产品已经下架|产品已经下架|产品已经下架"],
            ['qty', 'require|integer|>:0', "数量不正确|数量不正确|数量不正确"],
        ];
        $post = $this->request->param();
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1000, $result);
        }

        $spuId = $post['product_id'];
        $skuId = $post['sku_id'];
        $product = $this->model->getProduct($spuId, $skuId);
        if (empty($product['product_price'])) {
            $this->error('产品已经被收藏');
        }
        $where['user_id'] = $this->uid;
        $where['product_id'] = $post['product_id'];
        $where['sku_id'] = $post['sku_id'];
        $user_cart = $this->model->get($where);
        if (!empty($user_cart)) {
            $cart_data['is_check'] = 1;//默认选中
            $cart_data['qty'] = $post['qty'] + $user_cart['qty'];
            $res = $user_cart->save($cart_data);
        } else {
            $post['user_id'] = $this->uid;
            $post['is_check'] = 1;//默认选中
            $res = $this->model->save($post);
        }
        return $this->result($res, 200);
    }

    /*
    * 新增分类
    */
    public function add()
    {
        $singleRule = [
            ['group_name', 'require'],
            ['customer_id', 'require'],
        ];
        $post = $this->request->param();
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1000, $result);
        }

        $where['group_name'] = $post['group_name'];
        $where['customer_id'] = $post['customer_id'];
        $grouping = $this->model->get($where);
        if (!empty($grouping)) {
            return $this->result('', 1001, 'Name exists!');
        }
        $grouping = $this->model->save($where);
        $id = $this->model->id;
        return $this->result((int)$id, 200);
    }

    /*
     * 删除分类
     */
    public function del()
    {
        $singleRule = [
            'group_id' => 'require|number',
            'customer_id' => 'require|number',
        ];
        $post = $this->request->post();
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1001, $result);
        }
        //判断分组是否存在产品
        $where['group_id'] = $post['group_id'];
        $where['CustomerID'] = $post['customer_id'];
        $Count = $this->MyWish->getWishCount($where);
        if (!empty($Count)) {
            //删除组里产品
            $this->MyWish->delWish($where);
        }
        $map['id'] = $post['group_id'];
        $map['customer_id'] = $post['customer_id'];
        $res = $this->model->where($map)->delete();
        return $this->result($res, 200, '');
    }

    /*
     * 更新购物车
     */
    public function save()
    {
        $post = $this->request->post();
        $singleRule = [
            'group_id' => 'require|number',
            'customer_id' => 'require|number',
            'group_name' => 'require',
        ];
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1003, $result);
        }
        $da['group_name'] = $post['group_name'];
        $where['id'] = $post['group_id'];
        $where['customer_id'] = $post['customer_id'];
        $res = $this->model->save($da, $where);
        return $this->result($res, 200);
    }

    /*
     * 获取用户收藏列表
     */
    public function index()
    {
        $post = $this->request->post();
        $singleRule = [
            //          'currency' => 'require',
//            'lang' => 'require',
            'customer_id' => 'require|number',
        ];
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1003, $result);
        }
        $Lang = 'en';
        $where['customer_id'] = $post['customer_id'];
        $field = 'id,group_name';
        $list = $this->model->all($where);

        $data=[];
        foreach ($list as &$item) {
            $ProductLists['data'] = [];
            //收藏产品
            $where1['group_id'] = $item['id'];
            $where1['CustomerID'] = $post['customer_id'];
            $where1['IsDelete'] = 0;
            $item['spu'] = $this->MyWish->getWishSPU($where1);
        }

        return $this->result($list, 200);
    }

    /*
   * 获取用户购物车列表
   */
    public function getWishList()
    {
        $post = $this->request->post();
        $singleRule = [
            'currency' => 'require',
            'lang' => 'require',
            'customer_id' => 'require|number',
            'group_id' => 'require|number',
        ];
        $result = $this->validate($post, $singleRule);
        if (true !== $result) {
            // 验证失败 输出错误信息
            return $this->result('', 1003, $result);
        }
        $Lang = $post['lang'];
        $Currency = $post['currency'];
        $where1['group_id'] = $post['group_id'];
        //收藏产品
        $where1['CustomerID'] = $post['customer_id'];
        $where1['IsDelete'] = 0;
        $spus = $this->MyWish->getWishSPU($where1);

        return $this->result($spus, 200);
    }


}