<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">
                    <el-form-item label="Family" prop="family_id">
                        <el-select v-model="formData.family_id" filterable>
                            <el-option
                                v-for="item in agentAllows"
                                :key="item.id"
                                :label="item.family"
                                :value="item.id"
                            >
                            </el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item label="Name" prop="name">
                        <el-input v-model="formData.name" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Peer id" prop="peer_id">
                        <el-input v-model="formData.peer_id" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Agent" prop="agent">
                        <el-input v-model="formData.agent" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Comment" prop="comment">
                        <el-input type="textarea" v-model="formData.comment" placeholder=""></el-input>
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
    name: 'AgentAllowForm',
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
            agentAllows: [],
            formData: {
                family_id: '',
                name: '',
                peer_id: '',
                agent: '',
                comment: '',
            },
            rules: {
                family_id: [
                    { required: 'true',  }
                ],
                name: [
                    { required: 'true', }
                ],
                peer_id: [
                    { required: 'true', }
                ],
                agent: [
                    { required: 'true', }
                ],
            },
        })
        onMounted( async () => {
            await listAgentAllows()
            if (id) {
                api.getAgentDeny(id).then(res => {
                    state.formData.family_id = res.data.family_id
                    state.formData.name = res.data.name

                    state.formData.peer_id = res.data.peer_id
                    state.formData.agent = res.data.agent
                    state.formData.comment = res.data.comment
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
                        await api.updateAgentDeny(id, params)
                    } else {
                        await api.storeAgentDeny(params)
                    }
                    await router.push({name: 'agent-deny'})
                }
            })
        }

        const listAgentAllows = async () => {
            let res = await api.listAllAgentAllow()
            state.agentAllows = res.data
        }

        const getAgentAllow = async (id) => {
            let res = await api.getAgentAllow(id)
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
