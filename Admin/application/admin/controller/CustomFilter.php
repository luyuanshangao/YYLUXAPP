<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Loader;
use think\Exception;
use think\Log;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\ExcelTool;
use app\admin\dxcommon\FTPUpload;


/**
 * 运营管理--EMD营销--用户筛选
 * Add by:zhangheng
 * AddTime:2018-06-20
 * Info:
 *     1.运营管理--EMD营销--用户筛选:查询，修改，删除
 */
class CustomFilter extends Action
{
    const edm_query_result =  'dx_edm_query_result';
    const edm_customer_data =  'dx_edm_customer_data';
    const edm_order_data =  'dx_edm_order_data';
	public function __construct(){
       Action::__construct();
       // define('REGION', 'Region');//物流管理部
         define('SCREENING', 'screening_management');//mysql存储查询条件表
         define('SALES_ORDER', 'sales_order');//mysql存储查询条件表
         define('MOGOMODB_P_CLASS', 'dx_product_class');//mysql存储查询条件表
         define('NATIONAL_LANGUAGE', 'national_language');//mysql存储查询条件表
         define('MOGOMODB_REGION', 'dx_region');//mysql存储查询条件表
    }

    /**
     * 筛选任务管理--查询
     */
    public function index()
    {
        $data = input();
        //列表信息
        $where = [];
        $where['Parent_id'] = 0;

        if(!empty($data["TopicName"])){
            $where['TopicName'] = array('like', '%'.$data['TopicName'].'%');
        }
        if(!empty($data["startTime"]) && !empty($data["endTime"])){
            $where['add_time'] = array('between',strtotime($data["startTime"]).','.strtotime($data["endTime"]));
        }
        if(!empty($data["status"])){
            $where['status'] = $data["status"];
        }

        $page_size = config('paginate.list_rows');

        $ret = Db::name(SCREENING)->where($where)->order('add_time','desc')->paginate($page_size,false,$where);
        $page = $ret->render();
        $list = $ret->toArray();
        if(!empty($list['data'])){
            foreach($list['data'] as $key => $val){
                if(empty($val['user_num'])){
                    $user_num = $this->getEdmDate($val);
                    Db::name(SCREENING)->where(['id' => $val['id']])->update(['user_num' => $user_num]);
                    $list['data'][$key]['user_num'] = $user_num;
                }
            }
        }
        $this->assign(['list'=>$list['data'],'data'=>$data,'page'=>$page]);
        return view('index');
    }

