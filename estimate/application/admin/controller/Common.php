<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

namespace app\admin\controller;
use think\Controller;
use think\Request;
use think\Db;

class Common extends Controller
{
    public $user;

    //检查是否登录
    public function _initialize()
    {
        $this->user = session('USER_INFO_SESSION');
        if (!$this->user['username'] || !$this->user['uid']) {
            $this->error('请先登录！', url('/admin/login/index'));
        }
    }
}