<?php
namespace app\admin\controller;
use \think\Session;
use think\View;
use think\Controller;
use think\Paginator;
use think\Log;
use think\Db;
use app\admin\dxcommon\BaseApi;
use app\admin\model\Businessmanagement as Business;
use app\admin\dxcommon\ExcelTool;

/**
 * 工具类:历史数据同步:
 * 1.产品类别，产品数据;
 * 2.品牌数据;
 * 3.销售属性数据;
 * @author heng.zhang 2018-05-20
 *
 */
class Tool extends Action
{
     public function __construct(){
       // Action::__construct();
       $data = request()->post();
       // dump($data);
       // if($data['access_token'] != config('lms_logistics_token') || empty($data['access_token'])){
       //   echo  json_encode(array('code'=>100),true);
       //   exit;
       // }
       define('PRODUCT_CLASS', 'product_class');//MYSQL
       define('PRODUCT_CLASS_MAP', 'product_class_map');//MYSQL
       define('S_CONFIG', 'dx_sys_config');//Nosql数据表
       define('LOGISTICS', 'LogisticsManagement');
       define('PRODUCT_CLASS_MG','dx_product_class');//Nosql数据表-- 上线时需要修改名称 TODO
         //类别变更历史表
       define('MOGOMODB_P_CLASS_HISTORY', 'dx_product_class_histories');
       define('MOGOMODB_B_A', 'dx_brand_attribute');
       define('MOGOMODB_ATTRIBUTE', 'dx_attribute');
       define('MOGOMODB_ATTRIBUTE_HISTORY', 'dx_attribute_history');
        //ERP类别表
       define('PDVEE_PRODUCT_CATALOG', 'pdvee_product_catalog');//MYSQL

    }

    /**同步类别数据
     *
     * @return isView：1 代表执行类别同步
     */
    public function index($isView='')
    {
       if(!empty($isView)){
       	  if($isView ==1){#读取PDC资源文件到数据库
       	  	  echo 'Run start-------------------';
	       	  $this -> readAndWriteData();
	       	  echo 'Run end-------------------';
	       	  //exit();
	       	  //$attribute = Db::connect("db_mongo_old")->name("Category")->where(['_id'=>0])->select();
       	  //$attribute = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>4])->find();//存mongodb
       	  }elseif($isView ==2){#导入后台与ERP类别ID关系数据
       	  	  $this -> read_admin_and_erp_map_data();
       	  	  echo 'Run end 2222-------------------';
       	  }elseif($isView ==3){
       	  }
       }
       return view('index');
    }

