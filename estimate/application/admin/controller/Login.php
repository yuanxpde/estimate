<?php
namespace app\admin\controller;
use app\admin\model\login as lo;
use think\Controller;
use think\Session;

class Login extends Controller {

    /**
     * 登录页面
     * @return mixed
     */
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 登录处理
     * @return mixed
     */
    public function login(){
        if(request()->isPost()){
            $login = new lo;
            $status = $login->login(input('username'),input('password'));
            if($status == 1){
                if(!$this->check(input('code'))){
                    return $this->error('验证码错误！');
                }
                return $this->success('登录成功！','index/index');
            }elseif($status == 2){
                return $this->error('账号或密码错误！');
            }else{
                return $this->error('用户不存在！');
            }
        }
    }

    /**
     * 验证码验证
     * @param string $code
     * @return bool
     */
    public function check($code='')
    {
        if (!captcha_check($code)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 退出
     */
    public function logout(){
        Session::set('USER_INFO_SESSION',null);
        $this->redirect('admin/login/index');
    }

}
