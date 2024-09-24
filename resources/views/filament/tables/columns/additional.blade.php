<div style="width: 200%; word-break: break-word; white-space: normal;">
    <a href="https://xt-xarid.uz/procedure/{{ $getRecord()->proc_id }}/core" 
       target="_blank"
       rel="noreferrer noopener"
       style="font-size: 0.875rem; text-decoration: underline; color: #3B82F6;">
        {!! nl2br(e($getRecord()['fields']['desc']['value'] ?? 'No description available')) !!}
    </a>
</div>
