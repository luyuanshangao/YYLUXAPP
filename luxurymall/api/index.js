import request from '@/plugins/axios'
//banner
export const getHomeHeader = (data) => {
    return request({
        url: '/app/index/getHeadrIndex',
        method:'post',
        data:data
    })
}
export const getHomeCenter = (data) => {
    return request({
        url: '/app/index/getCenterIndex',
        method:'post',
        data:data
    })
}