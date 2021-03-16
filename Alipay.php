<?php

class Alipay{

    public function __construct()
    {
        include 'vendor/alipay/aop/AopClient.php';
    }

    public function index(){
        //TODO 第一步，获取code
        //TODO 第二步，根据code获取token
        //TODO 第三步，根据token获取用户信息
        //TODO 第四步，根据
    }



    /**
     * 支付宝授权登录
     */
    public function aliLogin(){
        //获取配置文件的ali参数
        $ali_config = C("ALI_CONFIG");

        //应用的APPID
        $app_id = "2017061407485473";
        //【成功授权】后的回调地址
        $my_url = "http://".$_SERVER['HTTP_HOST']."/Home/User/aliLogin";

        //Step1：获取auth_code
        $auth_code = $_REQUEST["auth_code"];//存放auth_code
        if(empty($auth_code)){
            //state参数用于防止CSRF攻击，成功授权后回调时会原样带回
            $_SESSION['alipay_state'] = md5(uniqid(rand(), TRUE));
            //拼接请求授权的URL
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=".$app_id."&scope=auth_user&redirect_uri=".$my_url."&state="
                . $_SESSION['alipay_state'];

            echo("<script> top.location.href='" . $url . "'</script>");
        }
        //Step2: 使用auth_code换取apauth_token
        if($_REQUEST['state'] == $_SESSION['alipay_state'] || 1)
        {
            vendor("Alipay.AopClient"); //引入sdk
            $aop = new \AopClient();
            $aop->gatewayUrl           　 = "https://openapi.alipay.com/gateway.do";
            $aop->appId                 　　= $app_id;
            $aop->rsaPrivateKey           = $ali_config['RSA_PRIVATE_KEY'];　　//应用私钥
            $aop->alipayrsaPublicKey   = $ali_config['ALIPAY_RSA_PBULIC_KEY'];　　//支付宝公钥
            $aop->apiVersion            　 = '1.0';
            $aop->signType              　 = 'RSA2';
            $aop->postCharset             = 'utf-8';
            $aop->format                　   = 'json';

            //根据返回的auth_code换取access_token
            vendor("Alipay.AlipaySystemOauthTokenRequest");　　//调用sdk里面的AlipaySystemOauthTokenRequest类
            $request = new \AlipaySystemOauthTokenRequest();
            $request->setGrantType("authorization_code");
            $request->setCode($auth_code);
            $result = $aop->execute($request);
            $access_token = $result->alipay_system_oauth_token_response->access_token;

        //Step3: 用access_token获取用户信息
            vendor("Alipay.AlipayUserInfoShareRequest");　　//调用sdk里面的AlipayUserInfoShareRequest类
            $request = new \AlipayUserInfoShareRequest();
            $result = $aop->execute ( $request, $access_token);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                $user_data = $result->$responseNode;
                $m = M("Member");
                $data = array();
                $data['sex']              = $user_data->gender=='m'?1:2;
                $data['province']      = $user_data->province;
                $data['city']             = $user_data->city;
                $data['person_name']   = $user_data->nick_name;
                $data['ali_openid']    = $user_data->user_id;
                $data['ali_name']      = $user_data->nick_name;
                $data['ali_img']       = $user_data->avatar;
                $data['addtime']       = date("Y-m-d H:i:s", time());
                $data['person_img']       = $user_data->avatar;
                $data['signtime']       = date("Y-m-d H:i:s", time());

                $user = M("Member")->where(array("ali_openid"=> $user_data->user_id))->find();

                　　　　//判断是否是第一次登录
                if($user){
                    $res = M("Member")->where(array("ali_openid"=> $user_data->user_id))->setField("signtime", date("Y-m-d H:i:s", time()));
                    if($res){
                        　　//成功登录业务逻辑

                    }else{
                        $this->error("操作异常，拒绝访问！", U('user/login'));
                    }
                }else{
                    $res=$m->add($data);
                    if($res){
                        　　//成功登录业务逻辑


                    }else{
                        $this->error("操作异常，拒绝访问！", U('user/login'));
                    }
                }

            } else {
                $this->error("操作异常，拒绝访问！", U('user/login'));
            }

        }
    }
}