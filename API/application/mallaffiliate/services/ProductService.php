<?php
namespace app\mallaffiliate\services;

use app\common\helpers\CommonLib;
use app\mallaffiliate\model\ProductClassModel;
use app\mallaffiliate\model\ProductModel;
use think\Cache;
use think\Exception;
use think\Log;


/**
 * 开发：钟宁
 * 功能：affiliate 产品业务层
 * 时间：2018-06-08
 */
class ProductService extends BaseService
{
    const IMGURL = 'https://img.dxcdn.com';

    /**
     * 产品查询接口
     * @param $params
     * @return mixed
     */
    public function query($params){
        $imgUrl = 'https://img.dxcdn.com';
        //语种切换
        $lang = isset($params['language']) ? $params['language'] : self::DEFAULT_LANG;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] > 50 ? 50 : (int)$params['pageSize'] : 50;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;
        if(isset($params['spus']) && !empty($params['spus'])){
            $params['spus'] = explode(',',$params['spus']);
        }
        if(isset($params['skus']) && !empty($params['skus'])){
            $params['skus'] = explode(',',$params['skus']);
        }

        $data = (new ProductModel())->selectProduct($params);
        $result['status'] = true;
        $result['errorCode'] = 0;
        $result['pageSize'] = $pageSize;
        $result['pageIndex'] = $pageIndex;
        $result['result'] = [];
        if(!empty($data)){
            foreach($data as $k => $product){
                //产品id
                $result['result'][$k]['spu'] = isset($product['_id']) ? $product['_id'] : '';
                $result['result'][$k]['CategoryPath'] = isset($product['CategoryPath']) ? $product['CategoryPath'] : '';
                //分类树名称
//                $classData = $this->productClassData($product['CategoryPath'],$lang);
//                if(!empty($classData)){
//                    $classPath = CommonLib::getColumn('id',$classData);
//                    $result['result'][$k]['CategoryPath'] = implode('-',$classPath);
//                }
//                $result['result'][$k]['FirstCategoryTitle'] = isset($classData[0]['title']) ? $classData[0]['title'] : '';
//                $result['result'][$k]['SecondCategoryTitle'] = isset($classData[1]['title']) ? $classData[1]['title'] : '';
//                $result['result'][$k]['ThirdCategoryTitle'] = isset($classData[2]['title']) ? $classData[2]['title'] : '';
//                $result['result'][$k]['FourthCategoryTitle'] = isset($classData[3]['title']) ? $classData[3]['title'] : '';

                $result['result'][$k]['BrandId'] = isset($product['BrandId']) ? $product['BrandId'] : '';
                //链接地址
                $rewrittenUrl = isset($product['RewrittenUrl']) ? $product['RewrittenUrl'] : '';
                $result['result'][$k]['ProductUrl'] = MALL_DOCUMENT .'p/'.$rewrittenUrl.'-'.$product['_id'];//链接地址
                //标题
                $result['result'][$k]['ProductTitle'] = isset($product['Title']) ? $product['Title'] : '';
                //品牌名称
//                $result['result'][$k]['BrandName'] = !empty($product['BrandName']) ? $product['BrandName'] : '';
//                if($product['BrandName'] == 'N/A' || $product['BrandName'] == 'None'){
//                    $result['result'][$k]['BrandName'] = '';
//                }

                //原价的价格区间
                $result['result'][$k]['LowPrice'] = isset($product['LowPrice']) ? sprintf('%01.2f',$product['LowPrice']) : '';//最低价格
                $result['result'][$k]['HighPrice'] = isset($product['HightPrice']) ? sprintf('%01.2f',$product['HightPrice']) : '';//最高价
                //折扣后的价格区间
                $result['result'][$k]['DiscountLowPrice'] = isset($product['DiscountLowPrice']) ? sprintf('%01.2f',$product['DiscountLowPrice']) : '';//最低价格
                $result['result'][$k]['DiscountHighPrice'] = isset($product['DiscountHightPrice']) ? sprintf('%01.2f',$product['DiscountHightPrice']) : '';//最高价
                $result['result'][$k]['Discount'] = isset($product['HightDiscount']) ? sprintf('%01.2f',$product['HightDiscount']) : '';
                $result['result'][$k]['AddTime'] = $product['AddTime'];
                $result['result'][$k]['ProductImg'] = array();
                if(isset($product['ImageSet']['ProductImg']) && !empty($product['ImageSet']['ProductImg'])){
                    $newImg = array();
                    foreach($product['ImageSet']['ProductImg'] as $imgkey => $ProductImg){
                        $newImg[$imgkey] = $imgUrl.$ProductImg;
                    }
                    $result['result'][$k]['ProductImg'] = $newImg;
                }

                $result['result'][$k]['Descriptions'] = isset($product['Descriptions']) ? $product['Descriptions'] : '';
                $result['result'][$k]['ProductStatus'] = $product['ProductStatus'];
                $result['result'][$k]['Skus'] = $product['Skus'];
                //多语言
                if(self::DEFAULT_LANG != $lang) {
                    //标题多语言
                    $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);

                    $result['result'][$k]['ProductTitle'] = isset($productMultiLang['Title'][$lang]) ?
                        $productMultiLang['Title'][$lang] : $result['result'][$k]['ProductTitle'];//默认英语
                    $result['result'][$k]['Descriptions'] = isset($productMultiLang['Descriptions'][$lang]) ?
                        $productMultiLang['Descriptions'][$lang] : $result['result'][$k]['Descriptions'];//默认英语
                    //属性多语言
                    foreach($product['Skus'] as $key => $sku){
                        foreach($sku['SalesAttrs'] as $attrkey => $attr){
                            $option = isset($attr['OptionId']) ? $attr['OptionId'] : 0;
                            $attrData = $this->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$product['_id']);
                            $key_lang = $option.'_'.$sku['_id'];
                            //例：color颜色的多语言
                            $result['result'][$k]['Skus'][$key]['SalesAttrs'][$attrkey]['Name'] = isset($attrData['Title'][$lang]) ?
                                $attrData['Title'][$lang] : $attr['Name'];
                            //例：color下蓝色blue的多语言
                            $result['result'][$k]['Skus'][$key]['SalesAttrs'][$attrkey]['Value'] = isset($attrData['Options'][$key_lang][$lang]) ?
                                $attrData['Options'][$key_lang][$lang] : $attr['Value'];
                            //dx_product_customAttr_multiLangs
                            if(isset($attrData['Options'][$key_lang][$lang]) && !empty($attrData['Options'][$key_lang][$lang])){
                                $result['result'][$k]['Skus'][$key]['SalesAttrs'][$k]['CustomValue'] = $attrData['Options'][$key_lang][$lang];
                            }
                            //dx_product_attr_multiLangs
                            if(isset($attrData['Options'][$option][$lang]) && empty($attrData['Options'][$option][$lang])){
                                $result['result'][$k]['Skus'][$key]['SalesAttrs'][$k]['CustomValue'] = $attrData['Options'][$option][$lang];
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 产品查询-何元
     * @param $params
     * @return mixed
     */
    public function lists($params){
        $imgUrl = 'https://img.dxcdn.com';
        //语种切换
        $lang = isset($params['language']) ? $params['language'] : self::DEFAULT_LANG;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] > 50 ? 50 : (int)$params['pageSize'] : 50;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;
        if(isset($params['spus']) && !empty($params['spus'])){
            $params['spus'] = explode(',',$params['spus']);
        }
        if(isset($params['skus']) && !empty($params['skus'])){
            $params['skus'] = explode(',',$params['skus']);
        }

        $data = (new ProductModel())->selectProduct($params);
        $result['status'] = true;
        $result['errorCode'] = 0;
        $result['pageSize'] = $pageSize;
        $result['pageIndex'] = $pageIndex;
        $result['result'] = [];
        if(!empty($data)){
            foreach($data as $k => $product){
                //产品id
                $result['result'][$k]['spu'] = isset($product['_id']) ? $product['_id'] : '';
                $result['result'][$k]['CategoryPath'] = isset($product['CategoryPath']) ? $product['CategoryPath'] : '';
                //分类树名称
                $classData = $this->productClassData($product['CategoryPath'],$lang);
                if(!empty($classData)){
                    $classPath = CommonLib::getColumn('id',$classData);
                    $result['result'][$k]['CategoryPath'] = implode('-',$classPath);
                }
                $result['result'][$k]['FirstCategoryTitle'] = isset($classData[0]['title']) ? $classData[0]['title'] : '';
                $result['result'][$k]['SecondCategoryTitle'] = isset($classData[1]['title']) ? $classData[1]['title'] : '';
                $result['result'][$k]['ThirdCategoryTitle'] = isset($classData[2]['title']) ? $classData[2]['title'] : '';
                $result['result'][$k]['FourthCategoryTitle'] = isset($classData[3]['title']) ? $classData[3]['title'] : '';

                $result['result'][$k]['BrandId'] = isset($product['BrandId']) ? $product['BrandId'] : '';
                //链接地址
                $rewrittenUrl = isset($product['RewrittenUrl']) ? $product['RewrittenUrl'] : '';
                $result['result'][$k]['ProductUrl'] = MALL_DOCUMENT .'p/'.$rewrittenUrl.'-'.$product['_id'];//链接地址
                //标题
                $result['result'][$k]['ProductTitle'] = isset($product['Title']) ? $product['Title'] : '';
                //品牌名称
                $result['result'][$k]['BrandName'] = !empty($product['BrandName']) ? $product['BrandName'] : '';
                if($product['BrandName'] == 'N/A' || $product['BrandName'] == 'None'){
                    $result['result'][$k]['BrandName'] = '';
                }

                //原价的价格区间
                $result['result'][$k]['LowPrice'] = isset($product['LowPrice']) ? sprintf('%01.2f',$product['LowPrice']) : '';//最低价格
                $result['result'][$k]['HighPrice'] = isset($product['HightPrice']) ? sprintf('%01.2f',$product['HightPrice']) : '';//最高价
                //折扣后的价格区间
                $result['result'][$k]['DiscountLowPrice'] = isset($product['DiscountLowPrice']) ? sprintf('%01.2f',$product['DiscountLowPrice']) : '';//最低价格
                $result['result'][$k]['DiscountHighPrice'] = isset($product['DiscountHightPrice']) ? sprintf('%01.2f',$product['DiscountHightPrice']) : '';//最高价
                $result['result'][$k]['Discount'] = isset($product['HightDiscount']) ? sprintf('%01.2f',$product['HightDiscount']) : '';
                $result['result'][$k]['AddTime'] = $product['AddTime'];
                $result['result'][$k]['ProductImg'] = array();
                if(isset($product['ImageSet']['ProductImg']) && !empty($product['ImageSet']['ProductImg'])){
                    $newImg = array();
                    foreach($product['ImageSet']['ProductImg'] as $imgkey => $ProductImg){
                        $newImg[$imgkey] = $imgUrl.$ProductImg;
                    }
                    $result['result'][$k]['ProductImg'] = $newImg;
                }
                $result['result'][$k]['Descriptions'] = isset($product['Descriptions']) ? $product['Descriptions'] : '';
                $result['result'][$k]['ProductStatus'] = $product['ProductStatus'];
                $result['result'][$k]['Skus'] = $product['Skus'];
                //多语言
                if(self::DEFAULT_LANG != $lang) {
                    //标题多语言
                    $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);

                    $result['result'][$k]['ProductTitle'] = isset($productMultiLang['Title'][$lang]) ?
                        $productMultiLang['Title'][$lang] : $result['result'][$k]['ProductTitle'];//默认英语
                    $result['result'][$k]['Descriptions'] = isset($productMultiLang['Descriptions'][$lang]) ?
                        $productMultiLang['Descriptions'][$lang] : $result['result'][$k]['Descriptions'];//默认英语
                    //属性多语言
                    foreach($product['Skus'] as $key => $sku){
                        foreach($sku['SalesAttrs'] as $attrkey => $attr){
                            $option = isset($attr['OptionId']) ? $attr['OptionId'] : 0;
                            $attrData = $this->getProductAttrMultiLang($attr['_id'],$option,$sku['_id'],$product['_id']);
                            $key_lang = $option.'_'.$sku['_id'];
                            //例：color颜色的多语言
                            $result['result'][$k]['Skus'][$key]['SalesAttrs'][$attrkey]['Name'] = isset($attrData['Title'][$lang]) ?
                                $attrData['Title'][$lang] : $attr['Name'];
                            //例：color下蓝色blue的多语言
                            $result['result'][$k]['Skus'][$key]['SalesAttrs'][$attrkey]['Value'] = isset($attrData['Options'][$key_lang][$lang]) ?
                                $attrData['Options'][$key_lang][$lang] : $attr['Value'];
                            //dx_product_customAttr_multiLangs
                            if(isset($attrData['Options'][$key_lang][$lang]) && !empty($attrData['Options'][$key_lang][$lang])){
                                $result['result'][$k]['Skus'][$key]['SalesAttrs'][$k]['CustomValue'] = $attrData['Options'][$key_lang][$lang];
                            }
                            //dx_product_attr_multiLangs
                            if(isset($attrData['Options'][$option][$lang]) && empty($attrData['Options'][$option][$lang])){
                                $result['result'][$k]['Skus'][$key]['SalesAttrs'][$k]['CustomValue'] = $attrData['Options'][$option][$lang];
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 产品查询分类
     * @param $params
     * @return mixed
     */
    public function queryByCategory($params){
        //美金汇率
        $exchangerate_us = 6.8808;
        //语种切换
        $lang = isset($params['language']) ? $params['language'] : self::DEFAULT_LANG;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] > 50 ? 50 : (int)$params['pageSize'] : 50;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;

        $params['lastCategory'] = $params['categoryid'];
        $productModel = model('admin/productModel');
        //类别映射
        //$this->newCommonClassMap($params);
        //查询产品
        $params['productStatus'] = 1;
        //过滤香港仓和916的数据 -- 蔡兰兰
        $params['store_id'] = ['in',[333,666,777]];

        $data = (new ProductModel())->selectProduct($params);
        $result['pageSize'] = $pageSize;
        $result['pageIndex'] = $pageIndex;
        $result['result'] = [];
        if(!empty($data)){
            foreach($data as $k => $product){
                $purchaseArray = array();
                //产品id
                $result['result'][$k]['spu'] = isset($product['_id']) ? $product['_id'] : '';
                //分类树名称
                $classData = $this->productClassData($product['CategoryPath'],$lang);

                $result['result'][$k]['Category'] = isset($classData[0]['title']) ? $classData[0]['title'] : '';
                $result['result'][$k]['SubCategory'] = isset($classData[1]['title']) ? $classData[1]['title'] : '';

                //链接地址
                $rewrittenUrl = isset($product['RewrittenUrl']) ? $product['RewrittenUrl'] : '';
                $result['result'][$k]['ProductUrl'] = MALL_DOCUMENT .'p/'.$rewrittenUrl.'-'.$product['_id'];//链接地址
                //标题
                $result['result'][$k]['ProductTitle'] = isset($product['Title']) ? $product['Title'] : '';

                //原价的价格区间
                $result['result'][$k]['SalePrice'] = isset($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
                //折扣后的价格区间
                $result['result'][$k]['ListPrice'] = isset($product['LowListPrice']) ? (string)$product['LowListPrice'] : '';//最低价格

                $result['result'][$k]['Discount'] = !empty($product['ListPriceDiscount']) ? (string)$product['ListPriceDiscount'] : 0;

                if(!empty($result['result'][$k]['SalePrice']) && !empty($result['result'][$k]['ListPrice'])){
                    if($result['result'][$k]['ListPrice'] > $result['result'][$k]['SalePrice']){
                        $result['result'][$k]['Discount'] = (string)round(($result['result'][$k]['ListPrice'] - $result['result'][$k]['SalePrice']) / $result['result'][$k]['ListPrice'],2);
                    }else{
                        $result['result'][$k]['ListPrice'] = '';
                    }
                }

                $result['result'][$k]['ProductImg'] = !empty($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
                if(isset($product['ImageSet']['ProductImg']) && !empty($product['ImageSet']['ProductImg'])){
                    $result['result'][$k]['ProductImg'] = self::IMGURL.$product['ImageSet']['ProductImg'][0];
                }

                $result['result'][$k]['ProductWeight'] = !empty($product['PackingList']['Weight']) ? (string)$product['PackingList']['Weight'] : 0;
                if(!empty($product['Skus'])){
                    foreach($product['Skus'] as $key => $val){
                        $result['result'][$k]['Skus'][$key]['Code'] = $val['Code'];
                        $purchase = $productModel->getProductPurchase(['sku' => $val['Code']]);
                        if(!empty($purchase['unitcost'])){
                            //价格请按 成本价+手续费（4.5%）+毛利（30%）传数据给对方
                            $unitcost = sprintf('%01.2f',($purchase['unitcost'] * (1 + 0.045 + 0.3)) / $exchangerate_us);
                            $result['result'][$k]['Skus'][$key]['SalesPrice'] = $unitcost;
                            //最低价展示
                            $purchaseArray[] = $unitcost;
                        }else{
                            //记录没找到的采购价格
                            Log::pathlog('purchase:',$product['_id'].';'.$val['Code'],'purchase.log');
                            //没有采购价格，按原价返回
                            $result['result'][$k]['Skus'][$key]['SalesPrice'] = (string)$val['SalesPrice'];
                        }
                    }
                }
                if(!empty($purchaseArray)){
                    $lowprice = min($purchaseArray);
                    $result['result'][$k]['SalePrice'] = $lowprice;
                    if(!empty($product['ListPriceDiscount'])){
                        $result['result'][$k]['ListPrice'] = sprintf('%01.2f',$lowprice * $product['ListPriceDiscount'] + $lowprice);
                    }
                }
                $result['result'][$k]['Descriptions'] = isset($product['Descriptions']) ? $product['Descriptions'] : '';
            }
        }
        return $result;
    }

    /**
     * 产品查询分类
     * @param $params
     * @return mixed
     */
    public function productList($params){
        //语种切换
        $lang = isset($params['language']) ? $params['language'] : self::DEFAULT_LANG;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] > 50 ? 50 : (int)$params['pageSize'] : 50;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;

        //查询产品
        $data = (new ProductModel())->paginateProduct($params);
        $result['pageSize'] = $pageSize;
        $result['pageIndex'] = $pageIndex;
        $result['totalPage'] = !empty($data['last_page']) ? $data['last_page'] : 0;
        $result['result'] = [];
        if(!empty($data['data'])){
            foreach($data['data'] as $k => $product){
                //产品id
                $result['result'][$k]['spu'] = isset($product['_id']) ? $product['_id'] : '';

                //标题
                $result['result'][$k]['name'] = isset($product['Title']) ? $product['Title'] : '';

                $result['result'][$k]['weight'] = !empty($product['PackingList']['Weight']) ? (string)$product['PackingList']['Weight'] : 0;//重量，换算成克，加泡10克重量
                $dimensions = explode('-', $product['PackingList']['Dimensions']);//长宽高
                $result['result'][$k]['length'] = !empty($dimensions[0]) ? $dimensions[0] : 0;
                $result['result'][$k]['width'] = !empty($dimensions[1]) ? $dimensions[1] : 0;
                $result['result'][$k]['height'] = !empty($dimensions[2]) ? $dimensions[2] : 0;

                $result['result'][$k]['images'] = array();
                if(isset($product['ImageSet']['ProductImg']) && !empty($product['ImageSet']['ProductImg'])){
                    foreach($product['ImageSet']['ProductImg'] as $key => $img){
                        $result['result'][$k]['images'][$key] = self::IMGURL.$img;
                    }
                }
                //分类树名称
                $result['result'][$k]['catalog_id'] = $this->productClassid($product['CategoryPath']);
                $result['result'][$k]['base_attrs'] =  $result['result'][$k]['skus'] = array();
                if(!empty($product['Skus'])){
                    $skus = array();
                    foreach($product['Skus'] as $skey => $sku){
                        $skus[$skey]['sku'] = $sku['Code'];
                        $skus[$skey]['img_url'] = !empty($sku['Image']) ? 'http://img.dxcdn.com'.$sku['Image'] : '';
                        $skus[$skey]['sale_attrs'] = '';
                        if(!empty($sku['SalesAttrs'])){
                            $sale_attrs = array();
                            foreach($sku['SalesAttrs'] as $akey => $attr){
                                $attrVal = '';
                                if(!empty($attr['CustomValue'])){
                                    $attrVal = $attr['CustomValue'];
                                }elseif(!empty($attr['DefaultValue'])){
                                    $attrVal = $attr['DefaultValue'];
                                }elseif(!empty($attr['Value'])){
                                    $attrVal = $attr['Value'];
                                }
                                $sale_attrs[$akey] = $attr['Name'].':'.$attrVal;
                            }
                            $skus[$skey]['sale_attrs'] = implode('$',$sale_attrs);
                        }
                    }
                    $result['result'][$k]['skus'] = $skus;
                }

                $result['result'][$k]['description'] = isset($product['Descriptions']) ? strip_tags($product['Descriptions']) : '';
            }
        }
        return $result;
    }

    /**
     * @param $categoryPath
     * @param $lang
     * @return array
     */
    private function productClassData($categoryPath,$lang){
        $classData = array();
        //分类信息
        $classArray = explode('-',$categoryPath);
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
                $classArray = explode('-',$categoryPath);
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
            $classArray = explode('-',$categoryPath);
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

    /**
     * @param $categoryPath
     * @return array
     */
    private function productClassid($categoryPath){
        //分类信息
        $classArray = explode('-',$categoryPath);
        $classid = end($classArray);
        $classInfo = (new ProductClassModel())->getClassDetail(['id'=>(int)$classid]);
        if($classInfo['type'] == 1){
            //ERP类别数据
            return !empty($classInfo['erp_id']) ? $classInfo['erp_id'] : $classid;
        }
        if( isset($classInfo['pdc_ids']) && !empty($classInfo['pdc_ids'])){
            $data = (new ProductClassModel())->getClassDetail(['id'=>(int)$classInfo['pdc_ids'][0]]);
            if(!empty($data)){
                $id_path = explode('-',$data['id_path']);
                return !empty($data['erp_id']) ? $data['erp_id'] : end($id_path);
            }else{
                return $classid;
            }
        }
        return $classid;
    }


    /**
     * 产品查询 --远超
     * @param $params
     * @return mixed
     */
    public function productListSpecial($params){
        //语种切换
        $lang = isset($params['language']) ? $params['language'] : self::DEFAULT_LANG;
        $pageSize = isset($params['pageSize']) ? (int)$params['pageSize'] > 50 ? 50 : (int)$params['pageSize'] : 50;
        $pageIndex = isset($params['pageIndex']) ? (int)$params['pageIndex'] : 1;

        //查询产品
        $data = (new ProductModel())->paginateProduct($params);
        $result['pageSize'] = $pageSize;
        $result['pageIndex'] = $pageIndex;
        $result['totalPage'] = !empty($data['last_page']) ? $data['last_page'] : 0;
        $result['result'] = [];
        if(!empty($data['data'])){
            foreach($data['data'] as $k => $product){
                //产品id
                $result['result'][$k]['spu'] = isset($product['_id']) ? $product['_id'] : '';
                if(!empty($product['Skus'])){
                    $skus = array();
                    foreach($product['Skus'] as $skey => $sku){
                        $skus[$skey]['sku'] = $sku['Code'];
                        $skus[$skey]['price'] = (string)$sku['SalesPrice'];
                    }
                    $result['result'][$k]['skus'] = $skus;
                }
                $result['result'][$k]['status'] = isset($product['ProductStatus']) ? $product['ProductStatus'] : 0;
                //链接地址
                $rewrittenUrl = isset($product['RewrittenUrl']) ? $product['RewrittenUrl'] : '';
                $result['result'][$k]['product_url'] = MALL_DOCUMENT .'p/'.$rewrittenUrl.'-'.$product['_id'];//链接地址
                $result['result'][$k]['add_time'] = isset($product['AddTime']) ? $product['AddTime'] : 0;
            }
        }
        return $result;
    }
    
}
