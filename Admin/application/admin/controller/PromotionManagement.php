<?php
namespace app\admin\controller;

use think\Exception;
use think\Log;
use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use think\Paginator;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\Base;
use app\admin\model\PromotionManagement as Promotion;
use app\admin\dxcommon\Mongo;
use app\admin\dxcommon\Common;
use app\admin\services\ActivityService;


/*
 * 商城促销
 * AddTime:2018-04-18
 * author: Wang
 *
 */
class PromotionManagement extends Action
{
    private $actService;
  	public function __construct(){
         Action::__construct();
         define('ACTIVITY', 'activity');//mysql数据表
         define('ACTIVITY_SKU', 'activitySku');//mysql数据表
         define('ACTIVITY_SPU', 'activity_spu');//mysql数据表
         defined("PRO_CLASS") OR define('PRO_CLASS', 'dx_product_class');//Nosql数据表
         defined("S_CONFIG") OR define('S_CONFIG', 'dx_sys_config');//mongodb数据表
         define('PRODUCT', 'dx_product');//mongodb数据表产品表
         //活动变更历史表
         define('MOGOMODB_ACTIVITY_HISTORY', 'dx_activity_histories');
        //mongodb活动报名的产品表
         define('MOGOMODB_PRODUCT_ACTIVITY', 'dx_product_activity');
         
         $this->actService = new ActivityService();
      }
  	/*
  	 *会员列表3
     *author: Wang
     *AddTime:2018-04-19
  	 */
  	public function index()
  	{
      if($data = request()->post()){
            if($data['activity_title']){
               $where['activity_title'] = array('like','%'.$data['activity_title'].'%');
            }
            if($data['status']){
               $where['status'] = $data['status'];
            }else{
               $where['status'] = array('neq',3);
            }
            $list = Db(ACTIVITY)->where($where)->order('add_time desc')->paginate(20);//dump($list);
            if($data['activity_title']){
                  $where['activity_title'] = $data['activity_title'];
            }
            $this->assign(['where'=>$where]);
      }else{
        $where['status'] = array('neq',3);
        $list = Db(ACTIVITY)->where($where)->order('add_time desc')->paginate(20);//dump($list);
      }
      $statusDict = Common::dictionariesQuery('Activity_Status');
      $ActivityType = $this->dictionariesQuery('ActivityType');
      $list_data = $list->items();
      /* 弃用时间判断，改用状态
      foreach ($list_data as $key => $value) {
        $time = time();
        $tatus_text = '';
        //var_dump($value['registration_start_time']); //1530781200
        //echo '<br>';
        //var_dump($time); //1530782350
        //die();
        if($value['registration_start_time'] > $time){
            $tatus_text= '报名未开始';
        }else{
            if($value['registration_end_time'] >= $time){
                $tatus_text= '报名中';
            }elseif($value['registration_end_time'] < $time && $value['activity_start_time'] > $time){
                $tatus_text= '报名结束';
            }else{
                if($value['activity_end_time'] < $time){
                    $tatus_text= '活动结束';
                }else{
                    if($value['activity_start_time'] > $time){
                        $tatus_text= '活动未开始';
                    }else{
                        if($value['activity_end_time'] >= $time){
                            $tatus_text= '活动进行中';
                        }
                    }
                }
            }
        }
          $list_data[$key]['s_status'] = $tatus_text;
      }
      */
      // 'mall_base_url'=>config('mall_url')；
      $mall_base_url = config('mall_url');
      $this->assign(['list'=>$list_data,'page'=>$list->render(),'ActivityType'=>$ActivityType,'mall_base_url'=>$mall_base_url,'activityStautsDict'=>$statusDict]);
      //没什么意义，仅仅是为了兼容前端页面的变量问题，不知道以前的开发者在前端页面弄这几个变量是干啥的
      $this->assign(['Navigation'=>'','where'=>['activity_title'=>'','status'=>'']]);
  		return View('promotionList');
  	}

