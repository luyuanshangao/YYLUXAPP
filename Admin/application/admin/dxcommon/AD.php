<?php
namespace app\admin\dxcommon;

use app\admin\model\AdvertisementManage;
use think\Controller;
use think\Log;

/**
 * 广告管理通用处理类
 * @author tinghu.liu
 * @date 2018-04-12
 * @package app\admin\dxCommon
 */
class AD extends Controller
{
    /**
     * 获取广告站点数据配置
     * @return mixed
     */
    public static function getSiteInfo($flag=1){
        $model = new AdvertisementManage();
        if ($flag == 1){
            return $model->getSiteData()[0];
        }else{
            return $model->getSiteData();
        }
    }

    /**
     * 获取广告页面配置数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getPagesData(array $where=[]){
        $model = new AdvertisementManage();
        return $model->getPageData($where);
    }

    /**
     * 获取广告内容类型
     * @param array $where 获取条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getADContentType(array $where=[]){
        $model = new AdvertisementManage();
        return $model->getContentTypeData($where);
    }

    /**
     * 判断增加广告数据格式
     * @param array $data 要增加的数据
     * @return bool
     */
    public static function judgeActivityParam(array $data){
        $rtn = false;
        $that = new self();
        /** 广告类型(1-Banner,2-Text,3-SKU_AD) **/
        $content_type = (int)$data['ContentTypeID'];
        switch ($content_type){
            case 1://1-Banner
                $banner_data = $data['Banners']['BannerImages']['BannerFonts'][0];
                //判断图片地址
                $judge_flag = true;
                foreach ($banner_data['ImageUrl'] as $img){
                    $img_arr['img_url'] = $img;
                    $validate = $that->validate($img_arr, [['img_url','require|url','不为空或者不是url']]);
                    if (true !== $validate){
                        $judge_flag = false;
                    }
                    Log::record("banner图片校验：".$validate);
                }
                //判断链接
                /*$validate = $that->validate($banner_data, [['LinkUrl','require|url','不为空或者不是url']]);
                if (true !== $validate){
                    $judge_flag = false;
                }
                Log::record("banner链接校验：".$validate);*/
                foreach ($banner_data['LinkUrl'] as $img_url){
                    $img_link_arr['img_link_url'] = $img_url;
                    $validate = $that->validate($img_link_arr, [['img_link_url','require|url','不为空或者不是url']]);
                    if (true !== $validate){
                        $judge_flag = false;
                    }
                    Log::record("banner链接校验：".$validate);
                }
                if (
                    !empty($banner_data['Language'])
                    && !empty($banner_data['LinkUrl'])
                    && $judge_flag
                )
                {
                    $rtn = true;
                }
                break;
            case 2://2-Text
                $text_data = $data['Keyworks']['TextData'][0];
                $validate = $that->validate($text_data, [
                    ['Language','require','Language为非空字段'],
                    ['Value','require','Value为非空字段'],
                ]);
                if (true === $validate){
                    $rtn = true;
                }
                Log::record("Text校验：".$validate);
                break;
            case 3://3-SKU_AD
                $sku_data = $data['SKUs']['SKUData'][0];
                $validate = $that->validate($sku_data, [
                    ['Language','require','Language为非空字段'],
                    ['SKU','require','SKU为非空字段'],
                    ['LinkUrl','require|url','LinkUrl为非空字段且为URL'],
                ]);
                if (true === $validate){
                    $rtn = true;
                }
                Log::record("SKU_AD校验：".$validate);
                break;
        }
        return $rtn;
    }




}
