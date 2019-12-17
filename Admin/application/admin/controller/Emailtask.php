<?php

namespace app\admin\controller;

use app\admin\dxcommon\ExcelTool;
use app\admin\model\EDMActivityModel;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use app\admin\dxcommon\BaseApi;
use think\Log;
use think\helper\Time;
use app\admin\model\EDMStatusManageModel;
use app\admin\model\EDMRecipientModel;
use app\admin\services\BaseService;

class Emailtask extends Action
{
    private $about_id;
    private $db;
    ///邮件发送者信息
    private  $EmailSender = array(
        "1" => "DX.com/news@e.dx.com",
        "2" => "DX.com/news@edm.dx.com"
    );
    public function __construct()
    {
        Action::__construct();
    }

    /**
     * 邮件任务列表页
     * @return View
     */
    public function index()
    {
        $params = input();
        $statusModel = new EDMStatusManageModel();
        $recipientModel = new EDMRecipientModel();
        $this->assign('task_Status', $statusModel::$EmailTaskStatus);
        //列表信息
        $where = [];
        if (!empty($params['title'])) {
            $where['TaskTitle'] = array('like', '%' . $params['title'] . '%');
        }
        $page_size = config('paginate.list_rows');
        $list = (new EDMActivityModel())->getEmailTaskPaginate($where, $page_size);
        $result = $list->items();
         foreach ($list as $key => $item) { 
            $result[$key]['SenderVale']=$this->EmailSender[$item['Sender']];
            $recipientInfo = $recipientModel->getRecipient(array('id' => $item['RecipientID']));
            $result[$key]['RecipientInfo']=$recipientInfo['title'];
        } 
        $this->assign('pageInfo', $list->render());
        $this->assign('list', $result);
        $this->assign('params', $params);
        return view();
    }

    /**
     * 邮件任务编辑页
     * @return \think\response\Json|View
     */
    public function saveTask()
    {
        $input = input();
        $model = new EDMActivityModel();
        $recipientModel = new EDMRecipientModel();
        $recipient_list = $recipientModel->selectRecipientData(['IsUsed' => 0, 'status' => 100, 'IsEnable' => 1]); //未使用
        //语种
        $this->assign('lang_data', $model::$langCode);
        $this->assign('email_service', $model::$emailService);
        $this->assign('recipient_list', $recipient_list);

        //是否编辑
        if (!empty($input['id'])) {
            $activity = $model->getEmailTask($input['id']);
            $recipientInfo = $recipientModel->getRecipient(array(
                'id' => $activity['RecipientID']
            ));
            $this->assign('activity', $activity);
            $this->assign('recipientInfo', $recipientInfo);
        } else {
            $this->assign('activity', array('Followers' => 'caill@comepro.com;chengxl@comepro.com;fuyt@comepro.com;linhc@comepro.com;zhongwt@comepro.com;caimc@comepro.com;chenxl@comepro.com;amy.cao@comepro.com;luodao@comepro.com;libei@comepro.com;niyy@comepro.com;liukai@comepro.com'));
        }

        if (!empty($input['data'])) {
            date_default_timezone_set('PRC');
            $params = $input['data'];
            $data['TaskTitle'] = !empty($params['TaskTitle']) ?  $params['TaskTitle'] : '';
            //实时发送将创建时间往后移动半小时，防止任务正在创建过程中
            $data['SendingTime'] = !empty($params['StartTime']) ?  $params['StartTime'] : date('Y-m-d H:i:s', time() + 30 * 60);
            $data['RecipientID'] = !empty($params['RecipientID']) ?  $params['RecipientID'] : '';
            $data['IsRealTime'] = $params['ImmediatelySend'] == true ?  1 : 2;
            $data['IsActivity'] = !empty($params['IsActivity']) ?  $params['IsActivity'] : '';
            $data['langage'] = !empty($params['LangCode']) ?  $params['LangCode'] : '';
            $data['IsEnable'] = !empty($params['IsEnable']) ?  $params['IsEnable'] : '';
            $data['EmailSubject'] = !empty($params['MailSubject']) ?  $params['MailSubject'] : '';
            $data['EmailBody'] = !empty($params['MailBody']) ?  $params['MailBody'] : '';
            $data['ActivityName'] = !empty($params['ActivityName']) ?  $params['ActivityName'] : '';
            $data['Sender'] = !empty($params['SenderID']) ?  $params['SenderID'] : '';
            $data['Status'] = 1; //正在请求数据
            $data['IsSender'] = 1; //是否发送邮件  默认1 否  2 为是
            $data['Followers'] = !empty($params['Followers']) ? $params['Followers'] : ''; //测试用户收件人

            if (!empty($params['id'])) {
                $ret = $model->updateEmailTask($params['id'], $data);
                if ($ret > 0) {
                    $recipientModel->updateRecipient(['id' => $params['RecipientID']], ['IsUsed' => 1]);
                }
                return json(['code' => 200, 'msg' => '修改成功', 'url' => '/emailtask/index']);
            } else {
                $ret = $model->createEmailTask($data);
                if ($ret > 0) {
                    $recipientModel->updateRecipient(['id' => $params['RecipientID']], ['IsUsed' => 1]);
                    return json(['code' => 200, 'msg' => '新增成功', 'url' => '/emailtask/index']);
                }
            }
            return json(['code' => 1001, 'msg' => '操作失败']);
        }
        return view();
    }


