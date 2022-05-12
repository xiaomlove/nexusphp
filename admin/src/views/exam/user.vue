<template>
    <el-card>
        <template #header>
            <div class="nexus-table-header">
                <div class="left">
                    <el-form :inline="true" :model="query">
                        <el-form-item>
                            <el-popconfirm
                                title="Confirm Remove ?"
                                @confirm="handleDeleteBulk"
                            >
                                <template #reference>
                                    <el-button type="default">Remove</el-button>
                                </template>
                            </el-popconfirm>
                            <el-popconfirm
                                title="Confirm Avoid ?"
                                @confirm="handleAvoidBulk"
                            >
                                <template #reference>
                                    <el-button type="default">Avoid</el-button>
                                </template>
                            </el-popconfirm>
                        </el-form-item>
                        <el-form-item label="">
                            <el-select v-model="query.exam_id" filterable placeholder="Exam" clearable>
                                <el-option
                                    v-for="item in extraData.exams"
                                    :key="item.id"
                                    :label="item.name"
                                    :value="item.id"
                                >
                                </el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item label="">
                            <el-select v-model="query.is_done" filterable placeholder="IsDone" clearable>
                                <el-option label="Yes" value="1"></el-option>
                                <el-option label="No" value="0"></el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item label="">
                            <el-select v-model="query.status" filterable placeholder="Status" clearable>
                                <el-option label="Avoided" value="-1"></el-option>
                                <el-option label="Normal" value="0"></el-option>
                                <el-option label="Finished" value="1"></el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchTableData">Query</el-button>
                            <el-button type="primary" @click="handleReset">Reset</el-button>
                        </el-form-item>
                    </el-form>
                </div>
                <div class="right">
<!--                    <el-button type="primary" size="small" icon="el-icon-plus" @click="handleAdd">Add</el-button>-->
                </div>
            </div>
        </template>
        <el-table
            v-loading="loading"
            ref="multipleTable"
            :data="tableData"
            tooltip-effect="dark"
            @sort-change="handleSortChange"
            @selection-change="handleSelectionChange">

            <el-table-column
                type="selection"
                width="55">
            </el-table-column>

            <el-table-column
                prop="id"
                label="Id"
                width="100"
                sortable="custom"
            ></el-table-column>

            <el-table-column
                prop="exam_id"
                label="Exam"
                :formatter="formatColumnExam"
            ></el-table-column>

            <el-table-column
                prop="uid"
                label="User"
                :formatter="formatColumnUser"
            ></el-table-column>

            <el-table-column
                prop="is_done_text"
                label="Is done"
            ></el-table-column>

            <el-table-column
                prop="status_text"
                label="Status"
            ></el-table-column>

            <el-table-column
                prop="created_at"
                label="Created At"
            >
            </el-table-column>

            <el-table-column
                label="Action"
                width="100"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleDetail(scope.row.uid)">Detail</a>
<!--                    <el-popconfirm-->
<!--                        title="Confirm Delete ?"-->
<!--                        @confirm="handleDelete(scope.row.id)"-->
<!--                    >-->
<!--                        <template #reference>-->
<!--                            <a style="cursor: pointer">Delete</a>-->
<!--                        </template>-->
<!--                    </el-popconfirm>-->
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
import { useTable, renderTableData, resetTableSort } from '../../utils/table'

export default {
    name: 'ExamUserTable',
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()

        let extraData = reactive({
            exams: []
        });

        onMounted(() => {
            api.listExamAll().then(res => {
                extraData.exams = res.data
            })
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            let res = await api.listExamUser(state.query)
            renderTableData(res, state)
            state.loading = false
        }
        const handleAdd = () => {
            router.push({ name: 'user-form' })
        }
        const handleEdit = (id) => {
            router.push({ name: 'user-form', query: { id } })
        }
        const handleDelete = async (id) => {
            let res = await api.deleteExam(id)
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleAvoidBulk = async () => {
            let ids = state.multipleSelection.map(item => item.id)
            if (ids.length == 0) {
                ElMessage.error("No data selected !")
                return
            }
            console.log(ids)
            let res = await api.avoidExamUserBulk({id: ids})
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleDeleteBulk = async () => {
            let ids = state.multipleSelection.map(item => item.id)
            if (ids.length == 0) {
                ElMessage.error("No data selected !")
                return
            }
            console.log(ids)
            let res = await api.deleteExamUserBulk({id: ids})
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        // 选择项
        const handleSelectionChange = (val) => {
            state.multipleSelection = val
        }
        const changePage = (val) => {
            state.query.page = val
            fetchTableData()
        }
        const handleSortChange = (val) => {
            resetTableSort(val, state)
            fetchTableData()
        }
        const handleDetail = (id) => {
            router.push({
                name: 'user-detail',
                query: {id: id}
            })
        }

        const formatColumnUser = (row, column) => {
            return row.user.username
        }

        const formatColumnExam = (row, column) => {
            return row.exam.name
        }
        const formatColumnDownloaded = (row, column) => {
            return row.downloaded_text
        }

        const handleReset = () => {
            state.query.is_done = '';
            state.query.status = '';
            state.query.exam_id = '';
        }

        return {
            ...toRefs(state),
            multipleTable,
            extraData,
            handleSelectionChange,
            handleAdd,
            handleEdit,
            handleDelete,
            handleDetail,
            handleAvoidBulk,
            handleDeleteBulk,
            fetchTableData,
            changePage,
            handleSortChange,
            formatColumnUser,
            formatColumnExam,
            formatColumnDownloaded,
            handleReset
        }
    }
}
</script>

<style scoped>
.nexus-table-header {
    display: flex;
    justify-content: space-between;
}
</style>
