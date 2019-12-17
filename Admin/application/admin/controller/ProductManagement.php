<?php
namespace app\admin\controller;
use app\admin\dxcommon\FTPUpload;
use \think\Session;
use think\View;
use think\Controller;
use think\Paginator;
use think\Db;
use app\admin\dxcommon\BaseApi;
use app\admin\model\Businessmanagement as Business;
use app\admin\dxcommon\ExcelTool;

class ProductManagement  extends Action
{
     const PRODUCT_STATUS_DOWN = 4;  //已下架
     public function __construct(){
       Action::__construct();
       // $this->ProductClass               = Db('ProductClass');
       $this->ClassAttribute             = Db('ProductClassAttribute');
       $this->ProductClassAttributeValue = Db('ProductClassAttributeValue');
       // define('P_CLASS', 'ProductClass');
       define('MOGOMODB_ATTRIBUTE', 'dx_attribute');

       define('MOGOMODB_P_CLASS', 'dx_product_class'); //线上数据表，上线时需要修改为“dx_product_class” TODO
       define('MOGOMODB_B_A_LIST', 'dx_brand_attribute');
       define('P_C_A', 'ProductClassAttribute');
       define('P_C_A_V', 'ProductClassAttributeValue');
       define('BRANDS', 'dx_brands');//mongodb品牌表
       // define('S_CONFIG', 'dx_sys_config');//mongodb数据表
       define('PRODUCT', 'dx_product');//mongodb数据表产品表

         //类别变更历史表
       define('MOGOMODB_P_CLASS_HISTORY', 'dx_product_class_histories');
       //产品历史记录表
       define('MOGOMODB_P_HISTORIES', 'dx_product_histories');
       define('PRODUCT_OPERATION_LOG', 'product_operation_log');
       // $this->Menu_logo();

    }
    /**分类树状图主页
     * [index description]
     * @return [type] [description]
     */
    public function index()
    {
       // $class_data = Db('ProductClass')->where(['pid'=>0])->select();//mysql数据表
       $class_data = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>0,'type'=>1])->order('id','asc')->select();//mongodb数据表
       $this->assign(['class_data'=>$class_data]);
       return $this->fetch("index");
    }

    public function class_name(){
      $classid = request()->post();
      $html = '';
      if(!empty($classid['id'])){
         $list = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$classid['id'],'type'=>1])->select();
          // $list = Db('ProductClass')->where(['pid'=>$classid['id']])->select();
          //dump($list);
         if($list){
          // $html .= '<ul>';
          foreach ($list as $key => $value) {
              $icon = isset($value['icon']) ? $value['icon'] : '';
              $class_table = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$value['id'],'type'=>1])->select();//dump($class_table);
             // $class_table = Db('ProductClass')->where(['pid'=>$value['id']])->select();//dump($class_table);
             if($class_table){
               $html .= '<li>';
                 $html .= '<div onclick="classid('.$value["id"].')" class="hitarea closed-hitarea collapsable-hitarea expandable-hitarea  hitarea'
                           .$value["id"].'"></div>';
                 $html .= '<span  onclick="classid('.$value["id"].')"  data-id ="'.$value['id'].
                   '" data-level ="'.$value['level'].'" title-cn ="'.$value["title_cn"].'" data-declare-en ="'.$value["declare_en"].
                   '" title-en = "'.$value["title_en"].'" parent-id = "'.$classid['id'].'" data-isleaf ="'.$value["isleaf"].'" data-icon ="'.$icon.
                   '" sort = "'.$value["sort"].'" status = "'.$value["status"].'" HSCode = "'.
                   $value["HSCode"].'"  class="cursor-pointer folder classid'.$value['id'].'">'.
                   $value['id'].' '.$value['title_cn'].'['.$value['title_en'].']'.
                   '</span><input name="sort" class="form-inline w40 ml5 sort" data-id = "'.$value["id"].'" value="'.$value['sort'].'" type="text">';
                 $html .= '<input name="HSCode" class="form-inline w90 ml5" value="'.$value['HSCode'].'" type="text">';
                 $html .= '<b class="pd2 btn btn-orange mr5 ml5"   onclick="edit('.$value['id'].')" > 修改</b>';
                 $html .= '<b onclick="add_class('.$value['id'].')" class="pd2 btn btn-qing"> 新增</b>';
                 $html .= '<ul class="class'.$value["id"].'"></ul>';
                 $html .= '</li>';
             }else{
               $html .= '<li><span class="folder classid'.$value['id'].
                   '"  data-id ="'.$value['id'].'" data-level ="'.
                   $value['level'].'" title-cn ="'.$value["title_cn"].
                   '" data-declare-en ="'.$value["declare_en"].
                   '" data-isleaf ="'.$value["isleaf"].
                   '" data-icon ="'.$icon.
                   '" title-en = "'.$value["title_en"].'" parent-id = "'.
                   $classid['id'].'" sort = "'.$value["sort"].'" status = "'.
                   $value["status"].'" HSCode = "'.$value["HSCode"].'">'.$value['id'].
                   ' '.$value['title_cn'].'['.$value['title_en'].']'.
                   '<input name="sort" class="form-inline w40 ml5  sort" data-id = "'.$value["id"].'" value="'
                   .$value['sort'].'" type="text"><input name="HSCode" class="form-inline w90 ml5" value="'
                   .$value['HSCode'].'" type="text"> <b class="pd2 btn btn-orange"  onclick="edit('.$value['id'].')"> 修改</b>
               <b onclick="add_class('.$value['id'].')" class="pd2 btn btn-qing"> 新增</b></span></li>';
             }
          }
         }
          $data = array(
             'code'=>200,
             'html'=>$html
          );
         echo json_encode($data);
         exit;
      }
    }

    /**
     * 修改分类
     * [updateClass description]
     * @return [type] [description]
     * 修改添加字段 rewritten_url，及过滤
     * @author: Wang edittime 2019-01-18
     */
    public function updateClass(){
      $classname = request()->post();
      $class_id = (int)$classname['class_id'];
      if($class_id >0){
      	    if(empty($class_id)){
      	    	echo json_encode(array('code'=>100,'result'=>'类别ID不可为空'));
      	    	exit;
      	    }
	      	if(strlen(trim($classname['title_en']))<1){
	      		echo json_encode(array('code'=>100,'result'=>'类别名称不可为空'));
	      		exit;
	      	}
	      	// $result= $this->checkClassName($classname['title_en'],$class_id);
	      	// if($result){
	      	// 	echo json_encode(array('code'=>102,'result'=>'类别名称重复'));
	      	// 	exit;
	      	// }
           $data['id']       = (int)$classname['class_id'];
           $data['status']       = (int)$classname['status'];
           $data['pid']      = (int)$classname['parent_id'];
           $data['title_cn'] = $classname['title_cn'];
           $data['title_en'] = $classname['title_en'];
           $data['isleaf']     = (int)$classname['isleaf'];
           $data['declare_en']     = $classname['declare_en'];
           $data['declare_cn']     = $classname['declare_cn'];
           $data['type']     = 1;
           $data['sort']     = (int)$classname['sort'];
           $data['HSCode']   = $classname['HSCode'];
           $data['icon']   = $classname['icon'];
           $data['classimg']   = $classname['classimg'];
           $data['edit_time'] = time();
           $data['edit_author'] = Session::get('username');
           /***********************因前端需求添加以下字段  @author: Wang addtime 2019-01-18************************/
           $data['rewritten_url']  = preg_replace('/(-)+/i','-',str_replace(array('&amp;',' & ','&', '/',' ','\'','"'), array('-','-','-','-','-','-','-'), strtolower($classname['title_en'])));
           if(preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$data['rewritten_url'])){
                echo json_encode(array('code'=>100,'result'=>'英文名存在特殊字符'));
                exit;
           }
           /**************************addtime 2019-01-18******************************************/
           $mongoResult = publicUpdate($data,MOGOMODB_P_CLASS,1);
           if($mongoResult){
               //插入变更历史--产品类别变更历史
               $data_histroy['EntityId'] =$data['id'];
               $data_histroy['CreatedDateTime'] = time();
               $data_histroy['IsSync'] = false;
               $data_histroy['Note'] = Session::get('username').'修改类别';
               $result_History = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($data_histroy);
               echo json_encode(array('code'=>200,'result'=>'修改成功'));
               exit;
           }else{
              echo json_encode(array('code'=>100,'result'=>'nomsql修改失败'));
              exit;
           }
      }else{
         echo json_encode(array('code'=>100,'result'=>'类别ID错误'));
         exit;
      }
    }

    /**
     * 添加分类
     * [addClass description]
     *
     * 新增pdc_id，rewritten_url,pdc_ids,Common,id_path
     * @author: Wang edittime 2019-01-18
     */
    public function addClass(){
      $classname = request()->post();
      if($classname['id'] || $classname['id']=== '0'){
      	   if(strlen(trim($classname['title_en']))<1){
	      	   	echo json_encode(array('code'=>100,'result'=>'类别名称不可为空'));
	      	   	exit;
      	   }
            $result= $this->checkClassName($classname['title_en']);
      	    if($result){
      	    	echo json_encode(array('code'=>102,'result'=>'类别名称重复'));
      	    	exit;
      	    }
      	   $data['pid']        = (int)$classname['parent_id'];
           $data['title_cn']   = $classname['title_cn'];
           $data['title_en']   = $classname['title_en'];
           $data['declare_en']   = $classname['declare_en'];
           $data['isleaf']   = (int)$classname['isleaf'];
           $data['level']   = (int)$classname['level'] +1;
           $data['type']     = 1;
           $data['sort']       = (int)$classname['sort'];
           $data['HSCode']     = $classname['HSCode'];
           $data['status']       = 1;
           $data['icon']     = $classname['icon'];
           $id   = $classname['class_id'];
           $data['add_time']    = time();
           $data['add_author'] = Session::get('username');

           /*********************** 因前端需求添加以下字段  @author: Wang addtime 2019-01-18 ******************************************/
           $data['pdc_id']     = '';
           $data['rewritten_url']  = preg_replace('/(-)+/i','-',str_replace(array('&amp;',' & ','&', '/',' ','\'','"'), array('-','-','-','-','-','-','-'), strtolower($classname['title_en'])));
           $data['pdc_ids']  = '';
           $data['Common']  = '';
           if(preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$data['rewritten_url'])){
                echo json_encode(array('code'=>100,'result'=>'英文名存在特殊字符'));
                exit;
           }
           $id_path = $this->UpperLayerID($data['pid']);
          if($id_path == 0){
              $data['id_path'] = $id;
          }else{
              $data['id_path']  = $id_path.'-'.$id;
          }
           /***********************addtime 2019-01-18********************************************************************************/
           if($id){
              $class_id = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$id])->field('id')->find();
              if($class_id){
                  echo json_encode(array('code'=>100,'result'=>'该分类id已添加过'));
                  exit;
              }
              $data['id']      = (int)$id;
              $result = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->insert($data);
              if($result){
                  //插入变更历史--产品类别变更历史
                  $data_histroy['EntityId'] =$data['id'];
                  $data_histroy['CreatedDateTime'] = time();
                  $data_histroy['IsSync'] = false;
                  $data_histroy['Note'] = Session::get('username').'新增类别';
                  $result_History = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($data_histroy);
                  echo json_encode(array('code'=>200,'result'=>'添加成功'));
                  exit;
              }else{
                  echo json_encode(array('code'=>100,'result'=>'添加nomysql失败'));
                  exit;
              }
           }else{
              echo json_encode(array('code'=>100,'result'=>'获取最大的id失败'));
              exit;
           }
      }else{
         echo json_encode(array('code'=>100,'result'=>'获取父级失败'));
         exit;
      }
    }

    /**
     * 获取所有分类父级ID
     * @param int  $id [分类ID]
     * [UpperLayerID description]
     * @author: Wang @addtime 2019-01-18
     */

    private function UpperLayerID($id=0){
       $id_path = $id;
       while (true) {
            $class_id = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$id,'type'=>1,'status'=>1])->field('id,pid')->find();
            if(!empty($class_id)){

                if($class_id['pid'] != 0){
                  $id_path = $class_id['pid'].'-'.$id_path;
                  $id = $class_id['pid'];
                }else{
                   return $id_path;
                }

            }else{
                return $id_path;
            }
       }

    }
    /**
     * 检查类别名称是否重复
     * 逻辑：检查整个表的名称是否存在完全相等，如果相等则返回true；
     * add by heng.zhang 2018-06-29
     */
    private function checkClassName($className,$class_id=''){
    	if(strlen($className)<1)
    		return true;
    	$data['title_en']=$className;
    	$data['status']=1;
      if($class_id!=''){
           $data['id'] = array('neq',$class_id);;
      }
        //dump($data);
    	$result = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where($data)->count();//dump($result);

        //dump($result);
    	return $result>0;
    }

    /**
     * 导出全部类别数据
     */
    public function exportAllClassData(){
        //获取类别数据
        $class_data = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)
                                             ->where('status',1)
                                             ->where('type',1)
                                             ->field('id,pid,title_en,title_cn,level')
                                             ->select();
        $header_data =['id'=>'ID','pid'=>'PID','title_en'=>'Title_EN','title_cn'=>'Title_CN','level'=>'Level'];
        $tool = new ExcelTool();
        $result = $tool ->export('All-Class-Data-'.date('Y-m-d'),$header_data,$class_data,'sheet1');
    }

    /**
     * 属性管理
     */
    public function attribute(){
       $data = request()->post();
       if(!empty($data)){
          $where_or['title_cn'] = array('like',$data['title_cn']);
          $where_or['title_en'] = array('like',$data['title_cn']);
       }
       $date['status'] =1;
       //dump($where_or);
       $page_size = config('paginate.list_rows');
       $list_attribute_json = Db::connect("db_mongo")->name("dx_attribute")
                                    ->where($date)->whereOr($where_or)->order('_id','desc')->paginate($page_size);
       //dump($list_attribute_json->render());
       $list_attribute = $list_attribute_json->items();
       foreach ($list_attribute as $key => $value) {
            $sttr = '';
           if(!empty($value["attribute"]) && count($value["attribute"])>0){
               foreach ($value["attribute"] as $k => $v) {
                   $sttr .= $v['title_cn'].'=>'.$v['title_en'];
                   if($v['value']){
                     $sttr .= '=>'.$v['value'].';';
                   }else{
                     $sttr .= ';';
                   }
                   // $sttr .= $v['title_cn'].'=>'.$v['title_en'].'=>'.$v['value'].';';
               }
           }
           $list_attribute[$key]['attribute_string'] = $sttr;
       }
       $this->assign(['list_attribute'=>$list_attribute,'page'=>$list_attribute_json->render(),'attribute_val'=>$data]);
       return view('attribute');
    }


    //添加与需改属性
    public function add_attribute(){
       return view('add_attribute');
    }
    //修改属性值(值的获取)
    public function edit_attribute(){
         $id             = input('attributeid');

         // $first_class    = $this->ProductClass->where(['pid'=>0])->select();
         $list_find = Db::connect("db_mongo")->name("dx_attribute")->where(['_id'=>(int)$id])->find();//dump($list_find);
         // $list_find      = $this->ClassAttribute->where(['id'=>$id])->find();
         // $class_html     = $this->parent_class($list_find['catalog_id']);
         // $class_html     = Business::parent_class($list_find['catalog_id']);
         // $list_attribute = $this->ProductClassAttributeValue->where(['attribute_id'=>$list_find['id']])->order('sort asc')->select();

         $sum  = count($list_find['attribute']);
         $html = '';
         $val  = '';
         $i    = 0;
         $max  = 0;
         $number = array();
         foreach ($list_find['attribute'] as $k => $v) {
               $list_find['input'][$k] = $k;
               $number[] = $k;
              // $val = max($list_find['input'])?max($list_find['input']):'';//用于添加 下一级元素
         }
         // $val = max($number)?max($number):'';
         // dump($list_find['attribute']);
         // $attribute = array_reverse($list_find['attribute']);dump($attribute);//倒序
         $attribute = $list_find['attribute'];
         foreach ($attribute as $key => $value) {
              $value['key'] = $key;
              if($max <$key){
                 $max = $key;
              }

              $i = $i+1;
              if($i == 1 && $sum != 1 ){
                    $html .='<dl class="c-h-dl-validator form-group clearfix add-attribute'.$value["key"].' delect_dl"><dd class="v-title"><label><em>*</em>中文名：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="where['.$value["key"].'][title_cn]" class="form-control input-medium " value="'.$value['title_cn'].'" type="text"></div> 英文名： <div class="input-icon right inline-block "><input name="where['.$value["key"].'][title_en]"  value="'.$value['title_en'].'" class="form-control" type="text"></div> 选项值： <div class="input-icon right inline-block "><input name="where['.$value["key"].'][value]"  value="'.$value['value'].'" class="form-control" type="text"></div> 排序：<div class="input-icon right inline-block inline_block"><input value="'.$value['sort'].'" name="where['.$value["key"].'][sort]" class="form-control input-val-1 w100" type="text"></div></dd><dt></dt></dl>';
              } else if($i == 1 && $sum == 1){
                    $html .='<dl class="c-h-dl-validator form-group clearfix add-attribute'.$value["key"].' delect_dl"><dd class="v-title"><label><em>*</em>中文名：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input value="'.$value['title_cn'].'" name="where['.$value["key"].'][title_cn]" class="form-control input-medium" type="text"></div>  英文名： <div class="input-icon right inline-block"><input value="'.$value['title_en'].'"  name="where['.$value["key"].'][title_en]" class="form-control " type="text"></div>  选项值： <div  class="input-icon right inline-block"><input value="'.$value['value'].'"  name="where['.$value["key"].'][value]" class="form-control " type="text"></div> 排序：<div class="input-icon right inline-block inline_block"><input value="'.$value['sort'].'" name="where['.$value["key"].'][sort]" class="form-control input-val-1 w100" type="text"></div><a class="add-attr-btn eliminate-btn2 delect added'.$max.'" data-total="'.$max.'" href="javascript:;">添加新项</a><a class="eliminate-btn2 eliminate'.$value["key"].'"  onclick="add_delect('.$value["key"].')" href="javascript:;">删除</a></dd><dt></dt></dl>';
              }else if($i == $sum){
                    $html .='<dl class="c-h-dl-validator form-group clearfix add-attribute'.$value["key"].' delect_dl"><dd class="v-title"><label><em>*</em>中文名：</label></dd><dd><div class="input-icon right inline-block"><i class="fa"></i><input name="where['.$value["key"].'][title_cn]" class="form-control input-medium" value="'.$value['title_cn'].'" type="text"></div>  英文名： <div class="input-icon right inline-block"><input name="where['.$value["key"].'][title_en]" class="form-control " value="'.$value['title_en'].'" type="text"></div>  选项值： <div class="input-icon right inline-block"><input name="where['.$value["key"].'][value]" class="form-control " value="'.$value['value'].'" type="text"></div> 排序：<div class="input-icon right inline-block inline_block"><input value="'.$value['sort'].'" name="where['.$value["key"].'][sort]" class="form-control input-val-1 w100" type="text"></div><a class="add-attr-btn eliminate-btn2 delect added'.$max.'" data-total="'.$max.'" href="javascript:;">添加新项</a><a class="eliminate-btn2 eliminate'.$value["key"].'"  onclick="add_delect('.$value["key"].')" href="javascript:;">删除</a></dd><dt></dt></dl>';
              } else{
                     $html .='<dl class="c-h-dl-validator form-group clearfix add-attribute'.$value["key"].' delect_dl">
                     <dd class="v-title"><label><em>*</em>中文名：</label></dd><dd><div class="input-icon right inline-block">
                     <i class="fa"></i><input name="where['.$value["key"].'][title_cn]" class="form-control input-medium" value="'.$value['title_cn'].'" type="text">
                     </div>  英文名：
                     <div class="input-icon right inline-block"><input name="where['.$value["key"].'][title_en]" class="form-control " value="'.$value['title_en'].'" type="text"></div>  选项值：
                     <div class="input-icon right inline-block"><input name="where['.$value["key"].'][value]" class="form-control " value="'.$value['value'].'" type="text"></div> 排序：<div class="input-icon right inline-block inline_block"><input value="'.$value['sort'].'" name="where['.$value["key"].'][sort]" class="form-control input-val-1 w100" type="text"></div>
                     <a class="eliminate-btn2 eliminate'.$value["key"].'"  onclick="add_delect('.$value["key"].')" href="javascript:;">删除</a></dd><dt></dt></dl>';
              }
         }
         $list_find['html']   = $html;
         $list_find['select'] = $class_html;
         $this->assign(['first_class'=>$first_class,'list_find'=>$list_find,'val'=>$val]);
         return view('add_attribute');
    }

    /**递归数去子集完父级分类
     * [parent_class description]
     * @return [type] [description]
     */
    public function parent_class($id,$data = array()){
            $parent_id     = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$id,'type'=>1])->field('id,pid')->find();
            $list  = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$id,'type'=>1])->select();
            $product_class = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>$parent_id['pid'],'type'=>1])->field('id,pid,title_cn,title_en')->select();
            if($product_class){
                 $product_class['select'] = $parent_id;
                 $data[] = $product_class;
                 if($parent_id['pid'] !=0){
                     return $this->parent_class($parent_id['pid'],$data);
                 }
            }
            $sum = count($data);//dump(array_reverse($data));
            $select_data = array(
                '1'=>'first',
                '2'=>'second',
                '3'=>'third',
                '4'=>'fourth',
                '5'=>'fifth',
            );
            $html = '';
            $selected = '';
            $i = 1;
            foreach (array_reverse($data) as $k => $v) {
               $html .= '<select id="'. $select_data[$i].'" name="'. $select_data[$i].'" class="form-control input-small inline">';
               foreach ($v as $ke => $va) {
                if($ke != 'select'){
                   if($v["select"]["id"] == $va['id']){$selected = 'selected = "selected"';}
                   $html .= '<option '.$selected.' value ="'.$va['id'].'">'.$va['title_cn'].'</option>';
                   $selected = '';
                }else if($ke == '0'){
                   if($v["select"]["id"] == $va['id']){$selected = 'selected = "selected"';}
                   $html .= '<option '.$selected.' value ="'.$va['id'].'">'.$va['title_cn'].'</option>';
                   $selected = '';
                }
               }
               $html .= '</select>';
              $i++;
            }
            return $html;
    }
    //父级往下查询
    public function catalog_subset($id= '',$select= '',$html = ''){//dump($select);
          $select_data = array(
              '1'=>'second',
              '2'=>'third',
              '3'=>'fourth',
              '4'=>'fifth',
          );
          $list  = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$id,'type'=>1])->select();
          // $list  = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>0])->select();
          if($list){
              $html .= '<select id="'.$select_data[$select].'" name="'.$select_data[$select].'" class="form-control input-small inline">';
              foreach ($list as $key=>$value){
                if($key == 0){
                    $catalog_id = $value['id'];
                }
                $html .= '<option value ="'.$value['id'].'">'.$value['title_cn'].'</option>';
              }
              $html .= '</select>';
              if($catalog_id){
                $select++;
                return  $this->catalog_subset($catalog_id,$select,$html);
              }
          }
          return $html;
    }
    //分类查询
    public function catalog(){
        $id = input('id');
        $select = input('select');
        return  $this->catalog_subset($id,$select);
    }

     /**
     * 添加属性值
     * [submit_attribute description]
     * @return [type] [description]
     */
    public function submit_attribute(){
        $attribute = request()->post();//dump($attribute);exit;
        $data = Business::AttributeJudge($attribute);
        //判断是添加还是修改
        try{
            $t = time();
            if(!empty($attribute['id'])){//修改 同时判断是否有修改分类id
                     $data['attribute']   = (object)$attribute["where"];
                     $data['edit_time']  = $t;
                     $data['edit_user']  = $t;//dump($data);exit;
                     //$result =  Db::connect("db_mongo")->name("dx_attribute")->where(['_id'=>(int)$attribute['id']])->find();dump($result);
                     // dump($data);exit;
                     $result =  Db::connect("db_mongo")->name("dx_attribute")->where(['_id'=>(int)$attribute['id']])->update($data);
            }else{
                  $data['attribute']   = $attribute["where"];$data['add_time']  = time();
                  $list = Db::connect("db_mongo")->name("dx_attribute")->whereOr(array('title_cn'=>$data['title_cn'],'title_cn'=>$data['title_cn']))->field('_id')->order('_id','desc')->find();
                  if($list){
                       echo json_encode(array('code'=>100,'result'=>'属性中文名或英文名重复'));
                       exit;
                  }
                  $_id = Db::connect("db_mongo")->name("dx_attribute")->field('_id')->order('_id','desc')->find();
                  if(is_int($_id['_id'])){
                    $id = $_id['_id']+1;
                  }else{
                    $id = 1;
                  }
                  $data['add_time']  = $t;
                  $data['add_user'] = Session::get('username');
                  $data['_id']         = $id;
                  $data['status']      = 1;
                  //新增
                  //判断是否添加过 attribute
                  $result   =  Db::connect("db_mongo")->name("dx_attribute")->insert($data);//存mongodb
                  // $id = Db::name(P_C_A)->where(array('title_cn'=>$data["title_cn"],'catalog_id'=>$data['catalog_id']))->field('id')->find();
                  // if($id){
                  //     echo json_encode(array('code'=>100,'result'=>'已添加过该属性'));
                  //     exit;
                  // }else{
                  //     $result = Db::name(P_C_A)->insertGetId($data);
                  // }
            }
        }catch(\Exception $e) {
              // $this->ClassAttribute->rollback();
               //TODO WRITE LOG
              echo json_encode(array('code'=>100,'result'=>'提交属性，系统异常，请联系管理员'));
              exit;
        }
      if($result){
          echo json_encode(array('code'=>200,'result'=>'提交属性成功'));
          exit;
      }else{
          echo json_encode(array('code'=>100,'result'=>'提交属性失败'));
          exit;
      }
    }

    /**
     * 添加属性值20180628前版本
     * [submit_attribute description]
     * @return [type] [description]
     */
    public function submit_attribute20180628(){
        $attribute = request()->post();
        $data = Business::AttributeJudge($attribute);
        Db::startTrans();
        //判断是添加还是修改
        $data['addtime']  = time();
        try{

            if(!empty($attribute['id']) && $data["catalog_id"] == $attribute["class_id"]){//修改 同时判断是否有修改分类id
                     $result = Db::name(P_C_A)->where(['id'=>$attribute['id']])->update($data);
            }else{//新增
                  //判断是否添加过
                  $id = Db::name(P_C_A)->where(array('title_cn'=>$data["title_cn"],'catalog_id'=>$data['catalog_id']))->field('id')->find();
                  if($id){
                      echo json_encode(array('code'=>100,'result'=>'已添加过该属性'));
                      exit;
                  }else{
                      $result = Db::name(P_C_A)->insertGetId($data);
                  }
           }

           Db::commit();
           $pcav = Db::name(P_C_A_V);
           if($result){
               if(!empty($attribute['id'])  && $data["catalog_id"] == $attribute["class_id"]){
                    $list = $pcav->where(['attribute_id'=>$attribute['id']])->select();
                    foreach ($attribute["where"] as $key => $value) {
                       $value['sort']      = (int)$value['sort'];
                       $value['edit_time'] = time();
                       $existence = $pcav->where(['attribute_id'=>$attribute['id'],'key'=>$key])->find();
                       if($existence){
                           $result_val            = $pcav->where(['attribute_id'=>$attribute['id'],'key'=>$key])->update($value);
                           $value['attribute_value_id'] = $existence['id'];
                           $update_data[]         = $value;//存要需改或添加的数据
                           //用判断是否存在需要删除的选项
                           if($result_val){
                               foreach ($list as $k => $v) {
                                  if($v['attribute_id'] == $attribute['id'] && $key == $v['key']){
                                      unset($list[$k]);
                                  }
                               }
                           }
                       }else{
                          $value['attribute_id'] = $attribute['id'];
                          $value['add_time']     = time();
                          $value['key']          = $key;
                          $result_val            = $pcav->insertGetId($value);
                       }
                       Db::commit();
                    }
                    //删除没有修改到的数据
                    if($list){
                       foreach ($list as $ke => $va) {
                          $result_delect = $pcav->where(['attribute_id'=>$attribute['id'],'key'=>$va['key']])->delete();
                          Db::commit();

                       }
                    }
                    $data['status'] = 1;
                    $result    = $this->EditAttribute($data,$update_data,$attribute['id'],$list);//把数据更新到nosql
               }else{
                   foreach ($attribute["where"] as $key => $value) {
                       $value['attribute_id'] = $result;
                       $value['sort']         = (int)$value['sort'];
                       $value['add_time']     = time();
                       $value['key']          = $key;
                       $result_val            = Db::name(P_C_A_V)->insertGetId($value);
                       Db::commit();
                       $attribute["where"][$key]['id'] = $result_val;

                   }
                   //推送到nosql
                   $data['attribute_id'] = $result;
                   $result =  $this->submit_attribute_nosql($data,$attribute["where"]);

               }
           }
        }catch(\Exception $e) {
              $this->ClassAttribute->rollback();
              echo json_encode(array('code'=>100,'result'=>'提交属性失败'));
              exit;
        }
      if($result){
          echo json_encode(array('code'=>200,'result'=>'提交属性成功'));
          exit;
      }else{
          echo json_encode(array('code'=>100,'result'=>'提交属性失败'));
          exit;
      }
    }

   /**修改nosql
    * [EditAttribute description]
    * @param [type] $data         分类数组
    * @param [type] $update_data  修改修改的数组
    * @param [type] $list         修改删除的数组
    * @param [type] $attributeID    属性表od
    *待优化
    */
    public function EditAttribute($data,$update_data,$attributeID,$list){

          $attribute = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$data['catalog_id']])->find();//存mongodb
          $catalog_id = $data['catalog_id'];
          unset($data['catalog_id']);
          if($attribute){//判断分类是否存在

             $attribute['attribute'][$attributeID] = $data;
             foreach ($update_data as $key => $value) {
                $attribute['attribute'][$attributeID]['attribute_value'][$value["attribute_value_id"]] = $value;
             }
             $result = Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$catalog_id])->update(['attribute'=>(Object)$attribute['attribute'],'edittime'=>time()]);//存mongodb
          }else{
            $mongo['product_brand']   = array();
            $mongo['addtime']         = time();
            $mongo['_id']             = (int)$catalog_id;
            $mongo['edittime']        = '';
            $mongo['attribute'][$attributeID] = $data;
            foreach ($update_data as $key => $value) {
                 $mongo['attribute'][$attributeID]['attribute_value'][$value["attribute_value_id"]]     = $value;
            }
            $result   =  Db::connect("db_mongo")->name("dx_brand_attribute")->insert($mongo);//存mongodb
          }
          if($result){
              return true;
          }else{
              return false;
          }
    }

    /**
     * 将属性值推送到添加到nosql
     */
    public function submit_attribute_nosql($data,$attribute_value){
        $no_data['title_cn'] = $data['title_cn'];
        $no_data['title_en'] = $data['title_en'];
        $no_data['type']    = $data['type'];
        $no_data['status']  = 1;
        foreach ($attribute_value as $key => $value) {
            $value['status']  = 1;
            $no_data['attribute_value'][$value["id"]]  = $value;
        }
        $attribute_data           =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$data['catalog_id']])->find();//存mongodb
        if($attribute_data){//分类存在的情况下
            $attribute_data['attribute'][$data['attribute_id']] = $no_data;//如果该id之前存在则会被覆盖
            $result_mongodb   =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$data['catalog_id']])->update(['attribute'=>(Object)$attribute_data['attribute'],'edittime'=>time()]);
        }else{
            $mongo['product_brand']   = array();
            $mongo['attribute'][$data['attribute_id']]     = $no_data;
            $mongo['add_time']         = time();
            $mongo['add_user']         = Session::get("username");
            $mongo['_id']             = (int)$data['catalog_id'];
            $mongo['edit_time']        = '';
            $result_mongodb   =  Db::connect("db_mongo")->name("dx_brand_attribute")->insert($mongo);//存mongodb
        }

        if($result_mongodb){
          return true;
        }else{
          return false;
        }
    }

    //删除单个销售属性
    public function del_attribute(){
        $id = input('id');
        if(empty($id)){
             echo  ajaxReturn(100,'获取属性类别id失败');
             exit;
        }
        $result = Business::deleteAttributeNosql($id);
        if($result){
            echo  ajaxReturn(200,'删除成功');
        }else{
            echo  ajaxReturn(100,'删除失败');
        }
    }
   /*品牌管理
    */
   public function brandManagement(){
          $page_size = config('paginate.list_rows');
          $Brand_M = Db::connect("db_mongo")->name(BRANDS);
          if($data = request()->post()){
               if(!empty($data['BrandName'])){
                  $where['BrandName'] =array('like',$data['BrandName']);
               }
               $list = $Brand_M->order('ModifiedTime' ,'desc')->where($where)->paginate($page_size);
               $this->assign(['data'=>$data]);
          }else{
               $list = $Brand_M->order('ModifiedTime','desc')->paginate($page_size);
          }
          $this->assign(['Brand_list'=>$list,'page'=>$list->render(),'dx_mall_img_url_brand'=>config('dx_mall_img_url_brand')]);
          return view('brand_management');
   }
   /**
    * [add_brand description]
    * 添加品牌  与   修改品牌
    */
   public function add_brand(){
      if($data = request()->post()){
          $brand_path_name = config('ftp_config.DX_FTP_ACCESS_PATH_BRAND');
          $path = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'img'.DS .str_replace('/','',$brand_path_name);
          $data_val = array();
          $files = $_FILES;
          //获取文件返回错误
          if ($files["file"]["error"] > 0){
             echo ajaxReturn(100,'获取图错误');
             exit;
          }
          //判断品牌名称
          if(empty($data["brand_name"]) || preg_match("/^[\x7f-\xff]+$/", $data["brand_name"])){
              echo json_encode(array('code'=>100,'result'=>'品牌名名称为空或者不是英文'));
              exit;
          }else{
          	    if($this -> checkBrandENName($data["brand_name"],0)){
          	    	echo json_encode(array('code'=>100,'result'=>'品牌名名称不可重复'));
          	    	exit;
          	    }
                $data_val['BrandName'] = $data["brand_name"];
          }
          //判断排序
          if(empty($data["sort"])){
              echo json_encode(array('code'=>100,'result'=>'排序不能为空'));
              exit;
          }else{
              $data_val['Sort'] = $data["sort"];
          }

          //品牌自增ID
          $increment = Db::connect("db_mongo")->name('dx_auto_increment')->find();
          $brand_id = $data_val['BrandId'] = $increment['BrandId'] + 1;

          if (!is_dir($path)){//当路径不穿在
                if(!mkdir($path, 0777, true)){
                    echo ajaxReturn(100,'创建文件夹失败');//获取文件返回错误
                    exit;
                }//创建路径
          }
          //图片名称
          $files["file"]["name"] = 'brand_'.$brand_id.'.jpg';
          $url = $path.'/'.$files["file"]["name"];
          //保存本地
          if(!move_uploaded_file($_FILES["file"]["tmp_name"],$url)){
             echo ajaxReturn(100,'图片保存本地失败');//获取文件返回错误
             exit;
          }
          //同步图片
          $ftp_config =['dirPath'=>$brand_path_name, // ftp保存目录
                        'romote_file'=>$files["file"]["name"],
                        'local_file'=>$url,
                       ];

          $uploadresult = FTPUpload::data_put($ftp_config);
          if(!$uploadresult){
              echo ajaxReturn(100,'图片上传远程服务器失败');//获取文件返回错误
              exit;
          }
          //$serverApi =  scoso();
          //$catalog   =  makeDir($serverApi,DX_FTP_ACCESS_PATH_BRAND);
          //$upload    =  ftp_put($serverApi,$files["file"]["name"],$url,FTP_BINARY);//上传到远程服务器

          //组装数据
          $data_val['Brand_Icon_Url'] = $brand_path_name.'/'.$files["file"]["name"];
          $data_val['Introduction'] =  $data["introduction"]?$data["introduction"]:'';
          $data_val['CreatedTime']     =  time();
          $data_val['CreateUserId']   =  Session::get('username');
          $data_val['ModifiedTime']     =  time();
          $data_val['ModifyUserId']   =  Session::get('username');

          $result = Db::connect("db_mongo")->name(BRANDS)->insert($data_val);
          if($result){
              //更新自增品牌id
              Db::connect("db_mongo")->name('dx_auto_increment')->where(['BrandId'=>(int)$increment['BrandId']])->update(['BrandId'=>(int)$brand_id]);
              echo json_encode(array('code'=>200,'result'=>'数据提交成功'));
              exit;
          }else{
              echo json_encode(array('code'=>100,'result'=>'数据提交db失败'));
              exit;
          }
      }else{
          return view('add_brand');
      }
   }

   /**
    *
    * @return [type] [description]
    * 品牌修改
    */
   public function edit_brand(){
      $Brand_M = Db::connect("db_mongo")->name(BRANDS);
      if($data = request()->post()){
          //dump((int)$data['id']);
          $files = $_FILES;
          if(empty($data["brand_name"]) || preg_match("/^[\x7f-\xff]+$/", $data["brand_name"])){
              echo json_encode(array('code'=>100,'result'=>'品牌名名称为空或者不是英文'));
              exit;
          }else{
          	if($this -> checkBrandENName($data["brand_name"],(int)$data['id'])){
          		echo json_encode(array('code'=>100,'result'=>'品牌名名称不可重复'));
          		exit;
          	}
             $data_val['BrandName'] = $data["brand_name"];
          }
          if(empty($data["sort"])){
              echo json_encode(array('code'=>100,'result'=>'排序不能为空'));
              exit;
          }else{
              $data_val['Sort'] = $data["sort"];
          }
          //如果重新上传图片
          if(!empty($files['file']['name'])){
              $brand_path_name = config('ftp_config.DX_FTP_ACCESS_PATH_BRAND');
              $path = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'img'.DS .str_replace('/','',$brand_path_name);
              if (!is_dir($path)){//当路径不存在
                  if(!mkdir($path, 0777, true)){
                      echo ajaxReturn(100,'创建文件夹失败');//获取文件返回错误
                      exit;
                  }//创建路径
              }
              //图片名称
              $files["file"]["name"] = 'brand_'.$data['id'].'.jpg';
              $url = $path.'/'.$files["file"]["name"];
              //保存本地
              if(!move_uploaded_file($_FILES["file"]["tmp_name"],$url)){
                  echo ajaxReturn(100,'图片保存本地失败');//获取文件返回错误
                  exit;
              }
              //同步图片
              $ftp_config =['dirPath'=>$brand_path_name, // ftp保存目录
                  'romote_file'=>$files["file"]["name"],
                  'local_file'=>$url,
              ];
              $uploadresult = FTPUpload::data_put($ftp_config);
              if(!$uploadresult){
                  echo ajaxReturn(100,'图片上传远程服务器失败');//获取文件返回错误
                  exit;
              }
              $data_val['Brand_Icon_Url'] = $brand_path_name.'/'.$files["file"]["name"];
          }

          //组装数据
          $data_val['Introduction'] =  $data["introduction"];
          $data_val['ModifiedTime']     =  time();
          $data_val['ModifyUserId']   =  Session::get('username');

          $result = Db::connect("db_mongo")->name(BRANDS)->where(['BrandId'=>(int)$data['id']])->update($data_val);
          if($result){
              echo json_encode(array('code'=>200,'result'=>'修改成功'));
              exit;
          }else{
              echo json_encode(array('code'=>100,'result'=>'修改失败'));
              exit;
          }
      }else{
          $id = input('id');
          $content = $Brand_M->where(['BrandId'=>(int)$id])->find();
          $this->assign(['content'=>$content,'dx_mall_img_url_brand'=>config('dx_mall_img_url_brand')]);
          return view('add_brand');
      }

   }

   /**
    * 检查品牌英文名称是否重复--大小写敏感
    * return true 重复,false 不重复
    */
   private function checkBrandENName($en_brand_name,$brand_id){
   	    if(strlen($en_brand_name)<1){
   	    	return false;
   	    }
   	    if($brand_id >0){
            $where['BrandId'] = array('<>',$brand_id);
        }
   	    $where['BrandName'] = $en_brand_name;
   		$result = Db::connect("db_mongo")->name(BRANDS)->where($where)->count('*');
   		return $result>0;
   }

   /**
   public function BrandNosql($result_data,$catalog_id,$id){
        $data_db         = $result_data["data_db"];
        $data_db["sort"] = $result_data["data_val"]["sort"];
        $data_db["id"]   = (int)$id;

        $AttributeData   =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$catalog_id])->find();//存mongodb
        if($AttributeData){
              $AttributeData['product_brand'][$id] = $data_db;//如果该id之前存在则会被覆盖
              $result_mongodb   =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$catalog_id])->update(['product_brand'=>(Object)$AttributeData['product_brand'],'edittime'=>time()]);
        }else{
              $mongo['product_brand'][$id] = $data_db;
              $mongo['attribute']       = array();
              $mongo['addtime']         = time();
              $mongo['_id']             = (int)$catalog_id;
              $mongo['edittime']        = '';
              $result_mongodb   =  Db::connect("db_mongo")->name("dx_brand_attribute")->insert($mongo);//存mongodb
        }
        if($result_mongodb){
             return true;
        }else{
             return false;
        }
   }
*/

   //缩略图
   public function imgsive($srcfile,$width='',$height='',$filename = ""){
        $size=getimagesize($srcfile);
        switch($size[2]){
            case 1:
            $img=imagecreatefromgif($srcfile);
            break;
            case 2:
            $img=imagecreatefromjpeg($srcfile);
            break;
            case 3:
            $img=imagecreatefrompng($srcfile);
            break;
            default:
            exit;
        }
        //源图片的宽度和高度
        $srcw=imagesx($img);
        $srch=imagesy($img);
        //目的图片的宽度和高度
        if($size[0] <= $width || $size[1] <= $height){
            $dstw=$srcw;
            $dsth=$srch;
        }else{
        if($width <= 1 && $height <= 1){
            // $dstw=$rate;
            // $dsth=$rate;
            $dstw=floor($srcw*$width);
            $dsth=floor($srch*$height);
        }else {
            $dstw=$width;
            $rate=$height/$srcw;
            $dsth=floor($srch*$rate);
        }
        }
        //echo "$dstw,$dsth,$srcw,$srch ";
        //新建一个真彩色图像
        $im=imagecreatetruecolor($dstw,$dsth);
        $black=imagecolorallocate($im,255,255,255);
        imagefilledrectangle($im,0,0,$dstw,$dsth,$black);
        imagecopyresized($im,$img,0,0,0,0,$dstw,$dsth,$srcw,$srch);
        // 以 JPEG 格式将图像输出到浏览器或文件

        if($filename) {

        //图片保存输出
        $result = imagejpeg($im, $filename);
        $size   = filesize($filename);

        }else {
            //图片输出到浏览器
            imagejpeg($im);
        }
        //释放图片
        imagedestroy($im);
        imagedestroy($img);

        return $result;
   }


   /**
    * 获取下一级分类
    */
   public function catalog_next(){
     $id          = input('id');
     $val         = input('class_level') + 1;
     $select_data = array(
              '1'=>'second_level',
              '2'=>'third_level',
              '3'=>'fourth_level',
              '4'=>'fifth_level',
          );
     // $Pclass = Db::name(P_CLASS);
     // $Pclass = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS);
     $html = '';
     $list  = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$id,'type'=>1])->select();
     if(!$list){
        return;
     }
     $html .= '<select id="'.$select_data[$val].'" name="'.$select_data[$val].'" class="form-control input-small inline">';
     $html .= '<option value ="">请选择</option>';
     foreach ($list as $key=>$value){
        $html .= '<option value ="'.$value['id'].'">'.$value['title_en'].'</option>';
     }
     $html .= '</select>';
     echo $html;
     exit;
  }

   /**
    * 由于前段冲突  所放了一个id名不一样的方法
    * 获取下一级分类
    */
   public function catalog_next_mongo(){
     $id          = input('id');
     $val         = input('class_level') + 1;
     $select_data = array(
              '1'=>'second_level_mongo',
              '2'=>'third_level_mongo',
              '3'=>'fourth_level_mongo',
              '4'=>'fifth_level_mongo',
          );
     $html = '';
     $list  = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$id,'type'=>1])->select();
     if(!$list){
        return;
     }
     $html .= '<select id="'.$select_data[$val].'" name="'.$select_data[$val].'" class="form-control input-small inline">';
     $html .= '<option value ="">请选择</option>';
     foreach ($list as $key=>$value){
        $html .= '<option value ="'.$value['id'].'">'.$value['title_en'].'</option>';
     }
     $html .= '</select>';
     echo $html;
     exit;
  }
  /**
   *删除品牌
   */
  public function del_brand(){
        if($data = request()->post()){
            if(empty($data['id'])){
                echo json_encode(array('code'=>100,'result'=>'id不能为空'));
                exit;
            }
            $result = Db::connect("db_mongo")->name(BRANDS)->where(['BrandId'=>(int)$data['id']])->delete();
           if($result){
              echo  ajaxReturn(200,'删除成功');
           }else{
              echo  ajaxReturn(100,'删除nosql失败');exit;
           }
        }
  }

  /**
   *产品列表
   */
  public function  productList(){
     if($data = request()->post()){
         if(isset($data['spu']) && !empty($data['spu'])){
               $where = array();
               $spu = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',',',',',',',',',' ',',',',',','],$data["spu"]);
               // dump($spu);
               $pattern = '/(,)+/i';
               $spu = preg_replace($pattern,',',$spu );
               $spu_array = explode(",", $spu);
               foreach ($spu_array as $k => $v) {
                 $where[] = (int)$v;
               }
               $data['id'] = $where;
         }
         if(isset($data['Code']) && !empty($data['Code'])){
               $where = array();
               $Code = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',',',',',',',',',' ',',',',',','],$data["Code"]);
               // dump($spu);
               $pattern = '/(,)+/i';
               $Code = preg_replace($pattern,',',$Code );
               $Code_array = explode(",", $Code);
               foreach ($Code_array as $kCode => $vCode) {
                 $where[] = (int)$vCode;
               }
               $data['Code'] = $where;
         }
         //API里使用的是id字段
         unset($data['spu']);
         //判断价格区间
         if(isset($data['lowPrice']) && !empty(trim($data['lowPrice']))){
             if(is_numeric ($data['lowPrice'])){
                 $data['lowPrice'] = trim($data['lowPrice']);
             }
         }
         if(isset($data['heightPrice']) && !empty(trim($data['heightPrice']))){
             if(is_numeric ($data['heightPrice'])){
                 if($data['lowPrice'] < $data['heightPrice']){
                     $data['heightPrice'] = trim($data['heightPrice']);
                 }
             }
         }

         if(isset($data['ProductStatus']) && $data['ProductStatus'] != ''){
             $ProductStatusDefault= $data['ProductStatus'];
         }
          //判断分类
          if(!empty($data["fifth_level"])){
               $parent_class = Business::parent_class($data['fifth_level']);
          }else if(!empty($data["fourth_level"])){
               $parent_class = Business::parent_class($data['fourth_level']);
          }else if(!empty($data["third_level"])){
               $parent_class = Business::parent_class($data['third_level']);
          }else if(!empty($data["second_level"])){
               $parent_class = Business::parent_class($data['second_level']);
          }else if(!empty($data["first_level"])){
               $parent_class = Business::parent_class($data['first_level']);
          }
         if($parent_class){
           $this->assign(['parent_class'=>$parent_class,]);
         }
         //dump($data);
         $this->assign(['data'=>$data,]);
     }else{
         $data = input();
         if(!empty($data['FirstCategory'])){
             $data['first_level'] = $data['FirstCategory'];
         }
         if(!empty($data['SecondCategory'])){
             $data['second_level'] = $data['SecondCategory'];
         }
         if(!empty($data['ThirdCategory'])){
             $data['third_level'] = $data['ThirdCategory'];
         }
         if(!empty($data['FourthCategory'])){
             $data['fourth_level'] = $data['FourthCategory'];
         }
         foreach($data as $k=>$v){
             if(empty($v)){
                 unset($data[$k]);
             }
         }

         if(isset($data['spu']) && !empty($data['spu'])){
               $where = array();
               $spu = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',':',',',',',',',' ',',',',',','],$data["spu"]);
               // dump($spu);
               $pattern = '/(,)+/i';
               $spu = preg_replace($pattern,',',$spu );
               $spu_array = explode(",", $spu);
               foreach ($spu_array as $k => $v) {
                 $where[] = (int)$v;
               }
               $data['_id'] = $where;
         }
         if(isset($data['Code']) && !empty($data['Code'])){
               $where = array();
               $Code = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',':',',',',',',',' ',',',',',','],$data["Code"]);
               // dump($spu);
               $pattern = '/(,)+/i';
               $Code = preg_replace($pattern,',',$Code );
               $Code_array = explode(",", $Code);
               foreach ($Code_array as $kCode => $vCode) {
                 $where[] = (int)$vCode;
               }
               $data['Code'] = $where;
         }
         unset($data["id"]);
         if(!empty($data['_id'])){
             $data["id"] = $data['_id'];
             unset($data['_id']);
         }
         $ProductStatusDefault= 1;
         $data['ProductStatus'] = $ProductStatusDefault;
     }

      $classList = FirstLevelClass();
      $page=input('page');
      if(!$page){
          $page = 1;
      }
      $data['page'] = $page;
      $data['page_size'] = config('paginate.list_rows');
      foreach ($data as $ke => $ve) {
         if($ve == ''){
            unset($data[$ke]);
         }
      }
      if(!empty($data['Title']) && is_array($data['Title'])){
          $data['Title'] = isset($data['Title'][1]) ? $data['Title'][1]: '';
      }
      if(!empty($data['BrandName']) && is_array($data['BrandName'])){
          $data['BrandName'] = isset($data['BrandName'][1]) ? $data['BrandName'][1]: '';
      }
      if(!empty($data['UserName']) && is_array($data['UserName'])){
          $data['UserName'] = isset($data['UserName'][1]) ? $data['UserName'][1]: '';
      }
      if(!empty($data['StoreName']) && is_array($data['StoreName'])){
          $data['UserName'] = isset($data['StoreName'][1]) ? $data['StoreName'][1]: '';
      }

      $this->assign('data',$data);
      $list = BaseApi::productList($data);

      $ProductStatus = $this->dictionariesQuery('ProductStatus');//获取字典  产品状态
      $product_class = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['status'=>1])->field(array('id'=>true,'title_cn'=>true,'title_en'=>true,'_id'=>false,'type'=>1))->select();
      $this->assign(['list'=>$list['data']["data"],
      'page'=>str_replace("/mallextend/product/lists","/ProductManagement/productList",$list["data"]['Page']),
      'classList'=>$classList,
      'ProductStatus'=> $ProductStatus,
      'product_class'=>$product_class,
      'ProductStatusDefault'=> $ProductStatusDefault]);
      $this->assign(['mall_base_url'=>config('mall_url'),'dx_mall_img_url'=>config('dx_mall_img_url')]);
      return view('productList');
  }

  /*
   *批量下架
   */
    public function ProductStatusList()
    {
        $data = input();
        if(!empty($data['id'])){
            $status = self::PRODUCT_STATUS_DOWN;
            $ids = explode(",", $data['id']);
            //日志记录下架
            $result=$this->setProductStatusLog($ids,$status);
            $ChangeStatus['id'] = $ids;
            $ChangeStatus['status'] = $status;
            $result = BaseApi::productChangeStatusPost($ChangeStatus);
            if($result["code"] == 200){
                echo str_replace("msg","result",json_encode($result));
                exit;
            }else{
                echo str_replace("msg","result",json_encode($result));
                exit;
            }
        }
    }

    /*
     * 下架日志
     */
    private function setProductStatusLog($ids,$status){
        foreach($ids as $id){
            $da=[
                'product_id'=>$id,
                'status'=>$status,
                'operator'=>Session::get("username"),
                'operator_id'=>Session::get("userid"),
                'ip'=>$_SERVER['REMOTE_ADDR'],
                'add_time'=>time()
            ];
            $log[]=$da;
        }

        $re= Db::name(PRODUCT_OPERATION_LOG)->insertAll($log);//记录操作日志让
        return $re;
    }

  /**产品状态
  * [MerchantDelete description]
  */
  public function ProductStatus(){
      $status = input('status');
      if(($data = request()->post()) && !empty($status)){
         $data['status'] = $status;//dump($data);
         $log = AdminLog(['status'=>$status,'product_id'=>$data['id']]);
         Db::name(PRODUCT_OPERATION_LOG)->insert($log);//记录操作日志
         // $this->ProductStatusLog(['status'=>4,'product_id'=>$data['id']]);
         $result = BaseApi::ProductStatus($data);
         if($result["code"] == 200){
               echo str_replace("msg","result",json_encode($result));
               exit;
         }else{
               echo str_replace("msg","result",json_encode($result));
               exit;
         }
      }else{
         echo  ajaxReturn(100,'获取相应参数失败');exit;
      }
  }
  /**
  * 产品下架日志
  * [MerchantDelete description]
  */
  public function productStatusLog(){
      $where = [];
      $page_size = config('paginate.list_rows');
      if($data = request()->post()){
          // dump($data);
            if(!empty($data['product_id'])){
                $where['product_id'] = $data['product_id'];
            }
            if(!empty($data['operator'])){
                $where['operator'] = $data['operator'];
            }
            if(!empty($data['operator_id'])){
                $where['operator_id'] = $data['operator_id'];
            }
            if(!empty($data['startTime']) && !empty($data['endTime'])){
                $where['add_time'] = array('between',''.strtotime($data['startTime']).','.strtotime($data['endTime']).'');
            }else if(!empty($data['startTime'])){
                $where['add_time'] = array('egt',strtotime($data['startTime']));
            }else if(!empty($data['endTime'])){
                $where['add_time'] = array('elt',strtotime($data['endTime']));
            }
      }
      $list = Db::name(PRODUCT_OPERATION_LOG)->where($where)->paginate($page_size,false,[
                        'type' => 'Bootstrap',
                        'query'=> $data
                    ]);
      // echo Db::name(PRODUCT_OPERATION_LOG)->getLastSql();
      $list_items  = $list->items();
      $page = $list->render();
      $this->assign(['list'=>$list_items,'page'=>$page]);
      return view();
  }


  /**
   * [productExamine description]
   * @return [type] [description]
   * 产品审核列表
   */
  public function productExamineList(){
      $data = [];
      $parent_class = '';
      if($data = request()->post()){
              //判断分类
              if(!empty($data["fifth_level"])){
                   $parent_class = Business::parent_class($data['fifth_level']);
              }else if(!empty($data["fourth_level"])){
                   $parent_class = Business::parent_class($data['fourth_level']);
              }else if(!empty($data["third_level"])){
                   $parent_class = Business::parent_class($data['third_level']);
              }else if(!empty($data["second_level"])){
                   $parent_class = Business::parent_class($data['second_level']);
              }else if(!empty($data["first_level"])){
                   $parent_class = Business::parent_class($data['first_level']);
              }
             // if($parent_class){
             //   $this->assign(['parent_class'=>$parent_class,]);
             // }
             $this->assign(['data'=>$data,]);
      }
      // dump(redis()->del(REDIS_PRODUCT_FIRST_CLASS));
      if(redis_exists(REDIS_PRODUCT_FIRST_CLASS)){
          $classList =  json_decode(redis_get(REDIS_PRODUCT_FIRST_CLASS),true);
      }else{
          // $classList = Db::name(P_CLASS)->where(['pid'=>0])->select();
          $classList = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>0,'type'=>1])->select();
          //缓存1DAY
          if($classList){
              redis_set(REDIS_PRODUCT_FIRST_CLASS,json_encode($classList,true),60*60*24);
          }
          //dump(json_decode(redis_get(REDIS_PRODUCT_FIRST_CLASS),true));
      }
      $page = input('page');
      if(!$page){
          $page = 1;
      }

      $data['ProductStatus'] = [0,5];
      $data['page']      = $page;
      $data['page_size'] = config('paginate.list_rows');
      if(!empty($data['id'])){
             $data['id'] = [$data['id']];
      }
      $list = BaseApi::productList($data);
      $type = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>'Audit',])->find();
      $keyVal = explode(";", $type["ConfigValue"]);
      $html = '';
      foreach ($keyVal as $key => $value) {
        $ExamineListType = explode(":", $value);
        $html .= '<option value="'.$ExamineListType[0].'">'.$ExamineListType[1].'</option>';

      }//dump($list['data']["data"]);
      $ProductStatus = $this->dictionariesQuery('ProductStatus');//获取字典  产品状态
      if($product_class = redis_get(REDIS_PRODUCT_CLASS)){//dump(REDIS_PRODUCT_CLASS);dump($product_class);
          $product_class = json_decode($product_class,true);
      }else{
          $product_class = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->field(array('id'=>true,'title_cn'=>true,'title_en'=>true,'_id'=>false,'type'=>1))->select();
          redis_set(REDIS_PRODUCT_CLASS,json_encode($product_class,true),60*60*24);
      }
