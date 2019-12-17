<?php
namespace app\admin\controller;

use app\admin\dxcommon\XmlTool;
use app\admin\model\CrontabModel;
use app\admin\model\OrderModel;
use app\admin\model\OrderStatisticsModel;
use app\admin\services\ProductService;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use app\admin\dxcommon\BaseApi;
use think\Log;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\ExcelTool;
use app\admin\model\Affiliate as AffiliateModel;
//use app\admin\controller\Tool;

//datafeed上传目录
defined('datafeed_base_upload_dir') or define('datafeed_base_upload_dir','/data/public_uploads/admin/uploads/datafeed/');

/**
 * 员工管理
 */
class DataQuery extends Action
{
	public function __construct(){
       Action::__construct();
       define('MOGOMODB_PRODUCT', 'dx_product');//产品表
       define('MOGOMODB_SHIPPING', 'dx_shipping_cost');//运费模板表
  }
  public function FreightQuery(){
       // $data['ShippingServiceID'] = '';
       $p = input('page');
       $data = request()->post();
       if($data || $p){
        // dump($data['spu']);
        if(empty($data) && $p){
              $data = Session::get('freightpage');
        }else{
             Session::set('freightpage',$data);
        }

        // $username = Session::get('username');
        // dump($data);
        $error = '';
        if(!$data['spu']){
           $error .= 'SPU不能为空;';
        }
        if($data['sum'] <= 0 && $data['sum'] != null){
           $error .= '数量必须是大于零同时必须是整数';
        }else if(!is_numeric($data['sum']) && $data['sum'] != null){
           $error .= '数量必须是数字类型';
        }
        if($error !=''){
           $this->assign(['error'=>$error,]);
           return View('FreightQuery');
        }

        $price = '';
        $list_weight = Db::connect("db_mongo")->name(MOGOMODB_PRODUCT)->where(['_id'=>(int)$data['spu']])->field('PackingList.Weight')->find();
        $GOODSWEIGHT = $list_weight["PackingList"]["Weight"];
        if($data['country']){
           $where['ToCountry'] = $data['country'];
        }
        // if($data['ShippingServiceID']){
        //    $where['ShippingCost.ShippingServiceID'] = $data['ShippingServiceID'];
        // }

        if($data['sum'] && $data['sum'] > 0){
            $GOODSWEIGHT = $GOODSWEIGHT * $data['sum'];
        }

        $where['ProductId'] = (string)$data['spu'];
        $page_size = config('paginate.list_rows');
        $list = Db::connect("db_mongo")->name(MOGOMODB_SHIPPING)->where($where)->field('ProductId,ShippingCost,ToCountry')->paginate($page_size);
        // echo  Db::connect("db_mongo")->name(MOGOMODB_SHIPPING)->getLastSql();
        $list_product = $list->items();

        foreach ($list_product as $k => $v) {
            foreach ($v["ShippingCost"] as $ke => $ve) {
                  // dump($ve["LmsRuleInfo"]);
                 $calculation_formula = '<?php '.htmlspecialchars_decode($ve["LmsRuleInfo"]).' ?>';
                 eval( '?>'.$calculation_formula);
                 $price = round($price, 2);
                 $list_product[$k]["ShippingCost"][$ke]['freight'] = $price;
            }
        }
        $page = $list->render();
       }
        // $ShippingServiceMethod = $this->dictionariesQuery('ShippingServiceMethod');dump($ShippingServiceMethod);
        // $ShippingServiceMethodHtml = $this->outSelectHtml($ShippingServiceMethod,'ShippingServiceID',$data['ShippingServiceID']);dump($ShippingServiceMethodHtml);

       // $list = Db::connect("db_mongo")->name(MOGOMODB_PRODUCT)->where(['_id'=>(int)$classid['id']])->select();
       // $ShippingServiceMethodHtml;
       $this->assign(['list_product'=>$list_product,'page'=>$page,]);
       return View('FreightQuery');
  }
  /**
   * 输出SelectHtml
   * @param array $dict
   * @param string $selectedValue
   * @return string select选择器的HTML
   */
  public function outSelectHtml(array $dict,$selectName='',$selectedValue=''){dump($selectedValue);
    $outHtml ='<select name="'.$selectName.'" id="'.$selectName.'" class="form-control input-small inline">';
    $outHtml .='<option value="">请选择</option>';
    if(!empty($dict)){
      foreach ($dict as $key => $value){
        if(count($value) ==2){
          $isSelected='';
          if($value[0] == $selectedValue){
            $isSelected =' selected = "selected" ';
          }
          $outHtml .='<option '.$isSelected . ' value="'.$value[0].'">'.$value[1].'</option>';
        }
      }
    }
    $outHtml .='</select>';
    return $outHtml;
  }

