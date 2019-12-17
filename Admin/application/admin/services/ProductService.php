<?php
namespace app\admin\services;

use app\admin\model\ProductModel;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;


/**
 * 产品接口
 */
class ProductService extends BaseService
{
    //币种符号
    public $currency_code = [
            'USD'=>'$',
            'GBP'=>'£',
            'CAD'=>'CA$',
            'EUR'=>'€',
            'BRL'=>'R$',
            'RUB'=>'RUB',
            'AUD'=>'AU$',
            'CZK'=>'CZK',
            'CLP'=>'CLP',
            'JPY'=>'JPY',
            'ILS'=>'ILS',
            'ARS'=>'ARS',
            'UAH'=>'UAH',
            'TRY'=>'TRY',
            'CHF'=>'CHF',
            'ZAR'=>'R',
            'DKK'=>'kr',
            'NOK'=>'kr',
            'SEK'=>'kr',
            'INR'=>'Rs',
            'SGD'=>'S$',
            'MXN'=>'Mex$',
            'KRW'=>'원',
            'PLN'=>'PLN',
    ];

    /**
     * affiliate feed 数据导出
     * @param $params
     * @return array
     */
    public function getDataFeed($params){
        $productModel = new ProductModel();
        $params['currency_code'] = $this->currency_code[$params['currency']];

        $category_id = isset($params['category_id']) ? $params['category_id'] : '';
        if(!empty($category_id)){
            $categoryArray = explode(',',$category_id);
            //类别映射
            $classData = $productModel->selectClass(['class_id' => CommonLib::supportArray($categoryArray)]);
            $class_id = array();
            if(!empty($classData)){
                foreach($classData as $class){
                    array_push($class_id,(int)$class['id']);
                    if(!empty($class['pdc_ids'])){
                        array_push($class_id,(int)$class['pdc_ids'][0]);
                    }
                }
                $params['lastCategory'] = $class_id;
            }else{
                //未能查询到此分类的数据，不可下载
                return array();
            }
        }
        $products = $productModel->paginateProductList($params);
        if (!empty($products['data'])) {
            $products['data'] = $this->commonProdcutDataToDownLoad($products['data'],$params);
        }else{
            return array();
        }
        return $products;
    }


    /**
     * 产品详情价格处理
     * @param $products
     * @param $discountLowPrice
     * @param $discountHightPrice
     */
    private function productPrice(&$products,$discountLowPrice,$discountHightPrice){
        //原价的价格区间
        $originalLowPrice = isset($products['LowPrice']) ? sprintf('%01.2f',$products['LowPrice']) : '';//最低价格
        $originalHightPrice = isset($products['HightPrice']) ? sprintf('%01.2f',$products['HightPrice']) : '';//最高价

        unset($products['DiscountHightPrice'],$products['DiscountLowPrice']);
        //价格逻辑处理
        $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
        //商品展示的售价
        $products['LowPrice'] = $priceArray['LowPrice'];
        $products['HightPrice'] = $priceArray['HightPrice'];
        //原价
        $products['OriginalLowPrice'] = $priceArray['OriginalLowPrice'];
        $products['OriginalHightPrice'] = $priceArray['OriginalHightPrice'];
    }

    /**
     * @param $products
     * @param $lang
     * @return array
     */
    private function productClassData($products,$lang){
        $classData = array();
        //分类信息
        $classArray = explode('-',$products['CategoryPath']);
        $classInfo = (new ProductClassModel())->getClassDetail(['id'=>(int)end($classArray)]);
        if($classInfo['type'] == 1){
            //ERP类别数据
            $classArray = explode('-',$classInfo['id_path']);
            foreach($classArray as $level => $class_id){
                $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                if(!empty($result[$level])){
                    $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                    $title = $result[$level]['title_en'];
                    //如果多语种没数据，默认取英文
                    if(DEFAULT_LANG != $lang) {
                        $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                            $result[$level]['Common'][$lang] : $title;
                    }
                    $classData[$level]['title'] = $title;
                    $classData[$level]['id'] = $result[$level]['id'];
                }
            }
            return $classData;
        }
        if( isset($classInfo['pdc_ids']) && !empty($classInfo['pdc_ids'])){
            $data = (new ProductClassModel())->getClassDetail(['id'=>(int)$classInfo['pdc_ids'][0]]);
            if(!empty($data)){
                //非一级类别
                if($data['level'] != 1){
                    $id_path = explode('-',$data['id_path']);
                    foreach($id_path as $level => $class_id){
                        $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                        if(!empty($result[$level])){
                            $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                            $title = $result[$level]['title_en'];
                            //如果多语种没数据，默认取英文
                            if(DEFAULT_LANG != $lang) {
                                $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                                    $result[$level]['Common'][$lang] : $title;
                            }
                            $classData[$level]['title'] = $title;
                            $classData[$level]['id'] = $result[$level]['id'];
                        }
                    }
                }else{
                    //只有一级类别
                    $result[0] =  $data;
                    if(!empty($data)){
                        $title = $data['title_en'];
                        //如果多语种没数据，默认取英文
                        if(DEFAULT_LANG != $lang) {
                            $title = isset($data['Common'][$lang]) && !empty($data['Common'][$lang]) ? $data['Common'][$lang] : $title;
                        }
                        $classData[0]['title'] = $title;
                        $classData[0]['hrefTitle'] = $data['rewritten_url'].'-'.$data['id'];
                        $classData[0]['id'] = $data['id'];
                    }
                }
            }else{
                //映射数据为空的情况下，展示PDC类别数据
                $classArray = explode('-',$products['CategoryPath']);
                foreach($classArray as $level => $class_id){
                    $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                    if(!empty($result[$level])){
                        $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                        $title = $result[$level]['title_en'];
                        //如果多语种没数据，默认取英文
                        if(DEFAULT_LANG != $lang) {
                            $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                                $result[$level]['Common'][$lang] : $title;
                        }
                        $classData[$level]['title'] = $title;
                        $classData[$level]['id'] = $result[$level]['id'];
                    }
                }
            }
        }else{
            //没有映射，只能展示PDC原来的类别
            $classArray = explode('-',$products['CategoryPath']);
            foreach($classArray as $level => $class_id){
                $result[$level] = (new ProductClassModel())->getClassDetail(['id'=>(int)$class_id],$lang);
                if(!empty($result[$level])){
                    $classData[$level]['hrefTitle'] = $result[$level]['rewritten_url'].'-'.$result[$level]['id'];
                    $title = $result[$level]['title_en'];
                    //如果多语种没数据，默认取英文
                    if(DEFAULT_LANG != $lang) {
                        $title = isset($result[$level]['Common'][$lang]) && !empty($result[$level]['Common'][$lang]) ?
                            $result[$level]['Common'][$lang] : $title;
                    }
                    $classData[$level]['title'] = $title;
                    $classData[$level]['id'] = $result[$level]['id'];
                }
            }
        }
        return $classData;
    }
}
