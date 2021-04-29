<template>
    <el-card>
        <template #header>
            <div class="header">
                <el-button type="primary" size="small" icon="el-icon-plus" @click="handleAdd">Add</el-button>
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
                prop="username"
                label="Username"
                sortable="custom"
            ></el-table-column>

            <el-table-column
                prop="email"
                label="Email"
            ></el-table-column>

            <el-table-column
                prop="class"
                label="Class"
                sortable="custom"
                :formatter="formatColumnClass"
            ></el-table-column>

            <el-table-column
                prop="uploaded"
                label="Uploaded"
                sortable="custom"
                :formatter="formatColumnUploaded"
            ></el-table-column>

            <el-table-column
                prop="downloaded"
                label="Downloaded"
                sortable="custom"
                :formatter="formatColumnDownloaded"
            ></el-table-column>

            <el-table-column
                prop="bonus"
                label="Bonus"
            ></el-table-column>

            <el-table-column
                prop="status"
                label="Status"
            ></el-table-column>

            <el-table-column
                prop="added"
                label="Added"
            >
            </el-table-column>

            <el-table-column
                label="Action"
                width="100"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleDetail(scope.row.id)">Detail</a>
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
    name: 'UserTable',
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()

        onMounted(() => {
            console.log('UserTable onMounted');
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            let res = await api.listUser(state.query)
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

        const formatColumnClass = (row, column) => {
            return row.class_text
        }

        const formatColumnUploaded = (row, column) => {
            return row.uploaded_text
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
            formatColumnClass,
            formatColumnUploaded,
            formatColumnDownloaded
        }
    }
}
</script>

<style scoped>

</style>
