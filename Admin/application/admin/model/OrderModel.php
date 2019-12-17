<?php
/**
 * User: wang
 * Date: 2019/3/05
 */
namespace app\admin\model;
use app\admin\dxcommon\BaseApi;
use app\admin\services\BaseService;
use think\Log;
use think\Model;
use think\Db;
use think\Session;
class OrderModel  extends Model{
    private $db;
    private $order_table;
    private $order_item_table;
    public function __construct(){
        $this->db = "db_order";
        $this->table = "dx_sales_order_message";
        $this->order_table = "dx_sales_order";
        $this->order_item_table = "dx_sales_order_item";
    }
    /**
     * 退款
     * [Refund description]
     * @param [type] $data [description]
     */
    public static function FullRefund($data = []){
      $order_data = [];
      $error_result = '';

      $DataDetection = self::DataDetection($data,1);
      if($DataDetection['code'] !=200){
         return json_encode($DataDetection);
      }
      $show_message = '';
      foreach ($DataDetection['data'] as $k => $v) {
            $refund_id = '';
            $param = [];
            $getOrderDetail = BaseApi::getOrderDetail(['orderNumber'=>$v[0]]);
            $order_after_sale_apply = BaseApi::getOrderRefundInfo(['order_number'=>$v[0]]);
            // if(!empty($order_after_sale_apply["data"]['status']) &&  $order_after_sale_apply["data"]['status'] == 2){
            //    continue;
            // }
            if(empty($getOrderDetail['data']['captured_amount']) || $getOrderDetail['data']['captured_amount'] < 0){
                $show_message .="<p style='color: red'>".$v[0]." 退款失败，原因：退款金额为0 或者该订单已退</p>";
                 Log::record('admin批量退款全额退款金额为0 或者该订单已退 error：'.'订单表order_id：'.$v[0]);
                 continue;
            }
            $param['refunded_fee'] = $param['captured_refunded_fee'] = $getOrderDetail['data']['captured_amount'];
            $param['order_id'] = $getOrderDetail['data']['order_id'];
            $param['order_number'] = $getOrderDetail['data']['order_number'];
            $param['customer_id'] = $getOrderDetail['data']['customer_id'];
            $param['customer_name'] = $getOrderDetail['data']['customer_name'];
            $param['store_name'] = $getOrderDetail['data']['store_name'];
            $param['store_id'] = $getOrderDetail['data']['store_id'];
            $param['type'] = 1;
            $param['status'] = 1;
            // $param['after_sale_reason'] = $v[1];
            $param['remarks'] = $v[1];
            $param['initiator'] = 3;//3表示后台介入
            $param['add_time'] = time();
            $param['refund_status'] = 1;
            $param['applicant_admin_id'] = session("userid");
            $param['applicant_admin'] = session("username");
            //记录申请表
            if(!empty($order_after_sale_apply["code"])){
                /*判断之前是否有退款，如果之前有退款中的则不能继续退款*/
                $refund_status = !empty($order_after_sale_apply['data']['status'])?$order_after_sale_apply['data']['status']:0;
                if($refund_status == 1){
                    $show_message .="<p style='color: red'>".$v[0]." 退款失败，原因：订单状态为退款中不能再次退款</p>";
                    Log::record('FullRefund 后台全款退款失败，订单状态为退款中不能再次退款，'.json_encode($v));
                    continue;
                }
                $res = BaseApi::saveOrderRefund($param);
                  if(!empty($res['code']) && $res['code'] == 200){
                        $refund_id = $res['data'];
                        //添加退款sku详情
                        if(!empty($getOrderDetail['data']["itemList"])){
                            foreach ($getOrderDetail['data']["itemList"] as $ke => $ve){
                                $param_sku = [];
                                $param_sku[$ke]['refund_id'] = $refund_id;
                                $param_sku[$ke]['product_id'] = $ve['product_id'];
                                $param_sku[$ke]['sku_id'] = $ve['sku_id'];
                                $param_sku[$ke]['sku_num'] = $ve['sku_num'];
                                $param_sku[$ke]['product_name'] = $ve['product_name'];
                                $param_sku[$ke]['product_img'] = $ve['product_img'];
                                $param_sku[$ke]['product_nums'] = $ve['product_nums'];
                                $param_sku[$ke]['product_price'] = $ve['product_price'];
                            }
                            $save_order_refund_item = BaseApi::save_order_refund_item(['param_sku'=>$param_sku]);
                            if(empty($save_order_refund_item['code']) || $save_order_refund_item['code'] != 200){
                                $show_message .="<p style='color: red'>".$v[0]." 退款详情申请提交失败,退款api返回结果：".$save_order_refund_item['msg']."</p></br>";
                                $error_result  .= '退款申请提交失败 :'.$v[0].',';
                                Log::record('save_order_refund_item->退款详情申请提交失败'.print_r($save_order_refund_item, true));
                                continue;
                            }
                        }
                      $show_message .="<p style='color: green'>".$v[0]." 退款申请提交成功</p></br>";
                      Log::record('saveOrderRefund->退款申请提交成功'.print_r($res, true));
              }else{
                  $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败,退款api返回结果：".$res['msg']."</p></br>";
                  $error_result  .= '退款申请提交失败 :'.$v[0].',';
                  Log::record('saveOrderRefund->退款申请提交失败'.print_r($res, true));
              }
            }else{
                $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败,原因：退款详情接口调用失败</p></br>";
                Log::record('FullRefund 退款申请提交失败');
            }
      }

      return json_encode(array('code'=>200,'data'=>'退款申请执行完成！','msg'=>$show_message));

    }
    /**
     * 订单部分退款而且不退货
     * [PartialRefund description]
     */
    public static function PartialRefund($data){
          $error_result = '';
          $DataDetection = self::DataDetection($data,3);
          if($DataDetection['code'] !=200){
             return json_encode($DataDetection);
          }
          $show_message = '';
          foreach ($DataDetection['data'] as $k => $v) {
                $param = [];
                $up_param = [];
                $getOrderDetail = BaseApi::getOrderDetail(['orderNumber'=>$v[0]]);
                $order_after_sale_apply = BaseApi::getOrderRefundInfo(['order_number'=>$v[0]]);
                // if(!empty($order_after_sale_apply["data"]['status']) &&  $order_after_sale_apply["data"]['status'] == 5){
                //     Log::record('admin退款重复error：'.'订单表order_id：'.$v[0]);
                //     continue;
                // }
                if(empty($getOrderDetail['data']['captured_amount'])
                  || $getOrderDetail['data']['captured_amount'] <= 0
                  || $v[1] > $getOrderDetail['data']['captured_amount']){
                    $show_message .="<p style='color: red'>".$v[0]." 退款失败，原因：退款金额为0 或者该订单已退完 或者退款大于总金额</p>";
                    Log::record('admin批量退款部分退款金额为0 或者该订单已退完 或者退款大于总金额 error：'.'订单表order_id：'.$v[0]);
                    continue;
                }
              if(empty($v[1]) || $v[1] <= 0){
                  $show_message .="<p style='color: red'>".$v[0]." 退款失败，原因：退款金额为0 或者该订单退款信息有误</p>";
                  Log::record('admin批量退款部分退款金额为0 或者该订单退款信息有误 error：'.'订单表order_id：'.$v[0]);
                  continue;
              }
                $param['refunded_fee'] = $param['captured_refunded_fee'] = $v[1];
                $param['order_id'] = $getOrderDetail['data']['order_id'];
                $param['order_number'] = $getOrderDetail['data']['order_number'];
                $param['customer_id'] = $getOrderDetail['data']['customer_id'];
                $param['customer_name'] = $getOrderDetail['data']['customer_name'];
                $param['store_name'] = $getOrderDetail['data']['store_name'];
                $param['store_id'] = $getOrderDetail['data']['store_id'];
                $param['type'] = 3;
                $param['status'] = 1;
                $param['refunded_type'] = 1;
                // $param['after_sale_reason'] = $v[1];
                $param['remarks'] = $v[2];
                $param['initiator'] = 3;//3表示后台介入
                $param['add_time'] = time();
                $param['refund_status'] = 2;
                if(!empty($order_after_sale_apply["code"])){
                    $refund_status = !empty($order_after_sale_apply['data']['status'])?$order_after_sale_apply['data']['status']:0;
                    if($refund_status == 1){
                        $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败，原因：订单状态为退款中不能再次退款</p>";
                        Log::record('PartialRefund 后台批量部分退款失败，订单状态为退款中不能再次退款，'.json_encode($v));
                        continue;
                    }
                    $res = BaseApi::saveOrderRefund($param);
                }else{
                    $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败，原因：订单退款详情接口调用失败</p>";
                    Log::record('PartialRefund 后台批量部分退款失败，订单退款详情接口调用失败');
                }
                if (!empty($res['code']) && $res['code'] == 200){
                    $show_message .="<p style='color: green'>".$v[0]." 退款申请提交成功</p></br>";
                    Log::record('saveOrderRefund->退款申请提交成功'.print_r($res, true));
                }else{
                    $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败，原因：未添加到申请表或申请表添加失败</p>";
                    $error_result  .= 'Submission failure, 未添加到申请表或申请表添加失败';
                }
          }
          return json_encode(array('code'=>200,'data'=>'退款申请执行完成！','msg'=>$show_message));
    }
    /**
     * 部分SKU退款
     * [SomeSkuRefunds description]
     */
    public static function SomeSkuRefunds($data){
          $error_result = '';
          $itemList = [];
          $DataDetection = self::DataDetection($data,2);
          if($DataDetection['code'] !=200){
             return json_encode($DataDetection);
          }
          $show_message = '';
          foreach ($DataDetection['data'] as $k => $v) {
                $param = [];
                $up_param = [];
                $captured_price = 0;
                $getOrderDetail = BaseApi::getOrderDetail(['orderNumber'=>$v[0]]);
                if(!empty($getOrderDetail["data"]["itemList"])){
                    foreach ($getOrderDetail["data"]["itemList"] as $ke=> $ve) {
                       $itemList[$ve['sku_num']] = $ve;
                    }
                    $getOrderDetail["data"]["itemList"] = $itemList;
                }
                $shipping_fee = 0;
                /*对比数据后得到的退款的sku数组*/
                $sku_arr = array();
                foreach ($v[1] as $ky => $vy) {
                    if(isset($itemList[$vy[0]]["sku_num"])){
                        $sku_arr[] = $itemList[$vy[0]]["sku_num"];
                        /**
                         * 判断当前sku数量是否足够
                         */
                        if(!empty($itemList[$vy[0]]["sku_num"])){
                             if(empty($itemList[$vy[0]]["product_nums"]) || $itemList[$vy[0]]["product_nums"] < $vy[1]){
                                   $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败，原因：sku退款数量大于现有数量</p>";
                                   Log::record('批量退款sku退款数量大于现有数量 error：'.'订单表order_id：'.$v[0]);
                                   continue;
                             }
                        }
                         //判断该产品是否有运费要退
                        if( !empty($DataDetection['shipping_fee'][$v[0]][$vy[0]]["shipping_fee"]) ){
                             $shipping_fee += $DataDetection['shipping_fee'][$v[0]][$vy[0]]["shipping_fee"];
                        }
                        $PaidAmount = 0;
                        //如果coupon_price大于0 则获取实收金额captured_price
                        if($itemList[$vy[0]]['coupon_price'] > 0){
                            $PaidAmount = $itemList[$vy[0]]['captured_price'] * $vy[1];
                        }else if($itemList[$vy[0]]['coupon_price'] == 0.00 && ($getOrderDetail['coupon_id'] == '' || $getOrderDetail['coupon_id'] == 0)){
                            $PaidAmount = $itemList[$vy[0]]['captured_price'] * $vy[1];
                        }else if($itemList[$vy[0]]['coupon_price'] == 0.00 && $getOrderDetail['coupon_id'] >0){
                             //如果coupon_price为0 和 coupon_id不为0需要算等比例价格
                            $PaidAmount = (1-($getOrderDetail["discount_total"]/$getOrderDetail["goods_total"])) * $itemList[$vy[0]]['captured_price'] * $vy[1];
                        }
                        $v[1][$ky]['captured_price'] =  $PaidAmount;
                        if($captured_price == 0){
                           $captured_price =  $PaidAmount;
                        }else{
                           $captured_price += $PaidAmount;
                        }
                        $PaidAmount = 0;
                    }
                }
                $captured_price += $shipping_fee;//产品总值加运费总值
                if(empty($sku_arr) || $captured_price <= 0){
                    $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败，原因：退款金额为0 或者该订单退款信息有误</p>";
                    Log::record('admin批量退款部分退款金额为0 或者该订单退款信息有误 error：'.'订单表order_id：'.$v[0]);
                    continue;
                }
                $order_after_sale_apply = BaseApi::getOrderRefundInfo(['order_number'=>$v[0]]);
                // if(!empty($order_after_sale_apply["data"]['status']) &&  $order_after_sale_apply["data"]['status'] == 5){
                //     Log::record('admin退款部分产品重复error：'.'订单表order_id：'.$v[0]);
                //     continue;
                // }
                //$order_after_sale_apply["code"] = 100;//每次都新增一条数据
                $param['refunded_fee'] = $param['captured_refunded_fee'] = $captured_price;
                $param['order_id'] = $getOrderDetail['data']['order_id'];
                $param['order_number'] = $getOrderDetail['data']['order_number'];
                $param['customer_id'] = $getOrderDetail['data']['customer_id'];
                $param['customer_name'] = $getOrderDetail['data']['customer_name'];
                $param['store_name'] = $getOrderDetail['data']['store_name'];
                $param['store_id'] = $getOrderDetail['data']['store_id'];
                $param['type'] = 3;
                $param['status'] = 1;
                $param['refunded_type'] = 1;
                // $param['after_sale_reason'] = $v[1];
                $param['remarks'] = $v[2];
                $param['initiator'] = 3;//3表示后台介入
                $param['add_time'] = time();
                $param['refund_status'] = 2;//dump($order_after_sale_apply);exit;
                $param['sku_refund'] = serialize($v);
                //记录申请表
                if(!empty($order_after_sale_apply["code"])){
                    $refund_status = !empty($order_after_sale_apply['data']['status'])?$order_after_sale_apply['data']['status']:0;
                    if($refund_status == 1){
                        $show_message .="<p style='color: red'>".$v[0]." 退款失败，原因：订单状态为退款中不能再次退款</p>";
                        Log::record('SomeSkuRefunds 后台批量部分退款失败，订单状态为退款中不能再次退款，'.json_encode($v));
                        continue;
                    }
                    Log::record('批量退款SKU参数日志->'.json_encode($param));
                    $res = BaseApi::saveOrderRefund($param);
                    if(!empty($res['code']) && $res['code'] == 200){
                        $refund_id = $res['data'];
                        //添加退款sku详情
                        if(!empty($getOrderDetail['data']["itemList"])){
                            foreach ($v[1] as $ke => $ve){
                                $param_sku = [];
                                $param_sku[$ke]['refund_id'] = $refund_id;
                                $param_sku[$ke]['product_id'] = $itemList[$ve[0]]['product_id'];
                                $param_sku[$ke]['sku_id'] = $itemList[$ve[0]]['sku_id'];
                                $param_sku[$ke]['sku_num'] = $itemList[$ve[0]]['sku_num'];
                                $param_sku[$ke]['product_name'] = $itemList[$ve[0]]['product_name'];
                                $param_sku[$ke]['product_img'] = $itemList[$ve[0]]['product_img'];
                                $param_sku[$ke]['product_nums'] = $ve[1];
                                $param_sku[$ke]['product_price'] = $itemList[$ve[0]]['product_price'];

                            }
                            $save_order_refund_item = BaseApi::save_order_refund_item(['param_sku'=>$param_sku]);
                            if(empty($save_order_refund_item['code']) || $save_order_refund_item['code'] != 200){
                                $show_message .="<p style='color: red'>".$v[0]." 退款详情申请提交失败,退款api返回结果：".$save_order_refund_item['msg']."</p></br>";
                                $error_result  .= '退款申请提交失败 :'.$v[0].',';
                                Log::record('save_order_refund_item->退款详情申请提交失败'.print_r($save_order_refund_item, true));
                                continue;
                            }
                        }
                        $show_message .="<p style='color: green'>".$v[0]." 退款申请提交成功</p></br>";
                        Log::record('saveOrderRefund->退款申请提交成功'.print_r($res, true));
                    }else{
                        $show_message .="<p style='color: red'>".$v[0]." 退款申请提交失败,退款api返回结果：".$res['msg']."</p></br>";
                        $error_result  .= '退款申请提交失败 :'.$v[0].',';
                        Log::record('saveOrderRefund->退款申请提交失败'.print_r($res, true));
                    }
                }else{
                    $show_message .="<p style='color: red'>".$v[0]." 退款失败，原因：订单退款详情接口调用失败</p>";
                    Log::record('SomeSkuRefunds 后台批量部分退款失败，订单退款详情接口调用失败');
                }
          }
          return json_encode(array('code'=>200,'data'=>'退款申请执行完成！','msg'=>$show_message));

    }
    /**
     *数据检测
     */
    public static function DataDetection($data,$i=0){
           $order_data = [];
           $result_data = [];
           $order_number = '';
           $order_number_data = [];
           if($i == 1){
                  foreach ($data as $k => $v) {
                       if(!empty($v)){
                          $result_data[] = $order_data = explode(',',$v);
                          if(empty($order_data[0]) || !is_numeric($order_data[0])){
                              return array('code'=>100,'data'=>$order_data[0].'订单必须纯数字');
                          }else if(empty($order_data[1])){
                              return array('code'=>100,'data'=>'退款原因不能留空');
                          }
                          $order_number_data[$order_data[0]] = $order_data[0];
                          $order_number .= $order_data[0].',';
                       }
                   }
                   $order_number = rtrim($order_number,',');
                   $OrderDetection = BaseApi::OrderDetection(['order'=>$order_number,'status'=>1]);
                   if($OrderDetection['code'] !=200 || empty($OrderDetection['data'])){
                          return array('code'=>100,'data'=>'订单未达到退款要求');
                   }else{
                       foreach ($OrderDetection['data'] as $ke => $ve) {
                           if(!empty($order_number_data[$ve['order_number']])  ){
                               unset($order_number_data[$ve['order_number']]);
                           }
                       }
                       if(!empty($order_number_data)){
                              return array('code'=>100,'data'=>'以上数据未达到退款要求'.json_encode($order_number_data));
                       }
                   }
                   return array('code'=>200,'data'=>$result_data);
           }else if($i == 2){
                  $shipping_fee = [];
                  foreach ($data as $k => $v) {
                       if(!empty($v)){
                           $order_data = explode(',',$v);
                           $order_data[1] = explode('|',$order_data[1]);
                           // $order_number_data[$order_data[0]]['order_number'] = $order_data[0];
                           foreach ($order_data[1] as $ke => $ve) {
                                $sku_num = [];
                                $sku_code = [];
                                $sku_num =  explode('-',$ve);
                                if(empty($sku_num[0]) || empty($sku_num[1]) || !is_numeric($sku_num[0])  || !is_numeric($sku_num[1])){
                                    return array('code'=>100,'data'=>$order_data[0].'对应sku或者数量有误');
                                }
                                $order_data[1][$ke] = $sku_num;
                                $order_number_data[$order_data[0]][$sku_num[0]]['sku'] =$sku_num[0];
                                $order_number_data[$order_data[0]][$sku_num[0]]['sum'] =$sku_num[1];
                           }

                           $order_number  .= $order_data[0].',';
                           $result_data[] = $order_data;
                           if(empty($order_data[0]) || !is_numeric($order_data[0])){
                              return array('code'=>100,'data'=>$order_data[0].'订单必须纯数字');
                           }else if(empty($order_data[1])){
                              return array('code'=>100,'data'=>'sku数据不能为空');
                           }else if(empty($order_data[2])){
                              return array('code'=>100,'data'=>'退款原因不能留空');
                           }
                       }
                   }
                   $order_number = rtrim($order_number,',');
                   $OrderDetection = BaseApi::OrderDetection(['order'=>$order_number,'status'=>2]);
                   if($OrderDetection['code'] !=200 || empty($OrderDetection['data'])){
                        return array('code'=>100,'data'=>'订单未达到退款要求');
                   }else{
                        foreach ($OrderDetection['data'] as $ky => $vy) {
                           if(!empty($order_number_data[$vy['order_number']])){
                                foreach ($vy["list_sku"] as $kt => $vt) {
                                    if(!empty($vt["sku_num"]) && !empty($order_number_data[$vy['order_number']][$vt["sku_num"]]["sku"])){
                                          if($order_number_data[$vy['order_number']][$vt["sku_num"]]["sum"] > $vt["product_nums"]){
                                              return array('code'=>100,'data'=>'订单：'.$vy['order_number'].'sku数量不能大于购买数量');
                                          }else if($vt["shipping_model"] == 'NOCNOC'){
                                              return array('code'=>100,'data'=>'订单：'.$vy['order_number'].'NOCNOC订单不支持退部分产品');
                                          }
                                          //如果退款数量等于该产品总数量，就需要推运费
                                          if($vt["product_nums"] == $order_number_data[$vy['order_number']][$vt["sku_num"]]["sum"]){
                                              $shipping_fee[$vy['order_number']][$vt["sku_num"]]['shipping_fee'] = $vt['shipping_fee'];
                                          }
                                    }
                                }
                                unset($order_number_data[$vy['order_number']]);
                           }
                        }
                        if(!empty($order_number_data)){
                            return array('code'=>100,'data'=>'以下数据未达到退款要求'.json_encode($order_number_data));
                        }
                   }

                   return array('code'=>200,'data'=>$result_data,'shipping_fee'=>$shipping_fee);
           }else if($i == 3){
                   foreach ($data as $k => $v) {
                       if(!empty($v)){
                          $result_data[] = $order_data = explode(',',$v);
                          if(empty($order_data[0]) || !is_numeric($order_data[0])){
                              return array('code'=>100,'data'=>$order_data[0].'订单必须纯数字');
                          }else if(empty($order_data[1])){
                              return array('code'=>100,'data'=>'退款金额不能留空');
                          }else if(empty($order_data[2])){
                              return array('code'=>100,'data'=>'退款原因不能留空');
                          }else{
                               if(!is_numeric(str_replace(["."],[''],$order_data[1]))){
                                    return array('code'=>100,'data'=>$order_data[1].'金额有误');
                               }else if(substr_count($order_data[1], '.') > 1){
                                    return array('code'=>100,'data'=>$order_data[1].'金额有误');
                               }
                          }
                          $order_number_data[$order_data[0]] = $order_data[0];
                          $order_number .= $order_data[0].',';
                       }
                   }
                   $order_number = rtrim($order_number,',');
                   $OrderDetection = BaseApi::OrderDetection(['order'=>$order_number,'status'=>1]);
                   if($OrderDetection['code'] !=200 || empty($OrderDetection['data'])){
                          return array('code'=>100,'data'=>'订单未达到退款要求');
                   }else{
                       foreach ($OrderDetection['data'] as $ke => $ve) {
                           if(!empty($order_number_data[$ve['order_number']])  ){
                               unset($order_number_data[$ve['order_number']]);
                           }
                       }
                       if(!empty($order_number_data)){
                              return array('code'=>100,'data'=>'以上数据未达到退款要求'.json_encode($order_number_data));
                       }
                   }
                   return array('code'=>200,'data'=>$result_data);
           }
    }

