<template>
    <div class="main">
        <login-top :data-obj="loginTopData"></login-top>
        <div class="login-register-box">
            <div class="form-box" v-for="(item,key) in registUserInfo" :key="key">
                <input :type="item.type" :placeholder="item.placeholder" v-model="item.value" @keyup="validateForm(item,key)"/>
                <div class="error-tips" v-show="item.error">{{item.errorTip}}</div>
                <div class="code-span-box" v-if="item.name === 'code'">
                    <span class="code-span" @click="getCode" v-show="!codeTimeOut">{{"获取验证码"}}</span>
                    <span class="code-span gray-bg" v-show="codeTimeOut">{{timeOut}}{{'s'}}</span>
                </div>
            </div>
            <div class="gray mt10">
                {{"密码必须包含字母（区分大小写）、数字、6~20位字符"}}
            </div>
            <div class="mt10 btn block-btn btn-black" v-if="isAvailabBtn" @click="loginSubmit">{{"登陆"}}</div>
            <div class="mt10 btn block-btn btn-gray" v-else>{{"登陆"}}</div>
            <div class="mt10 tright"><nuxt-link to="/">{{"收不到验证码"}}</nuxt-link></div>
        </div>
        <div class="user-agree fixted-bottom">
            {{"点击注册意味着您同意"}}
            <nuxt-link to="/">{{"(用户协议)"}}</nuxt-link>
        </div>
    </div>
</template>
<style src="../../../assets/css/page/users/login-register.scss" lang="scss" scoped></style>
<script>
 
import loginTop from '~/components/loginTop.vue'
import GLOBAL from '~/assets/js/global.js'
import {setPassword,customerCode} from '~/api/users'
import cookie from '~/assets/js/cookie'
export default {
    components:{
        loginTop
    },
    data (){
        return {
            timeOut:60,
            codeTimeOut:false, //获取验证码倒计时
            isAvailabBtn:false, //注册按钮是否可用
            loginTopData:{title:'找回密码',link:'',text:''},
            registUserInfo:{
                code: {name: "code", value:'',type:'text',errorTip:"",placeholder:'验证码', error: false },
                password:{name: "password",value:'',type:'password', placeholder:'密码',errorTip:"", error: false },
            }
        }
    },
    methods:{
        /**
         * 注册表单验证
         */
        validateForm (item,key){
           if(item.name === 'code'){
                if(GLOBAL.validation.isEmpty(item.value) || !GLOBAL.validation.isRegistPassword(this.registUserInfo.password.value)){
                    this.isAvailabBtn = false;
                    return false
                }
            }else if(item.name === 'password'){
                if(!GLOBAL.validation.isRegistPassword(item.value) || GLOBAL.validation.isEmpty(this.registUserInfo.code.value)){
                    this.isAvailabBtn = false;
                    return false
                }
            }
            this.isAvailabBtn = true;
        },
        /**
         * 找回密码提交
         */
        loginSubmit() {
            let registUserInfo = this.registUserInfo;
            let postData = {
                Telephone:this.$route.query ? this.$route.query.telephone:'',
                validateCode:registUserInfo.code.value,
                Password:registUserInfo.password.value
            }
            setPassword(postData)
            .then((data) => {
                let _data = data.data;
                if(_data.code == 200){
                    cookie.set('token',_data.data.token,168);//168是按照小时算 7天是168个小时
                    this.$router.push('/');
                }else{
                    this.$toast({
                        message:_data.msg
                    });
                }
            });
        },
        /**
         * 获取验证码
         */
        getCode (){
            this.codeTimeOut = true;
            let postData = {
                telephone:this.$route.query ? this.$route.query.telephone:'',
            }
            this.timeOut = 60;
            let _interval = setInterval(() =>{
                vm.timeOut--;
                if (vm.timeOut === 0){
                    vm.codeTimeOut = false;
                    clearInterval(_interval);
                }
            },1000);
            customerCode(postData)
            .then((data)=>{

            })
        }
    }
}
</script>