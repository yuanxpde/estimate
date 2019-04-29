<?php
namespace app\index\controller;

use think\Controller;

use app\index\model\login as lo;
use think\Db;

class Login extends Controller{

    /**
     * 登录页面
     * @return mixed
     */
    public function index()
    {
        if(!empty($_COOKIE['paper'])){
            $paper_id = $_COOKIE['paper'];
        }else{
            $paper_id = '';
        }
        $this->assign('paper_id', $paper_id);
        //获取地市
        $city =Db::table('estimate_city')
            ->where('p_id',$paper_id)
            ->column('id,city_name,p_id','id');
        $this->assign('city', $city);
        return $this->fetch();
    }

    public function findNum($str=''){
        $str=trim($str);
        if(empty($str)){return '';}
        $result='';
        for($i=0;$i<strlen($str);$i++){
            if(is_numeric($str[$i])){
                $result.=$str[$i];
            }
        }
        return $result;
    }

    /**
     * 登录处理
     * @return mixed
     */
    public function login(){
        if(request()->isPost()) {
            $login = new lo;
            $status = $login->login(input('mobile_phone'));
            if ($status) {
                setcookie("user_id", $status['id'],time()+3600*24,'/');
                echo json_encode(['code' => 200, 'data' => 'success']);
                exit;
            } else {
                echo json_encode(['code' => 210, 'data' => '手机号不存在']);
                exit;
            }
        }
    }

    /**
     * 退出
     */
    public function logout(){
        session("user",null);
        $this->redirect('index/login/index');
    }

}
