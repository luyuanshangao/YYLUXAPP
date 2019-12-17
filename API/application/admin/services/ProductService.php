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
            $classData = $productModel->selectClass(['class_id' => CommonLib::supportArray($categoryArray),'lang'=>$params['lang']]);
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

}
