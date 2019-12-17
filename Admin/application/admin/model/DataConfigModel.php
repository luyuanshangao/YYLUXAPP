<?php
namespace app\admin\model;

use think\Db;
use think\Log;
use think\Model;

/**
 * 商城业务数据配置-dx_data_config集合的MODEL
 * @author heng zhang
 * @version v1.0
 * @copyright
 */
class DataConfigModel extends Model
{
    protected $db;
    //表名称
    protected $tableName = 'dx_data_config';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect("db_mongo");
    }

    /**
     * 获取信息【分页】
     * @param int $page_size 分页大小
     * @return $this
     */
    public function getDataConfigPaginate($where=[], $page_size){
    	if(empty($page_size) || $page_size==0){
    		$page_size = config('paginate.list_rows');
    	}
    	$retsult= $this->db->table($this->tableName)
	    				 ->where($where)
                         ->order('updateTime','desc')
	    				 ->paginate($page_size);
    	return $retsult;
    }

    /**
     * 查询数据--通过_id查询单条数据
     * @param array $where
     * @return
     */
    public function getDataConfigByID($id){
        $result = $this->db->table($this->tableName)->where(['_id'=>(int)$id])->find();
        return $result;
    }

    /**
     * 查询数据--通过_id查询单条数据
     * @param array $where
     * @return 返回SKUS节点下的数据
     */
    public function getDataConfigSPUSByID($id){
    	$result = $this->db->table($this->tableName)->where(['_id'=>(int)$id])->column('spus');
    	return $result;
    }

    /**
     * 查询数据--通过key统计数据条数
     * @param string $key
     * @return 返回数据条数
     */
    public function countDataConfigByKey($key){
    	$result = $this->db->table($this->tableName)->where(['key'=>$key])->count();
    	return $result;
    }

    /**
     * 插入数据--更新指定ID的数据（spus节点）
     * @param unknown $id
     */
    public function updateDataConfigByID($id,$data){
    	$result = $this->db->table($this->tableName)->where(['_id'=>(int)$id])->update(['spus'=>(array)$data,'updateTime'=>time()]);
    	return $result;
    }

    /**
     * 新增广告页面区域数据
     * @param array $data
     * @return bool|int|string
     */
    public function insertDataConfig(array $data){
    	if(!empty($data['key'])){
    		//var_dump(trim($data['key']));
    		if($this->countDataConfigByKey(trim($data['key'])) >0){
    			//KEY重复
    			return 10;
    		}
    	}
        /**
         * 获取自增ID
         */
        $autoIncrement = new AutoIncrement();
		$model = $autoIncrement->getInfo();
		$maxId = 0;
		if(!empty($model)){
			$maxId = (int)$model['DataConfiId'];
		}
		$data['_id'] = $maxId+1;
		$data['key'] = trim($data['key']);
		$data['addTime'] =time();
        /**
         * 更新自增ID
         */
        if (!$autoIncrement->updateDataByWhere(['DataConfiId'=>$maxId], ['DataConfiId'=>$maxId+1])){
            return false;
        }
        $result= $this->db->table($this->tableName)->insert($data);
        return $result;
    }

   /**
     * 删除数据--由ID
     * @return
     */
   public function deleteDataConfigByID($id){
    	$result = $this->db->table($this->tableName)->where(['_id'=>(int)$id])->delete();
    	return $result;
    }

    /**
     * 删除指定节点数据--由ID
     * @return
     */
    public function deleteSonDom($id){
    	$result = $this->db->table($this->tableName)->where(['_id'=>(int)$id,'spus.spu'=>"1138"])->delete();
    	return $result;
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






}