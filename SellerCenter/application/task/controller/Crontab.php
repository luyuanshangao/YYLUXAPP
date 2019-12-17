<?php
namespace app\task\controller;

use app\index\dxcommon\Base;
use app\index\dxcommon\FTPUpload;
use app\index\dxcommon\Product;
use app\index\dxcommon\RedisClusterBase;
use app\index\dxcommon\BaseApi;
use app\index\model\LogisticsManagementModel;
use app\index\model\ShippingTemplateModel;
use think\Controller;
use think\Log;

/**
 * seller端定时服务类
 * Class Crontab
 * @author tinghu.liu
 * @date 2018-05-08
 * @package app\task\controller
 */

class Crontab extends Controller
{
    //FTP相关配置
    private $ftp_config;
    //FTP地址配置
    private $ftp_dir_config;
    //队列处理数量限制
    private $queue_handle_number_limit;

    public $base_api;

    /**
     * 同步历史数据运费模板标识
     * 要和获取历史产品接口保持一致
     */
    const ISHISTORYISSYNCSTANDIMGSFLAG = 8;

    public function __construct()
    {
        $this->ftp_config = config('ftp_config');
        $this->original_ftp_config = config('original_ftp_config');
        $this->ftp_dir_config = $this->ftp_config['UPLOAD_DIR'];
        $this->original_ftp_dir_config = $this->original_ftp_config['UPLOAD_DIR'];
        $this->queue_handle_number_limit = config('queue_handle_limit_number');
        $this->product_pic_upload_dir = config('product_pic_upload_dir');
        $this->upload_product_images_save = $this->ftp_config["UPLOAD_DIR"]['PRODUCT_IMAGES_SAVE'];
        $this->base_api = new BaseApi();
    }

    /**
     * 异步-处理产品上传，产品主图同步至CDN，且生成小图（70*70,210*210）
     */
    public function asyncProductImags(){
        //图片上传至本地的路径
        //生成小图请求地址
        $cdn_thumbnail_url = config('cdn_thumbnail_url');
        $upload_dir = $this->product_pic_upload_dir;//上传存放路径
        $water_dir = $this->product_pic_upload_dir.DS.'water';
        //队列名称
        $queue_key = QUEUE_PRODUCT_MAIN_IMAGES;
        $redis_base = new RedisClusterBase();
        //队列长度
        $list_length = $redis_base->lLen($queue_key);
        if ($list_length == 0){
            echo '<span style="color: red;">The data is empty...</span>';
        }
        if ($list_length < $this->queue_handle_number_limit){
            $this->queue_handle_number_limit = $list_length;
        }
        //循环处理队列所有数据
        for ($i=0; $i<$this->queue_handle_number_limit; $i++){
            try{
                $flag = false;
                /**
                 * Array
                (
                    [product_id] => 145
                    [imgs] => Array
                    (
                        [0] => /newprdimgs/20180531/87b040ada109fe390977ca8f6c02c3ab.png
                        [1] => /newprdimgs/20180531/1e7bf8e5003d1755ebdbe5b0e49fac1e.png
                    )
                    [from_flag] => 1-seller端上传图片（默认），2-erp上传图片（因为大图已经提前上传至CDN，所以只需要生成对应小图即可）
                )
                 */
                /*$rdata = $redis_base->lRange($queue_key, 0, 1);
                $data = json_decode($rdata[0], true);*/
                $data = json_decode($redis_base->rPop($queue_key), true);
                //1-seller端上传图片（默认），2-erp上传图片（因为大图已经提前上传至CDN，所以只需要生成对应小图即可）
                $from_flag = isset($data['from_flag'])?$data['from_flag']:1;
                if (!empty($data['imgs'])){
                    foreach ($data['imgs'] as $key=>$img){
                        if (!empty($img)){
                            //分割图片名称和上传目录
                            $img_ep_arr = explode('/', $img);
                            //分割图片名称，将名称和后缀名分割
                            $img_data = explode('.', $img_ep_arr[3]);
                            //上传至CDN
                            $dir_path = $this->ftp_dir_config['PRODUCT_IMAGES'].$img_ep_arr[1].'/'.$img_ep_arr[2];
                            //seller端上传图片，需要异步上传至CDN
                            $res = false;
                            if ($from_flag == 1){
                                /*上传原图到CDN*/
                                $img_ep_arr[4] = substr($img_data[0],0,-2);
                                $original_romote_file = $upload_dir.DS.$img;

                                $ftp_put_data = [
                                    'dirPath'=>$dir_path,
                                    'romote_file'=>$img_ep_arr[3], // 保存文件的名称
                                    'local_file'=>$original_romote_file
                                ];
                                $res = FTPUpload::data_put($ftp_put_data,config('original_ftp_config'));
                                $water_romote_file = $water_dir.DS.$img_ep_arr[2].DS.$img_ep_arr[3];
                                if($res){
                                    $ftp_put_data = [
                                        'dirPath'=>$dir_path,
                                        'romote_file'=>$img_ep_arr[3], // 保存文件的名称
                                        'local_file'=>$water_romote_file
                                    ];
                                    $res = FTPUpload::data_put($ftp_put_data);
                                    if($res){
                                        //删除本地原图
                                        @unlink($original_romote_file);
                                        @unlink($water_romote_file);
                                        Log::record('删除图片（product_id：'.$data['product_id'].'，original_romote_file：'.$original_romote_file.',water_romote_file:'.$water_romote_file.'）成功');
                                    }
                                }
                            }
                            //erp上传的图片不需要再次上传CDN，只需要生成对应小图
                            if ($res || $from_flag == 2){
                                //生成主图（默认第一张为主图）小图（规格：210*210）
                                if ($key == 0){
                                    $url_210 = $cdn_thumbnail_url.'/'.$dir_path.'/'.$img_data[0].'_210x210.'.$img_data[1];
                                    /*
                                     * 请求三次生成小图路径，如果中间返回成功跳出，如果请求三次还是失败，记入日志
                                     * kevin 2019/01/18
                                     * */
                                    $is_success = $this->repeatedlyCurlRequest($url_210,3);
                                    Log::write('$mall_pic_210,url:'.$url_210.', $product_id:'.$data['product_id'].', is_success:'.$is_success);
                                }
                                //生成小图（规格：70*70）
                                $url_70 = $cdn_thumbnail_url.'/'.$dir_path.'/'.$img_data[0].'_70x70.'.$img_data[1];
                                /*
                                     * 请求三次生成小图路径，如果中间返回成功跳出，如果请求三次还是失败，记入日志
                                     * kevin 2019/01/18
                                     * */
                                $is_success = $this->repeatedlyCurlRequest($url_70,3);
                                Log::write('$mall_pic_70,url:'.$url_70.', $product_id:'.$data['product_id'].', is_success:'.$is_success);
                                $flag = true;
                            }else{
                                $flag = false;
                            }
                        }
                    }
                    //处理失败或异常则重新加入队列
                    if (!$flag){
                        $queue_times = isset($data['queue_times'])?$data['queue_times']:0;
                        if ($queue_times <= 5){
                            $data['queue_times'] = $queue_times+1;
                            $redis_base->lPush($queue_key, json_encode($data));
                        }
                    }else{
                        echo "<br><br>============<span style='color: blue;'>处理成功的数据：</span>=============<br><br>";
                        pr($data);
                    }
                }
            }catch (\Exception $e){
                //程序异常，则重新加入队列
                $queue_times = isset($data['queue_times'])?$data['queue_times']:0;
                if ($queue_times <= 5){
                    $data['queue_times'] = $queue_times+1;
                    $redis_base->lPush($queue_key, json_encode($data));
                }
                Log::record('产品图片同步CDN&&生成小图，程序异常, data:'.json_encode($data).'（product_id：'.$data['product_id'].'，'.$e->getMessage());
                echo '产品图片同步CDN&&生成小图，程序异常（product_id：'.$data['product_id'].$e->getMessage().'<br><br>';
            }
        }
    }

