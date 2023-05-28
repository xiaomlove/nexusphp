@extends('layui-page')

@push('css')
    <style>
        .form-comments {
            display: flex;
        }
        .form-comments .form{
            flex-basis: 60%;
        }
        .form-comments .comments{
            flex-basis: 40%;
            padding: 15px;
        }
        .form-comments .comments .comment{
            padding: 5px 10px;
            border: 1px solid #ccc;
            border-radius: 10px;
            cursor: pointer;
            display: inline-block;
            margin: 4px 0;
        }
        .layui-form-label {
            width: 120px;
        }
        .layui-input-block {
            margin-left: 150px;
        }
    </style>
@endpush
@section('content')
    <div class="form-comments">
        <form class="layui-form form" action="">
            @csrf
            <input type="hidden" name="torrent_id" value="{{ $torrent->id }}">
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('torrent.approval.status_label') }}</label>
                <div class="layui-input-block">
                    @foreach (\App\Models\Torrent::listApprovalStatus(true) as $status => $text)
                    <input type="radio" name="approval_status" value="{{ $status }}" title="{{ $text }}" @if($status == $torrent->approval_status) checked @endif>
                    @endforeach
                </div>
            </div>
            <div class="layui-form-item layui-form-text">
                <label class="layui-form-label">{{ __('torrent.approval.comment_label') }}</label>
                <div class="layui-input-block">
                    <textarea id="approval-comment" name="comment" placeholder="" class="layui-textarea"></textarea>
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="formDemo">{{ __('label.submit') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('label.reset') }}</button>
                </div>
            </div>
        </form>
        <div class="comments">
            @foreach($denyReasons as $reason)
            <span class="comment">{{ $reason->name }}</span>
            @endforeach
        </div>
    </div>
    <div style="text-align: center;margin-top: 20px;font-weight: 400">{{ __('torrent.approval.logs_label') }}</div>
    <table id="table"></table>
    <script>
        layui.use('table', function(){
            var table = layui.table;
            var util = layui.util;
            table.render({
                elem: '#table'
                ,size: 'sm' //小尺寸的表格
                // ,height: 312
                ,url: '/web/torrent-approval-logs?__format=layui-table&torrent_id={{ $torrent->id }}' //数据接口
                ,page: true //开启分页
                ,cols: [[ //表头
                    {field: 'id', title: 'ID', }
                    ,{field: 'username', title: '{{ __('label.username') }}', }
                    ,{field: 'action_type_text', title: '{{ __('label.action') }}', }
                    ,{field: 'comment', title: '{{ __('label.comment') }}',}
                    ,{field: 'created_at', title: '{{ __('label.created_at') }}'}
                ]]
            });
        });
        layui.use('form', function(){
            var form = layui.form;

            //监听提交
            form.on('submit(formDemo)', function(data){
                console.log(data)
                jQuery.post('/web/torrent-approval', data.field, function (response) {
                    if (response.ret != 0) {
                        layer.alert(response.msg)
                        return
                    }
                    parent.window.location.reload()
                }, 'json')
                return false;
            });
        });
        let approvalComment = jQuery('#approval-comment')
        jQuery('.comments').on("click", '.comment', function () {
            let text = jQuery(this).text()
            let oldText = approvalComment.val()
            approvalComment.val(oldText + text)
        })
    </script>
@endsection
