<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">
                    <el-form-item label="Name" prop="name">
                        <el-input v-model="formData.name" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Price" prop="price">
                        <el-input v-model="formData.price" placeholder="Seed bonus"></el-input>
                    </el-form-item>
                    <el-form-item label="Get type" prop="get_type">
                        <el-radio-group v-model="formData.get_type">
                            <el-radio :label="1">Exchange</el-radio>
                            <el-radio :label="2">Grant</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="Image large" prop="image_large">
                        <el-input v-model="formData.image_large" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Image small" prop="image_small">
                        <el-input v-model="formData.image_small" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Duration" prop="duration">
                        <el-input v-model="formData.duration" placeholder="Unit: day, if empty, it's valid forever"></el-input>
                    </el-form-item>

                    <el-form-item label="Description" prop="description">
                        <el-input type="textarea" v-model="formData.description" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="submitAdd()">Submit</el-button>
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
import api from "../../utils/api";

export default {
    name: 'MedalForm',
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const formRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            token: localGet('token') || '',
            id: id,
            allClasses: [],
            formData: {
                name: '',
                description: '',
                image_large: '',
                image_small: '',
                duration: '',
                price: '',
                get_type: ''
            },
            rules: {
                name: [
                    { required: 'true',  }
                ],
                price: [
                    { required: 'true', }
                ],
                image_large: [
                    { required: 'true', }
                ],
                image_small: [
                    { required: 'true', }
                ],
                description: [
                    { required: 'true', }
                ],
                get_type: [
                    {required: 'true'}
                ]
            },
        })
        onMounted( async () => {
            if (id) {
                api.getMedal(id).then(res => {
                    state.formData.name = res.data.name
                    state.formData.image_large = res.data.image_large
                    state.formData.image_small = res.data.image_small
                    state.formData.description = res.data.description
                    state.formData.price = res.data.price
                    state.formData.duration = res.data.duration
                    state.formData.get_type = res.data.get_type
                })
            }
        })
        onBeforeUnmount(() => {

        })
        const submitAdd = () => {
            formRef.value.validate(async (vaild) => {
                if (vaild) {
                    let params = state.formData;
                    console.log(params)
                    if (id) {
                        await api.updateMedal(id, params)
                    } else {
                        await api.storeMedal(params)
                    }
                    await router.push({name: 'medal'})
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

        const listAllClass = async () => {
            let res = await api.listClass()
            state.allClasses = res.data
        }
        const listAllIndex = async () => {
            let res = await api.listMedalIndex()
            state.formData.indexes = res.data
        }
        const getMedal = async (id) => {
            let res = await api.getMedal(id)
            console.log(res)
        }
        return {
            ...toRefs(state),
            formRef,
            submitAdd,
            handleBeforeUpload,
            handleUrlSuccess,
            handleChangeCate,
        }
    }
}
</script>

<style scoped>

</style>