    /**
     * 异步-处理上传产品，同步运费模板
     * https://seller.dx.com/task/crontab/asyncProductShippingTemplate?product_id=&product_is_charged=&template_id=&template_name=&from_flag=2
     *
     * http://seller.localhost.com/task/crontab/asyncProductShippingTemplate?product_id=&product_is_charged=&template_id=&template_name=&from_flag=2
     */
    public function asyncProductShippingTemplate(){
        ini_set('max_execution_time', '0');
        $queue_key = QUEUE_PRODUCT_SHIPPING_TEMPLATE;
        $_sync_direction = input('sync_direction',1); //同步处理队列方向：1-右（默认），2-左
        $_product_id = input('product_id');
        $_product_is_charged = input('product_is_charged');
        $_template_id = input('template_id');
        $_template_name = input('template_name');
        $_from_flag = input('from_flag');
        $_is_manual = false;
        if (
            !empty($_product_id)
            && !empty($_product_is_charged)
            && !empty($_template_id)
            && !empty($_template_name)
            && !empty($_from_flag)
        ){
            $_is_manual = true;
        }
        $redis_base = new RedisClusterBase();
        //队列长度
        $list_length = $redis_base->lLen($queue_key);
        pr('$list_length:'.$list_length);
        if(ob_get_level()>0) {
            @ob_flush();
            @flush();
        }
        //pr($redis_base->lRange($queue_key, 0, -1));die;
        if ($list_length == 0 && !$_is_manual)
            pr('没有需要同步的数据...');
        if ($list_length < $this->queue_handle_number_limit){
            $this->queue_handle_number_limit = $list_length;
        }
        if ($_is_manual)
            $this->queue_handle_number_limit = 1;
        pr('处理条数：'.$this->queue_handle_number_limit);
        if(ob_get_level()>0) {
            @ob_flush();
            @flush();
        }
        //循环处理队列所有数据
        for ($i=0; $i<$this->queue_handle_number_limit; $i++){
            /**
             * 获取队列的数据，格式：
             * Array
                (
                    [product_id] => 22
                    [product_is_charged] => 2
                    [template_id] => 72
                    [template_name] => '' //from_flag = 2时存在
                    [from_flag] => 1 //来源标识：1-新增产品，2-修改产品信息
                )
             */
            if (!$_is_manual){
                if ($_sync_direction == 1){
                    $data = json_decode($redis_base->rPop($queue_key), true);
                }elseif ($_sync_direction == 2){
                    $data = json_decode($redis_base->lPop($queue_key), true);
                }else{
                    pr('$i：'.$i.'，数据方向错误');
                    continue;
                }
            }
            if ($_is_manual)
            {
                $data['product_id'] = $_product_id;
                $data['product_is_charged'] = $_product_is_charged;
                $data['template_id'] = $_template_id;
                $data['template_name'] = $_template_name;
                $data['from_flag'] = $_from_flag;
            }
            pr('$i：'.$i);
            pr('$i-data：');
            pr($data);

            $flag = true;
            try{
                //判断是否是更新
                $is_update = false;
                if (isset($data['from_flag']) && $data['from_flag'] == 2){
                    $is_update = true;
                }
                //拼装符合条件的运费模板数据
                $post_data = $this->getAsyncProductShippingTemplateData($data);
//                Log::record('post数据至商城-$post_data-before88:'.print_r(json_encode($post_data), true));
                $post_data = $this->handleShippingTemplatePostData($post_data);
//                Log::record('post数据至商城-$post_data:'.print_r(json_encode($post_data), true));

                if(ob_get_level()>0) {
                    @ob_flush();
                    @flush();
                }
                $base_api = new BaseApi();
                //post数据至商城(通过接口的形式)
                if (!empty($post_data)){
                    if ($is_update){
                        $post_data['is_update'] = 1;
                    }
                    $res = $base_api->createShippingCost($post_data);
                    //判断接口返回的状态，不成功则修改$flag为false
                    if ($res['code'] != API_RETURN_SUCCESS){
                        $flag = false;
                        Log::record('post数据至商城-res:'.print_r($res, true));
                    }else{
                        /**
                         * 数据来至：修改产品信息。需要的操作步骤：
                         * 1.异步更新运费模板数据至商城cost表 - （以上已处理）
                         * 2.调用商城提供的接口处理以下逻辑
                         *    a.再更改产品运费ID为要变更的ID、Name；
                         *    b.将之前商城运费模板cost对应的数据写入日志表（需要新增）记录下来；
                         *    c.再删除之前商城运费模板cost数据。
                         */
                        if ($is_update){
                            $res_last = $base_api->updateShippingCostForUpdateProduct($data);
                            if ($res_last['code'] != API_RETURN_SUCCESS) {
                                $flag = false;
                                //将“异步更新运费模板数据至商城cost表”的数据删除 TODO 容错机制（为了数据的统一）方案：另开一个队列来处理？
                                Log::record('数据来至：修改产品信息-res:'.print_r($res_last, true));
                            }
                        }
                    }
                }else{
                    pr('$post_data为空的数据');
                    pr($data);
                }
            } catch (\Exception $e) {
                Log::record('asyncProductShippingTemplate-异常:'.$e->getMessage());
                pr('asyncProductShippingTemplate-异常:'.$e->getMessage());
                pr($data);
                if(ob_get_level()>0) {
                    @ob_flush();
                    @flush();
                }
                $flag = false;
            }
            //存在异常情况则将取出的队列数据重新入队
            if (!$flag){
                $redis_base->lPush($queue_key, json_encode($data));
                Log::record('asyncProductShippingTemplate-fail:'.print_r($data, true));
                pr('执行失败');
            }else{
                pr('执行成功');
//                pr($data);
                Log::record('asyncProductShippingTemplate-success:'.print_r($data, true));
                if(ob_get_level()>0) {
                    @ob_flush();
                    @flush();
                }
            }
//            sleep(1);
        }
    }

