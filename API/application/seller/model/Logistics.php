<?php
namespace app\seller\model;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 物流更新模型
 * @author wang 2018/05/04
 *
 */
class Logistics extends Model{

    const seller = 'db_seller';
    const logistics_management = 'sl_logistics_management';
    const db_mongodb = 'db_mongodb';
    const shipping_cost = 'shipping_cost';
    const logistics_weight = 'sl_logistics_weight';
    public function __construct()
    {   set_time_limit(0);
        parent::__construct();
        // protected $db_seller = Db::connect('db_seller');
    }
   /**更新商城shipping_cost 数据表
   * [shipping_cost description]
   * @return [type] [description]  sl_logistics_management
   */
   public static function shipping_cost($page = 1){
       $time = strtotime(date("Y-m-d"),time())-15*24*60*60;//echo $time;
       $data_page['page']    = $page;
       $list = Db::connect(self::seller)->name(self::logistics_management)->where('edit_time >= '.$time.' OR add_time>= '.$time.'')->field('id,countryCode,shippingServiceID,time_slot,first_weight,first_freight,isCharged,shippingServiceText')->paginate(500, $simple = false, $data_page);//每次拿30条

       if(!$list->items()){
           return;
       }else{
           $list_id = $list->items();
           foreach ($list_id as $key => $value) {
              if($value){
                  $result = self::redis_enqueue(json_encode($value));
              }
           }
           self::redis_dequeue();
       }
       self::shipping_cost($page+1);
   }
    /**redis入队
    * [redis_rudui description]
    * @return [type] [description]
    * @author wang 2018/06/07
    */
   public static function redis_enqueue($value=''){
      $redis = new RedisClusterBase();
      if($value==''){
        return;
      }
      try{
       return $redis->LPUSH('logistics_json',$value);//左边添加 元素
        // return $redis->LPOP('logistics_json');
      }catch(Exception $e){
        echo $e->getMessage()."\n";
      }
   }
   /***出队
    * [redis_chudui description]
    * @return [type] [description]
    *
    * @author wang 2018/06/07
    */
   public static function redis_dequeue(){
     $redis = new RedisClusterBase();
     // $redis = redis();
     while(True){
         try{
             if($logistics = $redis->LPOP('logistics_json')){
                 self::weight_template($logistics);
             }else{
                return;
             }
         }catch(Exception $e){
            return $e->getMessage()."\n";
         }
     }
   }
   /**根据条件获取商城信息，及重量区间
    * [weight_template description]
    * @param  [type] $logistics [description]
    * @return [type]            [description]
    * @author wang 2018/06/07
    */
   public static function weight_template($logistics,$page = 1,$list_weight = array()){//pr($logistics);
            $data = json_decode($logistics,true);
            if($data["shippingServiceID"] == 10){
               $date["ShippingService"] = 'Standard';
            }else if($data["shippingServiceID"] == 20){
               $date["ShippingService"] = 'SuperSaver';
            }else if($data["shippingServiceID"] == 30){
               $date["ShippingService"] = 'Expedited';
            }else if($data["shippingServiceID"] == 40){
               $date["ShippingService"] = $data["shippingServiceText"];
            }
            $where['ToCountry'] = $data["countryCode"];
            $where['IsCharged'] = $data["isCharged"];

            if(empty($list_weight)){
                $list_weight = Db::connect(self::seller)->name(self::logistics_weight)->where(['logistics_id'=>$data["id"],])->select();//dump($list_weight);
            }
            set_time_limit(0);
            $data_page['page']    = $page;
            $list   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where($where)->field('ProductId,ShippingCost,IsCharged,ToCountry,ShippingCost')->order('AddTime desc')->paginate(30, $simple = false, $data_page);//每次拿30条

            if(!$list->items()){
                return;
            }else{
                foreach ($list as $key => $value) {
                    if($value){
                       self::assembly_update($value,$data,$list_weight,$date);
                    }

                }
            }

           self::weight_template($logistics,$page+1,$list_weight);
   }
   /**重传组装数据更新到商城
    * [shipping_cost1 description]
    * @param  string  $page [description]
    * @param  integer $sum  [description]
    * @return [type]        [description]
    * @author wang 2018/06/07
    */
   public static function assembly_update($value=array(),$weight_array,$list_weight,$date){
        // $value   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ProductId'=>'110','IsCharged'=>'1','ToCountry'=>'US'])->field('ProductId,ShippingCost,IsCharged,ToCountry,ShippingCost')->find();
        $channel = false;
        $IncreaseData = array();
        if(count($list_weight) != 0 ){
            foreach ($list_weight as $ke => $va) {
                 $IncreaseData[$ke]['add_weight']   = $va["add_weight"];
                 $IncreaseData[$ke]['add_freight']  = $va["add_freight"];
                 $IncreaseData[$ke]['start_weight'] = $va["start_weight"];
                 $IncreaseData[$ke]['end_weight']   = $va["end_weight"];
            }
        }

        foreach ((array)$value["ShippingCost"] as $k => $v) {
             //判断渠道是否存在
             if($v["ShippingService"] == $date["ShippingService"]){
                 $channel = true;
                 $value["ShippingCost"][$k]["LmsRuleInfo"]['FirstWeight'] = $weight_array["first_weight"];
                 $value["ShippingCost"][$k]["LmsRuleInfo"]['FirstPrice']  = $weight_array["first_freight"];
                 $value["ShippingCost"][$k]['EstimatedDeliveryTime']      = $weight_array["time_slot"];
                 if(count($IncreaseData) != 0 ){
                     $value["ShippingCost"][$k]["LmsRuleInfo"]['IncreaseData'] = $IncreaseData;
                 }
                 // pr($weight_array);
                  pr($value["ProductId"]);pr($value["ToCountry"]);
                 $list   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ToCountry'=>$value["ToCountry"],'IsCharged'=>$value["IsCharged"],'ProductId'=>(string)$value["ProductId"]])->update(['ShippingCost.'.$k.'.LmsRuleInfo.IncreaseData'=>(object)$IncreaseData,'ShippingCost.'.$k.'.LmsRuleInfo.FirstWeight'=>$weight_array["first_weight"],'ShippingCost.'.$k.'.LmsRuleInfo.FirstPrice'=>$weight_array["first_freight"],'ShippingCost.'.$k.'.EstimatedDeliveryTime'=>$weight_array["time_slot"],'EditTime'=>time()]);
pr($list);
                 if(!$list){
                     //写失败日志
                 }
                 // pr($list);exit;
                //此处差个日志

             }

        }
        return;
   }
   public static function shipping_cost1($page = '',$sum = 0){
       $result = false;
       $shipping_cos   =  Db::connect(self::db_mongodb)->name(self::shipping_cost);
       $data = array();
       if($page != ''){
          $data['page']    = $page;
       }else{
          $data['page']    = 1;
       }
       $where['ToCountry'] = array('neq',NULL);
       sleep(1);//返回后停一秒进入下一个
       $list   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where($where)->field('ProductId,ShippingCost,IsCharged,ToCountry,ShippingCost')->order('AddTime desc')->paginate(30, $simple = false, $data);//每次拿30条
       // dump($list);exit;
       if($list->items()){
           // file_put_contents (iconv("UTF-8","GB2312//IGNORE",'aaa.txt'), $data['page'].',',FILE_APPEND);
           $result = self::ergodic($list->items(),$data['page']);
       }else{
          // file_put_contents (iconv("UTF-8","GB2312//IGNORE",'bbb.txt'), '1,',FILE_APPEND);
          //如果差不到数据时再查两遍
          if($sum < 2){
             $sum++;
             self::shipping_cost($data['page'],$sum);
          }
       }
       if($result){
             // file_put_contents (iconv("UTF-8","GB2312//IGNORE",'cccc.txt'), $result.',',FILE_APPEND);
             self::shipping_cost($data['page'] + 1);
       }
   }
   /**
    *遍历更新seller  物流模板
    */
   public static function ergodic($list = '',$page = 1){

       foreach ($list as $key => $value) {
           $marking = false;
           foreach ($value["ShippingCost"] as $k => $v) {
               if(isset($v['shippingServiceID'])){
                     $Logistics = Db::connect(self::seller)->where(['countryCode'=>$value['ToCountry'],'isCharged'=>$value['IsCharged'],'shippingServiceID'=>$v['shippingServiceID']])->field('id,countryCode,shippingServiceID,freight,time_slot,first_weight,first_freight,isCharged')->table(self::logistics_management)->find();

                     if($Logistics){
                           if(empty($v['LmsRuleInfo']['IncreaseData'])){
                               $v['LmsRuleInfo']['IncreaseData'] = '';
                           }
                           $section = self::ergodic_weight($v['LmsRuleInfo']['IncreaseData'],$Logistics['id'],$Logistics['isCharged']);

                           if($Logistics['shippingServiceID'] != $v['EstimatedDeliveryTime'] || $Logistics['first_weight'] != $v['LmsRuleInfo']['FirstWeight'] || $Logistics['first_freight'] != $v['LmsRuleInfo']['FirstPrice'] || $section !=false){
                               $value["ShippingCost"][$k]['EstimatedDeliveryTime']          = $Logistics['shippingServiceID'];
                               $value["ShippingCost"][$k]['LmsRuleInfo']['FirstWeight']     = $Logistics['first_weight'];
                               $value["ShippingCost"][$k]['LmsRuleInfo']['FirstPrice']      = $Logistics['first_freight'];
                               if($section === false){
                                   $value["ShippingCost"][$k]['LmsRuleInfo']['IncreaseData']= $v['LmsRuleInfo']['IncreaseData'];
                               }else{
                                   $value["ShippingCost"][$k]['LmsRuleInfo']['IncreaseData']= $section;
                               }
                               //如果ShippingType等于1则有折扣价 折扣价 = 首价 * （1-折扣百分比）; 如果为3  则为首价
                               if(isset($v['ShippingType']) && $v['ShippingType'] == 1){
                                   $value["ShippingCost"][$k]['Cost'] = $Logistics['first_freight'] * (1-$value["ShippingCost"][$k]['ShippingTamplateRuleInfo']['Discount']/100);
                               }else if(isset($v['ShippingType']) && $v['ShippingType'] == 1){
                                   $value["ShippingCost"][$k]['Cost'] = $Logistics['first_freight'];
                               }
                               $marking = true;
                           }
                     }
               }
           } dump($value);exit;
             //数据有变动则修改
             if($marking){
                self::update($value["ShippingCost"],$value['ToCountry'],$value['IsCharged'],$value['ProductId']);
                sleep(1);//返回后停一秒进入下一个
             }
       }
       return true;
   }
   /**对各重量区间进行循环判断
    * [ergodic_weight description]
    * @return [type] [description]
    */
   public static function ergodic_weight($dada=array(),$id='',$isCharged=''){
          $list = Db::connect(self::seller)->name(self::logistics_weight)->where(['logistics_id'=>$id,'isCharged'=>$isCharged])->field('add_weight AS StartWeight,add_freight AS EndWeight,start_weight AS IncreaseWeight,end_weight AS IncreasePrice')->select();
          //echo Db::connect(self::seller)->name(self::logistics_weight)->getlastsql();
          if($list){
              if(!is_array($dada)){
                 return $list;
              }else if(count($list) != count($dada)){
                 return $list;
              }else{
                 $date = $list;
                 foreach ($dada as $key => $value) {
                    foreach ($date as $k => $v) {
                       if($value['StartWeight'] == $v['StartWeight'] && $value['EndWeight'] == $v['EndWeight'] && $value['IncreaseWeight'] == $v['IncreaseWeight'] && $value['IncreasePrice'] == $v['IncreasePrice']){
                            unset($date[$k]);
                       }
                    }
                 }
                 if(count($date) == 0){
                    return false;
                 }else{
                    return $list;
                 }
              }
          }
   }
   //更新
   public static function update($data=array(),$ToCountry='',$IsCharged='',$ProductId='',$sum=0){
      $list   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ToCountry'=>$ToCountry,'IsCharged'=>$IsCharged,'ProductId'=>$ProductId])->update(['ShippingCost'=>(object)$data,'EditTime'=>time()]);
      if(!$list){
           operation_log(2,'更新物流模板成功',1,'系统数据更新');
      }else{
           //如果失败将 重新推两次
           if($sum>2){
               operation_log(2,'更新物流模板失败相应物流模板产品ID：'.$ProductId.'国家：'.$ToCountry,1,'定时系统');
               // return;
           }else{
               $sum++;
               self::update($data,$ToCountry,$IsCharged,$ProductId,$sum);
           }

      }
      return;
   }

/*获取运费模板*/
    public function getLogisticsManagement($where){
        return Db::connect(self::seller)->name(self::logistics_management)->where($where)->field("id,countryENName,countryCode,shippingServiceID,shippingServiceText,freight,time_slot,isCharged,calculation_formula")->select();
    }



}