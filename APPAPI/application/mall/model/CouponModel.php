<?php
namespace app\mall\model;

use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\mongo\Query;

/**
 * Coupon模型
 * @author
 * @version  zhi gong 2018-05-25
 */
class CouponModel extends Model{

    protected $db;
    protected $coupon = 'coupon';
    protected $coupon_code = 'coupon_code';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 根据商家ID，skuID过滤出可用的coupon列表
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function getAvailableCoupon($params){
        $time = time();
        $where = array();
        $query = $this->db->name($this->coupon);
		//时间判断
        $where['CouponTime.StartTime'] = ['<',$time];
        $where['CouponTime.EndTime'] = ['>=',$time];
        $where['CouponStatus'] = 3;//开启状态的才可用
        //过滤是店铺还是平台优惠券
        if(isset($params['CouponRuleType']) && is_array($params['CouponRuleType'])){
            $idsType = [];
        	foreach($params['CouponRuleType'] as $id){
        		$idsType[] = (int)$id;
        	}
        	$where['CouponRuleSetting.CouponRuleType'] = ['in',$idsType];
        }
        if(isset($params['coupon_id']) && !empty($params['coupon_id'])){
            if(is_array($params['coupon_id'])){
                $params['coupon_id'] = array_unique($params['coupon_id']);
                $couponIds = [];
                foreach($params['coupon_id'] as $id){
                    $couponIds[] = (int)$id;
                }
                $where['CouponId'] = ['in',$couponIds];
            }else{
                $where['CouponId'] = (int)$params['coupon_id'];
            }
        }
        //CouponChannels过滤
        if(isset($params['CouponChannels']) && is_array($params['CouponChannels'])){
            $params['CouponChannels'] = array_unique($params['CouponChannels']);
            $idsChannels = [];
        	foreach($params['CouponChannels'] as $id){
        		$idsChannels[] = (int)$id;
        	}
        	$where['CouponChannels'] = ['in',$idsChannels];
        }
        if(isset($params['CouponStrategy']) && $params['CouponStrategy']){
            if(is_array($params['CouponStrategy'])){
                $params['CouponStrategy'] = array_unique($params['CouponStrategy']);
                $idsStrategy = [];
                foreach($params['CouponStrategy'] as $id){
                    $idsStrategy[] = (int)$id;
                }
                $where['CouponStrategy'] = ['in',$idsStrategy];
            }else{
                $where['CouponStrategy'] = (int)$params['CouponStrategy'];
            }
        }
        ////20181221 过滤活动策略：1-线上活动、2-线下活动。【为了兼容没有活动策略的老数据，需要在此循环过滤】
        $ActivityStrategy = isset($params['ActivityStrategy'])?(int)$params['ActivityStrategy']:1;
        $where['ActivityStrategy'] = $ActivityStrategy;

        if(isset($params['DiscountLevel']) && !empty($params['DiscountLevel'])){
            $where['DiscountLevel'] = $params['DiscountLevel'];
        }
        //商铺ID
        if(isset($params['store_id']) && $params['store_id']){
        	$where['SellerId'] = (int)$params['store_id'];
        }
        $query->order('StartTime','desc');
        $couponArray = $query->where($where)
            ->field("SellerId,CouponStrategy,CouponId,Name,DiscountLevel,DiscountType,CouponNumLimit,
            PurchaseAmountLimit,BuyGoodsNumLimit,CouponRuleSetting,CouponChannels,
            Description,CouponTime,CouponStatus,DesignatedStore,ReceiveLimit,ActivityStrategy")
            ->select();
        if($params['IsAutoCouponGetCouponCode']){
            if(!empty($couponArray)){
                $queryCouponCode = $this->db->name($this->coupon_code);
                foreach($couponArray as $k=>$v){
                    $ids[] = (int)$v['CouponId'];
                    $whereCouponCode['CouponId'] = (int)$v['CouponId'];
                    $couponCodeArray = $queryCouponCode->where($whereCouponCode)
                        ->field("CouponId,coupon_code")
                        ->find();
                    if(!empty($couponCodeArray)){
                        $couponArray[$k]['coupon_code'] = $couponCodeArray['coupon_code'];
                    }
                        }
                }
            }
        return $couponArray;

    }

    /**
     * 获取coupon code
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponCode($params){
        $where = array();
        if(isset($params['coupon_id']) && !empty($params['coupon_id'])){
            if(is_array($params['coupon_id'])){
                $where['CouponId'] = CommonLib::supportArray($params['coupon_id']);
            }else{
                $where['CouponId'] = (int)$params['coupon_id'];
            }
        }
        if(isset($params['status']) && !empty($params['status'])){
            if(is_array($params['status'])){
                $where['Status'] = CommonLib::supportArray($params['status']);
            }else{
                $where['Status'] = (int)$params['status'];
            }
        }
        return $this->db->name($this->coupon_code)->where($where)->field(['CouponId'=>true,'coupon_code'=>true,'_id'=>false])->select();
    }

    /**
     * 根据coupon分组
     * @param $coupon_id
     * @return mixed
     */
    public function getCouponCodeCount($coupon_id){
        $where = array();
        if(is_array($coupon_id)){
            $where['CouponId'] = ['$in'=>CommonLib::array_string_int($coupon_id)];
        }else{
            $where['CouponId'] = (int)$coupon_id;
        }
        $mongo = new Mongo('dx_coupon_code');
        $ret = $mongo->group('$CouponId',$where);
        return json_decode(json_encode($ret),true);
    }

    /**
     * coupon详情
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function findCoupon($params){
        $query = $this->db->name($this->coupon);

        if(isset($params['coupon_id']) && !empty($params['coupon_id'])){
            if(is_array($params['coupon_id'])){
                foreach($params['coupon_id'] as $id){
                    $ids[] = (int)$id;
                }
                $where['CouponId'] = ['in',$ids];
            }else{
                $where['CouponId'] = (int)$params['coupon_id'];
            }
        }

        $result = $query->where($where)
            ->field("SellerId,CouponStrategy,CouponId,Name,DiscountLevel,DiscountType,CouponNumLimit,PurchaseAmountLimit,BuyGoodsNumLimit,CouponRuleSetting,
            Description,CouponTime,CouponChannels,ReceiveLimit,DesignatedStore")
            ->find();
        return $result;

    }


    /**
     * 根据商家ID，skuID过滤出可用的coupon列表
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function getCouponList($params){
        $time = time();
        $where = array();
        $query = $this->db->name($this->coupon);
        //时间判断
        $where['CouponTime.StartTime'] = ['<',$time];
        $where['CouponTime.EndTime'] = ['>=',$time];
        $where['CouponStatus'] = 3;//开启状态的才可用
        //过滤是店铺还是平台优惠券
        if(isset($params['CouponRuleType']) && is_array($params['CouponRuleType'])){
            foreach($params['CouponRuleType'] as $id){
                $ids[] = (int)$id;
            }
            $where['CouponRuleSetting.CouponRuleType'] = ['in',$ids];
        }
        if(isset($params['coupon_id']) && !empty($params['coupon_id'])){
            if(is_array($params['coupon_id'])){
                $where['CouponId'] = CommonLib::supportArray($params['coupon_id']);
            }else{
                $where['CouponId'] = (int)$params['coupon_id'];
            }
        }
        //CouponChannels过滤
        if(isset($params['CouponChannels']) && is_array($params['CouponChannels'])){
            foreach($params['CouponChannels'] as $id){
                $ids[] = (int)$id;
            }
            $where['CouponChannels'] = ['in',$ids];
        }
        if(isset($params['CouponStrategy']) && !empty($params['CouponStrategy'])){
            if(is_array($params['CouponStrategy'])){
                $where['CouponStrategy'] = CommonLib::supportArray($params['CouponStrategy']);
            }else{
                $where['CouponStrategy'] = (int)$params['CouponStrategy'];
            }
        }

        if(isset($params['DiscountLevel']) && !empty($params['DiscountLevel'])){
            $where['DiscountLevel'] = $params['DiscountLevel'];
        }
        if(isset($params['ActivityStrategy']) && !empty($params['ActivityStrategy'])){
            $where['ActivityStrategy'] = (int)$params['ActivityStrategy'];
        }
        //商铺ID
        if(isset($params['store_id']) && $params['store_id']){
            $query->whereOr(['SellerId' => (int)$params['store_id']])->whereOr(['DesignatedStore'=>['in',[(int)$params['store_id']]]]);
        }
        //数据库是 CreateTime
        $query->order('CreateTime','desc');
        $couponArray = $query->where($where)
            ->field("SellerId,CouponStrategy,CouponId,Name,DiscountLevel,DiscountType,CouponNumLimit,
            PurchaseAmountLimit,BuyGoodsNumLimit,CouponRuleSetting,CouponChannels,Description.en,CouponTime".",Description.".$params['lang'].",DesignatedStore")
            ->select();
        return $couponArray;
    }

    /**
     * 更新coupon code
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function updateCodeStatus($params){
        return $this->db->name($this->coupon_code)->where(['coupon_code'=>$params['coupon_code'],'CouponId'=>(int)$params['coupon_id']])
            ->update(['Status'=>(int)1]);
    }

    /**
     * 查询code数量
     * @param $params
     * @return int|string
     */
    public function getCodeCount($params){
        if(is_array($params['status'])){
            $status = CommonLib::supportArray($params['status']);
        }else{
            $status = (int)$params['status'];
        }
        return $this->db->name($this->coupon_code)->where(['Status'=>$status,'CouponId'=>(int)$params['coupon_id']])
            ->count('CouponId');
    }


    public function getCouponInfoByCouponId($params){
        $query = $this->db->name($this->coupon);
        if(isset($params['coupon_id']) && !empty($params['coupon_id'])){
            if(is_array($params['coupon_id'])){
                foreach($params['coupon_id'] as $id){
                    $ids[] = (int)$id;
                }
                $where['CouponId'] = ['in',$ids];
            }else{
                $where['CouponId'] = (int)$params['coupon_id'];
            }
        }

        $result = $query->where($where)
            ->field("SellerId,CouponStrategy,CouponId,Name,DiscountLevel,DiscountType,CouponNumLimit,PurchaseAmountLimit,BuyGoodsNumLimit,CouponRuleSetting,
            Description,CouponTime,CouponChannels,ReceiveLimit,DesignatedStore")
            ->find();
        return $result;

    }

    /**
     * 根据coupon code获取coupon数据
     * @param array $params
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCouponByCouponCode(array $params){
        $StoreId = $params['StoreId'];
        $CouponCode = $params['CouponCode'];
        $code_data = $this->db->name($this->coupon_code)
            ->where([
                'coupon_code'=>$CouponCode
            ])
            ->find();
        if (empty($code_data) || !isset($code_data['CouponId']) || empty($code_data['CouponId'])){
            return ['code'=>0, 'msg'=>'Illegal Code.'];
        }
        $CouponId = $code_data['CouponId'];
        $query = $this->db->name($this->coupon);
        if (isset($params['CouponStatus'])){
            $query->where(['CouponStatus'=>(int)$params['CouponStatus']]);
        }
        //活动策略：1-线上活动、2-线下活动
        if (isset($params['ActivityStrategy'])){
            $query->where(['ActivityStrategy'=>(int)$params['ActivityStrategy']]);
        }
        $coupon_data = $query
            ->where([
                'CouponId'=>(int)$CouponId,
//                'SellerId'=>(int)$StoreId
            ])
            //添加多seller判断 字段：DesignatedStore 。。。。。。。。。。BY tinghu.liu IN 20190128
            ->where(function($query) use($StoreId){
                $query->whereOr(['SellerId'=>(int)$StoreId])->whereOr('DesignatedStore', 'in', [(int)$StoreId]);
            })
            ->find();
        Log::record('getCouponByCouponCode-0128:'.$query->getLastSql());
        if (empty($coupon_data)){
            return ['code'=>0, 'msg'=>'Illegal Coupon Info.'];
        }
        return ['code'=>1, 'data'=>$coupon_data];
    }


    /**
     * 商城首页展示coupon文案,不需要判断coupon条件
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|Model
     */
    public function selectCouponInfo($params){
        $where = array();
        $query = $this->db->name($this->coupon);

        if(isset($params['coupon_id']) && !empty($params['coupon_id'])){
            if(is_array($params['coupon_id'])){
                $where['CouponId'] = CommonLib::supportArray($params['coupon_id']);
            }else{
                $where['CouponId'] = (int)$params['coupon_id'];
            }
        }
        $query->order('CreateTime','desc');
        $couponArray = $query->where($where)
            ->field("SellerId,CouponStrategy,CouponId,Name,DiscountLevel,DiscountType,CouponNumLimit,
            PurchaseAmountLimit,BuyGoodsNumLimit,CouponRuleSetting,CouponChannels,Description.en,CouponTime".",Description.".$params['lang'].",DesignatedStore")
            ->select();
        return $couponArray;
    }

}