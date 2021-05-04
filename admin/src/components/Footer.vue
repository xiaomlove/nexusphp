<template>
    <div class="footer">
        <div class="left">Powered by <a target="_blank" href="https://nexusphp.org/">NexusPHP</a></div>
        <div class="right">
            Version: 1.6.0-beta5
        </div>
    </div>
</template>

<script>
export default {
    name: 'Footer'
}
</script>

<style scoped>
.footer {
    height: 50px;
    border-top: 1px solid #e9e9e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}
</style>
