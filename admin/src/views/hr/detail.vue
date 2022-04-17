<template>
    <div>
        <el-row>
            <el-col :span="12"  v-loading="loading">
                <el-card class="box-card">
                    <table class="table-base-info">
                        <tr>
                            <td>ID</td>
                            <td>{{formData.id}}</td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td>{{formData.status_text}}</td>
                        </tr>
                        <tr>
                            <td>UID</td>
                            <td>{{formData.uid}}</td>
                        </tr>
                        <tr>
                            <td>Username</td>
                            <td>{{formData.user && formData.user.username}}</td>
                        </tr>
                        <tr>
                            <td>Torrent ID</td>
                            <td>{{formData.torrent && formData.torrent.id}}</td>
                        </tr>
                        <tr>
                            <td>Torrent name</td>
                            <td>{{formData.torrent && formData.torrent.name}}</td>
                        </tr>
                        <tr>
                            <td>Uploaded</td>
                            <td>{{formData.snatch && formData.snatch.upload_text}}</td>
                        </tr>
                        <tr>
                            <td>Downloaded</td>
                            <td>{{formData.snatch && formData.snatch.download_text}}</td>
                        </tr>
                        <tr>
                            <td>Share ratio</td>
                            <td>{{formData.snatch && formData.snatch.share_ratio}}</td>
                        </tr>
                        <tr>
                            <td>Seed time required</td>
                            <td>{{formData.seed_time_required}}</td>
                        </tr>
                        <tr>
                            <td>Inspect time left</td>
                            <td>{{formData.inspect_time_left}}</td>
                        </tr>
                        <tr>
                            <td>Comment</td>
                            <td v-html="formData.comment"></td>
                        </tr>
                        <tr>
                            <td>Created at</td>
                            <td>{{formData.created_at}}</td>
                        </tr>
                        <tr>
                            <td>Updated at</td>
                            <td>{{formData.updated_at}}</td>
                        </tr>
                    </table>
                    <el-divider></el-divider>
                    <div style="text-align: center">
                        <el-popconfirm
                            title="Confirm Remove ?"
                            @confirm="handleDelete(formData.id)"
                        >
                            <template #reference>
                                <el-button type="danger">Remove</el-button>
                            </template>
                        </el-popconfirm>
                        <el-popconfirm
                            title="Confirm Pardon ?"
                            @confirm="handlePardon(formData.id)"
                            v-if="[1,3].includes(formData.status)"
                        >
                            <template #reference>
                                <el-button type="primary">Pardon</el-button>
                            </template>
                        </el-popconfirm>
                    </div>
                </el-card>
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
    name: 'HrDetail',
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const formRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            loading: false,
            id: id,
            agentAllows: [],
            formData: {

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
            if (id) {
                await fetchPageData()
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

        const fetchPageData = async () => {
            state.loading = true;
            let res = await api.getHr(id)
            state.loading = false
            state.formData = res.data
        }

        const getAgentAllow = async (id) => {
            let res = await api.getAgentAllow(id)
            console.log(res)
        }

        const handleDelete = async (id) => {
            let res = await api.deleteHr(id)
            ElMessage.success(res.msg)
            await router.push({name: 'hr'})
        }

        const handlePardon = async (id) => {
            let res = await api.pardonHr(id)
            ElMessage.success(res.msg)
            await fetchPageData()
        }

        return {
            ...toRefs(state),
            formRef,
            submitAdd,
            handleDelete,
            handlePardon
        }
    }
}
</script>

<style lang="scss" scoped>

.table-base-info {
    width: 100%;
    text-align: left;
    tr {
        td {
            padding: 4px;
        }
    }
}
</style>
