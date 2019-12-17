<?php
namespace app\admin\model;

use \think\Session;
use think\Model;
use think\Db;
class Businessmanagement  extends Model
{

    public function profile(){
        return $this->hasOne('Profile','uid')->field('uid,truename,birthday,phone,address');
    }
    public function mongod(){
  echo  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>12])->delete((string)array('attribute'=>array('name'=>'sss')));
      // return  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>12])->update(array('attribute'=>array('name'=>'sss')));
    }

    /**
     * /
     * @param [type] $attribute [description]
     * 判断提交属性是否为空
     */
    public function AttributeJudge($attribute){
      //判断中文名是否为空
      if(!empty($attribute["title_cn"])){
          if($attribute["title_cn"] == '颜色'){
               if($attribute["title_en"] != 'Color'){
                   echo json_encode(array('code'=>100,'result'=>'颜色英文名请用Color'));
                   exit;
               }
          }
          $data['title_cn'] = htmlspecialchars($attribute["title_cn"]);
      }else{
          echo json_encode(array('code'=>100,'result'=>'中文名不能为空'));
          exit;
      }
      //判断英文名是否为空
      if(!empty($attribute["title_en"])){
          $data['title_en'] = htmlspecialchars($attribute["title_en"]);
      }else{
          echo json_encode(array('code'=>100,'result'=>'英文名不能为空'));
          exit;
      }

      if(!empty($attribute["show_type"])){
            $data['show_type'] = $attribute["show_type"];
      }else{
            echo json_encode(array('code'=>100,'result'=>'显示类型有误'));
            exit;
      }
      if(!empty($attribute["input_type"])){
            $data['input_type'] = $attribute["input_type"];
      }else{
            echo json_encode(array('code'=>100,'result'=>'输入方式有误'));
            exit;
      }
      if(!empty($attribute['customized_name'])){
           $data['customized_name'] = $attribute["customized_name"];
      }else{
           $data['customized_name'] = 0;
      }
      if(!empty($attribute['customized_pic'])){
           $data['customized_pic'] = $attribute["customized_pic"];
      }else{
           $data['customized_pic'] = 0;
      }
      if(!empty($attribute['is_color'])){
           $data['is_color'] = $attribute["is_color"];
      }else{
           $data['is_color'] = 0;
      }
      if(!empty($attribute['input_type'])){
           $data['input_type'] = $attribute["input_type"];
      }else{
           echo json_encode(array('code'=>100,'result'=>'请选择数据类型'));
           exit;
      }
      if(!empty($attribute['show_type'])){
           $data['show_type'] = $attribute["show_type"];
      }else{
           echo json_encode(array('code'=>100,'result'=>'请选择展示方式'));
           exit;
      }
      if(is_array($attribute["where"])){
          //判断数组是否存在为空的值
          foreach ($attribute["where"] as $k=> $v) {
              $t_cn =trim($v['title_cn']);
              $t_en =trim($v['title_en']);
              $t_value =trim($v['value']);
              $t_sort =trim($v['sort']);
              // if(empty($t_cn) || empty($t_en) || empty($t_value) || empty($t_sort)){
              //       echo json_encode(array('code'=>100,'result'=>'所提交属性值存在为空的值'));
              //       exit;
              // }
              foreach ($attribute["where"] as $key => $value) {
                  if($v['title_cn'] == $value['title_cn'] && $k !=$key ){
                      echo json_encode(array('code'=>100,'result'=>'属性值中文名称：'.$v['title_cn'].'重复'));
                      exit;
                  }
                  if($v['title_en'] == $value['title_en'] && $k !=$key ){
                      echo json_encode(array('code'=>100,'result'=>'属性值英文名称：'.$v['title_en'].'重复'));
                      exit;
                  }
                  // if($v['value'] == $value['value'] && $k !=$key ){
                  //      echo json_encode(array('code'=>100,'result'=>'属性值：'.$v['value'].'重复'));
                  //      exit;
                  // }
                  if($v['sort'] == $value['sort'] && $k !=$key ){
                      echo json_encode(array('code'=>100,'result'=>'排序值：'.$v['sort'].'重复'));
                      exit;
                  }
              }
          }
      }else{
          echo json_encode(array('code'=>100,'result'=>'属性值不能为空'));
          exit;
      }
      return $data;
    }


     /**递归数去子集完父级分类
     * [parent_class description]
     * @return [type] [description]
     */
    public function parent_class($id,$data = array()){
            $parent_id     =  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$id])->field('id,pid')->find();
            $product_class =  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$parent_id['pid']])->field('id,pid,title_cn,title_en')->select();
            if($product_class){
                 $product_class['select'] = $parent_id;
                 $data[] = $product_class;
                 if($parent_id['pid'] !=0){
                     return Businessmanagement::parent_class($parent_id['pid'],$data);
                 }
            }
            $sum = count($data);
            $select_data = array(
                '1'=>'first_level',
                '2'=>'second_level',
                '3'=>'third_level',
                '4'=>'fourth_level',
                '5'=>'fifth_level',
            );
            $html = '';
            $selected = '';
            $i = 1;
            foreach (array_reverse($data) as $k => $v) {
               $html .= '<select id="'. $select_data[$i].'" name="'. $select_data[$i].'" class="form-control input-small inline">';
               $html .='<option value="">请选择</option>';
               foreach ($v as $ke => $va) {
                if($ke != 'select'){
                   if($v["select"]["id"] == $va['id']){$selected = 'selected = "selected"';}
                   $html .= '<option '.$selected.' value ="'.$va['id'].'">'.$va['title_en'].'</option>';
                   $selected = '';
                }else if($ke == '0'){
                   if($v["select"]["id"] == $va['id']){$selected = 'selected = "selected"';}
                   $html .= '<option '.$selected.' value ="'.$va['id'].'">'.$va['title_en'].'</option>';
                   $selected = '';
                }
               }
               $html .= '</select>';
              $i++;
            }
            return $html;
    }

     /**
      * 递归数去子集完父级分类前段id重复是使用
     * [parent_class description]
     * @return [type] [description]
     */
    public function parent_class_mongo($id,$data = array()){
            $parent_id     =  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['id'=>(int)$id])->field('id,pid')->find();
            $product_class =  Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$parent_id['pid']])->field('id,pid,title_cn,title_en')->select();
            if($product_class){
                 $product_class['select'] = $parent_id;
                 $data[] = $product_class;
                 if($parent_id['pid'] !=0){
                     return Businessmanagement::parent_class_mongo($parent_id['pid'],$data);
                 }
            }
            $sum = count($data);
            $select_data = array(
                '1'=>'first_level_mongo',
                '2'=>'second_level_mongo',
                '3'=>'third_level_mongo',
                '4'=>'fourth_level_mongo',
                '5'=>'fifth_level_mongo',
            );
            $html = '';
            $selected = '';
            $i = 1;
            foreach (array_reverse($data) as $k => $v) {
               $html .= '<select id="'. $select_data[$i].'" name="'. $select_data[$i].'" class="form-control input-small inline">';
               $html .= '<option  value ="">请选择</option>';
               foreach ($v as $ke => $va) {
                if($ke != 'select'){
                   if($v["select"]["id"] == $va['id']){$selected = 'selected = "selected"';}
                   $html .= '<option '.$selected.' value ="'.$va['id'].'">'.$va['title_en'].'</option>';
                   $selected = '';
                }else if($ke == '0'){
                   if($v["select"]["id"] == $va['id']){$selected = 'selected = "selected"';}
                   $html .= '<option '.$selected.' value ="'.$va['id'].'">'.$va['title_en'].'</option>';
                   $selected = '';
                }
               }
               $html .= '</select>';
              $i++;
            }
            return $html;
    }
    public function BrandImg($data_val){

          $files = $_FILES;
          // if (!is_dir("upload/img/".$data_val['catalog_id'])){//当路径不穿在
          //       mkdir("upload/img/".$data_val['catalog_id']);//创建路径
          // }
          if (!is_dir("/upload/img/".$data_val['catalog_id'])){//当路径不穿在
                if(!mkdir("/upload/img/".$data_val['catalog_id'], 0777, true)){
                    echo ajaxReturn(100,'创建文件夹失败');//获取文件返回错误
                    exit;
                }//创建路径

          }
          $url = "/upload/img/".$data_val['catalog_id'].'/';
          $files["file"]["name"] = $data_val['catalog_id'].'_'.$data_val['brand_name'].".".'jpg';
          $url       = $url.$files["file"]["name"];
          // $url_50_25 = "upload/img/".time().'_50x25.jpg';
          if(!move_uploaded_file($_FILES["file"]["tmp_name"],$url)){
             echo json_encode(array('code'=>100,'result'=>'图片保存失败'));//获取文件返回错误
             exit;
          }
          $serverApi =  scoso();
          $catalog   =  Businessmanagement::makeDir($serverApi,'brandImage/'.$data_val['catalog_id']);
          $upload    =  ftp_put($serverApi,$files["file"]["name"],$url,FTP_BINARY);//上传到远程服务器

         // $val = Businessmanagement::imgsive($url,50,25,$url_50_25);//生成小图
          // if(!$val){
          //    echo json_encode(array('code'=>100,'result'=>'生成小图失败失败'));
          //    exit;
          // }

          $data_db['url_original_url'] = $data_val['url_original_url']  = '/'.$data_val['catalog_id'].'/'.$files["file"]["name"];
          // $data_db['brand_icon_url']   = $data_val['brand_icon_url']      = $url_50_25;
          return $data_db;
    }
    //缩略图
   // public function imgsive($srcfile,$width='',$height='',$filename = ""){
   //      $size=getimagesize($srcfile);
   //      switch($size[2]){
   //          case 1:
   //          $img=imagecreatefromgif($srcfile);
   //          break;
   //          case 2:
   //          $img=imagecreatefromjpeg($srcfile);
   //          break;
   //          case 3:
   //          $img=imagecreatefrompng($srcfile);
   //          break;
   //          default:
   //          exit;
   //      }
   //      //源图片的宽度和高度
   //      $srcw=imagesx($img);
   //      $srch=imagesy($img);
   //      //目的图片的宽度和高度
   //      if($size[0] <= $width || $size[1] <= $height){
   //          $dstw=$srcw;
   //          $dsth=$srch;
   //      }else{
   //      if($width <= 1 && $height <= 1){
   //          // $dstw=$rate;
   //          // $dsth=$rate;
   //          $dstw=floor($srcw*$width);
   //          $dsth=floor($srch*$height);
   //      }else {
   //          $dstw=$width;
   //          $rate=$height/$srcw;
   //          $dsth=floor($srch*$rate);
   //      }
   //      }
   //      //echo "$dstw,$dsth,$srcw,$srch ";
   //      //新建一个真彩色图像
   //      $im=imagecreatetruecolor($dstw,$dsth);
   //      $black=imagecolorallocate($im,255,255,255);
   //      imagefilledrectangle($im,0,0,$dstw,$dsth,$black);
   //      imagecopyresized($im,$img,0,0,0,0,$dstw,$dsth,$srcw,$srch);
   //      // 以 JPEG 格式将图像输出到浏览器或文件

   //      if($filename) {

   //      //图片保存输出
   //      $result = imagejpeg($im, $filename);
   //      $size   = filesize($filename);

   //      }else {
   //          //图片输出到浏览器
   //          imagejpeg($im);
   //      }
   //      //释放图片
   //      imagedestroy($im);
   //      imagedestroy($img);

   //      return $result;
   // }

    /**
     *判断端提交品牌是否为空
     */
    public function BrandButeJudge($data){
          $data_val = array();
          $files = $_FILES;
          $data_db = array();

          //判断分类
          if(!empty($data["fifth_level"])){
               $data_val['catalog_id'] = $data["fifth_level"];
          }else if(!empty($data["fourth_level"])){
               $data_val['catalog_id'] = $data["fourth_level"];
          }else if(!empty($data["third_level"])){
               $data_val['catalog_id'] = $data["third_level"];
          }else if(!empty($data["second_level"])){
               $data_val['catalog_id'] = $data["second_level"];
          }else if(!empty($data["first_level"])){
               $data_val['catalog_id'] = $data["first_level"];
          }else{
              echo json_encode(array('code'=>100,'result'=>'分类不能放开空'));
              exit;
          }

          if(empty($data["brand_name"]) || preg_match("/^[\x7f-\xff]+$/", $data["brand_name"])){
              echo json_encode(array('code'=>100,'result'=>'品牌名名称为空或者不是英文'));
              exit;
          }else{
             $data_db['brand_name']   =  $data_val['brand_name'] = $data["brand_name"];
          }

          if(empty($data["sort"])){
              echo json_encode(array('code'=>100,'result'=>'排序不能为空'));
              exit;
          }else{
              $data_val['sort'] = $data["sort"];
          }
          if ($files["file"]["error"] == 0){
              $data_db = Businessmanagement::BrandImg($data_val);
          }

          return array('data_db' => $data_db,'data_val'=>$data_val);

    }
    //删除nosql的销售属性
    public function deleteAttributeNosql($id){
          //status =2 逻辑删除
          $result =  Db::connect("db_mongo")->name("dx_attribute")->where(['_id'=>(int)$id])
                       ->update(['status'=>2,'edit_author'=>Session::get('username'),'edit_time'=>time()]);
          if($result){
              return true;
          }else{
              return false;
          }
    }
    //删除nosql品牌数据
    public function deleteBrandNosql($classId,$id){
          $result_content =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$classId])->field('product_brand')->find();
          if($result_content){
                unset($result_content["product_brand"][$id]);
                $result   =  Db::connect("db_mongo")->name("dx_brand_attribute")->where(['_id'=>(int)$classId])->update(['product_brand'=>(Object)$result_content["product_brand"],'edittime'=>time()]);
          }else{//nosql 不存在则默认为true
                $result   =   true;
          }
          if($result){
              return true;
          }else{
              return false;
          }
    }
    /**
     * [scoso description]
     * @return [type] [description]
     * ftp链接远程服务器
     */
  //   public function scoso(){
  //       $server = DX_FTP_SERVER_ADDRESS;
  //       $port = DX_FTP_SERVER_PORT;
  //       $user_name = DX_FTP_USER_NAME;
  //       $password = DX_FTP_USER_PSD;
  //       $conn_id = ftp_connect(SBN_FTP_SERVER_ADDRESS, SBN_FTP_SERVER_PORT);
  //       if (!$conn_id) {
  //           echo "Error: Could not connect to ftp. Please try again later.\n";
  //           return self::FAIL;
  //       }
  //       $login_result = ftp_login($conn_id, $user_name, $password);
  //       if (!$login_result) {
  //           echo "Error: Could not login to ftp. Please try again later.\n";
  //           return self::FAIL;
  //       }
  //       //SET FTP TO PASSIVE MODE
  //       $pasv_result = ftp_pasv($conn_id, TRUE);
  //       if (!$pasv_result) {
  //           return self::FAIL;
  //       }
  //       return $conn_id;
  // }


    /**
     * 创建目录并将目录定位到当请目录
     *
     * @param resource $connect 连接标识
     * @param string $dirPath 目录路径
     * @return mixed
     *       2：创建目录失败
     *       true：创建目录成功
     */
    public function makeDir($connect, $dirPath){
      //处理目录
      $dirPath = '/' . trim($dirPath, '/');
      $dirPath = explode('/', $dirPath);
      foreach ($dirPath as $dir){
        if($dir == '') $dir = '/';
        //判断目录是否存在
        if(@ftp_chdir($connect, $dir) == false){
          //判断目录是否创建成功
          if(@ftp_mkDir($connect, $dir) == false){
            return 2;
          }
          @ftp_chdir($connect, $dir);
        }
      }
      return true;
    }

}