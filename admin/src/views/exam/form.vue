<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="goodRef" label-width="100px" class="formData">
                    <el-form-item label="" prop="name">
                        <el-input v-model="formData.name" placeholder="请输入商品名称"></el-input>
                    </el-form-item>
                    <el-form-item label="商品简介" prop="description">
                        <el-input type="textarea" v-model="formData.description" placeholder="请输入商品简介(100字)"></el-input>
                    </el-form-item>
                    <el-form-item label="商品价格" prop="begin">
                        <el-input type="number" min="0" v-model="formData.begin" placeholder="请输入商品价格"></el-input>
                    </el-form-item>
                    <el-form-item label="商品售卖价" prop="end">
                        <el-input type="number" min="0" v-model="formData.end" placeholder="请输入商品售价"></el-input>
                    </el-form-item>
                    <el-form-item label="商品库存" prop="requires">
                        <el-input type="number" min="0" v-model="formData.requires" placeholder="请输入商品库存"></el-input>
                    </el-form-item>
                    <el-form-item label="商品标签" prop="filters">
                        <el-input v-model="formData.filters" placeholder="请输入商品小标签"></el-input>
                    </el-form-item>
                    <el-form-item label="上架状态" prop="status">
                        <el-radio-group v-model="formData.status">
                            <el-radio label="0">启用</el-radio>
                            <el-radio label="1">禁用</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="submitAdd()">{{ id ? '立即修改' : '立即创建' }}</el-button>
                    </el-form-item>
                </el-form>
            </el-col>
        </el-row>
    </div>
</template>

<script>
import { reactive, ref, toRefs, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { localGet } from '../../utils'
export default {
    name: 'AddGood',
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const goodRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            token: localGet('token') || '',
            id: id,
            defaultCate: '',
            formData: {
                name: '',
                description: '',
                begin: '',
                end: '',
                requires: {},
                filters: {},
                status: '',
            },
            rules: {
                goodsName: [
                    { required: 'true',  }
                ],
                originalPrice: [
                    { required: 'true', }
                ],
                sellingPrice: [
                    { required: 'true',}
                ],
                stockNum: [
                    { required: 'true',  }
                ],
            },
        })
        let instance
        onMounted(() => {

        })
        onBeforeUnmount(() => {

        })
        const submitAdd = () => {
            goodRef.value.validate((vaild) => {
                if (vaild) {
                    // 默认新增用 post 方法
                    let httpOption = axios.post
                    let params = {
                        goodsCategoryId: state.categoryId,
                        goodsCoverImg: state.formData.goodsCoverImg,
                        goodsDetailContent: instance.txt.html(),
                        goodsIntro: state.formData.goodsIntro,
                        goodsName: state.formData.goodsName,
                        goodsSellStatus: state.formData.goodsSellStatus,
                        originalPrice: state.formData.originalPrice,
                        sellingPrice: state.formData.sellingPrice,
                        stockNum: state.formData.stockNum,
                        tag: state.formData.tag
                    }
                    if (hasEmoji(params.goodsIntro) || hasEmoji(params.goodsName) || hasEmoji(params.tag) || hasEmoji(params.goodsDetailContent)) {
                        ElMessage.error('不要输入表情包，再输入就打死你个龟孙儿~')
                        return
                    }
                    console.log('params', params)
                    if (id) {
                        params.goodsId = id
                        // 修改商品使用 put 方法
                        httpOption = axios.put
                    }
                    httpOption('/goods', params).then(() => {
                        ElMessage.success(id ? '修改成功' : '添加成功')
                        router.push({ path: '/good' })
                    })
                }
            })
        }
        const handleBeforeUpload = (file) => {
            const sufix = file.name.split('.')[1] || ''
            if (!['jpg', 'jpeg', 'png'].includes(sufix)) {
                ElMessage.error('请上传 jpg、jpeg、png 格式的图片')
                return false
            }
        }
        const handleUrlSuccess = (val) => {
            state.formData.goodsCoverImg = val.data || ''
        }
        const handleChangeCate = (val) => {
            state.categoryId = val[2] || 0
        }
        return {
            ...toRefs(state),
            goodRef,
            submitAdd,
            handleBeforeUpload,
            handleUrlSuccess,
            handleChangeCate
        }
    }
}
</script>

<style scoped>

</style>
