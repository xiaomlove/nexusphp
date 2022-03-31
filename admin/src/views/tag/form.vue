<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">
                    <el-form-item label="Name" prop="name">
                        <el-input v-model="formData.name" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Background color" prop="color">
                        <el-input v-model="formData.color" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Font color" prop="font_color">
                        <el-input v-model="formData.font_color" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Font size" prop="font_size">
                        <el-input v-model="formData.font_size" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Padding" prop="padding">
                        <el-input v-model="formData.padding" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Margin" prop="margin">
                        <el-input v-model="formData.margin" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Border radius" prop="border_radius">
                        <el-input v-model="formData.border_radius" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Priority" prop="priority">
                        <el-input v-model="formData.priority" placeholder="The higher the value, the higher the ranking"></el-input>
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
    name: 'TagForm',
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
            list: [],
            formData: {
                color: '',
                name: '',
                priority: '',
                font_color: '#ffffff',
                font_size: '12px',
                margin: 0,
                padding: 0,
                border_radius: 0,
            },
            rules: {
                color: [
                    { required: 'true',  }
                ],
                name: [
                    { required: 'true', }
                ],
            },
        })
        onMounted( async () => {
            if (id) {
                api.getTag(id).then(res => {
                    state.formData.name = res.data.name
                    state.formData.color = res.data.color
                    state.formData.priority = res.data.priority
                    state.formData.font_color = res.data.font_color
                    state.formData.font_size = res.data.font_size
                    state.formData.margin = res.data.margin
                    state.formData.padding = res.data.padding
                    state.formData.border_radius = res.data.border_radius

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
                        await api.updateTag(id, params)
                    } else {
                        await api.storeTag(params)
                    }
                    await router.push({name: 'tag'})
                }
            })
        }

        const listTag = async () => {
            let res = await api.listTag()
            state.list = res.data
        }

        const getTag = async (id) => {
            let res = await api.getTag(id)
            console.log(res)
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
