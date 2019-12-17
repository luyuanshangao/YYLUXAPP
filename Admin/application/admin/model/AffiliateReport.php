<?php
namespace app\admin\model;
use think\Model;
use think\Db;
use think\Exception;
use app\admin\dxcommon\BaseApi;
/**
 * Add by:wang
 * AddTime:2018-12-14
 */
class AffiliateReport  extends Model{
    const product_class = 'dx_product_class';
    const affiliate_order_item = 'affiliate_order_item';
    const affiliate_black = 'affiliate_black';

     /**
     * 报表统计
     * [ReportStatistics description]
     */
    public static function ReportStatistics($where=array(),$data=array(),$order_item = array()){
        if(empty($data['countPage'])){
            $data['countPage'] = Db::name(AFFILIATE_ORDER)->where($where)->count();
        }
        $affiliate_id = '';
        $sku_data = array();
        $list_data = array();
        $order_price = array();
        $InvalidOrder = '';
        $invalid_order = [];
        $list = Db::name(AFFILIATE_ORDER)->where($where)->order('create_on desc')->page($data['page'],$data['page_size'])->select();
        if(!empty($list)){
            foreach ($list as $k => $v) {
                $affiliate_id .= $v['affiliate_order_id'].',';
                if($v['order_status'] == 1400){
                    $InvalidOrder .= $v['order_number'].',';
                }
            }
            /**
             * $data['page_size']
             */
            $InvalidOrder = rtrim($InvalidOrder, ",");
            if($data['page_size']>100 && $InvalidOrder != ''){
                $invalid_order = BaseApi::InvalidOrder(['order_number' =>$InvalidOrder]);
                  if($invalid_order['code']==200){
                        $invalid_order = $invalid_order['data'];
                  }else{
                        $invalid_order = [];
                  }
            }

            if(!empty($affiliate_id)){
                   $order_item['affiliate_order_id'] = ['in',rtrim($affiliate_id, ",")];
                   $list_sku = Db::name(self::affiliate_order_item)->where($order_item)->field('affiliate_order_id,sku_id,sku_code,commission_price')->select();
                   if(!empty($list_sku)){
                       foreach ($list_sku as $ke => $va) {
                              if(!empty($va['sku_code'])){
                                  if(!empty($va['affiliate_order_id']) && empty($sku_data[$va['affiliate_order_id']])){
                                     $sku_data[$va['affiliate_order_id']] = '';
                                  }
                                  if(empty($order_price[$va['affiliate_order_id']])){
                                      $order_price[$va['affiliate_order_id']] = $va['commission_price'];
                                  }else{
                                      $order_price[$va['affiliate_order_id']] = $order_price[$va['affiliate_order_id']] + $va['commission_price'];
                                  }
                                  $sku_data[$va['affiliate_order_id']] .= $va['sku_code'].',';
                              }
                       }
                   }
            }
        }
        // $list['sql'] = Db::name(AFFILIATE_ORDER)->getLastSql();
        return array('list'=>$list,'list_sku'=>$sku_data,'countPage'=>$data['countPage'],'order_price'=>$order_price,'invalid_order'=>$invalid_order);
    }
    /**
     * 从sku表开始查
     * 报表统计
     * [ReportStatistics description]
     */
    public static function ReportStatistics_sku($where = array(),$data = array(),$order_item = array()){
         $list_sku = Db::name(self::affiliate_order_item)->where($order_item)->field('affiliate_order_id,sku_id,sku_code,commission_price')->order('add_time desc')->select();
          // dump(Db::name(self::affiliate_order_item)->getLastSql());
         $affiliate_id = '';
         $list = array();
         $order_price = array();
         if(!empty($list_sku)){
             foreach ($list_sku as $ke => $va) {
                   $affiliate_id .= $va['affiliate_order_id'].',';
                   $sku_data[$va['affiliate_order_id']] .= $va['sku_code'].',';
                   if(empty($order_price[$va['affiliate_order_id']])){
                      $order_price[$va['affiliate_order_id']] = $va['commission_price'];
                   }else{
                      $order_price[$va['affiliate_order_id']] = $order_price[$va['affiliate_order_id']] + $va['commission_price'];
                   }
             }
             if(!empty($affiliate_id)){
                  $where['affiliate_order_id'] = ['in',rtrim($affiliate_id,',')];
             }
             if(empty($data['countPage'])){
                $data['countPage'] = Db::name(AFFILIATE_ORDER)->where($where)->count();
             }
             $list = Db::name(AFFILIATE_ORDER)->where($where)->page($data['page'],$data['page_size'])->order('create_on desc')->select();
             if(!empty($list)){
                  foreach ($list as $k => $v) {
                     if($v['order_status'] == 1400){
                         $InvalidOrder .= $v['order_number'].',';
                     }
                  }

                  $InvalidOrder = rtrim($InvalidOrder, ",");
                  if($data['page_size']>100 && $InvalidOrder != ''){
                      $invalid_order = BaseApi::InvalidOrder(['order_number' =>$InvalidOrder]);
                      if($invalid_order['code']==200){
                            $invalid_order = $invalid_order['data'];
                      }else{
                            $invalid_order = [];
                      }
                  }
             }
             // dump(Db::name(AFFILIATE_ORDER)->getLastSql());

         }
         return array('list'=>$list,'list_sku'=>$sku_data,'countPage'=>$data['countPage'],'order_price'=>$order_price,'invalid_order'=>$invalid_order);
    }
    /**
     * 报表统计
     * [ReportStatistics description]
     */
    // public static function ReportStatistics($where=array(),$data=array()){
    //     if(empty($data['countPage'])){
    //         $data['countPage'] = Db::name(AFFILIATE_ORDER)
    //                             ->alias('AO')
    //                             ->join('__AFFILIATE_ORDER_ITEM__ AOI','AO.affiliate_order_id = AOI.affiliate_order_id')
    //                             ->where($where)
    //                             ->count();
    //     }

