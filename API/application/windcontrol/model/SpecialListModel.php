<?php
namespace app\windcontrol\model;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use think\Log;


/**
 * 供应商模型
 * @author wang  2019/03/29
 * @version
 */
class SpecialListModel extends Model{
    const customer                                  = 'cic_customer';
    const wind_control_special_list                 = 'dx_wind_control_special_list';
    const wind_control_special_address              = 'dx_wind_control_special_address';
    const wind_control_special_beforehand           = 'dx_wind_control_special_beforehand';
    const wind_control_special_afterwards           = 'dx_wind_control_special_afterwards';
    const wind_control_special_country              = 'dx_wind_control_special_country';
    const wind_control_special_city                 = 'dx_wind_control_special_city';
    const wind_control_special_sku                  = 'dx_wind_control_special_sku';
    const wind_control_special_Card                 = 'dx_wind_control_special_Card';
    const wind_control_special_log_save_result      = 'dx_wind_control_special_log_save_result';
    const wind_control_special_third_party_results  = 'dx_wind_control_special_third_party_results';
    const wind_control_special_child_order          = 'dx_wind_control_special_child_order';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
        $this->cic = Db::connect('db_cic');
        $this->crc = Db::connect('db_crc');
    }

    public function SpecialList($where = array(),$status = ''){
        return $this->cic->table(self::customer)->where($where)->find();
    }
    /**
     * 如果是白名单验证 只要存在白名单直接通过
     * 如果是黑名单验证 只要存在黑名单直接拒绝
     * @param array $where [description]
     */
    public function BlackWhiteList($where = array()){
        return $this->crc->table(self::wind_control_special_list)->where($where)->field('type')->select();
    }
    /**
     * 白名单验证 只要存在白名单直接通过(以整合一起弃用)
     * [whitelist description]
     * @return [type] [description]
     */
    public function whitelist($where = array()){
        return $this->crc->table(self::wind_control_special_list)->where($where)->field('id')->find();
        // $list = $this->crc->table(self::wind_control_special_list)->where($where)->find();
        // if(!empty($list)){
        //    return true;
        // }else{
        //    return false;
        // }

    }
    /**
     * 黑名单验证 只要存在黑名单直接拒绝（以整合一起弃用）
     * [blacklis description]
     * @return [type] [description]
     */
    public function blacklis($where = array()){
       return $this->crc->table(self::wind_control_special_list)->where($where)->field('id')->find();
    }
    /**
     * 检查地址是否在黑名单中 只要存在黑名单直接拒绝
     * [WindControlSpecialAddress description]
     */
    public function WindControlSpecialAddress($street = ''){
        return $this->crc->table(self::wind_control_special_address)->where($street)->field('id')->find();
    }
    /**
     * 事前记录主表
     * [BeforehandLog description]
     */
    public function BeforehandLog($data = array()){
         // return $this->crc->table(self::wind_control_special_beforehand)->insert($data);
         return $this->crc->table(self::wind_control_special_beforehand)->insertGetId($data);
    }
    /**
     * 事前Sku记录表
     * [BeforehandLog description]
     */
    public function BeforehandSkuLog($data = array()){
         // return $this->crc->table(self::wind_control_special_beforehand)->insert($data);
         return $this->crc->table(self::wind_control_special_sku)->insert($data);
    }
    /**
     * [BeforehandCardLog description]
     * @param [type] $whereCard [description]
     */
    public function BeforehandCardLog($data = []){
        return $this->crc->table(self::wind_control_special_Card)->insert($data);
    }
    /**
     * 添加事后数据
     * [DataRecord description]
     */
    public function DataRecord($data = array()){
         $this->crc->table(self::wind_control_special_afterwards)->insert($data);
         return $this->crc->table(self::wind_control_special_afterwards)->getLastInsID();

    }
    /**
     * 添加事后子订单用于查询
     * [ChildOrder description]
     * @param array $data [description]
     */
    public function ChildOrder($data = array()){
         $JudgementResult = true;
         foreach ($data['ChildOrderList'] as $k => $v) {
             $result = [];
             if(!empty($v)){
                $result =  $this->crc->table(self::wind_control_special_child_order)->insert([
                    'OrderNumber'=>$v,
                    'order_master_number'=>$data['OrderNumber'],
                    'CustomerID'=>$data['CustomerID'],
                    'add_time'=>time()]);
                if(empty($result)){
                   $JudgementResult = false;
                   break;
                }
             }
         }
         if($JudgementResult == false){
               return false;
         }
         return true;
    }
    /**
     * 存储最终结果
     * [SaveResult description]
     */
    public function SaveResult($id = '',$data = array()){
          return $this->crc->table(self::wind_control_special_afterwards)->where('id = "'.$id.'"')->update(['code'=>$data['code'],'msg'=>$data['msg']]);
    }
    /**
     * 事后风控结果记录
     * [LogSaveAfterwardsResult description]
     */
    public function LogSaveAfterwardsResult($data = []){
          return $this->crc->table(self::wind_control_special_log_save_result)->insert($data);
    }
    /**
     * 高风险城市
     * [HighRiskCountry description]
     */
    public function HighRiskCity($City = ''){
         $where = '(city_name = "'.$City.'" OR city_name_lower_case = "'.strtolower($City).'") AND status = 1 AND risk_level = 2';
         return $this->crc->table(self::wind_control_special_city)->where($where)->find();
    }
    /**
     * [ThirdPartyResults description]
     * @param [type] $data     解析后数组
     * @param [type] $RawData 原数据
     */
    public function ThirdPartyResults($data,$RawData,$OrderData,$id){
          $data_result = end($data);
          $where['OrderNumber'] = $OrderData['OrderNumber'];
          $where['PaymentChannel'] = $OrderData['PaymentChannel'];
          $where['Afterwards_id'] = $id;
          $where['Decision'] = $data_result['Decision'];
          $where['Recommendation'] = $data_result['recommendation'];
          $where['Decision_Desc'] = $data_result['Decision_Desc'].'  '.$data_result['Decision_Desc_2'].'  '.$data_result['Decision_Desc_3'];
          $where['RawData'] = $RawData;
          $where['add_time'] = time();
          return $this->crc->table(self::wind_control_special_third_party_results)->insert($where);
    }
    /**
     * 根据条件获取进行判断
     * [WindControlPaymentMethodRule description]
     */
    public function WindControlPaymentMethodRule($where = [],$data = [],$config=[]){
         $list = $this->crc->table(self::wind_control_special_afterwards)->where($where)->order('add_time ASC')->select();
         if(!empty($list)){
            $pay_channel = strtolower($data['PaymentChannel']);
             if($pay_channel == 'paypal'){
                     $i = 0;
                     foreach ($list as $k => $v) {
                        if($v['Amount'] > $config['SetAmountMax']){
                              $i = 0;
                        }else{
                            $i++;
                            if($i >= $config['SetCountMax'] ){
                               return ['code'=>1001, 'msg'=>'在'.$config["SetDaySpanMax"].'天内连续'.$config["SetCountMax"].'次价格低于或等于'.$config["SetAmountMax"].'美元进入风控'];
                            }
                        }
                     }
             }else if($pay_channel == 'astropay' OR $pay_channel == 'pagsmile'){
                    $TotalNumber = count($list);
                    $SetTxnCountMax = 0;
                    //在一定时间内订单总数大于配置次数，进入风控
                    if($TotalNumber>=$config['SetOrdersCountMax']){
                         return ['code'=>1001, 'msg'=>'在'.$config["SetDaySpanMax"].'天内订单数量大于或等于'.$config["SetOrdersCountMax"].'单进入风控'];
                    }

                    //判断支付次数是否大于配置次数
                    if($config['SetTxnCountMax']){
                         $SetTxnCountMax = count($list);
                         foreach ($list as $k => $v) {
                            if($SetTxnCountMax == 0){
                                $SetTxnCountMax = $v['SetTxnCountMax'];
                            }else{
                                $SetTxnCountMax += $v['SetTxnCountMax'];
                            }
                         }
                         if($SetTxnCountMax >= $config['SetTxnCountMax']){
                             return ['code'=>1001, 'msg'=>'在'.$config["SetDaySpanMax"].'天内订单支付次数大于或等于'.$config['SetTxnCountMax'].'单进入风控'];
                         }
                    }
             }
             return ['code'=>200, 'msg'=>'验证通过'];
         }else{
             return ['code'=>200, 'msg'=>'验证通过'];
         }
    }

    public function addBeforeLog($whereBefore,$whereSku,$whereCard=null){
        //加入同时进行事物处理
        $dbObj = $this->crc;
        $dbObj->startTrans();
        try{
            
            //1.插入beforhand表
            $beforeId = $dbObj->table(self::wind_control_special_beforehand)->insertGetId($whereBefore);
            if( empty($beforeId) ){
                riskLog('error',__FILE__,__LINE__,"增加表wind_control_special_beforehand记录失败",$whereBefore);
                $dbObj->rollback();
                return false;
            }

            //2.插入sku表
            foreach ($whereSku as $key => $value) {
                $whereSku[$key]['BeforehandId'] = $beforeId;
            }
            $nums = $dbObj->table(self::wind_control_special_sku)->insertAll($whereSku);

            if( $nums != count($whereSku) ){
                riskLog('error',__FILE__,__LINE__,"增加表wind_control_special_sku记录失败",$whereSku);
                $dbObj->rollback();
                return false;
            }
            //3.插入card表
            if( !empty($whereCard) ){
                $whereCard['BeforehandId'] = $beforeId;
                $cardId = $dbObj->table(self::wind_control_special_Card)->insertGetId($whereCard);    
                if( empty($cardId) ){
                    riskLog('error',__FILE__,__LINE__,"增加表wind_control_special_Card记录失败",$whereCard);
                    $dbObj->rollback();
                    return false;
                }
            }
            
            $dbObj->commit();

            return true;

        }catch(\Exception $e){
            riskLog('error',__FILE__,__LINE__,"增加事前风控操作记录失败",$e->getMessage());
            $dbObj->rollback();
            return false;
        }
    }

    public function addAfterInfo($after,$child){
        $dbObj = $this->crc;
        $dbObj->startTrans();
        try{

            $afterId = $dbObj->table(self::wind_control_special_afterwards)->insertGetId($after);
            if( empty($afterId) ){
                riskLog('error',__FILE__,__LINE__,"增加表wind_control_special_afterwards记录失败",$after);
                $dbObj->rollback();
                return false;
            }

            $nums = $dbObj->table(self::wind_control_special_child_order)->insertAll($child);
            if( $nums != count($child) ){
                riskLog('error',__FILE__,__LINE__,"增加表wind_control_special_child_order记录失败",$child);
                $dbObj->rollback();
                return false;
            }
            
            $dbObj->commit();

            return $afterId;

        }catch(\Exception $e){
            riskLog('error',__FILE__,__LINE__,"增加事后风控操作记录失败",$e->getMessage());
            $dbObj->rollback();
            return false;
        }
    }

    public function updateAfterInfo($afterId,$after_data,$isr_data){
        $dbObj = $this->crc;
        $dbObj->startTrans();
        try{

            $num = $dbObj->table(self::wind_control_special_afterwards)->where(['Id'=>$afterId])->update($after_data);
            if( empty($num) ){
                riskLog('error',__FILE__,__LINE__,"修改表wind_control_special_afterwards记录失败{afterId}",$after_data);
                $dbObj->rollback();
                return false;
            }

            if( !empty($isr_data) ){
                $id = $dbObj->table(self::wind_control_special_third_party_results)->insertGetId($isr_data);
                if( $id<1 ){
                    riskLog('error',__FILE__,__LINE__,"wind_control_special_third_party_results",$isr_data);
                    $dbObj->rollback();
                    return false;
                }
            }
                
            $dbObj->commit();

            return true;

        }catch(\Exception $e){
            riskLog('error',__FILE__,__LINE__,"修改事后风控操作记录失败",$e->getMessage());
            $dbObj->rollback();
            return false;
        }
    }

    public function getOrderCount($where,$time,$field='AddTime'){
        return $this->crc->table(self::wind_control_special_afterwards)->where($where)->where('AddTime','>',$time)->field($field)->select();
    }

    public function getRiskCountryOrCity($country,$city){
        $num = $this->crc->table(self::wind_control_special_country)->where(['country_code'=>$country,'risk_level'=>2])->count();
        if( $num>0 ){
            return true;
        }

        $num = $this->crc->table(self::wind_control_special_city)->where(['city_name_lower_case'=>$city,'risk_level'=>2])->count();

        if( $num>0 ){
            return true;
        }
        return false;
    }
}