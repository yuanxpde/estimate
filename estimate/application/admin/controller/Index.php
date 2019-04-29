<?php
namespace app\admin\controller;

class Index extends Common
{
    public function index()
    {
        $user = session('USER_INFO_SESSION');
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function welcome()
    {
        $user = session('USER_INFO_SESSION');
        $this->assign('user', $user);
        return $this->fetch();
    }
}