  /**
    *  sql查询页面
  */
  public function sqlQuery(){

    return View();
  }

  /**
    *  sql查询结果导出
  */
  public function sqlQueryExport(){

    $sql = trim(input('sql'));
    if(!empty($sql)){

      $sql = strtolower($sql);
      if( strpos($sql, 'select')===0 ){

        if( strpos($sql, 'limit')===false )$sql;//.=' limit 10000';

        try{

          set_time_limit(60);
          $db_order = Db::connect("db_order");
          $data = $db_order->query($sql);
          $columns = array();
          if(!empty($data)){

            foreach($data[0] as $k=>$v)$columns[$k] = $k;

            $tool = new ExcelTool();
            $tool ->export(date('YmdHis'),$columns,$data,'sheet1');
          }else{

            echo 'No results found.';
          }

        }catch(\Exception $e) {

          echo $e->getMessage();
        }
      }else{
        echo 'Select required.';
      }
    }else{
      echo 'Sql required.';
    }
    exit();
  }

    /**
     * 获取跟踪号节点
     * [package_number description]
     * @return [type] [description]
     * author  Wang
     * add_time 2018-12-14
     */
    public function LogisticsNode(){
        $post = input('');
        if(isset($post['tracking_number'])&&!empty($post['tracking_number'])){
            $where['tracking_number'] = $post['tracking_number'];
            $result = BaseApi::AdminLisLogisticsDetails($where);
            return json($result);
        }
        return View();
    }


    /**
     * 下载任务列表
     * @return View
     */
    public function DataFeed(){
        $model = new CrontabModel();
        $params = request()->post();
        if(!empty($params['platform']) && request()->isPost()){
            $error = '';
            //判断是否有产品数据
            $service = new ProductService();
            $params['page_size'] = 10;//查询是否有数据
            $data  = $service->getDataFeed($params);
            if(empty($data)){
                $error .= '无产品数据可下载.';
            }
            if($error !=''){
                $this->assign(['error'=>$error]);
                return View('DataFeed');
            }
            $model->createCrontab($params);
        }
        $list = $model->selectCrontab();
        $list_data = $list->items();
        $page = $list->render();
        $this->assign(['list'=>$list_data,'page'=>$page]);
        $this->assign(['error'=>'']);
        return View('DataFeed');
    }

    /**
     * 下载任务删除
     * @return \think\response\Json
     */
    public function DataFeedDelete(){
        $params = request()->post();
        if(empty($params['id'])){
            return json(['code'=>1002,'result'=>'数据有误']);
        }

        $model = new CrontabModel();
        $ret  = $model->deleteCrontab($params['id']);
        if($ret){
            return json(['code'=>200,'result'=>'删除成功']);
        }else{
            return json(['code'=>1002,'result'=>'删除失败']);
        }
    }

