<template>
    <el-card class="">
        <template #header>
            <div class="nexus-table-header">
                <div class="left">
                    <el-form :inline="true" :model="query">
                        <el-form-item label="">
                            <el-select v-model="query.family_id" filterable placeholder="Family">
                                <el-option
                                    v-for="item in extraData.agentAllows"
                                    :key="item.id"
                                    :label="item.family"
                                    :value="item.id"
                                >
                                </el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchTableData">Query</el-button>
                            <el-button type="primary" @click="handleReset">Reset</el-button>
                        </el-form-item>
                    </el-form>
                </div>
                <div class="right">
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
                prop="family_id"
                label="Family"
                :formatter="formatColumnFamilyId"
            ></el-table-column>
            <el-table-column
                prop="name"
                label="Name"
            >
            </el-table-column>

            <el-table-column
                prop="peer_id"
                label="Peer id"
            >
            </el-table-column>
            <el-table-column
                prop="agent"
                label="Agent"
            ></el-table-column>

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
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import api from '../../utils/api'
import { useTable, renderTableData } from '../../utils/table'

export default {
    name: 'ClientTable',
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()
        let extraData = reactive({
            agentAllows: []
        });

        onMounted(() => {
            console.log('MedalTable onMounted')
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            await listAgentAllows()
            let res = await api.listAgentDeny(state.query)
            renderTableData(res, state)
            state.loading = false
        }
        const handleAdd = () => {
            router.push({ name: 'agent-deny-form' })
        }
        const handleEdit = (id) => {
            router.push({ path: '/agent-deny-form', query: { id } })
        }
        const handleDelete = async (id) => {
            let res = await api.deleteAgentDeny(id)
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

        const listAgentAllows = async () => {
            let res = await api.listAllAgentAllow()
            extraData.agentAllows = res.data
        }

        const handleReset = () => {
            state.query.family_id = '';
        }

        const formatColumnFamilyId = (row, column) => {
            return row.family.family
        }

        return {
            ...toRefs(state),
            extraData,
            multipleTable,
            handleSelectionChange,
            handleAdd,
            handleEdit,
            handleDelete,
            fetchTableData,
            changePage,
            handleReset,
            formatColumnFamilyId
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
