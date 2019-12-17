<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/8/13
 * Time: 14:40
 */
namespace app\app\controller;

use app\app\model\MsgPush;
use app\app\dxcommon\BaseApi;
use app\app\model\SalesOrderStatusChange;
use app\common\controller\AppBase;
use app\common\params\mall\ProductParams;
use app\app\services\ProductService;
use think\Db;
use think\Exception;
use vendor\aes\aes;
use think\Log;
use app\common\controller\Email;

/**
 * 消息中心接口
 */
class Msg extends AppBase
{
    public $baseApi;
    public $productService;
    public $model;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->model = new MsgPush();
        $this->baseApi = new BaseApi();
    }

    /**
     * 编辑
     */
    public function edit($id = NULL)
    {
        //  $where=$this->request->param();
        $row = $this->model->get(['id' => $id]);

        if (!$row) {
            $this->error('记录未找到');
        }
        $this->code = -1;
        $params = $this->request->post();
        if ($params) {
            $res = $row->save($params);
            $this->code = 1;
        }
        return $this->result($this->code);
    }

    public function index()
    {
        $post = input();
        $Email = input("Email");
        $validate = [
            'msg_type' => 'require',
            'lang' => 'require',
            //'name'  => 'require',
        ];
        $res = $this->validate($post, $validate);
        if (true !== $res) {
            return ["code" => 1002, "msg" => "please reconfirm!"];
        }
        $type = $post['msg_type'];
        $CustomerID = !empty($post['CustomerID']) ? $post['CustomerID'] : 0;
        switch ($type) {
            case 0://全部
                $where = [];
                break;
            case 1://优惠
                $where['type'] = ['in', '1,2,3'];
                break;
            case 2://分类
                //订单消息较为特殊先取订单状态里面的记录
                return $this->getOrderMsg($CustomerID);
                break;
            case 3://3其他
                $where['type'] = 0;
                break;
            default:
                $where = [];
                break;
        }
        $MsgPush = new MsgPush();
        $list = $MsgPush->getList($where);
        foreach ($list as &$value) {
            if ($value['type'] == 1) {
                $value['activity_img'] = $this->getProductImg($value);
            }
        }
        return $list;
    }

    private function getProductImg($value)
    {
        $img = '';
        if ($value['type'] == 1) {
            $paramData['product_id'] = $value['complex_id'];
            $data = $this->productService->getProduct($paramData);
            if (!empty($data) && !empty($data['FirstProductImage'])) {
                $img = 'https:' . IMG_DXCDN . $data['FirstProductImage'];
            }
        }
        return $img;

    }

    /**
     * 发送邮件【订单相关错误】
     * @param $_params
     * @author tinghu.liu 20190408
     * @return bool
     */
    public function sendEmailForOrderBug()
    {
        $_params = input();
        Log::record('sendEmailForOrderBug - params is error, params:' . json_encode($_params));
        if (!isset($_params['title']) || !isset($_params['content'])) {
            return true;
        }

        $params['to_email'] = ['yanxh@comepro.com', 'liubh@comepro.com'];
        $params['title'] = $_params['title'];
        $params['content'] = $_params['content'];
        $res = doCurl(config('api_base_url') . '/share/EmailHandle/sendEmail', $params, null, true);
        if (!isset($res['code']) || $res['code'] != 200) {
            Log::record('sendEmailForOrderBug - is error, params:' . json_encode($params) . ', res:' . json_encode($res), 'error');
            return ['code' => 1901, 'send failure'];
        } else {
            return $res;
        }
    }

    public function getOrderMsg($CustomerID)
    {
        $SalesOrderStatusChange = new SalesOrderStatusChange();
        $where=[];
        $where['o.order_status'] = ['in', [900]];
        $where['os.customer_id'] = $CustomerID;
        $data = $SalesOrderStatusChange->getOrderChangeList($where);
        $data = $data->toArray();
        foreach($data['data'] as &$value){
            $value['activity_img']=IMG_URL.$value['activity_img'];
            $value['type']=4;
        }
        $data['code'] = 200;
        return $data;

    }

}