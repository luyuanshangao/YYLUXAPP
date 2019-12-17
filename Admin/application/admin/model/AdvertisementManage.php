<?php
namespace app\admin\model;

use think\Db;
use think\Log;
use think\Model;
/**
 * 广告管理数据模型
 * Created by tinghu.liu
 * Date: 2018/4/12
 * @package app\admin\model
 * 备注：
 * dx_ad_sites表、dx_ad_content_type表的数据固定在数据库，ID等最好不要变化。
 * dx_ad_sites表数据：
{
    "_id" : ObjectId("5acf1105260b6ea876828b72"),
    "CreateBy" : "admin",
    "CreateTime" : 1523519859,
    "UpdateBy" : null,
    "UpdateTime" : 1523519859,
    "SiteID" : 1,
    "SiteName" : "DX"
}
 * dx_ad_content_type表数据：
{
    "_id" : ObjectId("5acf11aa260b6ea876828c75"),
    "CreateBy" : "admin",
    "CreateTime" : 1523519962,
    "UpdateBy" : null,
    "UpdateTime" : 1523519962,
    "ContentTypeID" : 1,
    "ContentTypeName" : "Banner"
}

{
    "_id" : ObjectId("5acf11aa260b6ea876828c77"),
    "CreateBy" : "admin",
    "CreateTime" : 1523519962,
    "UpdateBy" : null,
    "UpdateTime" : 1523519962,
    "ContentTypeID" : 2,
    "ContentTypeName" : "Text"
}

{
    "_id" : ObjectId("5acf11aa260b6ea876828c79"),
    "CreateBy" : "admin",
    "CreateTime" : 1523519962,
    "UpdateBy" : null,
    "UpdateTime" : 1523519962,
    "ContentTypeID" : 3,
    "ContentTypeName" : "SKU_AD"
}
 *
 *
 */
