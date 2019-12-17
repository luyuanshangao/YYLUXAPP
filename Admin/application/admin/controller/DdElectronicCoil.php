<?php
namespace app\admin\controller;

use think\Log;
use think\View;
use think\Controller;
use think\Db;
use \think\Session;
use think\Paginator;
use app\admin\dxcommon\Mongo;

/*
 * DD电子卷管理
 * AddTime:2018-10-22
 * author: Wang
 *
 */
class DdElectronicCoil extends Action
{
  	public function __construct(){
         Action::__construct();
         define('ALECTRONIC_COIL', 'dx_electronic_coil');//mongode 点券表

  	}
  	/*
  	 * DD电子卷管理列表
  	 * author: Wang
  	 * AddTime:2018-10-22
  	 */
  	public function ElectronicCoilList(){
        $page_size = config('paginate.list_rows');
        $date = array();
        $data = array();
  	    if($data = request()->post()){
  	        if($data['ElectronicCoil_ID']){
                $date['ElectronicCoil_ID'] = $data['ElectronicCoil_ID'];
            }
            if($data['startTime'] && $data['endTime']){
                $startTime = strtotime($data['startTime']);
                $endTime   = strtotime($data['endTime']);
                $date['add_time'] = array('between',[$startTime,$endTime]);
            }
            if($data['status']){
                $date['status'] = (int)$data['status'];
            }
            foreach((array)$data as $k=>$v){
                if(empty($v)){
                    unset($data[$k]);
                }
            }
            if($date){
                $list = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->where($date)->order('add_time desc')->paginate($page_size,false,[
//                    'page' => $page,
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
            }else{
                $list = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->order('add_time desc')->paginate($page_size,false,[
                    'type' => 'Bootstrap',
                     //   'query'=> $data
                ]);
            }

            $list_items  = $list->items();
            $list_render = $list->render();
        }else{
            $data = input();
            foreach($data as $key=>$val){
                if(empty($val)){
                    unset($data[$key]);
                }
            }
            if(!empty($data['status'])){
                $date['status'] = (int)$data['status'];
            }
            if(!empty($data['ElectronicCoil_ID'])){
                $date['ElectronicCoil_ID'] = $data['ElectronicCoil_ID'];
            }
            if(!empty($data['page'])){
                $page = $data['page'];
            }
            if($data['startTime'] && $data['endTime']){
                $startTime = strtotime($data['startTime']);
                $endTime   = strtotime($data['endTime']);
                $date['add_time'] = array('between',[$startTime,$endTime]);
            }

            if(empty($date)){
                $list = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->order('add_time desc')->paginate($page_size,false,[
                    'page' => $page,
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
            }else{
                $list = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->where($date)->order('add_time desc')->paginate($page_size,false,[
                    'page' => $page,
                    'type' => 'Bootstrap',
                    'query'=> $data
                ]);
            }

            $list_items  = $list->items();
            $list_render = $list->render();
        }
        $this->assign(['list'=>$list_items,'page'=>$list_render,'data'=>$data]);
        return View();
    }
    /*
     * 添加DD电子卷管理列表
     * author: Wang
     * AddTime:2018-10-22
     */
    public function addElectronicCoil(){
        if($data = request()->post()){
            $where = array();
            $j = 0;
            if(is_numeric ($data['roll_sum']) && $data['roll_sum']>0){
                for($i =1;$i<=$data['roll_sum'];$i++){
                     $sum = '';
                     // $sum = mt_rand(0000000000,99999999999);
                     //$sum = mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9);
                     $sum = $this->createRandomStr(11);
                     $where['status'] = 1;
                     $where['user_id'] = 0;
                     $where['ElectronicCoil_ID'] = $sum;
                     $where['add_author'] = Session::get('username');
                     $where['add_time']   = time();
                     $resultInspectNumber = $this->InspectNumber($val='');
                     if($resultInspectNumber == 1){
                         $result_retry = $this->retry($where);
                         if($result_retry === true){
                             $j++;
                         }
                     }else{
                         $electronicCoil = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->where(['ElectronicCoil_ID'=>$sum])->field('ElectronicCoil_ID')->find();
                         if(!$electronicCoil){
                             $result = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->insert($where);
                             if(!$result){
                                 $result_retry = $this->retry($where);
                                 if($result_retry === true){
                                     $j++;
                                 }
                             }else{
                                 $j++;
                             }
                         }else{
                             $result_retry = $this->retry($where);
                             if($result_retry === true){
                                 $j++;
                             }
                         }
                     }
                }
                echo json_encode(array('code'=>200,'result'=>'添加了'.$j.'条数据'));
                exit;
            }else{
                echo json_encode(array('code'=>100,'result'=>'提交空数据有误'));
                exit;
            }
        }else{
            echo json_encode(array('code'=>100,'result'=>'不能提交空数据'));
            exit;
        }
    }
    /*
     * 添加DD电子卷管理失败重试
     * author: Wang
     * AddTime:2018-10-22
     */
    public function retry($where){
        $sum = '';
        while(true){
            //$sum = mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9).mt_rand(0, 9);
            $sum = $this->createRandomStr(11);
            $result = $this->InspectNumber($val='');
            if($result != 1){
                $where['ElectronicCoil_ID'] = $sum;
                $electronicCoil = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->where(['ElectronicCoil_ID'=>$sum])->field('ElectronicCoil_ID')->find();
                if(!$electronicCoil){
                    $result = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->insert($where);
                    if($result){
                        break;
                    }
                }
            }
        }
        return true;
    }
    /*
     * 修改DD电子卷管理状态
     * author: Wang
     * AddTime:2018-10-22
     */
    public function ElectronicCoilStatus(){
        if($data = request()->post()){
            if(!empty($data['status']) && !empty($data['electronicCoil_id'])){
                $result = Db::connect("db_mongo")->name(ALECTRONIC_COIL)->where(['ElectronicCoil_ID'=>$data['electronicCoil_id']])->update(['status'=>(int)$data['status'],'edit_time'=>time(),'edit_author'=>Session::get('username')]);
                if($result){
                    echo json_encode(array('code'=>200,'result'=>'数据修改成功'));
                    exit;
                }else{
                    echo json_encode(array('code'=>100,'result'=>'数据修改失败'));
                    exit;
                }
            }
        }
    }
    /* 1代表false,2代表true
     * 检查11个数是否都是同一个数字
     * author: Wang
     * AddTime:2018-10-23
     */
    public function InspectNumber($val=''){
//        $val = '99999999999';
        $str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';//36个字符
        for($i=0;$i<=36;$i++){
            $character = substr($str,$i,1);
            $sum = @substr_count($val,$character);
            if($sum == 11){
                return 1;
            }
        }
        return 2;
    }
    /*
     * 随机生成11位随机码
     * author: Wang
     * AddTime:2018-10-23
     */
    public function createRandomStr($length=''){
//        $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';//62个字符
        $str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';//36个字符
        $strlen = 36;
        while($length > $strlen){
            $str .= $str;
            $strlen += 36;
        }
        $str = str_shuffle($str);
        return substr($str,0,$length);
    }
}