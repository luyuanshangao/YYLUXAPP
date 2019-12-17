<?php
namespace app\admin\controller;


use app\admin\dxcommon\ExcelTool;
use app\admin\dxcommon\XmlTool;
use app\admin\model\CrontabModel;
use app\admin\services\ProductService;
use app\common\helpers\RedisClusterBase;
use think\Controller;
use think\Exception;
use think\Log;

/**
 * admin端定时服务类
 * @author zhongning
 */

class Crontab extends Controller
{

    /**
     * 异步-处理下载数据
     */
    public function asyncFeedDownload(){
        ini_set('max_execution_time', '0');
        $ftp_upload_dir = config('ftp_config.UPLOAD_DIR');
        $dir_path = $ftp_upload_dir['FILE_UPLOAD'];//'/document/'

        $model = new CrontabModel();
        $service = new ProductService();
        $tool = new ExcelTool('CSV');
        $xml = new XmlTool();
        $redis = new RedisClusterBase();
//        $lists = $redis->lRange("AffiliateFeedDownLoadQueue", 0, 10);
        $length = $redis->lLen('AffiliateFeedDownLoadQueue');
//        $length = 1;
        if($length <= 0){
            return false;
        }
        $length = $length > 10 ? 10 : $length;
        //一次处理10个下载任务
        for($i = 0 ; $i < $length ; $i++){
            $params = json_decode($redis->rPop('AffiliateFeedDownLoadQueue'),true);
//            $json = '{"filename":"Daisycon_XML","platform":"Daisycon","category_id":"32","format":"XML","currency":"GBP","country":"","lang":"pt","add_time":1555294678,"id":"45","page":1,"page_size":500}';
//            $params = json_decode($json,true);
            if(empty($params)){
                continue;
            }
            //查看查找当前页数
            $params['page'] = !empty($params['page']) ? $params['page'] : 1;
            $params['page_size'] = !empty($params['page_size']) ? $params['page_size'] : 500;//每次下载1000

            //查询任务是否存在，也有可能被删除了
            $findData = $model->findCrontab($params['id']);
            if(empty($findData)){
                continue;
            }
            $path = ROOT_PATH . 'public' . DS . 'uploads'.$dir_path.strtolower($params['format']).DS;
            if (!is_dir($path)){//当路径不存在
                if(!mkdir($path, 0777, true)){
                    Log::record("asyncFeedDownload: mkdir error",'error');
                    //重新入队列
                    $redis->lPush('AffiliateFeedDownLoadQueue',json_encode($params));
                    exit;
                }
            }
            try{
                $productData = $service->getDataFeed($params);
                if(!empty($productData)){
                    //文件路径
                    $filename = $params['filename'].'_'.$params['id'].'.'.strtolower($params['format']);
                    //判断是否最后一页
                    if($params['page'] > $productData['last_page']){
                        //上传文件到CDN
                        $ftp_put_data = [
                            'dirPath'=> $dir_path.strtolower($params['format']),///newprdimgs/20190413
                            'romote_file' => $filename, // 26911555125255.jpg
                            'local_file' => $path.$filename //D:\work\BugFixed\API\public\uploads\product/newprdimgs/20190413/26911555125255.jpg
                        ];
                        $res = self::data_put($ftp_put_data,config('ftp_config'));
                        //文件上传成功
                        if($res){
                            //下载已完成,更新状态
                            $model->updateCrontab(['id' => $params['id']],['status' => 3,'download_url'=>$dir_path.strtolower($params['format']).'/'.$filename]);
                        }
                        continue;
                    }
                    //插入或者追加数据到excel
                    if(!empty($productData['data'])){
                        //头部信息
                        $header = $this->getHeaderData($params['platform']);
                        if($params['format'] == 'CSV'){
                            //文件是否存在
                            if(!file_exists($path.$filename)) {
                                $tool->save($params['filename'].'_'.$params['id'],$header,$productData['data'],'sheet1',$path);
                            }else{
                                //追加
                                $objReader = \PHPExcel_IOFactory::createReader($params['format']);
                                $objPHPExcel = $objReader->load($path.$filename);
                                $sheet = $objPHPExcel->getSheet(0);
                                $highestRow = $sheet->getHighestRow(); //取得总行数
                                $highestColumn = $sheet->getHighestColumn();// 取得总列数
                                $tool->append($path.$filename,$header,$productData['data'],$highestRow,$highestColumn);
                            }
                        }else{
                            //xml文件处理
                            $xml->saveXML($filename,$header,$productData['data'],$path);
                        }
                        $progressed = round($params['page'] / $productData['last_page'],2);
                        //下载中，下载进度
                        $model->updateCrontab(['id' => $params['id']],['status' => 2,'progressed' => $progressed,'page'=> $params['page'] + 1]);
                        //页数增加
                        $params['page'] = $params['page'] + 1;
                        //判断是否最后一页
                        if($params['page'] > $productData['last_page']){
                            //上传文件到CDN
                            $ftp_put_data = [
                                'dirPath'=> $dir_path.strtolower($params['format']),///newprdimgs/20190413
                                'romote_file' => $filename, // 26911555125255.jpg
                                'local_file' => $path.$filename //D:\work\BugFixed\API\public\uploads\product/newprdimgs/20190413/26911555125255.jpg
                            ];
                            $res = self::data_put($ftp_put_data,config('ftp_config'));
                            if($res){
                                //下载已完成,更新状态
                                $model->updateCrontab(['id' => $params['id']],['status' => 3,'download_url'=>$dir_path.strtolower($params['format']).'/'.$filename]);
                            }
                            continue;
                        }
                        //重新加入队列
                        $redis->lPush('AffiliateFeedDownLoadQueue',json_encode($params));
                    }
                }
            }catch(Exception $e){
//                pr($e->getMessage());
                //记录日志
                Log::record("asyncFeedDownload: error = ".$e->getMessage(),'error');
                //写入失败，重新加入队列
                $redis->lPush('AffiliateFeedDownLoadQueue',json_encode($params));
            }
        }
        echo('done');
        return true;
    }

