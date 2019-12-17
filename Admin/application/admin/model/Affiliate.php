<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2018/4/11
 * Time: 10:55
 */
namespace app\admin\model;

use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Model;
use think\Db;
class Affiliate  extends Model{

    protected $table_product="dx_affiliate_product";
    public function __construct(){
        $this->db="db_mongo";
        $this->table="dx_affiliate_banner";
        $this->codeTable = 'dx_affiliate_code';
    }
    public function saveBanner($data){
        if(!empty($data['_id'])){
            $where["_id"] = (int)($data['_id']);
            unset($data["_id"]);
            return Db::connect($this->db)->table($this->table)->where($where)->update($data);
        }else{
            $last_id = $this->getLastId();
            $last_id = !empty($last_id)?(int)$last_id:(int)0;
            $data['_id'] = $last_id+1;
            return Db::connect($this->db)->table($this->table)->insert($data);
        }
    }

    public function getLastId(){
        return Db::connect($this->db)->table($this->table)->order("_id","desc")->value("_id");
    }

    public function getBanner($where='',$page_size=20,$page=1){
        $res = Db::connect($this->db)->table($this->table)->where($where)->order("_id","desc")->paginate($page_size,false,[ 'page' => $page,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    public function getBannerById($id){
        return Db::connect($this->db)->table($this->table)->where("_id",(int)$id)->find();
    }

    /**
     * 获取卖家设置的分类佣金比例（包含默认的分类佣金）
     *
     */
    public function getAffiliateCommission($type){

    }

    /**
     * 获取联盟营销产品信息【分页】
     * @param array $params
     * @return array
     */
    public function getAffiliateProductList(array $params){//dump($params);
        $base_api = new BaseApi();
        $query = Db::table($this->table_product);
        //去除已删除的产品
        $query->where('is_delete', '<>', 1);
        //seller_id
        if (isset($params['seller_id']) && !empty($params['seller_id'])){
            $query->where(['seller_id'=>$params['seller_id']]);
        }
        //type 数据类型:1 非主推产品; 2 主推产品;
        if (isset($params['type']) && !empty($params['type'])){
            $query->where(['type'=>$params['type']]);
        }
        //产品类别ID（一级品类ID）
        if (isset($params['class_id']) && !empty($params['class_id'])){
            $query->where(['class_id'=>$params['class_id']]);
        }
        //审核状态:0 待审核; 1 审核通过; 2 审核不通过;
        if (isset($params['status'])){
            $query->where(['status'=>$params['status']]);
        }
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $response = $query->paginate($page_size)->each(function($item, $key) use ($base_api){
            $spu = $item['spu'];
            $status = $item['status'];
            $seller_id = $item['seller_id'];
            /** 获取产品相关信息 start **/
            $pro_api = $base_api->getProductInfoByID($spu);//dump($pro_api);
            // $item['product_range_price'] = $pro_api['data']['RangePrice'];
            $item['product_title'] = $pro_api['data']['Title'];
            $item['product_img'] = $pro_api['data']['ImageSet']['ProductImg'][0];
            /** 获取产品相关信息 end **/
            //获取商家名称
            $seller_info = $base_api->getSellerByID($seller_id);
            $item['seller_name'] = $seller_info['data']['true_name'];
            //审核状态
            switch ($status){
                case 0:
                    $status_str = '待审核';
                    break;
                case 1:
                    $status_str = '已审核';
                    break;
                case 2:
                    $status_str = '已驳回';
                    break;
                default:
                    $status_str = '-';
                    break;
            }
            $item['status_str'] = $status_str;
            //佣金比例转换
            $item['commission'] *= 100;
            return $item;
        });
        $Page = $response->render();
        $data = $response->toArray();
        $data['page'] = $Page;
        /** 获取一级分类名称 start **/
        //获取一级分类数据
        $class_arr = [];
        foreach ($data['data'] as $info){
            $class_arr[] = $info['class_id'];
        }
        //获取一级分类详情
        $class_data_api = $base_api->getCategoryDataByCategoryIDData(array_unique($class_arr));
        $class_data = isset($class_data_api['data']) && !empty($class_data_api['data'])?$class_data_api['data']:[];
        //获取指定类别名称
        foreach ($data['data'] as &$d_info){
            $class_id = $d_info['class_id'];
            foreach ($class_data as $class_info){
                $class_id_info = $class_info['id'];
                if ($class_id == $class_id_info){
                    $d_info['FirstCategoryName_cn'] = $class_info['title_cn'];
                    $d_info['FirstCategoryName_en'] = $class_info['title_en'];
                    break;
                }
            }
        }
        /** 获取一级分类名称 end **/
        return $data;
    }

    /**
     * 根据数据更新佣金产品状态
     * @param array $data 数据
     * @return bool
     */
    public function checkProductStatusByData(array $data){
        $rtn = true;
        // start
        Db::startTrans();
        try{
            $update_time = time();
            $id_arr = $data['id'];
            $status = $data['status'];
            $remark = $data['remark'];
            Db::table($this->table_product)->where('id','in', $id_arr)->update([
                'status'=>$status,
                'remark'=>$remark,
                'update_time'=>$update_time,
            ]);
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('执行根据数据更新佣金产品状态事务出错');
            // roll
            Db::rollback();
        }
        return $rtn;
    }

    public function getCodeList($where='',$page_size=100,$page=1){
        $res = Db::connect($this->db)->table($this->codeTable)->where($where)->order("_id","desc")->paginate($page_size,false,[ 'page' => $page,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    public function addAffiliate($data){
        //判断是否存在
        $exist = Db::connect($this->db)->table($this->codeTable)->where(['_id'=>$data['_id']])->find();
        if($exist){
            return  false;
        }
        $res = Db::connect($this->db)->table($this->codeTable)->insert($data);
        return $res;
    }

    public function updateAffiliate($data){
        $id = $data['_id'];
        unset($data['_id']);
        //判断是否存在
        $exist = Db::connect($this->db)->table($this->codeTable)->where(['_id'=>$id])->find();
        if(!$exist){
            return  false;
        }
        $res = Db::connect($this->db)->table($this->codeTable)->where(['_id'=>$id])->update($data);
        return $res;
    }

    public function getCode($id){
        return Db::connect($this->db)->table($this->codeTable)->where(['_id'=>(int)$id])->find();
    }
}