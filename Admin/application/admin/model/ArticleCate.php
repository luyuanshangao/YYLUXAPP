<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2018/4/11
 * Time: 10:55
 */
namespace app\admin\model;
use think\Model;
use think\Db;
class ArticleCate  extends Model{
    public function save1($data){
        if(!isset($data['cate_id'])){//没有条件新增
            $res = Db::name("message")->insert($data);
        }else{
            $res = Db::name("message")->update($data);
        }
        return $res;
    }
}