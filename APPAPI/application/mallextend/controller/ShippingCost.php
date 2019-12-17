<?php
namespace app\mallextend\controller;
use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\mallextend\product\ProductShippingParams;
use app\mallextend\model\ProductModel;
use app\mallextend\services\ProductShippingService;
use think\Exception;
use app\mallextend\model\ShippingCostModel;
use think\Log;


/**
 * 产品运费
 * @author 钟宁 2018/5/10
 */
class ShippingCost extends Base
{

    public $shippingService;
    public function __construct()
    {
        $this->shippingService = new ProductShippingService();
        parent::__construct();
    }
    /**
     * 产品运费新增
     * @return mixed
     */
    public function create(){
        $paramData = request()->post();

        $shippingModel = new ShippingCostModel();
        $productModel = new ProductModel();
        try{

            $product_id = $paramData['product_id'];
            $shiping_fee = $paramData['shipping_fee'];
            $shippingData = $paramData['data'];

            //判断运费是否存在
            if(empty($product_id)){
                return apiReturn(['code'=>100000001,'msg'=>'error']);
            }

            $isExist = $shippingModel->getShippingCost(['id' => $product_id]);
            if(
                !empty($isExist)
                && !isset($paramData['is_update'])
            ){
                //因更新而调用的不用删除，因为会在其他接口调用删除且做备份等相关操作
                $shippingModel->del($product_id);
            }
            //更新运费
            if($shiping_fee == 1){
                $shiping_fee = 0;
            }
            $productModel->updateProductKey(['_id'=>(int)$product_id],['ShippingFee'=>(int)$shiping_fee]);

            foreach($shippingData as $key => $shipping){
                $shipping['ToCountry'] = trim($shipping['ToCountry']);
                $shippingModel->add($shipping);
            }
            return apiReturn(['code'=>200]);
        }catch (\Exception $e){
            return apiReturn(['code'=>100000001,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 修改产品时修改运费模板【修改运费模板定时任务调用】
     *   执行流程如下：
     *      a.再更改产品运费ID为要变更的ID、Name；
     *      b.将之前商城运费模板cost对应的数据（ProductId == $params["product_id"] && TempletID != $params["template_id"]）写入日志表（需要新增）记录下来；
     *      c.再删除之前商城运费模板cost数据（ProductId == $params["product_id"] && TempletID != $params["template_id"]）。
     *
     * Array
        (
            [product_id] => 22
            [product_is_charged] => 2
            [template_id] => 72
            [template_name] => '' //from_flag = 2时存在
            [from_flag] => 1 //来源标识：1-新增产品，2-修改产品信息
        )
     * @return mixed
     */
    public function updateForUpdateProduct(){
        $paramData = request()->post();
        //参数校验
        if (
            !isset($paramData['product_id']) || empty($paramData['product_id'])
            ||!isset($paramData['template_id']) || empty($paramData['template_id'])
            ||!isset($paramData['template_name']) || empty($paramData['template_name'])
        ){
            return apiReturn(['code'=>1002,'msg'=>'缺少必传参数']);
        }
        try{
            $product_model = new ProductModel();
            $shipping_model = new ShippingCostModel();
            //a.再更改产品运费ID为要变更的ID、Name；
            $product_model->updateDataForTemplate($paramData);
            //b.将之前商城运费模板cost对应的数据（ProductId == $params["product_id"] && TempletID != $params["template_id"]）写入日志表（需要新增）记录下来；
            $data = $shipping_model->getDataByWhereForUpdate($paramData);
            foreach ($data as &$info){
                unset($info['_id']);
            }
            $shipping_model->addAllUpdataBackData($data);
            //c.再删除之前商城运费模板cost数据（ProductId == $params["product_id"] && TempletID != $params["template_id"]）
            $shipping_model->deleteDataByWhereForUpdate($paramData);
            return apiReturn(['code'=>200]);
        }catch (\Exception $e){
            Log::record('修改产品时修改运费模板【修改运费模板定时任务调用】,系统异常 '.$e->getMessage());
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 修改运费模板时同步运费模板【修改运费模板定时任务调用】
     * 根据产品ID、运费模板ID修改商城运费数据（方案：添加新的运费数据、删除旧的运费数据并备份）
     * @return mixed
     */
    public function updateForShippingTemplateEditor(){
        try{
            $paramData = request()->post();
            //判断运费是否存在
            if(
                !isset($paramData['product_id']) || empty($paramData['product_id'])
                || !isset($paramData['template_id']) || empty($paramData['template_id'])
                || !isset($paramData['shipping_fee'])
                || !isset($paramData['time']) || empty($paramData['time'])
                || !isset($paramData['data']) || empty($paramData['data'])
            ){
                return apiReturn(['code'=>100000001,'msg'=>'error']);
            }
            $shippingModel = new ShippingCostModel();
            $productModel = new ProductModel();

            $product_id = $paramData['product_id'];
            $template_id = $paramData['template_id'];
            $shiping_fee = $paramData['shipping_fee'];
            $time = $paramData['time'];
            $shippingData = $paramData['data'];

            //1、产品是否免邮判断
            if($shiping_fee == 1){
                $shiping_fee = 0;
            }

            $productModel->updateProductKey(['_id'=>(int)$product_id],['ShippingFee'=>(int)$shiping_fee]);

            //获取修改前 【备份cost数据】
            //将之前商城运费模板cost对应的数据（ProductId == $paramData["product_id"] && TempletID == $paramData["template_id"]）写入日志表（需要新增）记录下来；
            $data = $shippingModel->getDataByWhereForSTUpdate(['product_id'=>$product_id, 'template_id'=>$template_id]);
            foreach ($data as &$info){
                unset($info['_id']);
            }
            $shippingModel->addAllUpdataBackData($data);
            //2、添加新运费数据
            foreach($shippingData as $key => $shipping){
                $shippingModel->add($shipping);
            }
            //3、删除旧的运费数据（根据添加时间来判断）
            $shippingModel->deleteByWhereForEditorST(['product_id'=>$product_id, 'template_id'=>$template_id, 'time'=>$time]);
            return apiReturn(['code'=>200]);
        }catch (\Exception $e){
            return apiReturn(['code'=>100000001,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据条件获取数据
     * @return mixed
     */
    public function getData(){
        try{
            $paramData = request()->post();
            if (empty($paramData)){
                return apiReturn(['code'=>1003]);
            }
            $model = new ShippingCostModel();
            $data = $model->getDataByWhere($paramData);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据条件获取产品数据
     * @return mixed
     */
    public function getDataByWhere(){
        try{
            $paramData = request()->post();
            if (empty($paramData)){
                return apiReturn(['code'=>1003]);
            }
            $model = new ShippingCostModel();
            $data = $model->getProductDataByWhere($paramData);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    public function do916Shipping(){
        ini_set('max_execution_time', '0');
        $ids = [2007432,2008114,2600689,2600778,2006902,2008286,2600791,2012057];
        $param = input();
        $productModel = new ProductModel;
        //$skus = $this->skus;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
//        $product_ids = $productModel->queryProductUpdateFor916(['seller_id'=>888,'page'=>$param['page'],'skus'=>$skus]);
        $product_ids = $productModel->queryProductUpdateFor916(['seller_id'=>888,'page'=>$param['page'],'ids'=>$ids]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateShippingFor916($product_ids['data']);
        }
        $url = url('ShippingCost/do916Shipping', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');

    }

    /**
     * 更新
     * 店铺id 888
     * skuid 916开头
     */
    public function updateShippingFor916($product_ids){
        ini_set('max_execution_time', '0');
        $rate = 1;
        $price = 0;
        //获取费率接口
        $currency = doCurl(config("currency_url"));
        if (!empty($currency) && is_array($currency)) {
            foreach ($currency as $k => $v) {
                if ($v['From'] == 'CNY' && $v['To'] == 'USD') {
                    $rate = $v['Rate'];
                }
            }
        }
        $shippingModel = new ShippingCostModel();
        $productModel = new ProductModel;


//        $product_ids = $productModel->queryProductUpdateFor916(['seller_id'=>888],1,10000);

        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $priceArray = array();
                pr($product_id['_id']);
//                Log::record('同步 916product_id:'.$product_id['_id'],'error');
                if(!empty($product_id['Skus'])){
                    //为了取最大价格
                    foreach($product_id['Skus'] as $sk => $skus){
                        $priceArray[$skus['_id']] = $skus['SalesPrice'];
                    }
                    $sku = CommonLib::getColumn('Code',$product_id['Skus']);
                    $sku = implode(',',$sku);
                    //走接口获取运费价格
                    pr('https://szdpd.tradeglobals.com/test-sku-area.php?sku='.$sku);
                    $newShippingList = doCurl('https://szdpd.tradeglobals.com/test-sku-area.php',null,['sku'=>$sku]);

                    //没有916产品数据
                    if(empty($newShippingList)){
                        pr('szdpd.tradeglobals.com no data');
                        continue;
                    }
                    $update =  array();
                    foreach($newShippingList as $key => $newShippingData){
                        if(empty($newShippingData['country_price'])){
                            pr("----price = 0 :".$newShippingData['sku']);
                            \think\Log::pathlog('price skuid = ',$newShippingData['sku'],'FunctionRequest3.log');
                            continue;
                        }
                        if($key == 0){
                            //查找运费模板
                            foreach($newShippingData['country_price'] as $country_price){
                                $oldShippingData = $shippingModel->findShipping(['product_id'=>$product_id['_id'],'to_country'=>$country_price['country']]);
                                //没有找到运费模板
                                if(empty($oldShippingData)){
//                                    pr('noshipping'.$country_price['country']);
                                    continue;
                                }
                                //重新赋值规则
                                foreach($oldShippingData['ShippingCost'] as $oldKey => $ShippingCost){
                                    if(isset($country_price['shipchannel'][$ShippingCost['ShippingServiceID']])){
                                        $rule = $country_price['shipchannel'][$ShippingCost['ShippingServiceID']];
                                        $calculation_formula = '<?php '.htmlspecialchars_decode($rule).' ?>';
                                        eval( '?>' .$calculation_formula );
                                        if($price == 0){
                                            $oldShippingData['ShippingCost'][$oldKey]['ShippingType'] = 2 ;
                                            $oldShippingData['ShippingCost'][$oldKey]['Cost'] = 0 ;
                                            $oldShippingData['ShippingCost'][$oldKey]['LmsRuleInfo'] = $rule ;
                                        }else{
                                            $oldShippingData['ShippingCost'][$oldKey]['ShippingType'] = 1 ;
                                            $newPrice = round($price / $rate,4);
                                            $ruleString = 'if(1) $price = '.$newPrice.';';
                                            $oldShippingData['ShippingCost'][$oldKey]['LmsRuleInfo'] = $ruleString ;
                                        }

                                    }else{
                                        unset($oldShippingData['ShippingCost'][$oldKey]);
                                    }
                                }
                                $ShippingCost = array_values($oldShippingData['ShippingCost']);

                                //更新运费模板
                                $ret = $shippingModel->updateShipping(['ProductId'=>(string)$product_id['_id'],'ToCountry'=>$country_price['country']],
                                    ['ShippingCost'=>(object)$ShippingCost]);
                            }
                        }
                        if((double)$newShippingData['price'] == 0){
                            continue;
                        }
                        //更新产品价格
//                        if($newShippingData['price'] < $product_id['LowPrice']){
//                            $update['LowPrice'] = (double)$newShippingData['price'];
//                        }
                        foreach($product_id['Skus'] as $sk => $skus){
                            if($skus['_id'] == $newShippingData['sku']){
                                //更新价格
                                $update['Skus.'.$sk.'.SalesPrice'] = (double)$newShippingData['price'];
                                $priceArray[$skus['_id']] = (double)$newShippingData['price'];
                                //更新批发价格
                                $bulkRatePrice = round((double)$newShippingData['price'] - (double)$newShippingData['price'] * 0.025,2);
                                $update['Skus.'.$sk.'.BulkRateSet.SalesPrice'] = (double)$bulkRatePrice;
                                $update['Skus.'.$sk.'.BulkRateSet.Discount'] = round(($newShippingData['price'] - $bulkRatePrice) / $newShippingData['price'],3);

                            }
                        }
                    }
                    if(empty($update)){
                        continue;
                    }
                    $update['HightPrice'] = (double)max($priceArray);
                    $update['LowPrice'] = (double)min($priceArray);
                    $update['ProductStatus'] = 1;
                    \think\Log::pathlog('sup = ',$product_id['_id'],'FunctionRequest4.log');
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                    pr($ret);
                }
            }
        }
        pr('success');


    }

    /**
     * 修复有空格国家的运费数据
     */
    public function updateErrorShippingData(){
        ini_set('max_execution_time', '0');
        $shippingModel = new ShippingCostModel();
        $productModel = new ProductModel;
        $product_ids = $productModel->getProductId(1,10000);
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $oldShippingData = $shippingModel->selectShipping(['product_id'=>$product_id['_id']]);
                if(!empty($oldShippingData)){
                    foreach($oldShippingData as $oldKey => $val){
                        $toCountry = trim($val['ToCountry']);
                        $ret = $shippingModel->updateShipping(['_id'=>$val['_id']],['ToCountry'=>$toCountry]);
                    }
                }
            }
        }
        pr('success');
    }


    /**
     * 获取产品运费
     */
    public function getShippingCost(){
        try{
            $params = request()->post();
            $data = $this->shippingService->countProductShipping($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取产品运费
     */
    public function getBatchShippingCost(){
        try{
            $params = request()->post();
            $paramsData['count'] = isset($params['count']) ? $params['count'] : 1;
            $paramsData['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
            $paramsData['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
            $paramsData['spus'] = isset($params['spus']) ? explode(',',$params['spus']) : array();
            $paramsData['countrys'] = isset($params['countrys']) ? explode(',',$params['countrys']) : array();

            $data = $this->shippingService->getBatchShippingCost($paramsData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取产品运费信息
     */
    public function getProuductShippingInfo(){
        $product_model = new ProductModel();
        $params = input();
        $validate = $this->validate($params,(new ProductShippingParams())->getProductShippingRule());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        $productData = $product_model->getProductInField(
            ['_id'=>(int)$params['product_id'],'ProductStatus' => ['in',[1,5]]],
            ['Skus._id','Skus.Code','Skus.SalesPrice','Skus.Inventory','PackingList','LogisticsLimit','LogisticsTemplateId','LogisticsTemplateName']
        );
        if(empty($productData)){
            return (['code'=>1002, 'msg'=>'not found product']);
        }
        return apiReturn(['code'=>200,'data'=>$productData]);
    }

    /**
     * 修改产品运费模板
     */
    public function updateProductShipping(){
        $redis = new RedisClusterBase();
        $product_model = new ProductModel();
        $params = input();
        //参数校验
        $validate = $this->validate($params,(new ProductShippingParams())->updateShippingRule());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        $productData = $product_model->getProductInField(
            ['_id'=>(int)$params['product_id'],'ProductStatus' => ['in',[1,5]]],
            ['LogisticsLimit','LogisticsTemplateId','LogisticsTemplateName']);
        if(empty($productData)){
            return (['code'=>1002, 'msg'=>'not found product']);
        }
        /* 异步处理产品运费模板数据 start */
        //将产品ID，产品带电属性，所选运费模板ID，写入队列
        $ret = $redis->lPush(
            'addProductShippingTemplateList',
            json_encode(
                [
                    'product_id' => $params['product_id'],
                    'product_is_charged' => $productData['LogisticsLimit'][0],
                    'template_id' => $params['template_id'],
                    'template_name' => $params['template_name'],
                    'from_flag' => 2 //来源标识：1-新增产品，2-修改产品信息
                ]
            )
        );
        if($ret ==  false){
            $params['oldtemplateid'] = $productData['LogisticsTemplateId'];
            //记录日志
            \think\Log::pathlog('params = ',$params,'updateProductShipping.log');
            return apiReturn(['code'=>1002]);
        }
        return apiReturn(['code'=>200]);
    }
}