    //     $list['list'] = Db::name(AFFILIATE_ORDER)
    //                     ->alias('AO')
    //                     ->join('__AFFILIATE_ORDER_ITEM__ AOI','AO.affiliate_order_id = AOI.affiliate_order_id')
    //                     // ->field('AO.affiliate_order_id,AO.order_number,AO.price,AO.order_status,AO.order_remark,AO.affiliate_id')
    //                     ->where($where)
    //                     ->field('AO.*,AOI.sku_id')
    //                     ->order('AO.add_time desc')
    //                     ->page($data['page'],$data['page_size'])
    //                     ->select();
    //     // $list['sql'] = Db::name(AFFILIATE_ORDER)->getLastSql();
    //     $list['countPage'] = $data['countPage'];
    //     return $list;
    // }
    /**
     * Affiliate订单用户详情
     * [OrderUserDetails description]
     */
    public static function OrderUserDetails($where=array(),$data=array()){
         $list = array();
         $products_sku = array();
         $parent_class = array();
         if(!empty($where)){
                $list['list'] = Db::name(AFFILIATE_ORDER)
                        ->alias('AO')
                        ->join('__AFFILIATE_ORDER_ITEM__ AOI','AO.affiliate_order_id = AOI.affiliate_order_id')
                        ->where($where)
                        ->field('AO.*,AOI.sku_id,AOI.product_id,AOI.price as sku_price,AOI.sku_count,AOI.commission_price,AOI.first_category_id,AOI.second_category_id,AOI.third_category_id,AOI.four_category_id,AOI.dollar_price,AOI.sku_code')
                        ->order('AO.add_time desc')
                        ->page($data['page'],$data['page_size'])
                        ->select();
                if(!empty($list['list'])){
                     foreach ($list['list'] as $k => $v) {
                         $products_sku[] = (int)$v['product_id'];
                         if(!empty($v['four_category_id'])){
                              $products_class[] = (int)$v['four_category_id'];
                         }else if(!empty($v['third_category_id'])){
                              $products_class[] = (int)$v['four_category_id'];
                         }else if(!empty($v['second_category_id'])){
                              $products_class[] = (int)$v['second_category_id'];
                         }else if(!empty($v['first_category_id'])){
                              $products_class[] = (int)$v['first_category_id'];
                         }
                     }
                     $parent_list     = Db::connect("db_mongo")->name(DX_PRODUCT)->where(['_id'=>['in',$products_sku]])->field('Title')->select();
                     if(!empty($products_class)){
                         $parent_class = Db::connect("db_mongo")->name(self::product_class)->where(['id'=>['in',$products_class]])->field('id,title_en')->select();
                     }
                     foreach ($list['list'] as $key => $value) {
                        if(!empty($parent_list)){
                            foreach ($parent_list as $ke => $va) {
                                if($value['product_id'] == $va['_id']){
                                  $list['list'][$key]['Title'] = $va['Title'];
                                }
                            }
                        }
                        if(!empty($parent_class)){
                            foreach ($parent_class as $k_class => $v_class) {
                                  if($value['four_category_id'] == $v_class['id']){
                                        $list['list'][$key]['class_name_en'] = $v_class['title_en'];
                                  }else if($value['third_category_id'] == $v_class['id']){
                                        $list['list'][$key]['class_name_en'] = $v_class['title_en'];
                                  }else if($value['second_category_id'] == $v_class['id']){
                                        $list['list'][$key]['class_name_en'] = $v_class['title_en'];
                                  }else if($value['first_category_id'] == $v_class['id']){
                                        $list['list'][$key]['class_name_en'] = $v_class['title_en'];
                                  }
                            }
                        }
                     }
                     // return Db::connect("db_mongo")->getLastSql();
                }
         }
         return $list;
    }
    /**
     * Affiliate用户统计
     * [AffiliateUserStatistics description]
     */
    public static function AffiliateUserStatistics($data=array(),$page=1,$page_size=30,$countPage=''){
          $where = array();
          $data['order_status'] = ['in','200,400,500,600,800,900,1100,1200,1300'];
          // if(empty($data['countPage'])){
          //     $countPage =  Db::name(AFFILIATE_ORDER)->where($data)->group('affiliate_id')->count();
          // }
          // $list = Db::name(AFFILIATE_ORDER)->where($data)->field('affiliate_order_id,order_number,affiliate_id,count(affiliate_id) as affiliate_sum,max(create_on) as create_on,sum(price) as price,sum(total_valid_commission_price) as total_valid_commission_price,sum(total_invalid_commission_price) as total_invalid_commission_price,cic_id')->order('affiliate_sum DESC')->group('affiliate_id')->page($page,$page_size)->select();
          $list = Db::name(AFFILIATE_ORDER)->where($data)->field('affiliate_order_id,order_number,affiliate_id,count(affiliate_id) as affiliate_sum,max(create_on) as create_on,sum(price) as price,sum(total_valid_commission_price) as total_valid_commission_price,sum(total_invalid_commission_price) as total_invalid_commission_price,cic_id')->order('affiliate_sum DESC')->group('affiliate_id')->select();
         /* dump(Db::name(AFFILIATE_ORDER)->getLastSql());
          // $where['order'] = $list;
          // $where['countPage'] = $countPage;
          dump($list);exit;*/
          $affiliate_order_item = self::AffiliateUserStatistics_item($data,$list);
          return array('list'=>$list,'affiliate_order_item'=>$affiliate_order_item['order_item'],'order_invalid'=>$affiliate_order_item['order_invalid']);
    }
    /**
     * 统计Affiliate用户佣金
     * [AffiliateUserStatistics_item description]
     */
    public static function AffiliateUserStatistics_item($data=array(),$list=array()){
          if(empty($list)){
             return;
          }
          $where = array();
          $order_item = array();
          $order_invalid = array();
          $where_invalid = array();
          if(!empty($data["create_on"])){
               $where["create_on"] = $data["create_on"];
          }
          foreach ($list as $k => $v) {
             $affiliate_order = [];
             if(!empty($v['affiliate_id'])){
                 //有效
                 $where["affiliate_id"] = $v['affiliate_id'];
                 $where["settlement_status"] = 2;
                 //$affiliate_order = Db::name(AFFILIATE_ORDER)->where($where)->field('affiliate_order_id')->select();
                 // dump(Db::name(AFFILIATE_ORDER)->getLastSql());
                      //有效佣金
                  $total_valid_commission_price = Db::name(AFFILIATE_ORDER)->where($where)->value('sum(total_valid_commission_price) AS commission_price');
                  if(!empty($total_valid_commission_price)){
                      $order_item[$v['affiliate_id']] = $total_valid_commission_price;
                  }
                 //无效
                 $where_invalid["affiliate_id"] = $v['affiliate_id'];
                 $where_invalid["settlement_status"] = ["in","1,2"];
                 $total_commission_price = Db::name(AFFILIATE_ORDER)
                     ->alias("ao")
                     ->join(self::affiliate_order_item." aoi","aoi.affiliate_order_id=ao.affiliate_order_id")
                     ->where($where_invalid)
                     ->value('sum(commission_price) commission_price');
                 $order_invalid[$v['affiliate_id']] = sprintf("%.2f",$total_commission_price - $total_valid_commission_price);
             }
          }
          //dump($order_item);exit;
         return array('order_item'=>$order_item,'order_invalid'=>$order_invalid);
    }
    /**
     * Affiliate订单交易
     * [OrderTransaction description]
     */
    public static function OrderTransaction($data){
          if(empty($data)){
              return;
          }
          $list = Db::name(AFFILIATE_ORDER)->where($data)->order('create_on desc')->select();
          return $list;
    }
     /**
     * 订单详情列表
     * [ListOfDetails description]
     */
    public static function ListOfDetails($data,$page=1,$page_size=30,$countPage = ''){
       if(empty($data)){  return; }
       $list_sku = array();
       $sku_data = array();
       if(empty($countPage)){
          $countPage = Db::name(AFFILIATE_ORDER)->where($data)->count();
       }

       $list = Db::name(AFFILIATE_ORDER)->where($data)->order('add_time desc')->page($page,$page_size)->select();
       if(!empty($list)){
           $affiliate_id = '';
           foreach ($list as $k => $v) {
               if(!empty($v['affiliate_order_id'])){
                  $affiliate_id .= $v['affiliate_order_id'].',';
               }
           }
           if($affiliate_id !=''){
               $where = array();
               $where['affiliate_order_id'] = ['in',rtrim($affiliate_id, ",")];
           $list_sku = Db::name(self::affiliate_order_item)->where($where)->field('affiliate_order_id,sku_id')->order('add_time desc')->select();
           if(!empty($list_sku)){
               foreach ($list_sku as $ke => $va) {
                      $sku_data[$va['affiliate_order_id']] .= $va['sku_id'].',';
               }
           }
              // return Db::name(self::affiliate_order_item)->getLastSql();
           }
       }
       return array('list'=>$list,'list_sku'=>$sku_data,'countPage'=>$countPage);
    }
    /**
     * 订单详情列表从sku开始查
     * [ListOfDetails description]
     */
    public static function ListOfDetailsSku($data = array(),$sku,$page=1,$page_size=30,$countPage=''){
        $list_sku = Db::name(self::affiliate_order_item)->where($sku)->field('affiliate_order_id,sku_id')->order('add_time desc')->select();
        if(!empty($list_sku)){
            $affiliate_id = array();
            foreach ($list_sku as $k => $v) {
                if(!empty($v['affiliate_order_id'])){
                   $affiliate_id[$v['affiliate_order_id']] = $v['affiliate_order_id'];
                }
            }
            $data['affiliate_order_id'] = ['in',implode(",",$affiliate_id)];
            if(empty($countPage)){
               $countPage = Db::name(AFFILIATE_ORDER)->where($data)->count();
            }
            $list = Db::name(AFFILIATE_ORDER)->where($data)->order('add_time desc')->page($page,$page_size)->select();
           // $result=array_intersect($a1,$a2);
        }else{
          return array('list'=>array(),'list_sku'=>array());
        }

        return array('list'=>$list,'list_sku'=>$sku_data,'countPage'=>$countPage);
    }
    /**
     * SKU销售情况统计
     * [SalesStatistics description]
     */
    public static function SalesStatistics($data,$page = 1,$page_size = 30,$countPage=''){
        if(empty($countPage)){
            $countPage =  Db::name(self::affiliate_order_item)->where($data)->group('sku_id')->having('count(sku_count)>=0')->order('sku_count desc')->field('sku_count,sku_id')->count();
        }
        $list = Db::name(self::affiliate_order_item)->where($data)->group('sku_id')->having('count(sku_count)>=0')->order('sku_count desc')->field('sku_count,sku_id,sku_code')->page($page,$page_size)->select();
        return array('list'=>$list,'countPage'=>$countPage);
    }
    /**
     * 获取 affiliate_id订单表对应ID
     * [SalesStatistics_affiliate_id description]
     * @param array   $model_affiliate [description]
     * @param integer $page            [description]
     * @param integer $page_size       [description]
     * @param string  $countPage       [description]
     */
    public static function SalesStatistics_affiliate_id($model_affiliate = array()){
        $list = Db::name(AFFILIATE_ORDER)->where($model_affiliate)->order('add_time desc')->field('affiliate_order_id')->select();
        if(!empty($list)){
            $affiliate_order_id = '';
            foreach ($list as $k => $v) {
                if(!empty($v['affiliate_order_id'])){
                   $affiliate_order_id .= $v['affiliate_order_id'].',';
                }
            }
            if(!empty($affiliate_order_id)){
                return $affiliate_order_id;
            }else{
                return;
            }
        }else{
            return;
        }
    }
    /**
     * 黑名单
     * [blacklist description]
     * @return [type] [description]
     */
    public static function blacklist($model_data,$page=1,$page_size=30,$countPage=''){
        if(empty($countPage)){
             $countPage = Db::name(self::affiliate_black)->where($model_data)->count();
        }
        $list = Db::name(self::affiliate_black)->where($model_data)->page($page,$page_size)->order('add_time desc')->select();
        return array('list'=>$list,'countPage'=>$countPage);
    }
    /**
     * 新增黑名单
     * [add_black description]
     */
    public static function add_black($data=array()){
         return Db::name(self::affiliate_black)->insertAll($data);

    }
    /**
     * 检查
     * [AnExamination description]
     */
    public static function AnExamination($value=''){
          return Db::name(self::affiliate_black)->where(['affiliate_id'=>$value,'status'=>1])->find();
    }
    /**
     * 移除黑名单
     * [delete_black description]
     * @return [type] [description]
     */
    public static function delete_black($model_data=array(),$model_update=array()){
         return Db::name(self::affiliate_black)->where($model_data)->update($model_update);
    }
     /**
     * 分类销售情况
     * [ClassifiedSales description]
     */
    public static function ClassifiedSales($model_data,$page=1,$page_size=30,$countPage='',$data=array()){
        if(empty($countPage)){
             $countPage = Db::name(self::affiliate_order_item)->where($model_data)->count();
        }
        $order_array = array();
        $class_list = array();
        $list = Db::name(self::affiliate_order_item)->where($model_data)->page($page,$page_size)->order('add_time desc')->select();
        if(!empty($list)){
             $data_order = array();
             $class_id = array();
             foreach ($list as $k => $v) {
                 $data_order[$v["affiliate_order_id"]]= $v["affiliate_order_id"];
                 if(!empty($v["first_category_id"])){
                      $class_id[$v["first_category_id"]] = (int)$v["first_category_id"];
                 }
                 if(!empty($v["second_category_id"])){
                      $class_id[$v["second_category_id"]] = (int)$v["second_category_id"];
                 }
                 if(!empty($v["third_category_id"])){
                      $class_id[$v["third_category_id"]] = (int)$v["third_category_id"];
                 }
                 if(!empty($v["four_category_id"])){
                      $class_id[$v["four_category_id"]] = (int)$v["four_category_id"];
                 }
             }

             $data['affiliate_order_id'] =['in',implode(',',$data_order)];
             $order_list = Db::name(AFFILIATE_ORDER)->where($data)->field('affiliate_order_id,order_number,settlement_status,source')->select();
             if(!empty($order_list)){
                 foreach ($order_list as $ke => $va) {
                   $order_array[$va['affiliate_order_id']]['order_number'] = $va['order_number'];
                   $order_array[$va['affiliate_order_id']]['settlement_status'] = $va['settlement_status'];
                   $order_array[$va['affiliate_order_id']]['source'] = $va['source'];
                 }
             }
             if(!empty($class_id)){
                $class_list = self::ClassificationQuery($class_id);
             }
        }
        return array('list'=>$list,'countPage'=>$countPage,'order_array'=>$order_array,'class_list'=>$class_list);
    }
    /**
     * 分类查询
     * [ClassificationQuery description]
     */
    public static  function ClassificationQuery($data = array()){
        $class_id = array();
        if(!empty($data)){
             foreach ($data as $key => $value) {
                 $class_id[] = (int)$value;
             }
        }else{
            return;
        }
        $list = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>['in',$class_id]])->field('id,title_en,pid,type,pdc_ids')->select();
        // dump($list);
        if(!empty($list)){
            $class_array = array();
            foreach ($list as $k => $v) {
               //判断是否为pdc分类如果是，映射到erp分类
               if($v['type'] == 2){
                   $class_array[$v["id"]] = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$v["pdc_ids"][0]])->field('id,title_en,pid,type,pdc_ids')->find();
               }else{
                   $class_array[$v["id"]] = $v;
               }

            }
            return $class_array;
        }else{
            return;
        }
    }
    /*
     *分类销售情况 从主表开始查询
     */
    public static function ClassifiedSalesOrder($model_order,$page,$page_size,$countPage,$model_data=array()){
        if(empty($countPage)){
             $countPage = Db::name(self::affiliate_order_item)->where($model_data)->count();
        }
        $order_array = array();
        $model_data = array();
        $class_list = array();
        $list = array();
        $order_list = Db::name(AFFILIATE_ORDER)->where($model_order)->page($page,$page_size)->field('affiliate_order_id,order_number,settlement_status,source')->select();
        if(!empty($order_list)){
             $order_sku = '';
             foreach ($order_list as $ke => $va) {
               $order_array[$va['affiliate_order_id']]['order_number'] = $va['order_number'];
               $order_array[$va['affiliate_order_id']]['settlement_status'] = $va['settlement_status'];
               $order_array[$va['affiliate_order_id']]['source'] = $va['source'];
               $order_sku .= $va['affiliate_order_id'].',';
             }

             $model_data['affiliate_order_id'] = ['in',rtrim($order_sku,',')];
             $list = Db::name(self::affiliate_order_item)->where($model_data)->order('add_time desc')->select();
             foreach ($list as $k => $v) {
                 if(!empty($v["first_category_id"])){
                      $class_id[$v["first_category_id"]] = (int)$v["first_category_id"];
                 }
                 if(!empty($v["second_category_id"])){
                      $class_id[$v["second_category_id"]] = (int)$v["second_category_id"];
                 }
                 if(!empty($v["third_category_id"])){
                      $class_id[$v["third_category_id"]] = (int)$v["third_category_id"];
                 }
                 if(!empty($v["four_category_id"])){
                      $class_id[$v["four_category_id"]] = (int)$v["four_category_id"];
                 }
             }
             if(!empty($class_id)){
                $class_list = self::ClassificationQuery($class_id);
             }
        }
        // dump(Db::name(AFFILIATE_ORDER)->getLastSql());
        return array('list'=>$list,'countPage'=>$countPage,'order_array'=>$order_array,'class_list'=>$class_list);
    }

}