    /**
     * 收件人列表页
     * @return View
     */
    public function recipientListIndex()
    {

        $params = input();
        $RecipientStatus = EDMStatusManageModel::$RecipientStatus;



        //列表信息
        $where = [];
        if (!empty($params['title'])) {
            $where['title'] = array('like', '%' . $params['title'] . '%');;
        }
        $page_size = config('paginate.list_rows');
        $list = (new EDMRecipientModel())->getRecipientDataPaginate($where, $page_size);
        $result = $list->items();
        foreach ($result as $key => $item) {
            $ScreeningList = Db::name('screening_management')->field('TopicName')->where(['id' => $item['ScreeningId']])->find();
            $result[$key]['ScreeningText'] = $ScreeningList['TopicName'];
            $result[$key]['StatusText'] = $RecipientStatus[(int) $item['status']];
        }
        $this->assign('pageInfo', $list->render());
        $this->assign('list', $result);
        $this->assign('params', $params);

        return view();
    }

    /**
     * 收件人列表编辑页
     * @return View
     */
    public function recipientListSave()
    {
        $params = input();
        if (!empty($params['id'])) {
            $params = (new EDMRecipientModel())->getRecipient(['id' => $params['id']]);
            $this->assign('params', $params);
        }
        //配置邮件服务商
        $EmailService = EDMStatusManageModel::$EmailService;
        $ScreeningList = Db::name('screening_management')->where(['status' => 2, 'Parent_id' => 0])->field('TopicName,id')->order('id desc')->limit(50)->select();
        $this->assign('EmailService', $EmailService);
        $this->assign('ScreeningList', $ScreeningList);
        return view();
    }

    public function saveRecipient()
    {
        $params = input();
        $this->assign('params', $params);
        //修改
        if (!empty($params['id'])) {
            $updateInfo = array(
                'title' => $params['title'],
                'ScreeningId' => $params['ScreeningId'],
                'IsSplit' => $params['IsSplit'],
                'EmailService' => $params['EmailService'],
                'status' => (int) $params['IsEnable'] == 1 ? 1 : 0,
                'IsEnable' => !empty($params['IsEnable']) ? $params['IsEnable'] : 0,
                'langage' => !empty($params['langage']) ? $params['langage'] : "en",
            );
            (new EDMRecipientModel())->updateRecipient(['id' => $params['id']], $updateInfo);
            echo "修改成功";
        } else {

            //转换时区
            date_default_timezone_set('PRC');
            $InsertInfo = array(
                'title' => $params['title'],
                'ScreeningId' => $params['ScreeningId'],
                'IsSplit' => $params['IsSplit'],
                'RecordCount' => 1,
                'EmailService' => $params['EmailService'],
                'status' => (int) $params['IsEnable'] == 1 ? 1 : 0,
                'IsEnable' => !empty($params['IsEnable']) ? $params['IsEnable'] : 0,
                'createtime' => date('Y-m-d H:i:s', time()),
                'langage' => !empty($params['langage']) ? $params['langage'] : "en",
                'createby' => Session::get('username')
            );


            $result = (new EDMRecipientModel())->getRecipientDataInsert($InsertInfo);
            if ($result > 0)
                echo "创建成功";
            else
                echo "创建失败";
        }
    }