    /**
     * 异步-编辑运费模板，数据同步
     */
    public function asyncShippingTemplateForEditor(){
        ini_set('max_execution_time', '0');
        $queue_key = QUEUE_SHIPPING_TEMPLATE_EDITOR;
        $redis_base = new RedisClusterBase();
        //队列长度
        $list_length = $redis_base->lLen($queue_key);
        //pr($redis_base->lRange($queue_key, 0, -1));/*die;*/
        if ($list_length == 0)
            pr('没有需要同步的数据...');
        if ($list_length < $this->queue_handle_number_limit){
            $this->queue_handle_number_limit = $list_length;
        }
        $base_api = new BaseApi();
        //循环处理队列所有数据
        for ($i=0; $i<$this->queue_handle_number_limit; $i++) {
            /**
             * 获取队列的数据，格式：
             * Array
             * (
             *      [template_id] => 72
             *      [is_charged] => 2
             *      [add_time] => 1531793126
             * )
             * 编辑运费模板逻辑处理：
             * 1、根据运费模板ID获取已绑定的产品数据
             * 2、根据产品ID、运费模板ID修改商城运费数据（方案：添加新的运费数据、删除旧的运费数据并备份）
             */
            $data = json_decode($redis_base->rPop($queue_key), true);
            $template_id = $data['template_id'];
            $is_charged = $data['is_charged'];
            //pr($data);
            try{
                //1、根据运费模板ID获取已绑定的产品数据
                $cost_data = $base_api->shippingCostGetData_New(['template_id'=>$template_id]);
                //pr($cost_data);
                if ($cost_data['code'] == API_RETURN_SUCCESS){
                    //获取产品ID
                    $product_id_arr = [];
                    foreach ($cost_data['data'] as $cost){
                        $product_id_arr[] = $cost['_id'];
                    }
                    $product_id_arr = array_unique($product_id_arr);
                    //重新组装产品ID、运费模板ID
                    $new_data = [];
                    foreach ($product_id_arr as $info){
                        $temp = [];
                        $temp['product_id'] = $info;
                        $temp['template_id'] = $template_id;
                        $temp['product_is_charged'] = $is_charged;
                        $new_data[] = $temp;
                    }
                    //pr($product_id_arr);
                    //pr($new_data);
                    //2、根据产品ID、运费模板ID修改商城运费数据（方案：添加新的运费数据、删除旧的运费数据并备份）
                    foreach ($new_data as $new_info){
                        $post_data = $this->getAsyncProductShippingTemplateData($new_info);
                        $post_data = $this->handleShippingTemplatePostData($post_data);
                        //pr($post_data);
                        $editor_res = $base_api->updateForShippingTemplateEditor($post_data);
                        Log::record('更新运费模板返回 $editor_res （'.json_encode($data).'）,params:'.json_encode($new_info).', '.json_encode($editor_res));
                    }
                    pr('已处理的数据（'.json_encode($data).'）-》 '.json_encode($new_data));
                }else{
                    pr('获取产品数据失败（'.json_encode($data).'） '.json_encode($cost_data));
                    Log::record('获取产品数据失败（'.json_encode($data).'） '.json_encode($cost_data));
                    $redis_base->lPush($queue_key, json_encode($data));
                }
            }catch (\Exception $e){
                pr('系统异常（'.json_encode($data).'） '.$e->getMessage());
                Log::record('asyncShippingTemplateForEditor系统异常（'.json_encode($data).'）：'.$e->getMessage());
                $redis_base->lPush($queue_key, json_encode($data));
            }
            //$redis_base->lPush($queue_key, json_encode($data));
        }
    }

    /**
     * 获取“异步-处理上传产品，同步运费模板”数据
     * @param array $param 参数
     * @return array
     */
    private function getAsyncProductShippingTemplateData(array $param){
        //拼装符合条件的运费模板数据
        /*$template_id = 80;
        $product_is_charged = 1;
        $product_id = 85;*/
        $time = time();
        $template_id = $param['template_id'];
        $product_is_charged = $param['product_is_charged'];
        $product_id = $param['product_id'];
        $template_data = Product::getShippingTemplateByIDForPost($template_id);
        $delivery_area = $template_data['delivery_area']; //发货国
        $template_name = $template_data['template_name']; //模板名称
        $is_free_shipping = $template_data['is_free_shipping']; //是否免邮：1-免邮，2-不免邮
        $rtn = [];
        $post_data = [];
        //获取运费模板包含的所有国家信息 start
        $country_arr = [];
        if (isset($template_data['data'])){
            foreach ($template_data['data'] as $type_info){
                if (isset($type_info['country_data'])){
                    foreach ($type_info['country_data'] as $country_info){
                        $country_arr[] = isset($country_info['country_code'])?$country_info['country_code']:'';
                    }
                }
            }
        }
        //去重
        $country_arr = array_unique($country_arr);
        //获取运费模板包含的所有国家信息 end
        foreach ($country_arr as $country_code){ //循环国家信息,根据国家获取对应模板类型下的运费参数
            //拼装到货国运费参数
            $post_data_tem = [];
            $post_data_tem['ProductId'] = $product_id;
            $post_data_tem['IsCharged'] = $product_is_charged;
            $post_data_tem['FormCountry'] = $delivery_area;
            $post_data_tem['ToCountry'] = $country_code;
            foreach ($template_data['data'] as $key => $type_info){ //循环物流服务
                $template_type_name = $type_info['template_type_name'];
                $template_type = $type_info['template_type'];
                //运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
                $shipping_type = $type_info['shipping_type'];
                //折扣，在$shipping_type = 1时有效
                $discount = $type_info['discount'];
                $country_data = isset($type_info['country_data'])?$type_info['country_data']:[];
                //获取符合国家的运费数据
                $rdata = [];
                if (!empty($country_data)){
                    $rdata = array_filter($country_data, function($t) use ($country_code){
                        return $t['country_code'] == $country_code;
                    });
                }
                if(!empty($rdata)){
                    $rdata = array_shift($rdata);//目的国详情以及运费计算相关参数详情
                    $post_data_tem['ShippingCost'][$key]['Cost'] = 0;
                    $post_data_tem['ShippingCost'][$key]['ShippingServiceID'] = $template_type;
                    if ($template_type == 40){
                        $post_data_tem['ShippingCost'][$key]['ShippingService'] = $rdata['shipping_service_text'];
                    }else{
                        $post_data_tem['ShippingCost'][$key]['ShippingService'] = $template_type_name;
                    }

                    $post_data_tem['ShippingCost'][$key]['EstimatedDeliveryTime'] = isset($rdata['shipping_day']) ? $rdata['shipping_day'] : 0;
                    $post_data_tem['ShippingCost'][$key]['TrackingInformation'] = '';

                    $post_data_tem['ShippingCost'][$key]['isCharged'] = $rdata['isCharged'];

                    $post_data_tem['ShippingCost'][$key]['ShippingType'] = $shipping_type;
                    $post_data_tem['ShippingCost'][$key]['discount'] = $discount;

                    /*$post_data_tem['ShippingCost'][$key]['LmsRuleInfo']['FirstWeight'] = $rdata['first_weight'];
                    $post_data_tem['ShippingCost'][$key]['LmsRuleInfo']['FirstPrice'] = $rdata['first_freight'];
                    $post_data_tem['ShippingCost'][$key]['LmsRuleInfo']['IncreaseData'] = $rdata['lms_freight_data'];*/

                    $post_data_tem['ShippingCost'][$key]['LmsRuleInfo'] = $rdata['lms_calculation_formula'];

                    $post_data_tem['ShippingCost'][$key]['ShippingTamplateRuleInfo']['delivery_type'] = isset($rdata['delivery_type'])?$rdata['delivery_type']:-1;
                    $post_data_tem['ShippingCost'][$key]['ShippingTamplateRuleInfo']['discount'] = isset($rdata['discount'])?$rdata['discount']:-1;
                    $post_data_tem['ShippingCost'][$key]['ShippingTamplateRuleInfo']['CustomShipping'] = isset($rdata['custom_freight_data'])?$rdata['custom_freight_data']:[];
                }
            }
            $post_data_tem['VAT'] = '';
            $post_data_tem['TempletID'] = $template_id;
            $post_data_tem['AddTime'] = $time;
            $post_data_tem['EditTime'] = '';
            $post_data[] = $post_data_tem;
        }
        $rtn['product_id'] = $product_id;
        $rtn['template_id'] = $template_id;
        $rtn['shipping_fee'] = $is_free_shipping;//是否免邮：1-免邮，2-不免邮
        $rtn['time'] = $time;
        $rtn['data'] = $post_data;
        return $rtn;
    }

