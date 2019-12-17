<template>
    <div class="main">
        <login-top :data-obj="loginTopData"></login-top>
        <div class="yellow-tips">{{"首次登陆将自动注册，注册则代表您同意"}}<nuxt-link to="">{{"用户协议"}}</nuxt-link></div>
        <div class="login-register-box">
            <div class="form-box" v-for="(item,key) in registUserInfo" :key="key">
                <input :type="item.type" :placeholder="item.placeholder" v-model="item.value" @keyup="validateForm(item,key)"/>
                <div class="error-tips" v-show="item.error">{{item.errorTip}}</div>
                <div class="code-span-box" v-if="item.name === 'code'">
                    <span class="code-span" @click="getCode" v-show="!codeTimeOut">{{"获取验证码"}}</span>
                    <span class="code-span gray-bg" v-show="codeTimeOut">{{timeOut}}{{'s'}}</span>
                </div>
            </div>
            <div class="mt10 btn block-btn btn-black" v-if="isAvailabBtn" @click="loginSubmit">{{"登陆"}}</div>
            <div class="mt10 btn block-btn btn-gray" v-else>{{"登陆"}}</div>
            <div class="mt10 tright"><nuxt-link to="/">{{"收不到验证码"}}</nuxt-link></div>
        </div>
        <ul class="login-type-list">
            <li><i class="iconfont icon-qq mr5 tmiddle"></i>QQ登陆</li>
            <li><i class="iconfont icon-weixin mr5 tmiddle"></i>微信登陆</li>
        </ul>
    </div>
</template>
<style src="../../assets/css/page/users/login-register.scss" lang="scss" scoped></style>
<script>
import loginTop from '~/components/loginTop.vue'
import GLOBAL from '~/assets/js/global.js'
import {customerLogin,customerCode} from '~/api/users'
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
            loginTopData:{title:'短信快捷登陆',link:'/users/login',text:''},
            registUserInfo:{
                phone: {name: "phone",value:'',type:'text',errorTip:"",placeholder:'手机号', error: false },
                code: {name: "code", value:'',type:'text',errorTip:"",placeholder:'验证码', error: false },
            }
        }
    },
    methods:{
        /**
         * 注册表单验证
         */
        validateForm (item,key){
            if(item.name === 'phone'){
                if(!GLOBAL.validation.isPhone(item.value) || GLOBAL.validation.isEmpty(this.registUserInfo.code.value)){
                    this.isAvailabBtn = false;
                    return false;
                }
            }else if(item.name === 'code'){
                if(GLOBAL.validation.isEmpty(item.value) || !GLOBAL.validation.isPhone(this.registUserInfo.phone.value)){
                    this.isAvailabBtn = false;
                    return false
                }
            }
            this.isAvailabBtn = true;
        },
        /**
         * 登陆
         */
        loginSubmit() {
            let registUserInfo = this.registUserInfo;
            let postData = {
                Telephone:registUserInfo.phone.value,
                Password:registUserInfo.code.value
            }
            customerLogin(postData)
            .then(data => {
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
            let vm = this;
            let postData = {
                telephone:vm.registUserInfo.phone.value,
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
                var _data = data.data;
                if(_data.code !== 200){
                    this.$toast({
                        message:_data.msg
                    });
                }
            })
        }
    }
}
</script>