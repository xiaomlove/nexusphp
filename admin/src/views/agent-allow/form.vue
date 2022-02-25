<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">
                    <el-form-item label="Family" prop="family">
                        <el-input v-model="formData.family" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Start name" prop="start_name">
                        <el-input v-model="formData.start_name" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item label="Peer id start" prop="peer_id_start">
                        <el-input v-model="formData.peer_id_start" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Peer id pattern" prop="peer_id_pattern">
                        <el-input v-model="formData.peer_id_pattern" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Peer id match num" prop="peer_id_match_num">
                        <el-input v-model="formData.peer_id_match_num" placeholder="" type="number"></el-input>
                    </el-form-item>
                    <el-form-item label="Peer id match type" prop="peer_id_matchtype">
                        <el-radio-group v-model="formData.peer_id_matchtype">
                            <el-radio label="dec">dec</el-radio>
                            <el-radio label="hex">hex</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item label="Agent start" prop="agent_start">
                        <el-input v-model="formData.agent_start" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Agent pattern" prop="agent_pattern">
                        <el-input v-model="formData.agent_pattern" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Agent match num" prop="agent_match_num">
                        <el-input v-model="formData.agent_match_num" placeholder="" type="number"></el-input>
                    </el-form-item>
                    <el-form-item label="Agent match type" prop="agent_matchtype">
                        <el-radio-group v-model="formData.agent_matchtype">
                            <el-radio label="dec">dec</el-radio>
                            <el-radio label="hex">hex</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item label="Exception" prop="exception">
                        <el-radio-group v-model="formData.exception">
                            <el-radio label="yes">Yes</el-radio>
                            <el-radio label="no">No</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="Allow https" prop="allowhttps">
                        <el-radio-group v-model="formData.allowhttps">
                            <el-radio label="yes">Yes</el-radio>
                            <el-radio label="no">No</el-radio>
                        </el-radio-group>
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
            allClasses: [],
            formData: {
                family: '',
                start_name: '',
                peer_id_pattern: '',
                peer_id_match_num: '',
                peer_id_matchtype: '',
                peer_id_start: '',
                agent_pattern: '',
                agent_match_num: '',
                agent_matchtype: '',
                agent_start: '',
                exception: '',
                allowhttps: '',
                comment: '',
            },
            rules: {
                family: [
                    { required: 'true',  }
                ],
                start_name: [
                    { required: 'true', }
                ],
                peer_id_pattern: [
                    { required: 'true', }
                ],
                peer_id_match_num: [
                    { required: 'true', }
                ],
                peer_id_matchtype: [
                    { required: 'true', }
                ],
                peer_id_start: [
                    {required: 'true'}
                ],
                agent_pattern: [
                    {required: 'true'}
                ],
                agent_match_num: [
                    {required: 'true'}
                ],
                agent_matchtype: [
                    {required: 'true'}
                ],
                agent_start: [
                    {required: 'true'}
                ],
                exception: [
                    {required: 'true'}
                ],
                allowhttps: [
                    {required: 'true'}
                ],
            },
        })
        onMounted( async () => {
            if (id) {
                api.getAgentAllow(id).then(res => {
                    state.formData.family = res.data.family
                    state.formData.start_name = res.data.start_name

                    state.formData.peer_id_pattern = res.data.peer_id_pattern
                    state.formData.peer_id_match_num = res.data.peer_id_match_num
                    state.formData.peer_id_matchtype = res.data.peer_id_matchtype
                    state.formData.peer_id_start = res.data.peer_id_start

                    state.formData.agent_pattern = res.data.agent_pattern
                    state.formData.agent_match_num = res.data.agent_match_num
                    state.formData.agent_matchtype = res.data.agent_matchtype
                    state.formData.agent_start = res.data.agent_start

                    state.formData.exception = res.data.exception
                    state.formData.allowhttps = res.data.allowhttps
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
                        await api.updateAgentAllow(id, params)
                    } else {
                        await api.storeAgentAllow(params)
                    }
                    await router.push({name: 'agent-allow'})
                }
            })
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
