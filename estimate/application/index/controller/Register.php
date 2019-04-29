<?php
namespace app\index\controller;

use think\Controller;
use think\Cache;
use app\index\model\user;
use redis\RedisPackage;
use app\index\model\login as lo;

class Register extends Controller{

    public function index()
    {
        return $this->fetch();
    }

    /**
     * 注册处理
     * @return mixed
     */
    public function reg(){
        $result = ['code'=>201,'data'=>'error'];
        if(request()->isPost()){
            $data=input('post.');
            $user_name = $data['user_name'];
            $mobile_phone = $data['mobile_phone'];
            //检查验证码
           /* $yzm = $this->checkYzm($data);

            $yzm_status = json_decode($yzm,true);
            if($yzm_status['status']!=1){
                echo json_encode(['code'=>215,'data'=>'验证码错误']);
                exit;
            }*/
            //检查手机号
            $check = $this->checkMobile($mobile_phone);
            if(!$check){
                echo json_encode(['code'=>211,'data'=>'手机号已注册，请直接登录']);
                exit;
            }
            $validate = validate('User');
            if(!$validate->check($data)){
                $result = ['code'=>202,'data'=>$validate->getError()];
                echo json_encode($result);
                exit;
            }
            $userData=array();
            $userData['user_name']=$user_name;
            $userData['mobile_phone']=$mobile_phone;
            $userData['city_id']=$data['city_id'];
            $userData['city_name']=$data['city_name'];
            $userData['station_id']=$data['station_id'];
            $userData['station']=$data['station'];
            $userData['station_number']=$data['station_number'];
            $userData['create_at']=time();
            $add=db('user')->insertGetId($userData);
            if($add){
                $result = ['code'=>200,'data'=>'success'];
                $redis= new RedisPackage();
                $redis::hMset("user:".$add,array('uid'=>$add,'user_name'=>$user_name,'mobile_phone'=>$mobile_phone,'city_id'=>$data['city_id'],'station_id'=>$data['station_id']));
            }else{
                $result = ['code'=>210,'data'=>'注册失败'];
            }
        }
        echo json_encode($result);exit;
    }

    /**
     * 判断手机号是否存在
     */
    public function checkMobile($mobile){
        $user =User::get(['mobile_phone'=>$mobile]);
        if($user){
            return false;
        }
        return true;
    }

    /**
     * 验证验证码
     */
    public function checkYzm($post){
        $url = 'http://zg99.offcn.com/index/duanxin/yzmsg?actid=91&callback=2';
        $post_data = ['phone'=>$post['mobile_phone'],'yzm'=>$post['yzm']];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        print_r($output);exit;
        var_dump(json_decode($output,true));exit;
        return $output;
    }
}
