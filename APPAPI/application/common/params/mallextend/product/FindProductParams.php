<?php
namespace app\common\params\mallextend\product;

class FindProductParams
{
    public $id;
    public $Title;
    public $BrandName;
    public $UserName;
    public $ProductStatus;
    public $startTime;
    public $endTime;
    public $first_level;
    public $path;
    public $page;
    public $page_size;
    public $Code;
    public $BrandId;
    public $UserId;
    public $StoreID;
    public $StoreName;
    public $GroupId;
    public $FirstCategory;
    public $SecondCategory;
    public $ThirdCategory;
    public $FourthCategory;
    public $FifthCategory;
    public $activityFlag;
    public $IsAffiliate;
    public $CommissionOrder;
    public $SalesCountsOrder;

    public function rules()
    {
        return[
        ];
    }
}