    /**
     * 添加活动
     * author: Wang
     * AddTime:2018-04-18
     */
    public function add_activity(){
        if($data = request()->post()){
            $data['add_user_name'] = Session::get('username');
            $data['add_time']      = time();
            $data['activity_status']      = 0;  //活动数据插入时默认状态是0-报名未开始，如果是当前时间的数据，则由定时监控重新修改状态
            $data_array =  Promotion::judgment($data);
            unset($data_array["where"],$data_array["first_level_mongo"],$data_array["first_level_mongo"],$data_array["second_level_mongo"],$data_array['third_level_mongo'],$data_array["fourth_level_mongo"],$data_array["class_url"]);
            $data_common = (object)[];
            if (isset($data_array['common']) && !empty($data_array['common'])){
                $data_common = $this->addPerson($data_array['common']);
            }
            $data_array['common'] = json_encode($data_common);
            /* 只有Flash Deals校验同一时间段只允许配置一场活动，其他类型的活动不做校验，允许同时间段配置多个！
            //检查时间是否重叠--add by heng.zhang 2018-07-13 TODO
            $checkResult =$this -> checkTime(2,$data_array['registration_start_time'],$data_array['registration_end_time']);
            if($checkResult){
                echo json_encode(array('code'=>100,'result'=>'活动报名时间重复'));
                exit;
            }
            */

            //如果是Flash Deals活动
            if ($data_array['type'] == 5){
                /************
                 * Flash Deals修改 start
                 *  修改规则
                 *  （1）.去掉一天多期活动拆分；
                    （2）.去掉报名时间；
                    （3）.活动之间活动时间可以不连贯；
                    （4）.活动时间不能有重叠；
                 *  （5）.其他细节：其他类型活动不变、创建活动、审核活动、task、产品活动数据等处理
                 *
                 * tinghu.liu 20190903
                 * ************/
                $check_time_for_fd_res = $this->check_time_for_flash_deals($data_array['activity_start_time'],$data_array['activity_end_time']);
                if($check_time_for_fd_res){
                    echo json_encode(array('code'=>100,'result'=>'活动时间重复'));
                    exit;
                }
                $this->add_activity_for_flash_deals($data_array);
                exit;
                /********* Flash Deals修改 end *********/

                 $flag = true;
                 //获取活动场次配置
                 $activity_dt_arr = Base::getActivityFlashDealsData($data_array['activity_start_time'], $data_array['activity_end_time']);
                 if(!$activity_dt_arr){
                    echo json_encode(array('code'=>100,'result'=>'获取配置场次出错'));
                    exit;
                 }
                 foreach ($activity_dt_arr as $key=>$info){
                     if ($key === 0){
                         $activity_title = $data_array['activity_title'];
                     }
                     unset($data_array['_id']);
                     //重新组装参数
                     $data_array['activity_title'] = $activity_title.' '.$info['name'];
                     $data_array['activity_start_time'] = strtotime($info['start_dt']);
                     $data_array['activity_end_time'] = strtotime($info['end_dt']);
                     //检查时间是否重叠--add by heng.zhang 2018-07-13
                     $checkResult =$this -> checkTime(2,$data_array['activity_start_time'],$data_array['activity_end_time']);
                     if($checkResult){
                         echo json_encode(array('code'=>100,'result'=>'活动开始时间重复'));
                         exit;
                     }
                     //还原数据。将common转化为json格式，因为经过一次循环添加到MongoDB后会自动解json
                     if ($key !== 0){
                         $data_array['common'] = json_encode($data_array['common']);
                     }
                     $id = Db::name(ACTIVITY)->insertGetId($data_array);
                     //var_dump($id);
                     if($id){
                         $data_array['_id']       = (int)$id;
                         $data_array['common']    = json_decode($data_array['common'],true);
                         if (isset($data_array['className'])){
                             $data_array['className'] = json_decode($data_array['className']);
                         }

                         $result = Db::connect("db_mongo")->name('dx_activity')->insert($data_array);
                         //var_dump($result);
                         if(!$result){
                             $flag = false;
                             break;
                         }else{
                             //插入变更历史--活动表
                             $data_histroy['EntityId'] = (int)$id;
                             $data_histroy['CreatedDateTime'] = time();
                             $data_histroy['IsSync'] = false;
                             $data_histroy['Note'] = Session::get('userName').'新增活动';
                             $result_History = Db::connect("db_mongo")->name(MOGOMODB_ACTIVITY_HISTORY)->insert($data_histroy);
                         }
                     }else{
                         $flag = false;
                         break;
                     }
                 }
                 if (!$flag){
                     echo json_encode(array('code'=>100,'result'=>'提交失败'));
                     exit;
                 }else{
                     echo json_encode(array('code'=>200,'result'=>'提交成功'));
                     exit;
                 }
            }else{
                 $id = Db::name(ACTIVITY)->insertGetId($data_array);
                 if($id){
                     $data_array['_id']       = (int)$id;
                     $data_array['common']    = json_decode($data_array['common'],true);
                     if (isset($data_array['className'])){
                         $data_array['className'] = json_decode($data_array['className']);
                     }
                     $result = Db::connect("db_mongo")->name('dx_activity')->insert($data_array);
                     if($result){
                         //插入变更历史--活动表
                         $data_histroy['EntityId'] =(int) $id;
                         $data_histroy['CreatedDateTime'] = time();
                         $data_histroy['IsSync'] = false;
                         $data_histroy['Note'] = Session::get('userName').'新增活动';
                         $result_History = Db::connect("db_mongo")->name(MOGOMODB_ACTIVITY_HISTORY)->insert($data_histroy);
                         echo json_encode(array('code'=>200,'result'=>'提交成功'));
                         exit;
                     }else{
                         echo json_encode(array('code'=>100,'result'=>'录入商城数据库失败'));
                         exit;
                     }
                 }else{
                     echo json_encode(array('code'=>100,'result'=>'提交失败'));
                     exit;
                 }
            }
        }else{
            $langs = BaseApi::langs();//语种
            $ActivityType = $this->dictionariesQuery('ActivityType');//获取字典
            $classList = Db::connect("db_mongo")->name(PRO_CLASS)->where(['pid'=>0])->field('id,title_cn,title_en')->select();//dump($ActivityType);
            $this->assign(['classList'=>$classList,'ActivityType'=>$ActivityType,'langs'=>$langs["data"],'time' =>time()]);
            return View('add_activity');
        }
    }

