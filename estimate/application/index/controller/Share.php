<?php
namespace app\index\controller;

use app\index\model\Login as lo;
use think\Controller;
use app\index\model\user;
use think\Db;
use redis\RedisPackage;
use think\Log;

class Share extends Controller
{
    /**
     * 首页
     * @return mixed
     */
    public function index()
    {
        $this->assign('paper', ['paper_name'=>'']);
        return $this->fetch();
    }

    /**
     * 测试存redis缓存 - 取redis缓存使用
     * @param RedisPackage $redisPackage
     */
    public function redisTest(){
        $redis= new RedisPackage();

        $arr = $redis::ZRANGE("paper_11",1,200);
        //var_dump($data);exit;
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("title_".$value);
            $info[$title['title_number']] = $title;
        }
        var_dump($info);exit;
        $add = [1,2,3,4,5];
        foreach ($add as $k=>$value){
            $user_name = 'yuan';
            $mobile_phone = '15501053025';
            $data['city_id'] = 2;
            $data['station_id'] = 2;
            $redis::hMset("user:".$value,array('uid'=>$value,'user_name'=>$user_name,'mobile_phone'=>$mobile_phone,'city_id'=>$data['city_id'],'station_id'=>$data['station_id']));
            $redis::zAdd('user_sort',$value,$value);
        }

        $data = $redis::ZRANGE("user_sort",1,5);

        $info = [];
        foreach ($data as $v){
            $info[] = $redis::hGetAll("user:".$v);
        }
        var_dump($info);exit;
        //$redis::LPush('paper',$uid);
        //var_dump( $redis->lRange('paper', 0, -1) );
    }

    /**
     * 晒分
     * @return mixed
     */
    public function shareScore()
    {
        return $this->fetch();
    }


}