//dump($list["data"]['Page']);
      $this->assign(['list'=>$list['data']["data"],
                      'page'=>str_replace("/mallextend/product/lists","/ProductManagement/productExamineList",$list["data"]['Page']),
                      'classList'=>$classList,
                      'html'=>$html,'ProductStatus'=>$ProductStatus,
                      'product_class'=>$product_class,]);
      $this->assign(['mall_base_url'=>config('mall_url'),'mall_url_token'=>config('mall_url_token'),
                     'dx_mall_img_url'=>config('dx_mall_img_url'),'user_en_name'=>Session::get("username"),'parent_class'=>$parent_class]);
      return view('productExamineList');
  }

   /**
   * [productExamine description]
   * @return [type] [description]
   * 产品审核
   */
  public function productExamine(){
    // dump(request()->post());
       if($data = request()->post()){
          if($data['status'] == 1){
               // unset($data['status']);
               $data['status'] = 1;
               $result = BaseApi::productExamine($data);
          }else{

               if(!empty($data['type']) ){
                    $data['status'] = 12;//不通过
               }
               $result = BaseApi::productExamine($data);
          }
          if($result['code'] == 200){
              echo json_encode(array('code'=>200,'result'=>'操作成功'));
              exit;
          }else{
              echo json_encode(array('code'=>100,'result'=>$result['data']));
              exit;
          }
       }
  }
  /**
   * [batchExamine description]
   * @return [type] [description]
   * 批量不通过
   */
  public function batchNotExamine(){
       if($data = request()->post()){
          $data['id'] = explode(",", $data['id']);
          if(!empty($data['type']) ){
                    $data['status'] = 12;//不通过
          }
          $result = BaseApi::productExamine($data);
          if($result['code'] == 200){
              echo json_encode(array('code'=>200,'result'=>'操作成功'));
              exit;
          }else{
              echo json_encode(array('code'=>100,'result'=>$result['data']));
              exit;
          }
       }
  }
  /* [batchExamine description]
   * @return [type] [description]
   * 批量通过
   */
  public function batchExamine(){
       if($data = request()->post()){
          $data['id'] = explode(",", $data['id']);
          $data['status'] = 1;
          $result = BaseApi::productExamine($data);
          if($result['code'] == 200){
              echo json_encode(array('code'=>200,'result'=>'批量审核成功'));
              exit;
          }else{
              echo json_encode(array('code'=>100,'result'=>$result['data']));
              exit;
          }
       }
  }

   /**
     * 根据 classid 和 Seller id更新在售的产品的commission数据 （ ProductStatus=1 在售产品）
     * 此方法只更新$type=(2)
     * @param array $classid
     * @param int $seller_id
     * @param float $commission
     * @return json
     */
    public function updateCommissionBySellerID($classid,$seller_id,$commission){
		if(empty($classid) || $seller_id<=0 || $commission <=0){
			return '参数错误';
		}
    	$updateWhere['StoreID'] = (int)$seller_id;
    	$updateWhere['FirstCategory'] = (int)$classid;
    	$updateWhere['ProductStatus'] = 1;
    	$updateWhere['CommissionType'] = array('<>',3);
    	#$type=1  默认类型的佣金
    	#特别注意：  类型等于=2 或者3 的数据需要在后台审核通过后更新数据，只有等于1的才无须审核，直接更新
        //先统计数据是否存在
    	$count = Db::connect("db_mongo")->name(PRODUCT)
			    	->where($updateWhere)
    				->count();
    	if($count){
    		$resultDB = Db::connect("db_mongo")->name(PRODUCT)
				    		->where($updateWhere)
				    		->update(['Commission'=>$commission,'CommissionType'=>2]);
    		if($resultDB){
    			return 200;
    		}else{
    			return '更新产品表数据失败';
    		}
    	}else{
    		return '无产品，请尽快上传商品';
    	}
    	return $result;
    }

    /**
     * 更新主推产品佣金数据
     * @param int $spu
     * @return 200 成功，非200返回字符串
     */
    public function updateCommissionBySPU($spu,$commission){
    	if($spu<=0 || $commission<=0){
    		return '参数错误';
    	}
    	$updateWhere['_id'] = (int)$spu;
    	$updateWhere['ProductStatus'] = 1;
    	#$type=1  默认类型的佣金
    	#特别注意：  类型等于=2 或者3 的数据需要在后台审核通过后更新数据，只有等于1的才无须审核，直接更新
    	//$updateWhere['CommissionType'] =1;
    	//先统计数据是否存在
        $resultDB = Db::connect("db_mongo")->name(PRODUCT)
			    		->where($updateWhere)
			    		->update(['Commission'=>$commission,'CommissionType'=>3]);
    	if($resultDB){
    		return 200;
    	}else{
    		return '更新产品表数据失败';
    	}
    	return $result;
    }

    /**
     *
     */
    public function brandAttributeList(){
         // $result = Db::connect("db_mongo")->name("dx_region")->where($where)->whereLike('Name',$whereLike)->field('_id,Name,Code,AreaName,AreaID')->select();
         if($data = request()->post()){
              if(!empty($data['fourth_level'])){
                  $where['_id'] = (int)$data['fourth_level'];
              }else if(!empty($data['third_level'])){
                  $where['_id'] = (int)$data['third_level'];
              }else if(!empty($data['second_level'])){
                  $where['_id'] = (int)$data['second_level'];
              }else if(!empty($data['first_level'])){
                  $where['_id'] = (int)$data['first_level'];
              }

             /*
                          if($CategoryArr){
                              $data['CategoryArr'] = $CategoryArr;
                          }
             */
             //判断分类
             if(!empty($data["fifth_level"])){
                 $parent_class = Business::parent_class($data['fifth_level']);
             }else if(!empty($data["fourth_level"])){
                 $parent_class = Business::parent_class($data['fourth_level']);
             }else if(!empty($data["third_level"])){
                 $parent_class = Business::parent_class($data['third_level']);
             }else if(!empty($data["second_level"])){
                 $parent_class = Business::parent_class($data['second_level']);
             }else if(!empty($data["first_level"])){
                 $parent_class = Business::parent_class($data['first_level']);
             }
              if($data['status'] != ''){
                  $where['status'] = (int)$data['status'];
              }else{
                  $where['status'] = array('neq',2);
              }
              if(!empty($data['startTime']) && !empty($data['endTime'])){
                  $where['addtime'] = array(array('>=',strtotime($data['startTime'])),array('<=',strtotime($data['endTime'])));
              }
             if($parent_class){
                 $this->assign(['parent_class'=>$parent_class,]);
             }
         }else{
            $where['status'] = array('neq',2);
             // $classList = Db::name(P_CLASS)->where(['pid'=>0])->select();
         }

         $first_class = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>0,'type'=>1])->select();
         $list = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->where($where)->order('addtime','desc')->paginate(20);
         // dump($list->items());
         $this->assign(['list'=>$list->items(),'where'=>$where,'page'=>$list->render(),'classList'=>$first_class,'data'=>$data]);
         return view();
    }
    /**
     * 获取绑定属性及品牌分类
     * [eidtBrandAttribute description]
     * @return [type] [description]
     * @author: Wang addtime 2018-06-30
     */
    public function eidtBrandAttribute(){

        $id = input('id');//dump($id);
        $where['status'] = array('neq',0);
        $first_class = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>0,'type'=>1])->select();
