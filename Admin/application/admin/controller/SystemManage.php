<?php
namespace app\admin\controller;

use app\admin\dxcommon\CommonLib;
use app\common\redis\RedisClusterBase;
use think\View;
use think\Controller;
use think\Config;
use think\Db;
use \think\Session;
use think\Paginator;
use app\admin\dxcommon\BaseApi;
use app\admin\model\Systemmanage as Systemmanagement;
use app\admin\model\DataConfigModel;
use app\admin\dxcommon\Common;
/*
 * 后台管理-系统设置控制器
 * Add by:zhangheng
 * AddTime:2018-03-25
 * Info:
 *     1.国家区域管理--查询，修改，删除
 */
class SystemManage extends Action
{
	public function __construct(){
       Action::__construct();
       define('S_CONFIG', 'dx_sys_config');//mongodb数据表
       define('E_TEMPLET', 'dx_emailTemplet');//mongodb数据表
       define('REGION', 'dx_region');//mongodb数据表
       // $this->Menu_logo();
    }
	/*
	 * 国家区域管理--查询
	 */
	public function index()
	{
		$where = array();
		$whereLike ='';
		$data = request()->post();
        $continent = $this->dictionariesQuery('Continent');
		$areaID = null;

		/*
		 * 从商城数据库读取--无分页
		 */
		//dump($data);
		if(count($data)==0){
			$where['ParentID'] =0; //默认条件不可以缺少
		}else{
			if(!empty($data['AreaID'])){
				$where['AreaID'] = (int)$data['AreaID'];
				$areaID = $data['AreaID'];
			}else{
				$where['ParentID'] =0;
			}
            if(!empty($data['Code'])){
                $where['Code'] =$data['Code'];
                $Code = $data['Code'];
            }
			if(!empty($data['CodeOrName'])){
				$whereLike=$data['CodeOrName'];
			}
		}
		//dump($where);
		//dump($whereLike);
		//die();
		//dump($where);
		$result = Db::connect("db_mongo")->name("dx_region")
					->where($where)
		            ->whereLike('Name',$whereLike)
					->field('_id,Name,Code,AreaName,AreaID')
					->select();
		// echo Db::connect("db_mongo")->name("dx_region")->getlastsql();
		$this->assign(['list'=>$result,'areaID'=>$areaID,'Code'=>$Code,'codeOrName'=>$whereLike,'continent'=>$continent]);
		return View('ragionManage');
	}

