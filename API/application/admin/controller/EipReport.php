<?php
namespace app\admin\controller;

use app\admin\model\EipReports;
use think\cache\driver\Redis;
use think\Controller;
use think\Exception;
use vendor\aes\aes;
use think\log;
class EipReport extends Controller
{
    /*
     * 获取EIP品类数据
     * 20190815 kevin
     * */
    public function getSkuSelection(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"EipReport.getSkuSelection");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            /*销售时间*/
            if(!empty($paramData['saleStartTime']) && !empty($paramData['saleEndTime'])){
                $where['sales_time'] = ["between",[strtotime($paramData['saleStartTime']),strtotime($paramData['saleEndTime'])]];
            }elseif(!empty($paramData['saleStartTime'])){
                $where['sales_time'] = ["EGT",strtotime($paramData['saleStartTime'])];
            }elseif (!empty($paramData['saleEndTime'])){
                $where['sales_time'] = ["LT",strtotime($paramData['saleEndTime'])];
            }
            /*国家简码*/
            if(!empty($paramData['country_code'])){
                $country_code = $paramData['country_code'];
            }

            /*评价得分*/
            if(!empty($paramData['reviewRating'])){
                $where['review_rating'] = ["EGT",$paramData['reviewRating']];
            }

            /*评价次数*/
            if(!empty($paramData['reviewTotal'])){
                $where['review_total'] = ["EGT",$paramData['reviewTotal']];
            }

            /*售价查询*/
            if(!empty($paramData['minSalesPrice']) && !empty($paramData['maxSalesPrice'])){
                $where['sales_price'] = ["between",[$paramData['minSalesPrice'],$paramData['maxSalesPrice']]];
            }else{
                if(!empty($paramData['minSalesPrice'])){
                    $where['sales_price'] = ["EGT",$paramData['minSalesPrice']];
                }
                if (!empty($paramData['maxSalesPrice'])){
                    $where['sales_price'] = ["LT",$paramData['maxSalesPrice']];
                }
            }
            /*订单量查询*/
            if(!empty($paramData['minOrderTotal']) && !empty($paramData['maxOrderTotal'])){
                $where['order_total'] = ["between",[$paramData['minOrderTotal'],$paramData['maxOrderTotal']]];
            }else{
                if(!empty($paramData['minOrderTotal'])){
                    $where['order_total'] = ["EGT",$paramData['minOrderTotal']];
                }
                if (!empty($paramData['maxOrderTotal'])){
                    $where['order_total'] = ["ELT",$paramData['maxOrderTotal']];
                }
            }
            /*销售额*/
            if(!empty($paramData['minSalesTotal']) && !empty($paramData['maxSalesTotal'])){
                $where['sales_total'] = ["between",[$paramData['minSalesTotal'],$paramData['maxSalesTotal']]];
            }else{
                if(!empty($paramData['minSalesTotal'])){
                    $where['sales_total'] = ["EGT",$paramData['minSalesTotal']];
                }
                if (!empty($paramData['maxSalesTotal'])){
                    $where['sales_total'] = ["ELT",$paramData['maxSalesTotal']];
                }
            }
            /*上架时间*/
            if(!empty($paramData['shelfStartTime']) && !empty($paramData['shelfEndTime'])){
                $where['shelf_time'] = ["between",[strtotime($paramData['shelfStartTime']),strtotime($paramData['shelfEndTime'])]];
            }elseif(!empty($paramData['shelfStartTime'])){
                $where['shelf_time'] = ["EGT",strtotime($paramData['shelfStartTime'])];
            }elseif (!empty($paramData['shelfEndTime'])){
                $where['shelf_time'] = ["LT",strtotime($paramData['shelfEndTime'])];
            }

            /*折扣*/
            if(!empty($paramData['MinDiscount']) && !empty($paramData['MaxDiscount'])){
                $where['discount'] = ["between",[$paramData['MinDiscount'],$paramData['MaxDiscount']]];
            }elseif(!empty($paramData['MinDiscount'])){
                $where['discount'] = ["EGT",$paramData['MinDiscount']];
            }elseif (!empty($paramData['MaxDiscount'])){
                $where['discount'] = ["LT",$paramData['MaxDiscount']];
            }

            /*一级分类ID*/
            if(!empty($paramData['first_category_id'])){
                $where['first_category_id'] = $paramData['first_category_id'];
            }
            /*二级分类ID*/
            if(!empty($paramData['second_category_id'])){
                $where['second_category_id'] = $paramData['second_category_id'];
            }
            /*三级分类ID*/
            if(!empty($paramData['third_category_id'])){
                $where['third_category_id'] = $paramData['third_category_id'];
            }
            /*关键字*/
            if(!empty($paramData['keyword'])){
                $where['keyword'] = ["like",'%'.$paramData['keyword'].'%'];
            }
            /*是否是MVP*/
            if(!empty($paramData['is_mvp'])){
                $where['is_mvp'] = $paramData['is_mvp'];
            }
            /*排名*/
            if(!empty($paramData['sale_rank'])){
                $limit = "0,".$paramData['sale_rank'];
            }
            /*排序类型,1:销量；2：销售额；3：订单量；4：折扣*/
            if(!empty($paramData['rank_type'])){
                $order ="";
                if($paramData['rank_type'] == 1){
                    $order = "sales_volume DESC";
                }elseif($paramData['rank_type'] == 2){
                    $order = "sales_total DESC";
                }elseif($paramData['rank_type'] == 3){
                    $order = "order_total DESC";
                }elseif($paramData['rank_type'] == 4){
                    $order = "discount DESC";
                }
            }
            $data = (new EipReports())->getSkuSelection($country_code,$where,$order,$limit);
            if(!empty($data)){
                return apiReturn(['code'=>200,'data'=>$data]);
            }else{
                return apiReturn(['code'=>1006,'data'=>$data]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

}
