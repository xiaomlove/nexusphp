<template>
    <el-card class="">
        <template #header>
            <div class="nexus-table-header">
                <div class="left">
                    <el-form :inline="true" :model="query">
                        <el-form-item label="">
                            <el-input placeholder="Family" v-model="query.family"></el-input>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchTableData">Query</el-button>
                            <el-button type="primary" @click="handleReset">Reset</el-button>
                        </el-form-item>
                    </el-form>
                </div>
                <div class="right">
                    <el-button type="primary" icon="Check" @click="handleCheck">Check</el-button>
                    <el-button type="primary" icon="Plus" @click="handleAdd">Add</el-button>
                </div>
            </div>
        </template>
        <el-table
            v-loading="loading"
            ref="multipleTable"
            :data="tableData"
            tooltip-effect="dark"
            @selection-change="handleSelectionChange">
            <el-table-column
                type="selection"
                width="55">
            </el-table-column>
            <el-table-column
                prop="id"
                label="Id"
                width="50"
            >
            </el-table-column>
            <el-table-column
                prop="family"
                label="Family"
            ></el-table-column>
            <el-table-column
                prop="start_name"
                label="Start name"
            >
            </el-table-column>

            <el-table-column
                prop="peer_id_start"
                label="Peer id start"
            >
            </el-table-column>
            <el-table-column
                prop="peer_id_pattern"
                label="Peer id pattern"
            ></el-table-column>
<!--            <el-table-column-->
<!--                prop="peer_id_match_num"-->
<!--                label="Peer id match num"-->
<!--            ></el-table-column>-->
<!--            <el-table-column-->
<!--                prop="peer_id_matchtype"-->
<!--                label="Peer id match type"-->
<!--            ></el-table-column>-->

            <el-table-column
                prop="agent_start"
                label="Agent start"
            >
            </el-table-column>
            <el-table-column
                prop="agent_pattern"
                label="Agent pattern"
            ></el-table-column>
<!--            <el-table-column-->
<!--                prop="agent_match_num"-->
<!--                label="Agent match num"-->
<!--            ></el-table-column>-->
<!--            <el-table-column-->
<!--                prop="agent_matchtype"-->
<!--                label="Agent match type"-->
<!--            ></el-table-column>-->



            <el-table-column
                label="Action"
                width="120"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleEdit(scope.row.id)">Edit</a>
                    <el-popconfirm
                        title="Confirm Delete ?"
                        @confirm="handleDelete(scope.row.id)"
                    >
                        <template #reference>
                            <a style="cursor: pointer">Delete</a>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>
        <!--总数超过一页，再展示分页器-->
        <el-pagination
            background
            layout="prev, pager, next"
            :total="total"
            :page-size="perPage"
            :current-page="currentPage"
            @current-change="changePage"
        />
    </el-card>
    <DialogCheck ref="refDialogCheck" />
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import api from '../../utils/api'
import { useTable, renderTableData } from '../../utils/table'
import DialogCheck from "../agent-allow/dialog-check.vue"


export default {
    name: 'ClientTable',
    components: {
        DialogCheck,
    },
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()
        const refDialogCheck = ref(null)

        onMounted(() => {
            console.log('MedalTable onMounted')
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            let res = await api.listAgentAllow(state.query)
            renderTableData(res, state)
            state.loading = false
        }
        const handleAdd = () => {
            router.push({ name: 'agent-allow-form' })
        }
        const handleEdit = (id) => {
            router.push({ path: '/agent-allow-form', query: { id } })
        }
        const handleDelete = async (id) => {
            let res = await api.deleteAgentAllow(id)
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleSelectionChange = (val) => {
            state.multipleSelection = val
        }
        const changePage = (val) => {
            state.query.page = val
            fetchTableData()
        }
        const handleReset = () => {
            state.query.family = '';
        }

        const handleCheck = () => {
            refDialogCheck.value.open()
        }

        return {
            ...toRefs(state),
            multipleTable,
            handleSelectionChange,
            handleAdd,
            handleEdit,
            handleDelete,
            fetchTableData,
            changePage,
            handleReset,
            handleCheck,
            refDialogCheck
        }
    }
}
</script>

<style scoped>
.swiper-container {
    min-height: 100%;
}
.el-card.is-always-shadow {
    min-height: 100%!important;
}
</style>