	/*
	 * 国家区域配置->系统管理->系统配置信息--列表页面
	 */
	public function configList(){
		$data = request()->post();
		$configName = '';
		$configValue = '';
		/*
		 * 从商城数据库读取--无分页
		 */
		//dump($data);
		if(!empty($data)){
			if(!empty($data['ConfigName'])){
				$configName =$data['ConfigName'];
			}
			if(!empty($data['ConfigValue'])){
				$configValue=$data['ConfigValue'];
			}
		}
        $page=input('page');
        if(!$page){
            $page = 1;
        }
        $data['page'] = $page;
        $page_size = config('paginate.list_rows');
		//dump($where);
        $query =['ConfigName'=>$configName,'ConfigValue'=>$configValue];
		$result = Db::connect("db_mongo")->name(S_CONFIG)
						->whereLike('ConfigName',$configName)
						->whereLike('ConfigValue',$configValue)
						->field('_id,ConfigName,ConfigValue,Addtime,Remark')
                        ->order('Addtime','desc')
                        ->paginate($page_size);
		//echo Db::connect("db_mongo")->name(S_CONFIG)->getlastsql();
		//dump($result);
		$this->assign(['list'=>$result,'page'=>$result->render(),'configName'=>$configName,'configValue'=>$configValue]);
		return View('configList');
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
			$mongo['Addtime']      = time();
			$mongo['Remark']       = trim($data['Remark']);
			$result_mongodb   =  Db::connect("db_mongo")->name(S_CONFIG)->insert($mongo);//存mongodb
			if($result_mongodb){
				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
				exit;
			}else{
			    //TODO write log
				echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
				exit;
			}
		}else{
			return View('add_config');
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

	/*
	 * 国家区域配置->系统管理->系统配置信息-弹出框页面及编辑数据（post）
	 */
	public function edit_config(){
		$_id = input('id');
		if(empty($_id)){
			return '';
		}
		if($data = request()->post()){//是否提交
			$configName = trim($data['ConfigName']);
			if(empty($configName)){
				echo json_encode(array('code'=>101,'result'=>'ConfigName不可为空'));
				exit;
			}
			//需要传入本次修改的数据的ID，排除自己
			if($this->checkConfigName($configName,$_id)){
				echo json_encode(array('code'=>102,'result'=>$configName.'重复'));
				exit;
			}
			$mongo['ConfigName']   = $configName;
			$mongo['ConfigValue'] = $data['ConfigValue'];
			$mongo['Addtime']         = time();
			$mongo['Remark']         = $data['Remark'];
			$result_mongodb=Db::connect("db_mongo")->name(S_CONFIG)
							->where(["_id" =>$_id])
							->update($mongo);//存mongodb

			if($result_mongodb){
				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
				exit;
			}else{
				echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
				exit;
			}
		}else{
			$result=Db::connect("db_mongo")->name(S_CONFIG)
								->where(["_id" =>$_id])
								->field('_id,ConfigName,ConfigValue,Remark,Addtime')
								->find();
			$this->assign(['list'=>$result,]);
			return View('add_config'); //共用一个页面
		}
	}

    /*
     *删除
     *author  Wang
     */
	public function public_delete(){
	   $table = S_CONFIG;
       publicDelete($table,2);
	}
    /*
     *信息发送列表
     *author  Wang
     */
	public function emailtemplet(){
	     if($data = request()->post()){
	     	if(!empty(trim($data["title"]))){
	     		$where["title"] = $data["title"];
	     	}
	     	if(!empty(trim($data["type"]))){
	     		$where["type"] = (int)$data["type"];
	     	}
            $templetList = Db::connect("db_mongo")->name(E_TEMPLET)->where($where)->order('addTime','desc')->paginate(15);

            $this->assign(['data'=>$where,]);
	 	 }else{
	 		$templetList = Db::connect("db_mongo")->name(E_TEMPLET)->order('addTime','desc')->paginate(15);
	 	 }
         $this->assign(['templetList'=>$templetList->items(),'Page'=>$templetList->render(),]);
         return View('emailtempletList');
	}

	public function add_email(){
		 if($data = request()->post()){
            $returnValue = Systemmanagement::add_email($data);
            if( $returnValue !== true){
            	echo $returnValue;exit;
            }
		 	$configData       =	explode('-', $data["templetName"]);
            $data["templetName"]    = $configData[1];
            $data["templetValueID"] = $configData[0];
		 	$data['addTime']  = time();
            $data['addUser']  = Session::get('username');
            $data['editTime'] = '';
            $data['editUser'] = '';
            $data['isdelete'] = 0;//判断是否可删除，0代表不可以
             //格式限制
             $data['type'] = (int)$data['type'];
             $data['templetValueID'] = (int)$data['templetValueID'];
		 	$result = Db::connect("db_mongo")->name(E_TEMPLET)->insert($data);
            if($result){
	               echo  json_encode(array('code'=>200,'result'=>'信息提交成功'));
	               exit;
            }else{
            	   echo  json_encode(array('code'=>100,'result'=>'信息提交失败'));
	               exit;
            }
		 }else{
            return View('add_email');
		 }
	}
	public function edit_email(){
		  $_id = input('id');
		  if(!$_id){
	             echo  json_encode(array('code'=>100,'result'=>'获取不到对应的id'));
		         exit;
		  }
		  if($data = request()->post()){
              $data['type'] = (int)$data['type'];
              $data['templetValueID'] = (int)$data['templetValueID'];
			  	$returnValue = Systemmanagement::add_email($data);
	            if($returnValue !== true){
	            	echo $returnValue;exit;
	            }
	            $configData       =	explode('-', $data["templetName"]);
	            $data["templetName"]    = $configData[1];
	            $data["templetValueID"] = (int)$configData[0];
	            $data['editTime'] = time();
	            $data['editUser'] = Session::get('username');
	            $data['isdelete'] = 0;//判断是否可删除，0代表不可以
			 	// $result = Db::connect("db_mongo")->name('dx_email_templet')->where(['_id'=>$_id])->update($data);
                $result = Db::connect("db_mongo")->name(E_TEMPLET)->where(['_id'=>$_id])->update($data);
	            if($result){
		               echo  json_encode(array('code'=>200,'result'=>'信息提交成功'));
		               exit;
	            }else{
	            	   echo  json_encode(array('code'=>100,'result'=>'信息提交失败'));
		               exit;
	            }

		  }else{
		  	 $list = Db::connect("db_mongo")->name(E_TEMPLET)->where(['_id'=>$_id])->find();
		  	 $date = json_decode($this->ergodic(array('select_value'=>$list['type']),$list["templetValueID"]));
		  	 // dump($date);
		  	 $list['option'] = $date->result;//dump($list);
		  	 // dump($list);
		  	 $this->assign(['list'=>$list,]);
		  	 return View('add_email');
		  }
	}
	/**
	 *
	 * @param  [type] $data   数组
	 * @param  [type] $templetValueID   用于判断select是否选中
	 * @return [type]       [description]
	 */
    public function ergodic($data,$templetValueID=''){
    	    $value = '';
            if($data["select_value"] == 1){
               $ConfigName = 'EmailTempletTypeForBuyer';
	     	}else if($data["select_value"] == 2){
	           $ConfigName = 'EmailTempletTypeForSeller';
	     	}else{
               return  json_encode(array('code'=>100,'result'=>'传递参数有误'));
	     	}
     	    $result = publicConfig(S_CONFIG,$ConfigName);
     	    if($result['code'] == 200){
     	       $configData =	explode(';', $result["result"]["ConfigValue"]);
     	       $html = '';
     	       foreach ($configData as $key => $value) {
     	         	$data  = explode(':', $value);
     	         	if($templetValueID == $data[0]  && $templetValueID!=''){
                       $selected = 'selected = "selected"';
     	         	}
         	       	if($key == 0){
                         $html  = '<option '.$selected.' class="optionID" value="'.$data[0].'-'.$data[1].'">'.$data[1].'</option>';
         	       	}else{
                         $html .= '<option '.$selected.' class="optionID" value="'.$data[0].'-'.$data[1].'">'.$data[1].'</option>';
         	       	}
     	       }
               return json_encode(array('code'=>200,'result'=>$html));
     	    }else{
               return json_encode($result);

     	    }
    }
    /*
     *发送模板删除
     */
    public function templet_delete(){
       $table = E_TEMPLET;
       publicDelete($table,2);
    }

    /**
     * 选择查询配置信息
     * @return [type] [description]
     */
	public function syste_config(){
         if($data = request()->post()){
             echo  $this->ergodic($data);
             exit;
		 }else{
              echo json_encode(array('code'=>100,'result'=>'获取参数失败'));
              exit;
		 }
	}
      /**分类设置
      * [MerchantDelete description]
      * author: Wang
      * AddTime:2018-04-20
      */
  public function ClassConfigure(){
     $language   =  BaseApi::langs();
     $pageSize = config('paginate.list_rows');
     $languageSelected='';
     if($data = request()->post()){
          if($data['className']){
               $this->assign(['className'=>$data['className'],]);
          }
          if($data['language']){
               $map['language'] = $data['language'];
               $languageSelected=$data['language'];
          }
          $list =  Db::connect("db_mongo")->name("dx_integration_class")
        				->where($map)
        				->whereLike('className',$data['className'])
        				->where('status','neq',0)
             			->order('add_time desc')
        				->paginate($pageSize);
     }else{
        $map['status'] = array('neq',0);
        $list =  Db::connect("db_mongo")->name("dx_integration_class")
        				->where($map)
        				->where('status','neq',0)
        				->order('add_time desc')
        				->paginate($pageSize);
     }

      $this->assign(['list'=>$list->items(),'page'=>$list->render(),
     		        'language'=>$language["data"],
     				'languageSelected'=>$languageSelected
     				]);
     return view('ClassConfigure');
  }
   /**
   * [add_Configure description]
   * 分类 配置添加
   */
  public function add_Configure(){
      if($data = request()->post()){
          $classId   = '';
          $className = '';
          $classNameHtml = '';//var_dump($data['where']);
          foreach ($data['where'] as $key => $v) {
              //dump($data['character']);
            $classId   .= $v['classId'].',';
            // $classInfo = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$v['classId']])->find();
            //   $title = CommonLib::filterTitle($classInfo['title_en']);
            if((empty($classId)) || empty(trim($v["className"]))){
                echo json_encode(array('code'=>100,'result'=>'第'.$key.'个分类名称不可为空'));
                exit;
            }

            $className .= $v["className"].$data['character'];
            $title = str_replace(array(' & ','&'),array('-','-'),$v["class_name"]);
            $classNameHtml .= '<a class="menu-title" href=/c/'.$title.'-'.$v['classId'].'>'.$v["className"].'</a>'.$data['character'];
          }
          $dataArray['language']   = $data['language'];
          $dataArray['classId']    = rtrim($classId, ",");//substr($classId,0,strlen($classId)-1);
          $dataArray['className']  = rtrim($className, $data['character']);
          $dataArray['classNameHtml']  = rtrim($classNameHtml, $data['character']);
          $dataArray["sort"]       = (int)$data["sort"];
          $dataArray["content"]    = $data["content"];
          $dataArray["content_right"]  = $data["content_right"];
          $dataArray["status"]     = (int)$data["status"];
          $dataArray["character"]  = $data['character'];
          $dataArray['add_time']   = time();
          $dataArray['add_person'] = Session::get('username');
          $dataArray['classIconfont']  = $data['classIconfont'];
          $dataArray['classIconImg']= $data['classIconImg'];
          $dataArray['edit_person']= '';
          $dataArray['edit_time']= '';

          if(empty($dataArray['language']) || empty($dataArray['classId']) ||
              empty($dataArray['className']) || empty($dataArray["sort"]) || empty($dataArray["content"]) ||
              empty($dataArray["content_right"])){
                echo json_encode(array('code'=>100,'result'=>'存在为空数据'));
                exit;
          }
          $language = Db::connect("db_mongo")->name("dx_integration_class")
              ->where(['language'=>$dataArray['language'],'classId'=>$dataArray['classId'],'status'=>1])->field('language')->find();
          if(!$language){
                $result   =  Db::connect("db_mongo")->name("dx_integration_class")->insert($dataArray);//存mongodb
                if($result){
                   echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
                   exit;
                }else{
                   echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
                   exit;
                }
          }else{
                echo json_encode(array('code'=>100,'result'=>'该数据已添加过'));
                exit;
          }
      }else{
          $mongodbClass   =  Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>0,'type'=>1])->select();//存mongodb
          $html = '';
          foreach ($mongodbClass as $key => $value) {
             $html .= '<option value="'.$value["id"].'">'.$value["title_en"].'</option>';
          }
          $language = BaseApi::langs();

          $common = new Common();
          //获取后台配置的数据
          $class_Icon_Css_Dict = $this->dictionariesQuery('Mall_Class_Icon_Css');
          $class_Icon_Css_Html = $common::outSelectHtml($class_Icon_Css_Dict,'classIconfont','');
          $this->assign(['language'=>$language["data"],'html'=>$html,'class_Icon_Css_Html' =>$class_Icon_Css_Html]);
          return view('add_Configure');
      }

  }

   /**Country
   * [add_Configure description]
   * 分类 配置修改
   */
  public function edit_Configure(){
        $id = input('_id');
        $integrationClass = Db::connect("db_mongo")->name("dx_integration_class");
        if($id){
           if($data = request()->post()){
           	    $data['language'] ?'en':$data['language'];
                $classId   = '';
                $className = '';
                $classNameHtml = '';
                foreach ($data['where'] as $key => $v) {
                   //dump($data['character']);
                    $classId   .= $v['classId'].',';
                    $classInfo = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$v['classId'],'type'=>1])->find();
                    $title = CommonLib::filterTitle($classInfo['title_en']);
                    if((empty($classId)) || empty(trim($v["className"]))){
                       echo json_encode(array('code'=>100,'result'=>'第'.$key.'个分类名称不可为空'));
                       exit;
                    }
                    $className .= $v["className"].$data['character'];
                    $classNameHtml .= '<a class="menu-title" href=/c/'.$title.'-'.$v['classId'].'>'.$v["className"].'</a>'.$data['character'];
                    $languageValue ='';
                    if($data['language'] !='en'){
                  	  $languageValue =$data['language'];
                    }
                    $classNameHtml = str_replace("{#language}",$languageValue,$classNameHtml);
                }
                $dataArray['language']   = $data['language'];
                $dataArray['classId']    = rtrim($classId, ",");
                $dataArray['className']  = rtrim($className, $data['character']);
                $dataArray['classNameHtml']  = rtrim($classNameHtml, $data['character']);
                $dataArray["sort"]       = (int)$data["sort"];
                $dataArray["content"]    = $data["content"];
                $dataArray["content_right"]  = $data["content_right"];
                $dataArray["status"]     = (int)$data["status"];
                $dataArray["character"]  = $data['character'];
                $dataArray['classIconfont']  = $data['classIconfont'];
                $dataArray['classIconImg']= $data['classIconImg'];
                $dataArray['edit_time']  = time();
                $dataArray['edit_person']= Session::get('username');
                if(empty($dataArray['language']) || empty($dataArray['classId']) || empty($dataArray['className'])
                    || empty($dataArray["sort"]) || empty($dataArray["content"]) || empty($dataArray["content_right"])){
                  echo json_encode(array('code'=>100,'result'=>'存在为空数据'));
                  exit;
                }
                $result = Db::connect("db_mongo")->name("dx_integration_class")->where(['_id'=>$id,])->update($dataArray);
                if($result){
                   echo json_encode(array('code'=>200,'result'=>'数据更新成功'));
                   exit;
                }else{
                   echo json_encode(array('code'=>100,'result'=>'数据更新失败'));
                   exit;
                }
           }else{
              $data['_id']["oid"] =  $id;
              $list = $integrationClass->where(['_id'=>$data['_id']["oid"]])->find();
              $list['class_id']   =  explode(",",$list['classId']);
              $list['class_name'] =  explode($list['character'], $list['className']);
              $list['select']     =  $this->ergodic_Configure($list['class_id'],$list['class_name']);
              $mongodbClass   =  Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>0,'type'=>1])->select();//存mongodb
              if($mongodbClass){
                  $html = '';
                  foreach ($mongodbClass as $key => $value) {
                     $html .= '<option value="'.$value["id"].'">'.$value["title_en"].'</option>';
                  }
              }
               $language = BaseApi::langs();
               $common = new Common();
               //获取后台配置的数据
               $class_Icon_Css_Dict = $this->dictionariesQuery('Mall_Class_Icon_Css');
               $class_Icon_Css_Html = $common::outSelectHtml($class_Icon_Css_Dict,'classIconfont',$list['classIconfont']);//dump($list);
              $this->assign(['list'=>$list,'language'=>$language["data"],'id'=>$id,'html'=>$html,'class_Icon_Css_Html'=>$class_Icon_Css_Html]);
              return view('add_Configure');
           }
        }
  }

  /**
   * [ergodic_Configure description]
   * @return [type] [description]
   *遍历组合分类 数据
   *$class_id   分类数组
   *class_name  分类名数组
   */
  public function ergodic_Configure($class_id,$class_name_write){
          $mongodbClass   =  Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>0,'type'=>1])->select();//存mongodb
          $i = 1;
          $sum =  count($class_id);
          $classHtml = '';
          $select = '';
          $html = '';
          $class_name = '';
          foreach ($class_id as $key => $value) {
              $html .= '<dl class="c-h-dl-validator form-group clearfix add-attribute'.$i.'">';
              $html .= '<dd class="v-title w100">';
              $html .= '<label><em>*</em>分类：</label></dd>';
              $html .= '<dd><div class="input-icon right">';
              $html .= '<select name="where['.$i.'][classId]" id="first" class="form-control input-small inline input-class-name">';
              $html .= '<option value="">请选择</option>';
                      foreach ($mongodbClass as $k => $v) {
                        if($value == $v["id"]){
                            $select = 'selected = "selected"';
                            $class_name = $v["title_en"];
                        }
                        $classHtml .= '<option '.$select.' value="'.$v["id"].'">'.$v["title_en"].'</option>';
                        $select = '';
                      }
              $html .= $classHtml;
              $html .= '</select></div></dd>';
              $html .= '<dd class="v-title">';
              $html .= '<label><em>*</em>分类名称：</label>';
              $html .= '</dd>';
              $html .= '<dd>';
              $html .= '<div class="input-icon right">';
              $html .= '<i class="fa"></i>';
              $html .= '<input  value="'.$class_name_write[$key].'"  name="where['.$i.'][className]" class="w130 fl form-control input-medium" type="text">';
              $html .= '<input class="class-name" type="hidden" name="where['.$i.'][class_name]" value="'.$class_name_write[$key].'">';
              $html .= '</div>';
              $html .= '</dd>';
              if($sum ==  ($key+1)){
                $html .= '<dd><a class="ml10 btn btn-qing add-new-item-btn" href="javascript:;">添加新项</a><a class="btn btn-qing ml10 delete-class-btn" data-index="'.$i.'"  href="javascript:;">删除</a></dd>';
              }else if(($key+1) != 1){
                $html .= '<ddv-title"><a class="btn btn-qing ml10 delete-class-btn" data-index="'.$i.'" href="javascript:;">删除</a></dd>';
              }
              $html .= '<dt></dt>';
              $html .= '</dl>';
              $i++;
              $classHtml .= '';
          }
          return $html;
  }
  /**
   * [delete_Configure description]
   * @param  [type] $class_id   [description]
   * @param  [type] $class_name [description]
   * @return [type]             [description]
   * 逻辑删除分类配置
   */
  public function delete_Configure(){
           if($data = request()->post()){
               $dat['status']    = 0;
               $dat['edit_time'] = time();
               $id = $data["id"];//dump(Db::connect("db_mongo")->name("dx_product_class")->where(['_id'=>$id,])->select());
               // $result = Db::connect("db_mongo")->name("dx_product_class")->where(['_id'=>$data["id"],])->update($dat);
               $result = Db::connect("db_mongo")->name("dx_integration_class")->where(['_id'=>$id,])->update($dat);
               if($result){
                    echo json_encode(array('code'=>200,'result'=>'删除成功'));
                    exit;
               }else{
                    echo json_encode(array('code'=>100,'result'=>'删除失败'));
                    exit;
               }
           }else{
                echo json_encode(array('code'=>100,'result'=>'获取相应数据失败'));
                exit;
           }
  }

  /**
   * @info 商城设置页面
   * @author hengzhang 2018-04-28
   */
  public function mallSet(){
    $region   =  BaseApi::langs();
    $Language = $this->exhibition('en');//默认英语
    $list =  Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1])->field('bottom_logo')->find();
    // $region   =  Db::connect("db_mongo")->name("dx_region")->where(['ParentID'=>0])->field('Code,Name')->select();//存mongodb
    $this->assign(['region'=>$region["data"],'Language' => $Language,'bottom_logo'=>$list['bottom_logo']]);
  	return view("mallSet");
  }
  /**
   * 删除lang包括对应数据
   * [lang_delecte description]
   * @return [type] [description]
   * @author wang 2018-08-30
   */
  public function lang_delecte(){
      if($data = request()->post()){
        $language = 'language.'.$data['lang_id'];
        // dump($data);
        $list =  Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1])->field('language')->find();
        if($list){
            if($list['language'][$data['lang_id']]){
                $date = array();
               // unset($list['bottom_logo'][$data['lang_id']]);
               // $result = Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1,])->update(["bottom_logo"=>$list['bottom_logo'],'edit_time'=>time(),'bottom_logo'=>$bottom_logo]);
               $result = Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1,])->update(["$language"=>$date,'edit_time'=>time()]);
               if($result){
                   echo json_encode(array('code'=>200,'result'=>'删除成功'));
                   exit;
               }else{
                   echo json_encode(array('code'=>100,'result'=>'数据删除失败'));
                   exit;
               }
            }else{
               echo json_encode(array('code'=>100,'result'=>'该语言没有数据'));
               exit;
            }
        }else{
            echo json_encode(array('code'=>100,'result'=>'原始数据丢失'));
            exit;
        }
      }
  }
  /**
   * *添加广告
   * @return [type] [description]
   * @author wang 2018-05-12
   */
  public function advertisement(){
     if($data = request()->post()){
         $language = 'language.'.$data['Code'];
         $date['Code']     = $data['Code'];
         $date['HotWords'] = $data['HotWords'];
         $bottom_logo = $data['bottom_logo'];

         $list =  Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1])->field("$language")->find();
         if($list){
            $result = Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1,])->update(["$language"=>$date,'edit_time'=>time(),'bottom_logo'=>$bottom_logo]);
         }else{
            $dat['_id']      = 1;
            $dat['add_time'] = time();
            $dat['bottom_logo'] = $data['bottom_logo'];
            $dat['language'][$data['Code']]['Code'] = $data['Code'];
            $dat['language'][$data['Code']]['HotWords'] = $date['HotWords'];
            $result =  Db::connect("db_mongo")->name("dx_mall_set")->insert($dat);
         }
         if($result){
            echo json_encode(array('code'=>200,'result'=>'添加成功'));
            exit;
         }else{
            echo json_encode(array('code'=>100,'result'=>'添加失败'));
            exit;
         }
     }
  }
  /**
   * 选择触发获取广告数据
   * @author wang 2018-05-12
   */
   public function exhibition($Languag =''){
      $data = request()->post();
      if($data || $Languag == 'en' ){
          if(empty($data) && $Languag=='en'){
              $data = array();
              $data['Code'] = $Languag;
          }
          $language = 'language.'.$data['Code'];
          $html = '';
          $list =  Db::connect("db_mongo")->name("dx_mall_set")->where(['_id'=>1])->field($language)->find();
          if(isset($list['language'][$data['Code']]) && !empty($list['language'][$data['Code']])){
              $sum = count($list['language'][$data['Code']]["HotWords"]);
              foreach ((array)$list['language'][$data['Code']]["HotWords"] as $key => $value) {
                 if($sum == 1){
                    $html .= '<dl class="c-h-dl-validator form-group clearfix dl-HotWords"><dd class="v-title w100">
                    <label>搜索热词：</label></dd><dd><div class="input-icon right"><i class="fa"></i>
                    <input value="'.$value.'" name="HotWords[]" class="form-control input-medium inline w200 fl" type="text">
                    </div></dd><dd class="Hot">
                    <a class="fa fa-plus hotwords-btn add-hotwords-btn" data-id="1" onclick="addHotWords(1)" href="javascript:;"></a></dd>
                    <dt></dt></dl>';
                 }else if(($sum - 1) == $key){
                     $i = $key + 1;
                     $html .= '<dl class="c-h-dl-validator form-group clearfix dl-HotWords">
                     <dd class="w100 v-title"><label></label></dd><dd><div class="input-icon right"><i class="fa"></i>
                     <input value="'.$value.'" name="HotWords[]" class="w200 fl form-control input-medium inline mr5" type="text">
                     </div></dd><dd>
                     <a class="fa fa-plus hotwords-btn add-hotwords-btn" data-id="'.$i.'" href="javascript:void(0);"></a>
                     <a class="fa fa-minus hotwords-btn delete-hotwords-btn delect-t'.$i.'" data-id="'.$i.'" href="javascript:;"></a></dd><dt></dt></dl>';
                 }else if($sum > 0 && $key == 0){
                     $html .= '<dl class="c-h-dl-validator form-group clearfix dl-HotWords">
                     <dd class="v-title w100"><label>搜索热词：</label></dd><dd><div class="input-icon right"><i class="fa"></i>
                     <input value="'.$value.'" name="HotWords[]" class="w200 fl form-control input-medium inline mr5" type="text">
                     </div></dd><dd class="Hot"></dd><dt></dt></dl>';
                 }else{
                    $i = $key + 1;
                    $html .= '<dl class="c-h-dl-validator form-group clearfix dl-HotWords">
                    <dd class="w100 v-title"><label></label></dd><dd><div class="input-icon right">
                    <i class="fa"></i>
                    <input value="'.$value.'" name="HotWords[]" class="w200 flform-control input-medium inline mr5" type="text">
                    </div></dd>
                    <dd>
                    <a class="fa fa-minus hotwords-btn delete-hotwords-btn" data-id="'.$i.'" href="javascript:;"></a></dd><dt></dt></dl>';
                }

              }
              if( $Languag=='en'){
                return $html;
              }else{
                echo json_encode(array('code'=>200,'result'=>$html));
                exit;
              }

          }else{
              $html .= '<dl class="c-h-dl-validator form-group clearfix dl-HotWords"><dd class="v-title w100"><label>搜索热词：</label></dd><dd><div class="input-icon right"><i class="fa"></i><input value="" name="HotWords[]" class="w200 fl form-control input-medium inline mr5" type="text"></div></dd><dd class="Hot"><a class="fa fa-plus hotwords-btn add-hotwords-btn" data-id="'.$i.'" href="javascript:void(0);"></a></dd><dt></dt></dl>';
              if( $Languag =='en'){
                return $html;
              }else{
                echo json_encode(array('code'=>200,'result'=>$html));
                exit;
              }

          }
      }
   }


  /**
   * @info 商城设置页面--更新顶部通栏广告
   * @author hengzhang 2018-05-09
   */
  public function asyncUpdateTopBannerUrl(){
  	if($data = request()->post()){
  		/** 考虑暂时用字典配置
  		$data['key'] ='TopBannerUrl';
  		$dataConfigModel = new DataConfigModel();
		$result = $dataConfigModel->insertDataConfig($data);
		*/
  		if($result){
	  		echo json_encode(array('code'=>200,'msg'=>'操作成功'));
	  		exit;
  		}else{
             echo json_encode(array('code'=>101,'result'=>'操作失败'));
             exit;
        }
  	}
  }

  /**
   * @info VAT管理列表页面
   * @author hengzhang 2018-05-20
   */
  public function vatList(){
  	if($data = request()->post()){
  		/**
  		$classId   = '';
  		$className = '';
  		foreach ($data['where'] as $key => $value) {
  			$classId   .= $value['classId'].',';
  			$className .= $value["classname"].$data['character'];
  			$classNameHtml .= '<a class="menu-title" href="http://www.dx.com/c/consumer-electronics-'.$value['classId'].'">'.$value["classname"].'</a>'.$data['character'];
  		}
  		$dataArray['language']   = $data['language'];
  		$dataArray['classId']    = rtrim($classId, ",");//substr($classId,0,strlen($classId)-1);
  		$dataArray['className']  = rtrim($className, $data['character']);
  		$dataArray['classNameHtml']  = rtrim($classNameHtml, $data['character']);
  		$dataArray["sort"]       = (int)$data["sort"];
  		$dataArray["content"]    = $data["content"];
  		$dataArray["content_right"]  = $data["content_right"];
  		$dataArray["status"]     = (int)$data["status"];
  		$dataArray["character"]  = $data['character'];
  		$dataArray['add_time']   = time();
  		$dataArray['add_person'] = Session::get('username');
  		$dataArray['edit_time']  = '';
  		$dataArray['edit_person']= '';
  		if(empty($dataArray['language']) || empty($dataArray['classId']) || empty($dataArray['className']) || empty($dataArray["sort"]) || empty($dataArray["content"]) || empty($dataArray["content_right"])){
  			echo json_encode(array('code'=>100,'result'=>'存在为空数据'));
  			exit;
  		}
  		$language = $integrationClass->where(['language'=>$dataArray['language'],'classId'=>$dataArray['classId']])->field('language')->find();
  		if(!$language){
  			$result   =  $integrationClass->insert($dataArray);//存mongodb
  			if($result){
  				echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
  				exit;
  			}else{
  				echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
  				exit;
  			}
  		}else{
  			echo json_encode(array('code'=>100,'result'=>'该数据已添加过'));
  			exit;
  		}
  		*/
  	}else{
  		$mongodbClass   =  Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>0,'type'=>1])->select();
  		return view('vatList');
  	}

  }

    /**
     * 弹出导入数据的就界面
     */
    public function importData(){
        // $model = new DataConfigModel();
        // $list = $model->getDataConfigByID($id);
        // $this->assign('keyName', $list['key']);
        return View('importData');
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
        // $id = input("id");
        if(!empty($file)){
            $info = $file->validate(['size'=>15678,'ext'=>'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');

            if($info){
                $exclePath = $info->getSaveName();  //获取文件名
                $file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址
                $objReader =\PHPExcel_IOFactory::createReader('Excel2007');
                $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
                $excel_array=$obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
                array_shift($excel_array);  //删除第一个数组(标题);
                $i = 0;
                $sum = count($excel_array);
                foreach ($excel_array as $k => $v) {
                     $result = Db::connect("db_mongo")->name(REGION)->where(['Code'=>$v[0],])->update(['NationalLanguage'=>$v[1]]);
                     if($result){
                        $i++;
                     }
                }


               echo json_encode(array('code'=>200,'result'=>'共有'.$sum.'个数据,更新数据有'.$i.'个'));
               exit;
            }else{
               echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
               exit;
            }
        }else{
            echo json_encode(array('code'=>100,'result'=>'请检查数据后再上传！'));
            exit;
        }
    }

   /**
   * @info VAT编辑页面
   * @author hengzhang 2018-05-20
   */
  public function add_vat(){
  	if($data = request()->post()){//是否提交
  		/**
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
  		$mongo['Addtime']      = time();
  		$mongo['Remark']       = trim($data['Remark']);
  		$result_mongodb   =  Db::connect("db_mongo")->name(S_CONFIG)->insert($mongo);//存mongodb
  		if($result_mongodb){
  			echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
  			exit;
  		}else{
  			echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
  			exit;
  		}
  		*/
  	}else{
  		return View('add_vat');
  	}
  }

  /*
   * 检查vat 国家数据 是否唯一
   * 配置的数据大于等于1则返回true,否则false
   */
  private function checkVatCountry($countryCode,$id=null){
  	if(empty($countryCode) || trim($countryCode)==''){
  		return false;
  	}
  	$result=0;
  	/**
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
  	*/
  	return  $result >= 1;
  }

  /*
   * VAT数据配置->弹出框页面及编辑数据（post）
   */
  public function edit_vat(){
  	$_id = input('id');
  	if(empty($_id)){
  		return '';
  	}
  	if($data = request()->post()){//是否提交
  		/**
  		$configName = trim($data['ConfigName']);
  		if(empty($configName)){
  			echo json_encode(array('code'=>101,'result'=>'ConfigName不可为空'));
  			exit;
  		}
  		//需要传入本次修改的数据的ID，排除自己
  		if($this->checkConfigName($configName,$_id)){
  			echo json_encode(array('code'=>102,'result'=>$configName.'重复'));
  			exit;
  		}
  		$mongo['ConfigName']   = $configName;
  		$mongo['ConfigValue'] = $data['ConfigValue'];
  		$mongo['Addtime']         = time();
  		$mongo['Remark']         = $data['Remark'];
  		$result_mongodb=Db::connect("db_mongo")->name(S_CONFIG)
  		->where(["_id" =>$_id])
  		->update($mongo);//存mongodb

  		if($result_mongodb){
  			echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
  			exit;
  		}else{
  			echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
  			exit;
  		}
  		*/
  	}else{
  		/**
  		$result=Db::connect("db_mongo")->name(S_CONFIG)
  		->where(["_id" =>$_id])
  		->field('_id,ConfigName,ConfigValue,Remark,Addtime')
  		->find();
  		$this->assign(['list'=>$result,]);
  		*/
  		return View('add_vat'); //共用一个页面
  	}
  }

    /**
     * 商城系统配置操作界面
     */
    public function mallConfigure(){
        $data = request()->post();
        $configName = '';
        $configValue = '';
        if(!empty($data)){
            if(!empty($data['ConfigName'])){
                $configName =$data['ConfigName'];
            }
        }
        $page=input('page');
        if(!$page){
            $page = 1;
        }
        $data['page'] = $page;
        $page_size = config('paginate.list_rows');
        $result = Db::connect("db_mongo")->name(S_CONFIG)
            ->where('ConfigName','in',['MobileIndexCoupons','IndexCouponsActivityPage','IndexCoupons','MobileIndexCategory'])
            ->field('_id,ConfigName,ConfigValue,Addtime,Remark,UpdateTime')
            ->order('UpdateTime','desc')
            ->paginate($page_size);
        $this->assign(['list'=>$result,'page'=>$result->render(),'configName'=>$configName,'configValue'=>$configValue]);
        return view('mallConfigure');
    }

    /**
     * 商城首页人工配置
     * 分类 配置添加
     */
    public function mallConfigUpdate(){
        $params = input();
        $key = !empty($params['key']) ? $params['key'] : '';
        if(empty($key)){
            return view('mallConfigure');
        }

        $configVal = Db::connect("db_mongo")->name(S_CONFIG)->where('ConfigName',$key)->field('_id,ConfigName,ConfigValue,Addtime,Remark')->find();
        $configVal = json_decode(htmlspecialchars_decode($configVal['ConfigValue']),true);
//        pr($configVal);die;
        $this->assign('config_val',$configVal);
        $this->assign('config_key',!empty($params['key']) ? $params['key'] : '');
        return view('mallConfigUpdate');
    }

    /**
     * 商城首页人工配置
     * 分类 配置添加
     */
    public function doMallConfigUpdate(){
        $params = input();
        $config_value = array();
        $config_key = !empty($params['config_key']) ? $params['config_key'] : '';
        switch($config_key){
            case 'IndexCoupons':
                $config_value['indexRightCoupon'] = explode(',',$params['index_coupon_ids']);
                $config_value['notLoginCoupon'] = $params['index_notlogin'];
                $config_value['loginCoupon'] = $params['index_login'];
                break;
            case 'MobileIndexCoupons':
                $config_value['indexGetCoupon'] = explode(',',$params['mobile_coupon_ids']);
                $config_value['notLoginCoupon'] = $params['mobile_notlogin'];
                $config_value['loginCoupon'] = $params['mobile_login'];
                break;
            case 'MobileIndexCategory':
                foreach($params['mobile_index_category'] as $val){
                    $class_id = !empty($val[1]) ? $val[1] : 0;
                    if(!is_numeric($class_id)){
                        echo json_encode(['code'=>1002,'msg'=>'分类ID必须是整型']);die;
                    }
                    $config_value[$class_id] = !empty($val[0]) ?  $val[0] : '';
                }
                if(count($config_value) < 3){
                    echo json_encode(['code'=>1002,'msg'=>'分类数据有误']);die;
                }
                break;
            case 'IndexCouponsActivityPage':
                foreach($params['activity_page'] as $key => $val){
                    $config_value[$key]['coupon_id'] = $val['coupon_id'];
                    $config_value[$key]['bg_img'] = $val['bg_img'];
                    $config_value[$key]['bg_color'] = $val['bg_color'];
                    if(!empty($val['coupon_show'])){
                        $config_value[$key]['coupon_show'] = explode(',',$val['coupon_show']);
                    }
                    if(!empty($val['product_show'])){
                        $config_value[$key]['product_show'] = explode(',',$val['product_show']);
                    }
                }
                $config_value = array_values($config_value);
                break;
        }
        $ret = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$config_key])->update(['ConfigValue' => json_encode($config_value),'UpdateTime'=>time()]);
        if($ret){
            echo json_encode(['code'=>200,'msg'=>'更新成功']);
        }else{
            echo json_encode(['code'=>1002,'msg'=>'更新失败']);
        }
        die;
    }

    /**
     * 商城缓存清除
     */
    public function clearRedisKey(){
        ini_set('max_execution_time', '0');
        $params = input();
        if(!empty($params['key'])){
            switch($params['key']){
                case 'IndexCoupons':
                    CommonLib::clearRedisCache('ADVERTISING_INFO_BY_');
                    CommonLib::clearRedisCache('SYSTEM_CONFIGVAL_IndexCoupons',false);
                    CommonLib::clearRedisCache('HOME_COUPONS',false);
                    CommonLib::clearRedisCache('HOME_COUPONS_DETAIL',true);
                    CommonLib::clearRedisCache('SYSTEM_CONFIGVAL_IndexCouponsActivityPage',false);
                    CommonLib::clearRedisCache('IndexCouponsRangeProduct',false);
                    break;
                case 'MobileIndexCategory':
                case 'MobileIndexCoupons':
                    CommonLib::clearRedisCache('SYSTEM_CONFIGVAL_MobileIndexCoupons',false);
                    CommonLib::clearRedisCache('HOME_COUPONS_MOBILE',false);
                    CommonLib::clearRedisCache('HOME_COUPONS_DETAIL_MOBILE',false);
                    CommonLib::clearRedisCache('INDEX_CATEGORY_TOP_RANGE',false);
                    break;
                case 'IndexCouponsActivityPage':
                    CommonLib::clearRedisCache('ALL_FLASHDEAL_DATA_');
                    CommonLib::clearRedisCache('SYSTEM_CONFIGVAL_IndexCoupons',false);
                    CommonLib::clearRedisCache('HOME_COUPONS',false);
                    CommonLib::clearRedisCache('SYSTEM_CONFIGVAL_IndexCouponsActivityPage',false);
                    CommonLib::clearRedisCache('HotProductPageConfig_');
                    CommonLib::clearRedisCache('ADVERTISING_INFO_BY_newArrivals-banner',false);
                break;
                default:
                    echo 'key不存在！';die;
            }
            echo '清除成功！';die;
        }else{
            echo '无删除key'; die;
        }
    }
}