class AdvertisementManage  extends Model
{
    /**
     * 广告管理相关数据设置
     * @var string
     */
    protected $db;
    protected $table = 'dx_ad_activity';/** 广告主表 **/
    protected $table_type = 'dx_ad_content_type';/** 广告类型表 **/
    protected $table_site = 'dx_ad_sites';/** 站点配置表 **/
    protected $table_page = 'dx_ad_pages';/** 站点页面配置表 **/
    protected $table_region = 'dx_ad_areas';/** 站点页面区域配置表 **/
    protected $table_region_extension = 'dx_ad_areas_layout';/** 站点页面区域编号配置表 **/

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect("db_mongo");
    }

    /**
     * 获取广告站点数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getSiteData(array $where=[]){
        if (!empty($where)){
            return $this->db->table($this->table_site)->where($where)->select();
        }else{
            return $this->db->table($this->table_site)->select();
        }
    }

    /**
     * 添加广告页面数据
     * @param array $data 要添加的数据
     * [
     * "_id"=>1,
     * "CreateBy"=>"admin",
     * "CreateTime"=>1523519962,
     * "UpdateBy"=>"admin",
     * "UpdateTime"=>1523519992,
     * "PageID"=>1,
     * "SiteID"=>1,
     * "PageName"=>"Home",
     * "Domain"=>"www.dx.com"
     * ]
     *
     * @return int|string
     */
    public function insertPagesData(array $data){
        /**
         * 获取自增ID
         */
        $auto_increment_model = new AutoIncrement();
        $auto_info = $auto_increment_model->getInfo();
        $page_id = (int)$auto_info['AdvertisementPagesId'];
        $page_auto_id = $page_id + 1;
        //拼装新增数据
        $data['_id'] = $page_auto_id;
        $data['PageID'] = $page_auto_id;
        /**
         * 更新自增ID
         */
        if (!$auto_increment_model->updateDataByWhere(['AdvertisementPagesId'=>$page_id], ['AdvertisementPagesId'=>$page_auto_id])){
            return false;
        }
        return $this->db->table($this->table_page)->insert($data);
    }

    /**
     * 更新广告页面数据
     * @param array $where 更新条件
     * @param array $update 更新的数据
     * @return int|string
     */
    public function updatePagesData(array $where, array $update){
        return $this->db->table($this->table_page)->where($where)->update($update);
    }

    /**
     * 获取广告页面数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getPageData(array $where=[]){
        if (!empty($where)){
            return $this->db->table($this->table_page)->where($where)->select();
        }else{
            return $this->db->table($this->table_page)->select();
        }
    }

    /**
     * 获取页面分页数据
     * @param int $page_size
     * @return \think\Paginator
     */
    public function getPageDataPaginate($page_size=10){
        return $this->db->table($this->table_page)->paginate($page_size);
    }

    /**
     * 获取区域配置数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRegionData(array $where=[]){
        if (!empty($where)){
            return $this->db->table($this->table_region)->where($where)->select();
        }else{
            return $this->db->table($this->table_region)->select();
        }
    }

    /**
     * 新增广告页面区域数据
     * @param array $data
     * @return bool|int|string
     */
    public function insertRegionData(array $data){
        /**
         * 获取自增ID
         */
        $auto_increment_model = new AutoIncrement();
        $auto_info = $auto_increment_model->getInfo();
        $_id = (int)$auto_info['AdvertisementAreaId'];
        $auto_id = $_id + 1;
        //拼装新增数据
        $data['_id'] = $auto_id;
        $data['AreaID'] = $auto_id;
        /**
         * 更新自增ID
         */
        if (!$auto_increment_model->updateDataByWhere(['AdvertisementAreaId'=>$_id], ['AdvertisementAreaId'=>$auto_id])){
            return false;
        }
        return $this->db->table($this->table_region)->insert($data);
    }

    /**
     * 获取页面区域分页数据
     * @param array $where 更新条件
     * @param array $update 更新的数据
     * @return int|string
     */
    public function updateRegionData(array $where, array $update){
        return $this->db->table($this->table_region)->where($where)->update($update);
    }

    /**
     * 获取页面区域分页数据【分页】
     * @param int $page_size
     * @return \think\Paginator
     */
    public function getRegionDataPaginate($page_size=10){
        return $this->db->table($this->table_region)->paginate($page_size)->each(function($item, $key){
            $item['SiteName'] = $this->getSiteData(['SiteID'=>$item['SiteID']])[0]['SiteName'];
            $item['PageName'] = $this->getPageData(['_id'=>$item['PageID']])[0]['PageName'];
            return $item;
        });
    }

    /**
     * 获取广告配置类型
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getContentTypeData(array $where=[]){
        if (!empty($where)){
            return $this->db->table($this->table_type)->where($where)->select();
        }else{
            return $this->db->table($this->table_type)->select();
        }
    }

    /**
     * @param $data
     * {
    "_id" : ObjectId("59f3f8998e6cbe1e24cc9bbd"),
    "CreateBy" : "chenjp",
    "CreateTime" : ISODate("2017-10-28T03:25:13.906Z"),
    "UpdateBy" : null,
    "UpdateTime" : Date(-62135596800000),
     *
    "AreasLayoutID" : 1,
     *
    "AreasLayoutName" : "1-1",
    "SiteID" : 1,
    "PageID" : 1,
    "AreaID" : 1,
    "ContentTypeID" : 1,
    "IsMoreImage" : false
    }
     * @return bool|int|string
     */
    public function insertRegionLayoutData($data){
        /**
         * 获取自增ID
         */
        $auto_increment_model = new AutoIncrement();
        $auto_info = $auto_increment_model->getInfo();
        $_id = (int)$auto_info['AdvertisementAreaLayoutId'];
        $auto_id = $_id + 1;
        //拼装新增数据
        $data['_id'] = $auto_id;
        $data['AreasLayoutID'] = $auto_id;
        /**
         * 更新自增ID
         */
        if (!$auto_increment_model->updateDataByWhere(['AdvertisementAreaLayoutId'=>$_id], ['AdvertisementAreaLayoutId'=>$auto_id])){
            return false;
        }
        return $this->db->table($this->table_region_extension)->insert($data);
    }

    /**
     * 获取页面区域编码配置数据【分页】
     * @param int $page_size
     * @return $this
     */
    public function getRegionLayoutDataPaginate($page_size=10){
        return $this->db->table($this->table_region_extension)->paginate($page_size)->each(function($item, $key){
            $item['SiteName'] = $this->getSiteData(['SiteID'=>$item['SiteID']])[0]['SiteName'];
            $item['PageName'] = $this->getPageData(['_id'=>$item['PageID']])[0]['PageName'];
            $item['AreaName'] = $this->getRegionData(['_id'=>$item['AreaID']])[0]['AreaName'];
            $item['ContentTypeName'] = $this->getContentTypeData(['ContentTypeID'=>$item['ContentTypeID']])[0]['ContentTypeName'];
            if ($item['IsMoreImage']){
                $item['IsMoreImageStr'] = '是';
            }else{
                $item['IsMoreImageStr'] = '否';
            }
            return $item;
        });
    }

    /**
     * 获取区域编码信息
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRegionLayoutData(array $where){
        if (!empty($where)){
            return $this->db->table($this->table_region_extension)->where($where)->select();
        }else{
            return $this->db->table($this->table_region_extension)->select();
        }
    }

    /**
     * 更新区域编码信息
     * @param array $where
     * @param array $update
     * @return int|string
     */
    public function updateRegionLayoutData(array $where, array $update){
        return $this->db->table($this->table_region_extension)->where($where)->update($update);
    }

    /**
     * 增加广告信息
     * @param $data 要增加的数据
     * @return bool|int|string
     */
    public function insertActivityData($data){
        //指定ID类型为int
        $data['SiteID'] = (int)$data['SiteID'];
        $data['PageID'] = (int)$data['PageID'];
        $data['AreaID'] = (int)$data['AreaID'];
        $data['AreasLayoutID'] = (int)$data['AreasLayoutID'];
        $data['ContentTypeID'] = (int)$data['ContentTypeID'];
        /**
         * 获取自增ID
         */
        $auto_increment_model = new AutoIncrement();
        $auto_info = $auto_increment_model->getInfo();
        $_id = (int)$auto_info['AdvertisementActivityId'];
        $auto_id = $_id + 1;
        //拼装新增数据
        $data['_id'] = $auto_id;
        $data['ActivityID'] = $auto_id;
        /**
         * 更新自增ID
         */
        if (!$auto_increment_model->updateDataByWhere(['AdvertisementActivityId'=>$_id], ['AdvertisementActivityId'=>$auto_id])){
            return false;
        }
        return $this->db->table($this->table)->insert($data);
    }

    /**
     * 更新广告信息
     * @param array $where
     * @param array $update
     * @return int|string
     */
    public function updateActivityData(array $where, array $update){
        return $this->db->table($this->table)->where($where)->update($update);
    }

    /**
     * 获取广告信息【分页】
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getActivityDataPaginate($where=[], $page_size=10){
        return $this->db->table($this->table)->where($where)->paginate($page_size)->each(function($item, $key){
            $item['SiteName'] = $this->getSiteData(['SiteID'=>$item['SiteID']])[0]['SiteName'];
            $item['PageName'] = $this->getPageData(['_id'=>$item['PageID']])[0]['PageName'];
            $item['AreaName'] = $this->getRegionData(['_id'=>$item['AreaID']])[0]['AreaName'];
            $item['ContentTypeName'] = $this->getContentTypeData(['ContentTypeID'=>$item['ContentTypeID']])[0]['ContentTypeName'];
            $layout_info = $this->getRegionLayoutData(['_id'=>$item['AreasLayoutID']])[0];
            $item['AreasLayoutName'] = $layout_info['AreasLayoutName'];
            if ($layout_info['IsMoreImage']){
                $item['IsMoreImageStr'] = '是';
            }else{
                $item['IsMoreImageStr'] = '否';
            }
            return $item;
        });
    }

    /**
     * 获取广告数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getActivityData($where=[]){
        return $this->db->table($this->table)->where($where)->select();
    }




}