<?php
namespace app\index\model;

use think\Model;
use app\index\model\user;

Class Login extends model{

    public function login($mobile){
        $user = User::get(['mobile_phone'=>$mobile]);
        if($user){
            return $user;
        }else{
            return false;
        }
    }
}