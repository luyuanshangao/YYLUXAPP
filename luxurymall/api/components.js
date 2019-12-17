import request from '@/plugins/axios'
//产品数据
export const getProductList = (url,data) => {
    return request({
        url:url,
        method:'post',
        data:data
    })
}