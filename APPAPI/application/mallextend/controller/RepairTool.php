<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\mallextend\product\ErpCreateProductParams;
use app\common\params\mallextend\product\ErpCreateProductSkuParams;
use app\common\params\mallextend\product\ProductParams;
use app\common\params\seller\product\CreateProductParams;
use app\common\params\seller\product\CreateProductSkuParams;
use app\common\params\mallextend\product\FindProductParams;
use app\common\params\seller\product\UpdateProductStatusParams;
use app\demo\controller\Auth;
use app\mallextend\model\ConfigDataModel;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductHistoryModel;
use app\mallextend\model\ProductModel;
use app\mallextend\services\BaseService;
use app\mallextend\services\ProductService;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * 功能：修复工具类
 * 开发：钟宁
 * 时间：2018-10-12
 */
class RepairTool extends Base
{
    public $productService;
    public $productModel;
    public $redis;

    public function __construct()
    {
        parent::__construct();
        $this->productService = new ProductService();
        $this->productModel = new ProductModel();
        $this->redis = new RedisClusterBase();
    }

    //插入指定变更历史
    public $spusArray = [
        2000275,
        2000458,
        2000840,
        2000931,
        2001103,
        2001116,
        2001357,
        2001384,
        2001532,
        2001538,
        2001733,
        2001793,
        2001951,
        2001964,
        2001976,
        2002003,
        2002057,
        2002085,
        2002154,
        2002209,
        2002233,
        2002349,
        2002370,
        2002374,
        2002432,
        2002437,
        2002440,
        2002459,
        2002470,
        2002473,
        2002484,
        2002568,
        2002799,
        2003234,
        2003248,
        2003335,
        2003394,
        2003483,
        2003586,
        2003588,
        2003590,
        2003733,
        2003743,
        2003747,
        2003751,
        2003754,
        2003758,
        2003760,
        2003762,
        2003765,
        2003766,
        2003768,
        2003770,
        2003778,
        2003781,
        2003783,
        2003788,
        2003791,
        2003794,
        2003811,
        2003855,
        2003857,
        2003859,
        2003866,
        2003872,
        2003877,
        2003884,
        2003885,
        2003889,
        2003891,
        2003895,
        2003896,
        2003897,
        2003902,
        2003904,
        2003910,
        2003913,
        2003915,
        2003919,
        2003920,
        2003923,
        2003944,
        2003960,
        2003967,
        2003971,
        2003975,
        2003980,
        2003986,
        2003991,
        2003992,
        2003994,
        2004181,
        2004213,
        2004281,
        2004661,
        2004745,
        2004870,
        2004972,
        2005172,
        2005323,
        2005683,
        2005756,
        2005805,
        2006198,
        2006452,
        2006466,
        2006475,
        2006500,
        2006568,
        2006654,
        2006659,
        2006841,
        2006891,
        2006991,
        2007014,
        2007088,
        2007144,
        2007160,
        2007296,
        2007408,
        2007685,
        2007738,
        2007848,
        2008194,
        2008259,
        2008347,
        2008594,
        2008650,
        2008651,
        2008899,
        2008921,
        2008931,
        2008937,
        2009514,
        2009573,
        2009671,
        2009674,
        2009715,
        2009840,
        2009885,
        2009937,
        2010022,
        2010177,
        2010405,
        2010678,
        2010854,
        2010959,
        2010963,
        2011140,
        2011169,
        2011425,
        2011428,
        2011708,
        2012151,
        2012193,
        2012279,
        2012382,
        2012387,
        2012494,
        2012497,
        2012547,
        2012684,
        2012844,
        2012903,
        2013015,
        2013110,
        2013289,
        2013435,
        2013760,
        2013944,
        2014390,
        2015334,
        2015351,
        2016016,
        2017087,
        2017337,
        2017343,
        2017348,
        2017350,
        2017353,
        2018027,
        2018049,
        2018122,
        2018324,
        2018498,
        2018555,
        2018584,
        2018858,
        2018964,
        2019843,
        2019853,
        2020092,
        2020182,
        2020560,
        2021054,
        2022263,
        2022704,
        2023057,
        2023153,
        2023943,
        2023999,
        2024123,
        2024486,
        2025047,
        2025452,
        2025455,
        2025972,
        2025973,
        2026175,
        2026572,
        2026574,
        2026747,
        2026750,
        2027215,
        2027663,
        2028109,
        2028489,
        2028718,
        2028738,
        2028757,
        2028904,
        2028917,
        2030066,
        2030205,
        2030411,
        2030919,
        2030958,
        2030964,
        2031015,
        2031096,
        2031640,
        2031908,
        2031915,
        2031922,
        2031958,
        2031994,
        2032137,
        2032602,
        2032735,
        2032825,
        2032899,
        2032941,
        2033165,
        2033412,
        2033431,
        2033802,
        2033898,
        2034021,
        2034493,
        2034507,
        2034753,
        2034896,
        2034933,
        2034979,
        2035310,
        2035571,
        2035572,
        2036071,
        2036311,
        2036386,
        2036577,
        2036704,
        2037044,
        2037047,
        2037147,
        2037207,
        2037288,
        2037560,
        2037656,
        2037657,
        2037658,
        2037817,
        2037834,
        2037850,
        2037891,
        2037900,
        2037907,
        2037910,
        2038120,
        2038276,
        2038401,
        2038406,
        2038410,
        2038425,
        2038435,
        2038504,
        2038667,
        2038683,
        2038761,
        2038914,
        2039105,
        2039536,
        2039817,
        2040019,
        2040091,
        2040319,
        2040456,
        2040718,
        2040758,
        2041341,
        2041471,
        2041649,
        2041825,
        2041912,
        2042053,
        2042078,
        2042452,
        2042484,
        2042737,
        2043359,
        2043446,
        2044013,
        2045142,
        2045174,
        2045199,
        2045247,
        2045423,
        2045610,
        2045836,
        2045843,
        2046232,
        2046371,
        2046408,
        2046489,
        2046738,
        2046877,
        2047200,
        2047997,
        2048620,
        2048865,
        2049163,
        2049323,
        2049375,
        2049378,
        2049431,
        2049493,
        2049644,
        2049652,
        2049800,
        2049995,
        2050008,
        2050093,
        2050291,
        2050375,
        2051160,
        2051261,
        2051365,
        2051591,
        2051816,
        2051820,
        2051882,
        2052272,
        2052283,
        2052320,
        2052940,
        2052977,
        2053001,
        2053146,
        2053255,
        2053560,
        2053573,
        2053627,
        2054043,
        2054091,
        2054143,
        2054161,
        2054166,
        2054168,
        2054169,
        2054173,
        2054177,
        2054181,
        2054201,
        2054240,
        2054269,
        2054272,
        2054277,
        2054334,
        2054420,
        2054523,
        2054625,
        2054630,
        2054646,
        2054729,
        2054749,
        2054767,
        2054768,
        2054798,
        2054879,
        2054892,
        2054914,
        2054927,
        2054934,
        2054972,
        2055034,
        2055055,
        2055058,
        2055073,
        2055135,
        2055142,
        2055149,
        2055157,
        2055176,
        2055201,
        2055239,
        2055410,
        2055457,
        2055510,
        2055611,
        2055715,
        2055716,
        2055726,
        2055751,
        2055765,
        2055820,
        2055827,
        2055851,
        2055894,
        2055910,
        2055916,
        2055920,
        2055925,
        2055951,
        2055985,
        2055988,
        2056040,
        2056054,
        2056058,
        2056092,
        2056234,
        2056384,
        2056387,
        2056503,
        2056518,
        2056567,
        2056584,
        2056594,
        2056721,
        2056864,
        2056944,
        2056966,
        2057072,
        2057085,
        2057104,
        2057135,
        2057246,
        2057248,
        2057308,
        2057310,
        2057398,
        2057452,
        2057544,
        2057581,
        2057594,
        2057789,
        2057911,
        2058100,
        2058102,
        2058123,
        2058170,
        2058224,
        2058370,
        2058497,
        2058622,
        2058739,
        2058908,
        2059007,
        2059133,
        2059211,
        2059372,
        2059549,
        2059644,
        2059658,
        2059669,
        2059744,
        2060079,
        2060601,
        2060886,
        2060927,
        2061174,
        2061192,
        2061281,
        2061331,
        2061405,
        2061495,
        2061572,
        2061821,
        2061867,
        2062017,
        2062531,
        2062560,
        2062591,
        2062631,
        2062641,
        2062661,
        2062800,
        2062829,
        2062988,
        2063031,
        2063053,
        2063064,
        2063183,
        2063342,
        2063567,
        2063701,
        2063717,
        2063754,
        2063860,
        2063892,
        2063906,
        2063920,
        2064016,
        2064146,
        2064393,
        2064519,
        2064561,
        2064815,
        2064817,
        2064818,
        2065073,
        2065189,
        2065374,
        2065450,
        2065717,
        2065769,
        2065917,
        2066429,
        2066434,
        2066442,
        2066445,
        2066482,
        2066617,
        2066638,
        2066764,
        2066824,
        2066909,
        2066978,
        2067046,
        2067173,
        2067186,
        2067417,
        2067777,
        2067908,
        2068095,
        2068107,
        2068217,
        2068736,
        2068969,
        2069237,
        2069256,
        2069438,
        2069482,
        2069561,
        2070061,
        2070391,
        2070668,
        2071732,
        2071923,
        2072061,
        2072128,
        2072220,
        2072425,
        2072432,
        2072499,
        2072894,
        2073055,
        2073100,
        2073337,
        2073512,
        2074513,
        2074539,
        2074663,
        2074722,
        2074965,
        2075151,
        2075243,
        2075883,
        2075898,
        2076308,
        2076432,
        2076836,
        2076952,
        2077450,
        2077917,
        2078449,
        2078632,
        2078726,
        2079242,
        2079289,
        2079333,
        2079348,
        2079491,
        2079802,
        2079814,
        2079953,
        2080036,
        2080221,
        2080256,
        2080257,
        2080323,
        2081022,
        2081315,
        2081646,
        2081820,
        2081836,
        2081837,
        2081890,
        2082234,
        2082332,
        2082399,
        2082438,
        2082454,
        2082518,
        2082569,
        2082570,
        2082575,
        2082701,
        2082775,
        2082859,
        2082860,
        2083126,
        2083295,
        2083327,
        2083506,
        2083571,
        2083614,
        2083762,
        2083858,
        2084602,
        2084996,
        2085536,
        2085882,
        2086086,
        2086394,
        2086832,
        2088164,
        2088271,
        2088363,
        2088754,
        2088756,
        2088768,
        2089102,
        2089142,
        2089514,
        2090204,
        2090742,
        2090886,
        2091280,
        2091281,
        2091354,
        2091437,
        2091536,
        2092167,
        2092287,
        2092340,
        2092654,
        2092730,
        2092770,
        2092951,
        2093004,
        2093010,
        2093102,
        2093105,
        2093225,
        2093365,
        2093416,
        2093469,
        2093500,
        2093509,
        2093512,
        2093969,
        2094245,
        2094789,
        2095017,
        2095018,
        2095019,
        2095048,
        2095111,
        2095114,
        2095130,
        2095360,
        2600016,
        2600052,
        2600105,
        2600171,
        2600201,
        2600250,
        2600294,
        2600403,
        2600404,
        2600405,
        2600443,
        2600444,
        2600445,
        2600457,
        2600485,
        2600514,
        2600544,
        2600883,
        2601049,
        2601248,
        2601267,
        2601268,
        2601365,
        2601366,
        2601388,
        2602947,
        2602957,
        2603832,
        2603914,
        2603921,
        2604158,
        2604167,
        2604195,
        2604345,
        2606187,
        2606188,
        2606559,
    ];
    public function fixProducts(){
        ini_set('max_execution_time', '0');
        $param = input();
        $spusArray = $this->spusArray;
        $spusArray = CommonLib::supportArray($spusArray);
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'product_id'=>$spusArray]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductHistory($product_ids['data']);
        }
        $url = url('repairTool/fixProducts', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    /**
     * 同步产品变更历史表
     */
    public function updateProductHistory($product_ids){
        ini_set('max_execution_time', '0');
        $historyModel = new ProductHistoryModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
//                $data = $historyModel->findProductHistory($product_id['_id']);
//                if(!empty($data)){
//                   continue;
//                }
//                if(isset($product_id['IsHistory']) && $product_id['IsHistory'] == 1){
//                    $IsHistory = 1;
//                }else{
                    $IsHistory = 0;
//                }
                $ret = $historyModel->addProductHistory($product_id['_id'],$IsHistory);
            }
        }
        pr('success');
    }

    public function fixProductsRewrittenUrl(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
//        $historyModel = new ProductHistoryModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page']]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductsRewrittenUrl($product_ids['data']);
        }
        $url = url('repairTool/fixProductsRewrittenUrl', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function updateProductsRewrittenUrl($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $regex = "/[^a-zA-Z0-9\-]/";
                $RewrittenUrl = preg_replace($regex,"",$product_id['RewrittenUrl']);
                pr($RewrittenUrl);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],['RewrittenUrl'=>$RewrittenUrl]);
                pr($ret);
            }
        }
        pr('success');
    }

    //根据Code下架产品
    public function updateProductByCode(){
        $params = request()->post();
        $productModel = new ProductModel;
        $codeArray = isset($params['code']) ? $params['code'] : array();
        if(empty($codeArray)){
            pr("empty codeArray");die;
        }
        foreach($codeArray as $key => $sku_id){
            //记录跑了的产品ID
            \think\Log::pathlog('SKUCODE = ',$sku_id,'updateProductByCode2.log');
            $updateKey = -1;
            $updateProduct = array();
            $findProudct = $productModel->getProduct(['sku_code'=>$sku_id,'status'=>[1,5],'field'=>['ProductStatus','_id','Skus._id','Skus.Code','Skus.Inventory']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //更新sku价格,查找key值
            foreach($findProudct['Skus'] as $pkey => $productSkus){
                if($sku_id == $productSkus['_id']){
                    $updateKey = $pkey;
                    $productSkus['Inventory'] = 0;
                }
                if($sku_id == $productSkus['Code']){
                    $updateKey = $pkey;
                    $productSkus['Inventory'] = 0;
                }
            }
            if($updateKey == -1){
                //记录找不到的SKU
                \think\Log::pathlog('productid = ',$sku_id,'updateProductByCode.error');
                continue;
            }
            $updateProduct['Skus.'.$updateKey.'.Inventory'] = 0;
            $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$updateProduct);
        }
        pr('success');die;
    }

    //根据Code下架产品
    public function updateProductStatus(){
        $params = request()->post();
        $productModel = new ProductModel;
        $codeArray = isset($params['code']) ? $params['code'] : array();
        if(empty($codeArray)){
            pr("empty codeArray");die;
        }
        foreach($codeArray as $key => $sku_id){
            $total = 0;
            $updateProduct = array();
            $findProudct = $productModel->getProduct(['sku_code'=>$sku_id,'status'=>[1,5],'field'=>['ProductStatus','_id','Skus._id','Skus.Code','Skus.Inventory']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //更新sku价格,查找key值
            foreach($findProudct['Skus'] as $pkey => $productSkus){
                $total = $total + $productSkus['Inventory'];
            }
            if($total == 0){
                \think\Log::pathlog('productid = ',$sku_id,'updateProductStatus.log');
                $updateProduct['ProductStatus'] = 3 ;
            }
            if(empty($updateProduct)){
                continue;
            }
            $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$updateProduct);
        }
        pr('success');die;
    }


    //修复产品首图
    public function fixProductsFirstImg(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
//        $historyModel = new ProductHistoryModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProductImg(['page'=>$param['page'],'AddTime'=>true]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductsImg($product_ids['data']);
        }
        $url = url('repairTool/fixProductsFirstImg', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function updateProductsImg($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $firstImg = '';
                if(isset($product_id['ImageSet']['ProductImg'][0]) && !empty($product_id['ImageSet']['ProductImg'][0])){
                    $firstImg = $product_id['ImageSet']['ProductImg'][0];
                }
                if(!empty($firstImg)){
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],['FirstProductImage'=>$firstImg]);
                }
            }
        }
        pr('success');
    }


    public function syncProductBulkRateFor916(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
//        $product_ids = $productModel->queryProduct916(['seller_id'=>888,'page'=>$param['page']]);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page']]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->updateProductLangs($product_ids['data']);
        }
        $url = url('product/syncProductBulkRateFor916', ['page'=>$param['page']+1,'access_token'=>'123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    /**
     * 产品多语言没有，插入变更历史
     */
    public function updateProductLangs($product_ids){
        $time = time();
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel();
//        $productHistoryModel = new ProductHistoryModel();
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $isTranLang = false;
                pr($product_id['_id']);
                $productLang = $productModel->getPrdouctmMultiLangs(['id'=>(int)$product_id['_id']]);
                if(empty($productLang)){
                    $isTranLang = true;
//                    $ret = $productHistoryModel->addProductHistory($product_id['_id']);
                }else{
                    //标题
                    if(isset($productLang['Title']) && !empty($productLang['Title'])){
                        if(is_array($productLang['Title'])){
                            $enLangVal = isset($productLang['Title']['en']) ? $productLang['Title']['en'] : '';
                            foreach($productLang['Title'] as $lang => $langVal){
                                if($lang != 'en'){
                                    if($langVal == $enLangVal){
                                        $isTranLang = true;
                                        break;
//                                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
                                    }
                                }
                            }
                            if(count($productLang['Title']) <= 10){
                                $isTranLang = true;
//                                $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                                continue;
                            }
                        }
                    }else{
                        $isTranLang = true;
//                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                        continue;
                    }

                    //详情
                    if(isset($productLang['Descriptions']) && !empty($productLang['Descriptions'])){
                        if(is_array($productLang['Descriptions'])){
                            $enLangVal = isset($productLang['Descriptions']['en']) ? $productLang['Descriptions']['en'] : '';
                            foreach($productLang['Descriptions'] as $lang => $langVal){
                                if($lang != 'en'){
                                    if($langVal == $enLangVal){
                                        $isTranLang = true;
                                        break;
//                                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
                                    }
                                }
                            }
                            if(count($productLang['Descriptions']) <= 10){
                                $isTranLang = true;
//                                $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                                continue;
                            }
                        }
                    }else{
                        $isTranLang = true;
//                        $ret = $productHistoryModel->addProductHistory($product_id['_id']);
//                        continue;
                    }
                }
                //记录需要翻译的产品ID
                if($isTranLang){
                    \think\Log::pathlog('spu:',$product_id['_id'],'spuLang.log');
                }
            }
        }
        pr('success');

    }

    /**
     * 更新
     * 店铺id 888
     * skuid 916开头
     */
    public function updateProductTime($product_ids){
        $time = time();
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $update = array();
                $update['AddTime'] = $time;
                pr($product_id['_id']);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                pr($ret);
            }
        }
        pr('success');


    }
    /**
     * 更新
     * 店铺id 888
     * skuid 916开头
     */
    public function product916($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $update = array();
                pr($product_id['_id']);
                if(!empty($product_id['Skus'])){
                    foreach($product_id['Skus'] as $key => $skus){
                        //916打头的产品数据
//                        if(substr($skus['_id'],0,3) == '916' ){
                        $bulkRatePrice = round((double)$skus['SalesPrice'] - (double)$skus['SalesPrice'] * 0.025,2);
//                            if($bulkRatePrice == $skus['BulkRateSet']['SalesPrice']){
//                                continue;
//                            }
                        $update['Skus.'.$key.'.BulkRateSet.SalesPrice'] = (double)$bulkRatePrice;
                        $update['Skus.'.$key.'.BulkRateSet.Discount'] = round(($skus['SalesPrice'] - $bulkRatePrice) / $skus['SalesPrice'],3);
//                        }
                    }
                    if(empty($update)){
                        continue;
                    }
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                    pr($ret);
//                    die;
                }

            }
        }
        pr('success');


    }


    /**
     * 同步产品批发折扣，价格
     */
    public function fixProductsBulkRatePrice(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'seller_id'=>333]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_ProductsBulkRatePrice($product_ids['data']);
        }
        $url = url('repairTool/fixProductsBulkRatePrice', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _ProductsBulkRatePrice($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                $update = array();
                pr($product_id['_id']);
                //获取价格数组
                $skus = $product_id['Skus'];
                if(!empty($skus)){
                    foreach($skus as $skey => $sku){
                        if(isset($sku['SalesPrice']) && isset($sku['BulkRateSet']['Discount']) && $sku['BulkRateSet']['Discount'] == '0.05'){
                            $price = $sku['SalesPrice'] - $sku['SalesPrice'] * 0.05;
                            $update['Skus.'.$skey.'.BulkRateSet.SalesPrice'] = (double)round($price,2);
                        }else{
                            continue;
                        }
                    }
                }
                if(!empty($update)){
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                }
            }
        }
        pr('success');
    }

    public function findErpProductByClass(){
        ini_set('max_execution_time', '0');
        $baseService = new BaseService();
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        $classArray = [1799940,1799954,1799957,1799962,1800024,1800032,1800036,1800047,2534,2561,2569,1800063,2620,2668,2691,2693,1800080,2759,2771,2789,2796,2855,2865,2872,2878,3860,3912
        ];
        $data = array();
        foreach($classArray as $class){
            $params['lastCategory'] = $class;
            $baseService->newCommonClassMap($params);
            $productList = $productModel->getProductListsByClass($params);
            $newProductList = array();
            if(!empty($productList)){
                foreach($productList as $pkey => $plist){
                    $newProductList[$pkey]['SPU'] = $plist['_id'];
                    $newProductList[$pkey]['Title'] = $plist['Title'];
                    $newProductList[$pkey]['StoreID'] = $plist['StoreID'];
                    $newProductList[$pkey]['CategoryPath'] = $plist['CategoryPath'];
                    $newProductList[$pkey]['class_id_old'] = $class;
                }
            }
            $data = array_merge($data,$newProductList);
        }
        $objPHPExcel = new \PHPExcel();

        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//水平居中
        $objPHPExcel->setActiveSheetIndex()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//垂直居中

        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension('A')->setWidth(25);//设置宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '产品ID')
            ->setCellValue('B1', '产品标题')
            ->setCellValue('C1', '店铺ID')
            ->setCellValue('D1', '类别路径')
            ->setCellValue('E1', 'ERP类别');
        $objPHPExcel->getActiveSheet()->setTitle('产品');
        //设置数据
        $i = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        foreach ($data as $vo){
            $objActSheet->setCellValue('A'.$i, $vo["SPU"]);
            $objActSheet->setCellValue('B'.$i, $vo["Title"]);
            $objActSheet->setCellValue('C'.$i, $vo["StoreID"]);
            $objActSheet->setCellValue('D'.$i, $vo["CategoryPath"]);
            $objActSheet->setCellValue('E'.$i, $vo["class_id_old"]);
            $i++;
        }
        // excel头参数
        $fileName = "指定ERP导出产品".date('_YmdHis');
        $xlsTitle = iconv('utf-8', 'gb2312', $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        //ob_end_clean();
        header("Content-Type: application/vnd.ms-excel;charset=utf-8;name='".$xlsTitle.".xls'");
        header("Content-Disposition: attachment;filename=$xlsTitle.xls");
        header('Cache-Control: max-age=0');
        //excel5为xls格式，excel2007为xlsx格式
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;

    }

    public $packingWeight = [
        37,61.5,

    ];


    public function productPackingList(){
        ini_set('max_execution_time', '0');
        $historyModel = new ProductHistoryModel();
        $productModel = new ProductModel;
        $packingList = $this->packingWeight;
        foreach($packingList as $list){
//            $update = array();
            $params = explode(',',$list);
            $product_id = isset($params[0]) ? $params[0] : 0;
//            $weight = isset($params[1]) ? $params[1] : 0;
//            $dimensions = isset($params[2]) ? $params[2] : 0;
//            $title = isset($params[3]) ? $params[3] : 0;
            if(empty($product_id)){
                continue;
            }
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['sku_search'=>$product_id,'status'=>[1,5],'field'=>['ProductStatus','_id','IsHistory']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //产品重量变更需要插入变更历史
            if(isset($findProudct['IsHistory']) && $findProudct['IsHistory'] == 1){
                $IsHistory = 1;
            }else{
                $IsHistory = 0;
            }
            $historyModel->addProductHistory($findProudct['_id'],$IsHistory);
//            if(!isset($findProudct['PackingList']) || !isset($findProudct['PackingList']['Weight'])){
//                $weight = sprintf("%01.3f",$weight/1000);
//                $update['PackingList']['Weight'] = $weight > 0.01 ? $weight : 0.01;//最小值
//                $update['PackingList']['UseCustomWeight'] = 0;
//                $update['PackingList']['CustomeWeightInfo'] = null;
//                $update['PackingList']['Dimensions'] = null;
//                $update['PackingList']['Title'] = null;
//                if(!empty($update)){
//                    pr($update);
//                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$update);
//                }
//            }
        }
        pr("done");
    }

    public function productPackingTitleList(){
        $productModel = new ProductModel;
        $packingList = $this->packingTitle;;
        foreach($packingList as $list){
            $update = array();
            $params = explode(',',$list);
            $product_id = isset($params[0]) ? $params[0] : 0;
            $title = isset($params[1]) ? $params[1] : null;
            if(empty($product_id)){
                continue;
            }
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['sku_search'=>$product_id,'status'=>[1,5],'field'=>['ProductStatus','_id','PackingList']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            if(isset($findProudct['PackingList']) && isset($findProudct['PackingList']['Title'])){
                $update['PackingList.Title'] = (string)$title;
                if(!empty($update)){
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$findProudct['_id']],$update);
                }
            }
        }
        pr("done");
    }


    /**
     * 更新产品类别
     * @var array
     */

    public function updateProductClass(){
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        $packingList = $this->productClass;
        foreach($packingList as $list){
            $update = array();
            $params = explode(',',$list);
            $product_id = isset($params[0]) ? $params[0] : 0;
            $class_id = isset($params[1]) ? $params[1] : null;
            if(empty($product_id)){
                continue;
            }
            //查找产品是否存在
            $findProudct = $productModel->getProduct(['product_id'=>$product_id,'status'=>[1,5],'field'=>['ProductStatus','_id']]);
            pr($findProudct);
            if(empty($findProudct)){
                continue;
            }
            //查询类别
            $classData = $classModel->getClassDetail(['id' => (int)$class_id]);
            pr($classData);
            if(empty($classData)){
                continue;
            }
            $path = isset($classData['id_path']) ? $classData['id_path'] : null;
            if(!empty($path)){
                $classArray = explode('-',$path);
                $update['CategoryPath'] = $path;
                $update['IsHistory'] = 0;
                $update['FirstCategory'] = isset($classArray[0]) ? (int)$classArray[0] : 0;
                $update['SecondCategory'] = isset($classArray[1]) ? (int)$classArray[1] : 0;
                $update['ThirdCategory'] = isset($classArray[2]) ? (int)$classArray[2] : 0;
                $update['FourthCategory'] = isset($classArray[3]) ? (int)$classArray[3] : 0;
                $update['FifthCategory'] = isset($classArray[4]) ? (int)$classArray[4] : 0;
                pr($update);
                $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id],$update);
            }
        }
        pr("done");
    }



    public function fixProductsClass(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page']]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixProductsClass($product_ids['data']);
        }
        $url = url('repairTool/fixProductsClass', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixProductsClass($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $update = array();
                if(isset($product_id['CategoryPath']) && !empty($product_id['CategoryPath'])){
                    $classArray = explode('-',$product_id['CategoryPath']);
                    $update['FirstCategory'] = isset($classArray[0]) ? (int)$classArray[0] : 0;
                    $update['SecondCategory'] = isset($classArray[1]) ? (int)$classArray[1] : 0;
                    $update['ThirdCategory'] = isset($classArray[2]) ? (int)$classArray[2] : 0;
                    $update['FourthCategory'] = isset($classArray[3]) ? (int)$classArray[3] : 0;
                    $update['FifthCategory'] = isset($classArray[4]) ? (int)$classArray[4] : 0;
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                    pr($ret);
                }
            }
        }
        pr('success');
    }

    //导出没有产品的分类分类名，一级分类及分类，三级分类，四级分类
    public function findClassNotProduct(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductClassModel();
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->paginateClass(['page'=>$param['page']]);
//        pr($product_ids);die;
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_findClassNotProduct($product_ids['data']);
        }
        $url = url('repairTool/findClassNotProduct', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    private function _findClassNotProduct($classArray){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        $classModel = new ProductClassModel();
        if(!empty($classArray)){
            foreach($classArray as $classDetail){
                pr($classDetail['id']);
                if(isset($classDetail['pdc_ids']) && !empty($classDetail['pdc_ids'])){
                    $class_ids = $classDetail['pdc_ids'];
                    array_push($class_ids,$classDetail['id']);
                    $class_ids = CommonLib::supportArray($class_ids);
                }else{
                    $class_ids = (int)$classDetail['id'];
                }
//                pr($class_ids);
                //查找产品是否存在
                $findProudct = $productModel->getProduct(['lastCategory'=>$class_ids,'status'=>[1,5],'field'=>['ProductStatus','_id']]);
//                pr($findProudct);
                if(!empty($findProudct)){
                    continue;
                }else{
                    $classPath = array();
                    $firstClass = array();
                    $firstname = $secname = $thirdname = $fouthname = '';
                    $classPath = explode('-',$classDetail['id_path']);
                    $first = isset($classPath[0]) ? $classPath[0] : 0 ;
                    if(!empty($first)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$first]);
                        $firstname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

                    $sec = isset($classPath[1]) ? $classPath[1] : 0 ;
                    if(!empty($sec)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$sec]);
                        $secname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

                    $third = isset($classPath[2]) ? $classPath[2] : 0 ;
                    if(!empty($third)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$third]);
                        $thirdname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

                    $fouth = isset($classPath[3]) ? $classPath[3] : 0 ;
                    if(!empty($fouth)){
                        $firstClass  = $classModel->getClassDetail(['id'=>(int)$fouth]);
                        $fouthname = isset($firstClass['title_en']) ? $firstClass['title_en'] : null;
                    }

                    //过滤特殊字符
                    $regex = "/\/|\,|\\\|\|/";
                    $classDetail['title_en'] = preg_replace($regex,"&",$classDetail['title_en']);
                    \think\Log::pathlog('class_not:',$classDetail['id'].';'.$classDetail['level'].';'.$first.';'.$firstname.';'.$sec.';'.$secname.';'.$third.';'.$thirdname.';'.$fouth.';'.$fouthname.';'.$classDetail['id_path'],'classNotProduct2.log');
                }
            }
        }
    }


    //修复拆分产品
    public function fixSplitProducts(){
        ini_set('max_execution_time', '0');
        $param = input();
        $productModel = new ProductModel;
        $param['page'] = isset($param['page']) ? $param['page'] : 1;
        pr('page = '.$param['page']);
        $product_ids = $productModel->queryProduct916(['page'=>$param['page'],'is_split'=>1]);
        if($param['page'] > $product_ids['last_page']){
            pr("end");die;
        }
        if(isset($product_ids['data']) && !empty($product_ids['data'])){
            $this->_fixSplitProducts($product_ids['data']);
        }
        $url = url('repairTool/fixSplitProducts', ['page'=>$param['page']+1,'access_token'=>'dx123']);
        $this->success('jump', $url, null, 1,[],'html');
    }

    public function _fixSplitProducts($product_ids){
        ini_set('max_execution_time', '0');
        $productModel = new ProductModel;
        if(!empty($product_ids)){
            foreach($product_ids as $product_id){
                pr($product_id['_id']);
                $update = array();
                if(isset($product_id['AddTime']) && !empty($product_id['AddTime'])){
                    $update['AddTime'] = (int)$product_id['AddTime'];
                }
                if(isset($product_id['EditTime']) && !empty($product_id['EditTime'])){
                    $update['EditTime'] = (int)$product_id['EditTime'];
                }
                if(!empty($update)){
                    pr($update);
                    $ret = $productModel->updateProductSkuPrice(['_id'=>(int)$product_id['_id']],$update);
                }
            }
        }
        pr('success');
    }
}
