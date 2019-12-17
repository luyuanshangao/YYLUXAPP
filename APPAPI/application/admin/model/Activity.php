<?php
namespace app\admin\model;

use think\Log;
use think\Model;
use think\Db;
/**
 * 活动模型
 * @author
 * @version tinghu.liu 2018/4/19
 */
class Activity extends Model
{
    private  $db = '';
    protected $table = 'dx_activity';
    protected $table_enroll = 'dx_activity_enroll';
    protected $table_sku = 'dx_activity_sku';
    protected $table_spu = 'dx_activity_spu';
    private $get_activity_data_seller_id;
    private $get_activity_sku_data_for_list_params;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }

    /**
     * 获取活动列表【平台活动报名】
     * @param array $params
     * @return array
     * @throws \think\exception\DbException
     */
    public function getActivityDataTabOne(array $params){
        $query = $this->db->table($this->table);
        //活动状态：1为上线，2为下线,3为删除
        $query->where('status','=','1');
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;

        $param_time = $params['time'];
        $seller_id = $this->get_activity_data_seller_id = $params['seller_id'];
        //活动类型:1专题活动;2定期活动;3节日活动4促销活动;
        $activity_type = $params['activity_type'];
        if (!empty($activity_type)){
            $query->where(['type'=>$activity_type]);
        }
        //活动名称
        if (isset($params['activity_title']) && !empty($params['activity_title'])){
            $query->where('activity_title', 'like', '%'.$params['activity_title'].'%');
        }
        //活动状态（只有在tab_type == 1时才有）：1-可参加活动（去掉已参加的活动），2-全部（所有的数据，包含已参加）
        $activity_status = $params['activity_status'];
        //可参加活动（去掉已参加的活动）
        if (!empty($activity_status) && $activity_status == 1){
            //去掉已经报名的数据
            //$where['ae.seller_id'] = array('<>', $seller_id);
            //$query->where($where);
            //$whereOr['ae.enroll_id'] = array('exp', 'IS NULL');
            //$query->where($where)->whereOr($whereOr);
            $time = time();
            /*$query->where(function ($query) {
                $query->where('ae.seller_id', '<>', $this->get_activity_data_seller_id)
                    ->whereOr('ae.seller_id', 'null')
                    ->whereOr(function ($query) {
                        $query->where('ae.seller_id', '=', $this->get_activity_data_seller_id)->where('ae.status','=', 2);
                    });
            });*/
            $query->where('registration_start_time','<',$time);
            $query->where('registration_end_time','>',$time);

        }

        $response = $query->field('id,type,activity_title,registration_start_time,registration_end_time,activity_img,activity_start_time,activity_end_time,status,description,range,className')
            ->order(['add_time'=>'desc'])
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])->each(function ($itme, $key) use ($param_time){
                //根据时间判断活动状态
                $activity_status = 0;
                if (
                    $itme['registration_start_time'] < $param_time
                    && $itme['registration_end_time'] > $param_time
                ){
                    //报名中
                    $activity_status = 1;
                }else if (
                    $itme['activity_start_time'] < $param_time
                    && $itme['activity_end_time'] > $param_time
                ){
                    //活动中
                    $activity_status = 2;
                }else if (
                    $itme['activity_end_time'] < $param_time
                ){
                    //已结束
                    $activity_status = 3;
                }
                $itme['activity_status'] = $activity_status;

                //判断seller是否参加了活动
                $is_join_activity = 0;
                $data = $this->db->table($this->table_enroll)
                    ->where([
                        'seller_id'=>$this->get_activity_data_seller_id,
                        'activity_id'=>$itme['id'],
                        'status'=>1, //1-已报名，2-退出活动
                    ])->select();
                if (!empty($data)){
                    $is_join_activity = 1;
                }

                $itme['is_join_activity'] = $is_join_activity;
                return $itme;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取活动列表
     * @param array $params 条件参数
     * @return array
     */
    public function getActivityData(array $params){
        $query = $this->db->table($this->table)->alias('a');
        $join = [
            ['dx_activity_enroll ae','a.id=ae.activity_id','LEFT'],
        ];
        $query->join($join);
        //活动状态：1为上线，2为下线,3为删除
        $query->where('a.status','=','1');
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        //获取数据类型：1-平台活动报名（全部活动数据），2-待确认（seller报名信息），3-参与中（seller报名信息），4-已结束（seller报名信息）
        $tab_type = $params['tab_type'];
        $param_time = $params['time'];
        $seller_id = $this->get_activity_data_seller_id = $params['seller_id'];
        //活动类型:1专题活动;2定期活动;3节日活动4促销活动;
        $activity_type = $params['activity_type'];
        if (!empty($activity_type)){
            $query->where(['type'=>$activity_type]);
        }
        //活动名称
        if (isset($params['activity_title']) && !empty($params['activity_title'])){
            $query->where('a.activity_title', 'like', '%'.$params['activity_title'].'%');
        }
        //活动状态（只有在tab_type == 1时才有）：1-可参加活动（去掉已参加的活动），2-全部（所有的数据，包含已参加）
        $activity_status = $params['activity_status'];
        switch ($tab_type){
            case 1://平台活动报名（全部活动数据）
                //可参加活动（去掉已参加的活动）
                if (!empty($activity_status) && $activity_status == 1){
                    //去掉已经报名的数据
                    //$where['ae.seller_id'] = array('<>', $seller_id);
                    //$query->where($where);
                    //$whereOr['ae.enroll_id'] = array('exp', 'IS NULL');
                    //$query->where($where)->whereOr($whereOr);
                    $time = time();
                    $query->where(function ($query) {
                        $query->where('ae.seller_id', '<>', $this->get_activity_data_seller_id)
                            ->whereOr('ae.seller_id', 'null')
                            ->whereOr(function ($query) {
                                $query->where('ae.seller_id', '=', $this->get_activity_data_seller_id)->where('ae.status','=', 2);
                            });
                    });
                    $query->where('a.registration_start_time','<',$time);
                    $query->where('a.registration_end_time','>',$time);

                }
                break;
            case 2://待确认（seller报名信息）在报名时间内
                $query->where('a.registration_start_time','<',$param_time);
                $query->where('a.registration_end_time','>',$param_time);
                $query->where('ae.seller_id','=',$seller_id);
                //报名状态：1-已报名，2-退出活动
                $query->where('ae.status','=',1);
                break;
            case 3://参与中（seller报名信息）在活动时间内
                $query->where('a.activity_start_time','<',$param_time);
                $query->where('a.activity_end_time','>',$param_time);
                $query->where('ae.seller_id','=',$seller_id);
                //报名状态：1-已报名，2-退出活动
                $query->where('ae.status','=',1);
                break;
            case 4://已结束（seller报名信息）已结束
                $query->where('a.activity_end_time','<',$param_time);
                $query->where('ae.seller_id','=',$seller_id);
                //报名状态：1-已报名，2-退出活动
                $query->where('ae.status','=',1);
                break;
        }
        $response = $query->field('a.id,a.type,a.activity_title,a.registration_start_time,a.registration_end_time,a.activity_img,a.activity_start_time,a.activity_end_time,a.status,a.description,a.range,a.className,ae.status as enroll_status,ae.seller_id')
            ->order(['a.add_time'=>'desc'])
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])->each(function ($itme, $key) use ($param_time){
                //根据时间判断活动状态
                $activity_status = 0;
                if (
                    $itme['registration_start_time'] < $param_time
                    && $itme['registration_end_time'] > $param_time
                ){
                    //报名中
                    $activity_status = 1;
                }else if (
                    $itme['activity_start_time'] < $param_time
                    && $itme['activity_end_time'] > $param_time
                ){
                    //活动中
                    $activity_status = 2;
                }else if (
                    $itme['activity_end_time'] < $param_time
                ){
                    //已结束
                    $activity_status = 3;
                }
                $itme['activity_status'] = $activity_status;

                //判断seller是否参加了活动
                $is_join_activity = 0;
                if ($itme['seller_id'] == $this->get_activity_data_seller_id && $itme['enroll_status'] == 1){
                    $is_join_activity = 1;
                }
                $itme['is_join_activity'] = $is_join_activity;

                return $itme;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 根据条件获取活动数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getActivityDataByWhere(array $where){
        $data = $this->db->table($this->table)->where($where)->select();
        foreach ($data as &$info){
            $type_str = '';
            $range_str = '';
            $status_str = '';
            //活动类型:1专题活动;2定期活动;3节日活动4促销活动;
            switch ($info['type']){
                case 1:
                    $type_str = '专题活动';
                    break;
                case 2:
                    $type_str = '定期活动';
                    break;
                case 3:
                    $type_str = '节日活动';
                    break;
                case 4:
                    $type_str = '促销活动';
                    break;
                case 5:
                    $type_str = 'Flash Deals活动';
                    break;
            }
            //活动范围:1按商品进行，2分类进行
            switch ($info['range']){
                case 1:
                    $range_str = '按商品进行';
                    break;
                case 2:
                    $range_str = '分类进行';
                    break;
            }
            //活动状态:1为上线，2为下线,3为删除
            switch ($info['status']){
                case 1:
                    $status_str = '上线';
                    break;
                case 2:
                    $status_str = '下线';
                    break;
                case 3:
                    $status_str = '删除';
                    break;
            }
            $info['type_str'] = $type_str;
            $info['range_str'] = $range_str;
            $info['status_str'] = $status_str;
            //重置分类数据
            $info['className'] = json_decode($info['className'], true);
        }
        return $data;
    }

    /**
     * 添加报名活动信息
     * @param array $data 具体信息
     * @return int|string
     */
    public function insertActivityEnrollData(array $data){
        return $this->db->table($this->table_enroll)->insert($data);
    }

    /**
     * 根据条件获取活动报名信息
     * @param array $where 相关条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getActivityEnrollData(array $where){
        return $this->db->table($this->table_enroll)->where($where)->select();
    }

    /**
     * 修改报名信息数据
     * @param array $up_data 要修改的数据
     * @param array $where 修改条件
     * @return int|string
     */
    public function updateActivityEnrollData(array $up_data, array $where){
        return $this->db->table($this->table_enroll)->where($where)->update($up_data);
    }

    /**
     * 修改活动sku信息数据
     * @param array $up_data 要修改的数据
     * @param array $where 修改条件
     * @return int|string
     */
    public function updateActivitySkuData(array $up_data, array $where){
        return $this->db->table($this->table_sku)->where($where)->update($up_data);
    }

    /**
     * 修改活动sku信息数据
     * @param array $data 要修改的数据
     * @return int|string
     */
    public function updateActivityAllData(array $data){
        $rtn = true;
        // start
        $this->db->startTrans();
        try{
            //修改SKU表状态
            foreach ($data as $info){
                $where = ['id'=>$info['id']];
                $up_data = [
                    'activity_price'=>$info['activity_price'],
                    'activity_inventory'=>$info['activity_inventory'],
                    'discount'=>$info['discount'],
                    'set_type'=>$info['set_type'],
                    'edit_time'=>$info['edit_time'],
                    'edit_user_name'=>$info['edit_user_name']
                ];
                $this->updateActivitySkuData($up_data, $where);
            }
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            $rtn = false;
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 重新提交活动sku信息数据
     * @param array $data 要提交的数据
     * @return int|string
     */
    public function resubmitActivitySKU(array $data){
        $rtn = true;
        $spu_id = $data[0]['spu_id'];
        // start
        $this->db->startTrans();
        try{
            //修改SKU表状态
            foreach ($data as $info){
                $where = ['id'=>$info['id']];
                $up_data = [
                    'activity_price'=>$info['activity_price'],
                    'activity_inventory'=>$info['activity_inventory'],
                    'discount'=>$info['discount'],
                    'set_type'=>$info['set_type'],
                    'edit_time'=>$info['edit_time'],
                    'edit_user_name'=>$info['edit_user_name']
                ];
                $this->updateActivitySkuData($up_data, $where);
            }
            //修改SPU状态为审核中
            $this->updateActivitySPUDataByWhere(
                ['id'=>$spu_id],
                ['status'=>1]
            );
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            $rtn = false;
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 退出活动
     * @param array $param 相关参数
     * @return bool
     */
    public function outActicityForSeller(array $param){
        $rtn = true;
        $activity_id = $param['activity_id'];
        $seller_id = $param['seller_id'];
        $edit_time = $param['edit_time'];
        $edit_user_name = $param['edit_user_name'];
        // start
        $this->db->startTrans();
        try{
            //修改报名信息表
            $this->updateActivityEnrollData(
                ['status'=>2, 'edit_time'=>$edit_time, 'edit_user_name'=>$edit_user_name],
                ['seller_id'=>$seller_id, 'activity_id'=>$activity_id]
            );
            //修改SKU表状态
            $this->updateActivitySkuData(
                ['is_quit'=>2, 'edit_time'=>$edit_time],
                ['seller_id'=>$seller_id, 'activity_id'=>$activity_id]
            );
            //修改SPU表状态
            $this->updateActivitySPUDataByWhere(
                ['seller_id'=>$seller_id, 'activity_id'=>$activity_id],
                ['status'=>3]
            );
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            $rtn = false;
            Log::record('outActicityForSeller:'.$e->getMessage());
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 获取活动SKU数据
     * @param array $param 
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getActivitySKUData(array $param){
        $query = $this->db->table($this->table_sku);
        if (isset($param['seller_id']) && is_numeric($param['seller_id']) && $param['seller_id']>0){
            $query->where('seller_id', '=', $param['seller_id']);
        }
        if (isset($param['activity_id']) && is_numeric($param['activity_id']) && $param['activity_id']>0){
            $query->where('activity_id', '=', $param['activity_id']);
        }
        if (isset($param['sku']) && !empty($param['sku'])){
            $query->where('sku', '=', $param['sku']);
        }
        if (isset($param['product_id']) && !empty($param['product_id'])){
            $query->where('product_id', '=', $param['product_id']);
        }
        if (isset($param['code']) && !empty($param['code'])){
            $query->where('code', '=', $param['code']);
        }
        return $query->select();
    }

    /**
     * 添加活动SKU信息
     * @param array $data 要增加的数据
     * @return int|string
     */
    public function addActivitySKUData(array $data){
        return $this->db->table($this->table_sku)->insert($data);
    }

    /**
     * 获取活动产品数据【列表分页】
     * @param $params
     * @return array
     */
    public function getActivitySKUDataForList_old($params){
        $query = $this->db->table($this->table_sku);
        //查询条件activity_id
        if (isset($params['activity_id']) && !empty($params['activity_id'])){
            $query->where('activity_id', '=', $params['activity_id']);
        }
        //查询条件seller_id
        if (isset($params['seller_id']) && !empty($params['seller_id'])){
            $query->where('seller_id', '=', $params['seller_id']);
        }
        //查询条件Code
        if (isset($params['Code']) && !empty($params['Code'])){
            $query->where('code', '=', $params['Code']);
        }
        //查询条件tab_type:2-已添加（状态为‘审核中’，‘审核通过’），3-已驳回（状态为‘审核不通过’）
        if (isset($params['tab_type']) && !empty($params['tab_type'])){
            $tab_type = $params['tab_type'];
            if ($tab_type == 2){
                $query->where('status', 'IN', [1,2]);
            }elseif ($tab_type == 3){
                $query->where('status', 'IN', [3]);
            }
        }
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])->each(function ($item, $key){
            $status_str = '';
            switch ($item['status']){
                case 1:
                    $status_str = '审核中';
                    break;
                case 2:
                    $status_str = '审核通过';
                    break;
                case 3:
                    $status_str = '审核不通过';
                    break;
            }
            $item['status_str'] = $status_str;
            return $item;
        });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取活动产品数据【列表分页】
     * @param $params
     * @return array
     */
    public function getActivitySKUDataForList($params){
        $this->get_activity_sku_data_for_list_params = $params;
        $query = $this->db->table($this->table_spu)->alias('spu');
        $join = [
            [$this->table_sku.' sku','spu.product_id = sku.product_id','LEFT'],
        ];
        $query->join($join);
        //查询条件activity_id
        if (isset($params['activity_id']) && !empty($params['activity_id'])){
            $query->where('spu.activity_id', '=', $params['activity_id']);
        }
        //查询条件seller_id
        if (isset($params['seller_id']) && !empty($params['seller_id'])){
            $query->where('spu.seller_id', '=', $params['seller_id']);
        }
        //查询条件Code
        if (isset($params['Code']) && !empty($params['Code'])){
            $query->where('sku.code', '=', $params['Code']);
        }
        //查询条件tab_type:2-已添加（状态为‘审核中’，‘审核通过’），3-已驳回（状态为‘审核不通过’）
        if (isset($params['tab_type']) && !empty($params['tab_type'])){
            $tab_type = $params['tab_type'];
            if ($tab_type == 2){
                $query->where('spu.status', 'IN', ['1','2']);
            }elseif ($tab_type == 3){
                $query->where('spu.status', 'IN', ['3']);
            }
        }
        //选择查询字段
        $query->field('spu.*, sku.sku, sku.code, sku.sales_price, sku.activity_price, sku.set_type, sku.discount, sku.activity_inventory, sku.is_quit');
        //分组
        $query->group('spu.product_id');
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])->each(function ($item, $key){
            //重新组装数据，为了和“待添加商品”数据格式一致 start
            $skus_data = $this->getActivitySKUData(['product_id'=>$item['product_id'],'activity_id'=>$this->get_activity_sku_data_for_list_params['activity_id']]);
            $item['skus_data'] = $skus_data;
            $status_str = '';
            switch ($item['status']){
                case 1:
                    $status_str = '审核中';
                    break;
                case 2:
                    $status_str = '审核通过';
                    break;
                case 3:
                    $status_str = '审核不通过';
                    break;
            }
            $item['status_str'] = $status_str;
            //重新组装数据，为了和“待添加商品”数据格式一致 end
            return $item;
        });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 添加活动SPU信息
     * @param array $data 要增加的数据
     * @return int|string
     */
    public function addActivitySPUWithData(array $data){
        return $this->db->table($this->table_spu)->insert($data);
    }

    /**
     * 添加活动SPU信息
     * @param array $where
     * @return int|string
     */
    public function getActivitySPUDataByWhere(array $where){
        return $this->db->table($this->table_spu)->where($where)->select();
    }


    /**
     * 添加活动SPU信息
     * @param array $where 条件
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateActivitySPUDataByWhere(array $where, array $up_data){
        return $this->db->table($this->table_spu)->where($where)->update($up_data);
    }

    /**
     * 添加活动SPU信息
     * @param array $data 要增加的数据
     * @return int|string
     */
    public function addActivitySPUData(array $data){
        $rtn = true;
        $time = $data[0]['add_time'];
        //产品ID
        $product_id = $data[0]['product_id'];
        //活动ID
        $activity_id = $data[0]['activity_id'];
        $seller_id = $data[0]['seller_id'];
        $seller_name = $data[0]['seller_name'];
        // start
        $this->db->startTrans();
        try{
            //添加spu表信息
            $this->addActivitySPUWithData([
                    'product_id'=>$product_id,
                    'status'=>1, //1为审核中，2为审核通过，3为审核不通过
                    'activity_id'=>$activity_id,
                    'seller_id'=>$seller_id,
                    'seller_name'=>$seller_name,
                    'add_time'=>$time
                ]);
            //添加sku表信息
            foreach ($data as $info){
                $this->addActivitySKUData([
                    'activity_id'=>$info['activity_id'],
                    'seller_id'=>$info['seller_id'],
                    'seller_name'=>$info['seller_name'],
                    'sku'=>$info['sku'],
                    'product_id'=>$info['product_id'],
                    'code'=>$info['code'],
                    'sales_price'=>$info['sales_price'],
                    'activity_price'=>$info['activity_price'],
                    'set_type'=>$info['set_type'], //设置类型：1-批量设置，2-单个设置
                    'discount'=>$info['discount'], //折扣，单位%，如：10，对应折扣10%，打九折
                    'activity_inventory'=>$info['activity_inventory'],
                    'add_time'=>$time
                ]);
            }
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            $rtn = false;
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }

}