    /**
     * 收件人列表详情
     * @return View
     */
    public function recipientListDetail()
    {
        $params = input();
        $RecipientResult = array();
        $RecipientLineResult = array();

        if ((int) $params['id'] > 0) {
            $RecipientStatus = EDMStatusManageModel::$RecipientStatus;
            $RecipientLineStatus = EDMStatusManageModel::$RecipientLineStatus;
            $RecipientId = (int) $params['id'];
            $RecipientModel = new EDMRecipientModel();

            $RecipientResult = $RecipientModel->getRecipientDataPaginate(array('id' => $RecipientId))->items()[0];
            //begin 格式化数据
            $ScreeningList = Db::name('screening_management')->field('TopicName')->where(['id' => $RecipientResult['ScreeningId']])->find();
            $RecipientResult['ScreeningText'] = $ScreeningList['TopicName'];
            $RecipientResult['statustext'] = $RecipientStatus[$RecipientResult['status']];
            //end  格式化数据

            $RecipientLineResult = $RecipientModel->getRecipientLineDataPaginate($RecipientId);
            if (!empty($RecipientLineResult)) {
                foreach ($RecipientLineResult as $key => $item) {
                    $RecipientLineResult[$key]['StatusText'] = $RecipientLineStatus[$item['status']];
                }
            }
        }
        $this->assign('ScreeningList', $RecipientResult);
        $this->assign('ScreeningLineList', $RecipientLineResult);
        return view();
    }

    /**
     * 邮件发送详情
     * @return \think\response\Json|View
     */
    public function emailTaskDetails()
    {
        $input = input();
        if (!empty($input['id'])) {
            $EmailTaskLineStatus = EDMStatusManageModel::$EmailTaskLineStatus;
            $recipientModel = new EDMRecipientModel();
            $activityModel = new EDMActivityModel();
            $activity = $activityModel->getEmailTask($input['id']);
            $recipient = $recipientModel->getRecipient(['id' => $activity['RecipientID']]);
            $activity['RecipientTitle'] = $recipient['title'];
            //邮件任务列表详情
            $linelist = $activityModel->selectEmailTaskLine($input['id']);
            $this->assign('list', $linelist);
            $this->assign('activity', $activity);
            $this->assign('taskLineStatus', $EmailTaskLineStatus);
        } else {
            return json(['code' => 1001, 'msg' => 'id 不存在']);
        }
        return view();
    }

    ///序列号国家语言数据
    public  function serializeRegionInfo()
    {
        $this->db = Db::connect("db_mongo");
        $regionList = $this->db->table('dx_region')->where(['ParentID' => 0])->select();
        //$a= $this->db->table('dx_region')->getLastSql();
        //print_r($regionList);die;
        //cn|en|cs|de|es|fi|fr|it|nl|no|pt|ru|sv|ja|ar
        //header("Content-Type: text/html; charset=utf-8");
        //echo "英语";


        $Info = [];
        $Info += array('英语' => 'en');
        $Info += array('汉语' => 'cn');
        $Info += array('捷克语' => 'cs');
        $Info += array('德语' => 'de');
        $Info += array('西班牙语' => 'es');
        $Info += array('芬兰语' => 'fi');
        $Info += array('法语' => 'fr');
        $Info += array('意大利语' => 'it');
        $Info += array('荷兰语' => 'nl');
        $Info += array('挪威语' => 'no');
        $Info += array('葡语' => 'pt');
        $Info += array('俄语' => 'ru');
        $Info += array('瑞典语' => 'sv');
        $Info += array('日本语' => 'ja');
        $Info += array('阿拉伯语' => 'ar');

        //var_dump($Info);


        foreach ($regionList as $key => $value) {
            if (!empty($value['Language'])) {
                $LanguageCode = $Info[$value['Language']];
                $ret = $this->db->table('dx_region')->where('_id', (int) $value['_id'])->update(['LanguageCode' => $LanguageCode]);
                $a = $this->db->table('dx_region')->getLastSql();
            }
        }


        //$PayemtMethod3 = Db::connect("db_mongo")->name('region')->where(['ParentID'=>0])->find();
        //$this->db = Db::connect("db_admin");
        return json_encode(['message' => '处理成功！']);
    }

