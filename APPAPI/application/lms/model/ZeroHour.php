<?php
namespace app\lms\model;
use app\common\helpers\RedisClusterBase;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 供应商模型
 * @author
 * @version  heng zhang 2018/3/30
 */
class ZeroHour extends Model{
    const seller = 'db_seller';
    const logistics_management = 'sl_logistics_management';
    const product = 'product';
    const product_class = 'product_class';
    const db_mongodb = 'db_mongodb';
    const product_histories = 'product_histories';
    const shipping_cost = 'shipping_cost';
    const shipping_cost_histories = 'shipping_cost_histories';
    const shipping_cost_change = 'shipping_cost_change';
    public $page_size = 10;
    public $page = 1;
    protected $user = 'sl_seller';
    protected $user_extension = 'sl_seller_extension';
    protected $logistics = 'sl_logistics_management';
    protected $vat = 'sl_vat';
    protected $product = 'dx_product';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }
  /**
   * 到lms获取更新数据
   * [LogisticsUpdateSeller description]
   */
  public function LogisticsUpdateSeller($data=array()){
     // file_put_contents ('../log/lms/LogisticsUpdateSeller.log',$data.'----------------------', FILE_APPEND|LOCK_EX);
     $data = json_decode($data,true);
     // $i = 0;
     // $j = 0;
     foreach ($data as $key => $value) {
          $date = array();
          if(empty($value['isCharged'])){
             continue;
          }
          $isCharged = explode(',', $value['isCharged']);
          if($value['type_id'] == 2){
             $shippingServiceID = 10;
             $date['shippingServiceText'] = '标准物流运费';
             if(!empty($value['about_time']) && !empty($value['end_about_time'])){
                $date['time_slot'] = $value['about_time'].'-'.$value['end_about_time'];
             }else{
                $date['time_slot'] = '7-10';
             }
          }else if($value['type_id'] == 3){
             $shippingServiceID = 30;
             $date['shippingServiceText'] = '快递物流运费';
             if(!empty($value['about_time']) && !empty($value['end_about_time'])){
                $date['time_slot'] = $value['about_time'].'-'.$value['end_about_time'];
             }else{
                $date['time_slot'] = '4-7';
             }
          }else if($value['type_id'] == 4){
             $shippingServiceID = 40;
             $date['shippingServiceText'] = $value['mark'];
             if(!empty($value['about_time']) && !empty($value['end_about_time'])){
                $date['time_slot'] = $value['about_time'].'-'.$value['end_about_time'];
             }else{
                $date['time_slot'] = '4-7';
             }
          }else if($value['type_id'] == 5){
             $shippingServiceID = 20;
             $date['shippingServiceText'] = '经济物流运费';
             if(!empty($value['about_time']) && !empty($value['end_about_time'])){
                $date['time_slot'] = $value['about_time'].'-'.$value['end_about_time'];
             }else{
                $date['time_slot'] = '7-10';
             }
          }else{
             continue;
          }

          $date['countryENName']     = $value['name_en'];
          $date['areaName']          = $value['AreaName'];
          $date['shippingServiceID'] = $shippingServiceID;
          $date['country_local']     = 'CN';
          $date['countryCode'] = $value['country_code'];
          $date['calculation_formula'] = $value['calculation_formula'];
          foreach ($isCharged as $k => $v) {
               $logistics = Db::connect('db_seller')->table('sl_logistics_management')->where(['countryCode'=>$value['country_code'],'shippingServiceID'=>$shippingServiceID,'isCharged'=>$v])->find();
                $date['isCharged'] = $v;
               if($logistics){
                    // $aaa[] = $logistics['time_slot'] .'--'. $date['time_slot'].'----'.$shippingServiceID;
                   if($logistics['countryENName'] != $value['name_en'] || $logistics['shippingServiceText'] != $date['shippingServiceText'] || $logistics['calculation_formula'] !=$date['calculation_formula'] || $logistics['time_slot'] != $date['time_slot']){pr($date);
                       $date['edit_time'] = time();
                       $date['edit_author']       = '系统更新';
                       $result_update = Db::connect('db_seller')->table('sl_logistics_management')->where(['countryCode'=>$value['country_code'],'shippingServiceID'=>$shippingServiceID,'isCharged'=>$v])->update($date);
                       // $i++;
                       $date = array();
                   }else{
                       // $j++;
                       $date = array();
                       continue;
                   }
               }else{
                  $date['add_author']       = '系统更新';
                  $date['add_time'] = time();pr($date);
                  $result_update = Db::connect('db_seller')->table('sl_logistics_management')->insertGetId($date);
               }

          }
     }
      // if(!$result_update){
      //      return '失败';
      //            //日志
      //  }
     return true;
  }


   /**更新商城shipping_cost 数据表  把要更新的数据存入队列
   * [shipping_cost description]
   * @return [type] [description]  sl_logistics_management
   */
   public static function LogisticsUpdateMall($page = 1){
       // $time_log =  date("Ym",time());
       ini_set('max_execution_time', '0');
       $time = strtotime(date("Y-m-d"),time())-15*24*60*60;//echo $time;
       // $data_page['page']    = $page;
       // return Db::connect(self::seller)->name(self::logistics_management)->find();
       $list = Db::connect(self::seller)->name(self::logistics_management)->where('calculation_formula != "" AND (edit_time >= '.$time.' OR add_time>= '.$time.')')->field('id,countryCode,shippingServiceID,time_slot,isCharged,shippingServiceText,calculation_formula,shippingServiceText')->page($page,50)->select();
       // file_put_contents ('../log/lms/limt.log',$data_page['page'].',', FILE_APPEND|LOCK_EX);
       if(!$list){
           return 'meile';
       }else{
           $list_id = $list;
           foreach ($list_id as $key => $value) {
              if($value){
                  $result = self::redis_enqueue(json_encode($value));// file_put_contents ('../runtime/log/'.$time_log.'22.log', $result.',', FILE_APPEND|LOCK_EX);
              }
           }
       }
       $page = $page+1;
       return self::LogisticsUpdateMall($page);
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
      //因redis导致数据丢失，该数据用于过滤
      $data = json_decode($value,true);
      $where['ToCountry'] = $data['countryCode'];
      $where['IsCharged'] = $data['isCharged'];
      $where['ShippingCost.ShippingServiceID'] = (string)$data["shippingServiceID"];

      $result = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where($where)->find();
      if($result){
          return ;
      }

      try{
	     // file_put_contents ('../log/lms/LPUSH.log',$value.',', FILE_APPEND|LOCK_EX);
       return $redis->LPUSH('logistics_json',$value);//左边添加 元素
        // return $redis->LPOP('logistics_json');
      }catch(Exception $e){
        // file_put_contents ('../log/lms/rukuyichang.log',$e->getMessage().',', FILE_APPEND|LOCK_EX);
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
     ignore_user_abort();
     $redis = new RedisClusterBase();
      $time_log =  date("Ym",time());
         try{
             $logistics = $redis->LPOP('logistics_json');
            // file_put_contents ('../log/lms/LPOP_api.log',$logistics.',', FILE_APPEND|LOCK_EX);
             if(!empty($logistics)){
                // file_put_contents ('../log/lms/i.log',$logistics.',', FILE_APPEND|LOCK_EX);$i++;
                $result = self::weight_template($logistics);
                return  $result;
             }else{
                return 'redis_meile';
             }
         }catch(Exception $e){
            file_put_contents ('../runtime/log/'.$time_log.'/xunhuanchucou.log',$e->getMessage().',', FILE_APPEND|LOCK_EX);
            // return $e->getMessage()."\n";
            return false;
         }
   }
   // public static function shipping_cost($logistics,$limit = 0){
   //       //$result = self::weight_template($logistics,$page);return $result;
   //       while(True){
   //            try{
   //                $result = self::weight_template($logistics,$limit);
   //                if($result === false){
   //                    return ;
   //                }else{
   //                 $limit = $result;
   //                }

   //            }catch(Exception $e){
   //                file_put_contents ('../log/lms/xunhuanchucou.log',$e->getMessage().',', FILE_APPEND|LOCK_EX);
   //                return $e->getMessage()."\n";
   //            }
   //       }
   // }

    /**根据条件获取商城信息，及重量区间
    * [weight_template description]
    * @param  [type] $logistics [description]
    * @return [type]            [description]
    * @author wang 2018/06/07
    */
   public static function weight_template($logistics,$limit = 0){

            // ['ToCountry'=>$value["ToCountry"],'IsCharged'=>$value["IsCharged"],'ProductId'=>(string)$value["ProductId"],'ShippingServiceID'=>$weight_array["shippingServiceID"]]

            // file_put_contents ('../log/lms/wuliu.log',$logistics.'-----------------------------------\n', FILE_APPEND|LOCK_EX);
            $time_log =  date("Ym",time());
            ini_set('max_execution_time', '0');
			      $data_page = array();
            $where_histories = array();
            $update_data = array();
            $i = 0;
            $sum = 0;
            $data = json_decode($logistics,true);//print_r($data); return 11;
            if($data["shippingServiceID"] == 10){
               $date["ShippingService"] = 'Standard';
            }else if($data["shippingServiceID"] == 20){
               $date["ShippingService"] = 'SuperSaver';
            }else if($data["shippingServiceID"] == 30){
               $date["ShippingService"] = 'Expedited';
            }else if($data["shippingServiceID"] == 40){
               $date["ShippingService"] = $data["shippingServiceText"];
            }
            $where['ToCountry'] = $data['countryCode'];
            $where['IsCharged'] = $data["isCharged"];
            $where['ShippingCost.ShippingServiceID'] = (string)$data["shippingServiceID"];

            $list   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where($where)->field('ProductId,ShippingCost,IsCharged,ToCountry,ShippingCost')->limit($limit,100)->select();
             //file_put_contents ('../log/lms/list.log',json_encode($list).'-----------------------------------------------------------\n', FILE_APPEND|LOCK_EX);
             //file_put_contents ('../log/lms/list_sql.log',Db::connect(self::db_mongodb)->name(self::shipping_cost)->getLastSql().'-----------------------------------------------------------\n', FILE_APPEND|LOCK_EX);
            //  pr(Db::connect(self::db_mongodb)->name(self::shipping_cost)->getLastSql());
            $where_data = $where_histories = $where;
            $where_data['status'] = $where_histories['status'] = 1;//未完成完成
            if(!$list){
                $update_data['status'] = 2;//完成
                Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where($where_data)->update($update_data);
                return false;
            }else{
                //记录翻页位置
                $cost_histories  = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where($where_data)->find();
                //print_r(Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->getLastSql());
                if($cost_histories){
                    // $update_data['page'] = $page;
                    $update_data['limit'] = $limit;
                    $update_data['edit_time'] = time();
                    Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where($where_data)->update($update_data);
                }else{
                    $where_histories['limit'] = $limit;
                    $where_histories['page_size'] = 30;
                    $where_histories['logistics'] = $logistics;
                    $where_histories['add_time'] = time();
                    $where_histories['ShippingCost']['ShippingServiceID'] = (string)$data["shippingServiceID"];
                    unset($where_histories['ShippingCost.ShippingServiceID']);
                    $histories = Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->insertGetId($where_histories);
                    //file_put_contents ('../log/lms/shipping_cost_histories_api.log',$histories.'------------------------\n', FILE_APPEND|LOCK_EX);
                }
                $sum = count($list);
                foreach ($list as $key => $value) {
                    if($value){
                       $delete_result = self::delete_shipping_cost($value);//过滤删除模板
                       if($delete_result == 1){
                            self::assembly_update($value,$data,$date);
                       }else if($delete_result == 3){
                       // file_put_contents ('../log/lms/iii.log',$i, FILE_APPEND|LOCK_EX);
                          $i++;//记录删除个数
                       }
                    }
                }


            }
            $sum = $sum - $i;//查出总个数减去删除个数
            $limit = $limit + $sum;//下一页开始条数
            if(!empty($limit)){
               Db::connect(self::db_mongodb)->name(self::shipping_cost_histories)->where($where_data)->update(['limit'=>$limit]);
               // file_put_contents ('../log/lms/limit.log',$limit.';', FILE_APPEND|LOCK_EX);
            }
            return $limit;
   }


   /**重传组装数据更新到商城
    * [shipping_cost1 description]
    * @param  string  $page [description]
    * @param  integer $sum  [description]
    * @return [type]        [description]
    * @author wang 2018/06/07
    */
   public static function assembly_update($value=array(),$weight_array,$date){
        ini_set('max_execution_time', '0');
        $channel = false;
        $IncreaseData = array();
        $time_log =  date("Ym",time());
        $data = array();
        $list = array();
        // $i = 0;
        foreach ((array)$value["ShippingCost"] as $k => $v) {
             //判断渠道是否存在
             if($v["ShippingServiceID"] == $weight_array["shippingServiceID"]){
                 $channel = true;
                 //因当前lms没有传时间间隔过来，先放一个判断在这里，备后面使用
                 // if(!empty($weight_array["time_slot"])){
                 //    $value["ShippingCost"][$k]['EstimatedDeliveryTime'] = $weight_array["time_slot"];
                 // }
                 if($v['EstimatedDeliveryTime'] != $weight_array["time_slot"] || $v['LmsRuleInfo'] !=$weight_array['calculation_formula']){

                     //是专线的情况下判断名称是否有变化
                     if($weight_array["shippingServiceID"] == 40 && $v['ShippingService'] != $weight_array["shippingServiceText"] && !empty($weight_array["shippingServiceText"])){
                           $list = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ToCountry'=>$value["ToCountry"],'IsCharged'=>$value["IsCharged"],'ProductId'=>(string)$value["ProductId"]])->update(['ShippingCost.'.$k.'.LmsRuleInfo'=>$weight_array['calculation_formula'],'ShippingCost.'.$k.'.ShippingService'=>$weight_array["shippingServiceText"],'ShippingCost.'.$k.'.EstimatedDeliveryTime'=>$weight_array["time_slot"],'EditTime'=>time()]);
                     }else{
                           $list = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ToCountry'=>$value["ToCountry"],'IsCharged'=>$value["IsCharged"],'ProductId'=>(string)$value["ProductId"]])->update(['ShippingCost.'.$k.'.LmsRuleInfo'=>$weight_array['calculation_formula'],'ShippingCost.'.$k.'.EstimatedDeliveryTime'=>$weight_array["time_slot"],'EditTime'=>time()]);
                     }
                 }else{
                     //file_put_contents ('../log/lms/buxiugai_api.log',$value["ProductId"].',', FILE_APPEND|LOCK_EX);
                 }
                 //还差个时间段
                 // $list   = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ToCountry'=>$value["ToCountry"],'IsCharged'=>$value["IsCharged"],'ProductId'=>(string)$value["ProductId"]])->update(['ShippingCost.'.$k.'.LmsRuleInfo'=>$weight_array['calculation_formula'],'EditTime'=>time()]);
                  // pr(Db::connect(self::db_mongodb)->name(self::shipping_cost)->getLastSql());
                 if(!$list){
                     //写失败日志
                 }else{
                        $time_time = date("YmdH",time());
                        self::shipping_cost_change($value["ProductId"]);
                        $time_time = date("YmdH",time());
                        file_put_contents ('../runtime/log/'.$time_log .'/product_wuliu_sql_'.$time_time.'.log',json_encode(['ToCountry'=>$value["ToCountry"],'IsCharged'=>$value["IsCharged"],'ProductId'=>(string)$value["ProductId"],'ShippingServiceID'=>$weight_array["shippingServiceID"]]).';', FILE_APPEND|LOCK_EX);
                        // file_put_contents ('../log/lms/xiugai_api_'.$time_time.'.log',$value["ProductId"].',', FILE_APPEND|LOCK_EX);
                        // exit;
                 }
             }
        }
        return ;
   }
    //记录更改过的spu
   public static function  shipping_cost_change($spu = 0){
      if(!empty($spu)){
              $data = array();
              //记录翻页位置
              $shipping_cost_change  = Db::connect(self::db_mongodb)->name(self::shipping_cost_change)->where(['_id'=>(int)$spu])->find();
              if(!empty($shipping_cost_change)){
                  $data['edit_time']  = time();
                  // $data['_id'] = (int)$spu;
                  $Number = $shipping_cost_change['Number'] + 1;
                  $data['Number'] = (int)$Number;
                  Db::connect(self::db_mongodb)->name(self::shipping_cost_change)->where(['_id'=>(int)$spu])->update($data);
              }else{
                  $data['add_time']   = time();
                  $data['edit_time']  = time();
                  $data['_id'] = (int)$spu;
                  $data['Number'] = 1;
                  Db::connect(self::db_mongodb)->name(self::shipping_cost_change)->insertGetId($data);
              }
              return ;
      }
      return;
   }
  /**
   * 删除产品没有的模板
   * 返回1则有这个产品
   * [delect_shipping_cost description]
   * @return [type] [description]
   */
  public static function delete_shipping_cost($data){
         $time_log =  date("Ym",time());
         $list = Db::connect(self::db_mongodb)->name(self::product)->where(['_id'=>(int)$data['ProductId']])->find();
         if($list){
             if(!empty($list['ProductStatus'])){
                 if($list['StoreID'] == 888){
                     return 2;
                 }else{
                       if($list['ProductStatus'] == 1 || $list['ProductStatus'] == 5){
                          return 1;
                       }else{
                          return 2;
                       }
                 }

             }else{
                return 2;
             }
         }else{
             $result = Db::connect(self::db_mongodb)->name(self::shipping_cost)->where(['ProductId'=>(string)$data["ProductId"]])->delete();
             if($result){
                file_put_contents ('../runtime/log/'.$time_log.'/delete_spu.log',$data["ProductId"].',', FILE_APPEND|LOCK_EX);
                 // file_put_contents ('../log/lms/delete_spu.log',$data["ProductId"].',', FILE_APPEND|LOCK_EX);
             }
             return 3;
         }
  }






    /**修改 或添加物流列表
   * [update_Logistics description]
   * $data  条件数组
   * $result  结果 数组
   * @return [type] [description]
   */
  public static function update_Logistics($data,$result){
       // $logistics = Db::name(LOGISTICS);
       $logistics = Db::connect('db_seller')->table('sl_logistics_management');
       $result_update = '';
       $result_edit   = '';
       $lms_result    = true;
       $result = json_decode($result,true);//return $result;
       $date['shippingServiceText'] = '';
       // dump($result);
       foreach ((array)$result as $key => $value) {
           if($value['code'] == 200){
             $list = Db::connect('db_seller')->table('sl_logistics_management')->where(['countryCode'=>$value['country'],'shippingServiceID'=>$data['shipping_type'],'isCharged'=>$value["isCharged"]])->find();
// return $value; return 4;
             if($list){
                 if($list['time_slot'] != $value["about_time"] || $list['countryENName'] != $value["name_en"] || $list['isCharged'] != $value["isCharged"] || $list['first_weight'] != $value["first_weight"] || $list['first_freight'] != $value["first_freight"]){
                     // $date['freight']       = $value['price'];
                     $date['time_slot']     = $value['about_time'];
                     $date['countryENName'] = $value['name_en'];
                     $date['isCharged']     = $value['isCharged'];
                     $date['areaName']      = $value['AreaName'];
                     $date['country_local'] = 'CN';//暂时默认中国
                     // if($data['shipping_type'] == 40){
                     //    $date['shippingServiceText'] = $value['firm_name'];
                     // }else{
                     //    $date['shippingServiceText'] = $data['shippingServiceText'];
                     // }
                     if($data['shipping_type'] == 40){
                        $date['shippingServiceText'] = $value['ship_type_name'];
                      }else{
                        $date['shippingServiceText'] = $data['shippingServiceText'];
                      }
                     // $date['shippingServiceText'] = $data['shippingServiceText'];
                     // $date['shippingServiceText'] = $data['shippingServiceText'];
                     $date['first_weight']  = $value['first_weight'];
                     $date['first_freight'] = $value['first_freight'];
                     $date['edit_author']   = '系统更新';
                     $date['edit_time']     = time();
                     $result_update = Db::connect('db_seller')->table('sl_logistics_management')->where(['countryCode'=>$value["country"],'shippingServiceID'=>$data['shipping_type'],'isCharged'=>$value["isCharged"]])->update($date);

                     if(!$result_update){
                        operation_log(2,'物流更新logistics_management表失败,对应修改参数：'.json_encode($date),3,'系统更新');
                        $result_edit .= '修改失败;';
                     }else{
                        //添加重量区间到区间表
                        if($value["weight"]){
                            self::ergodic($value["weight"],$list['id'],$value['isCharged']);
                        }
                     }
                 }else{
                     if($value["weight"]){
                         self::ergodic($value["weight"],$list['id'],$value['isCharged']);
                     }
                 }
             }else{
                // $date['freight']       = $value['price'];
                $date['time_slot']     = $value['about_time'];
                $date['countryENName'] = $value['name_en'];
                $date['areaName']      = $value['AreaName'];
                if($data['shipping_type'] == 40){
                  $date['shippingServiceText'] = $value['ship_type_name'];
                }else{
                  $date['shippingServiceText'] = $data['shippingServiceText'];
                }

                $date['isCharged']     = $value['isCharged'];
                $date['countryCode']   = $value['country'];
                $date['shippingServiceID']   = $data['shipping_type'];
                $date['first_weight']  = $value['first_weight'];
                $date['first_freight'] = $value['first_freight'];
                $date['add_author']    = '系统更新';
                $date['add_time']      = time();
                $date['country_local'] = 'CN';//暂时默认中国

                // $result_update = Db::connect('db_seller')->table('sl_logistics_management')->insert($date);
                $result_update = Db::connect('db_seller')->table('sl_logistics_management')->insertGetId($date);
                // return  Db::connect('db_seller')->getLastSql();
                if(!$result_update){
                        operation_log(2,'物流更新logistics_management表失败,对应修改参数：'.json_encode($date),3,'系统更新');
                        $result_edit .= '添加失败;';
                }else{
                      //添加重量区间到区间表
                        if($value["weight"]){
                            self::ergodic($value["weight"],$result_update,$value['isCharged']);
                        }
                }
             }
           }
       }

       if(!empty($result_edit)){
           return false;
       }else{
           return true;
       }
  }
  /*
   遍历组合数据
   */
  public static function ergodic($data,$id ='',$isCharged){
     foreach ($data as $k => $v) {
          $lms_weight = Db::connect('db_seller')->table('sl_logistics_weight')->where(['logistics_id'=>$id,'lms_id'=>$v['id'],'isCharged'=>$isCharged])->find();
          $dat['logistics_id'] = $id;
          $dat['lms_id']       = $v['id'];
          $dat['add_weight']   = $v['add_weight'];
          $dat['add_freight']  = $v['add_freight'];
          $dat['start_weight'] = $v['start_weight'];
          $dat['end_weight']   = $v['end_weight'];
          $dat['isCharged']    = $isCharged;
          if($lms_weight){
             if($dat['add_weight'] != $lms_weight ['add_weight'] || $dat['add_freight'] != $lms_weight['add_freight'] || $dat['start_weight'] != $lms_weight['start_weight']  || $dat['end_weight'] != $lms_weight['end_weight']){
                $lms_result = self::weight($dat,1);
             }
          }else{
             $lms_result = self::weight($dat,2);
          }
     }
     return;
  }
  /*
   * 遍历添加或修改
   */
  public static function weight($data=array(),$status=''){
       if($status == 1){
            $result = Db::connect('db_seller')->table('sl_logistics_weight')->where(['logistics_id'=>$data["logistics_id"],'lms_id'=>$data['lms_id'],'isCharged'=>$data['isCharged']])->update($data);
            if(!$result){
                operation_log(2,'物流更新logistics_weight表失败,对应修改参数：'.json_encode($data),3,'系统更新');
                return false;
            }else{
                return true;
            }
       }else if($status == 2){
            $result = Db::connect('db_seller')->table('sl_logistics_weight')->insert($data);
            if(!$result){
                operation_log(2,'','物流添加logistics_weight表失败,添加数据为：'.json_encode($data),'系统更新');
                return false;
            }else{
                return true;
            }
       }
  }
  /**
   * 添加或修改seller数据库vat
   * [vat description]
   * @return [type] [description]
   */
  public static function vat($data=array()){
          unset($data['access_token']);
          $vat = Db::connect('db_seller')->where(['code'=>$data['code']])->table('sl_vat')->find();
          if(!$vat){
             // if(empty($data['add_time']) ){
             //     $data['add_time'] = time();
             // }
             $result = Db::connect('db_seller')->table('sl_vat')->insert($data);
          }else{
             $data['edit_time'] = time();
             $result = Db::connect('db_seller')->table('sl_vat')->where(['code'=>$data['code']])->update($data);
          }
          if($result){
             return true;
          }else{
             return false;
          }
  }
  /**
  * lmsd到商城获取产品信息
  * [product_information description]
  * @return [type] [description]
  * author: Wang
  * AddTime:2018-04-26
  */
  public function product_information($data=array()){

     $data_array = json_decode($data['data']);
     foreach ($data_array as $k => $v) {
        $sku[] = (string)$v;
        // $sku[] = (int)$v;
     }
     // $list  =  Db::connect("db_mongodb")->where(['Skus._id'=>['in',$sku]])->field('Title,CategoryPath,Dimensions')->name('product')->select();
     // return Db::connect("db_mongodb")->getLastSql();
     if($data['status'] == 1){
           $list  =  Db::connect("db_mongodb")->where(['Skus.Code'=>['in',$sku]])->field('Descriptions,Title,CategoryPath,PackingList.Dimensions,HSCode,SalesUnitType,LogisticsLimit,Skus._id,Skus.Code')->name(self::product)->select();
     //       return  $list;
     //       return Db::connect("db_mongodb")->getLastSql();
     }else{
           $list  =  Db::connect("db_mongodb")->where(['Skus.Code'=>['in',$sku]])->field('Descriptions,Title,CategoryPath,Dimensions,HSCode,SalesUnitType,LogisticsLimit,Skus._id,Skus.Code')->name(self::product)->select();
     }

     foreach ((array)$list as $key => $value) {
           $class_id = array();
           $className = '';
           $category = explode('-',$value["CategoryPath"]);
           foreach ((array)$category as $ke => $va) {
             $class_id[] = (int)$va;
           }
           $class_name  =  Db::connect("db_mongodb")->where(['id'=>['in',$class_id]])->field('title_en')->name(self::product_class)->select();
           // return $class_name;
           foreach ((array)$class_name as $ky => $ve) {
                $className .= $ve['title_en'].'>';
           }
           $list[$key]['class_name'] = rtrim($className, ">");
     }
     // return Db::connect("db_mongodb")->getLastSql();

     if($list){
         return array('code'=>200,'data'=>$list);
     }else{
         return array('code'=>100,'data'=>'查不到数据');
     }
  }
   /**
  * lmsd到商城获取产品信息
  * [product_information description]
  * @return [type] [description]
  * author: Wang
  * AddTime:2018-04-26
  */
  public function product_information_pdc($data=array()){

     $data_array = json_decode($data['data']);
     foreach ($data_array as $k => $v) {
        $sku[] = (string)$v;
        // $sku[] = (int)$v;
     }
     // $list  =  Db::connect("db_mongodb")->where(['Skus._id'=>['in',$sku]])->field('Title,CategoryPath,Dimensions')->name('product')->select();
     // return Db::connect("db_mongodb")->getLastSql();
     if($data['status'] == 1){
           $list  =  Db::connect("db_mongodb")->where(['Skus.Code'=>['in',$sku]])->field('Descriptions,Title,CategoryPath,PackingList.Dimensions,HSCode,SalesUnitType,LogisticsLimit,Skus._id,Skus.Code')->name(self::product)->select();
     //       return  $list;
     //       return Db::connect("db_mongodb")->getLastSql();
     }else{
           $list  =  Db::connect("db_mongodb")->where(['Skus.Code'=>['in',$sku]])->field('Descriptions,Title,CategoryPath,Dimensions,HSCode,SalesUnitType,LogisticsLimit,Skus._id,Skus.Code')->name(self::product)->select();
     }

     foreach ((array)$list as $key => $value) {
           $class_id = array();
           $className = '';
           $category = explode('-',$value["CategoryPath"]);
           foreach ((array)$category as $ke => $va) {
             $class_id[] = (int)$va;
           }
           $class_name  =  Db::connect("db_mongodb")->where(['id'=>['in',$class_id]])->field('title_en')->name(self::product_class)->select();
           // return $class_name;
           foreach ((array)$class_name as $ky => $ve) {
                $className .= $ve['title_en'].'>';
           }
           $list[$key]['class_name'] = rtrim($className, ">");
     }
     // return Db::connect("db_mongodb")->getLastSql();

     if($list){
         return array('code'=>200,'data'=>$list);
     }else{
         return array('code'=>100,'data'=>'查不到数据');
     }
  }

}