//        $brand       = Db::connect("db_mongo")->name(BRANDS)->field('BrandId,BrandName,Sort')->select();
//        $attribute   = Db::connect("db_mongo")->where($where)->name(MOGOMODB_ATTRIBUTE)->select();

// if($product_class = redis_get(REDIS_PRODUCT_CLASS)){//dump(REDIS_PRODUCT_CLASS);dump($product_class);
//     $product_class = json_decode($product_class,true);
// }else{
//     $product_class = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->field(array('id'=>true,'title_cn'=>true,'title_en'=>true,'_id'=>false,'type'=>1))->select();
//     redis_set(REDIS_PRODUCT_CLASS,json_encode($product_class,true),60*60*24);
// }
        //id存在时为修改
        if($id){
            $select_class = Business::parent_class_mongo($id);
            $brand_attribute = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->where(['_id'=>(int)$id])->find();//dump($brand_attribute);
            //组合已选择的属性和品牌
            if($brand_attribute){
               $list_data = $this->group_brand_attribute($brand_attribute);
            }else{
               echo json_encode(array('code'=>100,'result'=>'获取数据时出错'));
               exit;
            }
            $this->assign(['select_class'=>$select_class,'brand'=>$brand,'attribute'=>$attribute,'id'=>$id,'list_data'=>$list_data,'brand_attribute'=>$brand_attribute]);
        }else{
            $this->assign(['first_class'=>$first_class,'brand'=>$brand,'attribute'=>$attribute]);
        }
        return view();
    }
    /**
     * 组合绑定的属性值和品牌
     * [group_brand_attribute description]
     * @return [type] [description]
     * @author: Wang addtime 2018-07-02
     */
    public function group_brand_attribute($data=array()){
        $html_brand     = '';
        $html_attribute = '';
        if($data["product_brand"]){
            foreach ($data["product_brand"] as $key => $value) {
                 //<label><em>*</em>属性：</label>
                 $html_brand .= '<dl class="c-h-dl-validator form-group clearfix delete'.$key.'">';
                 $html_brand .= '<dd  class="v-title"><label><em></em></label></dd><dd><div class="input-icon right">';
                 $html_brand .= '<input type="hidden" name="brand['.$key.'][id]" value="'.$key.'">';
                 $html_brand .= '<input value="'.$value["brand_name"].'" readonly="readonly" name="brand['.$key.'][name]" class="form-control input-medium fl w100" type="text">';
                  $html_brand .= '排序：<div style="" class="input-icon right inline-block inline_block brand_sort"><input value="'.$value["sort"].'" name="brand['.$key.'][sort]" class="form-control input-val-1 w100" type="text"></div></div></dd>';
                 $html_brand .= '<a class="eliminate-btn2 relative top5 eliminate'.$key.'" onclick="delect_brand('.$key.')" href="javascript:;">删除</a>';
                 $html_brand .= '<dt></dt></dl>';
            }
        }
        if($data["attribute"]){
            foreach ($data["attribute"] as $k => $v) {
                //<label><em></em></label>
                 $html_attribute .= '<dl class="c-h-dl-validator form-group clearfix delete_attribute'.$k.'"><dd class="v-title"><label><em></em></label></dd>';
                 $html_attribute .= '<dd><div class="input-icon right">';
                 $html_attribute .= '<input type="hidden" name="attribute['.$k.'][id]" value="'.$k.'">';
                 $html_attribute .= '<input value="'.$v["title_cn"].'" readonly="readonly" name="attribute['.$k.'][name]" id="input-color-en" class="form-control input-medium fl w200" type="text">';
                 $html_attribute .= ' 排序：<div style="" class="input-icon right inline-block inline_block sort"><input value="'.$v["sort"].'" name="attribute['.$k.'][sort]" class="form-control input-val-1 w100" type="text"></div></div></dd>';
                 $html_attribute .= '<a class="eliminate-btn2 relative top5 eliminate'.$k.'" onclick="delect_attribute('.$k.')" href="javascript:;">删除</a>';
                 $html_attribute .= '<dt></dt></dl>';
            }
        }
        return array('brand'=>$html_brand,'attribute'=>$html_attribute);
    }
    /**
     * 编辑绑定
     * [eidtBrandAttribute description]
     * @return [type] [description]
     * @author: Wang addtime 2018-06-30
     */
    public function bindingBrandAttribute(){
          if($data = request()->post()){
              if(!empty($data['fourth_level_mongo'])){
                  $catalog = $data['fourth_level_mongo'];
              }else if(!empty($data['third_level_mongo'])){
                  $catalog = $data['third_level_mongo'];
              }else if(!empty($data['second_level_mongo'])){
                  $catalog = $data['second_level_mongo'];
              }else if(!empty($data['first_level_mongo'])){
                  $catalog = $data['first_level_mongo'];
              }else{
                   echo json_encode(array('code'=>100,'result'=>'分类必须选'));
                   exit;
              }

              if($data['status'] == 1 || $data['status'] == 0){
                 $status = $data['status'];
              }else{
                 echo json_encode(array('code'=>100,'result'=>'状态必须选'));
                 exit;
              }
              //获取分类名
              $list = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$catalog])->field(array('title_en'=>true,'title_cn'=>true,'id'=>true,'_id'=>false,'type'=>1))->find();
              //TODO 这个命名很危险，注意与$data = request()区别
              $date['title_cn'] = $list['title_cn'];
              $date['title_en'] = $list['title_en'];
              $date['_id']     = $list['id'];
              $date['status']  = (int)$status;

              //获取品牌值
              if($data["brand"]){
                   foreach ((array)$data["brand"] as $key => $value) {
                        if($value['id']){
                            $brand = Db::connect("db_mongo")->name(BRANDS)->where(['BrandId'=>(int)$value['id']])->field(array('BrandName'=>true,'Brand_Icon_Url'=>true,'Sort'=>true,'id'=>true,'Introduction'=>true,'_id'=>false))->find();
                            if($brand){
                                $date['product_brand'][$value['id']]['brand_icon_url'] = $brand['Brand_Icon_Url'];
                                $date['product_brand'][$value['id']]['brand_name']     = $brand['BrandName'];
                                //此字段无须更新到商城库的类别属性表
                                //$date['product_brand'][$value['id']]['introduction']   = $brand['Introduction'];
                                $date['product_brand'][$value['id']]['id']             = $value['id'];
                                $date['product_brand'][$value['id']]['sort']           = $value['sort'];
                            }else{
                                echo json_encode(array('code'=>100,'result'=>'品牌表找不到'.$value['name']));
                                exit;
                            }

                        }else{
                            echo json_encode(array('code'=>100,'result'=>'品牌数据存在为空'));
                            exit;
                        }
                   }
              }

              //获取属性值
              if($data["attribute"]){
                   foreach ((array)$data["attribute"] as $k => $v) {
                        if($v['id']){
                           $attribute = Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->where(['_id'=>(int)$v['id']])->field(array('_id'=>false))->find();
                           if($attribute){
                               $i = 1;
                               foreach ((array)$attribute['attribute'] as $key => $value) {
                                   $attribute['attribute'][$key]['id'] = $v['id'].'_'.$i;
                                   $attribute['attribute'][$key]['name'] = $value['title_cn'];
                                   $i++;
                               }
                               $date['attribute'][$v['id']] = $attribute;
                               $date['attribute'][$v['id']]['id'] = (int)$v['id'];
                               $date['attribute'][$v['id']]['sort'] = $v['sort'];
                               $date['attribute'][$v['id']]['attribute_value']  = $attribute['attribute'];
                               unset($date['attribute'][$v['id']]['attribute']);
                           }else{
                               echo json_encode(array('code'=>100,'result'=>'属性表没有'.$v['name']));
                               exit;
                           }

                        }else{
                            echo json_encode(array('code'=>100,'result'=>'属性值存在为空'));
                            exit;
                        }
                   }
              }//dump($date);exit;
              if($data["id"]){
                  if($date['_id'] != $data["id"]){
                       echo json_encode(array('code'=>100,'result'=>'分类不允许修改'));
                       exit;
                  }
                  $date['edittime'] = time($date);
                  unset($date['_id']);
                  if(empty($date["attribute"])){
                     $date["attribute"] = [];
                  }
                  if(empty($date["product_brand"])){
                     $date["product_brand"] = [];
                  }
                  $result = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->where(['_id'=>(int)$data["id"],])->update($date);
              }else{
                  $list = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->where(['_id'=>(int)$date["_id"],])->find();
                  if($list){
                      if($list['status'] == 2){
                          $date['edittime'] = time($date);
                          $data["id"] = $date['_id'];
                          unset($date['_id']);
                          $result = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->where(['_id'=>(int)$data["id"],])->update($date);
                      }else{
                          echo json_encode(array('code'=>100,'result'=>'该分类被绑定过，请去修改'));
                          exit;
                      }
                  }else{
                     $date['addtime'] = time();
                     $result = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->insert($date);
                  }

              }
              if($result){
                  echo json_encode(array('code'=>200,'result'=>'数据更新成功'));
                  exit;
              }else{
                  echo json_encode(array('code'=>100,'result'=>'数据更新失败'));
                  exit;
              }
          }
    }
    /**
     * 逻辑删除分类及绑定的数据值
     * [delete_binding description]
     * @return [type] [description]
     * @author: Wang addtime 2018-06-30
     */
    public function delete_binding(){
       if($data = request()->post()){
           $date['edittime'] = time();
           $date['status']   = 2;//状态1为正常使用，0 为禁用，2为删除
           $result = Db::connect("db_mongo")->name(MOGOMODB_B_A_LIST)->where(['_id'=>(int)$data["id"],])->update($date);
           if($result){
                echo json_encode(array('code'=>200,'result'=>'删除成功'));
                exit;
           }else{
                echo json_encode(array('code'=>200,'result'=>'删除失败'));
                exit;
           }
       }
    }
    /**
     * 修改属性状态
     * [attribute_status description]
     * @return [type] [description]MOGOMODB_ATTRIBUTE
     */
    public function attribute_status(){
         $id = input('id');
         $status = input('status');
         if($id){
            $data['status'] = (int)$status;
            $result = Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->where(['_id'=>(int)$id,])->update($data);
            if($result){
                return array('code'=>200,'result'=>'修改状态成功');
            }else{
                return array('code'=>100,'result'=>'修改状态失败');
            }
         }else{
              return array('code'=>100,'result'=>'获取数据异常');
         }

    }
    /**
     * 修改分类多语言
     * [eidt_class_lang description]
     * @return [type] [description]
     */
    public function eidt_class_lang(){
        if($data = request()->post()){
              $list = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$data['class_id'],])->field('Common,HumanTranslation')->find();
              $str = array();
              $status = false;
              if($data["Common"]){
                   foreach ($data["Common"] as $k => $v) {
                       if($list['Common'][$k] != $data["Common"][$k]['title_en']){
                           if(!empty($list['HumanTranslation']) && !in_array($k, $list['HumanTranslation'])){
                              $list['HumanTranslation'][] = $k;
                           }else if(empty($list['HumanTranslation'])){
                              $list['HumanTranslation'][] = $k;
                           }
                           $status = true;
                       }
                       $str['Common'][$k] = trim($v["title_en"]);
                   }
                   if($status == true){
                      $str['HumanTranslation'] = $list['HumanTranslation'];
                      $result =  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$data['class_id'],])->update($str);
                      if($result){
                          $history=array();
                          $history['EntityId'] = (int)$data['class_id'];
                          $history['CreatedDateTime'] = time();
                          $history['IsSync']   = false;
                          $history['Note']     = '多语言修改';
                          Db::connect("db_mongo")->name(MOGOMODB_P_CLASS_HISTORY)->insert($history);

                          echo json_encode(array('code'=>200,'result'=>'数据修改成功'),true);
                          exit;
                      }else{
                          echo json_encode(array('code'=>100,'result'=>'数据修改失败'),true);
                          exit;
                      }
                   }else{
                      echo json_encode(array('code'=>100,'result'=>'数据与原始数据一样'),true);
                      exit;
                   }
              }else{
                     echo json_encode(array('code'=>100,'result'=>'提交数据为空'),true);
                     exit;
              }
        }else{
            return view();
        }
    }
    /**
     * 获取分类多语言数据 MOGOMODB_B_A_LIST
     * [class_language_data description]
     * @return [type] [description]
     */
    public function class_language_data(){
         if($data = request()->post()){
              if($data['class_id']){
                  $result = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$data['class_id'],])->field('title_en,Common,pid')->find();
                  if($result && $result['Common']){
                      $html = '';
                      $class_name = $this->class_Father_level($result['pid']);
                      if($class_name){

                          foreach ($class_name as $k => $v) {
                             $html .= $v['title_en'].'->';
                          }
                          $html .= $result["title_en"];
                          $result['class_html'] = rtrim($html,'->');
                      }else{
                          $result['class_html'] = $result["title_en"];
                      }

                      echo json_encode(array('code'=>200,'result'=>$result),true);
                      exit;
                  }else{

                      echo json_encode(array('code'=>100,'result'=>'没有该ID的分类'));
                      exit;

                  }
              }

         }
    }
    /**
     * 获取上一级分类id
     * [class_Father_level description]
     * @return [type] [description]
     */
    public function class_Father_level($class_id,$data=array()){
         $class_name = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$class_id,])->field('title_en,id,pid')->find();
        // return  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->getLastSql();
        //  return $class_name;
         if($class_name){
              $data[] = array(
                 'id'=>$class_name['id'],
                 'title_en'=>$class_name['title_en'],
              );
              return  $this->class_Father_level($class_name['pid'],$data);
         }else{
              return $data;
         }
    }
    /**
     * 分类排序修改
     * [class_sort description]
     * @return [type] [description]
     * @author: Wang addtime 2018-10-11
     */
    public function class_sort(){
        if($data = request()->post()){
            if(empty($data['class_id']) || empty($data['val'])){
                echo json_encode(array('code'=>100,'result'=>'获取相关数据失败'));
                exit;
            }else if(!is_numeric($data['val']) || !is_numeric($data['class_id'])){
                echo json_encode(array('code'=>100,'result'=>'请输入整数'));
                exit;
            }
            $result = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$data['class_id'],])->update(['sort'=>(int)$data['val']]);
            if($result){
                echo json_encode(array('code'=>200,'result'=>'数据修改成功'));
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'数据修改失败'));
                exit;
            }
        }
    }
    /**
     * 分类邮编修改
     * [class_HSCode description]
     * @return [type] [description]
     */
    public function class_HSCode(){
         if($data = request()->post()){
            if(empty($data['class_id']) || empty($data['HSCode'])){
                echo json_encode(array('code'=>100,'result'=>'获取相关数据失败'));
                exit;
            }else if(!is_numeric($data['class_id'])){
                echo json_encode(array('code'=>100,'result'=>'获取ID出错'));
                exit;
            }
            $result = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$data['class_id'],])->update(['HSCode'=>$data['HSCode']]);
            if($result){
                echo json_encode(array('code'=>200,'result'=>'数据修改成功'));
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'数据修改失败'));
                exit;
            }
         }
    }

    /*SKU转SPU*/
    public function skuToSpu(){
        if(request()->post()){
            $post = input();
            $sku = $post['sku'];
            $spu = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',':',',',',',',',' ',',',',',','],$sku);
            $pattern = '/(,)+/i';
            $spu = preg_replace($pattern,',',$spu );
            $skus = explode(",", $spu);

            //var_dump($skus);die;
            if(!empty($skus)){
                $skus=array_unique($skus);
                if($post['status']==1){
                    $name='SKU';
                    $type='SPU';
                    $Export = $this->getSpu($skus);
                }else{
                    $name='SPU';
                    $type='SKU';
                    $Export = $this->getSku($skus);
                }
                //var_dump($Export);die;
                //导出文件
                $header_data =[$name=>$name,$type=>$type,'status'=>'status','Title'=>'产品标题'];
                $tool = new ExcelTool();
                $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
                /*
                if($res){
                    $res = implode("</br>",$res);
                }else{
                    $res =[];
                }
                $data['data']=$res;
                $data['type']=$type;
                return $data;*/

            }
            return $skus;
        }else{
            return $this->fetch();
        }
    }

    /*
     *导入excel,转换sku和spu之后,自动进行下载
     */
    public function excel(){
        try{
            //接受和处理数据
            $ExcelTool=new ExcelTool();
            $file=$_FILES['file']['tmp_name'];
            $data=$ExcelTool->importExcel($file);
            if(empty($data)){
                $this->error('excel没有数据');
                return;
            }
            //判断sku还是spu
            reset($data[0]);
            $name = key($data[0]);
            $Export=[];
            if($name=='SKU'){//如果是sky,进行数据处理
                $Export=$this->getSpuExcel($data);
                $two='SPU';
            }elseif($name=='SPU'){//如果是spu,进行数据处理
                $Export=$this->getSkuExcel($data);
                $two='SKU';
            }else{
                $this->error('无法判断sku还是spu');
                return;
            }
           if(!empty($Export)){
               //导出文件
               $header_data =[$name=>$name,$two=>$two,'status'=>'status'];
               $tool = new ExcelTool();
               $tool ->export(date('YmdHis'),$header_data,$Export,'sheet1');
           }else{
               $this->error('无法判断sku还是spu');
           }
      }catch(Exception $e){
            $this->error($e->getMessage());
      }
    }

    /*
   *获取SPU
   */
    private function getSpuExcel($data){
        foreach ($data as $key=>$vlaue){
            if(!empty($vlaue['SKU'])){
                $where['Skus.Code'] = (string)$vlaue['SKU'];
                $SPU = Db::connect("db_mongo")->name(PRODUCT)->where($where)->field('_id,ProductStatus')->find();
                if(!empty($SPU)&&is_array($SPU)){
                    $data[$key]['SPU']=$SPU['_id'];
                    $data[$key]['status']=$SPU['ProductStatus'];
                }
            }
        }
        return $data;
    }

    /*
    *获取SKU
    */
    private function getSkuExcel($data){
        $da=[];
        $ve=[];
        foreach ($data as $key=>$vlaue){
            if(!empty($vlaue['SPU'])){
                $SPU=$where['_id'] = (int)$vlaue['SPU'];
                $res = Db::connect("db_mongo")->name(PRODUCT)->where($where)->field('_id,Skus,ProductStatus,Title')->find();
                if(!empty($res)&&!empty($res['Skus'])){
                    $ProductStatus=$res['ProductStatus'];
                    $Title=$res['Title'];
                    $Skus=$res['Skus'];
                    foreach($Skus as $v){
                            if (!empty($v['Code'])) {
                                $ve['SPU']= $vlaue['SPU'];
                                $ve['SKU']= $v['Code'];
                                $ve['status'] =$ProductStatus;
                                $ve['Title']= $Title;
                                $da[]=$ve;
                            }
                    }
                }
            }
        }
        return $da;
    }

    /*
     *获取SPU
     */
    private function getSpu($data){
        $da=[];
        foreach ($data as $key=>$vlaue){
            if(!empty($vlaue)){
                $where['Skus.Code'] = (string)$vlaue;
                $SPU = Db::connect("db_mongo")->name(PRODUCT)->where($where)->field('_id,ProductStatus,Title')->find();
                if(!empty($SPU)&&is_array($SPU)){
                    $da[$key]['SKU']=$vlaue;
                    $da[$key]['SPU']=$SPU['_id'];
                    $da[$key]['status']=$SPU['ProductStatus'];
                    $da[$key]['Title']=$SPU['Title'];
                }
            }
        }
        return $da;
    }

    /*
    *获取SKU
    */
    private function getSku($data){
        $da=[];
        $ve=[];
        foreach ($data as $key=>$vlaue){
            if(!empty($vlaue)){
                $SPU=$where['_id'] = (int)$vlaue;
                $res = Db::connect("db_mongo")->name(PRODUCT)->where($where)->field('_id,Skus,ProductStatus,Title')->find();
                if(!empty($res)&&!empty($res['Skus'])){
                    $ProductStatus=$res['ProductStatus'];
                    $Title=$res['Title'];
                    $Skus=$res['Skus'];
                    foreach($Skus as $v){
                        if (!empty($v['Code'])) {
                            $ve['SPU']= $vlaue;
                            $ve['SKU']= $v['Code'];
                            $ve['status'] =$ProductStatus;
                            $ve['Title']= $Title;
                            $da[]=$ve;
                        }
                    }
                }
            }
        }
        return $da;
    }

    /**
     * 店铺查询
     * [ShopInquiries description]
     * @author: Wang addtime 2018-10-13
     */
    public function ShopInquiries(){
        $data = array();
        $list = [];
        $data = request()->post();
        if(!$data){
            $getdata = input();
            if(!empty($getdata['spu'])){
               $data['spu'] = $getdata['spu'];
            }else if(!empty($getdata['sku'])){
               $data['sku'] = $getdata['sku'];
            }

        }
        if($data){
            $where = array();
            $paginate = config('paginate');
            $page_size = $paginate['list_rows'];
           if($data['spu']){

               $spu = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',':',',',',',',',' ',',',',',','],$data["spu"]);
               $pattern = '/(,)+/i';
               $spu = preg_replace($pattern,',',$spu );
               $spu_array = explode(",", $spu);
               foreach ($spu_array as $k => $v) {
                 $where[] = (int)$v;
               }

               $data['sku'] = NULL;
               $spuWhere['_id'] = ['in',$where];
               $spuWhere['ProductStatus'] = ['in',[1,5]];
               $list = Db::connect("db_mongo")->name(PRODUCT)->where($spuWhere)->paginate($page_size,false,[
                                             'type' => 'Bootstrap',
                                             // 'page' => $page,
                                             // 'path' => $path,
                                             'query'=> ['spu'=>$spu]
                                                  ]);
               $list_items = $list->items();
               $list_render = $list->render();
           }
           if($data['sku']){
               $sku = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',':',',',',',',',' ',',',',',','],$data['sku']);
               // dump($spu);
               $pattern = '/(,)+/i';
               $spu = preg_replace($pattern,',',$sku );
               $spu_array = explode(",", $sku);
               foreach ($spu_array as $k => $v) {
                 if($v){
                    $where[] = (int)$v;
                 }
               }
               $skuWhere['Skus._id'] = ['in',$where];
               $skuWhere['ProductStatus'] = ['in',[1,5]];
               $list = Db::connect("db_mongo")->name(PRODUCT)->where($skuWhere)->paginate($page_size,false,[
                                             'type' => 'Bootstrap',
                                             // 'page' => $page,
                                             // 'path' => $path,
                                             'query'=> ['sku'=>$spu]
                                                  ]);//return $this->db->getLastSql();return $res;
               $list_items = $list->items();
               $list_render = $list->render();
           }
           $this->assign(['list'=> $list_items,'spu'=>$data['spu'],'sku'=>$data['sku'],'page'=>$list_render]);
        }

        // $this->assign(['list'=>$list->items(),'where'=>$where,'page'=>$list->render(),'classList'=>$first_class,'data'=>$data]);

        return view();
    }
    /**
     * 单个修改spu商铺ID
     * [edit_StoreID description]
     * @return [type] [description]
     */
    public function edit_StoreID(){
        if($data = request()->post()){
             if($data["StoreID"] && $data["spu"]){
                 $where['seller_id'] = $data["spu"].':'.$data["StoreID"];//dump($where);
                 $list = BaseApi::shop_name($where);//seller表获取商铺名
                 if($list['code'] != 200 || empty($list["data"])){
                    echo json_encode(array('code'=>100,'result'=>'没有找到对应的商铺数据'));
                    exit;
                 }else{
                     foreach ($list["data"] as $key => $value) {
                         $ShopInquiries[$value['id']] = $value['id'];
                         $ShopInquiries_name[$value['id']] = $value['true_name'];
                     }
                     $result = Db::connect("db_mongo")->name(PRODUCT)->where(['_id'=>(int)$data["spu"],])->update(['StoreID'=>(int)$data["StoreID"],'StoreName'=>$ShopInquiries_name[$data["StoreID"]]]);
                      if($result){
                          $data_histories = array();
                          $data_histories['EntityId'] = (int)$data["spu"];
                          $data_histories['IsSync']   = false;
                          $data_histories['IsSync']   = time();
                          $data_histories['Note']     = 'ip为'.$_SERVER["REMOTE_ADDR"].';用户名：'.Session::get('username').'ID:'.Session::get('userid').'修改了SPU:'.$data["spu"].'的店铺ID';
                          Db::connect("db_mongo")->name(MOGOMODB_P_HISTORIES)->insert($data_histories);
                          echo json_encode(array('code'=>200,'result'=>'数据修改成功'));
                          exit;
                      }else{
                        echo json_encode(array('code'=>100,'result'=>'存在失败数据：'.$Failure_record));
                        exit;
                      }
                 }

             }
        }
    }
    /**
     * 批量修改spu
     * [submit_ShopInquiries description]
     * @return [type] [description]
     * @author: Wang addtime 2018-10-13
     */
    public function submit_ShopInquiries(){
         if($data = request()->post()){
               $ShopInquiries =array();
               // $ShopInquiries = array(
               //    'DX-ERP'=>  333,
               //    'DX'=>   666,
               //    'DX-US'=>  777,
               //    'DX-916'=>   888,
               //    'DX-HongKong'=>  999,
               // );
               $Failure_record = '';
               $spu = str_replace(['，',';','：',"\n","\r\n","\r",'  ','/','\\'],[',',',',':',',',',',',',' ',',',','],$data["spu"]);
               $pattern = '/(,)+/i';
               $spu = preg_replace($pattern,',',$spu );
               $where['seller_id'] = $spu;//dump($where);exit;
               $list = BaseApi::shop_name($where);//seller表获取商铺名
              //dump($list);exit;
               if($list['code'] != 200 || empty($list["data"])){
                  echo json_encode(array('code'=>100,'result'=>'没有找到对应的商铺数据'));
                  exit;
               }else{
                   foreach ($list["data"] as $key => $value) {
                       $ShopInquiries[$value['id']] = $value['id'];
                       $ShopInquiries_name[$value['id']] = $value['true_name'];
                   }
               }
               $spu_ShopInquiries_array = explode(",", $spu);
               //数据检查
               foreach ($spu_ShopInquiries_array as $k => $v) {
                 $spu_array = array();
                 if($v){
                    $spu_array = explode(":", $v);
                    if(!in_array($spu_array[1],$ShopInquiries)){
                       echo json_encode(array('code'=>100,'result'=>'SPU:'.$spu_array[0].'中'.$spu_array[1].'商铺ID不存在'));
                       exit;
                    }
                 }
               }

               //修改
               foreach ($spu_ShopInquiries_array as $k => $v) {
                 $spu_array = array();
                 if($v){
                    $spu_array = explode(":", $v);
                    $result = Db::connect("db_mongo")->name(PRODUCT)->where(['_id'=>(int)$spu_array[0],])->update(['StoreID'=>(int)$spu_array[1],'StoreName'=>$ShopInquiries_name[$spu_array[1]]]);

                    if(!$result){
                        $Failure_record .= $spu_array[0].';';
                    }else{
                        $data_histories = array();
                        $data_histories['EntityId'] = (int)$spu_array[0];
                        $data_histories['IsSync']   = false;
                        $data_histories['IsSync']   = time();
                        $data_histories['Note']     = 'ip为'.$_SERVER["REMOTE_ADDR"].';用户名：'.Session::get('username').'ID:'.Session::get('userid').'修改了SPU:'.$spu_array[0].'的店铺ID';
                        Db::connect("db_mongo")->name(MOGOMODB_P_HISTORIES)->insert($data_histories);
                    }
                 }
               }
               if($Failure_record == ''){
                  echo json_encode(array('code'=>200,'result'=>'数据修改成功'));
                  exit;
               }else{
                  echo json_encode(array('code'=>100,'result'=>'存在失败数据：'.$Failure_record));
                  exit;
               }

         }
    }
    /*
     * 模糊查询品牌数据
     * @author: Wang
     * addtime 2018-10-13
     */
    public function AcquireBrand(){
        if($data = request()->post()){
            $where['BrandName'] = array('like',$data['BrandName']);
            $Brand_list = Db::connect("db_mongo")->name(BRANDS)->where($where)->field('BrandName,BrandId')->select();
//   echo Db::connect("db_mongo")->name(BRANDS)->getLastSql();
            echo json_encode(array('code'=>200,'result'=>$Brand_list));
            exit;
        }
    }
    /*
    * 模糊查询销售属性数据
    * @author: Wang
    * addtime 2018-10-13
    */
    public function AcquireAttribute(){
        if($data = request()->post()){
            $where['title_en'] = array('like',$data['title_en']);
            $where['status']   = 1;
            $Attribute_list = Db::connect("db_mongo")->name(MOGOMODB_ATTRIBUTE)->where($where)->field('title_en,_id,title_cn')->select();
//            echo Db::connect("db_mongo")->name(BRANDS)->getLastSql();
            echo json_encode(array('code'=>200,'result'=>$Attribute_list));
            exit;
        }
    }

    /**
     * 测试方法
     */
    public function delredis(){
      redis()->del(REDIS_PRODUCT_FIRST_CLASS);
    }

    // //测试方法
    // public function calss_name(){
    //         $a = file_get_contents('http://pdvee.320.io:9999/Api.php?p=dxApi&a=GetCatalogInfo&f=ProductCatalogClass');
    //         $b = json_decode($a,true);
    //         dump($b);
    // }

    // //测试方法
    // public function redis_set(){

    //      $data=array(
    //          '0'=>1,
    //          '2'=>2
    //       );
    //      dump(redis_set('weather',json_encode($data,true) ,20));
    //      // $redis->set('weather',json_encode($data,true) ,20);

    // }
    // public function redis_get(){
    //     dump(redis_get('weather'));
    //   // $redis = redis();
    //   //  dump($redis->get('weather'));
    // }
    /*SPU产品图片*/
    public function ProductImgList(){
            $data = request()->post();
            if(!$data){
                $getdata = input();
                if($getdata['spu']){
                    $data['spu'] = $getdata['spu'];
                }else if($getdata['sku']){
                    $data['sku'] = $getdata['sku'];
                }

            }
            if($data){
                $where = array();
                $page_size = 100;
                if($data['spu']){

                    $spu = str_replace(['，',';','；',"\n","\r\n","\r",'  ',' ','/','\\'],[',',',',':',',',',',',',' ',',',',',','],$data["spu"]);
                    // dump($spu);
                    $pattern = '/(,)+/i';
                    $spu = preg_replace($pattern,',',$spu );
                    $spu_array = explode(",", $spu);
                    foreach ($spu_array as $k => $v) {
                        $where[] = (int)$v;
                    }

                    $data['sku'] = NULL;
                    $spuWhere['_id'] = ['in',$where];
                    //$spuWhere['ProductStatus'] = ['in',[1,5]];
                    $list = Db::connect("db_mongo")->name(PRODUCT)->where($spuWhere)->field("_id,AddTime,StoreID,StoreName,ImageSet,Title")->paginate($page_size,false,[
                        'type' => 'Bootstrap',
                        // 'page' => $page,
                        // 'path' => $path,
                        'query'=> ['spu'=>$spu]
                    ]);
                    $list_items = $list->items();
                    $list_render = $list->render();
                }

                $this->assign(['list'=>$list,'spu'=>$data['spu'],'sku'=>$data['sku'],'page'=>$list_render]);
            }
            // $this->assign(['list'=>$list->items(),'where'=>$where,'page'=>$list->render(),'classList'=>$first_class,'data'=>$data]);

            return view();
        }

}