    /**
     * 删除
     */
    public function operating()
    {
        $input = input();
        if (!empty($input['data'])) {
            $model = new EDMActivityModel();
            $params = $input['data'];
            if (empty($params['id'])) {
                return json(['code' => 1001, 'msg' => '数据有误']);
            }
            switch ($params['type']) {
                case 3:
                    $ret = $model->delEmailTask(['in', $params['id']]);
                    if ($ret > 0) {
                        return json(['code' => 200, 'msg' => '删除成功', 'url' => '/Emailtask/index']);
                    }
                    break;
                default:
                    break;
            }
            return json(['code' => 1001, 'msg' => '操作失败']);
        }
    }

    /**
     * 删除
     */
    public function recipientDel()
    {
        $input = input();
        if (!empty($input['data'])) {
            $params = $input['data'];
            if (empty($params['id'])) {
                return json(['code' => 1001, 'msg' => '数据有误']);
            }
            $ret = (new EDMRecipientModel())->delRecipient(['in', $params['id']]);
            if ($ret > 0) {
                return json(['code' => 200, 'msg' => '删除成功', 'url' => '/Emailtask/index']);
            }
            return json(['code' => 1001, 'msg' => '操作失败']);
        }
    }

    /**
     * 文件下载
     * @return \think\response\Json
     */
    public function download()
    {
        $params = input();
        if (empty($params['path'])) {
            return json(['code' => 1002, 'result' => '数据有误']);
        }
        //区分
        if (PATH_SEPARATOR == ':') {
            $path = $params['path'];
        } else {
            $path = ROOT_PATH . 'public' . $params['path'];
        }
        $filename = array_pop(explode('/', $params['path']));
        if (!file_exists($path)) {
            return json(['code' => 1002, 'result' => '文件不存在']);
        }
        $tool = new ExcelTool();
        $tool->down($path, $filename);
    }