    private function getHeaderData($platform){
        switch($platform){
            case 'DX':
                $header_data =[
                    'id'=>'MerchantCode',
                    'Title'=>'ProductName',
                    'LinkUrl'=>'ProductURL',
                    'LowPrice_Code'=>'ProductPrice',
                    'FirstProductImage'=>'ImageURL_large',
                    'FirstProductImage_210'=>'ImageURL_small',
                    'Descriptions'=>'Description',
                    'firstClassName'=>'Category',
                ];
                break;
            case 'Shareasale':
                $header_data =[
                    'id'=>'SKU',
                    'Title'=>'Name',
                    'LinkUrl'=>'URL_to_product',
                    'LowPrice'=>'Price',
                    'Retail_Price'=>'Retail_Price',//''
                    'FirstProductImage'=>'URL_to_image',
                    'FirstProductImage_210'=>'URL_to_thumbnail_image',
                    'Commission'=>'Commission',//''
                    'Category'=>'Category',//6
                    'SubCategory'=>'SubCategory',//47
                    'Descriptions'=>'Description',
                    'SearchTerms'=>'SearchTerms',//''
                    'Status'=>'Status',//instock
                    'Your_MerchantID'=>'Your_MerchantID',//32431
                    'Custom_1'=>'Custom_1',//DealeXtreme
                    'Custom_2'=>'Custom_2',
                    'Custom_3'=>'Custom_3',
                    'Custom_4'=>'Custom_4',
                    'Custom_5'=>'Custom_5',
                    'Manufacturer'=>'Manufacturer',
                    'PartNumber'=>'PartNumber',
                    'firstClassName'=>'MerchantCategory',
                    'secondClassName'=>'MerchantSubcategory',
                    'ShortDescription'=>'ShortDescription',
                    'ISBN'=>'ISBN',
                    'UPC'=>'UPC',
                    'CrossSell'=>'CrossSell',
                    'MerchantGroup'=>'MerchantGroup',
                    'MerchantSubgroup'=>'MerchantSubgroup',
                    'CompatibleWith'=>'CompatibleWith',
                    'CompareTo'=>'CompareTo',
                    'QuantityDiscount'=>'QuantityDiscount',
                    'Bestseller'=>'Bestseller',
                    'AddToCartURL'=>'AddToCartURL',
                    'ReviewsRSSURL'=>'ReviewsRSSURL',
                    'Option1'=>'Option1',
                    'Option2'=>'Option2',
                    'Option3'=>'Option3',
                    'Option4'=>'Option4',
                    'Option5'=>'Option5',
                    'ReservedForFutureUse'=>'ReservedForFutureUse',
                    'ReservedForFutureUse1'=>'ReservedForFutureUse1',
                    'ReservedForFutureUse2'=>'ReservedForFutureUse2',
                    'ReservedForFutureUse3'=>'ReservedForFutureUse3',
                    'ReservedForFutureUse4'=>'ReservedForFutureUse4',
                    'ReservedForFutureUse5'=>'ReservedForFutureUse5',
                    'ReservedForFutureUse6'=>'ReservedForFutureUse6',
                    'ReservedForFutureUse7'=>'ReservedForFutureUse7',
                    'ReservedForFutureUse8'=>'ReservedForFutureUse8',
                    'ReservedForFutureUse9'=>'ReservedForFutureUse9',
                ];
                break;
            case 'Admitad':
                $header_data =[
                    'id'=>'id',
                    'Title'=>'Name',
                    'LinkUrl'=>'url',
                    'LowPrice_Code'=>'Price',
                    'FirstProductImage'=>'URL_to_image',
                    'FirstProductImage_210'=>'URL_to_thumbnail_image',
                    'Descriptions'=>'Description',
                    'firstClassName'=>'categoryId',
                    'secondClassName'=>'MerchantSubcategory',
                ];
                break;
            case 'Affiliatewindow':
                $header_data =[
                    'id'=>'product_id',
                    'Title'=>'product_name',
                    'LinkUrl'=>'deep_link',
                    'LowPrice_Code'=>'price',
                    'FirstProductImage'=>'image_url',
                    'Descriptions'=>'description',
                    'firstClassName'=>'merchant_category',
                ];
                break;
            case 'Zanox':
                $header_data =[
                    'product_id'=>'Product_reference',
                    'Title'=>'Product_Name',
                    'LinkUrl'=>'Product_URL',
                    'LowPrice_Code'=>'Product_Price',
                    'FirstProductImage'=>'Product_URL_image_big',
                    'FirstProductImage_210'=>'Product_URL_image_small',
                    'Descriptions'=>'Product_short_description',
                    'firstClassName'=>'Product_category1',
                    'currency_code'=>'Product_Currency',
                ];
                break;
            case 'Tradetracker':
                $header_data =[
                    'product_id'=>'MerchantCode',
                    'Title'=>'ProductName',
                    'LinkUrl'=>'ProductURL',
                    'LowPrice'=>'ProductPrice',
                    'FirstProductImage'=>'ImageURL_large',
                    'FirstProductImage_210'=>'ImageURL_small',
                    'Descriptions'=>'Description',
                    'firstClassName'=>'Category',
                    'secondClassName'=>'Subcategories',
                ];
                break;
            case 'Daisycon':
                $header_data =[
                    'product_id'=>'ProductSKU',
                    'Title'=>'ProductName',
                    'LinkUrl'=>'ProductURL',
                    'LowPrice_Code'=>'ProductPrice',//币种符号
                    'FirstProductImage'=>'ImageURL_large',
                    'FirstProductImage_210'=>'ImageURL_small',
                    'Descriptions'=>'Description',
                    'firstClassName'=>'Category',
                ];
                break;
            default:
                return array();
        }
        return $header_data;
    }