    /**
     * 增加flash deals活动
     * @param $data_array
     */
    private function add_activity_for_flash_deals($data_array){
        $id = Db::name(ACTIVITY)->insertGetId($data_array);
        if($id){
            $data_array['_id']       = (int)$id;
            $data_array['common']    = json_decode($data_array['common'],true);
            if (isset($data_array['className'])){
                $data_array['className'] = json_decode($data_array['className']);
            }
            $result = Db::connect("db_mongo")->name('dx_activity')->insert($data_array);
            if($result){
                //插入变更历史--活动表
                $data_histroy['EntityId'] =(int) $id;
                $data_histroy['CreatedDateTime'] = time();
                $data_histroy['IsSync'] = false;
                $data_histroy['Note'] = Session::get('userName').'新增活动';
                $result_History = Db::connect("db_mongo")->name(MOGOMODB_ACTIVITY_HISTORY)->insert($data_histroy);
                echo json_encode(array('code'=>200,'result'=>'提交成功'));
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'录入商城数据库失败'));
                exit;
            }
        }else{
            echo json_encode(array('code'=>100,'result'=>'提交失败'));
            exit;
        }
    }

    /**
     * @param $type 检查的类型，1:检查报名时间，2:检查开始时间
     * @param $startTime 开始时间
     * @param $endTime 结束时间
     * @return  true 时间重叠，false 未重叠
     */
    private function checkTime($type,$startTime,$endTime){
        if(empty($type) || empty($startTime) || empty($endTime)){
            return true;
        }
        if($type === 1){
            $where['registration_start_time'] = array('>=',$startTime);
            $where['registration_start_time'] = array('<=',$endTime);
        }elseif($type === 2){
            $where['activity_start_time'] = array('>=',$startTime);
            $where['activity_end_time'] = array('<=',$endTime);
        }
        $result = Db::connect("db_mongo")->name('dx_activity')->where($where)->count();
        return $result>0;
    }

    /**
     * 检查活动时间，不允许有重叠
     * @param $startTime
     * @param $endTime
     * @return bool
     */
    private function check_time_for_flash_deals($startTime,$endTime){
        /*$count = Db::name(ACTIVITY)
            ->whereOr(function ($q1) use ($startTime, $endTime){
                $q1->where('activity_start_time', '>=', $startTime)
                    ->where('activity_end_time', '<=', $endTime);
            })
            ->whereOr(function ($q2) use ($startTime){
                $q2->where('activity_start_time', '<=', $startTime)
                    ->where('activity_end_time', '>=', $startTime);
            })
            ->whereOr(function ($q3) use ($endTime){
                $q3->where('activity_start_time', '<=', $endTime)
                    ->where('activity_end_time', '>=', $endTime);
            })
            ->count();*/
        /********* 为了使用索引，不使用OR的形式 ********/
        $count1 = Db::name(ACTIVITY)
            ->where('activity_start_time', '>=', $startTime)
            ->where('activity_end_time', '<=', $endTime)
            ->count();
        $count2 = Db::name(ACTIVITY)
            ->where('activity_start_time', '<=', $startTime)
            ->where('activity_end_time', '>=', $startTime)
            ->count();
        $count3 = Db::name(ACTIVITY)
            ->where('activity_start_time', '<=', $endTime)
            ->where('activity_end_time', '>=', $endTime)
            ->count();
        return ($count1+$count2+$count3)>0;
    }

     /**
     * 修改活动信息
     * author: Wang
     * AddTime:2018-04-19
     */
    public function edit_activity(){
        $id           = input('id');//P_CLASS
        $activity2 = Db::connect("db_mongo")->name('dx_activity')->where(['_id'=>(int)$id])->find();
         if($data = request()->post()){
            if(!$data["id"]){
                echo json_encode(array('code'=>100,'result'=>'获取该数据id失败'));
                exit;
            }
            $data['edit_user_name'] = Session::get('username');
            $data['edit_time']      = time();
            $data_array =  Promotion::judgment($data);
            unset($data_array["where"],$data_array["first_level_mongo"],$data_array["first_level_mongo"],$data_array["second_level_mongo"],$data_array['third_level_mongo'],$data_array["fourth_level_mongo"],$data_array["class_url"]);

            $data_array['common'] = json_encode($data_array['common']);
            $result = Db(ACTIVITY)->where(['id'=>$data["id"]])->update($data_array);
            if($result){
                  // $data_array['_id']       = (int)$id;
                  $data_array['common']    = json_decode($data_array['common'],true);
                  $data_array['className'] = isset($data_array['className'])?json_decode($data_array['className'],true):'';

                //如果en发生变化,则别的标题也需要翻译
                if($activity2['common']['en']['title']!=$data_array['common']['en']['title']){
                    foreach($data_array['common'] as $key=>&$val){
                        if($val['title']!=$activity2['common'][$key]['title']){
                            $val['is_person']=1;   //如果当前标题发生变化,则不需要翻译
                        }else{
                            $val['is_person']=0;
                        }
                    }
                }else{//如果en不发生变化,则别的标题也不需要翻译
                    foreach($data_array['common'] as $key=>&$val){
                        $val['is_person']=1;
                    }
                }

                  $result_update = Db::connect("db_mongo")->name('dx_activity')->where(['_id'=>(int)$data_array["id"]])->update($data_array);
                  if($result_update){
                      //插入变更历史--活动表
                      $data_histroy['EntityId'] = (int)$data_array["id"];
                      $data_histroy['CreatedDateTime'] = time();
                      $data_histroy['IsSync'] = false;
                      $data_histroy['Note'] = Session::get('userName').'编辑活动';
                      $result_History = Db::connect("db_mongo")->name(MOGOMODB_ACTIVITY_HISTORY)->insert($data_histroy);
                      echo json_encode(array('code'=>200,'result'=>'提交成功'));
                      exit;
                  }else{
                      echo json_encode(array('code'=>100,'result'=>'录入商城数据库失败'));
                      exit;
                  }
             }else{
                echo json_encode(array('code'=>100,'result'=>'提交失败'));
                exit;
             }
         }else{
            $ActivityType = $this->dictionariesQuery('ActivityType');//获取字典
            $classList = Db::connect("db_mongo")->name(PRO_CLASS)->where(['pid'=>0])->field('id,title_cn,title_en')->select();
            $activity  = Db::name('activity')->where(['id'=>$id])->find();

            $className = json_decode($activity["className"],true);
            $langs = BaseApi::langs();//币种
             $html = '';
             $i= '';
            if($className){
              foreach ($className as $key => $value) {
                 $html .= '<dl class="c-h-dl-validator form-group clearfix delete'.$key.'"><dd class="v-title w100"><label><em>*</em>选中的分类：</label></dd><dd><input value="'.$value["classname"].'" name="className['.$key.'][classname]" readonly="readonly" class="form-control input-medium fl w360" type="text"><input type="hidden" name="className['.$key.'][classid]"  value="'.$value["classid"].'"><a class="eliminate-btn2 eliminate'.$key.'" onclick="delect_dl('.$key.')" href="javascript:;">删除</a></dd></dl>';
                  $i = $key;//用于前端添加新项
              }
            }
            $common = $activity2["common"];
            if($common){
               $html_common = '';
               foreach ($common as $k => $v) {
                 $html_common .= '<dl class="c-h-dl-validator form-group clearfix delete'.$v["code"].'"> <dd class="v-title"><label><em>*</em>简码：</label></dd><dd><div class="input-icon right"><input value="'.$v['code'].'" readonly="readonly" name="common['.$v['code'].'][code]" id="input-color-en" class="form-control input-medium fl w60" type="text"></div></dd><dd class="v-title"><label class="w60">活动类型：</label></dd><dd><div class="input-icon right"><input value="'.$v['type'].'" name="common['.$v['code'].'][type]" id="input-color-en" class="form-control input-medium fl" type="text"></div></dd><dd class="v-title"><label class="w40">标题：</label></dd><dd><div class="input-icon right"><input value="'.$v['title'].'" name="common['.$v['code'].'][title]" id="input-color-en" class="form-control input-medium fl w500" type="text"></div></dd><a class="eliminate-btn eliminate'.$v['code'].'" onclick="delect_langs(\''.$v['code'].'\')" href="javascript:;">删除</a><dt></dt></dl>';
               }
            }
            $time = time();
            $exhibition           = input('exhibition');//P_CLASS
            if($activity["registration_start_time"] <= $time){
               $exhibition = 1;//用于是否展示按钮
            }
            $this->assign(['classList'=>$classList,'ActivityType'=>$ActivityType,'activity'=>$activity,'html'=>$html,'i'=>$i,'html_common'=>$html_common,'langs'=>$langs["data"],'exhibition'=>$exhibition,'time' =>time()]);
            return View('add_activity');
         }
    }
    /**
     * 获取子集分类
     * author: Wang
     * AddTime:2018-04-19
     */
    public function catalog_next(){
        $this->catalogNext($sql='_mongo');
    }
    /**
     * 审核列表
     * author: Wang
     * AddTime:2018-04-18
     */
    public function checkList(){
          if($data = request()->post()){
             if($data['product_id']){
                $date['product_id'] = $where['product_id'] = $data['product_id'];
             }
              if($data['code']){
                  $date['code'] = $where['code'] = $data['code'];
              }
             $date['activity_id']   = $where['id']    = $data['id'];
             $where['activity_title'] = $data['activity_title'];
             //dump($date);
             $list_spu = Db::name(ACTIVITY_SPU)->where($date)->order('add_time asc')->paginate(20);
             $list = $list_spu->items();
             // $this->assign(['where'=>$where,]);
          }else{
             $where['id']             = input('id');//P_CLASS
             $where['activity_title'] = input('activity_title');//P_CLASS
             $list_spu = Db::name(ACTIVITY_SPU)->where(['activity_id'=>$where['id']])->order('add_time asc')->paginate(20);
              //dump($list_spu->items());
              //die();
             $list = $list_spu->items();//dump($list);

          }
          foreach ($list as $key =>$value) {
                $list[$key]['sku'] = Db::name(ACTIVITY_SKU)->where(['activity_id'=>$where['id'],'product_id'=>$value['product_id']])->order('add_time asc')->select();
                if($list[$key]['sku']){
                  foreach ($list[$key]['sku'] as $k => $v) {
                      //批量设置时，单位为折扣
                      if ($v['set_type'] == 1){
                          $list[$key]['sku'][$k]['activity_price'] = round($v['sales_price'] * ($v['discount']/100), 2);
                      }
                  }
                }
          }
            //dump($list);
          $this->assign(['list'=>$list,'where'=>$where,'page'=>$list_spu->render(),]);
          return View('checkList');
    }

    /**
    * 审核活动
    * author: heng.zhang
    * AddTime:2018-07-28
    */
    public function checkActivity(){
        if($data = request()->post()){
            $spuTableInfoid = (int)$data['id'];
            //status 为2表示通过 ，为3表示不通过
            if($data['status'] == 2){
                $andOther['sales_price']=['>',0];
                $andOther['discount']=['>',0];
                $andOther['activity_inventory']=['>',0];
                //检查SKU的库存
                $spuModel = Db::name(ACTIVITY_SPU)->where(['id'=>$spuTableInfoid,'status'=>1])->find();
                if(empty($spuModel)){
                    echo json_encode(array('code'=>100,'result'=>'数据不存在或已审核过'));
                    exit;
                }
                $list_sku = Db::name(ACTIVITY_SKU)
                    ->where(['activity_id'=>(int)$spuModel['activity_id'],'product_id'=>(int)$spuModel['product_id'],'seller_id'=>(int)$spuModel['seller_id']])
                    ->where($andOther)
                    ->field('sku,sales_price,set_type,discount,activity_id,code,product_id,activity_inventory,activity_price')
                    ->select();
                $productModel = Db::connect("db_mongo")->name(PRODUCT)->where(['_id'=>(int)$spuModel['product_id']])
                    ->field('Skus,LowPrice,DiscountLowPrice')->find();
                $activity = Db::name(ACTIVITY)->where(['id'=>(int)$spuModel['activity_id']])->field('activity_start_time,activity_end_time')->find();
                if(empty($spuTableInfoid) || empty($spuModel) || empty($list_sku) || empty($productModel)){
                    //TODO Wirte log
                    echo json_encode(array('code'=>100,'result'=>'获取活动数据出错'));
                    exit;
                }
                $DiscountPrice = array();
                $Discount      = array();
                $spu_activity_inventory =0;
                //待更新入库的数组
                $updateModel['ActivityID'] = (int)$spuModel['activity_id'];
                $updateModel['SPU'] = (int)$spuModel['product_id'];
                foreach ($list_sku as $k => $v) {
                    $sku_data = $v;
                    foreach ((array)$productModel["Skus"] as $ke => $va) {
                        if($v['sku'] == $va["_id"]){
                            //活动库存不可以大于在售库存
                            if($sku_data['activity_inventory'] > $va["Inventory"]){
                                //TODO Wirte log
                                //continue;//退出本次循环
                                //用实际库存作为活动库存
                                $sku_data['activity_inventory']=$va["Inventory"];
                            }
                            $sku_discount = round($sku_data['discount']/100,2);
                            $Discount[] = $data_array['Discount'] = $sku_discount; //商城直接使用 N*0.6 这样的方式计算
                            $sku_discount_price = round($sku_data['sales_price'] * $sku_discount,2);
                            if(isset($sku_data['set_type'])  && $sku_data['set_type'] == 2){
                                $sku_discount_price = $sku_data['activity_price'];
                            }
                            $DiscountPrice[] = $data_array['DiscountPrice'] = $sku_discount_price;
                            //$data_array['ActivityId']          = $sku_data['activity_id'];
                            $data_array['SalesLimit']          = $sku_data['activity_inventory'];
                            $data_array['SetType']          = $sku_data['set_type'];
                            $spu_activity_inventory += $sku_data['activity_inventory'];
                            //$data_array['ActivityStartTime']   = $activity['activity_start_time'];
                            //$data_array['ActivityEndTime']     = $activity['activity_end_time'];
                            if(empty($data_array['Discount']) || empty($data_array['DiscountPrice'])
                                || empty($data_array['SalesLimit'])|| empty($data_array['SetType'])){
                                //TODO Wirte log
                                echo json_encode(array('code'=>100,'result'=>'活动数据错误无法通过审核'));
                                break;
                            }
                            $updateModel["Skus"][$ke]['_id'] = $va["_id"];
                            $updateModel["Skus"][$ke]['Code'] = $va["Code"];
                            $updateModel["Skus"][$ke]['ActivityInfo'] = $data_array;
                        }
                    }
                }
                //dump($productModel);
                $DiscountLowPrice   = min($DiscountPrice);//折扣后最低价格 --搜索使用
                $DiscountHightPrice = max($DiscountPrice);//折扣后最高价 --搜索使用
                $HightDiscount      = min($Discount);//最高折扣比例 --按SPU打折，不按skus
                //$updateModel['SKU'] = $productModel["Skus"];
                $updateModel['DiscountLowPrice'] = $DiscountLowPrice;
                $updateModel['DiscountHightPrice'] = $DiscountHightPrice;
                $updateModel['HightDiscount'] = $HightDiscount;
                //$updateModel['IsActivity'] = (int)$spuModel['activity_id'];
                $updateModel['InventoryActivity'] = $spu_activity_inventory; //SPU总的参与活动的库存量
                $updateModel['InventoryActivitySalse'] = (int)0;
                $updateModel['AddTime'] = time();
                //unset($productModel['Skus']);
                //dump($updateModel);
                //die();
                unset($productModel);
                //exit();
                //原生写法
                //$mongo = new Mongo(PRODUCT);
                $result =0;
                try{
                    /*
                    //更新产品表IsActivity,禁止修改产品资料
                    $resultP = Db::connect("db_mongo")->name(PRODUCT)
                                    ->where('_id',$updateModel['SPU'])
                                    ->update(['IsActivity'=>$updateModel['ActivityID'],'EditTime'=>time()]);
                                    // Db::connect("db_mongo")->name(PRODUCT)->getLastSql();
                    */
                    //插入活动产品表
                    $result = Db::connect("db_mongo")->name(MOGOMODB_PRODUCT_ACTIVITY)->insert($updateModel);
                    //dump($result);
                }catch (Exception $e){
                    //exit();
                    return apiReturn(['code'=>1006,'msg'=>$e->getMessage()]);
                }
                //die();
                //exit();
                if($result){
                    $update['status'] = $data['status'];
                    $update['edit_user'] = Session::get('username');
                    $update['edit_time'] = time();
                    $update['reason'] = '审核通过';
                    $result = Db::name(ACTIVITY_SPU)->where(['id'=>$spuTableInfoid])->update($update);
                    if($result){
                        echo json_encode(array('code'=>200,'result'=>'数据提交成功!'));
                        exit;
                    }else{
                        //TODO Write Log
                        echo json_encode(array('code'=>100,'result'=>'更新活动数据失败!'));
                        exit;
                    }
                }else{
                    //TODO Write Log
                    echo json_encode(array('code'=>100,'result'=>'更新商城产品数据失败!'));
                    exit;
                }
            }else if($data['status'] == 3){
                $update['status'] = $data['status'];
                $update['edit_user'] = Session::get('username');
                $update['edit_time'] = time();
                $update['reason'] = $data['reason'];
                $result = Db::name(ACTIVITY_SPU)->where(['id'=>$spuTableInfoid])->update($update);
                if($result){
                    echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
                    exit;
                }else{
                    echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
                    exit;
                }
            }
        }
    }

    /**
     * 审核
     * author: Wang
     * AddTime:2018-04-20
     */
    public function checkWang(){
        if($data = request()->post()){
            $spuTableInfoid = (int)$data['id'];
            //status 为2表示通过 ，为3表示不通过
            if($data['status'] == 2){
                //检查SKU的库存
                $spuModel = Db::name(ACTIVITY_SPU)->where(['id'=>$spuTableInfoid])->find();
                $list_sku = Db::name(ACTIVITY_SKU)
                                ->where(['activity_id'=>(int)$spuModel['activity_id'],'product_id'=>(int)$spuModel['product_id'],'seller_id'=>(int)$spuModel['seller_id']])
                                ->field('sku,sales_price,set_type,discount,activity_id,code,product_id,activity_inventory,activity_price')
                                ->select();
                $productModel = Db::connect("db_mongo")->name(PRODUCT)->where(['_id'=>(int)$spuModel['product_id']])
                                ->field('Skus,LowPrice,DiscountLowPrice')->find();
                $activity = Db::name(ACTIVITY)->where(['id'=>(int)$spuModel['activity_id']])->field('activity_start_time,activity_end_time')->find();
                if(empty($spuTableInfoid) || empty($spuModel) || empty($list_sku) || empty($productModel)){
                    //TODO Wirte log
                    echo json_encode(array('code'=>100,'result'=>'获取活动数据出错'));
                    exit;
                }
                $DiscountPrice = array();
                $Discount      = array();
                $spu_activity_inventory =0;
                foreach ($list_sku as $k => $v) {
                    $sku_data = $v;
                    foreach ((array)$productModel["Skus"] as $ke => $va) {
                        if($v['sku'] == $va["_id"]){
                            // echo $sku_data['activity_inventory'].'-----'.$va["Inventory"].';';
                            //活动库存不可以大于在售库存
                            if($sku_data['activity_inventory'] > $va["Inventory"]){
                                //TODO Wirte log
                                //continue;//退出本次循环
                                //用实际库存作为活动库存
                                $sku_data['activity_inventory']=$va["Inventory"];
                            }
                            $sku_discount = round($sku_data['discount']/100,2);
                            $Discount[] = $data_array['Discount'] = $sku_discount; //商城直接使用 N*0.6 这样的方式计算
                            $sku_discount_price = round($sku_data['sales_price'] * $sku_discount,2);
                            if(isset($sku_data['set_type'])  && $sku_data['set_type'] == 2){
                                $sku_discount_price = $sku_data['activity_price'];
                            }
                            $DiscountPrice[] = $data_array['DiscountPrice'] = $sku_discount_price;
                            $data_array['ActivityId']          = $sku_data['activity_id'];
                            $data_array['SalesLimit']          = $sku_data['activity_inventory'];
                            $data_array['SetType']          = $sku_data['set_type'];
                            $spu_activity_inventory += $sku_data['activity_inventory'];
                            $data_array['ActivityStartTime']   = $activity['activity_start_time'];
                            $data_array['ActivityEndTime']     = $activity['activity_end_time'];
                            if(empty($data_array['Discount']) || empty($data_array['DiscountPrice']) || empty($data_array['ActivityId'])
                                || empty($data_array['ActivityStartTime']) || empty($data_array['ActivityEndTime'])){
                                //TODO Wirte log
                                echo json_encode(array('code'=>100,'result'=>'活动数据错误无法通过审核'));
                                break;
                            }
                            $productModel["Skus"][$ke]['ActivityInfo'] = $data_array;
                        }
                    }
                 }
                $DiscountLowPrice   = min($DiscountPrice);//折扣后最低价格 --搜索使用
                $DiscountHightPrice = max($DiscountPrice);//折扣后最高价 --搜索使用
                $HightDiscount      = min($Discount);//最高折扣比例 --按SPU打折，不按skus
                $productModel['DiscountLowPrice'] = $DiscountLowPrice;
                $productModel['DiscountHightPrice'] = $DiscountHightPrice;
                $productModel['HightDiscount'] = $HightDiscount;
                $productModel['IsActivity'] = (int)$spuModel['activity_id'];
                $productModel['InventoryActivity'] = $spu_activity_inventory; //SPU总的参与活动的库存量
                $productModel['InventoryActivitySalse'] = (int)0;
                unset($productModel['_id']);
                //exit();
                //原生写法
                //$mongo = new Mongo(PRODUCT);
                $result =0;
                try{
                    //先更新商城产品表
                    $result = Db::connect("db_mongo")->name(PRODUCT)->insert(['_id' =>(int)$spuModel['product_id']], ['$set'=>$productModel]);
                    //dump($result);
                }catch (Exception $e){
                    //exit();
                    return apiReturn(['code'=>1006,'msg'=>$e->getMessage()]);
                }
                //exit();
                if($result){
                    $update['status'] = $data['status'];
                    $update['edit_user'] = Session::get('username');
                    $update['edit_time'] = time();
                    $update['reason'] = '审核通过';
                    $result = Db::name(ACTIVITY_SPU)->where(['id'=>$spuTableInfoid])->update($update);
                    if($result){
                        echo json_encode(array('code'=>200,'result'=>'数据提交成功!'));
                        exit;
                    }else{
                        //TODO Write Log
                        echo json_encode(array('code'=>100,'result'=>'更新活动数据失败!'));
                        exit;
                    }
                }else{
                    //TODO Write Log
                    echo json_encode(array('code'=>100,'result'=>'更新商城产品数据失败!'));
                    exit;
                }
            }else if($data['status'] == 3){
                $update['status'] = $data['status'];
                $update['edit_user'] = Session::get('username');
                $update['edit_time'] = time();
                $update['reason'] = $data['reason'];
                $result = Db::name(ACTIVITY_SPU)->where(['id'=>$spuTableInfoid])->update($update);
                if($result){
                    echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
                    exit;
                }else{
                    echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
                    exit;
                }
            }
        }
    }
    /**
     * 审核通过后对产品改成活动价格
     * [edit_product_activity description]
     * $data  审核通后sku 需要推送商城的部分数据
     * @return [type] [description]
     * author: Wang
     * AddTime:2018-04-21

    //使用原生的方法重写了更新操作
    public function edit_product_activity($value = array()){

          $activity_time = Db::name(ACTIVITY)->where(['id'=>$value['activity_id']])->find();
          if(!$activity_time){
                    return false;
          }
          $list_sku = Db::name(ACTIVITY_SKU)
                         ->where(['activity_id'=>$value['activity_id'],'product_id'=>$value['product_id'],'seller_id'=>$value['seller_id']])
                        ->field('sku,sales_price,set_type,discount,activity_id,code,product_id,activity_inventory')
                         ->select();
          $list = Db::connect("db_mongo")->name(PRODUCT)->where(['_id'=>(int)$value['product_id']])
                            ->field('DefaultSkuId,Skus,product_id,LowPrice,DiscountLowPrice')->find();
            if(!empty($list)){

            }



          $DiscountPrice = array();
          $Discount      = array();
          foreach ($list_sku as $k => $v) {
                $data = $v;
                foreach ((array)$list["Skus"] as $ke => $va) {

                    if($v['sku'] == $va["_id"]){

                        $Discount[] = $data_array['Discount']            = 1 - $data['discount']/100;
                        if($data['set_type'] == 1){
                          $DiscountPrice[] = $data_array['DiscountPrice']    = round($data['sales_price'] * ($data['discount']/100),2);
                        }else if($data['set_type'] == 2){
                          $DiscountPrice[] = $data_array['DiscountPrice']    = $data['sales_price'];
                        }

                        $data_array['ActivityId']          = $data['activity_id'];
                        $data_array['SalesLimit']          = $data['activity_inventory'];
                        $data_array['ActivityStartTime']   = $activity_time['activity_start_time'];
                        $data_array['ActivityEndTime']     = $activity_time['activity_end_time'];
                        if(empty($data_array['Discount']) || empty($data_array['DiscountPrice']) || empty($data_array['ActivityId']) || empty($data_array['ActivityStartTime']) || empty($data_array['ActivityEndTime'])){
                            return false;
                            // return array('code'=>100,'result'=>'必填信息存在为空');
                        }
                        $list["Skus"][$ke]['ActivityInfo'] = $data_array;
                        $data_price = '';

                    }
                }
          }
          $DiscountLowPrice   = min($DiscountPrice);//折扣后最低价格 --搜索使用
          $DiscountHightPrice = max($DiscountPrice);//折扣后最高价 --搜索使用
          $HightDiscount      = min($Discount);//最高折扣比例 --按SPU打折，不按skus
          $result = Db::connect("db_mongo")->name(PRODUCT)
              ->where(['_id'=>(int)$list["_id"]])->
              update(['Skus'=>(object)$list["Skus"],
                  'DiscountLowPrice'=>$DiscountLowPrice,
                  'DiscountHightPrice'=>$DiscountHightPrice,
                  'HightDiscount'=>$HightDiscount,
                  'IsActivity'=>(int)$data['activity_id']
              ]);
          // dump($data['activity_id']]);
          // dump($list["Skus"]);

          if($result){
             return true;
          }else{
            return false;
          }
    }
    */

    /**
     * 逻辑删除活动
     * author: Wang
     * AddTime:2018-04-20
     */
    public function delete_activity(){
        if($data = request()->post()){
           if($data['id']){
             $date['status']  = 3;
             $date['edit_user_name']  = Session::get("username");
             $date['edit_time']  = time();
             $where['status'] = 2;//只有下线了的活动才可以删除
             $where['id'] =$data['id'];
             $list = Db::name(ACTIVITY)->where($where)->update($date);
             if($list){
                echo json_encode(array('code'=>200,'result'=>'删除成功'));
                exit;
             }else{
                echo json_encode(array('code'=>100,'result'=>'删除失败'));
                exit;
             }
           }else{
             echo json_encode(array('code'=>100,'result'=>'没有获取到对应id值'));
             exit;
           }
        }
    }
    /*
     * 添加一个字段
     */
    private function addPerson($data){
        foreach($data as &$value){
            $value['is_person']=1;
        }
        return $data;
    }

    /*
     * 新增历史旧数据
     */
    public function addOldActivity(){
        $where['_id']=['<',449];
        $activityList = Db::connect("db_mongo")->name('dx_activity')->field('_id')->where($where)->select();
        $res=0;
        foreach ($activityList as $key=>$info){
            //插入变更历史--活动表
            $where2['EntityId']=(int)$info["_id"];
            $count=Db::connect("db_mongo")->name(MOGOMODB_ACTIVITY_HISTORY)->where($where2)->count('*');
            if(!$count){
                $data_histroy['EntityId'] = (int)$info["_id"];
                $data_histroy['CreatedDateTime'] = time();
                $data_histroy['IsSync'] = false;
                $data_histroy['Note'] = '历史活动';
                $res = Db::connect("db_mongo")->name(MOGOMODB_ACTIVITY_HISTORY)->insert($data_histroy);
            }
        }
        return $res;
    }

    public function upload(){
        $params = request()->post();
        if( empty($params['activity_id']) && !is_numeric($params['activity_id']) ){
            $this->error('活动ID格式错误');
        }
        $activity_id = $params['activity_id'];

        $res = $this->actService->checkAct($activity_id);
        if( !$res ){
            $this->error('活动ID不存在');   
        }

        $file = request()->file('file');
        
        if (empty($file)) {
            $this->error('请选择上传文件');
        }
        
        $info = $file->validate(['ext' => 'xls,xlsx'])->move(ROOT_PATH . 'public' . DS . 'uploads');
        if ( !$info ) {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
        
        $filename = $info->getRealPath();

        $fileId = $this->actService->addActFileLog($filename,$activity_id);
        
        if( !$fileId ){
            $this->error('保存文件记录失败！');
        }

        $res = $this->actService->saveFileData($filename,$fileId,$activity_id);
        
        if( $res['code']==200 ){
            $this->success("文件上传成功！");
        }
        $this->error($res['msg']);
    }

    public function subProduct(){
        set_time_limit(0);

        $id = input('id');
        if( empty($id) ){
            echo json_encode(['code'=>400,'msg'=>'活动id错误！']);exit;
        }

        $res = $this->actService->subProduct($id);
        if( $res['code']==200 ){
            $this->success("活动产品发布成功！");
            echo json_encode(['code'=>200,'msg'=>'活动产品发布成功！']);exit;
        }
        echo json_encode(['code'=>400,'msg'=>$res['msg']]);exit;

    }

    public function checkAct(){
        set_time_limit(0);

        $id = input('id');
        if( empty($id) ){
            echo json_encode(['code'=>400,'msg'=>'活动id错误！']);exit;
        }

        $res = $this->actService->checkActData($id);
        if( $res['code']==200 ){
            echo json_encode(['code'=>200,'msg'=>'活动产品审核成功！']);exit;
        }
        echo json_encode(['code'=>400,'msg'=>$res['msg']]);exit;
    }
}
