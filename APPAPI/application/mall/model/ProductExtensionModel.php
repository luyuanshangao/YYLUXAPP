<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 产品推荐
 */
class ProductExtensionModel extends Model{

    /// ProductExtension表中ProductRecommend的推荐类型
    /// 1 - related products
    /// 2 - Customers Who Viewed This Item Also Viewed
    /// 3 - Customers Who Bought This Item Also Bought
    /// 产品详情页 --related products位置推荐
//ProductDetail_RelatedProducts = 1,
    /// 产品详情页 --Customers Who Viewed This Item Also Viewed位置推荐
//ProductDetail_AlsoViewed = 2,
    /// 产品详情页 --Customers Who Bought This Item Also Bought位置推荐
//ProductDetail_AlsoBought = 3,

    protected $db;
    const productExtension = 'product_extension';//产品推荐

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 获取spu下的推荐
     */
    public function getRecommendedSpu($params){
        return $this->db->name(self::productExtension)->where(['_id' => (int)$params['product_id']])->find();
    }

}