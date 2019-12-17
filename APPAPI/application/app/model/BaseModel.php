<?php
/**
 * Created by PhpStorm.
 * User: yxh
 * Date: 2019/9/11
 * Time: 18:40
 */

namespace app\app\model;


use think\Model;


class BaseModel extends Model
{
    /**
     * 获取数据数量
     * @param    array $map where语句数组形式
     * @return   boolean          操作是否成功
     */
    public function getCount($map)
    {
        $result = $this->where($map)->count();
        $result=$result?$result:0;
        return $result;
    }

    public function getWithOne($where, $with='',$field = '*')
    {
        $list=$this->get($where,$with);
        if($list){
            $list=$list->toArray();
        }else{
            $list=[];
        }
        return $list;
    }
}