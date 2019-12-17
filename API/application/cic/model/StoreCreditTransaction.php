<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * SC信息详情模型
 * @author
 * @version Kevin 2018/3/25
 */
class StoreCreditTransaction extends Model{
    protected $table = 'cic_store_credit_transaction';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
    /*
* 新增用户积分详情
* */
    public function addStoreCreditTransaction($data){
        $res = $this->db->table($this->table)->insertGetId($data);
        return $res;
    }

    /*
    * 获取用户积分详情列表
    * */
    public function getStoreCreditTransactionList($where,$page_size,$page,$path){
        $res = $this->db->table($this->table)->where($where)->order("ID desc")->field("ID,CustomerID,OrderNumber,TransactionTime,OperateType,CurrencyType,TransactionAmount,CreateTime,Memo,Reserve1,RequestClientID")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        if(isset($data['data']) && !empty($data['data'])){
            foreach ($data['data'] as $key => $value){
                $Currency = config("Currency");
                foreach ($Currency as $k=>$v){
                    if($value['CurrencyType'] == $v['Name']){
                        $data['data'][$key]['CurrencyCode'] = $v['Code'];
                        break;
                    }
                }
            }
        }
        return $data;
    }
}