	/*
	 * 获取EDM筛选条件
	 * $list 筛选任务
	 * $type 1.统计数据数量，2返回用户数据
	 * $is_merge 是否获取合并数据
	 * add by kevin 20191030
	 * */
	public function getEdmDate($list,$type=1,$is_merge=1){
        $where = array();
        $order_where = array();
        $having = '';
        if(!empty($list)){
            if($list['IsRegister'] == 2){
                //注册时间
                if(!empty($list['reg_start_time']) && !empty($list['reg_end_time'])){
                    $where['c.register_on'] = array('between',''.$list['reg_start_time'].','.$list['reg_end_time'].'');
                }else if(!empty($list['reg_start_time'])){
                    $where['c.register_on'] = array('egt',$list['reg_start_time']);
                }else if(!empty($list['reg_end_time'])){
                    $where['c.register_on'] = array('elt',$list['reg_end_time']);
                }
                //注册国家
                if(!empty($list['register_countrys'])){
                    $register_countrys = explode(",",$list['register_countrys']);
                    $where['c.country_code'] = ['in',$register_countrys];
                }
            }

            //登录时间
            if(!empty($list['login_start_time']) && !empty($list['login_end_time'])){
                $where['last_login_date'] = array('between',''.$list['login_start_time'].','.$list['login_end_time'].'');
            }else if(!empty($list['login_start_time'])){
                $where['last_login_date'] = array('egt',$list['login_start_time']);
            }else if(!empty($list['login_end_time'])) {
                $where['last_login_date'] = array('elt', $list['login_end_time']);
            }

            //订单数量
            if(!empty($list['orders_of_limit'])){
                if($list['orders_of_limit'] == 1 && !empty($list['orders'])){
                    $having = "count(order_id)<".$list['orders'];
                }else if($list['orders_of_limit'] == 2 && !empty($list['orders'])){
                    $having = "count(order_id)>".$list['orders'];
                }
            }
            //下单时间
            if(!empty($list['order_create_start_time']) && !empty($list['order_create_end_time'])){
                $order_where['o.create_on'] = array('between',''.$list["order_create_start_time"].','.$list["order_create_end_time"].'');
            }else if(!empty($list['order_create_start_time'])){
                $order_where['o.create_on'] = array('egt',$list['order_create_start_time']);
            }else if(!empty($list['order_create_end_time'])){
                $order_where['o.create_on'] = array('elt',$list['order_create_end_time']);
            }
            //支付方式
            if(!empty($list['order_pay_mode'])){
                $order_where['pay_type'] = ['in',$list['order_pay_mode']];
            }
            //存放所选分类id
            /*if(!empty($list['class_id'])){
                $order_item_where['first_category_id'] = ['in',$list['class_id']];
            }*/
            //订单所在国家
            if(!empty($list['national'])){
                $national = explode(",",$list['national']);
                $order_where['o.country_code'] = ['in',$national];//country_code
            }
            //订单实收金额
            if(!empty($list['order_amount_of_start']) && !empty($list['order_amount_of_end'])){
                $order_where['captured_amount'] = array('between',''.$list['order_amount_of_start'].','.$list['order_amount_of_end'].'');
            }else if(!empty($list['order_amount_of_start'])){
                $order_where['captured_amount'] = array('egt',$list['order_amount_of_start']);
            }else if(!empty($list['order_amount_of_end'])){
                $order_where['captured_amount'] = array('elt',$list['order_amount_of_end']);
            }
        }
        if(empty($where) && empty($order_where) && empty($list['merge']) && $list['is_export'] == 0){
            if($type == 1){
                return 0;
            }else{
                return [];
            }
        }
        //获取数量数据
        if($type == 1){
            if($is_merge == 0){
                $count = Db::connect("db_data_storage")->table(self::edm_query_result)->where(['id'=>$list['id']])->count();
                if($count>0){
                    $list['merge'] = $list['id'];
                }else{
                    $list['merge'] = '';
                }
            }
            if(!empty($list['merge'])){
                $res = Db::connect("db_data_storage")->table(self::edm_query_result)->where(['id'=>['IN',$list['merge']]])->count("distinct(customer_id)");
            }else{
                if($list['is_export'] == 1){
                    $res = Db::connect("db_data_storage")->table(self::edm_query_result)->where(['id'=>$list['id']])->count();
                }else{
                    //如果有订单查询条件数据
                    if(!empty($order_where) || !empty($having)){
                        if(!empty($having)){
                            $res = Db::connect("db_data_storage")
                                ->table(self::edm_customer_data)
                                ->alias("c")
                                ->join(self::edm_order_data." o","c.customer_id=o.customer_id")
                                ->where(array_merge($where,$order_where))
                                ->count("distinct(o.customer_id)");
                        }else{
                            $res = Db::connect("db_data_storage")
                                ->table(self::edm_customer_data)
                                ->alias("c")
                                ->join(self::edm_order_data." o","c.customer_id=o.customer_id")
                                ->where(array_merge($where,$order_where))
                                ->count("distinct(o.customer_id)");
                        }
                    }else{
                        $res = Db::connect("db_data_storage")
                            ->table(self::edm_customer_data)
                            ->alias("c")
                            ->where($where)
                            ->count("distinct(c.customer_id)");
                    }
                }
            }
        }else{//获取用户数据
            if($is_merge == 0){
                $count = Db::connect("db_data_storage")->table(self::edm_query_result)->where(['id'=>$list['id']])->count();
                if($count>0){
                    $list['merge'] = $list['id'];
                }else{
                    $list['merge'] = '';
                }
            }
            if(!empty($list['merge'])){
                    $res =  Db::connect("db_data_storage")->table(self::edm_query_result)
                        ->where(['id'=>['IN',$list['merge']]])
                        ->group("customer_id")
                        ->field('customer_id,mailbox,LastName last_name,FirstName first_name,UserName user_name,CountryCode country_code')->select();
            }else {
                if ($list['is_export'] == 1) {
                    $res =  Db::connect("db_data_storage")->table(self::edm_query_result)
                        ->where(['id' => $list['id']])
                        ->group("customer_id")
                        ->field('customer_id,mailbox,LastName last_name,FirstName first_name,MiddleName middle_name,UserName user_name,CountryCode country_code')->select();
                }else{
                    $aes = aes();
                    //如果有订单查询条件数据
                    if(!empty($order_where) || !empty($having)){
                        if(!empty($having)){
                            $res = Db::connect("db_data_storage")
                                ->table(self::edm_customer_data)
                                ->alias("c")
                                ->join(self::edm_order_data." o","c.customer_id=o.customer_id")
                                ->group("o.customer_id")
                                ->having($having)
                                ->where(array_merge($where,$order_where))
                                ->field('c.customer_id,c.email_user_name,c.email_domain_name,c.last_name,c.first_name,c.middle_name,c.user_name,c.country_code')
                                ->select();
                        }else{
                            $res = Db::connect("db_data_storage")
                                ->table(self::edm_customer_data)
                                ->alias("c")
                                ->join(self::edm_order_data." o","c.customer_id=o.customer_id")
                                ->group("o.customer_id")
                                ->where(array_merge($where,$order_where))
                                ->field('c.customer_id,c.email_user_name,c.email_domain_name,c.last_name,c.first_name,c.middle_name,c.user_name,c.country_code')
                                ->select();
                        }
                    }else{
                        $res = Db::connect("db_data_storage")
                            ->table(self::edm_customer_data)
                            ->alias("c")
                            ->group("c.customer_id")
                            ->where($where)
                            ->field('c.customer_id,c.email_user_name,c.email_domain_name,c.last_name,c.first_name,c.middle_name,c.user_name,c.country_code')
                            ->select();
                    }
                    //解密用户邮箱
                    if(!empty($res)){
                        foreach ($res as $key=>$value){
                            $email_user_name = $aes->decrypt($value['email_user_name'],'Customer','EmailUserName');//解密邮件前缀
                            $res[$key]['mailbox'] = $email_user_name."@".$value['email_domain_name'];
                        }
                    }
                }
            }
        }

        return $res;
    }

