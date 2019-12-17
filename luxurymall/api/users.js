import request from '@/plugins/axios'
//用户注册
export const customerRegister = (data) => {
    return request({
        url: '/cic/Customer/register',
        method:'post',
        data:data
    })
}
//用户验证码
export const customerCode = (data) => {
    return request({
        url: '/cic/Customer/sendSms',
        method:'post',
        data:data
    })
}
//用户登陆
export const customerLogin = (data) => {
    return request({
        url: '/cic/Customer/login',
        method:'post',
        data:data
    })
}
//校验手机账号是否存在
export const sendTelephone = (data) => {
    return request({
        url: '/cic/Customer/validateCustomer',
        method:'post',
        data:data
    })
}

//找回密码，设置新密码
export const setPassword = (data) => {
    return request({
        url: 'cic/Customer/passwordFind',
        method:'post',
        data:data
    })
}
