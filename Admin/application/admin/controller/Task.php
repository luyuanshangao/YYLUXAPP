<?php
namespace app\admin\controller;
use \think\Session;
use think\View;
use think\Controller;
use think\Paginator;
use think\Db;
use app\admin\dxcommon\BaseApi;
use app\admin\model\Businessmanagement as Business;

/**
 * 工具类:历史数据同步
 * 1.产品类别，产品数据
 * @author pc
 *
 */
class Task
{
     public function __construct(){
       // Action::__construct();
       $this->ProductClass               = Db('ProductClass');
       $this->ClassAttribute             = Db('ProductClassAttribute');
       $this->ProductClassAttributeValue = Db('ProductClassAttributeValue');
       define('P_CLASS', 'ProductClass');
       define('MOGOMODB_P_CLASS', 'dx_product_class');
       define('P_C_A', 'ProductClassAttribute');
       define('P_C_A_V', 'ProductClassAttributeValue');
       define('Brand_M', 'BrandManagement');

       define('LOGISTICS', 'LogisticsManagement');

        define('S_CONFIG', 'dx_sys_config');//Nosql数据表
    }
    /**同步类别数据
     *
     * @return isView：1 代表执行类别同步
     */
    public function index($isView='')
    {
       if(!empty($isView)){
       	  echo '$isView:'. $isView;
       	  $attribute = Db::connect("db_mongo_old")->name("Category")->where(['_id'=>0])->select();
       	  //$attribute = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>4])->find();//存mongodb

       }
       return view('Tool\index');
    }


  /**
   * [LMS description]
   * 同步LMS系统渠道数据数据
   * author: Wang
   * AddTime:2018-04-26
   */
  public function LMS(){
     //类型用于判断是否带电的条件
     $type = array(
        '0'=>1,
        '1'=>2,
        '2'=>3,
      );
     //获取字典中的物流模板
     $shipping = $this->dictionariesQuery('ShippingServiceMethod');
     return json_encode(array('code'=>200,'type'=>$type,'shipping'=>$shipping),true);
  }
   //字典数据的获取
  protected function dictionariesQuery($val){
          $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
          $data = explode(";",$PayemtMethod['ConfigValue']);
          foreach ($data as $key => $value) {
             $list[] = explode(":",$value);
          }
          return $list;
  }

  /**
   * [LMS description]
   * 同步LMS系统渠道数据数据
   * author: Wang
   * AddTime:2018-04-26
   */
  public function LMS1(){
       // $data['country'] = $_POST['country'];
       // $data['type']    = $_POST['type'];
       // $data['local']   = $_POST['local'];
     $type = array(
        '0'=>1,
        '1'=>2,
        '2'=>3,
      );
     $shipping = $this->dictionariesQuery('ShippingServiceMethod');dump($shipping);exit;
     foreach ($shipping as $k => $v) {
         foreach ($type as $key => $value) {
             // $data['country'] = 'US';
             $data['type']          = $value;
             $data['local']         = "CN";//出口地暂写中国
             $data['shipping_type'] = $v['0'];
             $result  = json_decode(BaseApi::LMS($data),true);
             if(count($result)>0){
              $data['shippingServiceText'] = $v['1'];
                $update_result = $this->update_Logistics($data,$result);
                if(!$update_result){
                     $date['result'] .= '修改失败;';
                }
             }else{
                     // $date['result'] .= '没获取到数据;';
             }
         }
     }
     //零时使用与判断
     if($date){
         echo json_encode(array('code'=>100,'result'=>$date));
         exit;
     }else{
         echo json_encode(array('code'=>200,'result'=>'更新成功'));
         exit;
     }
  }
    // //字典数据的获取
    // public function dictionariesQuery($val){
    //       $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
    //       $data = explode(";",$PayemtMethod['ConfigValue']);
    //       foreach ($data as $key => $value) {
    //          $list[] = explode(":",$value);
    //       }
    //       return $list;
    // }
  /**修改 或添加物流列表
   * [update_Logistics description]
   * $data  条件数组
   * $result  结果 数组
   * @return [type] [description]
   */
  public function update_Logistics($data,$result){
       $logistics = Db::name(LOGISTICS);
       $result_update = '';
       foreach ($result as $key => $value) {
           if($value['code'] == 200){
             $list = $logistics->where(['countryCode'=>$value['country'],'shippingServiceID'=>$data['shipping_type']])->find();
             if($list){//["isCharged"]
                 if($list['freight'] != $value["price"] || $list['time_slot'] != $value["about_time"] || $list['countryENName'] != $value["name_en"] || $list['isCharged'] != $value["isCharged"]){
                     $date['freight']       = $value['price'];
                     $date['time_slot']     = $value['about_time'];
                     $date['countryENName'] = $value['name_en'];
                     $date['isCharged']     = $value['isCharged'];
                     $date['shippingServiceText'] = $data['shippingServiceText'];
                     $date['edit_author'] = '系统更新';
                     $date['edit_time']   = time();
                     $result_update = $logistics->where(['countryCode'=>$value["country"],'shippingServiceID'=>$data['shipping_type']])->update($date);

                     if(!$result_update){
                        $result_edit .= '修改失败;';
                     }
                 }
             }else{
                $date['freight']       = $value['price'];
                $date['time_slot']     = $value['about_time'];
                $date['countryENName'] = $value['name_en'];
                $date['shippingServiceText'] = $data['shippingServiceText'];
                $date['isCharged']     = $value['isCharged'];
                $date['countryCode']   = $value['country'];
                $date['shippingServiceID']   = $data['shipping_type'];
                $date['add_author']   = '系统更新';
                $date['add_time']     = time();
                $date['country_local']     = 'CN';//暂时默认中国
                $result_update = $logistics->insert($date);
                if(!$result_update){
                        $result_edit .= '添加失败;';
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
   /**测试方法
    * [redis_rudui description]
    * @return [type] [description]
    */
   public function redis_rudui(){

      $redis = redis();
      // $redis->set('key', 'This is a test!uiyiui', 0, 60);usleep()微妙
      // $val = $mem->get('key');echo $val;exit;
      try{
        $value = 'value_'.date('Y-m-d H:i:s');
        $redis->LPUSH('key1',$value);//左边添加 元素
        // $redis->rPush('key1',$value);//尾部 添加 元素
       echo  $redis->LPOP('key1');
      }catch(Exception $e){
        echo $e->getMessage()."\n";
      }
   }
   /***测试方法
    * [redis_chudui description]
    * @return [type] [description]
    */
   public function redis_chudui(){
     $redis = redis();
     while(True){
     try{
        echo $redis->LPOP('key1')."\n";
     }catch(Exception $e){
        echo $e->getMessage()."\n";
     }
     sleep(rand()%3);
  }
   }
   /***测试方法
    * [redis_bubianli description]
    * @return [type] [description]
    */
   public function redis_bubianli(){
     $redis = redis();
     try{
        echo $redis->LPOP('key1')."\n";
     }catch(Exception $e){
        echo $e->getMessage()."\n";
     }
  //    sleep(rand()%3);
  //     while(True){
  // }
    // $redis->set('test','hello redis');
    // echo $redis->get('test');
   }

}
