<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2019/04/24
 * Time: 10:55
 */
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Model;
use think\Db;
class OrderMessageTemplateModel  extends Model{
    public function __construct(){
        $this->table = "dx_order_message_template";
    }

    /*
     * 获取订单留言模板
     * */
    public function getOrderMessageTemplate($where){
        $res = Db::table($this->table)->where($where)->order("id","DESC")->select();
        foreach ($res as $key=>$value){
            $res[$key]['content_en'] = htmlspecialchars_decode(htmlspecialchars_decode($value['content_en']));
        }
        return $res;
    }

    /*
     * 获取订单留言模板一条
     * */
    public function getOrderMessageTemplateInfo($where){
        $res = Db::table($this->table)->where($where)->find();
        $res['content_en'] = htmlspecialchars_decode(htmlspecialchars_decode($res['content_en']));
        return $res;
    }

    /*保存或更新订单留言模板*/
    public function saveOrderMessageTemplate($data){
        if($data['type'] == 2 && $data['status'] == 1){
            $update_where['type'] = 2;
            $update_where['status'] = 1;
            Db::table($this->table)->where($update_where)->update(['status'=>2]);
        }
        if(!empty($data['id'])){
            $where['id'] = $data['id'];
            return Db::table($this->table)->where($where)->update($data);
        }else{
            return Db::table($this->table)->insertGetId($data);
        }
    }

    /*
     * 删除订单留言模板
     * */
    public function deleteOrderMessageTemplate($where){
        return Db::table($this->table)->where($where)->delete();
    }
}