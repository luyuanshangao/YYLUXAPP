<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\Session;

/**
 * 区块链提醒审核表
 * @author zhongning 20191022
 */
class BlockChainWithdrawModel extends Model{

    protected $table_withdraw = 'dx_block_chain_withdraw';
    protected $table_daily_income = 'dx_block_chain_daily_income';

    /**
     * 获取信息【分页】
     * @param array $where
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getWithdrawPaginate($where = array(), $page_size=10,$query_param = array()){
        return Db::table($this->table_withdraw)->where($where)->order(['status'=>'asc','add_time'=>'desc'])->paginate($page_size,false,['query'=>$query_param]);
    }

    /**
     * 查询数据
     * @param array $where
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function findWithdraw($where = array()){
        return Db::table($this->table_withdraw)->where($where)->find();
    }

    /**
     * 查询数据
     * @param array $where
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function selectWithdraw($where = array()){
        return Db::table($this->table_withdraw)->where($where)->select();
    }

    /**
     * @param array $where
     * @param array $update
     * @return int|string
     */
    public function updateWithdraw($where = array(),$update = array()){
        return Db::table($this->table_withdraw)->where($where)->update($update);
    }


    /**
     * 查询数据
     * @param array $where
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function findDailyIncome($where = array()){
        return Db::table($this->table_daily_income)->where($where)->find();
    }

    /**
     * 查询数据
     * @param array $data
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function insertAllDailyIncome($data = array()){
        return Db::table($this->table_daily_income)->insertAll($data);
    }
}