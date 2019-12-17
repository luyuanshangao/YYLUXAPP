<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use app\admin\model\DataConfigModel;
use app\admin\model\AutoIncrement;
use app\admin\model\SPU;

/**
 * 后台管理-商城业务数据配置
 * Add by:zhangheng
 * AddTime:2018-04-24
 * Info:
 *     1.替换旧系统的CMS功能;
 *     2.业务数据的插入及获取;
 */
class DataConfig extends Action
{
	//mongodb数据表--业务数据配置表
	const TABLENAME='dx_data_config';

	public function __construct(){
       Action::__construct();
          define('PRODUCT', 'dx_product');//mongodb数据表产品表
    }
	/**
	 * 商城业务数据配置--查询
	 */
	public function index()
	{
		$key = input("key");
		$configValue = input("configValue");
		$startTime = input("startTime");
		$endTime = input("endTime");
		if(!empty($key)){
			$where['key'] = ["like","$key"];
		}
		if(!empty($configValue)){
			$where['spus'] = $configValue;
		}
		if(!empty($startTime)){
			$startTime = strtotime($startTime);
			$where['updateTime'] = ["gt",$startTime];
		}
		if(!empty($endTime)){
			$endTime = strtotime($endTime);
			$where['updateTime'] = ["lt",$endTime];
		}
		if($startTime > $endTime){
			echo '<script>alert("结束时间不可以大于开始时间"); location=location;</script>';
			exit();
		}
		if(!empty($configValue)){
			//仅支持用逗号','分隔
			$configValue = explode(',', $where['spus']);
			foreach ($configValue as $key=>$value){
				$configValue[$key] = (int)$value;
			}
			$where['spus'] = ["in",$configValue];
		}
        $model = new DataConfigModel();
        $list  = $model->getDataConfigPaginate($where,config('paginate.list_rows'));
        $this->assign('list', $list);
        return view('data_config/index');
	}

	/**
	 * 商城业务数据配置--新增配置节点
	 */
	public function addDataConfig(){
		//是否提交
		if($data = request()->post()){
			if(empty($data['key'])){
				echo json_encode(array('code'=>101,'result'=>'KEY不可为空'));
				exit;
			}
			$dataConfigModel = new DataConfigModel();
			$result = $dataConfigModel->insertDataConfig($data);
			//var_dump($result);
			//exit();
			if($result==10){
				echo json_encode(array('code'=>102,'result'=>'KEY键已存在，提交失败'));
				exit;
			}else if($result){
				echo json_encode(array('code'=>200,'result'=>'操作成功'));
				exit;
			}else{
				echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
				exit;
			}
		}
		return View('data_config/addDataConfig');
	}

	/**
	 * 商城业务数据配置--查看或修改页面
	 */
	public function editConfig(){
		$id = input("id");
		$model = new DataConfigModel();
		$rs = $model->getDataConfigByID($id);
		//var_dump($rs);
		$this->assign('KEY', $rs['key']);
		$this->assign('id', $id);
		//var_dump($rs['spus']);
		$this->assign('list', $rs['spus']);
		return View('data_config/editConfig');
	}

	/**
	 * 弹出导入数据的就界面
	 */
	public function importData(){
		$id = input("id");
		if(empty($id)){
			echo json_encode(array('code'=>101,'result'=>'id不可为空'));
		    exit;
		}else{
			$model = new DataConfigModel();
			$list = $model->getDataConfigByID($id);
			$this->assign('keyName', $list['key']);
			$this->assign('id', $id);
			return View('data_config/importData');
		}
	}