    /**
     * 文件下载
     * @return \think\response\Json
     */
    public function DataFeedDownload(){
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '0');
        $params = input();
        if(empty($params['id'])){
            return json(['code'=>1002,'result'=>'数据有误']);
        }
        $model = new CrontabModel();
        $data = $model->findCrontab($params['id']);
        if(empty($data)){
            return json(['code'=>1002,'result'=>'数据有误']);
        }else{
            //线上之前下载的
            if($data['id'] < 367){
                $url = 'http://img.dxcdn.com';//线上
                $path = ROOT_PATH . 'public' . DS . 'uploads' . DS .strtolower($data['format']) . DS;
                $filename = $data['filename'].'_'.$data['id'].'.'.strtolower($data['format']);
                $tool = new ExcelTool();
                //下载本地
                $result  = $tool->getFile($url.$data['download_url'],$path,$filename,1);
                if(!empty($result['save_path'])){
                    $tool->down($path.$filename,$filename);
                    return json(['code'=>200,'result'=>'下载成功']);
                }
                return json(['code'=>200,'result'=>'下载失败']);
            }else{
                $filename = $data['filename'].'_'.$data['id'].'.zip';
                $filename = datafeed_base_upload_dir .$filename;
                if(!file_exists($filename)){
                    return json(['code'=>1002,'result'=>'文件不存在','filename'=>$filename]);
                }
                //下面是输出下载;
                header ( "Cache-Control: max-age=0" );
                header ( "Content-Description: File Transfer" );
                header ( 'Content-disposition: attachment; filename=' . basename ( $filename ) ); // 文件名
                header ( "Content-Type: application/zip" ); // zip格式的
                header ( "Content-Transfer-Encoding: binary" ); // 告诉浏览器，这是二进制文件
                header ( 'Content-Length: ' . filesize ( $filename ) ); // 告诉浏览器，文件大小
                @readfile ( $filename );//输出文件;
            }
        }
    }

    /*
     *统计国家和渠道
     */
    /*public function stati(){
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '0');
        $OrderModel=new OrderModel();
        $sql='SELECT country_code,country,pay_channel,count(*) number FROM `dx_sales_order`
              WHERE create_on>=1549497600
              AND order_master_number <> 0
              AND pay_channel <> ""
              GROUP BY country_code,pay_channel order by number desc';
        $data=$OrderModel->orderSql($sql);

        foreach($data as $key=>&$value){
            $where['country_code']=$value['country_code'];
            $where['pay_channel']=$value['pay_channel'];
            $where['order_status']=['in','200,400,407,500,600,900'];
            $where['create_on']=['>',1549497600];
            $where['order_master_number']=['<>',''];
            $Count=$OrderModel->getCount($where);
            $value['count']=$Count;
        }

        $columns = array();
        if(!empty($data)){
            foreach($data[0] as $k=>$v)$columns[$k] = $k;
            $tool = new ExcelTool();
            $tool ->export(date('YmdHis'),$columns,$data,'sheet1');
        }else{

            echo 'No results found.';
        }
    }*/

    /**
     * 统计
     * @return View
     */
    public function statistics(){
        $model = new OrderStatisticsModel();
        $OrderModel=new OrderModel();
        $params = request()->post();
        $where=[];
        if(!empty($params['startTime']) && !empty($params['endTime'])) {
            $where['add_time'] = array(array('egt', strtotime('-1 day',strtotime($params['startTime']))), array('elt', strtotime($params['endTime'])));
        }
        $page_size = 20;
        $list=$model->where($where)->order('add_time desc')->paginate($page_size);
        $list_data = $list->items();
        foreach($list_data as &$value){
            if(!empty($value['order_num'])){
                $amount=$value['order_amount']/$value['order_num'];
                $amount=round($amount,2);
                $value['order_amount']=number_format($value['order_amount']);
                $value['order_num']=number_format($value['order_num']);
            }else{
                $amount=0;
            }

            if(!empty($value['day_order_num'])){
                $day_amount=$value['day_order_amount']/$value['day_order_num'];
                $day_amount=round($day_amount,2);
                $value['day_order_amount']=number_format($value['day_order_amount']);
                $value['day_order_num']=number_format($value['day_order_num']);
            }else{
                $day_amount=0;
            }
            /*
            $value['day_order_num']=0;
            $value['day_order_amount']=0;

            if(!empty($value['add_time'])){
                $yesterday=$value['add_time'];
                $time= strtotime("+1 day", $yesterday);
                $sql="SELECT COUNT(*) AS order_num,SUM(captured_amount_usd) AS order_amount FROM `dx_sales_order` WHERE  ( `create_on` >= ".$yesterday." AND `create_on` < ".$time." AND `order_status` IN (400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000) AND `order_master_number` <> '0')
OR ( `create_on` >= ".$yesterday." AND `create_on` < ".$time." AND `order_status` =120  AND `order_branch_status` =105 AND `order_master_number` <> '0')";
                $da=$OrderModel->orderSql($sql);
                if(!empty($da[0])){
                    $order_data=$da[0];
                    $value['day_order_num']=!empty($order_data['order_num'])?$order_data['order_num']:0;
                    $value['day_order_amount']=!empty($order_data['order_amount'])?$order_data['order_amount']:0;
                }
            }*/
            $value['amount']=$amount;
            $value['day_amount']=$day_amount;
        }

        $page = $list->render();
        $startTime = date("Y-m-d H:i:s",strtotime('-3 month'));
        $endTime   = date("Y-m-d H:i:s",time());
        $this->assign("startTime",$startTime);
        $this->assign("endTime",$endTime);
        $this->assign(['list'=>$list_data,'page'=>$page]);
        $this->assign(['error'=>'']);
        return $this->fetch();
    }
}