    /**
     * 同步类别数据--调用ERP接口获取类别数据
     */
    public function getERPData(){
    	#调用ERP接口获取数据后插入ERP类别表
    	#该方法只更新数据到后台
    	/** 注释代码
    	#读取产品表一级类别
    	$where_first['pid'] = 0;
    	//$where_first['id'] = array(array('gt',20),array('elt',1799),array('neq',1106));
    	$where_first['id'] = '199'; //TODO 该行测试代码
    	$where_first['status'] = 1;
    	$firstClass = DB::name(PRODUCT_CLASS)
					    	->where($where_first)
					    	->order('id asc')
					    	->field('id')
					    	->select();
		#最大一级分类ID
    	$firstClassMaxId= max($firstClass)['id'];
    	//dump($firstClass);
    	//die();
    	 * */
    	#读取一级类别Map
    	$firstClassMap = DB::name(PRODUCT_CLASS_MAP)
						    	->order('id asc')
						    	->field('mall_id,erp_id,dx_new_id')
						    	->select();
    	#基础数据都有的情况下去调用ERP接口获取数据
    	if(count($firstClassMap)>0){
    		 /**
            //先获取当前数据库里最大的类别ID和排序（分两次查询，最大ID的sort并不一定是最大）
    		$maxId = DB::name(PRODUCT_CLASS)
				    		->where(['pid'=>0,'status'=>1])
				    		->max('id');
    		$maxSortId = DB::name(PRODUCT_CLASS)
				    		->where(['pid'=>0,'status'=>1])
				    		->max('sort');
    		//echo '<br>$erpMap_id一级类别不重复：'.$erpMap_id;
    		//生成2位数，不足前面补0
    		$firstClassMaxId=sprintf("%'02s", $maxId+1);
    		echo '<br>$firstClassMaxId：'.$firstClassMaxId;
    		*/
    		#循环一级分类
    		foreach($firstClassMap as $key){
	    		//echo 'ddd';
	    		#根据DX类别的ID取得ERP类别的ID数据
	    		$erpMap_id=$key['erp_id'];
	    		$dxMap_id =$key['mall_id'];
	    		$firstClassMaxId = $key['dx_new_id'];
	    		echo '<br>$firstClassMaxId：'.$firstClassMaxId;
    			echo '<br>关系表里取得的dxMap_id:'.$dxMap_id;
    			//die();
    			#一级品类ID=0，代表是DX后台缺少的一级分类，则需要拉取ERP系统的数据合并
    			#此逻辑分支是代表DX没有的数据，需要新增
    			if($dxMap_id == 0){// 245 行结束这个逻辑
	    			#如果关系表数据erp_id 也是空，则本次遍历结束
	    			if($erpMap_id <=0){
		    			echo '<br>erpMap_id:'.$erpMap_id.'错误';
		    			continue;
	    			}
    			    #产品类别在后台不存在则插入
    			    #获取ERP数据,调用ERP接口获取当前类别的一级分类数据
    			    echo '<br>获取ERP一级分类:'.$erpMap_id.'的子类';
    				$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=CategoryData&id='.$erpMap_id;
		    		$request = file_get_contents($url);
		    		$erpSingleData = json_decode($request,true);
		    		$erpSingleData = $erpSingleData['message'][0];//此处没判断，比较乐观
		    		echo '<br>获取到数据$erpSingleData:'.count($erpSingleData).'字段';

    				#判断ID是否重复
    				$check = DB::name(PRODUCT_CLASS)
				    					->where(['id'=>$firstClassMaxId])
				    					->count();
				    					#不重复则插入
				    if($check>0){
				    		echo '<br>一级类别重复：'.$firstClassMaxId.'__LINE__:'.__LINE__;
				    		continue;
				    }
    				$data = array(
    							'id'=>$firstClassMaxId, //新增的一级分类ID
    							'title_en'=>str_replace("\'","'",$erpSingleData['title_en']),//$erpSingleData['title_en'],
    							'title_cn'=>$erpSingleData['title_cn'],
    							'pid' => 0,
    							'status' =>1,
    							'sort' =>$maxSortId+1,
    							'addtime' =>time(),
    							'erp_id' =>$erpSingleData['id'],
    							'add_author'=>Session::get("username")
    							);
    				$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
    				echo '<br>新增后台数据库一级分类结果:';
    				echo $result ? '成功':'失败';
    				echo '<br>一级类别ID:'.$firstClassMaxId;
    							//dump($result);
    								//die();
    								#一级类别插入成功后，开始处理二级分类
    				if($result){
    						#插入二级分类
    						#获取该一级分类$erpMap_id的全部二级分类数据
    					    $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$erpMap_id;
    						$request = file_get_contents($url);
    						$secondClassErpArr = json_decode($request,true);
    												//dump($secondClassErpArr);
    												//die();
    						echo '<br>获取到ERP大类：'.$erpMap_id.'的二级分类：'.count($secondClassErpArr).'条数据，__LINE__:'.__LINE__;
    						if(count($secondClassErpArr)>0){
    							for($i =0;$i<count($secondClassErpArr);$i++){
    							//生成2位数，不足前面补0
    							$secondClassFormatID=sprintf("%'02s",$i+1);
    							echo '<br> $secondClassFormatID:'.$secondClassFormatID;
    							#判断ID是否重复
    							$check = DB::name(PRODUCT_CLASS)
			    							->where(['id'=>$firstClassMaxId.$secondClassFormatID])
			    							->count();
			    				if($check>0){
    								echo '<br>二级类别重复：'.$firstClassMaxId.$secondClassFormatID.'__LINE__:'.__LINE__;
    								continue;
			    				}
    							$data = array(
    										'id'=>$firstClassMaxId.$secondClassFormatID,
    										'title_en'=>str_replace("\'","'",$secondClassErpArr[$i]['title_en']),//$secondClassErpArr[$i]['title_en'],
    										'title_cn'=>$secondClassErpArr[$i]['title_cn'],
    										'pid' => $firstClassMaxId,
    										'status' =>1,
    										'sort' =>$i,
    										'addtime' =>time(),
    									    'erp_id' =>$secondClassErpArr[$i]['id'],
				       	  			 		'add_author'=>Session::get("username")
    										);
    							$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
    							echo '<br>新增二级分类结果:';
    							echo $result ? '成功':'失败';
    							echo '<br>二级类别ID:'.$firstClassMaxId.$secondClassFormatID.',__LINE__:'.__LINE__;
    							#二级分类插入成功后，开始插入三级分类
    							if($result){
    								#插入三级分类
    								#获取该二级分类的全部三级分类数据  $secondClassErpArr[$i]['id']
    								$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$secondClassErpArr[$i]['id'];
    								$request = file_get_contents($url);
    								$thirdClassErpArr = json_decode($request,true);
    								echo '<br> 获取到ERP二级分类:'.$secondClassErpArr[$i]['id'].'的三级分类数据:'.count($thirdClassErpArr).'条';
    								if(count($thirdClassErpArr)>0){
	    								for($j =0;$j<count($thirdClassErpArr);$j++){
	    									//生成2位数，不足前面补0
	    									$thirdClassFormatID=sprintf("%'02s", $j+1);
	    									echo '<br>$thirdClassFormatID:'.$thirdClassFormatID;
	    									$third_class_id=$firstClassMaxId.$secondClassFormatID.$thirdClassFormatID;
	    									#判断ID是否重复
	    									$check = DB::name(PRODUCT_CLASS)
					    									->where(['id'=>$third_class_id])
					    									->count();
	    									if($check>0){
	    										echo '<br>三级类别重复：'.$third_class_id.'__LINE__:'.__LINE__;
	    										continue;
	    									}
	    									$data = array(
	    												'id'=>$third_class_id,
	    												'title_en'=>str_replace("\'","'",$thirdClassErpArr[$j]['title_en']),
	    												'title_cn'=>$thirdClassErpArr[$j]['title_cn'],
	    												'pid' => $firstClassMaxId.$secondClassFormatID,//二级分类的ID
	    												'status' =>1,
	    												'sort' =>$j,
	    												'addtime' =>time(),
	    												'erp_id' =>$thirdClassErpArr[$j]['id'],
	    												'add_author'=>Session::get("username")
	    												);
	    									$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
	    									echo '<br>新增三级分类结果:';
	    									echo $result ? '成功':'失败';
					       	  			 	echo '<br>三级类别ID:'.$third_class_id.',__LINE__:'.__LINE__;
	    									#三级分类插入成功后，开始插入四级分类
	    									if($result){
	    										#插入四级分类
	    										#获取该三级分类的全部四级分类数据  $thirdClassErpArr[$j]['id']
	    										$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='. $third_class_id;
	    										$request = file_get_contents($url);
	    										$fourClassErpArr = json_decode($request,true);
	    										echo '<br> 获取到ERP三级分类:'.$third_class_id.'的四级分类数据:'.count($fourClassErpArr).'条';
	    										if(count($fourClassErpArr)>0){
	    											for($z =0;$z<count($fourClassErpArr);$z++){
	    												//生成2位数，不足前面补0
	    												$fourClassFormatID=sprintf("%'02s", $z+1);
	    												$four_class_id =$firstClassMaxId.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID;
	    												echo '<br>$fourClassFormatID:'.$fourClassFormatID.',__LINE__:'.__LINE__;
	    												$data = array(
	    															'id'=>$four_class_id,
	    															'title_en'=>str_replace("\'","'",$fourClassErpArr[$z]['title_en']),//$fourClassErpArr[$z]['title_en'],
	    															'title_cn'=>$fourClassErpArr[$z]['title_cn'],
	    															'pid' => $third_class_id,//三级分类的ID
	    															'status' =>1,
	    															'sort' =>$z,
	    															'addtime' =>time(),
	    															'erp_id' =>$fourClassErpArr[$z]['id'],
	    															'add_author'=>Session::get("username")
	    															);
	    												$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
	    												echo '<br>新增四级分类结果:';
	    												echo $result ? '成功':'失败';
	    												echo '<br>四级类别ID:'.$four_class_id.',__LINE__:'.__LINE__;
	    											}
	    										}
    									 }
    						            }
				       	  			 }
				       	  		}
		       	  			 }
    					}else{
    						echo '<br>获取'.$firstClassMaxId.'一级类别的下的二级分类数据为空.<br>'.',__LINE__:'.__LINE__;
    					}
    				 }
    		}else{//end if($dxMap_id == 0)
    			echo '<br>$dxMap_id 不等于0,开始走对比数据，做者插入子类逻辑--------';
    			#一级品类ID>0，代表是DX后台已的一级分类，则需要更新或者插入子级类别的数据
    			#获取ERP类别数据 通过大类ID获取-- $erpMap_id
    			echo '<br>开始获取ERP一级分类的数据，erpMap_id:'.$erpMap_id.',__LINE__:'.__LINE__;
    			$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=CategoryData&id='.$erpMap_id;
    			$request = file_get_contents($url);
    			$firstClassERPData =json_decode($request,true);
    			$firstClassERPData = $firstClassERPData['message'][0];
    			//dump($firstClassERPData['title_cn']);
    			//die();
    			if(count($firstClassERPData) >0){
    				echo '<br>获取到erpMap_id二级分类:'.$erpMap_id.'条数据';
	    			#将ERP系统的一级类别（中文/英文标题）数据更新到Admin
	    			$updateFirstClass =  DB::name(PRODUCT_CLASS)
	    								    ->where(['id'=>$dxMap_id,'status'=>1])
	    									->update(['title_cn'=>str_replace("\'","'",$firstClassERPData['title_cn'])//$firstClassERPData['title_cn']
	    											,'title_en'=>$firstClassERPData['title_en']
	    											,'edittime'=>time()
	    											,'erp_id' =>$firstClassERPData['id']
	    											,'edit_author'=>Session::get("username")]);
	    			echo '<br>$updateFirstClass更新';
	    			echo $updateFirstClass ? '成功':'失败';
	    			echo '<br>大类ID:'.$dxMap_id.'__LINE__:'.__LINE__.'<br>';
    			    if($updateFirstClass){#更新成功后，继续更新分类数据
	    				#DX获取二级分类
	    				$secondClassAdmin = DB::name(PRODUCT_CLASS)
	    									->where(['pid'=>$dxMap_id,'status'=>1])
	    									->order('id asc')
	    									->field('id')
	    									->select();
	    				#获取二级分类-ERP,$erpMap_id
	    				echo '<br>开始查找ERP Map_id的子类，id:'.$erpMap_id;
	    				$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$erpMap_id;
	    				$request = file_get_contents($url);
	    				$secondClassERP = json_decode($request,true);
    				//dump($secondClassERP);
    				//die();
    				#二级分类双方肯定都有，只要处理这个逻辑就好，任何一方没有数据，均无需处理
					if(count($secondClassAdmin)>0 && count($secondClassERP)>0){
						echo '<br>获取到erpMap_id:'.$erpMap_id.'的子类'.count($secondClassERP).'条数据'.'__LINE__:'.__LINE__;
	    				#遍历更新
	    				#按$secondClassAdmin数组的索引逐个取ID数据，中文名称和英文名称数据从相同索引的$secondClassERP数组取
	    				$forCout = 0; //循环次数使用两个数组小的一个数组的数量
	    				if(count($secondClassAdmin) >= count($secondClassERP)){
	    					$forCout = count($secondClassERP);//只用ERP的数据更新过来即可，DX多余的分类不管
	    				}else{
	    					$forCout = count($secondClassAdmin);//DX的分类少，那么先更新自己有的，然后再用ERP多出的数据插入我们系统
	    				}
	    				echo '<br>DX二级类别数：'.count($secondClassAdmin);
	    				echo '<br> ERP二级类别数：'.count($secondClassERP);
	    				echo '<br> forCout:'.$forCout;
    				    for($k=0;$k < $forCout; $k++){
	    					$title_cn_02 = '';
	    					$title_en_02 = '';
	    					$second_class_id=0;
	    					if(isset($secondClassERP[$k]['id'])){
	    						if(isset($secondClassERP[$k]['title_cn'])){
	    							$title_cn_02 =$secondClassERP[$k]['title_cn'];
	    						}else{
	    							echo '<br>ERP数据缺失title_cn字段.<br>';
	    						}
		    					if(isset($secondClassERP[$k]['title_en'])){
		    						$title_en_02 =$secondClassERP[$k]['title_en'];
							    }
							    $second_class_id=$secondClassERP[$k]['id'];
		    					#移除已经匹配到的数据，剩余数据需要插入Admin
		    					unset($secondClassERP[$k]);
	    				   	}else{
	    					  	echo '<br>ERP数据缺失id字段.<br>';
	    				   	}
	    				    //dump($secondClassAdmin[$k]['id']);
	    				    //die();
	    				   	echo '<br>取得类别ERP 二级分类second_class_id:'.$second_class_id.',英文标题：'.$title_en_02.'<br>'.'__LINE__:'.__LINE__;
	    				    #英文标题不为空则更新
	    			        if(!empty($title_en_02)){
	    					    #更新类别数据
	    					    $updateSecondClass =  DB::name(PRODUCT_CLASS)
	    												->where(['id'=>$secondClassAdmin[$k]['id'],'status'=>1])
	    												->update(['title_cn'=>$title_cn_02
	    											           		,'title_en'=>str_replace("\'","'",$title_en_02)//$title_en_02
	    													   		,'edittime'=>time()
	    													   		,'erp_id' =>$second_class_id
	    													   		,'edit_author'=>Session::get("username")]);
		    					echo '<br>$updateSecondClass更新';
		    					echo $updateSecondClass ? '成功':'失败';
		    					echo '<br>DX二级分类ID:'.$secondClassAdmin[$k]['id'].'第'.$k.'次<br>';
		    					//die();
    							if($updateSecondClass){#二级分类更新成功后，继续更新子分类数据
	    							#获取三级分类
	    							$thirdClassAdmin = DB::name(PRODUCT_CLASS)
		    											->where(['pid'=>$secondClassAdmin[$k]['id'],'status'=>1])
		    											->order('id asc')
		    											->field('id')
		    											->select();
		    						#获取三级分类-ERP,$class_id
		    						//dump($k);
		    						//dump($secondClassERP);
		    						//die();
		    						echo '<br>开始查找ERP二级分类second_class_id的子类，id:'.$second_class_id.'__LINE__:'.__LINE__;
		    						$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$second_class_id;
		    						$request = file_get_contents($url);
		    						$thirdClassERP = json_decode($request,true);
		    						echo '<br>获取到ERP $second_class_id:'.$second_class_id.'的三级分类'.count($thirdClassERP).'条数据';
		    						#DX或者ERP没有
		    						if(count($thirdClassAdmin)>0 && count($thirdClassERP)>0){
		    							//dump($thirdClassERP);
		    							//die();
		    							#遍历更新
		    							#按$thirdClassAdmin数组的索引逐个取ID数据，中文名称和英文名称数据从相同索引的$thirdClassERP数组取
		    							$forCout = 0; //循环次数使用两个数组小的一个数组的数量
		    							if(count($thirdClassAdmin) >= count($thirdClassERP)){
		    								$forCout = count($thirdClassERP);
		    							}else{
		    								$forCout = count($thirdClassAdmin);
		    							}
		    							echo '<br> DX三级类别数：'.count($thirdClassAdmin);
		    							echo '<br> ERP三级类别数：'.count($thirdClassERP);
		    							echo '<br> forCout:'.$forCout;
		    							//die();
		    							for($j=0;$j < $forCout; $j++){
		    								$title_cn_03 = '';
		    								$title_en_03 = '';
		    								$third_class_id=0;
											if(isset($thirdClassERP[$j]['id'])){
			    								if(isset($thirdClassERP[$j]['title_cn'])){
			    									$title_cn_03 =$thirdClassERP[$j]['title_cn'];
			    								}else{
			    									echo '<br>ERP数据缺失title_cn字段.<br>';
			    								}
			    							if(isset($thirdClassERP[$j]['title_en'])){
			    								$title_en_03 =$thirdClassERP[$j]['title_en'];
			    							}
			    							$third_class_id=$thirdClassERP[$j]['id'];
			    							//dump('$third_class_id'.$third_class_id);
		    							   #移除已经匹配到的数据，剩余数据需要插入Admin
		    							   unset($thirdClassERP[$j]);
			    						}else{
			    							echo 'ERP数据缺失id字段.<br>';
			    						}
			    						echo '<br>取得$third_class_id:'.$third_class_id.',标题$title_en_03:'.$title_en_03;
		    							#英文标题不为空则更新
		    					 		if(!empty($title_en_03)){
				    					    #更新类别数据
				    						$updateThirdClass =  DB::name(PRODUCT_CLASS)
				    												->where(['id'=>$thirdClassAdmin[$j]['id'],'status'=>1])
				    												->update(['title_cn'=>$title_cn_03
				    														  ,'title_en'=>str_replace("\'","'",$title_en_03)//$title_en_03
				    														  ,'edittime'=>time()
				    														  ,'erp_id' =>$third_class_id
				    														  ,'edit_author'=>Session::get("username")]);
				    						echo '<br>$updateThirdClass更新';
				    						echo $updateThirdClass ? '成功':'失败';
				    						echo '<br>三级分类ID:'.$thirdClassAdmin[$j]['id'].'第'.$j.'次'.'__LINE__:'.__LINE__;
				    						//die();
				    					    if($updateThirdClass){#三级分类更新成功后，继续更新子分类数据
					    					    //echo '$updateThirdClass----------------';
					    						#获取四级分类
					    						$fourClassAdmin = DB::name(PRODUCT_CLASS)
					    											->where(['pid'=>$thirdClassAdmin[$j]['id'],'status'=>1])
					    											->order('id asc')
					    											->field('id')
					    											->select();
					    					     #获取四级分类-ERP,$class_id
					    						 echo '<br>开始查找三级分类third_class_id的子类，id:'.$third_class_id;
					    						 $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$third_class_id;
					    						 $request = file_get_contents($url);
					    						 $fourClassERP = json_decode($request,true);
					    						 //dump($fourClassERP);
					    						 //die();
					    						 echo '<br>DX四级分类：$fourClassAdmin：'.count($fourClassAdmin).'条';
					    						 echo '<br>ERP四级分类：$fourClassERP：'.count($fourClassERP).'条';
					    						 #判断四级分类是否有数据；--如果DX有且ERP也有，则更新
					    						 if(count($fourClassAdmin)>0 && count($fourClassERP)>0){
					    						 	echo '<br>获取到ERP $$third_class_id:'.$third_class_id.'的四级分类'.count($fourClassERP).'条数据';
					    							#遍历更新
					    							#按$fourClassAdmin数组的索引逐个取ID数据，中文名称和英文名称数据从相同索引的$fourClassERP数组取
					    							$forCout = 0; //循环次数使用两个数组小的一个数组的数量
					    							if(count($fourClassAdmin) >= count($fourClassERP)){
					    								$forCout = count($thirdClassERP);
					    							}else{
					    								$forCout = count($fourClassAdmin);
					    							}
													echo '<br>DX四级类别数：'.count($fourClassAdmin);
						    						echo '<br>ERP四级类别数：'.count($fourClassERP);
						    						echo '<br>forCout:'.$forCout;
					    							for($z=0;$z < $forCout; $z++){
					    								$title_cn_04 = '';
					    								$title_en_04 = '';
					    								if(isset($fourClassERP[$z]['id'])){
					    									if(isset($fourClassERP[$z]['title_cn'])){
					    										$title_cn_04 =$fourClassERP[$z]['title_cn'];
					    									}else{
					    										echo '<br>ERP数据缺失title_cn字段.<br>';
					    									}
					    								    if(isset($fourClassERP[$z]['title_en'])){
					    			                       		$title_en_04 =$fourClassERP[$z]['title_en'];
					    									}
					    									#移除已经匹配到的数据，剩余数据需要插入Admin
					    									unset($fourClassERP[$z]);
					    								}else{
					    									echo '<br>ERP数据缺失id字段.<br>';
					    								}
					    								echo '$fourClassERP[$z]["id"]:'.$fourClassERP[$z]['id'].',标题$title_en_04：'.$title_en_04;
					    								#英文标题不为空则更新
					    								if(!empty($title_en_04)){
					    									#更新类别数据
					    									$updateFourClass =  DB::name(PRODUCT_CLASS)
					    														  ->where(['id'=>$fourClassAdmin[$z]['id'],'status'=>1])
					    														  ->update(['title_cn'=>$title_cn_04
					    														    		,'title_en'=>str_replace("\'","'",$title_en_04)//$title_en_04
					    														    		,'edittime'=>time()
					    														    		,'erp_id' =>$fourClassERP[$z]['id']
					    														    		,'edit_author'=>Session::get("username")]);
					    									echo '<br>$updateFourClass更新';
					    									echo $updateFourClass ? '成功':'失败';
					    									echo '<br>四级分类ID:'.$fourClassAdmin[$z]['id'].'第'.$z.'次<br>';
					    									echo '<br>四级分类 更新完成';
					    									die('四级分类 更新完成');
					    								}
    							 					 }//for($z=0;$z < $forCout; $z++){ 408 行
					    							  //增加ERP多出的数据--四级分类
					    							  if(count($fourClassERP)>0){
					    							  	//重置数组索引
					    							  	sort($fourClassERP);
					    							  	$fourClassMaxId = DB::name(PRODUCT_CLASS)
										    							  	->where(['pid'=>$thirdClassAdmin[$j]['id'],'status'=>1])
										    							  	->max('id');
					    							  	#当前$thirdClassAdmin[$j]['id']（二级品类ID）下全部的三级分类里的最大sort
					    							  	$fourClassMaxSortId = DB::name(PRODUCT_CLASS)
											    							  	->where(['pid'=>$thirdClassAdmin[$j]['id'],'status'=>1])
											    							  	->max('sort');
					    							  	$fourClassMaxSortId +=1;
					    							  	$fourClassMaxId += 1;
					    								#开始插入ERP-$fourClassERP数组中剩余的数据
					    								for($y =0; $y < count($fourClassERP); $y++){
					    									#查询最大ID--该类别$fourClassERP[$x]['id']（三级品类ID）的四级分类的最大ID
					    									echo '<br>开始插入ERP-$fourClassERP数组中剩余的数据,<br>$thirdClassAdmin[$j]["id"]:'.$thirdClassAdmin[$j]['id'];
					    									#生成2位数，不足前面补0
					    									$fourClassFormatID=sprintf("%'02s", $fourClassMaxId+$y+1);

					    									#判断ID是否重复
					    									$check = DB::name(PRODUCT_CLASS)
									    									->where(['id'=>$thirdClassAdmin[$j]['id'].$fourClassFormatID])
									    									->count();
					    									if($check>0){
					    										echo '<br>四级类别重复：'.$thirdClassAdmin[$j]['id'].$fourClassFormatID.'__LINE__:'.__LINE__;
					    										continue;
					    									}
					    									#插入四级分类数据
					    									$data = array(
					    											'id'=>$thirdClassAdmin[$j]['id'].$fourClassFormatID,
					    											'title_en'=>str_replace("\'","'",$fourClassERP[$y]['title_en']),//$fourClassERP[$y]['title_en'],
					    											'title_cn'=>$fourClassERP[$y]['title_cn'],
					    											'pid' => $thirdClassAdmin[$j]['id'],
					    											'status' =>1,
					    											'sort' =>$fourClassMaxSortId + $y,
					    											'addtime' =>time(),
					    											'erp_id' =>$fourClassERP[$y]['id'],
					    											'add_author'=>Session::get("username")
					    										);
					    								    $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
					    									echo '<br>新增四级分类结果:';
					    									echo $result ? '成功':'失败';
					    									echo '<br>id:'.$thirdClassAdmin[$j]['id'].$fourClassFormatID.'<br>';
					    							    }
					    							}else{
					    								echo '<br> ERP没有多余的四级分类增加;';
					    							}
    											}else{//结束  if(count($fourClassAdmin)>0 && count($fourClassERP)>0){ 415行
					    							echo '<br> DX或者ERP没有四级分类,DX三级分类ID:'.$thirdClassAdmin[$j]['id'];
					    							//DX没有四级分类，而ERP有，则插入--只有四级分类比较特殊，存在DX没有，ERP有的情况
					    							if(count($fourClassERP)>0){
					    								echo '<br> ERP比DX多四级分类;';
					    								for($h =0; $h <count($fourClassERP); $h++){
					    									//dump('id:'$thirdClassAdmin[$j]['id'].$fourClassFormatID);
					    									//die();
					    									#生成2位数，不足前面补0
					    									$fourClassFormatID=sprintf("%'02s", $h+1);
					    									#判断ID是否重复
					    									$check = DB::name(PRODUCT_CLASS)
								    									->where(['id'=>$thirdClassAdmin[$j]['id'].$fourClassFormatID])
								    									->count();
					    									if($check>0){
					    										echo '<br>四级类别重复：'.$thirdClassAdmin[$j]['id'].$fourClassFormatID.'__LINE__:'.__LINE__;
					    										continue;
					    									}
					    									#插入四级分类数据
					    									$data = array(
								    									'id'=>$thirdClassAdmin[$j]['id'].$fourClassFormatID,
								    									'title_en'=>str_replace("\'","'",$fourClassERP[$h]['title_en']),//$fourClassERP[$h]['title_en'],
								    									'title_cn'=>$fourClassERP[$h]['title_cn'],
								    									'pid' => $thirdClassAdmin[$j]['id'], //父类ID
								    									'status' =>1,
								    									'sort' => $h,
								    									'addtime' =>time(),
								    									'erp_id' =>$fourClassERP[$h]['id'],
								    									'add_author'=>Session::get("username")
					    											);
					    									$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
					    									echo '<br>新增四级分类结果:';
					    									echo $result ? '成功':'失败';
					    									echo '<br>id:'.$thirdClassAdmin[$j]['id'].$fourClassFormatID.'<br>';
					    								}
					    							}else{
					    								echo '<br> ERP也没有四级分类:'.$thirdClassAdmin[$j]['id'];
					    							}
					    						}//结束 }else{//结束  if(count($fourClassAdmin)>0 && count($fourClassERP)>0){
    					 					} //if($updateThirdClass){#三级分类更新成功后，继续更新子分类数据 380行
    									}//if(!empty($title_en_03)){  369行
    								} //for($j=0;$j < $forCout; $j++){ 347行  循环更新三级分类数据
    			                echo '<br>开始插入ERP多的三级分类数据:'.count($thirdClassERP).'条------------------------------------';
				                //开始插入ERP多的三级分类数据
				    		    if(count($thirdClassERP)>0){
				    		    	//重新排序数组索引
				    		    	sort($thirdClassERP);
				    		    	//dump('dddddd');
				    		    	//dump($thirdClassERP);
				    		    	//die();

				    		    	#查询最大ID--该类别$secondClassAdmin[$k]['id']（二级品类ID）的三级分类的最大ID
				    		    	$thirdClassMaxId = DB::name(PRODUCT_CLASS)
										    		    	->where(['pid'=>$secondClassAdmin[$k]['id'],'status'=>1])
										    		    	->max('id');
				    		    	#当前类最大sort
				    		    	$thirdClassMaxSortId = DB::name(PRODUCT_CLASS)
										    		    	->where(['pid'=>$secondClassAdmin[$k]['id'],'status'=>1])
										    		    	->max('sort');
									$thirdClassMaxId +=1;
				    		    	$thirdClassMaxSortId +=1;
				    		    	#开始插入ERP-$fourClassERP数组中剩余的数据
				    		    	for($t =0; $t <count($thirdClassERP); $t++){
				    		    		#生成2位数，不足前面补0
				    		    		$thirdClassFormatID=sprintf("%'02s", $thirdClassMaxId+$t+1);
				    		    		echo '<br> $thirdClassFormatID:'.$thirdClassFormatID;
				    		    		$count = DB::name(PRODUCT_CLASS)
										    		  ->where(['pid'=>$secondClassAdmin[$k]['id'].$thirdClassFormatID
										    		    		,'status'=>1])
										    		  ->count();
										//dump($x);
				                        //dump($thirdClassERP[$x]['title_en']);
				                        //die();
										#防止重复插入
				    		    		if($count<1){
					    		    		#插入三级分类数据
					    		    		$data = array(
								    		    		'id'=>$secondClassAdmin[$k]['id'].$thirdClassFormatID,
								    		    		'title_en'=>str_replace("\'","'",$thirdClassERP[$t]['title_en']),//$thirdClassERP[$t]['title_en'],
								    		    		'title_cn'=>$thirdClassERP[$t]['title_cn'],
								    		    		'pid' => $secondClassAdmin[$k]['id'],
								    		    		'status' =>1,
								    		    		'sort' =>$thirdClassMaxSortId + $t,
								    		    		'addtime' =>time(),
								    		    		'erp_id' =>$thirdClassERP[$t]['id'],
								    		    		'add_author'=>Session::get("username")
					    		    				);
					    		    		$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
					    		    		echo '<br>新增三级分类结果:';
					    		    		echo $result ? '成功':'失败';
					    		    		echo '<br>$secondClassAdmin[$k]["id"].$thirdClassFormatID:'.$secondClassAdmin[$k]['id'].$thirdClassFormatID.'<br>';
					    		    		//die();
					    		    		#三级插入成功后插入四级数据
					    		    		if($result){
					    		    			#获取四级分类-ERP,$thirdClassERP[$t]['id']
					    		    			echo '<br> 开始获取三级分类的thirdClassERP[$t]["id"]'.$thirdClassERP[$t]['id'].'的子级分类,第'.$t.'次执行';
					    		    			$url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$thirdClassERP[$t]['id'];
					    		    			$request = file_get_contents($url);
					    		    			$fourClassERP = json_decode($request,true);
					    		    			echo '<br>获取到数据$fourClassERP:'.count($fourClassERP).'条';
					    		    			if(count($fourClassERP)>0){
					    		    				for($e =0; $e <count($fourClassERP); $e++){
					    		    					#生成2位数，不足前面补0
					    		    					$fourClassFormatID=sprintf("%'02s",$e+1);
					    		    					echo '<br> $fourClassFormatID:'.$fourClassFormatID;
					    		    					$count = DB::name(PRODUCT_CLASS)
									    		    					->where(['pid'=>$secondClassAdmin[$k]['id'].$thirdClassFormatID.$fourClassFormatID
									    		    							,'status'=>1])
									    		    					->count();
									    		        if($count<1){
										    		    	#插入四级分类数据
						    		    					$data = array(
									    		    					'id'=>$secondClassAdmin[$k]['id'].$thirdClassFormatID.$fourClassFormatID,
									    		    					'title_en'=>str_replace("\'","'",$fourClassERP[$e]['title_en']),//$fourClassERP[$e]['title_en'],
									    		    					'title_cn'=>$fourClassERP[$e]['title_cn'],
									    		    					'pid' => $secondClassAdmin[$k]['id'].$thirdClassFormatID, //父级分类
									    		    					'status' =>1,
									    		    					'sort' =>$e,
									    		    					'addtime' =>time(),
									    		    					'erp_id' =>$fourClassERP[$e]['id'],
									    		    					'add_author'=>Session::get("username")
						    		    								);
						    		    					$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
						    		    					echo '<br>新增四级分类结果:';
						    		    					echo $result ? '成功':'失败';
						    		    					echo '<br>id:'.$secondClassAdmin[$k]['id'].$thirdClassFormatID.$fourClassFormatID.'<br>';
						    		    					//die();
									    		        }else{
									    		        	echo '<br>四级分类$secondClassAdmin[$k]["id"].$thirdClassFormatID.$fourClassFormatID:'.$secondClassAdmin[$k]['id'].$thirdClassFormatID.$fourClassFormatID.'重复';
									    		        }
					    		    				}
					    		    			}else{
					    		    				echo '无四级分类数据';
					    		    			}
					    		    		} //结束，三级插入成功后插入四级数据
				    		    	  	}else{//结束防止重复插入
				    		    	  	 	echo '<br>四级分类重复，ID:'.$dxMap_id.$secondClassAdmin[$k]['id'].$thirdClassFormatID;
				    		    	  	}
				    		    	} //for($t =0; $t <count($thirdClassERP); $t++){ 开始插入ERP-$fourClassERP数组中剩余的数据 520行
				    		    }//结束插入ERP多的三级分类数据
				    		  }//#DX或者ERP没有,if(count($thirdClassAdmin)>0 && count($thirdClassERP)>0){ 332行
				    	   }//if($updateSecondClass){#二级分类更新成功后，继续更新子分类数据  316行
				       }// #英文标题不为空则更新  if(!empty($title_en_02)){ 305行
       //-------------------------二级品类数据for 循环完成------------   for($k=0;$k < $forCout; $k++){  279行
        }#遍历Admin数组数据完成
    	 //dump($secondClassERP);
    	 //die();
    	 //ERP类别有剩余的二级分类数据，则增加到DX的类别里
       if(count($secondClassERP)>0){
       	   echo '<br>开始插入二级分类ERP多的数据------------';
       	   #重新排序数组
       	   sort($secondClassERP);
       	   #查询最大ID--该类别$dxMap_id（一级品类ID）的二级分类的最大ID
       	   $secondClassMaxId = DB::name(PRODUCT_CLASS)
						       	   ->where(['pid'=>$dxMap_id,'status'=>1])
						       	   ->max('id');
       	   #最大sort
       	   $secondClassMaxSortId = DB::name(PRODUCT_CLASS)
							       	   ->where(['pid'=>$dxMap_id,'status'=>1])
							       	   ->max('sort');
			$secondClassMaxId +=1;
			$secondClassMaxSortId +=1;
       	   #开始插入ERP-$secondClassERP数组中剩余的数据
    	   for($u =0; $u <count($secondClassERP); $u++){
    		   #生成2位数，不足前面补0
    		   $secondClassFormatID=sprintf("%'02s", $secondClassMaxId+$u+1);
    		   echo '<br>$secondClassFormatID:'.$secondClassFormatID;
    		   $count = DB::name(PRODUCT_CLASS)
						->where(['pid'=>$dxMap_id.$secondClassFormatID
								 ,'status'=>1])
						->count();
			   if($count>0){
			   	  echo '<br>二级类别$dxMap_id.$secondClassFormatID:'.$dxMap_id.$secondClassFormatID.'重复';
			   	  continue;
			   }
    		   #插入二级分类数据
    		   $data = array(
    						'id'=>$dxMap_id.$secondClassFormatID,
    						'title_en'=>$secondClassERP[$u]['title_en'],
    						'title_cn'=>$secondClassERP[$u]['title_cn'],
    						'pid' => $dxMap_id,
    						'status' =>1,
    						'sort' =>$secondClassMaxSortId + $u,
    						'addtime' =>time(),
    						'erp_id' =>$secondClassERP[$u]['id'],
    						'add_author'=>Session::get("username")
    					  );
	    		$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
	    		echo '<br>新增二级分类结果:';
				echo $result ? '成功':'失败';
	    		echo '<br>id:'.$dxMap_id.$secondClassFormatID.'<br>';
	    		if($result){
	    			#开始插入三级品类数据
	    			#先获取ERP里该二级分类下的三级分类 //$secondClassERP[$u]['id']
	    			echo '<br> 开始获取ERP里$secondClassERP[$u]["id"]二级分类下的三级分类,id:'.$secondClassERP[$u]['id'];
	    			$request = file_get_contents('http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$secondClassERP[$u]['id']);
	    			$thirdClassErpArr = json_decode($request,true);
	    			echo '<br>获取到数据$thirdClassErpArr:'.count($thirdClassErpArr).'条';
	    			if(count($thirdClassErpArr) >0 ){
	    				for ($z=0;$z<count($thirdClassErpArr);$z++){
	    					#准备插入数据
	    					#生成2位数，不足前面补0
	    					$thirdClassFormatID=sprintf("%'02s", $z+1);
	    					echo '<br>$thirdClassFormatID:'.$thirdClassFormatID;
	    					$count = DB::name(PRODUCT_CLASS)
					    					->where(['pid'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID
					    							,'status'=>1])
					    					->count();
	    					if($count>0){
	    						echo '<br>三级类别$dxMap_id.$secondClassFormatID.$thirdClassFormatID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.'重复';
	    						continue;
	    					}
	    					#插入三级分类数据
	    					$data = array(
	    								'id'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID, #1990501
	    								'title_en'=>$thirdClassErpArr[$z]['title_en'],
	    								'title_cn'=>$thirdClassErpArr[$z]['title_cn'],
	    								'pid' => $dxMap_id.$secondClassFormatID, #二级类别ID
	    								'status' =>1,
	    								'sort' =>$z,
	    								'addtime' =>time(),
	    								'erp_id' =>$thirdClassErpArr[$z]['id'],
	    								'add_author'=>Session::get("username")
								    );
	    				    $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
	    				    echo '<br>新增三级分类结果:';
	    					echo $result ? '成功':'失败';
	    					echo '<br>ID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.'<br>';
	    					if($result){
	    						#开始插入四级品类数据
	    						#先获取ERP里该三级分类下的四级分类 $thirdClassErpArr[$z]['id']
	    						echo '<br> 先获取ERP里该三级分类下的四级分类 $thirdClassErpArr[$z]["id"],id:'.$thirdClassErpArr[$z]['id'];
		    					$request = file_get_contents('http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$thirdClassErpArr[$z]['id']);
		    					$fourClassErpArr = json_decode($request,true);
		    					echo '<br>获取到数据$fourClassErpArr:'.count($fourClassErpArr).'条';
		    					if(count($fourClassErpArr)>0){
		    						for ($a=0;$a<count($fourClassErpArr);$a++){
		    							#准备插入数据
		    							#生成2位数，不足前面补0
		    						    $fourClassFormatID=sprintf("%'02s", $a+1);
		    						    echo '$fourClassFormatID:'.$fourClassFormatID;
		    						    $count = DB::name(PRODUCT_CLASS)
		    						    			->where(['pid'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID
		    						    					,'status'=>1])
		    						    			->count();
		    						    if($count>0){
		    						    	echo '<br>四级类别$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID.'重复';
		    						    	continue;
		    						    }

		    						    #插入三级分类数据
		    							$data = array(
		    										'id'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID, #199050101
		    										'title_en'=>$fourClassErpArr[$a]['title_en'],
		    										'title_cn'=>$fourClassErpArr[$a]['title_cn'],
		    										'pid' => $dxMap_id.$secondClassFormatID.$thirdClassFormatID, #三级类别ID
		    										'status' =>1,
		    										'sort' =>$a,
		    										'addtime' =>time(),
		    										'erp_id' =>$fourClassErpArr[$a]['id'],
		    										'add_author'=>Session::get("username")
		    								      );
		    							$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
		    							echo '<br>新增四级分类结果:';
		    							echo $result ? '成功':'失败';
		    							echo '<br>ID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID.'<br>';
		    						}
	    						}
    				     	}
    			         }
    		          }
    	             }//if($result){  新增二级分类结果
                    }//for($u =0; $u <count($secondClassERP); $u++){
                   }//ERP类别有剩余的二级分类数据，则增加到DX的类别里  if(count($secondClassERP)>0){ 600行
    	        }
    	      }
            }
           }#DX后台与ERP数据关联到了，则进行相关更新    if($dxMap_id == 0)
    	 }#循环一级分类
       }else{
    	  echo '基础数据未配置或者读取错误.<br>';
       }
    	//结束 if(!empty($firstClass) && !empty($firstClassMap)){
        echo '<br>-------------数据同步完成.<br>';
    }


    /**
     * 同步类别数据--调用ERP接口获取类别数据,把对比后ERP多的类别数据插入到DX
     * add by heng.zhang 2018-07-05
     */
    public function getERPDataAndInsert(){
        #调用ERP接口获取数据后插入ERP类别表
        #该方法只更新数据到后台
        #读取一级类别Map
        $firstClassMap = DB::name(PRODUCT_CLASS_MAP)
                        ->order('id asc')
                        ->field('mall_id,erp_id,dx_new_id')
                        ->select();
        #基础数据都有的情况下去调用ERP接口获取数据
        if(count($firstClassMap)>0){
            #循环一级分类
            foreach($firstClassMap as $key){
                //echo 'ddd';
                #根据DX类别的ID取得ERP类别的ID数据
                $erpMap_id=$key['erp_id'];
                $dxMap_id =$key['mall_id'];
                $firstClassMaxId = $key['dx_new_id'];
                echo '<br>$firstClassMaxId：'.$firstClassMaxId;
                echo '<br>关系表里取得的dxMap_id:'.$dxMap_id;
                //die();
                #一级品类ID=0，代表是DX后台缺少的一级分类，则需要拉取ERP系统的数据合并
                #此逻辑分支是代表DX没有的数据，需要新增
                if($dxMap_id == 0){// 245 行结束这个逻辑
                    #如果关系表数据erp_id 也是空，则本次遍历结束
                    if($erpMap_id <=0){
                        echo '<br>erpMap_id:'.$erpMap_id.'错误';
                        continue;
                    }
                    #产品类别在后台不存在则插入
                    #获取ERP数据,调用ERP接口获取当前类别的一级分类数据
                    echo '<br>获取ERP一级分类:'.$erpMap_id.'的子类';
                    $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=CategoryData&id='.$erpMap_id;
                    $request = file_get_contents($url);
                    $erpSingleData = json_decode($request,true);
                    $erpSingleData = $erpSingleData['message'][0];//此处没判断，比较乐观
                    echo '<br>获取到数据$erpSingleData:'.count($erpSingleData).'字段';

                    #判断ID是否重复
                    $check = DB::name(PRODUCT_CLASS)
                        ->where(['id'=>$firstClassMaxId])
                        ->count();
                    #不重复则插入
                    if($check>0){
                        echo '<br>一级类别重复：'.$firstClassMaxId.'__LINE__:'.__LINE__;
                        continue;
                    }
                    $data = array(
                        'id'=>$firstClassMaxId, //新增的一级分类ID
                        'title_en'=>str_replace("\'","'",$erpSingleData['title_en']),//$erpSingleData['title_en'],
                        'title_cn'=>$erpSingleData['title_cn'],
                        'pid' => 0,
                        'status' =>1,
                        'sort' =>$maxSortId+1,
                        'addtime' =>time(),
                        'erp_id' =>$erpSingleData['id'],
                        'add_author'=>Session::get("username")
                    );
                    $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                    echo '<br>新增后台数据库一级分类结果:';
                    echo $result ? '成功':'失败';
                    echo '<br>一级类别ID:'.$firstClassMaxId;
                    //dump($result);
                    //die();
                    #一级类别插入成功后，开始处理二级分类
                    if($result){
                        #插入二级分类
                        #获取该一级分类$erpMap_id的全部二级分类数据
                        $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$erpMap_id;
                        $request = file_get_contents($url);
                        $secondClassErpArr = json_decode($request,true);
                        //dump($secondClassErpArr);
                        //die();
                        echo '<br>获取到ERP大类：'.$erpMap_id.'的二级分类：'.count($secondClassErpArr).'条数据，__LINE__:'.__LINE__;
                        if(count($secondClassErpArr)>0){
                            for($i =0;$i<count($secondClassErpArr);$i++){
                                //生成2位数，不足前面补0
                                $secondClassFormatID=sprintf("%'02s",$i+1);
                                echo '<br> $secondClassFormatID:'.$secondClassFormatID;
                                #判断ID是否重复
                                $check = DB::name(PRODUCT_CLASS)
                                    ->where(['id'=>$firstClassMaxId.$secondClassFormatID])
                                    ->count();
                                if($check>0){
                                    echo '<br>二级类别重复：'.$firstClassMaxId.$secondClassFormatID.'__LINE__:'.__LINE__;
                                    continue;
                                }
                                $data = array(
                                    'id'=>$firstClassMaxId.$secondClassFormatID,
                                    'title_en'=>str_replace("\'","'",$secondClassErpArr[$i]['title_en']),//$secondClassErpArr[$i]['title_en'],
                                    'title_cn'=>$secondClassErpArr[$i]['title_cn'],
                                    'pid' => $firstClassMaxId,
                                    'status' =>1,
                                    'sort' =>$i,
                                    'addtime' =>time(),
                                    'erp_id' =>$secondClassErpArr[$i]['id'],
                                    'add_author'=>Session::get("username")
                                );
                                $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                                echo '<br>新增二级分类结果:';
                                echo $result ? '成功':'失败';
                                echo '<br>二级类别ID:'.$firstClassMaxId.$secondClassFormatID.',__LINE__:'.__LINE__;
                                #二级分类插入成功后，开始插入三级分类
                                if($result){
                                    #插入三级分类
                                    #获取该二级分类的全部三级分类数据  $secondClassErpArr[$i]['id']
                                    $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$secondClassErpArr[$i]['id'];
                                    $request = file_get_contents($url);
                                    $thirdClassErpArr = json_decode($request,true);
                                    echo '<br> 获取到ERP二级分类:'.$secondClassErpArr[$i]['id'].'的三级分类数据:'.count($thirdClassErpArr).'条';
                                    if(count($thirdClassErpArr)>0){
                                        for($j =0;$j<count($thirdClassErpArr);$j++){
                                            //生成2位数，不足前面补0
                                            $thirdClassFormatID=sprintf("%'02s", $j+1);
                                            echo '<br>$thirdClassFormatID:'.$thirdClassFormatID;
                                            $third_class_id=$firstClassMaxId.$secondClassFormatID.$thirdClassFormatID;
                                            #判断ID是否重复
                                            $check = DB::name(PRODUCT_CLASS)
                                                ->where(['id'=>$third_class_id])
                                                ->count();
                                            if($check>0){
                                                echo '<br>三级类别重复：'.$third_class_id.'__LINE__:'.__LINE__;
                                                continue;
                                            }
                                            $data = array(
                                                'id'=>$third_class_id,
                                                'title_en'=>str_replace("\'","'",$thirdClassErpArr[$j]['title_en']),
                                                'title_cn'=>$thirdClassErpArr[$j]['title_cn'],
                                                'pid' => $firstClassMaxId.$secondClassFormatID,//二级分类的ID
                                                'status' =>1,
                                                'sort' =>$j,
                                                'addtime' =>time(),
                                                'erp_id' =>$thirdClassErpArr[$j]['id'],
                                                'add_author'=>Session::get("username")
                                            );
                                            $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                                            echo '<br>新增三级分类结果:';
                                            echo $result ? '成功':'失败';
                                            echo '<br>三级类别ID:'.$third_class_id.',__LINE__:'.__LINE__;
                                            #三级分类插入成功后，开始插入四级分类
                                            if($result){
                                                #插入四级分类
                                                #获取该三级分类的全部四级分类数据  $thirdClassErpArr[$j]['id']
                                                $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='. $third_class_id;
                                                $request = file_get_contents($url);
                                                $fourClassErpArr = json_decode($request,true);
                                                echo '<br> 获取到ERP三级分类:'.$third_class_id.'的四级分类数据:'.count($fourClassErpArr).'条';
                                                if(count($fourClassErpArr)>0){
                                                    for($z =0;$z<count($fourClassErpArr);$z++){
                                                        //生成2位数，不足前面补0
                                                        $fourClassFormatID=sprintf("%'02s", $z+1);
                                                        $four_class_id =$firstClassMaxId.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID;
                                                        echo '<br>$fourClassFormatID:'.$fourClassFormatID.',__LINE__:'.__LINE__;
                                                        $data = array(
                                                            'id'=>$four_class_id,
                                                            'title_en'=>str_replace("\'","'",$fourClassErpArr[$z]['title_en']),//$fourClassErpArr[$z]['title_en'],
                                                            'title_cn'=>$fourClassErpArr[$z]['title_cn'],
                                                            'pid' => $third_class_id,//三级分类的ID
                                                            'status' =>1,
                                                            'sort' =>$z,
                                                            'addtime' =>time(),
                                                            'erp_id' =>$fourClassErpArr[$z]['id'],
                                                            'add_author'=>Session::get("username")
                                                        );
                                                        $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                                                        echo '<br>新增四级分类结果:';
                                                        echo $result ? '成功':'失败';
                                                        echo '<br>四级类别ID:'.$four_class_id.',__LINE__:'.__LINE__;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }else{
                            echo '<br>获取'.$firstClassMaxId.'一级类别的下的二级分类数据为空.<br>'.',__LINE__:'.__LINE__;
                        }
                    }
                }else{//end if($dxMap_id == 0)
                   #重写插入逻辑--当配置的ERP一级类别ID不等于0，意思是我们需要对比双方数据进行插入ERP标题不一样的数据
                    #DX获取二级分类
                    $secondClassAdmin = DB::name(PRODUCT_CLASS)
                                            ->where(['pid'=>$dxMap_id,'status'=>1])
                                            ->order('id asc')
                                            ->field('id,title_en')
                                            ->select();
                    #获取二级分类-ERP,$erpMap_id
                    echo '<br>开始查找ERP Map_id的子类，id:'.$erpMap_id;
                    $url = 'http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$erpMap_id;
                    $request = file_get_contents($url);
                    $secondClassERP = json_decode($request,true);
                    #双方都有二级分类，只要处理这个逻辑就好，任何一方没有数据，均无需处理
                    if(count($secondClassAdmin)>0 && count($secondClassERP)>0){
                        echo '<br>获取到dxMap_id:'.$dxMap_id.'的子类'.count($secondClassAdmin).'条数据'.'__LINE__:'.__LINE__;
                        echo '<br>获取到erpMap_id:'.$erpMap_id.'的子类'.count($secondClassERP).'条数据';

                        #遍历更新
                        #按$secondClassAdmin数组的索引逐个取ID数据，中文名称和英文名称数据从相同索引的$secondClassERP数组取
                        $forCout = 0; //循环次数,使用两个数组小的一个数组的数量
                        if(count($secondClassAdmin) >= count($secondClassERP)){
                            $forCout = count($secondClassERP);//只用ERP的数据更新过来即可，DX多余的分类不管
                        }else{
                            $forCout = count($secondClassAdmin);//DX的分类少，那么先更新自己有的，然后再用ERP多出的数据插入我们系统
                        }
                        echo '<br>forCout:'.$forCout;

                        #计算出需要插入的数据的结果集
                        #--逻辑是对比双方数组个数相同的下标的数据的标题是否相等，如果相等则插入数组;
                        #--ERP数组多余的数据也需要插入到待入库的数据集合;
                        $waitInsertToDXSecondData = [];
                        for($k=0;$k < $forCout; $k++){
                            if(trim($secondClassAdmin[$k]['title_en']) != trim($secondClassERP[$k]['title_en'])){
                                $waitInsertToDXSecondData[] = array('id'=>$secondClassERP[$k]['id'],
                                                                    'title_en'=>trim($secondClassERP[$k]['title_en']),
                                                                    'title_cn'=>trim($secondClassERP[$k]['title_cn'])
                                                                    );
                                #移除已经匹配到的数据，剩余数据需要插入Admin
                                unset($secondClassERP[$k]);
                            }
                        }
                        if(count($secondClassERP) >0){
                            #插入$secondClassERP数组里剩余的数据
                            for ($k=0;$k < count($secondClassERP); $k++) {
                                $waitInsertToDXSecondData[] = array('id'=>$secondClassERP[$k]['id'],
                                                                    'title_en'=>trim($secondClassERP[$k]['title_en']),
                                                                    'title_cn'=>trim($secondClassERP[$k]['title_cn'])
                                                                    );
                            }
                        }
                        if(count($waitInsertToDXSecondData)>0){
                            $secondClassERP = $waitInsertToDXSecondData;
                            //dump($secondClassERP);
//die();
                            #将二级数据插入Admin后台
                            echo '<br>开始插入二级分类ERP多的数据------------';
                            #查询最大ID--该类别$dxMap_id（一级品类ID）的二级分类的最大ID
                            $secondClassMaxId = DB::name(PRODUCT_CLASS)
                                                    ->where(['pid'=>$dxMap_id,'status'=>1])
                                                    ->max('id');
                            #最大sort
                            $secondClassMaxSortId = DB::name(PRODUCT_CLASS)
                                                        ->where(['pid'=>$dxMap_id,'status'=>1])
                                                        ->max('sort');
                            $secondClassMaxId +=1;
                            $secondClassMaxSortId +=1;
                            #开始插入ERP-$secondClassERP数组中剩余的数据
                            for($u =0; $u <count($secondClassERP); $u++){
                                #生成2位数，不足前面补0
                                $secondClassFormatID=sprintf("%'02s", $secondClassMaxId+$u+1);
                                echo '<br>$secondClassFormatID:'.$secondClassFormatID;
                                $count = DB::name(PRODUCT_CLASS)
                                                ->where(['pid'=>$dxMap_id.$secondClassFormatID
                                                        ,'status'=>1])
                                                ->count();
                                echo '<br>第'.$u.'次';
                                if($count>0 ){
                                    echo '<br>二级类别$dxMap_id.$secondClassFormatID:'.$dxMap_id.$secondClassFormatID.'重复';
                                    continue;
                                }
                                if(trim($secondClassERP[$u]['id'] =='') ){
                                    echo '<br>$secondClassERP[$u][\'id\']:为空';
                                    continue;
                                }

                                //dump($secondClassERP[$u]);
                                //die();
                                #插入二级分类数据
                                $data = array(
                                    'id'=>$dxMap_id.$secondClassFormatID,
                                    'title_en'=>$secondClassERP[$u]['title_en'],
                                    'title_cn'=>$secondClassERP[$u]['title_cn'],
                                    'pid' => $dxMap_id,
                                    'status' =>1,
                                    'sort' =>$secondClassMaxSortId + $u,
                                    'addtime' =>time(),
                                    'erp_id' =>$secondClassERP[$u]['id'],
                                    'add_author'=>Session::get("username")
                                );
                                $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                                echo '<br>新增二级分类结果:';
                                echo $result ? '成功':'失败';
                                echo '<br>id:'.$dxMap_id.$secondClassFormatID.'<br>';
                                if($result){
                                    #开始插入三级品类数据
                                    #先获取ERP里该二级分类下的三级分类 //$secondClassERP[$u]['id']
                                    echo '<br> 开始获取ERP里$secondClassERP[$u]["id"]二级分类下的三级分类,id:'.$secondClassERP[$u]['id'];
                                    $request = file_get_contents('http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$secondClassERP[$u]['id']);
                                    $thirdClassErpArr = json_decode($request,true);
                                    echo '<br>获取到数据$thirdClassErpArr:'.count($thirdClassErpArr).'条';
                                    if(count($thirdClassErpArr) >0 ){
                                        for ($z=0;$z<count($thirdClassErpArr);$z++){
                                            #准备插入数据
                                            #生成2位数，不足前面补0
                                            $thirdClassFormatID=sprintf("%'02s", $z+1);
                                            echo '<br>$thirdClassFormatID:'.$thirdClassFormatID;
                                            $count = DB::name(PRODUCT_CLASS)
                                                            ->where(['pid'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID
                                                                     ,'status'=>1])
                                                            ->count();
                                            if($count>0){
                                                echo '<br>三级类别$dxMap_id.$secondClassFormatID.$thirdClassFormatID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.'重复';
                                                continue;
                                            }
                                            #插入三级分类数据
                                            $data = array(
                                                'id'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID, #1990501
                                                'title_en'=>$thirdClassErpArr[$z]['title_en'],
                                                'title_cn'=>$thirdClassErpArr[$z]['title_cn'],
                                                'pid' => $dxMap_id.$secondClassFormatID, #二级类别ID
                                                'status' =>1,
                                                'sort' =>$z,
                                                'addtime' =>time(),
                                                'erp_id' =>$thirdClassErpArr[$z]['id'],
                                                'add_author'=>Session::get("username")
                                            );
                                            $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                                            echo '<br>新增三级分类结果:';
                                            echo $result ? '成功':'失败';
                                            echo '<br>ID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.'<br>';
                                            if($result){
                                                #开始插入四级品类数据
                                                #先获取ERP里该三级分类下的四级分类 $thirdClassErpArr[$z]['id']
                                                echo '<br> 先获取ERP里该三级分类下的四级分类 $thirdClassErpArr[$z]["id"],id:'.$thirdClassErpArr[$z]['id'];
                                                $request = file_get_contents('http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=SubClassData&id='.$thirdClassErpArr[$z]['id']);
                                                $fourClassErpArr = json_decode($request,true);
                                                echo '<br>获取到数据$fourClassErpArr:'.count($fourClassErpArr).'条';
                                                if(count($fourClassErpArr)>0){
                                                    for ($a=0;$a<count($fourClassErpArr);$a++){
                                                        #准备插入数据
                                                        #生成2位数，不足前面补0
                                                        $fourClassFormatID=sprintf("%'02s", $a+1);
                                                        echo '$fourClassFormatID:'.$fourClassFormatID;
                                                        $count = DB::name(PRODUCT_CLASS)
                                                            ->where(['pid'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID
                                                                ,'status'=>1])
                                                            ->count();
                                                        if($count>0){
                                                            echo '<br>四级类别$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID.'重复';
                                                            continue;
                                                        }

                                                        #插入三级分类数据
                                                        $data = array(
                                                            'id'=>$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID, #199050101
                                                            'title_en'=>$fourClassErpArr[$a]['title_en'],
                                                            'title_cn'=>$fourClassErpArr[$a]['title_cn'],
                                                            'pid' => $dxMap_id.$secondClassFormatID.$thirdClassFormatID, #三级类别ID
                                                            'status' =>1,
                                                            'sort' =>$a,
                                                            'addtime' =>time(),
                                                            'erp_id' =>$fourClassErpArr[$a]['id'],
                                                            'add_author'=>Session::get("username")
                                                        );
                                                        $result=DB::name(PRODUCT_CLASS)->data($data)->insert();
                                                        echo '<br>新增四级分类结果:';
                                                        echo $result ? '成功':'失败';
                                                        echo '<br>ID:'.$dxMap_id.$secondClassFormatID.$thirdClassFormatID.$fourClassFormatID.'<br>';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }//if($result){  新增二级分类结果
                            }//for($u =0; $u <count($secondClassERP); $u++){

                        }

                    }

                }#DX后台与ERP数据关联到了，则进行相关更新    if($dxMap_id == 0)
            }#循环一级分类
        }else{
            echo '基础数据未配置或者读取错误.<br>';
        }
        //结束 if(!empty($firstClass) && !empty($firstClassMap)){
        echo '<br>-------------数据同步完成.<br>';
    }

//-------------------------------------------------------------------------------------------------------------------


    /**
     * 读取后台与ERP类别ID关系数据
     */
    private function read_admin_and_erp_map_data(){
    	$result=DB::name(PRODUCT_CLASS_MAP)->count();
    	if($result>0){
    		echo '请先清空数据库数据;<br>';
    	}else{
	    	$Con=file_get_contents('./uploads/adminAanERPData.txt');
	    	$ConArr=explode("\n",$Con);
	    	$i=0;
	    	foreach($ConArr as $Value){
	    		if($i ==0){
	    			$i++;
	    			continue;
	    		}
	    		$Arr=explode(',',$Value);
	    		//var_dump(trim($Arr[0]));
	    		$mall_id = trim($Arr[0]); #商城ID
	    		$erp_id = (int)trim($Arr[1]);  #ERP ID
	    		//var_dump('mall_id:'.(int)$mall_id.'<Br>');
	    		//var_dump($erp_id.'<Br>');
	    		//die();
	    		if (!is_null($mall_id) && !is_null($erp_id)){
	    			$data = array(
	    					'mall_id'=>$mall_id,
	    					'erp_id'=>$erp_id,
	    					'addtime' =>time()
	    			);
	    			//初始化数据，只管插入
	    			$result=DB::name(PRODUCT_CLASS_MAP)->data($data)->insert();
	    			if ($result) {
	    				echo $mall_id.'--OK<br>';
	    			}
	    		}else{
	    			echo '$mall_id or $erp_id is:'.$mall_id.','.$erp_id.'<br>';
	    		}
	    	}
    	}
    }

    /**
     * 读取文件并更新数据库
     */
    private function readAndWriteData(){
    	$result=DB::name(PRODUCT_CLASS)->count();
    	if($result>0){
    		echo '请先清空数据库数据;<br>';
    	}else{
	    	$Con=file_get_contents('./uploads/classData.txt');
	    	$ConArr=explode("\n",$Con);

//dump($ConArr);
//exit();
            $i =0;
            $t  = time();
	    	foreach($ConArr as $Value){
	    		$Arr=explode('&&&&',$Value);
	    		$id = (int)trim($Arr[0]);//分类ID
	    		$pid = (int)trim($Arr[3]);//父类ID
                $level = (int)trim($Arr[4]);//Level
	    		$maxSort =0;
	    		/*
	    		//DB::name(REGION)->
	    		$getSort = DB::name(PRODUCT_CLASS)
	    							->where(['pid'=>$pid,'id'=>$id])
	    							->field('sort')
	    							->find();
	    		if($getSort && isset($getSort['sort'])){
	    			$maxSort =$getSort['sort'] +1;
	    		}
	    		*/
	    		// dump($Arr);exit;
	    		if (!empty($Arr[0]) && !empty($Arr[1])){
	    			$data = array(
	    					'id'=> $id,
	    					'title_en'=> trim($Arr[1]),
	    					'title_cn'=> trim($Arr[2]),
	    					'pid' => $pid,
	    					'status' => 1,
	    					'sort' => $maxSort,
	    					'level'=> $level,
	    					'isleaf' => 2, //非发布目录
	    					'type' => 2,
	    					'pdc_id' => $id,
	    					'add_time' => $t,
	    					'add_author'=>'system'
	    			);
	    			//初始化数据，只管插入
	    			$result=DB::name(PRODUCT_CLASS)->data($data)->insert();
	    			if ($result) {
	    				echo $Arr[0].'--OK，'.$i++.'次<br>';
	    			}
	    		}
	    	}
    	}
    }

    /**
     * 处理标题，大写转小写，加横杆
     */
    private static function filterTitle($strParam){
        //过滤特殊字符
        $regex = "/\/|\~|\!|\@|\&|\#|\\$|\%|\^|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\’|\`|\-|\=|\\\|\|/";
        $string = preg_replace($regex,"",strtolower($strParam));
        $string = preg_replace ( "/\s(?=\s)/","\\1", $string );//多个连续空格
        //空格替换横线
        $search = array(" ","　");
        $replace = array("-","-");
        return str_replace($search, $replace, $string);
    }

    /**
     * 同步类别数据--将类别数据更新到Mongo
     */
    public function updateToMongo(){
    	$count =  Db::connect("db_mongo")->name(PRODUCT_CLASS_MG)->count('_id');
    	//dump($count);
    	//die();
    	if($count>0){
    		echo '数据初始化失败，请先清空数据库数据，此功能需要类别数据全部为空;<br>';
    	}else{
	    	#获取后台数据
	    	#获取、更新一级分类数据
	    	$firstClassData = DB::name(PRODUCT_CLASS)
							    	->where(['pid'=>0,'status'=>1])
							    	->field('*')
							    	->select();
	    	$count1 = count($firstClassData);
			if($count1>0){
				for($i=0;$i < $count1; $i++){
                    $id_path = (int)$firstClassData[$i]['pid'] ==0 ? $firstClassData[$i]['id'] :
                                                                     $firstClassData[$i]['pid'].'-'.(int)$firstClassData[$i]['id'];
                    $pdc_ids = [];
                    $pdc_ids_str = trim($firstClassData[$i]['pdc_ids']);
                    if(!empty($pdc_ids_str)){
                        $pdc_ids = explode(",",$pdc_ids_str);
                    }
                    $data = array(
                                'id'=>(int)$firstClassData[$i]['id'],
                                'pid'=>(int)$firstClassData[$i]['pid'],
                                'title_en'=>trim($firstClassData[$i]['title_en']),
                                'title_cn'=>trim($firstClassData[$i]['title_cn']),
                                'status'=>(int)$firstClassData[$i]['status'],
                                'sort' =>(int)trim($firstClassData[$i]['sort']),
                                'level' =>(int)trim($firstClassData[$i]['level']),
                                'isleaf' =>(int)trim($firstClassData[$i]['isleaf']),
                                'declare_en' =>trim($firstClassData[$i]['declare_en']),
                                'declare_cn' =>trim($firstClassData[$i]['declare_cn']),
                                'HSCode'=>trim($firstClassData[$i]['HSCode']),
                                'type' => (int)$firstClassData[$i]['type'],
                                'erp_id' => (int)$firstClassData[$i]['erp_id'],
                                'pdc_id' => (int)$firstClassData[$i]['pdc_id'],
                                'rewritten_url'=> $this -> filterTitle(trim($firstClassData[$i]['title_en'])),
                                'id_path' =>$id_path,
                                'pdc_ids' => $pdc_ids,
                                'add_time' =>(int)$firstClassData[$i]['add_time'],
                                'add_author'=>Session::get("username")
					         );
					$result = Db::connect("db_mongo")->name(PRODUCT_CLASS_MG)->insert($data);
                    pr($result==1 ? '更新一级分类数据'.$firstClassData[$i]['id'].'成功' : $firstClassData[$i]['id']."失败");
					if(!$result)
						exit();
                    //插入变更历史--产品类别变更历史
                    $data_histroy['EntityId'] =$data['id'];
                    $data_histroy['CreatedDateTime'] = time();
                    $data_histroy['IsSync'] = false;
                    $data_histroy['Note'] = Session::get('userName').'新增类别';
                    $result_History = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($data_histroy);
					#获取、更新二级分类数据
                    $secondClassData = DB::name(PRODUCT_CLASS)
											->where(['pid'=>(int)$firstClassData[$i]['id'],'status'=>1])
											->field('*')
											->select();
                    $count2 = count($secondClassData);
					if($count2 >0){
						for($j=0;$j < $count2; $j++){
                            $id_path = $secondClassData[$j]['pid'].'-'.(int)$secondClassData[$j]['id'];
                            $pdc_ids = [];
                            $pdc_ids_str = trim($secondClassData[$j]['pdc_ids']);
                            if(!empty($pdc_ids_str)){
                                $pdc_ids = explode(",",$pdc_ids_str);
                            }
                            $data = array(
                                    'id'=>(int)$secondClassData[$j]['id'],
                                    'pid'=>(int)$secondClassData[$j]['pid'],
                                    'title_en'=>trim($secondClassData[$j]['title_en']),
                                    'title_cn'=>trim($secondClassData[$j]['title_cn']),
                                    'status'=>(int)$secondClassData[$j]['status'],
                                    'sort' =>(int)trim($secondClassData[$j]['sort']),
                                    'level' =>(int)trim($secondClassData[$j]['level']),
                                    'isleaf' =>(int)trim($secondClassData[$j]['isleaf']),
                                    'declare_en' =>trim($secondClassData[$j]['declare_en']),
                                    'declare_cn' =>trim($secondClassData[$j]['declare_cn']),
                                    'HSCode'=>trim($secondClassData[$j]['HSCode']),
                                    'type' => (int)$secondClassData[$j]['type'],
                                    'erp_id' => (int)$secondClassData[$j]['erp_id'],
                                    'pdc_id' => (int)$secondClassData[$j]['pdc_id'],
                                    'rewritten_url'=> $this -> filterTitle(trim($secondClassData[$j]['title_en'])),
                                    'id_path' =>$id_path,
                                    'pdc_ids' => $pdc_ids,
                                    'add_time' =>(int)$secondClassData[$j]['add_time'],
                                    'add_author'=>Session::get("username")
                            );
							$result = Db::connect("db_mongo")->name(PRODUCT_CLASS_MG)->insert($data);
                            pr($result==1 ? '更新二级分类数据'.$secondClassData[$j]['id'].'成功' : $secondClassData[$j]['id']."失败");
							if(!$result)
								exit();
                            //插入变更历史--产品类别变更历史
                            $data_histroy['EntityId'] =$data['id'];
                            $data_histroy['CreatedDateTime'] = time();
                            $data_histroy['IsSync'] = false;
                            $data_histroy['Note'] = Session::get('userName').'新增类别';
                            $result_History = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($data_histroy);
							#获取、更新三级分类数据
							$thirdClassData = DB::name(PRODUCT_CLASS)
													->where(['pid'=>(int)$secondClassData[$j]['id'],'status'=>1])
													->field('*')
													->select();
							$count3 = count($thirdClassData);
							if($count3 > 0){
								for($k=0;$k < $count3; $k++){
                                    $id_path = $firstClassData[$i]['id'].'-'.$thirdClassData[$k]['pid'].'-'.(int)$thirdClassData[$k]['id'];
                                    $pdc_ids = [];
                                    $pdc_ids_str = trim($thirdClassData[$k]['pdc_ids']);
                                    if(!empty($pdc_ids_str)){
                                        $pdc_ids = explode(",",$pdc_ids_str);
                                    }
                                    $data = array(
                                            'id'=>(int)$thirdClassData[$k]['id'],
                                            'pid'=>(int)$thirdClassData[$k]['pid'],
                                            'title_en'=>trim($thirdClassData[$k]['title_en']),
                                            'title_cn'=>trim($thirdClassData[$k]['title_cn']),
                                            'status'=>(int)$thirdClassData[$k]['status'],
                                            'sort' =>(int)trim($thirdClassData[$k]['sort']),
                                            'level' =>(int)trim($thirdClassData[$k]['level']),
                                            'isleaf' =>(int)trim($thirdClassData[$k]['isleaf']),
                                            'declare_en' =>trim($thirdClassData[$k]['declare_en']),
                                            'declare_cn' =>trim($thirdClassData[$k]['declare_cn']),
                                            'HSCode'=>trim($thirdClassData[$k]['HSCode']),
                                            'type' => (int)$thirdClassData[$k]['type'],
                                            'erp_id' => (int)$thirdClassData[$k]['erp_id'],
                                            'pdc_id' => (int)$thirdClassData[$k]['pdc_id'],
                                            'rewritten_url'=> $this -> filterTitle(trim($thirdClassData[$k]['title_en'])),
                                            'id_path' =>$id_path,
                                            'pdc_ids' => $pdc_ids,
                                            'add_time' =>(int)$thirdClassData[$k]['add_time'],
                                            'add_author'=>Session::get("username")
                                    );
									$result = Db::connect("db_mongo")->name(PRODUCT_CLASS_MG)->insert($data);
                                    pr($result==1 ? '更新三级分类数据'.$thirdClassData[$k]['id'].'成功' : $thirdClassData[$k]['id']."失败");
									if(!$result)
										exit();
                                    //插入变更历史--产品类别变更历史
                                    $data_histroy['EntityId'] =$data['id'];
                                    $data_histroy['CreatedDateTime'] = time();
                                    $data_histroy['IsSync'] = false;
                                    $data_histroy['Note'] = Session::get('userName').'新增类别';
                                    $result_History = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($data_histroy);
									#获取、更新四级分类数据
									$fourClassData = DB::name(PRODUCT_CLASS)
															->where(['pid'=>(int)$thirdClassData[$k]['id'],'status'=>1])
															->field('*')
															->select();
									$count4 = count($fourClassData);
									if($count4 > 0){
										for($x=0;$x < $count4; $x++){
                                            $id_path = $firstClassData[$i]['id'].'-'.$secondClassData[$j]['id'].'-'.$fourClassData[$x]['pid'].'-'.(int)$fourClassData[$x]['id'];
                                            $pdc_ids = [];
                                            $pdc_ids_str = trim($fourClassData[$x]['pdc_ids']);
                                            if(!empty($pdc_ids_str)){
                                                $pdc_ids = explode(",",$pdc_ids_str);
                                            }
                                            $data = array(
                                                'id'=>(int)$fourClassData[$x]['id'],
                                                'pid'=>(int)$fourClassData[$x]['pid'],
                                                'title_en'=>trim($fourClassData[$x]['title_en']),
                                                'title_cn'=>trim($fourClassData[$x]['title_cn']),
                                                'status'=>(int)$fourClassData[$x]['status'],
                                                'sort' =>(int)trim($fourClassData[$x]['sort']),
                                                'level' =>(int)trim($fourClassData[$x]['level']),
                                                'isleaf' =>(int)trim($fourClassData[$x]['isleaf']),
                                                'declare_en' =>trim($fourClassData[$x]['declare_en']),
                                                'declare_cn' =>trim($fourClassData[$x]['declare_cn']),
                                                'HSCode'=>trim($fourClassData[$x]['HSCode']),
                                                'type' => (int)$fourClassData[$x]['type'],
                                                'erp_id' => (int)$fourClassData[$x]['erp_id'],
                                                'pdc_id' => (int)$fourClassData[$x]['pdc_id'],
                                                'rewritten_url'=> $this -> filterTitle(trim($fourClassData[$x]['title_en'])),
                                                'id_path' =>$id_path,
                                                'pdc_ids' => $pdc_ids,
                                                'add_time' =>(int)$fourClassData[$x]['add_time'],
                                                'add_author'=>Session::get("username")
											);
											$result = Db::connect("db_mongo")->name(PRODUCT_CLASS_MG)->insert($data);
                                            pr($result==1 ? '更新四级分类数据'.$fourClassData[$x]['id'].'成功' : $fourClassData[$x]['id']."失败");
											if(!$result)
												exit();
                                            //插入变更历史--产品类别变更历史
                                            $data_histroy['EntityId'] =$data['id'];
                                            $data_histroy['CreatedDateTime'] = time();
                                            $data_histroy['IsSync'] = false;
                                            $data_histroy['Note'] = Session::get('userName').'新增类别';
                                            $result_History = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($data_histroy);
										}
									}
								}
							}
						}
                    }
				}
			}else{
				echo '后台数据库一级分类读取失败;<br>';
			}
    	}
    }



   // public function asyncAttr(){
   //      $id = input('class_id');
   //      if(empty($id) && $id != 0){
   //          echo '<br>类别ID不可为空';
   //          exit();
   //      }
   //      $firstClassData = Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>0,'status'=>1,'type'=>1])->select();
   //      if($firstClassData){
   //          foreach ($firstClassData as $key => $value) {
   //             $firstClassID = $firstClassData[$i]['id'];
   //             $firstClassID_ERP = $firstClassData[$i]['erp_id'];
   //          }

   //      }else if($id != 0){
   //          //无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性-$secondClassClassID_ERP
   //          $ret = $this->getERPDataByClassID($secondClassClassID_ERP,$secondClassClassID);
   //          if(!$ret){
   //              continue;
   //          }
   //      }
   //      dump($firstClassData);
   // }
   public function asyncAttr(){
       ini_set('max_execution_time', '0');
        $id = input('class_id');
        if(empty($id) && $id != 0){
            echo '<br>类别ID不可为空';
            exit();
        }
        //获取产品类别数据从一级类别开始，任何一个类别都可能有销售属性数据，所以就无脑从一级类别从头到尾遍历
        $firstClassData  = Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>(int)$id,'status'=>1,'type'=>1])->select();dump($firstClassData);
        $firstClassCount = count($firstClassData);
        if($firstClassCount>0){
            for($i=0;$i<$firstClassCount;$i++){
                $firstClassID = $firstClassData[$i]['id'];
                $firstClassID_ERP = $firstClassData[$i]['erp_id'];
                //获取该一级分类的二级类别数据
                // $secondClassData = DB::name(PRODUCT_CLASS)
                //                         ->where(['pid'=>$firstClassID,'status'=>1])
                //                         ->field('id,erp_id')
                //                         ->select();
                $secondClassData = Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>$firstClassID,'status'=>1,'type'=>1])->field('id,erp_id')->select();
                $secondClassCount = count($secondClassData);
                if($secondClassCount>0){
                    //有子类，则说明非末级分类，则继续查找末级分类
                    for($j=0;$j<$secondClassCount;$j++){
                        $secondClassClassID= $secondClassData[$j]['id'];
                        $secondClassClassID_ERP= $secondClassData[$j]['erp_id'];
                        pr("二级ID : ".$secondClassClassID);
                        //获取该二级分类的三级类别数据
                        // $thirdClassData = DB::name(PRODUCT_CLASS)
                        //                         ->where(['pid'=>$secondClassClassID,'status'=>1])
                        //                         ->field('id,erp_id')
                        //                         ->select();
                        $thirdClassData = Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>$secondClassClassID,'status'=>1,'type'=>1])->field('id,erp_id')->select();
                        $thirdClassCount = count($thirdClassData);
                        if($thirdClassCount>0){
                            //有子类，则说明非末级分类，则继续查找末级分类
                            for($k=0;$k<$thirdClassCount;$k++){
                                $thirdClassClassID= $thirdClassData[$k]['id'];
                                $thirdClassClassID_ERP= $thirdClassData[$k]['erp_id'];
                                pr("三级ID : ".$thirdClassClassID);
                                //获取该三级分类的四级类别数据
                                // $fourClassData = DB::name(PRODUCT_CLASS)
                                //                         ->where(['pid'=>$thirdClassClassID,'status'=>1])
                                //                         ->field('id,erp_id')
                                //                         ->select();
                                $fourClassData = Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>$thirdClassClassID,'status'=>1,'type'=>1])->field('id,erp_id')->select();
                                $fourClassCount = count($fourClassData);
                                if($fourClassCount>0){
                                    //有子类，则说明非末级分类，则继续查找末级分类
                                    for($m=0;$m<$fourClassCount;$m++){
                                        $fourClassClassID_ERP= $fourClassData[$m]['erp_id'];
                                        $fourClassClassID = $fourClassData[$m]['id'];
                                        if(empty($fourClassClassID_ERP) || empty($fourClassClassID)){
                                            continue;
                                        }
                                        //四级分类已是末级分类，去获取ERP里的销售及产品筛选属性-$fourClassClassID_ERP
                                        $ret = $this -> getERPDataByClassID($fourClassClassID_ERP,$fourClassClassID);
                                        if(!$ret){
                                            continue;
                                        }
                                    }
                                }else{
                                    echo '<br>执行三级数据,__LINE__:'.__LINE__.',id:'.$thirdClassClassID_ERP;
                                    //无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性-$thirdClassClassID_ERP
                                    if(!empty($thirdClassClassID_ERP)){
                                        $ret = $this -> getERPDataByClassID($thirdClassClassID_ERP,$thirdClassClassID);
                                        if(!$ret){
                                            continue;
                                        }
                                    }
                                }
                            }
                        }else{
                            //无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性-$secondClassClassID_ERP
                            $ret = $this -> getERPDataByClassID($secondClassClassID_ERP,$secondClassClassID);
                            if(!$ret){
                                continue;
                            }
                        }
                    }
                }else{//if($secondClassCount>0){ 1021行
                    //无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性
                    $ret = $this -> getERPDataByClassID($firstClassID_ERP,$firstClassID);
                    if(!$ret){
                        continue;
                    }
                }
            }
        }
   }
   /**
    * 把分类erp—_di属性转换成对应class_id的属性
    * [classTransformation description]
    * @return [type] [description]
    */
   // public function classTransformation(){
   //     $limit = input('limit');
   //     if(!$limit){
   //        echo '请输入limit';
   //        exit;
   //     }
   //     $list = Db::connect("db_mongo")->name("dx_product_class")->where(['type'=>1])->field('id,erp_id')->limit($limit,3000)->select();
   //     foreach ($list as $key => $value) {
   //        $list_erp_id = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$value['erp_id']])->find();
   //        if($list_erp_id){
   //            $list_id = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$value['id']])->find();
   //            if(!$list_id){
   //                $list_erp_id['_id'] = (int)$value['id'];
   //                $ret = Db::connect("db_mongo")->name("dx_brand_attribute")->insert($list_erp_id);
   //                if($ret){
   //                    echo $value['id'].'添加成功';
   //                }else{
   //                    echo $value['id'].'添加失败';
   //                }
   //            }
   //        }
   //     }
   // }
   /**
    * 更新类别的筛选属性
    */
   public function asyncAttr_20180823(){
      	$id = input('class_id');
      	if(empty($id)){
      		echo '<br>类别ID不可为空';
      		exit();
      	}
	   	//获取产品类别数据从一级类别开始，任何一个类别都可能有销售属性数据，所以就无脑从一级类别从头到尾遍历
	   	$firstClassData = DB::name(PRODUCT_CLASS)
						   	->where(['pid'=>0,'id'=>$id,'status'=>1])
						   	->field('id,erp_id')
						   	->select();
	   	$firstClassCount =count($firstClassData);
	   	//pr($firstClassData);die();
	   	if($firstClassCount>0){
	   		for($i=0;$i<$firstClassCount;$i++){
	   			$firstClassID = $firstClassData[$i]['id'];
	   			$firstClassID_ERP = $firstClassData[$i]['erp_id'];
	   			//获取该一级分类的二级类别数据
	   			$secondClassData = DB::name(PRODUCT_CLASS)
									   	->where(['pid'=>$firstClassID,'status'=>1])
									   	->field('id,erp_id')
									   	->select();
	   			$secondClassCount = count($secondClassData);
//                pr($secondClassData);die;
	   			if($secondClassCount>0){
	   				//有子类，则说明非末级分类，则继续查找末级分类
	   				for($j=0;$j<$secondClassCount;$j++){
	   					$secondClassClassID= $secondClassData[$j]['id'];
	   					$secondClassClassID_ERP= $secondClassData[$j]['erp_id'];
                        pr("二级ID : ".$secondClassClassID);
	   					//获取该二级分类的三级类别数据
	   					$thirdClassData = DB::name(PRODUCT_CLASS)
							   					->where(['pid'=>$secondClassClassID,'status'=>1])
							   					->field('id,erp_id')
							   					->select();
	   					$thirdClassCount = count($thirdClassData);
//                        pr($thirdClassData);
	   					if($thirdClassCount>0){
	   						//有子类，则说明非末级分类，则继续查找末级分类
	   						for($k=0;$k<$thirdClassCount;$k++){
	   							$thirdClassClassID= $thirdClassData[$k]['id'];
	   							$thirdClassClassID_ERP= $thirdClassData[$k]['erp_id'];
                                pr("三级ID : ".$thirdClassClassID);
	   							//获取该三级分类的四级类别数据
	   							$fourClassData = DB::name(PRODUCT_CLASS)
							   							->where(['pid'=>$thirdClassClassID,'status'=>1])
							   							->field('id,erp_id')
							   							->select();
	   							$fourClassCount = count($fourClassData);
	   							if($fourClassCount>0){
	   								//有子类，则说明非末级分类，则继续查找末级分类
	   								for($m=0;$m<$fourClassCount;$m++){
	   									$fourClassClassID_ERP= $fourClassData[$m]['erp_id'];
                                        $fourClassClassID = $fourClassData[$m]['id'];
                                        if(empty($fourClassClassID_ERP) || empty($fourClassClassID)){
                                            continue;
                                        }
	   									//四级分类已是末级分类，去获取ERP里的销售及产品筛选属性-$fourClassClassID_ERP
                                        $ret = $this -> getERPDataByClassID($fourClassClassID_ERP,$fourClassClassID);
                                        if(!$ret){
                                            continue;
                                        }

	   								}
	   							}else{
	   								echo '<br>执行三级数据,__LINE__:'.__LINE__.',id:'.$thirdClassClassID_ERP;
	   								//无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性-$thirdClassClassID_ERP
	   								if(!empty($thirdClassClassID_ERP)){
	   									$ret = $this -> getERPDataByClassID($thirdClassClassID_ERP,$thirdClassClassID);
                                        if(!$ret){
                                            continue;
                                        }
	   								}
	   							}
	   						}
	   					}else{
	   						//无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性-$secondClassClassID_ERP
	   						$ret = $this -> getERPDataByClassID($secondClassClassID_ERP,$secondClassClassID);
                            if(!$ret){
                                continue;
                            }
	   					}
	   				}
	   			}else{//if($secondClassCount>0){ 1021行
	   				//无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性
	   				$ret = $this -> getERPDataByClassID($firstClassID_ERP,$firstClassID);
                    if(!$ret){
                        continue;
                    }
	   			}
	   		}
	   	}
   }

   /**
    * 根据erp_id获取对应的属性数据
    * @param string $erp_calss_id erp产品类别ID
    * @param string $dx_class_id dx产品类别ID
    */
   private function getERPDataByClassID($erp_calss_id,$dx_class_id){
        // pr($erp_calss_id.'-'.$dx_class_id);
	   ini_set('max_execution_time', '0');
       	if(empty($erp_calss_id)){
	   		echo '<br>$erp_calss_id为空';
	   		return false;
	   	}
	   	//暂停 500毫秒
	   	//usleep(200);
   		//erpAPI接口的地址
   		$erpAPIUrl ='http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=CataLogData&id=';
	   	//无子类，则说明是末 级分类，去获取ERP里的销售及产品筛选属性
	   	echo '<br>获取ERP里该类-$calss_id:'.$erp_calss_id;
        $request = $this->CURL_GET($erpAPIUrl.$erp_calss_id);
	   	// $request = file_get_contents($erpAPIUrl.$erp_calss_id);
	   	$erpAtrr = json_decode($request,true);
       //  dump($erpAtrr);
       //  return;
       // pr($erpAPIUrl.$erp_calss_id);
	   	//dump($firstErpAtrr);
	   	//die();
	   	if(isset($erpAtrr['statusCode']) &&  $erpAtrr['statusCode']== 200){
            echo '<br>获取到数据$erpAtrr:'.count($erpAtrr).'条';

            $this->asyncErpDataToBrandAttribute($dx_class_id,$erp_calss_id,$erpAtrr['message']);
            return true;
//	   		if(isset($erpAtrr['message']['catalog_attr_list'])){
//	   			//取到数据更新数据库TODO
//	   			echo '<br>等待入库,count:'.count($erpAtrr['message']['catalog_attr_list']);
//
//	   			//
//
//	   			//die();
//	   		}else{
//	   			echo '<br>erp无catalog_attr_list数据';
//	   		}
	   	}else{
	   		echo '<br>ERP无该类别的属性数据';
            return false;
	   	}
   }
    public  function CURL_GET($Url=''){
         // $Url = 'http://api.test.myib.com/v4/tracking/events?packageId=DX993349';
        // $Url = 'http://api.test.myib.com/v4/tracking/events?trackingCode=9274890217040900043180';
        $header = array(
            'Content-Type: application/json',
            // 'ClientKey: 1LZnTmO0MTHgLJaNPJcOQA==',
            // 'ClientSecret: /gxHmXpwveHN7oU6vDBCNg=='
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $Url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置请求头信息
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
   }
   /**
     *同步erp属性数据到本地品牌属性表
     * @param $class_id dx类别id
     * @param $erp_class_id 同步erpid
     * @param $data
     * @return bool
     * @throws \think\Exception
     */
    public function asyncErpDataToBrandAttribute($class_id,$erp_class_id,$data){
        ini_set('max_execution_time', '0');
        if(empty($data)){
            return false;
        }
        $time = time();
        //查询是否存在
        $exist_id = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$class_id])->value('_id');
        if(!empty($exist_id)){
           $this->asyncErpDataToattribute($class_id,$erp_class_id,$data);//修改
           return;
        }
        // return;
        //数据新增
        $insertData = array();
        $insertData['_id'] = (int)$class_id;
        $insertData['title_cn'] = $data['title_cn'];
        $insertData['title_en'] = $data['title_en'];
        $insertData['status'] = 1;
        //产品属性封装
        $insertData['catalog_attr_list'] = [];
        if(!empty($data['catalog_attr_list'])){
            foreach($data['catalog_attr_list'] as $key => $val){
                $id = $val['id'];
                $insertData['catalog_attr_list'][$id]['id'] = $val['id'];
                $insertData['catalog_attr_list'][$id]['title_cn'] = $val['names_cn'];
                $insertData['catalog_attr_list'][$id]['title_en'] = $val['names_en'];
                $insertData['catalog_attr_list'][$id]['show_type'] = $val['show_type'];
                $insertData['catalog_attr_list'][$id]['input_type'] = $val['input_type'];
                $insertData['catalog_attr_list'][$id]['required'] = $val['required'];;
                $insertData['catalog_attr_list'][$id]['sort'] = $key;
                $insertData['catalog_attr_list'][$id]['status'] = 1;
                $insertData['catalog_attr_list'][$id]['addtime'] = $time;
                $insertData['catalog_attr_list'][$id]['attr_value'] = [];
                if(!empty($val['attr_value'])){
                    foreach($val['attr_value'] as $k => $v){
                        $attr_id = $v['id'];
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['id'] = $attr_id;
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['title_cn'] = $v['title_cn'];
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['title_en'] = $v['title_en'];
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['sort'] = $k;
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['edit_time'] = $time;
                    }
                }
            }
        }
        //销售属性封装
        $insertData['attribute'] = [];
        if(!empty($data['sale_attr_list'])){
            foreach($data['sale_attr_list'] as $key => $val){
                $id = $val['id'];
                $insertData['attribute'][$id]['id'] = $val['id'];
                $insertData['attribute'][$id]['title_cn'] = $val['names_cn'];
                $insertData['attribute'][$id]['title_en'] = $val['names_en'];
                $insertData['attribute'][$id]['show_type'] = $val['show_type'];
                $insertData['attribute'][$id]['input_type'] = $val['input_type'];
                $insertData['attribute'][$id]['spec'] = $val['spec'];
                $insertData['attribute'][$id]['customized_name'] = $val['customized_name'];
                $insertData['attribute'][$id]['customized_pic'] = $val['customized_pic'];
                $insertData['attribute'][$id]['is_color'] = $val['is_color'];
                $insertData['attribute'][$id]['sort'] = $key;
                $insertData['attribute'][$id]['status'] = 1;
                $insertData['attribute'][$id]['addtime'] = $time;
                $insertData['attribute'][$id]['attribute_value'] = [];
                if(!empty($val['sale_attr_value_list'])){
                    foreach($val['sale_attr_value_list'] as $k => $v){
                        $attr_id = $v['id'];
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['id'] = $attr_id;
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['title_cn'] = $v['title_cn'];
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['title_en'] = $v['title_en'];
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['sort'] = $k;
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['edit_time'] = $time;
                    }
                }
            }
        }
        $insertData['add_time'] = time();
        $insertData['add_user'] = Session::get('userName');
        //插入数据
        $ret = Db::connect("db_mongo")->name("dx_brand_attribute")->insert($insertData);
        if(!$ret){
            pr('dxclassid = '.$class_id.',erpid = '.$erp_class_id.'新增失败');
            return false;
        }else{
            echo 'dxclassid = '.$class_id.',erpid = '.$erp_class_id.'新增成功';
        }
        return true;
    }
    /**
     * 修改销售属性
     * [asyncErpDataToattribute description]
     * @return [type] [description]
     */
    public function asyncErpDataToattribute($class_id,$erp_class_id,$data){
        // echo '到这里了:'.$class_id;
        $brand_attribute = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$class_id])->find();
        if($brand_attribute){
                //产品属性封装
                if(!empty($data['catalog_attr_list'])){
                    foreach((array)$data['catalog_attr_list'] as $key => $val){
                        $id = $val['id'];
                        $brand_attribute['catalog_attr_list'][$id]['id'] = $val['id'];
                        $brand_attribute['catalog_attr_list'][$id]['title_cn'] = $val['names_cn'];
                        $brand_attribute['catalog_attr_list'][$id]['title_en'] = $val['names_en'];
                        $brand_attribute['catalog_attr_list'][$id]['show_type'] = $val['show_type'];
                        $brand_attribute['catalog_attr_list'][$id]['input_type'] = $val['input_type'];
                        $brand_attribute['catalog_attr_list'][$id]['required'] = $val['required'];;
                        $brand_attribute['catalog_attr_list'][$id]['sort'] = $key;
                        $brand_attribute['catalog_attr_list'][$id]['status'] = 1;
                        $brand_attribute['catalog_attr_list'][$id]['addtime'] = time();
                        $brand_attribute['catalog_attr_list'][$id]['attr_value'] = [];
                        if(!empty($val['attr_value'])){
                            foreach((array)$val['attr_value'] as $k => $v){
                                $attr_id = $v['id'];
                                $brand_attribute['catalog_attr_list'][$id]['attr_value'][$attr_id]['id'] = $attr_id;
                                $brand_attribute['catalog_attr_list'][$id]['attr_value'][$attr_id]['title_cn'] = $v['title_cn'];
                                $brand_attribute['catalog_attr_list'][$id]['attr_value'][$attr_id]['title_en'] = $v['title_en'];
                                $brand_attribute['catalog_attr_list'][$id]['attr_value'][$attr_id]['sort'] = $k;
                                $brand_attribute['catalog_attr_list'][$id]['attr_value'][$attr_id]['edit_time'] = time();
                            }
                        }
                    }
                }
                //销售属性封装
                if(!empty($data['sale_attr_list'])){
                    foreach((array)$data['sale_attr_list'] as $key => $val){
                        $id = $val['id'];
                        $brand_attribute['attribute'][$id]['id'] = $val['id'];
                        $brand_attribute['attribute'][$id]['title_cn'] = $val['names_cn'];
                        $brand_attribute['attribute'][$id]['title_en'] = $val['names_en'];
                        $brand_attribute['attribute'][$id]['show_type'] = $val['show_type'];
                        $brand_attribute['attribute'][$id]['input_type'] = $val['input_type'];
                        $brand_attribute['attribute'][$id]['spec'] = $val['spec'];
                        $brand_attribute['attribute'][$id]['customized_name'] = $val['customized_name'];
                        $brand_attribute['attribute'][$id]['customized_pic'] = $val['customized_pic'];
                        $brand_attribute['attribute'][$id]['is_color'] = $val['is_color'];
                        $brand_attribute['attribute'][$id]['sort'] = $key;
                        $brand_attribute['attribute'][$id]['status'] = 1;
                        $brand_attribute['attribute'][$id]['addtime'] = time();
                        $brand_attribute['attribute'][$id]['attribute_value'] = [];
                        if(!empty($val['sale_attr_value_list'])){
                            foreach((array)$val['sale_attr_value_list'] as $k => $v){
                                $attr_id = $v['id'];
                                $brand_attribute['attribute'][$id]['attribute_value'][$attr_id]['id'] = $attr_id;
                                $brand_attribute['attribute'][$id]['attribute_value'][$attr_id]['title_cn'] = $v['title_cn'];
                                $brand_attribute['attribute'][$id]['attribute_value'][$attr_id]['title_en'] = $v['title_en'];
                                $brand_attribute['attribute'][$id]['attribute_value'][$attr_id]['sort'] = $k;
                                $brand_attribute['attribute'][$id]['attribute_value'][$attr_id]['edit_time'] = time();
                            }
                        }
                    }
                }
        // $brand_attribute['edit_time'] = time();
        $brand_attribute['edit_user'] = Session::get('username');
        $result =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$class_id])
        ->update(['catalog_attr_list'=>$brand_attribute['catalog_attr_list'],'attribute'=>$brand_attribute['attribute'],'edit_time'=>time(),'edit_user'=>$brand_attribute['edit_user']]);
            if($result){
                echo '修改成功';
            }else{
                echo '修改失败';;
            }
        }else{
          echo '没查到数据';;
        }
        // dump($brand_attribute);
        return true ;

    }

    /**
     *同步erp属性数据到本地品牌属性表
     * @param $class_id dx类别id
     * @param $erp_class_id 同步erpid
     * @param $data
     * @return bool
     * @throws \think\Exception
     */
    public function asyncErpDataToBrandAttribute_20180823($class_id,$erp_class_id,$data){
        if(empty($data)){
            return false;
        }
        $time = time();
        //查询是否存在
        $exist_id = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$class_id])->value('_id');
        if(!empty($exist_id)){
            Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$class_id])->delete();
        }
        //数据新增
        $insertData = array();
        $insertData['_id'] = (int)$class_id;
        $insertData['title_cn'] = $data['title_cn'];
        $insertData['title_en'] = $data['title_en'];
        $insertData['status'] = 1;
        //产品属性封装
        $insertData['catalog_attr_list'] = [];
        if(!empty($data['catalog_attr_list'])){
            foreach($data['catalog_attr_list'] as $key => $val){
                $id = $val['id'];
                $insertData['catalog_attr_list'][$id]['id'] = $val['id'];
                $insertData['catalog_attr_list'][$id]['title_cn'] = $val['names_cn'];
                $insertData['catalog_attr_list'][$id]['title_en'] = $val['names_en'];
                $insertData['catalog_attr_list'][$id]['show_type'] = $val['show_type'];
                $insertData['catalog_attr_list'][$id]['input_type'] = $val['input_type'];
                $insertData['catalog_attr_list'][$id]['required'] = $val['required'];;
                $insertData['catalog_attr_list'][$id]['sort'] = $key;
                $insertData['catalog_attr_list'][$id]['status'] = 1;
                $insertData['catalog_attr_list'][$id]['addtime'] = $time;
                $insertData['catalog_attr_list'][$id]['attr_value'] = [];
                if(!empty($val['attr_value'])){
                    foreach($val['attr_value'] as $k => $v){
                        $attr_id = $v['id'];
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['id'] = $attr_id;
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['title_cn'] = $v['title_cn'];
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['title_en'] = $v['title_en'];
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['sort'] = $k;
                        $insertData['catalog_attr_list'][$id]['attr_value'][$attr_id]['edit_time'] = $time;
                    }
                }
            }
        }
        //销售属性封装
        $insertData['attribute'] = [];
        if(!empty($data['sale_attr_list'])){
            foreach($data['sale_attr_list'] as $key => $val){
                $id = $val['id'];
                $insertData['attribute'][$id]['id'] = $val['id'];
                $insertData['attribute'][$id]['title_cn'] = $val['names_cn'];
                $insertData['attribute'][$id]['title_en'] = $val['names_en'];
                $insertData['attribute'][$id]['show_type'] = $val['show_type'];
                $insertData['attribute'][$id]['input_type'] = $val['input_type'];
                $insertData['attribute'][$id]['spec'] = $val['spec'];
                $insertData['attribute'][$id]['customized_name'] = $val['customized_name'];
                $insertData['attribute'][$id]['customized_pic'] = $val['customized_pic'];
                $insertData['attribute'][$id]['is_color'] = $val['is_color'];
                $insertData['attribute'][$id]['sort'] = $key;
                $insertData['attribute'][$id]['status'] = 1;
                $insertData['attribute'][$id]['addtime'] = $time;
                $insertData['attribute'][$id]['attribute_value'] = [];
                if(!empty($val['sale_attr_value_list'])){
                    foreach($val['sale_attr_value_list'] as $k => $v){
                        $attr_id = $v['id'];
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['id'] = $attr_id;
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['title_cn'] = $v['title_cn'];
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['title_en'] = $v['title_en'];
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['sort'] = $k;
                        $insertData['attribute'][$id]['attribute_value'][$attr_id]['edit_time'] = $time;
                    }
                }
            }
        }
        $insertData['add_time'] = time();
        $insertData['add_user'] = Session::get('username');
        //插入数据
        $ret = Db::connect("db_mongo")->name("dx_brand_attribute")->insert($insertData);
        if(!$ret){
            pr('dxclassid = '.$class_id.',erpid = '.$erp_class_id.'新增失败');
            return false;
        }
        return true;
    }


   /**
    * 更新类别的品牌数据 TODO
    */
   public function asyncBrand(){

   }

   /**
    * 获取产品类别数据
    * @return 获取一级产品类别数据:
    */
   public function getProductFirstClass(){
	   	$result = Db::connect("db_mongo")->name("dx_product_class")
				   	->where(['pid'=>0])
				   	->select();
	   	//->field('id','pid','title_en');
	   	return $result;
   }

   /**
    * 获取产品类别数据--指定类别ID
    * @return 获取一级产品类别数据:
    */
   public function getProductFirstClassByIDs(array $firstClassIDs){
	   	$map['id']  = array('in',$firstClassIDs);
	   	//print_r($firstClassIDs);
	   	$result = Db::connect("db_mongo")->name("dx_product_class")
	   	                ->where($map)
	   	//->field('id','pid','title_en') //MYSQL
	   	->field(['id'=>true,'pid'=>true,'title_en'=>true])
	   	->select();
	   	return $result;
   }

    /**
     * 类别数据导出--PDC类别数据
     */
   public  function export_PDC_class($id=0){
       if(empty($id)){
           echo 'ID不可为空!<br><br>';
           exit();
       }
       $firstClassName ='';
       $temp = array();
       //获取类别数据
       $firstClass=DB::name(PRODUCT_CLASS)->where('id',$id)->field('id,pid,title_en')->order('id asc')->select();
       $firstClassCount = count($firstClass);
       if($firstClassCount > 0){
           //dump($firstClassCount);
           foreach ($firstClass as $key => $value){
               /*
                *  $temp[] = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                                   '二级分类id'=>$v['id'],'二级分类'=>$v['title_en'],
                                                   '三级分类id'=>$i['id'],'三级分类'=>$i['title_en'],
                                                   '四级分类id'=>$y['id'],'四级分类'=>$y['title_en']
                */
               //dump($value['id']);
               //exit();
               //TODO 这里使用的是单类别导出模式，故foreach 里只有一个一级类别
               $firstClassName =$value['title_en'];
               $secondClass=DB::name(PRODUCT_CLASS)->where('pid',$value['id'])->field('id,pid,title_en')->order('id asc')->select();
               if(count($secondClass)>0){
                   foreach($secondClass as $key2 => $value2){
                       $thirdClass=DB::name(PRODUCT_CLASS)->where('pid',$value2['id'])->field('id,pid,title_en')->order('id asc')->select();
                       if(count($thirdClass)>0){
                           foreach($thirdClass as $key3 => $value3){
                               $fourthClass=DB::name(PRODUCT_CLASS)->where('pid',$value3['id'])->field('id,pid,title_en')->order('id asc')->select();
                               if(count($fourthClass) >0 ){
                                   foreach($fourthClass as $key4 => $value4){
                                       $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                                   '二级分类id'=>$value2['id'],'二级分类'=>$value2['title_en'],
                                                   '三级分类id'=>$value3['id'],'三级分类'=>$value3['title_en'],
                                                   '四级分类id'=>$value4['id'],'四级分类'=>$value4['title_en']
                                                 ];
                                       array_push($temp,$temp_1);
                                   }
                               }else{
                                   $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                       '二级分类id'=>$value2['id'],'二级分类'=>$value2['title_en'],
                                       '三级分类id'=>$value3['id'],'三级分类'=>$value3['title_en'],
                                       '四级分类id'=>'','四级分类'=>''
                                   ];
                                   array_push($temp,$temp_1);
                               }
                           }
                       }else{
                           $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                               '二级分类id'=>$value2['id'],'二级分类'=>$value2['title_en'],
                               '三级分类id'=>'','三级分类'=>'',
                               '四级分类id'=>'','四级分类'=>''
                           ];
                           array_push($temp,$temp_1);
                       }
                   }
               }else{
                   $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                       '二级分类id'=>'','二级分类'=>'',
                       '三级分类id'=>'','三级分类'=>'',
                       '四级分类id'=>'','四级分类'=>''
                   ];
                   array_push($temp,$temp_1);
               }
           }
           //dump($temp);
       }
       //die();
       $header_data =['一级分类id'=>'一级分类id','一级分类'=>'一级分类'
           ,'二级分类id'=>'二级分类id','二级分类'=>'二级分类'
           ,'三级分类id'=>'三级分类id','三级分类'=>'三级分类'
           ,'四级分类id'=>'四级分类id','四级分类'=>'四级分类'
       ];
       $tool = new ExcelTool();
       // dump($temp);exit;
       $result = $tool ->export('PDC-'.$id.'-'.$firstClassName.'-Class-Data-'.date('Y-m-d'),$header_data,$temp,'sheet1');
   }

    /**
     * 类别数据导出--ERP类别数据
     */
    public  function export_ERP_class($id=0){
        if(empty($id)){
            echo 'ID不可为空!<br><br>';
            exit();
        }
        $firstClassName ='';
        $temp = array();
        //获取类别数据
        $firstClass=DB::name(PRODUCT_CLASS)
                        ->where('id',$id)
                        ->where('status',1)
                        ->where('type',1)
                        ->field('id,pid,title_en')
                        ->order('id asc')
                        ->select();
        $firstClassCount = count($firstClass);
        if($firstClassCount > 0){
            //dump($firstClassCount);
            foreach ($firstClass as $key => $value){
                /*
                 *  $temp[] = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                                    '二级分类id'=>$v['id'],'二级分类'=>$v['title_en'],
                                                    '三级分类id'=>$i['id'],'三级分类'=>$i['title_en'],
                                                    '四级分类id'=>$y['id'],'四级分类'=>$y['title_en']
                 */
                //dump($value['id']);
                //exit();
                //TODO 这里使用的是单类别导出模式，故foreach 里只有一个一级类别
                $firstClassName =$value['title_en'];
                $secondClass=DB::name(PRODUCT_CLASS)
                            ->where('pid',$value['id'])->where('status',1)
                            ->where('type',1)
                            ->field('id,pid,title_en')->order('id asc')->select();
                //dump(count($secondClass));
                //exit();
                if(count($secondClass)>0){
                    foreach($secondClass as $key2 => $value2){
                        $thirdClass=DB::name(PRODUCT_CLASS)->where('pid',$value2['id'])->where('status',1)->where('type',1)
                                                            ->field('id,pid,title_en')->order('id asc')->select();
                        if(count($thirdClass)>0){
                            foreach($thirdClass as $key3 => $value3){
                                $fourthClass=DB::name(PRODUCT_CLASS)->where('pid',$value3['id'])->where('status',1)->where('type',1)
                                                                    ->field('id,pid,title_en')->order('id asc')->select();
                                if(count($fourthClass) >0 ){
                                    foreach($fourthClass as $key4 => $value4){
                                        $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                            '二级分类id'=>$value2['id'],'二级分类'=>$value2['title_en'],
                                            '三级分类id'=>$value3['id'],'三级分类'=>$value3['title_en'],
                                            '四级分类id'=>$value4['id'],'四级分类'=>$value4['title_en']
                                        ];
                                        array_push($temp,$temp_1);
                                    }
                                }else{
                                    $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                        '二级分类id'=>$value2['id'],'二级分类'=>$value2['title_en'],
                                        '三级分类id'=>$value3['id'],'三级分类'=>$value3['title_en'],
                                        '四级分类id'=>'','四级分类'=>''
                                    ];
                                    array_push($temp,$temp_1);
                                }
                            }
                        }else{
                            $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                                '二级分类id'=>$value2['id'],'二级分类'=>$value2['title_en'],
                                '三级分类id'=>'','三级分类'=>'',
                                '四级分类id'=>'','四级分类'=>''
                            ];
                            array_push($temp,$temp_1);
                        }
                    }
                }else{
                    $temp_1 = ['一级分类id'=>$value['id'],'一级分类'=>$value['title_en'],
                        '二级分类id'=>'','二级分类'=>'',
                        '三级分类id'=>'','三级分类'=>'',
                        '四级分类id'=>'','四级分类'=>''
                    ];
                    array_push($temp,$temp_1);
                }
            }
            //dump($temp);
        }
        //die();
        $header_data =['一级分类id'=>'一级分类id','一级分类'=>'一级分类'
            ,'二级分类id'=>'二级分类id','二级分类'=>'二级分类'
            ,'三级分类id'=>'三级分类id','三级分类'=>'三级分类'
            ,'四级分类id'=>'四级分类id','四级分类'=>'四级分类'
        ];
        $tool = new ExcelTool();
        $result = $tool ->export('ERP-'.$id.'-'.$firstClassName.'-Class-Data-'.date('Y-m-d'),$header_data,$temp,'sheet1');
    }

    /**
     * 临时使用，更新ERPid
     */
    public function updataClassErpid(){
        $allClassID=DB::name(PRODUCT_CLASS)->field('id')->select();
        if(count($allClassID)>0){
            foreach ($allClassID as $key => $value){
                 $result = DB::name(PRODUCT_CLASS)->where('id',$value['id'])->update(['erp_id'=>$value['id']]);
                 echo '更新成功：'.$value['id'];
            }
        }
    }

    /**
     * 临时使用,插入ERP类别数据，检查重复，如果重复则修改ID
     */
    public function InsertERPClass(){
        //获取一级类别数据
        $firstClass= $this::getERPSonClassByParentID(0);
        /*
        $firstClass=DB::name(PDVEE_PRODUCT_CATALOG)
            ->where('id',13)
            ->where('status',1)
            ->field('id,pid,title_cn,title_en,level,isleaf')
            ->select();
        */
        $t = time();
        if(count($firstClass)>0){
            foreach ($firstClass as $key => $value){
                $result = $this::insertPorductClass($value,$t);
                if($result){
                    echo '一级分类插入成功：$v1：'.$value['id'].'<br>';
                    $secondClass = $this::getERPSonClassByParentID($value['id']);
                    if(count($secondClass) >0){
                        foreach ($secondClass as $k2 => $v2){
                            $result2 = $this::insertPorductClass($v2,$t);
                            if($result2){
                                echo '二级分类插入成功：$v2：'.$v2['id'].'<br>';
                                $thirdClass = $this::getERPSonClassByParentID($v2['id']);
                                if(count($thirdClass) > 0){
                                    foreach ($thirdClass as $k3 => $v3) {
                                        $result3 = $this::insertPorductClass($v3,$t);
                                        if($result3){
                                            echo '三级分类插入成功：$v3：'.$v3['id'].'<br>';
                                            $fourClass = $this::getERPSonClassByParentID($v3['id']);
                                            if($fourClass){
                                                foreach ($fourClass as $k4 => $v4) {
                                                    $result4 = $this::insertPorductClass($v4,$t);
                                                    if($result4){
                                                        echo '四级分类插入成功：$v4：'.$v4['id'].'<br>';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $parentID 父类ID;
     * @return Collection
     */
    public static function getERPSonClassByParentID($parentID){
        $result=DB::name(PDVEE_PRODUCT_CATALOG)
            ->where('pid',$parentID)
            ->where('status',1)
            ->field('id,pid,title_cn,title_en,level,isleaf')
            ->select();
        return $result;
    }

    /**
     * @param $id
     * @return bool true,存在重复，false 不重复
     */
    public static function checkProductClassID($id){
        $result=DB::name(PRODUCT_CLASS)
            ->where('id',$id)
            ->where('status',1)
            ->count();
        return $result>0;
    }

    /**
     * 获取ERP最大类别ID
     */
    public static function getProductMaxClassID(){
        $idMax=DB::name(PRODUCT_CLASS)
            ->order('id desc')
            ->value('id');

        $pidMax=DB::name(PRODUCT_CLASS)
            ->order('pid desc')
            ->value('pid');
        if($idMax > $pidMax){
            return $idMax;
        }else{
            return $pidMax;
        }
    }

    /**
     * @param $parentID
     */
    public static function updateProductSonClassID($parentID,$newParentID){
        echo '$parentID:'.$parentID.'<br>';
        $count = DB::name(PRODUCT_CLASS)->where('pid',$parentID)->count();
        if($count > 0){
            $result=DB::name(PRODUCT_CLASS)
                        ->where('pid',$parentID)
                        ->update(['pid'=>$newParentID]);
            if($result){
                echo '更新'.$parentID.'类别的子类成功,新的父类ID:'.$newParentID.'<br>';
                die('---------------------- stop');
                exit();
            }else{
                echo '更新'.$parentID.'类别的子类失败！<br>';
            }
        }else{
            echo $parentID.'无子类别！<br>';
        }
    }

    /**
     * @param $data 数据对象
     * @return 是否插入成功
     */
    public static function insertPorductClass($value,$t){
        //dump($value);
        //die();
        $check = self::checkProductClassID($value['id']);
        $_id = $value['id'];
        if($check){
            //重复则要重新编码ID
            //新的编码规则是取出数据库最大ID加1
            $max_id= self::getProductMaxClassID();
            if($max_id>0){
                pr("获取最大的ID: max_id = ".$max_id);
                pr("'获取最大的ID加1后：$max_id ".($max_id +1));
                $_id = (int)$max_id + 1;
                //self::updateProductSonClassID($value['id'],$_id); //该方法总是不能更新到子类，暂时放弃
            }else{
                echo '获取最大的ID错误：$max_id'.$max_id.'<br>';
            }
        }
        $data = array(
            'id' =>  $_id, //类别ID如果重复会重置
            'pid' => $value['pid'],
            'title_cn' => $value['title_cn'],
            'title_en' => $value['title_en'],
            'level' => $value['level'],
            'type' => 1,
            'isleaf' => $value['isleaf'],
            'erp_id' => $value['id'], //ERP ID不可变
            'add_author' => Session::get('username'),
            'add_time' => $t
        );
        $result = DB::name(PRODUCT_CLASS)->insert($data);
        return $result;
    }

    /**
     *由于ERP数据导入产品类别表时ID重复，修改了类别ID，
     * 但是如果是否该类别有子类，则需要更新子类的PID字段为新的值，
     * 否则在查询子类时无法查询到结果
     */
    public static function updateProductClassParentID(){
        $result=DB::name(PRODUCT_CLASS)
                        ->query('SELECT id,pid,erp_id from dx_product_class where id <> erp_id and type =1');
        //$sql = DB::name(PRODUCT_CLASS)->getLastSql();
        //dump($sql);
       // exit();
        $count = count($result);
        if($count >0){
            //dump($count);
            foreach($result as $key =>$value){
                $c  = DB::name(REDIS_PRODUCT_CLASS)
                    ->where(['pid'=>$value['erp_id'],'type'=>1])
                    ->count();
                if($c>0){
                    $r = DB::name(REDIS_PRODUCT_CLASS)
                        ->where(['pid'=>$value['erp_id'],'type'=>1])
                        ->update(['pid'=>$value['id']]);
                    //dump(DB::name(REDIS_PRODUCT_CLASS)->getLastSql());
                    //dump($value);
                    //dump($r);
                    //die();
                    if($r){
                        echo '更新成功，erp_id:'.$value['erp_id'].',pid:'.$value['id']."<br>";
                    }else{
                        echo '更新失败，erp_id:'.$value['erp_id']."<br>";
                    }
                }else{
                    echo '无子分类，erp_id:'.$value['erp_id']."<br>";
                }
            }
        }else{
            echo '没查询到数据！';
        }
    }

    /**
     * 同步PDC类别的品牌数据到ERP类别
     */
    public  static  function asyncPDCBrandToERPBrand(){
        //获取ERP一级分类
        $result = self::getClassByID(null,1);
        $count1 = count($result);
        $t = time();
        //dump($count1);
        if($count1>0){
            foreach ($result as $item =>$value) {
                if(!empty($value['pdc_ids'])){//dump($value['pdc_ids']);
                    //dump($value['pdc_ids']);
                    $resultBrand = self::getBrandByClassID($value['pdc_ids']);
                    // dump($value);
                    // dump($resultBrand);
                    //dump($resultBrand);
                    if(!empty($resultBrand)){
                        $checkResult = Db::connect("db_mongo")->name("dx_brand_attribute")
                                    ->where('_id',$value['id'])
                                    ->find();
                                    //dump($checkResult);
                        // echo '$checkResult:'.$checkResult.'<br>';
                        if($checkResult >0){
                            // unset($resultBrand['_id']);
                            foreach ($resultBrand as $k => $v) {
                                foreach ($v['product_brand'] as $ke => $va) {
                                   $checkResult['product_brand'][$ke] = $va;
                                }
                            }
                            // dump($checkResult);exit;continue;
                            //EditTime
                            $resultBrand['EditTime'] =$t;
                            $result = Db::connect("db_mongo")->name("dx_brand_attribute")
                                ->where('_id',$value['id'])
                                ->update(['product_brand'=>(Object)$checkResult['product_brand']]);
                                // dump($result);
                            if($result){
                                echo '更新品牌数据成功：'.$value['id'].'<br>';
                            }
                        }else{
                             foreach ($resultBrand as $k => $v) {
                                foreach ($v['product_brand'] as $ke => $va) {
                                   $checkResult['product_brand'][$ke] = $va;
                                }
                            }
                            $data['_id'] = $value['id'];
                            $data['product_brand'] = $checkResult['product_brand'];
                            $data['add_time'] = $t;
                            $data['add_user'] = Session::get('username');
                            $data['status']   = 1;
                            //dump($data);
                            $result = Db::connect("db_mongo")->name("dx_brand_attribute")
                                     ->insert($data);
                            if($result){
                                echo '插入品牌数据成功：'.$value['id'].'<br>';
                            }
                        }
                    }else{
                        echo '类别无品牌数据：'.$value['id'].'<br>';
                    }
                }
            }
        }else{
            echo '没有符合条件的数据<br>';
        }
    }

    /**
     * @param $id ID
     * @return Collection
     */
    public static function getClassByID($ids,$type=1){
        if(!empty($ids)){
            $map['pid'] =['in',$ids];
        }
        $map['type'] =$type;
        $map['pdc_ids'] = ['gt',[]]; //数组不等于空
        $map['status'] = 1;
        $result = Db::connect("db_mongo")->name("dx_product_class")
                    ->where($map)
                    ->select();
        //dump(Db::connect("db_mongo")->getLastSql());
        //dump($result);
        //die();
        return $result;
    }

    /**
     * @param array $ids 类别ID
     * @return Collection
     */
    public static function getBrandByClassID(array $ids = []){
        if(empty($ids)){
            return;
        }
        foreach ($ids as $key => $value) {
           $int_array[] = (int)$value;
        }
        // $ids = [1204];
        $map['_id'] =['in', $int_array];
        $map['status'] = 1;
        $result = Db::connect("db_mongo")->name("dx_brand_attribute")
                    ->where($map)
                    ->field('product_brand')
                    ->select();
         // dump($result);
         // dump(Db::connect("db_mongo")->name("dx_brand_attribute")->getLastSql());
        //dump($result);
        //die();
        return $result;
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
        if(!empty($file)){
            $info = $file->validate(['size'=>15678,'ext'=>'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');
            //var_dump($id);
            if($info){
                $exclePath = $info->getSaveName();  //获取文件名
                $file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址
                $objReader =\PHPExcel_IOFactory::createReader('Excel2007');
                $obj_PHPExcel =$objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
                $excel_array=$obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
                //var_dump($id);
                array_shift($excel_array);  //删除第一个数组(标题);
                //var_dump($onlyData);
                //die('look ....');
                //}else{
                //var_dump($excel_array);
                foreach ($excel_array as $key => $val){
                        print_r($val);
                        die('--------');
                }
                //echo '<br>';
                //var_dump($onlyData);
                //die('look ....');
                //}
                if(!empty($oldSPUSArray)){
                    sort($oldSPUSArray);
                    sort($onlyData);
                    if($oldSPUSArray == $onlyData){
                        echo json_encode(array('code'=>101,'result'=>'提交的数据全部重复，请核实后再操作'));
                        exit;
                    }
                }
            }else{
                echo json_encode(array('code'=>102,'result'=>'文件验证失败'));
                exit;
            }
        }else{
            echo json_encode(array('code'=>101,'result'=>'请检查数据后再上传！'));
            exit;
        }
    }

    /**
     * 读取EXCEL文件内容，修正及更新正向、逆向映射关系
     * 正向：ERP ID映射PDC ID
     * 逆向：反之
     * @return bool
     */
    public function redadEexcel(){
        vendor("PHPExcel.PHPExcel");
        $objPHPExcel = new \PHPExcel();
        $PHPReader = new \PHPExcel_Reader_Excel2007();
        //$filePath = __DIR__.'\public\uploads\test.xlsx';
        $filePath ='./uploads/test.xlsx';
        //dump($filePath);
        //判断文件类型
        if (!$PHPReader->canRead($filePath)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($filePath)) {
                echo 'no Excel';
                return false;
            }
        }
        //die('----停下来看看-------------');
        $PHPExcel = $PHPReader->load($filePath);
        /**读取excel文件中的第一个工作表*/
        $currentSheet = $PHPExcel->getSheet(0);
        //dump($currentSheet);
        /**取得最大的列号 A,B,C...*/
        //$allColumn = $currentSheet->getHighestColumn();
        /**取得一共有多少行*/
        $allRow = $currentSheet->getHighestRow();
        /**正向更新的ID集合**/
        $forwardID=[];
        //dump($forwardID);
        /**从第2行开始输出,跳过表头*/
        for ($currentRow = 2; $currentRow <= $allRow; $currentRow++) {
            $row=[];
            $rowIndex = $currentRow - 1;
            /**从第A列开始输出*/
            for ($currentColumn = 'A'; $currentColumn <= 'B'; $currentColumn++) {
                $val = $currentSheet->getCellByColumnAndRow(ord($currentColumn) - 65, $currentRow)->getValue();
                /**ord()将字符转为十进制数*/
                $row[$rowIndex][] = $val;
            }
            //dump($row[$rowIndex][0]);
            if(empty($row[$rowIndex][0])){
                echo 'id为空或者0<br>';
                continue;
            }
            if(in_array($row[$rowIndex][0],$forwardID)){
                echo 'id 重复<br>';
                continue;
            }
            unset($requery);
            //dump($row);
            $requery['type'] =1;
            $requery['status'] =1;
            $requery['erp_id'] = $row[$rowIndex][0];
            $resultDate =  DB::name(PRODUCT_CLASS)->where($requery)->column('id');
            //dump(DB::name(PRODUCT_CLASS)->getLastSql());
            echo '<br>';
            $newID = 0;
            //dump($resultDate);
            echo '<br>';
            if($resultDate){
                $newID =  $resultDate[0];//替换原来EXCEL文件里的旧的ID数据，使用新的ID创建映射关系，有些ID没有更新，则id与erp_id相等；
                //$row[1][0] = $resultDate[0];
                unset($requery['erp_id']);
                $requery['id'] = $newID;
            }else{
                echo 'get erp_id:'.$row[$rowIndex][0].' empty <br>';
            }
            //$sql ="UPDATE dx_product_class SET pdc_ids= CONCAT(pdc_ids,',".$row[$rowIndex][1]."') WHERE type= 1 and status=1 and id=".$newID;
            //dump($sql);
            //使用追加字段值的方式，这样可以支持双向多对多
            $pdc_ids='';
            $find =  DB::name(PRODUCT_CLASS)->where($requery)->column('pdc_ids');
            //dump($find);
            if($find && !empty($find[0])){
                $pdc_ids=$find[0];
            }
            if(strlen($pdc_ids)>0){
                $pdc_ids .= ','.$row[$rowIndex][1];
            }else{
                $pdc_ids = $row[$rowIndex][1];
            }
            //dump($pdc_ids);
            $result =  DB::name(PRODUCT_CLASS)->where($requery)->update(['pdc_ids'=>$pdc_ids]);
            //$result =  DB::name(PRODUCT_CLASS)->query($sql);
            if($result){
                $forwardID[] =$row[1][0];//把EXCEL文件的ID插入正向更新的集合
                echo 'forward update success id:'.$row[$rowIndex][0].',newID:'.$newID.',pdc_ids:'.$row[$rowIndex][1].'<br>';
            }else{
                echo 'forward update fail id:'.$row[$rowIndex][0].',newID:'.$newID.',pdc_ids:'.$row[$rowIndex][1].'<br>';
            }
            unset($requery);
            //逆向更新
            $requery['type'] =2; //PDC数据
            $requery['status'] =1;
            $requery['id'] = ['in',$row[$rowIndex][1]]; //多个PDC ID 映射到一个ERP ID
            /**
            if($newID != $row[$rowIndex][0]){
                $newID = $row[$rowIndex][0];
            }
             */
            //$sql ="UPDATE dx_product_class SET pdc_ids= CONCAT(pdc_ids,',".$newID."') WHERE type= 2 and status=1 and id in(".$row[$rowIndex][1].")";
            //dump($sql);
            //使用追加字段值的方式，这样可以支持双向多对多
            $pdc_ids='';
            //dump('ddd'.strlen($pdc_ids));
            $find =  DB::name(PRODUCT_CLASS)->where($requery)->column('pdc_ids');
            //dump($find);
            if($find && !empty($find[0])){
                $pdc_ids=$find[0];
                //dump($pdc_ids);
            }
            //dump(strlen($pdc_ids));
            if(strlen($pdc_ids)>0){
                $pdc_ids .= ','.$newID;
            }else{
                $pdc_ids = $newID;
            }
            //dump($pdc_ids);
            $result =  DB::name(PRODUCT_CLASS)->where($requery)->update(['pdc_ids'=>$pdc_ids]);
            //$result =  DB::name(PRODUCT_CLASS)->query($sql);
            //dump(DB::name(PRODUCT_CLASS)->getLastSql());
            echo '<br>';
            $msg = 'pdc_id:'.$row[$rowIndex][1].',newID:'.$newID.'<br>';
            if($result){
                echo 'reverse update success '.$msg;
            }else{
                echo 'reverse update fail :'.$msg;
            }
            //dump($forwardID);
            //die();
        }
        echo '$allRow:'.$allRow.'-----<br>';
    }


    /**
     * 临时使用,遍历ERP全部类别，将没有映射PDC 类别的数据设置为不可用 status =0
     */
    public function updateERPClassStatus(){
        //获取一级类别数据
        $firstClass= $this::getProductERPSonClassByParentID(0,0);
        $t = time();
        if(count($firstClass)>0){
            foreach ($firstClass as $key => $value){
                    $secondClass = $this::getProductERPSonClassByParentID($value['id'],0);
                    if(count($secondClass) >0){
                        foreach ($secondClass as $k2 => $v2){
                                $thirdClass = $this::getProductERPSonClassByParentID($v2['id'],1);
                                if(count($thirdClass) > 0){
                                    foreach ($thirdClass as $k3 => $v3) {
                                        $result3 = $this::insertPorductClass($v3,$t);
                                        if($result3){
                                            echo '三级分类插入成功：$v3：'.$v3['id'].'<br>';
                                            $fourClass = $this::getERPSonClassByParentID($v3['id']);
                                            if($fourClass){
                                                foreach ($fourClass as $k4 => $v4) {
                                                    $result4 = $this::insertPorductClass($v4,$t);
                                                    if($result4){
                                                        echo '四级分类插入成功：$v4：'.$v4['id'].'<br>';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                        }
                    }
            }
        }
    }

    /**
     * 更新产品类别状态
     */
    private function updateClassStatusById($id){
        $result = DB::name(PRODUCT_CLASS)->where('id',$id)->update(['status'=>0]);
        if(!$result){
            echo '<span class="red">类别ID:'.$id.'</span>';
        }
    }

    /**
     * 获取产品表的子类数据
     * @param $parentID 父类ID;
     * @param $isHave 是否只查询pdc_ids 非空数据
     * @return Collection
     */
    public static function getProductERPSonClassByParentID($parentID,$isHave=0){
        $query['pid'] = $parentID;
        $query['status'] = 1;
        $query['type'] = 1;
        if($isHave){
            $query['pdc_ids'] = null;
        }
        $result=DB::name(PRODUCT_CLASS)
                    ->where($query)
                    ->field('id,pid,pdc_ids')
                    ->select();
        return $result;
    }
    public function  history(){
        $data = array();
        $list = Db::connect("db_mongo")->name("dx_product_class")->where(['type'=>1])->select();
        foreach ($list as $k => $v) {
           $data['EntityId'] = (int)$v['id'];
           $data['CreatedDateTime'] = time();
           $data['IsSync'] = false;
           $data['Note']   = '新增类别';
           $result = Db::connect("db_mongo")->name("dx_product_class_histories")->insert($data);

           if(!$result){
               echo $v['id'].'<br/>';
           }else{
               echo $v['id'].'成功<br/>';
           }
        }
//        $list = Db::connect("db_mongo")->name("dx_product_class_histories")->where(['IsSync'=> false])->delete(); //删除
          /*  DB::name('dx_product_class_histories')->where(['IsSync'=> false])->delete();*/
//        dump($list);
    }


    /**
     * 设置916的SKU库存为0,add by heng.zhang 2018-08-29
     */
    public function hk_inventory_set_zero(){
        echo 'start ----------------<br>';

        $skus = [
            916475518,
            916476723,
            916476724,
            916476727,
            916476698,
            916476699,
            916478608,
            916478618,
            916478627,
            916478628,
            916478631,
            916478637,
            916478641,
            916478646,
            916478649,
            916478647,
            916478650,
            916478648,
            916478666,
            916478692,
            916478668,
            916478670,
            916478667,
            916478671,
            916478693,
            916478882,
            916478879,
            916478889,
            916478888,
            916478885,
            916478881,
            916478883,
            916478884,
            916478887,
            916478906,
            916478904,
            916478902,
            916478905,
            916478903,
            916478901,
            916479280,
            916479403,
            916479404,
            916479405,
            916479323,
            916479283,
            916479289,
            916479336,
            916479337,
            916479338,
            916479392,
            916479394,
            916479391,
            916479397,
            916479473,
            916479475,
            916479740,
            916479742,
            916479745,
            916480971,
            916480991,
            916480979,
            916480980,
            916480997,
            916481294,
            916481425,
            916481468,
            916482337,
            916482802,
            916482832,
            916482809,
            916482835,
            916482836,
            916482812,
            916482811,
            916482842,
            916482841,
            916482844,
            916482847,
            916482856,
            916482857,
            916482864,
            916482875,
            916482885,
            916482888,
            916482889,
            916482928,
            916482915,
            916482916,
            916482930,
            916482917,
            916482918,
            916482920,
            916482922,
            916482941,
            916483207,
            916483208,
            916483172,
            916483171,
            916483173,
            916483182,
            916483177,
            916483176,
            916483179,
            916483174,
            916483175,
            916483178,
            916483169,
            916483804,
            916483806,
            916483805,
            916483807,
            916483808,
            916483880,
            916483881,
            916483882,
            916483883,
            916483884,
            916483886,
            916483885,
            916483887,
            916484258,
            916484264,
            916484270,
            916484269,
            916484272,
            916484273,
            916484274,
            916484454,
            916484667,
            916485050,
            916485228,
            916485210,
            916485202,
            916485365,
            916485405,
            916485404,
            916485403,
            916485406,
            916486212,
            916486237,
            916486260,
            916486261,
            916486262,
            916486446,
            916486488,
            916487277,
            916487278,
            916487501,
            916487502,
            916487503,
            916487504,
            916487655,
            916486302,
            916487703,
            916488087,
            916488560,
            916488554,
            916487742,
            916488243,
            916488018,
            916521652,
            916521653,
            916475160,
            916475163,
            916475551,
            916475550,
            916475549,
            916477767,
            916478918,
            916478919,
            916479243,
            916479270,
            916479926,
            916479936,
            916479939,
            916480822,
            916480827,
            916480829,
            916480830,
            916480831,
            916481602,
            916481609,
            916481693,
            916482313,
            916482318,
            916483426,
            916483425,
            916483427,
            916483564,
            916483566,
            916483565,
            916484805,
            916484806,
            916486292,
            916486293,
            916486297,
            916486765,
            916486780,
            916486779,
            916487156,
            916487157,
            916487922,
            916487923,
            916487926,
            916487934,
            916488482,
            916488574,
            916488575
        ];
        //$skus =[266001,322185];
        $count = count($skus);
        for($i=0;$i<$count;$i++){
            $sku = $skus[$i];
            $list = Db::connect("db_mongo")->name("dx_product")
                    ->where(['ProductStatus'=>1,'Skus._id'=>$sku])
                    ->field('Skus')
                    ->find();
            if($list){
                if(isset($list['Skus'])){
                    foreach($list['Skus'] as $key => $value){
                        if($value['_id'] == $sku){
                            //$value['Inventory'] = 0;
                            $result = Db::connect("db_mongo")->name("dx_product")
                                        ->where(['ProductStatus'=>1,'Skus._id'=>$sku])
                                        ->update(['Skus.'.$key.'.Inventory'=>0]);
                            echo '执行'.($result?'成功':'失败').'<br>';
                        }
                    }
                }
            }else{
                echo 'sku:'.$sku.'无数据<br>';
            }
        }
        echo 'end --------------<br>';
    }


    /**
     * 销售属性颜色
     * [attribute_color description]
     * @return [type] [description]
     */
    public function attribute_color($limit_1 = 0,$limit_2 = 100){
       if($limit_1 == 0){
             $limit_1 = input('limit_1');
             if(!$limit_1){
                 $limit_1 = 0;
             }
       }

       ini_set('max_execution_time', '0');

       $limit = $limit_1.','.$limit_2;
       // file_put_contents ('limiti.log',$limit.';', FILE_APPEND|LOCK_EX);
       $list_mongo =  Db::connect("db_mongo")->name("dx_brand_attribute")
                    ->field('attribute')
                    ->limit($limit)
                    ->select();
        // echo  Db::connect("db_mongo")->name("brand_attribute")->getLastSql();
        //file_put_contents ('mysql.log',Db::connect("db_mongo")->name("brand_attribute")->getLastSql().';', FILE_APPEND|LOCK_EX);

       //  file_put_contents ('limit.log',$limit_1.';', FILE_APPEND|LOCK_EX);
       if(!$list_mongo){
          exit;
       }
       file_put_contents ('limit.log',$limit_1);
     // echo  Db::connect("db_mongo")->name("brand_attribute")->getLastSql();
       foreach ($list_mongo as $key => $value) {
           if($value["attribute"]){
                foreach ($value["attribute"] as $k => $v) {
                   if($v["is_color"] == 1){
                      foreach ($v["attribute_value"] as $ke => $ve) {
                           if($ve['title_en']){
                                 $attribute_color =  DB::name('attribute_color')->where(['title_en'=>$ve['title_en']])->find();
                                 $list_mongo[$key]["attribute"][$k]["attribute_value"][$ke]['value'] = $attribute_color['color_value'];
                           }
                      }
                   }
                }
                $result = Db::connect("db_mongo")->name("dx_brand_attribute")
                          ->where(['_id'=>(int)$value['_id']])
                          ->update(['attribute'=>(Object)$list_mongo[$key]["attribute"]]);
                if(!$result){
                    file_put_contents ('class_id_shibai.log',$value['_id'].';', FILE_APPEND|LOCK_EX);
                    echo $value['_id'].'失败';
                }else{
                    file_put_contents ('class_id_chenggong.log',$value['_id'].';', FILE_APPEND|LOCK_EX);
                    echo $value['_id'].'成功';
                }
           }

       }
       $limit_1 = $limit_1 + 100;
       $this->attribute_color($limit_1,$limit_2);
    }

     /**
     * 零时使用  找出sku  与  图片不一致的sku
     * [sku description]
     * @return [type] [description]
     */
    public function sku($page_size = 0,$ProductStatus = 1){
        ini_set('max_execution_time', '0');
        try{

                    file_put_contents ('page_size.log',$page_size);
                    $map['ProductStatus'] = (int)$ProductStatus;
                    $list = Db::connect("db_mongo")->name("dx_product")
                                ->where($map)
                                ->field('Skus,ImageSet')
                                ->limit($page_size,10)
                                ->select();
                    //  echo Db::connect("db_mongo")->name("dx_product")->getLastSql();
                    // dump($list);
                    if(!$list){
                          exit;
                    }
                    foreach ($list as $k => $v) {
                            if(isset($v["Skus"][0]["Code"]) && isset($v["ImageSet"]["ProductImg"][0])){
                                $sku = trim($v["Skus"][0]["Code"]);
                                $preg= '%_(.*?)_%si';
                                preg_match_all($preg,$v["ImageSet"]["ProductImg"][0],$res);
                                $imgSku = trim($res[1][0]);
                                if($sku !=$imgSku){
                                   file_put_contents ('sku.log',$imgSku.',', FILE_APPEND|LOCK_EX);
                                }else{
                                   file_put_contents ('xiangdeng.log',$imgSku.',', FILE_APPEND|LOCK_EX);
                                }
                            }else{
                                file_put_contents ('spu.log',$v['_id'].',', FILE_APPEND|LOCK_EX);
                                file_put_contents ('skuImg.log',$sku.',', FILE_APPEND|LOCK_EX);
                            }
                    }
                    // $serverName = $_SERVER['SERVER_NAME'];

            $page_size = $page_size + 10;
            $list = array();
// $result = call_user_func_array('$this->sku', $params);dump($result);

            // return curl_request($serverName.'/Tool/sku',$where);
        }catch(\Exception $e){

           echo $e->getMessage();

        }
    }
    public function aaa(){
        $page_size = 0;
        while (true) {
            $this->sku($page_size,$ProductStatus = 1);
            $page_size = $page_size + 20;
        }

    }
     public function bbb(){
        $page_size = 0;
        while (true) {
            $this->sku($page_size,$ProductStatus = 5);
            $page_size = $page_size + 20;
        }

    }
    /**
     * 导出国家分类语言
     * [NationalLanguage description]
     */
    public function NationalLanguage(){
        $result = Db::connect("db_mongo")->name("dx_product_class")->where(['type'=>1])->field('id,erp_id,title_en,title_cn,Common')->select();
        $temp = array();
        if($result){
            foreach ($result as $key => $value) {
              $temp[] = ['id'=>$value['id'],'erp_id'=>$value['erp_id'],
                       'title_en'=>$value['title_en'],'title_cn'=>$value['title_cn'],'cs'=>$value['Common']['cs'],
                       'de'=>$value['Common']['de'],'en'=>$value['Common']['en'],
                       'es'=>$value['Common']['es'],'fr'=>$value['Common']['fr'],
                       'nl'=>$value['Common']['nl'],'pt'=>$value['Common']['pt'],
                       'ru'=>$value['Common']['ru'],'it'=>$value['Common']['it'],
                       'sv'=>$value['Common']['sv'],'fi'=>$value['Common']['fi'],
                       'no'=>$value['Common']['no'],'ja'=>$value['Common']['ja'],
                       'ar'=>$value['Common']['ar']
             ];
            }
            $header_data =['id'=>'id','erp_id'=>'erp_id',
                       'title_en'=>'title_en','title_cn'=>'title_cn','cs'=>'cs',
                       'de'=>'de','en'=>'en',
                       'es'=>'es','fr'=>'fr',
                       'nl'=>'nl','pt'=>'pt',
                       'ru'=>'ru','it'=>'it',
                       'sv'=>'sv','fi'=>'fi',
                       'no'=>'no','ja'=>'ja',
                       'ar'=>'ar'
             ];
            $tool = new ExcelTool();
            $result = $tool ->export('NationalLanguage'.date('Y-m-d'),$header_data,$temp,'sheet1');
        }
    }
    /**
     * 过滤出没有销售属性的分类
     * [class_filter description]
     * @return [type] [description]
     */
    public function class_filter(){
        $list_calss =  Db::connect("db_mongo")->name("dx_product_class")
                    ->where(['isleaf'=>1,'type'=>1])
                    ->field('id')
                    ->select();
        foreach ($list_calss as $key => $value) {
            $attribute =  Db::connect("db_mongo")->name("dx_brand_attribute")
                    ->where(['_id'=>$value['id']])
                    ->field('attribute')
                    ->find();
            if(empty($attribute['attribute']) && !isset($attribute['attribute'])){
                $temp[] = ['id'=>$value['id']];
            }
        }
        $header_data =['id'=>'id'];
        $tool = new ExcelTool();
        $result = $tool ->export('class_filter'.date('Y-m-d'),$header_data,$temp,'sheet1');

    }
    /**
     * 分类禁用
     * [class_disable description]
     * @return [type] [description]
     */
    public function class_disable(){
       $tool = new ExcelTool();
       $objPHPExcel = $tool->load('uploads/DX_class_ID.xlsx');
       $sheet = $objPHPExcel->getSheet(0);
       $highestRow = $sheet->getHighestRow(); //取得总行数
       $highestColumn = $sheet->getHighestColumn();// 取得总列数
       //对获取的数据，重新组装其结构
       for($j=1;$j<=$highestRow;$j++) {
              $forRusultMsg = ''; //for 内部循环用的字符串，返回给用户提示用的
              $str = '';
              for ($k = 'A'; $k == $highestColumn; $k++) {//echo 11;
                  $str .= $objPHPExcel->getActiveSheet()->getCell("$k$j")->getValue() . '\\';//读取单元格
              }
              $strs = explode("\\", $str);
              if($strs[0]){
                   $result =  Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$strs[0]])->update(['status' =>0]);
                   echo $result.'-'.$strs[0].';';
              }
       }
    }
    /**
     * 补分类末级状态mongodb
     * [isleaf_update description]
     * @return [type] [description]
     */
    public function isleaf_update(){
        $strat = 0;
        $sum = 500;
        while (true) {
           $result =  Db::connect("db_mongo")->name("dx_product_class")->field('id,isleaf')->limit($strat,$sum)->select();
           if($result){
                foreach ($result as $key => $value) {
                    $class_id =  Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>(int)$value['id']])->field('id')->find();
                    if(!$class_id){
                        if($value['isleaf'] != 1 ){
                           $update_result = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$value['id']])->update(['isleaf'=>1]);
                           if($update_result){
                               echo '分类ID'.$value['id'].'末级修改成功---';
                           }else{
                               echo '分类ID'.$value['id'].'末级修改失败---';
                           }
                        }
                    }else if($value['isleaf'] != 0){
                        $update_result = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$value['id']])->update(['isleaf'=>0]);
                        if($update_result){
                           echo '分类ID'.$value['id'].'不是末级修改成功---';
                        }else{
                           echo '分类ID'.$value['id'].'不是末级修改失败---';
                        }
                    }
                }
                $strat = $strat + 500;
           }else{
                exit;
           }
        }

    }
    /**
     * 补分类末级状态mysql
     * [isleaf_update description]
     * @return [type] [description]
     */
    public function isleaf_update_mysql(){
        $strat = 0;
        $sum = 500;
        while (true) {
           $result =  DB::name(PRODUCT_CLASS)->field('id,isleaf')->limit($strat,$sum)->select();
           // $result =  Db::connect("db_mongo")->name("dx_product_class")->field('id,isleaf')->limit($strat,$sum)->select();
           if($result){
                foreach ($result as $key => $value) {
                    $class_id =  DB::name(PRODUCT_CLASS)->where(['pid'=>$value['id']])->field('id')->find();
                    // $class_id =  Db::connect("db_mongo")->name("dx_product_class")->where(['pid'=>(int)$value['id']])->field('id')->find();
                    if(!$class_id){
                        if($value['isleaf'] != 1 ){
                           //$update_result = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$value['id']])->update(['isleaf'=>1]);
                           $update_result =  DB::name(PRODUCT_CLASS)->where(['id'=>$value['id']])->update(['isleaf'=>1]);
                           if($update_result){
                               echo '分类ID'.$value['id'].'末级修改成功---';
                           }else{
                               echo '分类ID'.$value['id'].'末级修改失败---';
                           }
                        }
                    }else if($value['isleaf'] != 0){
                        //$update_result = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$value['id']])->update(['isleaf'=>0]);
                        $update_result =  DB::name(PRODUCT_CLASS)->where(['id'=>$value['id']])->update(['isleaf'=>0]);
                        if($update_result){
                           echo '分类ID'.$value['id'].'不是末级修改成功---';
                        }else{
                           echo '分类ID'.$value['id'].'不是末级修改失败---';
                        }
                    }
                }
                $strat = $strat + 500;
           }else{
                exit;
           }
        }
    }
    /**
     * 销售属性反插入
     * [attribute_insert description]
     * @return [type] [description]
     */
    public function attribute_insert(){
         $start  = 0;
         $number = 200;
         $data = array();
         ini_set('max_execution_time', '0');
         ignore_user_abort();
         while (true) {

             $class_data =  Db::connect("db_mongo")->name(PRODUCT_CLASS_MG)->where(['type'=>1])->limit($start,$number)->field('id')->select();
             // dump($class_data);exit;
             if(!$class_data){
                 exit;
             }
             foreach ($class_data as $kclass => $vclass) {
                     $classattribute =  Db::connect("db_mongo")->name(MOGOMODB_B_A)->where(['_id'=>(int)$vclass["id"]])->field('attribute')->select();
                     if(!$classattribute){
                         continue;
                         // exit;
                     }
                     foreach ((array)$classattribute as $ke => $ve) {
                        foreach ((array)$ve["attribute"] as $k => $v) {
                            $attribute = array();
                            $attribute_cn = array();
                            $attribute_en = array();

                            if(empty($v["id"])){
                                 continue;
                            }
                            usleep(10000);
                            $where['_id']    = (int)$v["id"];
                            $where['title_cn']    =  trim((string)$v["title_cn"]);
                            $where['title_en']    =  trim((string)$v["title_en"]);
                            $attribute =  Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->whereOr( $where )->select();
                            // $attribute =  Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->where(['_id'=>(int)$v["id"]])->find();
                            // $attribute_cn =  Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->where( ['title_cn'=>$v["title_cn"]] )->find();
                            // $attribute_en =  Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->where( ['title_en'=>$v["title_en"]] )->find();
                             // echo Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->getlastsql();exit;
                            if(!$attribute){
                                 // dump($attribute);
                                 foreach ($v["attribute_value"] as $key => $value) {
                                     if(strpos($value['value'],'/upload/images')){
                                         unset($v["attribute_value"][$key]['value']);
                                     }
                                 }
                                 $data['_id']             = (int)$v["id"];
                                 $data['title_cn']        = $v["title_cn"];
                                 $data['title_en']        = $v["title_en"];
                                 $data['show_type']       = $v["show_type"];
                                 $data['input_type']      = $v["input_type"];
                                 $data['customized_name'] = $v["customized_name"];
                                 $data['customized_pic']  = $v["customized_pic"];
                                 $data['is_color']        = $v["is_color"];
                                 $data['attribute']       = $v["attribute_value"];
                                 $data['sort']            = $v["sort"];
                                 $data['status']          = $v["status"];
                                 $data['add_time']         = time();
                                 $data_histroy['EntityId']= (int)$v["id"];
                                 $data_histroy['IsSync']  = false;
                                 $data_histroy['CreatedDateTime']  = time();
                                 $data_histroy['Note']    = '由分类绑定属性表反插入';

                                 $result = Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->insert($data);
                                 if(!$result){
                                     echo $v["id"].'失败;';
                                 }else{
                                     $result_histroy = Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE_HISTORY)->insert($data_histroy);
                                     if(!$result_histroy){
                                          echo $v["id"].'历史数据失败;';
                                     }
                                 }
                            }
                        }
                     }
             }


             $start = $start + 200;
         }
    }


    /**
     * 导出导出条件分类
     */
    public function exportAllClassDatadaochu(){
        //获取类别数据
        $class_data = Db::name(PRODUCT_CLASS)
                     ->where('status',1)
                     ->where('type',1)
                     ->where('isleaf',1)
                     ->field('id,pid,title_en,title_cn')
                     // ->limit(10)
                     ->select();
        if($class_data){
             foreach ($class_data as $key => $value) {
                   $Father_level = $this->class_Father_level($value['pid']);
                   $html = '';
                   if($Father_level){
                          foreach ($Father_level as $k => $v) {
                             $html .= $v['title_cn'].'->';
                          }
                          $html .= $value["title_cn"];
                          $class_data[$key]['class_html'] = rtrim($html,'->');
                   }
                   $temp[] = ['id'=>$value['id'],'class_html'=>$class_data[$key]['class_html'],
                       'title_en'=>$value['title_en'],'title_cn'=>$value['title_cn'],
                       'HSCode'=>$value['HSCode'],'declare_en'=>$value['declare_en']

                   ];
             }

        }
         $header_data =['id'=>'类目ID','class_html'=>'类目树',
                   'HSCode'=>'编码','declare_en'=>'英文海关品名'

         ];
         $tool = new ExcelTool();
         $result = $tool ->export('class_name'.date('Y-m-d'),$header_data,$temp,'sheet1');



    }
     /**
     * 获取上一级分类id
     * [class_Father_level description]
     * @return [type] [description]
     */
    public function class_Father_level($class_id,$data=array()){
         $class_name = Db::name(PRODUCT_CLASS)->where(['id'=>$class_id,])->field('title_en,id,pid,title_cn')->find();
        // return  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->getLastSql();
        //  return $class_name;
         if($class_name){
              $data[] = array(
                 'id'=>$class_name['id'],
                 'title_en'=>$class_name['title_en'],
                 'title_cn'=>$class_name['title_cn'],
              );
              return  $this->class_Father_level($class_name['pid'],$data);
         }else{
              return $data;
         }

    }
    /**
     * 分类设置组装插入
     * [Taxonomy description]
     */
    public function Taxonomy(){
        $data = array();
        $daohang = Db::name('daohang')->select();
//        dump(json_decode('{"1":"\u0645\u0644\u0627\u0628\u0633","26":"\u0633\u0627\u0639\u0627\u062a"}',true));exit;
        foreach ($daohang as $k => $v) {//dump($v["cate_id_name"]);exit;
            $data = array();
              // dump(json_encode($v["cate_id_name"]) );dump(json_decode(json_encode($v["cate_id_name"])));
            $cate_id_name = json_decode($v["cate_id_name"],true);//dump($cate_id_name);exit;
            foreach ((array)$cate_id_name as $ke => $va) {
//                 dump($va);exit;
                 if($data["classId"]){
                     $data["classId"] .= ','.$ke;
                 }else{
                     $data["classId"] = $ke;
                 }
                 if($data["className"]){
                    $data["className"] .= $v["fenge"].$va;
                    $data["classNameHtml"] .= $v["fenge"].'<a class="menu-title" href=/c/Watches-'.$ke.'>'.$va.'</a>';
                 }else{//dump($va);exit;
                    $data["className"] = $va;
                    $data["classNameHtml"] = '<a class="menu-title" href=/c/Watches-'.$ke.'>'.$va.'</a>';
                     // <a class="menu-title" href=/c/Watches-26>23</a>&<a class="menu-title" href=/c/Weddings-Events-27>232</a>
                 }
              }
              $data["language"] = $v["lang"];
              $data["sort"]     = (int)$v["rank_id"];
              $data["content"]  = $v["display_model"];
              $data["content_right"] = $v["right_display"]?$v["right_display"]:'';
              $data["status"]   = 1;
              $data["character"]  = $v["fenge"];
              $data["add_person"] = '组装插入';
              $data["add_time"] = time();
              $data["classIconfont"] = $v["type1"];
              $language = Db::connect("db_mongo")->name("dx_integration_class")->where(['language'=>$data["language"],'classId'=>$data["classId"],'status'=>1])->field('language')->find();
              if(!$language){
                $result   =  Db::connect("db_mongo")->name("dx_integration_class")->insert($data);//存mongodb
                if($result){
                    echo '成功'.$data["classId"].'----'.$data["language"];
                   // echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
                   // exit;
                }else{
                    echo '失败'.$data["classId"].'----'.$data["language"];
                   // echo json_encode(array('code'=>100,'result'=>'数据提交失败'));
                   // exit;
                }
              }else{
                  echo '已存在'.$data["classId"].'----'.$data["language"];
                  //已存在
                  // echo json_encode(array('code'=>100,'result'=>'该数据已添加过'));
                  // exit;
              }


        }
    }
    /**
     *
     * [file_load description]
     * @return [type] [description]
     */
    public function file_load(){
         echo copy("uploads/DX_class_ID.xlsx","uploads/orderfile/DX_class_ID.xlsx");
         // $objPHPExcel = $tool->load('uploads/DX_class_ID.xlsx');
         // dump($objPHPExcel);
    }
    /**
     * 添加海关编码
     * [ClassCustomsCode description]
     */
    public function ClassCustomsCode(){
          $tool = new ExcelTool();
          $objPHPExcel = $tool->load("./uploads/class_name2018-10-12.xls");
          $sheet = $objPHPExcel->getSheet(0);
          $highestRow = $sheet->getHighestRow(); //取得总行数
          $highestColumn = $sheet->getHighestColumn();// 取得总列数
          $notAttrDXid = array();
          $highestColumn++;
          //对获取的数据，重新组装其结构
          for($j=2;$j<=$highestRow;$j++) {
              $forRusultMsg = ''; //for 内部循环用的字符串，返回给用户提示用的
              $str = '';
              for ($k = 'A'; $k != $highestColumn; $k++) {
                  $str .= $objPHPExcel->getActiveSheet()->getCell("$k$j")->getValue() . '\\';//读取单元格
              }
              $strs = explode("\\", $str);
              if(!empty($strs[0]) && !empty($strs[2])){
                  $where = array();
                  $where['HSCode'] = $strs[2];
                  if(!empty($strs[3])){
                      $where['declare_en'] = $strs[3];
                  }
                  $list = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$strs[0]])->field('pdc_ids,declare_en,HSCode')->find();
                  if($list){
                         if($list['HSCode'] !=$where['HSCode']){
                            $result = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$strs[0]])->update($where);
                         }else if(!empty($where['declare_en']) && $where['declare_en'] !=$list['declare_en']){
                            $result = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$strs[0]])->update($where);
                         }
                         if($result){
                            echo 'ERP分类成功：'.$strs[0].';';
                            file_put_contents ('../runtime/log/ERP_success.txt',$strs[0].',', FILE_APPEND|LOCK_EX);
                         }else{
                            echo 'ERP分类失败：'.$strs[0].';';
                            file_put_contents ('../runtime/log/ERP_error.txt',$strs[0].',', FILE_APPEND|LOCK_EX);
                         }
                         //判断映射分类id是否存在
                         if(!empty($list['pdc_ids'])){
                             foreach ($list['pdc_ids'] as $k => $v) {
                                 $list_pdc = array();
                                 $list_pdc = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$v])->field('pdc_ids,declare_en,HSCode')->find();
                                 if($list_pdc){
                                    if($list_pdc['HSCode'] !=$where['HSCode']){
                                        $result_pdc = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$v])->update($where);
                                     }else if(!empty($where['declare_en']) && $where['declare_en'] != $list_pdc['declare_en']){
                                        $result_pdc = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$v])->update($where);
                                     }
                                     if($result_pdc){
                                        echo 'PDC分类成功：'.$v.';';
                                        file_put_contents ('../runtime/log/PDC_success.txt',$v.',', FILE_APPEND|LOCK_EX);
                                     }else{
                                        echo 'PDC分类失败：'.$v.';';
                                        file_put_contents ('../runtime/log/PDC_error.txt',$v.',', FILE_APPEND|LOCK_EX);
                                     }
                                 }
                             }
                         }
                  }
              }

          }
    }
     /**
     * 产品添加海关编码
     * [ClassCustomsCode description]
     */
    public function ProductHSCode(){
         //DeclarationName
          ignore_user_abort();
          set_time_limit(0);
          // ini_set('max_execution_time', '0');
          $tool = new ExcelTool();
          $objPHPExcel = $tool->load("./uploads/class_name2018-10-12.xls");
          $sheet = $objPHPExcel->getSheet(0);
          $highestRow = $sheet->getHighestRow(); //取得总行数
          $highestColumn = $sheet->getHighestColumn();// 取得总列数
          $notAttrDXid = array();
          $highestColumn++;
          //对获取的数据，重新组装其结构
          // for($j=2;$j<=$highestRow;$j++) {
          $data = input();
          $number = '';
          $end = '';

          if(!empty($data['number'])){
                $number = $data['number'];
          }else{
                return;
          }
          if(!empty($data['end'])){
               $end =$data['end'];
          }
          // for($j=2;$j<=$highestRow;$j++) {

          for($j=$number;$j<=$end;$j++) {
              $str = '';
              for ($k = 'A'; $k != $highestColumn; $k++) {
                  $str .= $objPHPExcel->getActiveSheet()->getCell("$k$j")->getValue() . '\\';//读取单元格
              }
              $strs = explode("\\", $str);
              if(!empty($strs[0]) && !empty($strs[2])){
                  $this->productHSCodeEdit($strs);
              }
          }
    }
    public function productHSCodeEdit($strs=array()){
        ignore_user_abort();
        set_time_limit(0);
        $data_array = array();
        // ini_set('max_execution_time', '0');
        if(!empty($strs)){
             file_put_contents ('../runtime/log/product_class.txt',$strs[0].',', FILE_APPEND|LOCK_EX);
             $data_array[] = $list_ERP = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$strs[0]])->field('level,pdc_ids')->find();
             if(!empty($list_ERP['pdc_ids'])){
                 foreach ($list_ERP['pdc_ids'] as $k => $v) {
                     $list_pdc = Db::connect("db_mongo")->name("dx_product_class")->where(['id'=>(int)$v])->field('level,pdc_ids')->find();
                     if($list_pdc){
                        $data_array[] = $list_pdc;
                     }
                 }
             }

             foreach ($data_array as $ke => $va) {
                   if($va){
                            $data=array();
                            if($va['level'] ==5){
                                 $data['FifthCategory'] = (int)$strs[0];
                            }else if($va['level'] ==4){
                                 $data['FourthCategory'] = (int)$strs[0];
                            }else if($va['level'] ==3){
                                 $data['ThirdCategory'] = (int)$strs[0];
                            }else if($va['level'] ==2){
                                 $data['SecondCategory'] = (int)$strs[0];
                            }else if($va['level'] ==1){
                                 $data['FirstCategory'] = (int)$strs[0];
                            }else{
                                return;
                            }
                            $where = array();
                            $where['HSCode'] = $strs[2];
                            if(!empty($strs[3])){
                                $where['DeclarationName'] = $strs[3];
                            }
                            $page = 1;
                            $page_size = 10;
                            while(true){
                                $product_list = Db::connect("db_mongo")->name("dx_product")->where($data)
                                                            ->field('HSCode,DeclarationName,CategoryPath,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,FifthCategory')
                                                            ->paginate($page_size,false,[
                                                                'type' => 'Bootstrap',
                                                                'page' => $page,
                                                               ]);
                                $list = $product_list->items();//echo Db::connect("db_mongo")->name("dx_product")->getLastSql();exit;
                                if($list){
                                     foreach ($list as $key => $value) {
                                         //再有子集分类的情况下，同时修改子集分类下的所有产品
                                         $this->productHSCodePdcClass($where,$value,$va['level'],$strs);
                                         $product_result = array();
                                         if($value["HSCode"]!=$strs[2]){
                                             $product_result = Db::connect("db_mongo")->name("dx_product")->where(['_id'=>(int)$value['_id']])->update($where);
                                         }else if(!empty($where['DeclarationName']) && $where['DeclarationName'] != $value['DeclarationName']){
                                             $product_result = Db::connect("db_mongo")->name("dx_product")->where(['_id'=>(int)$value['_id']])->update($where);
                                         }
                                         if($product_result){
                                              echo 'spu_success:'.$value['_id'].';';
                                              file_put_contents ('../runtime/log/product_spu_success.txt',$value['_id'].',', FILE_APPEND|LOCK_EX);
                                         }else{
                                              echo 'spu_error:'.$value['_id'].';';
                                              file_put_contents ('../runtime/log/product_spu_error.txt',$value['_id'].',', FILE_APPEND|LOCK_EX);
                                         }
                                     }
                                }else{
                                    break;
                                }
                                $page++;
                            }
                         }
                    }
             }
             return;
    }
    /**
     * 非末级的子集分类产品，更新海关编码
     * [productHSCodePdcEdit description]
     * @param  array  $strs [description]
     * @return [type]       [description]
     */
    public function productHSCodePdcClass($where=array(),$value=array(),$level='',$strs=array()){
            $CategoryPath = array();
            $data = array();
            if(!empty($where) && !empty($value) && !empty($strs) && $level !=''){
                if(!empty($value['CategoryPath'])){
                       return;
                }
                $CategoryPath = implode("-",$value['CategoryPath']);
                foreach ($CategoryPath as $k => $v) {
                    if($k > $level){
                        if($k ==5 && !empty($v)){
                             $data['FifthCategory']  = (int)$v;
                             $this->productHSCodePdcEdit($strs,$data,$where);
                        }else if($k ==4){
                             $data['FourthCategory'] = (int)$v;
                             $this->productHSCodePdcEdit($strs,$data,$where);
                        }else if($k ==3){
                             $data['ThirdCategory']  = (int)$v;
                             $this->productHSCodePdcEdit($strs,$data,$where);
                        }else if($k ==2){
                             $data['SecondCategory'] = (int)$v;
                             $this->productHSCodePdcEdit($strs,$data,$where);
                        }else if($k ==1){
                             $data['FirstCategory']  = (int)$v;
                             $this->productHSCodePdcEdit($strs,$data,$where);
                        }
                    }
                }
            }else{
                return;
            }
            return;
    }
    /**
     * 产品修改
     * [productHSCodePdcEdit description]
     * @return [type] [description]
     */
    public function productHSCodePdcEdit($strs,$data,$where){
          if(!empty($strs) && !empty($data) && !empty($where)){
                $page_size = 10;
                $page = 1;
                while(true){
                       $product_list = Db::connect("db_mongo")->name("dx_product")
                                            ->where($data)
                                            ->field('HSCode,DeclarationName,CategoryPath,FirstCategory,SecondCategory,ThirdCategory,FourthCategory,FifthCategory')
                                            ->paginate($page_size,false,[
                                                'type' => 'Bootstrap',
                                                'page' => $page,
                                               ]);
                        $list = $product_list->items();
                        if($list){
                             foreach ($list as $key => $value) {
                                 //再有子集分类的情况下，同时修改子集分类下的所有产品
                                 // $this->productHSCodePdcClass($where,$value,$va['level']);
                                 $product_result = array();
                                 if($value["HSCode"]!=$strs[2]){
                                     $product_result = Db::connect("db_mongo")->name("dx_product")->where(['_id'=>(int)$value['_id']])->update($where);
                                 }else if(!empty($where['DeclarationName']) && $where['DeclarationName'] != $value['DeclarationName']){
                                     $product_result = Db::connect("db_mongo")->name("dx_product")->where(['_id'=>(int)$value['_id']])->update($where);
                                 }
                                 if($product_result){
                                      echo 'spu_success:'.$value['_id'].';';
                                      file_put_contents ('../runtime/log/product_spu_success.txt',$value['_id'].',', FILE_APPEND|LOCK_EX);
                                 }else{
                                      echo 'spu_error:'.$value['_id'].';';
                                      file_put_contents ('../runtime/log/product_spu_error.txt',$value['_id'].',', FILE_APPEND|LOCK_EX);
                                 }
                             }
                        }else{
                            break;
                        }
                        $page++;
                }
          }
          return;
    }
    /**
     * 商城业务数据配置  修复出错的时间
     * [DataConfig description]
     */
    public function DataConfig(){
         $list = Db::connect("db_mongo")->table('dx_data_config')->select();
         $startTime = strtotime("2018-03-27 06:45:46");
         $time = 1539660750;
         foreach ($list as $key => $value) {
            if($value['addTime'] < $startTime){
                $result = Db::connect("db_mongo")->table('dx_data_config')->where(['_id'=>$value['_id']])->update(['addTime'=> (int)$time]);
                if($result){
                   echo 'id为'.$value['_id'].'时间修改成功;';
                }else{
                   echo 'id为'.$value['_id'].'时间修改失败;';
                }
            }
         }
    }
    public function redis_qingchu(){
      echo redis()->del(REDIS_REVIEW_FILTERING);
    }

    public function qqqqqqq(){
        $class_id[] = 1799086;
        // $class_id[] = 1799085;
        // $class_id[] = 110;
        // $class_name  =  Db::connect("db_mongo")->name('dx_product_class')->where(['id'=>['in',$class_id]])->field('title_en,HSCode')->limit(2)->select();
        $key = 1;
        $class_name  =  Db::connect("db_mongo")->where(['id'=>['in',$class_id]])->field('title_en,HSCode')->name('dx_product_class')->select();
        $sum = count($class_name);
         if(empty($list[$key]['HSCode']) && $sum > 0){
               $LastStage      = $sum - 1;
               $InvertedSecond = $sum - 2;
               $InvertedThird  = $sum - 3;
               $InvertedFourth = $sum - 4;dump($InvertedFourth);
               if($LastStage >= 0 && !empty($class_name[$LastStage]['HSCode'])){
                  $list[$key]['HSCode'] = $class_name[$LastStage]['HSCode'];
               }else if($InvertedSecond >= 0 && !empty($class_name[$InvertedSecond]['HSCode'])){
                  $list[$key]['HSCode'] = $class_name[$InvertedSecond]['HSCode'];
               }else if($InvertedThird  >= 0 && !empty($class_name[$InvertedThird]['HSCode'])){
                  $list[$key]['HSCode'] = $class_name[$InvertedThird]['HSCode'];
               }else if($InvertedFourth >= 0 && !empty($class_name[$InvertedFourth]['HSCode'])){
                  $list[$key]['HSCode'] = $class_name[$InvertedFourth]['HSCode'];
               }
           }
           dump($list);
        echo Db::connect("db_mongo")->name("product_class")->getLastSql();

        echo $sum;
        dump($class_name);
    }
    public function sdsdsd(){
        $list = Db::connect("db_mongo")->name("dx_product")->where(['_id'=>852255819])->field('BrandId,DeclarationName')->find();
        dump($list);
    }

}