    /**
     * 下载中途异常，重新加入队列
     */
    public function lpush(){
        $model= new CrontabModel();
        $redis = new RedisClusterBase();

        $findData = $model->selectCrontabList(['status'=>2]);
        if(!empty($findData)){
            foreach($findData as $data){
                $redis->lPush("AffiliateFeedDownLoadQueue", json_encode($data));
            }
        }
    }


    /**
     * 推送数据至CDN
     * @param array $config 配置，如下：
     * [
     *  'dirPath'=>'productImage/'.date('Ymd'), // ftp保存目录
     *  'romote_file'=>'test.jpg', // 保存文件的名称
     *  'local_file'=>'uploads\product\20180323/4b71b238fab435e853512a384c8b321a.jpg', // 要上传的文件
     * ]
     * @return bool
     */
    public static function data_put(array $config,$ftp_config=''){
        $connect =  self::initFTP($ftp_config);
        self::makeDir($connect, $config['dirPath']);
        //上传到远程服务器
        return self::uploadFile($connect, $config['romote_file'], $config['local_file']);
    }


    /**
     * 上传文件至FTP
     * @param $connect
     * @param $romote_file
     * @param $local_file
     * @param string $mode
     * @return bool
     */
    private static function uploadFile($connect, $romote_file, $local_file){
        $rtn = false;
        $rtn = ftp_put($connect, $romote_file, $local_file, FTP_BINARY);
        ftp_close($connect);
        return $rtn;
    }

    /**
     * 创建目录并将目录定位到当请目录
     *
     * @param resource $connect 连接标识
     * @param string $dirPath 目录路径
     * @return mixed
     *       2：创建目录失败
     *       true：创建目录成功
     */
    private static function makeDir($connect, $dirPath){
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

    /**
     * ftp链接远程服务器
     * @return resource
     */
    private static function initFTP($ftp_config = ''){
        $ftp_config = !empty($ftp_config)?$ftp_config:config('ftp_config');
        $server = $ftp_config['DX_FTP_SERVER_ADDRESS'];
        $port = $ftp_config['DX_FTP_SERVER_PORT'];
        $server_sbn = $ftp_config['SBN_FTP_SERVER_ADDRESS'];
        $port_sbn = $ftp_config['SBN_FTP_SERVER_PORT'];
        $user_name = $ftp_config['DX_FTP_USER_NAME'];
        $password = $ftp_config['DX_FTP_USER_PSD'];
        $conn_id = ftp_connect($server_sbn, $port_sbn);
        if (!$conn_id) {
            //echo "Error: Could not connect to ftp. Please try again later.\n";
            Log::record('initFTP错误,ftp_config:'.json_encode($ftp_config));
            return 100;
        }
        $login_result = ftp_login($conn_id, $user_name, $password);
        if (!$login_result) {
            //echo "Error: Could not login to ftp. Please try again later.\n";
            Log::record('initFTP ftp_login错误,ftp_config:'.json_encode($ftp_config));
            return 101;
        }
        //SET FTP TO PASSIVE MODE
        $pasv_result = ftp_pasv($conn_id, TRUE);
        if (!$pasv_result) {
            Log::record('initFTP ftp_pasv错误,ftp_config:'.json_encode($ftp_config));
            return 102;
        }
        return $conn_id;
    }
}