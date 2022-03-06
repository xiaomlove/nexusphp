<template>
    <el-card>
        <template #header>
            <div class="nexus-table-header">
                <div class="left">
                    <el-form :inline="true" :model="query">
                        <el-form-item label="">
                            <el-input placeholder="ID" v-model="query.id"></el-input>
                        </el-form-item>
                        <el-form-item label="">
                            <el-input placeholder="Username" v-model="query.username"></el-input>
                        </el-form-item>
                        <el-form-item label="">
                            <el-input placeholder="Email" v-model="query.email"></el-input>
                        </el-form-item>
                        <el-form-item label="">
                            <el-select v-model="query.class" filterable placeholder="Class">
                                <el-option
                                    v-for="(item, index) in extraData.classes"
                                    :key="index"
                                    :label="item"
                                    :value="index"
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
            @sort-change="handleSortChange"
            @selection-change="handleSelectionChange">

            <el-table-column
                type="selection"
                width="55">
            </el-table-column>

            <el-table-column
                prop="id"
                label="Id"
                width="80"
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
                width="200"
            ></el-table-column>

            <el-table-column
                prop="class"
                label="Class"
                sortable="custom"
                width="120"
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
                width="100"
            ></el-table-column>

            <el-table-column
                prop="enabled"
                label="Enabled"
                width="100"
            ></el-table-column>

            <el-table-column
                prop="last_access"
                label="Last access"
                width="150"
            ></el-table-column>

            <el-table-column
                prop="added"
                label="Added"
                width="150"
            ></el-table-column>

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

        let extraData = reactive({
            classes: []
        });

        onMounted(() => {
            api.listClass().then(function (res) {
                extraData.classes = res.data
            })
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

        const handleReset = () => {
            state.query.id = '';
            state.query.username = '';
            state.query.email = '';
            state.query.class = '';
            state.query.sort_field = '';
            state.query.sort_type = '';
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
            extraData,
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
            formatColumnDownloaded,
            handleReset
        }
    }
}
</script>

<style scoped>

</style>