	/**
	 * 导入数据
	 * @info:
	 *      1.上传EXCEL文件到服务器;
	 *      2.根据ID获取该条记录;
	 *      3.合并数据且去重复的SKU--把原有数据和EXCEL文件合并到一个数组;
	 *      4.插入数据到数据库;
	 *      //TODO 该方法的内部逻辑未完全实现，请勿删除相关注释掉的代码
	 */
	public function importDataPost(){
		vendor("PHPExcel.PHPExcel");
		$objPHPExcel = new \PHPExcel();
		//获取表单上传文件
		$file = request()->file('excel');
		$id = input("id");
		if(!empty($file) && !empty($id)){
			$info = $file->validate(['size'=>1567878,'ext'=>'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');
			if($info){
				$exclePath = $info->getSaveName();  //获取文件名
				$file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址
				$objReader =\PHPExcel_IOFactory::createReader('Excel2007');
				$obj_PHPExcel =$objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
				$excel_array=$obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式

				array_shift($excel_array);  //删除第一个数组(标题);
				//var_dump($excel_array);
				//用于插入系统的数组集合，新增：使用$excel_array数组内的全部数据，修改：需要合并数据库原值及$excel_array里的数据，且过滤掉重复SPU
				/**
				 * @todo 此处方法内部逻辑未实现内嵌文档数组,当前暂时使用数组，待完善;
				 */

				$onlyData=[];
				$val[0] =[];
				$model = new DataConfigModel();
				$oldData = $model->getDataConfigByID($id);
				$oldSPUSArray = $oldData['spus'];
				//var_dump($oldData);
				//echo '<br>';
				//var_dump($oldSPUSArray);
				//die();
				if(!empty($oldSPUSArray) && is_array($oldSPUSArray) && isset($oldSPUSArray[0])){
					//有节点且有值的情况
					//TODO
					//echo 'here.......';
					//die();
					foreach ($oldSPUSArray as $key => $val){
						if(!empty($val)){
							if(!in_array($val, $onlyData)){
								array_push($onlyData,(int)$val);
							}
						}
					}
				}

					foreach ($excel_array as $key => $val){

						if(!empty($val[0])){
							if(!in_array($val[0], $onlyData)){
								array_push($onlyData,(int)$val[0]);
							}
						}
					}
				if(!empty($oldSPUSArray)){
					sort($oldSPUSArray);
					sort($onlyData);
					if($oldSPUSArray == $onlyData){
						echo json_encode(array('code'=>101,'result'=>'提交的数据全部重复，请核实后再操作'));
						exit;
					}
				}

				$result_mongodb= $model->updateDataConfigByID($id,$onlyData);
				//$result_mongodb=1; //TODO
				if($result_mongodb){
					//只有等KeyName=StaffPicks,Presale时才需要去更新产品表，该需求比较特殊.
					if(input("keyName") == 'StaffPicks' || input("keyName") == 'Presale'){
						//更新产品表-IsStaffPick字段值等于true,
						//供前端页面查询数据使用（StaffPick列表页面，产品详情页面StaffPick标签）
                        if(input("keyName") == 'StaffPicks'){
                            $result = Db::connect("db_mongo")->table(PRODUCT)
                                        ->where(['_id'=>['in',$onlyData]])
                                        ->update(['IsStaffPick'=>(int)1]);
                        }
						//这里不做判断的原因是因为有些情况下，加入的SKU已经全部都是IsPresale，执行后$result =0，
					    //代表执行后影响行数是0
                        if(input("keyName") == 'Presale'){
                            $result = Db::connect("db_mongo")->table(PRODUCT)
                                        ->where(['_id'=>['in',$onlyData]])
                                        ->update(['Tags.IsPresale'=>(int)1]);
                        }
						echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
						exit;
					}else{
						echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
						exit;
					}
				}else{
					echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
					exit;
				}
			}else{
			   echo json_encode(array('code'=>102,'result'=>'数据提交db失败'));
			   exit;
			}
		}else{
			echo json_encode(array('code'=>101,'result'=>'请检查数据后再上传！'));
			exit;
		}
	}

	/**
	 * 删除数组里的某个值
	 */
	public function deleteSonDom($spu){
		$id = input("id/d");//var_dump($id);var_dump($spu);
		if(empty($id) || empty($spu)){
			echo json_encode(array('code'=>101,'result'=>'参数不可为空'));
			exit;
		}else{
			$model = new DataConfigModel();
			$record = $model->getDataConfigByID($id);
			//dump($record);

			$onlyData = array_diff($record['spus'], [(int)$spu]);//var_dump($onlyData);exit;

			//die();
		    $result_mongodb= $model->updateDataConfigByID($id,$onlyData);
			if($result_mongodb){
                //edit by heng.zhang 2018-07-13 更新商城产品表字段
                if(!empty($record)){
                   if($record['key'] === 'StaffPicks'){
	                    //更新产品表数据
	                    $result = Db::connect("db_mongo")->name('dx_product')
	                                ->where(['_id'=>(int)$spu])->update(['IsStaffPick'=>0,'EditTime'=>time()]);
                   }
                   if($record['key'] === 'Presale'){
                        //更新产品表数据
	                    $result = Db::connect("db_mongo")->name('dx_product')
	                                ->where(['_id'=>(int)$spu])->update(['Tags.IsPresale'=>0,'EditTime'=>time()]);
                   }

                }
                //dump('dddd');
				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
				exit;
			}else{
				echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
				exit;
			}
		}
	}

	/**
	 * 删除当前节点
	 */
	public function deleteSonDomAll(){

		$id = input("id");
		$spus = 0;
		if(empty($id) || !is_numeric($id)){
			echo json_encode(array('code'=>101,'result'=>'id不可为空'));
			exit;
		}else{
			$model = new DataConfigModel();
            $record = $model->getDataConfigByID($id);
            if(!empty($record) && ($record['key'] === 'StaffPicks' || $record['key'] === 'Presale')){
                $spus = $record['spus'];
            }
            if(empty($record)){
                echo json_encode(array('code'=>100,'result'=>'参数错误'));
                exit;
            }
			$result_mongodb= $model->updateDataConfigByID($id,[]);
			if($result_mongodb){
				$spus = array_values($spus);
			    // dump(array_values($spus));
			    //die();
                //edit by heng.zhang 2018-07-13 更新商城产品表字段
                if(count($spus) >0){
                	if($record['key'] === 'StaffPicks'){
	                     //更新产品表数据
	                     $result = Db::connect("db_mongo")->name('dx_product')
	                                ->where('_id','in',$spus)
	                                ->update(['IsStaffPick'=>0,'EditTime'=>time()]);
                	}
                	if($record['key'] === 'Presale'){
                         //更新产品表数据
	                     $result = Db::connect("db_mongo")->name('dx_product')
	                                ->where('_id','in',$spus)
	                                ->update(['Tags.IsPresale'=>0,'EditTime'=>time()]);//dump(Db::connect("db_mongo")->getLastSql());
                	}

                }
            }
			if($result_mongodb){
				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
				exit;
			}else{
				echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
				exit;
			}
		}
	}

	/*
	 * 国家区域配置->系统管理->系统配置信息-弹出框页面及新增数据（post）
	 */
	public function add_config(){
		if($data = request()->post()){//是否提交
			$configName = trim($data['ConfigName']);
			if(empty($configName)){
				echo json_encode(array('code'=>101,'result'=>'ConfigName不可为空'));
				exit;
			}
			if($this->checkConfigName($configName)){
				echo json_encode(array('code'=>102,'result'=>$configName.'重复'));
				exit;
			}
			$mongo['ConfigName']   = $configName;
			$mongo['ConfigValue']  = trim($data['ConfigValue']);
			$mongo['addTime']      = time();
			$mongo['Remark']       = trim($data['Remark']);
			$result_mongodb   =  Db::connect("db_mongo")->name(S_CONFIG)->insert($mongo);//存mongodb
			if($result_mongodb){
				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
				exit;
			}else{
				echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
				exit;
			}
		}else{
			return View('data_config/add_config');
		}
	}

	/*
	 * 检查ConfigName 是否唯一
	 * 配置的名称大于等于1则返回true,否则false
	 */
    private function checkConfigName($configName,$id=null){
    	if(empty($configName) || trim($configName)==''){
    		return false;
    	}
    	$result=0;
    	if(empty($id)){
	    	$result = Db::connect("db_mongo")->name(S_CONFIG)
				    	->where('ConfigName',$configName)
				    	->count();
    	}else{
    		$result = Db::connect("db_mongo")->name(S_CONFIG)
    					->where('_id','NEQ',$id)
			    		->where('ConfigName',$configName)
			    		->count();
    	}
    	return  $result >= 1;
    }
}