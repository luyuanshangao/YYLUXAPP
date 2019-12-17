<?php
namespace app\admin\controller;
use \think\Session;
use app\admin\dxcommon\BaseApi;
use think\Db;
use app\admin\dxcommon\ExcelTool;

/*
 * EIP报表
 * add by 2019-06-17 kevin
 * */
class EipReport  extends Action
{
     public function __construct(){
       Action::__construct();
       define('MOGOMODB_P_CLASS', 'dx_product_class');
    }

    /*
     * SKU选品报表
     * add by 2019-06-18 kevin
     * */
    public function skuSelection()
    {
        $baseApi = new BaseApi();
        $data = request()->post();
        $countryList = $baseApi::getRegionData_AllCountryData();
        /*是否提交查询条件*/
        if(!empty($data)){
            $error_msg = "";
            /*销售时间*/
            if(!empty($data['saleStartTime']) && !empty($data['saleEndTime'])){
                $param_product['saleStartTime'] = $data['saleStartTime'];
                $param_product['saleEndTime'] = $data['saleEndTime'];
            }else{
                $error_msg = "销售起止时间不能为空";
            }
            /*国家*/
            if(!empty($data['country'])){
                $param_product['country_code'] = explode(",",$data['country']);
            }else{
                $error_msg = "国家不能为空";
            }

            /*产品上架时间*/
            if(!empty($data['shelfStartTime'])){
                $param_product['shelfStartTime'] = $data['shelfStartTime'];
            }
            if (!empty($data['shelfEndTime'])){
                $param_product['shelfEndTime'] = $data['shelfEndTime'];
            }
            /*产品一级分类*/
            if(!empty($data['oneCategory'])){
                $param_product['first_category_id'] = (int)$data['oneCategory'];
            }
            /*产品二级分类*/
            if(!empty($data['twoCategory'])){
                $param_product['second_category_id'] = (int)$data['twoCategory'];
            }
            /*产品三级分类*/
            if(!empty($data['threeCategory'])){
                $param_product['third_category_id'] = (int)$data['threeCategory'];
            }
            /*产品关键字*/
            if(!empty($data['productKeyWord'])){
                $param_product['keyword'] = $data['productKeyWord'];
            }
            /*排名*/
            if(!empty($data['saleRank'])){
                $param_product['sale_rank'] = $data['saleRank'];
            }else{
                $error_msg = "排名不能为空";
            }
            /*排序方式*/
            if(!empty($data['rankType'])){
                $param_product['rank_type'] = $data['rankType'];
            }
            /*是否是MVP*/
            if(!empty($data['mvp'])){
                $param_product['is_mvp'] = $data['mvp'];
            }
            /*评价次数*/
            if(!empty($data['reviewRating'])){
                $param_product['reviewRating'] = $data['reviewRating'];
            }
            /*评价次数*/
            if(!empty($data['reviewTotal'])){
                $param_product['reviewTotal'] = $data['reviewTotal'];
            }
            /*最小售价*/
            if(!empty($data['minSalesPrice'])){
                $param_product['minSalesPrice'] = $data['minSalesPrice'];
            }
            /*最大售价*/
            if(!empty($data['maxSalesPrice'])){
                $param_product['maxSalesPrice'] = $data['maxSalesPrice'];
            }elseif(isset($data['maxSalesTotal']) && $data['maxSalesTotal'] === 0){
                $error_msg = "最大售价不能为0";
            }
            /*最小订单量*/
            if(!empty($data['minOrderTotal'])){
                $param_product['minOrderTotal'] = $data['minOrderTotal'];
            }
            /*最大订单量*/
            if(!empty($data['maxOrderTotal'])){
                $param_product['maxOrderTotal'] = $data['maxOrderTotal'];
            }
            /*最小销售额*/
            if(!empty($data['minSalesTotal'])){
                $param_product['minSalesTotal'] = $data['minSalesTotal'];
            }
            /*折扣*/
            if(!empty($data['MinDiscount']) && !empty($data['MaxDiscount'])){
                if($data['MinDiscount']>=$data['MaxDiscount']){
                    $error_msg = "最小售价不能大于或等于最大售价";
                }
                if($data['MinDiscount']<0.01 || $data['MinDiscount']>=1){
                    $error_msg = "最小售价超过了限制";
                }
                if($data['MaxDiscount']<0.01 || $data['MaxDiscount']>=1){
                    $error_msg = "最小售价超过了限制";
                }
                $param_product['MinDiscount'] = $data['MinDiscount'];
                $param_product['MaxDiscount'] = $data['MaxDiscount'];
            }
            /*最小折扣*/
            if(!empty($data['MinDiscount'])){
                if($data['MinDiscount']<0.01 || $data['MinDiscount']>=1){
                    $error_msg = "最小售价超过了限制";
                }
                $param_product['MinDiscount'] = $data['MinDiscount'];
            }
            /*最大折扣*/
            if(!empty($data['MaxDiscount'])){
                if($data['MaxDiscount']<0.01 || $data['MaxDiscount']>=1){
                    $error_msg = "最大售价超过了限制";
                }
                $param_product['MaxDiscount'] = $data['MaxDiscount'];
            }
            /*最大销售额*/
            if(!empty($data['maxSalesTotal'])){
                $param_product['maxSalesTotal'] = $data['maxSalesTotal'];
            }
            if(!empty($error_msg)){
                $this->assign("error_msg",$error_msg);
            }else{
                $sku_data = $baseApi::getSkuSelection($param_product);
                if(!empty($sku_data['data'])){
                    $all_class = getAllProductClass("id,title_en,type,pdc_ids",false);
                    $all_class_data = array();
                    if(!empty($all_class)){
                        foreach ($all_class as $key=>$value){
                            $all_class_data[$value['id']] = $value['title_en'];
                        }
                    }
                    $eip_report_data = array();
                    $export_data = array();
                    foreach ($sku_data['data'] as $key=>$value){
                        $eip_report_data[$key] = $value;
                        if(!empty($value)){
                            foreach ($value as $k=>$v){
                                $eip_report_data[$key][$k]["first_category_name"] = isset($all_class_data[$v['first_category_id']])?$all_class_data[$v['first_category_id']]:'';
                                $eip_report_data[$key][$k]["second_category_name"] = isset($all_class_data[$v['second_category_id']])?$all_class_data[$v['second_category_id']]:'';
                                $eip_report_data[$key][$k]["third_category_name"] = isset($all_class_data[$v['third_category_id']])?$all_class_data[$v['third_category_id']]:'';
                                $eip_report_data[$key][$k]['shelf_time'] = !empty($v['shelf_time'])?date("Y-m-d H:i:s",$v['shelf_time']):'';
                                $eip_report_data[$key][$k]['discount'] = !empty($v['discount'])?($v['discount']*100)."%":'';
                                $eip_report_data[$key][$k]['is_mvp'] = $v['is_mvp']?"是":"否";
                                $export_data[] = $eip_report_data[$key][$k];
                            }
                        }
                    }
                    /*判断是否是导出*/
                    if(isset($data['is_export']) && $data['is_export'] == 1){
                        $header_data =[
                            'country' => '国家',
                            'sku_num' => 'SKU',
                            'product_name' => '产品名称',
                            'first_category_name' => '一级品类',
                            'second_category_name' =>'二级品类',
                            'third_category_name' => '三级品类',
                            'shelf_time' =>'上架日期',
                            'sales_price' =>'售价',
                            'discount' => '折扣',
                            'order_total' => '国家订单量',
                            'sales_volume' => '国家销售量',
                            'sales_total' => '国家销售额',
                            'is_mvp' => 'MVP',
                        ];
                        $tool = new ExcelTool();
                        return  $tool ->export('Wholesale Inquiry'.date("Ymd"),$header_data,$export_data);
                    }else{
                        $this->assign("eip_report_data",$eip_report_data);
                    }
                }else{
                    $error_msg = "查询数据为空！";
                    $this->assign("error_msg",$error_msg);
                }
            }

        }
        $classList = FirstLevelClass();
        if(!empty($data['oneCategory'])){
            $second_category_data = $this->catalog_next($data['oneCategory']);
            $this->assign("second_category_data",$second_category_data);
        }
        if(!empty($data['twoCategory'])){
            $third_category_data = $this->catalog_next($data['twoCategory']);
            $this->assign("third_category_data",$third_category_data);
        }
        $country_data = isset($countryList['data'])?$countryList['data']:'';
        $this->assign("class_list",$classList);
        $this->assign("country_data",$country_data);
        return view();
    }

    /*
     * 指定SKU销售查询
     * */
    public function specifySkuSales(){
       return view();
    }

    /**
     * 获取下一级分类
     */
    public function catalog_next($id='',$val=''){
        if(empty($id)){
            $id = input('id');
        }
        //$val         = input('class_level',$val) + 1;
        $select_data = array(
            '1'=>'second_level',
            '2'=>'third_level',
            '3'=>'fourth_level',
            '4'=>'fifth_level',
        );
        // $Pclass = Db::name(P_CLASS);
        // $Pclass = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS);
        $html = '';
        $list  = Db::connect("db_mongo")->name(MOGOMODB_P_CLASS)->where(['pid'=>(int)$id])->select();
        if(!$list){
            return '';
        }
       return $list;
    }
}
