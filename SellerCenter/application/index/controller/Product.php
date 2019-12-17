<?php
namespace app\index\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\BaseApi;
use app\index\dxcommon\LogHandler;
use app\index\dxcommon\Product as baseProduct;
use app\index\dxcommon\RedisClusterBase;
use app\index\model\AlbumInfoModel;
use app\index\model\LogisticsManagementModel;
use app\index\model\ShippingTemplateModel;
use app\index\model\ShippingTemplateTypeModel;
use Config\RedisConfig;
use think\Cache;
use think\Config;
use think\Log;
use think\Session;

/**
 * Class Common
 * @author tinghu.liu
 * @date 2018-03-06
 * @package app\index\controller
 */
class Product extends Common
{
    private $reselect_category_from_editorproduct_flag = 'proeditor';
    /**
     * 管理产品
     */
    public function index(){
        $base_api = new BaseApi();
        //$user_data = Session::get('user_data');
        $user_data = $this->login_user_data;
        $data['UserId'] = $user_data['user_id'];
        //$data['path'] = url("Product/index");
        //分页地址
        $input = input();
        $p = [];
        foreach ($input as $k=>$v){
            if ($k != 'page' && $k != 'page_size'){
                $p[$k] = $v;
            }
        }
        $data['path'] = url('Product/index', $p, config('default_return_type'), true);
        $title = input("Title");
        if (is_numeric($title)){
            $data['id'] = $title;
        }else{
            $data['Title'] = $title;
        }
        $data['Code'] = input("Code");
        //$data['Title'] = input("Title");
        $data['page_size'] = input('page_size',20);
        $data['page'] = input('page',1);
        $ExpiryTime = input("ExpiryTime");
        $sort_time =input("sort_time");
        $data['ExpiryTime'] = !empty($ExpiryTime)?strtotime("+$ExpiryTime days"):'';
        $data['InventoryFlag'] = input("InventoryFlag");

        //按照最新更新日期排序：1-倒序 desc，2-正序 asc
        if(!empty($sort_time)){
             $data['sort_time'] = input('sort_time', $sort_time);
        }

        $data=array_filter($data);
        $product_status = input("ProductStatus",0);
        //审核不通过增加审核不通过类型条件
        if ($product_status == PRODUCT_STATUS_REJECT){
            $RejectType = input("RejectType",1);
            $data['RejectType'] = $RejectType;

        }
        $this->assign("RejectType",empty($RejectType)?0:$RejectType);
        //审核成功条件，状态包含1和5
        if ($product_status == PRODUCT_STATUS_SUCCESS){
            $data['ProductStatus'] = [PRODUCT_STATUS_SUCCESS, PRODUCT_STATUS_SUCCESS_UPDATE];
        }elseif ($product_status == PRODUCT_STATUS_DOWN){
            $data['ProductStatus'] = [PRODUCT_STATUS_DOWN, PRODUCT_STATUS_STOP_PRESALE];
        }else{
            $data['ProductStatus'] = $product_status;
        }


        $data['GroupId'] = input("GroupId",0);
        $product = $base_api->getGroupProductPost($data);
        $count_pro = $base_api->CountBySellerPost($user_data['user_id'],[PRODUCT_STATUS_REVIEWING,PRODUCT_STATUS_REJECT,PRODUCT_STATUS_SUCCESS,PRODUCT_STATUS_STOP_PRESALE,PRODUCT_STATUS_DOWN, PRODUCT_STATUS_SUCCESS_UPDATE], [1,2]);//审核中
        //$count_pro = $base_api->CountBySellerPost($user_data['user_id']);
        $cdata = array();
        $category_data = $base_api->getNextCategoryByID(0);
        if (!empty($category_data['data'])){
            $cdata = $category_data['data'];
        }
        $group_data = $base_api->getGroupPost($user_data['user_id']);
        if(!empty($group_data) && isset($product['data']['data'])){
            foreach ($product['data']['data'] as $key=>$value){
                /*if(!empty($value['GroupId'])){
                    $group_name = $base_api->getGroupNamePost($value['GroupId']);
                    $product['data']['data'][$key]['GroupName'] = $group_name['data'];
                }*/
                $product['data']['data'][$key]['total_Inventory'] = 0;
                foreach ($value['Skus'] as $k=>$v){
                    $Inventory = isset($v['Inventory'])?$v['Inventory']:0;
                    $product['data']['data'][$key]['total_Inventory']+= $Inventory;
                }
                //获取分类ID
                /*$CategoryArr = $value['CategoryArr'][0];
                if (!empty($CategoryArr)){
                    $product['data']['data'][$key]['category_id'] = Base::getProductCategoryIdByCategoryArr($CategoryArr);
                }*/
                if (isset($value['CategoryPath']) && !empty($value['CategoryPath'])){
                    $c_arr = explode('-', $value['CategoryPath']);
                    $product['data']['data'][$key]['category_id'] = $c_arr[count($c_arr)-1];
                }else{
                    $product['data']['data'][$key]['category_id'] = 0;
                }
                //过期时间处理
                $expiry_time = ($value['ExpiryDate'] - time())>0?($value['ExpiryDate'] - time()):0;
                $product['data']['data'][$key]['ExpiryDateStr'] = ceil($expiry_time/(24*60*60));
            }
        }
        $this->assign('sort_time',$sort_time);
        $this->assign("ExpiryTime",$ExpiryTime);
        $this->assign("count_pro",$count_pro['data']);
        $this->assign("ProductStatus",input("ProductStatus",0));
        $this->assign("GroupId",$data['GroupId']);
        $this->assign('group_data',$group_data['data']);
        $this->assign('product',isset($product['data'])?$product['data']:['data'=>'']);
        $this->assign('child_menu','pro-manage');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /*
     * 获取产品详情
     * */
    public function getProductInfo(){
        $product_id = input("product_id");
        $base_api = new BaseApi();
        $product = $base_api->getProductInfoPost($product_id);
        if(isset($product['data']['Skus'])){
            $product['data']['total_Inventory'] = 0;
            foreach ($product['data']['Skus'] as $k=>$v){
                $Inventory = isset($v['Inventory'])?$v['Inventory']:0;
                $product['data']['total_Inventory']+= $Inventory;
            }
        }
        return $product['data'];
    }

    /*
     * 修改产品
     * */
    public function editProduct(){
        $data = input();
        $id = $data['id'];
        $inventory_arr = isset($data['inventory_arr'])?$data['inventory_arr']:'';
        $ProductStatus = input("ProductStatus");
        $base_api = new BaseApi();
        $user_data = $this->login_user_data;
        $product = $base_api->getProductInfo(['product_id'=>$id,'store_id'=>$user_data['user_id']]);
        $redis_cluster = new RedisClusterBase();
        if(is_array($id) && !empty($product['data'])){
            foreach ($product['data'] as $key=>$value){
                /** 异步处理产品运费模板数据 start TODO....... 如果修改（通过之前的运费模板ID和修改后的ID对比），商城之前保存的数据如何处理？【方案：延迟更新运费模板，流程：先将变更的“产品ID”、“带电信息”、“运费模板ID”写进队列，之后异步更新运费模板数据至商城cost表，再更改产品运费ID为要变更的ID、Name，将之前商城运费模板cost对应的数据写入日志表记录下来，再删除之前商城运费模板cost数据。】 **/
                //将产品ID，产品带电属性，所选运费模板ID，写入队列
                if(!empty($value['_id']) && !empty($value['LogisticsLimit'][0]) && !empty($value['LogisticsTemplateId']) && !empty($value['LogisticsTemplateName'])){
                    //如果运费模板有变化(修改后的ID和之前的不一致)
                    $redis_cluster->lPush(
                        QUEUE_PRODUCT_SHIPPING_TEMPLATE,
                        json_encode(
                            [
                                'product_id'=>$value['_id'],
                                'product_is_charged'=>$value['LogisticsLimit'][0],
                                'template_id'=>$value['LogisticsTemplateId'],
                                'template_name'=>$value['LogisticsTemplateName'],
                                'from_flag'=>2 //来源标识：1-新增产品，2-修改产品信息
                            ]
                        )
                    );
                }

            }
        }else{
            if(isset($data['inventory_arr'])){//是否有修改库存数组
                if(isset($data['inventory_arr'])){
                    $skus=$product['data']['Skus'];
                    foreach ($skus as $key=>$value){
                        foreach ($inventory_arr as $k=>$v){
                            if($value['_id'] == $k){
                                $skus[$key]['Inventory'] = $v;
                            }
                        }
                    }
                    $edit_data['Skus'] = $skus;
                }
            }
            /** 异步处理产品运费模板数据 start TODO....... 如果修改（通过之前的运费模板ID和修改后的ID对比），商城之前保存的数据如何处理？【方案：延迟更新运费模板，流程：先将变更的“产品ID”、“带电信息”、“运费模板ID”写进队列，之后异步更新运费模板数据至商城cost表，再更改产品运费ID为要变更的ID、Name，将之前商城运费模板cost对应的数据写入日志表记录下来，再删除之前商城运费模板cost数据。】 **/
            //将产品ID，产品带电属性，所选运费模板ID，写入队列
            if(!empty($product['data']['_id']) && !empty($product['data']['LogisticsLimit'][0]) && !empty($product['data']['LogisticsTemplateId']) && !empty($product['data']['LogisticsTemplateName'])){
                //如果运费模板有变化(修改后的ID和之前的不一致)
              $redis_cluster->lPush(
                    QUEUE_PRODUCT_SHIPPING_TEMPLATE,
                    json_encode(
                        [
                            'product_id'=>$product['data']['_id'],
                            'product_is_charged'=>$product['data']['LogisticsLimit'][0],
                            'template_id'=>$product['data']['LogisticsTemplateId'],
                            'template_name'=>$product['data']['LogisticsTemplateName'],
                            'from_flag'=>2 //来源标识：1-新增产品，2-修改产品信息
                        ]
                    )
                );
            }

        }

        if(!empty($ProductStatus)){
            $ChangeStatus['id'] = $id;
            $ChangeStatus['status'] = $ProductStatus;
            $res = $base_api->productChangeStatusPost($ChangeStatus);

            LogHandler::opRecord(__METHOD__,__FUNCTION__,'修改产品状态-'.json_encode($id),$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/product/changeStatus',json_encode($ChangeStatus),json_encode($res),json_encode($product));
        }elseif(isset($data['ExpiryTime'])){//延长有效期
            $extend['id'] =  $id ;
            $extend['days'] =  config("extend_day");
            $res = $base_api->prolongExpiryPost($extend);
            LogHandler::opRecord(__METHOD__,__FUNCTION__,'修改产品有效期-'.json_encode($id),$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/product/prolongExpiry',json_encode($extend),json_encode($res),json_encode($product));
        }else{
            $edit_data['id'] = $id;
            $edit_data['StoreID'] = $this->login_user_id;
            $edit_data['InventoryEditorFromSeller'] = 1;
            $res = $base_api->updateProductInfoPost(json_encode($edit_data));
            LogHandler::opRecord(__METHOD__,__FUNCTION__,'修改产品库存InventoryEditorFromSeller-'.json_encode($id),$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/product/update',json_encode($edit_data),json_encode($res),json_encode($product));
        }
        return $res;
    }
    
    /**
     * 上架产品-类别选择
     */
    public function category(){
        $category_id = input('category_id/d');

        /** 20181219 产品编辑页面重选类别 **/
        $product_id = input('product_id/d');
        $language = input('language');
        $flag = input('flag');

        $is_editor_pro = 0; //是否来至编辑产品
        if (
            !empty($category_id)
            &&!empty($product_id)
            &&!empty($language)
            &&!empty($flag)
            &&$flag == $this->reselect_category_from_editorproduct_flag
        ){
            $is_editor_pro = 1;
        }
//        $category_str = '';
    	$cdata = array();
    	$base_api = new BaseApi();
    	$category_data = $base_api->getNextCategoryByID(0);
    	if (isset($category_data['data']) && !empty($category_data['data'])){
    		$cdata = $category_data['data'];
    	}
        /** 获取类别 start **/
        /*if (is_numeric($category_id) && $category_id > 0){
            $base_api = new BaseApi();
            $category_data = $base_api->getCategoryDataWithID($category_id);
            $category_array = array();
            $category_id_array = array();
            if (!empty($category_data['data'])){
                foreach ($category_data['data'] as $cdata){
                    foreach ($cdata as $info){
                        if ($info['is_select'] == 1){
                            $category_array[] = $info['title_cn'];
                            $category_id_array[] = $info['id'];
                        }
                    }
                }
            }else{
                $this->redirect(url('Product/category'));
            }
            $category_str = implode('>>', $category_array);
        }*/
        /** 获取类别 end **/

        $this->assign('is_editor_pro',$is_editor_pro);
        $this->assign('get_data',json_encode(input()));
        $this->assign('category_id',$category_id);
    	$this->assign('category_str', baseProduct::getCategoryStr($category_id));
    	$this->assign('category_data',$cdata);
    	$this->assign('child_menu','shelf-product');
    	$this->assign('parent_menu','product-management');
    	return $this->fetch();
    }
    

    /**
     * 上架产品-填写内容
     * @return mixed
     */
    public function shelfPro()
    {
        $category_id = input('category_id/d');
        $product_id= input('product_id/d');
        if (empty($category_id)){
            $this->redirect(url('Product/category'));
        }
        //获取类别
        $base_api = new BaseApi();
        $category_data = $base_api->getCategoryDataWithID($category_id);
        $category_array = array();
        $category_id_array = array();
        if (!empty($category_data['data'])){
            foreach ($category_data['data'] as $cdata){
                foreach ($cdata as $info){
                    if ($info['is_select'] == 1){
                        $category_array[] = $info['title_cn'];
                        $category_id_array[] = $info['id'];
                    }
                }
            }
        }else{
            $this->redirect(url('Product/category'));
        }
        //产品ID不为空，则为添加类似产品，需要获取产品信息
        $is_charged = 1;
        if (!empty($product_id)){
            $product_info = $base_api->getProductInfoPost($product_id);
            $is_charged = isset($product_info['data']['LogisticsLimit'])?$product_info['data']['LogisticsLimit'][0]:1;
        }
        //获取产品运费模板
        $model = new ShippingTemplateModel();
        //$template_data = $model->getTemplateAllData($this->login_user_id);
        $template_data = $model->getTemplateDataByWhere(['seller_id'=>$this->login_user_id,'is_charged'=>$is_charged,'is_delete'=>0]);
        //产品分组
        $group_data = $base_api->getGroupPost($this->login_user_id);
        //产品所属分类
        $category_str = implode('>>', $category_array);
        $category_id_str = implode('>>', $category_id_array);
        $this->assign('is_self_support', $this->is_self_support);
        $this->assign('template_data', $template_data);
        $this->assign('product_group', $group_data['data']);
        $this->assign('select_category_url', url('Product/category',['category_id'=>$category_id, 'category_str'=>urlencode($category_str)]));
        $this->assign('category_str',$category_str);
        $this->assign('category_id_str',$category_id_str);
        $this->assign('category_id',$category_id);
        $this->assign('is_shelfpro', 1);
        $this->assign('get_data', json_encode(input()));
        $this->assign('child_menu','shelf-product');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /**
     * 上架产品-编辑
     * @return mixed
     */
    public function shelfProEditor()
    {
        $category_id = input('category_id');
        $product_id = input('product_id');

        if (empty($product_id) || !is_numeric($product_id)){
            abort(404);
        }
//        $base_api = new BaseApi();
//        $product_info = $base_api->getProductInfoByID($product_id);
//        print_r($product_info['data']);
//        $this->assign('product_data', json_encode($product_info['data']));
        $base_api = new BaseApi();
        //产品信息
        $product_info = $base_api->getProductInfoPost($product_id, $this->login_user_id);
        if (!isset($product_info['data']) || empty($product_info['data'])){
            abort(404);
        }
        $is_charged = isset($product_info['data']['LogisticsLimit'])?$product_info['data']['LogisticsLimit'][0]:1;
        //获取产品运费模板
        $model = new ShippingTemplateModel();
        //$template_data = $model->getTemplateAllData($this->login_user_id);
        $template_data = $model->getTemplateDataByWhere(['seller_id'=>$this->login_user_id,'is_charged'=>$is_charged,'is_delete'=>0]);
        //产品分组
        $group_data = $base_api->getGroupPost($this->login_user_id);
        //20181219 重选类别地址
        $this->assign('reselect_category_url', url('Product/category',[
            'product_id'=>$product_id,
            'category_id'=>$category_id,
            'language'=>input('language', 'en'),
            'flag'=>$this->reselect_category_from_editorproduct_flag
        ]));
        $this->assign('category_str', baseProduct::getCategoryStr($category_id));
        $this->assign('category_id_str', baseProduct::getCategoryStr($category_id,2));
        $this->assign('is_self_support', $this->is_self_support);
        $this->assign('product_group', $group_data['data']);
        $this->assign('get_data', json_encode(input()));
        $this->assign('template_data', $template_data);
        $this->assign('category_id', $category_id);
        $this->assign('product_id', $product_id);
        $this->assign('is_shelfpro', 1);
        $this->assign('child_menu','shelf-product');
        $this->assign('parent_menu','product-management');
        $this->assign('ImageSet',isset($product_info['data']['ImageSet'])?$product_info['data']['ImageSet']:'');
        return $this->fetch();
    }
    /**
     * 上架产品-History编辑
     * @return mixed
     */
    public function historyShelfProEditor()
    {
        $category_id = input('category_id');
        $product_id = input('product_id');

        if (empty($product_id) || !is_numeric($product_id)){
            abort(404);
        }

        $base_api = new BaseApi();
        //产品类别
        $cdata = array();$cate_id ='';$id_path ='';
        $category_data = $base_api->getNextCategoryByID(0);
        if (isset($category_data['data']) && !empty($category_data['data'])){
            $cdata = $category_data['data'];
        }
        //产品信息
        $product_info = $base_api->getProductInfoPost($product_id, $this->login_user_id);
        if (!isset($product_info['data']) || empty($product_info['data'])){
            abort(404);
        }
        $is_charged = isset($product_info['data']['LogisticsLimit'])?$product_info['data']['LogisticsLimit'][0]:1;
        $is_hostory =isset($product_info['data']['IsHistory'])?$product_info['data']['IsHistory']:0;
        if(empty($is_hostory)){
           $this->redirect('Product/index');
        }
        //获取产品运费模板
        $model = new ShippingTemplateModel();
        $template_data = $model->getTemplateDataByWhere(['seller_id'=>$this->login_user_id,'is_charged'=>$is_charged,'is_delete'=>0]);
        //获取对应分类及它的上级中最先有映射关系的映射类别id
        $res =$base_api->getMapDataByCategoryID($category_id, 1);
        if ($res['code'] == API_RETURN_SUCCESS){
            $cate_id = $res['data']['cate_id'];
            $id_path = $res['data']['id_path'];
        }
        //产品分组
        $group_data = $base_api->getGroupPost($this->login_user_id);
        $this->assign('is_self_support', $this->is_self_support);
        $this->assign('product_group', $group_data['data']);
        $this->assign('get_data', json_encode(input()));
        $this->assign('template_data', $template_data);
        $this->assign('category_id', $category_id);
        $this->assign('category_select_id', $cate_id);
        $this->assign('id_path', $id_path);
        $this->assign('is_history',$is_hostory);
        $this->assign('category_data',$cdata);
        $this->assign('product_id', $product_id);
        $this->assign('is_shelfpro', 1);
        $this->assign('child_menu','shelf-product');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

     /**
     * spu拆分-编辑页
     * @return mixed
     * 2018-09-08
     */
     public function productSplit(){
        $type = input('type',1);
        $code = input('code');
        $newAttrList =array();
        $AttrList =array();
        if(!empty($code)){
            $base_api =new BaseApi();
            //获取产品信息
            if($type==1){
                $product_info = $base_api->getProductInfoByID($code,$this->login_user_id);
            }else{
                $product_info = $base_api->getProductInfoByID('',$this->login_user_id,$code);
            }
            if(!empty($product_info) && !empty($product_info['data']['Skus'])){
                $skus =$product_info['data']['Skus'];
                $AttrList =$product_info['data']['AttrList'];//以属性OptionId组合为key的sku
                $skuCode =array();$productAttr =array();
                //重组产品属性
                foreach ($skus as $key => $sku) {
                    $attrs =$sku['SalesAttrs'];
                    if(!empty($attrs)){
                        foreach ($attrs as $k => $attr) {
                            $t = array();
                            $productAttr[$attr['_id']]['id'] = $attr['_id'];
                            $productAttr[$attr['_id']]['name'] = $attr['Name'];
                            $t['option_id'] = $attr['OptionId'];
                            if(isset($attr['DefaultValue']) && !empty($attr['DefaultValue'])){
                                $t['option_name'] = $attr['DefaultValue'];
                            }elseif(isset($attr['CustomValue']) && !empty($attr['CustomValue'])){
                                $t['option_name'] = $attr['CustomValue'];
                            }else{
                                $t['option_name'] = $attr['Value'];
                            }
                            if (isset($attr['Image']) && !empty($attr['Image'])) {
                                $t['img'] = $attr['Image'];
                            }
                            $productAttr[$attr['_id']]['attr'][$key] =$t;
                        }

                    }
                    $skuCode[$key] = $sku['Code'];
                }
                $this->assign('product_id',$product_info['data']['_id']);
                $this->assign('product_name',$product_info['data']['Title']);
                $this->assign('skuCode',implode(',', $skuCode));
            }
            if(!empty($productAttr)){
                //去重
                $i = 0;
                foreach($productAttr as $key => $attrs){
                    $newAttr = array_values(array_unset_repeat($attrs['attr'],'option_id'));
                    $newAttrList[$i]['_id'] = $attrs['id'];
                    $newAttrList[$i]['name'] = $attrs['name'];
                    $newAttrList[$i]['attr'] = $newAttr;
                    $i++;
                }
            }
        }
        $this->assign('attrList',json_encode($AttrList));
        $this->assign('code',$code);
        $this->assign('type',$type);
        $this->assign('productAttr',$newAttrList);
        $this->assign('child_menu','pro-manage');
        $this->assign('parent_menu','product-management');
        return $this->fetch();

     }
    /**
     * spu拆分-提交
     * @return \think\response\Json
     * 2018-09-11
     */
    public function splitProduct(){
        $product_id =input("post.product_id");
        $data = htmlspecialchars_decode(input("post.data"));
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '参数错误，请重试';
        if(!empty($product_id) && !empty($data)){
            $base_api = new BaseApi();
            $param['product_id']=$product_id;
            $param['store_id'] =$this->login_user_id;
            $data =json_decode($data,true);
            foreach ($data as $key => $val) {
               $data[$key]['sku_codes'] =trim($val['sku_codes'],",");
            }
            $newSku = implode(',',array_column($data, 'sku_codes'));
            $allSku = explode(',',$newSku);
            //去重
            $newSku =array_unique(explode(',',$newSku));
            $df=array_diff_assoc($allSku,$newSku);
            if($df){
                $rtn['msg'] = 'SKU CODE:'.implode(',',$df).'重复'; 
                return json($rtn);
            }
            //获取产品详情
            $product_info = $base_api->getProductInfoByID($product_id,$this->login_user_id);
            if(!empty($product_info['data'])){
                $oldSku =array_column($product_info['data']['Skus'], 'Code');//获取产品的Skus Code
                $diff =array_diff($newSku,$oldSku);
                if(empty($diff)){//判断提交的sku code是否在产品中
                    $param['data'] =$data;
                    $res = $base_api->splitProduct($param);
                    LogHandler::opRecord(__METHOD__,__FUNCTION__,'拆分产品-'.$product_id,$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/Product/splitProduct',json_encode($param),json_encode($res),json_encode($product_info));
                    if ($res['code'] == API_RETURN_SUCCESS){
                        $rtn['code'] = 0;
                        $msg ='操作成功，拆分前SPU：'.$res['data']['product_id'].';拆分后SPU：'.implode(',', array_column($res['data']['new_data'], 'product_id')).';';
                        if($res['data']['is_all_split']){
                           $msg .= 'SPU'.$res['data']['product_id'].'已删除'; 
                        }
                        $rtn['msg'] = $msg;
                    }else{
                        $rtn['msg'] =$res['msg'];
                    }
                }else{
                    $rtn['msg'] = '当前产品SKU CODE:'.implode(',',$diff).'不存在'; 
                }
            }else{
               $rtn['msg'] = '产品不存在';  
            }
        }
        return json($rtn);
    }

    /**
     * 产品价格批量修改查询
     * @return mixed
     * 2018-09-08
     */
    public function productBacthModify(){
        $product_ids = trim(input('product_id'),',');
        $percent = input("percent");
        $amount = input("amount");
        $usage_amount = input("usage_amount");
        $type =input("type",0);
        $product_list = array();
        if(!empty($product_ids)){
            $base_api =new BaseApi();
            $ids = array_unique(explode(',',$product_ids));
            foreach ($ids as &$id) {
               //获取产品信息
                $product_info = $base_api->getProductInfoByID($id);
                if(!empty($product_info['data'])){
                    $product_list[] = $product_info['data'];
                }
            }
        }//print_r($product_list);exit;
        $this->assign("usage_amount",$usage_amount);
        $this->assign("type",$type);
        $this->assign('percent',$percent);
        $this->assign('amount',$amount);
        $this->assign('product_list',$product_list);
        $this->assign('product_id',$product_ids);
        $this->assign('child_menu','pro-manage');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }
    /**
     * 产产品价格批量修改-提交
     * @return \think\response\Json
     * 2018-09-08
     */
    public function bacthModify(){
        $data = htmlspecialchars_decode(input("post.data",''));
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '提交失败，请重试';
        if(!empty($data)){
             $base_api =new BaseApi();   
             $data =json_decode($data,true);
             $param =array();
             foreach ($data as $key => $val) {
                $param[$val['product_id']]['product_id'] =$val['product_id'];
                $param[$val['product_id']]['skus'][] =array('id' =>$val['sku_id'] ,'price'=>$val['price'] );
             }
             $return = $base_api->updateProductsPrice(array_values($param));
             if ($return['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $return;
            }else{
                $rtn['msg'] = $return['msg'];
            }
        }
        return json($rtn);
    }

    /**
     * 上架产品-成功
     */
    public function shelfSuccess(){


        $this->assign('child_menu','shelf-product');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /**
     * 产品管理
     * @return mixed
     */
    public function proManage()
    {


        $this->assign('child_menu','pro-manage');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /**
     * 运费模板管理-列表页
     * @return mixed
     */
    public function shippingTemplate(){
        $template_model = new ShippingTemplateModel();
        $list = $template_model->getTemplateData($this->login_user_id, 5);
        if (input('page') > $list->lastPage()){
            $this->redirect('shippingTemplate');
        }
        $list->currentPages = $list->currentPage(); //当前页
        $list->lastPages = $list->lastPage(); //最大页数
        $this->assign('list',$list);
        $this->assign('editor_template_url', url('Product/shippingTemplateEditor'));
        $this->assign('child_menu','shipping-templates');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /**
     * 新增运费模板
     * @return mixed
     */
    public function shippingTemplateAdd(){
        $this->assign('child_menu','shipping-templates');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /**
     * 修改运费模板
     * @return mixed
     */
    public function shippingTemplateEditor(){
        $template_id = input('template_id/d');

        $this->assign('template_id',$template_id);
        $this->assign('child_menu','shipping-templates');
        $this->assign('parent_menu','product-management');
        return $this->fetch();
    }

    /**
     * 根据类别名称获取类别
     * @return \think\response\Json
     */
    public function m_getCgSearchContent(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $search_content = input('post.search_content');
        if (!empty($search_content)){
            $base_api = new BaseApi();
            $type = 1;
            if (is_have_chinese($search_content)){
                $type = 2;
            }
            $res = $base_api->getCategoryDataWithTitle($search_content, $type);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = $res['code']."->".$res['msg'];
            }
        }
        return json($rtn);
    }

    /**
     * 根据子[末级]分类ID获取分类完整数据【倒推】
     * @return \think\response\Json
     */
    public function m_getCgID(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $category_id = input('post.category_id');
        if (!empty($category_id)){
            $base_api = new BaseApi();
            $res = $base_api->getCategoryDataWithID($category_id, 1);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = $res['code']."->".$res['msg'];
            }
        }
        return json($rtn);
    }

    /**
     * 根据分类ID获取下一个子级
     * @return \think\response\Json
     */
    public function m_getNextCategoryByID(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $category_id = input('post.category_id');
        if (!empty($category_id)){
            $base_api = new BaseApi();
            $res = $base_api->getNextCategoryByID($category_id);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = $res['code']."->".$res['msg'];
            }
        }
        return json($rtn);
    }

    /**
     * 根据分类ID获取产品品牌
     * @return \think\response\Json
     */
    public function m_getProductBrand(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $category_id = input('post.category_id');
        if (!empty($category_id)){
            $base_api = new BaseApi();
            $res = $base_api->getProBrandByCategoryID($category_id);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '请求接口错误：'.$res['code'];
            }
        }else{
            $rtn['msg'] = '类别ID为必填项';
        }
        return json($rtn);
    }

    /**
     * 根据分类ID获取产品属性
     * @return \think\response\Json
     */
    public function m_getProductAttr(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
//        $category_id = input('post.category_id');
        $category_id = input('category_id');
//        Log::record('m_getProductAttr->'.$category_id);
        if (!empty($category_id)){
            $base_api = new BaseApi();
            $res = $base_api->getProAttrByCategoryID($category_id);
            if ($res['code'] == API_RETURN_SUCCESS){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '请求接口错误：'.$res['msg'];
            }
        }else{
            $rtn['msg'] = '类别ID为必填项';
        }
        return json($rtn);
    }

    /**
     * 根据seller ID 获取对应的产品图片信息
     * @return \think\response\Json
     */
    public function m_getProductImages(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $page = !empty(input('page/d'))?input('page/d'):1;
        if (!empty($page) && is_numeric($page) && $page > 0){
            $model = new AlbumInfoModel();
            $img_data = $model->getDataBySellerIdPaginate($this->login_user_id, $page, 18);
            if (!empty($img_data)){
                $rtn['data'] = $img_data;
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '没有相关图片信息';
            }
        }else{
            $rtn['msg'] = '参数错误';
        }
        return json($rtn);
    }

    /**
     * 根据图片ID删除产品图片
     * @return \think\response\Json
     */
    public function m_deleteProductImage(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $id = input('id/d');
        if (!empty($id) && is_numeric($id) && $id > 0){
            $model = new AlbumInfoModel();
            if ($model->deleteImageByID($id)){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '删除失败，请重试';
            }
        }else{
            $rtn['msg'] = '参数错误';
        }
        return json($rtn);
    }

    /**
     * 上传产品
     * @return \think\response\Json
     */
    public function m_productPost(){
        $rtn = Config::get('ajax_return_data');
        $rtn['msg'] = '参数错误，请重试';
        if (request()->isAjax()) {//限制为ajax请求
            $product_data = htmlspecialchars_decode(input('post.product_data'));
            Log::record('上传产品post-原始值：'.print_r($product_data, true));
            if (!empty($product_data)){
                $base_api = new BaseApi();
                //组装运费模板数据
                $post_arr = json_decode($product_data, true);
                //替换图片路径反斜杠
                foreach ($post_arr['ImageSet']['ProductImg'] as $key=>&$img){
                    $post_arr['ImageSet']['ProductImg'][$key] = str_replace('\\', '/', $img);
                }
                foreach ($post_arr['ImageSet']['AttributeImg'] as $akey=>&$aimg){
                    $post_arr['ImageSet']['AttributeImg'][$akey] = str_replace('\\', '/', $aimg);
                }
                //描述图片特殊性处理（粘贴的情况下）
                $pattern = "/src=\"\/uploads/";
                $post_arr['Descriptions'] = preg_replace($pattern,'src="'.config('seller_img_url'), $post_arr['Descriptions']);
                
                //$post_arr['LogisticsTemplateInfo'] = baseProduct::getShippingTemplateByIDForPost($post_arr['LogisticsTemplateId']); //运费数据异步处理，此处去掉
                //来源标识，为了和来至erp上传的产品进行区分
                $post_arr['from_flag'] = 1;
                Log::record('上传产品post-最终值：'.json_encode($post_arr));
                $res = $base_api->productPost(json_encode($post_arr));
                $product_id = isset($res['data']['id'])?$res['data']['id']:0;
                LogHandler::opRecord(__METHOD__,__FUNCTION__,'上架产品-'.$product_id,$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/Product/addProduct',json_encode($post_arr),json_encode($res));
                if ($res['code'] == API_RETURN_SUCCESS){
//                    $product_id = $res['data']['id'];
                    /** 异步处理产品运费模板数据 start **/
                    //将产品ID，产品带电属性，所选运费模板ID，写入队列
                    $redis_cluster = new RedisClusterBase();
                    $redis_cluster->lPush(
                        QUEUE_PRODUCT_SHIPPING_TEMPLATE,
                        json_encode(
                            [
                                'product_id'=>$product_id,
                                'product_is_charged'=>$post_arr['LogisticsLimit'][0],
                                'template_id'=>$post_arr['LogisticsTemplateId'],
                                'from_flag'=>1 //来源标识：1-新增产品，2-修改产品信息
                            ]
                        )
                    );
                    /** 异步处理产品运费模板数据 end **/
                    /** 异步处理产品、属性图片，传至CDN，生成小图操作 start **/
                    $redis_cluster->lPush(
                        QUEUE_PRODUCT_MAIN_IMAGES,
                        json_encode(
                            [
                                'product_id'=>$product_id,
                                'imgs'=>array_merge($post_arr['ImageSet']['ProductImg'], $post_arr['ImageSet']['AttributeImg'])
                            ]
                        )
                    );
                    /** 异步处理产品图片，传至CDN，生成小图操作 end **/
                    $c_arr = explode('-', $post_arr['CategoryPath']);
                    $category_id = $c_arr[count($c_arr)-1];
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                    $rtn['url'] = url('Product/shelfSuccess', ['product_id'=>$product_id,'category_id'=>$category_id, 'language'=>'en']);
                    $rtn['data'] = $res;
                }else{
//                    $rtn['msg'] = '新增产品失败，请重试';
                    $rtn['msg'] = $res['msg'];
                    Log::record('m_productPost->新增产品失败'.print_r($res, true));
                }
            }else{
                $rtn['msg'] = '错误的参数';
            }
        }else{
            $rtn['msg'] = '错误访问';
        }
        return json($rtn);
    }

	/**
     * 获取计量单位
     * @return \think\response\Json
     */
    public function m_getMeasurementUnit(){

    }

    /*
     * 产品分组
     * */
    public function productGroup(){
        //$user_data = Session::get('user_data');
        $user_data = $this->login_user_data;
        if(request()->isAjax()){
            $base_api = new BaseApi();
            $res = $base_api->getGroupPost($user_data['user_id']);
            return $res;
        }else{
            $base_api = new BaseApi();
            $res = $base_api->getGroupPost($user_data['user_id']);
            $this->assign('child_menu','shelf-productGroup');
            $this->assign('parent_menu','product-management');
            $this->assign("progroup",$res['data']);
            return $this->fetch();
        }
    }

    /*
     * 获取产品分组
     * */
    public function addProductGroup(){
        //$user_data = Session::get('user_data');
        $user_data = $this->login_user_data;
        $group_name = input("group_name");
        $parent_id = input("parent_id");
        $base_api = new BaseApi();
        $has = $base_api->hasGroupPost($user_data['user_id'],$group_name);
        if($has['data']){
            return ['code'=>1002,'msg'=>"分组名字已存在！"];
        }
        $base_api = new BaseApi();
        $res = $base_api->addGroupPost($user_data['user_id'],$group_name,$parent_id);
        return $res;
    }

    /*
     *删除产品分组
     * */
    public function delGroup(){
        $group_id = input("group_id");
        $base_api = new BaseApi();
        $res = $base_api->delGroupPost($group_id);
        return $res;
    }

    /*
         *删除产品分组
         * */
    public function editGroup(){
        $data['group_id'] = input("group_id");
        $data['group_name'] = input("group_name");
        $data['store_open'] = input("store_open");
        $data=array_filter($data);
        $base_api = new BaseApi();
        $res = $base_api->editGroupPost($data);
        return $res;
    }

    /*
     * 组内产品
     * */
    public function groupProduct(){
        //$user_data = Session::get('user_data');
        $user_data = $this->login_user_data;
        if(request()->isAjax()){
            $data['UserId'] = $user_data['user_id'];
            $data['GroupId'] = input("group_id",0);
            $data['page_size'] = input('page_size',10);
            $data['page'] = input('page_size',1);
            $data=array_filter($data);
            $base_api = new BaseApi();
            $res = $base_api->getGroupProductPost($data);
            return $res;
        }else{
            $data['UserId'] = $user_data['user_id'];
            $data['GroupId'] = input("group_id",0);
            $data['page_size'] = input('page_size',10);
            $data['page'] = input('page_size',1);
            $data=array_filter($data);
            $base_api = new BaseApi();
            $res = $base_api->getGroupProductPost($data);
            $this->assign("product",$res['data']);
            $this->assign('child_menu','shelf-productGroup');
            $this->assign('parent_menu','product-management');
            return $this->fetch();
        }
    }

    /*
     * 更改产品分组
     * */
    public function updateProductGroup(){
        $post_data = input();
        $data['id'] = $post_data['product_id'];
        $data['GroupId'] = $post_data["group_id"];
        $base_api = new BaseApi();
        $res = $base_api->updateProductGrouptPost($data);
        return $res;
    }

    /**
     * 根据模板类型、带电属性获取对应运费国家信息
     * @return \think\response\Json
     */
    public function m_getSTCountry(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $st_type = input('st_type/d');
        $is_charged = input('is_charged/d');
        if (
            (!empty($st_type) || $st_type == 0)
            && !empty($is_charged)
        ){
            $rtn['data'] = baseProduct::getSTCountryByTemplateType($st_type, $is_charged);
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);
    }

    /**
     * 根据带电属性获取运费模板数据
     * @return \think\response\Json
     */
    public function m_getSTByIsCharged(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $is_charged = input('is_charged/d');
        if (
            !empty($is_charged)
        ){
            $rtn['data'] = baseProduct::getShippingTemplateByIsChargedAndSellerId($is_charged, $this->login_user_id);
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);
    }

    /**
     * 根据运费模板ID获取运费模板对应国家数据
     * @return \think\response\Json
     */
    public function m_getSTCountryByTemplateId(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $template_id = input('template_id/d');
        if (!empty($template_id) && $template_id >0 && is_numeric($template_id)){
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = baseProduct::getSTCountryByTemplateId($template_id);
        }else{
            $rtn['msg'] = '非法参数';
        }
        return json($rtn);
    }

    /**
     * 根据产品ID获取产品详情
     * @return \think\response\Json
     */
    public function m_getProductInfoByID(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $product_id = input('product_id');
        $lang =input("lang");
        if (!empty($product_id)){
            $base_api = new BaseApi();
            $res = $base_api->getProductInfoByID($product_id,'','',$lang);
            if ($res['code'] == API_RETURN_SUCCESS){
                /** 获取产品图片hash值 为了校验产品图片唯一性 start **/
                /*** 暂时不做 ***/
                /*$img_hash_arr = [];
                $base_cdn_url = config('cdn_url_config.url');

                $product_img_arr = $res['data']['ImageSet']['ProductImg'];
                if (!empty($product_img_arr)){
                    foreach ($product_img_arr as $k=>$v){
                        $v = str_replace('\\', '/', $v);
                        //图片地址
                        $img_url = $base_cdn_url.$v;
                        //临时保存路径
                        $temp_v_arr = explode('/', $v);
                        $temp_save_path = ROOT_PATH.'public'.DS.'uploads'.DS.'temp'.DS.end($temp_v_arr);
                        file_put_contents($temp_save_path, file_get_contents($img_url));
                        $img_hash_arr[$k] = hash_file('sha256', $temp_save_path);
                    }
                }*/
                /** 获取产品图片hash值 为了校验产品图片唯一性 end **/
                $res['data']['ImageSet']['AttributeImg'] = isset($res['data']['ImageSet']['AttributeImg'])?$res['data']['ImageSet']['AttributeImg']:[];
                /** 处理属性颜色图片对应问题 start **/
                //需要再修改，将颜色属性图片和一级属性和二级属性绑定起来，前端再根据绑定的属性ID填充对应属性图片
                $_tem_attr = [];
                //将颜色属性图片和一级属性和二级属性绑定
                $_attr_img = [];
                if (!empty($res['data']['Skus']) && is_array($res['data']['Skus'])){
                    foreach ($res['data']['Skus'] as $sku){
                        if (isset($sku['SalesAttrs']) && is_array($sku['SalesAttrs'])){
                            foreach ($sku['SalesAttrs'] as $attr){
                                if (isset($attr['Image']) && !empty($attr['Image'])){
                                    $_tem_attr[] = $attr['Image'];
                                    //将颜色属性图片和一级属性和二级属性绑定
                                    $_temp_attr_img = [];
                                    $_temp_attr_img['id'] = $attr['_id'];
                                    $_temp_attr_img['OptionId'] = $attr['OptionId'];
                                    $_temp_attr_img['Image'] = $attr['Image'];
                                    $_attr_img[$attr['OptionId']] = $_temp_attr_img;
                                }
                            }
                        }
                    }
                }
                $res['data']['ImageSet']['AttributeImg'] = array_merge(array_unique($_tem_attr),[]);
                $res['data']['NewAttributeImg'] = $_attr_img;
                /** 处理属性颜色图片对应问题 end **/
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $res['data']['Descriptions'] = htmlspecialchars_decode($res['data']['Descriptions']);
                $res['data']['Title'] = htmlspecialchars_decode($res['data']['Title']);
                $rtn['data'] = $res;
            }else{
                $rtn['msg'] = '请求接口错误：'.$res['code'];
            }
        }else{
            $rtn['msg'] = '产品ID为必填项';
        }
        return json($rtn);
    }

    /**
     * 设置默认模板
     * @return \think\response\Json
     */
    public function m_setDefualtShippingTemplate(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $template_id = input('template_id');
        if (!empty($template_id)){
            $model = new ShippingTemplateModel();
            if ($model->setDefaultTemplate($this->login_user_id, $template_id)){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '设置失败，请重试';
            }
        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);
    }

    /**
     * 删除运费模板
     * @return \think\response\Json
     */
    public function m_deleteShippingTemplate(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $template_id = input('template_id');
        if (!empty($template_id)){
            $model = new ShippingTemplateModel();
            if ($model->updateTemplateData($template_id, ['is_delete'=>1])){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '删除失败';
            }
        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);
    }

    /**
     * 复制运费模板
     * @return \think\response\Json
     */
    public function m_copyShippingTemplate(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '复制模板失败，请重试';
        //要复制的模板ID
        $template_id = input('template_id');
        if (!empty($template_id)){
            $model = new ShippingTemplateModel();
            if ($model->copyShippingTemplateData($template_id)){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '复制失败，请重试';
            }
        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);

    }

    /**
     * 新增运费模板
     * 数据格式如下：
     * $data = [
        'template_id'=>21, //此参数只有在编辑运费模板时才会有
        'template_name'=>'test',
        'delivery_area'=>'china',
        'is_charged'=>1, //是否带电：1-为普货，2-为纯电，3-为带电
        'data'=>[
                    [
                        'logisticsServices'=>10,
                        'freightSettings'=>1,
                        'relief'=>10
                    ],
                    [
                        'logisticsServices'=>20,
                        'freightSettings'=>1,
                        'relief'=>10
                    ],
                    [
                        'logisticsServices'=>30,
                        'freightSettings'=>3,
                        'country_data'=>[
                            'country'=>[
                                ['logistics_id'=>10,'name'=>'AT', 'country_name'=>'', 'area'=>'亚洲','shipping_service_text'=>'Exclusive'],
                                ['logistics_id'=>10,'name'=>'AS', 'country_name'=>'', 'area'=>'亚洲','shipping_service_text'=>'Toll'],
                                ['logistics_id'=>10,'name'=>'US', 'country_name'=>'', 'area'=>'亚洲','shipping_service_text'=>'IB,Toll']
                            ],
                            'freightType'=>3,
                            'relief'=>10,//freightType=1，选择“标准运费时后效”
                            'custom_freight_data'=>[
                                  'custom_freight_type'=>1, //1-重量规则，2-数量规则
                                  'first_data'=>2,
                                  'first_freight_type'=>1, //first_freight单位：1-按照百分比，2-单位为美元
                                  'first_freight'=>14,
                                  'increase_data'=>[
                                        [
                                          'start_data'=>2,
                                          'end_data'=>50,
                                          'add_data'=>1,
                                          'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                          'add_freight'=>3
                                        ],
                                        [
                                          'start_data'=>50,
                                          'end_data'=>500,
                                          'add_data'=>10,
                                          'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                          'add_freight'=>50
                                        ]
                                  ]
                            ]
                        ]
                    ],
                    [
                        'logisticsServices'=>40,
                        'freightSettings'=>3,
                        'country_data'=>[
                            'country'=>[
                                [
                                    'logistics_id'=>10,
                                    'name'=>'AT',
                                    'country_name'=>'',
                                    'area'=>'亚洲',
                                    'shipping_service_text'=>'Toll',

                                    'freightType'=>3,
                                    'relief'=>10,//freightType=1，选择“标准运费时后效”,
                                    'custom_freight_data'=>[
                                          'custom_freight_type'=>1, //1-重量规则，2-数量规则
                                          'first_data'=>2,
                                          'first_freight_type'=>1, //first_freight单位：1-按照百分比，2-单位为美元
                                          'first_freight'=>14,
                                          'increase_data'=>[
                                                [
                                                  'start_data'=>2,
                                                  'end_data'=>50,
                                                  'add_data'=>1,
                                                  'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                  'add_freight'=>3
                                                ],
                                                [
                                                  'start_data'=>50,
                                                  'end_data'=>500,
                                                  'add_data'=>10,
                                                  'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                  'add_freight'=>50
                                                ]
                                          ]
                                    ]
                                ],
                            ],
                        ]
                    ],
            ]
        ]
     *
     */
    public function m_addShippingTemplate(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增模板失败，请重试';
        $data = input();
        Log::record('m_addShippingTemplate->s'.json_encode($data));
        if (!empty($data['data'])){
            $data = $data['data'];
            $model = new ShippingTemplateModel();
            $template_name = trimall($data['template_name']);
            if (baseProduct::checkShippingTemplateName($template_name)){
                $info = $model->getInfoForTemplateByTemplateName($template_name);
                if (empty($info)){
                    $res = $model->insertData($this->login_user_id, $data);
                    if (!empty($res) && is_numeric($res)){
                        $rtn['code'] = 0;
                        $rtn['msg'] = 'success';
                    }else{
                        $rtn['msg'] = '新增失败，请重试';
                    }
                }else{
                    $rtn['msg'] = '运费模板['.$template_name.']已存在';
                }
            }else{
                $rtn['msg'] = '运费模板名称格式不对';
            }

        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);
    }

    /**
     * 获取运费模板基础数据
     * @return \think\response\Json
     */
    public function m_getShippingTemplateData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $model = new ShippingTemplateModel();
        $template_data = $model->getTemplateAllData($this->login_user_id);
        $rtn['code'] = 0;
        $rtn['data'] = $template_data;
        $rtn['msg'] = 'success';
        return json($rtn);
    }

    /**
     * 根据运费模板ID获取对应运费模板信息 
     * @return \think\response\Json 返回的json格式和新增运费模板一致
     */
    public function m_getShippingTemplateByID(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $template_id = input('template_id/d');
        if (!empty($template_id) && is_numeric($template_id) && $template_id > 0){
            $template_info = baseProduct::getShippingTemplateByID($template_id, $this->login_user_id);
            if (!empty($template_info)){
                $rtn['data'] = $template_info;
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '模板信息不存在';
            }
        }else{
            $rtn['msg'] = '非法参数：'.$template_id;
        }
        return json($rtn);
    }

    /**
     * 根据运费模板ID获取详细信息【上传/编辑产品，提交组装数据用】
     * @return \think\response\Json
     */
    public function m_getShippingTemplateByIDForPost(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $template_id = input('template_id/d');
        if (!empty($template_id) && is_numeric($template_id) && $template_id > 0){
            $data = baseProduct::getShippingTemplateByIDForPost($template_id);
            if (!empty($data)){
                $rtn['code'] = 0;
                $rtn['msg'] = 'success';
                $rtn['data'] = $data;
            }else{
                $rtn['msg'] = '运费模板数据为空';
            }
        }else{
            $rtn['msg'] = '非法参数：'.$template_id;
        }
        return json($rtn);
    }

    /**
     * 根据模板ID、国家获取运费模板信息
     * @return \think\response\Json
     */
    public function m_getSTDetailByIDAndCountry(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $template_id = input('template_id/d');
        $iso_code = input('iso_code/s');
        if (!empty($template_id) && !empty($iso_code) && is_numeric($template_id) && $template_id >0){
            $data = baseProduct::getSTDetailByIDAndCountry($template_id, $iso_code);
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $data;
        }else{
            $rtn['msg'] = '参数错误';
        }
        return json($rtn);
    }


    /**
     * 修改运费模板
     * 数据格式：参考 m_addShippingTemplate 方法的数据格式
     */
    public function m_editorShippingTemplate(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '编辑模板失败，请重试';
        $data = input();
        Log::record('m_editorShippingTemplate->s'.json_encode($data));
        if (!empty($data['data'])){
            $data = $data['data'];
            $model = new ShippingTemplateModel();
            $seller_info = $this->login_user_info;
            if ($model->editorData($seller_info['true_name'], $data)){
                //TODO 修改运费模板后，已经绑定产品的运费模板同步修改问题（不允许修改还是同步更新mongo库里的产品运费模板表数据？）方案：同步更新mongo库里的产品运费模板表数据
                /** 异步更新商城产品关联的运费模板信息 start **/
                $redis_cluster = new RedisClusterBase();
                //如果运费模板有变化(修改后的ID和之前的不一致)
                $res = $redis_cluster->lPush(
                    QUEUE_SHIPPING_TEMPLATE_EDITOR,
                    json_encode(
                        [
                            'template_id'=>$data['template_id'],
                            'is_charged'=>$data['is_charged'],
                            'add_time'=>time(),
                        ]
                    )
                );
                /** 异步更新商城产品关联的运费模板信息 end **/
                if ($res){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }
            }else{
                $rtn['msg'] = '编辑失败，请重试';
            }
        }else{
            $rtn['msg'] = '缺少必填参数';
        }
        return json($rtn);
    }

    /**
     * 获取产品帮助中心数据
     * @return \think\response\Json
     */
    public function m_getProductHelpData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $help_id = input('help_id/d');
        if (!empty($help_id) && is_numeric($help_id)){
            $rtn['code'] = 0;
            $rtn['data'] = Base::getProductHelpDataByID($help_id);
            $rtn['msg'] = 'success';
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 获取产品分组信息
     * @return \think\response\Json
     */
    public function m_getProductGroup(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $base_api = new BaseApi();
        $res = $base_api->getGroupPost($this->login_user_id);
        if ($res['code'] == API_RETURN_SUCCESS){
            $rtn['data'] = $res['data'];
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
        }else{
            $rtn['msg'] = $res['code']."->".$res['msg'];
        }
        return json($rtn);
    }

    /**
     * 编辑产品
     * @return \think\response\Json
     */
    public function m_editAllProductData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '修改产品数据失败';
        if (request()->isAjax()) {//限制为ajax请求
            $product_data = htmlspecialchars_decode(input('post.product_data'));
            if (!empty($product_data)){
                $base_api = new BaseApi();
                $post_arr = json_decode($product_data, true);
                //更改前产品数据
                $product_res = $base_api->getProductInfoByID($post_arr['id']);
                //如果是其他语言
                if(isset($post_arr['lang'])&& $post_arr['lang'] !='en'){
                    $res = $base_api->updatePrdouctmMultiLangs($post_arr);
                    LogHandler::opRecord(__METHOD__,__FUNCTION__,'编辑产品（多语言）'.$post_arr['lang'].'-'.$post_arr['id'],$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/product/updatePrdouctmMultiLangs',json_encode($post_arr),json_encode($res),json_encode($product_res));
                    if($res['code'] == API_RETURN_SUCCESS){
                        $c_arr = explode('-', $post_arr['CategoryPath']);
                        $category_id = $c_arr[count($c_arr)-1];
                        $rtn['code'] = 0;
                        $rtn['msg'] = 'success';
                        $rtn['url'] = url('Product/shelfSuccess', ['product_id'=>$post_arr['id'],'category_id'=>$category_id, 'language'=>$post_arr['lang']]);
                        $rtn['data'] = $res;
                    }else{
                        $rtn['msg'] = $res['msg'];
                        Log::record('m_editAllProductData->修改产品失败'.print_r($res, true));
                    }
                }else{
                    //如果运费模板有变化(修改后的ID和之前的不一致)
                    $change_flag = 0;
                    if ($post_arr['oLogisticsTemplateId'] != $post_arr['LogisticsTemplateId']){
                        $change_flag = 1;
                    }
                    $template_id = $post_arr['LogisticsTemplateId'];
                    $template_name = $post_arr['LogisticsTemplateName'];

                    //运费模板ID、运费模板名称，延迟修改
                    unset($post_arr['oLogisticsTemplateId']);
                    unset($post_arr['LogisticsTemplateId']);
                    unset($post_arr['LogisticsTemplateName']);
                    /** 产品状态处理 start **/
                    //默认修改后状态变为“待审核”
                    $product_status_new = PRODUCT_STATUS_REVIEWING;
                    switch ($post_arr['ProductStatus']){
                        case PRODUCT_STATUS_SUCCESS:
                        case PRODUCT_STATUS_SUCCESS_UPDATE:
                            //如果是审核通过的，将状态修改为“正常销售，编辑状态”
                            $product_status_new = PRODUCT_STATUS_SUCCESS_UPDATE;
                            break;
                    }
                    $post_arr['ProductStatus'] = $product_status_new;
                    /** 产品状态处理 end **/
                    //修改历史同步数据标识
                    $post_arr['IsHistory'] = 0;
                    //描述图片特殊性处理（粘贴的情况下）
                    $pattern = "/src=\"\/uploads/";
                    $post_arr['Descriptions'] = preg_replace($pattern,'src="'.config('seller_img_url'), $post_arr['Descriptions']);

                    /** 活动判断，如果是活动，指定字段不能修改 start **/
                    $CategoryPath = $post_arr['CategoryPath'];
                    $LogisticsLimit = $post_arr['LogisticsLimit'];
                    if (
                        isset($product_res['data']['IsActivity'])
                        && !empty($product_res['data']['IsActivity'])
                        && $product_res['data']['IsActivity'] > 0
                    ){
                        unset($post_arr['Skus']);

                        unset($post_arr['CategoryPath']);

                        unset($post_arr['FirstCategory']);
                        unset($post_arr['SecondCategory']);
                        unset($post_arr['ThirdCategory']);
                        unset($post_arr['FourthCategory']);
                        unset($post_arr['FifthCategory']);

                        unset($post_arr['SalesUnitType']);

                        unset($post_arr['SalesMode']);
                        unset($post_arr['PackingList']);

                        unset($post_arr['LogisticsLimit']);
                    }
                    /** 活动判断，如果是活动，指定字段不能修改 end **/
                    //增加sellerID
                    $post_arr['StoreID'] = $product_res['data']['StoreID'];
                    $update_product_data = json_encode($post_arr);
                    Log::record('编辑产品数据：'.print_r($update_product_data, true));
                    $res = $base_api->updateProductInfoPost($update_product_data);
                    LogHandler::opRecord(__METHOD__,__FUNCTION__,'编辑产品-'.$post_arr['id'],$this->real_login_user_id, $this->real_login_user_name,'info',config('api_base_url').'/mallextend/product/update',$update_product_data,json_encode($res),json_encode($product_res));
                    if ($res['code'] == API_RETURN_SUCCESS){

                        /** 异步处理产品运费模板数据 start TODO....... 如果修改（通过之前的运费模板ID和修改后的ID对比），商城之前保存的数据如何处理？【方案：延迟更新运费模板，流程：先将变更的“产品ID”、“带电信息”、“运费模板ID”写进队列，之后异步更新运费模板数据至商城cost表，再更改产品运费ID为要变更的ID、Name，将之前商城运费模板cost对应的数据写入日志表记录下来，再删除之前商城运费模板cost数据。】 **/
                        //将产品ID，产品带电属性，所选运费模板ID，写入队列
                        $redis_cluster = new RedisClusterBase();
                        //如果运费模板有变化(修改后的ID和之前的不一致)
                        if ($change_flag == 1){
                            $redis_cluster->lPush(
                                QUEUE_PRODUCT_SHIPPING_TEMPLATE,
                                json_encode(
                                    [
                                        'product_id'=>$post_arr['id'],
                                        'product_is_charged'=>$LogisticsLimit[0],
                                        'template_id'=>$template_id,
                                        'template_name'=>$template_name,
                                        'from_flag'=>2 //来源标识：1-新增产品，2-修改产品信息
                                    ]
                                )
                            );
                        }
                        /** 异步处理产品运费模板数据 end **/
                        /** 异步处理产品、属性图片，传至CDN，生成小图操作 start TODO.............  参考修改运费模板？？ **/
                        //替换图片路径反斜杠
                        foreach ($post_arr['ImageSet']['ProductImg'] as $key=>&$img){
                            $post_arr['ImageSet']['ProductImg'][$key] = str_replace('\\', '/', $img);
                        }
                        foreach ($post_arr['ImageSet']['AttributeImg'] as $akey=>&$aimg){
                            $post_arr['ImageSet']['AttributeImg'][$akey] = str_replace('\\', '/', $aimg);
                        }
                        $up_imgs = array_diff(
                            array_merge($post_arr['ImageSet']['ProductImg'], $post_arr['ImageSet']['AttributeImg']),
                            array_merge($product_res['data']['ImageSet']['ProductImg'], $product_res['data']['ImageSet']['AttributeImg'])
                        );
                        $redis_cluster->lPush(
                            QUEUE_PRODUCT_MAIN_IMAGES,
                            json_encode(
                                [
                                    'product_id'=>$post_arr['id'],
                                    'imgs'=>$up_imgs
                                ]
                            )
                        );
                        /** 异步处理产品图片，传至CDN，生成小图操作 end **/
                        $c_arr = explode('-', $CategoryPath);
                        $category_id = $c_arr[count($c_arr)-1];
                        $rtn['code'] = 0;
                        $rtn['msg'] = 'success';
                        $rtn['url'] = url('Product/shelfSuccess', ['product_id'=>$post_arr['id'],'category_id'=>$category_id, 'language'=>'en']);
                        $rtn['data'] = $res;
                    }else{
                        $rtn['msg'] = $res['msg'];
                        Log::record('m_editAllProductData->修改产品失败'.print_r($res, true));
                    }
                }
            }else{
                $rtn['msg'] = '缺少必须参数';
            }
        }else{
            $rtn['msg'] = '错误访问';
        }
        return json($rtn);
    }

    /**
     * 根据带电属性获取存在的物流模板类型数据
     * @return \think\response\Json
     */
    public function async_getShippingTemplateTypeData(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $is_charged = input('is_charged/d');
        if (!empty($is_charged) && is_numeric($is_charged)){
            $data = [];
            //运费模板类型：10-Standard[标准物流运费]，20-SuperSaver[经济物流运费]，30-Expedited[快速物流运费]，40-Exclusive[专线物流运费]
            $model = new LogisticsManagementModel();
            $data10 = $model->getCountryDataByShippingServiceID(10, $is_charged);
            if (!empty($data10)){
                $data[] = Base::getShippingTamplateType(10);
            }
            $data20 = $model->getCountryDataByShippingServiceID(20, $is_charged);
            if (!empty($data20)){
                $data[] = Base::getShippingTamplateType(20);
            }
            $data30 = $model->getCountryDataByShippingServiceID(30, $is_charged);
            if (!empty($data30)){
                $data[] = Base::getShippingTamplateType(30);
            }
            $data40 = $model->getCountryDataByShippingServiceID(40, $is_charged);
            if (!empty($data40)){
                $data[] = Base::getShippingTamplateType(40);
            }
            $rtn['code'] = 0;
            $rtn['data'] = $data;
            $rtn['msg'] = 'success';
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 根据带电属性获取模板基础数据
     */
    public function async_getTemplateDataByIsCharged(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $is_charged = input('is_charged/d');
        if (!empty($is_charged) && is_numeric($is_charged)){
            $model = new ShippingTemplateModel();
            $data = $model->getTemplateDataByWhere(['is_charged'=>$is_charged, 'is_delete'=>0]);
            if (!empty($data)){
                $rtn['code'] = 0;
                $rtn['data'] = $data;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '没有相关信息';
            }
        }
        return json($rtn);
    }

    public function test(){

        $base_api = new BaseApi();
        echo $base_api->makeSign();
        $flag = true;
        if ($flag === TRUE){
            echo 88;
        }

        die;
        $temp = [];
        $temp[] = 1;
        $temp[] = 2;
        $temp[] = 3;
        $temp[] = 3;
        $temp[] = 4;

        $arr = json_decode('{"IsSuccess":true,"Description":"180610012784067402","Remark":"The order number for completing the delivery failure is 180610012784067402"}', true);

        pr($arr);
        if ($arr['IsSuccess'] === true){
            echo 999;
        }

        $i = 1;


        do{
            echo $i;
            $i = 4;
        }while($i<=3);

        die;



        pr(array_merge(array_unique($temp),[]));

        die;

        $data = [
            'template_id'=>73, //此参数只有在编辑运费模板时才会有
            'template_name'=>'test',
            'delivery_area'=>'china',
            'is_charged'=>1, //是否带电：1-为普货，2-为纯电，3-为带电
            'data'=>[
                [
                    'logisticsServices'=>10,
                    'freightSettings'=>2,
                    'relief'=>0
                ],
                [
                    'logisticsServices'=>20,
                    'freightSettings'=>1,
                    'relief'=>8
                ],
                [
                    'logisticsServices'=>30,
                    'freightSettings'=>3,
                    'country_data'=>[
                            [
                            'country'=>[
                                ['logistics_id'=>55, 'name'=>'AT', 'country_name'=>'Austria', 'area'=>'亚洲','shipping_service_text'=>'Exclusive'],
                                ['logistics_id'=>65, 'name'=>'SV', 'country_name'=>'El Salvador', 'area'=>'亚洲','shipping_service_text'=>'Toll'],
                                ['logistics_id'=>333, 'name'=>'US', 'country_name'=>'United States', 'area'=>'亚洲','shipping_service_text'=>'IB']
                            ],
                            'freightType'=>3,
                            'relief'=>10, //freightType=1，选择“标准运费时后效”
                            'custom_freight_data'=>[
                                'custom_freight_type'=>1, //1-重量规则，2-数量规则
                                'first_data'=>2,
                                'first_freight_type'=>1, //first_freight单位：1-按照百分比，2-单位为美元
                                'first_freight'=>14,
                                'increase_data'=>[
                                    [
                                        'start_data'=>2,
                                        'end_data'=>30,
                                        'add_data'=>1,
                                        'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                        'add_freight'=>4
                                    ],
                                    [
                                        'start_data'=>50,
                                        'end_data'=>100,
                                        'add_data'=>10,
                                        'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                        'add_freight'=>45
                                    ],
                                    [
                                        'start_data'=>100,
                                        'end_data'=>500,
                                        'add_data'=>10,
                                        'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                        'add_freight'=>66
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'logisticsServices'=>40,
                    'freightSettings'=>3,
                    'country_data'=>[
                        [
                            'country'=>[
                                [
                                    'logistics_id'=>53,
                                    'name'=>'AT',
                                    'country_name'=>'Austria',
                                    'area'=>'亚洲',
                                    'shipping_service_text'=>'Toll',

                                    'freightType'=>3,
                                    'relief'=>10,//freightType=1，选择“标准运费时后效”
                                    'custom_freight_data'=>[
                                        'custom_freight_type'=>1, //1-重量规则，2-数量规则
                                        'first_data'=>2,
                                        'first_freight_type'=>1, //first_freight单位：1-按照百分比，2-单位为美元
                                        'first_freight'=>14,
                                        'increase_data'=>[
                                            [
                                                'start_data'=>2,
                                                'end_data'=>50,
                                                'add_data'=>1,
                                                'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                'add_freight'=>8
                                            ],
                                            [
                                                'start_data'=>50,
                                                'end_data'=>200,
                                                'add_data'=>10,
                                                'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                'add_freight'=>55
                                            ],
                                            [
                                                'start_data'=>200,
                                                'end_data'=>500,
                                                'add_data'=>10,
                                                'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                'add_freight'=>88
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    'logistics_id'=>54,
                                    'name'=>'AG',
                                    'country_name'=>'Antigua And Barbuda',
                                    'area'=>'北美洲',
                                    'shipping_service_text'=>'IB',

                                    'freightType'=>3,
                                    'relief'=>10,//freightType=1，选择“标准运费时后效”
                                    'custom_freight_data'=>[
                                        'custom_freight_type'=>1, //1-重量规则，2-数量规则
                                        'first_data'=>2,
                                        'first_freight_type'=>1, //first_freight单位：1-按照百分比，2-单位为美元
                                        'first_freight'=>14,
                                        'increase_data'=>[
                                            [
                                                'start_data'=>2,
                                                'end_data'=>50,
                                                'add_data'=>1,
                                                'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                'add_freight'=>3
                                            ],
                                            [
                                                'start_data'=>50,
                                                'end_data'=>200,
                                                'add_data'=>10,
                                                'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                'add_freight'=>50
                                            ],
                                            [
                                                'start_data'=>200,
                                                'end_data'=>500,
                                                'add_data'=>10,
                                                'add_freight_type'=>1, //add_freight单位：1-按照百分比，2-单位为美元
                                                'add_freight'=>80
                                            ]
                                        ]
                                    ]
                                ],
                            ],
                        ]
                    ]
                ],
            ]
        ];

        echo json_encode($data);die;
        $model = new ShippingTemplateModel(); //ok
        $model->insertData($this->login_user_id, $data);//ok
//        $model->editorData($this->login_user_name, $data);
//        $model->copyShippingTemplateData(73);
//        return json($data);


//        Cache::store('redis')->set('redis-test', '001');
        /*new \Redis();
        $redis = new \RedisCluster(NULL,
            [
                'redis.dxinterns.com:7000',
                'redis.dxinterns.com:7001',
                'redis.dxinterns.com:7002',
            ]);
        $redis->set('testredis', json_encode(['id'=>11, 'name'=>'lth']));
        print_r($redis->get('testredis')) ;
        print_r($redis);

        $redis->lPush('addProductShippingTemplate', json_encode(['id'=>11, 'name'=>'lth']));

        $redis->close();*/
        /*$redis_base = new RedisClusterBase(config('redis_cluster_config'));
        $redis = $redis_base->handler();
        $res = $redis->lRange('addProductShippingTemplate',0,-1);
        $redis_base->set('ccc',['name'=>'1', 'age'=>'2345']);
        print_r($redis_base->get('ccc'));*/

        $redis_cluster = new RedisClusterBase(config('redis_cluster_config'));
        $redis_cluster->set('test', '11');
        $redis_cluster->lPush(
            'addProductShippingTemplateList',
            json_encode(['product_id'=>1,'product_is_charged'=>2,'template_id'=>72])
        );

//        $redis_base->rm('ccc');

//        print_r($res);

/*        $redis = Cache::connect(config('cache_redisd'));
//        $redis->master(true)->setnx('key');
        $ins = $redis->master(false)->handler();
//        $redis->master();
//        $ins->set('testredis', '1');
//        $redis->set();
        echo $ins->get('testredis');

        print_r($redis);*/


    }

    /**
     * 根据产品ID获取产品多语言翻译
     * @return \think\response\Json
     */
    public function getProductLangById(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $product_id = input('product_id');
        $type = input("type");//1产品翻译状态2标题翻译状态
        if (!empty($product_id)&&!empty($type)){
            $data = array();
            $product_multiLangs = config('product_multiLangs');
            $base_api = new BaseApi();
            $res = $base_api->getProductMultiLangs($product_id);
            if ($res['code'] == API_RETURN_SUCCESS){
                $resdata = $res['data'];$i =0;
                foreach($product_multiLangs as $k =>$val){
                    $data[$i]['title'] =$k;
                    $data[$i]['name'] =$val;
                    $data[$i]['status'] ='(翻译中)';
                    if(!empty($resdata)&&!empty($resdata['Title'][$k])&&!empty($resdata['Descriptions'][$k])&&!empty($resdata['Keywords'][$k])&&$type==1){
                        $data[$i]['status'] ='';
                    }
                    if(!empty($resdata)&&!empty($resdata['Title'][$k])&&$type==2){
                        $data[$i]['status'] =$resdata['Title'][$k];
                    }
                    if($k=='en'){
                        if($type==1){
                           $data[$i]['status'] =''; 
                       }else{
                         $i-=1;
                         unset($data[$i]);
                       }
                        
                    }
                    $i +=1;
                }
                $rtn['code'] = 0;
                $rtn['data'] = $data;
                $rtn['msg'] = 'success';
            }else{
                $rtn['msg'] = '请求接口错误：'.$res['code'];
            }
        }else{
            $rtn['msg'] = '产品ID为必填项';
        }
        return json($rtn);
    }
    /**
     * 获取当前最大skuCode
     * @return \think\response\Json
     */
    public function getMaxSkuCode(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '数据获取失败，请重试';
        $base_api = new BaseApi();
        $res = $base_api->getMaxSkuCode();
        if($res['code'] == API_RETURN_SUCCESS){
            $rtn['code'] = 0;
            $rtn['data'] = $res['data'];
            $rtn['msg'] = 'success';
        }else{
            $rtn['msg'] = '请求接口错误：'.$res['code'];
        }
        return json($rtn);
    }

    /*
     * 获取最新的运费模板规则并更新
     * */
    static function updateNewShippingCost($product_id){
        $base_api = new BaseApi();
        $shipping_data = $base_api->getShipping(['product_id'=>$product_id]);
        if(!empty($shipping_data['data'])){
            /*$logistics_where['countryCode'] = $paramData['countryCode'];
            $logistics_where['isCharged'] = $paramData['isCharged'];
            $logistics_where['shippingServiceID'] = $paramData['shippingServiceID'];
            $logistics_data = $base_api->getLogisticsManagement(['product_id'=>$product_id]);*/
        }
    }

}
