<?php
namespace app\index\model;
use think\Db;
use think\Log;
use think\Model;

/**
 * Created by tinghu.liu
 * Date: 2018/3/29
 * Time: 14:06
 */

class AlbumInfoModel extends Model{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'sl_album_info';

    /**
     * 新增sl_album_info表数据
     * @param $data
     * @return bool|string
     */
    public function insertData($data){
        $picture_id = 0;
        // start
        Db::startTrans();
        try{
            //写入sl_album_info表
            Db::table($this->table)->insert($data);
            $picture_id = Db::table($this->table)->getLastInsID();//返回新增数据的自增主键
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $picture_id = 0;
            Log::record('执行新增sl_album_info表事务出错');
            // roll
            Db::rollback();
        }
        return $picture_id;
    }

    /**
     * 根据用户ID[seller ID]获取单条数据
     * @param $seller_id 商家ID
     * @return array|false|\PDOStatement|string|Model
     */
    public function getDataBySellerId($seller_id){
        $where = [
            'seller_id'=>$seller_id
        ];
        return Db::table($this->table)->where($where)->select();
    }

    /**
     * 根据seller ID获取图片信息【分页】
     * @param $seller_id 商家ID
     * @param int $page 第几页数据
     * @param int $page_size 每页数据大小
     * @return $this
     */
    public function getDataBySellerIdPaginate($seller_id, $page=1, $page_size=10){
        $list = Db::table($this->table)
            ->where(['seller_id'=>$seller_id, 'is_delete'=>0])
            ->field(['id', 'seller_id', 'picture_name','picture_origin_name', 'picture_size', 'picture_extension'])
            ->page($page, $page_size)
            ->select();
        $count = Db::table($this->table)
            ->where(['seller_id'=>$seller_id, 'is_delete'=>0])
            ->count();
        //计算总页数
        $page_flag = ($count%$page_size)>0?1:0;
        return ['page_count'=>floor($count/$page_size)+$page_flag, 'list'=>$list];
    }

    /**
     * 根据图片ID删除图片
     * @param $id
     * @return int
     */
    public function deleteImageByID($id){
        return Db::table($this->table)->where(['id'=>$id])->update(['is_delete'=>1]);
    }



}