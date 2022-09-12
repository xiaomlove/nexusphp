<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="/styles/sprites.css">
    <link rel="stylesheet" href="/vendor/layui/css/layui.css">
    <link rel="stylesheet" href="/vendor/datatables-1.12.1/datatables.min.css">
    @stack('css')
    <script type="text/javascript" src="/js/jquery-1.12.4.min.js"></script>
    <script type="text/javascript" src="/vendor/layui/layui.js"></script>
    <script type="text/javascript" src="/vendor/datatables-1.12.1/datatables.min.js"></script>
    @stack('scripts')
    <script>
        var nexusDataTableConfig = {{ \Illuminate\Support\Js::from([
            'processing' => true,
            'serverSide' => true,
            'searching' => false,
            'ordering' => false,
            'columnDefs' => [
                [
                    'targets' => '_all',
                    'className' => 'dt-body-center dt-head-center'
                ]
            ],
            'language' => nexus_trans('pagination')
        ]) }};
    </script>
</head>
<body>
@yield('content')
</body>
</html>
