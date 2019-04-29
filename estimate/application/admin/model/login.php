<?php
namespace app\admin\model;

use think\Model;
use think\Session;
use app\admin\model\user;

Class Login extends model{

    public function login($username,$password){

        $user = User::get(['username'=>$username]);
        if($user){
            if($user['password'] == md5($password)){
                $data = [
                    'uid' => $user['id'],
                    'username'=>$username,
                ];
                // 存储用户信息
                Session::set('USER_INFO_SESSION',$data);
                return 1;
            }else{
                return 2;
            }
        }else{
            return 3;
        }
    }
}