    /*
     * 直接执行SQL
     */
    public  function orderSql($sql){
        $config=config('db_order');
        return Db::connect($config)->query($sql);
    }

    /*
     * 获取总数量
     */
    public  function getCount($where){
        $config=config('db_order');
        return Db::connect($config)->table('dx_sales_order')->where($where)->count();
    }

    public function getOrderInfo(){
        $beginLastweek  =date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d')-date('w')+1-7,date('Y')));

        $endLastweek    =date('Y-m-d H:i:s',mktime(23,59,59,date('m'),date('d')-date('w')+7-7,date('Y')));
        
        $where = array(
            'lock_status'=>60,
            'order_status'=>['between','200,1300'],
            'add_time'=>['between',"{$beginLastweek},{$endLastweek}"],
        );
        $info = Db::connect($this->db)->table('dx_sales_order')->where($where)->where('order_master_number','>',0)->field(['count(*)'=>'order_number','sum(goods_count)'=>'sku_number','sum(grand_total/exchange_rate)'=>'order_amount'])->find();

        $where = array(
            'lock_status'=>60,
            'order_status'=>['between','200,407'],
            'add_time'=>['between',"{$beginLastweek},{$endLastweek}"],
        );
        $weekSum = Db::connect($this->db)->table('dx_sales_order')->where($where)->where('order_master_number','>',0)->sum('grand_total/exchange_rate');
        
        $where = array(
            'lock_status'=>60,
            'order_status'=>['between','200,407'],
        );
        $sum = Db::connect($this->db)->table('dx_sales_order')->where($where)->where('order_master_number','>',0)->sum('grand_total/exchange_rate');
        
        $info['week_amount'] = $weekSum;
        $info['all_amount'] = $sum;
        return $info;
    }

