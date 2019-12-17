import axios from 'axios'

import { Message, Notification } from 'element-ui'
const MODE = process.env.mode;
let BASE_URL = '';
if(MODE === 'local'){
    BASE_URL = '/ajaxurl';
}else if(MODE === 'test'){
    BASE_URL = '';
}else if(MODE === 'prod'){
    BASE_URL = ''
}

axios.defaults.headers['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.post['Content-Type'] = 'text/plain;charset=UTF-8'
let service = axios.create({
  baseURL: BASE_URL,
  timeout: 10000
})

 // 请求拦截 可在请求头中加入token等
service.interceptors.request.use(config => {

  return config
}, error => {
  return Promise.reject(error)
})

// 响应拦截 对响应消息作初步的处理
service.interceptors.response.use(resp => {
  if (resp.data) {
    if (resp.data.code !== '0') {
      // Message({
      //   type: 'error',
      //   message: resp.data.message,
      //   duration: 5000
      // })
    }
    return {data: resp.data}
  } else {
    return resp
  }
}, error => {
  if (error.response) {
    switch (error.response.states) {
      case 400: {
        if (error.response && error.response.data && error.response.message) {
          Notification.error({
            title: '400错误',
            message: error.response.data.message,
            duration: 5000,
            closable: true
          })
        }
        break
      }
    }
  }
})

export default service