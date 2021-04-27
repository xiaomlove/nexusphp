<template>
    <el-card>
        <template #header>
            <div class="nexus-table-header">
                <div class="left">

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
                width="60"
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

        onMounted(() => {
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
        return {
            ...toRefs(state),
            multipleTable,
            handleSelectionChange,
            handleAdd,
            handleEdit,
            handleDelete,
            handleDetail,
            fetchTableData,
            changePage,
            handleSortChange,
            formatColumnUser,
            formatColumnExam,
            formatColumnDownloaded
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