    /**
     * 根据订单号获取订单数据
     * @param $order_number
     * @param string $field
     * @param boolean $cache
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderInfoByOrderNumber($order_number,$field="*",$cache=false){
        $data = [];
        $redis = (new BaseService())->redis;
        $cache_key = md5('getOrderInfoByOrderNumber_'.$order_number.$field);
        if ($cache === true){
            $data = $redis->get($cache_key);
        }
        if (empty($data)){
            $data = Db::connect($this->db)->table($this->order_table)->where(['order_number'=>$order_number])->field($field)->find();
            if ($cache === true){
                $redis->set($cache_key, $data, 60*60*3);
            }
        }
        return $data;
    }

    /**
     * 查询订单金额
     * @param $start_time
     * @param $end_time
     * @return array
     * @throws \Exception
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function getQueryOrderAmount($start_time, $end_time){
        //含风控
        $sql = "SELECT COUNT(*) AS order_num,SUM(captured_amount_usd) AS order_amount FROM `dx_sales_order` WHERE  ( `create_on` >= ".$start_time." AND `create_on` < ".$end_time." AND `order_status` IN (400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000) AND `order_master_number` <> '0')
OR ( `create_on` >= ".$start_time." AND `create_on` < ".$end_time." AND `order_status` =120  AND `order_branch_status` =105 AND `order_master_number` <> '0')";
        //不包含风控
        $sql_2 = "SELECT COUNT(*) AS order_num,SUM(captured_amount_usd) AS order_amount FROM `dx_sales_order` WHERE  `create_on` >= ".$start_time." AND `create_on` < ".$end_time." AND `order_status` IN (400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000) AND `order_master_number` <> '0'";

        $have_risk = Db::connect($this->db)->query($sql);
        $have_no_risk = Db::connect($this->db)->query($sql_2);
        return ['have_risk'=>$have_risk, 'have_no_risk'=>$have_no_risk];
    }

    public function getRmaOrderInfoByOrderId($order_id, $field='order_number,remark'){
        return Db::connect($this->db)->table($this->order_table)->where(['parent_id'=>$order_id])->field($field)->select();
    }

}