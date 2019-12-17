<?php
namespace app\admin\controller;

use app\admin\model\EDMActivityModel;
use app\common\helpers\CommonLib;
use think\Exception;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use think\Log;

class EDMActivity extends Controller
{
    protected $activityModel;
	public function _initialize(){
        $this->activityModel = new EDMActivityModel();
    }

    /**
     * @param $title
     * @param $country
     * @return int
     * 生成活动页面源码
     */
    public function viewSource($title,$country='US'){
        try {
            if(empty($title)){
                return '';
            }
            $banner2nd = $content = $banner = $gifspu = $order_spu_string = $recommend_spu_string ='';
            $spuText = $product = $spus = $otherSpu = $recommend_products = $other_products = $spulist = $bannerProductList = $bannerItem= array();
            $key = 'category';
            $path = ROOT_PATH . DS . 'public' . DS .'template' . DS . 'template.html';
            $templateType = 'landing_pg';

            $img_url = 'https://admin.dx.com/uploads/edm/';
            $activity = $this->activityModel->getEdmActivity(['UrlSnippet' => $title]);
            if(empty($activity)){
                $activity = $this->activityModel->getEdmActivity(['ActivityTitle' => $title]);
                if(empty($activity)){
                    return '';
                }
            }

            if (!empty($activity['TemplateID'])) {
                $template = $this->activityModel->getEdmActivityTemplate($activity['TemplateID']);
                $templateType = !empty($template['TemplateType']) ? $template['TemplateType'] : 'landing_pg';
                if (!empty($template['Content'])) {
                    //html模板
                    $content = $template['Content'];
                }else{
                    Log::record('TemplateID is not sync:'.$activity['TemplateID'],'error');
                    return '';
                }
            }
            $page_title = !empty($activity['PageTitle']) ? $activity['PageTitle'] : '';
            //GA跟踪代码
            $ga_code = !empty($activity['GACode']) ? $activity['GACode'] : '';
            //链接地址
            $bannerurl = !empty($activity['BannerUrl']) ? $activity['BannerUrl'] : '';
            //banner图片地址
            if (!empty($activity['Banner'])) {
                if(strstr($activity['Banner'],'http://') !== false || strstr($activity['Banner'],'https://') !== false){
                    $banner = $activity['Banner'];
                }else{
                    $banner = $img_url . str_replace('\\','/',$activity['Banner']);
                }
            }
            //banner2图片地址
            if (!empty($activity['Banner2nd'])) {
                if(strstr($activity['Banner2nd'],'http://') !== false || strstr($activity['Banner2nd'],'https://') !== false){
                    $banner2nd = $activity['Banner2nd'];
                }else{
                    $banner2nd = $img_url . str_replace('\\','/',$activity['Banner2nd']);
                }
            }
            $bannerArray = !empty($activity['Banners']) ? json_decode($activity['Banners'], true) : array();
            $bannerT = isset($bannerArray[0]) ? $bannerArray[0] : array();
            $topbg = 'http://c.dx.com/edm/201802/20180228/images/edmBanner.jpg';
            if(!empty($bannerT['ImageUrl'])) {
                if (strstr($bannerT['ImageUrl'], 'http://') !== false || strstr($bannerT['ImageUrl'], 'https://') !== false) {
                    $topbg = str_replace('\\','/',$bannerT['ImageUrl']);
                }else{
                    $topbg = $img_url. str_replace('\\','/',$bannerT['ImageUrl']);
                }
            }
            if(!empty($bannerT['SPUList'])){
                $spulist = explode('+',$bannerT['SPUList']);
                //只要2个，规则规定要spu+赠送spu，如果不是，那么这个模板展示就会有问题
                if(count($spulist) != 2){
                    $spulist = array();
                }
            }
            //banner 轮播图，需要点击的时候要跳转产品链接，标题信息，所以需要获取产品信息
            if(!empty($bannerArray)){
                foreach($bannerArray as $val){
                    if(!empty($val['SPUList'])){
		    	//每个banner，只取一个产品就可以了，原来他们就是这样的逻辑
                        $bannerProductArr[] =  explode('+',$val['SPUList'])[0];
                    }
                }
            }

            //产品主推的spu列表
            if(!empty($activity['SPUText'])){
                if($activity['IsHistory'] == 1){
                    $spuText = explode("\r\n", trim($activity['SPUText']));
                }else{
                    $spuText = explode("\n", trim($activity['SPUText']));
                }
            }
            //其他产品 + 类别信息
            if(!empty($activity['OtherSPUText'])){
                if($activity['IsHistory'] == 1){
                    $otherSpuText = explode("\r\n", trim($activity['OtherSPUText']));
                }else{
                    $otherSpuText = explode("\n", trim($activity['OtherSPUText']));
                }
                foreach ($otherSpuText as $val) {
                    //第一个肯定是类别，规则规定
                    if (!preg_match("/^\d*$/", $val)) {
                        $key = $val;
                    } else {
                        $otherSpu[$key][] = $val;
                        $spus[] = $val;
                    }
                }
            }
	        //给前端页面传值，异步加载价格数据
            $order_spu_string = implode(',',$spus);
            $spus = array_merge($spus, $spuText);
            //banner 推荐产品，spu+赠品活动显示，而且只有一组显示
            if(!empty($spulist)){
	    	    $gifspu = implode(',',$spulist);
                $spus = array_merge($spus, $spulist);
            }
            //banner 推荐产品
            if(!empty($bannerProductArr)){
                $spus = array_merge($spus, $bannerProductArr);
            }
            $products = controller('mall/product')->getEdmActivityProductListBySpus([
                'spus' => implode(',', $spus),
                'lang' => !empty($activity['LangCode']) ? $activity['LangCode'] : 'en',
                'currency' => !empty($activity['CurrencyCode']) ? $activity['CurrencyCode'] : 'USD',
                'country' => $country,
                'templateType'=>$templateType
            ]);

            if(!is_array($products) && !empty($products)){
                $products = json_decode($products,true);
            }

            //调用产品接口
//            $products = accessTokenToCurl(config("api_base_url") . "mall/product/getEdmActivityProductListBySpus", null, [
//                'spus' => implode(',', $spus),
//                'lang' => !empty($activity['LangCode']) ? $activity['LangCode'] : 'en',
//                'currency' => !empty($activity['CurrencyCode']) ? $activity['CurrencyCode'] : 'USD',
//                'country' => $country
//            ], true);

            if (isset($products['code']) && $products['code'] == 200 && !empty($products['data'])) {
                $this->assign('currencyCode', $products['data']['currencyCode']);
                $this->assign('currency_flag', $products['data']['currencyCodeSymbol']);
                unset($products['data']['currencyCode'], $products['data']['currencyCodeSymbol']);

                $product = $products['data'];
                // 查询，组装数据
                $search_ids = array_column($product, 'id');
            }
            if (empty($product)) {
                return '';
            }
            //banner 推荐产品 (spu+spu(价格为0))
            if (!empty($spulist)) {
                foreach ($spulist as $key => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (isset($product[$index]) && $index !== false) {
                        $bannerProductList[$key] = $product[$index];
                    }
                }
                if(count($bannerProductList) == 2){
                    $this->assign('bannerProductList',$bannerProductList);
                }
            }

            //主推产品
            if (!empty($spuText)) {
	    	    $recommend_spu_string = implode(',',$spuText);
                foreach ($spuText as $key => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (!empty($product[$index]) && $index !== false) {
                        $recommend_products[$key] = $product[$index];
                    }
                }
            }

            //banner 轮播图，产品路径跳转
            if (!empty($bannerProductArr)) {
                foreach ($bannerProductArr as $key => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (!empty($product[$index]) && $index !== false) {
                        $findData = CommonLib::filterArrayByKey($bannerArray,'SPUList',$product[$index]['id']);
                        $bannerItem[$key] = $product[$index];
                        $bannerItem[$key]['bannerUrl'] = '';
                        if(!empty($findData['ImageUrl'])) {
                            if (strstr($findData['ImageUrl'], 'http://') !== false || strstr($findData['ImageUrl'], 'https://') !== false) {
                                $bannerItem[$key]['bannerUrl'] = str_replace('\\','/',$findData['ImageUrl']);
                            }else{
                                $bannerItem[$key]['bannerUrl'] = $img_url. str_replace('\\','/',$findData['ImageUrl']);
                            }
                        }
                    }
                }
            }

            //其他产品列表
            foreach ($otherSpu as $category => $products) {
                foreach ($products as $pkey => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (isset($product[$index]) && $index !== false) {
                        $other_products[$category][$pkey] = $product[$index];
                    }
                }
                //将数组分割3个为一个数组
                if (!empty($other_products[$category])) {
                    array_values($other_products[$category]);
//                $other_products[$category] = array_chunk($other_products[$category],3);
                }
            }

            $this->assign('lang', $activity['LangCode']);
            $this->assign('currency', !empty($activity['CurrencyCode']) ? $activity['CurrencyCode'] : 'USD');
            $this->assign('recommend_spu_string', $recommend_spu_string);
            $this->assign('gifspu', $gifspu);
            //判断是LP还是EDM
            if(!empty($template['TemplateType']) && ($template['TemplateType'] == 'landing_pg' || $template['TemplateType'] == 'm_landing_pg')){
                $this->assign('order_spu_string', json_encode(array_values($otherSpu)));
            }else{
                $this->assign('order_spu_string', $order_spu_string);
            }
            $this->assign('page_title', $page_title);
            $this->assign('banner2nd',$banner2nd);
            $this->assign('topbg', $topbg);
            $this->assign('bannerT', $bannerT);
            $this->assign('banner', $banner);
            $this->assign('ga_code', $ga_code);
            $this->assign('recommend_products', $recommend_products);
            $this->assign('products', $other_products);
            $this->assign('bannerurl', $bannerurl);
            $this->assign('video_products', array());
            $this->assign('bannerItem',$bannerItem);

            //写入内容到模板
            if (!empty($content)) {
                $fp = fopen($path, 'w+');
                fwrite($fp, $content);
                fclose($fp);
            }
            return $this->fetch($path);
//        $contents = ob_get_contents();
//        $ret = $model->updateEdmActivity($id,['PreviewHtml'=>htmlspecialchars_decode($contents)]);
//        return $ret;
        }catch (Exception $e){
            Log::record('viewSource error:'.$e->getMessage().' line:'.$e->getLine(),'error');
            return '';
        }
    }
}