    /**
     * 筛选任务管理--新增
     */
    public function UserSelection(){
        if($data = ParameterCheck(input())){
            $where = array();
            if(empty($data['Topic'])){
                echo json_encode(array('code'=>100,'result'=>'标题不能留空'),true);
                exit;
            }else{
                $where['TopicName'] = $data['Topic'];
            }
            if(!empty($data["readyCheckbox"])){
                $where['class_id'] = implode(',',$data["readyCheckbox"]);
                $where['class_status'] = 100;
            }
            if(!empty($data["Countrys"])){
                $where['national'] = $data["Countrys"];
            }
            if(!empty($data["RegisterTimeStart"])){
                $where['reg_start_time'] = strtotime($data["RegisterTimeStart"]);
            }
            if(!empty($data["RegisterTimeEnd"])){
                $where['reg_end_time'] = strtotime($data["RegisterTimeEnd"]);
            }
            if(!empty($data["LoginTimeStart"])){
                $where['login_start_time'] = strtotime($data["LoginTimeStart"]);
            }
            if(!empty($data["LoginTimeEnd"])){
                $where['login_end_time'] = strtotime($data["LoginTimeEnd"]);
            }
            if(!empty($data["OrderTimeOfStart"])){
                $where['order_create_start_time'] = strtotime($data["OrderTimeOfStart"]);
            }
            if(!empty($data["OrderTimeOfEnd"])){
                $where['order_create_end_time'] = strtotime($data["OrderTimeOfEnd"]);
            }
            if(!empty($data["OrderAmountOfStart"])){
                $where['order_amount_of_start'] = $data["OrderAmountOfStart"];
            }
            if(!empty($data["OrderAmountOfEnd"])){
                $where['order_amount_of_end'] = $data["OrderAmountOfEnd"];
            }
            if(!empty($data["OrdersOfLimit"]) && !empty($data["Orders"])){
                $where['orders_of_limit'] = $data["OrdersOfLimit"];
                $where['orders'] = $data["Orders"];
            }
            if(!empty($data["PaymentMethod"])){
                $where['order_pay_mode'] = implode(',',$data["PaymentMethod"]);
            }
            if(!empty($data["DataField"])){
                $where['DataField'] = $data["DataField"];
            }
            if(!empty($data["whether_country"])){
                $where['whether_country'] = $data["whether_country"];
            }
            if(!empty($data["register_countrys"])){
                $where['register_countrys'] = $data["register_countrys"];
            }
            if(!empty($data["rdorder"])){
                $where['rdorder'] = $data["rdorder"];
            }
            if(!empty($data["IsRegister"]) && $data["IsRegister"] == 2){
                $where['IsRegister'] = $data["IsRegister"];
                $where['status_register'] = 100;
            }else if(!empty($data["IsRegister"])){
                $where['IsRegister'] = $data["IsRegister"];
            }

            $where['add_time']   = time();
            $where['add_author'] = Session::get('username');
            $title = Db::name(SCREENING)->where(['TopicName'=>$where['TopicName']])->find();
            if(!empty($title) && empty($data['id'])){
                echo json_encode(array('code'=>100,'result'=>'标题重复'),true);
                exit;
            }
            if(!empty($data['id'])){
               $where['Parent_id']     = $data['id'];
                $where['status']     = 1;
            }else{
                $where['status']     = 2;
            }
            $result = Db::name(SCREENING)->insertGetId($where);
            if(!empty($result)){

                echo json_encode(array('code'=>200,'result'=>'数据添加成功','id'=>!empty($data['id'])?$data['id']:$result),true);
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'数据添加失败'),true);
                exit;
            }
        }
    }
	/**
	 * 筛选任务管理--新增
	 */
	public function add(){
        if($data = request()->post()){
            $where = array();
        	if(!empty($data['TopicName'])){
        		$where['TopicName'] = $data['TopicName'];
        	}
        	if(!empty($data["reg_start_time"]) && !empty($data["reg_end_time"])){
                $where['reg_start_time'] = strtotime($data["reg_start_time"]);
                $where['reg_end_time']   = strtotime($data["reg_end_time"]);
        	}else if(!empty($data["reg_start_time"]) || !empty($data["reg_end_time"])){
                echo json_encode(array('code'=>100,'result'=>'注册数据要么都填要么都不填'),true);
                exit;
        	}
        	if(!empty($data["login_start_time"]) && !empty($data["login_end_time"])){
                $where['login_start_time'] = strtotime($data["login_start_time"]);
                $where['login_end_time']   = strtotime($data["login_end_time"]);
        	}else if(!empty($data["login_start_time"]) || !empty($data["login_end_time"])){
                echo json_encode(array('code'=>100,'result'=>'登录时间要么都填要么都不填'),true);
                exit;
        	}
        	if(!empty($data["national"])){
                $where['national'] = $data["national"];
        	}
            if(!empty($data["order_create_start_time"]) && !empty($data["order_create_end_time"])){
                $where['order_create_start_time'] = strtotime($data["order_create_start_time"]);
                $where['order_create_end_time']   = strtotime($data["order_create_end_time"]);
        	}else if(!empty($data["order_create_start_time"]) || !empty($data["order_create_end_time"])){
                echo json_encode(array('code'=>100,'result'=>'下单时间要么都填要么都不填'),true);
                exit;
        	}
        	if(!empty($data["order_amount"])){
                $where['order_amount'] = $data["order_amount"];
        	}
        	if(isset($data["order_buy_condition"]) || $data["order_buy_condition"] == 0){
        		if($data["order_buy_condition"] != 0){
                    if(isset($data["order_buy_count"]) && $data["order_buy_count"] > 0){
                       $where['order_buy_count'] = $data["order_buy_count"];
        	        }else{
                       echo json_encode(array('code'=>100,'result'=>'点击次数有限制时,点击不能留空'),true);
                       exit;
        	        }
        	        $where['order_buy_condition'] = $data["order_buy_condition"];
        		}
        		$where['order_buy_condition'] = $data["order_buy_condition"];
        	}
        	if(!empty($data["order_pay_mode"])){
                  $where['order_pay_mode'] = json_encode($data["order_pay_mode"]);
        	}
        	$state = false;
        	//查询条件至少要有个存在
            foreach ($where as $key => $value) {
            	if($key == 'TopicName'){
                  continue;
            	}
            	if($value){
                  $state = true;
                  break;
            	}
            }
            if($state != true){
                echo json_encode(array('code'=>100,'result'=>'查询条件至少要有一个'),true);
                exit;
            }

            $where['add_time']   = time();
            $where['add_author'] = Session::get('username');
            $where['status']     = 1;
            $result = Db::name(SCREENING)->insert($where);
            if($result){
                echo json_encode(array('code'=>200,'result'=>'数据提交成功','type'=>1,'url'=>'/CustomFilter/index/id/89'),true);//type ==1 表示成功后返回列表
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'数据提交失败'),true);
                exit;
            }

        }
		return View();
	}

	/*
	 * 删除筛选任务
	 * add by kevin 20191104
	 * */
	public function filterDel(){
	    $id = input("fId");
	    if(empty($id)){
            return -1;
        }
        $where['id'] = $id;
        $result = Db::name(SCREENING)->where($where)->find();
        if(empty($result)){
            return -1;
        }else{
            if(!empty($result['merge'])){
                return -99;
            }
        }
        $del_result = Db::name(SCREENING)->where($where)->delete();
        return $del_result;
    }

	/**
	 * 国家赛选
	 * [nationalCompetition description]
	 * @return [type] [description]
	 * @author wang   addtime 2018-09-14
	 */
	public function nationalCompetition(){
		if($data = request()->post()){
			if(!empty($data["national"])){
				$national = implode(",", $data["national"]);
				// $national = json_encode($data["national"],true);

				$str[] = array(
					   'type'=>'html',
                       'id'=>'.national',
                       'data'=>$national,
					);
				$str[] = array(
					   'type'=>'val',
                       'id'=>'.national',
                       'data'=>$national,
					);
				echo json_encode(array('code'=>201,'result'=>$str),true);
                exit;
			}

		}else{
	        $national = BaseApi::getRegionData_AllCountryData();
	        $this->assign(['national'=>$national['data']]);
	        return View();
		}
	}
	/**
	 * 根据条件查询
	 * [order description]
	 * @return [type] [description]
	 * @author wang   addtime 2018-09-17
	 */
	public function order_query(){
         ini_set('max_execution_time', '0');
         ignore_user_abort();
         $userData   = array();//查询
         $OrderDdata = array();//查询订单
         $result = Db::name(SCREENING)->where(['status'=>1])->order('add_time ASC')->find();
         if(!$result){  return; }


         if(!empty($result['order_create_start_time']) && !empty($result['order_create_end_time'])){
             $OrderDdata['create_on'] = array('between',''.$result["order_create_start_time"].','.$result["order_create_end_time"].'');
         }
         if(!empty($result['order_pay_mode'])){
             $order_pay_mode = json_decode($result['order_pay_mode'],true);
             foreach ((array)$order_pay_mode as $k => $v) {
                  $OrderDdata['pay_type'][] = ['=',$v];
             }
             $OrderDdata['pay_type'][] = "or";
         }

         if(!empty($result['reg_start_time']) && !empty($result['reg_end_time'])){
             $userData['CreateOn'] = array('between',''.$result['reg_start_time'].','.$result['reg_end_time'].'');
         }
         if(!empty($result['login_start_time']) && !empty($result['login_end_time'])){
             $userData['LastLoginDate'] = array('between',''.$result['login_start_time'].','.$result['login_end_time'].'');
         }
         //当所有条件为空时，或去所有订阅用户
         if(empty($OrderDdata) && empty($userData)){
            return;
         }

         $data['OrderDdata'] = $OrderDdata;
         $data['userData']   = $userData;
         $data['page_size'] = 100;
         $data['page'] = 1;
         // file_put_contents ('../runtime/log/201812/data.log',json_encode($data).',', FILE_APPEND|LOCK_EX);
         Db::name(SCREENING)->where(['status'=>1,'id'=>$result['id']])->order('add_time ASC')->update(['status'=>3]);
         while (true) {
             $data['status'] = 1;//1代表新表
             $list = BaseApi::order_query($data);
             if($list["code"] == 200){
                    foreach ((array)$list['data'] as $key => $value) {
                        $json_data = ['customer_id'=>$value['customer_id'],'mail'=>$value['mail']];
                    }
             }else{
                    //跑完新表跑老表
                    $data['page'] = 1;
                    while (true) {
                      $data['status'] = 2;//2代表老表
                      $list = BaseApi::order_query($data);
                      if($list["code"] == 200){
                          foreach ((array)$list['data'] as $key => $value) {
                             $json_data = ['customer_id'=>$value['customer_id'],'mail'=>$value['mail']];
                          }
                      }else{
                          if(!empty($result['id'])){
                              $this->exportData($result['id']);
                              Db::name(SCREENING)->where(['status'=>3])->order('add_time ASC')->update(['status'=>4]);
                              exit;
                          }else{
                            echo $result['id'];
                          }
                      }
                      $data['page'] = $data['page'] + 1;
                    }
             }
             $data['page'] = $data['page'] + 1;
         }
	}


    /**
     * 导出国家分类语言
     * [NationalLanguage description]
     */
    public function exportData($id = 0){
            if($id<=0){
              return;
            }
            $header_data = ['customer_id'=>'买家ID','mail'=>'买家邮箱',];
            $tool = new ExcelTool();
            $tool ->redis_export('OrderDdata_'.$id,$header_data,'','sheet1','./uploads/edm/',DX_ORDER_QUERY.'_'.$id);
            // $this->EDM_FTP('OrderDdata');
    }

    /**
     *筛选用户
     */
    public function filterUsers(){
        $screening_management = [];
        $screening_select = [];
        $user_sum = 0;
        $field = 'id,title_en';
        $class_name = FirstLevelClass($field);
        $PaymentMethod = $this->dictionariesQuery('PaymentMethod');//获取字典  产品状态
        $sum = 0;
        $id = input('id');
        $list = Db::name(NATIONAL_LANGUAGE)->field('id,country_short_code,shortCutShowName')->select();
        if(!empty($list)){
            foreach ($list as $k => $v) {
                    $country_code_string = '';
                    $country_code_sum = 0;
                    $country_code = json_decode($v["country_short_code"]);
                    foreach ($country_code as $ke => $ve) {
                          if(empty($country_code_string)){
                              $country_code_string = $ve[1];
                          }else{
                              $country_code_string .= ','.$ve[1];
                          }
                          $country_code_sum += count(explode(',',$ve[1]));
                    }
                    $list[$k]['country_code_string'] = $country_code_string;
                    $list[$k]['country_code_sum']    = $country_code_sum;
            }
        }
        $sum = ceil(count($list)/4);
        $user_sum = 0;
        if(!empty($id)){
            $map = 'id = '.$id.' OR Parent_id = '.$id;
            $screening_management = Db::name(SCREENING)->where($map)->select();
            //合并并且已经执行完成的ID
            $merge_ids = array();
            if(!empty($screening_management)){
                foreach ($screening_management as $ke => $ve) {
                    if($ve['status'] == 2){
                          $screening_management[$ke]['count'] = $this->getEdmDate($ve,1,0);
                          $user_sum += $screening_management[$ke]['count'];
                          $merge_ids[] = $ve['id'];
                    }
                }
            }
            if(!empty($merge_ids)){
                $user_sum = Db::connect("db_data_storage")->table(self::edm_query_result)->where(['id'=>['in',$merge_ids]])->count("distinct(customer_id)");
            }
        }
        $this->assign([
            'class_name'=>$class_name,
            'PaymentMethod'=>$PaymentMethod,
            'list'=>$list,
            'sum'=>$sum,
            'screening_management'=>$screening_management,
            'id'=>$id,
            'user_sum'=>$user_sum
            ]);
        return view();
    }
    public function DataMerge(){
        $data = request()->post();
        if(!empty($data["TopicId"]) && !empty($data["Filters"])){
            $status = Db::name(SCREENING)->where(['id'=>$data["TopicId"],'Parent_id'=>0])->value("status");
            if($status == 2){
                $count = Db::connect("db_data_storage")->table(self::edm_query_result)->where(['id'=>$data["TopicId"]])->count();
                if($count == 0){
                    $update_data['status'] = 1;
                }
            }
             $update_data['merge'] = $data["Filters"];
             $update_data['edit_time'] = time();
             $update_result = Db::name(SCREENING)->where(['id'=>$data["TopicId"],'Parent_id'=>0])->update($update_data);
             // dump($update_result);
             if(!empty($update_result)){
                 echo json_encode(array('code'=>200,'result'=>'数据合并成功'),true);
                 exit;
             }else{
                 echo json_encode(array('code'=>100,'result'=>'数据合并失败'),true);
                 exit;
             }
        }
    }

    /**
     *配置国家快捷选择
     *
     */
    public function configureCountryShortcuts(){
        $list = Db::name(NATIONAL_LANGUAGE)->order('add_time ASC')->select();
        foreach ($list as $k => $v) {
            $country_code = [];
            $country_short_code = '';
            $country_code = json_decode($v["country_short_code"]);
            foreach ($country_code as $ke => $ve) {
                if($country_short_code == ''){
                      $country_short_code = $ve[1];
                }else{
                      $country_short_code .= ','.$ve[1];
                }
            }
            if($country_short_code != ''){
                 $list[$k]['national_name'] = Db::connect("db_mongo")->name(MOGOMODB_REGION)->where(['ParentID'=>0,'Code'=>['in',$country_short_code]])->field('id,Name,Code,Language')->select();
            }
        }
        $this->assign(['list'=>$list]);
        return view();
    }

    /**
     *新增编辑（要）
     *
     */
    public function CountryShortCutAct(){

        $where = [];
        $languageType = [];
        if($data = $_POST){
            $i = 0;
            foreach ($data["rptLanguageType"] as $k => $v) {
                $languageType[$i][0] = $data["LanguageType"][$k];
                $languageType[$i][1] = implode($v,',') ;
                $i++;
            }
            $where['country_short_code'] = json_encode($languageType);
            $where['shortCutShowName'] = $data['shortCutShowName'];

            //判断是修改或新增
            if(!empty($data['code_id'])){
                $where['edit_time'] = time();
                $result = Db::name(NATIONAL_LANGUAGE)->where(['id'=>$data['code_id']])->update($where);
            }else{
                $where['add_time'] = time();
                $shortCutShowName = Db::name(NATIONAL_LANGUAGE)->where(['shortCutShowName'=>$where['shortCutShowName']])->order('add_time ASC')->find();
                $result = Db::name(NATIONAL_LANGUAGE)->insert($where);
                if(!empty($shortCutShowName )){
                    echo json_encode(array('code'=>100,'result'=>'显示名不能重复'),true);
                    exit;
                }
            }

            if(!empty($result)){
                echo json_encode(array('code'=>200,'result'=>'提交成功'),true);
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'提交失败'),true);
                exit;
            }
        }else{
            $code_id = '';
            $country = array('西班牙语','葡语','英语','汉语','俄语','荷兰语','瑞典语','捷克语','芬兰语','德语','挪威语','意大利语','日本语','法语');
            $region = [];
            foreach ($country as $k => $v) {
                $region[]  = Db::connect("db_mongo")->name(MOGOMODB_REGION)->where(['ParentID'=>0,'Language'=>$v])->field('Name,Code,AreaCode,Gmt,Language')->select();
            }

            $code_id = input('code_id');
            if(!empty($code_id) && is_numeric($code_id)){
                    $national_language = Db::name(NATIONAL_LANGUAGE)->where(['id'=> $code_id])->find();
                    $country_code = [];
                    $country_short_code = [];
                    $country_code = json_decode($national_language["country_short_code"]);
                    foreach ($country_code as $ke => $ve) {
                        if(!empty($ve[1])){
                             foreach (explode(",", $ve[1]) as $ky => $vy) {
                                 if(!empty($vy)){
                                     $country_short_code[$vy] = $vy;
                                 }
                             }
                        }
                    }
            }
            $this->assign(['region'=>$region,'country'=>$country,'country_short_code'=>$country_short_code,'national_language'=> $national_language]);
            return view();
        }
    }

    /**
     *角色管理
     *
     */
    public function RoleManager(){
        return view('RoleManager');
    }

    /**
     *国家管理
     *
     */
    public function CountryList(){
        return view('CountryList');
    }

    /**
     *用户角色分配
     *
     */
    public function RoleUser(){
        return view('RoleUser');
    }

    /**
     *配置角色国家
     *
     */
    // public function RoleConutry(){
    //     return view('RoleConutry');
    // }

    /**
     *国家管理新增编辑
     *
     */
    public function CountryEdit(){
        return view();
    }

    /**
     *合并任务
     *
     */
    public function DistinctTasks(){
        return view('DistinctTasks');
    }

    /**
     *查看详细条件
     *
     */
    public function FilterDetails(){
        $id = input('id');
        if(empty($id)){
          return;
        }
        $list = AdminFind(SCREENING,['id'=>$id]);
        $this->assign(['list'=>$list]);
        return view();
    }

    /**
     *筛选弹窗
     *
     */
    public function SelectCountryDialog(){

        $id = input('id');
        $NationalLanguage = '';
        $where = array();
        if(!empty($id)){
           $national = Db::name(NATIONAL_LANGUAGE)->where(['id'=>$id])->order('add_time ASC')->find();
           if(empty($national)){
             return;
           }
           $national['country_short_code']  = json_decode($national['country_short_code'],true);
           foreach ($national['country_short_code'] as $k => $v) {
               if($v[1]){
                  $where['Code'] = ['in',$v[1]];
                  $where['ParentID'] = 0;
                  $national['country_short_code'][$k]['ShortCode'] = Db::connect("db_mongo")->name(MOGOMODB_REGION)->where($where)->field('Name,Code')->select();
                  // echo Db::connect("db_mongo")->name(MOGOMODB_REGION)->getLastSql();
               }
           }
        }
        $this->assign(['list'=>$national]);
        return view();
    }
    /**
     *国家添加语种(一次性使用)
     */
    public function NationalLanguage(){
        $tool = new ExcelTool();
        // $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
        $list = $tool->load('./uploads/Country.xlsx');//dump($list);
        $sheet = $list->getSheet(0);
        $highestRow = $sheet->getHighestRow(); //取得总行数
        $highestColumn = $sheet->getHighestColumn();// 取得总列数
        $notAttrDXid = array();
        $highestColumn++;
        for($j=2;$j<=$highestRow;$j++) {
            $str = '';
            $country_code = '';
            $area_code = '';
            $gmt = '';
            $data = [];

            $country_code = $list->getActiveSheet()->getCell("D$j")->getValue();//读取单元格
            $area_code = $list->getActiveSheet()->getCell("E$j")->getValue();//读取单元格
            $gmt = $list->getActiveSheet()->getCell("F$j")->getValue();//读取单元格
            $language = $list->getActiveSheet()->getCell("G$j")->getValue();//读取单元格
            if(empty($country_code)){
               continue;
            }
            $region = Db::connect("db_mongo")->name(MOGOMODB_REGION)->where(['Code'=>$country_code,'ParentID'=>0])->field('Name,Code,AreaCode,Gmt,Language')->find();
            if(empty($region)){
                file_put_contents ('../runtime/log/'.date("Ym",time()).'/Country_does_not_exist.log',$country_code.',', FILE_APPEND|LOCK_EX);
                continue;
            }
            if(empty($region['language'])){
               $data['AreaCode'] = $area_code;
               $data['Gmt'] = $gmt;
               $data['Language'] = $language;
               $result = Db::connect("db_mongo")->name(MOGOMODB_REGION)->where(['Code'=>$country_code,'ParentID'=>0])->update($data);
               if(empty($result)){
                    file_put_contents ('../runtime/log/'.date("Ym",time()).'/Country_does_not_exist_failure.log',$country_code.',', FILE_APPEND|LOCK_EX);
               }else{
                    file_put_contents ('../runtime/log/'.date("Ym",time()).'/Country_does_not_exist_success.log',$country_code.',', FILE_APPEND|LOCK_EX);
               }
            }
        }
    }
    /**
    * 数据导出
    * [export description]
    * @return [type] [description]
    * shit code
    */
    public function export(){
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '0');
        $id = input('id');
        $edm = input('edm');
        $is_merge = input("is_merge",1);
        $name_array = [];
        $letter_array = ['D','E','F','G'];
        $merge = '';
        if(empty($id)){
             return;
        }
        $screening_management = Db::name(SCREENING)->where(['id'=>(int)$id])->find();
        if(!empty($screening_management['merge'])){
              $merge = $screening_management['merge'];
        }
        if(!empty($screening_management['DataField'])){
            $DataField = explode(",",$screening_management['DataField']);
            if(!empty($DataField)){
                foreach ($DataField as $k => $v) {
                     if(!empty($v)){
                        $user_name = explode("|",$v);
                        if(!empty($user_name[0]) ){
                              if($user_name[0] == 4){
                                   if(!empty($user_name[1])){
                                           $name_array['FirstName'] = $user_name[1];
                                   }else{
                                           $name_array['FirstName'] = 'FirstName';
                                   }
                              }
                              if($user_name[0] == 5){
                                   if(!empty($user_name[1])){
                                           $name_array['MiddleName'] = $user_name[1];
                                   }else{
                                           $name_array['MiddleName'] = 'MiddleName';
                                   }
                              }
                              if($user_name[0] == 6){
                                   if(!empty($user_name[1])){
                                           $name_array['LastName'] = $user_name[1];
                                   }else{
                                           $name_array['LastName'] = 'LastName';
                                   }
                              }
                              if($user_name[0] == 7){
                                   if(!empty($user_name[1])){
                                           $name_array['UserName'] = $user_name[1];
                                   }else{
                                           $name_array['UserName'] = 'UserName';
                                   }
                              }
                        }
                     }
                }
            }
        }
        $j = 1;
        $data = [];
        vendor("PHPExcel.PHPExcel");
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1','cid' )
            ->setCellValue('B1','email')
            ->setCellValue('C1','country');
        if(!empty($name_array)){
           $sum = count($name_array);
           for($b=0;$b<$sum;$b++){
                $array_slice = array_slice($name_array,$b,1,true);
                foreach ((array)$array_slice as $ky => $ve) {
                     $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letter_array[$b].'1',$ve);
                }
           }
        }

        $objPHPExcel->getActiveSheet()->setTitle('sheet1');
        $objActSheet = $objPHPExcel->getActiveSheet();
        $j = 2;

        $_id = 0;//用于导出翻译

        $list = $this->getEdmDate($screening_management,2,$is_merge);
        if(!empty($list)){
            foreach ($list as $k => $v) {
                $_id = $v['_id'];
                $objActSheet->setCellValue('A'.$j,$v['customer_id']?$v['customer_id']:'');
                $objActSheet->setCellValue('B'.$j,'');
                $objActSheet->setCellValue('C'.$j,$v['country_code']?$v['country_code']:'');
                // $objActSheet->setCellValue('B'.$j,$v["mailbox"]?$v["mailbox"]:'');
                $sum = count($name_array);
                for($b=0;$b<$sum;$b++){
                    $array_slice = [];
                    $array_slice = array_slice($name_array,$b,1,true);
                    foreach ((array)$array_slice as $ky => $ve) {
                        $objActSheet->setCellValue($letter_array[$b].$j,!empty($v[$ky])?$v[$ky]:'' );
                    }
                }
                $j++;
                $v = 0;
            }
        }
       $fileName = trim($screening_management['TopicName']);
       $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
       $objPHPExcel->setActiveSheetIndex(0);
       $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'CSV')->setEnclosure('');
       if($edm == 1){
            $objWriter->save('uploads/edm/'.$xlsTitle.'.csv');
            $result = $this->EDM_FTP($xlsTitle.'.csv');
            if($result['code'] == 200){
               echo json_encode($result);
            }else{
               echo json_encode($result);
            }
            exit;
       }else{
           header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".csv'");
           header("Content-Disposition: attachment;filename=$xlsTitle.csv");
           header('Cache-Control: max-age=0');
           $objWriter->save('php://output');
       }

    }

    public function EDM_FTP($name = ''){

         if($name==''){
            return false;
         }
         $Zip_Ftp = $this->Zip_Ftp($name);
         if($Zip_Ftp['code'] != 200){
          return $Zip_Ftp;
         }
         $ftp_config = config('ftp_edm_config');

         $config = [
                'dirPath'=>$ftp_config['DX_FTP_FILE_URL'].'/', // ftp保存目录
                'romote_file'=>$ftp_config['DX_FTP_FILE_URL'].'/'.$Zip_Ftp['ZipName'],//保存文件的名称
                'local_file'=>$ftp_config['DX_FILE_URL'].$Zip_Ftp['ZipName'], // 要上传的文件
            ];

         $ftp = new FTPUpload();
         $upload = $ftp->data_put($config,1);
         if(empty($upload)){
            Log::record('emd推送失败 :'.$name.'/'.$upload,'error');
            return array('code'=>100,'msg'=>'推送edm失败');
         }
         unlink($ftp_config['DX_FILE_URL'].$Zip_Ftp['ZipName']);
         unlink($ftp_config['DX_FILE_URL'].$name);
         return array('code'=>200,'msg'=>'推送edm成功');

    }
     //压缩
    public function Zip_Ftp($data='',$id=''){
        $zip_result = '';
        $ftp_config = config('ftp_edm_config');
        //年_月_日_小时_分钟_秒_任务名称(筛选的账号名称).zip
        $ZipName = 'DxTask'.date('Y_m_d_H_i_s',time()).'_'.str_replace(strrchr($data, "."),"",$data)."(".Session::get("username").").zip";
        $filename = "./uploads/edm/" .$ZipName;
        $zip = new \ZipArchive();
        if ($zip->open($filename ,\ZipArchive::OVERWRITE) !== true){
            if($zip->open($filename ,\ZipArchive::CREATE) !== true){
                Log::record('Zip失败 创建路劲 :'.$filename,'error');
                return array('code'=>100,'msg'=>'无法打开文件，或者文件创建失败');
            }
        }
        $fileName = $ftp_config['DX_FILE_URL'].$data;//存放文件的真实路径
        if(file_exists($fileName)){
              $zip->addEmptyDir('');
              $zip_result = $zip->addFile( $fileName , basename($fileName));
        }else{
              Log::record('该路径下文件不存在（来自EDM） :'.$fileName,'error');
              return array('code'=>100,'msg'=>'生成zip失败');
        }
        $zip->close();// 关闭
        //下面是输出下载;
        // header ( "Cache-Control: max-age=0" );
        // header ( "Content-Description: File Transfer" );
        // header ( 'Content-disposition: attachment; filename=' . basename ( $filename ) ); // 文件名
        // header ( "Content-Type: application/zip" ); // zip格式的
        // header ( "Content-Transfer-Encoding: binary" ); // 告诉浏览器，这是二进制文件
        // header ( 'Content-Length: ' . filesize ( $filename ) ); // 告诉浏览器，文件大小
        // @readfile ( $filename );//输出文件;
       if(!empty($zip_result)  && $zip_result){
            return array('code'=>200,'ZipName'=>$ZipName);
       }else{
            Log::record('Zip生成失败 :'.$filename,'error');
            return array('code'=>100,'msg'=>'生成zip失败');
       }
    }
    public function nationalLanguageDelete(){
        if($data = request()->post()){
            if(empty($data['id'])){
                echo json_encode(array('code'=>100,'result'=>'获取参数失败'),true);
                exit;
            }
            $result = Db::name(NATIONAL_LANGUAGE)->where(['id'=>$data['id']])->delete();
            if(!empty($result)){
                echo json_encode(array('code'=>200,'result'=>'数据删除成功'),true);
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'数据删除失败'),true);
                exit;
            }
        }
    }

    /**
     * 弹出导入数据的就界面
     */
    public function importData(){
        return View();
    }

    /**
     * 导入数据
     */
    public function importDataPost()
    {
        ini_set('memory_limit','1024M');
        $time = time();
        vendor("PHPExcel.PHPExcel");
        //获取表单上传文件
        $file = request()->file('excel');
        if (!empty($file)) {
            $info = $file->validate(['ext' => 'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');
            if ($info) {
                $exclePath = $info->getSaveName();  //获取文件名
                $file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址

                $name_arr = explode(".",$info->getInfo('name'));
                if( $name_arr[1] =='xlsx' )
                {
                    $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
                }
                else
                {
                    $objReader = \PHPExcel_IOFactory::createReader('Excel5');
                }

                $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
                $excel_array = $obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
                array_shift($excel_array);  //删除第一个数组(标题);

                $TopicName = pathinfo($info->getInfo()['name'])['filename'];
                if (empty($TopicName)) {
                    $TopicName = 'import' . date('YmdHis');
                } else {
                    $TopicName = $TopicName;
                }

                //插入数据
                $screening['TopicName'] = $TopicName;
                $screening['status'] = 1;
                $screening['is_export'] = 1;
                $screening['add_time']   = $time;
                $screening['add_author'] = Session::get('username');
                $insertID = Db::name(SCREENING)->insertGetId($screening);
                if ($insertID) {
                    $insertAll = array();
                    foreach ($excel_array as $key => $val) {
                        if (!empty($val[0])) {
                            $insertAll[$key]['customer_id'] = $val[0];
                            $insertAll[$key]['id'] = $insertID;
                            $insertAll[$key]['add_time'] = $time;
                        }
                    }
                    $insertAll = array_values($insertAll);
                    $ret = Db::connect("db_data_storage")->table(self::edm_query_result)->insertAll($insertAll);
                    if ($ret) {
                        echo json_encode(array('code' => 200, 'result' => '导入成功'));
                    } else {
                        echo json_encode(array('code' => 103, 'result' => '导入失败'));
                    }
                } else {
                    echo json_encode(array('code' => 104, 'result' => '导入失败'));
                }
            } else {
                echo json_encode(array('code' => 102, 'result' => '数据提交失败'));
            }
        } else {
            echo json_encode(array('code' => 101, 'result' => '请检查数据后再上传！'));
        }
        exit;
    }
}