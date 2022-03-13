<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">

                    <el-form-item label="Username" prop="username">
                        <el-input v-model="formData.username" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Email" prop="email">
                        <el-input v-model="formData.email" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Password" prop="password">
                        <el-input type="password" v-model="formData.password" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Password Confirmation" prop="password_confirmation">
                        <el-input type="password" v-model="formData.password_confirmation" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="UID" prop="id">
                        <el-input v-model="formData.id" type="number" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Class" prop="class">
                        <el-select v-model="formData.class" filterable clearable>
                            <el-option
                                v-for="(item, index) in userClasses"
                                :key="index"
                                :label="item"
                                :value="index"
                            >
                            </el-option>
                        </el-select>
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
    name: 'UserForm',
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const formRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            id: id,
            userClasses: [],
            formData: {
                username: '',
                email: '',
                password: '',
                password_confirmation: '',
                id: '',
                class: ''
            },
            rules: {
                username: [
                    { required: 'true'}
                ],
                email: [
                    { required: 'true', type: 'email'}
                ],
                password: [
                    { required: 'true', min: 6, max: 40}
                ],
                password_confirmation: [
                    { required: 'true', min: 6, max: 40}
                ],
            },
        })
        onMounted( async () => {
            await listAllClass()
        })
        onBeforeUnmount(() => {

        })
        const submitAdd = () => {
            formRef.value.validate(async (vaild) => {
                if (vaild) {
                    let params = state.formData;
                    await api.storeUser(params)
                    await router.push({name: 'user'})
                }
            })
        }

        const listAllClass = async () => {
            let res = await api.listClass()
            state.userClasses = res.data
        }

        return {
            ...toRefs(state),
            formRef,
            submitAdd,
        }
    }
}
</script>

<style scoped>

</style>