    ///实时发送邮件 Broadcast 即发送测试邮件
    public function BroadcastTrigger()
    {
        $input = input();
        if (!empty($input)) {

            $taskID = $input['data']['taskID'];
            $activityModel = new EDMActivityModel();


            $activity = $activityModel->getEmailTask($taskID);

            if (!empty($activity) && $activity['Status'] >= 2) {
                //整理收件人信息
                $receiveEmail = $activity['Followers'];
                $date = array();
                foreach (explode(';', $receiveEmail) as $key => $item) {
                    if (!empty($item)) {
                        $list[] = array(
                            'customerid' => $key,
                            'email' => $item
                        );
                    }
                }
                //邮件任务列表详情
                $SentStatus = false;
                $lineList = $activityModel->selectEmailTaskLine($taskID);
                $recipientModel = new EDMRecipientModel();
                $recipient = $recipientModel->getRecipient(['id' => $activity['RecipientID']]);
                if (count($list) > 0) {
                    foreach ($lineList as $item) {

                        #20191030  ES, NL, CS, FI, DE, FR, SV, IT, RU, NO, BR, US, UK, CA, AU, ZA, JP 国家限制 测试邮件只发17国家 
                        $CountryArray = array('ES', 'NL', 'CZ', 'FI', 'DE', 'FR', 'SE', 'IT', 'RU', 'NO', 'BR', 'US', 'GB', 'CA', 'AU', 'ZA', 'JP');
                        $CountryCode = strtoupper($item['CountryCode']);

                        if ($item['Status'] == 4 && strlen($item['EmailSubject']) > 0 && strlen($item['EmailContent']) > 0 && in_array($CountryCode, $CountryArray)) {

                            $EmailTitle = "";
                            if ($recipient['IsSplit'] == "1") {
                                $EmailTitle = $item['EmailSubject'] . " (test-" . $item['CountryCode'] . "-" . $item['langage'] . ")";
                            } else {
                                $EmailTitle = $item['EmailSubject'] . " (test-" . $item['langage'] . ")";
                            }
                            $DateInfo = array(
                                'email_name' => date("Ymd-His") . '-' . rand(100, 999) . '-' . $item['id'],
                                'subject_line' => $EmailTitle,
                                'from_sender' => "news@edm.dx.com",
                                'from_name' => "DX.com",
                                'html_version' => $item['EmailContent'],
                                'data' => $list
                            );

                            $result =  $this->doRequest("https://service.dx.com/create_campaign/trigger.html", "POST", json_encode($DateInfo));
                            $SentStatus = true;
                        }
                    }
                    if ($SentStatus) {
                        $activityModel->updateEmailTask( $taskID,array('is_send_test_email'=>'2'));
                        return json(['code' => 200, 'msg' => '正在发送']);
                    } else {
                        return json(['code' => 1001, 'msg' => '发送失败，有效邮件信息为0条，请查看详情信息']);
                    }
                } else {
                    return json(['code' => 1001, 'msg' => '发送失败，收件人为空']);
                }
            } else {
                return json(['code' => 1001, 'msg' => '邮件任务状态不正确']);
            }
        } else {
            return json(['code' => 1001, 'msg' => 'id 不存在']);
        }
    }

    function doRequest($url, $method, $data = 0)
    {


        $curl = curl_init($url);

        // set the method
        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "put");
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                break;
        }

        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type:application/json"));
        }



        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);


        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $this->_header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($status == 200) {
            return $body;
        } else {
            return $body;
        }
    }


    public function SentEmail()
    {
        $input = input();
        if (!empty($input)) {

            $taskID = $input['data']['taskID'];
            $activityModel = new EDMActivityModel();
            $activity = $activityModel->getEmailTask($taskID);

            if (!empty($activity) && $activity['Status'] == 2) {
                //整理收件人信息
                $UpdateInfo = array(
                    'IsSender' => 2,
                );

                $ret = $activityModel->updateEdmTask($taskID, $UpdateInfo);
                if ($ret > 0) {
                    return json(['code' => 200, 'msg' => '发送中，请稍后']);
                } else {
                    return json(['code' => 1002, 'msg' => '发送失败，请联系管理员！']);
                }
            } else {
                return json(['code' => 1002, 'msg' => '邮件任务不正确,请稍后重试！']);
            }
        } else {
            return json(['code' => 1001, 'msg' => '邮件任务不存在']);
        }
    }

    //重新上传，针对Extreme创建邮件服务商失败
    public function RetryRecipientLine()
    {
        $input = input();
        if (!empty($input)) {
            $recipientLineId = $input['data']['recipientLineId'];
            if ($recipientLineId > 0) {
                $RecipientModel = new EDMRecipientModel();
                $result = $RecipientModel->getRecipientLineById($recipientLineId);
                if (!empty($result)) {
                    $status = $RecipientModel->updateRecipientLine(array('id' => $recipientLineId), array(
                        'IsRetry' => 1,
                        'status' => 1,
                        'Remark' => '',
                        'ServiceInfo' => '',
                    ));
                    if ($status > 0)
                        return json(['code' => 200, 'msg' => '正在重试，请稍后!']);
                    else
                        return json(['code' => 1001, 'msg' => '重试失败,清联系管理员!']);
                } else {
                    return json(['code' => 1001, 'msg' => 'recipientLineId数据不存在']);
                }
            } else {
                return json(['code' => 1001, 'msg' => '无效数据']);
            }
        }
    }
}
