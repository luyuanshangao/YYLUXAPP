<?php
namespace app\admin\model;

use think\Log;
use think\Model;
use think\Db;

/**
 * 旧CRC数据处理
 * User: tinghu.liu
 * Date: 2019/09/06
 */
class WindControlForOldModel extends Model{
    protected $db_crc;
    protected $connection = 'db_crc_sqlserver';

    public function __construct()
    {
        parent::__construct();
        $this->db_crc = Db::connect($this->connection);
    }

    /**
     * 历史信息页面 - 查询数据
     * @param array $params
     * @return mixed
     *
     * exec [dbo].[SP_TransAnalysisRecords_astropay(varchar @SearchType, nvarchar @SingleValue, bit @IsClearText, nvarchar @ClearCardNumberHash, char @BinCode, char @Last4Dig, nvarchar @CountryCode, nvarchar @City4Check, nvarchar @State4Check, nvarchar @Street4Check, datetime @StartTime, datetime @EndTime, int @PageSize, int @PageIndex, int @RecordSum)]
     *
     * "
    SET QUOTED_IDENTIFIER ON; SET ANSI_WARNINGS ON; SET ANSI_PADDING ON; SET ANSI_NULLS ON; SET CONCAT_NULL_YIELDS_NULL ON; SET NOCOUNT ON;
    DECLARE @RecordSum int
    exec [dbo].[SP_TransAnalysisRecords_astropay] 'IP', '192.168.11.157', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2010-01-01 00:00:00', '2019-09-01 00:00:00', 1, 1 ,@RecordSum"
     *
     */
    public function getHistoryInfoDetails(array $params){
        $data = [];
//        $params['SingleValue'] = '192.168.11.157'; //测试数据
        try{
            $sql = 'SET QUOTED_IDENTIFIER ON; SET ANSI_WARNINGS ON; SET ANSI_PADDING ON; SET ANSI_NULLS ON; SET CONCAT_NULL_YIELDS_NULL ON; SET NOCOUNT ON;
        DECLARE @RecordSum int
exec [dbo].[SP_TransAnalysisRecords_astropay] "'.$params['SearchType'].'","'.$params['SingleValue'].'",NULL,NULL,"'.$params['BinCode'].'","'.$params['Last4Dig'].'","'.$params['CountryCode'].'","'.$params['City4Check'].'","'.$params['State4Check'].'","'.$params['Street4Check'].'","'.$params['StartTime'].'","'.$params['EndTime'].'",'.$params['PageSize'].','.$params['PageIndex'].', @RecordSum';
            $data = $this->db_crc->query($sql);
        }catch (\Exception $e){
//            pr($e->getMessage());
            Log::record('getHistoryInfoDetails-调用异常：'.$e->getMessage().'('.$e->getFile().') ['.$e->getLine().']');
        }
        return $data;
    }

};