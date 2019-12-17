<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class Img extends Model{
    protected $connection = 'db_admin';
    protected $table='dx_img';
}