    /**
     * 同步运费模板顺序处理： supersaver > standard > expedited > xx 专线
     * @param array $data
     * @return array
     */
    private function handleShippingTemplatePostData(array $data){
        foreach ($data['data'] as $k=>$info){
            foreach ($info['ShippingCost'] as $key=>$val){
                if ($val['ShippingServiceID'] == 20){
                    $h_data = $val;
                    unset($data['data'][$k]['ShippingCost'][$key]);
                    array_unshift($data['data'][$k]['ShippingCost'], $h_data);
                }
            }
        }
        return $data;
    }

    /**
     * 同步历史产品数据（运费模板和图片）
     *
     * 1、产品分段处理：
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=100001&end_spu_id=200000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=200001&end_spu_id=300000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=300001&end_spu_id=400000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=400001&end_spu_id=500000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=500001&end_spu_id=600000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=600001&end_spu_id=700000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=700001&end_spu_id=800000&size=1 NO
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=800001&end_spu_id=900000&size=1 NO
     *
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=900001&end_spu_id=1200000&size=1 YES
     * 900001&end_spu_id=1200000再分段：
            http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=900001&end_spu_id=920000&size=1 NO
            http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=920001&end_spu_id=930000&size=1 NO
            http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=930001&end_spu_id=950000&size=1 YES
            http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=950001&end_spu_id=970000&size=1 YES
            http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=970001&end_spu_id=1200000&size=1 NO
     *
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=8520000000&end_spu_id=8530000000&size=1 NO
     *
     * 2、美国国家特殊处理：
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?us_spu_flag=1&size=1
     *
     * 3、复查已经上传的运费模板：
        http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=1&end_spu_id=8530000000&size=1&check_flag=1
     *
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?check_flag=1 no
     *
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=100001&end_spu_id=200000&size=1&check_flag=1 no
     *
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=200001&end_spu_id=300000&size=1&check_flag=1 no
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=300001&end_spu_id=400000&size=1&check_flag=1 no
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=400001&end_spu_id=500000&size=1&check_flag=1 no
     * 
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=500001&end_spu_id=600000&size=1&check_flag=1 no
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=600001&end_spu_id=700000&size=1&check_flag=1 no
     *
     *
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=700001&end_spu_id=800000&size=1&check_flag=1 no
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=800001&end_spu_id=900000&size=1&check_flag=1 no
     *
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=900001&end_spu_id=1200000&size=1&check_flag=1 no
     *
    http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=8520000000&end_spu_id=8530000000&size=1&check_flag=1 no
     * 
     * 
     * =================== 新2000000产品ID 分段 ======================
     * 最小 2000000
     * 最大 2083965
     *
     * 852：852214137
     *
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2000000&end_spu_id=2010000&size=1&check_flag=1 no
     *
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2010001&end_spu_id=2020000&size=1&check_flag=1
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2020001&end_spu_id=2030000&size=1&check_flag=1 no
     *
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2030001&end_spu_id=2040000&size=1&check_flag=1
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2040001&end_spu_id=2050000&size=1&check_flag=1
     *
     *
     *
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2050001&end_spu_id=2060000&size=1&check_flag=1 no
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2060001&end_spu_id=2070000&size=1&check_flag=1 no
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2070001&end_spu_id=2080000&size=1&check_flag=1
     *
     *
     *
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2080001&end_spu_id=2090000&size=1&check_flag=1 no
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=2090001&end_spu_id=2100000&size=1&check_flag=1 no
     * http://seller.dx.com/task/crontab/dosyncHistoryProductSTAndImgs?start_spu_id=852000000&end_spu_id=853000000&size=1&check_flag=1
     *
     *
     * @return mixed
     */
    public function doSyncHistoryProductSTAndImgs(){
        ini_set('max_execution_time', '0');
        //美国产品特殊处理：0-不是，1-是
        $_us_spu_flag = input('us_spu_flag', 0);
        //分段处理 开始产品ID
        $start_spu_id = input('start_spu_id', 1);
        //分段处理 结束产品ID
        $end_spu_id = input('end_spu_id', 100000);
        //每次处理历史产品数据的条数，默认5条
        $size = input('size', 1);
        $session_i = session('doSyncHistoryProductSTAndImgsI');
        $i = !empty($session_i)?$session_i:0;
        $j = $i+50;
        while (true){
            echo $i.'<br>';
            if ($i > $j){
                session('doSyncHistoryProductSTAndImgsI', $i);
                $url = url('Crontab/doSyncHistoryProductSTAndImgs', ['start_spu_id'=>$start_spu_id, 'end_spu_id'=>$end_spu_id, 'size'=>$size, 'us_spu_flag'=>$_us_spu_flag,'check_flag'=>input('check_flag', 0), 'img_flag'=>input('img_flag', 0)]);
                $this->success('jump', $url, null, 1);
            }
            $this->syncHistoryProductSTAndImgs($start_spu_id, $end_spu_id, $_us_spu_flag);
            $i++;
            //sleep(1);
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }
        }
    }

    /**
     * 同步历史产品数据（运费模板和图片）
     * TODO ... 需要确认的两点：1、历史产品带电属性是否一致？2、产品图片是否已经上传至CDN？
     * @return mixed
     */
    public function syncHistoryProductSTAndImgs($start_spu_id, $end_spu_id, $_us_spu_flag=0)
    {
        $check_flag = input('check_flag', 0);
        /*//分段处理 开始产品ID
        $start_spu_id = input('start_spu_id', 1);
        //分段处理 结束产品ID
        $end_spu_id = input('end_spu_id', 100000);*/
        //每次处理历史产品数据的条数，默认5条
        $size = input('size', 1);
        //是否处理图片：1-处理，0-不处理
        $img_flag = input('img_flag', 0);

        //运费模板同步处理成功的产品ID
        $success_arr = [];

        //产品数据错误，也要更新是否更新过的值
        $error_flag_sync = [];

        //TODO... 运费模板同步增加sellerID的判断
        /** TODO 可变... 普货&&纯电&&带电运费模板相关ID、名称需要按照需要求来配置 start **/
        /*//普货运费模板
        $template_id_1 = ['id'=>353,'name'=>'NF001'];
        //纯电运费模板
        $template_id_2 = ['id'=>354,'name'=>'CF001'];
        //带电运费模板
        $template_id_3 = ['id'=>355,'name'=>'BF001'];*/

        /** TODO 可变... 普货&&纯电&&带电运费模板相关ID、名称需要按照需要求来配置 end **/

        //1、获取需要同步的产品数据（运费模板为空）
        if ($_us_spu_flag == 1){
            $data = $this->base_api->getHistoryDataForAsyncShippingTemplateAndImgs($start_spu_id, $end_spu_id, $size, $this->getUSBaseShippingConfig()['spus'], $check_flag);
        }else{
            $data = $this->base_api->getHistoryDataForAsyncShippingTemplateAndImgs($start_spu_id, $end_spu_id, $size, [],$check_flag);
        }
        if (empty($data['data']['data'])){
            $data['data']['data'] = [];
            pr('无处理数据...'.'start_spu_id->'.$start_spu_id.', end_spu_id->'.$end_spu_id.', size->'.$size);
        }

        //是否带电：1-为普货，2-为纯电，3-为带电
        $ids_1 = []; //普货产品ID、运费模板数据
        $ids_2 = []; //纯电产品ID、运费模板数据
        $ids_3 = []; //带电产品ID、运费模板数据


        $post_img = [
            'first_img'=>[], //要生成首图(210*210)的图片
            'small_img'=>[], //要生成小图(70*70)的图片
        ];

        $template_config = config('sync_history_product_shipping_template_config');
        foreach ($data['data']['data'] as $info){
            $template_id_1 = [];
            $template_id_2 = [];
            $template_id_3 = [];

            //组装产品ID
            $product_id = $info['_id'];
            //根据sellerID匹配不同运费模板数据
            /********** 注意：这定义要和刘锐那边同步产品时保持一致 start ******************/
            if ($_us_spu_flag == 1){
                switch ((int)$info['StoreID']){
                    case 777:
                        //只有-普货运费模板
                        $template_id_1 = $this->getUSBaseShippingConfig()['st'][$info['StoreID']][1];
                        $template_id_2 = $this->getUSBaseShippingConfig()['st'][$info['StoreID']][2];
                        $template_id_3 = $this->getUSBaseShippingConfig()['st'][$info['StoreID']][3];
                        break;
                }
            }else{
                switch ($info['StoreID']){
                    case 333:
                    case 666:
                    case 888:
                    case 999:
                        //普货运费模板
                        $template_id_1 = $template_config[$info['StoreID']][1];
                        //纯电运费模板
                        $template_id_2 = $template_config[$info['StoreID']][2];
                        //带电运费模板
                        $template_id_3 = $template_config[$info['StoreID']][3];
                        break;
                }
            }

            /*switch ($info['StoreID']){
                case 666:
                    //普货运费模板
                    $template_id_1 = ['id'=>1,'name'=>'NF01'];
                    //纯电运费模板
                    $template_id_2 = ['id'=>2,'name'=>'BF01'];
                    //带电运费模板
                    $template_id_3 = ['id'=>3,'name'=>'EF01'];
                    break;
                case 888:
                    //普货运费模板
                    $template_id_1 = ['id'=>4,'name'=>'NF916'];
                    //纯电运费模板
                    $template_id_2 = ['id'=>5,'name'=>'BF916'];
                    //带电运费模板
                    $template_id_3 = ['id'=>6,'name'=>'EF916'];
                    break;
                case 999:
                    //普货运费模板
                    $template_id_1 = ['id'=>7,'name'=>'NFHK'];
                    //纯电运费模板
                    $template_id_2 = ['id'=>8,'name'=>'BFHK'];
                    //带电运费模板
                    $template_id_3 = ['id'=>9,'name'=>'EFHK'];
                    break;
            }*/
            /********** 注意：这定义要和刘锐那边同步产品时保持一致 end ******************/
            if (
                empty($template_id_1) || empty($template_id_2) || empty($template_id_3)
            ){
                $error_flag_sync[]['product_id'] = $product_id;
                pr('同步历史数据运费模板错误：基础运费模板为空，product_id:'.$product_id);
                Log::record('同步历史数据运费模板错误：基础运费模板为空，product_id:'.$product_id);
                continue;
            }

            if (!empty($info['LogisticsLimit']) && is_array($info['LogisticsLimit'])){
                if (in_array(1, $info['LogisticsLimit'])){
                    $temp = [];
                    $temp['product_id'] = $product_id;
                    $temp['template_id'] = $template_id_1['id'];
                    $temp['template_name'] = $template_id_1['name'];
                    $temp['product_is_charged'] = 1;
                    $temp['from_flag'] = 2;
                    $ids_1[] = $temp;
                }elseif (in_array(2, $info['LogisticsLimit'])){
                    $temp = [];
                    $temp['product_id'] = $product_id;
                    $temp['template_id'] = $template_id_2['id'];
                    $temp['template_name'] = $template_id_2['name'];
                    $temp['product_is_charged'] = 2;
                    $temp['from_flag'] = 2;
                    $ids_2[] = $temp;
                }elseif (in_array(3, $info['LogisticsLimit'])){
                    $temp = [];
                    $temp['product_id'] = $product_id;
                    $temp['template_id'] = $template_id_3['id'];
                    $temp['template_name'] = $template_id_3['name'];
                    $temp['product_is_charged'] = 3;
                    $temp['from_flag'] = 2;
                    $ids_3[] = $temp;
                }else{ //产品带电属性为空的情况
                    $error_flag_sync[]['product_id'] = $product_id;
                    pr('同步历史数据运费模板错误：历史运费模板属性不在“1,2,3”内，product_id:'.$product_id.'，LogisticsLimit:'.json_encode($info['LogisticsLimit']));
                    Log::record('同步历史数据运费模板错误：历史运费模板属性不在“1,2,3”内，product_id:'.$product_id.'，LogisticsLimit:'.json_encode($info['LogisticsLimit']));
                    continue;
                    /*$temp = [];
                    $temp['product_id'] = $product_id;
                    $temp['template_id'] = $template_id_1['id'];
                    $temp['template_name'] = $template_id_1['name'];
                    $temp['product_is_charged'] = 1;
                    $temp['from_flag'] = 2;
                    $ids_1[] = $temp;*/
                }
            }
            /*else{ //产品带电属性为空的情况
                $temp = [];
                $temp['product_id'] = $product_id;
                $temp['template_id'] = $template_id_1['id'];
                $temp['template_name'] = $template_id_1['name'];
                $temp['product_is_charged'] = 1;
                $temp['from_flag'] = 2;
                $ids_1[] = $temp;
            }*/
            else{
                $error_flag_sync[]['product_id'] = $product_id;
                pr('同步历史数据运费模板错误：历史运费模板属性为空，product_id:'.$product_id.'，LogisticsLimit:'.json_encode($info['LogisticsLimit']));
                Log::record('同步历史数据运费模板错误：历史运费模板属性为空，product_id:'.$product_id.'，LogisticsLimit:'.json_encode($info['LogisticsLimit']));
                continue;
            }
            //组装产品图片
            foreach ($info['ImageSet']['ProductImg'] as $key=>$img){
                if ($key == 0){
                    $post_img['first_img'][] = $img;
                }
                $post_img['small_img'][] = $img;
            }
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }
        }
        $post_img['first_img'] = array_unique($post_img['first_img']);
        $post_img['small_img'] = array_unique($post_img['small_img']);

        //2、根据产品数据及其带电属性，匹配对应的运费模板
        //3、同步产品对应的运费模板数据至商城库
        //普货处理
        foreach ($ids_1 as $info1){
            $post_data1 = $this->getAsyncProductShippingTemplateData($info1);
            $post_data1 = $this->handleShippingTemplatePostData($post_data1);
            $editor_res1 = $this->base_api->createShippingCost($post_data1);
            if ($editor_res1['code'] == API_RETURN_SUCCESS){
                $success_arr[] = $info1;
            }
            Log::record('同步历史产品数据-运费模板-普货 ： （'.json_encode($info1).'）'.json_encode($editor_res1));
            pr('同步历史产品数据-运费模板-普货 ： （'.json_encode($info1).'）'.json_encode($editor_res1));
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }
        }

        //纯电处理
        foreach ($ids_2 as $info2){
            $post_data2 = $this->getAsyncProductShippingTemplateData($info2);
            $post_data2 = $this->handleShippingTemplatePostData($post_data2);
            $editor_res2 = $this->base_api->createShippingCost($post_data2);
            if ($editor_res2['code'] == API_RETURN_SUCCESS){
                $success_arr[] = $info2;
            }
            Log::record('同步历史产品数据-运费模板-纯电 ： （'.json_encode($info2).'）'.json_encode($editor_res2));
            pr('同步历史产品数据-运费模板-纯电 ： （'.json_encode($info2).'）'.json_encode($editor_res2));
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }
        }

        //带电处理
        foreach ($ids_3 as $info3){
            $post_data3 = $this->getAsyncProductShippingTemplateData($info3);
            $post_data3 = $this->handleShippingTemplatePostData($post_data3);
            $editor_res3 = $this->base_api->createShippingCost($post_data3);
            if ($editor_res3['code'] == API_RETURN_SUCCESS){
                $success_arr[] = $info3;
            }
            Log::record('同步历史产品数据-运费模板-带电 ： （'.json_encode($info3).'）'.json_encode($editor_res3));
            pr('同步历史产品数据-运费模板-带电 ： （'.json_encode($info3).'）'.json_encode($editor_res3));
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }
        }

        //4、图片处理
        if ($img_flag == 1){
            //生成小图请求地址
            $cdn_thumbnail_url = config('cdn_thumbnail_url');
            foreach ($post_img['first_img'] as $f_img){
                $f_img_data = explode('.', $f_img);
                $url_210 = $cdn_thumbnail_url.$f_img_data[0].'_210x210.'.$f_img_data[1];
                $res_210 = curl_request($url_210);
                pr('同步历史产品数据-图片处理-210 ： （'.json_encode($res_210).'）');
                if(ob_get_level()>0) {
                    @ob_flush();
                    @flush();
                }
            }
            foreach ($post_img['small_img'] as $s_img){
                $s_img_data = explode('.', $s_img);
                $url_70 = $cdn_thumbnail_url.$s_img_data[0].'_70x70.'.$s_img_data[1];
                $res_70 = curl_request($url_70);
                pr('同步历史产品数据-图片处理-70 ： （'.json_encode($res_70).'）');
                if(ob_get_level()>0) {
                    @ob_flush();
                    @flush();
                }
            }
        }

        //5、修改产品运费模板ID和运费模板名称、以及是否同步运费模板标识(IsHistoryIsSyncSTAndImgs：为2则是已经同步过)
        foreach ($success_arr as $val){
            $up_params = [];
            $up_params['id'] = $val['product_id'];
            $up_params['LogisticsTemplateId'] = $val['template_id'];
            $up_params['LogisticsTemplateName'] = $val['template_name'];
            //$up_params['IsHistoryIsSyncSTAndImgs'] = 2;
            $up_params['IsHistoryIsSyncSTAndImgs'] = self::ISHISTORYISSYNCSTANDIMGSFLAG;
            //$up_res = $this->base_api->updateProductInfoPost(json_encode($up_params));
            $up_res = $this->base_api->updateProductInfoPostForSyncHistoryProductSTAndImgs(json_encode($up_params));

            Log::record('修改产品运费模板ID和运费模板名称、以及是否同步运费模板标识:res:'.json_encode($up_res));
            if ($up_res['code'] == API_RETURN_SUCCESS) {
                $res_last = $this->base_api->updateShippingCostForUpdateProduct($val);
                Log::record('修改产品运费模板ID和运费模板名称、以及是否同步运费模板标识-更新-:res:'.json_encode($res_last));
            }
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }
        }

        //6、错误产品数据同步更新标识
        if (!empty($error_flag_sync)){
            foreach ($error_flag_sync as $verror){
                $up_params = [];
                $up_params['id'] = $verror['product_id'];
                //$up_params['IsHistoryIsSyncSTAndImgs'] = 2;
                $up_params['IsHistoryIsSyncSTAndImgs'] = self::ISHISTORYISSYNCSTANDIMGSFLAG;
                //$up_res = $this->base_api->updateProductInfoPost(json_encode($up_params));
                $up_res = $this->base_api->updateProductInfoPostForSyncHistoryProductSTAndImgs(json_encode($up_params));
                pr("错误产品数据同步更新标识:res:".json_encode($up_res));
                if(ob_get_level()>0) {
                    @ob_flush();
                    @flush();
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getUSBaseShippingConfig(){
        return [
            'st'=>[
                '777'=>[ //店铺ID
                    //是否带电：1-为普货，2-为纯电，3-为带电
                    '1'=>['id'=>10,'name'=>'NFUS'],//普货运费模板
                    '2'=>['id'=>11,'name'=>'BFUS'],//纯电运费模板
                    '3'=>['id'=>12,'name'=>'EFUS'],//带电运费模板
                ]
            ],
            'spus'=>[
                /*618795,
                618794,
                619479,
                616729,
                616677,
                616914,
                616631,
                616102,
                616629,
                616106,
                616736,
                616466,
                616090,
                616463,
                616077,
                616461,
                616050,
                616459,
                616041,
                616455,
                616035,
                616451,
                616007,
                616610,
                615985,
                616446,
                616696,
                615980,
                616851,
                616647,
                616718,
                616857,
                616693,
                616619,
                616850,
                616470,
                616468,
                615999*/
                2077798,
                2078472,
                2078474,
                2079454,
                2079457,
                2079458,
                2079460,
                2079461,
                2079464,
                2079465,
                2079467,
                2079469,
                2079470,
                2079472,
                2079474,
                2079476,
                2079477,
                2079479,
                2079480,
                2079482,
                2079484,
                2079486,
                2079488,
                2079490,
                2079492,
                2079494,
                2079495,
                2079496,
                2079498,
                2079500,
                2079501,
                2079504,
                2079505,
                2079507,
                2079509,
                2079511,
                2079513,
                2079514,
                2079516,
                2079518,
                2079519,
                2079520,
                2079522,
                2079525
            ]
        ];
    }

    public function test(){
die;
//        if (!empty($result) && isset($result['attribute'])){
//            array_multisort(array_column($result['attribute'], 'sort'), SORT_ASC, $result['attribute']);
//            foreach ($result['attribute'] as $k=>&$v){
//                array_multisort(array_column($v['attribute_value'], 'sort'), SORT_ASC, $v['attribute_value']);
//            }
//        }
//
//        print_r($result);
//
//
//
//        die;

        $redis_base = new RedisClusterBase();
        $res = $redis_base->lPush(
            QUEUE_PRODUCT_SHIPPING_TEMPLATE,
            json_encode(
                [
                    'product_id'=>886218,
                    'product_is_charged'=>1,
                    'template_id'=>496,
                    'template_name'=>'CNM',
                    'from_flag'=>1 //来源标识：1-新增产品，2-修改产品信息
                ]
            )
        );

        $list_length = $redis_base->lLen(QUEUE_PRODUCT_SHIPPING_TEMPLATE);
        pr('$res:'.$res);
        pr('$list_length:'.$list_length);
        pr($redis_base->lRange(QUEUE_PRODUCT_SHIPPING_TEMPLATE, 0, -1));

        die;



        die;
        echo get_seller_password(input('key'));die;

        //3dbe69212c437a34326b9cffaf09b84f

        /*echo get_seller_password('Dx+123456789');

        die;

        $redis = new RedisClusterBase();
        print_r($redis->lRange('mall_order_success_change_order_status', 0, -1));die;*/

        /*$temp['product_id'] = 886041;
        $temp['template_id'] = 11;
        $temp['product_is_charged'] = 1;

        $post_data = $this->getAsyncProductShippingTemplateData($temp);

        print_r($post_data);die;*/

        $base_api = new BaseApi();
        //同步运费模板
        /*$data = [
            [
                'product_id'=>266004,
                'template_id'=>368,
                'product_is_charged'=>1,
                'template_name'=>'NF01',
                'from_flag'=>2
            ],
            [
                'product_id'=>267283,
                'template_id'=>370,
                'product_is_charged'=>3,
                'template_name'=>'EF01',
                'from_flag'=>2
            ],
            [
                'product_id'=>266671,
                'template_id'=>370,
                'product_is_charged'=>3,
                'template_name'=>'EF01',
                'from_flag'=>2
            ],
            [
                'product_id'=>271684,
                'template_id'=>368,
                'product_is_charged'=>1,
                'template_name'=>'NF01',
                'from_flag'=>2
            ],
            [
                'product_id'=>279316,
                'template_id'=>369,
                'product_is_charged'=>2,
                'template_name'=>'BF01',
                'from_flag'=>2
            ],
        ];*/

        $data = [
            /*[
                'product_id'=>535897,
                'template_id'=>368,
                'product_is_charged'=>3,
                'template_name'=>'EF01',
                'from_flag'=>2
            ],
            [
                'product_id'=>548448,
                'template_id'=>368,
                'product_is_charged'=>1,
                'template_name'=>'NF01',
                'from_flag'=>2
            ],
            [
                'product_id'=>558122,
                'template_id'=>368,
                'product_is_charged'=>2,
                'template_name'=>'BF01',
                'from_flag'=>2
            ],

            [
                'product_id'=>558815,
                'template_id'=>368,
                'product_is_charged'=>3,
                'template_name'=>'EF01',
                'from_flag'=>2
            ],


            [
                'product_id'=>559458,
                'template_id'=>368,
                'product_is_charged'=>1,
                'template_name'=>'NF01',
                'from_flag'=>2
            ],


            [
                'product_id'=>578609,
                'template_id'=>368,
                'product_is_charged'=>2,
                'template_name'=>'BF01',
                'from_flag'=>2
            ],*/




            //##############################//
            [
                'product_id'=>567825,
                'template_id'=>492, //888
                'product_is_charged'=>1,
                'template_name'=>'SKNF01',
                'from_flag'=>2
            ],

            [
                'product_id'=>586445,
                'template_id'=>438, //999
                'product_is_charged'=>1,
                'template_name'=>'NF001',
                'from_flag'=>2
            ],


            //##############################//
            /*[
                'product_id'=>266004,
                'template_id'=>368,
                'product_is_charged'=>1,
                'template_name'=>'NF01',
                'from_flag'=>2
            ],

            [
                'product_id'=>267283,
                'template_id'=>370,
                'product_is_charged'=>3,
                'template_name'=>'EF01',
                'from_flag'=>2
            ],


            [
                'product_id'=>266671,
                'template_id'=>370,
                'product_is_charged'=>3,
                'template_name'=>'EF01',
                'from_flag'=>2
            ],


            [
                'product_id'=>271684,
                'template_id'=>368,
                'product_is_charged'=>1,
                'template_name'=>'NF01',
                'from_flag'=>2
            ],


            [
                'product_id'=>279316,
                'template_id'=>369,
                'product_is_charged'=>2,
                'template_name'=>'BF01',
                'from_flag'=>2
            ],*/
        ];

        //重新组装产品ID、运费模板ID
        /*$new_data = [];
        foreach ($product_id_arr as $info){
            $temp = [];
            $temp['product_id'] = $info;
            $temp['template_id'] = $template_id;
            $temp['product_is_charged'] = $is_charged;
            $new_data[] = $temp;
        }
                    [product_id] => 22
                    [product_is_charged] => 2
                    [template_id] => 72


                    [template_name] => '' //from_flag = 2时存在
                    [from_flag] => 1 //来源标识：1-新增产品，2-修改产品信息

        */
        //pr($product_id_arr);
        //pr($new_data);
        //2、根据产品ID、运费模板ID修改商城运费数据（方案：添加新的运费数据、删除旧的运费数据并备份）
        foreach ($data as $new_info){
            $post_data = $this->getAsyncProductShippingTemplateData($new_info);
            $post_data = $this->handleShippingTemplatePostData($post_data);
            //pr($post_data);
            $editor_res = $base_api->createShippingCost($post_data);
            pr($new_info);
            pr($editor_res);
            Log::record('获取产品数据失败 $editor_res （'.json_encode($data).'）'.json_encode($editor_res));


            $res_last = $base_api->updateShippingCostForUpdateProduct($new_info);
            pr($res_last);
            /*if ($res_last['code'] != API_RETURN_SUCCESS) {
                $flag = false;
                //将“异步更新运费模板数据至商城cost表”的数据删除 TODO 容错机制（为了数据的统一）方案：另开一个队列来处理？
                Log::record('数据来至：修改产品信息-res:'.print_r($res_last, true));
            }*/

        }





        die;
        $redis_key = QUEUE_PRODUCT_SHIPPING_TEMPLATE;
        $redis_base = new RedisClusterBase();
        //队列长度
        $list = $redis_base->lRange($redis_key, 0, -1);
        //$redis_base->rPop($redis_key);

        //{"product_id":"189","product_is_charged":"1","template_id":"236"}
//        $redis_base->rPush($redis_key, json_encode([
//            "product_id"=>189,
//            "product_is_charged"=>1,
//            "template_id"=>236,
//        ]));

        print_r($list);die;


//        echo str_pad('',4096);
       /* for ($i = 0; $i < 10; $i++) {


            echo $i;
            if(ob_get_level()>0) {
                @ob_flush();
                @flush();
            }

            Log::record("test->".$i);
            sleep(5);


        }*/


        /*$redis_key = 'addProductShippingTemplateList';
        $redis_base = new RedisClusterBase();
        //队列长度
        $list = $redis_base->lRange($redis_key, 0, -1);
//        $redis_base->rPop($redis_key);

        print_r($list);

        $flag = true;
        $i = 3;
        do {
            echo $i;

            $i--;


        } while ($i > 0 && !$flag);

        die;*/

        //拼装符合条件的运费模板数据
        $template_id = 80;
        $product_is_charged = 1;
        $product_id = 85;
        $template_data = Product::getShippingTemplateByIDForPost($template_id);
        $delivery_area = $template_data['delivery_area']; //发货国
        $template_name = $template_data['template_name']; //模板名称
        $post_data = [];
        //获取运费模板包含的所有国家信息 start
        $country_arr = [];
        foreach ($template_data['data'] as $type_info){
            foreach ($type_info['country_data'] as $country_info){
                $country_arr[] = $country_info['country_code'];
            }
        }
        //去重
        $country_arr = array_unique($country_arr);
        //获取运费模板包含的所有国家信息 end
        foreach ($country_arr as $country_code){ //循环国家信息,根据国家获取对应模板类型下的运费参数
            //拼装到货国运费参数
            $post_data_tem = [];
            $post_data_tem['ProductId'] = $product_id;
            $post_data_tem['IsCharged'] = $product_is_charged;
            $post_data_tem['FormCountry'] = $delivery_area;
            $post_data_tem['ToCountry'] = $country_code;
            foreach ($template_data['data'] as $key => $type_info){ //循环物流服务
                $template_type_name = $type_info['template_type_name'];
                $template_type = $type_info['template_type'];
                //运费类型：1-标准运费[有折扣，单位%，如：50，打5折，如价格为100，则最终价格为100*0.5=50]，2-卖家承担运费，3-自定义运费[sl_shipping_tamplate_country才有对应数据]
                $shipping_type = $type_info['shipping_type'];
                //折扣，在$shipping_type = 1时有效
                $discount = $type_info['discount'];
                $country_data = $type_info['country_data'];
                //获取符合国家的运费数据
                $rdata = array_filter($country_data, function($t) use ($country_code){
                    return $t['country_code'] == $country_code;
                });
                if(!empty($rdata)){
                    $rdata = array_shift($rdata);//目的国详情以及运费计算相关参数详情
                    $post_data_tem['ShippingCost'][$key]['Cost'] = 0;
                    if ($template_type == 40){
                        $post_data_tem['ShippingCost'][$key]['ShippingService'] = $rdata['shipping_service_text'];
                    }else{
                        $post_data_tem['ShippingCost'][$key]['ShippingService'] = $template_type_name;
                    }

                    $post_data_tem['ShippingCost'][$key]['EstimatedDeliveryTime'] = isset($rdata['shipping_day']) ? $rdata['shipping_day'] : 0;
                    $post_data_tem['ShippingCost'][$key]['TrackingInformation'] = '';

                    $post_data_tem['ShippingCost'][$key]['isCharged'] = $rdata['isCharged'];

                    $post_data_tem['ShippingCost'][$key]['ShippingType'] = $shipping_type;
                    $post_data_tem['ShippingCost'][$key]['discount'] = $discount;

                    $post_data_tem['ShippingCost'][$key]['LmsRuleInfo']['FirstWeight'] = $rdata['first_weight'];
                    $post_data_tem['ShippingCost'][$key]['LmsRuleInfo']['FirstPrice'] = $rdata['first_freight'];
                    $post_data_tem['ShippingCost'][$key]['LmsRuleInfo']['IncreaseData'] = $rdata['lms_freight_data'];
                    $post_data_tem['ShippingCost'][$key]['ShippingTamplateRuleInfo']['delivery_type'] = isset($rdata['delivery_type'])?$rdata['delivery_type']:-1;
                    $post_data_tem['ShippingCost'][$key]['ShippingTamplateRuleInfo']['discount'] = isset($rdata['discount'])?$rdata['discount']:-1;
                    $post_data_tem['ShippingCost'][$key]['ShippingTamplateRuleInfo']['CustomShipping'] = isset($rdata['custom_freight_data'])?$rdata['custom_freight_data']:[];
                }
            }
            $post_data_tem['VAT'] = '';
            $post_data_tem['TempletID'] = $template_id;
            $post_data_tem['AddTime'] = time();
            $post_data_tem['EditTime'] = '';
            $post_data[] = $post_data_tem;
        }

        $base_api = new BaseApi();
        $res = $base_api->createShippingCost($post_data);


        $res_last = $base_api->updateShippingCostForUpdateProduct($data);
        if ($res_last['code'] != API_RETURN_SUCCESS) {
            $flag = false;
            //将“异步更新运费模板数据至商城cost表”的数据删除 TODO 容错机制（为了数据的统一）方案：另开一个队列来处理？
            Log::record('数据来至：修改产品信息-res:'.print_r($res_last, true));
        }


        return json($template_data);
    }

    /** php 接收流文件
    * @param  String  $file 接收后保存的文件名
    */
    function receiveStreamFile(){
        Log::record('数据来至：receiveStreamFile:'.print_r(input(), true));
        $content = base64_decode(input('post.content'));
        $date = input("post.date");
        $filename= input("post.name"); 
        if(!empty($content)&& !empty($filename)){
            $base_upload_dir = config('product_pic_upload_dir');
        $ftp_config = config('ftp_config');
            $ftp_upload_dir = $ftp_config['UPLOAD_DIR'];
            $path = $base_upload_dir.$ftp_upload_dir['PRODUCT_IMAGES_SAVE'].$date;
        if(!file_exists($path)){//检测文
            mkdir($path, 0777 , true );
        }
        $upload_dir =$path.'/'.$filename;
            $file = fopen($upload_dir,"w+");
            fputs($file,$content);//写入文件
            fclose($file);
            return json(true);
        }else{
            return json(false);
        }   

        
    }

    /*
     * 多次请求生成小图路径，如果中间返回成功跳出
     * kevin 2019/01/18
     * */

    function repeatedlyCurlRequest($url,$requer_num=3){
        for ($ti=1;$ti<=$requer_num;$ti++){
            $res = curl_request($url,'','',1);
            $is_success = false;
            if(isset($res['header'])){
                $success_header = strpos($res['header'],"200 OK");
                if($success_header !== false){
                    $is_success = true;
                    break;
                }else{
                    sleep(2);
                }
            }
        }
        return $is_success;
    }
}