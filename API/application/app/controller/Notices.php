<?php
namespace app\app\controller;

use app\app\services\NoticesService;
use app\common\controller\AppBase;
use app\common\params\app\NoticesParams;
use think\Exception;
use think\Monlog;


/**
 * 消息相关接口
 * add by heng.zhang 2018-09-07
 */
class Notices extends AppBase
{
    protected $noticesService;
    public function __construct()
    {
        parent::__construct();
        $this -> noticesService = new NoticesService();
    }

    //TODO CRUD业务

    /**
     *获取客户是否存在未读消息
     */
    public function getIsNotRead(){
        $paramData = input();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$paramData);
        //参数校验
        $validate = $this->validate($paramData,(new NoticesParams())->getIsNotReadRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this-> noticesService->getIsNotRead($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     *消息回执接口
     */
    public function feedBackNotice(){
        $paramData = input(); //CustomerID NoticeID
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$paramData);
        //参数校验
        $validate = $this->validate($paramData,(new NoticesParams())->noticeCustomerSave());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this-> noticesService->noticeCustomerSave($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }

        //TODO C# DEMO
/*
        XMLData<NoticeFeedBackResponse> response = new XMLData<NoticeFeedBackResponse>();
            NoticeFeedBackResponse responseModel = new NoticeFeedBackResponse();
            try
            {
                //验证Token与客户ID是否匹配
                DMSError error = customerBll.CheckToken(requestModel.Token, requestModel.CustomerID);
                if (error != null)
                {
                    response.Status = error.ErrorCode;
                    responseModel.Error = error;
                }
                else
                {
                    //根据消息ID获取实体
                    DX.MS.Models.NoticePush pushModel = iNoticeBll.NoticePush_GetModel(requestModel.NoticeID);

                    //消息推送客户信息
                    NoticeCustomer customerModel = new NoticeCustomer();
                    customerModel.CustomerType = NoticeCustomerType.CustomerID;
                    customerModel.Customer = Convert.ToString(requestModel.CustomerID);
                    customerModel.IsRead = false;
                    customerModel.IsDeleted = false;
                    customerModel.CreateAt = DateTime.Now;
                    customerModel.NoticeType = pushModel.NoticeType;
                    List<NoticeCustomer> data = new List<NoticeCustomer>();
                    data.Add(customerModel);

                    //保存数据
                    iNoticeBll.NoticeCustomer_Save(requestModel.NoticeID, data);

                    //响应
                    responseModel.NoticeID = requestModel.NoticeID;
                    response.Status = SuccessStatus;
                }
            }
            catch (Exception e)
            {
                response.Status = DefaultFailStatus;
                responseModel.Error = new DMSError() { ErrorCode = DefaultFailStatus, LongMessage = e.Message, ShortMessage = e.Message };
                Diagnosis.Log.Error(m => m("Notice/FeedBackNotice"), e);
            }
            response.Data = responseModel;
            return response;
*/

    }

    /**
     * 获取消息类型列表
     * 获取当前用户下每种NoticeType下最新的一条数据，并判断是否存在未读消息
     */
    public function getNoticeTypeList(){
        //TODO C# DEMO
            /*
              /// <summary>
                    /// 系统消息
                    /// </summary>
                    [Description("系统消息")]
                    System = 1,

                    /// <summary>
                    /// 活动消息
                    /// </summary>
                    [Description("活动消息")]
                    Active = 2,
                    /// <summary>
                    /// 物流消息
                    /// </summary>
                    [Description("物流消息")]
                    Shipping = 3
             */
 //TODO 读取表：NoticeCustomer里的数据，

        /* 数据层实现逻辑：
          NoticeTypeListResponse response = new NoticeTypeListResponse();

            Type en = typeof(NoticeType);
            string[] names = Enum.GetNames(en);

            IList<string> noticeIDs = new List<string>();
            foreach (string t in names)
            {
                var type = (NoticeType)Enum.Parse(en, t);
                NoticeCustomer nc = null;

                if (type != NoticeType.Shipping)
                    nc = iNoticeCustomer.GetLastModel(customer, type);
                else
                {
                    nc = iNoticeCustomer.GetLastAndNotReadModel(customer, type);
                    if (nc == null)
                        nc = iNoticeCustomer.GetLastModel(customer, type);
                }

                if (nc != null)
                {
                    int unCount = iNoticeCustomer.UnReadCount(customer, type);
                    response.NoticeGroups.Add(new NoticeGroup { NoticeType = type, UnReadCount = unCount });
                    noticeIDs.Add(nc.NoticeID);
                }

            }

            IList<NoticePush> item = iNoticePush.GetObjectListByField("NoticeID", noticeIDs.ToArray()).OrderByDescending(p => p.CreateAt).ToList();

            response.NoticeGroups.ForEach(m =>
            {
                m.NoticePush = item.FirstOrDefault(o => o.NoticeType.Equals(m.NoticeType));
            });

            return response;

         */


        //return 的数据结构，用JSON
        //        public NoticeType NoticeType { get; set; }
        //        public int UnReadCount { get; set; }
        //        public NoticePush NoticePush { get; set; }

        /*NoticePush 结构：
         /// <summary>
    /// 消息推送实体类型
    /// </summary>
    public class NoticePush : Entity
    {
        public NoticePush()
        {
            this.ContentType = NoticeContentType.System;
            this.NoticeType = NoticeType.System;
            this.PushType = NoticePushType.AllCustomer;
            this.CreateAt = DateTime.Now;
        }

        /// <summary>
        /// 消息ID
        /// </summary>
        public string NoticeID { get; set; }

        /// <summary>
        /// 消息类型
        /// </summary>
        public NoticeType NoticeType { get; set; }

        /// <summary>
        /// 标题
        /// </summary>
        public string Title { get; set; }

        /// <summary>
        /// 消息内容
        /// </summary>
        public string Content { get; set; }

        /// <summary>
        /// 消息内容关联类型
        /// </summary>
        public NoticeContentType ContentType { get; set; }

        /// <summary>
        /// 消息内容关联数据
        /// </summary>
        public string ContentID { get; set; }

        /// <summary>
        /// 消息推送时间
        /// </summary>
        public DateTime CreateAt { get; set; }

        /// <summary>
        /// 推送给哪些客户
        /// </summary>
        public NoticePushType PushType { get; set; }

        /// <summary>
        /// 拓展字段
        /// </summary>
        public NoticeExtras Extras { get; set; }
    }

         */
    }

    /**
     * 获取对应的消息列表
     */
    public function getNotices(){
       //GetNotices(long customerID, string token, NoticeType noticeType = NoticeType.System, int pageIndex = 1, int pageSize = 10) 方法签名


       /* C# DEMO
         NoticesResponse response = new NoticesResponse();
            IList<NoticeCustomer> nCustomers = iNoticeCustomer.GetCustomerPagerData(customer, noticeType, pageIndex, pageSize);
            IList<NoticePush> nPush = iNoticePush.GetObjectListByField("NoticeID", nCustomers.Select(m => m.NoticeID).ToArray()).OrderByDescending(p => p.CreateAt).ToList();

            IList<ClientNoticePush> cNoticePust = new List<ClientNoticePush>();
            nPush.ForEach(m =>
            {
                cNoticePust.Add(new ClientNoticePush()
                {
                    IsRead = nCustomers.FirstOrDefault(o => o.NoticeID.Equals(m.NoticeID)).IsRead,
                    NoticePush = m,
                });
            });

            response.NoticePushs = cNoticePust;

            return response;

        */

    }

    /**
     * 删除消息--非物理删除
     */
    public function deleteNotices(){
//TODO

        /*
          bool result = false;
            string customer = request.CustomerID.ToString();
            switch (request.ActionType)
            {
                case ActionType.All:
                    result = iNoticeCustomer.DeleteCustomerAllNotice(customer, out rowCount);
                    break;
                case ActionType.Custom:
                    result = iNoticeCustomer.DeleteCustomerNoticeByNoticeIDs(customer, request.NoticeIDs, out rowCount);
                    break;
                default:
                    result = iNoticeCustomer.DeleteCustomerNoticeByNoticeType(customer, (NoticeType)request.ActionType, out rowCount);
                    break;
            }
            return result;
         */
    }

    /**
     * 设置消息已读接口
     */
    public function setNoticeRead(){
//TODO

        /*
         bool result = false;
            string customer = request.CustomerID.ToString();
            switch (request.ActionType)
            {
                case ActionType.All:
                    result = iNoticeCustomer.SetCustomerAllNoticeRead(customer, out rowCount);
                    break;
                case ActionType.Custom:
                    result = iNoticeCustomer.SetCustomerNoticeReadByNoticeIDs(customer, request.NoticeIDs, out rowCount);
                    break;
                default:
                    result = iNoticeCustomer.SetCustomerNoticeReadByNoticeType(customer, (NoticeType)request.ActionType, out rowCount);
                    break;
            }
            return result;

         */
    }
}
